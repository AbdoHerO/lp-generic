<?php
$money = fn($v) => number_format((float)$v, 2) . ' د.م';
$pct   = fn($v) => number_format((float)$v, 1) . '%';

/** Colour a confirmation rate so a weak page is visible without reading numbers. */
$rateClass = function (float $r): string {
    if ($r >= 60) return 'rate-good';
    if ($r >= 40) return 'rate-ok';
    return 'rate-bad';
};

$sourceLabels = [
    'facebook' => 'Meta', 'tiktok' => 'TikTok', 'google' => 'Google',
    'organic'  => 'مباشر / عضوي',
];
$statusLabels = [
    'new' => 'جديد', 'called' => 'تم الاتصال', 'confirmed' => 'مؤكد', 'shipped' => 'مشحون',
    'delivered' => 'تم التسليم', 'cancelled' => 'ملغى', 'no_answer' => 'لا يرد',
];
?>
<form method="get" class="filters report-filters">
  <?php foreach ($presets as $key => [$label, , ]): ?>
    <a class="btn-sm <?= $preset === $key ? 'active' : '' ?>"
       href="<?= base_url('admin/reports.php?preset=' . $key) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
  <span class="filters-sep"></span>
  <input type="date" name="from" value="<?= e($range['from']) ?>">
  <input type="date" name="to"   value="<?= e($range['to']) ?>">
  <button class="btn">تطبيق</button>
</form>

<p class="hint report-note">
  «الإيرادات» تحتسب الطلبات المؤكدة والمشحونة والمُسلَّمة فقط — لا الطلبات الجديدة التي لم تُؤكَّد بعد.
</p>

<!-- ── headline numbers ─────────────────────────────────────── -->
<div class="cards">
  <div class="card"><div class="c-l">الطلبات</div><div class="c-v"><?= (int)$totals['orders'] ?></div></div>
  <div class="card"><div class="c-l">مؤكدة</div><div class="c-v"><?= (int)$totals['confirmed'] ?></div></div>
  <div class="card">
    <div class="c-l">نسبة التأكيد</div>
    <div class="c-v <?= $rateClass((float)$totals['confirm_rate']) ?>"><?= $pct($totals['confirm_rate']) ?></div>
  </div>
  <div class="card"><div class="c-l">الإيرادات</div><div class="c-v"><?= e($money($totals['revenue'])) ?></div></div>
  <div class="card"><div class="c-l">متوسط الطلب</div><div class="c-v"><?= e($money($totals['aov'])) ?></div></div>
  <div class="card"><div class="c-l">زبناء مختلفون</div><div class="c-v"><?= (int)$totals['customers'] ?></div></div>
</div>

<!-- ── daily trend ──────────────────────────────────────────── -->
<?php
  $max = 0.0;
  foreach ($daily as $d) $max = max($max, (float)$d['revenue']);
  $n = count($daily);
?>
<section class="grp wide">
  <h3>الإيرادات اليومية</h3>
  <?php if (!$n || $max <= 0): ?>
    <p class="hint">لا توجد إيرادات مؤكدة في هذه الفترة.</p>
  <?php else: ?>
  <div class="spark" role="img"
       aria-label="الإيرادات اليومية من <?= e($range['from']) ?> إلى <?= e($range['to']) ?>">
    <?php foreach ($daily as $d):
      $h = $max > 0 ? max(2, round((float)$d['revenue'] / $max * 100)) : 2; ?>
      <div class="spark-col" style="height:<?= $h ?>%"
           title="<?= e($d['date']) ?> — <?= e($money($d['revenue'])) ?> · <?= (int)$d['orders'] ?> طلب"></div>
    <?php endforeach; ?>
  </div>
  <div class="spark-axis">
    <span><?= e($daily[0]['date']) ?></span>
    <span>أعلى يوم: <?= e($money($max)) ?></span>
    <span><?= e($daily[$n - 1]['date']) ?></span>
  </div>
  <?php endif; ?>
</section>

<!-- ── page × source: the scaling decision ──────────────────── -->
<section class="grp wide">
  <h3>صفحة الهبوط × مصدر الزيارة</h3>
  <p class="hint">أين تضاعف الميزانية: نفس الصفحة قد تكون رابحة على منصة وخاسرة على أخرى.</p>
  <?php if (!$matrix): ?>
    <p class="hint">لا توجد بيانات في هذه الفترة.</p>
  <?php else: ?>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr>
        <th>صفحة الهبوط</th><th>المصدر</th><th>الطلبات</th><th>مؤكدة</th>
        <th>نسبة التأكيد</th><th>الإيرادات</th><th>متوسط الطلب</th>
      </tr></thead>
      <tbody>
      <?php foreach ($matrix as $r): ?>
        <tr>
          <td><a href="<?= base_url($r['slug']) ?>" target="_blank"><?= e($r['title']) ?></a></td>
          <td><span class="src src-<?= e($r['source']) ?>"><?= e($sourceLabels[$r['source']] ?? $r['source']) ?></span></td>
          <td><?= (int)$r['orders'] ?></td>
          <td><?= (int)$r['confirmed'] ?></td>
          <td class="<?= $rateClass((float)$r['confirm_rate']) ?>"><?= $pct($r['confirm_rate']) ?></td>
          <td><strong><?= e($money($r['revenue'])) ?></strong></td>
          <td><?= e($money($r['aov'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>

<div class="report-cols">
  <!-- ── by landing page ────────────────────────────────────── -->
  <section class="grp">
    <h3>حسب صفحة الهبوط</h3>
    <?php if (!$products): ?><p class="hint">لا توجد بيانات.</p><?php else: ?>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th>الصفحة</th><th>طلبات</th><th>تأكيد</th><th>الإيرادات</th></tr></thead>
        <tbody>
        <?php foreach ($products as $r): ?>
          <tr>
            <td>
              <?= e($r['title']) ?>
              <?= $r['status'] ? '' : ' <span class="st st-cancelled">معطل</span>' ?>
            </td>
            <td><?= (int)$r['orders'] ?></td>
            <td class="<?= $rateClass((float)$r['confirm_rate']) ?>"><?= $pct($r['confirm_rate']) ?></td>
            <td><?= e($money($r['revenue'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>

  <!-- ── by source ──────────────────────────────────────────── -->
  <section class="grp">
    <h3>حسب المصدر</h3>
    <?php if (!$sources): ?><p class="hint">لا توجد بيانات.</p><?php else: ?>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th>المصدر</th><th>طلبات</th><th>تأكيد</th><th>الإيرادات</th></tr></thead>
        <tbody>
        <?php foreach ($sources as $r): ?>
          <tr>
            <td><span class="src src-<?= e($r['source']) ?>"><?= e($sourceLabels[$r['source']] ?? $r['source']) ?></span></td>
            <td><?= (int)$r['orders'] ?></td>
            <td class="<?= $rateClass((float)$r['confirm_rate']) ?>"><?= $pct($r['confirm_rate']) ?></td>
            <td><?= e($money($r['revenue'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>
</div>

<!-- ── campaigns ────────────────────────────────────────────── -->
<?php if ($campaigns): ?>
<section class="grp wide">
  <h3>حسب الحملة (utm_campaign)</h3>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>الحملة</th><th>المصدر</th><th>طلبات</th><th>مؤكدة</th><th>نسبة التأكيد</th><th>الإيرادات</th></tr></thead>
      <tbody>
      <?php foreach ($campaigns as $r): ?>
        <tr>
          <td><?= e($r['campaign']) ?></td>
          <td><span class="src src-<?= e($r['source']) ?>"><?= e($sourceLabels[$r['source']] ?? $r['source']) ?></span></td>
          <td><?= (int)$r['orders'] ?></td>
          <td><?= (int)$r['confirmed'] ?></td>
          <td class="<?= $rateClass((float)$r['confirm_rate']) ?>"><?= $pct($r['confirm_rate']) ?></td>
          <td><?= e($money($r['revenue'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<!-- ── call queue ───────────────────────────────────────────── -->
<section class="grp wide">
  <h3>حالة الطلبات</h3>
  <div class="status-grid">
    <?php foreach ($statuses as $key => $s): ?>
      <a class="status-cell" href="<?= base_url('admin/leads.php?status=' . $key
            . '&from=' . urlencode($range['from']) . '&to=' . urlencode($range['to'])) ?>">
        <span class="st st-<?= e($key) ?>"><?= e($statusLabels[$key] ?? $key) ?></span>
        <strong><?= (int)$s['n'] ?></strong>
        <small><?= e($money($s['value'])) ?></small>
      </a>
    <?php endforeach; ?>
  </div>
</section>
