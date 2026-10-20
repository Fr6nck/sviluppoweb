<?php use function MHW\t, MHW\tf;
/* 9A · Il conto del primo anno prima del pagamento: prezzo pieno, una riga per ogni sconto, totale,
   e cosa si paga dal secondo anno. Riceve $riep (Sconti::riepilogo), $pv (la versione), $quantita. */
use MHW\{Support, Plans};
$valR = (string) ($pv['currency'] ?? 'EUR');
$perStrutturaR = Plans::perProperty($pv); ?>
<div class="riepilogo" role="region" aria-label="<?= MHW\Support::e(t('Riepilogo del primo anno')) ?>">
  <div class="riepilogo__riga"><span><?= Support::e($pv['name']) ?><?= $perStrutturaR ? ' · ' . Support::e(tf('{n} strutture', ['{n}' => (int) $quantita])) : '' ?></span><b><?= Support::e(Support::money($riep['prezzo'], $valR)) ?></b></div>
  <?php foreach ($riep['righe'] as $rr): ?>
    <div class="riepilogo__riga riepilogo__riga--sconto"><span><?= Support::e($rr['nome']) ?><?= $rr['percento'] > 0 ? ' · −' . Support::e(tf('{n}% primo anno', ['{n}' => (int) $rr['percento']])) : ' · ' . t('primo anno') ?></span><b>−<?= Support::e(Support::money((int) $rr['cents'], $valR)) ?></b></div>
  <?php endforeach; ?>
  <?php if (!empty($riep['omaggio'])): /* 9D · codice omaggio: oggi 0 €, il primo addebito alla fine dei mesi gratis */ $omR = $riep['omaggio']; ?>
    <div class="riepilogo__riga riepilogo__riga--sconto"><span><?= Support::e(tf('Codice omaggio {codice}', ['{codice}' => $omR['codice']])) ?></span><b><?= Support::e(tf('{n} mesi gratis', ['{n}' => (int) $omR['mesi']])) ?></b></div>
    <div class="riepilogo__riga riepilogo__riga--totale"><span><?= t('Oggi') ?></span><b><?= t('0&nbsp;€') ?></b></div>
    <div class="riepilogo__riga" style="font-weight:600"><span><?= Support::e(tf('Il {data}', ['{data}' => MHW\Pannello::data(gmdate('Y-m-d\TH:i:s\Z', (int) $omR['fine']))])) ?></span><b><?= Support::e(Support::money($riep['totale'], $valR)) ?> <small><?= t('+ IVA') ?></small></b></div>
    <p class="small muted" style="margin:0"><?= Support::e(tf($riep['sconto'] > 0 ? 'Poi {prezzo} + IVA l\'anno: lo sconto vale solo sul primo addebito.' : 'Poi {prezzo} + IVA l\'anno.', ['{prezzo}' => Support::money($riep['prezzo'], $valR)])) ?></p>
  <?php else: ?>
  <div class="riepilogo__riga riepilogo__riga--totale"><span><?= t('Totale primo anno') ?></span><b><?= Support::e(Support::money($riep['totale'], $valR)) ?> <small><?= t('+ IVA') ?></small></b></div>
  <p class="small muted" style="margin:0"><?= Support::e(tf($riep['sconto'] > 0 ? 'Dal secondo anno {prezzo} + IVA: gli sconti valgono solo sul primo anno.' : 'Dal secondo anno {prezzo} + IVA.', ['{prezzo}' => Support::money($riep['prezzo'], $valR)])) ?></p>
  <?php endif; ?>
  <?php if ($riep['nota'] !== ''): ?><p class="small" style="margin:0"><?= Support::e($riep['nota']) ?></p><?php endif; ?>
</div>
