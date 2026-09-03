<?php
$platformLabels = ['facebook' => 'Meta / Facebook', 'tiktok' => 'TikTok'];
?>
<?php if ($msg): ?><div class="al ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="al err"><?= e($err) ?></div><?php endif; ?>

<p class="hint" style="margin-bottom:16px">
  سجّل هنا كل البكسلات التي تستعملها، ثم اختر لكل صفحة هبوط البكسل المناسب من
  <strong>المنتجات ← تعديل ← التتبع والبكسلات</strong>.
  الصفحة التي تُترك على «افتراضي» تستعمل البكسل المعلَّم كافتراضي لمنصته.
</p>

<?php foreach (Pixel::PLATFORMS as $platform): ?>
<section class="px-section">
  <h2 class="sec-title"><?= e($platformLabels[$platform]) ?></h2>

  <?php $rows = $grouped[$platform] ?? []; ?>
  <?php if (!$rows): ?>
    <p class="hint media-empty">لا يوجد أي بكسل <?= e($platformLabels[$platform]) ?> بعد.</p>
    <?php if (!empty($legacy[$platform])): ?>
      <p class="hint">قيمة قديمة في الإعدادات العامة ستُستعمل مؤقتاً: <code><?= e($legacy[$platform]) ?></code></p>
    <?php endif; ?>
  <?php else: ?>
  <div class="tbl-wrap">
  <table class="tbl">
    <thead><tr>
      <th>الاسم</th><th>معرّف البكسل</th><th>افتراضي</th><th>الحالة</th>
      <th>سيرفر</th><th>صفحات الهبوط</th><th>ملاحظات</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $px): $pages = $usage[(int)$px['id']] ?? []; ?>
      <tr>
        <td><strong><?= e($px['name']) ?></strong></td>
        <td><code class="px-id"><?= e($px['pixel_id']) ?></code></td>
        <td>
          <?php if ($px['is_default']): ?>
            <span class="st st-confirmed">افتراضي</span>
          <?php else: ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="make_default">
              <input type="hidden" name="id" value="<?= (int)$px['id'] ?>">
              <button class="btn-sm" title="اجعله الافتراضي لهذه المنصة">تعيين</button>
            </form>
          <?php endif; ?>
        </td>
        <td><?= $px['status'] ? '<span class="st st-confirmed">مفعّل</span>' : '<span class="st st-cancelled">موقوف</span>' ?></td>
        <td><?= !empty($px['access_token'])
              ? '<span class="st st-confirmed" title="Conversions API جاهز">CAPI</span>'
              : '<span class="st" title="لا يوجد Access Token">—</span>' ?></td>
        <td>
          <?php if (!$pages): ?><span class="hint">—</span><?php else: ?>
            <?php foreach ($pages as $pg): ?>
              <a class="px-page" href="<?= base_url('admin/product-edit.php?id=' . (int)$pg['id']) ?>"><?= e($pg['title']) ?></a>
            <?php endforeach; ?>
          <?php endif; ?>
        </td>
        <td class="px-notes"><?= e($px['notes'] ?? '') ?></td>
        <td class="px-actions">
          <a class="btn-sm" href="<?= base_url('admin/pixels.php?edit=' . (int)$px['id']) ?>#pixel-form">تعديل</a>
          <form method="post" style="display:inline"
                onsubmit="return confirm('حذف هذا البكسل؟ الصفحات التي تستعمله ستعود إلى «افتراضي».')">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$px['id'] ?>">
            <button class="btn-sm danger">حذف</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>
<?php endforeach; ?>

<hr style="margin:30px 0">

<section id="pixel-form">
<h2 class="sec-title"><?= $editing ? 'تعديل البكسل' : 'إضافة بكسل جديد' ?></h2>
<form method="post" class="form-grid">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="save">
  <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>

  <div class="grp">
    <h3>المنصة والمعرّف</h3>
    <label>المنصة
      <select name="platform" required>
        <?php foreach (Pixel::PLATFORMS as $p): ?>
          <option value="<?= e($p) ?>" <?= ($editing['platform'] ?? '') === $p ? 'selected' : '' ?>><?= e($platformLabels[$p]) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>اسم تعريفي (يظهر في القوائم)
      <input name="name" placeholder="مثال: Meta — حساب الإعلانات 2" value="<?= e($editing['name'] ?? '') ?>">
    </label>
    <label>معرّف البكسل
      <input name="pixel_id" required placeholder="Meta: 15 رقماً · TikTok: مثل CXXXXXXXXXXXXXXXX"
             value="<?= e($editing['pixel_id'] ?? '') ?>">
      <small>Meta: Events Manager ← Data Sources. TikTok: Assets ← Events ← Web Events.</small>
    </label>
    <label class="cb"><input type="checkbox" name="status" <?= (!$editing || $editing['status']) ? 'checked' : '' ?>> مفعّل</label>
    <label class="cb"><input type="checkbox" name="is_default" <?= !empty($editing['is_default']) ? 'checked' : '' ?>> اجعله الافتراضي لهذه المنصة</label>
  </div>

  <div class="grp">
    <h3>خيارات متقدمة</h3>
    <label>Access Token (Conversions API)
      <?php $__hasToken = !empty($editing['access_token']); ?>
      <input type="password" name="access_token" autocomplete="new-password"
             placeholder="<?= $__hasToken ? 'محفوظ — اتركه فارغاً للإبقاء عليه' : 'الصق المفتاح هنا' ?>">
      <small>
        <?php if ($__hasToken): ?>
          <span class="tok-set">✓ مفتاح محفوظ (<?= e(substr((string)$editing['access_token'], -4)) ?>…)</span> —
        <?php endif; ?>
        يفعّل إرسال التحويلات من السيرفر، فتصل حتى عند حجب الزائر للسكريبت.
        Meta: Events Manager ← Settings ← Generate access token.
        TikTok: Events ← Manage ← Generate access token.
        يجب تفعيل الخيار العام من <a href="<?= base_url('admin/settings.php') ?>">الإعدادات</a>.
      </small>
    </label>
    <label>Test Event Code
      <input name="test_event_code" placeholder="TEST12345" value="<?= e($editing['test_event_code'] ?? '') ?>">
    </label>
    <label>ملاحظات
      <input name="notes" maxlength="255" placeholder="مثال: حساب الإعلانات الخاص بمنتجات الشتاء"
             value="<?= e($editing['notes'] ?? '') ?>">
    </label>
  </div>

  <div class="grp wide" style="display:flex; gap:10px; align-items:center">
    <button class="btn-buy" type="submit" style="width:auto"><?= $editing ? 'حفظ التعديلات' : '+ إضافة البكسل' ?></button>
    <?php if ($editing): ?>
      <a class="btn ghost" href="<?= base_url('admin/pixels.php') ?>">إلغاء</a>
    <?php endif; ?>
  </div>
</form>
</section>

<section class="grp wide" style="margin-top:24px">
  <h3>كيف تتحقق من أن البكسل يعمل</h3>
  <ol class="hint" style="line-height:2; padding-inline-start:18px">
    <li>افتح صفحة الهبوط وأضف <code>?pxdebug=1</code> إلى الرابط، ثم راقب رسائل <code>[LPX]</code> في الـConsole.</li>
    <li>Meta: إضافة <strong>Meta Pixel Helper</strong> في المتصفح، أو Events Manager ← Test Events.</li>
    <li>TikTok: إضافة <strong>TikTok Pixel Helper</strong>، أو Events ← Test Event.</li>
    <li>الأحداث المرسلة: <code>PageView</code> ← <code>ViewContent</code> ← <code>AddToCart</code> (عند اختيار عرض)
        ← <code>InitiateCheckout</code> (عند إرسال النموذج) ← <code>Purchase</code> / <code>CompletePayment</code> في صفحة الشكر.</li>
  </ol>
</section>
