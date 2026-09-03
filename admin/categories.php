<?php
require __DIR__ . '/_bootstrap.php';
admin_require_admin();

// Categories had no admin screen at all — rows had to be inserted with SQL,
// which meant in practice nobody ever added one.
$msg = null;
$err = null;
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    $pdo    = db();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = clean_string($_POST['name'] ?? '', 120);

        // Slug: taken from the field, else derived from the name. Arabic names
        // transliterate to nothing useful, so a name-only category falls back
        // to a stable generated slug rather than an empty one.
        $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
        if ($slug === '') $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        if ($slug === '') $slug = 'cat-' . substr(bin2hex(random_bytes(4)), 0, 6);

        if ($name === '') {
            $err = 'الاسم مطلوب';
        } else {
            try {
                if ($id) {
                    $pdo->prepare("UPDATE categories SET name=:n, slug=:s, position=:p WHERE id=:i")
                        ->execute([':n' => $name, ':s' => $slug, ':p' => (int)($_POST['position'] ?? 0), ':i' => $id]);
                } else {
                    $pdo->prepare("INSERT INTO categories (name, slug, position) VALUES (:n,:s,:p)")
                        ->execute([':n' => $name, ':s' => $slug, ':p' => (int)($_POST['position'] ?? 0)]);
                }
                Activity::log($id ? 'update' : 'create', 'category', $id ?: (int)$pdo->lastInsertId(), $name);
                redirect(base_url('admin/categories.php?saved=1'));
            } catch (PDOException $e) {
                $err = (int)$e->getCode() === 23000
                    ? 'الرابط (slug) مستعمل بالفعل في فئة أخرى'
                    : 'تعذر الحفظ';
            }
        }
    }

    if ($action === 'delete') {
        // products.category_id is ON DELETE SET NULL, so the products survive
        // and simply become uncategorised.
        $delId = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM categories WHERE id=:i")->execute([':i' => $delId]);
        Activity::log('delete', 'category', $delId);
        redirect(base_url('admin/categories.php?deleted=1'));
    }
}

if (!empty($_GET['edit'])) {
    $st = db()->prepare("SELECT * FROM categories WHERE id=:i");
    $st->execute([':i' => (int)$_GET['edit']]);
    $editing = $st->fetch() ?: null;
}

$rows = db()->query(
    "SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
     FROM categories c ORDER BY c.position, c.name"
)->fetchAll();

admin_render('categories', [
    'title'   => 'الفئات',
    'rows'    => $rows,
    'editing' => $editing,
    'msg'     => $msg ?? (isset($_GET['saved']) ? 'تم الحفظ' : (isset($_GET['deleted']) ? 'تم الحذف' : null)),
    'err'     => $err,
]);
