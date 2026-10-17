<?php use function MHW\b; use MHW\{Support, Icon}; $title = 'Guida pubblicata — ' . $prop['name']; ?>
<div class="stack stack--lg" style="max-width:900px">
  <div class="stack stack--sm">
    <span class="badge badge--pine" style="align-self:flex-start"><span class="dot"></span>Online</span>
    <h1>La tua guida è online.</h1>
    <p class="lead">Gli ospiti la trovano al link e al QR qui sotto. Quando cambi qualcosa, torna qui e pubblica le modifiche: il QR non cambia.</p>
  </div>
  <?php include __DIR__ . '/_qr_box.php'; ?>
  <div class="actions">
    <a class="btn btn--go" href="<?= b() ?>/g/<?= Support::e($prop['slug']) ?>/benvenuto" target="_blank" rel="noopener">Apri la guida <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></a>
    <a class="btn btn--ghost" href="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>">Torna ai contenuti</a>
  </div>
  <?php include __DIR__ . '/_invita.php'; /* il momento migliore per invitare: la guida è appena andata online */ ?>
</div>
