<?php
require __DIR__ . '/_bootstrap.php';
admin_require_auth();

// Inline status change from the list. A caller working a queue of thirty orders
// otherwise opens and leaves every single one.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    $id     = (int)($_POST['lead_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $valid  = ['new','called','confirmed','shipped','delivered','cancelled','no_answer'];

    if ($id && in_array($status, $valid, true)) {
        Lead::updateStatus($id, $status, clean_string($_POST['note'] ?? '', 500) ?: null, admin_id());
    }
    // Bounce back to exactly the same filtered page.
    redirect(base_url('admin/leads.php' . ($_POST['qs'] ?? '')));
}

$filters = [
    'phone'      => $_GET['phone']  ?? '',
    'status'     => $_GET['status'] ?? '',
    'product_id' => $_GET['product_id'] ?? '',
    'source'     => $_GET['source'] ?? '',
    'from'       => $_GET['from']   ?? '',
    'to'         => $_GET['to']     ?? '',
];
$page = max(1, (int)($_GET['page'] ?? 1));
$res  = Lead::paginate($filters, $page, 25);
$products = db()->query("SELECT id, title FROM products ORDER BY title")->fetchAll();

admin_render('leads', [
    'title'    => 'الطلبات',
    'res'      => $res,
    'filters'  => $filters,
    'products' => $products,
    // Flags a phone that already ordered recently, so the caller knows before
    // dialling rather than after.
    'dupes'    => Lead::duplicatePhones($res['rows']),
]);
