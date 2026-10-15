<?php
/* Il telaio del sito pubblico. Chi è già dentro vede "Le mie guide", non
   "Registrati": nessuno si registra due volte. */
use function MHW\a; use function MHW\b; use MHW\{Auth, Support, Csrf, Icon, LinguaSito};
$u = Auth::user(); $f = Support::flash();
$U = [LinguaSito::class, 't'];
$en = LinguaSito::en();
/* 8E: l'interruttore IT / EN, solo sulle pagine che esistono nelle due lingue (home e domande). */
$itP = ['home' => '/', 'en' => '/', 'domande' => '/domande', 'en-domande' => '/domande'][$seoPagina ?? ''] ?? null;
$coppia = $itP !== null ? ['it' => $itP, 'en' => LinguaSito::COPPIE[$itP]] : null;
if ($en) { $etScuro = 'Switch to dark theme'; $etChiaro = 'Switch to light theme'; }
$lingueBottoni = function () use ($coppia, $en): string {
    if (!$coppia) return '';
    return '<span class="lingua-sito" role="group" aria-label="' . ($en ? 'Language' : 'Lingua') . '">'
         . '<a href="' . b() . $coppia['it'] . '" hreflang="it" lang="it"' . ($en ? '' : ' aria-current="true"') . '>IT</a>'
         . '<a href="' . b() . $coppia['en'] . '" hreflang="en" lang="en"' . ($en ? ' aria-current="true"' : '') . '>EN</a></span>';
}; ?>
<!doctype html>
<html lang="<?= $en ? 'en' : 'it' ?>">
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
<body>
<?php $principale = $u
    ? ['href' => b() . '/' . ($u['role'] === 'admin' ? 'admin' : 'pannello'), 'testo' => $u['role'] === 'admin' ? 'Amministrazione' : 'Le mie guide']
    : ['href' => b() . '/registrati' . ($en ? '?lang=en' : ''), 'testo' => $U('Crea gratis', 'Start free')];
      $casa = $en ? '/en' : '/';
      $voci = [[$casa . '#come-funziona', $U('Come funziona', 'How it works')], [$casa . '#qr', $U('Il QR', 'The QR code')],
               [$casa . '#piani', $U('Prezzi', 'Pricing')], [$casa . '#domande', $U('Domande', 'Questions')]];
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
  <a class="brand" href="<?= b() . ($en ? '/en' : '/') ?>"><?= Icon::brand(26) ?><span>myhouse welcome</span></a>
  <nav class="nav topbar__nav" aria-label="Sito">
    <?php foreach ($voci as [$h, $t]): ?><a href="<?= b() . $h ?>"><?= $t ?></a><?php endforeach; ?>
  </nav>
  <div class="row topbar__azioni" style="gap:10px">
    <?= $lingueBottoni() ?>
    <?php include __DIR__ . '/_tema-bottone.php'; ?>
    <?php if (!$u): ?><a class="btn btn--ghost btn--sm" href="<?= b() ?>/accedi"><?= $U('Accedi', 'Log in') ?></a><?php endif; ?>
    <a class="btn btn--sm" href="<?= $principale['href'] ?>"><?= $principale['testo'] ?></a>
    <?= $conto(false) ?>
  </div>
  <?php /* Sul telefono: una riga sola. Il menu è un <details>, quindi si apre anche senza JavaScript. */ ?>
  <div class="topbar__telefono">
    <?php /* Sul telefono stretto resta il pulsante che serve di più: «Accedi» per chi è fuori, l'iniziale per chi è dentro. */ ?>
    <?php if (!$u): ?><a class="btn btn--ghost btn--sm" href="<?= b() ?>/accedi"><?= $U('Accedi', 'Log in') ?></a><?php endif; ?>
    <a class="btn btn--sm solo-largo" href="<?= $principale['href'] ?>"><?= $principale['testo'] ?></a>
    <?= $conto(true) ?>
    <details class="menu-sito">
      <summary class="icon-btn" aria-label="Menu"><?= Icon::svg('menu', 20, 2) ?></summary>
      <nav class="menu-sito__pannello" aria-label="Sito">
        <a class="menu-sito__primo" href="<?= $principale['href'] ?>"><?= $u ? $principale['testo'] : MHW\TestiSito::html('cta.crea') ?></a>
        <?php foreach ($voci as [$h, $t]): ?><a href="<?= b() . $h ?>"><?= $t ?></a><?php endforeach; ?>
        <?php if (!$u): ?><a href="<?= b() ?>/accedi"><?= $U('Accedi', 'Log in') ?></a><?php endif; ?>
        <?php if ($coppia): ?><div class="menu-sito__tema"><span><?= $U('Lingua', 'Language') ?></span><?= $lingueBottoni() ?></div><?php endif; ?>
        <div class="menu-sito__tema"><span><?= $U('Tema', 'Theme') ?></span><?php include __DIR__ . '/_tema-bottone.php'; ?></div>
      </nav>
    </details>
  </div>
</div></header>
<main class="wrap" style="padding-top:12px;padding-bottom:40px">
<?php if ($f): ?><p class="note note--<?= $f['kind'] === 'err' ? 'err' : 'ok' ?>" style="margin:20px 0" role="status"><?= Support::e($f['msg']) ?></p><?php endif; ?>
<?= $content ?>
</main>
<?php include __DIR__ . '/_piede.php'; ?>
<script src="<?= MHW\av('/assets/posta.js') ?>" defer></script>
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
<?php if (MHW\Statistiche::qui()) include __DIR__ . '/_statistiche.php'; /* 8H: solo pagine pubbliche che contano */ ?>
</body>
</html>
