<?php
/**
 * Chi vi accoglie.
 *
 * Senza un ritratto vero il componente mostra l'iniziale del nome su
 * surface-alt, come prescrive il design system: una lettera è più onesta
 * della faccia di un estraneo presa da una banca immagini, e si vede.
 *
 * @var string      $nome
 * @var string      $ruolo
 * @var string      $bio
 * @var string|null $ritratto  percorso, quando la fotografia vera esiste
 * @var array       $contatti
 * @var string|null $nota      quello che ancora manca della sua storia
 */

$ritratto = $ritratto ?? null;
$contatti = $contatti ?? [];
?>
<article class="adv-persona adv-rivela">
  <?php if ($ritratto): ?>
    <figure class="adv-persona__figura">
      <?= component('picture', ['src' => $ritratto, 'alt' => $nome, 'width' => 600, 'height' => 750]) ?>
    </figure>
  <?php else: ?>
    <p class="adv-persona__iniziale" aria-hidden="true"><?= e(mb_substr($nome, 0, 1)) ?></p>
  <?php endif; ?>

  <h3 class="adv-persona__nome"><?= e($nome) ?></h3>
  <p class="adv-persona__ruolo"><?= e($ruolo) ?></p>
  <p class="adv-persona__bio"><?= e($bio) ?></p>

  <?php if (!empty($nota)): ?>
    <p class="adv-persona__bio adv-persona__nota"><?= e($nota) ?> <?= daConfermare() ?></p>
  <?php endif; ?>

  <?php if ($contatti !== []): ?>
    <ul class="adv-persona__contatti">
      <?php foreach ($contatti as $tipo => $valore): ?>
        <li><?= component('contact-line', ['tipo' => $tipo, 'valore' => $valore]) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</article>
