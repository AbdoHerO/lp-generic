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
      <a href="tel:<?= e(settings_get('support_phone','')) ?>" class="ftr-link">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M6.6 10.8c1.4 2.7 3.6 4.9 6.3 6.3l2.1-2.1c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.5.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.5 21 3 13.5 3 4.5c0-.6.4-1 1-1H7.5c.6 0 1 .4 1 1 0 1.2.2 2.4.6 3.5.1.3 0 .7-.2 1l-2.3 1.8z"/></svg>
        <?= e(settings_get('support_phone','')) ?>
      </a>
      <a href="https://wa.me/<?= e(preg_replace('/\D/','',settings_get('whatsapp',''))) ?>" target="_blank" rel="noopener" class="ftr-link wa" aria-label="واتساب">
        <svg viewBox="0 0 32 32" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M16.003 3C9.376 3 4 8.376 4 15c0 2.385.7 4.605 1.9 6.476L4 29l7.74-1.86A11.94 11.94 0 0 0 16 27c6.627 0 12-5.373 12-12S22.63 3 16.003 3zm0 21.6c-1.83 0-3.546-.493-5.02-1.353l-.36-.213-4.6 1.105 1.123-4.486-.235-.37A9.557 9.557 0 0 1 6.4 15c0-5.293 4.31-9.6 9.603-9.6 5.293 0 9.6 4.307 9.6 9.6s-4.31 9.6-9.6 9.6zm5.464-7.05c-.3-.15-1.77-.873-2.04-.97-.272-.1-.47-.15-.668.15-.198.3-.768.97-.94 1.17-.173.198-.346.222-.643.075-.297-.15-1.255-.46-2.39-1.475-.883-.788-1.48-1.76-1.654-2.057-.173-.297-.018-.458.13-.605.134-.134.297-.347.446-.52.15-.173.198-.297.297-.495.1-.198.05-.372-.025-.52-.075-.148-.668-1.61-.916-2.205-.24-.578-.484-.5-.668-.51-.173-.008-.372-.01-.57-.01-.198 0-.52.075-.792.372-.272.297-1.04 1.02-1.04 2.487 0 1.467 1.064 2.886 1.213 3.084.15.198 2.094 3.2 5.078 4.486.71.307 1.262.49 1.694.628.712.227 1.36.195 1.873.118.572-.085 1.77-.722 2.02-1.42.248-.7.248-1.298.173-1.42-.075-.124-.272-.198-.57-.347z"/></svg>
        واتساب
      </a>
      <a href="https://www.facebook.com/<?= e(settings_get('facebook_handle','')) ?>" target="_blank" rel="noopener" class="ftr-link" aria-label="فيسبوك">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M22 12c0-5.522-4.478-10-10-10S2 6.478 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987H7.898V12h2.54V9.797c0-2.506 1.493-3.89 3.776-3.89 1.094 0 2.238.195 2.238.195v2.46H15.19c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89H13.56v6.988C18.343 21.128 22 16.99 22 12z"/></svg>
      </a>
    </div>
  </div>
  <div class="ftr-bottom container">© <?= date('Y') ?> <?= e(settings_get('store_name','متجر')) ?>. جميع الحقوق محفوظة.</div>
</footer>
