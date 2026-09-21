<?php /* L'interruttore. Senza JavaScript non compare: un bottone che non fa
         niente è peggio di un bottone che non c'è. */
use MHW\Icon; ?>
<button type="button" class="icon-btn tema" data-tema="chiaro" onclick="mhwTema()"
        aria-label="Cambia tema" hidden>
  <?= Icon::svg('moon', 18, 1.8, 'i-luna') ?><?= Icon::svg('sun', 18, 1.8, 'i-sole') ?>
</button>
<script>document.currentScript.previousElementSibling.hidden = false;</script>
