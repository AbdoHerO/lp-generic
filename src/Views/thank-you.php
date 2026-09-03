<section class="msg-page container">
  <div class="msg-card success">
    <div class="msg-icon">✓</div>
    <h1>تم استلام طلبك بنجاح</h1>
    <?php if (!empty($purchase)): ?>
      <p class="order-ref">رقم الطلب: <strong>#<?= (int)$purchase['order_id'] ?></strong></p>
    <?php endif; ?>
    <p>شكراً لثقتك. سيتواصل معك فريقنا عبر الهاتف قريباً لتأكيد طلبك قبل الشحن.</p>
    <div class="msg-actions">
      <a href="<?= base_url('/') ?>" class="btn-buy">العودة للرئيسية</a>
    </div>
  </div>
</section>

<?php if (!empty($purchase)): ?>
<script>
/* Conversion. The value comes from the server-recomputed offer price, never
   from anything the browser could have edited, and the pixels that fire are
   the ones this order's landing page is assigned to. */
(function () {
  var order = <?= json_encode([
      'id'       => $purchase['id'],
      'name'     => $purchase['name'],
      'value'    => $purchase['value'],
      'quantity' => $purchase['quantity'],
      'currency' => $purchase['currency'],
      'event_id' => $purchase['event_id'],
  ], JSON_UNESCAPED_UNICODE) ?>;

  function fire() {
    if (!window.LPX) return;
    <?php if (!empty($purchase['phone'])): ?>
    /* TikTok hashes this in the browser before it leaves the page. */
    window.LPX.identify({ phone_number: <?= json_encode($purchase['phone']) ?> });
    <?php endif; ?>
    window.LPX.track('purchase', order);
    window.LPX.track('lead', { id: order.id, name: order.name, value: order.value, currency: order.currency,
                               event_id: order.event_id + '.lead' });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fire);
  else fire();
})();
</script>
<?php endif; ?>
