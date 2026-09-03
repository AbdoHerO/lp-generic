<?php if ($msg): ?><div class="al ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="al err"><?= e($err) ?></div><?php endif; ?>

<p class="hint">
  الفئات تظهر كشريط تصفية في الصفحة الرئيسية، ولكل فئة رابط خاص
  <code>/category/{slug}</code> يمكن توجيه الإعلانات إليه.
</p>

<div class="tbl-wrap">
<table class="tbl">
  <thead><tr><th>الترتيب</th><th>الاسم</th><th>الرابط</th><th>المنتجات</th><th></th></tr></thead>
  <tbody>
  <?php if (!$rows): ?>
    <tr><td colspan="5" class="empty-row">لا توجد فئات بعد.</td></tr>
  <?php endif; ?>
  <?php foreach ($rows as $c): ?>
    <tr>
      <td><?= (int)$c['position'] ?></td>
      <td><?= e($c['name']) ?></td>
      <td><a target="_blank" href="<?= base_url('category/' . $c['slug']) ?>">/category/<?= e($c['slug']) ?></a></td>
      <td>
        <?php if ((int)$c['product_count'] > 0): ?>
          <a href="<?= base_url('admin/products.php?category_id=' . (int)$c['id']) ?>"><?= (int)$c['product_count'] ?></a>
        <?php else: ?>
          <span class="hint">0</span>
        <?php endif; ?>
      </td>
      <td class="row-actions">
        <a class="btn-sm" href="<?= base_url('admin/categories.php?edit=' . (int)$c['id']) ?>#catForm">تعديل</a>
        <form method="post" style="display:inline"
              onsubmit="return confirm('حذف هذه الفئة؟ المنتجات لن تُحذف، ستصبح بدون فئة.')">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
          <button class="btn-sm danger">حذف</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<hr style="margin:26px 0">

<section id="catForm">
<h2 class="sec-title"><?= $editing ? 'تعديل الفئة' : 'إضافة فئة' ?></h2>
<form method="post" class="inline-form">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="save">
  <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>

  <label class="stacked">
    <span>الاسم</span>
    <input name="name" required placeholder="ملابس" value="<?= e($editing['name'] ?? '') ?>">
  </label>
  <label class="stacked">
    <span>الرابط (اختياري)</span>
    <input name="slug" placeholder="apparel" value="<?= e($editing['slug'] ?? '') ?>">
  </label>
  <label class="stacked">
    <span>الترتيب</span>
    <input name="position" type="number" value="<?= (int)($editing['position'] ?? 0) ?>" style="width:80px">
  </label>
  <button class="btn"><?= $editing ? 'حفظ' : '+ إضافة' ?></button>
  <?php if ($editing): ?>
    <a class="btn-sm" href="<?= base_url('admin/categories.php') ?>">إلغاء</a>
  <?php endif; ?>
</form>
</section>
