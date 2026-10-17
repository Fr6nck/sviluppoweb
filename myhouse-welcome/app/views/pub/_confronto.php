<?php
/* «Confronta tutti i piani»: la tabella completa, generata dalle funzioni dei
   pacchetti in vendita (cambia da sola quando l'amministratore crea una versione). */
use MHW\{Support, Icon, Plans, LinguaSito};
$enC = LinguaSito::en();
$cf = Plans::comparison($enC ? 'en' : 'it'); if (!$cf['righe']) return; ?>
<details class="confronto">
  <summary><?= $enC ? 'Compare all plans' : 'Confronta tutti i piani' ?></summary>
  <div class="tablewrap confronto__tabella">
    <table class="data">
      <caption class="sr-only"><?= $enC ? 'The features included in each plan' : 'Le funzioni comprese in ogni piano' ?></caption>
      <thead><tr><th scope="col"><?= $enC ? 'Feature' : 'Funzione' ?></th>
        <?php foreach ($cf['piani'] as $p): ?><th scope="col"><?= Support::e($p['nome']) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
        <tr><th scope="row"><?= $enC ? 'Price' : 'Prezzo' ?></th>
          <?php foreach ($cf['piani'] as $p): ?><td><?= Plans::perProperty($p) ? ($enC ? 'from ' : 'da ') : '' ?><?= Support::e(Support::money((int) $p['price_cents'], $p['currency'])) ?><?= $enC ? ' + VAT / year' : ' + IVA / anno' ?></td><?php endforeach; ?></tr>
        <?php foreach ($cf['righe'] as $r): ?>
          <tr><th scope="row"><?= Support::e($r['label']) ?><?php if (($r['code'] ?? '') === 'auto_translation'): ?> <span class="small muted"><?= $enC ? 'free for a year' : 'in omaggio per un anno' ?></span><?php endif; ?></th>
            <?php foreach ($r['valori'] as $v): ?>
              <td><?php if ($v === 'si'): ?><span class="confronto__si"><?= Icon::svg('check', 16, 2.2) ?><span class="sr-only"><?= $enC ? 'Included' : 'Compreso' ?></span></span>
                  <?php elseif ($v === 'no'): ?><span class="confronto__no" aria-hidden="true">—</span><span class="sr-only"><?= $enC ? 'Not included' : 'Non compreso' ?></span>
                  <?php else: ?><?= Support::e($v) ?><?php endif; ?></td>
            <?php endforeach; ?></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</details>
