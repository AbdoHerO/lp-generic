<?php
$slider  = array_values(array_filter($media, fn($m) => $m['kind'] === 'slider'));
$gallery = array_values(array_filter($media, fn($m) => $m['kind'] === 'gallery'));
$hero       = $sections['hero']         ?? [];
$features   = $sections['features']     ?? [];
$tests      = $sections['testimonials'] ?? [];
$faqs       = $sections['faqs']         ?? [];
$cdTitle    = $sections['countdown_title'] ?? 'تخفيض 50% و الشحن السريع بالمجان';
$ctaTxt     = $sections['cta_text']        ?? 'إضغط هنا لطلب المنتج';
$banner     = settings_get('header_banner', 'التوصيل مجاني لجميع أنحاء المغرب');
$cdHours    = (int)settings_get('countdown_hours', '25');
$isAdminPreview = !empty($_GET['preview']) && !empty($_SESSION['admin_id']);

// JSON for JS (offers + groups)
$jsOffers = array_map(fn($o) => [
    'id'              => (int)$o['id'],
    'label'           => $o['label'],
    'quantity'        => (int)$o['quantity'],
    'total_price'     => (float)$o['total_price'],
    'compare_price'   => $o['compare_price'] !== null ? (float)$o['compare_price'] : null,
    'is_recommended'  => (int)$o['is_recommended'],
    'free_shipping'   => (int)$o['free_shipping'],
    'is_default'      => (int)$o['is_default'],
    'requires_options'=> (int)$o['requires_options'],
], $offers);

$jsGroups = array_map(fn($g) => [
    'id'    => (int)$g['id'],
    'name'  => $g['name'],
    'label' => $g['label'],
    'type'  => $g['type'],
    'is_required' => (int)$g['is_required'],
    'values'=> array_map(fn($v) => ['value'=>$v['value'], 'swatch'=>$v['swatch']], $g['values']),
], $groups);

// First offer headline (for "واحد ب 249 درهم و إثنان ب459 درهم فقط")
$firstTwo = array_slice($offers, 0, 2);
$specialLine = '';
if (count($firstTwo) >= 2) {
    $specialLine = $firstTwo[0]['label'] . ' و ' . $firstTwo[1]['label'];
} elseif ($firstTwo) {
    $specialLine = $firstTwo[0]['label'];
}
?>
<?php if ($isAdminPreview): ?>
<div class="admin-preview-bar">
  وضع المعاينة (مدير) — هذه الصفحة قد تكون غير منشورة. <a href="<?= base_url('admin/product-edit.php?id=' . (int)$product['id']) ?>">العودة للتحرير</a>
</div>
<?php endif; ?>

<?php if ($banner): ?><div class="top-banner"><?= e($banner) ?></div><?php endif; ?>

<section class="p-hero">
  <div class="p-hero-inner">
    <div class="p-slider" id="pSlider">
      <?php if ($slider): ?>
        <div class="p-slides">
          <?php foreach ($slider as $i => $m): ?>
            <img class="p-slide <?= $i===0?'active':'' ?>" src="<?= e(upload_url($m['url'])) ?>" alt="<?= e($product['title']) ?>" loading="<?= $i===0?'eager':'lazy' ?>">
          <?php endforeach; ?>
        </div>
        <?php if (count($slider) > 1): ?>
        <button class="p-nav prev" aria-label="السابق">›</button>
        <button class="p-nav next" aria-label="التالي">‹</button>
        <div class="p-dots">
          <?php foreach ($slider as $i => $m): ?>
            <span class="p-dot <?= $i===0?'active':'' ?>" data-i="<?= $i ?>"></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      <?php else: ?>
        <img class="p-slide active" src="<?= e($product['cover_image'] ? upload_url($product['cover_image']) : asset('img/placeholder.svg')) ?>" alt="<?= e($product['title']) ?>">
      <?php endif; ?>
    </div>

    <div class="p-headline">
      <?php if (!empty($product['badges'])): ?>
      <div class="p-badges">
        <?php foreach (array_filter(array_map('trim', explode(',', $product['badges']))) as $b): ?>
          <span class="badge"><?= e($b) ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <h1 class="p-title"><?= e($hero['headline'] ?? $product['title']) ?></h1>
      <p class="p-sub"><?= e($hero['subheadline'] ?? $product['short_desc']) ?></p>

      <?php if (!empty($hero['badges'])): ?>
      <ul class="p-mini">
        <?php foreach ($hero['badges'] as $b): ?><li>✦ <?= e($b) ?></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <a class="p-jump" href="#orderForm"><?= e($hero['cta'] ?? 'اطلب الآن') ?></a>
    </div>
  </div>
</section>

<section class="p-order" id="orderForm">
  <form method="post" action="<?= base_url('lead/submit') ?>" id="leadForm" class="lead-form" novalidate>
    <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
    <input type="hidden" name="offer_id" id="offerIdInput" value="">
    <input type="hidden" name="utm_source"   value="<?= e($_GET['utm_source']   ?? '') ?>">
    <input type="hidden" name="utm_medium"   value="<?= e($_GET['utm_medium']   ?? '') ?>">
    <input type="hidden" name="utm_campaign" value="<?= e($_GET['utm_campaign'] ?? '') ?>">
    <input type="hidden" name="fbclid"       value="<?= e($_GET['fbclid']       ?? '') ?>">
    <input type="hidden" name="ttclid"       value="<?= e($_GET['ttclid']       ?? '') ?>">
    <input type="hidden" name="gclid"        value="<?= e($_GET['gclid']        ?? '') ?>">

    <div class="offer-headline">
      <span class="of-special">عرض خاص !</span>
      <?php if ($specialLine): ?><span class="of-tag"><?= e($specialLine) ?></span><?php endif; ?>
    </div>

    <div class="price-hero" id="priceHero">—</div>

    <div class="offers" id="offersList"><!-- rendered by JS --></div>

    <div class="customer">
      <div class="row">
        <label>الإسم الكامل
          <input type="text" name="fullname" required minlength="3" placeholder="مثال: محمد العلوي">
        </label>
        <label>رقم الهاتف
          <input type="tel" name="phone" required inputmode="tel" placeholder="06XXXXXXXX" pattern="0[6-7][0-9]{8}">
        </label>
      </div>
      <label>العنوان (المدينة + الحي + الشارع)
        <input type="text" name="address" required placeholder="مثال: الدار البيضاء، حي السلام، شارع 12">
      </label>
      <input type="hidden" name="city" value="">
      <input type="hidden" name="notes" value="">
    </div>

    <button type="submit" class="btn-buy"><?= e($ctaTxt) ?></button>
    <p class="form-foot">بالضغط على الزر، سيتواصل معك فريقنا لتأكيد الطلب قبل الشحن — الدفع عند الاستلام.</p>
    <div id="formError" class="form-error" role="alert" hidden></div>
  </form>
</section>

<?php if ($gallery): ?>
<section class="p-gallery">
  <h2 class="sec-title" style="text-align:center">المنتج عن قرب</h2>
  <div class="gal-grid">
    <?php foreach ($gallery as $g): ?>
      <img src="<?= e(upload_url($g['url'])) ?>" loading="lazy" alt="<?= e($product['title']) ?>">
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($features): ?>
<section class="p-features">
  <h2 class="sec-title" style="text-align:center">لماذا هذا المنتج</h2>
  <div class="feat-grid">
    <?php foreach ($features as $f): ?>
      <div class="feat">
        <div class="feat-icon"><?= e($f['icon'] ?? '✦') ?></div>
        <h3><?= e($f['title'] ?? '') ?></h3>
        <p><?= e($f['text'] ?? '') ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($tests): ?>
<section class="p-tests">
  <h2 class="sec-title" style="text-align:center">أكثر من 750 زبون يثقون بنا</h2>
  <div class="tests-grid">
    <?php foreach ($tests as $t): ?>
      <article class="test-card">
        <div class="stars">★★★★★</div>
        <p>«<?= e($t['text'] ?? '') ?>»</p>
        <div class="t-name">— <?= e($t['name'] ?? 'عميل') ?></div>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="p-countdown" id="countdown" data-hours="<?= $cdHours ?>">
  <h2 class="cd-title"><?= e($cdTitle) ?></h2>
  <div class="cd-grid">
    <div class="cd-cell"><div class="cd-num" id="cdD">00</div><div class="cd-lbl">يوم</div></div>
    <div class="cd-cell"><div class="cd-num" id="cdH">00</div><div class="cd-lbl">ساعة</div></div>
    <div class="cd-cell"><div class="cd-num" id="cdM">00</div><div class="cd-lbl">دقيقة</div></div>
    <div class="cd-cell"><div class="cd-num" id="cdS">00</div><div class="cd-lbl">ثانية</div></div>
  </div>
  <a href="#orderForm" class="cd-cta">احصل عليه الآن</a>
</section>

<?php if ($faqs): ?>
<section class="p-faq">
  <h2 class="sec-title">أسئلة متكررة</h2>
  <div class="faq-list">
    <?php foreach ($faqs as $f): ?>
      <details class="faq-item">
        <summary><?= e($f['q'] ?? '') ?></summary>
        <p><?= e($f['a'] ?? '') ?></p>
      </details>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($related)): ?>
<section class="p-related">
  <h2 class="sec-title" style="text-align:center">قد يعجبك أيضاً</h2>
  <div class="product-grid">
    <?php foreach ($related as $rp):
      $img = $rp['cover_image'] ? upload_url($rp['cover_image']) : asset('img/placeholder.svg');
    ?>
    <a class="product-card" href="<?= base_url($rp['slug']) ?>">
      <div class="pc-media"><img src="<?= e($img) ?>" alt="<?= e($rp['title']) ?>" loading="lazy"></div>
      <div class="pc-body">
        <h3 class="pc-title"><?= e($rp['title']) ?></h3>
        <div class="pc-price"><span class="pc-price-now">من <?= e(number_format((float)$rp['base_price'],0)) ?> د.م</span></div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<div class="sticky-cta" id="stickyCta">
  <div class="sc-info"><span id="scLabel">اختر عرضاً</span> <strong id="scPrice"></strong></div>
  <a href="#orderForm" class="sc-btn">إشتري الآن</a>
</div>

<script>
  window.PRODUCT_DATA = {
    productId: <?= (int)$product['id'] ?>,
    title: <?= json_encode($product['title'], JSON_UNESCAPED_UNICODE) ?>,
    offers: <?= json_encode($jsOffers, JSON_UNESCAPED_UNICODE) ?>,
    groups: <?= json_encode($jsGroups, JSON_UNESCAPED_UNICODE) ?>
  };
</script>
<script>
(function(){
  /* ── Disable right-click context menu ── */
  document.addEventListener('contextmenu', function(e){ e.preventDefault(); });

  /* ── Disable devtools keyboard shortcuts ── */
  document.addEventListener('keydown', function(e){
    // F12
    if (e.key === 'F12') { e.preventDefault(); return false; }
    // Ctrl/Cmd + Shift + I / J / C  (inspector, console, element picker)
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && /^[IJC]$/i.test(e.key)) { e.preventDefault(); return false; }
    // Ctrl/Cmd + U  (view source)
    if ((e.ctrlKey || e.metaKey) && !e.shiftKey && e.key.toLowerCase() === 'u') { e.preventDefault(); return false; }
    // Ctrl/Cmd + S  (save page)
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); return false; }
    // Ctrl/Cmd + P  (print / inspect)
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'p') { e.preventDefault(); return false; }
  });

  /* ── Disable copy / cut outside form inputs ── */
  ['copy','cut'].forEach(function(ev){
    document.addEventListener(ev, function(e){
      var t = e.target;
      if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable)) return;
      e.preventDefault();
    });
  });

  /* ── Disable image drag-save ── */
  document.addEventListener('dragstart', function(e){
    if (e.target && e.target.tagName === 'IMG') e.preventDefault();
  });

  /* ── Disable long-press text selection on touch (image areas) ── */
  document.addEventListener('selectstart', function(e){
    var t = e.target;
    if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable)) return;
    e.preventDefault();
  });
})();
</script>
