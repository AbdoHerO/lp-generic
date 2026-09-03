<?php
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../src/Models/Report.php';
admin_require_auth();

// Quick ranges cover the way this is actually used — "this week", "last month" —
// without making anyone type two dates to answer a daily question.
$preset  = $_GET['preset'] ?? '';
$presets = [
    'today'  => ['من اليوم',        date('Y-m-d'),                              date('Y-m-d')],
    '7d'     => ['آخر 7 أيام',      date('Y-m-d', strtotime('-6 days')),        date('Y-m-d')],
    '30d'    => ['آخر 30 يوم',      date('Y-m-d', strtotime('-29 days')),       date('Y-m-d')],
    'month'  => ['هذا الشهر',        date('Y-m-01'),                             date('Y-m-d')],
    'prev'   => ['الشهر الماضي',     date('Y-m-01', strtotime('first day of last month')),
                                     date('Y-m-t', strtotime('last day of last month'))],
];

if (isset($presets[$preset])) {
    [$_, $from, $to] = $presets[$preset];
} else {
    $from = $_GET['from'] ?? '';
    $to   = $_GET['to']   ?? '';
}
$range = Report::range($from ?: null, $to ?: null);

admin_render('reports', [
    'title'     => 'التقارير',
    'range'     => $range,
    'preset'    => $preset,
    'presets'   => $presets,
    'totals'    => Report::totals($range),
    'daily'     => Report::daily($range),
    'products'  => Report::byProduct($range),
    'sources'   => Report::bySource($range),
    'matrix'    => Report::byProductAndSource($range),
    'campaigns' => Report::byCampaign($range),
    'statuses'  => Report::statusBreakdown($range),
]);
