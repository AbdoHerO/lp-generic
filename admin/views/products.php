<?php if (isset($_GET['clone_err'])): ?>
  <div class="notice err">فشل نسخ المنتج. تحقق من السجلات.</div>
<?php endif; ?>
<div class="actions">
  <a class="btn" href="<?= base_url('admin/product-edit.php') ?>">+ منتج جديد</a>
</div>
<div class="tbl-wrap">
<table class="tbl">
<thead><tr><th>صورة</th><th>المنتج</th><th>الفئة</th><th>السعر</th><th>الحالة</th><th>الرابط</th><th></th></tr></thead>
<tbody>
<?php foreach ($products as $p): ?>
<tr>
  <td><?php if ($p['cover_image']): ?><img class="thumb" src="<?= e(upload_url($p['cover_image'])) ?>" alt=""><?php endif; ?></td>
  <td><?= e($p['title']) ?></td>
  <td><?= e($p['category_name'] ?? '-') ?></td>
  <td><?= e(number_format((float)$p['base_price'],2)) ?> د.م</td>
  <td><?= $p['status'] ? '<span class="st st-confirmed">نشط</span>' : '<span class="st st-cancelled">معطل</span>' ?></td>
  <td><a target="_blank" href="<?= base_url($p['slug']) ?>">/<?= e($p['slug']) ?></a></td>
  <td>
    <a class="btn-sm" href="<?= base_url('admin/product-edit.php?id=' . $p['id']) ?>">تعديل</a>
    <a class="btn-sm" target="_blank" href="<?= base_url($p['slug'] . '?preview=1') ?>" title="معاينة الصفحة">👁 معاينة</a>
    <form method="post" action="<?= base_url('admin/product-clone.php') ?>" style="display:inline" onsubmit="return confirm('نسخ هذا المنتج إلى منتج جديد؟')">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
      <button class="btn-sm" type="submit" title="إنشاء نسخة من هذا المنتج">📋 نسخ</button>
    </form>
    <form method="post" action="<?= base_url('admin/product-delete.php') ?>" style="display:inline" onsubmit="return confirm('حذف نهائي؟')">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
      <button class="btn-sm danger" type="submit">حذف</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
