<?php
require __DIR__ . '/_bootstrap.php';
admin_require_admin();

$msg = null;
$err = null;

// ── Bulk actions ───────────────────────────────────────────────────────────
// Switching a whole category to a new ad account was thirty separate edits;
// retiring last season's pages was thirty more.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();

    $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), fn($v) => $v > 0));
    $action = $_POST['bulk_action'] ?? '';

    if (!$ids) {
        $err = 'لم تحدد أي منتج';
    } else {
        $in  = implode(',', $ids);   // safe: every element was cast to int above
        $pdo = db();

        switch ($action) {
            case 'activate':
                $pdo->exec("UPDATE products SET status = 1 WHERE id IN ($in)");
                $msg = 'تم تفعيل ' . count($ids) . ' منتج';
                break;

            case 'deactivate':
                $pdo->exec("UPDATE products SET status = 0 WHERE id IN ($in)");
                $msg = 'تم تعطيل ' . count($ids) . ' منتج';
                break;

            case 'delete':
                // Retire, not destroy. The page disappears from the store and
                // the admin list; its orders stay exactly where they are, which
                // is what the accounts need.
                $pdo->exec("UPDATE products SET deleted_at = NOW(), status = 0 WHERE id IN ($in)");
                Activity::log('bulk', 'product', null, 'retired: ' . implode(',', $ids));
                $msg = 'تم نقل ' . count($ids) . ' منتج إلى المهملات';
                break;

            case 'restore':
                $pdo->exec("UPDATE products SET deleted_at = NULL WHERE id IN ($in)");
                Activity::log('bulk', 'product', null, 'restored: ' . implode(',', $ids));
                $msg = 'تمت استعادة ' . count($ids) . ' منتج';
                break;

            case 'purge':
                // The only path that destroys orders, and it is behind a typed
                // confirmation from the trash screen.
                if (($_POST['confirm_delete'] ?? '') !== 'DELETE') {
                    $err = 'لم يتم تأكيد الحذف النهائي';
                    break;
                }
                $pdo->exec("DELETE FROM products WHERE id IN ($in)");
                Activity::log('bulk', 'product', null, 'purged: ' . implode(',', $ids));
                $msg = 'تم الحذف النهائي لـ ' . count($ids) . ' منتج';
                break;

            case 'set_fb_pixel':
            case 'set_tt_pixel':
                $col = $action === 'set_fb_pixel' ? 'fb_pixel_id' : 'tt_pixel_id';
                $raw = $_POST['bulk_pixel'] ?? '';
                // Same three states as the per-product dropdown.
                $value = $raw === '' ? null : (int)$raw;

                $st = $pdo->prepare("UPDATE products SET $col = :v WHERE id IN ($in)");
                $st->bindValue(':v', $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $st->execute();

                $label = $value === null ? 'افتراضي' : ($value === 0 ? 'بدون تتبع' : (Pixel::find($value)['name'] ?? '؟'));
                $msg = 'تم ضبط البكسل إلى «' . $label . '» لـ ' . count($ids) . ' منتج';
                break;

            default:
                $err = 'إجراء غير معروف';
        }
    }

    // Keep the operator where they were.
    $keep = array_filter([
        'q'        => $_POST['f_q']        ?? '',
        'status'   => $_POST['f_status']   ?? '',
        'pixel_id' => $_POST['f_pixel_id'] ?? '',
        'category_id' => $_POST['f_category_id'] ?? '',
        'trashed'  => $_POST['f_trashed']  ?? '',
        'page'     => $_POST['f_page']     ?? '',
        'msg'      => $msg,
        'err'      => $err,
    ], fn($v) => $v !== '' && $v !== null);

    redirect(base_url('admin/products.php' . ($keep ? '?' . http_build_query($keep) : '')));
}

$filters = [
    'q'           => trim((string)($_GET['q'] ?? '')),
    'status'      => $_GET['status'] ?? '',
    'pixel_id'    => $_GET['pixel_id'] ?? '',
    'category_id' => $_GET['category_id'] ?? '',
    'trashed'     => $_GET['trashed'] ?? '',
];
$res = Product::paginate($filters, max(1, (int)($_GET['page'] ?? 1)), 25);

admin_render('products', [
    'title'   => 'المنتجات',
    'res'     => $res,
    'filters' => $filters,
    'pixels'  => Pixel::grouped(),
    'cats'    => db()->query("SELECT id, name FROM categories ORDER BY position, name")->fetchAll(),
    'trashCount' => Product::trashCount(),
    'msg'     => $_GET['msg'] ?? null,
    'err'     => $_GET['err'] ?? (isset($_GET['clone_err']) ? 'فشل نسخ المنتج. تحقق من السجلات.' : null),
]);
