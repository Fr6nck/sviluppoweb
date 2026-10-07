<?php
/* Amministrazione → Anomalie: le cose che non tornano, dalla più grave. Ogni voce dice cosa fare;
   in fondo i controlli andati bene, così si vede anche cosa è stato guardato. */
use function MHW\b;
use MHW\{Support, Icon};
$title = 'Anomalie';
$tono = ['alta' => ['Subito', 'alert'], 'media' => ['Da guardare', 'ochre'], 'bassa' => ['Quando hai tempo', 'sea']]; ?>
<div class="stack stack--lg">
  <div class="stack stack--sm">
    <h1>Anomalie.</h1>
    <p class="muted">Controlli fatti adesso sul database, sul registro tecnico e sui promemoria. I clienti di esempio non contano.</p>
  </div>

  <?php if (!$voci): ?>
    <p class="note" role="status" style="background:var(--pine-soft)">Niente da segnalare: tutti i controlli sono andati bene.</p>
  <?php endif; ?>

  <?php foreach ($voci as $i => $v): [$etichetta, $colore] = $tono[$v['gravita']]; ?>
    <section class="panel stack anomalia anomalia--<?= $v['gravita'] ?>" style="gap:10px" aria-labelledby="anom-<?= $i ?>">
      <div class="anomalia__testa">
        <h2 id="anom-<?= $i ?>" style="font-size:20px;margin:0"><?= Support::e($v['titolo']) ?> <span class="muted" style="font-size:16px">· <?= count($v['righe']) ?></span></h2>
        <span class="badge badge--<?= $colore ?>"><?= $etichetta ?></span>
      </div>
      <p class="small" style="margin:0"><?= Support::e($v['cosa']) ?></p>
      <ul class="anomalia__righe">
        <?php foreach ($v['righe'] as [$testo, $dettaglio, $link]): ?>
          <li><span><?= $link !== '' ? '<a href="' . b() . Support::e($link) . '"><b>' . Support::e($testo) . '</b></a>' : '<b>' . Support::e($testo) . '</b>' ?></span>
            <span class="small muted"><?= Support::e($dettaglio) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endforeach; ?>

  <?php if ($superati): ?>
    <section class="stack" style="gap:10px" aria-labelledby="superati-titolo">
      <h2 id="superati-titolo" style="font-size:18px">Controlli andati bene</h2>
      <ul class="superati"><?php foreach ($superati as $t): ?><li><?= Icon::svg('check', 14, 2) ?><?= Support::e($t) ?></li><?php endforeach; ?></ul>
    </section>
  <?php endif; ?>
</div>
