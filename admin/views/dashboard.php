<?php
$money = fn($v) => number_format((float)$v, 2) . ' د.م';
$pct   = fn($v) => number_format((float)$v, 1) . '%';
$rateClass = function (float $r): string {
    if ($r >= 60) return 'rate-good';
    if ($r >= 40) return 'rate-ok';
    return 'rate-bad';
};
$sourceLabels = ['facebook' => 'Meta', 'tiktok' => 'TikTok', 'google' => 'Google', 'organic' => 'مباشر'];
$statusLabels = [
    'new' => 'جديد', 'called' => 'تم الاتصال', 'confirmed' => 'مؤكد', 'shipped' => 'مشحون',
    'delivered' => 'تم التسليم', 'cancelled' => 'ملغى', 'no_answer' => 'لا يرد',
];
?>
<?php if (!empty($audit)): ?>
<section class="audit">
  <?php foreach ($audit as $a): ?>
    <div class="audit-item audit-<?= e($a['level']) ?>">
      <span class="audit-badge"><?= $a['level'] === 'critical' ? 'خطر' : ($a['level'] === 'warning' ? 'تنبيه' : 'ملاحظة') ?></span>
      <div class="audit-body">
        <strong><?= e($a['title']) ?></strong>
        <p><?= e($a['detail']) ?></p>
      </div>
      <?php if ($a['action']): ?>
        <a class="btn-sm" href="<?= base_url($a['action']) ?>">إصلاح</a>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<!-- ── today, and the last 30 days ──────────────────────────── -->
<div class="cards">
  <div class="card">
    <div class="c-l">طلبات اليوم</div>
    <div class="c-v"><?= (int)$stats['new_today'] ?></div>
  </div>
  <div class="card">
    <div class="c-l">طلبات (30 يوم)</div>
    <div class="c-v"><?= (int)$totals['orders'] ?></div>
  </div>
  <div class="card">
    <div class="c-l">إيرادات مؤكدة (30 يوم)</div>
    <div class="c-v"><?= e($money($totals['revenue'])) ?></div>
  </div>
  <div class="card">
    <div class="c-l">نسبة التأكيد</div>
    <div class="c-v <?= $rateClass((float)$totals['confirm_rate']) ?>"><?= $pct($totals['confirm_rate']) ?></div>
  </div>
  <div class="card">
    <div class="c-l">متوسط الطلب</div>
    <div class="c-v"><?= e($money($totals['aov'])) ?></div>
  </div>
  <div class="card">
    <div class="c-l">منتجات نشطة</div>
    <div class="c-v"><?= (int)$stats['active_products'] ?></div>
  </div>
</div>

<!-- ── the call queue, first: it is the daily job ───────────── -->
<section class="grp wide">
  <h3>ما ينتظر المعالجة</h3>
  <div class="status-grid">
    <?php foreach (['new', 'called', 'no_answer', 'confirmed', 'shipped', 'delivered', 'cancelled'] as $key): ?>
      <?php $s = $statuses[$key] ?? ['n' => 0, 'value' => 0]; ?>
      <a class="status-cell <?= in_array($key, ['new', 'no_answer'], true) && $s['n'] > 0 ? 'needs-action' : '' ?>"
         href="<?= base_url('admin/leads.php?status=' . $key) ?>">
        <span class="st st-<?= e($key) ?>"><?= e($statusLabels[$key]) ?></span>
        <strong><?= (int)$s['n'] ?></strong>
        <small><?= e($money($s['value'])) ?></small>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<div class="report-cols">
  <!-- ── 30-day trend ───────────────────────────────────────── -->
  <section class="grp">
    <h3>الإيرادات اليومية</h3>
    <?php
      $max = 0.0;
      foreach ($daily as $d) $max = max($max, (float)$d['revenue']);
    ?>
    <?php if ($max <= 0): ?>
      <p class="hint">لا توجد إيرادات مؤكدة في آخر 30 يوم.</p>
    <?php else: ?>
      <div class="spark">
        <?php foreach ($daily as $d): ?>
          <div class="spark-col" style="height:<?= max(2, round((float)$d['revenue'] / $max * 100)) ?>%"
               title="<?= e($d['date']) ?> — <?= e($money($d['revenue'])) ?> · <?= (int)$d['orders'] ?> طلب"></div>
        <?php endforeach; ?>
      </div>
      <div class="spark-axis">
        <span><?= e($daily[0]['date']) ?></span>
        <span>أعلى يوم: <?= e($money($max)) ?></span>
        <span><?= e($daily[count($daily) - 1]['date']) ?></span>
      </div>
    <?php endif; ?>
    <p class="hint"><a href="<?= base_url('admin/reports.php?preset=30d') ?>">التقرير الكامل ←</a></p>
  </section>

  <!-- ── where the orders come from ─────────────────────────── -->
  <section class="grp">
    <h3>مصادر الطلبات (30 يوم)</h3>
    <?php if (!$sources): ?>
      <p class="hint">لا توجد طلبات في هذه الفترة.</p>
    <?php else: ?>
      <?php $maxRev = max(array_map(fn($s) => (float)$s['revenue'], $sources)) ?: 1; ?>
      <ul class="src-list">
        <?php foreach ($sources as $s): ?>
          <li>
            <span class="src src-<?= e($s['source']) ?>"><?= e($sourceLabels[$s['source']] ?? $s['source']) ?></span>
            <div class="src-bar"><span style="width:<?= round((float)$s['revenue'] / $maxRev * 100) ?>%"></span></div>
            <span class="src-val"><?= e($money($s['revenue'])) ?></span>
            <span class="src-meta <?= $rateClass((float)$s['confirm_rate']) ?>"><?= $pct($s['confirm_rate']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<!-- ── best pages ───────────────────────────────────────────── -->
<?php if ($products): ?>
<section class="grp wide">
  <h3>أفضل صفحات الهبوط (30 يوم)</h3>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>الصفحة</th><th>طلبات</th><th>مؤكدة</th><th>نسبة التأكيد</th><th>الإيرادات</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($products as $p): ?>
        <tr>
          <td><?= e($p['title']) ?></td>
          <td><?= (int)$p['orders'] ?></td>
          <td><?= (int)$p['confirmed'] ?></td>
          <td class="<?= $rateClass((float)$p['confirm_rate']) ?>"><?= $pct($p['confirm_rate']) ?></td>
          <td><strong><?= e($money($p['revenue'])) ?></strong></td>
          <td><a class="btn-sm" href="<?= base_url('admin/product-edit.php?id=' . (int)$p['id']) ?>">تعديل</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<!-- ── latest orders ────────────────────────────────────────── -->
<section class="grp wide">
  <h3>آخر الطلبات</h3>
  <div class="tbl-wrap">
  <table class="tbl">
  <thead><tr><th>#</th><th>العميل</th><th>الهاتف</th><th>المنتج</th><th>المبلغ</th><th>الحالة</th><th>التاريخ</th><th></th></tr></thead>
  <tbody>
  <?php if (!$recent): ?>
    <tr><td colspan="8" class="empty-row">لا توجد طلبات بعد.</td></tr>
  <?php endif; ?>
  <?php foreach ($recent as $r): ?>
  <tr>
    <td>#<?= (int)$r['id'] ?></td>
    <td><?= e($r['fullname']) ?></td>
    <td><a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a></td>
    <td><?= e($r['product_title'] ?? '-') ?></td>
    <td><?= e($money($r['total_price'])) ?></td>
    <td><span class="st st-<?= e($r['status']) ?>"><?= e($statusLabels[$r['status']] ?? $r['status']) ?></span></td>
    <td><?= e($r['created_at']) ?></td>
    <td><a class="btn-sm" href="<?= base_url('admin/lead-detail.php?id=' . $r['id']) ?>">عرض</a></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
  </table>
  </div>
</section>
