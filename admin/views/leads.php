<form method="get" class="filters">
  <input type="text" name="phone"  placeholder="هاتف" value="<?= e($filters['phone']) ?>">
  <select name="status">
    <option value="">كل الحالات</option>
    <?php foreach (['new','called','confirmed','shipped','delivered','cancelled','no_answer'] as $s): ?>
      <option value="<?= $s ?>" <?= $filters['status']===$s?'selected':'' ?>><?= $s ?></option>
    <?php endforeach; ?>
  </select>
  <select name="product_id">
    <option value="">كل المنتجات</option>
    <?php foreach ($products as $p): ?>
      <option value="<?= (int)$p['id'] ?>" <?= (string)$filters['product_id']===(string)$p['id']?'selected':'' ?>><?= e($p['title']) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="text" name="source" placeholder="المصدر" value="<?= e($filters['source']) ?>">
  <input type="date" name="from" value="<?= e($filters['from']) ?>">
  <input type="date" name="to"   value="<?= e($filters['to']) ?>">
  <button class="btn">تصفية</button>
  <a class="btn" href="<?= base_url('admin/leads-export.php') ?>">تصدير CSV</a>
</form>

<table class="tbl">
<thead><tr><th>#</th><th>التاريخ</th><th>العميل</th><th>الهاتف</th><th>المنتج</th><th>العرض</th><th>المبلغ</th><th>الحالة</th><th>المصدر</th><th></th></tr></thead>
<tbody>
<?php foreach ($res['rows'] as $r): ?>
<tr>
  <td>#<?= (int)$r['id'] ?></td>
  <td><?= e($r['created_at']) ?></td>
  <td><?= e($r['fullname']) ?></td>
  <td><a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a></td>
  <td><?= e($r['product_title']) ?></td>
  <td><?= e($r['offer_label']) ?></td>
  <td><?= e(number_format((float)$r['total_price'],2)) ?></td>
  <td><span class="st st-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
  <td><?= e($r['source']) ?></td>
  <td><a class="btn-sm" href="<?= base_url('admin/lead-detail.php?id=' . $r['id']) ?>">عرض</a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<?php
$totalPages = max(1, (int)ceil($res['total'] / $res['per_page']));
$qs = $_GET; unset($qs['page']);
$baseQs = http_build_query($qs);
?>
<nav class="pager">
  <?php for ($i=1;$i<=$totalPages;$i++): ?>
    <a class="<?= $i===$res['page']?'active':'' ?>" href="?<?= $baseQs ?>&page=<?= $i ?>"><?= $i ?></a>
  <?php endfor; ?>
</nav>
