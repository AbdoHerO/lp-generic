<?php if ($msg): ?><div class="al ok"><?= e($msg) ?></div><?php endif; ?>

<?php if (!$product && !empty($templates)): ?>
<section class="tpl-picker">
  <h3>ابدأ من قالب</h3>
  <p class="hint">
    القالب يملأ المحتوى ومجموعات الخيارات ومستويات العروض مسبقاً — تبقى عليك الصور والأسعار.
    أو تجاهله واملأ النموذج أسفله يدوياً.
  </p>
  <form method="post" class="tpl-form">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="from_template">
    <input type="hidden" name="template" id="tplChoice" value="">

    <label class="tpl-title">
      <span>اسم المنتج</span>
      <input name="title" id="tplTitle" required placeholder="مثال: سروال كاجوال كلاس">
    </label>

    <div class="tpl-grid">
      <?php foreach ($templates as $key => $t): ?>
        <button type="button" class="tpl-card" data-key="<?= e($key) ?>">
          <span class="tpl-icon"><?= e($t['icon'] ?? '📄') ?></span>
          <strong><?= e($t['label'] ?? $key) ?></strong>
          <small><?= e($t['description'] ?? '') ?></small>
          <span class="tpl-meta">
            <?= count($t['sections']['features'] ?? []) ?> مميزات ·
            <?= count($t['sections']['faqs'] ?? []) ?> أسئلة ·
            <?= count($t['offers'] ?? []) ?> عروض
          </span>
        </button>
      <?php endforeach; ?>
    </div>
  </form>
</section>

<script>
(function () {
  var form  = document.querySelector('.tpl-form');
  var title = document.getElementById('tplTitle');
  if (!form) return;

  form.querySelectorAll('.tpl-card').forEach(function (card) {
    card.addEventListener('click', function () {
      if (!title.value.trim()) {
        alert('اكتب اسم المنتج أولاً.');
        title.focus();
        return;
      }
      document.getElementById('tplChoice').value = card.dataset.key;
      form.submit();
    });
  });
})();
</script>

<hr style="margin:24px 0">
<h3 class="sec-title">أو أنشئ صفحة فارغة</h3>
<?php endif; ?>

<?php if (!empty($product['id']) && !empty($product['slug'])): ?>
<div class="page-actions">
  <a class="btn" target="_blank" href="<?= base_url($product['slug'] . '?preview=1') ?>">👁 معاينة في تبويب جديد</a>
  <a class="btn ghost" href="<?= base_url('admin/products.php') ?>">← العودة للقائمة</a>
</div>
<?php endif; ?>
<?php include __DIR__ . '/partials/live-preview.php'; ?>

<?php if ($product): ?>
  <?php include __DIR__ . '/partials/product-tabs.php'; ?>
<?php endif; ?>

<?php if (!$product): ?>
<div class="pe-steps">
  <div class="pe-step current"><span>1</span> المعلومات الأساسية</div>
  <div class="pe-step"><span>2</span> المحتوى والعروض والصور</div>
  <p class="hint">
    املأ الأساسيات واحفظ — عندها تُفتح بقية الأقسام (المحتوى، العروض، الصور، الحملة)
    ويظهر لك مؤشّر جاهزية يخبرك بما ينقص قبل النشر.
  </p>
</div>
<?php endif; ?>

<div class="pe-body">
<form method="post" enctype="multipart/form-data" class="form-grid" id="productForm">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="save">

  <div class="grp pe-panel" data-tab="basics">
    <h3>المعلومات الأساسية</h3>
    <label>العنوان <input name="title" required value="<?= e($product['title'] ?? '') ?>"></label>
    <label>الـSlug (رابط)
      <input name="slug" placeholder="my-product" value="<?= e($product['slug'] ?? '') ?>">
      <small>أحرف لاتينية صغيرة وأرقام وشرطات. مثال: <code>casual-pants</code></small>
    </label>
    <label>الفئة
      <select name="category_id">
        <option value="">-</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= ($product['category_id'] ?? null) == $c['id'] ? 'selected':'' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>وصف قصير <input name="short_desc" value="<?= e($product['short_desc'] ?? '') ?>" maxlength="500"></label>
    <label>الوصف الكامل <textarea name="full_desc" rows="4"><?= e($product['full_desc'] ?? '') ?></textarea></label>
    <div class="row2">
      <label>السعر الأساسي <input type="number" step="0.01" name="base_price" value="<?= e($product['base_price'] ?? '0') ?>"></label>
      <label>سعر المقارنة <input type="number" step="0.01" name="compare_price" value="<?= e($product['compare_price'] ?? '') ?>"></label>
    </div>
    <label>الشارات
      <div class="tag-input-wrap" id="badgesWrap">
        <input type="hidden" name="badges" id="badgesHidden" value="<?= e($product['badges'] ?? '') ?>">
        <input type="text" id="badgesTyping" class="tag-typing-input" placeholder="اكتب شارة واضغط Enter أو فاصلة..." autocomplete="off">
      </div>
    </label>
    <script>
    (function(){
      var wrap  = document.getElementById('badgesWrap');
      var input = document.getElementById('badgesTyping');
      var hidden= document.getElementById('badgesHidden');
      function getTags(){ return hidden.value.split(',').map(s=>s.trim()).filter(Boolean); }
      function setTags(arr){ hidden.value = arr.join(','); }
      function render(){
        wrap.querySelectorAll('.tag-chip').forEach(el=>el.remove());
        getTags().forEach(function(tag){
          var chip = document.createElement('span');
          chip.className = 'tag-chip';
          chip.innerHTML = tag + '<button type="button" class="rm" aria-label="حذف">×</button>';
          chip.querySelector('.rm').onclick = function(){ setTags(getTags().filter(t=>t!==tag)); render(); };
          wrap.insertBefore(chip, input);
        });
      }
      function addTag(val){
        var v = val.trim().replace(/,$/,'').trim();
        if (!v) return;
        var tags = getTags();
        if (!tags.includes(v)) { tags.push(v); setTags(tags); render(); }
      }
      input.addEventListener('keydown', function(e){
        if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addTag(input.value); input.value=''; }
        if (e.key === 'Backspace' && input.value === '') { var t=getTags(); t.pop(); setTags(t); render(); }
      });
      input.addEventListener('blur', function(){ if(input.value.trim()){ addTag(input.value); input.value=''; } });
      wrap.addEventListener('click', function(){ input.focus(); });
      render();
    })();
    </script>
    <label class="cb"><input type="checkbox" name="status" <?= !empty($product['status']) || !$product ? 'checked':'' ?>> منتج نشط</label>
  </div>

  <div class="grp pe-panel" data-tab="basics">
    <h3>صورة الغلاف والـSEO</h3>
    <label>صورة الغلاف
      <?php if (!empty($product['cover_image'])): ?><img class="thumb" src="<?= e(upload_url($product['cover_image'])) ?>"><?php endif; ?>
      <input type="file" name="cover_image" accept="image/*">
      <span class="or-sep">— أو —</span>
      <div class="url-input-wrap">
        <span class="url-pfx">🔗</span>
        <input type="url" name="cover_image_url" placeholder="https://..." value="<?= preg_match('#^https?://#', $product['cover_image'] ?? '') ? e($product['cover_image']) : '' ?>">
      </div>
    </label>
    <label>صورة Open Graph
      <?php if (!empty($product['og_image'])): ?><img class="thumb" src="<?= e(upload_url($product['og_image'])) ?>"><?php endif; ?>
      <input type="file" name="og_image" accept="image/*">
      <span class="or-sep">— أو —</span>
      <div class="url-input-wrap">
        <span class="url-pfx">🔗</span>
        <input type="url" name="og_image_url" placeholder="https://..." value="<?= preg_match('#^https?://#', $product['og_image'] ?? '') ? e($product['og_image']) : '' ?>">
      </div>
    </label>
    <label>عنوان SEO <input name="seo_title" value="<?= e($product['seo_title'] ?? '') ?>"></label>
    <label>وصف SEO <input name="seo_description" value="<?= e($product['seo_description'] ?? '') ?>"></label>
  </div>

  <div class="grp wide px-picker pe-panel" data-tab="campaign">
    <h3>التتبع والبكسلات</h3>
    <p class="hint">
      اختر البكسل الذي يستقبل أحداث <strong>هذه الصفحة</strong> فقط. هكذا يمكنك تشغيل إعلان ميتا بحساب،
      وإعلان تيك توك بحساب آخر، لكل صفحة هبوط على حدة.
      تُدار القائمة من <a href="<?= base_url('admin/pixels.php') ?>">صفحة البكسلات</a>.
    </p>
    <div class="row2">
      <?php foreach ([
            ['facebook', 'fb_pixel_id', 'بكسل Meta / Facebook'],
            ['tiktok',   'tt_pixel_id', 'بكسل TikTok'],
          ] as [$__platform, $__field, $__label]):
        $__current = $product[$__field] ?? null;   // null = inherit, 0 = off, N = pixels.id
        $__list    = $pixels[$__platform] ?? [];
        $__default = null;
        foreach ($__list as $__row) { if ($__row['is_default']) { $__default = $__row; break; } }
      ?>
      <label><?= e($__label) ?>
        <select name="<?= e($__field) ?>">
          <option value="" <?= $__current === null ? 'selected' : '' ?>>
            افتراضي<?= $__default ? ' — ' . e($__default['name']) : ' (لا يوجد بكسل افتراضي)' ?>
          </option>
          <option value="0" <?= ($__current !== null && (int)$__current === 0) ? 'selected' : '' ?>>
            بدون تتبع لهذه المنصة
          </option>
          <?php foreach ($__list as $__row): ?>
            <option value="<?= (int)$__row['id'] ?>" <?= ((int)($__current ?? -1) === (int)$__row['id']) ? 'selected' : '' ?>>
              <?= e($__row['name']) ?> — <?= e($__row['pixel_id']) ?><?= $__row['status'] ? '' : ' (موقوف)' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (!$__list): ?>
          <small>لم تُسجّل أي بكسل لهذه المنصة بعد — <a href="<?= base_url('admin/pixels.php') ?>">أضف واحداً</a>.</small>
        <?php endif; ?>
      </label>
      <?php endforeach; ?>
    </div>
  </div>

  <?php include __DIR__ . '/partials/section-editor.php'; ?>

  <?php include __DIR__ . '/partials/page-options.php'; ?>

</form>
</div><!-- /.pe-body -->

<!-- Sticky action bar. The button lives outside the form and reaches it with
     form=, so it stays visible on every tab instead of only at the bottom of
     the first one. -->
<div class="pe-actions">
  <div class="pe-actions-inner">
    <?php if ($product): ?>
      <span class="pe-status <?= !empty($product['status']) ? 'live' : 'draft' ?>">
        <?= !empty($product['status']) ? '● منشور' : '○ غير منشور' ?>
      </span>
      <a class="btn ghost" target="_blank" href="<?= base_url($product['slug'] . '?preview=1') ?>">معاينة ↗</a>
    <?php endif; ?>
    <span class="pe-actions-spacer"></span>
    <span class="pe-actions-hint" id="peDirtyHint" hidden>تعديلات غير محفوظة</span>
    <button class="btn-buy pe-save" type="submit" form="productForm">
      <?= $product ? 'حفظ التعديلات' : 'حفظ ومتابعة' ?>
    </button>
  </div>
</div>
<?php include __DIR__ . '/partials/unsaved-guard.php'; ?>

<?php if ($product): ?>
<div class="pe-body">
<section id="offers" class="pe-panel pe-section" data-tab="offers">
<h2 class="sec-title">العروض</h2>
<p class="hint">
  مستويات السعر التي يختار منها الزائر. اجعل عرضاً واحداً «افتراضي» ليكون محدداً عند فتح الصفحة،
  وواحداً «موصى به» ليظهر بشارة «الأفضل».
</p>
<div class="tbl-wrap">
<table class="tbl">
<thead><tr><th>العنوان</th><th>الكمية</th><th>السعر</th><th>سعر المقارنة</th><th>افتراضي</th><th>موصى به</th><th>شحن مجاني</th><th>اختيارات؟</th><th></th></tr></thead>
<tbody>
<?php foreach ($offers as $o): ?>
<tr>
  <td><?= e($o['label']) ?></td>
  <td><?= (int)$o['quantity'] ?></td>
  <td><?= number_format((float)$o['total_price'],2) ?></td>
  <td><?= $o['compare_price'] !== null ? number_format((float)$o['compare_price'],2) : '-' ?></td>
  <td><?= $o['is_default']?'✓':'' ?></td>
  <td><?= $o['is_recommended']?'✓':'' ?></td>
  <td><?= $o['free_shipping']?'✓':'' ?></td>
  <td><?= $o['requires_options']?'✓':'' ?></td>
  <td>
    <form method="post" style="display:inline" onsubmit="return confirm('حذف العرض؟')">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="del_offer">
      <input type="hidden" name="offer_id" value="<?= (int)$o['id'] ?>">
      <button class="btn-sm danger">حذف</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<form method="post" class="inline-form">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="add_offer">
  <input name="label" placeholder="عنوان العرض" required>
  <input name="quantity" type="number" min="1" value="1" required>
  <input name="total_price" type="number" step="0.01" placeholder="السعر" required>
  <input name="compare_price" type="number" step="0.01" placeholder="مقارنة">
  <input name="position" type="number" placeholder="ترتيب" value="0">
  <label class="cb"><input type="checkbox" name="is_default"> افتراضي</label>
  <label class="cb"><input type="checkbox" name="is_recommended"> موصى به</label>
  <label class="cb"><input type="checkbox" name="free_shipping"> شحن مجاني</label>
  <label class="cb"><input type="checkbox" name="requires_options" checked> يتطلب اختيارات</label>
  <button class="btn">+ إضافة عرض</button>
</form>
</section>

<section id="options" class="pe-panel pe-section" data-tab="offers">
<h2 class="sec-title">مجموعات الخيارات</h2>
<p class="hint">
  اللون، المقاس، عدد الرفوف… تتكرر لكل وحدة عندما تكون كمية العرض أكثر من واحدة.
  <strong>مجموعة بلا قيم تمنع إتمام الطلب</strong> — أضف قيمها أو احذفها.
</p>
<?php foreach ($groups as $g): ?>
  <div class="grp">
    <div class="grp-head">
      <strong><?= e($g['label']) ?></strong> — <code><?= e($g['name']) ?></code> (<?= e($g['type']) ?>)<?= $g['is_required']?' · إلزامي':'' ?>
      <form method="post" style="display:inline; margin-inline-start:auto" onsubmit="return confirm('حذف المجموعة وكل قيمها؟')">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="del_group">
        <input type="hidden" name="group_id" value="<?= (int)$g['id'] ?>">
        <button class="btn-sm danger">حذف المجموعة</button>
      </form>
    </div>
    <ul class="vals">
      <?php foreach ($g['values'] as $v): ?>
        <li>
          <?php if ($v['swatch']): ?><span class="dot" style="background:<?= e($v['swatch']) ?>"></span><?php endif; ?>
          <?= e($v['value']) ?>
          <form method="post" style="display:inline">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="del_value">
            <input type="hidden" name="value_id" value="<?= (int)$v['id'] ?>">
            <button class="btn-sm danger">×</button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
    <form method="post" class="inline-form">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="add_value">
      <input type="hidden" name="group_id" value="<?= (int)$g['id'] ?>">
      <input name="value" placeholder="قيمة (مثل: أسود)" required>
      <input name="swatch" placeholder="#000000 (للألوان فقط)">
      <input name="position" type="number" value="0">
      <button class="btn">+ قيمة</button>
    </form>
  </div>
<?php endforeach; ?>

<form method="post" class="inline-form">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="add_group">
  <input name="name"  placeholder="اسم تقني (color)" required>
  <input name="label" placeholder="تسمية بالعربية (اللون)" required>
  <select name="type">
    <option value="select">قائمة منسدلة</option>
    <option value="swatch">عينات لون</option>
    <option value="radio">أزرار راديو</option>
    <option value="text">نص حر</option>
  </select>
  <input name="position" type="number" value="0">
  <label class="cb"><input type="checkbox" name="is_required" checked> إلزامي</label>
  <button class="btn">+ مجموعة جديدة</button>
</form>
</section>

<!-- ══════════════════ SLIDER IMAGES ══════════════════ -->
<section id="slider" class="pe-panel pe-section" data-tab="media">
<div class="media-section-head">
  <span class="media-section-badge slider-badge">🖼️ سلايدر</span>
  <h2 class="sec-title">صور السلايدر</h2>
  <p class="hint">الصور الرئيسية التي تظهر في واجهة المنتج</p>
</div>
<?php $sliderMedia = array_values(array_filter($media, fn($m) => $m['kind'] === 'slider')); ?>
<?php if ($sliderMedia): ?>
<div class="media-grid">
  <?php foreach ($sliderMedia as $m): ?>
    <div class="m-card">
      <img src="<?= e(upload_url($m['url'])) ?>">
      <form method="post" onsubmit="return confirm('حذف الصورة؟')">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="del_media">
        <input type="hidden" name="media_id" value="<?= (int)$m['id'] ?>">
        <input type="hidden" name="media_kind" value="slider">
        <button class="btn-sm danger">حذف</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<p class="hint media-empty">لا توجد صور للسلايدر بعد.</p>
<?php endif; ?>
<form method="post" enctype="multipart/form-data" class="inline-form">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="add_media">
  <input type="hidden" name="kind" value="slider">
  <label class="stacked">
    <span>رفع ملفات</span>
    <input type="file" name="media_files[]" multiple accept="image/*">
  </label>
  <label class="stacked">
    <span>أو روابط URL (سطر لكل رابط)</span>
    <textarea name="media_urls" rows="3" placeholder="https://example.com/img1.jpg&#10;https://example.com/img2.jpg"></textarea>
  </label>
  <button class="btn">+ إضافة صور سلايدر</button>
</form>
</section>

<!-- ══════════════════ GALLERY / BODY IMAGES ══════════════════ -->
<section id="gallery" class="pe-panel pe-section" data-tab="media">
<div class="media-section-head">
  <span class="media-section-badge gallery-badge">🗂️ معرض</span>
  <h2 class="sec-title">صور الجسم (المعرض)</h2>
  <p class="hint">صور إضافية في وصف المنتج — <strong>اسحب الصور لإعادة ترتيبها</strong></p>
</div>
<?php
  $galleryMedia = array_filter($media, fn($m) => $m['kind'] === 'gallery');
  usort($galleryMedia, fn($a,$b) => (int)$a['position'] <=> (int)$b['position']);
  $galleryMedia = array_values($galleryMedia);
?>
<?php if ($galleryMedia): ?>
<div class="media-grid sortable-grid" id="galleryGrid"
     data-reorder-url="<?= e(base_url('admin/product-edit.php?id=' . (int)$product['id'])) ?>"
     data-csrf="<?= e(csrf_token()) ?>">
  <?php foreach ($galleryMedia as $m): ?>
    <div class="m-card sortable-item" data-id="<?= (int)$m['id'] ?>">
      <div class="drag-handle" title="اسحب لإعادة الترتيب">⠿</div>
      <img src="<?= e(upload_url($m['url'])) ?>">
      <form method="post" onsubmit="return confirm('حذف الصورة؟')">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="del_media">
        <input type="hidden" name="media_id" value="<?= (int)$m['id'] ?>">
        <input type="hidden" name="media_kind" value="gallery">
        <button class="btn-sm danger">حذف</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<p class="hint media-empty">لا توجد صور للمعرض بعد.</p>
<?php endif; ?>
<form method="post" enctype="multipart/form-data" class="inline-form">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="add_media">
  <input type="hidden" name="kind" value="gallery">
  <label class="stacked">
    <span>رفع ملفات</span>
    <input type="file" name="media_files[]" multiple accept="image/*">
  </label>
  <label class="stacked">
    <span>أو روابط URL (سطر لكل رابط)</span>
    <textarea name="media_urls" rows="3" placeholder="https://example.com/img1.jpg&#10;https://example.com/img2.jpg"></textarea>
  </label>
  <button class="btn">+ إضافة صور المعرض</button>
</form>
</section>

<script>
(function(){
  var grid = document.getElementById('galleryGrid');
  if (!grid) return;
  var dragging = null;

  function getItems(){ return Array.from(grid.querySelectorAll('.sortable-item')); }

  function saveOrder(){
    var ids = getItems().map(function(el){ return el.dataset.id; });
    var fd  = new FormData();
    fd.append('_csrf', grid.dataset.csrf);
    fd.append('action', 'reorder_media');
    fd.append('ids', JSON.stringify(ids));
    fetch(grid.dataset.reorderUrl, { method:'POST', body:fd })
      .catch(function(e){ console.error('Reorder failed', e); });
  }

  getItems().forEach(function(item){
    item.setAttribute('draggable', 'true');
    item.addEventListener('dragstart', function(e){
      dragging = item;
      setTimeout(function(){ item.classList.add('dragging'); }, 0);
      e.dataTransfer.effectAllowed = 'move';
    });
    item.addEventListener('dragend', function(){
      item.classList.remove('dragging');
      dragging = null;
      saveOrder();
    });
  });

  grid.addEventListener('dragover', function(e){
    e.preventDefault();
    if (!dragging) return;
    var target = e.target.closest('.sortable-item');
    if (!target || target === dragging) return;
    var rect = target.getBoundingClientRect();
    var midX = rect.left + rect.width / 2;
    if (e.clientX < midX) {
      grid.insertBefore(dragging, target);
    } else {
      grid.insertBefore(dragging, target.nextSibling);
    }
  });
})();
</script>

</div><!-- /.pe-body -->

<?php include __DIR__ . '/partials/product-tabs-js.php'; ?>
<?php endif; ?>
