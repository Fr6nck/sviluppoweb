<?php
/* 9N · Amministrazione → Richiami: le email automatiche ai clienti degli ultimi 30 giorni, per tipo, con l'ultimo giro
   e chi non vuole più riceverle. Riceve $righe, $perTipo, $noGrazie, $attivi, $giro, $cron, $bozze (9Q: Richiami::statoBozze), $sconti (9P: EmailSconti::elenco). */
use function MHW\b;
use MHW\{Support, Richiami, Csrf};
$title = 'Richiami';
$nomi = ['arrivo' => 'Check-in da compilare', 'sezioni' => 'Sezioni da aggiungere', 'pubblica' => 'Guida da pubblicare', 'rinnovo' => 'Rinnovo in arrivo',
         'scadenza' => 'Scadenza', 'eventi' => 'Eventi passati', 'inviti' => 'Inviti', 'conferma' => 'Email da confermare', 'pagamento' => 'Pagamento da riprendere',
         'parere' => 'Cosa ti ha fermato?', 'giudizio' => 'Richiesta di giudizio', 'benvenuto' => 'Benvenuto',
         'bozza7' => 'Bozza · giorno 7 (checklist)', 'bozza21' => 'Bozza · giorno 21 (la finiamo noi)', 'sconti' => 'Fino al ' . MHW\Sconti::massimoRinnovo()['totale'] . '% (7 giorni dopo l\'attivazione)'];
$noGrazie['bozza7'] = $noGrazie['bozza21'] = (int) ($noGrazie['bozza'] ?? 0) + (int) ($noGrazie['bozza7'] ?? 0) + (int) ($noGrazie['bozza21'] ?? 0);   // 9Q · un rifiuto ferma tutte e due
$badge = ['inviata' => 'pine', 'programmata' => 'ochre', 'fermata' => 'paper', 'da_mandare' => 'ochre', 'attesa' => 'alert', 'saltata' => 'paper'];
$attesa = count(array_filter($sconti, fn($x) => $x['sconti']['stato'] === 'attesa')); ?>
<div class="stack stack--lg">
  <div class="spread spread--mid">
    <div class="stack stack--sm"><h1>Richiami.</h1>
      <p class="muted small" style="max-width:760px">Le email che il sito manda da solo ai clienti: il percorso (check-in, sezioni, pubblicazione, conferma, pagamento),
        gli avvisi di rinnovo e scadenza, gli eventi passati, gli inviti, la richiesta di giudizio. Ognuna parte una volta sola; i clienti di prova non ne ricevono.
        Per mandare un avviso a mano: <a href="<?= b() ?>/admin/scadenze">Scadenze</a> o la scheda del cliente.</p></div>
    <a class="btn btn--ghost btn--sm" href="<?= b() ?>/admin/richiami?formato=csv">Esporta CSV</a>
  </div>
  <?php if (!$attivi): ?><p class="note" role="status"><span>La tabella dei richiami non c'è ancora: manca la migrazione 014.</span></p><?php endif; ?>
  <p class="small" style="margin:0">Ultimo giro: <b><?= $giro !== '' ? Support::e(Support::date($giro)) . ' ' . Support::e(substr($giro, 11, 5)) . ' UTC' : 'mai' ?></b>.
    Il controllo gira mentre qualcuno usa il sito, al massimo ogni 15 minuti<?= $cron ? ', e con il cron impostato' : ' (cron non impostato: vedi DA-CONFIGURARE)' ?>.</p>

  <div class="tablewrap"><table class="data">
    <caption class="sr-only">Richiami per tipo, ultimi 30 giorni</caption>
    <thead><tr><th>Tipo</th><th>Mandati negli ultimi 30 giorni</th><th>Non vogliono riceverlo</th></tr></thead>
    <tbody><?php foreach ($nomi as $k => $et): ?>
      <tr><td><?= Support::e($et) ?></td><td><?= (int) ($perTipo[$k] ?? 0) ?></td><td><?= isset(Richiami::TIPI[$k]) ? (int) ($noGrazie[$k] ?? 0) : '—' ?></td></tr>
    <?php endforeach; ?></tbody></table></div>

  <section class="stack" style="gap:10px" id="sconti" aria-labelledby="ric-sconti">
    <h2 id="ric-sconti" style="font-size:22px">L'email «Fino al <?= (int) MHW\Sconti::massimoRinnovo()['totale'] ?>%»</h2>
    <p class="muted small" style="margin:0;max-width:760px">7 giorni dopo l'attivazione (primo pagamento o codice omaggio), una volta per cliente: Porta un amico e Giudizio con sconto, con i conti sul suo piano.
      Mai ai clienti di prova, alle offerte su misura, agli abbonamenti dati a mano senza pagamento, a chi ha già <?= MHW\Inviti::amiciMassimi() ?> inviti andati a buon fine.</p>
    <?php if ($attesa > 0): ?><p class="note" role="status"><span>Email sconti in attesa: <b><?= $attesa ?></b>. Gli inviti sono spenti: partono da sole quando li accendi (MHW_INVITI=1).</span></p><?php endif; ?>
    <?php if (!$sconti): ?><p class="muted small">Nessun cliente attivato negli ultimi due mesi senza l'email.</p><?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Cliente</th><th>Stato</th><th></th></tr></thead>
      <tbody><?php foreach ($sconti as $sc): $stS = $sc['sconti']; ?>
        <tr><td><a href="<?= b() ?>/admin/cliente/<?= (int) $sc['id'] ?>"><?= Support::e($sc['user_name'] ?: $sc['email']) ?></a> <span class="small"><?= MHW\Codici::link((int) $sc['id']) ?></span></td>
          <td class="small"><span class="badge badge--<?= $badge[$stS['stato']] ?? 'paper' ?>"><?= Support::e(['da_mandare' => 'da mandare', 'attesa' => 'in attesa'][$stS['stato']] ?? $stS['stato']) ?></span> <?= Support::e($stS['motivo']) ?></td>
          <td><?php if (in_array($stS['stato'], ['programmata', 'da_mandare'], true)): ?>
            <form method="post" action="<?= b() ?>/admin/richiami/sconti/<?= (int) $sc['id'] ?>" style="margin:0"><?= Csrf::field() ?><button class="btn btn--ghost btn--sm">Manda adesso</button></form><?php endif; ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </section>

  <section class="stack" style="gap:10px" id="bozze" aria-labelledby="ric-bozze">
    <h2 id="ric-bozze" style="font-size:22px">Le email sulla bozza</h2>
    <p class="muted small" style="margin:0;max-width:760px">Le strutture mai pubblicate create da 1 a 35 giorni: al giorno 7 la checklist con il consiglio, al giorno 21 le tre strade
      («la finiamo noi», quindici minuti insieme, «Non ora»). Una volta sola per struttura; mai se l'account ha già una guida online, ha detto «Non ora», è di prova o della vetrina.
      Bozze ferme da più tempo: <a href="<?= b() ?>/admin/clienti?stato=bozze21">Clienti → Bozze da più di 21 giorni</a>.</p>
    <?php if (!$bozze): ?><p class="muted small">Nessuna bozza in questo periodo.</p><?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Struttura</th><th>Creata</th><th>Giorno 7</th><th>Giorno 21</th></tr></thead>
      <tbody><?php foreach ($bozze as $bz): ?>
        <tr><td><b><?= Support::e($bz['name']) ?></b> <span class="small muted">· <?= (int) $bz['giorni'] ?> giorni</span><br>
            <a class="small" href="<?= b() ?>/admin/cliente/<?= (int) $bz['account_id'] ?>"><?= Support::e($bz['cliente'] ?: $bz['email']) ?></a></td>
          <td class="small"><?= Support::e(Support::date($bz['created_at'])) ?></td>
          <?php foreach (['bozza7', 'bozza21'] as $tb): [$st, $nt] = $bz['stati'][$tb]; ?>
          <td class="small"><span class="badge badge--<?= $badge[$st] ?>"><?= Support::e($st) ?></span> <?= Support::e($nt) ?>
            <?php if ($st === 'programmata'): ?>
              <form method="post" action="<?= b() ?>/admin/richiami/bozza/<?= (int) $bz['id'] ?>/<?= $tb ?>" style="margin-top:6px"><?= Csrf::field() ?><button class="btn btn--ghost btn--sm">Manda adesso</button></form>
            <?php endif; ?></td>
          <?php endforeach; ?></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </section>

  <section class="stack" style="gap:10px" aria-labelledby="ric-ultimi">
    <h2 id="ric-ultimi" style="font-size:22px">Gli ultimi mandati</h2>
    <?php if (!$righe): ?><p class="muted small">Nessun richiamo negli ultimi 30 giorni.</p><?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Quando</th><th>Tipo</th><th>Cliente</th></tr></thead>
      <tbody><?php foreach ($righe as $x): ?>
        <tr><td class="small" style="white-space:nowrap"><?= Support::e(Support::date($x['sent_at'])) ?> <span class="tiny muted"><?= Support::e(substr((string) $x['sent_at'], 11, 5)) ?></span></td>
          <td><?= Support::e($nomi[$x['kind']] ?? $x['kind']) ?><?= str_starts_with((string) $x['ref'], 'manuale-') ? ' <span class="tiny muted">a mano</span>' : '' ?></td>
          <td><a href="<?= b() ?>/admin/cliente/<?= (int) $x['account_id'] ?>"><?= Support::e($x['cliente'] ?: $x['email']) ?></a> <span class="small"><?= MHW\Codici::link((int) $x['account_id']) ?></span></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </section>
</div>
