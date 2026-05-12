<section class="hero-home container">
  <div class="hero-eyebrow">مجموعة موسم جديدة</div>
  <h1 class="hero-title">منتجات منتقاة بعناية<br><span class="accent">لتجربة استثنائية</span></h1>
  <p class="hero-sub">تشكيلة عصرية بأسعار مدروسة، مع الدفع عند الاستلام وشحن سريع لكل المغرب.</p>
</section>

<?php if (!empty($cats)): ?>
<nav class="cat-bar container" aria-label="الفئات">
  <a href="<?= base_url('/') ?>" class="<?= empty($currentCat) ? 'active' : '' ?>">كل المنتجات</a>
  <?php foreach ($cats as $c): ?>
    <a href="<?= base_url('category/' . $c['slug']) ?>" class="<?= ($currentCat['id'] ?? null) == $c['id'] ? 'active' : '' ?>"><?= e($c['name']) ?></a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>

<section class="container">
  <?php if (empty($products)): ?>
    <p class="empty">لا توجد منتجات حالياً.</p>
  <?php else: ?>
  <div class="product-grid">
    <?php foreach ($products as $p):
      $img = $p['cover_image'] ? upload_url($p['cover_image']) : asset('img/placeholder.svg');
      $badges = array_filter(array_map('trim', explode(',', $p['badges'] ?? '')));
    ?>
    <a class="product-card" href="<?= base_url($p['slug']) ?>">
      <div class="pc-media">
        <img src="<?= e($img) ?>" alt="<?= e($p['title']) ?>" loading="lazy">
        <?php if ($badges): ?>
        <div class="pc-badges">
          <?php foreach ($badges as $b): ?><span class="pc-badge"><?= e($b) ?></span><?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="pc-body">
        <h3 class="pc-title"><?= e($p['title']) ?></h3>
        <p class="pc-hook"><?= e($p['short_desc']) ?></p>
        <div class="pc-price">
          <span class="pc-price-now">من <?= e(number_format((float)$p['base_price'],2)) ?> د.م</span>
          <?php if (!empty($p['compare_price']) && $p['compare_price'] > $p['base_price']): ?>
            <span class="pc-price-old"><?= e(number_format((float)$p['compare_price'],2)) ?></span>
          <?php endif; ?>
        </div>
        <span class="pc-cta">اطلب الآن ←</span>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
