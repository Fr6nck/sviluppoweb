<?php
/**
 * Il telaio di ogni pagina.
 *
 * @var string      $contenuto     la pagina già resa
 * @var string      $pagina        chiave della pagina corrente
 * @var string      $titoloSeo
 * @var string      $descrizione
 * @var string|null $canonico
 * @var array       $alternative   lingua => indirizzo
 * @var bool        $noindex
 */

$noindex = $noindex ?? false;
$base    = rtrim((string) \ArcoDelVento\App::instance()->config('app.url'), '/');
?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titoloSeo) ?></title>
<?php if ($descrizione !== ''): ?>
<meta name="description" content="<?= e($descrizione) ?>">
<?php endif; ?>
<?php if ($noindex): ?>
<meta name="robots" content="noindex, follow">
<?php endif; ?>
<?php if (!empty($canonico)): ?>
<link rel="canonical" href="<?= e($base . $canonico) ?>">
<?php endif; ?>
<?php
// hreflang: ogni lingua dichiara l'indirizzo della stessa pagina nelle altre.
// «x-default» punta all'italiano, che è la lingua di casa.
foreach (($alternative ?? []) as $lingua => $indirizzo): ?>
<link rel="alternate" hreflang="<?= e($lingua) ?>" href="<?= e($base . $indirizzo) ?>">
<?php endforeach; ?>
<?php if (!empty($alternative['it'])): ?>
<link rel="alternate" hreflang="x-default" href="<?= e($base . $alternative['it']) ?>">
<?php endif; ?>

<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= te('common.brand') ?>">
<meta property="og:locale" content="<?= e(locale() === 'it' ? 'it_IT' : 'en_GB') ?>">
<meta property="og:title" content="<?= e($titoloSeo) ?>">
<meta property="og:description" content="<?= e($descrizione) ?>">
<?php if (!empty($canonico)): ?>
<meta property="og:url" content="<?= e($base . $canonico) ?>">
<?php endif; ?>
<meta property="og:image" content="<?= e($base . '/assets/img/foto/vicolo-campanile-4x3.jpg') ?>">
<meta property="og:image:alt" content="<?= te('home.hero.image_alt') ?>">
<meta name="twitter:card" content="summary_large_image">

<link rel="icon" href="<?= e(asset('img/logo/icona-192.png')) ?>" sizes="192x192" type="image/png">
<link rel="apple-touch-icon" href="<?= e(asset('img/logo/icona-512.png')) ?>">

<?php
/* I due caratteri che si vedono per primi — il display del titolo e il testo
   corrente — si precaricano. Allura entra solo su una parola per schermata,
   quindi può arrivare con calma. */ ?>
<link rel="preload" href="<?= e(asset('fonts/prata-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e(asset('fonts/figtree-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>

<?php /* Sessanta byte, senza defer: segnano che il JavaScript c'è prima
         che la pagina si disegni, così le voci di riserva non compaiono
         per un istante spostando tutto il resto. */ ?>
<script src="<?= e(asset('js/abilita-js.js')) ?>"></script>

<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/tokens.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/bundle.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">

<?= partial('structured-data', ['pagina' => $pagina, 'domande' => $domande ?? null, 'camera' => $camera ?? null, 'base' => $base]) ?>
</head>
<body class="adv-pagina" data-pagina="<?= e($pagina) ?>">

<a class="adv-salta" href="#contenuto"><?= te('common.skip') ?></a>

<?= partial('nav', ['alternative' => $alternative ?? []]) ?>

<main id="contenuto" tabindex="-1">
<?= $contenuto ?>
</main>

<?= partial('footer') ?>
<?= partial('sticky-booking') ?>

<script src="<?= e(asset('js/site.js')) ?>" defer></script>
</body>
</html>
