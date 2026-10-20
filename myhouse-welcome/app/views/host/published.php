<?php use function MHW\t, MHW\tf; use function MHW\b; use MHW\{Support, Icon}; $title = t('Guida pubblicata — ') . $prop['name']; ?>
<div class="stack stack--lg" style="max-width:900px">
  <div class="stack stack--sm">
    <span class="badge badge--pine" style="align-self:flex-start"><span class="dot"></span><?= t('Online') ?></span>
    <h1><?= t('La tua guida è online.') ?></h1>
    <p class="lead"><?= t('Gli ospiti la trovano al link e al QR qui sotto. Quando cambi qualcosa, torna qui e pubblica le modifiche: il QR non cambia.') ?></p>
  </div>
  <?php include __DIR__ . '/_qr_box.php'; ?>
  <div class="actions">
    <a class="btn btn--go" href="<?= b() ?>/g/<?= Support::e($prop['slug']) ?>/benvenuto" target="_blank" rel="noopener"><?= t('Apri la guida') ?> <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></a>
    <a class="btn btn--ghost" href="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>"><?= t('Torna ai contenuti') ?></a>
  </div>
  <?php include __DIR__ . '/_invita.php'; /* il momento migliore per invitare: la guida è appena andata online */ ?>
</div>
