<?php use function MHW\a; use MHW\Support; $notte = ($theme ?? '') === 'night'; ?>
<!doctype html>
<html lang="<?= Support::e($loc ?? 'it') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Support::e($title ?? 'Guida della casa') ?></title>
<meta name="robots" content="noindex">
<meta name="theme-color" content="<?= $notte ? '#17130d' : '#faf5ec' ?>">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Gloock&family=Onest:wght@300..800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= a() ?>/assets/app.css">
</head>
<body<?= $notte ? ' class="night"' : '' ?>>
<main class="phone" style="padding-bottom:8px"><?= $content ?></main>
</body>
</html>
