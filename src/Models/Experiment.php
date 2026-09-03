<?php
/**
 * Experiment — A/B testing on a landing page's content.
 *
 * The whole test is one extra JSON column: variant B's sections. Everything
 * else the page renders — offers, images, options — stays shared, because a
 * test that changes two things at once cannot be read.
 *
 * Assignment is sticky per visitor per product, held in a cookie so a shopper
 * who returns sees the same page they saw before. The variant is written onto
 * the order, which is what makes the result readable next to revenue rather
 * than just clicks.
 *
 * The split is deterministic from a hash rather than random per request, so a
 * visitor with cookies disabled still gets a stable variant instead of flipping
 * on every page load.
 */
class Experiment {
    private const COOKIE_PREFIX = 'lp_ab_';
    private const COOKIE_DAYS   = 30;

    /**
     * Decide which variant this visitor sees, and return the sections for it.
     *
     * @return array{variant: ?string, sections: array}
     */
    public static function resolve(array $product): array {
        require_once __DIR__ . '/Sections.php';

        $sectionsA = Sections::decode($product['sections_json'] ?? null);

        if (empty($product['ab_enabled']) || trim((string)($product['sections_json_b'] ?? '')) === '') {
            return ['variant' => null, 'sections' => $sectionsA];
        }

        $variant = self::assign((int)$product['id'], (int)($product['ab_split'] ?? 50));

        return [
            'variant'  => $variant,
            'sections' => $variant === 'b'
                ? Sections::decode($product['sections_json_b'])
                : $sectionsA,
        ];
    }

    /** Sticky assignment: cookie first, then a stable hash of the visitor. */
    public static function assign(int $productId, int $splitPercent): string {
        $cookie = self::COOKIE_PREFIX . $productId;

        $existing = $_COOKIE[$cookie] ?? '';
        if ($existing === 'a' || $existing === 'b') return $existing;

        $split   = max(1, min(99, $splitPercent));   // never 0/100: that is not a test
        $variant = (self::bucket($productId) < $split) ? 'a' : 'b';

        // Headers may already be sent when this runs inside a view; the cookie
        // is a convenience, and the hash fallback keeps assignment stable
        // without it.
        if (!headers_sent()) {
            setcookie($cookie, $variant, [
                'expires'  => time() + self::COOKIE_DAYS * 86400,
                'path'     => '/',
                'httponly' => false,   // read by nothing else, but harmless
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE[$cookie] = $variant;
        return $variant;
    }

    /**
     * A stable 0–99 bucket for this visitor and product.
     *
     * Derived from the address and user agent rather than random, so the same
     * visitor lands in the same bucket across requests even with no cookie.
     */
    private static function bucket(int $productId): int {
        $seed = ($_SERVER['REMOTE_ADDR'] ?? '') . '|'
              . ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . $productId;
        return hexdec(substr(hash('sha256', $seed), 0, 4)) % 100;
    }

    /** The variant to record on an order, or null when the page is not testing. */
    public static function variantForOrder(array $product): ?string {
        if (empty($product['ab_enabled'])) return null;
        $v = $_COOKIE[self::COOKIE_PREFIX . (int)$product['id']] ?? '';
        return ($v === 'a' || $v === 'b') ? $v : null;
    }

    /**
     * Results for one page: orders, confirmations and revenue per variant.
     *
     * Deliberately reports revenue, not clicks. A variant that gets more orders
     * and fewer confirmations is worse, and only this view shows that.
     */
    public static function results(int $productId, ?string $from = null, ?string $to = null): array {
        $sql = "SELECT ab_variant AS variant,
                       COUNT(*) AS orders,
                       SUM(status IN ('confirmed','shipped','delivered')) AS confirmed,
                       COALESCE(SUM(CASE WHEN status IN ('confirmed','shipped','delivered')
                                         THEN total_price END), 0) AS revenue
                FROM leads
                WHERE product_id = :p AND ab_variant IS NOT NULL";
        $params = [':p' => $productId];

        if ($from) { $sql .= " AND created_at >= :from"; $params[':from'] = $from . ' 00:00:00'; }
        if ($to)   { $sql .= " AND created_at <= :to";   $params[':to']   = $to . ' 23:59:59'; }

        $sql .= " GROUP BY ab_variant";
        $st = db()->prepare($sql);
        $st->execute($params);

        $out = [
            'a' => ['variant' => 'a', 'orders' => 0, 'confirmed' => 0, 'revenue' => 0.0, 'confirm_rate' => 0.0, 'aov' => 0.0],
            'b' => ['variant' => 'b', 'orders' => 0, 'confirmed' => 0, 'revenue' => 0.0, 'confirm_rate' => 0.0, 'aov' => 0.0],
        ];
        foreach ($st->fetchAll() as $r) {
            $v = $r['variant'];
            if (!isset($out[$v])) continue;
            $orders    = (int)$r['orders'];
            $confirmed = (int)$r['confirmed'];
            $revenue   = (float)$r['revenue'];
            $out[$v] = [
                'variant'      => $v,
                'orders'       => $orders,
                'confirmed'    => $confirmed,
                'revenue'      => $revenue,
                'confirm_rate' => $orders > 0 ? round($confirmed / $orders * 100, 1) : 0.0,
                'aov'          => $confirmed > 0 ? round($revenue / $confirmed, 2) : 0.0,
            ];
        }
        return $out;
    }

    /**
     * A plain-language read of the result, including "not yet".
     *
     * The most common A/B mistake is calling a winner on nine orders. This says
     * so rather than showing a percentage that invites it.
     */
    public static function verdict(array $results): array {
        $a = $results['a'];
        $b = $results['b'];
        $total = $a['orders'] + $b['orders'];

        if ($total < 40) {
            return [
                'state' => 'early',
                'text'  => "لا تزال العينة صغيرة ({$total} طلب). انتظر 40 طلباً على الأقل قبل الحكم.",
            ];
        }

        $aRev = $a['revenue'];
        $bRev = $b['revenue'];
        if ($aRev <= 0 && $bRev <= 0) {
            return ['state' => 'early', 'text' => 'لا توجد إيرادات مؤكدة بعد في أي من النسختين.'];
        }

        $best  = $bRev > $aRev ? 'B' : 'A';
        $worst = $best === 'B' ? $aRev : $bRev;
        $win   = $best === 'B' ? $bRev : $aRev;
        $lift  = $worst > 0 ? round((($win - $worst) / $worst) * 100, 1) : 100.0;

        if ($lift < 10) {
            return ['state' => 'tie', 'text' => 'النسختان متقاربتان (فرق أقل من 10%). لا يوجد فائز واضح.'];
        }
        return [
            'state' => 'winner',
            'text'  => "النسخة {$best} متقدمة بفارق {$lift}% في الإيرادات المؤكدة.",
        ];
    }
}
