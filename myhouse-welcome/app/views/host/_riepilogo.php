<?php
/* 9A · Il conto del primo anno prima del pagamento: prezzo pieno, una riga per ogni sconto, totale,
   e cosa si paga dal secondo anno. Riceve $riep (Sconti::riepilogo), $pv (la versione), $quantita. */
use MHW\{Support, Plans};
$valR = (string) ($pv['currency'] ?? 'EUR');
$perStrutturaR = Plans::perProperty($pv); ?>
<div class="riepilogo" role="region" aria-label="Riepilogo del primo anno">
  <div class="riepilogo__riga"><span><?= Support::e($pv['name']) ?><?= $perStrutturaR ? ' · ' . (int) $quantita . ' strutture' : '' ?></span><b><?= Support::e(Support::money($riep['prezzo'], $valR)) ?></b></div>
  <?php foreach ($riep['righe'] as $rr): ?>
    <div class="riepilogo__riga riepilogo__riga--sconto"><span><?= Support::e($rr['nome']) ?><?= $rr['percento'] > 0 ? ' · −' . (int) $rr['percento'] . '% primo anno' : ' · primo anno' ?></span><b>−<?= Support::e(Support::money((int) $rr['cents'], $valR)) ?></b></div>
  <?php endforeach; ?>
  <div class="riepilogo__riga riepilogo__riga--totale"><span>Totale primo anno</span><b><?= Support::e(Support::money($riep['totale'], $valR)) ?> <small>+ IVA</small></b></div>
  <p class="small muted" style="margin:0">Dal secondo anno <?= Support::e(Support::money($riep['prezzo'], $valR)) ?> + IVA<?= $riep['sconto'] > 0 ? ': gli sconti valgono solo sul primo anno.' : '.' ?></p>
  <?php if ($riep['nota'] !== ''): ?><p class="small" style="margin:0"><?= Support::e($riep['nota']) ?></p><?php endif; ?>
</div>
