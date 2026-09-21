<?php /* Schermo intero, senza intestazioni: la soglia e il congedo. */ ?>
<?php use function MHW\a; use MHW\Support; ?>
<!doctype html>
<html lang="<?= Support::e($loc ?? 'it') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= Support::e($title ?? 'MyHouse Welcome') ?></title>
<meta name="robots" content="noindex">
<meta name="theme-color" content="#17130d">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Gloock&family=Onest:wght@300..800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= a() ?>/assets/app.css">
</head>
<body style="background:#17130d"><?= $content ?></body>
</html>
