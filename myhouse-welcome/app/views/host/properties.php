<?php use function MHW\b; use MHW\{Support, Media, Icon}; $title = 'Le mie guide'; $nav = 'pannello'; ?>
<div class="spread">
  <div class="stack stack--sm">
    <h1>Le mie guide.</h1>
    <?php if ($sub): ?>
      <p class="muted small">Piano <strong><?= Support::e($sub['package_name']) ?></strong>,
        versione <?= (int) $sub['version'] ?> — quella che avete comprato, e che resta vostra.</p>
    <?php endif; ?>
  </div>
  <a class="btn btn--go" href="<?= b() ?>/pannello/nuova">
    Aggiungi una struttura <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></a>
</div>

<?php if (!$sub): ?>
  <p class="note" style="margin-top:24px"><?= Icon::svg('info', 19) ?>
    <span>Nessun piano attivo: potete costruire la guida, ma per pubblicarla serve un piano.
      <a href="<?= b() ?>/">Guardate i piani</a>.</span></p>
<?php endif; ?>

<div class="grid grid-3" style="margin-top:28px">
  <?php foreach ($props as $pr): $cop = Media::url($pr['cover_media_id'] ? (int) $pr['cover_media_id'] : null); ?>
    <a class="panel stack" style="padding:0;overflow:hidden;gap:0;color:var(--ink)"
       href="<?= b() ?>/pannello/<?= (int) $pr['id'] ?>">
      <?php if ($cop): ?>
        <span style="display:block;height:160px;overflow:hidden;background:var(--sunk)">
          <img src="<?= Support::e($cop) ?>" alt="" style="width:100%;height:100%;object-fit:cover"></span>
      <?php else: ?>
        <span style="display:block;height:160px;background:var(--sunk)"></span>
      <?php endif; ?>
      <span class="stack stack--sm" style="padding:20px">
        <b style="font-size:19px;font-weight:500;letter-spacing:-.5px"><?= Support::e($pr['name']) ?></b>
        <span class="small muted"><?= Support::e($pr['city'] ?: 'senza città') ?> · /g/<?= Support::e($pr['slug']) ?></span>
        <span class="badge badge--<?= $pr['status'] === 'published' ? 'pine' : 'ochre' ?>" style="align-self:flex-start">
          <span class="dot"></span><?= $pr['status'] === 'published' ? 'Pubblicata' : 'Bozza' ?></span>
      </span>
    </a>
  <?php endforeach; ?>
</div>
