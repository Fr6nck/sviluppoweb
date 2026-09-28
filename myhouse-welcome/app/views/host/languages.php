<?php
/* Lingue: quali pubblicare e quanto è tradotto ciascuna. */
use function MHW\b;
use MHW\{Support, Icon};
$title = 'Lingue — ' . $prop['name'];
$def = $prop['default_locale']; ?>
<div class="stack stack--lg" style="max-width:760px">
  <div class="stack stack--sm">
    <h1>Lingue.</h1>
    <p class="lead">Scegli in quali lingue pubblicare la guida e scrivi le traduzioni, sezione per sezione, accanto al testo originale.</p>
  </div>
  <?php if ($err): ?><p class="note note--err" role="alert"><?= Support::e($err) ?></p><?php endif; ?>

  <?php $dopoPasso = ''; include __DIR__ . '/_lingue_form.php'; ?>

  <?php $altre = array_values(array_filter($lingueAttive, fn($l) => $l !== $def)); if ($altre): ?>
    <div class="stack" style="gap:10px">
      <h2 style="font-size:22px">Traduzioni</h2>
      <?php foreach ($altre as $l): [$fatte, $tot] = $copertura[$l] ?? [0, 0]; $ok = in_array($l, $consentite, true); ?>
        <a class="rowcard <?= $ok ? '' : 'rowcard--locked' ?>" href="<?= $ok ? b() . '/pannello/' . (int) $prop['id'] . '/lingue/' . Support::e($l) : '#' ?>">
          <span style="color:var(--accent);display:flex"><?= Icon::svg('globe', 20) ?></span>
          <b class="grow"><?= Support::e($tutte[$l] ?? $l) ?></b>
          <span class="meter"><i><b style="width:<?= $tot ? (int) round($fatte / $tot * 100) : 0 ?>%"></b></i><?= (int) $fatte ?> su <?= (int) $tot ?> sezioni</span>
          <span class="small muted"><?= $ok ? 'Traduci' : 'Fuori piano' ?></span>
        </a>
      <?php endforeach; ?>
      <p class="tiny muted">Dove manca la traduzione, l'ospite legge il testo nella lingua principale.</p>
    </div>
  <?php endif; ?>
</div>
