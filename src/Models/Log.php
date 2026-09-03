<?php
/**
 * Log — application logging with a level and context.
 *
 * Before this, failures either went to error_log (wherever PHP happened to
 * point that) or were swallowed outright — the SheetDB sync's `catch { }` being
 * the worst example: orders could stop reaching the sheet for a week with
 * nothing anywhere to say so.
 *
 * Writes newline-delimited JSON to storage/logs/app-YYYY-MM-DD.log, which is
 * greppable by hand and parseable by anything. Falls back to error_log when the
 * directory is not writable, so logging never becomes the failure.
 */
class Log {
    public const DEBUG   = 'debug';
    public const INFO    = 'info';
    public const WARNING = 'warning';
    public const ERROR   = 'error';

    private const KEEP_DAYS = 30;

    /** Keys whose values must never reach a log file. */
    private const REDACT = ['password', 'access_token', 'token', 'secret', 'sheetdb_token', 'authorization'];

    public static function debug(string $msg, array $ctx = []): void   { self::write(self::DEBUG, $msg, $ctx); }
    public static function info(string $msg, array $ctx = []): void    { self::write(self::INFO, $msg, $ctx); }
    public static function warning(string $msg, array $ctx = []): void { self::write(self::WARNING, $msg, $ctx); }
    public static function error(string $msg, array $ctx = []): void   { self::write(self::ERROR, $msg, $ctx); }

    /** Log a caught exception without letting it escape. */
    public static function exception(string $msg, Throwable $e, array $ctx = []): void {
        self::write(self::ERROR, $msg, $ctx + [
            'exception' => get_class($e),
            'message'   => $e->getMessage(),
            'at'        => basename($e->getFile()) . ':' . $e->getLine(),
        ]);
    }

    public static function write(string $level, string $message, array $ctx = []): void {
        $line = json_encode([
            'ts'      => date('c'),
            'level'   => $level,
            'message' => $message,
            'context' => self::redact($ctx),
            'uri'     => $_SERVER['REQUEST_URI'] ?? (PHP_SAPI === 'cli' ? 'cli' : null),
            'admin'   => $_SESSION['admin_username'] ?? null,
            'ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $dir = self::dir();
        if ($dir === null) {
            error_log("[$level] $message " . json_encode(self::redact($ctx), JSON_UNESCAPED_UNICODE));
            return;
        }

        $file = $dir . '/app-' . date('Y-m-d') . '.log';
        // LOCK_EX: two concurrent requests must not interleave half-lines.
        @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);

        self::pruneOccasionally($dir);
    }

    /**
     * The most recent entries, newest first — surfaced on the dashboard so an
     * error is noticed without anyone opening a terminal.
     *
     * @return list<array>
     */
    public static function recent(int $limit = 20, ?string $minLevel = self::WARNING): array {
        $dir = self::dir(false);
        if ($dir === null) return [];

        $rank  = [self::DEBUG => 0, self::INFO => 1, self::WARNING => 2, self::ERROR => 3];
        $floor = $rank[$minLevel] ?? 0;

        $out = [];
        // Today first, then backwards — enough to fill the list without reading
        // the whole retention window.
        for ($d = 0; $d < 7 && count($out) < $limit; $d++) {
            $file = $dir . '/app-' . date('Y-m-d', strtotime("-$d day")) . '.log';
            if (!is_file($file)) continue;

            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach (array_reverse($lines) as $line) {
                $row = json_decode($line, true);
                if (!is_array($row)) continue;
                if (($rank[$row['level'] ?? ''] ?? 0) < $floor) continue;
                $out[] = $row;
                if (count($out) >= $limit) break;
            }
        }
        return $out;
    }

    /** Replace secret-looking values with a marker, recursively. */
    private static function redact(array $ctx): array {
        foreach ($ctx as $k => $v) {
            if (is_array($v)) { $ctx[$k] = self::redact($v); continue; }
            foreach (self::REDACT as $needle) {
                if (stripos((string)$k, $needle) !== false) { $ctx[$k] = '[redacted]'; break; }
            }
        }
        return $ctx;
    }

    /** @return string|null the log directory, or null when it cannot be used */
    private static function dir(bool $create = true): ?string {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (is_dir($dir)) return is_writable($dir) || !$create ? $dir : null;
        if (!$create) return null;
        return @mkdir($dir, 0775, true) || is_dir($dir) ? $dir : null;
    }

    private static function pruneOccasionally(string $dir): void {
        if (random_int(1, 200) !== 1) return;
        $cutoff = time() - self::KEEP_DAYS * 86400;
        foreach (glob($dir . '/app-*.log') ?: [] as $f) {
            if (@filemtime($f) < $cutoff) @unlink($f);
        }
    }
}
