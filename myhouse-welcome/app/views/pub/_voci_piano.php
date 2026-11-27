<?php
/* L'elenco puntato di un piano. Una voce che finisce con i due punti («Tutto di
   Essential, e in più:») è il titoletto dell'elenco, senza spunta: così Plus e
   Portfolio non ripetono quello che Essential ha già. Il testo lo scrive
   l'amministratore; «{sezioni}» diventa il numero di sezioni del catalogo (7B). Riceve: $voci. */
use MHW\{Support, Icon, SectionCatalog}; ?>
<ul class="plan__lista">
  <?php foreach ($voci as $v): $v = str_replace('{sezioni}', (string) SectionCatalog::quante(), (string) $v); ?>
    <?php if (str_ends_with(trim($v), ':')): ?><li class="plan__da"><?= Support::e($v) ?></li>
    <?php else: ?><li><?= Icon::svg('check', 16, 2) ?><span><?= Support::e($v) ?></span></li><?php endif; ?>
  <?php endforeach; ?>
  <?php if (MHW\Casa::disponibile()): /* 9V · «La casa in foto», inclusa in tutti i piani: la stessa voce in ogni scheda, da Testi e foto, con «Novità» */ ?>
    <li class="plan__casa"><?= Icon::svg('check', 16, 2) ?><span><?= MHW\Casa::voce() ?></span></li>
  <?php endif; ?>
</ul>
