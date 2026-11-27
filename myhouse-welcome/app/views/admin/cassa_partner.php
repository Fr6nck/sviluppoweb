<?php
/* 9O · Cassa → «Commissioni partner» (mockup partner.html, blocco 2). Le commissioni sono un costo, non un incasso: stanno
   qui, a parte, e non toccano i totali della Cassa. Per partner il periodo, i clienti, l'imponibile e la commissione da
   liquidare, con «Segna come pagata» (data, importo, riferimento del bonifico, numero della fattura del partner). CSV.
   Riceve $dl (ProgrammaPartner::daLiquidare), $numeri, $anno, $liquidazioni. */
use function MHW\b;
use MHW\{Support, Csrf};
$title = 'Commissioni partner'; ?>
<div class="stack stack--lg" style="max-width:1080px">
  <div class="spread spread--mid">
    <div class="stack stack--sm">
      <p class="small" style="margin:0"><a href="<?= b() ?>/admin/cassa">← Cassa</a></p>
      <h1>Commissioni partner.</h1>
      <p class="muted" style="max-width:760px;margin:0">Un costo, non un incasso: le commissioni stanno in questa scheda e non toccano i totali della Cassa. Si liquidano con un bonifico su fattura del partner.</p>
    </div>
    <a class="btn btn--ghost btn--sm" href="<?= b() ?>/admin/cassa/partner?formato=csv">Esporta CSV</a>
  </div>
  <div class="cifre cifre--4" aria-label="In breve">
    <div class="cifra cifra--ocra"><span class="cifra__testa">Da liquidare</span><b class="cifra__valore"><?= Support::e(Support::money((int) $numeri['da_liquidare'])) ?></b><span class="cifra__nota"><?= (int) $numeri['partner_da_liquidare'] ?> partner</span></div>
    <div class="cifra cifra--pino"><span class="cifra__testa">Liquidate nel <?= (int) $anno ?></span><b class="cifra__valore"><?= Support::e(Support::money((int) $numeri['liquidate'])) ?></b><span class="cifra__nota">bonifici fatti</span></div>
    <div class="cifra cifra--mare"><span class="cifra__testa">Clienti dai partner</span><b class="cifra__valore"><?= (int) $numeri['clienti'] ?></b><span class="cifra__nota">con l'abbonamento attivo</span></div>
    <div class="cifra cifra--carta"><span class="cifra__testa">Imponibile portato</span><b class="cifra__valore"><?= Support::e(Support::money((int) $numeri['imponibile'])) ?></b><span class="cifra__nota">nel <?= (int) $anno ?></span></div>
  </div>
  <?php if (!$dl): ?><p class="note note--quiet">Ancora nessun partner: si crea da <a href="<?= b() ?>/admin/partner">Vendite → Partner</a>.</p><?php else: ?>
  <div class="tablewrap"><table class="data">
    <thead><tr><th>Partner</th><th>Periodo</th><th>Clienti</th><th>Imponibile</th><th>Commissione</th><th></th></tr></thead>
    <tbody><?php foreach ($dl as $x): $p = $x['partner']; ?>
      <tr><td><a href="<?= b() ?>/admin/partner/p/<?= (int) $p['id'] ?>"><?= Support::e($p['ragione_sociale'] ?: $p['referente']) ?></a> · <?= (int) $p['commissione_pct'] ?>%<br><span class="tiny muted"><code><?= Support::e($p['codice']) ?></code> · <?= Support::e($p['periodicita']) ?><?= $p['iban'] !== '' ? ' · IBAN ' . Support::e($p['iban']) : '' ?></span></td>
        <td class="small"><?= $x['da'] !== '' ? Support::e(Support::date($x['da'])) . ' – ' . Support::e(Support::date($x['a'])) : '—' ?><?= $x['scaduto'] ? '<br><span class="badge badge--alert">periodo finito</span>' : '' ?></td>
        <td><?= (int) $x['clienti'] ?></td>
        <td class="small"><?= $x['righe'] ? Support::e(Support::money((int) $x['imponibile'])) : '—' ?></td>
        <td><b><?= $x['righe'] ? Support::e(Support::money((int) $x['totale'])) : '—' ?></b></td>
        <td class="small"><?php if ($x['totale'] > 0): ?>
          <details><summary class="btn btn--sm">Segna come pagata</summary>
            <form method="post" action="<?= b() ?>/admin/cassa/partner/<?= (int) $p['id'] ?>/pagata" class="stack" style="gap:8px;margin-top:8px;min-width:240px"><?= Csrf::field() ?>
              <div class="field" style="margin:0"><label for="lq-d-<?= (int) $p['id'] ?>" class="small">Data del bonifico</label><input id="lq-d-<?= (int) $p['id'] ?>" name="data" type="date" required max="<?= gmdate('Y-m-d') ?>" value="<?= gmdate('Y-m-d') ?>"></div>
              <div class="field" style="margin:0"><label for="lq-i-<?= (int) $p['id'] ?>" class="small">Importo (€)</label><input type="text" id="lq-i-<?= (int) $p['id'] ?>" name="importo" inputmode="decimal" required value="<?= number_format($x['totale'] / 100, 2, ',', '') ?>"></div>
              <div class="field" style="margin:0"><label for="lq-r-<?= (int) $p['id'] ?>" class="small">Riferimento del bonifico</label><input type="text" id="lq-r-<?= (int) $p['id'] ?>" name="riferimento" required maxlength="120"></div>
              <div class="field" style="margin:0"><label for="lq-f-<?= (int) $p['id'] ?>" class="small">Numero della fattura del partner</label><input type="text" id="lq-f-<?= (int) $p['id'] ?>" name="fattura" required maxlength="60"></div>
              <button class="btn btn--sm">Registra la liquidazione</button></form></details>
          <?php else: ?><span class="muted">niente da liquidare</span><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
  <?php if ($liquidazioni): ?>
  <section class="stack" style="gap:10px" aria-labelledby="cp-liq">
    <h2 id="cp-liq" style="font-size:22px;margin:0">Liquidazioni fatte</h2>
    <div class="tablewrap"><table class="data"><thead><tr><th>Bonifico</th><th>Partner</th><th>Periodo</th><th>Importo</th><th>Riferimento</th><th>Fattura</th></tr></thead><tbody>
      <?php foreach ($liquidazioni as $l): ?><tr><td class="small"><?= Support::e(Support::date($l['data_bonifico'])) ?></td><td><code><?= Support::e($l['codice']) ?></code> <?= Support::e($l['ragione_sociale']) ?></td>
        <td class="small"><?= Support::e(Support::date($l['periodo_da'])) ?> – <?= Support::e(Support::date($l['periodo_a'])) ?></td><td><b><?= Support::e(Support::money((int) $l['importo_cents'])) ?></b></td>
        <td class="small"><?= Support::e($l['riferimento']) ?></td><td class="small"><?= Support::e($l['fattura_partner']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </section>
  <?php endif; ?>
</div>
