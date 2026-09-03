<?php
$slider  = array_values(array_filter($media, fn($m) => $m['kind'] === 'slider'));
$gallery = array_values(array_filter($media, fn($m) => $m['kind'] === 'gallery'));
$hero       = $sections['hero']         ?? [];
$features   = $sections['features']     ?? [];
$tests      = $sections['testimonials'] ?? [];
$faqs       = $sections['faqs']         ?? [];
// ?: not ??  — the editor always writes these keys, so "left blank" arrives as
// an empty string rather than a missing key, and must still fall back.
$cdTitle    = ($sections['countdown_title'] ?? '') ?: 'تخفيض 50% و الشحن السريع بالمجان';
$ctaTxt     = ($sections['cta_text']        ?? '') ?: 'إضغط هنا لطلب المنتج';
$banner     = settings_get('header_banner', 'التوصيل مجاني لجميع أنحاء المغرب');
$cdHours    = (int)settings_get('countdown_hours', '25');
// A real campaign deadline beats a rolling timer that resets itself forever:
// when one is set, the page counts down to it and stops.
$cdEndsAt   = !empty($product['campaign_ends_at']) ? strtotime($product['campaign_ends_at']) : null;
$cdExpired  = $cdEndsAt !== null && $cdEndsAt <= time();
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
<?php
// Schema.org for the landing page. Rendered in the body rather than the head
// because it needs $offers and $media, which only this view receives.
$canonical = (request_is_https() ? 'https' : 'http') . '://'
           . ($_SERVER['HTTP_HOST'] ?? '') . base_url($product['slug']);
include __DIR__ . '/partials/structured-data.php';
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
            <?= responsive_img($m['url'], [
                  'class'   => 'p-slide ' . ($i === 0 ? 'active' : ''),
                  'alt'     => $product['title'],
                  // The first slide is the largest thing above the fold, so it
                  // loads eagerly and at high priority; the rest wait.
                  'loading' => $i === 0 ? 'eager' : 'lazy',
                  'sizes'   => '(max-width: 700px) 100vw, 640px',
                ]) ?>
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
        <?= responsive_img($product['cover_image'] ?: 'public/assets/img/placeholder.svg', [
              'class' => 'p-slide active', 'alt' => $product['title'],
              'loading' => 'eager', 'sizes' => '(max-width: 700px) 100vw, 640px',
            ]) ?>
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
      <h1 class="p-title"><?= e(($hero['headline'] ?? '') ?: $product['title']) ?></h1>
      <p class="p-sub"><?= e(($hero['subheadline'] ?? '') ?: $product['short_desc']) ?></p>

      <?php if (!empty($hero['badges'])): ?>
      <ul class="p-mini">
        <?php foreach ($hero['badges'] as $b): ?><li>✦ <?= e($b) ?></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <a class="p-jump" href="#orderForm"><?= e(($hero['cta'] ?? '') ?: 'اطلب الآن') ?></a>
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

    <?php /* Anti-bot. Both are invisible to shoppers: the honeypot is hidden by
             CSS and skipped by the tab order, and the stamp is HMAC-signed so a
             script cannot fabricate a plausible fill time. */ ?>
    <div class="hp-field" aria-hidden="true">
      <label>لا تملأ هذا الحقل
        <input type="text" name="website" tabindex="-1" autocomplete="off" value="">
      </label>
    </div>
    <input type="hidden" name="form_ts" value="<?= e(form_token()) ?>">
    <?php if (!empty($abVariant)): ?>
    <input type="hidden" name="ab_variant" value="<?= e($abVariant) ?>">
    <?php endif; ?>

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
      <label>المدينة
        <input type="text" name="city" list="moroccoCities" autocomplete="address-level2"
               placeholder="الدار البيضاء">
      </label>
      <datalist id="moroccoCities">
        <?php foreach (morocco_cities() as $__city): ?>
          <option value="<?= e($__city) ?>"></option>
        <?php endforeach; ?>
      </datalist>
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
      <?= responsive_img($g['url'], ['alt' => $product['title'], 'sizes' => '(max-width: 700px) 100vw, 340px']) ?>
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

<?php if (!$cdExpired): ?>
<section class="p-countdown" id="countdown" data-hours="<?= $cdHours ?>"
         <?= $cdEndsAt ? 'data-ends="' . (int)$cdEndsAt . '"' : '' ?>>
  <h2 class="cd-title"><?= e($cdTitle) ?></h2>
  <div class="cd-grid">
    <div class="cd-cell"><div class="cd-num" id="cdD">00</div><div class="cd-lbl">يوم</div></div>
    <div class="cd-cell"><div class="cd-num" id="cdH">00</div><div class="cd-lbl">ساعة</div></div>
    <div class="cd-cell"><div class="cd-num" id="cdM">00</div><div class="cd-lbl">دقيقة</div></div>
    <div class="cd-cell"><div class="cd-num" id="cdS">00</div><div class="cd-lbl">ثانية</div></div>
  </div>
  <a href="#orderForm" class="cd-cta">احصل عليه الآن</a>
</section>
<?php endif; ?>

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
      <div class="pc-media"><?= responsive_img($rp['cover_image'] ?: 'public/assets/img/placeholder.svg',
            ['alt' => $rp['title'], 'sizes' => '(max-width: 700px) 50vw, 260px']) ?></div>
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
    slug:  <?= json_encode($product['slug'], JSON_UNESCAPED_UNICODE) ?>,
    title: <?= json_encode($product['title'], JSON_UNESCAPED_UNICODE) ?>,
    currency: 'MAD',
    draftUrl: <?= json_encode(base_url('lead/draft')) ?>,
    offers: <?= json_encode($jsOffers, JSON_UNESCAPED_UNICODE) ?>,
    groups: <?= json_encode($jsGroups, JSON_UNESCAPED_UNICODE) ?>
  };

  /* ViewContent — fired for whichever pixels this landing page is assigned to.
     The slug is the content id, so Meta/TikTok catalogues line up with the URL. */
  (function () {
    function fire() {
      if (!window.LPX) return;
      window.LPX.track('view_content', {
        id: window.PRODUCT_DATA.slug,
        name: window.PRODUCT_DATA.title,
        value: <?= json_encode((float)($offers[0]['total_price'] ?? $product['base_price'])) ?>,
        currency: 'MAD'
      });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fire);
    else fire();
  })();
</script>
<?php if (settings_get('protect_images', '1') === '1'): ?>
<script>
/* Image protection.
   Scoped to images on purpose. The previous version blocked right-click,
   F12, Ctrl+U/S/P, copy and text selection across the whole page, which
   stopped nobody who wanted the source — it is one `curl` away — while
   preventing shoppers from copying the phone number, saving the page or
   printing an order. Dragging and long-pressing a product photo is the only
   part worth discouraging, so that is the only part still handled. */
(function () {
  document.addEventListener('dragstart', function (e) {
    if (e.target && e.target.tagName === 'IMG') e.preventDefault();
  });
  document.addEventListener('contextmenu', function (e) {
    if (e.target && e.target.tagName === 'IMG') e.preventDefault();
  });
})();
</script>
<?php endif; ?>
