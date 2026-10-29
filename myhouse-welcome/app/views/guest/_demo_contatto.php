<?php
/* 9T · 5.4 · Nella guida dimostrativa i bottoni per scrivere all'host non portano a numeri di fantasia (WhatsApp darebbe
   errore): aprono un riquadro «È una demo: nella tua guida, qui gli ospiti scrivono a te su WhatsApp» con «Crea la tua
   guida». Un <details>: funziona anche senza JavaScript. Riceve: $loc, $dcClasse (le classi del bottone), $dcEtichetta
   (HTML già sicuro: icona e testo escapato). */
use MHW\{Support, I18n}; ?>
<details class="demo-contatto">
  <summary class="<?= Support::e($dcClasse) ?>"><?= $dcEtichetta ?></summary>
  <div class="demo-contatto__pannello" role="note">
    <p><?= Support::e(I18n::t($loc, 'demo_contact_note')) ?></p>
    <a class="btn btn--sm" href="<?= Support::e(Support::baseUrl()) ?>/registrati"><?= Support::e(I18n::t($loc, 'demo_create')) ?></a>
  </div>
</details>
