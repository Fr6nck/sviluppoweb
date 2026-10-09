<?php
/* Amministrazione → Vendite per piano: che piani si comprano, mese per mese.
   Grafici disegnati dal server (niente librerie): colonne impilate per mese, barre orizzontali
   per gli abbonamenti attivi e l'incasso del periodo; ogni grafico ha la sua tabella.
   Colori: --viz-1/2/3 (Essential, Plus, Portfolio), validati per il chiaro e lo scuro.
   Riceve: $v (Gestione::vendite), $mesi (3, 6 o 12). */
use function MHW\b;
use MHW\{Support, Icon, Gestione};
$title = 'Vendite per piano';
$piani = Gestione::PIANI_VENDITE;
$colore = ['essential' => 'var(--viz-1)', 'plus' => 'var(--viz-2)', 'portfolio' => 'var(--viz-3)'];
$soldi = fn(int $c) => Support::e(Support::money($c));
$nomeMese = fn(string $m) => ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'][(int) substr($m, 5, 2) - 1] . ' ' . substr($m, 2, 2);
$t = $v['totali'];
$nuoviTot = array_sum(array_intersect_key($t['nuovi'], $piani));
$incassoTot = array_sum(array_intersect_key($t['incasso'], $piani));
$attiviTot = array_sum(array_map(fn($a) => $a['stripe'] + $a['staff'], $v['attivi']));
$percento = fn(int $n, int $tot) => $tot > 0 ? (int) round($n / $tot * 100) : 0;
// Il più scelto nel periodo (solo se c'è almeno un acquisto).
$primo = $nuoviTot > 0 ? array_search(max(array_intersect_key($t['nuovi'], $piani)), array_intersect_key($t['nuovi'], $piani), true) : null;
$massimoMese = max(1, ...array_map(fn($m) => array_sum(array_intersect_key($m['nuovi'], $piani)), $v['mesi']));
$legenda = function () use ($piani, $colore): void { ?>
  <ul class="viz-legenda" aria-label="Legenda">
    <?php foreach ($piani as $k => $nome): ?><li><span class="viz-legenda__segno" style="background:<?= $colore[$k] ?>"></span><?= Support::e($nome) ?></li><?php endforeach; ?>
  </ul>
<?php };
$barre = function (array $valori, callable $etichetta, string $titolo) use ($piani, $colore): void {
    $max = max(1, ...array_values($valori)); ?>
  <div class="viz-barre" role="img" aria-label="<?= Support::e($titolo) ?>: il dettaglio è nella tabella">
    <?php foreach ($piani as $k => $nome): $val = (int) ($valori[$k] ?? 0); ?>
      <div class="viz-barre__riga" data-tip="<?= Support::e($nome . ': ' . $etichetta($k, $val)) ?>">
        <span class="viz-barre__nome"><?= Support::e($nome) ?></span>
        <span class="viz-barre__pista"><span class="viz-barre__barra" style="width:<?= $val > 0 ? max(1.5, round($val / $max * 100, 1)) : 0 ?>%;background:<?= $colore[$k] ?>"></span></span>
        <b class="viz-barre__valore"><?= Support::e($etichetta($k, $val)) ?></b>
      </div>
    <?php endforeach; ?>
  </div>
<?php }; ?>
<div class="stack stack--lg">
  <div class="spread spread--mid">
    <div class="stack stack--sm">
      <h1>Vendite per piano.</h1>
      <p class="muted">Che piani comprano i clienti, mese per mese. Ordini pagati su Stripe, importi IVA esclusa; clienti di esempio esclusi.</p>
    </div>
    <a class="btn btn--ghost btn--sm" href="<?= b() ?>/admin/vendite?mesi=<?= $mesi ?>&amp;formato=csv">Esporta in CSV</a>
  </div>

  <nav class="viz-filtri" aria-label="Periodo">
    <?php foreach ([3 => 'Ultimi 3 mesi', 6 => 'Ultimi 6 mesi', 12 => 'Ultimi 12 mesi'] as $n => $et): ?>
      <a class="viz-filtri__voce<?= $n === $mesi ? ' is-attivo' : '' ?>" href="<?= b() ?>/admin/vendite?mesi=<?= $n ?>"<?= $n === $mesi ? ' aria-current="page"' : '' ?>><?= $et ?></a>
    <?php endforeach; ?>
  </nav>

  <div class="cifre cifre--4">
    <div class="cifra cifra--carta"><span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg('people', 17) ?></span>Nuovi abbonamenti</span>
      <b class="cifra__valore"><?= (int) $nuoviTot ?></b><span class="cifra__nota">negli ultimi <?= $mesi ?> mesi<?= $primo ? ' · il più scelto: ' . Support::e($piani[$primo]) . ' (' . $percento($t['nuovi'][$primo], $nuoviTot) . '%)' : '' ?></span></div>
    <div class="cifra cifra--carta"><span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg('euro', 17) ?></span>Incasso del periodo</span>
      <b class="cifra__valore"><?= $soldi($incassoTot) ?></b><span class="cifra__nota">nuovi abbonamenti e cambi di piano, senza i rinnovi</span></div>
    <div class="cifra cifra--carta"><span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg('layers', 17) ?></span>Abbonamenti attivi oggi</span>
      <b class="cifra__valore"><?= (int) $attiviTot ?></b><span class="cifra__nota"><?= implode(' · ', array_map(fn($k) => $piani[$k] . ' ' . $percento($v['attivi'][$k]['stripe'] + $v['attivi'][$k]['staff'], $attiviTot) . '%', array_keys($piani))) ?></span></div>
    <div class="cifra cifra--carta"><span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg('key', 17) ?></span>Varianti camera</span>
      <b class="cifra__valore"><?= (int) $v['varianti'] ?></b><span class="cifra__nota">attive oggi, in tutte le guide</span></div>
  </div>

  <section class="panel stack" aria-labelledby="mesi-v">
    <div class="spread spread--mid"><h2 id="mesi-v" style="font-size:22px;margin:0">Nuovi abbonamenti, mese per mese</h2><?php $legenda(); ?></div>
    <div class="viz-colonne" role="img" aria-label="Nuovi abbonamenti per mese e per piano: il dettaglio è nella tabella qui sotto" style="--viz-mesi:<?= count($v['mesi']) ?>">
      <span class="viz-colonne__max small muted"><?= (int) $massimoMese ?></span>
      <?php foreach ($v['mesi'] as $m): $totM = array_sum(array_intersect_key($m['nuovi'], $piani));
            $tip = $nomeMese($m['mese']) . ': ' . ($totM ? implode(', ', array_filter(array_map(fn($k) => $m['nuovi'][$k] ? $piani[$k] . ' ' . $m['nuovi'][$k] : '', array_keys($piani)))) : 'nessun acquisto'); ?>
        <div class="viz-colonne__col" data-tip="<?= Support::e($tip) ?>" tabindex="0" aria-label="<?= Support::e($tip) ?>">
          <span class="viz-colonne__pila" style="height:<?= round($totM / $massimoMese * 100, 1) ?>%">
            <?php foreach (array_reverse(array_keys($piani)) as $k): if (!$m['nuovi'][$k]) continue; ?>
              <span class="viz-colonne__pezzo" style="flex-grow:<?= (int) $m['nuovi'][$k] ?>;background:<?= $colore[$k] ?>"></span>
            <?php endforeach; ?>
          </span>
          <span class="viz-colonne__mese"><?= Support::e($nomeMese($m['mese'])) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <details>
      <summary class="linkbtn" style="cursor:pointer">Vedi come tabella</summary>
      <div class="tablewrap" style="margin-top:10px"><table class="data">
        <thead><tr><th scope="col">Mese</th><?php foreach ($piani as $nome): ?><th scope="col"><?= Support::e($nome) ?></th><?php endforeach; ?><th scope="col">Cambi di piano</th><th scope="col">Incasso</th></tr></thead>
        <tbody><?php foreach (array_reverse($v['mesi']) as $m): ?>
          <tr><td><?= Support::e($nomeMese($m['mese'])) ?></td>
            <?php foreach (array_keys($piani) as $k): ?><td><?= (int) $m['nuovi'][$k] ?></td><?php endforeach; ?>
            <td><?= (int) array_sum($m['cambi']) ?></td><td><?= $soldi(array_sum($m['incasso'])) ?></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
    </details>
  </section>

  <div class="grid grid-2" style="align-items:start">
    <section class="panel stack" aria-labelledby="attivi-v">
      <h2 id="attivi-v" style="font-size:22px;margin:0">Abbonamenti attivi oggi</h2>
      <?php $barre(array_map(fn($a) => $a['stripe'] + $a['staff'], $v['attivi']), function (string $k, int $n) use ($v): string {
          $a = $v['attivi'][$k];
          return $n . ($k === 'portfolio' && $n ? ' · ' . $a['strutture'] . ' strutture' : '') . ($a['staff'] ? ' (' . $a['staff'] . ' dello staff)' : '');
      }, 'Abbonamenti attivi per piano'); ?>
      <p class="tiny muted" style="margin:0">Con gli abbonamenti attivati dallo staff; senza quelli dimostrativi.</p>
    </section>
    <section class="panel stack" aria-labelledby="incasso-v">
      <h2 id="incasso-v" style="font-size:22px;margin:0">Incasso per piano, ultimi <?= $mesi ?> mesi</h2>
      <?php $barre($t['incasso'], fn(string $k, int $c) => Support::money($c) . ' · ' . (int) $t['nuovi'][$k] . ' nuovi' . ($t['cambi'][$k] ? ', ' . (int) $t['cambi'][$k] . ' cambi' : ''), 'Incasso per piano'); ?>
      <p class="tiny muted" style="margin:0">«Cambi»: chi è passato a quel piano pagando la differenza.</p>
    </section>
  </div>

  <section class="panel stack" aria-labelledby="ultimi-v">
    <h2 id="ultimi-v" style="font-size:22px;margin:0">Ultimi acquisti</h2>
    <?php if (!$v['ultimi']): ?>
      <p class="small muted">Ancora nessun acquisto pagato su Stripe.</p>
    <?php else: ?>
      <div class="tablewrap"><table class="data">
        <thead><tr><th scope="col">Data</th><th scope="col">Cliente</th><th scope="col">Piano</th><th scope="col">Tipo</th><th scope="col">Importo</th></tr></thead>
        <tbody><?php foreach ($v['ultimi'] as $o): $f = str_starts_with((string) $o['code'], 'portfolio') ? 'portfolio' : (string) $o['code']; ?>
          <tr><td><?= Support::e(Support::date($o['created_at'])) ?></td>
            <td><a href="<?= b() ?>/admin/cliente/<?= (int) $o['account_id'] ?>"><?= Support::e($o['cliente'] ?: $o['email']) ?></a></td>
            <td><span class="viz-legenda__segno" style="background:<?= $colore[$f] ?? 'var(--line-strong)' ?>"></span> <?= Support::e($o['piano']) ?><?= (int) $o['quantity'] > 1 ? ' · ' . (int) $o['quantity'] . ' strutture' : '' ?></td>
            <td><?= $o['kind'] === 'change' ? 'Cambio di piano' : 'Nuovo' ?></td><td><?= $soldi((int) $o['amount_cents']) ?></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
    <?php endif; ?>
  </section>
</div>
<div class="viz-tip" role="tooltip" hidden></div>
<script>
/* Il dettaglio al passaggio (o al focus): un'etichetta che segue il punto, letta da data-tip. */
(function () {
  var tip = document.querySelector('.viz-tip'); if (!tip) return;
  function mostra(el, x, y) { tip.textContent = el.getAttribute('data-tip'); tip.hidden = false;
    var w = tip.offsetWidth; tip.style.left = Math.max(8, Math.min(window.innerWidth - w - 8, x - w / 2)) + 'px'; tip.style.top = (y - tip.offsetHeight - 12) + 'px'; }
  document.querySelectorAll('[data-tip]').forEach(function (el) {
    el.addEventListener('pointermove', function (e) { mostra(el, e.clientX, e.clientY); });
    el.addEventListener('pointerleave', function () { tip.hidden = true; });
    el.addEventListener('focus', function () { var r = el.getBoundingClientRect(); mostra(el, r.left + r.width / 2, r.top); });
    el.addEventListener('blur', function () { tip.hidden = true; });
  });
})();
</script>
