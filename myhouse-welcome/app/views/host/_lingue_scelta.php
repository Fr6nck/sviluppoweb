<?php
/* Le caselle delle lingue della guida (8C), con le regole del piano (Entitlements::regolaLingue):
     Essential — la principale e UNA seconda a scelta tra tutte: una scelta sola (radio);
     Plus e Portfolio — la principale, le altre lingue di base (caselle) e UNA lingua aggiuntiva (radio).
   Quello che il piano non comprende si vede col lucchetto e apre «Più lingue sono comprese in Plus».
   Il server controlla comunque (Properties::setLocales). Riceve: $prop, $lingueAttive, $tutte. */
use MHW\{Support, Icon, I18n, Entitlements};
$defS = (string) $prop['default_locale'];
$aidS = (int) $prop['account_id'];
$nS = Entitlements::limit($aidS, 'locales', 1);
$xS = Entitlements::limit($aidS, 'lingua_extra', 0);
$tenuteS = Entitlements::lingueGuida($aidS, $defS, $lingueAttive);
$baseS = array_filter($tutte, fn($c) => I18n::base($c) && $c !== $defS, ARRAY_FILTER_USE_KEY);
$aggS = array_filter($tutte, fn($c) => !I18n::base($c) && $c !== $defS, ARRAY_FILTER_USE_KEY);
$attivaS = fn(string $c) => in_array($c, $lingueAttive, true);
$daTogliere = fn(string $c) => $attivaS($c) && !in_array($c, $tenuteS, true)
    ? ' <span class="badge badge--alert">da togliere</span>' : '';
$casella = function (string $code, string $nome, string $tipo, bool $lucchetto = false) use ($attivaS, $daTogliere) {
    $id = 'lingua-' . $code; ?>
  <label class="scelta">
    <?php if ($lucchetto): ?>
      <input type="checkbox" value="<?= Support::e($code) ?>" data-sblocca="lingue" aria-describedby="<?= $id ?>-piano">
    <?php else: ?>
      <input type="<?= $tipo ?>" name="locali[]" value="<?= Support::e($code) ?>" <?= $attivaS($code) ? 'checked' : '' ?>>
    <?php endif; ?>
    <span class="scelta__testo"><span lang="<?= Support::e($code) ?>"><?= Support::e($nome) ?></span><?php if ($lucchetto): ?> <span class="small muted" id="<?= $id ?>-piano"><?= Icon::svg('lock', 13, 2) ?> con il piano Plus</span><?php endif; ?><?= $daTogliere($code) ?></span>
  </label>
<?php };
$nessuna = fn(array $gruppo) => !array_filter(array_keys($gruppo), $attivaS); ?>
<input type="hidden" name="locali[]" value="<?= Support::e($defS) ?>">
<p class="small" style="margin:0"><b><?= Support::e($tutte[$defS] ?? $defS) ?></b> <span class="muted">· lingua principale (la cambi in Impostazioni)</span></p>

<?php if ($xS <= 0): /* Essential: in tutto $nS lingue, la seconda (e le altre) a scelta tra tutte */ ?>
  <?php if ($nS >= 3): ?>
    <p class="help" style="margin:0">Fino a <?= $nS ?> lingue in tutto, compresa la principale.</p>
    <div class="scelte"><?php foreach ($baseS + $aggS as $code => $nome) $casella($code, $nome, 'checkbox'); ?></div>
  <?php elseif ($nS === 2): ?>
    <p class="help" style="margin:0">Il tuo piano comprende due lingue: la principale e una seconda, quella che vuoi.</p>
    <div class="scelte scelte--riga" role="radiogroup" aria-label="La seconda lingua">
      <label class="scelta"><input type="radio" name="locali[]" value="" <?= $nessuna($baseS + $aggS) ? 'checked' : '' ?>><span class="scelta__testo">Nessuna</span></label>
      <?php foreach ($baseS as $code => $nome) $casella($code, $nome, 'radio'); ?>
    </div>
    <details class="lingue-altre"<?= array_filter(array_keys($aggS), $attivaS) ? ' open' : '' ?>>
      <summary>Altre lingue</summary>
      <div class="scelte scelte--riga" style="margin-top:8px"><?php foreach ($aggS as $code => $nome) $casella($code, $nome, 'radio'); ?></div>
    </details>
    <p class="small" style="margin:0"><button type="button" class="linkbtn" data-sblocca="lingue"><?= Icon::svg('lock', 13, 2) ?> Più lingue con Plus: le cinque di base e una in più</button></p>
  <?php else: ?>
    <div class="scelte"><?php foreach ($baseS + $aggS as $code => $nome) $casella($code, $nome, 'checkbox', true); ?></div>
  <?php endif; ?>
<?php else: /* Plus e Portfolio: le lingue di base, e una (o $xS) lingua aggiuntiva */ ?>
  <span class="legend" style="font-size:16px">Le lingue di base</span>
  <div class="scelte"><?php foreach ($baseS as $code => $nome) $casella($code, $nome, 'checkbox'); ?></div>
  <span class="legend" style="font-size:16px">Una lingua in più</span>
  <?php if (!I18n::base($defS) && $xS <= 1): ?>
    <p class="help" style="margin:0">La lingua in più è già la tua lingua principale, <?= Support::e($tutte[$defS] ?? $defS) ?>: le cinque lingue di base ci sono tutte.</p>
  <?php elseif ($xS === 1): ?>
    <div class="scelte scelte--riga" role="radiogroup" aria-label="Una lingua in più">
      <label class="scelta"><input type="radio" name="locali[]" value="" <?= $nessuna($aggS) ? 'checked' : '' ?>><span class="scelta__testo">Nessuna</span></label>
      <?php foreach ($aggS as $code => $nome) $casella($code, $nome, 'radio'); ?>
    </div>
  <?php else: ?>
    <p class="help" style="margin:0">Fino a <?= $xS ?> lingue in più.</p>
    <div class="scelte"><?php foreach ($aggS as $code => $nome) $casella($code, $nome, 'checkbox'); ?></div>
  <?php endif; ?>
  <p class="small muted" style="margin:0">I bottoni e i titoli della guida sono tradotti in olandese, portoghese (del Portogallo e del Brasile) e polacco; per le altre lingue in più l'ospite li legge in inglese.</p>
<?php endif; ?>
