<?php $s = $settings; ?>
<?php if (!empty($saved)): ?><div class="al ok">تم الحفظ</div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="form-grid">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

  <div class="grp">
    <h3>عام</h3>
    <label>اسم المتجر <input name="store_name" value="<?= e($s['store_name'] ?? '') ?>"></label>
    <label>الشعار
      <?php if (!empty($s['store_logo'])): ?><img class="thumb" src="<?= e(upload_url($s['store_logo'])) ?>"><?php endif; ?>
      <input type="file" name="store_logo_file" accept="image/*">
    </label>
    <label>هاتف الدعم <input name="support_phone" value="<?= e($s['support_phone'] ?? '') ?>"></label>
    <label>واتساب <input name="whatsapp" value="<?= e($s['whatsapp'] ?? '') ?>"></label>
    <label>معرف فيسبوك (handle) <input name="facebook_handle" value="<?= e($s['facebook_handle'] ?? '') ?>"></label>
    <label>اللون المميز <input type="color" name="accent_color" value="<?= e($s['accent_color'] ?? '#0e7c7b') ?>"></label>
    <h4 style="margin:14px 0 8px;font-size:13px;color:#6a6258;">إظهار أزرار التذييل</h4>
    <label class="cb"><input type="checkbox" name="show_footer_phone" <?= ($s['show_footer_phone'] ?? '1')==='1'?'checked':'' ?>> إظهار زر الهاتف</label>
    <label class="cb"><input type="checkbox" name="show_footer_whatsapp" <?= ($s['show_footer_whatsapp'] ?? '1')==='1'?'checked':'' ?>> إظهار زر واتساب</label>
    <label class="cb"><input type="checkbox" name="show_footer_facebook" <?= ($s['show_footer_facebook'] ?? '1')==='1'?'checked':'' ?>> إظهار زر فيسبوك</label>
  </div>

  <div class="grp">
    <h3>التتبع</h3>
    <label>Facebook Pixel ID <input name="fb_pixel_id" value="<?= e($s['fb_pixel_id'] ?? '') ?>"></label>
    <label>TikTok Pixel ID <input name="tiktok_pixel_id" value="<?= e($s['tiktok_pixel_id'] ?? '') ?>"></label>
    <label>GTM ID <input name="gtm_id" value="<?= e($s['gtm_id'] ?? '') ?>"></label>
    <label>GA4 ID <input name="ga_id" value="<?= e($s['ga_id'] ?? '') ?>"></label>
  </div>

  <div class="grp">
    <h3>SheetDB (مزامنة من السيرفر)</h3>
    <label class="cb"><input type="checkbox" name="sheetdb_enabled" <?= ($s['sheetdb_enabled'] ?? '0')==='1'?'checked':'' ?>> تفعيل المزامنة</label>
    <label>SheetDB URL <input name="sheetdb_url" value="<?= e($s['sheetdb_url'] ?? '') ?>"></label>
    <label>SheetDB Token <input name="sheetdb_token" value="<?= e($s['sheetdb_token'] ?? '') ?>"></label>
    <p class="hint">المفتاح يبقى على السيرفر فقط، ولا يظهر في الواجهة.</p>
  </div>

  <div class="grp wide">
    <h3>الصفحات القانونية</h3>
    <label>سياسة الخصوصية (HTML) <textarea name="policy_privacy" rows="6"><?= e($s['policy_privacy'] ?? '') ?></textarea></label>
    <label>شروط الاستخدام (HTML) <textarea name="policy_terms"   rows="6"><?= e($s['policy_terms'] ?? '') ?></textarea></label>
    <label>سياسة الإرجاع (HTML)  <textarea name="policy_refund"  rows="6"><?= e($s['policy_refund'] ?? '') ?></textarea></label>
  </div>

  <div class="grp">
    <h3>تغيير كلمة المرور</h3>
    <label>كلمة مرور جديدة <input type="password" name="new_password" autocomplete="new-password"></label>
  </div>

  <div class="grp wide"><button class="btn-buy" type="submit">حفظ</button></div>
</form>
