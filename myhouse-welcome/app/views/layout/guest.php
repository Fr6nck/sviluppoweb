<?php
/* Il telaio della guida ospite. La palette e il tema li ha scelti l'host; l'ospite
   può passare all'altra variante (sempre leggibile: le palette sono verificate).
   Nessun cookie, nessuna sessione, nessuna indicizzazione. */
use function MHW\a; use MHW\{Support, I18n};
$notte = ($theme ?? '') === 'night';
$loc = $loc ?? 'it';
$temaChiave = 'mhw-tema-ospite'; $temaBase = $tema ?? 'chiaro'; ?>
<!doctype html>
<html lang="<?= Support::e($loc) ?>" data-theme="<?= Support::e($temaBase) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= Support::e($title ?? 'Guida della casa') ?></title>
<meta name="robots" content="noindex, nofollow, noarchive">
<meta name="theme-color" content="<?= $notte ? '#17130d' : '#faf5ec' ?>">
<?php include __DIR__ . '/_tema.php'; ?>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Gloock&family=Onest:wght@300..800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= a() ?>/assets/app.css">
<?php if (!empty($paletteCss)): ?><style id="palette"><?= $paletteCss ?></style><?php endif; ?>
</head>
<body<?= $notte ? ' class="night"' : '' ?>>
<?php if (!empty($anteprima)): ?><div class="ribbon"><?= Support::e(I18n::t($loc, 'preview_ribbon')) ?></div><?php endif; ?>
<main class="phone" style="padding-bottom:8px"><?= $content ?></main>
</body>
</html>
