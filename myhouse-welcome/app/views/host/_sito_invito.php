<?php
/* «Anche il tuo sito»: un invito breve in «Le mie guide», che rimanda alla sezione della
   landing. «Non ora» lo nasconde per 30 giorni in questo browser; «Non mostrare più» lascia
   un cookie tecnico del pannello (mhw_no_sito, 180 giorni) e non compare più. */
use function MHW\b;
use MHW\{Icon, Csrf};
if (!empty($_COOKIE['mhw_no_sito'])) return; ?>
<aside class="sito-invito" data-sito-invito aria-label="Il sito della tua struttura">
  <span class="sito-invito__ico"><?= Icon::svg('globe', 20, 1.8) ?></span>
  <p class="sito-invito__testo"><b>Meno commissioni ai portali. Più incasso per te. Più ospiti diretti.</b>
    <span>Realizziamo anche il sito della tua struttura, con la prenotazione diretta.</span></p>
  <a class="btn btn--sm sito-invito__vai" href="<?= b() ?>/#sito">Scopri come <?= Icon::svg('arrow', 15, 2) ?></a>
  <form method="post" action="<?= b() ?>/pannello/sito/nascondi" class="sito-invito__mai"><?= Csrf::field() ?>
    <button class="linkbtn small">Non mostrare più</button></form>
  <button type="button" class="icon-btn sito-invito__chiudi" data-sito-chiudi aria-label="Non ora: nascondi per 30 giorni" hidden>&times;</button>
</aside>
