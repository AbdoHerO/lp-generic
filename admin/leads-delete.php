<?php
require __DIR__ . '/_bootstrap.php';
admin_require_auth();
admin_require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(base_url('admin/leads.php'));

$pdo = db();

// Bulk delete: ids[] array
$ids = array_map('intval', (array)($_POST['ids'] ?? []));
$ids = array_filter($ids, fn($v) => $v > 0);

if ($ids) {
    $in  = implode(',', $ids);                       // safe — all are ints
    $pdo->exec("DELETE FROM lead_units WHERE lead_id IN ($in)");
    $pdo->exec("DELETE FROM lead_events WHERE lead_id IN ($in)");
    $pdo->exec("DELETE FROM leads WHERE id IN ($in)");
}

// keep query string filters for redirect
$qs = http_build_query(array_filter([
    'status'     => $_POST['status_filter']     ?? '',
    'product_id' => $_POST['product_id_filter'] ?? '',
    'phone'      => $_POST['phone_filter']      ?? '',
    'page'       => $_POST['page_filter']       ?? '',
]));

redirect(base_url('admin/leads.php' . ($qs ? '?' . $qs : '')));
