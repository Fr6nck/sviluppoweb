<?php use MHW\{Support, Icon}; $title = 'Diagnostica'; $nav = 'diagnostica';
$male = array_values(array_filter($checks, fn($c) => !$c[1])); ?>

<div class="stack stack--sm">
  <h1>Diagnostica.</h1>
  <p class="lead" style="max-width:620px">Queste voci non sono promesse: il server interroga davvero
    sé stesso, e prova perfino a scaricare il proprio database.</p>
</div>

<?php if ($male): ?>
  <p class="note note--err" style="margin-top:24px"><?= Icon::svg('warning', 19) ?>
    <span><?= count($male) ?> voce<?= count($male) === 1 ? '' : ' su ' . count($checks) ?> da sistemare.</span></p>
<?php else: ?>
  <p class="note note--ok" style="margin-top:24px"><?= Icon::svg('check', 19, 2.2) ?>
    <span>Tutte e <?= count($checks) ?> le voci sono a posto.</span></p>
<?php endif; ?>

<div class="stack" style="margin-top:24px;gap:8px">
  <?php foreach ($checks as [$label, $buono, $dettaglio]): ?>
    <div class="rowcard" style="<?= $buono ? '' : 'border-color:var(--alert)' ?>">
      <span style="color:var(--<?= $buono ? 'pine' : 'alert' ?>);display:flex;flex:none">
        <?= Icon::svg($buono ? 'check' : 'warning', 18, 2.2) ?></span>
      <span class="grow stack" style="gap:3px">
        <b style="font-size:16px"><?= Support::e($label) ?></b>
        <span class="small muted"><?= Support::e($dettaglio) ?></span></span>
      <span class="badge badge--<?= $buono ? 'pine' : 'alert' ?>"><?= $buono ? 'ok' : 'da vedere' ?></span>
    </div>
  <?php endforeach; ?>
</div>

<p class="tiny muted" style="margin-top:24px">Indirizzo interrogato: <?= Support::e($base) ?></p>
