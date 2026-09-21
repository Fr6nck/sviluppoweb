<?php use function MHW\b; use MHW\{Support, View, Icon};
$title = 'QR — ' . $p['name'];
$topnav = View::render('host/_propnav', ['p' => $p, 'qui' => 'qr'], null);
$url = Support::baseUrl() . '/q/' . $qr['token']; ?>

<div class="sheet">
  <div class="stack stack--lg">
    <div class="stack stack--sm">
      <h1>Il QR della casa.</h1>
      <p class="lead">Stampatelo e appendetelo dove lo si vede: sul frigo, dietro la porta,
        accanto al router.</p>
    </div>

    <p class="note"><?= Icon::svg('info', 19) ?>
      <span>Questo indirizzo <strong>non cambia mai</strong>, nemmeno se rinominate la casa o cambiate
        l'indirizzo della guida: il foglio stampato resta valido per sempre.
        Scansioni finora: <strong><?= (int) $qr['scans'] ?></strong>.</span></p>

    <p class="noprint"><button class="btn btn--ghost" onclick="window.print()">Stampa il foglio</button></p>
  </div>

  <div class="qr-sheet">
    <img src="<?= b() ?>/qr/<?= Support::e($qr['token']) ?>.png" width="280" height="280"
         alt="Codice QR che porta alla guida di <?= Support::e($p['name']) ?>">
    <h2 style="margin-top:18px"><?= Support::e($p['name']) ?></h2>
    <p class="muted small" style="margin-top:10px">Inquadrate per la guida della casa</p>
    <p class="tiny muted" style="margin-top:14px;word-break:break-all"><?= Support::e($url) ?></p>
  </div>
</div>
