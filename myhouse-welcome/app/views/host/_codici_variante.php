<?php
/* 6M · varianti camera: si aggiornano senza pubblicare, quindi la conferma «a mio rischio» si
   chiede al salvataggio. Il riquadro compare (assets/codici.js) quando un campo del modulo
   contiene un codice; senza la casella spuntata il server non salva. $mostra: già aperto. */
use MHW\Icon; ?>
<div class="codici-conferma" data-codici-box<?= !empty($mostra) ? '' : ' hidden' ?> role="group" aria-label="Codici di accesso">
  <h3 style="font-size:18px;line-height:24px;display:flex;gap:8px;align-items:center"><?= Icon::svg('lock', 18) ?>Nella variante ci sono codici di accesso</h3>
  <p class="small" style="margin:0">Chi ha il link o il QR della guida può leggerli, anche dopo la partenza, e il link si può inoltrare. Ti consigliamo di toglierli e comunicarli
    all'ospite in privato. Se salvi lo stesso, lo fai a tuo rischio: MyHouse Welcome non risponde dell'uso di questi codici.</p>
  <label class="check"><input type="checkbox" name="codici_ok" value="1" data-codici-ok<?= !empty($mostra) ? ' required' : '' ?>> <span>Ho capito, pubblico a mio rischio</span></label>
</div>
