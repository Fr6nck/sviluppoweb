<?php
/* Contenuti di una struttura: stato della guida, il blocco Check-in &
   Check-out sempre in testa, le sezioni aggiuntive, il catalogo. A destra il
   telefono con l'anteprima. */
use function MHW\b;
use MHW\{Support, Csrf, Icon, SectionCatalog};
$title = $prop['name'];
$pid = (int) $prop['id'];
$pubblicata = $prop['status'] === 'published';
$core = null; $altre = [];
foreach ($sections as $s) { if ((int) $s['is_core'] === 1) $core = $s; else $altre[] = $s; } ?>

<div class="editor">
  <div class="stack stack--lg">
    <div class="spread">
      <div class="stack stack--sm">
        <h1><?= Support::e($prop['name']) ?>.</h1>
        <div class="row" style="gap:10px">
          <?php if (!$pubblicata): ?>
            <span class="badge badge--ochre"><span class="dot"></span>Bozza</span>
            <span class="small muted">Gli ospiti non la vedono ancora.</span>
          <?php elseif ($online): ?>
            <span class="badge badge--pine"><span class="dot"></span>Online</span>
            <span class="small muted"><?= $pending ? 'Ci sono modifiche non ancora pubblicate.' : 'Gli ospiti vedono la versione aggiornata.' ?></span>
          <?php else: ?>
            <span class="badge badge--alert"><span class="dot"></span>Offline</span>
            <span class="small muted">L'abbonamento non è attivo: i contenuti sono salvati, la guida tornerà online quando lo rinnovi.</span>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($sub && $pubblicata && !$pending): ?>
        <span class="row small muted" style="gap:6px"><?= Icon::svg('check', 16, 2) ?>Tutto pubblicato</span>
      <?php elseif ($sub && $pubblicata): ?>
        <form method="post" action="<?= b() ?>/pannello/<?= $pid ?>/pubblica" style="margin:0"><?= Csrf::field() ?>
          <button class="btn btn--go">Pubblica le modifiche <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></button></form>
      <?php else: ?>
        <a class="btn btn--go" href="<?= b() ?>/pannello/<?= $pid ?>/procedura/<?= $pubblicata || $prop['wizard_step'] === 'fatto' ? 'pubblica' : Support::e($prop['wizard_step'] ?: 'struttura') ?>">
          <?= $pubblicata ? 'Riattiva la guida' : 'Continua la configurazione' ?> <span class="go"><?= Icon::svg('arrow', 18, 2) ?></span></a>
      <?php endif; ?>
    </div>

    <?php if ($problemi): ?>
      <div class="note" role="status"><?= Icon::svg('info', 19) ?>
        <div class="stack" style="gap:6px"><b>Prima di pubblicare</b>
          <?php foreach ($problemi as $pr): ?><span><?= Support::e($pr) ?></span><?php endforeach; ?></div></div>
    <?php endif; ?>

    <?php if ($stats): ?>
      <div class="cifre">
        <a class="cifra" href="<?= b() ?>/pannello/<?= $pid ?>/statistiche">
          <span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg('eye', 17) ?></span>Aperture</span><?= Icon::svg('arrow', 18, 2, 'cifra__freccia') ?>
          <b class="cifra__valore"><?= (int) $stats['views'] ?></b><span class="cifra__nota">negli ultimi 30 giorni</span></a>
        <div class="cifra cifra--mare">
          <span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg('qr', 17) ?></span>Dal QR</span>
          <b class="cifra__valore"><?= (int) $stats['qr'] ?></b><span class="cifra__nota">aperture inquadrando il QR</span></div>
        <div class="cifra cifra--ocra">
          <span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg('book', 17) ?></span>La più letta</span>
          <b class="cifra__valore cifra__valore--testo"><?= Support::e($stats['sections'][0]['title'] ?? '—') ?></b><span class="cifra__nota">la sezione più aperta</span></div>
      </div>
    <?php endif; ?>

    <?php if ($core): ?>
    <div class="stack" style="gap:10px">
      <h2 style="font-size:22px">Sempre inclusa</h2>
      <a class="rowcard <?= $core['empty'] ? 'rowcard--flag' : '' ?>" href="<?= b() ?>/pannello/<?= $pid ?>/sezioni/<?= (int) $core['id'] ?>">
        <span style="color:var(--accent);display:flex"><?= Icon::svg('home', 20) ?></span>
        <b class="grow"><?= Support::e($core['title']) ?></b>
        <?= $core['empty'] ? '<span class="badge badge--terracotta">Da compilare</span>' : '<span class="badge badge--pine">Pronta</span>' ?>
        <span class="small muted">Modifica</span>
      </a>
      <p class="tiny muted">Check-in &amp; Check-out non conta nel limite delle sezioni del piano.</p>
    </div>
    <?php endif; ?>

    <?php $sezioni = $altre; $torna = ''; include __DIR__ . '/_sezioni.php'; ?>

    <?php include __DIR__ . '/_sito_invito.php'; ?>

    <a class="btn btn--ghost phonebtn" href="<?= b() ?>/pannello/<?= $pid ?>/anteprima" target="_blank" rel="noopener"><?= Icon::svg('eye', 16) ?>Apri l'anteprima</a>
  </div>
  <?php include __DIR__ . '/_telefono.php'; ?>
</div>
