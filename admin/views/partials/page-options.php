<?php
/**
 * Per-page campaign options: colours, a real deadline, and the A/B split.
 *
 * All three belong to the landing page rather than the store, because that is
 * the level campaigns are run at — one page can match the creative that sent
 * the visitor, end when its promotion ends, and test its own copy, without
 * touching any other page.
 *
 * Expects: $product (may be null), $abResults (may be null)
 */
$storeAccent = settings_get('accent_color', '#0e7c7b');
$endsAt = !empty($product['campaign_ends_at'])
    ? date('Y-m-d\TH:i', strtotime($product['campaign_ends_at']))
    : '';
?>
<div class="grp wide">
  <h3>خيارات الحملة</h3>

  <div class="row2">
    <label>لون الصفحة
      <div class="color-row">
        <input type="color" name="accent_color"
               value="<?= e(($product['accent_color'] ?? '') ?: $storeAccent) ?>">
        <label class="cb"><input type="checkbox" name="accent_color_clear"
               <?= empty($product['accent_color'] ?? '') ? 'checked' : '' ?>> استعمال لون المتجر</label>
      </div>
      <small>لمطابقة لون الإعلان الذي جاء منه الزائر.</small>
    </label>

    <label>لون زر الطلب
      <div class="color-row">
        <input type="color" name="cta_color" value="<?= e(($product['cta_color'] ?? '') ?: '#22c55e') ?>">
        <label class="cb"><input type="checkbox" name="cta_color_clear"
               <?= empty($product['cta_color'] ?? '') ? 'checked' : '' ?>> افتراضي</label>
      </div>
    </label>
  </div>

  <label>نهاية العرض (اختياري)
    <input type="datetime-local" name="campaign_ends_at" value="<?= e($endsAt) ?>">
    <small>
      عند ضبطه يعدّ المؤقّت تنازلياً نحو هذا الوقت <strong>ويتوقف فعلاً</strong> عند انتهائه،
      بدل المؤقّت المتجدد الذي يعيد نفسه بلا نهاية. اتركه فارغاً للسلوك الافتراضي.
    </small>
  </label>
</div>

<?php if (!empty($product['id'])): ?>
<div class="grp wide ab-block">
  <h3>اختبار A/B</h3>
  <p class="hint">
    نسخة ثانية من محتوى الصفحة تُعرض لجزء من الزوار. العروض والصور والخيارات تبقى مشتركة —
    تغيير شيئين في وقت واحد يجعل النتيجة غير قابلة للقراءة.
    <strong>النسخة تُسجَّل مع كل طلب</strong>، فتُقاس بالإيرادات المؤكدة لا بالنقرات.
  </p>

  <div class="row2">
    <label class="cb"><input type="checkbox" name="ab_enabled" <?= !empty($product['ab_enabled']) ? 'checked' : '' ?>>
      تفعيل الاختبار</label>
    <label>نسبة النسخة A
      <input type="number" name="ab_split" min="1" max="99"
             value="<?= (int)($product['ab_split'] ?? 50) ?>"> %
      <small>الباقي يرى النسخة B. التوزيع ثابت لكل زائر فلا تتغير الصفحة بين الزيارات.</small>
    </label>
  </div>

  <label>محتوى النسخة B (JSON)
    <textarea name="sections_json_b" rows="10" class="mono" spellcheck="false"
      placeholder="اتركه فارغاً لتعطيل الاختبار. انسخ محتوى النسخة A من محرّر المحتوى وعدّل العنوان أو النص."><?= e($product['sections_json_b'] ?? '') ?></textarea>
    <small>نفس بنية محتوى الصفحة. أسهل طريقة: افتح «JSON متقدّم» أعلاه، انسخ، ثم عدّل هنا.</small>
  </label>

  <?php if (!empty($abResults)): ?>
    <?php
      $a = $abResults['a']; $b = $abResults['b'];
      $verdict = Experiment::verdict($abResults);
      $money = fn($v) => number_format((float)$v, 2) . ' د.م';
    ?>
    <div class="ab-results">
      <div class="ab-verdict ab-<?= e($verdict['state']) ?>"><?= e($verdict['text']) ?></div>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr><th>النسخة</th><th>الطلبات</th><th>المؤكدة</th><th>نسبة التأكيد</th><th>الإيرادات</th><th>متوسط الطلب</th></tr></thead>
          <tbody>
            <?php foreach ([$a, $b] as $v): ?>
            <tr class="<?= $verdict['state'] === 'winner'
                           && (($v['variant'] === 'b') === ($b['revenue'] > $a['revenue'])) ? 'ab-winner' : '' ?>">
              <td><strong><?= strtoupper($v['variant']) ?></strong></td>
              <td><?= (int)$v['orders'] ?></td>
              <td><?= (int)$v['confirmed'] ?></td>
              <td><?= number_format($v['confirm_rate'], 1) ?>%</td>
              <td><strong><?= e($money($v['revenue'])) ?></strong></td>
              <td><?= e($money($v['aov'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>
