<?php
/* Statistiche di base (Plus): quante volte si apre la guida, quanto dal QR,
   cosa si legge, in che lingua. Eventi anonimi, niente cookie, niente profili. */
use function MHW\b;
use MHW\Support;
$title = 'Statistiche — ' . $prop['name']; ?>
<div class="stack stack--lg" style="max-width:860px">
  <div class="stack stack--sm">
    <h1>Statistiche.</h1>
    <p class="muted small">Ultimi 30 giorni. Contiamo le aperture in forma anonima: nessun cookie, nessun dato personale degli ospiti.</p>
  </div>
<?php if (!$stats): ?>
  <div class="limit"><p style="max-width:560px">Le statistiche di lettura sono comprese dal piano Plus: quante volte si apre la guida,
    quante dal QR, quali sezioni si leggono di più e in quali lingue.</p><a class="btn btn--sm" href="<?= b() ?>/piano">Scopri Plus</a></div>
<?php else: $max = max(1, ...array_values($stats['series'])); ?>
  <div class="grid grid-2">
    <div class="stat"><b><?= (int) $stats['views'] ?></b><span>aperture della guida</span></div>
    <div class="stat"><b><?= (int) $stats['qr'] ?></b><span>aperture dal QR Code</span></div>
  </div>
  <div class="panel stack">
    <span class="kicker">Aperture al giorno</span>
    <div class="bars" role="img" aria-label="Aperture al giorno negli ultimi 30 giorni">
      <?php foreach ($stats['series'] as $g => $n): ?><i style="height:<?= max(2, (int) round($n / $max * 100)) ?>%" title="<?= Support::e(Support::date($g)) ?>: <?= (int) $n ?>"></i><?php endforeach; ?>
    </div>
    <?php if (!$stats['views']): ?><p class="small muted">Ancora nessuna apertura in questo periodo.</p><?php endif; ?>
  </div>
  <div class="grid grid-2" style="align-items:start">
    <div class="panel stack"><span class="kicker">Sezioni più lette</span>
      <?php $top = max(1, ...array_map(fn($s) => (int) $s['n'], $stats['sections'] ?: [['n' => 1]]));
            foreach ($stats['sections'] as $s): ?>
        <div class="hbar"><span><?= Support::e($s['title'] ?: MHW\SectionCatalog::title($s['kind'], 'it')) ?></span><i><b style="width:<?= (int) round($s['n'] / $top * 100) ?>%"></b></i><span><?= (int) $s['n'] ?></span></div>
      <?php endforeach; if (!$stats['sections']): ?><p class="small muted">Nessun dato.</p><?php endif; ?>
    </div>
    <div class="panel stack"><span class="kicker">Lingue</span>
      <?php $tot = max(1, array_sum(array_map(fn($l) => (int) $l['n'], $stats['languages'])));
            $nomi = MHW\Config::get('locales');
            foreach ($stats['languages'] as $l): ?>
        <div class="hbar"><span><?= Support::e($nomi[$l['locale']] ?? $l['locale']) ?></span><i><b style="width:<?= (int) round($l['n'] / $tot * 100) ?>%"></b></i><span><?= (int) round($l['n'] / $tot * 100) ?>%</span></div>
      <?php endforeach; if (!$stats['languages']): ?><p class="small muted">Nessun dato.</p><?php endif; ?>
    </div>
  </div>
<?php endif; ?>
</div>
