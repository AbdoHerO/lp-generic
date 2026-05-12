<?php
require __DIR__ . '/_bootstrap.php';
admin_require_auth();
admin_require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(base_url('admin/products.php'));
$id = (int)($_POST['id'] ?? 0);
if ($id) {
    $st = db()->prepare("DELETE FROM products WHERE id=:i");
    $st->execute([':i' => $id]);
}
redirect(base_url('admin/products.php'));
