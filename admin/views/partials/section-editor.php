<?php
/**
 * The landing-page content editor.
 *
 * Replaces the raw sections_json textarea. Repeatable rows post as parallel
 * arrays (sec[features][title][], sec[features][text][], …) so the DOM order is
 * the submitted order and adding or dragging a row needs no index bookkeeping.
 *
 * The JSON view is still here, behind a toggle, for anyone who prefers it or
 * needs a key this form does not render. `sections_mode` decides which one the
 * server reads, so the two can never silently fight over the same field.
 *
 * Expects: $sections (decoded array), $product (may be null for a new product)
 */
$heroBadges = implode(', ', $sections['hero']['badges'] ?? []);
$iconChoices = ['🚚', '💵', '🛡️', '🎧', '✅', '⭐', '🔥', '🎁', '⏱️', '💎', '👌', '✦'];
?>
<div class="grp wide sec-editor pe-panel" id="sectionEditor" data-tab="content">
  <div class="sec-head">
    <h3>محتوى صفحة الهبوط</h3>
    <div class="seg-ctrl sec-mode">
      <input type="radio" id="secModeForm" name="sections_mode" value="form" checked>
      <label class="seg-opt" for="secModeForm">محرّر</label>
      <input type="radio" id="secModeJson" name="sections_mode" value="json">
      <label class="seg-opt" for="secModeJson">JSON متقدّم</label>
    </div>
  </div>
  <p class="hint">
    كل ما تكتبه هنا يظهر مباشرة في صفحة المنتج: العنوان الرئيسي، المميزات، آراء الزبناء، والأسئلة الشائعة.
  </p>

  <!-- ══════════════ FORM MODE ══════════════ -->
  <div class="sec-pane" id="secPaneForm">

    <!-- Hero -->
    <fieldset class="sec-block">
      <legend>القسم الرئيسي (Hero)</legend>
      <label>العنوان الرئيسي
        <input name="sec[hero][headline]" maxlength="200"
               placeholder="سروال كاجوال كلاس"
               value="<?= e($sections['hero']['headline'] ?? '') ?>">
        <small>يُستعمل عنوان المنتج إذا تُرك فارغاً.</small>
      </label>
      <label>العنوان الفرعي
        <input name="sec[hero][subheadline]" maxlength="300"
               placeholder="إطلالة راقية وراحة طوال اليوم"
               value="<?= e($sections['hero']['subheadline'] ?? '') ?>">
      </label>
      <div class="row2">
        <label>نقاط سريعة (افصل بفاصلة)
          <input name="sec[hero][badges]" maxlength="500"
                 placeholder="مريح بزاف, جودة عالية, الدفع عند الاستلام"
                 value="<?= e($heroBadges) ?>">
        </label>
        <label>نص زر الانتقال
          <input name="sec[hero][cta]" maxlength="80" placeholder="اطلب الآن"
                 value="<?= e($sections['hero']['cta'] ?? '') ?>">
        </label>
      </div>
    </fieldset>

    <!-- Features -->
    <fieldset class="sec-block" data-repeat="features">
      <legend>المميزات <span class="sec-count"></span></legend>
      <p class="hint">تظهر كبطاقات تحت عنوان «لماذا هذا المنتج». اسحب من ⠿ لإعادة الترتيب.</p>
      <div class="rep-rows" data-rows>
        <?php foreach (($sections['features'] ?: [[]]) as $f): ?>
        <div class="rep-row" draggable="true">
          <span class="rep-drag" title="اسحب لإعادة الترتيب">⠿</span>
          <div class="rep-fields">
            <div class="icon-field">
              <input class="icon-input" name="sec[features][icon][]" maxlength="20" placeholder="✦"
                     value="<?= e($f['icon'] ?? '') ?>">
              <div class="icon-picks">
                <?php foreach ($iconChoices as $ic): ?>
                  <button type="button" class="icon-pick" tabindex="-1"><?= $ic ?></button>
                <?php endforeach; ?>
              </div>
            </div>
            <input name="sec[features][title][]" maxlength="120" placeholder="العنوان — مثال: شحن مجاني"
                   value="<?= e($f['title'] ?? '') ?>">
            <input name="sec[features][text][]" maxlength="400" placeholder="شرح قصير"
                   value="<?= e($f['text'] ?? '') ?>">
          </div>
          <button type="button" class="rep-del" title="حذف">×</button>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn-sm rep-add">+ إضافة ميزة</button>
    </fieldset>

    <!-- Testimonials -->
    <fieldset class="sec-block" data-repeat="testimonials">
      <legend>آراء الزبناء <span class="sec-count"></span></legend>
      <div class="rep-rows" data-rows>
        <?php foreach (($sections['testimonials'] ?: [[]]) as $t): ?>
        <div class="rep-row" draggable="true">
          <span class="rep-drag" title="اسحب لإعادة الترتيب">⠿</span>
          <div class="rep-fields">
            <input class="f-narrow" name="sec[testimonials][name][]" maxlength="80" placeholder="الاسم"
                   value="<?= e($t['name'] ?? '') ?>">
            <input name="sec[testimonials][text][]" maxlength="500" placeholder="نص الرأي"
                   value="<?= e($t['text'] ?? '') ?>">
          </div>
          <button type="button" class="rep-del" title="حذف">×</button>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn-sm rep-add">+ إضافة رأي</button>
    </fieldset>

    <!-- FAQs -->
    <fieldset class="sec-block" data-repeat="faqs">
      <legend>الأسئلة الشائعة <span class="sec-count"></span></legend>
      <div class="rep-rows" data-rows>
        <?php foreach (($sections['faqs'] ?: [[]]) as $q): ?>
        <div class="rep-row" draggable="true">
          <span class="rep-drag" title="اسحب لإعادة الترتيب">⠿</span>
          <div class="rep-fields stacked-fields">
            <input name="sec[faqs][q][]" maxlength="300" placeholder="السؤال؟"
                   value="<?= e($q['q'] ?? '') ?>">
            <textarea name="sec[faqs][a][]" rows="2" maxlength="1000" placeholder="الجواب."><?= e($q['a'] ?? '') ?></textarea>
          </div>
          <button type="button" class="rep-del" title="حذف">×</button>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn-sm rep-add">+ إضافة سؤال</button>
    </fieldset>

    <!-- Countdown + CTA -->
    <fieldset class="sec-block">
      <legend>العدّاد ونص الطلب</legend>
      <div class="row2">
        <label>عنوان العدّاد
          <input name="sec[countdown_title]" maxlength="200"
                 placeholder="تخفيض 50% و الشحن السريع بالمجان"
                 value="<?= e($sections['countdown_title'] ?? '') ?>">
        </label>
        <label>نص زر الطلب
          <input name="sec[cta_text]" maxlength="120" placeholder="اطلب الآن واستفد من العرض"
                 value="<?= e($sections['cta_text'] ?? '') ?>">
        </label>
      </div>
    </fieldset>
  </div>

  <!-- ══════════════ JSON MODE ══════════════ -->
  <div class="sec-pane" id="secPaneJson" hidden>
    <p class="hint">
      للتحرير اليدوي. ما يُحفظ هو محتوى هذا الحقل عندما يكون وضع «JSON متقدّم» مفعّلاً.
      <button type="button" class="btn-sm" id="secJsonFromForm">توليد من المحرّر</button>
    </p>
    <textarea name="sections_json" id="secJsonArea" rows="18" class="mono" spellcheck="false"
      ><?= e(Sections::encode($sections)) ?></textarea>
    <div class="json-status" id="secJsonStatus"></div>
  </div>
</div>

<script>
(function () {
  var root = document.getElementById('sectionEditor');
  if (!root) return;

  /* ── mode toggle ─────────────────────────────────────────────────────── */
  var paneForm = document.getElementById('secPaneForm');
  var paneJson = document.getElementById('secPaneJson');
  var radios   = root.querySelectorAll('input[name="sections_mode"]');

  function applyMode() {
    var json = document.getElementById('secModeJson').checked;
    paneJson.hidden = !json;
    paneForm.hidden = json;
    /* Disabled inputs are not submitted, so the inactive pane cannot contribute
       fields the server would then have to guess about. */
    paneForm.querySelectorAll('input, textarea').forEach(function (el) { el.disabled = json; });
    document.getElementById('secJsonArea').disabled = !json;
  }
  radios.forEach(function (r) { r.addEventListener('change', applyMode); });
  applyMode();

  /* ── repeatable rows ─────────────────────────────────────────────────── */
  function countRows(block) {
    var label = block.querySelector('.sec-count');
    var n = block.querySelectorAll('.rep-row').length;
    if (label) label.textContent = n ? '(' + n + ')' : '';
  }

  root.querySelectorAll('[data-repeat]').forEach(function (block) {
    var rows = block.querySelector('[data-rows]');

    block.querySelector('.rep-add').addEventListener('click', function () {
      var last  = rows.querySelector('.rep-row:last-child');
      var clone = last.cloneNode(true);
      clone.querySelectorAll('input, textarea').forEach(function (el) {
        if (el.tagName === 'TEXTAREA') el.textContent = '';
        el.value = '';
      });
      rows.appendChild(clone);
      wireRow(clone, rows, block);
      countRows(block);
      var first = clone.querySelector('input:not(.icon-input), textarea') || clone.querySelector('input');
      if (first) first.focus();
    });

    rows.querySelectorAll('.rep-row').forEach(function (row) { wireRow(row, rows, block); });
    countRows(block);

    /* Drag to reorder. The submitted order is the DOM order, so moving the node
       is the whole implementation — nothing to renumber. */
    var dragging = null;
    rows.addEventListener('dragstart', function (e) {
      var row = e.target.closest('.rep-row');
      if (!row) return;
      dragging = row;
      setTimeout(function () { row.classList.add('is-dragging'); }, 0);
      e.dataTransfer.effectAllowed = 'move';
    });
    rows.addEventListener('dragend', function () {
      if (dragging) dragging.classList.remove('is-dragging');
      dragging = null;
    });
    rows.addEventListener('dragover', function (e) {
      e.preventDefault();
      if (!dragging) return;
      var target = e.target.closest('.rep-row');
      if (!target || target === dragging) return;
      var box = target.getBoundingClientRect();
      rows.insertBefore(dragging, (e.clientY < box.top + box.height / 2) ? target : target.nextSibling);
    });
  });

  function wireRow(row, rows, block) {
    var del = row.querySelector('.rep-del');
    if (del) del.onclick = function () {
      /* Always leave one row: an empty group with no row at all gives the user
         nothing to click. Empty rows are dropped server-side anyway. */
      if (rows.querySelectorAll('.rep-row').length > 1) row.remove();
      else row.querySelectorAll('input, textarea').forEach(function (el) { el.value = ''; });
      countRows(block);
    };
    row.querySelectorAll('.icon-pick').forEach(function (btn) {
      btn.onclick = function () {
        var input = row.querySelector('.icon-input');
        if (input) { input.value = btn.textContent.trim(); input.dispatchEvent(new Event('input', {bubbles: true})); }
      };
    });
  }

  /* ── JSON pane: live validation + generate-from-form ──────────────────── */
  var area   = document.getElementById('secJsonArea');
  var status = document.getElementById('secJsonStatus');

  function validate() {
    var v = area.value.trim();
    if (v === '') { status.className = 'json-status'; status.textContent = ''; area.classList.remove('bad'); return true; }
    try {
      JSON.parse(v);
      status.className = 'json-status ok';
      status.textContent = '✓ JSON صالح';
      area.classList.remove('bad');
      return true;
    } catch (err) {
      status.className = 'json-status bad';
      /* Browsers report the byte offset; turning it into a line number is the
         difference between a usable error and a shrug. */
      var m = /position (\d+)/.exec(err.message);
      var where = '';
      if (m) {
        var line = v.slice(0, Number(m[1])).split('\n').length;
        where = ' (سطر ' + line + ')';
      }
      status.textContent = '✗ ' + err.message + where;
      area.classList.add('bad');
      return false;
    }
  }
  area.addEventListener('input', validate);
  validate();

  document.getElementById('secJsonFromForm').addEventListener('click', function () {
    var g = function (n) { var el = root.querySelector('[name="' + n + '"]'); return el ? el.value.trim() : ''; };
    var list = function (name, fields) {
      var cols = fields.map(function (f) {
        return Array.from(root.querySelectorAll('[name="sec[' + name + '][' + f + '][]"]')).map(function (el) { return el.value.trim(); });
      });
      var out = [];
      for (var i = 0; i < (cols[0] || []).length; i++) {
        var row = {}, any = false;
        fields.forEach(function (f, j) { row[f] = cols[j][i] || ''; if (row[f]) any = true; });
        if (any) out.push(row);
      }
      return out;
    };
    var badges = g('sec[hero][badges]').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
    area.value = JSON.stringify({
      hero: {
        headline: g('sec[hero][headline]'), subheadline: g('sec[hero][subheadline]'),
        badges: badges, cta: g('sec[hero][cta]')
      },
      features:     list('features', ['icon', 'title', 'text']),
      testimonials: list('testimonials', ['name', 'text']),
      faqs:         list('faqs', ['q', 'a']),
      countdown_title: g('sec[countdown_title]'),
      cta_text:        g('sec[cta_text]')
    }, null, 2);
    validate();
  });

  /* Block a submit that would save invalid JSON, rather than silently keeping
     the previous value and leaving the operator to discover it later. */
  var form = root.closest('form');
  if (form) form.addEventListener('submit', function (e) {
    if (document.getElementById('secModeJson').checked && !validate()) {
      e.preventDefault();
      area.scrollIntoView({ behavior: 'smooth', block: 'center' });
      area.focus();
    }
  });
})();
</script>
