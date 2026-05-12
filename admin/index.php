<?php
require __DIR__ . '/_bootstrap.php';
admin_require_auth();

$stats = Lead::dashboardStats();
$recent = db()->query("SELECT l.*, p.title AS product_title FROM leads l LEFT JOIN products p ON p.id=l.product_id ORDER BY l.id DESC LIMIT 8")->fetchAll();

admin_render('dashboard', [
    'title' => 'لوحة التحكم',
    'stats' => $stats,
    'recent' => $recent,
]);
