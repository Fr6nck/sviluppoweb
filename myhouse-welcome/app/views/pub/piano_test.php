<?php
/* 9L · /piano per chi non è dentro: «Quale piano scegliere», il questionario come pagina (lo stesso partial della home e
   delle pagine per tipologia). È il link «Rispondi a 3 domande» quando il browser non apre la finestra. Riceve i dati
   del questionario ($qPiani, $qRiep, $qRisposte, $qStrutture). In inglese su /en/plan (LinguaSito). */
use function MHW\b;
use MHW\LinguaSito;
$title = LinguaSito::t('Quale piano scegliere · MyHouse Welcome', 'Which plan to choose · MyHouse Welcome');
$dentro = !empty($user) && ($user['role'] ?? '') !== 'admin'; $qOrigine = 'piano'; ?>
<div class="pagina-piano">
  <p class="small"><a href="<?= b() . LinguaSito::percorso('/') ?>#piani"><?= LinguaSito::t('← Tutti i piani e i prezzi', '← All plans and prices') ?></a></p>
  <?php include __DIR__ . '/_questionario.php'; ?>
  <?php if (($promoR = MHW\Sconti::promoPerChiGuarda())): $promoPiccola = true; include __DIR__ . '/_promo.php'; endif; /* 9U */ ?>
</div>
