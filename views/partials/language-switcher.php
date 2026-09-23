<?php
/**
 * Il cambio di lingua.
 *
 * Due link, non un menu a tendina: con due lingue una tendina costa un clic
 * in più e non si capisce cosa contiene finché non la si apre. Ogni link
 * porta alla stessa pagina nell'altra lingua — non alla home, che è l'errore
 * più comune e fa perdere il segno a chi stava leggendo.
 *
 * @var array  $alternative lingua => indirizzo
 * @var string $id          suffisso per non ripetere un id nella pagina
 */

$alternative = $alternative ?? [];
$id          = $id ?? 'testata';
$lingue      = \ArcoDelVento\App::instance()->config('i18n.available');

if (count($lingue) < 2) {
    return;
}
?>
<nav class="adv-lingue" aria-labelledby="lingue-<?= e($id) ?>">
  <span class="adv-lingue__etichetta" id="lingue-<?= e($id) ?>"><?= te('common.language') ?></span>
  <ul class="adv-lingue__lista">
    <?php foreach ($lingue as $lingua): ?>
      <?php $href = $alternative[$lingua] ?? url('home', [], [], $lingua); ?>
      <li>
        <a class="adv-lingue__link" href="<?= e($href) ?>" lang="<?= e($lingua) ?>" hreflang="<?= e($lingua) ?>"
           <?= $lingua === locale() ? ' aria-current="true"' : '' ?>>
          <?= e(strtoupper($lingua)) ?>
          <span class="adv-visually-hidden"><?= e(t('common.language', [], $lingua)) ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</nav>
