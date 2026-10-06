<?php
/* Invita un amico: il riquadro con la barra, come funziona, gli amici invitati
   (nome breve e stato, niente altro) e le regole in breve. Riceve $acc, $inv, $amici, $codice. */
use function MHW\b;
use MHW\{Support, Inviti};
$title = 'Invita un amico';
$traguardo = Inviti::MASSIMO === 50 ? 'metà prezzo' : 'il ' . Inviti::MASSIMO . '%'; ?>
<div class="stack stack--lg" style="max-width:980px">
  <div class="stack stack--sm">
    <h1>Invita un amico.</h1>
    <p class="lead" style="max-width:680px">Conosci un altro host? Mandagli il tuo link. Lui ha il <?= Inviti::AMICO ?>% di sconto sul primo anno,
      tu il <?= Inviti::PASSO ?>% in meno sul prossimo rinnovo per ogni amico che pubblica, fino a <?= $traguardo ?>.</p>
  </div>

  <?php $paginaInviti = true; include __DIR__ . '/_invita.php'; ?>

  <section class="stack" style="gap:12px" aria-labelledby="inviti-come">
    <h2 id="inviti-come" style="font-size:24px">Come funziona</h2>
    <div class="invito__passi">
      <div class="invito__passo"><i aria-hidden="true">1</i><b>Manda il tuo link</b>
        <span class="small muted">Su WhatsApp, per email o a voce: vale anche il codice <b><?= Support::e($codice) ?></b>, scritto dove si inserisce il codice sconto.</span></div>
      <div class="invito__passo"><i aria-hidden="true">2</i><b>Il tuo amico pubblica la guida</b>
        <span class="small muted">Si registra, prepara la guida e ha il <?= Inviti::AMICO ?>% di sconto sul primo anno. L'invito conta quando paga.</span></div>
      <div class="invito__passo"><i aria-hidden="true">3</i><b>Il tuo rinnovo scende</b>
        <span class="small muted">Il <?= Inviti::PASSO ?>% in meno per ogni amico, fino al <?= Inviti::MASSIMO ?>% con <?= Inviti::amiciMassimi() ?> amici. Lo sconto si applica da solo al prossimo rinnovo.</span></div>
    </div>
  </section>

  <section class="stack" style="gap:10px" aria-labelledby="inviti-elenco">
    <h2 id="inviti-elenco" style="font-size:24px">I tuoi inviti</h2>
    <?php if (!$amici): ?>
      <p class="muted">Ancora nessuno. Il primo amico che pubblica vale il <?= Inviti::PASSO ?>% in meno sul tuo rinnovo.</p>
    <?php else: foreach ($amici as $am):
          [$tono, $etichetta, $quando] = match ($am['status']) {
              'valido' => ['pine', "\u{2212}" . Inviti::PASSO . '% sul tuo rinnovo', 'Ha pubblicato il ' . Support::date($am['qualified_at'])],
              'oltre'  => ['paper', 'Oltre il massimo', 'Ha pubblicato il ' . Support::date($am['qualified_at'])],
              'usato'  => ['paper', 'Già scontato', 'Ha pubblicato il ' . Support::date($am['qualified_at'])],
              default  => ['ochre', 'Sta preparando la guida', 'Dal ' . Support::date($am['created_at'])],
          }; ?>
      <div class="rowcard" style="flex-wrap:wrap"><b class="grow"><?= Support::e($am['nome']) ?></b>
        <span class="small muted"><?= Support::e($quando) ?></span>
        <span class="badge badge--<?= $tono ?>"><?= Support::e($etichetta) ?></span></div>
    <?php endforeach; endif; ?>
  </section>

  <section class="invito__regole" aria-labelledby="inviti-regole">
    <b id="inviti-regole">Le regole, in breve</b>
    <ul>
      <li>Un invito conta quando il tuo amico paga il suo primo abbonamento.</li>
      <li>Lo sconto vale sul tuo prossimo rinnovo automatico, poi il conto riparte da zero.</li>
      <li>Il massimo è il <?= Inviti::MASSIMO ?>%: oltre <?= Inviti::amiciMassimi() ?> amici, ognuno ha comunque il suo <?= Inviti::AMICO ?>%.</li>
      <li>Non contano account tuoi o con i tuoi stessi dati di fatturazione.</li>
      <li>Per il tuo amico l'invito non si somma ad altri codici sconto.</li>
    </ul>
    <a class="small" href="<?= b() ?>/termini#inviti">Leggi il regolamento completo</a>
  </section>
</div>
