<?php use function MHW\t, MHW\tf;
/* Il QR e il link, con i download. Riceve: $prop, $qr. */
use function MHW\b;
use MHW\{Support, Icon};
$pid = (int) $prop['id'];
$link = Support::baseUrl() . '/g/' . $prop['slug'];
$corto = Support::baseUrl() . '/q/' . $qr['token']; ?>
<div class="grid grid-2" style="align-items:start;gap:24px">
  <div class="qr-sheet" style="text-align:center">
    <img src="<?= b() ?>/qr/<?= Support::e($qr['token']) ?>.png" alt="<?= Support::e(tf('QR della guida di {nome}', ['{nome}' => $prop['name']])) ?>" width="280" height="280">
    <p class="small" style="margin-top:12px;color:#231b12"><?= t('Inquadra per aprire la guida') ?></p>
  </div>
  <div class="stack">
    <div class="fieldset">
      <span class="legend"><?= t('Link della guida') ?></span>
      <div class="copyline"><code class="small" style="word-break:break-all"><?= Support::e($link) ?></code>
        <button type="button" class="btn btn--ghost btn--sm" data-copia="<?= Support::e($link) ?>" data-copiato="<?= t('Link copiato') ?>" hidden><?= Icon::svg('copy', 15) ?><?= t('Copia') ?></button></div>
      <p class="help"><?= t('Mandalo prima dell\'arrivo, su WhatsApp o per email.') ?></p>
    </div>
    <div class="fieldset">
      <span class="legend"><?= t('Scarica il QR') ?></span>
      <div class="row" style="gap:8px">
        <a class="btn btn--sm" href="<?= b() ?>/pannello/<?= $pid ?>/qr.pdf" download><?= t('PDF da stampare') ?></a>
        <a class="btn btn--ghost btn--sm" href="<?= b() ?>/pannello/<?= $pid ?>/qr.svg" download><?= t('SVG da stampare') ?></a>
        <a class="btn btn--ghost btn--sm" href="<?= b() ?>/pannello/<?= $pid ?>/qr.png" download><?= t('PNG (solo il codice)') ?></a>
      </div>
      <p class="help"><?= t('Il foglio da stampare ha il logo e il nome della struttura, il QR e l\'invito a inquadrarlo in italiano e in inglese. Il PNG è solo il codice, da mettere nelle tue grafiche.') ?></p>
      <p class="help" style="overflow-wrap:anywhere"><?= Support::e(tf('Il QR resta sempre lo stesso: lo stampi una volta, e ogni volta che aggiorni la guida gli ospiti vedono la versione nuova. Punta a {link}.', ['{link}' => $corto])) ?></p>
    </div>
  </div>
</div>
