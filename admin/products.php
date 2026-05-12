<?php
require __DIR__ . '/_bootstrap.php';
admin_require_auth();

$products = db()->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id ORDER BY p.id DESC")->fetchAll();
admin_render('products', ['title' => 'المنتجات', 'products' => $products]);
