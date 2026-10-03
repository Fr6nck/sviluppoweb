<?php
/* L'elenco puntato di un piano. Una voce che finisce con i due punti («Tutto di
   Essential, e in più:») è il titoletto dell'elenco, senza spunta: così Plus e
   Portfolio non ripetono quello che Essential ha già. Il testo lo scrive
   l'amministratore. Riceve: $voci. */
use MHW\{Support, Icon}; ?>
<ul class="plan__lista">
  <?php foreach ($voci as $v): ?>
    <?php if (str_ends_with(trim($v), ':')): ?><li class="plan__da"><?= Support::e($v) ?></li>
    <?php else: ?><li><?= Icon::svg('check', 16, 2) ?><span><?= Support::e($v) ?></span></li><?php endif; ?>
  <?php endforeach; ?>
</ul>
