<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title ?? 'الإدارة') ?> · لوحة التحكم</title>
<link rel="stylesheet" href="<?= asset('css/theme.css') ?>">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body class="admin">
<aside class="side" id="adminSide">
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
<div class="side-overlay" id="sideOverlay"></div>
<main class="main">
  <header class="top">
    <button type="button" class="side-toggle" id="sideToggle" aria-label="القائمة" aria-controls="adminSide">
      <span></span><span></span><span></span>
    </button>
    <h1><?= e($title) ?></h1>
  </header>
  <div class="wrap"><?= $content ?></div>
</main>
<script>
(function(){
  var btn = document.getElementById('sideToggle');
  var side = document.getElementById('adminSide');
  var ov = document.getElementById('sideOverlay');
  if(!btn||!side||!ov) return;
  function open(){ side.classList.add('open'); ov.classList.add('show'); document.body.classList.add('side-open'); }
  function close(){ side.classList.remove('open'); ov.classList.remove('show'); document.body.classList.remove('side-open'); }
  btn.addEventListener('click', function(){ side.classList.contains('open') ? close() : open(); });
  ov.addEventListener('click', close);
  side.querySelectorAll('nav a').forEach(function(a){ a.addEventListener('click', close); });
  window.addEventListener('keydown', function(e){ if(e.key==='Escape') close(); });
})();
</script>
</body></html>
