<div class="cards">
  <div class="card"><div class="c-l">إجمالي الطلبات</div><div class="c-v"><?= (int)$stats['total_leads'] ?></div></div>
  <div class="card"><div class="c-l">طلبات اليوم</div><div class="c-v"><?= (int)$stats['new_today'] ?></div></div>
  <div class="card"><div class="c-l">منتجات نشطة</div><div class="c-v"><?= (int)$stats['active_products'] ?></div></div>
  <div class="card"><div class="c-l">طلبات مؤكدة</div><div class="c-v"><?= (int)$stats['confirmed'] ?></div></div>
  <div class="card"><div class="c-l">طلبات ملغاة</div><div class="c-v"><?= (int)$stats['cancelled'] ?></div></div>
</div>

<h2 class="sec-title">آخر الطلبات</h2>
<div class="tbl-wrap">
<table class="tbl">
<thead><tr><th>#</th><th>العميل</th><th>الهاتف</th><th>المنتج</th><th>المبلغ</th><th>الحالة</th><th>التاريخ</th><th></th></tr></thead>
<tbody>
<?php foreach ($recent as $r): ?>
<tr>
  <td>#<?= (int)$r['id'] ?></td>
  <td><?= e($r['fullname']) ?></td>
  <td><?= e($r['phone']) ?></td>
  <td><?= e($r['product_title']) ?></td>
  <td><?= e(number_format((float)$r['total_price'],2)) ?> د.م</td>
  <td><span class="st st-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
  <td><?= e($r['created_at']) ?></td>
  <td><a class="btn-sm" href="<?= base_url('admin/lead-detail.php?id=' . $r['id']) ?>">عرض</a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
