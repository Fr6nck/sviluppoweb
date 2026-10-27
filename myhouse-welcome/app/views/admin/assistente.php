<?php
/* Amministrazione → Vendite → Assistente AI (10): «Chiedi alla guida» per cliente nel mese scelto: domande, senza
   risposta, token, costo stimato dal listino delle Impostazioni, stato del limite; i totali del sito, gli ultimi errori,
   il CSV. I costi sono stime: fa fede la console di Anthropic. */
use function MHW\b;
use MHW\{Support, Csrf, Icon, Assistente};
$title = 'Assistente AI';
$n = fn(int $x) => number_format($x, 0, ',', '.');
$eur = fn(float $x) => number_format($x, 2, ',', '.') . ' €';
$nomeMese = fn(string $m) => ['01' => 'gennaio', '02' => 'febbraio', '03' => 'marzo', '04' => 'aprile', '05' => 'maggio', '06' => 'giugno', '07' => 'luglio',
                              '08' => 'agosto', '09' => 'settembre', '10' => 'ottobre', '11' => 'novembre', '12' => 'dicembre'][substr($m, 5, 2)] . ' ' . substr($m, 0, 4);
$percTetto = $tetto > 0 ? (int) floor($totali['euro'] / $tetto * 100) : 0;
$percRisposte = $totali['domande'] > 0 ? (int) round($totali['ok'] * 100 / $totali['domande']) : 0;
$medio = $totali['domande'] > 0 ? $totali['euro'] / $totali['domande'] : 0.0; ?>
<div class="stack stack--lg">
  <div class="stack stack--sm">
    <h1>Assistente AI.</h1>
    <p class="muted">Le domande degli ospiti a «Chiedi alla guida», per cliente, con il costo stimato dai token e dal listino del modello salvato nelle
      <a href="<?= b() ?>/admin/impostazioni#assistente">Impostazioni</a>. Il costo vero è nella console di Anthropic: qui serve a vedere chi usa cosa.</p>
    <?php if (!$configurato): ?><p class="note note--err" role="status">Manca la chiave API di Anthropic: i clienti Plus e Portfolio non vedono il bottone nella guida. Inseriscila nelle Impostazioni.</p><?php endif; ?>
    <?php if ($tetto > 0 && $percTetto >= 80 && $mese === Assistente::mese()): ?><p class="note note--err" role="status">Questo mese siete al <?= $percTetto ?>% del tetto di spesa (<?= $eur($tetto) ?>): al 100% l'assistente si ferma per tutti fino al primo del mese.</p><?php endif; ?>
  </div>

  <form method="get" action="<?= b() ?>/admin/assistente" class="row" style="gap:8px;align-items:center;flex-wrap:wrap">
    <label for="mese" class="small">Mese</label>
    <select id="mese" name="mese" onchange="this.form.submit()"><?php foreach ($mesi as $m): ?><option value="<?= Support::e($m) ?>" <?= $m === $mese ? 'selected' : '' ?>><?= Support::e($nomeMese($m)) ?></option><?php endforeach; ?></select>
    <noscript><button class="btn btn--ghost btn--sm">Vai</button></noscript>
    <a class="btn btn--ghost btn--sm" href="<?= b() ?>/admin/assistente/csv?mese=<?= Support::e($mese) ?>"><?= Icon::svg('doc', 15) ?>Esporta CSV</a>
  </form>

  <div class="cifre">
    <?php foreach ([['cifra--ocra', 'euro', 'Costo stimato · ' . $nomeMese($mese), $eur($totali['euro']), $n($totali['domande']) . ' domande' . ($tetto > 0 ? ' · tetto mensile ' . $eur($tetto) . ' (' . $percTetto . '%)' : ' · nessun tetto')],
                    ['cifra--pino', 'check', 'Domande con risposta', $n($totali['ok']), $percRisposte . '% del totale · ' . $n($totali['senza']) . ' senza risposta'],
                    ['cifra--mare', 'book', 'Guide con l\'assistente acceso', $n($totali['accese']), $n($totali['guide']) . ' guide con domande nel mese'],
                    ['', 'message', 'Costo medio a domanda', number_format($medio, 4, ',', '.') . ' €', 'modello ' . Assistente::modello() . ' · token: ' . $n($totali['token_in']) . ' in, ' . $n($totali['token_out']) . ' out']] as [$tono, $ico, $et, $val, $nota]): ?>
      <div class="cifra <?= $tono ?>"><span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg($ico, 17) ?></span><?= Support::e($et) ?></span>
        <b class="cifra__valore"><?= Support::e($val) ?></b><span class="cifra__nota"><?= Support::e($nota) ?></span></div>
    <?php endforeach; ?>
  </div>

  <section class="stack" aria-labelledby="per-cliente">
    <h2 id="per-cliente" style="font-size:20px">Per cliente · <?= Support::e($nomeMese($mese)) ?></h2>
    <?php if (!$righe): ?><p class="note note--quiet">Nessuna domanda in questo mese.</p><?php else: ?>
    <div class="tablewrap"><table class="data">
      <caption class="sr-only">Domande e costi stimati per cliente, <?= Support::e($nomeMese($mese)) ?></caption>
      <thead><tr><th scope="col">Cliente</th><th scope="col">Piano</th><th scope="col">Guide</th><th scope="col">Domande</th><th scope="col">Senza risposta</th><th scope="col">Token</th><th scope="col">Costo stimato</th><th scope="col">Limite</th></tr></thead>
      <tbody>
      <?php foreach ($righe as $x): $aid = (int) $x['account_id']; ?>
        <tr><td><a href="<?= b() ?>/admin/cliente/<?= $aid ?>"><?= Support::e($x['nome'] ?: $x['email']) ?></a> <span class="small"><?= MHW\Codici::link($aid) ?></span><br><span class="small muted"><?= Support::e($x['email']) ?></span></td>
          <td><?= Support::e($x['piano'] ?: '—') ?></td><td><?= $n($x['guide']) ?></td><td><?= $n($x['domande']) ?></td><td><?= $n($x['senza']) ?></td>
          <td><?= $n($x['token_in'] + $x['token_out']) ?> <span class="small muted"><?= $n($x['token_out']) ?> out</span></td>
          <td><b><?= $eur($x['euro']) ?></b></td>
          <td><?php if ($x['spente'] > 0 && $x['domande'] === 0): ?><span class="badge badge--paper">Spento dall'host</span>
              <?php elseif ($x['limite'] > 0 && $x['percento'] >= 100): ?><span class="badge badge--alert">Limite raggiunto · fermo</span>
              <?php elseif ($x['limite'] > 0 && $x['percento'] >= 80): ?><span class="badge badge--ochre"><?= (int) $x['percento'] ?>% · avvisato</span>
              <?php else: ?><span class="badge badge--pine"><?= (int) $x['percento'] ?>% del limite</span><?php endif; ?>
            <?php if ($x['errori'] > 0): ?> <span class="small muted"><?= $n($x['errori']) ?> errori</span><?php endif; ?></td></tr>
      <?php endforeach; ?>
        <tr style="font-weight:600"><td>Totale</td><td></td><td><?= $n($totali['guide']) ?></td><td><?= $n($totali['domande']) ?></td><td><?= $n($totali['senza']) ?></td><td><?= $n($totali['token_in'] + $totali['token_out']) ?></td><td><?= $eur($totali['euro']) ?></td><td></td></tr>
      </tbody>
    </table></div>
    <?php endif; ?>
    <p class="small muted" style="margin:0">Costo stimato = token in ingresso × prezzo + token in uscita × prezzo (la cache dei prompt costa 1,25× in scrittura e 0,1× in lettura), con il listino salvato in Impostazioni.
      Le domande sono anonime: qui solo numeri; i testi delle domande senza risposta li vede l'host nel suo pannello. Il limite personale di un cliente è nella sua scheda.</p>
  </section>

  <?php if ($errori): ?>
  <section class="stack" aria-labelledby="err-titolo">
    <h2 id="err-titolo" style="font-size:20px">Ultime chiamate non riuscite o fermate</h2>
    <div class="tablewrap"><table class="data">
      <thead><tr><th scope="col">Quando</th><th scope="col">Cliente</th><th scope="col">Esito</th><th scope="col">Dettaglio</th></tr></thead>
      <tbody>
      <?php foreach ($errori as $e): ?>
        <tr><td><?= Support::e(Support::date($e['created_at'])) ?></td><td><?= Support::e((string) ($e['email'] ?? '—')) ?></td>
          <td><?= $e['outcome'] === 'limite' ? 'Fermata dal limite' : 'Errore' ?></td><td class="small"><?= Support::e($e['error']) ?><?= $e['outcome'] === 'errore' && ($c = Assistente::consiglio((string) $e['error'])) !== '' ? ' <span class="muted">' . Support::e($c) . '</span>' : '' ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
  <?php endif; ?>
</div>
