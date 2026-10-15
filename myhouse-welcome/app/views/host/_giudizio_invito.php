<?php
/* «Ti sta piacendo MyHouse Welcome?» in «Le mie guide» (7D): da 30 giorni dopo la prima
   pubblicazione, finché non c'è un giudizio. La ✕ lo chiude; torna una volta sola dopo 60 giorni. */
use function MHW\b;
use MHW\{Icon, Csrf};
if (empty($chiediGiudizio)) return; ?>
<aside class="giudizio-invito" aria-labelledby="giudizio-invito-titolo">
  <span class="giudizio-invito__stelle" aria-hidden="true"><?php for ($i = 0; $i < 5; $i++) echo Icon::svg('star', 18, 1.8); ?></span>
  <div class="giudizio-invito__testo">
    <h2 id="giudizio-invito-titolo">Ti sta piacendo MyHouse Welcome?</h2>
    <p class="small">Due righe per un altro host che sta decidendo. Ci vuole un minuto.</p>
  </div>
  <a class="btn btn--sm" href="<?= b() ?>/giudizio">Dai il tuo giudizio</a>
  <form method="post" action="<?= b() ?>/giudizio/chiudi" class="giudizio-invito__chiudi"><?= Csrf::field() ?>
    <button class="giudizio-invito__x" aria-label="Non ora"><?= Icon::svg('close', 16, 2) ?></button></form>
</aside>
