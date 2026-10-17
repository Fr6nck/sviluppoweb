<?php /* Schermo intero, senza intestazioni: la soglia e il congedo. Stanno sempre
         sopra una fotografia scura, ma i bottoni prendono l'accento della palette. */
use function MHW\a; use MHW\{Support, I18n}; ?>
<!doctype html>
<html lang="<?= Support::e($loc ?? 'it') ?>" data-theme="scuro"<?= isset($snap['property']['slug']) && empty($anteprima) ? ' data-guida="' . Support::e((string) $snap['property']['slug']) . '"' : '' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= Support::e($title ?? 'MyHouse Welcome') ?></title>
<meta name="robots" content="<?= Support::ROBOTS_GUIDE ?>">
<?php include __DIR__ . '/_og_guida.php'; ?>
<meta name="theme-color" content="#17130d">
<?php include __DIR__ . '/_icone.php'; ?>
<?php /* 8H: i caratteri dal nostro server (assets/fonts): niente richieste a Google; si anticipa solo Onest, il testo. */ ?>
<link rel="preload" href="<?= MHW\a('/assets/fonts/onest-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= MHW\av('/assets/app.css') ?>">
<?php if (!empty($paletteCss)): ?><style id="palette"><?= $paletteCss ?></style><?php endif; ?>
</head>
<body style="background:#17130d">
<?php if (!empty($anteprima)): ?><div class="ribbon"><?= Support::e(I18n::t($loc ?? 'it', 'preview_ribbon')) ?></div><?php endif; ?>
<?= $content ?>
<script src="<?= MHW\av('/assets/guida.js') ?>" defer></script>
</body>
</html>
