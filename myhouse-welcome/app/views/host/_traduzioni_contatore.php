<?php
/* 9G · Il contatore delle traduzioni suggerite dell'anno: «24% dell'anno usato · si azzera il {data}» con la barra;
   sotto il 10% che resta diventa arancione; al 100% il messaggio con l'assistenza. Riceve $annoT (Traduttore::contatore) o null. */
use MHW\Support;
if (empty($annoT)) return;
$nT = fn(int $x) => number_format($x, 0, ',', '.'); ?>
<div class="tradu-contatore<?= $annoT['finito'] ? ' tradu-contatore--finito' : ($annoT['basso'] ? ' tradu-contatore--basso' : '') ?>" role="status" aria-label="Traduzioni suggerite dell'anno">
  <p style="margin:0"><b>Traduzioni suggerite: <?= (int) $annoT['percento'] ?>% dell'anno usato</b> · si azzera il <?= Support::e(Support::date($annoT['azzera'])) ?>
    <span class="small muted">(<?= $nT($annoT['usati']) ?> su <?= $nT($annoT['tetto']) ?> caratteri)</span></p>
  <span class="meter tradu-contatore__barra"><i><b style="width:<?= (int) $annoT['percento'] ?>%"></b></i></span>
  <?php if ($annoT['finito']): ?>
    <p class="small" style="margin:0">Hai usato le traduzioni suggerite di quest'anno. Puoi scrivere le traduzioni a mano, oppure
      <?php if (Support::contatto() !== ''): ?><a href="<?= Support::e(Support::mailto('Traduzioni suggerite: riattivazione', 'Ciao, ho usato le traduzioni suggerite di quest\'anno e vorrei riattivarle.')) ?>">scrivici</a><?php else: ?>scrivici<?php endif; ?>: le riattiviamo noi.</p>
  <?php elseif ($annoT['basso']): ?>
    <p class="small" style="margin:0">Ti restano <?= $nT($annoT['restante']) ?> caratteri fino al <?= Support::e(Support::date($annoT['azzera'])) ?>.</p>
  <?php endif; ?>
</div>
