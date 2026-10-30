<?php
/* «Confronta tutti i piani»: la tabella completa, generata dalle funzioni dei
   pacchetti in vendita (cambia da sola quando l'amministratore crea una versione). */
use MHW\{Support, Icon, Plans, LinguaSito, Iva};
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
          <?php $promoC = MHW\Sconti::promoPerChiGuarda(); /* 9U · la promo in corso: listino barrato e primo anno */
                foreach ($cf['piani'] as $p): ?><td><?php if ($promoC): ?><span class="badge-promo">−<?= (int) $promoC['value'] ?>%</span> <?= Iva::cifra((int) $p['price_cents'], $p['currency'], 'class="muted"', 's') ?> <?php endif; ?><?= Plans::perProperty($p) ? ($enC ? 'from ' : 'da ') : '' ?><?= Iva::cifra($promoC ? MHW\Sconti::conPromo((int) $p['price_cents'], $promoC) : (int) $p['price_cents'], $p['currency']) ?><?= $promoC ? ($enC ? ' the 1st year,' : ' il 1° anno,') : '' ?> <?= $enC ? Iva::nota('+ VAT / year', 'incl. VAT / year') : Iva::nota('+ IVA / anno', 'IVA inclusa / anno') ?></td><?php endforeach; ?></tr>
        <?php foreach ($cf['righe'] as $r): ?>
          <tr><th scope="row"><?= Support::e($r['label']) ?><?php if (($r['code'] ?? '') === 'auto_translation'): ?> <span class="small muted"><?= $enC ? 'within a yearly character limit' : 'entro un limite annuale di caratteri' ?></span><?php elseif (($r['code'] ?? '') === 'ai_concierge'): ?> <span class="small muted"><?= $enC ? 'up to ' . number_format(MHW\Assistente::limite(0)) . ' questions a month per property' : 'fino a ' . number_format(MHW\Assistente::limite(0), 0, ',', '.') . ' domande al mese per struttura' ?></span><?php endif; ?></th>
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
