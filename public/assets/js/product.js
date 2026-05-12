/* Product page — modern COD landing JS
   Reads window.PRODUCT_DATA injected by product.php */
(function(){
  if (!window.PRODUCT_DATA) return;
  const D = window.PRODUCT_DATA;

  const offersList = document.getElementById('offersList');
  const offerInput = document.getElementById('offerIdInput');
  const priceHero  = document.getElementById('priceHero');
  const stickyCta  = document.getElementById('stickyCta');
  const scLabel    = document.getElementById('scLabel');
  const scPrice    = document.getElementById('scPrice');

  let activeOfferId = null;

  /* ---------- helpers ---------- */
  const fmt = n => Number(n).toLocaleString('fr-MA', { maximumFractionDigits: 0 });
  const esc  = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  /* ---------- offer rendering ---------- */
  D.offers.forEach((o) => {
    const card = document.createElement('div');
    card.className = 'offer';
    card.dataset.id = o.id;

    const tags = [];
    if (o.is_recommended) tags.push('<span class="tag rec">الأفضل</span>');
    if (o.free_shipping)  tags.push('<span class="tag free">شحن مجاني</span>');
    const compare = o.compare_price && o.compare_price > o.total_price
      ? `<s>${fmt(o.compare_price)} د.م</s>` : '';

    card.innerHTML = `
      <div class="offer-head">
        <div class="offer-qty">x${o.quantity}</div>
        <div class="offer-label">${esc(o.label)}</div>
        <div class="offer-radio"></div>
      </div>
      <div class="offer-body">
        <div class="offer-price">${fmt(o.total_price)} د.م ${compare}</div>
        ${tags.length ? `<div class="offer-meta">${tags.join('')}</div>` : ''}
        <div class="units-wrap"></div>
      </div>`;

    card.querySelector('.offer-head').addEventListener('click', () => selectOffer(o.id));
    offersList.appendChild(card);

    // Pre-render units inside this offer so radios persist across switches
    const unitsWrap = card.querySelector('.units-wrap');
    if (o.requires_options === 1 && D.groups.length) {
      for (let i = 1; i <= o.quantity; i++) unitsWrap.appendChild(renderUnit(o.id, i));
    }
  });

  function renderUnit(offerId, idx) {
    const block = document.createElement('div');
    block.className = 'unit-block';
    const pair = document.createElement('div');
    pair.className = 'unit-pair';
    D.groups.forEach(g => pair.appendChild(renderGroup(g, offerId, idx)));
    block.appendChild(pair);
    return block;
  }

  function renderGroup(g, offerId, unitIdx) {
    const row = document.createElement('div');
    row.className = 'opt-row';
    const inputName = `opt_${offerId}_${g.name}_${unitIdx}`;
    let control = '';
    const req = g.is_required ? 'required' : '';
    const placeholder = g.name === 'color' ? 'إختيار اللون' : (g.name === 'size' ? 'إختيار الحجم' : `اختر ${esc(g.label)}`);

    if (g.type === 'swatch' || g.type === 'select') {
      control = `<select name="${inputName}" data-group="${esc(g.name)}" ${req}>
        <option value="">${esc(placeholder)}</option>
        ${g.values.map(v => `<option value="${esc(v.value)}">${esc(v.value)}</option>`).join('')}
      </select>`;
    } else if (g.type === 'radio') {
      control = `<div class="radios" data-name="${inputName}">${g.values.map((v,i) => `
        <label class="radio-pill ${i===0?'selected':''}">
          <input type="radio" name="${inputName}" value="${esc(v.value)}" ${i===0?'checked':''} ${req}>
          <span>${esc(v.value)}</span>
        </label>`).join('')}</div>`;
    } else {
      control = `<input type="text" name="${inputName}" ${req}>`;
    }

    const labelText = g.name === 'color' ? 'لون المنتج' : (g.name === 'size' ? 'الحجم' : esc(g.label));
    row.innerHTML = `<label>${labelText}</label><div>${control}</div>`;

    row.addEventListener('change', e => {
      if (e.target.matches('input[type=radio]')) {
        const parent = e.target.closest('.radios, .swatches');
        if (!parent) return;
        parent.querySelectorAll('.radio-pill, .swatch').forEach(el => el.classList.remove('selected'));
        e.target.closest('.radio-pill, .swatch').classList.add('selected');
      }
    });
    return row;
  }

  function selectOffer(id) {
    activeOfferId = id;
    offerInput.value = id;
    const offer = D.offers.find(o => o.id === id);
    if (!offer) return;

    document.querySelectorAll('.offer').forEach(el => {
      const isActive = Number(el.dataset.id) === id;
      el.classList.toggle('active', isActive);
      // Disable inputs in non-active cards so non-selected fields don't validate
      el.querySelectorAll('select, input, textarea').forEach(inp => { inp.disabled = !isActive; });
    });

    if (priceHero) priceHero.innerHTML = `${fmt(offer.total_price)} <span class="dh">د.م</span>`;
    if (scLabel)   scLabel.textContent = offer.label;
    if (scPrice)   scPrice.textContent = `${fmt(offer.total_price)} د.م`;
  }

  /* Default selection */
  const def = D.offers.find(o => o.is_default === 1) || D.offers[0];
  if (def) selectOffer(def.id);

  /* ---------- Slider ---------- */
  const slider = document.getElementById('pSlider');
  if (slider) {
    const slides = slider.querySelectorAll('.p-slide');
    const dots   = slider.querySelectorAll('.p-dot');
    let idx = 0;
    function go(n) {
      idx = (n + slides.length) % slides.length;
      slides.forEach((s,i) => s.classList.toggle('active', i===idx));
      dots.forEach((d,i)   => d.classList.toggle('active', i===idx));
    }
    slider.querySelector('.p-nav.prev')?.addEventListener('click', () => go(idx - 1));
    slider.querySelector('.p-nav.next')?.addEventListener('click', () => go(idx + 1));
    dots.forEach(d => d.addEventListener('click', () => go(Number(d.dataset.i))));
    if (slides.length > 1) {
      let auto = setInterval(() => go(idx + 1), 5000);
      slider.addEventListener('mouseenter', () => clearInterval(auto));
    }
    let sx = 0;
    slider.addEventListener('touchstart', e => sx = e.touches[0].clientX, { passive: true });
    slider.addEventListener('touchend',   e => {
      const dx = e.changedTouches[0].clientX - sx;
      if (Math.abs(dx) > 40) go(idx + (dx > 0 ? 1 : -1));
    });
  }

  /* ---------- Sticky CTA ---------- */
  const formEl = document.getElementById('orderForm');
  const onScroll = () => {
    if (!formEl || !stickyCta) return;
    const r = formEl.getBoundingClientRect();
    const inView = r.top < window.innerHeight && r.bottom > 0;
    stickyCta.classList.toggle('show', !inView && window.scrollY > 400);
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ---------- Form validation + submit ---------- */
  const form = document.getElementById('leadForm');
  const errBox = document.getElementById('formError');

  function showError(msg, focusEl) {
    if (errBox) {
      errBox.textContent = msg;
      errBox.hidden = false;
      errBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } else {
      alert(msg);
    }
    if (focusEl) {
      focusEl.classList.add('has-err');
      setTimeout(() => focusEl.focus({ preventScroll: true }), 250);
    }
  }
  function clearError() {
    if (errBox) { errBox.textContent = ''; errBox.hidden = true; }
    form?.querySelectorAll('.has-err').forEach(el => el.classList.remove('has-err'));
  }

  if (form) {
    // Live: clear "has-err" once the user touches the field
    form.addEventListener('input', e => {
      if (e.target.classList?.contains('has-err')) e.target.classList.remove('has-err');
    });
    form.addEventListener('change', e => {
      if (e.target.classList?.contains('has-err')) e.target.classList.remove('has-err');
    });

    form.addEventListener('submit', (e) => {
      clearError();

      // 1) Offer chosen
      if (!offerInput.value) {
        e.preventDefault();
        return showError('الرجاء اختيار عرض من القائمة');
      }
      const offer = D.offers.find(o => o.id === Number(offerInput.value));

      // 2) Per-unit option groups (only inside active offer)
      if (offer && offer.requires_options === 1) {
        const activeCard = document.querySelector('.offer.active');
        if (activeCard) {
          const selects = activeCard.querySelectorAll('select[required], input[required][type=text]');
          for (const sel of selects) {
            if (!sel.value) {
              e.preventDefault();
              const groupName = sel.dataset.group || '';
              const m = sel.name.match(/_(\d+)$/);
              const unitIdx = m ? m[1] : '?';
              const label = groupName === 'color' ? 'اللون' : (groupName === 'size' ? 'المقاس' : 'الخيار');
              return showError(`الرجاء اختيار ${label} للوحدة رقم ${unitIdx}`, sel);
            }
          }
        }
      }

      // 3) Customer fields
      const fullname = form.querySelector('input[name=fullname]');
      if (!fullname.value || fullname.value.trim().length < 3) {
        e.preventDefault();
        return showError('الرجاء إدخال الاسم الكامل (3 أحرف على الأقل)', fullname);
      }

      const phone = form.querySelector('input[name=phone]');
      if (!/^0[6-7]\d{8}$/.test(phone.value.trim())) {
        e.preventDefault();
        return showError('رقم الهاتف غير صحيح. مثال: 0612345678', phone);
      }

      const address = form.querySelector('input[name=address]');
      if (!address.value || address.value.trim().length < 5) {
        e.preventDefault();
        return showError('الرجاء إدخال العنوان الكامل', address);
      }

      // Disable button to prevent double-submit
      const btn = form.querySelector('button[type=submit]');
      if (btn) { btn.disabled = true; btn.textContent = '... جاري إرسال الطلب'; }
    });
  }

  /* ---------- Countdown ---------- */
  const cdRoot = document.getElementById('countdown');
  if (cdRoot) {
    const HOURS = parseInt(cdRoot.dataset.hours || '25', 10);
    const KEY = 'lp_cd_end_' + (D.productId || 'p');
    let end = parseInt(localStorage.getItem(KEY) || '0', 10);
    if (!end || end < Date.now()) {
      end = Date.now() + HOURS * 60 * 60 * 1000;
      localStorage.setItem(KEY, String(end));
    }
    const $ = id => cdRoot.querySelector('#' + id);
    function tick() {
      let dist = end - Date.now();
      if (dist < 0) {
        end = Date.now() + HOURS * 60 * 60 * 1000;
        localStorage.setItem(KEY, String(end));
        dist = end - Date.now();
      }
      const d = Math.floor(dist / 86400000);
      const h = Math.floor((dist % 86400000) / 3600000);
      const m = Math.floor((dist % 3600000) / 60000);
      const s = Math.floor((dist % 60000) / 1000);
      const pad = n => String(n).padStart(2, '0');
      $('cdD').textContent = pad(d);
      $('cdH').textContent = pad(h);
      $('cdM').textContent = pad(m);
      $('cdS').textContent = pad(s);
    }
    tick();
    setInterval(tick, 1000);
  }
})();
