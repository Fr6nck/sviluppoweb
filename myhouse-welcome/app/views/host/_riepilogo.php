<?php use function MHW\t, MHW\tf;
/* 9A · Il conto del primo anno prima del pagamento: prezzo pieno, una riga per ogni sconto, totale,
   e cosa si paga dal secondo anno. Riceve $riep (Sconti::riepilogo), $pv (la versione), $quantita. */
use MHW\{Support, Plans, Iva, Auth};
$valR = (string) ($pv['currency'] ?? 'EUR');
$perStrutturaR = Plans::perProperty($pv);
// 9O · Sempre imponibile, IVA e totale; per una persona fisica il totale con l'IVA è quello in grande (e niente «+ IVA»).
$privatoR = ((isset($acc) && is_array($acc) ? $acc : (Auth::account() ?? []))['billing_type'] ?? '') === 'privato';
$righeIva = function (int $netto) use ($privatoR, $valR): string {
    $iva = Iva::di($netto);
    return '<div class="riepilogo__riga"><span>' . Support::e(tf('IVA {n}%', ['{n}' => Iva::aliquota()])) . '</span><b>' . Support::e(Support::money($iva, $valR)) . '</b></div>'
         . '<div class="riepilogo__riga riepilogo__riga--lordo' . ($privatoR ? ' riepilogo__riga--totale riepilogo__riga--grande' : '') . '"><span>' . Support::e(t('Totale con IVA')) . '</span><b>'
         . Support::e(Support::money($netto + $iva, $valR)) . '</b></div>';
}; ?>
<div class="riepilogo" role="region" aria-label="<?= MHW\Support::e(t('Riepilogo del primo anno')) ?>">
  <div class="riepilogo__riga"><span><?= Support::e($pv['name']) ?><?= $perStrutturaR ? ' · ' . Support::e(tf('{n} strutture', ['{n}' => (int) $quantita])) : '' ?></span><b><?= Support::e(Support::money($riep['prezzo'], $valR)) ?></b></div>
  <?php foreach ($riep['righe'] as $rr): ?>
    <div class="riepilogo__riga riepilogo__riga--sconto"><span><?= Support::e($rr['nome']) ?><?= $rr['percento'] > 0 ? ' · −' . Support::e(tf('{n}% primo anno', ['{n}' => (int) $rr['percento']])) : ' · ' . t('primo anno') ?></span><b>−<?= Support::e(Support::money((int) $rr['cents'], $valR)) ?></b></div>
  <?php endforeach; ?>
  <?php if (!empty($riep['omaggio'])): /* 9D · codice omaggio: oggi 0 €, il primo addebito alla fine dei mesi gratis */ $omR = $riep['omaggio']; ?>
    <div class="riepilogo__riga riepilogo__riga--sconto"><span><?= Support::e(tf('Codice omaggio {codice}', ['{codice}' => $omR['codice']])) ?></span><b><?= Support::e(tf('{n} mesi gratis', ['{n}' => (int) $omR['mesi']])) ?></b></div>
    <div class="riepilogo__riga riepilogo__riga--totale"><span><?= t('Oggi') ?></span><b><?= t('0&nbsp;€') ?></b></div>
    <div class="riepilogo__riga" style="font-weight:600"><span><?= Support::e(tf('Il {data}', ['{data}' => MHW\Pannello::data(gmdate('Y-m-d\TH:i:s\Z', (int) $omR['fine']))])) ?></span><b><?= Support::e(Support::money($riep['totale'], $valR)) ?> <small><?= t('+ IVA') ?></small></b></div>
    <?= $righeIva((int) $riep['totale']) ?>
    <p class="small muted" style="margin:0"><?= Support::e(Plans::settimanale($pv) ? tf('Poi {prezzo} + IVA a settimana (piano di prova).', ['{prezzo}' => Support::money($riep['prezzo'], $valR)])
                                                 : tf($riep['sconto'] > 0 ? 'Poi {prezzo} + IVA l\'anno: lo sconto vale solo sul primo addebito.' : 'Poi {prezzo} + IVA l\'anno.', ['{prezzo}' => Support::money($riep['prezzo'], $valR)])) ?></p>
  <?php else: ?>
  <?php if ($privatoR): ?>
    <div class="riepilogo__riga"><span><?= t('Imponibile primo anno') ?></span><b><?= Support::e(Support::money($riep['totale'], $valR)) ?></b></div>
  <?php else: ?>
    <div class="riepilogo__riga riepilogo__riga--totale"><span><?= t('Totale primo anno') ?></span><b><?= Support::e(Support::money($riep['totale'], $valR)) ?> <small><?= t('+ IVA') ?></small></b></div>
  <?php endif; ?>
  <?= $righeIva((int) $riep['totale']) ?>
  <p class="small muted" style="margin:0"><?= Support::e(Plans::settimanale($pv) ? tf('Dalla seconda settimana {prezzo} + IVA (piano di prova).', ['{prezzo}' => Support::money($riep['prezzo'], $valR)])
                                                 : tf($riep['sconto'] > 0 ? 'Dal secondo anno {prezzo} + IVA: gli sconti valgono solo sul primo anno.' : 'Dal secondo anno {prezzo} + IVA.', ['{prezzo}' => Support::money($riep['prezzo'], $valR)])) ?></p>
  <?php endif; ?>
  <?php if ($riep['nota'] !== ''): ?><p class="small" style="margin:0"><?= Support::e($riep['nota']) ?></p><?php endif; ?>
</div>
