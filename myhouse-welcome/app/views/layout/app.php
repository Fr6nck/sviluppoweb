<?php
/* Il telaio del sito pubblico. Chi è già dentro vede "Le mie guide", non
   "Registrati": nessuno si registra due volte. */
use function MHW\a; use function MHW\b; use MHW\{Auth, Support, Csrf, Icon};
$u = Auth::user(); $f = Support::flash(); ?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Support::e($title ?? 'MyHouse Welcome') ?></title>
<meta name="description" content="La reception digitale per case vacanza, B&amp;B, affittacamere e agriturismi. Una guida per gli ospiti in un link e un QR.">
<?php include __DIR__ . '/_tema.php'; ?>
<?php include __DIR__ . '/_icone.php'; ?>
<?php include __DIR__ . '/_condivisione.php'; ?>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Gloock&family=Onest:wght@300..800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= a() ?>/assets/app.css">
</head>
<body>
<?php $principale = $u
    ? ['href' => b() . '/' . ($u['role'] === 'admin' ? 'admin' : 'pannello'), 'testo' => $u['role'] === 'admin' ? 'Amministrazione' : 'Le mie guide']
    : ['href' => b() . '/registrati', 'testo' => 'Crea gratis'];
      $voci = [['/#come-funziona', 'Come funziona'], ['/#piani', 'Piani'], ['/#qr', 'Il QR']]; ?>
<header class="topbar topbar--sito"><div class="wrap">
  <a class="brand" href="<?= b() ?>/"><?= Icon::brand(26) ?><span>myhouse welcome</span></a>
  <nav class="nav topbar__nav" aria-label="Sito">
    <?php foreach ($voci as [$h, $t]): ?><a href="<?= b() . $h ?>"><?= $t ?></a><?php endforeach; ?>
  </nav>
  <div class="row topbar__azioni" style="gap:10px">
    <?php include __DIR__ . '/_tema-bottone.php'; ?>
    <?php if (!$u): ?><a class="btn btn--ghost btn--sm" href="<?= b() ?>/accedi">Accedi</a><?php endif; ?>
    <a class="btn btn--sm" href="<?= $principale['href'] ?>"><?= $principale['testo'] ?></a>
  </div>
  <?php /* Sul telefono: una riga sola. Il menu è un <details>, quindi si apre anche senza JavaScript. */ ?>
  <div class="topbar__telefono">
    <a class="btn btn--sm" href="<?= $principale['href'] ?>"><?= $principale['testo'] ?></a>
    <details class="menu-sito">
      <summary class="icon-btn" aria-label="Menu"><?= Icon::svg('menu', 20, 2) ?></summary>
      <nav class="menu-sito__pannello" aria-label="Sito">
        <?php foreach ($voci as [$h, $t]): ?><a href="<?= b() . $h ?>"><?= $t ?></a><?php endforeach; ?>
        <?php if (!$u): ?><a href="<?= b() ?>/accedi">Accedi</a><?php endif; ?>
        <div class="menu-sito__tema"><span>Tema</span><?php include __DIR__ . '/_tema-bottone.php'; ?></div>
      </nav>
    </details>
  </div>
</div></header>
<main class="wrap" style="padding-top:12px;padding-bottom:40px">
<?php if ($f): ?><p class="note note--<?= $f['kind'] === 'err' ? 'err' : 'ok' ?>" style="margin:20px 0" role="status"><?= Support::e($f['msg']) ?></p><?php endif; ?>
<?= $content ?>
</main>
<?php include __DIR__ . '/_piede.php'; ?>
<script>
/* Il menu del telefono si chiude quando si sceglie una voce o si tocca fuori. */
document.addEventListener('click', function (e) {
  var m = document.querySelector('.menu-sito[open]');
  if (m && (!m.contains(e.target) || e.target.closest('a'))) m.open = false;
});
</script>
</body>
</html>
