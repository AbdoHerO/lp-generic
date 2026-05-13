<!-- Filter bar -->
<form method="get" class="filters" id="filterForm">
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

<!-- Bulk-delete form wraps the table -->
<form method="post" action="<?= base_url('admin/leads-delete.php') ?>" id="bulkForm">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <!-- pass current filters back so redirect keeps them -->
  <input type="hidden" name="status_filter"     value="<?= e($filters['status']) ?>">
  <input type="hidden" name="product_id_filter" value="<?= e($filters['product_id']) ?>">
  <input type="hidden" name="phone_filter"      value="<?= e($filters['phone']) ?>">
  <input type="hidden" name="page_filter"       value="<?= (int)($res['page'] ?? 1) ?>">

  <!-- Bulk toolbar (hidden until ≥1 row selected) -->
  <div class="bulk-bar" id="bulkBar">
    <span class="bulk-count" id="bulkCount">0 محدد</span>
    <button type="button" class="btn-del-bulk" id="bulkDeleteBtn">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
      حذف المحدد
    </button>
    <button type="button" class="bulk-clear" id="bulkClear">إلغاء التحديد</button>
  </div>

  <div class="tbl-wrap">
  <table class="tbl" id="leadsTable">
  <thead>
    <tr>
      <th class="th-chk"><label class="cb-row"><input type="checkbox" id="checkAll"><span class="chk-box"></span></label></th>
      <th>#</th><th>التاريخ</th><th>العميل</th><th>الهاتف</th><th>المنتج</th><th>العرض</th><th>المبلغ</th><th>الحالة</th><th>المصدر</th><th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($res['rows'] as $r): ?>
  <tr data-id="<?= (int)$r['id'] ?>">
    <td class="td-chk">
      <label class="cb-row">
        <input type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>" class="row-chk">
        <span class="chk-box"></span>
      </label>
    </td>
    <td>#<?= (int)$r['id'] ?></td>
    <td><?= e($r['created_at']) ?></td>
    <td><?= e($r['fullname']) ?></td>
    <td><a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a></td>
    <td><?= e($r['product_title']) ?></td>
    <td><?= e($r['offer_label']) ?></td>
    <td><?= e(number_format((float)$r['total_price'],2)) ?></td>
    <td><span class="st st-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
    <td><?= e($r['source']) ?></td>
    <td>
      <a class="btn-sm" href="<?= base_url('admin/lead-detail.php?id=' . $r['id']) ?>">عرض</a>
      <button type="button" class="btn-sm danger btn-del-single" data-id="<?= (int)$r['id'] ?>" title="حذف">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M9 6V4h6v2"/></svg>
      </button>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
  </table>
  </div>
</form>

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

<!-- ── Confirm Delete Modal ─────────────────────────── -->
<div class="del-modal-bg" id="delModalBg">
  <div class="del-modal" role="dialog" aria-modal="true" aria-labelledby="delModalTitle">
    <div class="del-modal-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="3 6 5 6 21 6"/>
        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
        <path d="M10 11v6"/><path d="M14 11v6"/>
        <path d="M9 6V4h6v2"/>
      </svg>
    </div>
    <h3 id="delModalTitle">تأكيد الحذف</h3>
    <p id="delModalMsg">هل أنت متأكد من حذف هذا الطلب؟ لا يمكن التراجع عن هذا الإجراء.</p>
    <div class="del-modal-actions">
      <button class="del-modal-cancel" id="delModalCancel">إلغاء</button>
      <button class="del-modal-confirm" id="delModalConfirm">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M9 6V4h6v2"/></svg>
        نعم، احذف
      </button>
    </div>
  </div>
</div>

<script>
(function(){
  var form      = document.getElementById('bulkForm');
  var checkAll  = document.getElementById('checkAll');
  var rowChks   = function(){ return form.querySelectorAll('.row-chk'); };
  var bulkBar   = document.getElementById('bulkBar');
  var bulkCount = document.getElementById('bulkCount');
  var bulkDelBtn= document.getElementById('bulkDeleteBtn');
  var bulkClear = document.getElementById('bulkClear');
  var bg        = document.getElementById('delModalBg');
  var msg       = document.getElementById('delModalMsg');
  var confirmBtn= document.getElementById('delModalConfirm');
  var cancelBtn = document.getElementById('delModalCancel');
  var pendingIds= [];

  function countChecked(){
    return Array.from(rowChks()).filter(c=>c.checked).length;
  }

  function updateBar(){
    var n = countChecked();
    if(n > 0){
      bulkBar.classList.add('visible');
      bulkCount.textContent = n + ' محدد';
    } else {
      bulkBar.classList.remove('visible');
    }
  }

  function getCheckedIds(){
    return Array.from(rowChks()).filter(c=>c.checked).map(c=>parseInt(c.value));
  }

  // highlight selected rows
  function syncRowHighlight(){
    rowChks().forEach(function(c){
      c.closest('tr').classList.toggle('row-selected', c.checked);
    });
  }

  form.addEventListener('change', function(e){
    if(e.target === checkAll){
      rowChks().forEach(c=>{ c.checked = checkAll.checked; });
    }
    syncRowHighlight();
    updateBar();
    // sync check-all state
    var chks = rowChks();
    checkAll.indeterminate = false;
    if(countChecked() === 0) checkAll.checked = false;
    else if(countChecked() === chks.length) checkAll.checked = true;
    else { checkAll.checked = false; checkAll.indeterminate = true; }
  });

  bulkClear.addEventListener('click', function(){
    checkAll.checked = false;
    checkAll.indeterminate = false;
    rowChks().forEach(c=>{ c.checked = false; });
    syncRowHighlight();
    updateBar();
  });

  /* ── open modal ── */
  function openModal(ids, label){
    pendingIds = ids;
    msg.textContent = label;
    bg.classList.add('open');
    confirmBtn.focus();
  }

  /* ── bulk delete button ── */
  bulkDelBtn.addEventListener('click', function(){
    var ids = getCheckedIds();
    if(!ids.length) return;
    openModal(ids, 'هل أنت متأكد من حذف ' + ids.length + ' طلب' + (ids.length > 1 ? ' نهائياً؟' : ' نهائياً؟') + ' لا يمكن التراجع عن هذا الإجراء.');
  });

  /* ── single row trash btn ── */
  document.addEventListener('click', function(e){
    var btn = e.target.closest('.btn-del-single');
    if(!btn) return;
    var id = parseInt(btn.dataset.id);
    // uncheck all, check only this one
    rowChks().forEach(c=>{ c.checked = parseInt(c.value) === id; });
    syncRowHighlight();
    updateBar();
    openModal([id], 'هل أنت متأكد من حذف الطلب #' + id + ' نهائياً؟ لا يمكن التراجع عن هذا الإجراء.');
  });

  /* ── confirm ── */
  confirmBtn.addEventListener('click', function(){
    // ensure only pending ids are checked
    rowChks().forEach(c=>{ c.checked = pendingIds.includes(parseInt(c.value)); });
    bg.classList.remove('open');
    form.submit();
  });

  /* ── cancel / backdrop click ── */
  function closeModal(){ bg.classList.remove('open'); pendingIds = []; }
  cancelBtn.addEventListener('click', closeModal);
  bg.addEventListener('click', function(e){ if(e.target === bg) closeModal(); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeModal(); });
})();
</script>
