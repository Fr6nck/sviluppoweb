<?php
/* 9N · Amministrazione → Cassa: gli incassi dell'anno mese per mese, per il commercialista.
   Riceve $anno, $mese (aperto, o ''), $cassa (Gestione::cassa), $prima (totale dell'anno prima), $pagamenti, $nomi.
   Stessa fonte di Prospetti: ordini pagati (Stripe e bonifici), rinnovi dalle fatture di Stripe, rimborsi. */
use function MHW\b;
use MHW\{Support, Gestione};
$title = 'Cassa';
$soldi = fn(?int $c) => $c === null ? '—' : Support::e(Support::money($c));
$nomeMese = fn(string $m) => ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'][(int) substr($m, 5, 2) - 1];
$t = $cassa['totale'];
$celle = function (array $m) use ($soldi) { ?>
  <td><?= (int) $m['nuovi'] ?></td><td><?= (int) $m['cambi'] ?></td><td><?= (int) $m['rinnovi'] ?></td>
  <td><?= $soldi($m['stripe']) ?></td><td><?= $soldi($m['bonifico']) ?></td><td><?= $soldi($m['imponibile']) ?></td>
  <td><?= $soldi($m['iva']) ?><?= $m['senza_iva'] ? ' <span class="tiny muted" title="Pagamenti di cui Stripe non ha detto l\'IVA">*</span>' : '' ?></td>
  <td><?= $soldi($m['totale']) ?></td><td><?= $m['rimborsi'] ? '−' . $soldi($m['rimborsi']) : $soldi(0) ?></td><td><b><?= $soldi($m['netto']) ?></b></td>
<?php }; ?>
<div class="stack stack--lg">
  <div class="spread spread--mid">
    <div class="stack stack--sm">
      <h1>Cassa <?= (int) $anno ?>.</h1>
      <p class="muted" style="max-width:760px">Quello che è entrato davvero, mese per mese: pagamenti con Stripe (primi abbonamenti, cambi di piano, rinnovi) e bonifici segnati
        all'attivazione manuale. I rimborsi si tolgono nel mese in cui sono stati fatti. Gli stessi conti di <a href="<?= b() ?>/admin/prospetti">Prospetti</a>; clienti di esempio e di prova esclusi.</p>
    </div>
    <div class="row" style="gap:8px;flex-wrap:wrap">
      <a class="btn btn--ghost btn--sm" href="<?= b() ?>/admin/cassa?anno=<?= (int) $anno - 1 ?>">← <?= (int) $anno - 1 ?></a>
      <?php if ($anno < (int) gmdate('Y')): ?><a class="btn btn--ghost btn--sm" href="<?= b() ?>/admin/cassa?anno=<?= (int) $anno + 1 ?>"><?= (int) $anno + 1 ?> →</a><?php endif; ?>
      <?php if (MHW\ProgrammaPartner::disponibili()): ?><a class="btn btn--ghost btn--sm" href="<?= b() ?>/admin/cassa/partner">Commissioni partner →</a><?php endif; ?>
      <a class="btn btn--ghost btn--sm" href="<?= b() ?>/admin/cassa?anno=<?= (int) $anno ?>&amp;formato=csv">Esporta CSV (mesi)</a>
      <a class="btn btn--ghost btn--sm" href="<?= b() ?>/admin/cassa?anno=<?= (int) $anno ?>&amp;formato=csv&amp;pagamenti=1">Esporta CSV (pagamenti dell'anno)</a>
    </div>
  </div>

  <div class="cifre cifre--4">
    <div class="cifra cifra--ocra"><span class="cifra__testa">Imponibile <?= (int) $anno ?></span><b class="cifra__valore"><?= $soldi($t['imponibile']) ?></b><span class="cifra__nota">IVA <?= $soldi($t['iva']) ?> · totale <?= $soldi($t['totale']) ?></span></div>
    <div class="cifra cifra--pino"><span class="cifra__testa">Netto incassato</span><b class="cifra__valore"><?= $soldi($t['netto']) ?></b><span class="cifra__nota">totale meno <?= $soldi($t['rimborsi']) ?> di rimborsi</span></div>
    <div class="cifra cifra--mare"><span class="cifra__testa">Pagamenti</span><b class="cifra__valore"><?= (int) ($t['nuovi'] + $t['cambi'] + $t['rinnovi']) ?></b><span class="cifra__nota"><?= (int) $t['nuovi'] ?> nuovi · <?= (int) $t['cambi'] ?> cambi · <?= (int) $t['rinnovi'] ?> rinnovi</span></div>
    <div class="cifra cifra--carta"><span class="cifra__testa">Anno <?= (int) $anno - 1 ?></span><b class="cifra__valore"><?= $soldi($prima['imponibile']) ?></b><span class="cifra__nota">imponibile · netto <?= $soldi($prima['netto']) ?></span></div>
  </div>

  <div class="tablewrap"><table class="data cassa">
    <caption class="sr-only">Incassi del <?= (int) $anno ?> per mese. Apri un mese per vedere i pagamenti.</caption>
    <thead><tr><th scope="col">Mese</th><th scope="col">Nuovi</th><th scope="col">Cambi</th><th scope="col">Rinnovi</th><th scope="col">Stripe</th><th scope="col">Bonifici</th>
      <th scope="col">Imponibile</th><th scope="col">IVA</th><th scope="col">Totale</th><th scope="col">Rimborsi</th><th scope="col">Netto</th></tr></thead>
    <tbody>
    <?php foreach ($cassa['mesi'] as $m): if ($m['mese'] > gmdate('Y-m')) continue; $aperto = $m['mese'] === $mese; ?>
      <tr<?= $aperto ? ' class="cassa__aperto"' : '' ?>><td><a href="<?= b() ?>/admin/cassa?anno=<?= (int) $anno ?><?= $aperto ? '' : '&amp;mese=' . $m['mese'] . '#pagamenti' ?>"<?= $aperto ? ' aria-expanded="true"' : '' ?>><?= Support::e(ucfirst($nomeMese($m['mese']))) ?></a></td>
        <?php $celle($m); ?></tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td>Totale <?= (int) $anno ?></td><?php $celle($t); ?></tr>
      <tr class="muted"><td>Anno <?= (int) $anno - 1 ?></td><?php $celle($prima); ?></tr></tfoot>
  </table></div>
  <p class="tiny muted" style="margin:0">Stripe e Bonifici: imponibile. L'IVA dei pagamenti Stripe è quella calcolata da Stripe (con l'IVA automatica accesa);
    quella dei bonifici è scritta all'attivazione. <?= $t['senza_iva'] ? '* ' . (int) $t['senza_iva'] . ' pagamenti senza IVA letta da Stripe: controllali nella dashboard di Stripe.' : '' ?>
    Le fatture elettroniche si fanno in Adamo: il numero comparirà qui quando Adamo sarà collegato.</p>

  <?php if ($mese !== ''): ?>
  <section class="stack" style="gap:10px" id="pagamenti" aria-labelledby="pag-titolo">
    <div class="spread spread--mid"><h2 id="pag-titolo" style="font-size:22px">Pagamenti di <?= Support::e($nomeMese($mese)) ?> <?= (int) $anno ?></h2>
      <a class="btn btn--ghost btn--sm" href="<?= b() ?>/admin/cassa?anno=<?= (int) $anno ?>&amp;mese=<?= Support::e($mese) ?>&amp;formato=csv&amp;pagamenti=1">Esporta CSV</a></div>
    <?php if (!$pagamenti): ?><p class="muted small">Nessun pagamento in questo mese.</p><?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Data</th><th>Codice</th><th>Cliente</th><th>Tipo</th><th>Piano</th><th>Sconti</th><th>Imponibile</th><th>IVA</th><th>Totale</th><th>Fattura</th></tr></thead>
      <tbody><?php foreach ($pagamenti as $p): ?>
        <tr><td class="small" style="white-space:nowrap"><?= Support::e(Support::date($p['data'])) ?></td>
          <td><code class="codice-mw"><?= Support::e($p['codice'] ?: '—') ?></code></td>
          <td><?php if ($p['account_id']): ?><a href="<?= b() ?>/admin/cliente/<?= (int) $p['account_id'] ?>"><?= Support::e($p['cliente'] ?: $p['email']) ?></a><?php else: ?>—<?php endif; ?></td>
          <td class="small"><?= Support::e($nomi[$p['tipo']]) ?><br><span class="tiny muted"><?= Support::e(Gestione::VIE_INCASSO[$p['via']] ?? $p['via']) ?><?= $p['riferimento'] !== '' ? ' · ' . Support::e($p['riferimento']) : '' ?></span></td>
          <td class="small"><?= Support::e($p['piano'] ?: '—') ?></td>
          <td class="small"><?= Support::e($p['sconti'] ?: '—') ?></td>
          <td style="white-space:nowrap"><?= $p['tipo'] === 'rimborso' ? '—' : $soldi($p['imponibile']) ?></td>
          <td style="white-space:nowrap"><?= $p['tipo'] === 'rimborso' ? '—' : $soldi($p['iva']) ?></td>
          <td style="white-space:nowrap"><b><?= $soldi($p['totale']) ?></b></td>
          <td class="small"><?= Support::e($p['fattura'] ?: '—') ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</div>
