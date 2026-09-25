<?php
/**
 * L'apertura di una sezione: occhiello con il filetto, titolo in Cormorant
 * con l'ultima parola in corsivo terracotta, e un testo breve.
 *
 * «|» nel titolo e nel testo va a capo.
 *
 * @var string      $occhiello
 * @var string      $titolo
 * @var string|null $firma      l'ultima parola, in corsivo
 * @var string|null $testo
 * @var string      $livello    h1 | h2 | h3
 * @var string      $misura     l | m | s | xs
 * @var bool        $centro
 * @var string|null $id         per aria-labelledby
 */

$livello = $livello ?? 'h2';
$misura  = $misura ?? 'm';
$centro  = $centro ?? false;
$id      = $id ?? null;
?>
<div class="adv-intestazione<?= $centro ? ' adv-intestazione--centro' : '' ?>">
  <?php if (!empty($occhiello)): ?>
    <?= occhiello($occhiello, $centro ? 'adv-occhiello--centro' : '') ?>
  <?php endif; ?>

  <<?= $livello ?> class="adv-titolo adv-titolo--<?= e($misura) ?>"<?= $id ? ' id="' . e($id) . '"' : '' ?> data-reveal="title-reveal">
    <?= titolo($titolo, $firma ?? null) ?>
  </<?= $livello ?>>

  <?php if (!empty($testo)): ?>
    <p class="adv-testo" data-reveal="reveal"><?= righe($testo) ?></p>
  <?php endif; ?>
</div>
