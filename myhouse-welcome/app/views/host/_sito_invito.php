<?php
/* «MyHouse Pro · da blackout.in» (7B): il sito della struttura con la prenotazione diretta, in «Le mie guide».
   Fondo nero con una grana leggera, accento mattone, coerente con blackout.in. La ✕ («Non ora») lo nasconde
   per 30 giorni in questo browser; «Non mostrare più» lascia un cookie tecnico del pannello (mhw_no_sito,
   180 giorni) e non compare più. */
use function MHW\b;
use MHW\{Icon, Csrf};
if (!empty($_COOKIE['mhw_no_sito'])) return; ?>
<aside class="sito-invito" data-sito-invito aria-labelledby="sito-invito-titolo">
  <button type="button" class="sito-invito__chiudi" data-sito-chiudi aria-label="Non ora: nascondi per 30 giorni" hidden><?= Icon::svg('close', 18, 2) ?></button>
  <span class="sito-invito__kicker">MyHouse Pro · da blackout.in</span>
  <h2 class="sito-invito__titolo" id="sito-invito-titolo">Il tuo sito, con la prenotazione diretta.</h2>
  <ul class="sito-invito__punti">
    <li><?= Icon::svg('check', 16, 2.2) ?>Meno commissioni ai portali</li>
    <li><?= Icon::svg('check', 16, 2.2) ?>Più ospiti che prenotano da te</li>
    <li><?= Icon::svg('check', 16, 2.2) ?>Fatto da un'agenzia con oltre 20 anni nel digitale</li>
  </ul>
  <div class="sito-invito__piede">
    <a class="btn sito-invito__vai" href="https://blackout.in/myhouse/" target="_blank" rel="noopener">Scopri MyHouse Pro <?= Icon::svg('arrow', 15, 2) ?></a>
    <form method="post" action="<?= b() ?>/pannello/sito/nascondi" class="sito-invito__mai"><?= Csrf::field() ?>
      <button class="linkbtn">Non mostrare più</button></form>
  </div>
</aside>
