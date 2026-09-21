<?php use function MHW\b; use MHW\{Auth, Support, Csrf}; $u = Auth::user(); $f = Support::flash(); ?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Support::e($title ?? 'MyHouse Welcome') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Gloock&family=Onest:wght@300..800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= b() ?>/assets/app.css">
</head>
<body>
<?php if (Auth::isImpersonating()): ?>
<div class="impersonating"><div class="wrap">
  <span>State guardando l'applicazione con l'account di un cliente.</span>
  <form method="post" action="<?= b() ?>/admin/esci-da-cliente"><?= Csrf::field() ?><button class="btn">Torna al mio account</button></form>
</div></div>
<?php endif; ?>
<header class="topbar"><div class="wrap">
  <a class="brand" href="<?= b() ?>/">myhouse welcome</a>
  <?php if ($u): ?>
    <nav class="nav">
      <?php if ($u['role'] === 'admin'): ?>
        <a href="<?= b() ?>/admin" class="<?= ($nav ?? '') === 'clienti' ? 'on' : '' ?>">Clienti</a>
        <a href="<?= b() ?>/admin/pacchetti" class="<?= ($nav ?? '') === 'pacchetti' ? 'on' : '' ?>">Pacchetti</a>
        <a href="<?= b() ?>/admin/diagnostica">Diagnostica</a>
      <?php endif; ?>
      <a href="<?= b() ?>/pannello" class="<?= ($nav ?? '') === 'pannello' ? 'on' : '' ?>">Le mie guide</a>
    </nav>
    <form method="post" action="<?= b() ?>/esci" class="row"><?= Csrf::field() ?>
      <span class="small muted"><?= Support::e($u['email']) ?></span>
      <button class="btn btn--ghost btn--sm">Esci</button>
    </form>
  <?php else: ?>
    <div class="row"><a class="btn btn--ghost btn--sm" href="<?= b() ?>/accedi">Accedi</a>
      <a class="btn btn--sm" href="<?= b() ?>/registrati">Create la vostra guida</a></div>
  <?php endif; ?>
</div></header>
<main class="wrap" style="padding-top:28px;padding-bottom:56px">
<?php if ($f): ?><p class="note note--<?= $f['kind'] === 'err' ? 'err' : 'ok' ?>"><?= Support::e($f['msg']) ?></p><?php endif; ?>
<?= $content ?>
</main>
</body>
</html>
