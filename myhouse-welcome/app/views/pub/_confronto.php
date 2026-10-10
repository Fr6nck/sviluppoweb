<?php
/* «Confronta tutti i piani»: la tabella completa, generata dalle funzioni dei
   pacchetti in vendita (cambia da sola quando l'amministratore crea una versione). */
use MHW\{Support, Icon, Plans};
$cf = Plans::comparison(); if (!$cf['righe']) return; ?>
<details class="confronto">
  <summary>Confronta tutti i piani</summary>
  <div class="tablewrap confronto__tabella">
    <table class="data">
      <caption class="sr-only">Le funzioni comprese in ogni piano</caption>
      <thead><tr><th scope="col">Funzione</th>
        <?php foreach ($cf['piani'] as $p): ?><th scope="col"><?= Support::e($p['nome']) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
        <tr><th scope="row">Prezzo</th>
          <?php foreach ($cf['piani'] as $p): ?><td><?= Plans::perProperty($p) ? 'da ' : '' ?><?= Support::e(Support::money((int) $p['price_cents'], $p['currency'])) ?> + IVA / anno</td><?php endforeach; ?></tr>
        <?php foreach ($cf['righe'] as $r): ?>
          <tr><th scope="row"><?= Support::e($r['label']) ?><?php if (($r['code'] ?? '') === 'auto_translation'): ?> <span class="small muted">in omaggio per un anno</span><?php endif; ?></th>
            <?php foreach ($r['valori'] as $v): ?>
              <td><?php if ($v === 'si'): ?><span class="confronto__si"><?= Icon::svg('check', 16, 2.2) ?><span class="sr-only">Compreso</span></span>
                  <?php elseif ($v === 'no'): ?><span class="confronto__no" aria-hidden="true">—</span><span class="sr-only">Non compreso</span>
                  <?php else: ?><?= Support::e($v) ?><?php endif; ?></td>
            <?php endforeach; ?></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</details>
