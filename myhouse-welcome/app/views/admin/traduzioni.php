<?php
/* Amministrazione → Vendite → Traduzioni: consumi e costi stimati delle traduzioni suggerite
   (Amazon Translate), gli ultimi 12 mesi con gli esiti (ok / cache / limite / errore), chi traduce
   di più nel mese e nell'anno con il tetto annuale (9G), la previsione. I costi sono stime: fa fede la fattura di AWS. */
use function MHW\b;
use MHW\{Support, Csrf, Icon, Traduttore};
$title = 'Traduzioni';
$n = fn(int $x) => number_format($x, 0, ',', '.');
$usd = fn(float $x) => number_format($x, 2, ',', '.') . ' $';
$eur = fn(float $x) => number_format(Traduttore::inEuro($x), 2, ',', '.') . ' €';
$costo = Traduttore::costoUsd($usati, $gratis);
$perc = $tetti['sito'] > 0 ? (int) floor($usati / $tetti['sito'] * 100) : 0;
$nomeMese = fn(string $m) => ['01' => 'gennaio', '02' => 'febbraio', '03' => 'marzo', '04' => 'aprile', '05' => 'maggio', '06' => 'giugno', '07' => 'luglio',
                              '08' => 'agosto', '09' => 'settembre', '10' => 'ottobre', '11' => 'novembre', '12' => 'dicembre'][substr($m, 5, 2)] . ' ' . substr($m, 0, 4);
$oggi = Support::now(); $tra30 = gmdate('Y-m-d\TH:i:s\Z', strtotime('+30 days')); ?>
<div class="stack stack--lg">
  <div class="stack stack--sm">
    <h1>Traduzioni.</h1>
    <p class="muted">Le traduzioni suggerite di Plus e Portfolio, con Amazon Translate. I costi sono stime fatte con il prezzo e il cambio delle
      <a href="<?= b() ?>/admin/impostazioni#traduzioni">Impostazioni</a>: fa fede la fattura di AWS.</p>
    <?php if (!$configurato): ?><p class="note note--err" role="status">Amazon Translate non è configurato: i clienti possono accendere le traduzioni suggerite, ma non riceverne. Inserisci le chiavi nelle Impostazioni.</p><?php endif; ?>
    <?php if ($perc >= 80): ?><p class="note note--err" role="status">Questo mese siete al <?= $perc ?>% del tetto del sito: al tetto le richieste si fermano fino al primo del mese.</p><?php endif; ?>
  </div>

  <div class="cifre">
    <?php foreach ([['cifra--mare', 'globe', 'Caratteri di ' . $nomeMese($mese), $n($usati), $perc . '% del tetto del sito (' . $n($tetti['sito']) . ')'],
                    ['cifra--ocra', 'euro', 'Costo stimato del mese', $eur($costo), $usd($costo) . ($gratis ? ' · con il piano gratuito AWS' : ' · senza piano gratuito')],
                    ['cifra--pino', 'people', 'Tetto per account', $n($tetti['account']), 'caratteri all\'anno' . ($cache ? ' · memoria: ' . $n($cache['righe']) . ' frasi, ' . $n($cache['riusi']) . ' riusi' : '')]] as [$tono, $ico, $et, $val, $nota]): ?>
      <div class="cifra <?= $tono ?>"><span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg($ico, 17) ?></span><?= Support::e($et) ?></span>
        <b class="cifra__valore"><?= Support::e($val) ?></b><span class="cifra__nota"><?= Support::e($nota) ?></span></div>
    <?php endforeach; ?>
  </div>

  <section class="panel stack" aria-labelledby="prev-titolo">
    <h2 id="prev-titolo" style="font-size:20px;margin:0">Previsione</h2>
    <p class="small" style="margin:0"><b><?= $n($clienti) ?></b> client<?= $clienti === 1 ? 'e' : 'i' ?> con le traduzioni suggerite nel piano.
      Nelle lingue che hanno acceso mancano <b><?= $n($daTradurre) ?></b> caratteri di traduzione.</p>
    <p class="small" style="margin:0">Se li facessero tradurre tutti in un mese:
      <b><?= $eur(Traduttore::costoUsd($daTradurre, false)) ?></b> senza piano gratuito,
      <b><?= $eur(max(0, Traduttore::costoUsd($usati + $daTradurre, true) - Traduttore::costoUsd($usati, true))) ?></b> con il piano gratuito AWS
      <?= $gratis ? '(attivo fino al ' . Support::e(Support::date($config['free_tier_until'] . 'T12:00:00Z')) . ')' : '(non attivo)' ?>.</p>
    <p class="tiny muted" style="margin:0">Il piano gratuito AWS: 2 milioni di caratteri al mese per 12 mesi dalla prima traduzione. È una stima: molti testi i clienti li traducono a mano.</p>
  </section>

  <section class="stack" aria-labelledby="mesi-titolo">
    <h2 id="mesi-titolo" style="font-size:20px">Ultimi 12 mesi</h2>
    <?php if (!$mesi): ?><p class="note note--quiet">Ancora nessuna traduzione.</p><?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Mese</th><th>Caratteri (ok)</th><th>Chiamate ok</th><th>Dalla memoria</th><th>Fermate dal tetto</th><th>Errori</th><th>Costo stimato</th></tr></thead>
      <tbody>
      <?php foreach ($mesi as $m): $c = Traduttore::costoUsd((int) $m['chars'], Traduttore::gratuitoAttivo($m['month'] . '-01')); ?>
        <tr><td><?= Support::e($nomeMese($m['month'])) ?></td><td><?= $n((int) $m['chars']) ?></td><td><?= $n((int) $m['chiamate']) ?></td>
          <td><?= $n((int) ($m['cache'] ?? 0)) ?> <span class="muted small">(<?= $n((int) ($m['chars_cache'] ?? 0)) ?> car. risparmiati)</span></td>
          <td><?= $n((int) $m['limiti']) ?></td><td><?= $n((int) $m['errori']) ?></td><td><?= $eur($c) ?> <span class="muted small"><?= $usd($c) ?></span></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </section>

  <section class="stack" aria-labelledby="primi-titolo">
    <h2 id="primi-titolo" style="font-size:20px">Chi traduce di più, questo mese</h2>
    <?php if (!$primi): ?><p class="note note--quiet">Nessuno, per ora.</p><?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Cliente</th><th>Caratteri</th><th>Del suo tetto</th></tr></thead>
      <tbody>
      <?php foreach ($primi as $x): $aid = (int) $x['account_id']; ?>
        <tr><td><?php if ($aid): ?><a href="<?= b() ?>/admin/cliente/<?= $aid ?>"><?= Support::e($x['name'] ?: $x['email']) ?></a> <span class="small"><?= MHW\Codici::link($aid) ?></span> <span class="small muted"><?= Support::e((string) $x['email']) ?></span>
                <?php else: ?><span class="muted">Prove dalle Impostazioni</span><?php endif; ?></td>
          <td><?= $n((int) $x['chars']) ?></td><td><?= $aid && $tetti['account'] ? (int) floor($x['chars'] / $tetti['account'] * 100) . '%' : '—' ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </section>

  <section class="stack" id="anno" aria-labelledby="anno-titolo">
    <h2 id="anno-titolo" style="font-size:20px">Chi ha usato di più negli ultimi 12 mesi</h2>
    <p class="small muted" style="margin:0">9G · Le traduzioni suggerite sono comprese in Plus e Portfolio entro un tetto annuale di caratteri per account (di serie <?= $n($tetti['account']) ?>), che parte dall'attivazione dell'abbonamento e si azzera al rinnovo. Il tetto personale e «Aggiungi 250.000» stanno nella scheda del cliente.</p>
    <?php if (!$anno): ?><p class="note note--quiet">Nessuno, per ora.</p><?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Cliente</th><th>Caratteri (12 mesi)</th><th>Anno in corso</th><th>Si azzera il</th></tr></thead>
      <tbody>
      <?php foreach ($anno as $x): $ct = $x['contatore']; ?>
        <tr><td><a href="<?= b() ?>/admin/cliente/<?= (int) $x['account_id'] ?>"><?= Support::e($x['name'] ?: $x['email']) ?></a> <span class="small"><?= MHW\Codici::link((int) $x['account_id']) ?></span> <span class="small muted"><?= Support::e((string) $x['email']) ?></span></td>
          <td><?= $n((int) $x['chars']) ?></td>
          <td><?= $n((int) $ct['usati']) ?> su <?= $n((int) $ct['tetto']) ?> <span class="badge badge--<?= $ct['finito'] ? 'paper' : ($ct['basso'] ? 'ochre' : 'pine') ?>"><?= (int) $ct['percento'] ?>%</span></td>
          <td><?= Support::e(Support::date($ct['azzera'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </section>

  <?php if ($errori): ?>
  <section class="stack" aria-labelledby="err-titolo">
    <h2 id="err-titolo" style="font-size:20px">Ultime chiamate non riuscite</h2>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Quando</th><th>Cliente</th><th>Esito</th><th>Dettaglio</th></tr></thead>
      <tbody>
      <?php foreach ($errori as $e): ?>
        <tr><td><?= Support::e(Support::date($e['created_at'])) ?></td><td><?= Support::e((string) ($e['email'] ?? '—')) ?></td>
          <td><?= $e['outcome'] === 'limite' ? 'Fermata dal tetto' : 'Errore' ?></td><td class="small"><?= Support::e($e['error']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
  <?php endif; ?>
</div>
