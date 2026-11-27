<?php
/* Amministrazione → Vendite → Marketing (7F): tabelle semplici, numeri e percentuali, con una barra
   orizzontale in CSS accanto al numero (nessuna libreria). Ogni tabella si scarica in CSV.
   Riceve: $tabelle (Marketing::tabelle), $periodo ('30', '90', '365', 'tutto'). */
use function MHW\b;
use MHW\{Support, Marketing};
$title = 'Marketing';
$ordine = ['percorso', 'motivi', 'tipologie', 'piani', 'regioni', 'citta', 'sezioni', 'lingue', 'visitate', 'inviti', 'omaggi']; ?>
<div class="stack stack--lg marketing">
  <div class="stack stack--sm">
    <h1>Marketing.</h1>
    <p class="muted small">Chi arriva, fin dove arriva e come usa le guide. Fuori i clienti di esempio, i clienti di prova e le guide vetrina.</p>
  </div>
  <nav class="row" style="gap:8px;flex-wrap:wrap" aria-label="Periodo">
    <?php foreach (Marketing::PERIODI as $k => $nome): ?>
      <a class="btn btn--sm<?= $periodo === (string) $k ? '' : ' btn--ghost' ?>" href="<?= b() ?>/admin/marketing?periodo=<?= Support::e((string) $k) ?>"<?= $periodo === (string) $k ? ' aria-current="page"' : '' ?>><?= Support::e($nome) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="marketing__griglia">
    <?php foreach ($ordine as $k): if (!isset($tabelle[$k])) continue; $tab = $tabelle[$k]; $cli = $tab['cliente'] ?? null; ?>
      <section class="panel stack marketing__tabella<?= $k === 'visitate' || $k === 'percorso' ? ' marketing__tabella--larga' : '' ?>" id="mk-<?= $k ?>" style="gap:10px" aria-labelledby="mk-<?= $k ?>-titolo">
        <div class="spread spread--mid" style="gap:10px;flex-wrap:wrap">
          <h2 id="mk-<?= $k ?>-titolo" style="font-size:20px;margin:0"><?= Support::e($tab['titolo']) ?></h2>
          <?php if ($tab['righe']): ?>
            <a class="btn btn--ghost btn--sm" href="<?= b() ?>/admin/marketing?periodo=<?= Support::e($periodo) ?>&amp;formato=csv&amp;tabella=<?= $k ?>">Scarica CSV</a>
          <?php endif; ?>
        </div>
        <p class="small muted" style="margin:0"><?= Support::e($tab['nota']) ?></p>
        <?php if (!$tab['righe']): ?>
          <p class="small">Ancora nessun dato in questo periodo.</p>
        <?php else: ?>
          <div class="tablewrap"><table class="data">
            <thead><tr><?php foreach ($tab['colonne'] as $i => $c): if ($i === $cli) continue; ?><th scope="col"<?= $i > 0 ? ' class="num"' : '' ?>><?= Support::e($c === '' ? 'Voce' : $c) ?></th><?php endforeach; ?>
              <?php if ($cli !== null): ?><th scope="col"><span class="sr-only">Scheda cliente</span></th><?php endif; ?></tr></thead>
            <tbody>
              <?php foreach ($tab['righe'] as $riga): ?>
                <tr><?php foreach ($riga as $i => $v): if ($i === $cli) continue; ?>
                  <?php if ($i === 0): ?><th scope="row"><?= Support::e((string) $v) ?></th>
                  <?php elseif ($i === $tab['barra']): $w = max(0, min(100, round((int) $v / $tab['totale'] * 100, 1))); ?>
                    <td class="num mk-cella"><span class="mk-barra" aria-hidden="true"><span style="width:<?= $w > 0 ? max(2, $w) : 0 ?>%"></span></span><b><?= (int) $v ?></b></td>
                  <?php else: ?><td class="num"><?= Support::e((string) $v) ?></td><?php endif; ?>
                <?php endforeach; ?>
                  <?php if ($cli !== null): ?><td><a class="small" href="<?= b() ?>/admin/cliente/<?= (int) $riga[$cli] ?>">Scheda cliente</a></td><?php endif; ?></tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  </div>
</div>
