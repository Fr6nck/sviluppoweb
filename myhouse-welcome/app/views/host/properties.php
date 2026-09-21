<?php use function MHW\b; use MHW\Support; $title = 'Le mie guide'; $nav = 'pannello'; ?>
<div class="spread"><h1>Le mie guide.</h1><a class="btn" href="<?= b() ?>/pannello/nuova">Aggiungi una struttura</a></div>
<?php if ($sub): ?>
  <p class="muted small" style="margin-top:10px">Piano <strong><?= Support::e($sub['package_name']) ?></strong>,
  versione <?= (int) $sub['version'] ?>.</p>
<?php else: ?>
  <p class="note" style="margin-top:16px">Nessun piano attivo: potete costruire la guida, ma per pubblicarla serve un piano.
  <a href="<?= b() ?>/">Guardate i piani</a>.</p>
<?php endif; ?>
<div class="list" style="margin-top:24px">
  <?php foreach ($props as $p): ?>
    <a class="list-item" href="<?= b() ?>/pannello/<?= (int) $p['id'] ?>">
      <span class="grow"><strong><?= Support::e($p['name']) ?></strong>
        <span class="muted small" style="display:block"><?= Support::e($p['city'] ?: 'senza città') ?> · /g/<?= Support::e($p['slug']) ?></span></span>
      <span class="badge badge--<?= $p['status'] === 'published' ? 'pine' : 'ochre' ?>">
        <?= $p['status'] === 'published' ? 'Pubblicata' : 'Bozza' ?></span>
    </a>
  <?php endforeach; ?>
</div>
