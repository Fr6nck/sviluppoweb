<?php
/* Amministrazione → Prospetti: come va la piattaforma. In alto i numeri di adesso
   (ricavo ricorrente, rinnovi attesi), poi gli ultimi 12 mesi, i piani, la conversione,
   gli inviti, i codici sconto e il costo delle traduzioni. IVA esclusa; gli abbonamenti
   dello staff e quelli di esempio non sono incassi. */
use function MHW\b;
use MHW\{Support, Icon};
$title = 'Prospetti';
$a = $adesso;
$soldi = fn(int $c) => Support::e(Support::money($c));
$nomeMese = fn(string $m) => ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'][(int) substr($m, 5, 2) - 1] . ' ' . substr($m, 2, 2);
$massimo = max(1, ...array_column($mesi, 'incasso'));
$tot = fn(string $k) => array_sum(array_column($mesi, $k)); ?>
<div class="stack stack--lg">
  <div class="spread spread--mid">
    <div class="stack stack--sm">
      <h1>Prospetti.</h1>
      <p class="muted">Importi IVA esclusa. Gli abbonamenti attivati dallo staff e quelli di esempio non sono incassi.</p>
    </div>
    <a class="btn btn--ghost btn--sm" href="<?= b() ?>/admin/prospetti?formato=csv">Esporta i 12 mesi in CSV</a>
  </div>

  <div class="cifre cifre--4">
    <?php foreach ([['cifra--pino', 'euro', 'Ricavo annuo ricorrente', $soldi($a['arr']), 'abbonamenti Stripe che si rinnovano da soli'],
                    ['cifra--ocra', 'ban', 'A rischio', $soldi($a['a_rischio']), 'rinnovo disattivato: non tornano se non li riattivano'],
                    ['cifra--mare', 'calendar', 'Rinnovi entro 30 giorni', (int) $a['rinnovi']['30'][0] . ' · ' . $soldi($a['rinnovi']['30'][1]), 'con sconti inviti e cambi programmati'],
                    ['cifra--carta', 'calendar', 'Rinnovi entro 90 giorni', (int) $a['rinnovi']['90'][0] . ' · ' . $soldi($a['rinnovi']['90'][1]), $a['manuali'] . ' abbonamenti dello staff a parte']] as [$t, $ico, $et, $val, $nota]): ?>
      <div class="cifra <?= $t ?>"><span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg($ico, 17) ?></span><?= Support::e($et) ?></span>
        <b class="cifra__valore"><?= $val ?></b><span class="cifra__nota"><?= Support::e($nota) ?></span></div>
    <?php endforeach; ?>
  </div>

  <section class="panel stack" aria-labelledby="mesi-titolo">
    <div class="spread spread--mid"><h2 id="mesi-titolo" style="font-size:22px;margin:0">Incasso, ultimi 12 mesi</h2>
      <span class="small muted">in totale <b><?= $soldi($tot('incasso')) ?></b></span></div>
    <div class="barre" role="img" aria-label="Incasso mese per mese: il dettaglio è nella tabella qui sotto">
      <?php foreach ($mesi as $m): ?>
        <div class="barre__col" title="<?= Support::e($nomeMese($m['mese']) . ': ' . Support::money($m['incasso'])) ?>">
          <span class="barre__barra" style="height:<?= round($m['incasso'] / $massimo * 100, 1) ?>%"></span>
          <span class="barre__mese"><?= Support::e($nomeMese($m['mese'])) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="tablewrap"><table class="data">
      <thead><tr><th scope="col">Mese</th><th scope="col">Registrati</th><th scope="col">Nuovi abbonati</th><th scope="col">Nuovi</th><th scope="col">Cambi di piano</th>
        <th scope="col">Rinnovi</th><th scope="col">Incasso</th><th scope="col">Persi</th></tr></thead>
      <tbody>
      <?php foreach (array_reverse($mesi) as $m): ?>
        <tr><td><?= Support::e($nomeMese($m['mese'])) ?></td><td><?= (int) $m['registrati'] ?></td><td><?= (int) $m['nuovi'] ?></td>
          <td><?= $soldi($m['incasso_nuovi']) ?></td><td><?= $soldi($m['incasso_cambi']) ?></td>
          <td><?= $soldi($m['incasso_rinnovi']) ?> <span class="tiny muted">(<?= (int) $m['rinnovi'] ?>)</span></td>
          <td><b><?= $soldi($m['incasso']) ?></b></td><td><?= (int) $m['persi'] ?></td></tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><th scope="row">Totale</th><td><?= (int) $tot('registrati') ?></td><td><?= (int) $tot('nuovi') ?></td><td><?= $soldi($tot('incasso_nuovi')) ?></td>
        <td><?= $soldi($tot('incasso_cambi')) ?></td><td><?= $soldi($tot('incasso_rinnovi')) ?></td><td><b><?= $soldi($tot('incasso')) ?></b></td><td><?= (int) $tot('persi') ?></td></tr></tfoot>
    </table></div>
    <p class="tiny muted" style="margin:0">«Nuovi» e «Cambi di piano» dagli ordini pagati; «Rinnovi» dalle fatture di rinnovo arrivate col webhook. «Persi»: abbonamenti finiti nel mese senza che ne arrivasse un altro.</p>
  </section>

  <div class="grid grid-2" style="align-items:start">
    <section class="panel stack" aria-labelledby="piani-titolo">
      <h2 id="piani-titolo" style="font-size:22px;margin:0">Piani</h2>
      <?php if (!$a['piani']): ?><p class="small muted">Nessun abbonamento Stripe attivo.</p><?php else: ?>
      <div class="tablewrap"><table class="data">
        <thead><tr><th scope="col">Piano</th><th scope="col">Clienti</th><th scope="col">Strutture</th><th scope="col">Ricavo annuo</th></tr></thead>
        <tbody><?php foreach ($a['piani'] as $p): ?>
          <tr><td><?= Support::e($p['piano']) ?><?= $p['disdetti'] ? '<br><span class="tiny muted">' . (int) $p['disdetti'] . ' con rinnovo disattivato</span>' : '' ?></td>
            <td><?= (int) $p['clienti'] ?></td><td><?= (int) $p['strutture'] ?></td><td><?= $soldi($p['arr']) ?></td></tr>
        <?php endforeach; ?></tbody></table></div>
      <?php endif; ?>
      <a class="small" href="<?= b() ?>/admin/pacchetti">Prezzi e versioni dei piani</a>
    </section>

    <section class="panel stack" aria-labelledby="conv-titolo">
      <h2 id="conv-titolo" style="font-size:22px;margin:0">Dalla registrazione all'abbonamento</h2>
      <?php $base = max(1, (int) $a['conversione'][0][1]); ?>
      <ul class="conversione">
        <?php foreach ($a['conversione'] as [$et, $n]): ?>
          <li><span><?= Support::e($et) ?></span><b><?= (int) $n ?> <span class="tiny muted"><?= round($n / $base * 100) ?>%</span></b>
            <span class="conversione__barra" aria-hidden="true"><span style="width:<?= round($n / $base * 100, 1) ?>%"></span></span></li>
        <?php endforeach; ?>
      </ul>
      <a class="small" href="<?= b() ?>/admin">Il percorso dalla landing, nel quadro</a>
    </section>
  </div>

  <div class="grid grid-2" style="align-items:start">
    <?php if ($a['inviti']): $iv = $a['inviti']; ?>
    <section class="panel stack" aria-labelledby="inviti-titolo">
      <div class="spread spread--mid"><h2 id="inviti-titolo" style="font-size:22px;margin:0">Porta un amico</h2>
        <span class="badge badge--<?= $iv['attivi'] ? 'pine' : 'ochre' ?>"><?= $iv['attivi'] ? 'Acceso' : 'Spento' ?></span></div>
      <p class="small" style="margin:0">Invitati <b><?= array_sum(array_map('intval', $iv['stati'])) ?></b> ·
        in attesa <b><?= (int) ($iv['stati']['registrato'] ?? 0) ?></b> · hanno pagato <b><?= (int) ($iv['stati']['valido'] ?? 0) + (int) ($iv['stati']['usato'] ?? 0) + (int) ($iv['stati']['oltre'] ?? 0) ?></b> ·
        annullati <b><?= (int) ($iv['stati']['annullato'] ?? 0) ?></b></p>
      <p class="small" style="margin:0">Sconti già dati sui rinnovi di chi invita: <b><?= $soldi($iv['concesso']) ?></b> · sul primo anno degli amici: <b><?= $soldi($iv['amico']) ?></b></p>
      <?php if ($iv['primi']): ?>
        <div class="tablewrap"><table class="data">
          <thead><tr><th scope="col">Chi invita</th><th scope="col">Invitati</th><th scope="col">Hanno pagato</th><th scope="col">Sconto ora</th></tr></thead>
          <tbody><?php foreach ($iv['primi'] as $x): ?>
            <tr><td><a href="<?= b() ?>/admin/cliente/<?= (int) $x['account_id'] ?>"><?= Support::e($x['cliente'] ?: $x['email']) ?></a></td>
              <td><?= (int) $x['invitati'] ?></td><td><?= (int) $x['paganti'] ?></td><td><?= (int) $x['percento'] ?>%</td></tr>
          <?php endforeach; ?></tbody></table></div>
      <?php endif; ?>
      <a class="small" href="<?= b() ?>/admin/inviti">Tutti gli inviti</a>
    </section>
    <?php endif; ?>

    <section class="panel stack" aria-labelledby="costi-titolo">
      <h2 id="costi-titolo" style="font-size:22px;margin:0">Codici sconto e traduzioni</h2>
      <?php if ($a['sconti']): ?>
        <div class="tablewrap"><table class="data">
          <thead><tr><th scope="col">Codice</th><th scope="col">Usi</th><th scope="col">Sconto dato</th></tr></thead>
          <tbody><?php foreach ($a['sconti'] as $c): ?>
            <tr><td><a href="<?= b() ?>/admin/sconti/<?= (int) $c['id'] ?>"><code><?= Support::e($c['code']) ?></code></a></td><td><?= (int) $c['usi'] ?></td><td><?= $soldi((int) $c['cents']) ?></td></tr>
          <?php endforeach; ?></tbody></table></div>
      <?php else: ?><p class="small muted" style="margin:0">Nessun codice sconto usato finora.</p><?php endif; ?>
      <?php if ($a['traduzioni']): $t = $a['traduzioni']; ?>
        <p class="small" style="margin:0">Traduzioni suggerite, questo mese: <b><?= number_format($t['caratteri'], 0, ',', '.') ?></b> caratteri,
          costo stimato <b><?= number_format($t['mese'], 2, ',', '.') ?> €</b>. Ultimi 12 mesi: <b><?= number_format($t['anno'], 2, ',', '.') ?> €</b>.</p>
        <a class="small" href="<?= b() ?>/admin/traduzioni">Consumi, tetti e omaggi delle traduzioni</a>
      <?php endif; ?>
    </section>
  </div>
</div>
