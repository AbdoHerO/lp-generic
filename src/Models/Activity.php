<?php
/**
 * Activity — who changed what, and when.
 *
 * lead_status_logs already audits orders. This does the same for everything
 * else an operator can change: products, pixels, categories, settings, admin
 * accounts. It is the first thing anyone asks for when a live landing page
 * changes and nobody remembers touching it.
 *
 * Writes are best-effort. An audit trail must never be the reason an edit fails
 * to save.
 */
class Activity {
    public static function log(string $action, string $entity, ?int $entityId = null, ?string $summary = null): void {
        try {
            db()->prepare(
                "INSERT INTO activity_log (admin_id, admin_name, action, entity, entity_id, summary, ip)
                 VALUES (:aid, :aname, :action, :entity, :eid, :summary, :ip)"
            )->execute([
                ':aid'     => $_SESSION['admin_id'] ?? null,
                ':aname'   => $_SESSION['admin_username'] ?? null,
                ':action'  => mb_substr($action, 0, 40),
                ':entity'  => mb_substr($entity, 0, 40),
                ':eid'     => $entityId,
                ':summary' => $summary === null ? null : mb_substr($summary, 0, 255),
                ':ip'      => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64) ?: null,
            ]);
        } catch (Throwable $e) {
            error_log('Activity::log failed: ' . $e->getMessage());
        }
    }

    /** @return array{rows: list<array>, total: int, page: int, per_page: int, pages: int} */
    public static function paginate(array $filters = [], int $page = 1, int $perPage = 50): array {
        $where  = '1=1';
        $params = [];

        if (!empty($filters['entity']))   { $where .= ' AND entity = :e';  $params[':e'] = $filters['entity']; }
        if (!empty($filters['action']))   { $where .= ' AND action = :a';  $params[':a'] = $filters['action']; }
        if (!empty($filters['admin_id'])) { $where .= ' AND admin_id = :ad'; $params[':ad'] = (int)$filters['admin_id']; }

        $countSt = db()->prepare("SELECT COUNT(*) FROM activity_log WHERE $where");
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();

        $perPage = max(10, min(200, $perPage));
        $pages   = max(1, (int)ceil($total / $perPage));
        $page    = max(1, min($page, $pages));
        $offset  = ($page - 1) * $perPage;

        $st = db()->prepare("SELECT * FROM activity_log WHERE $where ORDER BY id DESC LIMIT $perPage OFFSET $offset");
        $st->execute($params);

        return ['rows' => $st->fetchAll(), 'total' => $total, 'page' => $page,
                'per_page' => $perPage, 'pages' => $pages];
    }

    /** Everything ever done to one record — shown on that record's own page. */
    public static function forEntity(string $entity, int $id, int $limit = 20): array {
        $limit = max(1, min(100, $limit));
        $st = db()->prepare("SELECT * FROM activity_log WHERE entity = :e AND entity_id = :i
                             ORDER BY id DESC LIMIT $limit");
        $st->execute([':e' => $entity, ':i' => $id]);
        return $st->fetchAll();
    }

    /** Trim the trail so it cannot grow without bound on a busy store. */
    public static function prune(int $keepDays = 180): int {
        try {
            $st = db()->prepare("DELETE FROM activity_log WHERE created_at < (NOW() - INTERVAL :d DAY)");
            $st->bindValue(':d', $keepDays, PDO::PARAM_INT);
            $st->execute();
            return $st->rowCount();
        } catch (Throwable $e) {
            error_log('Activity::prune failed: ' . $e->getMessage());
            return 0;
        }
    }
}
