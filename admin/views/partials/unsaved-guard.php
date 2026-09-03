<script>
/* Warns before leaving a form with unsaved edits.
   The editor is a long form that people tune for a while; a stray back-button
   or a clicked "preview" link otherwise costs the whole session's copy. */
(function () {
  var form = document.querySelector('form.form-grid');
  if (!form) return;

  var clean = serialize();
  var submitting = false;

  function serialize() {
    var out = [];
    form.querySelectorAll('input, textarea, select').forEach(function (el) {
      if (el.type === 'file' || el.name === '_csrf') return;
      out.push(el.name + '=' + (el.type === 'checkbox' || el.type === 'radio' ? el.checked : el.value));
    });
    return out.join('&');
  }

  function dirty() { return !submitting && serialize() !== clean; }

  form.addEventListener('submit', function () { submitting = true; });

  window.addEventListener('beforeunload', function (e) {
    if (!dirty()) return;
    e.preventDefault();
    e.returnValue = '';   // browsers show their own wording
  });

  /* Links inside the panel (preview, back to list) are the common way to lose
     work, and beforeunload on a same-tab navigation is easy to dismiss without
     reading — so ask in plain language first. */
  document.querySelectorAll('.page-actions a, .side nav a').forEach(function (a) {
    a.addEventListener('click', function (e) {
      if (a.target === '_blank' || !dirty()) return;
      if (!confirm('لديك تعديلات غير محفوظة. هل تريد المغادرة دون حفظ؟')) e.preventDefault();
    });
  });

  /* A visible marker beats a dialog nobody expected. */
  var badge = document.createElement('span');
  badge.className = 'dirty-badge';
  badge.textContent = '● تعديلات غير محفوظة';
  badge.hidden = true;
  var head = document.querySelector('.top h1');
  if (head) head.appendChild(badge);

  ['input', 'change'].forEach(function (ev) {
    form.addEventListener(ev, function () { badge.hidden = !dirty(); });
  });
})();
</script>
