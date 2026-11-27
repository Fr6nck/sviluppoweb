<?php
/* 9A · 9U · «Più di N strutture? Ti facciamo una proposta su misura.» con «Chiedi il preventivo»: il riquadro che prende
   il posto del prezzo quando il selettore del Portfolio supera il massimo del listino (prezzi.js, oltre()). Lo stesso
   nella home e nelle pagine per tipologia. Riceve $maxO (il massimo del listino) e $hrefO: «#preventivo» se il modulo
   è nella pagina, altrimenti l'indirizzo di /piu-strutture, a cui prezzi.js aggiunge le strutture scelte. */
use MHW\{Support, LinguaSito};
$fuoriO = !str_starts_with($hrefO, '#'); ?>
<div class="plan__oltre" data-oltre hidden><p><?= LinguaSito::t('Più di ' . (int) $maxO . ' strutture? Ti facciamo una proposta su misura.', 'More than ' . (int) $maxO . ' properties? We will make you a tailored offer.') ?></p>
  <a class="btn btn--sm" href="<?= Support::e($fuoriO ? $hrefO . '?strutture=' . ((int) $maxO + 1) . '#preventivo' : $hrefO) ?>"<?= $fuoriO ? ' data-oltre-href="' . Support::e($hrefO) . '"' : '' ?>><?= LinguaSito::t('Chiedi il preventivo', 'Ask for a quote') ?></a></div>
