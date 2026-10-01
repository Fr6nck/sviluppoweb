<?php
/* I campi di una sezione, disegnati dal catalogo. Passaggi ed elenchi sono
   righe vere: una casella per voce, "+ Aggiungi" per la prossima. Niente
   sintassi da imparare.
   Le righe con sottocampi (repeater) le disegna _ripetitore.php.
   Riceve: $kind, $dati (campi uguali in ogni lingua), $tdati (campi tradotti),
           e se c'è $prop (per i suggerimenti nella lingua della guida). */
use MHW\{Support, SectionCatalog, I18n, Icon};
$uid = $uid ?? 'c';
$linguaGuida = $prop['default_locale'] ?? 'it';
foreach (SectionCatalog::fields($kind) as $nome => $defCampo):
    [$tipo, $etichetta, $aiuto] = $defCampo;
    $id = $uid . '-' . $nome;
    $valore = SectionCatalog::isTranslated($tipo) ? ($tdati[$nome] ?? '') : ($dati[$nome] ?? '');
    if ($tipo === 'repeater'):
        (function (array $rip) { include __DIR__ . '/_ripetitore.php'; })([
            'name' => $nome, 'legend' => $etichetta, 'help' => $aiuto, 'sub' => $defCampo['sub'],
            'rows' => SectionCatalog::rows($defCampo, $dati[$nome] ?? [], $tdati[$nome] ?? []),
            'add' => $defCampo['add'] ?? 'Aggiungi', 'max' => $defCampo['max'] ?? 30,
            'foto' => $foto ?? true, 'pdf' => $pdf ?? true,
            // Le righe pronte hanno il nome nella lingua della guida (Guardia medica, Out-of-hours doctor…).
            'presets' => array_combine(
                array_map(fn($k) => I18n::t($linguaGuida, $k), array_keys($defCampo['presets'] ?? [])),
                array_map(fn($k, $v) => ['name' => I18n::t($linguaGuida, $k)] + $v, array_keys($defCampo['presets'] ?? []), $defCampo['presets'] ?? []))]);
    elseif ($tipo === 'checks'): /* più spunte, con un campo vuoto: così togliere tutte le spunte si salva */ ?>
  <fieldset class="fieldset">
    <legend><?= Support::e($etichetta) ?></legend>
    <?php if ($aiuto !== ''): ?><p class="help"><?= Support::e($aiuto) ?></p><?php endif; ?>
    <input type="hidden" name="<?= Support::e($nome) ?>[]" value="">
    <div class="scelte scelte--riga dotazioni">
      <?php foreach ($defCampo['options'] as $ok => $ol): ?>
        <label class="scelta scelta--mini"><input type="checkbox" name="<?= Support::e($nome) ?>[]" value="<?= Support::e($ok) ?>" <?= in_array($ok, (array) $valore, true) ? 'checked' : '' ?>>
          <span><?= Icon::svg(Icon::amenita($ok), 16) ?> <?= Support::e($ol) ?></span></label>
      <?php endforeach; ?>
    </div>
  </fieldset>
<?php elseif ($tipo === 'toggles'): /* sì / no / non indicato, uno per regola */ ?>
  <fieldset class="fieldset">
    <legend><?= Support::e($etichetta) ?></legend>
    <?php if ($aiuto !== ''): ?><p class="help"><?= Support::e($aiuto) ?></p><?php endif; ?>
    <div class="stack" style="gap:10px">
      <?php foreach ($defCampo['options'] as $ok => $ol): $ora = (string) (((array) $valore)[$ok] ?? ''); ?>
        <fieldset class="interruttore">
          <legend class="interruttore__nome"><?= Support::e($ol) ?></legend>
          <div class="scelte scelte--riga">
            <?php foreach (['si' => 'Ammesso', 'no' => 'Non ammesso', '' => 'Non indicato'] as $tv => $tl): ?>
              <label class="scelta scelta--mini"><input type="radio" name="<?= Support::e($nome) ?>[<?= Support::e($ok) ?>]" value="<?= $tv ?>" <?= $ora === $tv ? 'checked' : '' ?>><span><?= $tl ?></span></label>
            <?php endforeach; ?>
          </div>
        </fieldset>
      <?php endforeach; ?>
    </div>
  </fieldset>
<?php elseif ($tipo === 'time'): ?>
  <div class="field" style="margin:0">
    <label for="<?= Support::e($id) ?>"><?= Support::e($etichetta) ?></label>
    <?php if ($aiuto !== ''): ?><p class="help" style="margin:0 0 6px"><?= Support::e($aiuto) ?></p><?php endif; ?>
    <input id="<?= Support::e($id) ?>" name="<?= Support::e($nome) ?>" type="time" value="<?= Support::e((string) $valore) ?>" style="max-width:10rem">
  </div>
<?php elseif ($tipo === 'choice'): ?>
  <fieldset class="fieldset">
    <legend><?= Support::e($etichetta) ?></legend>
    <?php if ($aiuto !== ''): ?><p class="help"><?= Support::e($aiuto) ?></p><?php endif; ?>
    <div class="scelte scelte--riga">
      <?php foreach (['' => 'Non indicato'] + $defCampo['options'] as $ok => $ol): ?>
        <label class="scelta"><input type="radio" name="<?= Support::e($nome) ?>" value="<?= Support::e((string) $ok) ?>" <?= (string) $valore === (string) $ok ? 'checked' : '' ?>>
          <span class="scelta__testo"><?= Support::e($ol) ?></span></label>
      <?php endforeach; ?>
    </div>
  </fieldset>
<?php elseif (in_array($tipo, ['steps', 'list'], true)):
        $righe = array_values(array_filter((array) $valore, fn($x) => trim((string) $x) !== ''));
        // Senza JavaScript servono righe vuote già pronte; con JavaScript se ne aggiungono altre.
        $righe = array_merge($righe, array_fill(0, count($righe) ? 1 : 3, '')); ?>
  <fieldset class="fieldset">
    <legend><?= Support::e($etichetta) ?></legend>
    <?php if ($aiuto !== ''): ?><p class="help"><?= Support::e($aiuto) ?></p><?php endif; ?>
    <div class="rows" id="<?= Support::e($id) ?>">
      <?php foreach ($righe as $i => $riga): ?>
        <div class="r">
          <?php if ($tipo === 'steps'): ?><span class="num" aria-hidden="true"><?= $i + 1 ?></span><?php endif; ?>
          <input type="text" name="<?= Support::e($nome) ?>[]" value="<?= Support::e((string) $riga) ?>" maxlength="600"
                 aria-label="<?= Support::e($etichetta) ?>, voce <?= $i + 1 ?>">
          <button type="button" class="icon-btn" data-togli-riga aria-label="Togli questa voce" hidden>&times;</button>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="linkbtn" data-aggiungi-riga="<?= Support::e($id) ?>" hidden>
      + <?= $tipo === 'steps' ? 'Aggiungi passaggio' : 'Aggiungi voce' ?></button>
    <?php if (!empty($defCampo['suggest'])): /* suggerimenti a un tocco, nella lingua della guida */ ?>
      <div class="suggerimenti" data-solo-js hidden>
        <span class="small muted">Suggerimenti:</span>
        <?php foreach ($defCampo['suggest'] as $sg): ?>
          <button type="button" class="chip-sugg" data-suggerisci="<?= Support::e($id) ?>"
                  data-testo="<?= Support::e(I18n::t($linguaGuida, 'sugg_' . $sg)) ?>">+ <?= Support::e(I18n::t('it', 'checkout_' . $sg)) ?></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </fieldset>
<?php elseif ($tipo === 'textarea'): ?>
  <div class="field" style="margin:0">
    <label for="<?= Support::e($id) ?>"><?= Support::e($etichetta) ?></label>
    <?php if ($aiuto !== ''): ?><p class="help" style="margin:0 0 6px"><?= Support::e($aiuto) ?></p><?php endif; ?>
    <textarea id="<?= Support::e($id) ?>" name="<?= Support::e($nome) ?>" rows="3" maxlength="2000"><?= Support::e((string) $valore) ?></textarea>
  </div>
<?php else: ?>
  <div class="field" style="margin:0">
    <label for="<?= Support::e($id) ?>"><?= Support::e($etichetta) ?></label>
    <?php if ($aiuto !== ''): ?><p class="help" style="margin:0 0 6px"><?= Support::e($aiuto) ?></p><?php endif; ?>
    <input id="<?= Support::e($id) ?>" name="<?= Support::e($nome) ?>" value="<?= Support::e((string) $valore) ?>"
           type="<?= $tipo === 'url' ? 'url' : 'text' ?>" maxlength="<?= $tipo === 'url' ? 500 : 300 ?>"
           <?= $tipo === 'url' ? 'placeholder="https://"' : '' ?> <?= $tipo === 'secret' ? 'autocomplete="off" spellcheck="false"' : '' ?>>
  </div>
<?php endif; endforeach; ?>
