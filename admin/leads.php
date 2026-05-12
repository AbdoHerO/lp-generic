<?php
require __DIR__ . '/_bootstrap.php';
admin_require_auth();

$filters = [
    'phone'      => $_GET['phone']  ?? '',
    'status'     => $_GET['status'] ?? '',
    'product_id' => $_GET['product_id'] ?? '',
    'source'     => $_GET['source'] ?? '',
    'from'       => $_GET['from']   ?? '',
    'to'         => $_GET['to']     ?? '',
];
$page = max(1, (int)($_GET['page'] ?? 1));
$res = Lead::paginate($filters, $page, 25);
$products = db()->query("SELECT id, title FROM products ORDER BY title")->fetchAll();

admin_render('leads', [
    'title' => 'الطلبات',
    'res' => $res,
    'filters' => $filters,
    'products' => $products,
]);
