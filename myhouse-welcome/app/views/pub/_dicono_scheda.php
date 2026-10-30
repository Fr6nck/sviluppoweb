<?php
/* 9L · Un giudizio di «Cosa dicono di noi»: copertina in alto (o il segnaposto «Welcome My House»), logo nel quadrato
   bianco sul bordo (o il quadratino con il marchio), stelle ocra, «commento», la risposta pubblica se c'è, poi il nome
   dell'host (senza cognome) e «Struttura · Città (PR)». Nessuna foto dell'host, nessun link. Riceve $g (Vetrina::perScheda
   con il giudizio). Serve anche alle anteprime del pannello e dell'amministrazione. */
use function MHW\a;
use MHW\{Support, LinguaSito};
$enS = LinguaSito::en(); ?>
<figure class="dicono-scheda<?= $g['copertina'] ? '' : ' dicono-scheda--segnaposto' ?>">
  <div class="dicono-scheda__cop">
    <?php if ($g['copertina']): ?><img src="<?= Support::e($g['copertina']) ?>" alt="<?= Support::e($g['property_name']) ?>" loading="lazy" width="800" height="450">
    <?php else: ?><img src="<?= a('/assets/vetrina-segnaposto.jpg') ?>" alt="" loading="lazy" width="1200" height="675"><?php endif; ?>
    <span class="dicono-scheda__logo<?= $g['logo'] ? '' : ' dicono-scheda__logo--segnaposto' ?>"><?php if ($g['logo']): ?><img src="<?= Support::e($g['logo']) ?>" alt="<?= Support::e(($enS ? 'Logo of ' : 'Logo di ') . $g['property_name']) ?>" loading="lazy" width="56" height="56"><?php else: ?><img src="<?= a('/assets/vetrina-logo-segnaposto.png') ?>" alt="" loading="lazy" width="56" height="56"><?php endif; ?></span>
  </div>
  <div class="dicono-scheda__corpo">
    <span class="stelline dicono-scheda__voto" role="img" aria-label="<?= (int) $g['rating'] ?> <?= $enS ? 'out of 5' : 'su 5' ?>"><?= str_repeat('★', (int) $g['rating']) . str_repeat('☆', 5 - (int) $g['rating']) ?></span>
    <blockquote><p>«<?= Support::e($g['body']) ?>»</p></blockquote>
    <?php if ($g['risposta'] !== ''): ?><p class="dicono-scheda__risposta"><b>MyHouse Welcome:</b> <?= Support::e($g['risposta']) ?></p><?php endif; ?>
    <figcaption class="dicono-scheda__chi">
      <b><?= Support::e($g['name']) ?></b><span class="small muted"><?= Support::e(trim($g['property_name'] . ($g['luogo'] !== '' ? ' · ' . $g['luogo'] : ''), ' ·')) ?></span>
    </figcaption>
  </div>
</figure>
