<?php
/* 9N · Amministrazione → Richiami: le email automatiche ai clienti degli ultimi 30 giorni, per tipo, con l'ultimo giro
   e chi non vuole più riceverle. Riceve $righe, $perTipo, $noGrazie, $attivi, $giro, $cron. */
use function MHW\b;
use MHW\{Support, Richiami};
$title = 'Richiami';
$nomi = ['arrivo' => 'Check-in da compilare', 'sezioni' => 'Sezioni da aggiungere', 'pubblica' => 'Guida da pubblicare', 'rinnovo' => 'Rinnovo in arrivo',
         'scadenza' => 'Scadenza', 'eventi' => 'Eventi passati', 'inviti' => 'Inviti', 'conferma' => 'Email da confermare', 'pagamento' => 'Pagamento da riprendere',
         'parere' => 'Cosa ti ha fermato?', 'giudizio' => 'Richiesta di giudizio', 'benvenuto' => 'Benvenuto']; ?>
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
