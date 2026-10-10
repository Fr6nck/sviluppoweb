<?php
/* La barra delle azioni della procedura: la stessa in ogni passo, fissa in
   basso sul telefono. Indietro a sinistra, a destra lo stato del salvataggio
   automatico e il passo successivo.
   Riceve: $barraIndietro (indirizzo o ''), $barraAvanti (HTML del bottone). */
use MHW\{Support, Icon}; ?>
<div class="barra-passo">
  <?php if (($barraIndietro ?? '') !== ''): ?>
    <a class="btn btn--quiet barra-passo__indietro" href="<?= Support::e($barraIndietro) ?>"><?= Icon::svg('back', 16) ?>Indietro</a>
  <?php else: ?><span></span><?php endif; ?>
  <span class="barra-passo__destra">
    <span class="stato stato--ok" data-stato-salvataggio aria-live="polite">Salvato</span>
    <?= $barraAvanti ?>
  </span>
</div>
