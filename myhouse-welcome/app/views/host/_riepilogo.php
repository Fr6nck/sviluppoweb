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
  <?php if (!empty($riep['omaggio'])): /* 9D · codice omaggio: oggi 0 €, il primo addebito alla fine dei mesi gratis */ $omR = $riep['omaggio']; ?>
    <div class="riepilogo__riga riepilogo__riga--sconto"><span>Codice omaggio <?= Support::e($omR['codice']) ?></span><b><?= (int) $omR['mesi'] ?> mesi gratis</b></div>
    <div class="riepilogo__riga riepilogo__riga--totale"><span>Oggi</span><b>0&nbsp;€</b></div>
    <div class="riepilogo__riga" style="font-weight:600"><span>Il <?= Support::e(Support::date(gmdate('Y-m-d\TH:i:s\Z', (int) $omR['fine']))) ?></span><b><?= Support::e(Support::money($riep['totale'], $valR)) ?> <small>+ IVA</small></b></div>
    <p class="small muted" style="margin:0">Poi <?= Support::e(Support::money($riep['prezzo'], $valR)) ?> + IVA l'anno<?= $riep['sconto'] > 0 ? ': lo sconto vale solo sul primo addebito.' : '.' ?></p>
  <?php else: ?>
  <div class="riepilogo__riga riepilogo__riga--totale"><span>Totale primo anno</span><b><?= Support::e(Support::money($riep['totale'], $valR)) ?> <small>+ IVA</small></b></div>
  <p class="small muted" style="margin:0">Dal secondo anno <?= Support::e(Support::money($riep['prezzo'], $valR)) ?> + IVA<?= $riep['sconto'] > 0 ? ': gli sconti valgono solo sul primo anno.' : '.' ?></p>
  <?php endif; ?>
  <?php if ($riep['nota'] !== ''): ?><p class="small" style="margin:0"><?= Support::e($riep['nota']) ?></p><?php endif; ?>
</div>
