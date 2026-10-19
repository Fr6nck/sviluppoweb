<?php
/* 9L · /piano per chi non è dentro: «Quale piano scegliere», il questionario come pagina (lo stesso partial della home e
   delle pagine per tipologia). È il link «Rispondi a 3 domande» quando il browser non apre la finestra. Riceve i dati
   del questionario ($qPiani, $qRiep, $qRisposte, $qStrutture). */
use function MHW\b;
$title = 'Quale piano scegliere · MyHouse Welcome';
$dentro = false; $qOrigine = 'piano'; ?>
<div class="pagina-piano">
  <p class="small"><a href="<?= b() ?>/#piani">← Tutti i piani e i prezzi</a></p>
  <?php include __DIR__ . '/_questionario.php'; ?>
</div>
