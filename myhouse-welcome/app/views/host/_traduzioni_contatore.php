<?php use function MHW\t, MHW\tf;
/* 9G · Il contatore delle traduzioni suggerite dell'anno: «24% dell'anno usato · si azzera il {data}» con la barra;
   sotto il 10% che resta diventa arancione; al 100% il messaggio con l'assistenza. Riceve $annoT (Traduttore::contatore) o null. */
use MHW\Support;
if (empty($annoT)) return;
$nT = fn(int $x) => number_format($x, 0, ',', '.'); ?>
<div class="tradu-contatore<?= $annoT['finito'] ? ' tradu-contatore--finito' : ($annoT['basso'] ? ' tradu-contatore--basso' : '') ?>" role="status" aria-label="<?= MHW\Support::e(t('Traduzioni suggerite dell\'anno')) ?>">
  <p style="margin:0"><b><?= Support::e(tf('Traduzioni suggerite: {n}% dell\'anno usato', ['{n}' => (int) $annoT['percento']])) ?></b> · <?= Support::e(tf('si azzera il {data}', ['{data}' => MHW\Pannello::data($annoT['azzera'])])) ?>
    <span class="small muted">(<?= Support::e(tf('{usati} su {tetto} caratteri', ['{usati}' => $nT($annoT['usati']), '{tetto}' => $nT($annoT['tetto'])])) ?>)</span></p>
  <span class="meter tradu-contatore__barra"><i><b style="width:<?= (int) $annoT['percento'] ?>%"></b></i></span>
  <?php if ($annoT['finito']): ?>
    <p class="small" style="margin:0"><?= tf('Hai usato le traduzioni suggerite di quest\'anno. Puoi scrivere le traduzioni a mano, oppure {scrivici}: le riattiviamo noi.', ['{scrivici}' => Support::contatto() !== ''
      ? '<a href="' . Support::e(Support::mailto(t('Traduzioni suggerite: riattivazione'), t('Ciao, ho usato le traduzioni suggerite di quest\'anno e vorrei riattivarle.'))) . '">' . t('scrivici') . '</a>' : t('scrivici')]) ?></p>
  <?php elseif ($annoT['basso']): ?>
    <p class="small" style="margin:0"><?= Support::e(tf('Ti restano {n} caratteri fino al {data}.', ['{n}' => $nT($annoT['restante']), '{data}' => MHW\Pannello::data($annoT['azzera'])])) ?></p>
  <?php endif; ?>
</div>
