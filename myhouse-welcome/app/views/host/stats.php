<?php use function MHW\t, MHW\tf;
/* Statistiche di base (Plus): quante volte si apre la guida, quanto dal QR,
   cosa si legge, in che lingua. Eventi anonimi, niente cookie, niente profili. */
use function MHW\b;
use MHW\{Support, Icon};
$title = t('Statistiche — ') . $prop['name']; ?>
<div class="stack stack--lg" style="max-width:1040px">
  <div class="stack stack--sm">
    <h1><?= t('Statistiche.') ?></h1>
    <p class="muted small"><?= t('Ultimi 30 giorni. Contiamo le aperture in forma anonima: nessun cookie, nessun dato personale degli ospiti.') ?></p>
  </div>
<?php if (!$stats): ?>
  <div class="limit"><p style="max-width:560px"><?= t('Le statistiche di lettura sono disponibili con il piano Plus: quante volte si apre la guida, quante dal QR, quali sezioni si leggono di più e in quali lingue.') ?></p><a class="btn btn--sm" href="<?= b() ?>/piano?passa=plus"><?= t('Scopri Plus') ?></a></div>
<?php else: $max = max(1, ...array_values($stats['series'])); ?>
  <?php if ((int) $stats['views'] === 0): /* 9N · lo stato vuoto: cosa fare perché i numeri arrivino */ ?>
    <div class="panel stack" style="gap:10px">
      <b><?= t('Ancora nessuna apertura in questi 30 giorni.') ?></b>
      <p class="small" style="margin:0"><?= t('I numeri arrivano quando gli ospiti aprono la guida: manda il link prima dell\'arrivo e metti il QR in vista, all\'ingresso o sul tavolo.') ?></p>
      <div class="row" style="gap:8px"><a class="btn btn--sm" href="<?= b() ?>/pannello/<?= (int) $prop['id'] ?>/qr"><?= Icon::svg('qr', 15) ?><?= t('Condividi il QR e il link') ?></a></div>
    </div>
  <?php endif; ?>
  <?php $v = (int) $stats['views']; $q = min($v, (int) $stats['qr']); $perc = $v ? (int) round($q / $v * 100) : 0; ?>
  <div class="statistiche-testa">
    <div class="panel anello">
      <div class="anello__cerchio" style="--p:<?= $perc ?>" role="img" aria-label="<?= Support::e(tf('{n} aperture: {q} dal QR, {l} dal link', ['{n}' => $v, '{q}' => $q, '{l}' => $v - $q])) ?>">
        <span class="anello__centro"><b><?= $v ?></b><span><?= t('aperture') ?></span></span>
      </div>
      <ul class="legenda">
        <li><i></i><?= t('Dal QR') ?><b><?= $q ?></b></li>
        <li><i></i><?= t('Dal link') ?><b><?= $v - $q ?></b></li>
      </ul>
    </div>
    <div class="cifre">
      <div class="cifra">
        <span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg('eye', 17) ?></span><?= t('In totale') ?></span>
        <b class="cifra__valore"><?= $v ?></b><span class="cifra__nota"><?= t('aperture della guida negli ultimi 30 giorni') ?></span></div>
      <div class="cifra cifra--mare">
        <span class="cifra__testa"><span class="cifra__ico"><?= Icon::svg('qr', 17) ?></span><?= t('Aperture dal QR') ?></span>
        <b class="cifra__valore"><?= $q ?></b><span class="cifra__nota"><?= $v ? Support::e(tf('{n}% del totale', ['{n}' => $perc])) : t('ancora nessuna apertura') ?></span></div>
    </div>
  </div>
  <div class="panel stack">
    <span class="kicker"><?= t('Aperture al giorno') ?></span>
    <div class="bars" role="img" aria-label="<?= MHW\Support::e(t('Aperture al giorno negli ultimi 30 giorni')) ?>">
      <?php foreach ($stats['series'] as $g => $n): ?><i style="height:<?= max(2, (int) round($n / $max * 100)) ?>%" title="<?= Support::e(MHW\Pannello::data($g)) ?>: <?= (int) $n ?>"></i><?php endforeach; ?>
    </div>
    <?php if (!$stats['views']): ?><p class="small muted"><?= t('Ancora nessuna apertura in questo periodo.') ?></p><?php endif; ?>
  </div>
  <div class="grid grid-2" style="align-items:start">
    <div class="panel stack"><span class="kicker"><?= t('Sezioni più lette') ?></span>
      <?php $top = max(1, ...array_map(fn($s) => (int) $s['n'], $stats['sections'] ?: [['n' => 1]]));
            foreach ($stats['sections'] as $s): ?>
        <div class="hbar"><span><?= Support::e($s['title'] ?: MHW\SectionCatalog::title($s['kind'], 'it')) ?></span><i><b style="width:<?= (int) round($s['n'] / $top * 100) ?>%"></b></i><span><?= (int) $s['n'] ?></span></div>
      <?php endforeach; if (!$stats['sections']): ?><p class="small muted"><?= t('Ancora nessun dato.') ?></p><?php endif; ?>
    </div>
    <div class="panel stack"><span class="kicker"><?= t('Voti dei tuoi ospiti') ?></span>
      <?php $voti = $stats['voti'] ?? array_fill(1, 5, 0); $totV = array_sum($voti); $topV = max(1, ...array_values($voti)); ?>
      <?php if ($totV): ?>
        <p class="small" style="margin:0"><?= tf($totV === 1 ? 'Media {media} su 5, 1 voto nel commiato.' : 'Media {media} su 5, {n} voti nel commiato.', ['{media}' => '<b>' . number_format(array_sum(array_map(fn($n, $q) => $n * $q, array_keys($voti), $voti)) / $totV, 1, MHW\Pannello::en() ? '.' : ',', '') . '</b>', '{n}' => $totV]) ?></p>
        <?php for ($n = 5; $n >= 1; $n--): ?>
          <div class="hbar"><span><?= str_repeat('★', $n) ?></span><i><b style="width:<?= (int) round($voti[$n] / $topV * 100) ?>%"></b></i><span><?= (int) $voti[$n] ?></span></div>
        <?php endfor; ?>
      <?php else: ?><p class="small muted"><?= t('Ancora nessun voto. Gli ospiti votano con le stelle nella pagina di saluto; il voto è anonimo.') ?></p><?php endif; ?>
    </div>
    <div class="panel stack"><span class="kicker"><?= t('Lingue') ?></span>
      <?php $tot = max(1, array_sum(array_map(fn($l) => (int) $l['n'], $stats['languages'])));
            $nomi = MHW\Config::get('locales');
            foreach ($stats['languages'] as $l): ?>
        <div class="hbar"><span><?= Support::e($nomi[$l['locale']] ?? $l['locale']) ?></span><i><b style="width:<?= (int) round($l['n'] / $tot * 100) ?>%"></b></i><span><?= (int) round($l['n'] / $tot * 100) ?>%</span></div>
      <?php endforeach; if (!$stats['languages']): ?><p class="small muted"><?= t('Ancora nessun dato.') ?></p><?php endif; ?>
    </div>
  </div>
<?php endif; ?>
</div>
