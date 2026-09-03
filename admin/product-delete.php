<?php
require __DIR__ . '/_bootstrap.php';
admin_require_admin();
admin_require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(base_url('admin/products.php'));

$id = (int)($_POST['id'] ?? 0);
if ($id) {
    $row = Product::findAny($id);

    if (($_POST['purge'] ?? '') === 'DELETE') {
        // Permanent, and it takes the product's orders with it. Only reachable
        // from the trash screen, behind a typed confirmation.
        Product::purge($id);
        Activity::log('delete', 'product', $id, 'purged: ' . ($row['title'] ?? ''));
    } else {
        // Default: retire. The landing page stops serving, the orders remain.
        Product::softDelete($id);
        Activity::log('delete', 'product', $id, 'retired: ' . ($row['title'] ?? ''));
    }
}

redirect(base_url('admin/products.php' . (($_POST['qs'] ?? '') ?: '')));
