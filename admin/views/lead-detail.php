<div class="lead-detail">
  <div class="grp">
    <h3>معلومات العميل</h3>
    <div><strong>الاسم:</strong> <?= e($lead['fullname']) ?></div>
    <div><strong>الهاتف:</strong> <a href="tel:<?= e($lead['phone']) ?>"><?= e($lead['phone']) ?></a></div>
    <div><strong>المدينة:</strong> <?= e($lead['city']) ?></div>
    <div><strong>العنوان:</strong> <?= e($lead['address']) ?></div>
    <div><strong>ملاحظات:</strong> <?= e($lead['notes']) ?></div>
    <div><strong>تاريخ:</strong> <?= e($lead['created_at']) ?></div>
    <div><strong>المصدر:</strong> <?= e($lead['source']) ?> · UTM: <?= e($lead['utm_source']) ?>/<?= e($lead['utm_medium']) ?>/<?= e($lead['utm_campaign']) ?></div>
    <div><strong>fbclid/ttclid/gclid:</strong> <?= e($lead['fbclid']) ?> · <?= e($lead['ttclid']) ?> · <?= e($lead['gclid']) ?></div>
    <div><strong>IP:</strong> <?= e($lead['ip']) ?></div>
  </div>

  <div class="grp">
    <h3>الطلب</h3>
    <div><strong>المنتج:</strong> <?= e($product['title'] ?? '-') ?></div>
    <div><strong>العرض:</strong> <?= e($lead['offer_label']) ?></div>
    <div><strong>الكمية:</strong> <?= (int)$lead['quantity'] ?></div>
    <div><strong>المبلغ:</strong> <?= e(number_format((float)$lead['total_price'],2)) ?> د.م</div>
    <h4>الوحدات والخيارات</h4>
    <ul class="vals">
      <?php foreach ($items as $it):
        $opts = json_decode($it['options_json'] ?? '{}', true) ?: [];
        $parts = []; foreach ($opts as $k=>$v) $parts[] = e($k) . ': ' . e($v);
      ?>
      <li>وحدة #<?= (int)$it['unit_index'] ?> — <?= implode(' · ', $parts) ?: '-' ?></li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="grp">
    <h3>تحديث الحالة</h3>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <select name="status">
        <?php foreach (['new','called','confirmed','shipped','delivered','cancelled','no_answer'] as $s): ?>
          <option value="<?= $s ?>" <?= $lead['status']===$s?'selected':'' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="note" placeholder="ملاحظة (اختياري)">
      <button class="btn">تحديث</button>
    </form>

    <h4>سجل الحالات</h4>
    <ul class="vals">
      <?php foreach ($logs as $log): ?>
        <li><?= e($log['created_at']) ?> — <?= e($log['from_status']) ?> → <strong><?= e($log['to_status']) ?></strong> <?= $log['note']?'· '.e($log['note']):'' ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
