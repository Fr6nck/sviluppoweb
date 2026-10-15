<?php use function MHW\a; use function MHW\b; use MHW\{Support, Icon}; $f = Support::flash(); ?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php /* Titolo, descrizione, robots, canonical, Open Graph e dati strutturati: Amministrazione → SEO e GEO.
         Le pagine senza $seoPagina (accesso, registrazione…) hanno noindex. */ ?>
<?= MHW\Seo::head($seoPagina ?? null, $title ?? 'MyHouse Welcome') ?>
<?php include __DIR__ . '/_tema.php'; ?>
<?php include __DIR__ . '/_icone.php'; ?>
<?php if (empty($seoPagina)) include __DIR__ . '/_condivisione.php'; ?>
<?php /* 8H: i caratteri dal nostro server (assets/fonts): niente richieste a Google; si anticipa solo Onest, il testo. */ ?>
<link rel="preload" href="<?= MHW\a('/assets/fonts/onest-latin.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= MHW\av('/assets/app.css') ?>">
</head>
<body class="cms">
<main class="wrap" style="max-width:<?= Support::e($larghezza ?? '520px') ?>;padding-top:40px;padding-bottom:48px">
  <div class="spread spread--mid">
    <a class="brand" href="<?= b() ?>/"><?= Icon::brand(26) ?><span>myhouse welcome</span></a>
    <?php include __DIR__ . '/_tema-bottone.php'; ?>
  </div>
  <?php if ($f): ?><p class="note note--<?= $f['kind'] === 'err' ? 'err' : 'ok' ?>" style="margin-top:24px" role="status"><?= Support::e($f['msg']) ?></p><?php endif; ?>
  <div style="margin-top:28px"><?= $content ?></div>
  <p class="tiny muted" style="margin-top:40px;text-align:center">
    <a href="<?= b() ?>/termini">Termini</a> · <a href="<?= b() ?>/privacy">Privacy</a><?php if (MHW\Statistiche::qui() && MHW\Statistiche::consensoServe()): ?> · <a href="#" data-consenso-apri><?= MHW\TestiSito::html('consenso.preferenze') ?></a><?php endif; ?></p>
</main>
<?php if (MHW\Statistiche::qui()) include __DIR__ . '/_statistiche.php'; /* 8H: solo pagine pubbliche che contano */ ?>
</body>
</html>
