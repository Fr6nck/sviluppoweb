<?php
/* Il telaio del pannello: area host e amministrazione.
   Una barra con le sezioni dell'account, una seconda — se si sta lavorando su
   una struttura — con Contenuti, Lingue, Aspetto, QR & Link, Statistiche. */
use function MHW\a; use function MHW\b;
use MHW\{Auth, Support, Csrf, Entitlements, Icon};
$u = Auth::user(); $f = Support::flash();
$admin = ($u['role'] ?? '') === 'admin' && !Auth::isImpersonating();
$iniziale = mb_strtoupper(mb_substr((string) ($u['name'] ?: $u['email'] ?? '?'), 0, 1));
$prop = $prop ?? null; $qui = $qui ?? ''; $nav = $nav ?? '';
$vedeStatistiche = $prop && Entitlements::can((int) $prop['account_id'], 'analytics');
// Durante la procedura di una guida mai pubblicata si vede una sola navigazione:
// i passi. Niente tab della struttura, e la verifica email diventa una riga nei passi.
$soloPassi = $prop && $qui === 'procedura' && $prop['status'] !== 'published'; ?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= Support::e($title ?? 'MyHouse Welcome') ?></title>
<?php include __DIR__ . '/_tema.php'; ?>
<?php include __DIR__ . '/_icone.php'; ?>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Gloock&family=Onest:wght@300..800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= a() ?>/assets/app.css">
</head>
<body class="cms">
<?php if (Auth::isImpersonating()): ?>
<div class="impersonating"><div class="wrap">
  <span>Stai guardando l'applicazione con l'account di un cliente. La sessione finisce da sola entro un'ora.</span>
  <form method="post" action="<?= b() ?>/admin/esci-da-cliente"><?= Csrf::field() ?><button class="btn">Torna al mio account</button></form>
</div></div>
<?php endif; ?>

<header class="topbar"><div class="wrap">
  <div class="row" style="gap:14px">
    <a class="brand" href="<?= b() ?>/<?= $admin ? 'admin' : 'pannello' ?>"><?= Icon::brand(26) ?><span>myhouse welcome</span></a>
    <?php if ($admin): ?><span class="tag">Amministrazione</span><?php endif; ?>
  </div>
  <?php if ($u && !$soloPassi): ?>
    <nav class="nav" aria-label="Sezioni dell'account">
      <?php if ($admin): ?>
        <?php foreach (['admin' => ['/admin', 'Quadro'], 'clienti' => ['/admin/clienti', 'Clienti'], 'abbonamenti' => ['/admin/abbonamenti', 'Abbonamenti'],
                        'guide' => ['/admin/guide', 'Guide'], 'pacchetti' => ['/admin/pacchetti', 'Pacchetti'],
                        'registro' => ['/admin/registro', 'Registro'], 'diagnostica' => ['/admin/diagnostica', 'Diagnostica']] as $k => [$href, $l]): ?>
          <a href="<?= b() . $href ?>" class="<?= $nav === $k ? 'on' : '' ?>"<?= $nav === $k ? ' aria-current="page"' : '' ?>><?= $l ?></a>
        <?php endforeach; ?>
      <?php else: ?>
        <a href="<?= b() ?>/pannello" class="<?= $nav === 'guide' || $prop ? 'on' : '' ?>">Le mie guide</a>
        <a href="<?= b() ?>/account" class="<?= $nav === 'account' ? 'on' : '' ?>">Account &amp; Fatturazione</a>
      <?php endif; ?>
    </nav>
    <div class="row" style="gap:10px">
      <?php include __DIR__ . '/_tema-bottone.php'; ?>
      <form method="post" action="<?= b() ?>/esci" class="row" style="gap:10px"><?= Csrf::field() ?>
        <span class="avatar" title="<?= Support::e($u['email']) ?>"><?= Support::e($iniziale) ?></span>
        <button class="btn btn--ghost btn--sm">Esci</button>
      </form>
    </div>
  <?php elseif ($u): /* nella procedura: solo il marchio e il tema, i passi fanno il resto */ ?>
    <?php include __DIR__ . '/_tema-bottone.php'; ?>
  <?php endif; ?>
</div></header>

<?php if ($u && !$admin && !Auth::isVerified($u) && !$soloPassi): ?>
<div class="banner banner--info"><div class="wrap">
  <span>Conferma la tua email (<?= Support::e($u['email']) ?>): puoi preparare la guida, ma per pubblicarla serve la conferma.</span>
  <form method="post" action="<?= b() ?>/verifica/invia"><?= Csrf::field() ?><button class="btn btn--ghost btn--sm">Mandamela di nuovo</button></form>
</div></div>
<?php endif; ?>

<?php if ($prop): ?>
<div class="propbar"><div class="wrap">
  <div class="row" style="gap:14px">
    <span class="propbar__name"><?= Support::e($prop['name']) ?></span>
    <?php if ($qui === 'procedura'): ?>
      <a class="btn btn--quiet btn--sm propbar__dopo" href="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>"><?= $soloPassi ? 'Esci, continuo dopo' : 'Continua dopo' ?></a>
    <?php endif; ?>
    <?php if (!$soloPassi): ?>
    <nav class="nav" aria-label="La guida">
      <a href="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>" class="<?= in_array($qui, ['contenuti', 'procedura'], true) ? 'on' : '' ?>">Contenuti</a>
      <a href="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/lingue" class="<?= $qui === 'lingue' ? 'on' : '' ?>">Lingue</a>
      <a href="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/aspetto" class="<?= $qui === 'aspetto' ? 'on' : '' ?>">Aspetto</a>
      <a href="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/qr" class="<?= $qui === 'qr' ? 'on' : '' ?>">QR &amp; Link</a>
      <?php if ($vedeStatistiche): ?>
        <a href="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/statistiche" class="<?= $qui === 'statistiche' ? 'on' : '' ?>">Statistiche</a>
      <?php endif; ?>
      <a href="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/impostazioni" class="<?= $qui === 'impostazioni' ? 'on' : '' ?>">Impostazioni</a>
    </nav>
    <?php endif; ?>
  </div>
  <?php if (!$soloPassi): ?>
  <div class="row" style="gap:10px">
    <a class="btn btn--ghost btn--sm" href="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/anteprima" target="_blank" rel="noopener"><?= Icon::svg('eye', 15) ?>Anteprima</a>
    <a class="btn btn--sm" href="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/procedura/pubblica">Pubblica</a>
  </div>
  <?php endif; ?>
</div></div>
<?php endif; ?>

<main class="wrap" style="padding-top:32px;padding-bottom:64px">
<?php if ($f): ?>
  <?php if ($f['kind'] === 'limite'): ?>
    <div class="limit" style="margin-bottom:24px" role="status">
      <p style="max-width:620px"><?= Support::e($f['msg']) ?></p>
      <a class="btn btn--sm" href="<?= b() ?>/piano">Scopri Plus</a>
    </div>
  <?php else: ?>
    <p class="note <?= ['err' => 'note--err', 'avviso' => ''][$f['kind']] ?? 'note--ok' ?>" style="margin-bottom:24px" role="status"><?= Support::e($f['msg']) ?></p>
  <?php endif; ?>
<?php endif; ?>
<?= $content ?>
</main>
<script src="<?= a() ?>/assets/cms.js" defer></script>
</body>
</html>
