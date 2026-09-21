<?php use function MHW\a; use function MHW\b; use MHW\Support; $f = Support::flash(); ?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Support::e($title ?? 'MyHouse Welcome') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Gloock&family=Onest:wght@300..800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= a() ?>/assets/app.css">
</head>
<body>
<main class="wrap" style="max-width:520px;padding-top:56px;padding-bottom:56px">
  <a class="brand" href="<?= b() ?>/">myhouse welcome</a>
  <?php if ($f): ?><p class="note note--<?= $f['kind'] === 'err' ? 'err' : 'ok' ?>" style="margin-top:20px"><?= Support::e($f['msg']) ?></p><?php endif; ?>
  <div style="margin-top:24px"><?= $content ?></div>
</main>
</body>
</html>
