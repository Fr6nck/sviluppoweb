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
<link rel="stylesheet" href="<?= MHW\av('/assets/app.css') ?>">
</head>
<body>
<?php $principale = $u
    ? ['href' => b() . '/' . ($u['role'] === 'admin' ? 'admin' : 'pannello'), 'testo' => $u['role'] === 'admin' ? 'Amministrazione' : 'Le mie guide']
    : ['href' => b() . '/registrati', 'testo' => 'Crea gratis'];
      $voci = [['/#come-funziona', 'Come funziona'], ['/#qr', 'Il QR'], ['/#domande', 'Domande'], ['/#piani', 'Piani']];
      /* Chi è dentro si riconosce subito: l'iniziale e il nome in testata, con il menu
         dell'account (le guide, i dati, esci). È un <details>: si apre anche senza JavaScript. */
      $nomeU = $u ? trim((string) ($u['name'] ?? '')) : '';
      $primoNome = $nomeU !== '' ? explode(' ', $nomeU)[0] : '';
      $iniziale = $u ? mb_strtoupper(mb_substr($nomeU !== '' ? $nomeU : (string) $u['email'], 0, 1)) : '';
      $conto = function (bool $soloIniziale) use ($u, $primoNome, $iniziale, $principale): string {
          if (!$u) return '';
          $h = '<details class="conto' . ($soloIniziale ? ' conto--corto' : '') . '">'
             . '<summary class="conto__chip" aria-label="Il tuo account: ' . Support::e($u['email']) . '">'
             . '<span class="conto__avatar" aria-hidden="true">' . Support::e($iniziale) . '</span>'
             . ($soloIniziale ? '' : '<span class="conto__nome">' . Support::e($primoNome !== '' ? $primoNome : 'Il tuo account') . '</span>' . Icon::svg('chevron', 14, 2, 'conto__freccia'))
             . '</summary><div class="conto__pannello">'
             . '<p class="conto__chi"><span class="small muted">Sei dentro come</span><b>' . Support::e($u['email']) . '</b></p>'
             . '<a href="' . $principale['href'] . '">' . Icon::svg($u['role'] === 'admin' ? 'sliders' : 'grid', 17) . Support::e($principale['testo']) . '</a>'
             . ($u['role'] === 'admin' ? '' : '<a href="' . b() . '/account">' . Icon::svg('card', 17) . 'Account &amp; Fatturazione</a>')
             . '<form method="post" action="' . b() . '/esci">' . Csrf::field() . '<button type="submit" class="conto__esci">' . Icon::svg('logout', 17) . 'Esci</button></form>'
             . '</div></details>';
          return $h;
      }; ?>
<header class="topbar topbar--sito"><div class="wrap">
  <a class="brand" href="<?= b() ?>/"><?= Icon::brand(26) ?><span>myhouse welcome</span></a>
  <nav class="nav topbar__nav" aria-label="Sito">
    <?php foreach ($voci as [$h, $t]): ?><a href="<?= b() . $h ?>"><?= $t ?></a><?php endforeach; ?>
  </nav>
  <div class="row topbar__azioni" style="gap:10px">
    <?php include __DIR__ . '/_tema-bottone.php'; ?>
    <?php if (!$u): ?><a class="btn btn--ghost btn--sm" href="<?= b() ?>/accedi">Accedi</a><?php endif; ?>
    <a class="btn btn--sm" href="<?= $principale['href'] ?>"><?= $principale['testo'] ?></a>
    <?= $conto(false) ?>
  </div>
  <?php /* Sul telefono: una riga sola. Il menu è un <details>, quindi si apre anche senza JavaScript. */ ?>
  <div class="topbar__telefono">
    <?php /* Sul telefono stretto resta il pulsante che serve di più: «Accedi» per chi è fuori, l'iniziale per chi è dentro. */ ?>
    <?php if (!$u): ?><a class="btn btn--ghost btn--sm" href="<?= b() ?>/accedi">Accedi</a><?php endif; ?>
    <a class="btn btn--sm solo-largo" href="<?= $principale['href'] ?>"><?= $principale['testo'] ?></a>
    <?= $conto(true) ?>
    <details class="menu-sito">
      <summary class="icon-btn" aria-label="Menu"><?= Icon::svg('menu', 20, 2) ?></summary>
      <nav class="menu-sito__pannello" aria-label="Sito">
        <a class="menu-sito__primo" href="<?= $principale['href'] ?>"><?= $u ? $principale['testo'] : 'Crea gratis la tua guida' ?></a>
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
/* Il menu del telefono e quello dell'account si chiudono quando si sceglie una voce, si tocca fuori o si preme Esc. */
document.addEventListener('click', function (e) {
  document.querySelectorAll('.menu-sito[open],.conto[open]').forEach(function (m) {
    if (!m.contains(e.target) || e.target.closest('a')) m.open = false;
  });
});
document.addEventListener('keydown', function (e) {
  if (e.key !== 'Escape') return;
  document.querySelectorAll('.menu-sito[open],.conto[open]').forEach(function (m) { m.open = false; m.querySelector('summary').focus(); });
});
</script>
</body>
</html>
