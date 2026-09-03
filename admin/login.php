<?php
require __DIR__ . '/_bootstrap.php';

// Sign-in throttling. The username is known (`admin`), so without this the only
// thing between an attacker and the panel is the password's entropy. Bucketed
// by username AND address so one attacker cannot lock a real operator out by
// failing logins on their behalf from elsewhere.
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_WINDOW       = 900;   // 15 minutes

$error   = null;
$blocked = false;
$retryIn = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u      = trim($_POST['username'] ?? '');
    $bucket = 'login:' . mb_substr($u, 0, 30) . ':' . Throttle::ip();

    if (Throttle::tooMany($bucket, LOGIN_MAX_ATTEMPTS, LOGIN_WINDOW)) {
        $blocked = true;
        $retryIn = Throttle::retryAfter($bucket, LOGIN_WINDOW);
        $error   = 'محاولات كثيرة. حاول بعد ' . max(1, (int)ceil($retryIn / 60)) . ' دقيقة.';
    } elseif (!csrf_check($_POST['_csrf'] ?? null)) {
        $error = 'انتهت الجلسة، حاول مرة أخرى';
    } else {
        $p     = (string)($_POST['password'] ?? '');
        $admin = Admin::findByUsername($u);

        // A disabled account must fail exactly like a wrong password: saying
        // "this account is disabled" confirms the username exists.
        $ok = $admin
            && password_verify($p, $admin['password_hash'])
            && (int)($admin['status'] ?? 1) === 1;

        if ($ok) {
            Throttle::clear($bucket);
            session_regenerate_id(true);
            $_SESSION['admin_id']       = (int)$admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role']     = $admin['role'] ?? 'admin';
            unset($_SESSION['csrf']);

            Admin::touchLogin((int)$admin['id']);
            Activity::log('login', 'admin', (int)$admin['id'], $admin['username']);

            // An agent has no dashboard worth landing on.
            redirect(base_url(($admin['role'] ?? 'admin') === 'agent'
                ? 'admin/leads.php' : 'admin/index.php'));
        }

        // Recorded whether or not the username exists, so the response time and
        // the lockout behaviour do not reveal which usernames are real.
        Throttle::hit($bucket);
        $left = LOGIN_MAX_ATTEMPTS - Throttle::count($bucket, LOGIN_WINDOW);
        $error = 'بيانات الدخول غير صحيحة'
               . ($left > 0 && $left <= 2 ? " — تبقّى {$left} محاولة قبل الحظر المؤقت" : '');
    }
}
?><!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>تسجيل الدخول · الإدارة</title>
<meta name="robots" content="noindex,nofollow">
<link rel="icon" href="<?= e(store_favicon_url()) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= asset('css/theme.css') ?>">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body class="admin-login">
<form method="post" class="login-card">
  <?php $__logo = store_logo_url(); ?>
  <?php if ($__logo): ?>
    <img class="login-logo" src="<?= e($__logo) ?>" alt="<?= e(settings_get('store_name', 'متجر')) ?>">
  <?php else: ?>
    <h1>دخول الإدارة</h1>
  <?php endif; ?>
  <?php if ($error): ?><div class="al err"><?= e($error) ?></div><?php endif; ?>
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <label>اسم المستخدم<input name="username" required autofocus autocomplete="username"
         value="<?= e($blocked ? '' : ($_POST['username'] ?? '')) ?>" <?= $blocked ? 'disabled' : '' ?>></label>
  <label>كلمة المرور<input type="password" name="password" required autocomplete="current-password"
         <?= $blocked ? 'disabled' : '' ?>></label>
  <button class="btn-buy" type="submit" <?= $blocked ? 'disabled' : '' ?>>دخول</button>
</form>
</body></html>
