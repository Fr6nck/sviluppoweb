<?php use function MHW\a; use function MHW\b; use MHW\{Support, Icon}; $f = Support::flash(); ?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Support::e($title ?? 'MyHouse Welcome') ?></title>
<?php include __DIR__ . '/_tema.php'; ?>
<?php include __DIR__ . '/_icone.php'; ?>
<?php include __DIR__ . '/_condivisione.php'; ?>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Gloock&family=Onest:wght@300..800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= MHW\av('/assets/app.css') ?>">
</head>
<body class="cms">
<main class="wrap" style="max-width:<?= Support::e($larghezza ?? '520px') ?>;padding-top:40px;padding-bottom:48px">
  <div class="spread spread--mid">
    <a class="brand" href="<?= b() ?>/"><?= Icon::brand(26) ?><span>myhouse welcome</span></a>
    <?php include __DIR__ . '/_tema-bottone.php'; ?>
  </div>
  <?php if ($f): ?><p class="note note--<?= $f['kind'] === 'err' ? 'err' : 'ok' ?>" style="margin-top:24px" role="status"><?= Support::e($f['msg']) ?></p><?php endif; ?>
  <div style="margin-top:28px"><?= $content ?></div>
  <p class="tiny muted" style="margin-top:40px;text-align:center">
    <a href="<?= b() ?>/termini">Termini</a> · <a href="<?= b() ?>/privacy">Privacy</a></p>
</main>
</body>
</html>
