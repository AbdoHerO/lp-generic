<?php
/**
 * Live preview panel.
 *
 * The editing loop was: change a field → save → switch tab → reload → look.
 * For a landing page that gets tuned a dozen times before launch, that friction
 * is most of the work. This pins the real page beside the form and reloads it
 * on demand, at the device width being designed for.
 *
 * It renders the actual public URL in an iframe rather than re-implementing the
 * template — a preview that can disagree with the live page is worse than none.
 *
 * Only shown for a saved product: there is nothing to preview before the first
 * save, and ?preview=1 needs a real slug.
 */
if (empty($product['id']) || empty($product['slug'])) return;
$previewUrl = base_url($product['slug'] . '?preview=1');
?>
<div class="preview-dock" id="previewDock" hidden>
  <div class="preview-head">
    <strong>معاينة مباشرة</strong>
    <div class="preview-widths" role="group" aria-label="عرض الجهاز">
      <button type="button" class="btn-sm active" data-w="390">📱 هاتف</button>
      <button type="button" class="btn-sm" data-w="820">📊 لوحي</button>
      <button type="button" class="btn-sm" data-w="0">🖥 كامل</button>
    </div>
    <button type="button" class="btn-sm" id="previewReload" title="إعادة التحميل">↻</button>
    <a class="btn-sm" href="<?= e($previewUrl) ?>" target="_blank" title="فتح في تبويب جديد">↗</a>
    <button type="button" class="btn-sm" id="previewClose" title="إغلاق">×</button>
  </div>
  <div class="preview-body">
    <iframe id="previewFrame" title="معاينة صفحة المنتج" loading="lazy"></iframe>
  </div>
  <p class="preview-note">المعاينة تعرض آخر نسخة <strong>محفوظة</strong>. احفظ لترى تعديلاتك.</p>
</div>

<button type="button" class="preview-toggle" id="previewToggle">👁 معاينة</button>

<script>
(function () {
  var dock   = document.getElementById('previewDock');
  var toggle = document.getElementById('previewToggle');
  var frame  = document.getElementById('previewFrame');
  var url    = <?= json_encode($previewUrl) ?>;

  /* Loading the iframe only when the dock is first opened keeps the editor's
     own load fast — the landing page pulls fonts, images and pixel scripts. */
  var loaded = false;
  function load() {
    // Cache-bust so a save is reflected without the browser serving the old page.
    frame.src = url + '&_=' + Date.now();
    loaded = true;
  }

  function open()  { dock.hidden = false; document.body.classList.add('has-preview'); if (!loaded) load(); remember(true); }
  function close() { dock.hidden = true;  document.body.classList.remove('has-preview'); remember(false); }

  function remember(on) { try { localStorage.setItem('lp_preview_open', on ? '1' : '0'); } catch (e) {} }
  function wasOpen() { try { return localStorage.getItem('lp_preview_open') === '1'; } catch (e) { return false; } }

  toggle.addEventListener('click', function () { dock.hidden ? open() : close(); });
  document.getElementById('previewClose').addEventListener('click', close);
  document.getElementById('previewReload').addEventListener('click', load);

  dock.querySelectorAll('[data-w]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      dock.querySelectorAll('[data-w]').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      var w = Number(btn.dataset.w);
      frame.style.width = w ? w + 'px' : '100%';
    });
  });

  /* After a save the page reloads with ?saved=1, which is exactly when the
     preview should be showing the new version. */
  if (wasOpen() || /[?&]saved=1/.test(location.search)) open();

  window.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !dock.hidden) close();
  });
})();
</script>
