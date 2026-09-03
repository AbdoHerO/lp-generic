<?php $__logo = store_logo_url(); ?>
<header class="site-header">
  <div class="container hdr">
    <a class="brand" href="<?= base_url('/') ?>">
      <?php if ($__logo): ?>
        <img class="brand-logo" src="<?= e($__logo) ?>" alt="<?= e(settings_get('store_name','متجر')) ?>">
      <?php else: ?>
        <span class="brand-mark">◆</span>
        <span class="brand-name"><?= e(settings_get('store_name','متجر')) ?></span>
      <?php endif; ?>
    </a>
    <form class="search" method="get" action="<?= base_url('search') ?>">
      <input type="search" name="q" placeholder="ابحث عن منتج..." value="<?= e($_GET['q'] ?? '') ?>">
      <button type="submit" aria-label="بحث">⌕</button>
    </form>
    <a class="hdr-cta" href="tel:<?= e(settings_get('support_phone','')) ?>">اتصل بنا</a>
  </div>
  <div class="trust-strip">
    <span>✦ الدفع عند الاستلام</span>
    <span>✦ شحن لكل المغرب</span>
    <span>✦ ضمان الجودة</span>
  </div>
</header>
