<?php
/* Il telaio del sito pubblico. Chi è già dentro vede "Le mie guide", non
   "Registrati": nessuno si registra due volte. */
use function MHW\a; use function MHW\b; use MHW\{Auth, Support, Csrf};
$u = Auth::user(); $f = Support::flash(); ?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Support::e($title ?? 'MyHouse Welcome') ?></title>
<meta name="description" content="La reception digitale per case vacanza, B&amp;B, affittacamere e agriturismi. Una guida per gli ospiti in un link e un QR.">
<?php include __DIR__ . '/_tema.php'; ?>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Gloock&family=Onest:wght@300..800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= a() ?>/assets/app.css">
</head>
<body>
<header class="topbar"><div class="wrap">
  <a class="brand" href="<?= b() ?>/">myhouse welcome</a>
  <nav class="nav" aria-label="Sito">
    <a href="<?= b() ?>/#come-funziona">Come funziona</a>
    <a href="<?= b() ?>/#piani">Piani</a>
    <a href="<?= b() ?>/#qr">Il QR</a>
  </nav>
  <div class="row" style="gap:10px">
    <?php include __DIR__ . '/_tema-bottone.php'; ?>
    <?php if ($u): ?>
      <a class="btn btn--sm" href="<?= b() ?>/<?= $u['role'] === 'admin' ? 'admin' : 'pannello' ?>"><?= $u['role'] === 'admin' ? 'Amministrazione' : 'Le mie guide' ?></a>
    <?php else: ?>
      <a class="btn btn--ghost btn--sm" href="<?= b() ?>/accedi">Accedi</a>
      <a class="btn btn--sm" href="<?= b() ?>/registrati">Crea gratis</a>
    <?php endif; ?>
  </div>
</div></header>
<main class="wrap" style="padding-top:12px;padding-bottom:40px">
<?php if ($f): ?><p class="note note--<?= $f['kind'] === 'err' ? 'err' : 'ok' ?>" style="margin:20px 0" role="status"><?= Support::e($f['msg']) ?></p><?php endif; ?>
<?= $content ?>
</main>
<footer class="wrap" style="padding-bottom:40px">
  <hr class="rule">
  <div class="spread spread--mid" style="padding-top:20px">
    <span class="small muted">MyHouse Welcome · la reception digitale della tua struttura</span>
    <span class="small"><a href="<?= b() ?>/termini">Termini</a> · <a href="<?= b() ?>/privacy">Privacy</a></span>
  </div>
</footer>
</body>
</html>
