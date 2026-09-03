<?php
/**
 * Draft — a shopper who typed a valid phone number and then left.
 *
 * In COD selling these are often the cheapest recoverable orders on the books:
 * the ad has already been paid for, the shopper was interested enough to reach
 * the form, and one call converts a meaningful share of them.
 *
 * Deliberately NOT a lead with a special status. A draft must never reach the
 * order queue, the revenue report or a conversion pixel — it is an intention,
 * not a sale, and mixing the two would quietly inflate every number the store
 * makes decisions from.
 *
 * Only the phone (and optionally a first name) is captured. No address, no
 * options, no price: enough to call back, and nothing more.
 */
class Draft {
    /** Drafts older than this are deleted — see prune(). */
    private const KEEP_DAYS = 60;

    /**
     * Record or refresh a draft.
     *
     * Upserts on (phone, product_id) so a shopper who retypes their number a
     * dozen times produces one row, not twelve.
     */
    public static function capture(int $productId, string $phone, ?string $fullname, ?int $offerId): bool {
        $phone = clean_phone($phone);
        if (!preg_match('/^0[6-7]\d{8}$/', $phone)) return false;   // same rule as the order form

        try {
            db()->prepare(
                "INSERT INTO lead_drafts (product_id, phone, fullname, offer_id, source, ip)
                 VALUES (:p, :ph, :fn, :o, :src, :ip)
                 ON DUPLICATE KEY UPDATE
                    fullname   = COALESCE(VALUES(fullname), fullname),
                    offer_id   = VALUES(offer_id),
                    updated_at = NOW()"
            )->execute([
                ':p'   => $productId ?: null,
                ':ph'  => $phone,
                ':fn'  => $fullname !== null && $fullname !== '' ? clean_string($fullname, 160) : null,
                ':o'   => $offerId ?: null,
                ':src' => detect_source(),
                ':ip'  => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64) ?: null,
            ]);
            return true;
        } catch (Throwable $e) {
            require_once __DIR__ . '/Log.php';
            Log::exception('Draft capture failed', $e, ['product_id' => $productId]);
            return false;
        }
    }

    /**
     * Mark a draft as converted once the real order arrives.
     *
     * Kept rather than deleted: "how many drafts turn into orders on their own"
     * is the number that says whether calling them back is worth the time.
     */
    public static function markConverted(int $productId, string $phone): void {
        try {
            db()->prepare("UPDATE lead_drafts SET converted = 1
                           WHERE phone = :ph AND (product_id = :p OR product_id IS NULL)")
               ->execute([':ph' => clean_phone($phone), ':p' => $productId ?: null]);
        } catch (Throwable $e) {
            // Never let bookkeeping break an order that already saved.
        }
    }

    /** Unconverted drafts, newest first — the call-back list. */
    public static function paginate(array $filters = [], int $page = 1, int $perPage = 25): array {
        $where  = 'd.converted = 0';
        $params = [];

        if (!empty($filters['product_id'])) {
            $where .= ' AND d.product_id = :pid';
            $params[':pid'] = (int)$filters['product_id'];
        }
        if (!empty($filters['phone'])) {
            $where .= ' AND d.phone LIKE :ph';
            $params[':ph'] = '%' . $filters['phone'] . '%';
        }

        $countSt = db()->prepare("SELECT COUNT(*) FROM lead_drafts d WHERE $where");
        $countSt->execute($params);
        $total = (int)$countSt->fetchColumn();

        $perPage = max(10, min(100, $perPage));
        $pages   = max(1, (int)ceil($total / $perPage));
        $page    = max(1, min($page, $pages));
        $offset  = ($page - 1) * $perPage;

        $st = db()->prepare("SELECT d.*, p.title AS product_title, p.slug AS product_slug
                             FROM lead_drafts d LEFT JOIN products p ON p.id = d.product_id
                             WHERE $where ORDER BY d.updated_at DESC
                             LIMIT $perPage OFFSET $offset");
        $st->execute($params);

        return ['rows' => $st->fetchAll(), 'total' => $total, 'page' => $page,
                'per_page' => $perPage, 'pages' => $pages];
    }

    /** @return array{total:int, pending:int, converted:int, rate:float} */
    public static function stats(int $days = 30): array {
        $st = db()->prepare("SELECT COUNT(*) AS total, SUM(converted) AS converted
                             FROM lead_drafts WHERE created_at >= (NOW() - INTERVAL :d DAY)");
        $st->bindValue(':d', $days, PDO::PARAM_INT);
        $st->execute();
        $r = $st->fetch() ?: [];

        $total     = (int)($r['total'] ?? 0);
        $converted = (int)($r['converted'] ?? 0);
        return [
            'total'     => $total,
            'pending'   => $total - $converted,
            'converted' => $converted,
            'rate'      => $total > 0 ? round($converted / $total * 100, 1) : 0.0,
        ];
    }

    public static function delete(int $id): void {
        db()->prepare("DELETE FROM lead_drafts WHERE id = :i")->execute([':i' => $id]);
    }

    /**
     * Drop old drafts.
     *
     * These are phone numbers of people who never became customers. Keeping
     * them forever is neither useful nor defensible.
     */
    public static function prune(?int $days = null): int {
        $days = $days ?? self::KEEP_DAYS;
        try {
            $st = db()->prepare("DELETE FROM lead_drafts WHERE created_at < (NOW() - INTERVAL :d DAY)");
            $st->bindValue(':d', $days, PDO::PARAM_INT);
            $st->execute();
            return $st->rowCount();
        } catch (Throwable $e) {
            return 0;
        }
    }
}
