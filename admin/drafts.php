<?php
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../src/Models/Draft.php';
admin_require_auth();   // agents work this list — it is the call-back queue

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    if (($_POST['action'] ?? '') === 'delete') {
        Draft::delete((int)($_POST['id'] ?? 0));
    }
    redirect(base_url('admin/drafts.php' . (($_POST['qs'] ?? '') ?: '')));
}

$filters = [
    'product_id' => $_GET['product_id'] ?? '',
    'phone'      => $_GET['phone'] ?? '',
];

admin_render('drafts', [
    'title'    => 'طلبات لم تكتمل',
    'res'      => Draft::paginate($filters, max(1, (int)($_GET['page'] ?? 1)), 25),
    'filters'  => $filters,
    'stats'    => Draft::stats(30),
    'products' => db()->query("SELECT id, title FROM products WHERE deleted_at IS NULL ORDER BY title")->fetchAll(),
]);
