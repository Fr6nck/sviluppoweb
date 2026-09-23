<?php
/**
 * L'apertura di una sezione: occhiello, titolo display, firma corsiva, filetto.
 *
 * La firma è l'ultima parola del titolo e passa ad Allura: è il gesto del
 * logotipo — serif per il nome, corsivo per «Assisi» — ripetuto dentro la
 * pagina. Una volta per titolo, mai due.
 *
 * @var string      $occhiello
 * @var string      $titolo
 * @var string|null $firma      ultima parola, in corsivo
 * @var string|null $testo
 * @var string      $livello    h1 | h2 | h3
 * @var bool        $filetto
 * @var bool        $piccolo
 */

$livello  = $livello ?? 'h2';
$filetto  = $filetto ?? true;
$piccolo  = $piccolo ?? false;
$classe   = 'adv-sezione__titolo' . ($piccolo ? ' adv-sezione__titolo--sm' : '');
?>
<div class="adv-sezione">
  <?php if (!empty($occhiello)): ?>
    <span class="adv-sezione__occhiello"><?= e($occhiello) ?></span>
  <?php endif; ?>

  <<?= $livello ?> class="<?= e($classe) ?>">
    <?= e($titolo) ?><?php if (!empty($firma)): ?> <span class="adv-firma-inline"><?= e($firma) ?></span><?php endif; ?>
  </<?= $livello ?>>

  <?php if ($filetto): ?><hr class="adv-sezione__filetto"><?php endif; ?>
  <?php if (!empty($testo)): ?><p class="adv-sezione__testo"><?= e($testo) ?></p><?php endif; ?>
</div>
