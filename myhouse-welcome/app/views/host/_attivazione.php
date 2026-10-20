<?php
/* 9M · «Disponibile dopo l'attivazione»: accanto ai bottoni spenti prima del pagamento (traduzione automatica, «Rendi
   campione»). Una chiave sola in «Testi e foto» (attivazione.spiegone), la stessa nelle FAQ e nella pagina dei piani. */
use MHW\{TestiSito, Icon}; ?>
<div class="attivazione" role="note"><span class="attivazione__ico" aria-hidden="true"><?= Icon::svg('info', 18, 2) ?></span>
  <p><?= TestiSito::html('attivazione.spiegone') ?></p></div>
