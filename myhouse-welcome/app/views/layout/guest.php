<?php use function MHW\a; use function MHW\b; use MHW\Support; ?>
<!doctype html>
<html lang="<?= Support::e($loc ?? 'it') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Support::e($title ?? 'Guida della casa') ?></title>
<meta name="robots" content="noindex">
<link href="https://fonts.googleapis.com/css2?family=Gloock&family=Onest:wght@300..800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= a() ?>/assets/app.css">
</head>
<body>
<main class="phone" style="padding-top:20px;padding-bottom:40px"><?= $content ?></main>
</body>
</html>
