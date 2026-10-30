<?php
/* Amministrazione → Vendite → Concierge AI (10, 9T): consumo e spesa del mese scelto. In cima quattro numeri (domande e
   media al giorno, costo stimato con la quota di cache e, con la Admin key facoltativa, il costo vero letto da Anthropic,
   proiezione a fine mese, costo medio a domanda), le domande per giorno degli ultimi 30 giorni, la tabella per cliente con
   le sue strutture (il limite è per struttura) e la riga a sé della demo, le 20 domande senza risposta più frequenti
   (anonime), gli ultimi errori, il CSV. I costi sono stime dal listino delle Impostazioni. */
use function MHW\b;
use MHW\{Support, Csrf, Icon, Assistente};
$title = 'Concierge AI';
$n = fn(int $x) => number_format($x, 0, ',', '.');
$eur = fn(float $x) => number_format($x, 2, ',', '.') . ' €';
$nomeMese = fn(string $m) => ['01' => 'gennaio', '02' => 'febbraio', '03' => 'marzo', '04' => 'aprile', '05' => 'maggio', '06' => 'giugno', '07' => 'luglio',
                              '08' => 'agosto', '09' => 'settembre', '10' => 'ottobre', '11' => 'novembre', '12' => 'dicembre'][substr($m, 5, 2)] . ' ' . substr($m, 0, 4);
$giornoBreve = fn(string $g) => (int) substr($g, 8, 2) . ' ' . ['01' => 'gen', '02' => 'feb', '03' => 'mar', '04' => 'apr', '05' => 'mag', '06' => 'giu', '07' => 'lug',
                                                                 '08' => 'ago', '09' => 'set', '10' => 'ott', '11' => 'nov', '12' => 'dic'][substr($g, 5, 2)];
$percTetto = $tetto > 0 ? (int) floor($totali['euro'] / $tetto * 100) : 0;
$percRisposte = $totali['domande'] > 0 ? (int) round($totali['ok'] * 100 / $totali['domande']) : 0;
$medio = $totali['domande'] > 0 ? $totali['euro'] / $totali['domande'] : 0.0;
// 9T · medie e proiezione: per il mese in corso al ritmo dei giorni passati (oggi compreso), per gli altri il mese intero
$inCorso = $mese === Assistente::mese();
$giorniMese = (int) gmdate('t', (int) strtotime($mese . '-01 00:00:00 UTC'));
$mediaGiorno = $totali['domande'] / max(1, $giorni);
$proDomande = $inCorso ? (int) round($mediaGiorno * $giorniMese) : $totali['domande'];
$proEuro = $inCorso ? $totali['euro'] / max(1, $giorni) * $giorniMese : $totali['euro'];
$maxGiorno = max(1, max($serie));
$fmt1 = fn(float $x) => number_format($x, 1, ',', '.'); ?>
<div class="stack stack--lg">
  <div class="stack stack--sm">
    <h1>Concierge AI.</h1>
    <p class="muted">Consumo e spesa del Concierge AI: le domande degli ospiti per cliente e per struttura, con il costo stimato dai token e dal listino salvato nelle
      <a href="<?= b() ?>/admin/impostazioni#assistente">Impostazioni</a>. La demo di Casa Checco ha una riga a sé.</p>
    <?php if (!$configurato): ?><p class="note note--err" role="status">Manca la chiave API di Anthropic: i clienti Plus e Portfolio non vedono il bottone nella guida. Inseriscila nelle Impostazioni.</p><?php endif; ?>
    <?php if ($tetto > 0 && $percTetto >= 80 && $mese === Assistente::mese()): ?><p class="note note--err" role="status">Questo mese siete al <?= $percTetto ?>% del tetto di spesa (<?= $eur($tetto) ?>): al 100% l'assistente si ferma per tutti fino al primo del mese.</p><?php endif; ?>
  </div>

  <form method="get" action="<?= b() ?>/admin/assistente" class="row" style="gap:8px;align-items:center;flex-wrap:wrap">
    <label for="mese" class="small">Mese</label>
    <select id="mese" name="mese" onchange="this.form.submit()"><?php foreach ($mesi as $m): ?><option value="<?= Support::e($m) ?>" <?= $m === $mese ? 'selected' : '' ?>><?= Support::e($nomeMese($m)) ?></option><?php endforeach; ?></select>
    <noscript><button class="btn btn--ghost btn--sm">Vai</button></noscript>
    <a class="btn btn--ghost btn--sm" href="<?= b() ?>/admin/assistente/csv?mese=<?= Support::e($mese) ?>"><?= Icon::svg('doc', 15) ?>Esporta CSV</a>
  </form>

  <div class="cifre cifre--4">
    <div class="cifra cifra--mare"><span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg('message', 17) ?></span>Domande · <?= Support::e($nomeMese($mese)) ?></span>
      <b class="cifra__valore"><?= $n($totali['domande']) ?></b>
      <span class="cifra__nota">media <?= $fmt1($mediaGiorno) ?> al giorno · <?= $percRisposte ?>% con risposta · <?= $n($totali['accese']) ?> guide con il Concierge acceso</span></div>
    <div class="cifra cifra--ocra"><span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg('euro', 17) ?></span>Costo stimato del mese</span>
      <b class="cifra__valore"><?= $eur($totali['euro']) ?></b>
      <span class="cifra__nota">di cui cache <?= $eur($cache) ?><?= $tetto > 0 ? ' · tetto ' . $eur($tetto) . ' (' . $percTetto . '%)' : ' · nessun tetto' ?></span>
      <?php if ($reale !== null): ?>
        <span class="cifra__nota" data-costo-reale><?php if ($reale['errore'] === ''): ?><b>Costo vero da Anthropic: <?= $eur((float) $reale['euro']) ?></b> (<?= number_format((float) $reale['usd'], 2, ',', '.') ?> $<?= trim((string) ($config['workspace'] ?? '')) !== '' ? ', solo il workspace' : ', tutta l\'organizzazione' ?>; <?= $inCorso ? 'aggiornato ogni 30 minuti' : 'mese chiuso' ?>)
          <?php else: ?>Il costo vero non si legge: <?= Support::e($reale['errore']) ?>. Controlla la Admin key nelle <a href="<?= b() ?>/admin/impostazioni#assistente">Impostazioni</a>.<?php endif; ?></span>
      <?php endif; ?></div>
    <div class="cifra cifra--pino"><span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg('chart', 17) ?></span><?= $inCorso ? 'Proiezione a fine mese' : 'Totale del mese chiuso' ?></span>
      <b class="cifra__valore"><?= $eur($proEuro) ?></b>
      <span class="cifra__nota"><?= $inCorso ? 'circa ' . $n($proDomande) . ' domande in ' . $giorniMese . ' giorni, al ritmo dei primi ' . $giorni : $n($proDomande) . ' domande in ' . $giorniMese . ' giorni' ?></span></div>
    <div class="cifra cifra--carta"><span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg('check', 17) ?></span>Costo medio a domanda</span>
      <b class="cifra__valore"><?= number_format($medio, 4, ',', '.') ?> €</b>
      <span class="cifra__nota">modello <?= Support::e(Assistente::modello()) ?> · token: <?= $n($totali['token_in']) ?> in, <?= $n($totali['token_out']) ?> out</span></div>
  </div>

  <section class="stack" aria-labelledby="giorni-titolo">
    <h2 id="giorni-titolo" style="font-size:20px">Domande per giorno · ultimi 30 giorni</h2>
    <figure class="giorni">
      <div class="giorni__grafico" aria-hidden="true">
        <span class="giorni__scala"><?= $n($maxGiorno) ?></span>
        <?php foreach ($serie as $g => $v): ?><span class="giorni__col" data-v="<?= Support::e($giornoBreve($g) . ': ' . $v . ($v === 1 ? ' domanda' : ' domande')) ?>"><i class="giorni__barra" style="height:<?= $v > 0 ? max(2, (int) round($v * 100 / $maxGiorno)) : 0 ?>%"></i></span><?php endforeach; ?>
      </div>
      <div class="giorni__asse small muted" aria-hidden="true"><span><?= Support::e($giornoBreve((string) array_key_first($serie))) ?></span><span>oggi</span></div>
      <figcaption class="small muted">In 30 giorni <?= $n(array_sum($serie)) ?> domande delle guide (prove escluse), al massimo <?= $n(max($serie)) ?> in un giorno. Passa sopra una barra per il numero del giorno.</figcaption>
    </figure>
    <details class="giorni__tabella"><summary class="small">I numeri giorno per giorno</summary>
      <div class="tablewrap"><table class="data"><caption class="sr-only">Domande per giorno negli ultimi 30 giorni</caption>
        <thead><tr><th scope="col">Giorno</th><th scope="col">Domande</th></tr></thead>
        <tbody><?php foreach (array_reverse($serie, true) as $g => $v): ?><tr><td><?= Support::e($giornoBreve($g) . ' ' . substr($g, 0, 4)) ?></td><td><?= $n($v) ?></td></tr><?php endforeach; ?></tbody>
      </table></div>
    </details>
  </section>

  <section class="stack" aria-labelledby="per-cliente">
    <h2 id="per-cliente" style="font-size:20px">Per cliente · <?= Support::e($nomeMese($mese)) ?></h2>
    <?php if (!$righe && !$demo['acceso'] && $demo['domande'] === 0): ?><p class="note note--quiet">Nessuna domanda in questo mese.</p><?php else: ?>
    <div class="tablewrap"><table class="data">
      <caption class="sr-only">Domande e costi stimati per cliente e per struttura, <?= Support::e($nomeMese($mese)) ?></caption>
      <thead><tr><th scope="col">Cliente</th><th scope="col">Piano</th><th scope="col">Domande</th><th scope="col">Senza risposta</th><th scope="col">Token</th><th scope="col">Costo stimato</th><th scope="col">Per struttura</th></tr></thead>
      <tbody>
      <?php foreach ($righe as $x): $aid = (int) $x['account_id']; ?>
        <tr><td><a href="<?= b() ?>/admin/cliente/<?= $aid ?>"><?= Support::e($x['nome'] ?: $x['email']) ?></a> <span class="small"><?= MHW\Codici::link($aid) ?></span><br><span class="small muted"><?= Support::e($x['email']) ?></span></td>
          <td><?= Support::e($x['piano'] ?: '—') ?></td><td><?= $n($x['domande']) ?></td><td><?= $n($x['senza']) ?></td>
          <td><?= $n($x['token_in'] + $x['token_out']) ?> <span class="small muted"><?= $n($x['token_out']) ?> out</span></td>
          <td><b><?= $eur($x['euro']) ?></b></td>
          <td><?php if (!$x['strutture'] && $x['spente'] > 0): ?><span class="badge badge--paper">Spento dall'host</span><?php endif; ?>
            <ul class="uso-strutture">
            <?php foreach ($x['strutture'] as $st): ?>
              <li><span><?= Support::e($st['nome'] ?: 'Struttura ' . $st['id']) ?> <span class="small muted"><?= $n($st['domande']) ?><?= $x['limite'] > 0 ? '/' . $n($x['limite']) : '' ?></span></span>
                <?php if ($x['limite'] > 0 && $st['percento'] >= 100): ?><span class="badge badge--alert">100% · fermo</span>
                <?php elseif ($x['limite'] > 0 && $st['percento'] >= 80): ?><span class="badge badge--ochre"><?= (int) $st['percento'] ?>% · avvisato</span>
                <?php elseif ($x['limite'] > 0): ?><span class="badge badge--pine"><?= (int) $st['percento'] ?>%</span><?php endif; ?></li>
            <?php endforeach; ?>
            </ul>
            <?php if ($x['limite'] !== $limite): ?><span class="small muted">limite personale <?= $n($x['limite']) ?></span><?php endif; ?>
            <?php if ($x['errori'] > 0): ?> <span class="small muted"><?= $n($x['errori']) ?> errori</span><?php endif; ?></td></tr>
      <?php endforeach; ?>
      <?php if ($demo['acceso'] || $demo['domande'] > 0): ?>
        <tr class="riga-demo"><td><b>Demo Casa Checco</b><br><span class="small muted">guida vetrina della home<?= $demo['guide'] > 1 ? ' e altre ' . ($demo['guide'] - 1) . ' guide di esempio' : '' ?> · <?= $demo['acceso'] ? 'Concierge acceso' : 'Concierge spento' ?> (<a href="<?= b() ?>/admin/impostazioni#assistente">Impostazioni</a>)</span></td>
          <td>—</td><td><?= $n($demo['domande']) ?></td><td><?= $n($demo['senza']) ?></td>
          <td><?= $n($demo['token_in'] + $demo['token_out']) ?> <span class="small muted"><?= $n($demo['token_out']) ?> out</span></td>
          <td><b><?= $eur($demo['euro']) ?></b></td>
          <td><span class="small muted">limite suo <?= $n($demo['limite']) ?> al mese</span>
            <?php if ($demo['limite'] > 0 && $demo['percento'] >= 100): ?><span class="badge badge--alert">100% · ferma fino al mese dopo</span>
            <?php elseif ($demo['limite'] > 0): ?><span class="badge <?= $demo['percento'] >= 80 ? 'badge--ochre' : 'badge--pine' ?>"><?= (int) $demo['percento'] ?>%</span><?php endif; ?>
            <?php if ($demo['errori'] > 0): ?> <span class="small muted"><?= $n($demo['errori']) ?> errori</span><?php endif; ?></td></tr>
      <?php endif; ?>
        <tr style="font-weight:600"><td>Totale<?= $totali['domande'] > array_sum(array_column($righe, 'domande')) + $demo['domande'] ? ' <span class="small muted" style="font-weight:400">(con le prove dalle Impostazioni)</span>' : '' ?></td><td></td><td><?= $n($totali['domande']) ?></td><td><?= $n($totali['senza']) ?></td><td><?= $n($totali['token_in'] + $totali['token_out']) ?></td><td><?= $eur($totali['euro']) ?></td><td></td></tr>
      </tbody>
    </table></div>
    <?php endif; ?>
    <p class="small muted" style="margin:0">Stima calcolata con i prezzi in Impostazioni. Il costo vero è nella Claude Console → Usage / Cost del workspace MyHouse Welcome.</p>
    <p class="small muted" style="margin:0">Costo stimato = token in ingresso × prezzo + token in uscita × prezzo (la cache dei prompt costa 1,25× in scrittura e 0,1× in lettura). Il limite è per struttura: di serie <?= $n($limite) ?> domande al mese ciascuna; quello personale di un cliente è nella sua scheda.</p>
  </section>

  <section class="stack" aria-labelledby="senza-titolo">
    <h2 id="senza-titolo" style="font-size:20px">Le domande senza risposta più frequenti · <?= Support::e($nomeMese($mese)) ?></h2>
    <p class="small muted" style="margin:0">Anonime e raggruppate: solo il testo, senza sapere chi le ha scritte. Dicono cosa manca più spesso nelle guide (ogni host vede le sue nel pannello).</p>
    <?php if (!$frequenti): ?><p class="note note--quiet">Nessuna domanda senza risposta in questo mese.</p><?php else: ?>
    <div class="tablewrap"><table class="data">
      <caption class="sr-only">Le <?= count($frequenti) ?> domande senza risposta più frequenti</caption>
      <thead><tr><th scope="col">Domanda</th><th scope="col">Volte</th><th scope="col">Lingue</th><th scope="col">Guide</th></tr></thead>
      <tbody>
      <?php foreach ($frequenti as $f): ?>
        <tr><td><?= Support::e($f['domanda']) ?></td><td><?= $n($f['n']) ?></td><td><?= Support::e(strtoupper(implode(', ', $f['lingue']))) ?></td><td><?= $n($f['guide']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
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
