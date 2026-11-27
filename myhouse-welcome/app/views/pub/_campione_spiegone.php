<?php
/* 9M · Lo spiegone «La scheda campione» (blocco 3 del mockup): cos'è, cosa si copia (e si aggiorna), cosa non si copia
   mai, come funziona l'avviso. Una chiave per testo in «Testi e foto» (campione.*): lo stesso nel pannello («La scheda
   campione»), nella pagina /piu-strutture e, in testo semplice, nelle FAQ. Facoltativo $csTitolo (h2 di serie, h1 nel pannello). */
use MHW\TestiSito;
$csTag = $csTitolo ?? 'h2'; ?>
<section class="panel campione-spiegone" aria-labelledby="campione-spiegone-titolo">
  <div class="stack" style="gap:10px">
    <<?= $csTag ?> id="campione-spiegone-titolo" class="campione-spiegone__titolo"><?= TestiSito::html('campione.titolo') ?></<?= $csTag ?>>
    <p class="muted" style="margin:0"><?= TestiSito::html('campione.testo') ?></p>
    <b><?= MHW\LinguaSito::t('Si copiano (e si aggiornano)', 'Copied (and updated)') ?></b>
    <ul class="campione-spiegone__lista"><?php foreach (TestiSito::lista('campione.si') as $x): ?><li><?= $x ?></li><?php endforeach; ?></ul>
  </div>
  <div class="stack" style="gap:10px">
    <b><?= MHW\LinguaSito::t('Non si copiano mai', 'Never copied') ?></b>
    <ul class="campione-spiegone__lista"><?php foreach (TestiSito::lista('campione.no') as $x): ?><li><?= $x ?></li><?php endforeach; ?></ul>
    <b><?= MHW\LinguaSito::t('Come funziona l\'avviso', 'How the notice works') ?></b>
    <ul class="campione-spiegone__lista"><?php foreach (TestiSito::lista('campione.avviso') as $x): ?><li><?= $x ?></li><?php endforeach; ?></ul>
  </div>
</section>
<?php unset($csTitolo); ?>
