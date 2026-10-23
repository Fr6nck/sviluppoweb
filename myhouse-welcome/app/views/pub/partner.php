<?php
/* 9O · La pagina del partner (mockup partner.html, blocco 3). Senza accesso: il modulo «mandami il link» (niente password).
   Dentro: il codice, lo sconto per i suoi clienti e la sua percentuale, i quattro numeri, i clienti (struttura, città, piano,
   dal, ultimo pagamento, la sua commissione, stato) e i materiali. Mai email, importi pagati dai clienti, dati di pagamento.
   Riceve $p (o null), $clienti, $riep. Nella lingua del partner (IT/EN). Noindex (nessuna $seoPagina). */
use function MHW\b;
use MHW\{Support, Csrf, ProgrammaPartner};
$en = $p && $p['lingua'] === 'en';
$U = fn(string $it, string $inglese) => $en ? $inglese : $it;
$title = $U('Pagina partner', 'Partner page'); ?>
<div class="stack stack--lg" style="max-width:980px;margin:0 auto">
<?php if (!$p): ?>
  <section class="panel stack" style="max-width:560px" aria-labelledby="pt-accedi">
    <h1 id="pt-accedi" style="margin:0">Pagina partner.</h1>
    <p class="muted" style="margin:0">Scrivi l'email con cui sei partner di MyHouse Welcome: ti mandiamo un link per entrare, senza password (vale <?= ProgrammaPartner::ORE_LINK ?> ore, una volta sola).
      <span lang="en">Partners: enter your email and we send you a link to sign in, no password needed.</span></p>
    <form method="post" action="<?= b() ?>/partner/accedi" class="stack" style="gap:10px"><?= Csrf::field() ?>
      <div class="field" style="margin:0"><label for="pt-email">Email</label><input id="pt-email" name="email" type="email" required autocomplete="email"></div>
      <div class="actions"><button class="btn">Mandami il link</button></div>
    </form>
    <p class="small muted" style="margin:0">Vuoi diventare partner? <a href="<?= b() ?>/property-manager">Il programma per property manager e agenzie</a>.</p>
  </section>
<?php else: $nome = trim(explode(' ', trim((string) ($p['referente'] ?: $p['ragione_sociale'])))[0] ?? ''); $link = ProgrammaPartner::link($p);
      $statiC = ['maturata' => $U('Da ricevere', 'To be received'), 'liquidata' => $U('Ricevuta', 'Received'), 'stornata' => $U('Stornata', 'Reversed')]; ?>
  <div class="spread" style="align-items:flex-start;gap:16px;flex-wrap:wrap">
    <div class="stack stack--sm">
      <h1 style="margin:0"><?= $U('Ciao', 'Hi') ?><?= $nome !== '' ? ' ' . Support::e($nome) : '' ?>.</h1>
      <p style="margin:0"><?= $U('Il tuo codice:', 'Your code:') ?> <b style="font-size:22px"><?= Support::e($p['codice']) ?></b>
        <span class="muted"> · <?= (int) $p['sconto_clienti_pct'] > 0 ? $U('i tuoi clienti hanno il ', 'your clients get ') . (int) $p['sconto_clienti_pct'] . $U('% sul primo anno', '% off the first year') . ' · ' : '' ?><?= $U('a te il ', 'you get ') . (int) $p['commissione_pct'] . $U('% su ogni pagamento', '% of every payment') ?><?= (int) $p['rinnovi'] ? '' : $U(' del primo anno', ' in the first year') ?></span></p>
      <?php if ($p['stato'] === 'sospeso'): ?><p class="note" role="status"><?= $U('Il tuo codice è sospeso: scrivici a ', 'Your code is suspended: write to us at ') . Support::e(Support::commerciale()) ?>.</p><?php endif; ?>
    </div>
    <form method="post" action="<?= b() ?>/partner/esci" style="margin:0"><?= Csrf::field() ?><button class="btn btn--quiet btn--sm"><?= $U('Esci', 'Sign out') ?></button></form>
  </div>
  <div class="field" style="margin:0;max-width:640px"><label for="pt-link"><?= $U('Il tuo link con il codice già inserito', 'Your link with the code already filled in') ?></label>
    <div class="row" style="gap:8px"><input id="pt-link" type="text" readonly value="<?= Support::e($link) ?>" style="flex:1">
      <button type="button" class="btn btn--ghost" data-copia="<?= Support::e($link) ?>" data-copiato="<?= $U('Copiato', 'Copied') ?>"><?= $U('Copia il link con il codice', 'Copy the link with the code') ?></button></div></div>
  <div class="cifre cifre--4" aria-label="<?= $U('In breve', 'At a glance') ?>">
    <div class="cifra cifra--pino"><span class="cifra__testa"><?= $U('Clienti attivi', 'Active clients') ?></span><b class="cifra__valore"><?= (int) $riep['attivi'] ?></b></div>
    <div class="cifra cifra--ocra"><span class="cifra__testa"><?= $U('Da ricevere', 'To be received') ?></span><b class="cifra__valore"><?= Support::e(Support::money((int) $riep['da_ricevere'])) ?></b><span class="cifra__nota"><?= $U('liquidazione ', 'paid ') . ($p['periodicita'] === 'mensile' ? $U('mensile', 'monthly') : $U('trimestrale', 'quarterly')) ?></span></div>
    <div class="cifra cifra--mare"><span class="cifra__testa"><?= $U('Ricevuto nel ', 'Received in ') . gmdate('Y') ?></span><b class="cifra__valore"><?= Support::e(Support::money((int) $riep['ricevuto_anno'])) ?></b></div>
    <div class="cifra cifra--carta"><span class="cifra__testa"><?= $U('Prossimi rinnovi', 'Upcoming renewals') ?></span><b class="cifra__valore"><?= (int) $riep['rinnovi_90'] ?></b><span class="cifra__nota"><?= $U('entro 90 giorni', 'within 90 days') ?></span></div>
  </div>
  <section class="stack" style="gap:10px" aria-labelledby="pt-clienti">
    <h2 id="pt-clienti" style="font-size:22px;margin:0"><?= $U('I tuoi clienti', 'Your clients') ?></h2>
    <?php if (!$clienti): ?><p class="note note--quiet"><?= $U('Ancora nessun cliente con il tuo codice: condividi il link qui sopra.', 'No clients with your code yet: share the link above.') ?></p><?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th><?= $U('Struttura', 'Property') ?></th><th><?= $U('Piano', 'Plan') ?></th><th><?= $U('Dal', 'Since') ?></th><th><?= $U('Ultimo pagamento', 'Last payment') ?></th><th><?= $U('La tua commissione', 'Your commission') ?></th><th><?= $U('Stato', 'Status') ?></th></tr></thead>
      <tbody><?php foreach ($clienti as $c): $u = $c['ultima']; ?>
        <tr><td><?= Support::e(trim($c['struttura'] . ($c['citta'] !== '' ? ' · ' . $c['citta'] : '')) ?: '—') ?></td>
          <td class="small"><?= Support::e($c['piano'] ?: '—') ?></td>
          <td class="small"><?= $c['dal'] !== '' ? Support::e(Support::date($c['dal'])) : '—' ?></td>
          <td class="small"><?= $c['ultimo_pagamento'] !== '' ? Support::e(Support::date($c['ultimo_pagamento'])) : '—' ?></td>
          <td><b><?= Support::e(Support::money((int) $c['commissione'])) ?></b></td>
          <td class="small"><?= $u ? Support::e($statiC[$u['stato']] ?? $u['stato']) : $U('Nessun pagamento', 'No payment yet') ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
    <p class="small muted" style="margin:0"><?= $U('Qui vedi solo struttura, città, piano, date e la tua commissione: i dati personali e di pagamento dei clienti restano a loro.', 'Here you only see property, town, plan, dates and your commission: clients\' personal and payment details stay with them.') ?></p>
  </section>
  <section class="panel stack" aria-labelledby="pt-materiali">
    <h2 id="pt-materiali" style="font-size:22px;margin:0"><?= $U('Come presentare MyHouse Welcome ai tuoi proprietari', 'How to present MyHouse Welcome to your owners') ?></h2>
    <ul class="stack small" style="gap:6px;margin:0;padding-left:20px">
      <li><?= $U('Una guida digitale per gli ospiti, sul telefono: arrivo, Wi-Fi, regole, consigli in zona, nelle loro lingue. Niente app.', 'A digital guide for guests, on their phone: arrival, Wi-Fi, house rules, local tips, in their languages. No app.') ?></li>
      <li><?= $U('Meno domande ripetute su WhatsApp, recensioni migliori, più prenotazioni dirette.', 'Fewer repeated WhatsApp questions, better reviews, more direct bookings.') ?></li>
      <li><?= $U('Si prova gratis e si paga solo quando si pubblica; con il tuo codice ', 'They try it for free and pay only when they publish; with your code ') . Support::e($p['codice']) . ((int) $p['sconto_clienti_pct'] > 0 ? $U(' hanno il ', ' they get ') . (int) $p['sconto_clienti_pct'] . $U('% sul primo anno.', '% off the first year.') : '.') ?></li>
      <li><?= $U('Più strutture? Il piano Portfolio: più ne aggiungono, meno costa ognuna.', 'Several properties? The Portfolio plan: the more they add, the less each one costs.') ?></li>
    </ul>
    <div class="actions"><a class="btn btn--ghost" href="<?= b() ?>/partner/foglio.pdf"><?= $U('Scarica il foglio da stampare (PDF, con il codice)', 'Download the sheet to print (PDF, with your code)') ?></a>
      <a class="btn btn--quiet" href="<?= Support::e($link) ?>" target="_blank" rel="noopener"><?= $U('Guarda la pagina che vedono', 'See the page they see') ?></a></div>
  </section>
  <p class="small muted"><?= $U('Domande sul programma o sulle liquidazioni? Scrivi a ', 'Questions about the programme or payments? Write to ') ?><a href="mailto:<?= Support::e(Support::commerciale()) ?>"><?= Support::e(Support::commerciale()) ?></a>.</p>
  <script>document.querySelectorAll('[data-copia]').forEach(function (b) { b.addEventListener('click', function () { if (navigator.clipboard) navigator.clipboard.writeText(b.getAttribute('data-copia')).then(function () { b.textContent = b.getAttribute('data-copiato'); }); }); });</script>
<?php endif; ?>
</div>
