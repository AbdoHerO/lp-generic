<footer class="site-footer">
  <div class="container ftr">
    <div class="ftr-brand">
      <div class="brand"><span class="brand-mark">◆</span> <?= e(settings_get('store_name','متجر')) ?></div>
      <p class="ftr-tag">تجربة تسوق راقية مع الدفع عند الاستلام في كل المغرب.</p>
    </div>
    <nav class="ftr-nav">
      <a href="<?= base_url('/') ?>">الرئيسية</a>
      <a href="<?= base_url('page/privacy') ?>">سياسة الخصوصية</a>
      <a href="<?= base_url('page/terms') ?>">شروط الاستخدام</a>
      <a href="<?= base_url('page/refund') ?>">سياسة الإرجاع</a>
    </nav>
    <div class="ftr-contact">
      <a href="tel:<?= e(settings_get('support_phone','')) ?>">📞 <?= e(settings_get('support_phone','')) ?></a>
      <a href="https://wa.me/<?= e(preg_replace('/\D/','',settings_get('whatsapp',''))) ?>" target="_blank">واتساب</a>
    </div>
  </div>
  <div class="ftr-bottom container">© <?= date('Y') ?> <?= e(settings_get('store_name','متجر')) ?>. جميع الحقوق محفوظة.</div>
</footer>
