<?php
require __DIR__ . '/_bootstrap.php';
admin_require_admin();

$msg = null;
$err = null;
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id  = (int)($_POST['id'] ?? 0) ?: null;
        $res = Admin::save($_POST, $id);
        if ($res['ok']) {
            Activity::log($id ? 'update' : 'create', 'admin', $res['id'],
                          clean_string($_POST['username'] ?? '', 80) . ' · ' . ($_POST['role'] ?? ''));
            redirect(base_url('admin/users.php?saved=1'));
        }
        $err = $res['error'];
    }

    if ($action === 'delete') {
        $id  = (int)($_POST['id'] ?? 0);
        $row = Admin::find($id);
        $res = Admin::delete($id, (int)admin_id());
        if ($res['ok']) {
            Activity::log('delete', 'admin', $id, $row['username'] ?? '');
            redirect(base_url('admin/users.php?deleted=1'));
        }
        $err = $res['error'];
    }
}

if (!empty($_GET['edit'])) $editing = Admin::find((int)$_GET['edit']);

admin_render('users', [
    'title'   => 'المستخدمون',
    'rows'    => Admin::all(),
    'editing' => $editing,
    'msg'     => $msg ?? (isset($_GET['saved']) ? 'تم الحفظ' : (isset($_GET['deleted']) ? 'تم الحذف' : null)),
    'err'     => $err,
]);
