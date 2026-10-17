<?php
/* Il telaio della guida ospite. La palette e il tema li ha scelti l'host; l'ospite
   può passare all'altra variante (sempre leggibile: le palette sono verificate).
   Nessun cookie, nessuna sessione, nessuna indicizzazione. */
use function MHW\a; use MHW\{Support, I18n};
$notte = ($theme ?? '') === 'night';
$loc = $loc ?? 'it';
$temaChiave = 'mhw-tema-ospite'; $temaBase = $tema ?? 'chiaro'; ?>
<!doctype html>
<html lang="<?= Support::e($loc) ?>" data-theme="<?= Support::e($temaBase) ?>" data-chiudi="<?= Support::e(I18n::t($loc, 'close')) ?>"<?= isset($snap['property']['slug']) && empty($anteprima) ? ' data-guida="' . Support::e((string) $snap['property']['slug']) . '"' : '' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= Support::e($title ?? 'Guida della casa') ?></title>
<meta name="robots" content="<?= Support::ROBOTS_GUIDE ?>">
<?php include __DIR__ . '/_og_guida.php'; ?>
<meta name="theme-color" content="<?= $notte ? '#17130d' : '#faf5ec' ?>">
<?php include __DIR__ . '/_tema.php'; ?>
<?php include __DIR__ . '/_icone.php'; ?>
<?php /* 8H: i caratteri dal nostro server (assets/fonts): niente richieste a Google; si anticipa solo Onest, il testo. */ ?>
<link rel="preload" href="<?= MHW\a('/assets/fonts/onest-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= MHW\av('/assets/app.css') ?>">
<?php if (!empty($paletteCss)): ?><style id="palette"><?= $paletteCss ?></style><?php endif; ?>
</head>
<body<?= $notte ? ' class="night"' : '' ?>>
<?php if (!empty($anteprima)): ?><div class="ribbon"><?= Support::e(I18n::t($loc, 'preview_ribbon')) ?></div><?php endif; ?>
<main class="phone" style="padding-bottom:8px"><?= $content ?></main>
<script src="<?= MHW\av('/assets/guida.js') ?>" defer></script>
</body>
</html>
