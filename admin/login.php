<?php
require __DIR__ . '/_bootstrap.php';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? null)) { $error = 'انتهت الجلسة، حاول مرة أخرى'; }
    else {
        $u = trim($_POST['username'] ?? '');
        $p = (string)($_POST['password'] ?? '');
        $st = db()->prepare("SELECT * FROM admins WHERE username = :u LIMIT 1");
        $st->execute([':u' => $u]);
        $admin = $st->fetch();
        if ($admin && password_verify($p, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']       = (int)$admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            unset($_SESSION['csrf']);
            redirect(base_url('admin/index.php'));
        } else {
            $error = 'بيانات الدخول غير صحيحة';
        }
    }
}
?><!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>تسجيل الدخول · الإدارة</title>
<link rel="stylesheet" href="<?= asset('css/theme.css') ?>">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body class="admin-login">
<form method="post" class="login-card">
  <h1>دخول الإدارة</h1>
  <?php if ($error): ?><div class="al err"><?= e($error) ?></div><?php endif; ?>
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <label>اسم المستخدم<input name="username" required autofocus></label>
  <label>كلمة المرور<input type="password" name="password" required></label>
  <button class="btn-buy" type="submit">دخول</button>
  <p class="tip">المستخدم الافتراضي: <code>admin</code> / <code>admin123</code> — قم بتغييره فوراً.</p>
</form>
</body></html>
