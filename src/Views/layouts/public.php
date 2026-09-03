<?php
$store  = settings_get('store_name', 'متجر');
$accent = settings_get('accent_color', '#0e7c7b');
$gtm    = settings_get('gtm_id', '');
$ga     = settings_get('ga_id', '');
?><!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=5">
<title><?= e($title ?? $store) ?> · <?= e($store) ?></title>
<meta name="description" content="<?= e($metaDesc ?? 'تسوق بأفضل الأسعار مع الدفع عند الاستلام') ?>">
<meta property="og:title" content="<?= e($title ?? $store) ?>">
<meta property="og:description" content="<?= e($metaDesc ?? '') ?>">
<meta property="og:site_name" content="<?= e($store) ?>">
<meta property="og:type" content="website">
<?php if (!empty($ogImage)): ?>
<meta property="og:image" content="<?= e(upload_url($ogImage)) ?>">
<meta name="twitter:card" content="summary_large_image">
<?php endif; ?>
<meta name="theme-color" content="<?= e($accent) ?>">
<?php
// Canonical: a landing page reached with ?fbclid=… &utm_source=… is the same
// page, and without this every ad variant looks like duplicate content.
$__origin    = (request_is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
$__canonical = $canonical ?? ($__origin . strtok($_SERVER['REQUEST_URI'] ?? '/', '?'));
?>
<link rel="canonical" href="<?= e($__canonical) ?>">
<?php if (!empty($noindex)): ?>
<meta name="robots" content="noindex,nofollow">
<?php endif; ?>
<meta property="og:url" content="<?= e($__canonical) ?>">
<link rel="icon" href="<?= e(store_favicon_url()) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(store_favicon_url()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/theme.css') ?>">
<link rel="stylesheet" href="<?= asset('css/home.css') ?>">
<link rel="stylesheet" href="<?= asset('css/product.css') ?>">
<style>
:root{ --accent: <?= e($pageAccent ?? $accent) ?>; }
<?php if (!empty($pageCta)): ?>
/* Per-page CTA colour: matches the button in the ad creative. */
.btn-buy, .cd-cta, .sc-btn{ background: <?= e($pageCta) ?>; box-shadow: 0 8px 22px <?= e($pageCta) ?>40; }
.btn-buy:hover, .cd-cta:hover, .sc-btn:hover{ filter: brightness(.92); }
<?php endif; ?>
</style>
<?php if ($gtm): ?>
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?= e($gtm) ?>');</script>
<?php endif; ?>
<?php if ($ga): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= e($ga) ?>');</script>
<?php endif; ?>
<?php include __DIR__ . '/../partials/pixels-head.php'; ?>
</head>
<body>
<?php if ($gtm): ?>
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?= e($gtm) ?>" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<?php endif; ?>
<?php include __DIR__ . '/../partials/header.php'; ?>
<main class="page"><?= $content ?></main>
<?php include __DIR__ . '/../partials/footer.php'; ?>
<script src="<?= asset('js/home.js') ?>" defer></script>
<script src="<?= asset('js/product.js') ?>" defer></script>
</body>
</html>
