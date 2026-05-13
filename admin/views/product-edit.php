<?php
$sectionsTemplate = '{
  "hero": {
    "headline": "عنوان جذاب",
    "subheadline": "وصف قصير يبرز القيمة",
    "badges": ["شحن مجاني","ضمان الجودة"],
    "cta": "اطلب الآن"
  },
  "features": [
    {"icon":"✦","title":"ميزة 1","text":"شرح قصير"},
    {"icon":"✦","title":"ميزة 2","text":"شرح قصير"}
  ],
  "testimonials": [
    {"name":"أحمد","text":"منتج رائع"}
  ],
  "faqs": [
    {"q":"سؤال؟","a":"جواب."}
  ],
  "cta_text": "اطلب الآن"
}';
?>
<?php if ($msg): ?><div class="al ok"><?= e($msg) ?></div><?php endif; ?>

<?php if (!empty($product['id']) && !empty($product['slug'])): ?>
<div class="page-actions">
  <a class="btn" target="_blank" href="<?= base_url($product['slug'] . '?preview=1') ?>">👁 معاينة الصفحة في تبويب جديد</a>
  <a class="btn ghost" href="<?= base_url('admin/products.php') ?>">← العودة للقائمة</a>
</div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="form-grid">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="save">

  <div class="grp">
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

  <div class="grp">
    <h3>الصور والـSEO</h3>
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

  <div class="grp wide">
    <details class="json-section">
      <summary><strong>أقسام صفحة المنتج (JSON متقدم)</strong> — اضغط للتحرير اليدوي</summary>
      <p class="hint">عدّل المحتوى البصري للصفحة (Hero / مميزات / آراء / FAQ). كن حذراً مع صياغة JSON.</p>
      <textarea name="sections_json" rows="14" class="mono"><?= e($product['sections_json'] ?? $sectionsTemplate) ?></textarea>
    </details>
  </div>

  <div class="grp wide">
    <button class="btn-buy" type="submit">حفظ المنتج</button>
  </div>
</form>

<?php if ($product): ?>
<hr style="margin:30px 0">

<section id="offers">
<h2 class="sec-title">العروض</h2>
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

<hr style="margin:30px 0">

<section id="options">
<h2 class="sec-title">مجموعات الخيارات</h2>
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

<hr style="margin:30px 0">

<section id="media">
<h2 class="sec-title">الصور (سلايدر / معرض)</h2>
<div class="media-grid">
  <?php foreach ($media as $m): ?>
    <div class="m-card">
      <img src="<?= e(upload_url($m['url'])) ?>">
      <div><?= e($m['kind']) ?></div>
      <form method="post" onsubmit="return confirm('حذف الصورة؟')">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="del_media">
        <input type="hidden" name="media_id" value="<?= (int)$m['id'] ?>">
        <button class="btn-sm danger">حذف</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>

<form method="post" enctype="multipart/form-data" class="inline-form">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="add_media">
  <label class="stacked" style="width:100%">
    <span>نوع الصورة</span>
    <div class="kind-cards">
      <label class="kind-card">
        <input type="radio" name="kind" value="slider" checked>
        <span class="kc-check">✓</span>
        <span class="kc-icon">🖼️</span>
        <span class="kc-title">سلايدر</span>
        <span class="kc-desc">صور رئيسية تظهر في الواجهة</span>
      </label>
      <label class="kind-card">
        <input type="radio" name="kind" value="gallery">
        <span class="kc-check">✓</span>
        <span class="kc-icon">🗂️</span>
        <span class="kc-title">معرض</span>
        <span class="kc-desc">صور إضافية في وصف المنتج</span>
      </label>
    </div>
  </label>
  <label class="stacked">
    <span>رفع ملفات</span>
    <input type="file" name="media_files[]" multiple accept="image/*">
  </label>
  <label class="stacked">
    <span>أو روابط URL (سطر لكل رابط)</span>
    <textarea name="media_urls" rows="3" placeholder="https://example.com/img1.jpg&#10;https://example.com/img2.jpg"></textarea>
  </label>
  <button class="btn">+ إضافة</button>
</form>
</section>

<?php endif; ?>
