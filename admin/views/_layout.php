<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title ?? 'الإدارة') ?> · لوحة التحكم</title>
<link rel="stylesheet" href="<?= asset('css/theme.css') ?>">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body class="admin">
<aside class="side">
  <div class="side-brand">◆ <?= e($store) ?></div>
  <nav>
    <a href="<?= base_url('admin/index.php') ?>"    class="<?= ($_view==='dashboard'?'active':'') ?>">لوحة التحكم</a>
    <a href="<?= base_url('admin/products.php') ?>" class="<?= (in_array($_view,['products','product-edit'])?'active':'') ?>">المنتجات</a>
    <a href="<?= base_url('admin/leads.php') ?>"    class="<?= (in_array($_view,['leads','lead-detail'])?'active':'') ?>">الطلبات</a>
    <a href="<?= base_url('admin/settings.php') ?>" class="<?= ($_view==='settings'?'active':'') ?>">الإعدادات</a>
  </nav>
  <div class="side-bottom">
    <span><?= e(admin_username()) ?></span>
    <a href="<?= base_url('admin/logout.php') ?>">خروج</a>
  </div>
</aside>
<main class="main">
  <header class="top"><h1><?= e($title) ?></h1></header>
  <div class="wrap"><?= $content ?></div>
</main>
</body></html>
