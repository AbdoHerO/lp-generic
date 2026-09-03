<?php
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../src/Models/Pixel.php';
admin_require_admin();

$msg = null;
$err = null;
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id       = (int)($_POST['id'] ?? 0) ?: null;
        $pixelId  = clean_string($_POST['pixel_id'] ?? '', 80);
        $platform = $_POST['platform'] ?? '';

        if ($pixelId === '') {
            $err = 'الرجاء إدخال معرف البكسل';
        } elseif (!in_array($platform, Pixel::PLATFORMS, true)) {
            $err = 'المنصة غير صالحة';
        } else {
            try {
                $savedId = Pixel::save($_POST, $id);
                Activity::log($id ? 'update' : 'create', 'pixel', $savedId,
                              $platform . ' · ' . $pixelId);
                redirect(base_url('admin/pixels.php?saved=1'));
            } catch (PDOException $e) {
                // Unique key on (platform, pixel_id).
                $err = ((int)$e->getCode() === 23000)
                    ? 'هذا البكسل مُسجَّل بالفعل لنفس المنصة'
                    : 'تعذر الحفظ، حاول مرة أخرى';
            }
        }
    }

    if ($action === 'delete') {
        $id  = (int)($_POST['id'] ?? 0);
        $row = Pixel::find($id);
        Pixel::delete($id);
        Activity::log('delete', 'pixel', $id, ($row['name'] ?? '') . ' · ' . ($row['pixel_id'] ?? ''));
        redirect(base_url('admin/pixels.php?deleted=1'));
    }

    if ($action === 'make_default') {
        $row = Pixel::find((int)($_POST['id'] ?? 0));
        if ($row) Pixel::makeDefault((int)$row['id'], $row['platform']);
        redirect(base_url('admin/pixels.php?saved=1'));
    }
}

if (!empty($_GET['edit'])) {
    $editing = Pixel::find((int)$_GET['edit']);
}

// Which landing pages point at each pixel — the answer an advertiser actually
// needs before disabling or deleting one.
$usage = [];
foreach (db()->query("SELECT id, title, slug, fb_pixel_id, tt_pixel_id FROM products ORDER BY title")->fetchAll() as $p) {
    foreach (['fb_pixel_id', 'tt_pixel_id'] as $col) {
        if ($p[$col] !== null && (int)$p[$col] > 0) $usage[(int)$p[$col]][] = $p;
    }
}

admin_render('pixels', [
    'title'   => 'البكسلات',
    'grouped' => Pixel::grouped(),
    'editing' => $editing,
    'usage'   => $usage,
    'msg'     => $msg ?? (isset($_GET['saved']) ? 'تم الحفظ' : (isset($_GET['deleted']) ? 'تم الحذف' : null)),
    'err'     => $err,
    'legacy'  => [
        'facebook' => trim((string)settings_get('fb_pixel_id', '')),
        'tiktok'   => trim((string)settings_get('tiktok_pixel_id', '')),
    ],
]);
