<?php /* L'intestazione della guida: marchio della casa, demo, tema, lingua. */
use MHW\{Support, Guide, Media, I18n};
$pr = $snap['property'];
$logo = !empty($pr['logo_id']) ? Media::url((int) $pr['logo_id']) : null;
$profilo = !empty($pr['profile_id']) ? Media::url((int) $pr['profile_id']) : null;
$etScuro = I18n::t($loc, 'theme_dark'); $etChiaro = I18n::t($loc, 'theme_light'); ?>
<div class="guest-top">
  <div class="row" style="gap:9px;min-width:0">
    <?php if (!empty($indietro)): ?>
      <a class="icon-btn" href="<?= Support::e($base) ?>?l=<?= Support::e($loc) ?>" aria-label="<?= Support::e(I18n::t($loc, 'back')) ?>"><?= MHW\Icon::svg('back', 18, 1.9) ?></a>
    <?php elseif ($logo): ?>
      <img class="logo-guest" src="<?= Support::e($logo) ?>" alt="<?= Support::e($pr['name']) ?>" width="120" height="30">
    <?php elseif ($profilo): ?>
      <img class="profile-guest" src="<?= Support::e($profilo) ?>" alt="" width="30" height="30">
    <?php else: ?>
      <?= MHW\Icon::brand(28, 'simbolo--ospite') ?>
    <?php endif; ?>
    <?php if (empty($indietro) && !$logo): ?><span class="guest-name"><?= Support::e($pr['name']) ?></span><?php endif; ?>
    <?php if (!empty($pr['is_demo'])): /* 9F · nella vetrina il marchio DEMO porta alla home del sito; nelle guide vere non c'è alcun link */ ?>
      <a class="demo-tag demo-tag--link" href="<?= Support::e(Support::baseUrl()) ?>/" title="<?= Support::e(I18n::t($loc, 'demo_back_title', $hostSito = (string) parse_url(Support::baseUrl(), PHP_URL_HOST) ?: 'welcomemyhouse.it')) ?>"><?= Support::e(I18n::t($loc, 'demo_badge')) ?></a>
    <?php endif; ?>
  </div>
  <div class="row" style="gap:8px">
    <?php if (empty($nascondiTema)) include __DIR__ . '/../layout/_tema-bottone.php'; ?>
    <?php include __DIR__ . '/_lingue.php'; ?>
  </div>
</div>
