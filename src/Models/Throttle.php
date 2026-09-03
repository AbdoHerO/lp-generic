<?php
/**
 * Throttle — a shared rate limiter for anything that can be hammered.
 *
 * One table, one bucket string per thing being limited:
 *   login:{username}:{ip}   admin sign-in attempts
 *   lead:{ip}               order submissions
 *
 * Rows are written on every attempt and read back over a time window, so the
 * limit survives a restart and applies across processes — which a session or an
 * APCu counter would not. Old rows are pruned opportunistically rather than by
 * cron, because this application has no scheduler of its own.
 */
class Throttle {
    /** Rows older than this are meaningless to every caller and get deleted. */
    private const RETENTION_SECONDS = 86400;

    /** Record one attempt. */
    public static function hit(string $bucket, ?string $ip = null): void {
        try {
            db()->prepare("INSERT INTO throttle_hits (bucket, ip) VALUES (:b, :i)")
                ->execute([':b' => mb_substr($bucket, 0, 64), ':i' => $ip ?? self::ip()]);
            self::pruneOccasionally();
        } catch (Throwable $e) {
            // A limiter that breaks the feature it protects is worse than no
            // limiter. Log and let the request through.
            error_log('Throttle::hit failed: ' . $e->getMessage());
        }
    }

    /** How many attempts landed in this bucket within the last $seconds. */
    public static function count(string $bucket, int $seconds): int {
        try {
            $st = db()->prepare("SELECT COUNT(*) FROM throttle_hits
                                 WHERE bucket = :b AND created_at >= (NOW() - INTERVAL :s SECOND)");
            $st->bindValue(':b', mb_substr($bucket, 0, 64));
            $st->bindValue(':s', $seconds, PDO::PARAM_INT);
            $st->execute();
            return (int)$st->fetchColumn();
        } catch (Throwable $e) {
            error_log('Throttle::count failed: ' . $e->getMessage());
            return 0;
        }
    }

    /** True once the bucket has reached its limit for the window. */
    public static function tooMany(string $bucket, int $max, int $seconds): bool {
        return self::count($bucket, $seconds) >= $max;
    }

    /**
     * Seconds until the oldest attempt in the window ages out — i.e. how long
     * the caller has to wait before the next attempt is allowed.
     */
    public static function retryAfter(string $bucket, int $seconds): int {
        try {
            $st = db()->prepare("SELECT TIMESTAMPDIFF(SECOND, NOW(), created_at + INTERVAL :s SECOND)
                                 FROM throttle_hits
                                 WHERE bucket = :b AND created_at >= (NOW() - INTERVAL :s2 SECOND)
                                 ORDER BY created_at ASC LIMIT 1");
            $st->bindValue(':b', mb_substr($bucket, 0, 64));
            $st->bindValue(':s', $seconds, PDO::PARAM_INT);
            $st->bindValue(':s2', $seconds, PDO::PARAM_INT);
            $st->execute();
            return max(0, (int)$st->fetchColumn());
        } catch (Throwable $e) {
            return $seconds;
        }
    }

    /** Forget a bucket — called after a successful sign-in. */
    public static function clear(string $bucket): void {
        try {
            db()->prepare("DELETE FROM throttle_hits WHERE bucket = :b")
                ->execute([':b' => mb_substr($bucket, 0, 64)]);
        } catch (Throwable $e) {
            error_log('Throttle::clear failed: ' . $e->getMessage());
        }
    }

    /**
     * The visitor's address.
     *
     * REMOTE_ADDR only. Behind the production Nginx, mod_remoteip has already
     * rewritten it from X-Forwarded-For; reading that header here as well would
     * let anyone reset their own rate limit by sending a different value.
     */
    public static function ip(): string {
        return substr((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 64);
    }

    /** Deletes expired rows on roughly 1 request in 50. */
    private static function pruneOccasionally(): void {
        if (random_int(1, 50) !== 1) return;
        try {
            db()->exec("DELETE FROM throttle_hits
                        WHERE created_at < (NOW() - INTERVAL " . self::RETENTION_SECONDS . " SECOND)");
        } catch (Throwable $e) {
            error_log('Throttle prune failed: ' . $e->getMessage());
        }
    }
}
