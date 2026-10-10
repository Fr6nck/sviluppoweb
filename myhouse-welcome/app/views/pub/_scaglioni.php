<?php
/* Il Portfolio struttura per struttura: la prima, poi gli scaglioni, e quanto costa in media
   ciascuna per il numero scelto (lo aggiorna prezzi.js). Riceve: $pvS (versione), $qS (quantità). */
use MHW\{Support, Plans}; ?>
<div class="scaglioni">
  <p class="scaglioni__titolo">Quanto costa ogni struttura, all'anno</p>
  <ul class="scaglioni__elenco">
    <li><span>1ª</span><b><?= Support::e(Support::money((int) $pvS['price_cents'], $pvS['currency'])) ?></b></li>
    <?php foreach (Plans::tiers($pvS) as $t): ?>
      <li><span><?= Support::e(ucfirst(Plans::tierLabel($t))) ?></span><b>+<?= Support::e(Support::money($t['cents'], $pvS['currency'])) ?><?php if ($t['a'] !== $t['da']): ?> <small>l'una</small><?php endif; ?></b></li>
    <?php endforeach; ?>
  </ul>
  <p class="scaglioni__media">Con <span data-quante><?= (int) $qS ?></span> strutture: in media
    <b data-media><?= Support::e(Support::money((int) round(Plans::price($pvS, $qS) / max(1, $qS)), $pvS['currency'])) ?></b> a struttura + IVA.</p>
</div>
