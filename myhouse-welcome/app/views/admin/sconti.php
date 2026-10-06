<?php
/* Codici sconto del primo anno (6E): i numeri, l'elenco e il modulo per crearne uno.
   L'anteprima sotto il modulo si calcola nel browser dai prezzi del listino. */
use function MHW\b;
use MHW\{Support, Csrf, Icon, Sconti, Plans};
$title = 'Codici sconto';
$v = $vecchi + ['code' => $proposto, 'kind' => 'percent', 'value' => '', 'valid_from' => Sconti::oggi(), 'valid_until' => '', 'max_uses' => '', 'piani' => 'tutti', 'packages' => [], 'note' => ''];
$pillola = ['attivo' => ['Attivo', 'pine'], 'programmato' => ['Programmato', 'sea'], 'scaduto' => ['Scaduto', 'paper'], 'esaurito' => ['Esaurito', 'ochre'],
            'disattivato' => ['Disattivato', 'paper'], 'da_sincronizzare' => ['Da sincronizzare', 'alert']];
$giorno = fn(string $d) => Support::e(Support::date($d));
$nomiPiani = array_column($piani, 'name', 'code'); ?>
<div class="stack stack--lg">
  <div class="stack stack--sm">
    <h1>Codici sconto.</h1>
    <p class="muted">Valgono solo sul primo anno: dal rinnovo si paga il prezzo pieno. Dopo la creazione si cambiano solo nota, piani e stato;
      per cambiare valore o date, disattiva il codice e creane uno nuovo.</p>
    <?php if (!$stripe): ?><p class="note note--err" role="status">Stripe non è configurato: i codici si salvano ma restano «da sincronizzare» e non si possono usare.</p><?php endif; ?>
  </div>

  <div class="cifre">
    <?php foreach ([['cifra--pino', 'check', 'Codici attivi', (string) $cifre['attivi'], 'utilizzabili oggi'],
                    ['cifra--mare', 'people', 'Utilizzi', (string) $cifre['usi'], 'pagamenti con un codice'],
                    ['cifra--ocra', 'euro', 'Sconto concesso', Support::money($cifre['euro']), 'IVA esclusa']] as [$tono, $ico, $et, $val, $nota]): ?>
      <div class="cifra <?= $tono ?>"><span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg($ico, 17) ?></span><?= $et ?></span>
        <b class="cifra__valore"><?= Support::e($val) ?></b><span class="cifra__nota"><?= Support::e($nota) ?></span></div>
    <?php endforeach; ?>
  </div>

  <?php if (!$righe): ?><p class="note note--quiet">Ancora nessun codice.</p><?php else: ?>
  <div class="tablewrap"><table class="data">
    <thead><tr><th>Codice</th><th>Sconto</th><th>Validità</th><th>Piani</th><th>Utilizzi</th><th>Stato</th><th><span class="sr-only">Azioni</span></th></tr></thead>
    <tbody><?php foreach ($righe as $c): [$st, $tono] = $pillola[$c['stato']]; $link = Support::baseUrl() . '/?codice=' . rawurlencode($c['code']); ?>
      <tr><td><a href="<?= b() ?>/admin/sconti/<?= (int) $c['id'] ?>"><b><?= Support::e($c['code']) ?></b></a><?= $c['note'] !== '' ? '<br><span class="tiny muted">' . Support::e($c['note']) . '</span>' : '' ?></td>
        <td><?= Support::e(Sconti::etichetta($c)) ?></td>
        <td class="small"><?= $giorno($c['valid_from']) ?> → <?= $giorno($c['valid_until']) ?></td>
        <td class="small"><?= Sconti::pacchetti($c) ? Support::e(implode(', ', array_map(fn($k) => $nomiPiani[$k] ?? $k, Sconti::pacchetti($c)))) : 'Tutti' ?></td>
        <td><?= (int) $c['usi'] ?><?= (int) $c['max_uses'] > 0 ? ' / ' . (int) $c['max_uses'] : '' ?></td>
        <td><span class="badge badge--<?= $tono ?>"><?= $st ?></span></td>
        <td><div class="row" style="gap:6px;flex-wrap:nowrap">
          <?php if (in_array($c['stato'], ['attivo', 'programmato'], true)): ?>
            <button type="button" class="btn btn--ghost btn--sm" data-copia="<?= Support::e($link) ?>" data-copiato="Link copiato" hidden>Copia il link</button>
          <?php endif; ?>
          <?php if ($c['stato'] === 'da_sincronizzare'): ?>
            <form method="post" action="<?= b() ?>/admin/sconti/<?= (int) $c['id'] ?>/sincronizza" style="margin:0"><?= Csrf::field() ?><button class="btn btn--sm">Riprova la sincronizzazione</button></form>
          <?php endif; ?>
          <?php if ((int) $c['active']): ?>
            <form method="post" action="<?= b() ?>/admin/sconti/<?= (int) $c['id'] ?>/disattiva" style="margin:0"><?= Csrf::field() ?><button class="linkbtn">Disattiva</button></form>
          <?php endif; ?>
        </div></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>

  <form method="post" action="<?= b() ?>/admin/sconti" class="panel stack" data-sconto-anteprima style="max-width:820px"><?= Csrf::field() ?>
    <h2 style="font-size:22px">Nuovo codice</h2>
    <div class="grid grid-2">
      <div class="field" style="margin:0"><label for="sc-code">Codice</label>
        <div class="row" style="gap:8px;flex-wrap:nowrap"><input id="sc-code" name="code" type="text" required maxlength="24" pattern="[A-Za-z0-9-]{4,24}" style="text-transform:uppercase"
               value="<?= Support::e($v['code']) ?>" aria-describedby="sc-code-aiuto">
          <button type="button" class="btn btn--ghost btn--sm" data-genera hidden>Genera</button></div>
        <p class="help" id="sc-code-aiuto">Da 4 a 24 caratteri: lettere, cifre e trattino.</p></div>
      <fieldset class="field" style="margin:0;border:0;padding:0"><legend class="label">Tipo</legend>
        <div class="scelte scelte--riga">
          <label class="scelta scelta--mini"><input type="radio" name="kind" value="percent" <?= $v['kind'] !== 'amount' ? 'checked' : '' ?>><span>Percentuale</span></label>
          <label class="scelta scelta--mini"><input type="radio" name="kind" value="amount" <?= $v['kind'] === 'amount' ? 'checked' : '' ?>><span>Importo in euro</span></label>
        </div></fieldset>
      <div class="field" style="margin:0"><label for="sc-value">Valore</label>
        <input id="sc-value" name="value" type="text" inputmode="decimal" required value="<?= Support::e((string) $v['value']) ?>" aria-describedby="sc-value-aiuto">
        <p class="help" id="sc-value-aiuto">Percentuale da 1 a 100, oppure euro (per esempio 20 o 15,50).</p></div>
      <div class="field" style="margin:0"><label for="sc-max">Utilizzi massimi</label>
        <input id="sc-max" name="max_uses" type="number" min="1" step="1" value="<?= Support::e((string) $v['max_uses']) ?>" aria-describedby="sc-max-aiuto">
        <p class="help" id="sc-max-aiuto">Vuoto: senza limite.</p></div>
      <div class="field" style="margin:0"><label for="sc-da">Valido dal</label><input id="sc-da" name="valid_from" type="date" required value="<?= Support::e($v['valid_from']) ?>"></div>
      <div class="field" style="margin:0"><label for="sc-a">Valido fino al</label><input id="sc-a" name="valid_until" type="date" required value="<?= Support::e($v['valid_until']) ?>"></div>
    </div>
    <fieldset class="fieldset" style="margin:0"><legend>Piani</legend>
      <div class="scelte scelte--riga">
        <label class="scelta scelta--mini"><input type="radio" name="piani" value="tutti" <?= $v['piani'] !== 'scelti' ? 'checked' : '' ?>><span>Tutti i piani</span></label>
        <label class="scelta scelta--mini"><input type="radio" name="piani" value="scelti" <?= $v['piani'] === 'scelti' ? 'checked' : '' ?>><span>Solo questi:</span></label>
        <?php foreach ($piani as $p): ?>
          <label class="scelta scelta--mini"><input type="checkbox" name="packages[]" value="<?= Support::e($p['code']) ?>" <?= in_array($p['code'], (array) $v['packages'], true) ? 'checked' : '' ?>><span><?= Support::e($p['name']) ?></span></label>
        <?php endforeach; ?>
      </div></fieldset>
    <div class="field" style="margin:0"><label for="sc-note">Nota interna</label>
      <input id="sc-note" name="note" type="text" maxlength="255" value="<?= Support::e($v['note']) ?>" placeholder="Per esempio: Fiera TTG"></div>
    <div class="note note--quiet" aria-live="polite">
      <b style="display:block;margin-bottom:4px">Anteprima</b>
      <ul class="small" style="margin:0;padding-left:18px" data-anteprima-righe>
        <?php foreach ($piani as $p): ?><li data-piano="<?= Support::e($p['code']) ?>" data-prezzo="<?= (int) $p['price_cents'] ?>" data-nome="<?= Support::e($p['name']) ?>"><?= Support::e($p['name']) ?>: <?= Support::e(Support::money((int) $p['price_cents'])) ?></li><?php endforeach; ?>
      </ul>
    </div>
    <div><button class="btn">Crea il codice</button></div>
  </form>
</div>
<script>
/* «Genera» e l'anteprima per piano: «Plus: 117 € → 93,60 € il primo anno, poi 117 €». */
(function () {
  var f = document.querySelector('[data-sconto-anteprima]'); if (!f) return;
  var gen = f.querySelector('[data-genera]'); gen.hidden = false;
  gen.addEventListener('click', function () {
    var a = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789', c = 'MHW-', b = new Uint8Array(6); crypto.getRandomValues(b);
    for (var i = 0; i < 6; i++) c += a[b[i] % a.length];
    f.querySelector('[name=code]').value = c;
  });
  var euro = function (c) { var s = (c / 100).toFixed(c % 100 ? 2 : 0).replace('.', ','); return s + ' €'; };
  var aggiorna = function () {
    var tipo = (f.querySelector('[name=kind]:checked') || {}).value, v = parseFloat((f.querySelector('[name=value]').value || '').replace(',', '.'));
    var tutti = (f.querySelector('[name=piani]:checked') || {}).value !== 'scelti';
    var scelti = Array.prototype.map.call(f.querySelectorAll('[name="packages[]"]:checked'), function (x) { return x.value; });
    f.querySelectorAll('[data-anteprima-righe] li').forEach(function (li) {
      var p = parseInt(li.getAttribute('data-prezzo'), 10), nome = li.getAttribute('data-nome');
      li.hidden = !tutti && scelti.indexOf(li.getAttribute('data-piano')) === -1;
      if (!(v > 0)) { li.textContent = nome + ': ' + euro(p); return; }
      var s = tipo === 'amount' ? Math.round(v * 100) : Math.round(p * v / 100);
      s = Math.max(0, Math.min(s, p - 100));
      li.textContent = nome + ': ' + euro(p) + ' → ' + euro(p - s) + ' il primo anno, poi ' + euro(p);
    });
  };
  f.addEventListener('input', aggiorna); f.addEventListener('change', aggiorna); aggiorna();
})();
</script>
