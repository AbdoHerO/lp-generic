<?php
/**
 * Reports — the aggregation that ad-budget decisions are made from.
 *
 * The numbers are checked against a hand-built ledger, because a report that is
 * quietly wrong is worse than no report: it gets believed.
 *
 * Runs on in-memory SQLite. The model's SQL is portable enough to execute
 * as-is, so these are the real queries, not re-implementations.
 *
 * Run:  php tests/reports_test.php
 */

$ROOT = dirname(__DIR__);

$pass = 0; $fail = 0;
function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    printf("%-4s %s%s\n", $ok ? 'ok' : 'FAIL', $label, $detail !== '' ? "  — $detail" : '');
}

$PDO = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
// SQLite has no DATE(); its date() is close enough for the GROUP BY.
$PDO->sqliteCreateFunction('DATE', fn($v) => substr((string)$v, 0, 10));
function db(): PDO { return $GLOBALS['PDO']; }
require_once $ROOT . '/src/Models/Report.php';

$PDO->exec("CREATE TABLE products (id INTEGER PRIMARY KEY, title TEXT, slug TEXT, status INT DEFAULT 1)");
$PDO->exec("CREATE TABLE leads (
    id INTEGER PRIMARY KEY, product_id INT, product_slug TEXT, total_price REAL,
    phone TEXT, status TEXT, source TEXT, utm_campaign TEXT, created_at TEXT)");

$PDO->exec("INSERT INTO products (id,title,slug,status) VALUES
    (1,'سروال','pants',1), (2,'قميص','shirt',1), (3,'حذاء','shoes',0)");

$today = date('Y-m-d');
$y     = date('Y-m-d', strtotime('-1 day'));
$old   = date('Y-m-d', strtotime('-90 days'));

// A deliberate, hand-countable ledger.
//                       product, price, phone,   status,     source,     campaign,  day
$rows = [
    [1, 100, '0600000001', 'confirmed', 'facebook', 'winter', $today],
    [1, 200, '0600000002', 'delivered', 'facebook', 'winter', $today],
    [1,  50, '0600000003', 'cancelled', 'facebook', 'winter', $today],
    [1, 300, '0600000004', 'new',       'tiktok',   'tt1',    $today],
    [1, 400, '0600000005', 'shipped',   'tiktok',   'tt1',    $y],
    [2, 150, '0600000006', 'confirmed', '',         null,     $y],
    [2,  90, '0600000006', 'no_answer', '',         null,     $y],
    [1, 999, '0600000009', 'confirmed', 'facebook', 'winter', $old],   // outside the range
];
$ins = $PDO->prepare("INSERT INTO leads (product_id,product_slug,total_price,phone,status,source,utm_campaign,created_at)
                      VALUES (?,?,?,?,?,?,?,?)");
foreach ($rows as $r) {
    $slug = [1 => 'pants', 2 => 'shirt', 3 => 'shoes'][$r[0]];
    $ins->execute([$r[0], $slug, $r[1], $r[2], $r[3], $r[4], $r[5], $r[6] . ' 12:00:00']);
}

$range = Report::range(date('Y-m-d', strtotime('-7 days')), $today);

// ── range handling ─────────────────────────────────────────────────────────
check('range defaults to the last 30 days',
    Report::range(null, null)['from'] === date('Y-m-d', strtotime('-29 days')));
check('a reversed range is swapped, not empty',
    Report::range('2026-09-30', '2026-09-01') === ['from' => '2026-09-01', 'to' => '2026-09-30']);
check('an explicit range is respected',
    Report::range('2026-01-01', '2026-01-31')['to'] === '2026-01-31');

// ── totals ─────────────────────────────────────────────────────────────────
// In range: 7 orders. Earning = confirmed/shipped/delivered = 100+200+400+150 = 850.
$t = Report::totals($range);
check('counts only orders in the range', $t['orders'] === 7, (string)$t['orders']);
check('revenue counts confirmed, shipped and delivered', abs($t['revenue'] - 850.0) < 0.01, (string)$t['revenue']);
check('revenue excludes new orders',     $t['revenue'] < 850.0 + 300);
check('revenue excludes cancelled',      abs($t['revenue'] - 850.0) < 0.01);
check('gross counts everything',         abs($t['gross'] - 1290.0) < 0.01, (string)$t['gross']);
check('confirmed count is right',        $t['confirmed'] === 4, (string)$t['confirmed']);
check('lost counts cancelled + no_answer', $t['lost'] === 2, (string)$t['lost']);
check('pending counts new',              $t['pending'] === 1);
check('confirm rate is confirmed/orders', abs($t['confirm_rate'] - round(4 / 7 * 100, 1)) < 0.05, (string)$t['confirm_rate']);
check('AOV is revenue/confirmed',        abs($t['aov'] - round(850 / 4, 2)) < 0.01, (string)$t['aov']);
check('distinct customers dedupe by phone', $t['customers'] === 6, (string)$t['customers']);

// An empty range must produce zeros, not a division by zero.
$empty = Report::totals(Report::range('2000-01-01', '2000-01-02'));
check('an empty range gives zero orders',   $empty['orders'] === 0);
check('an empty range gives 0% not NaN',    $empty['confirm_rate'] === 0.0);
check('an empty range gives 0 AOV',         $empty['aov'] === 0.0);

// ── daily series ───────────────────────────────────────────────────────────
$d = Report::daily($range);
check('daily covers every day in the range', count($d) === 8, count($d) . ' days');
check('daily has no gaps',
    $d[0]['date'] === $range['from'] && $d[count($d) - 1]['date'] === $range['to']);
$byDate = array_column($d, null, 'date');
check('today\'s revenue is right',  abs($byDate[$today]['revenue'] - 300.0) < 0.01, (string)$byDate[$today]['revenue']);
check('today\'s order count is right', $byDate[$today]['orders'] === 4);
check('a quiet day reports zero, not missing',
    isset($byDate[date('Y-m-d', strtotime('-5 days'))]) &&
    $byDate[date('Y-m-d', strtotime('-5 days'))]['orders'] === 0);

// ── by product ─────────────────────────────────────────────────────────────
$p = array_column(Report::byProduct($range), null, 'slug');
check('product revenue is right',  abs($p['pants']['revenue'] - 700.0) < 0.01, (string)$p['pants']['revenue']);
check('product order count is right', $p['pants']['orders'] === 5);
check('second product is included', abs($p['shirt']['revenue'] - 150.0) < 0.01);
check('products are ordered by revenue', array_key_first($p) === 'pants');
check('a product with no orders is absent', !isset($p['shoes']));

// ── by source ──────────────────────────────────────────────────────────────
$s = array_column(Report::bySource($range), null, 'source');
check('facebook revenue is right', abs($s['facebook']['revenue'] - 300.0) < 0.01, (string)$s['facebook']['revenue']);
check('tiktok revenue is right',   abs($s['tiktok']['revenue'] - 400.0) < 0.01);
check('an empty source becomes organic', isset($s['organic']), implode(',', array_keys($s)));
check('organic revenue is right',  abs($s['organic']['revenue'] - 150.0) < 0.01);
check('facebook confirm rate is right', abs($s['facebook']['confirm_rate'] - round(2 / 3 * 100, 1)) < 0.05);

// ── page × source ──────────────────────────────────────────────────────────
$m = Report::byProductAndSource($range);
$cell = null;
foreach ($m as $row) if ($row['slug'] === 'pants' && $row['source'] === 'tiktok') $cell = $row;
check('the cross-tab splits one page across sources', $cell !== null);
check('the tiktok cell has its own revenue', $cell && abs($cell['revenue'] - 400.0) < 0.01);
check('the tiktok cell has its own order count', $cell && $cell['orders'] === 2);
check('the cross-tab is ordered by revenue', $m[0]['revenue'] >= end($m)['revenue']);

// ── campaigns ──────────────────────────────────────────────────────────────
$c = Report::byCampaign($range);
$winter = null;
foreach ($c as $row) if ($row['campaign'] === 'winter') $winter = $row;
check('campaigns are grouped',       $winter !== null);
check('campaign revenue is right',   $winter && abs($winter['revenue'] - 300.0) < 0.01);
check('untagged orders are excluded', !array_filter($c, fn($r) => ($r['campaign'] ?? '') === ''));

// ── status breakdown ───────────────────────────────────────────────────────
$st = Report::statusBreakdown($range);
check('every status key is present', count($st) === 7, implode(',', array_keys($st)));
check('a status with no orders reports zero', $st['called']['n'] === 0);
// statusBreakdown groups by the literal status, so this is the 2 rows marked
// 'confirmed' — not the 4 that count as earning (confirmed+shipped+delivered).
check('confirmed status count is right', $st['confirmed']['n'] === 2, (string)$st['confirmed']['n']);
check('shipped and delivered are separate rows',
    $st['shipped']['n'] === 1 && $st['delivered']['n'] === 1);
check('the status rows sum to the order total',
    array_sum(array_column($st, 'n')) === $t['orders'], (string)array_sum(array_column($st, 'n')));
check('cancelled value is right',    abs($st['cancelled']['value'] - 50.0) < 0.01);

// ── injection safety ───────────────────────────────────────────────────────
// The two LIMITs are interpolated (PDO cannot bind LIMIT); they must be clamped.
$src = file_get_contents($ROOT . '/src/Models/Report.php');
check('LIMIT values are clamped, not interpolated raw',
    substr_count($src, 'max(1, min(') >= 2);
check('date bounds are bound parameters', str_contains($src, "':from' =>") && str_contains($src, "':to' =>"));
check('no string concatenation of user input into SQL', !preg_match('/\$_(GET|POST)/', $src));
$limited = Report::byProductAndSource($range, 99999);
check('an absurd limit is capped', count($limited) <= 200);

// ── the view renders these rows without warnings ───────────────────────────
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function base_url($p = '') { return '/lp_tifaw/' . ltrim($p, '/'); }

$presets   = ['7d' => ['آخر 7 أيام', '', ''], '30d' => ['آخر 30 يوم', '', '']];
$preset    = '7d';
$totals    = $t;
$daily     = $d;
$products  = Report::byProduct($range);
$sources   = Report::bySource($range);
$matrix    = $m;
$campaigns = $c;
$statuses  = $st;

ob_start(); include $ROOT . '/admin/views/reports.php'; $html = ob_get_clean();

check('report view renders',             strlen($html) > 3000, strlen($html) . ' bytes');
check('report view has no PHP warning',  !str_contains($html, 'Warning:') && !str_contains($html, 'Notice:'));
check('report view shows the revenue',   str_contains($html, '850.00'));
check('report view shows the cross-tab', str_contains($html, 'صفحة الهبوط × مصدر الزيارة'));
check('report view draws a sparkline',   str_contains($html, 'spark-col'));
check('report view labels sources',      str_contains($html, 'src-tiktok'));
check('report view links to filtered orders', str_contains($html, 'admin/leads.php?status=confirmed'));

// An empty period must render an explanation, not a blank page or a division by zero.
$range     = Report::range('2000-01-01', '2000-01-05');
$preset    = '';
$totals    = Report::totals($range);
$daily     = Report::daily($range);
$products  = [];
$sources   = [];
$matrix    = [];
$campaigns = [];
$statuses  = Report::statusBreakdown($range);

ob_start(); include $ROOT . '/admin/views/reports.php'; $blankHtml = ob_get_clean();
check('an empty period renders',            strlen($blankHtml) > 1000);
check('an empty period says so',            str_contains($blankHtml, 'لا توجد'));
check('an empty period has no PHP warning',
    !str_contains($blankHtml, 'Warning:') && !str_contains($blankHtml, 'Notice:'));
check('an empty period draws no bars',      !str_contains($blankHtml, 'spark-col'));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
