<?php
/**
 * Advertising pixel base code for the page currently being rendered.
 *
 * Which pixels appear here is decided per landing page in
 * Admin → Products → "التتبع والبكسلات", falling back to the platform default
 * from Admin → Pixels. Two different products can therefore report to two
 * different ad accounts on the same domain.
 *
 * Nothing below is emitted when a platform resolves to no pixel, so a page can
 * be deliberately untracked for Meta while still tracked for TikTok.
 */
$__px   = pixel_context();
$__fb   = $__px['facebook'] ?? null;
$__tt   = $__px['tiktok']   ?? null;
$__pvId = pixel_event_id('pv');
?>
<script>
/* Pixel bridge — one call site for both platforms.
   Populated with real ids below; stays a no-op when no pixel is configured. */
window.LPX = window.LPX || (function () {
  var cfg = { fb: null, tt: null, currency: 'MAD', debug: false };

  function log() { if (cfg.debug && window.console) console.log.apply(console, ['[LPX]'].concat([].slice.call(arguments))); }

  function uid(p) { return p + '.' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10); }

  /* Normalised payload → the shape each platform expects. */
  function fbProps(d) {
    var p = { currency: d.currency || cfg.currency };
    if (d.id != null)       p.content_ids = [String(d.id)];
    if (d.name)             p.content_name = d.name;
    if (d.value != null)    p.value = Number(d.value);
    if (d.quantity != null) p.num_items = Number(d.quantity);
    if (d.id != null)       p.content_type = 'product';
    return p;
  }
  function ttProps(d) {
    var p = { currency: d.currency || cfg.currency };
    if (d.id != null)       p.content_id = String(d.id);
    if (d.name)             p.content_name = d.name;
    if (d.value != null)    p.value = Number(d.value);
    if (d.price != null)    p.price = Number(d.price);
    if (d.quantity != null) p.quantity = Number(d.quantity);
    if (d.id != null)       p.content_type = 'product';
    return p;
  }

  /* Intent → platform event names. Keeping the mapping in one table is what
     stops Meta and TikTok drifting apart as events are added. */
  var MAP = {
    view_content:      { fb: 'ViewContent',      tt: 'ViewContent' },
    add_to_cart:       { fb: 'AddToCart',        tt: 'AddToCart' },
    initiate_checkout: { fb: 'InitiateCheckout', tt: 'InitiateCheckout' },
    lead:              { fb: 'Lead',             tt: 'SubmitForm' },
    purchase:          { fb: 'Purchase',         tt: 'CompletePayment' }
  };

  var fired = {};

  return {
    config: cfg,

    /** Fire an intent on every configured platform. */
    track: function (intent, data, opts) {
      data = data || {}; opts = opts || {};
      var names = MAP[intent];
      if (!names) { log('unknown intent', intent); return; }

      if (opts.once) {
        var key = intent + ':' + (opts.onceKey || '');
        if (fired[key]) return;
        fired[key] = true;
      }

      var eventId = data.event_id || uid(intent);

      if (cfg.fb && window.fbq) {
        try { window.fbq('track', names.fb, fbProps(data), { eventID: eventId }); log('fb', names.fb, data); }
        catch (e) { log('fb error', e); }
      }
      if (cfg.tt && window.ttq) {
        try { window.ttq.track(names.tt, ttProps(data), { event_id: eventId }); log('tt', names.tt, data); }
        catch (e) { log('tt error', e); }
      }
      if (window.dataLayer) {
        try { window.dataLayer.push({ event: intent, ecommerce: data, event_id: eventId }); } catch (e) {}
      }
      return eventId;
    },

    /** Hand hashed-free identifiers to TikTok for better match quality. */
    identify: function (info) {
      if (cfg.tt && window.ttq && window.ttq.identify) {
        try { window.ttq.identify(info); } catch (e) { log('tt identify error', e); }
      }
    },

    active: function () { return { facebook: cfg.fb, tiktok: cfg.tt }; }
  };
})();
window.LPX.config.fb = <?= json_encode($__fb ? (string)$__fb['pixel_id'] : null) ?>;
window.LPX.config.tt = <?= json_encode($__tt ? (string)$__tt['pixel_id'] : null) ?>;
window.LPX.config.debug = <?= json_encode(!empty($_GET['pxdebug'])) ?>;
</script>

<?php if ($__fb): ?>
<!-- Meta Pixel · <?= e($__fb['name']) ?> (<?= e($__fb['pixel_id']) ?>) -->
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;
s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', <?= json_encode((string)$__fb['pixel_id']) ?>);
fbq('track', 'PageView', {}, { eventID: <?= json_encode($__pvId) ?> });
</script>
<noscript><img height="1" width="1" style="display:none" alt=""
  src="https://www.facebook.com/tr?id=<?= e(rawurlencode((string)$__fb['pixel_id'])) ?>&ev=PageView&noscript=1"></noscript>
<?php endif; ?>

<?php if ($__tt): ?>
<!-- TikTok Pixel · <?= e($__tt['name']) ?> (<?= e($__tt['pixel_id']) ?>) -->
<script>
!function (w, d, t) {
  w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];
  ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"];
  ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};
  for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);
  ttq.instance=function(t){var e=ttq._i[t]||[];for(var n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e};
  ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js",o=n&&n.partner;
    ttq._i=ttq._i||{};ttq._i[e]=[];ttq._i[e]._u=r;ttq._t=ttq._t||{};ttq._t[e]=+new Date;ttq._o=ttq._o||{};ttq._o[e]=n||{};
    n=document.createElement("script");n.type="text/javascript";n.async=!0;n.src=r+"?sdkid="+e+"&lib="+t;
    e=document.getElementsByTagName("script")[0];e.parentNode.insertBefore(n,e)};
  ttq.load(<?= json_encode((string)$__tt['pixel_id']) ?>);
  ttq.page();
}(window, document, 'ttq');
</script>
<?php endif; ?>
