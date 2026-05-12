<?php
$statusLabels = [
  'new' => 'جديد',
  'called' => 'تم الاتصال',
  'confirmed' => 'مؤكَّد',
  'shipped' => 'تم الشحن',
  'delivered' => 'تم التسليم',
  'cancelled' => 'ملغى',
  'no_answer' => 'بدون رد',
];
$st = $lead['status'];
?>
<div class="lead-detail">

  <div>
    <div class="grp">
      <h3>معلومات العميل</h3>
      <div class="kv">
        <div class="k">الاسم</div>      <div class="v"><?= e($lead['fullname']) ?></div>
        <div class="k">الهاتف</div>     <div class="v"><a href="tel:<?= e($lead['phone']) ?>"><?= e($lead['phone']) ?></a></div>
        <div class="k">المدينة</div>    <div class="v"><?= e($lead['city']) ?: '—' ?></div>
        <div class="k">العنوان</div>    <div class="v"><?= e($lead['address']) ?></div>
        <?php if ($lead['notes']): ?>
        <div class="k">ملاحظات</div>    <div class="v"><?= e($lead['notes']) ?></div>
        <?php endif; ?>
        <div class="k">تاريخ</div>      <div class="v"><?= e($lead['created_at']) ?></div>
        <div class="k">المصدر</div>     <div class="v"><?= e($lead['source']) ?></div>
        <?php if ($lead['utm_source'] || $lead['utm_medium'] || $lead['utm_campaign']): ?>
        <div class="k">UTM</div>
        <div class="v"><?= e($lead['utm_source']) ?> / <?= e($lead['utm_medium']) ?> / <?= e($lead['utm_campaign']) ?></div>
        <?php endif; ?>
        <?php if ($lead['fbclid'] || $lead['ttclid'] || $lead['gclid']): ?>
        <div class="k">معرّفات</div>
        <div class="v" style="font-size:12px;color:#888"><?= e($lead['fbclid']) ?: '—' ?> · <?= e($lead['ttclid']) ?: '—' ?> · <?= e($lead['gclid']) ?: '—' ?></div>
        <?php endif; ?>
        <div class="k">IP</div>         <div class="v"><?= e($lead['ip']) ?></div>
      </div>
    </div>

    <div class="grp" style="margin-top:18px">
      <h3>تحديث الحالة</h3>
      <form method="post" class="status-form">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <select name="status">
          <?php foreach ($statusLabels as $key=>$label): ?>
            <option value="<?= $key ?>" <?= $st===$key?'selected':'' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="note" placeholder="ملاحظة (اختياري)" style="flex:1; min-width: 200px">
        <button class="btn">تحديث</button>
      </form>

      <h4 style="margin: 18px 0 8px; font-size:14px">سجل الحالات</h4>
      <?php if ($logs): ?>
      <ul class="timeline">
        <?php foreach ($logs as $log): ?>
          <li>
            <span class="when"><?= e($log['created_at']) ?></span>
            <span class="st st-<?= e($log['from_status']) ?>"><?= e($statusLabels[$log['from_status']] ?? $log['from_status']) ?></span>
            <span class="arrow">→</span>
            <span class="st st-<?= e($log['to_status']) ?>"><?= e($statusLabels[$log['to_status']] ?? $log['to_status']) ?></span>
            <?php if ($log['note']): ?><div style="margin-top:4px; color:#555">«<?= e($log['note']) ?>»</div><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
        <p class="empty" style="padding:14px 0">لا يوجد سجل بعد.</p>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <div class="order-card">
      <div class="c-l">المبلغ الإجمالي</div>
      <div class="order-amount"><?= e(number_format((float)$lead['total_price'],2)) ?> <span class="dh">د.م</span></div>
      <div class="order-meta"><strong>الحالة:</strong> <span class="st st-<?= e($st) ?>"><?= e($statusLabels[$st] ?? $st) ?></span></div>
      <div class="order-meta"><strong>المنتج:</strong> <?= e($product['title'] ?? '—') ?></div>
      <div class="order-meta"><strong>العرض:</strong> <?= e($lead['offer_label']) ?></div>
      <div class="order-meta"><strong>الكمية:</strong> <?= (int)$lead['quantity'] ?></div>

      <div style="margin-top: 14px;">
        <div class="c-l" style="margin-bottom: 6px">الوحدات والخيارات</div>
        <?php foreach ($items as $it):
          $opts = json_decode($it['options_json'] ?? '{}', true) ?: [];
        ?>
          <div class="unit-pill">
            <span class="idx">#<?= (int)$it['unit_index'] ?></span>
            <?php foreach ($opts as $k=>$v): ?>
              <span class="opt"><?= e($k) ?>:<b><?= e($v) ?></b></span>
            <?php endforeach; ?>
            <?php if (!$opts): ?><span class="opt">—</span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($product): ?>
      <div style="margin-top: 18px; display:flex; gap:8px; flex-wrap:wrap">
        <a class="btn ghost" href="<?= base_url($product['slug']) ?>?preview=1" target="_blank">معاينة المنتج</a>
        <a class="btn ghost" href="<?= base_url('admin/product-edit.php?id=' . (int)$product['id']) ?>">تحرير المنتج</a>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>
