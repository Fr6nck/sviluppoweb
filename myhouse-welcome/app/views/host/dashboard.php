<?php use function MHW\b; use MHW\{Support, Csrf}; $title = $p['name']; $nav = 'pannello'; ?>
<div class="spread">
  <div>
    <h1><?= Support::e($p['name']) ?></h1>
    <p class="row small" style="margin-top:10px">
      <span class="badge badge--<?= $p['status'] === 'published' ? 'pine' : 'ochre' ?>">
        <?= $p['status'] === 'published' ? 'Pubblicata' : 'Bozza' ?></span>
      <span class="muted"><a href="<?= b() ?>/g/<?= Support::e($p['slug']) ?>">/g/<?= Support::e($p['slug']) ?></a></span>
    </p>
  </div>
  <form method="post" action="<?= b() ?>/pannello/<?= (int) $p['id'] ?>/pubblica"><?= Csrf::field() ?>
    <button class="btn"><?= $pending ? 'Pubblica le modifiche' : ($p['status'] === 'published' ? 'Ripubblica' : 'Pubblica la guida') ?></button>
  </form>
</div>

<div class="grid grid-3" style="margin-top:24px">
  <div class="card card--sunk"><p style="font-size:32px;margin:0;font-family:Gloock,Georgia,serif"><?= (int) $stats['aperture'] ?></p>
    <p class="muted small" style="margin:4px 0 0">aperture della guida</p></div>
  <div class="card card--sunk"><p style="font-size:32px;margin:0;font-family:Gloock,Georgia,serif"><?= (int) $stats['scansioni'] ?></p>
    <p class="muted small" style="margin:4px 0 0">scansioni del QR</p></div>
  <div class="card card--sunk"><p style="font-size:32px;margin:0;font-family:Gloock,Georgia,serif"><?= (int) $stats['lingue'] ?></p>
    <p class="muted small" style="margin:4px 0 0">lingue attive</p></div>
</div>

<nav class="nav" style="margin-top:24px;display:inline-flex">
  <a class="on" href="<?= b() ?>/pannello/<?= (int) $p['id'] ?>">Sezioni</a>
  <a href="<?= b() ?>/pannello/<?= (int) $p['id'] ?>/lingue">Lingue</a>
  <a href="<?= b() ?>/pannello/<?= (int) $p['id'] ?>/qr">QR</a>
  <a href="<?= b() ?>/pannello/<?= (int) $p['id'] ?>/impostazioni">Impostazioni</a>
</nav>

<div class="spread" style="margin-top:24px">
  <h2>Le sezioni</h2>
  <form method="post" action="<?= b() ?>/pannello/<?= (int) $p['id'] ?>/sezioni/nuova"><?= Csrf::field() ?>
    <button class="btn btn--ghost btn--sm">Aggiungi sezione</button></form>
</div>
<div class="list" style="margin-top:12px">
  <?php foreach ($sections as $s): ?>
    <a class="list-item" href="<?= b() ?>/pannello/<?= (int) $p['id'] ?>/sezioni/<?= (int) $s['id'] ?>">
      <span class="grow"><strong><?= Support::e($s['tr']['title'] ?: 'Senza titolo') ?></strong>
        <span class="muted small" style="display:block"><?= Support::e($s['kind']) ?> · <?= (int) $s['locales'] ?> lingua/e</span></span>
      <span class="badge badge--<?= Support::e($s['color']) ?>"><?= Support::e($s['color']) ?></span>
    </a>
  <?php endforeach; ?>
  <?php if (!$sections): ?>
    <p class="note">Nessuna sezione ancora. Aggiungetene una: il Wi-Fi è quella che gli ospiti cercano per prima.</p>
  <?php endif; ?>
</div>
<p class="muted tiny" style="margin-top:20px">
  Piano attuale: <?= (int) count(array_filter($ent, fn($e) => $e['value'] !== '0')) ?> funzioni attive.
  Sezioni comprese: <?= $ent['sections']['value'] === 'unlimited' ? 'senza limite' : Support::e($ent['sections']['value']) ?>.
</p>
