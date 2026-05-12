<section class="msg-page container">
  <div class="msg-card error">
    <div class="msg-icon">!</div>
    <h1>تعذر إتمام الطلب</h1>
    <p><?= e($message ?? 'حدث خطأ غير متوقع') ?></p>
    <div class="msg-actions">
      <a href="javascript:history.back()" class="btn-buy">العودة وتعديل البيانات</a>
    </div>
  </div>
</section>
