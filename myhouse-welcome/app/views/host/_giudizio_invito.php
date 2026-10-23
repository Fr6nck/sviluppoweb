<?php use function MHW\t, MHW\tf;
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
      <span class="giudizio-invito__stato"><?= Support::e($rq['sconto'] ?: tf('{n}% con il tuo giudizio', ['{n}' => $pct])) ?></span>
      <h2 id="giudizio-invito-titolo"><?= t('Giudizio con sconto') ?></h2>
      <p class="small"><?= $rq['pubblicata'] ? t('Hai pubblicato la guida: puoi lasciarci il tuo giudizio quando vuoi (te lo chiediamo dopo 30 giorni). Ci vuole un minuto.') : t('Hai aderito: quando pubblichi la guida la tua scheda va in elenco; il giudizio te lo chiediamo 30 giorni dopo. Lo sconto non dipende dal voto.') ?></p>
    <?php elseif ($rq['stato'] === 'invito'): ?>
      <h2 id="giudizio-invito-titolo"><?= Support::e(tf('Giudizio con sconto: il {n}% sul prossimo rinnovo', ['{n}' => $pct])) ?></h2>
      <p class="small"><?= t('La tua guida tra «Le guide dei nostri host» su welcomemyhouse.it e, dopo 30 giorni, il tuo giudizio. Lo sconto non dipende dal voto.') ?></p>
    <?php else: ?>
      <h2 id="giudizio-invito-titolo"><?= t('Ti piace MyHouse Welcome? Raccontacelo') ?></h2>
      <p class="small"><?= t('Un voto e due righe per un altro host che sta decidendo. Ci vuole un minuto.') ?></p>
    <?php endif; ?>
  </div>
  <?php if ($rq['stato'] === 'invito'): ?>
    <form method="post" action="<?= b() ?>/giudizio/partecipo" class="giudizio-invito__partecipo"><?= Csrf::field() ?>
      <label class="check" style="align-items:flex-start"><input type="checkbox" name="partecipo" value="1" required style="margin-top:1px">
        <span><?= Giudizi::casellaHtml() /* 9L · lo stesso testo del pagamento */ ?></span></label>
      <p class="small muted" style="margin:0"><?= Giudizi::casellaNota(false) /* 9R */ ?> <?= t('Regole complete nei') ?> <a href="<?= b() ?>/termini#giudizio-sconto" target="_blank" rel="noopener"><?= t('Termini') ?></a><?= t(', punto «Giudizio con sconto».') ?></p>
      <div class="row" style="gap:10px;align-items:center"><button class="btn btn--ghost btn--sm"><?= t('Aderisco') ?></button></div>
    </form>
  <?php elseif ($rq['pubblicata']): ?>
    <a class="btn btn--sm" href="<?= b() ?>/giudizio"><?= t('Giudizio con sconto') ?></a>
  <?php endif; ?>
  <form method="post" action="<?= b() ?>/giudizio/chiudi" class="giudizio-invito__chiudi"><?= Csrf::field() ?>
    <button class="giudizio-invito__x" aria-label="<?= MHW\Support::e(t('Non ora')) ?>"><?= Icon::svg('close', 16, 2) ?></button></form>
</aside>
