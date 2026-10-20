<?php
/* 9L · Una scheda di «Le guide dei nostri host» (home, «Tutte le guide», anteprime): copertina in alto (max 800 px, alt =
   nome della struttura), logo nel quadrato bianco sul bordo; se mancano, il segnaposto «Welcome My House» (sabbia con il
   marchio) e il quadratino con il marchio, finché l'host non li carica. Sotto: nome della struttura, nome dell'host
   (senza cognome) e «Città (PR)». Nessuna foto dell'host, nessun link, nessuna area toccabile. Riceve $v (Vetrina::perScheda). */
use function MHW\a;
use MHW\{Support, LinguaSito};
$enV = LinguaSito::en(); ?>
<figure class="dicono-scheda dicono-scheda--vetrina<?= $v['copertina'] ? '' : ' dicono-scheda--segnaposto' ?>">
  <div class="dicono-scheda__cop">
    <?php if ($v['copertina']): ?><img src="<?= Support::e($v['copertina']) ?>" alt="<?= Support::e($v['property_name']) ?>" loading="lazy" width="800" height="450">
    <?php else: ?><img src="<?= a('/assets/vetrina-segnaposto.jpg') ?>" alt="" loading="lazy" width="1200" height="675"><?php endif; ?>
    <span class="dicono-scheda__logo<?= $v['logo'] ? '' : ' dicono-scheda__logo--segnaposto' ?>"><?php if ($v['logo']): ?><img src="<?= Support::e($v['logo']) ?>" alt="<?= Support::e(($enV ? 'Logo of ' : 'Logo di ') . $v['property_name']) ?>" loading="lazy" width="56" height="56"><?php else: ?><img src="<?= a('/assets/vetrina-logo-segnaposto.png') ?>" alt="" loading="lazy" width="56" height="56"><?php endif; ?></span>
  </div>
  <figcaption class="dicono-scheda__corpo dicono-scheda__chi">
    <b><?= Support::e($v['property_name']) ?></b><span class="small muted"><?= Support::e(trim($v['name'] . ($v['luogo'] !== '' ? ' · ' . $v['luogo'] : ''), ' ·')) ?></span>
  </figcaption>
</figure>
