<?php
/**
 * The product editor's tab bar and readiness panel.
 *
 * The editor grew into one 6,000-pixel column where every field looked equally
 * important and nothing said what was still missing. Someone who did not build
 * it had no way to tell a required field from a nice-to-have.
 *
 * Two things fix that: grouping the work into the order it is actually done
 * (basics → content → offers → media → campaign), and a checklist that names
 * what is left. The checklist doubles as navigation — each item jumps to the
 * tab that fixes it.
 *
 * Expects: $product, $checklist, $tabIssues
 */
$tabs = [
    'basics'   => ['الأساسيات',   '📝', 'العنوان، السعر، الصورة، الـSEO'],
    'content'  => ['المحتوى',     '✍️', 'العنوان الرئيسي، المميزات، الآراء، الأسئلة'],
    'offers'   => ['العروض',      '🏷️', 'الأسعار والكميات والخيارات'],
    'media'    => ['الصور',       '🖼️', 'السلايدر ومعرض الصور'],
    'campaign' => ['الحملة',      '🎯', 'البكسلات، الألوان، المؤقّت، اختبار A/B'],
];
$isNew = empty($product['id']);
?>
<div class="pe-head">
  <nav class="pe-tabs" role="tablist" aria-label="أقسام تحرير المنتج">
    <?php foreach ($tabs as $key => [$label, $icon, $hint]): ?>
      <?php
        // Offers, media and the campaign panel all need a product id to attach
        // to, so they stay locked until the first save.
        $locked = $isNew && in_array($key, ['offers', 'media', 'campaign'], true);
        $issues = $tabIssues[$key] ?? 0;
      ?>
      <button type="button" class="pe-tab" role="tab" data-tab="<?= e($key) ?>"
              title="<?= e($locked ? 'احفظ المنتج أولاً' : $hint) ?>"
              <?= $locked ? 'disabled' : '' ?>>
        <span class="pe-tab-icon" aria-hidden="true"><?= $icon ?></span>
        <span class="pe-tab-label"><?= e($label) ?></span>
        <?php if ($locked): ?>
          <span class="pe-tab-lock" aria-label="مقفل">🔒</span>
        <?php elseif ($issues): ?>
          <span class="pe-tab-badge" aria-label="<?= (int)$issues ?> عناصر ناقصة"><?= (int)$issues ?></span>
        <?php endif; ?>
      </button>
    <?php endforeach; ?>
  </nav>

  <?php if (!$isNew): ?>
  <div class="pe-ready <?= $checklist['ready'] ? 'is-ready' : 'not-ready' ?>">
    <button type="button" class="pe-ready-toggle" id="peReadyToggle" aria-expanded="false">
      <span class="pe-ready-ring" style="--pct: <?= (int)$checklist['percent'] ?>">
        <span><?= (int)$checklist['percent'] ?>%</span>
      </span>
      <span class="pe-ready-text">
        <strong><?= $checklist['ready'] ? 'جاهزة للنشر' : 'ناقصة للنشر' ?></strong>
        <small><?= (int)$checklist['done'] ?> من <?= (int)$checklist['total'] ?> — اضغط للتفاصيل</small>
      </span>
    </button>

    <div class="pe-ready-panel" id="peReadyPanel" hidden>
      <?php foreach ([['مطلوب للنشر', $checklist['required'], true],
                      ['موصى به', $checklist['recommended'], false]] as [$title, $items, $req]): ?>
        <?php if (!$items) continue; ?>
        <h4><?= e($title) ?></h4>
        <ul class="pe-check">
          <?php foreach ($items as $i): ?>
            <li class="<?= $i['ok'] ? 'done' : ($req ? 'todo-req' : 'todo') ?>">
              <span class="pe-check-mark" aria-hidden="true"><?= $i['ok'] ? '✓' : '○' ?></span>
              <span class="pe-check-body">
                <strong><?= e($i['label']) ?></strong>
                <?php if (!$i['ok']): ?><small><?= e($i['hint']) ?></small><?php endif; ?>
              </span>
              <?php if (!$i['ok']): ?>
                <button type="button" class="btn-sm pe-goto" data-goto="<?= e($i['tab']) ?>">إصلاح</button>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
