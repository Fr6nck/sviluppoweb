<?php use MHW\Support; $title = 'QR'; $nav = 'pannello';
$url = Support::baseUrl() . '/q/' . $qr['token']; ?>
<h1>Il QR della casa</h1>
<nav class="nav noprint" style="margin-top:20px;display:inline-flex">
  <a href="/pannello/<?= (int) $p['id'] ?>">Sezioni</a>
  <a href="/pannello/<?= (int) $p['id'] ?>/lingue">Lingue</a>
  <a class="on" href="/pannello/<?= (int) $p['id'] ?>/qr">QR</a>
  <a href="/pannello/<?= (int) $p['id'] ?>/impostazioni">Impostazioni</a>
</nav>
<div class="qr-sheet" style="margin-top:24px;max-width:420px">
  <img src="/qr/<?= Support::e($qr['token']) ?>.png" width="260" height="260"
       alt="Codice QR che porta alla guida di <?= Support::e($p['name']) ?>">
  <h2 style="margin-top:18px"><?= Support::e($p['name']) ?></h2>
  <p class="muted small" style="margin-top:8px">Inquadrate per la guida della casa</p>
  <p class="tiny muted" style="margin-top:14px;word-break:break-all"><?= Support::e($url) ?></p>
</div>
<p class="note noprint" style="margin-top:20px;max-width:560px">
  Questo indirizzo <strong>non cambia mai</strong>, nemmeno se rinominate la casa o cambiate lo slug:
  il QR stampato resta valido per sempre. Scansioni finora: <?= (int) $qr['scans'] ?>.</p>
<p class="noprint" style="margin-top:16px"><button class="btn btn--ghost" onclick="window.print()">Stampa</button></p>
