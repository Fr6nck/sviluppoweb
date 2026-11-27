<?php
/* 9L · Una scheda di «Le guide dei nostri host» (home, «Tutte le guide», anteprime): copertina in alto (800 px, 16:10, alt =
   nome della struttura), logo 46×46 su bianco sul bordo; se mancano, il segnaposto «Welcome My House» (sabbia con il
   marchio) e il quadratino con l'arco. 9R (mockup scheda-home): la tipologia in una pillola sulla copertina (facoltativa),
   il nome in serif, «Città (PR) · Regione», «Host: Nome» e, solo con il giudizio approvato e acceso, le stelle ocra e il
   commento (al massimo 160 caratteri). Mai la foto dell'host, nessun link, nessun contatto, nessuna area toccabile.
   Riceve $v (Vetrina::perScheda). */
use function MHW\a;
use MHW\{Support, LinguaSito};
$enV = LinguaSito::en();
$doveV = trim(($v['luogo'] ?? '') . (($v['regione'] ?? '') !== '' && ($v['luogo'] ?? '') !== '' ? ' · ' : '') . ($v['regione'] ?? '')); ?>
<figure class="dicono-scheda dicono-scheda--vetrina<?= $v['copertina'] ? '' : ' dicono-scheda--segnaposto' ?>">
  <div class="dicono-scheda__cop">
    <?php if ($v['copertina']): ?><img src="<?= Support::e($v['copertina']) ?>" alt="<?= Support::e($v['property_name']) ?>" loading="lazy" width="800" height="500">
    <?php else: ?><img src="<?= a('/assets/vetrina-segnaposto.jpg') ?>" alt="" loading="lazy" width="1200" height="675"><?php endif; ?>
    <?php if (($v['tipologia'] ?? '') !== ''): ?><span class="dicono-scheda__tipo"><?= Support::e($v['tipologia']) ?></span><?php endif; ?>
    <span class="dicono-scheda__logo<?= $v['logo'] ? '' : ' dicono-scheda__logo--segnaposto' ?>"><?php if ($v['logo']): ?><img src="<?= Support::e($v['logo']) ?>" alt="<?= Support::e(($enV ? 'Logo of ' : 'Logo di ') . $v['property_name']) ?>" loading="lazy" width="46" height="46"><?php else: ?><img src="<?= a('/assets/vetrina-logo-segnaposto.png') ?>" alt="" loading="lazy" width="46" height="46"><?php endif; ?></span>
  </div>
  <figcaption class="dicono-scheda__corpo dicono-scheda__chi">
    <b class="dicono-scheda__nome"><?= Support::e($v['property_name']) ?></b>
    <?php if ($doveV !== ''): ?><span class="dicono-scheda__dove"><?= Support::e($doveV) ?></span><?php endif; ?>
    <span class="dicono-scheda__host">Host: <b><?= Support::e($v['name']) ?></b></span>
    <?php if (!empty($v['stelle'])): $st = (int) $v['stelle']; ?>
      <span class="dicono-scheda__voto" role="img" aria-label="<?= Support::e($enV ? $st . ' out of 5 stars' : $st . ' stelle su 5') ?>"><?= str_repeat('★', $st) . str_repeat('☆', 5 - $st) ?></span>
      <?php if (($v['commento'] ?? '') !== ''): ?><q class="dicono-scheda__commento"><?= Support::e($v['commento']) ?></q><?php endif; ?>
    <?php endif; ?>
  </figcaption>
</figure>
