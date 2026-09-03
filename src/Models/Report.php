<?php
/**
 * Report — the numbers that decide where the ad budget goes.
 *
 * Everything here is derived from data the order flow already captures
 * (product, source, utm_campaign, status, total_price), so it costs nothing to
 * collect and answers the only question that matters weekly: which landing page
 * on which traffic source is actually producing confirmed revenue.
 *
 * "Revenue" throughout means confirmed + shipped + delivered. Counting `new`
 * orders as revenue is how a COD store convinces itself a campaign works when
 * half the orders are about to be cancelled on the phone.
 */
class Report {
    /** Statuses that represent money that will actually arrive. */
    private const EARNING = "('confirmed','shipped','delivered')";
    private const LOST    = "('cancelled','no_answer')";

    /** @return array{from:string, to:string} inclusive date bounds */
    public static function range(?string $from, ?string $to): array {
        $to   = $to   ?: date('Y-m-d');
        $from = $from ?: date('Y-m-d', strtotime('-29 days'));
        // A reversed range returns nothing and looks like "no data", which
        // wastes an afternoon. Swap instead.
        if ($from > $to) [$from, $to] = [$to, $from];
        return ['from' => $from, 'to' => $to];
    }

    private static function bounds(array $r): array {
        return [':from' => $r['from'] . ' 00:00:00', ':to' => $r['to'] . ' 23:59:59'];
    }

    /** Headline totals for the selected range. */
    public static function totals(array $range): array {
        $st = db()->prepare("
            SELECT
                COUNT(*)                                                   AS orders,
                SUM(status IN " . self::EARNING . ")                       AS confirmed,
                SUM(status IN " . self::LOST . ")                          AS lost,
                SUM(status = 'new')                                        AS pending,
                COALESCE(SUM(CASE WHEN status IN " . self::EARNING . " THEN total_price END), 0) AS revenue,
                COALESCE(SUM(total_price), 0)                              AS gross,
                COUNT(DISTINCT phone)                                      AS customers
            FROM leads
            WHERE created_at BETWEEN :from AND :to");
        $st->execute(self::bounds($range));
        $row = $st->fetch() ?: [];

        $orders    = (int)($row['orders'] ?? 0);
        $confirmed = (int)($row['confirmed'] ?? 0);
        $revenue   = (float)($row['revenue'] ?? 0);

        return [
            'orders'     => $orders,
            'confirmed'  => $confirmed,
            'lost'       => (int)($row['lost'] ?? 0),
            'pending'    => (int)($row['pending'] ?? 0),
            'revenue'    => $revenue,
            'gross'      => (float)($row['gross'] ?? 0),
            'customers'  => (int)($row['customers'] ?? 0),
            // The single most useful COD number: of the orders taken, how many
            // survive the confirmation call.
            'confirm_rate' => $orders > 0 ? round($confirmed / $orders * 100, 1) : 0.0,
            'aov'          => $confirmed > 0 ? round($revenue / $confirmed, 2) : 0.0,
        ];
    }

    /** One row per day, for the sparkline. Days with no orders are filled in. */
    public static function daily(array $range): array {
        $st = db()->prepare("
            SELECT DATE(created_at) AS d,
                   COUNT(*) AS orders,
                   COALESCE(SUM(CASE WHEN status IN " . self::EARNING . " THEN total_price END), 0) AS revenue
            FROM leads
            WHERE created_at BETWEEN :from AND :to
            GROUP BY DATE(created_at)
            ORDER BY d");
        $st->execute(self::bounds($range));

        $byDay = [];
        foreach ($st->fetchAll() as $r) $byDay[$r['d']] = $r;

        // A gap-free series: a chart that silently skips empty days makes a
        // quiet week look like a busy one.
        $out = [];
        for ($d = $range['from']; $d <= $range['to']; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
            $out[] = [
                'date'    => $d,
                'orders'  => (int)($byDay[$d]['orders'] ?? 0),
                'revenue' => (float)($byDay[$d]['revenue'] ?? 0),
            ];
            if (count($out) > 400) break;   // guard against a pathological range
        }
        return $out;
    }

    /** Per landing page. */
    public static function byProduct(array $range): array {
        $st = db()->prepare("
            SELECT p.id, p.title, p.slug, p.status,
                   COUNT(l.id)                        AS orders,
                   SUM(l.status IN " . self::EARNING . ") AS confirmed,
                   SUM(l.status IN " . self::LOST . ")    AS lost,
                   COALESCE(SUM(CASE WHEN l.status IN " . self::EARNING . " THEN l.total_price END), 0) AS revenue
            FROM leads l
            JOIN products p ON p.id = l.product_id
            WHERE l.created_at BETWEEN :from AND :to
            GROUP BY p.id, p.title, p.slug, p.status
            ORDER BY revenue DESC, orders DESC");
        $st->execute(self::bounds($range));
        return array_map([self::class, 'withRates'], $st->fetchAll());
    }

    /** Per traffic source (facebook / tiktok / google / organic). */
    public static function bySource(array $range): array {
        $st = db()->prepare("
            SELECT COALESCE(NULLIF(source, ''), 'organic') AS source,
                   COUNT(*)                        AS orders,
                   SUM(status IN " . self::EARNING . ") AS confirmed,
                   SUM(status IN " . self::LOST . ")    AS lost,
                   COALESCE(SUM(CASE WHEN status IN " . self::EARNING . " THEN total_price END), 0) AS revenue
            FROM leads
            WHERE created_at BETWEEN :from AND :to
            GROUP BY COALESCE(NULLIF(source, ''), 'organic')
            ORDER BY revenue DESC");
        $st->execute(self::bounds($range));
        return array_map([self::class, 'withRates'], $st->fetchAll());
    }

    /**
     * The cross-tab: landing page × source.
     *
     * This is the table that answers "which ad account should I scale" — a page
     * can be profitable on TikTok and a loss on Meta, and either number alone
     * hides that.
     */
    public static function byProductAndSource(array $range, int $limit = 40): array {
        $limit = max(1, min(200, $limit));
        $st = db()->prepare("
            SELECT p.title, p.slug,
                   COALESCE(NULLIF(l.source, ''), 'organic') AS source,
                   COUNT(*)                        AS orders,
                   SUM(l.status IN " . self::EARNING . ") AS confirmed,
                   COALESCE(SUM(CASE WHEN l.status IN " . self::EARNING . " THEN l.total_price END), 0) AS revenue
            FROM leads l
            JOIN products p ON p.id = l.product_id
            WHERE l.created_at BETWEEN :from AND :to
            GROUP BY p.title, p.slug, COALESCE(NULLIF(l.source, ''), 'organic')
            ORDER BY revenue DESC, orders DESC
            LIMIT $limit");
        $st->execute(self::bounds($range));
        return array_map([self::class, 'withRates'], $st->fetchAll());
    }

    /** Per campaign, for the pages tagged with utm_campaign. */
    public static function byCampaign(array $range, int $limit = 25): array {
        $limit = max(1, min(200, $limit));
        $st = db()->prepare("
            SELECT utm_campaign AS campaign,
                   COALESCE(NULLIF(source, ''), 'organic') AS source,
                   COUNT(*)                        AS orders,
                   SUM(status IN " . self::EARNING . ") AS confirmed,
                   COALESCE(SUM(CASE WHEN status IN " . self::EARNING . " THEN total_price END), 0) AS revenue
            FROM leads
            WHERE created_at BETWEEN :from AND :to
              AND utm_campaign IS NOT NULL AND utm_campaign <> ''
            GROUP BY utm_campaign, COALESCE(NULLIF(source, ''), 'organic')
            ORDER BY revenue DESC
            LIMIT $limit");
        $st->execute(self::bounds($range));
        return array_map([self::class, 'withRates'], $st->fetchAll());
    }

    /** Where orders currently sit, so the call queue is visible. */
    public static function statusBreakdown(array $range): array {
        $st = db()->prepare("
            SELECT status, COUNT(*) AS n,
                   COALESCE(SUM(total_price), 0) AS value
            FROM leads
            WHERE created_at BETWEEN :from AND :to
            GROUP BY status");
        $st->execute(self::bounds($range));

        $all = ['new' => 0, 'called' => 0, 'confirmed' => 0, 'shipped' => 0,
                'delivered' => 0, 'cancelled' => 0, 'no_answer' => 0];
        $out = [];
        foreach ($all as $s => $_) $out[$s] = ['n' => 0, 'value' => 0.0];
        foreach ($st->fetchAll() as $r) {
            $out[$r['status']] = ['n' => (int)$r['n'], 'value' => (float)$r['value']];
        }
        return $out;
    }

    /** Adds confirm_rate and aov to any grouped row. */
    private static function withRates(array $row): array {
        $orders    = (int)($row['orders'] ?? 0);
        $confirmed = (int)($row['confirmed'] ?? 0);
        $revenue   = (float)($row['revenue'] ?? 0);

        $row['orders']       = $orders;
        $row['confirmed']    = $confirmed;
        $row['lost']         = (int)($row['lost'] ?? 0);
        $row['revenue']      = $revenue;
        $row['confirm_rate'] = $orders > 0 ? round($confirmed / $orders * 100, 1) : 0.0;
        $row['aov']          = $confirmed > 0 ? round($revenue / $confirmed, 2) : 0.0;
        return $row;
    }
}
