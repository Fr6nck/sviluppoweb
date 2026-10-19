<?php
/* 9K · La riga «Fino al 55% di sconto sul rinnovo» (pagine /piu-strutture e /property-manager; il questionario ha la sua,
   con la tabella). Solo quello che è acceso: gli inviti (fino a Inviti::MASSIMO) e il giudizio con sconto. I testi sono
   quelli del questionario in «Testi e foto». */
use function MHW\b;
use MHW\{TestiSito, Inviti, Giudizi};
$srInviti = Inviti::disponibili(); $srGiudizio = Giudizi::attivo();
$srTot = ($srInviti ? Inviti::MASSIMO : 0) + ($srGiudizio ? Giudizi::percentuale() : 0);
if ($srTot > 0): ?>
  <div class="questionario__sconto tipo-sconto">
    <span class="questionario__perc"><?= (int) $srTot ?>%</span>
    <p><?= TestiSito::html('questionario.sconto.' . ($srInviti && $srGiudizio ? 'entrambi' : ($srInviti ? 'inviti' : 'giudizio')),
             ['totale' => $srTot, 'amici' => (int) (Inviti::MASSIMO / max(1, Inviti::PASSO)), 'passo' => Inviti::PASSO, 'max' => Inviti::MASSIMO, 'giudizio' => Giudizi::percentuale()]) ?>
      <a href="<?= b() ?>/domande#porta-amico">Come funziona</a></p>
  </div>
<?php endif; ?>
