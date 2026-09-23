<?php
/**
 * Il filetto con la stella a quattro punte: l'unico ornamento vettoriale del
 * sistema. Una volta per sezione, mai due di fila. La rosa dei venti non si
 * usa mai come decorazione: quella è il marchio.
 *
 * @var bool $corto
 */
$corto = $corto ?? false;
?>
<div class="adv-ornamento<?= $corto ? ' adv-ornamento--corto' : '' ?>">
  <svg class="adv-ornamento__stella" width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
    <path d="M9 0 L10.6 7.4 L18 9 L10.6 10.6 L9 18 L7.4 10.6 L0 9 L7.4 7.4 Z" fill="currentColor"/>
  </svg>
</div>
