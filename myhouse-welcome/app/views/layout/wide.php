<?php /* Come layout/app, ma il contenuto corre fino ai bordi: serve alle
         pagine costruite a colonne (il quadro, il wizard). */ ?>
<?php use function MHW\a; use function MHW\b; use MHW\{Auth, Support, Csrf};
$u = Auth::user(); $f = Support::flash();
$iniziale = strtoupper(mb_substr((string) ($u['name'] ?? $u['email'] ?? '?'), 0, 1)); ?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Support::e($title ?? 'MyHouse Welcome') ?></title>
<?php include __DIR__ . '/_tema.php'; ?>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Gloock&family=Onest:wght@300..800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= a() ?>/assets/app.css">
</head>
<body>
<?php if (Auth::isImpersonating()): ?>
<div class="impersonating"><div class="wrap">
  <span>State guardando l'applicazione con l'account di un cliente.</span>
  <form method="post" action="<?= b() ?>/admin/esci-da-cliente"><?= Csrf::field() ?><button class="btn">Torna al mio account</button></form>
</div></div>
<?php endif; ?>
<header class="topbar"><div class="wrap">
  <div class="row" style="gap:14px">
    <a class="brand" href="<?= b() ?>/">myhouse welcome</a>
    <?php if (($u['role'] ?? '') === 'admin'): ?><span class="tag">Amministrazione</span><?php endif; ?>
  </div>
  <?php if ($u): ?>
    <nav class="nav"><?= $topnav ?? '' ?></nav>
    <div class="row" style="gap:10px">
      <?= $topright ?? '' ?>
      <?php include __DIR__ . '/_tema-bottone.php'; ?>
      <form method="post" action="<?= b() ?>/esci" class="row" style="gap:10px"><?= Csrf::field() ?>
        <span class="avatar" title="<?= Support::e($u['email']) ?>"><?= Support::e($iniziale) ?></span>
        <button class="btn btn--ghost btn--sm">Esci</button>
      </form>
    </div>
  <?php endif; ?>
</div></header>
<main class="wrap" style="padding-top:36px;padding-bottom:64px">
<?php if ($f): ?>
  <p class="note note--<?= $f['kind'] === 'err' ? 'err' : 'ok' ?>" style="margin-bottom:24px"><?= Support::e($f['msg']) ?></p>
<?php endif; ?>
<?= $content ?>
</main>
</body>
</html>
