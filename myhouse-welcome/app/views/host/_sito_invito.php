<?php
/* «Anche il tuo sito»: un invito breve nel pannello, che rimanda alla sezione della
   landing. Si nasconde per 30 giorni con «Non ora» (solo in questo browser). */
use function MHW\b;
use MHW\Icon; ?>
<aside class="sito-invito" data-sito-invito aria-label="Il sito della tua struttura">
  <span class="sito-invito__ico"><?= Icon::svg('globe', 20, 1.8) ?></span>
  <p class="sito-invito__testo"><b>Meno commissioni ai portali. Più incasso per te. Più ospiti diretti.</b>
    <span>Realizziamo anche il sito della tua struttura, con la prenotazione diretta.</span></p>
  <a class="btn btn--sm sito-invito__vai" href="<?= b() ?>/#sito">Scopri come <?= Icon::svg('arrow', 15, 2) ?></a>
  <button type="button" class="icon-btn sito-invito__chiudi" data-sito-chiudi aria-label="Non ora: nascondi per 30 giorni" hidden>&times;</button>
</aside>
