<?php
$currentQs = ($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
$waNumber  = fn(string $p) => preg_replace('/[^0-9]/', '', $p);
$qs = function (array $over = []) use ($filters, $res) {
    $p = array_filter(array_merge($filters, ['page' => $res['page']], $over), fn($v) => $v !== '' && $v !== null);
    return $p ? '?' . http_build_query($p) : '';
};
?>
<p class="hint">
  زوار أدخلوا رقم هاتف صحيح في نموذج الطلب ثم غادروا قبل الإرسال.
  الإعلان الذي جلبهم مدفوع أصلاً، ومكالمة واحدة تحوّل جزءاً منهم.
  <strong>لا تُحتسب كطلبات</strong> ولا تظهر في الإيرادات ولا تُرسل لأي بكسل.
</p>

<div class="cards">
  <div class="card"><div class="c-l">لم تكتمل (30 يوم)</div><div class="c-v"><?= (int)$stats['pending'] ?></div></div>
  <div class="card"><div class="c-l">اكتملت لاحقاً</div><div class="c-v"><?= (int)$stats['converted'] ?></div></div>
  <div class="card"><div class="c-l">نسبة الإكمال الذاتي</div><div class="c-v"><?= number_format($stats['rate'], 1) ?>%</div></div>
</div>

<form method="get" class="filters">
  <input type="text" name="phone" placeholder="هاتف" value="<?= e($filters['phone']) ?>">
  <select name="product_id">
    <option value="">كل المنتجات</option>
    <?php foreach ($products as $p): ?>
      <option value="<?= (int)$p['id'] ?>" <?= (string)$filters['product_id'] === (string)$p['id'] ? 'selected' : '' ?>>
        <?= e($p['title']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <button class="btn">تصفية</button>
  <span class="filters-count"><?= (int)$res['total'] ?> سجل</span>
</form>

<div class="tbl-wrap">
<table class="tbl">
  <thead><tr><th>الهاتف</th><th>الاسم</th><th>المنتج</th><th>المصدر</th><th>آخر نشاط</th><th></th></tr></thead>
  <tbody>
  <?php if (!$res['rows']): ?>
    <tr><td colspan="6" class="empty-row">لا توجد طلبات غير مكتملة.</td></tr>
  <?php endif; ?>
  <?php foreach ($res['rows'] as $d): ?>
    <tr>
      <td class="lead-contact">
        <a href="tel:<?= e($d['phone']) ?>"><?= e($d['phone']) ?></a>
        <a class="wa-link" target="_blank" rel="noopener"
           href="https://wa.me/<?= e($waNumber($d['phone'])) ?>">wa</a>
      </td>
      <td><?= e($d['fullname'] ?: '—') ?></td>
      <td>
        <?php if ($d['product_slug']): ?>
          <a target="_blank" href="<?= base_url($d['product_slug']) ?>"><?= e($d['product_title']) ?></a>
        <?php else: ?>—<?php endif; ?>
      </td>
      <td><span class="src src-<?= e($d['source'] ?: 'organic') ?>"><?= e($d['source'] ?: 'مباشر') ?></span></td>
      <td class="nowrap"><?= e($d['updated_at']) ?></td>
      <td>
        <form method="post" style="display:inline" onsubmit="return confirm('حذف هذا السجل؟')">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="qs" value="<?= e($currentQs) ?>">
          <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
          <button class="btn-sm danger">حذف</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php if ($res['pages'] > 1): ?>
<div class="pager">
  <?php for ($i = 1; $i <= $res['pages']; $i++): ?>
    <a class="<?= $i === $res['page'] ? 'active' : '' ?>"
       href="<?= base_url('admin/drafts.php' . $qs(['page' => $i])) ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
