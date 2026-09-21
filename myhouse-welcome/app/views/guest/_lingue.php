<?php /* La pastiglia che cambia lingua. Aperta è un elenco vero, non un menu
         finto: funziona anche se il JavaScript non parte. */
use function MHW\b; use MHW\{Support, Icon, Config};
$nomi = Config::get('locales'); ?>
<?php if (count($snap['locales']) > 1): ?>
  <details class="langpick">
    <summary class="lang" aria-label="Cambia lingua">
      <?= Icon::svg('globe', 15) ?><?= Support::e(strtoupper($loc)) ?>
    </summary>
    <div class="langpick__menu">
      <?php foreach ($snap['locales'] as $l): ?>
        <a href="<?= b() ?>/g/<?= Support::e($slug) ?><?= Support::e($coda ?? '') ?>?l=<?= Support::e($l) ?>"
           hreflang="<?= Support::e($l) ?>" class="<?= $l === $loc ? 'on' : '' ?>">
          <?= Support::e($nomi[$l] ?? strtoupper($l)) ?></a>
      <?php endforeach; ?>
    </div>
  </details>
<?php endif; ?>
