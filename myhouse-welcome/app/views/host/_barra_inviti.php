<?php use function MHW\t, MHW\tf;
/* 9R · La barra degli inviti: 10 rettangoli, uno per amico che ha attivato (pieno, con «−5%», «−10%»…; il più recente ha
   l'alone per 3 giorni), chi si è solo registrato tratteggiato con la clessidra (9T: «in attesa che attivi» nel title e per lo screen reader); sotto, nella versione grande, i tre numeri sul
   piano del cliente. Riceve $barra (Inviti::barra) e $piccola (true nel riquadro di «Le mie guide»). */
use MHW\{Support, Inviti};
$piccola = $piccola ?? false;
$euro = fn(?int $c) => $c === null ? '—' : Support::money($c, $barra['valuta']); ?>
<div class="barra-inviti<?= $piccola ? ' barra-inviti--piccola' : '' ?>">
  <div class="invito__conto"><span><?= t('Amici che hanno attivato') ?></span><b><?= Support::e(tf('{n} su {tetto}', ['{n}' => $barra['pieni'], '{tetto}' => count($barra['caselle'])])) ?></b></div>
  <ol class="barra-inviti__caselle" role="list" aria-label="<?= Support::e(tf('{n} amici su {tetto} hanno attivato la guida', ['{n}' => $barra['pieni'], '{tetto}' => count($barra['caselle'])])) ?>">
    <?php foreach ($barra['caselle'] as $c): ?>
      <li class="barra-inviti__casella barra-inviti__casella--<?= $c['stato'] ?><?= $c['alone'] ? ' barra-inviti__casella--alone' : '' ?>"><?php if (!$piccola && $c['stato'] === 'attesa'): /* 9T · nel rettangolo solo la clessidra: la spiegazione è nella riga sotto */ ?><span class="barra-inviti__attesa" title="<?= Support::e($c['etichetta']) ?>"><?= MHW\Icon::svg('clessidra', 16, 1.8) ?><span class="sr-only"><?= Support::e($c['etichetta']) ?></span></span>
        <?php elseif (!$piccola): ?><span><?= Support::e($c['etichetta']) ?></span><?php elseif ($c['stato'] === 'attesa'): ?><span class="sr-only"><?= Support::e($c['etichetta']) ?></span><?php endif; ?></li>
    <?php endforeach; ?>
  </ol>
  <?php if (!$piccola): ?>
  <div class="invito__scala" aria-hidden="true"><span>0</span><span><?= "\u{2212}" . intdiv(Inviti::MASSIMO, 2) ?>%</span><span><?= "\u{2212}" . Inviti::MASSIMO ?>%</span></div>
  <div class="barra-inviti__numeri">
    <div class="barra-inviti__numero"><span class="small muted"><?= t('Sconto maturato') ?></span><b><?= (int) $barra['maturato'] ?>%</b>
      <span class="tiny muted"><?= Support::e(tf('{n} amici × {passo}%', ['{n}' => $barra['pieni'], '{passo}' => Inviti::PASSO])) ?></span></div>
    <div class="barra-inviti__numero"><span class="small muted"><?= t('Risparmi al prossimo rinnovo') ?></span><b class="barra-inviti__accento"><?= Support::e($euro($barra['risparmio'])) ?></b>
      <span class="tiny muted"><?= $barra['staff'] ? t('non paghi: account staff') : Support::e(tf('su {prezzo} · paghi {paga} + IVA', ['{prezzo}' => $euro($barra['prezzo']), '{paga}' => $euro($barra['paga'])])) ?></span></div>
    <div class="barra-inviti__numero"><span class="small muted"><?= Support::e(tf('Se arrivano tutti e {n}', ['{n}' => count($barra['caselle'])])) ?></span><b><?= Support::e($euro($barra['tutti'])) ?></b>
      <span class="tiny muted"><?= $barra['staff'] ? t('non paghi: account staff') : Support::e(tf('in meno · paghi {paga}', ['{paga}' => $euro($barra['paga_tutti'])])) ?></span></div>
  </div>
  <?php if ($barra['attesa'] > 0): ?><p class="small muted" style="margin:0"><?= Support::e($barra['attesa'] === 1 ? t('Un amico si è registrato ma non ha ancora attivato la guida: il suo rettangolo si accende quando paga.') : tf('{n} amici si sono registrati ma non hanno ancora attivato la guida: i loro rettangoli si accendono quando pagano.', ['{n}' => $barra['attesa']])) ?></p><?php endif; ?>
  <?php endif; ?>
</div>
