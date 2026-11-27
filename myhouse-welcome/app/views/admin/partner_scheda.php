<?php
/* 9O · La scheda del partner (mockup partner.html, blocco 1). Nuovo ($p null): «Approva e crea il partner» dalla richiesta,
   con il codice proposto. Esistente: i dati modificabili (commissione, sconto, rinnovi, IBAN, P.IVA, periodicità), i clienti
   portati con imponibile, commissione e stato, le liquidazioni, Sospendi / Rimanda i materiali e lo storico.
   Riceve $p, $v (valori del modulo), $richiesta; per l'esistente $clienti, $liquidazioni, $storico, $riep. */
use function MHW\b;
use MHW\{Support, Csrf, ProgrammaPartner};
$nuovo = $p === null;
$title = $nuovo ? 'Nuovo partner' : 'Partner ' . $p['codice'];
$statiC = ['maturata' => ['Da liquidare', 'ochre'], 'liquidata' => ['Liquidata', 'pine'], 'stornata' => ['Stornata', 'paper']];
$pill = ['attivo' => 'pine', 'sospeso' => 'ochre', 'chiuso' => 'paper']; ?>
<div class="stack stack--lg" style="max-width:1080px">
  <p class="small" style="margin:0"><a href="<?= b() ?>/admin/partner">← Partner</a></p>
  <?php if ($nuovo): ?>
    <div class="stack stack--sm">
      <h1>Approva e crea il partner.</h1>
      <p class="muted" style="margin:0"><?= $richiesta ? 'Dalla richiesta di ' . Support::e($richiesta['nome']) . ' del ' . Support::e(Support::date($richiesta['created_at'])) . '. ' : '' ?>Alla creazione il partner riceve l'email di benvenuto con il codice, il link e i materiali (da commerciale@).</p>
    </div>
  <?php else: ?>
    <div class="spread" style="align-items:flex-start;gap:16px;flex-wrap:wrap">
      <div class="stack stack--sm">
        <h1><?= Support::e($p['ragione_sociale'] ?: $p['referente']) ?>.</h1>
        <p class="muted" style="margin:0"><?= Support::e(implode(' · ', array_filter([$p['referente'], $p['email'], $p['telefono'], $p['zona'], 'partner dal ' . Support::date($p['created_at'])]))) ?></p>
      </div>
      <span class="badge badge--<?= $pill[$p['stato']] ?? 'paper' ?>"><?= Support::e(ProgrammaPartner::STATI[$p['stato']] ?? $p['stato']) ?></span>
    </div>
    <div class="cifre cifre--4" aria-label="In breve">
      <div class="cifra cifra--pino"><span class="cifra__testa">Codice partner</span><b class="cifra__valore"><?= Support::e($p['codice']) ?></b><span class="cifra__nota"><a href="<?= Support::e(ProgrammaPartner::link($p)) ?>" target="_blank" rel="noopener">il link con il codice</a></span></div>
      <div class="cifra cifra--mare"><span class="cifra__testa">Clienti attivi</span><b class="cifra__valore"><?= (int) $riep['attivi'] ?></b><span class="cifra__nota"><?= count($clienti) ?> portati in tutto</span></div>
      <div class="cifra cifra--ocra"><span class="cifra__testa">Da liquidare</span><b class="cifra__valore"><?= Support::e(Support::money((int) $riep['da_ricevere'])) ?></b><span class="cifra__nota"><a href="<?= b() ?>/admin/cassa/partner">Cassa → Commissioni partner</a></span></div>
      <div class="cifra cifra--carta"><span class="cifra__testa">Liquidato nel <?= gmdate('Y') ?></span><b class="cifra__valore"><?= Support::e(Support::money((int) $riep['ricevuto_anno'])) ?></b><span class="cifra__nota"><?= (int) $riep['rinnovi_90'] ?> rinnovi entro 90 giorni</span></div>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= b() . ($nuovo ? '/admin/partner/crea' : '/admin/partner/p/' . (int) $p['id']) ?>" class="panel stack" style="gap:14px"><?= Csrf::field() ?>
    <?php if ($nuovo && $richiesta): ?><input type="hidden" name="richiesta" value="<?= (int) $richiesta['id'] ?>"><?php endif; ?>
    <div class="grid grid-2">
      <div class="field" style="margin:0"><label for="pp-rs">Ragione sociale</label><input type="text" id="pp-rs" name="ragione_sociale" maxlength="160" value="<?= Support::e((string) $v['ragione_sociale']) ?>"></div>
      <div class="field" style="margin:0"><label for="pp-ref">Referente</label><input type="text" id="pp-ref" name="referente" maxlength="120" value="<?= Support::e((string) $v['referente']) ?>"></div>
      <div class="field" style="margin:0"><label for="pp-em">Email</label><input id="pp-em" name="email" type="email" required value="<?= Support::e((string) $v['email']) ?>"></div>
      <div class="field" style="margin:0"><label for="pp-tel">Telefono</label><input type="text" id="pp-tel" name="telefono" maxlength="40" value="<?= Support::e((string) $v['telefono']) ?>"></div>
      <div class="field" style="margin:0"><label for="pp-zona">Zona</label><input type="text" id="pp-zona" name="zona" maxlength="255" value="<?= Support::e((string) $v['zona']) ?>"></div>
      <?php if ($nuovo): ?>
        <div class="field" style="margin:0"><label for="pp-cod">Codice partner</label><input type="text" id="pp-cod" name="codice" maxlength="24" pattern="PM-[A-Za-z0-9]{2,20}" value="<?= Support::e((string) $v['codice']) ?>"><p class="help">Proposto dal nome: si può cambiare (PM- e lettere o cifre).</p></div>
      <?php endif; ?>
    </div>
    <div class="grid grid-4">
      <div class="field" style="margin:0"><label for="pp-comm">Commissione (%)</label><input id="pp-comm" name="commissione_pct" type="number" min="1" max="50" required value="<?= (int) $v['commissione_pct'] ?>"></div>
      <div class="field" style="margin:0"><label for="pp-sc">Sconto per i suoi clienti, 1° anno (%)</label><input id="pp-sc" name="sconto_clienti_pct" type="number" min="0" max="50" required value="<?= (int) $v['sconto_clienti_pct'] ?>"></div>
      <div class="field" style="margin:0"><label for="pp-per">Liquidazione</label><select id="pp-per" name="periodicita"><?php foreach (ProgrammaPartner::PERIODI as $k => $et): ?><option value="<?= $k ?>"<?= $v['periodicita'] === $k ? ' selected' : '' ?>><?= $et ?>, su fattura</option><?php endforeach; ?></select></div>
      <div class="field" style="margin:0"><label for="pp-lin">Lingua delle email</label><select id="pp-lin" name="lingua"><option value="it"<?= $v['lingua'] !== 'en' ? ' selected' : '' ?>>Italiano</option><option value="en"<?= $v['lingua'] === 'en' ? ' selected' : '' ?>>Inglese</option></select></div>
    </div>
    <div class="grid grid-2">
      <div class="field" style="margin:0"><label for="pp-iban">IBAN per le liquidazioni</label><input type="text" id="pp-iban" name="iban" maxlength="40" value="<?= Support::e((string) $v['iban']) ?>"></div>
      <div class="field" style="margin:0"><label for="pp-piva">P.IVA del partner</label><input type="text" id="pp-piva" name="piva" inputmode="numeric" maxlength="13" value="<?= Support::e((string) $v['piva']) ?>"></div>
    </div>
    <input type="hidden" name="rinnovi" value="0">
    <label class="check"><input type="checkbox" name="rinnovi" value="1"<?= (int) $v['rinnovi'] === 1 ? ' checked' : '' ?>> <span><b>Commissione sui rinnovi</b> · spenta, da quel giorno maturano solo le commissioni sui nuovi clienti</span></label>
    <div class="field" style="margin:0"><label for="pp-note">Note interne</label><textarea id="pp-note" name="note" rows="2" maxlength="2000"><?= Support::e((string) ($v['note'] ?? '')) ?></textarea></div>
    <p class="note" style="margin:0">La commissione si calcola sull'<b>imponibile incassato</b> (dopo gli sconti, senza IVA), matura solo a pagamento riuscito e si storna in caso di rimborso. Ogni modifica va nel Registro azioni.</p>
    <div class="actions"><button class="btn"><?= $nuovo ? 'Approva e crea il partner' : 'Salva' ?></button></div>
  </form>

  <?php if (!$nuovo): ?>
    <div class="row" style="gap:8px;flex-wrap:wrap">
      <?php foreach ($p['stato'] === 'attivo' ? ['sospeso' => 'Sospendi il partner'] : ['attivo' => 'Riattiva il partner'] as $az => $et): ?>
        <form method="post" action="<?= b() ?>/admin/partner/p/<?= (int) $p['id'] ?>" style="margin:0"<?= $az === 'sospeso' ? ' onsubmit="return confirm(\'Sospendere il partner? Il codice smette di valere e non maturano commissioni.\')"' : '' ?>><?= Csrf::field() ?><button class="btn btn--ghost btn--sm" name="azione" value="<?= $az ?>"><?= $et ?></button></form>
      <?php endforeach; ?>
      <form method="post" action="<?= b() ?>/admin/partner/p/<?= (int) $p['id'] ?>" style="margin:0"><?= Csrf::field() ?><button class="btn btn--ghost btn--sm" name="azione" value="materiali">Rimanda il codice e i materiali</button></form>
      <a class="btn btn--ghost btn--sm" href="#storico">Storico</a>
    </div>

    <section class="stack" style="gap:10px" aria-labelledby="pp-clienti">
      <h2 id="pp-clienti" style="font-size:22px;margin:0">Clienti portati · <?= count($clienti) ?></h2>
      <?php if (!$clienti): ?><p class="note note--quiet">Ancora nessun cliente con il codice <?= Support::e($p['codice']) ?>.</p><?php else: ?>
      <div class="tablewrap"><table class="data">
        <thead><tr><th>Struttura</th><th>Piano</th><th>Pagamento</th><th>Imponibile</th><th>Commissione</th><th>Stato</th></tr></thead>
        <tbody><?php foreach ($clienti as $c): $u = $c['ultima']; [$stEt, $stCol] = $u ? ($statiC[$u['stato']] ?? [$u['stato'], 'paper']) : ['Nessun pagamento', 'paper']; ?>
          <tr><td><a href="<?= b() ?>/admin/cliente/<?= (int) $c['account_id'] ?>"><?= Support::e(trim($c['struttura'] . ($c['citta'] !== '' ? ' · ' . $c['citta'] : '')) ?: $c['codice_cliente']) ?></a>
                <br><span class="tiny muted"><code class="codice-mw"><?= Support::e($c['codice_cliente']) ?></code><?= $c['dal'] !== '' ? ' · codice ' . Support::e($p['codice']) . ' usato il ' . Support::e(Support::date($c['dal'])) : '' ?></span></td>
            <td class="small"><?= Support::e($c['piano'] ?: '—') ?></td>
            <td class="small"><?= $u ? ($u['tipo'] === 'rinnovo' ? 'Rinnovo' : '1° anno') . ' · ' . Support::e(Support::date($u['created_at'])) : '—' ?></td>
            <td class="small"><?= Support::e(Support::money((int) $c['imponibile'])) ?></td>
            <td><b><?= Support::e(Support::money((int) $c['commissione'])) ?></b></td>
            <td><span class="badge badge--<?= $stCol ?>"><?= Support::e($stEt) ?></span></td></tr>
        <?php endforeach; ?></tbody></table></div>
      <?php endif; ?>
    </section>

    <?php if ($liquidazioni): ?>
    <section class="stack" style="gap:10px" aria-labelledby="pp-liq">
      <h2 id="pp-liq" style="font-size:22px;margin:0">Liquidazioni</h2>
      <div class="tablewrap"><table class="data"><thead><tr><th>Bonifico</th><th>Periodo</th><th>Importo</th><th>Riferimento</th><th>Fattura del partner</th></tr></thead><tbody>
        <?php foreach ($liquidazioni as $l): ?><tr><td class="small"><?= Support::e(Support::date($l['data_bonifico'])) ?></td><td class="small"><?= Support::e(Support::date($l['periodo_da'])) ?> – <?= Support::e(Support::date($l['periodo_a'])) ?></td>
          <td><b><?= Support::e(Support::money((int) $l['importo_cents'])) ?></b></td><td class="small"><?= Support::e($l['riferimento']) ?></td><td class="small"><?= Support::e($l['fattura_partner']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    </section>
    <?php endif; ?>

    <details id="storico" class="panel"><summary><b>Storico</b> <span class="small muted">· le azioni del Registro su questo partner</span></summary>
      <ul class="stack small" style="gap:4px;margin:10px 0 0;padding-left:18px">
        <?php foreach ($storico as $s): ?><li><?= Support::e(Support::date($s['created_at'])) ?> · <code><?= Support::e($s['action']) ?></code> <span class="muted"><?= Support::e(mb_substr((string) $s['meta'], 0, 220)) ?></span></li><?php endforeach; ?>
        <?php if (!$storico): ?><li class="muted">Niente ancora.</li><?php endif; ?>
      </ul></details>
  <?php endif; ?>
</div>
