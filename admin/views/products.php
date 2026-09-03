<?php
$qs = function (array $over = []) use ($filters, $res) {
    $p = array_filter(array_merge($filters, ['page' => $res['page']], $over),
                      fn($v) => $v !== '' && $v !== null);
    return $p ? '?' . http_build_query($p) : '';
};
?>
<?php if (!empty($msg)): ?><div class="al ok"><?= e($msg) ?></div><?php endif; ?>
<?php if (!empty($err)): ?><div class="al err"><?= e($err) ?></div><?php endif; ?>

<?php $inTrash = !empty($filters['trashed']); ?>
<div class="actions">
  <?php if (!$inTrash): ?>
    <a class="btn" href="<?= base_url('admin/product-edit.php') ?>">+ منتج جديد</a>
  <?php endif; ?>
  <?php if (!empty($trashCount) || $inTrash): ?>
    <a class="btn ghost" href="<?= base_url('admin/products.php' . ($inTrash ? '' : '?trashed=1')) ?>">
      <?= $inTrash ? '← العودة للمنتجات' : '🗑 المهملات (' . (int)$trashCount . ')' ?>
    </a>
  <?php endif; ?>
</div>
<?php if ($inTrash): ?>
  <div class="al warn">
    منتجات متقاعدة: لا تظهر في المتجر، لكن طلباتها محفوظة.
    <strong>الحذف النهائي يمحو الطلبات المرتبطة أيضاً ولا يمكن التراجع عنه.</strong>
  </div>
<?php endif; ?>

<!-- ── filters ─────────────────────────────────────────────── -->
<form method="get" class="filters">
  <input type="search" name="q" placeholder="ابحث بالعنوان أو الرابط…" value="<?= e($filters['q']) ?>">
  <select name="status">
    <option value="">كل الحالات</option>
    <option value="1" <?= $filters['status'] === '1' ? 'selected' : '' ?>>نشط</option>
    <option value="0" <?= $filters['status'] === '0' ? 'selected' : '' ?>>معطل</option>
  </select>
  <select name="category_id">
    <option value="">كل الفئات</option>
    <?php foreach (($cats ?? []) as $c): ?>
      <option value="<?= (int)$c['id'] ?>" <?= (string)($filters['category_id'] ?? '') === (string)$c['id'] ? 'selected' : '' ?>>
        <?= e($c['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <select name="pixel_id">
    <option value="">كل البكسلات</option>
    <?php foreach ($pixels as $platform => $list): ?>
      <?php foreach ($list as $px): ?>
        <option value="<?= (int)$px['id'] ?>" <?= (string)$filters['pixel_id'] === (string)$px['id'] ? 'selected' : '' ?>>
          <?= e($px['name']) ?>
        </option>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </select>
  <button class="btn">تصفية</button>
  <?php if (array_filter($filters, fn($v) => $v !== '' && $v !== null)): ?>
    <a class="btn-sm" href="<?= base_url('admin/products.php') ?>">مسح</a>
  <?php endif; ?>
  <span class="filters-count"><?= (int)$res['total'] ?> منتج</span>
</form>

<form method="post" id="bulkProducts">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="f_q"        value="<?= e($filters['q']) ?>">
  <input type="hidden" name="f_status"   value="<?= e($filters['status']) ?>">
  <input type="hidden" name="f_pixel_id" value="<?= e($filters['pixel_id']) ?>">
  <input type="hidden" name="f_category_id" value="<?= e($filters['category_id'] ?? '') ?>">
  <input type="hidden" name="f_trashed" value="<?= e($filters['trashed'] ?? '') ?>">
  <input type="hidden" name="f_page"     value="<?= (int)$res['page'] ?>">
  <input type="hidden" name="bulk_action" id="bulkAction" value="">
  <input type="hidden" name="confirm_delete" id="bulkConfirmDelete" value="">
  <input type="hidden" name="bulk_pixel" id="bulkPixelValue" value="">

  <!-- ── bulk toolbar ─────────────────────────────────────── -->
  <div class="bulk-bar" id="pBulkBar">
    <span class="bulk-count" id="pBulkCount">0 محدد</span>
    <button type="button" class="btn-sm" data-bulk="activate">تفعيل</button>
    <button type="button" class="btn-sm" data-bulk="deactivate">تعطيل</button>

    <span class="filters-sep"></span>
    <select id="bulkPixelPick" class="bulk-select">
      <option value="">— اختر بكسلاً —</option>
      <option value="__default">افتراضي (حسب المنصة)</option>
      <option value="0">بدون تتبع</option>
      <?php foreach (['facebook' => 'Meta', 'tiktok' => 'TikTok'] as $plat => $label): ?>
        <?php foreach (($pixels[$plat] ?? []) as $px): ?>
          <option value="<?= (int)$px['id'] ?>" data-platform="<?= e($plat) ?>">
            <?= e($label) ?> — <?= e($px['name']) ?>
          </option>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </select>
    <button type="button" class="btn-sm" data-bulk="set_fb_pixel">اضبط بكسل Meta</button>
    <button type="button" class="btn-sm" data-bulk="set_tt_pixel">اضبط بكسل TikTok</button>

    <span class="filters-sep"></span>
    <?php if ($inTrash): ?>
      <button type="button" class="btn-sm" data-bulk="restore">استعادة</button>
      <button type="button" class="btn-del-bulk" data-bulk="purge">حذف نهائي</button>
    <?php else: ?>
      <button type="button" class="btn-del-bulk" data-bulk="delete">نقل للمهملات</button>
    <?php endif; ?>
    <button type="button" class="bulk-clear" id="pBulkClear">إلغاء التحديد</button>
  </div>

  <div class="tbl-wrap">
  <table class="tbl" id="productsTable">
  <thead><tr>
    <th class="th-chk"><label class="cb-row"><input type="checkbox" id="pCheckAll"><span class="chk-box"></span></label></th>
    <th>صورة</th><th>المنتج</th><th>الفئة</th><th>السعر</th><th>طلبات</th>
    <th>الحالة</th><th>البكسلات</th><th>الرابط</th><th></th>
  </tr></thead>
  <tbody>
  <?php if (!$res['rows']): ?>
    <tr><td colspan="10" class="empty-row">لا توجد منتجات مطابقة.</td></tr>
  <?php endif; ?>
  <?php foreach ($res['rows'] as $p): ?>
  <tr>
    <td class="td-chk">
      <label class="cb-row">
        <input type="checkbox" name="ids[]" value="<?= (int)$p['id'] ?>" class="p-chk">
        <span class="chk-box"></span>
      </label>
    </td>
    <td><?php if ($p['cover_image']): ?><img class="thumb" src="<?= e(upload_url($p['cover_image'])) ?>" alt="" loading="lazy"><?php endif; ?></td>
    <td><?= e($p['title']) ?></td>
    <td><?= e($p['category_name'] ?? '-') ?></td>
    <td><?= e(number_format((float)$p['base_price'], 2)) ?> د.م</td>
    <td><?= (int)($p['lead_count'] ?? 0) ?></td>
    <td><?= $p['status'] ? '<span class="st st-confirmed">نشط</span>' : '<span class="st st-cancelled">معطل</span>' ?></td>
    <td class="px-cell">
      <span class="px-tag fb" title="Meta">f · <?= e(Pixel::describeChoice($p['fb_pixel_id'] ?? null, 'facebook')) ?></span>
      <span class="px-tag tt" title="TikTok">tt · <?= e(Pixel::describeChoice($p['tt_pixel_id'] ?? null, 'tiktok')) ?></span>
    </td>
    <td><a target="_blank" href="<?= base_url($p['slug']) ?>">/<?= e($p['slug']) ?></a></td>
    <td class="row-actions">
      <?php if ($inTrash): ?>
        <button type="button" class="btn-sm" data-single="restore" data-id="<?= (int)$p['id'] ?>">استعادة</button>
        <button type="button" class="btn-sm danger" data-single="purge" data-id="<?= (int)$p['id'] ?>"
                data-title="<?= e($p['title']) ?>" data-leads="<?= (int)($p['lead_count'] ?? 0) ?>">حذف نهائي</button>
      <?php else: ?>
        <a class="btn-sm" href="<?= base_url('admin/product-edit.php?id=' . $p['id']) ?>">تعديل</a>
        <a class="btn-sm" target="_blank" href="<?= base_url($p['slug'] . '?preview=1') ?>" title="معاينة الصفحة">👁</a>
        <button type="button" class="btn-sm" data-single="clone" data-id="<?= (int)$p['id'] ?>" title="إنشاء نسخة">📋</button>
        <button type="button" class="btn-sm danger" data-single="delete" data-id="<?= (int)$p['id'] ?>"
                data-title="<?= e($p['title']) ?>" data-leads="<?= (int)($p['lead_count'] ?? 0) ?>">حذف</button>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
  </table>
  </div>
</form>

<!-- Clone posts to its own endpoint, so it lives outside the bulk form. -->
<form method="post" action="<?= base_url('admin/product-clone.php') ?>" id="cloneForm" style="display:none">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="id" id="cloneId">
</form>

<?php if ($res['pages'] > 1): ?>
<div class="pager">
  <?php for ($i = 1; $i <= $res['pages']; $i++): ?>
    <a class="<?= $i === $res['page'] ? 'active' : '' ?>"
       href="<?= base_url('admin/products.php' . $qs(['page' => $i])) ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<script>
(function () {
  var form   = document.getElementById('bulkProducts');
  var bar    = document.getElementById('pBulkBar');
  var count  = document.getElementById('pBulkCount');
  var all    = document.getElementById('pCheckAll');
  var boxes  = function () { return Array.from(form.querySelectorAll('.p-chk')); };
  var picked = function () { return boxes().filter(function (b) { return b.checked; }); };

  function refresh() {
    var n = picked().length;
    count.textContent = n + ' محدد';
    bar.classList.toggle('visible', n > 0);
    all.checked = n > 0 && n === boxes().length;
    all.indeterminate = n > 0 && n < boxes().length;
  }

  all.addEventListener('change', function () {
    boxes().forEach(function (b) { b.checked = all.checked; });
    refresh();
  });
  form.addEventListener('change', function (e) { if (e.target.classList.contains('p-chk')) refresh(); });
  document.getElementById('pBulkClear').addEventListener('click', function () {
    boxes().forEach(function (b) { b.checked = false; });
    refresh();
  });

  /* Bulk buttons */
  bar.querySelectorAll('[data-bulk]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var action = btn.dataset.bulk;
      var n = picked().length;
      if (!n) return;

      if (action === 'delete') {
        if (!confirm('نقل ' + n + ' منتج إلى المهملات؟ الطلبات المرتبطة تبقى محفوظة.')) return;
      }

      if (action === 'purge') {
        var leads = picked().reduce(function (sum, b) {
          var cell = b.closest('tr').querySelector('[data-single="purge"]');
          return sum + Number(cell ? cell.dataset.leads : 0);
        }, 0);
        var warn = 'حذف ' + n + ' منتج نهائياً؟';
        if (leads) warn += '\nسيتم حذف ' + leads + ' طلب مرتبط بها أيضاً، ولا يمكن التراجع.';
        if (!confirm(warn)) return;
        document.getElementById('bulkConfirmDelete').value = 'DELETE';
      }

      if (action === 'set_fb_pixel' || action === 'set_tt_pixel') {
        var pick = document.getElementById('bulkPixelPick');
        if (pick.value === '') { alert('اختر بكسلاً من القائمة أولاً.'); pick.focus(); return; }
        /* '' means inherit; the select cannot carry an empty value and still be
           distinguishable from "nothing chosen", hence the sentinel. */
        document.getElementById('bulkPixelValue').value = pick.value === '__default' ? '' : pick.value;

        var opt = pick.options[pick.selectedIndex];
        var wanted = action === 'set_fb_pixel' ? 'facebook' : 'tiktok';
        if (opt.dataset.platform && opt.dataset.platform !== wanted) {
          alert('البكسل المختار من منصة أخرى. اختر بكسلاً يطابق الزر الذي ضغطته.');
          return;
        }
        if (!confirm('تطبيق هذا البكسل على ' + n + ' منتج؟')) return;
      }

      document.getElementById('bulkAction').value = action;
      form.submit();
    });
  });

  /* Per-row actions */
  form.querySelectorAll('[data-single]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (btn.dataset.single === 'clone') {
        if (!confirm('نسخ هذا المنتج إلى منتج جديد؟')) return;
        document.getElementById('cloneId').value = btn.dataset.id;
        document.getElementById('cloneForm').submit();
        return;
      }
      var act = btn.dataset.single;
      if (act === 'restore') {
        if (!confirm('استعادة هذا المنتج؟')) return;
      } else if (act === 'purge') {
        var warn = 'حذف «' + btn.dataset.title + '» نهائياً؟';
        if (Number(btn.dataset.leads)) warn += '\nسيتم حذف ' + btn.dataset.leads + ' طلب مرتبط به أيضاً، ولا يمكن التراجع.';
        if (!confirm(warn)) return;
        document.getElementById('bulkConfirmDelete').value = 'DELETE';
      } else {
        if (!confirm('نقل «' + btn.dataset.title + '» إلى المهملات؟ الطلبات تبقى محفوظة.')) return;
      }

      boxes().forEach(function (b) { b.checked = b.value === btn.dataset.id; });
      document.getElementById('bulkAction').value = act;
      form.submit();
    });
  });

  refresh();
})();
</script>
