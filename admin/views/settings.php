<?php $s = $settings; ?>
<?php if (!empty($saved)): ?><div class="al ok">تم الحفظ</div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="form-grid">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

  <div class="grp">
    <h3>عام</h3>
    <label>اسم المتجر <input name="store_name" value="<?= e($s['store_name'] ?? '') ?>"></label>
    <?php foreach ([
          ['store_logo',       'store_logo_file',       'الشعار (خلفية فاتحة)', 'يظهر في رأس الموقع'],
          ['store_logo_light', 'store_logo_light_file', 'الشعار (خلفية داكنة)', 'يظهر في التذييل ولوحة التحكم'],
          ['store_favicon',    'store_favicon_file',    'أيقونة الموقع (Favicon)', 'أيقونة تبويب المتصفح'],
        ] as [$__k, $__f, $__label, $__desc]): ?>
    <label><?= e($__label) ?>
      <?php if (!empty($s[$__k])): ?>
        <img class="thumb brand-thumb" src="<?= e(upload_url($s[$__k])) ?>" alt="">
      <?php endif; ?>
      <small><?= e($__desc) ?></small>
      <input type="file" name="<?= e($__f) ?>" accept="image/*">
      <span class="or-sep">— أو —</span>
      <div class="url-input-wrap">
        <span class="url-pfx">🔗</span>
        <input type="url" name="<?= e($__k) ?>_url" placeholder="https://..."
               value="<?= preg_match('#^https?://#', $s[$__k] ?? '') ? e($s[$__k]) : '' ?>">
      </div>
    </label>
    <?php endforeach; ?>
    <label class="cb"><input type="checkbox" name="brand_reset"> إعادة الشعارات إلى تصميم tujjar.store الافتراضي</label>
    <label>هاتف الدعم <input name="support_phone" value="<?= e($s['support_phone'] ?? '') ?>"></label>
    <label>واتساب <input name="whatsapp" value="<?= e($s['whatsapp'] ?? '') ?>"></label>
    <label>معرف فيسبوك (handle) <input name="facebook_handle" value="<?= e($s['facebook_handle'] ?? '') ?>"></label>
    <label>اللون المميز <input type="color" name="accent_color" value="<?= e($s['accent_color'] ?? '#0e7c7b') ?>"></label>
    <h4 style="margin:14px 0 8px;font-size:13px;color:#6a6258;">إظهار أزرار التذييل</h4>
    <label class="cb"><input type="checkbox" name="show_footer_phone" <?= ($s['show_footer_phone'] ?? '1')==='1'?'checked':'' ?>> إظهار زر الهاتف</label>
    <label class="cb"><input type="checkbox" name="show_footer_whatsapp" <?= ($s['show_footer_whatsapp'] ?? '1')==='1'?'checked':'' ?>> إظهار زر واتساب</label>
    <label class="cb"><input type="checkbox" name="show_footer_facebook" <?= ($s['show_footer_facebook'] ?? '1')==='1'?'checked':'' ?>> إظهار زر فيسبوك</label>

    <h4 style="margin:16px 0 8px;font-size:13px;color:#6a6258;">الطلبات غير المكتملة</h4>
    <label class="cb"><input type="checkbox" name="capture_drafts" <?= ($s['capture_drafts'] ?? '1')==='1'?'checked':'' ?>>
      حفظ رقم الهاتف عند مغادرة الزائر قبل الإرسال</label>
    <p class="hint">
      يظهر في <a href="<?= base_url('admin/drafts.php') ?>">«لم تكتمل»</a> للاتصال بهم لاحقاً.
      لا يُحتسب كطلب ولا يُرسل لأي بكسل. تُحذف السجلات تلقائياً بعد 60 يوماً.
    </p>

    <h4 style="margin:16px 0 8px;font-size:13px;color:#6a6258;">حماية الصور</h4>
    <label class="cb"><input type="checkbox" name="protect_images" <?= ($s['protect_images'] ?? '1')==='1'?'checked':'' ?>>
      منع سحب صور المنتج وقائمة الزر الأيمن عليها</label>
    <p class="hint">
      يقتصر المنع على الصور فقط. حجب الاختصارات ونسخ النص في كامل الصفحة لا يوقف من يريد المصدر فعلاً،
      لكنه يمنع الزبون من نسخ رقم الهاتف أو طباعة الطلب.
    </p>
  </div>

  <div class="grp">
    <h3>التتبع</h3>
    <p class="hint">
      بكسلات ميتا وتيك توك أصبحت تُدار من <a href="<?= base_url('admin/pixels.php') ?>"><strong>صفحة البكسلات</strong></a>،
      وتُختار لكل صفحة هبوط على حدة. الحقلان أدناه احتياطيان فقط: يُستعملان عندما لا يوجد أي بكسل
      مسجّل لتلك المنصة.
    </p>
    <label>Facebook Pixel ID (احتياطي) <input name="fb_pixel_id" value="<?= e($s['fb_pixel_id'] ?? '') ?>"></label>
    <label>TikTok Pixel ID (احتياطي) <input name="tiktok_pixel_id" value="<?= e($s['tiktok_pixel_id'] ?? '') ?>"></label>
    <label>GTM ID <input name="gtm_id" value="<?= e($s['gtm_id'] ?? '') ?>"></label>
    <label>GA4 ID <input name="ga_id" value="<?= e($s['ga_id'] ?? '') ?>"></label>

    <h4 style="margin:16px 0 8px;font-size:13px;color:#6a6258;">التتبع من السيرفر (Conversions API)</h4>
    <label class="cb"><input type="checkbox" name="capi_enabled" <?= ($s['capi_enabled'] ?? '0')==='1'?'checked':'' ?>>
      إرسال التحويلات من السيرفر أيضاً</label>
    <p class="hint">
      حوالي 20% من الزوار يحجبون سكريبت البكسل، فلا تصل تحويلاتهم. تفعيل هذا الخيار يرسل نفس
      الطلب من السيرفر مباشرة إلى ميتا وتيك توك بنفس <code>event_id</code>، فلا يُحتسب مرتين.
      يتطلب إضافة <strong>Access Token</strong> لكل بكسل في
      <a href="<?= base_url('admin/pixels.php') ?>">صفحة البكسلات</a>.
      بيانات العميل تُشفَّر (SHA-256) قبل الإرسال ولا تغادر السيرفر كنص واضح.
    </p>
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
