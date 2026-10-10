<?php
/* Invita un amico: il riquadro con la barra, il link e il codice, l'invito per email, come funziona,
   gli amici invitati (nome breve e stato, niente altro) e le regole in breve.
   Riceve $acc, $puo, $inv, $amici, $perEmail, $codice, $sub. Chi non può ancora invitare ($puo falso)
   vede come funziona e cosa manca. */
use function MHW\b;
use MHW\{Support, Inviti, Icon};
$title = 'Invita un amico';
$traguardo = Inviti::MASSIMO === 50 ? 'metà prezzo' : 'il ' . Inviti::MASSIMO . '%'; ?>
<div class="stack stack--lg" style="max-width:980px">
  <div class="stack stack--sm">
    <h1>Invita un amico.</h1>
    <p class="lead" style="max-width:680px">Conosci un altro host? Mandagli il tuo link. Lui ha il <?= Inviti::AMICO ?>% di sconto sul primo anno,
      tu il <?= Inviti::PASSO ?>% in meno sul prossimo rinnovo per ogni amico che pubblica, fino a <?= $traguardo ?>.</p>
  </div>

  <?php if (!$puo): ?>
    <section class="panel stack" style="gap:10px" aria-labelledby="inviti-quando">
      <h2 id="inviti-quando" style="font-size:22px;margin:0">Gli inviti si attivano con il tuo abbonamento</h2>
      <?php if ($sub && $sub['provider'] !== 'stripe'): ?>
        <p style="margin:0">Il tuo abbonamento è stato attivato dal nostro staff: lo sconto degli inviti si applica agli abbonamenti pagati online. Scrivici e vediamo insieme.</p>
      <?php else: ?>
        <p style="margin:0">Quando la tua guida è pubblicata trovi qui il tuo link e il tuo codice da girare agli amici, e puoi mandare l'invito per email direttamente da questa pagina.</p>
        <div class="row"><a class="btn" href="<?= b() ?>/pannello">Vai alle tue guide</a></div>
      <?php endif; ?>
    </section>
  <?php else: $paginaInviti = true; include __DIR__ . '/_invita.php'; ?>

  <?php $bozza = $_SESSION['invito_email'] ?? null; unset($_SESSION['invito_email']); ?>
  <section class="panel stack invito-email" id="per-email" aria-labelledby="per-email-titolo">
    <h2 id="per-email-titolo" style="font-size:22px;margin:0">Invita per email</h2>
    <p class="small muted" style="margin:0">Scrivi gli indirizzi dei tuoi amici: mandiamo noi l'invito, a nome tuo, con il tuo link e il tuo codice. Non conserviamo gli indirizzi.</p>
    <form method="post" action="<?= b() ?>/inviti/email" class="stack" style="gap:14px"><?= MHW\Csrf::field() ?>
      <div class="field" style="margin:0">
        <label for="inv-email">Email dei tuoi amici</label>
        <p class="help" id="inv-email-aiuto" style="margin:0 0 6px">Fino a <?= Inviti::EMAIL_PER_INVIO ?>, separati da una virgola o uno per riga.</p>
        <textarea id="inv-email" name="email" rows="3" required aria-describedby="inv-email-aiuto" autocomplete="off" spellcheck="false" inputmode="email"
                  placeholder="mario@esempio.it, giulia@esempio.it"><?= Support::e((string) ($bozza['email'] ?? '')) ?></textarea>
      </div>
      <div class="field" style="margin:0">
        <label for="inv-msg">Un messaggio <span class="muted">(facoltativo)</span></label>
        <textarea id="inv-msg" name="messaggio" rows="3" maxlength="400" placeholder="Ciao, io la uso per la mia casa vacanze: prova anche tu."><?= Support::e((string) ($bozza['messaggio'] ?? '')) ?></textarea>
      </div>
      <div class="actions"><button class="btn"><?= Icon::svg('message', 16) ?>Manda l'invito</button></div>
    </form>
    <?php if ($perEmail): ?>
      <div class="stack" style="gap:8px">
        <b class="small">Inviti mandati per email</b>
        <?php foreach ($perEmail as $pe):
              [$tono, $et] = match ($pe['stato']) {
                  'valido', 'usato', 'oltre' => ['pine', 'Ha pubblicato'],
                  'registrato' => ['ochre', 'Si è registrato'],
                  'annullato' => ['paper', 'Non vale'],
                  default => ['paper', 'Invitato'],
              }; ?>
          <div class="rowcard" style="flex-wrap:wrap"><b class="grow"><?= Support::e($pe['email_mask']) ?></b>
            <span class="small muted">Il <?= Support::e(Support::date($pe['sent_at'])) ?></span>
            <span class="badge badge--<?= $tono ?>"><?= Support::e($et) ?></span></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <section class="stack" style="gap:12px" aria-labelledby="inviti-come">
    <h2 id="inviti-come" style="font-size:24px">Come funziona</h2>
    <div class="invito__passi">
      <div class="invito__passo"><i aria-hidden="true">1</i><b>Manda il tuo link</b>
        <span class="small muted">Per email da questa pagina, su WhatsApp o a voce<?= $codice !== '' ? ': vale anche il codice <b>' . Support::e($codice) . '</b>, scritto dove si inserisce il codice sconto' : '' ?>.</span></div>
      <div class="invito__passo"><i aria-hidden="true">2</i><b>Il tuo amico pubblica la guida</b>
        <span class="small muted">Si registra, prepara la guida e ha il <?= Inviti::AMICO ?>% di sconto sul primo anno. L'invito conta quando paga.</span></div>
      <div class="invito__passo"><i aria-hidden="true">3</i><b>Il tuo rinnovo scende</b>
        <span class="small muted">Il <?= Inviti::PASSO ?>% in meno per ogni amico, fino al <?= Inviti::MASSIMO ?>% con <?= Inviti::amiciMassimi() ?> amici. Lo sconto si applica da solo al prossimo rinnovo.</span></div>
    </div>
  </section>

  <?php if ($puo): ?>
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
  <?php endif; ?>

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
