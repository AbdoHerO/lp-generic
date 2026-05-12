<?php
$store = settings_get('store_name', 'متجر');
$accent = settings_get('accent_color', '#0e7c7b');
$gtm = settings_get('gtm_id', '');
$fbpx = settings_get('fb_pixel_id', '');
$ttpx = settings_get('tiktok_pixel_id', '');
$ga  = settings_get('ga_id', '');
?><!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=5">
<title><?= e($title ?? $store) ?> · <?= e($store) ?></title>
<meta name="description" content="<?= e($metaDesc ?? 'تسوق بأفضل الأسعار مع الدفع عند الاستلام') ?>">
<meta property="og:title" content="<?= e($title ?? $store) ?>">
<meta property="og:description" content="<?= e($metaDesc ?? '') ?>">
<?php if (!empty($ogImage)): ?>
<meta property="og:image" content="<?= e(upload_url($ogImage)) ?>">
<?php endif; ?>
<meta name="theme-color" content="<?= e($accent) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/theme.css') ?>">
<link rel="stylesheet" href="<?= asset('css/home.css') ?>">
<link rel="stylesheet" href="<?= asset('css/product.css') ?>">
<style>:root{ --accent: <?= e($accent) ?>; }</style>
<?php if ($gtm): ?>
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?= e($gtm) ?>');</script>
<?php endif; ?>
<?php if ($fbpx): ?>
<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','<?= e($fbpx) ?>');fbq('track','PageView');</script>
<?php endif; ?>
</head>
<body>
<?php include __DIR__ . '/../partials/header.php'; ?>
<main class="page"><?= $content ?></main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
<script src="<?= asset('js/home.js') ?>" defer></script>
<script src="<?= asset('js/product.js') ?>" defer></script>
</body>
</html>
