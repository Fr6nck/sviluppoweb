<?php
/* «Ti piace MyHouse Welcome?» in «Le mie guide» (7D, 9B): il riquadro del programma del giudizio con lo sconto.
   Dal primo accesso, finché non c'è un giudizio. La ✕ lo chiude; torna una volta sola dopo 60 giorni. In Account →
   Il tuo giudizio resta sempre. Stati (Giudizi::riquadro): aderito (al pagamento o dal pannello), invito (non ha
   aderito, interruttore acceso: la stessa unica casella del pagamento, in breve), senza_sconto (interruttore spento). */
use function MHW\b;
use MHW\{Icon, Csrf, Support, Giudizi};
if (empty($chiediGiudizio)) return;
$rq = $giudizioRiquadro ?? ['stato' => 'invito', 'pubblicata' => true, 'aderito' => false, 'sconto' => '', 'giudizio' => null];
if (!in_array($rq['stato'], ['aderito', 'invito', 'senza_sconto'], true)) return;
$pct = Giudizi::disponibili() ? Giudizi::percentuale() : 5; ?>
<aside class="giudizio-invito giudizio-invito--<?= $rq['stato'] ?>" aria-labelledby="giudizio-invito-titolo" id="giudizio-invito">
  <span class="giudizio-invito__stelle" aria-hidden="true"><?php for ($i = 0; $i < 5; $i++) echo Icon::svg('star', 18, 1.8); ?></span>
  <div class="giudizio-invito__testo">
    <?php if ($rq['stato'] === 'aderito'): ?>
      <span class="giudizio-invito__stato"><?= Support::e($rq['sconto'] ?: $pct . '% con il tuo giudizio') ?></span>
      <h2 id="giudizio-invito-titolo">Ti piace MyHouse Welcome?</h2>
      <p class="small"><?= $rq['pubblicata'] ? 'Hai pubblicato la guida: ora puoi lasciarci il tuo giudizio. Ci vuole un minuto.' : 'Hai aderito: quando avrai pubblicato la guida potrai lasciare il tuo giudizio, un voto e due righe. Lo sconto non dipende dal voto.' ?></p>
    <?php elseif ($rq['stato'] === 'invito'): ?>
      <h2 id="giudizio-invito-titolo"><?= $pct ?>% sul prossimo rinnovo, con un giudizio</h2>
      <p class="small">Lasciaci un voto da 1 a 5 e due righe quando hai pubblicato la guida: il <?= $pct ?>% si applica al rinnovo annuale successivo. Lo sconto non dipende dal voto.</p>
    <?php else: ?>
      <h2 id="giudizio-invito-titolo">Ti piace MyHouse Welcome? Raccontacelo</h2>
      <p class="small">Un voto e due righe per un altro host che sta decidendo. Ci vuole un minuto.</p>
    <?php endif; ?>
  </div>
  <?php if ($rq['stato'] === 'invito'): ?>
    <form method="post" action="<?= b() ?>/giudizio/partecipo" class="giudizio-invito__partecipo"><?= Csrf::field() ?>
      <label class="check" style="align-items:flex-start"><input type="checkbox" name="partecipo" value="1" required style="margin-top:1px">
        <span><b>Partecipo: lascerò un giudizio</b> e accetto che possa essere mostrato in home con nome, copertina, foto e logo, senza link alla mia guida.</span></label>
      <div class="row" style="gap:10px;align-items:center"><button class="btn btn--ghost btn--sm">Conferma</button>
        <a class="small" href="<?= b() ?>/termini#giudizio-sconto" target="_blank" rel="noopener">Le regole</a></div>
    </form>
  <?php elseif ($rq['pubblicata']): ?>
    <a class="btn btn--sm" href="<?= b() ?>/giudizio">Dai il tuo giudizio</a>
  <?php endif; ?>
  <form method="post" action="<?= b() ?>/giudizio/chiudi" class="giudizio-invito__chiudi"><?= Csrf::field() ?>
    <button class="giudizio-invito__x" aria-label="Non ora"><?= Icon::svg('close', 16, 2) ?></button></form>
</aside>
