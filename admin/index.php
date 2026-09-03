<?php
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../src/Models/Report.php';
admin_require_auth();

// Last 30 days by default — long enough to show a trend, short enough that a
// change made this week is visible in it.
$range = Report::range(null, null);

$recent = db()->query(
    "SELECT l.*, p.title AS product_title
     FROM leads l LEFT JOIN products p ON p.id = l.product_id
     ORDER BY l.id DESC LIMIT 8"
)->fetchAll();

admin_render('dashboard', [
    'title'    => 'لوحة التحكم',
    'range'    => $range,
    'stats'    => Lead::dashboardStats(),
    'totals'   => Report::totals($range),
    'daily'    => Report::daily($range),
    'products' => array_slice(Report::byProduct($range), 0, 5),
    'sources'  => Report::bySource($range),
    'statuses' => Report::statusBreakdown($range),
    'recent'   => $recent,
    'audit'    => SecurityAudit::findings(),
]);
