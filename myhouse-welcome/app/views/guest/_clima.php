<?php
/* Riscaldamento e aria condizionata, nella guida dell'ospite: una scheda per impianto.
   Se va da solo, si vede subito se adesso è acceso (ora italiana) e le fasce orarie;
   se lo accende l'ospite, come si fa. In fondo il consiglio per non sprecare energia.
   Contano solo i campi del modo scelto: quelli rimasti da un modo precedente non si vedono.
   Riceve dalla pagina della sezione: $sec, $d, $loc, $def, $val, $lista. */
use MHW\{Support, Icon, Guide, Clima, I18n};
$modo = ['heating' => (string) ($d['heating_mode'] ?? ''), 'ac' => (string) ($d['ac_mode'] ?? '')];
$gradi = fn(string $k) => preg_match('/^\d{1,2}$/', trim((string) ($d[$k] ?? ''))) ? trim((string) $d[$k]) : '';
$scheda = function (string $chi) use ($sec, $d, $loc, $def, $val, $lista, $modo, $gradi) {
    $m = $modo[$chi];
    if ($m === '') return;
    $caldo = $chi === 'heating';
    $titolo = I18n::t($loc, $caldo ? 'clima.heating' : 'clima.ac');
    $id = 'clima-' . $chi;
    if ($m === 'no'): ?>
      <p class="clima__assente"><?= Icon::svg($caldo ? 'flame' : 'snow', 17) ?><?= Support::e(I18n::t($loc, $caldo ? 'clima.none_heating' : 'clima.none_ac')) ?></p>
    <?php return; endif;
    $aOrari = ($caldo && $m === 'auto') || (!$caldo && $m === 'orari');
    $fasce = $aOrari ? Clima::fasce(Guide::rows($sec, $caldo ? 'heating_times' : 'ac_times', $loc, $def)) : [];
    $stato = $fasce ? Clima::stato($fasce) : null;
    $temp = $gradi($caldo ? 'heating_temp' : 'ac_temp');
    $come = $lista($caldo ? 'heating_how' : 'ac_how');
    $periodo = $caldo ? $val('heating_period') : ''; ?>
  <section class="panel clima__scheda clima__scheda--<?= $caldo ? 'caldo' : 'fresco' ?>" aria-labelledby="<?= $id ?>">
    <div class="clima__testa">
      <span class="clima__ico" aria-hidden="true"><?= Icon::svg($caldo ? 'flame' : 'snow', 20) ?></span>
      <h2 id="<?= $id ?>" class="clima__titolo"><?= Support::e($titolo) ?></h2>
    </div>
    <?php if ($stato): ?>
      <p class="clima__stato clima__stato--<?= $stato['acceso'] ? 'on' : 'off' ?>" role="status">
        <span class="clima__punto" aria-hidden="true"></span>
        <b><?= Support::e(I18n::t($loc, $stato['acceso'] ? 'clima.on_now' : 'clima.off_now')) ?></b>
        <?php if ($stato['acceso']): ?><span>· <?= Support::e(I18n::t($loc, 'clima.on_until', Clima::ora($stato['fino']))) ?></span>
        <?php elseif ($stato['prossima']): ?><span>· <?= Support::e(I18n::t($loc, $stato['domani'] ? 'clima.next_tomorrow' : 'clima.next_today', Clima::ora($stato['prossima']))) ?></span><?php endif; ?>
      </p>
    <?php endif; ?>
    <p class="clima__frase"><?= Support::e(I18n::t($loc, match (true) {
        $caldo && $m === 'auto' => 'clima.auto', $caldo => 'clima.manual', $m === 'orari' => 'clima.ac_times', default => 'clima.ac_free' })) ?></p>
    <?php if ($fasce): ?>
      <div class="clima__orari">
        <span class="kicker"><?= Support::e(I18n::t($loc, $caldo ? 'clima.when' : 'clima.ac_when')) ?></span>
        <ul class="clima__fasce">
          <?php /* Le fasce degli stessi giorni su una riga sola: «tutti i giorni — 6:30–9:00, 17:00–23:00». */
          $perGiorni = [];
          foreach ($fasce as $f) $perGiorni[implode(',', $f['days'])][] = $f;
          foreach ($perGiorni as $gruppo): ?>
            <li><span><?= Support::e(Clima::giorni($gruppo[0]['days'], $loc)) ?></span>
              <b><?= implode('<br>', array_map(fn($f) => Support::e(Clima::ora($f['from']) . ' – ' . Clima::ora($f['to'])), $gruppo)) ?></b></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
    <?php if ($temp !== '' || $periodo !== ''): ?>
      <div class="clima__dati">
        <?php if ($temp !== ''): ?><span class="clima__temp"><?= Icon::svg($caldo ? 'flame' : 'snow', 15) ?><?= Support::e(I18n::t($loc, $aOrari && $caldo ? 'clima.set_to' : 'clima.recommended', $temp)) ?></span><?php endif; ?>
        <?php if ($periodo !== ''): ?><span class="small"><?= Icon::svg('calendar', 14) ?> <?= Support::e(I18n::t($loc, 'clima.period')) ?>: <?= Support::e($periodo) ?></span><?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if ($come): ?>
      <div class="stack" style="gap:10px">
        <span class="kicker"><?= Support::e(I18n::t($loc, 'clima.how')) ?></span>
        <?php foreach ($come as $i => $passo): ?>
          <div class="step"><span class="n"><?= $i + 1 ?></span><p style="white-space:pre-line"><?= Support::e($passo) ?></p></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
<?php }; ?>
<div class="stack clima" style="margin-top:20px;gap:16px">
  <?php $scheda('heating'); $scheda('ac'); ?>

  <?php /* Il consiglio sull'energia: acceso di serie, l'host lo può spegnere. Le temperature sono quelle
           scritte dall'host, altrimenti 20 °C d'inverno e 26 °C d'estate. */
  $haCaldo = in_array($modo['heating'], ['auto', 'manuale'], true); $haFresco = in_array($modo['ac'], ['libero', 'orari'], true);
  if (($haCaldo || $haFresco) && (string) ($d['risparmio'] ?? '1') !== '0' && (string) ($d['risparmio'] ?? '1') !== ''): ?>
    <section class="clima__energia" aria-labelledby="clima-energia">
      <h2 id="clima-energia" class="clima__energia-titolo"><?= Icon::svg('info', 18) ?><?= Support::e(I18n::t($loc, 'clima.eco_title')) ?></h2>
      <p><?= Support::e(I18n::t($loc, 'clima.eco_text')) ?></p>
      <ul>
        <li><?= Support::e(I18n::t($loc, 'clima.eco_windows')) ?></li>
        <?php if ($haCaldo): ?><li><?= Support::e(I18n::t($loc, 'clima.eco_heat', $gradi('heating_temp') ?: '20')) ?></li><?php endif; ?>
        <?php if ($haFresco): ?><li><?= Support::e(I18n::t($loc, 'clima.eco_cool', $gradi('ac_temp') ?: '26')) ?></li><?php endif; ?>
        <li><?= Support::e(I18n::t($loc, 'clima.eco_out')) ?></li>
      </ul>
    </section>
  <?php endif; ?>

  <?php if ($val('note') !== ''): ?>
    <p class="note"><?= Icon::svg('info', 19) ?><span style="white-space:pre-line"><?= Support::e($val('note')) ?></span></p>
  <?php endif; ?>
</div>
