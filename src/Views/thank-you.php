<section class="msg-page container">
  <div class="msg-card success">
    <div class="msg-icon">✓</div>
    <h1>تم استلام طلبك بنجاح</h1>
    <p>شكراً لثقتك. سيتواصل معك فريقنا عبر الهاتف قريباً لتأكيد طلبك قبل الشحن.</p>
    <div class="msg-actions">
      <a href="<?= base_url('/') ?>" class="btn-buy">العودة للرئيسية</a>
    </div>
  </div>
</section>
<script>
  // Hooks for tracking — add real IDs in admin settings to activate.
  if (window.fbq) fbq('track','Purchase',{currency:'MAD'});
  if (window.dataLayer) dataLayer.push({event:'purchase'});
</script>
