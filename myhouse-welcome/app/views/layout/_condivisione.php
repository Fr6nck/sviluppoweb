<?php /* L'anteprima dei link condivisi (WhatsApp, Facebook, LinkedIn): solo nelle
         pagine pubbliche, mai nella guida degli ospiti. */
use function MHW\a; use MHW\Support;
$ogTitolo = $ogTitolo ?? 'MyHouse Welcome · La casa risponde prima che chiedano';
$ogTesto = $ogTesto ?? 'La guida digitale per case vacanza, B&B, affittacamere e agriturismi: check-in, Wi-Fi e consigli in un link e un QR Code. La crei gratis, paghi solo quando la pubblichi.';
$origine = (string) preg_replace('#^(https?://[^/]+).*$#', '$1', Support::baseUrl()); ?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="MyHouse Welcome">
<meta property="og:title" content="<?= Support::e($ogTitolo) ?>">
<meta property="og:description" content="<?= Support::e($ogTesto) ?>">
<meta property="og:image" content="<?= Support::e($origine . a('/assets/og.jpg')) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale" content="it_IT">
<meta name="twitter:card" content="summary_large_image">
