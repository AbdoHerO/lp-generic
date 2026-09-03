<script>
/**
 * Product editor tabs.
 *
 * Panels are not moved or re-parented — they carry data-tab and are shown or
 * hidden in place. That matters because the main product form and the offer /
 * media forms are separate elements that both contribute panels, and HTML has
 * no nested forms to re-parent them into.
 *
 * The active tab is kept in the URL so a save (which redirects) and the browser
 * back button both land where the operator was, not back on the first tab.
 */
(function () {
  var tabs   = Array.prototype.slice.call(document.querySelectorAll('.pe-tab'));
  var panels = Array.prototype.slice.call(document.querySelectorAll('.pe-panel'));
  if (!tabs.length || !panels.length) return;

  var VALID = tabs.filter(function (t) { return !t.disabled; })
                  .map(function (t) { return t.dataset.tab; });

  /* The child forms redirect to #offers / #options / #slider / #gallery; map
     those anchors onto the tab that now contains them. */
  var ANCHORS = { offers: 'offers', options: 'offers', slider: 'media', gallery: 'media' };

  function wanted() {
    var q = new URLSearchParams(location.search).get('tab');
    if (q && VALID.indexOf(q) !== -1) return q;

    var hash = location.hash.replace('#', '');
    if (ANCHORS[hash] && VALID.indexOf(ANCHORS[hash]) !== -1) return ANCHORS[hash];

    try {
      var last = sessionStorage.getItem('pe_tab');
      if (last && VALID.indexOf(last) !== -1) return last;
    } catch (e) {}

    return VALID[0];
  }

  function show(name, push) {
    if (VALID.indexOf(name) === -1) name = VALID[0];

    tabs.forEach(function (t) {
      var on = t.dataset.tab === name;
      t.classList.toggle('active', on);
      t.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    panels.forEach(function (p) {
      p.hidden = p.dataset.tab !== name;
    });

    try { sessionStorage.setItem('pe_tab', name); } catch (e) {}

    if (push) {
      var url = new URL(location.href);
      url.searchParams.set('tab', name);
      url.hash = '';
      history.replaceState(null, '', url);
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  tabs.forEach(function (t) {
    if (t.disabled) return;
    t.addEventListener('click', function () { show(t.dataset.tab, true); });
  });

  /* The checklist doubles as navigation. */
  document.querySelectorAll('.pe-goto').forEach(function (b) {
    b.addEventListener('click', function () {
      show(b.dataset.goto, true);
      var panel = document.getElementById('peReadyPanel');
      var toggle = document.getElementById('peReadyToggle');
      if (panel) panel.hidden = true;
      if (toggle) toggle.setAttribute('aria-expanded', 'false');
    });
  });

  var toggle = document.getElementById('peReadyToggle');
  var panel  = document.getElementById('peReadyPanel');
  if (toggle && panel) {
    toggle.addEventListener('click', function () {
      panel.hidden = !panel.hidden;
      toggle.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
    });
    document.addEventListener('click', function (e) {
      if (!panel.hidden && !panel.contains(e.target) && !toggle.contains(e.target)) {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* A field failing browser validation on a hidden tab reports "not focusable"
     and the form silently refuses to submit. Switch to its tab first. */
  var form = document.getElementById('productForm');
  if (form) {
    form.addEventListener('invalid', function (e) {
      var panel = e.target.closest('.pe-panel');
      if (panel && panel.hidden) show(panel.dataset.tab, true);
    }, true);
  }

  show(wanted(), false);
})();
</script>
