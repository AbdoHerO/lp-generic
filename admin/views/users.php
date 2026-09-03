<?php if ($msg): ?><div class="al ok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="al err"><?= e($err) ?></div><?php endif; ?>

<p class="hint">
  <strong>مدير</strong>: صلاحية كاملة.
  <strong>موظف طلبات</strong>: الطلبات والتقارير فقط — لا يمكنه تعديل أو حذف المنتجات والإعدادات.
</p>

<div class="tbl-wrap">
<table class="tbl">
  <thead><tr><th>المستخدم</th><th>الدور</th><th>الحالة</th><th>آخر دخول</th><th>أُنشئ</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $u): ?>
    <tr>
      <td>
        <strong><?= e($u['username']) ?></strong>
        <?php if ((int)$u['id'] === (int)admin_id()): ?><span class="hint">(أنت)</span><?php endif; ?>
      </td>
      <td><span class="st <?= $u['role'] === 'admin' ? 'st-confirmed' : 'st-new' ?>">
        <?= e(Admin::ROLES[$u['role']] ?? $u['role']) ?></span></td>
      <td><?= $u['status'] ? '<span class="st st-confirmed">مفعّل</span>' : '<span class="st st-cancelled">موقوف</span>' ?></td>
      <td><?= e($u['last_login_at'] ?: '—') ?></td>
      <td><?= e($u['created_at']) ?></td>
      <td class="row-actions">
        <a class="btn-sm" href="<?= base_url('admin/users.php?edit=' . (int)$u['id']) ?>#userForm">تعديل</a>
        <?php if ((int)$u['id'] !== (int)admin_id()): ?>
        <form method="post" style="display:inline" onsubmit="return confirm('حذف الحساب «<?= e($u['username']) ?>»؟')">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
          <button class="btn-sm danger">حذف</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<hr style="margin:26px 0">

<section id="userForm">
<h2 class="sec-title"><?= $editing ? 'تعديل الحساب' : 'إضافة مستخدم' ?></h2>
<form method="post" class="form-grid">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="save">
  <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>

  <div class="grp">
    <h3>الحساب</h3>
    <label>اسم المستخدم
      <input name="username" required minlength="3" autocomplete="off"
             value="<?= e($editing['username'] ?? '') ?>">
      <small>أحرف لاتينية صغيرة وأرقام و <code>. _ -</code></small>
    </label>
    <label>كلمة المرور
      <input type="password" name="password" autocomplete="new-password" minlength="8"
             placeholder="<?= $editing ? 'اتركها فارغة للإبقاء على الحالية' : '8 أحرف على الأقل' ?>"
             <?= $editing ? '' : 'required' ?>>
    </label>
    <label>الدور
      <select name="role">
        <?php foreach (Admin::ROLES as $k => $label): ?>
          <option value="<?= e($k) ?>" <?= ($editing['role'] ?? 'agent') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="cb"><input type="checkbox" name="status" <?= (!$editing || $editing['status']) ? 'checked' : '' ?>> مفعّل</label>
  </div>

  <div class="grp wide" style="display:flex;gap:10px;align-items:center">
    <button class="btn-buy" type="submit" style="width:auto"><?= $editing ? 'حفظ' : '+ إضافة المستخدم' ?></button>
    <?php if ($editing): ?><a class="btn ghost" href="<?= base_url('admin/users.php') ?>">إلغاء</a><?php endif; ?>
  </div>
</form>
</section>
