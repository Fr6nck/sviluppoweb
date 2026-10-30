<?php /* 8B: l'anteprima del link della guida su WhatsApp, Telegram e Messenger. Solo nella guida pubblicata
         (non nell'anteprima del pannello); noindex resta. Mai indirizzo, Wi-Fi o contatti: titolo, una frase
         fissa e l'immagine 1200×630 della copertina (/g/{slug}/anteprima.jpg). */
use MHW\Support;
if (empty($og) || !empty($anteprima)) return; ?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="MyHouse Welcome">
<meta property="og:title" content="<?= Support::e($og['titolo']) ?>">
<meta property="og:description" content="<?= Support::e($og['testo']) ?>">
<meta property="og:image" content="<?= Support::e($og['immagine']) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
