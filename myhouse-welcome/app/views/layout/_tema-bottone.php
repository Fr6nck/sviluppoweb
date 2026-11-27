<?php /* L'interruttore del tema. Senza JavaScript non compare: un bottone che non
         fa niente è peggio di un bottone che non c'è. Le etichette arrivano da
         chi lo include, così nella guida parlano la lingua dell'ospite. */
use MHW\Icon;
$etScuro = $etScuro ?? 'Passa al tema scuro';
$etChiaro = $etChiaro ?? 'Passa al tema chiaro'; ?>
<button type="button" class="icon-btn tema" data-tema="chiaro" onclick="mhwTema()" hidden
        aria-label="<?= htmlspecialchars($etScuro) ?>" data-et-scuro="<?= htmlspecialchars($etScuro) ?>"
        data-et-chiaro="<?= htmlspecialchars($etChiaro) ?>">
  <?= Icon::svg('moon', 18, 1.8, 'i-luna') ?><?= Icon::svg('sun', 18, 1.8, 'i-sole') ?>
</button>
<script>document.currentScript.previousElementSibling.hidden = false;</script>
