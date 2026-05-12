<?php
require __DIR__ . '/_bootstrap.php';
admin_require_auth();

$id = (int)($_GET['id'] ?? 0);
$lead = Lead::find($id);
if (!$lead) redirect(base_url('admin/leads.php'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    $status = $_POST['status'] ?? $lead['status'];
    $note   = clean_string($_POST['note'] ?? '', 500);
    $valid = ['new','called','confirmed','shipped','delivered','cancelled','no_answer'];
    if (in_array($status, $valid, true)) {
        Lead::updateStatus($id, $status, $note ?: null, admin_id());
        redirect(base_url('admin/lead-detail.php?id=' . $id));
    }
}

$items   = Lead::items($id);
$logs    = Lead::statusLogs($id);
$product = Product::find((int)$lead['product_id']);

admin_render('lead-detail', [
    'title' => 'الطلب #' . $lead['id'],
    'lead'  => $lead,
    'items' => $items,
    'logs'  => $logs,
    'product' => $product,
]);
