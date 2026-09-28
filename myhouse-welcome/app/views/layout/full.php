<?php /* Schermo intero, senza intestazioni: la soglia e il congedo. Stanno sempre
         sopra una fotografia scura, ma i bottoni prendono l'accento della palette. */
use function MHW\a; use MHW\{Support, I18n}; ?>
<!doctype html>
<html lang="<?= Support::e($loc ?? 'it') ?>" data-theme="scuro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= Support::e($title ?? 'MyHouse Welcome') ?></title>
<meta name="robots" content="noindex, nofollow, noarchive">
<meta name="theme-color" content="#17130d">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Gloock&family=Onest:wght@300..800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= a() ?>/assets/app.css">
<?php if (!empty($paletteCss)): ?><style id="palette"><?= $paletteCss ?></style><?php endif; ?>
</head>
<body style="background:#17130d">
<?php if (!empty($anteprima)): ?><div class="ribbon"><?= Support::e(I18n::t($loc ?? 'it', 'preview_ribbon')) ?></div><?php endif; ?>
<?= $content ?>
</body>
</html>
