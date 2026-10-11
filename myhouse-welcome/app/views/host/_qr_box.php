<?php
/* Il QR e il link, con i download. Riceve: $prop, $qr. */
use function MHW\b;
use MHW\{Support, Icon};
$pid = (int) $prop['id'];
$link = Support::baseUrl() . '/g/' . $prop['slug'];
$corto = Support::baseUrl() . '/q/' . $qr['token']; ?>
<div class="grid grid-2" style="align-items:start;gap:24px">
  <div class="qr-sheet" style="text-align:center">
    <img src="<?= b() ?>/qr/<?= Support::e($qr['token']) ?>.png" alt="QR della guida di <?= Support::e($prop['name']) ?>" width="280" height="280">
    <p class="small" style="margin-top:12px;color:#231b12">Inquadra per aprire la guida</p>
  </div>
  <div class="stack">
    <div class="fieldset">
      <span class="legend">Link della guida</span>
      <div class="copyline"><code class="small" style="word-break:break-all"><?= Support::e($link) ?></code>
        <button type="button" class="btn btn--ghost btn--sm" data-copia="<?= Support::e($link) ?>" data-copiato="Link copiato" hidden><?= Icon::svg('copy', 15) ?>Copia</button></div>
      <p class="help">Mandalo prima dell'arrivo, su WhatsApp o per email.</p>
    </div>
    <div class="fieldset">
      <span class="legend">Scarica il QR</span>
      <div class="row" style="gap:8px">
        <a class="btn btn--sm" href="<?= b() ?>/pannello/<?= $pid ?>/qr.pdf" download>PDF da stampare</a>
        <a class="btn btn--ghost btn--sm" href="<?= b() ?>/pannello/<?= $pid ?>/qr.svg" download>SVG da stampare</a>
        <a class="btn btn--ghost btn--sm" href="<?= b() ?>/pannello/<?= $pid ?>/qr.png" download>PNG (solo il codice)</a>
      </div>
      <p class="help">Il foglio da stampare ha il logo e il nome della struttura, il QR e l'invito a inquadrarlo in italiano e in inglese. Il PNG è solo il codice, da mettere nelle tue grafiche.</p>
      <p class="help" style="overflow-wrap:anywhere">Il QR resta sempre lo stesso: lo stampi una volta, e ogni volta che aggiorni la guida gli ospiti vedono la versione nuova.
        Punta a <?= Support::e($corto) ?>.</p>
    </div>
  </div>
</div>
