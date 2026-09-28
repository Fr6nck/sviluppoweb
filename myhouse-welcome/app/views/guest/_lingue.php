<?php /* La pastiglia che cambia lingua: un elenco vero, funziona anche senza JavaScript. */
use MHW\{Support, Icon, Config, I18n};
$nomi = Config::get('locales'); ?>
<?php if (count($snap['locales']) > 1): ?>
  <details class="langpick">
    <summary class="lang" aria-label="<?= Support::e(I18n::t($loc, 'change_language')) ?>">
      <?= Icon::svg('globe', 15) ?><?= Support::e(strtoupper($loc)) ?>
    </summary>
    <div class="langpick__menu">
      <?php foreach ($snap['locales'] as $l): ?>
        <a href="<?= Support::e($base . ($coda ?? '')) ?>?l=<?= Support::e($l) ?>" hreflang="<?= Support::e($l) ?>" lang="<?= Support::e($l) ?>"
           class="<?= $l === $loc ? 'on' : '' ?>"<?= $l === $loc ? ' aria-current="true"' : '' ?>><?= Support::e($nomi[$l] ?? strtoupper($l)) ?></a>
      <?php endforeach; ?>
    </div>
  </details>
<?php endif; ?>
