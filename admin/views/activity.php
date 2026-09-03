<?php
$actionLabels = [
    'create' => 'إنشاء', 'update' => 'تعديل', 'delete' => 'حذف',
    'login'  => 'دخول',  'bulk'   => 'إجراء جماعي',
];
$entityLabels = [
    'product' => 'منتج', 'pixel' => 'بكسل', 'category' => 'فئة',
    'settings' => 'إعدادات', 'admin' => 'مستخدم', 'lead' => 'طلب',
];
$qs = function (array $over = []) use ($filters, $res) {
    $p = array_filter(array_merge($filters, ['page' => $res['page']], $over),
                      fn($v) => $v !== '' && $v !== null);
    return $p ? '?' . http_build_query($p) : '';
};
?>
<?php if ($logs): ?>
<section class="grp wide">
  <h3>أحدث الأخطاء التقنية</h3>
  <p class="hint">من <code>storage/logs/</code> — آخر 7 أيام، مستوى تنبيه فما فوق.</p>
  <ul class="log-list">
    <?php foreach ($logs as $l): ?>
      <li class="log-<?= e($l['level'] ?? 'info') ?>">
        <span class="log-level"><?= e($l['level'] ?? '') ?></span>
        <span class="log-time"><?= e(substr((string)($l['ts'] ?? ''), 0, 19)) ?></span>
        <span class="log-msg"><?= e($l['message'] ?? '') ?></span>
        <?php if (!empty($l['context'])): ?>
          <code class="log-ctx"><?= e(json_encode($l['context'], JSON_UNESCAPED_UNICODE)) ?></code>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<form method="get" class="filters">
  <select name="entity">
    <option value="">كل العناصر</option>
    <?php foreach ($entityLabels as $k => $label): ?>
      <option value="<?= e($k) ?>" <?= $filters['entity'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="action">
    <option value="">كل الإجراءات</option>
    <?php foreach ($actionLabels as $k => $label): ?>
      <option value="<?= e($k) ?>" <?= $filters['action'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="admin_id">
    <option value="">كل المستخدمين</option>
    <?php foreach ($admins as $a): ?>
      <option value="<?= (int)$a['id'] ?>" <?= (string)$filters['admin_id'] === (string)$a['id'] ? 'selected' : '' ?>>
        <?= e($a['username']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <button class="btn">تصفية</button>
  <span class="filters-count"><?= (int)$res['total'] ?> سجل</span>
</form>

<div class="tbl-wrap">
<table class="tbl">
  <thead><tr><th>التاريخ</th><th>المستخدم</th><th>الإجراء</th><th>العنصر</th><th>التفاصيل</th><th>IP</th></tr></thead>
  <tbody>
  <?php if (!$res['rows']): ?>
    <tr><td colspan="6" class="empty-row">لا توجد سجلات مطابقة.</td></tr>
  <?php endif; ?>
  <?php foreach ($res['rows'] as $r): ?>
    <tr>
      <td class="nowrap"><?= e($r['created_at']) ?></td>
      <td><?= e($r['admin_name'] ?? '—') ?></td>
      <td><span class="st st-<?= $r['action'] === 'delete' ? 'cancelled' : ($r['action'] === 'create' ? 'confirmed' : 'new') ?>">
        <?= e($actionLabels[$r['action']] ?? $r['action']) ?></span></td>
      <td>
        <?= e($entityLabels[$r['entity']] ?? $r['entity']) ?>
        <?php if ($r['entity_id']): ?>
          <?php if ($r['entity'] === 'product'): ?>
            <a href="<?= base_url('admin/product-edit.php?id=' . (int)$r['entity_id']) ?>">#<?= (int)$r['entity_id'] ?></a>
          <?php else: ?>
            <span class="hint">#<?= (int)$r['entity_id'] ?></span>
          <?php endif; ?>
        <?php endif; ?>
      </td>
      <td><?= e($r['summary'] ?? '') ?></td>
      <td class="hint"><?= e($r['ip'] ?? '') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php if ($res['pages'] > 1): ?>
<div class="pager">
  <?php for ($i = 1; $i <= $res['pages']; $i++): ?>
    <a class="<?= $i === $res['page'] ? 'active' : '' ?>"
       href="<?= base_url('admin/activity.php' . $qs(['page' => $i])) ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
