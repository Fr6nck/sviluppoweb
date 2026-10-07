<?php
/* I campi di una sezione, disegnati dal catalogo. Passaggi ed elenchi sono
   righe vere: una casella per voce, "+ Aggiungi" per la prossima. Niente
   sintassi da imparare.
   Le righe con sottocampi (repeater) le disegna _ripetitore.php.
   Riceve: $kind, $dati (campi uguali in ogni lingua), $tdati (campi tradotti),
           e se c'è $prop (per i suggerimenti nella lingua della guida). */
use MHW\{Support, SectionCatalog, I18n, Icon, Tassonomie};
$uid = $uid ?? 'c';
$linguaGuida = $prop['default_locale'] ?? 'it';
// I campi legati a un interruttore (l'orario del silenzio) si disegnano insieme, su una riga.
$legati = [];
foreach (SectionCatalog::fields($kind) as $n => $d) if (isset($d['se'])) $legati[$d['se']][] = $n;
foreach (SectionCatalog::fields($kind) as $nome => $defCampo):
    [$tipo, $etichetta, $aiuto] = $defCampo;
    // «Facoltativo.» in testa all'aiuto va accanto all'etichetta: l'aiuto resta per l'esempio.
    [$fac, $aiuto] = SectionCatalog::facoltativo((string) $aiuto);
    $facHtml = $fac !== '' ? ' <span class="muted">(' . $fac . ')</span>' : '';
    $id = $uid . '-' . $nome;
    $valore = SectionCatalog::isTranslated($tipo) ? ($tdati[$nome] ?? '') : ($dati[$nome] ?? '');
    if (isset($defCampo['se'])) continue;   // li disegna il loro interruttore
    if ($tipo === 'check'): /* un interruttore; spento, i campi legati spariscono e al salvataggio si svuotano */
        $acceso = SectionCatalog::acceso($kind, $nome, $dati); ?>
  <div class="field interruttore-campo" style="margin:0">
    <input type="hidden" name="<?= Support::e($nome) ?>" value="">
    <label class="interruttore-check"><input type="checkbox" role="switch" name="<?= Support::e($nome) ?>" value="1" <?= $acceso ? 'checked' : '' ?>
        aria-describedby="<?= Support::e($id) ?>-aiuto"<?= isset($legati[$nome]) ? ' data-interruttore="' . Support::e($id) . '-legati"' : '' ?>>
      <span class="interruttore-check__testo"><?= Support::e($etichetta) ?></span></label>
    <?php if ($aiuto !== ''): ?><p class="help" id="<?= Support::e($id) ?>-aiuto"><?= Support::e($aiuto) ?></p><?php endif; ?>
    <?php if (isset($legati[$nome])): ?>
      <div class="grid grid-2 campi-legati" id="<?= Support::e($id) ?>-legati">
        <?php foreach ($legati[$nome] as $ln): $ld = SectionCatalog::field($kind, $ln); $lv = (string) ($dati[$ln] ?? '');
              if ($lv === '' && isset($ld['default'])) $lv = $ld['default']; ?>
          <div class="field" style="margin:0">
            <label for="<?= Support::e($uid . '-' . $ln) ?>"><?= Support::e($ld[1]) ?></label>
            <input id="<?= Support::e($uid . '-' . $ln) ?>" name="<?= Support::e($ln) ?>" type="time" value="<?= Support::e($lv) ?>" style="max-width:10rem"
                   <?= isset($ld['default']) ? 'data-predefinito="' . Support::e($ld['default']) . '"' : '' ?>>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php elseif ($tipo === 'repeater'):
        (function (array $rip) { include __DIR__ . '/_ripetitore.php'; })([
            'name' => $nome, 'legend' => $etichetta, 'help' => $aiuto, 'sub' => $defCampo['sub'],
            'rows' => SectionCatalog::rows($defCampo, $dati[$nome] ?? [], $tdati[$nome] ?? []),
            'add' => $defCampo['add'] ?? 'Aggiungi', 'item' => $defCampo['item'] ?? 'Voce', 'max' => $defCampo['max'] ?? 30,
            'foto' => $foto ?? true, 'pdf' => $pdf ?? true, 'eventi' => !empty($defCampo['eventi']),
            // «Già in <struttura>»: le righe scritte nelle altre strutture, con il modulo che le copia qui.
            'altre' => $suggRighe[$nome] ?? [], 'modulo_altre' => isset($sid) ? 'da-altra-' . $sid : '',
            // Le righe pronte hanno il nome nella lingua della guida (Guardia medica, Out-of-hours doctor…).
            'presets' => array_combine(
                array_map(fn($k) => I18n::t($linguaGuida, $k), array_keys($defCampo['presets'] ?? [])),
                array_map(fn($k, $v) => ($defCampo['preset_nome'] ?? true) ? ['name' => I18n::t($linguaGuida, $k)] + $v : $v, array_keys($defCampo['presets'] ?? []), $defCampo['presets'] ?? []))]);
    elseif ($tipo === 'checks'): /* più spunte, con un campo vuoto: così togliere tutte le spunte si salva */ ?>
  <fieldset class="fieldset">
    <legend><?= Support::e($etichetta) ?><?= $facHtml ?></legend>
    <?php if ($aiuto !== ''): ?><p class="help"><?= Support::e($aiuto) ?></p><?php endif; ?>
    <input type="hidden" name="<?= Support::e($nome) ?>[]" value="">
    <?php /* Le dotazioni a gruppi (Cucina, Comfort…), con un titoletto: i gruppi si vedono solo qui. */
    $gruppi = ($defCampo['tassonomia'] ?? '') === 'dotazioni' ? Tassonomie::DOTAZIONI : ['' => array_keys($defCampo['options'])];
    foreach ($gruppi as $titolo => $chiavi): ?>
      <?php if ($titolo !== ''): ?><p class="dotazioni__gruppo"><?= Support::e($titolo) ?></p><?php endif; ?>
      <div class="scelte scelte--riga dotazioni">
        <?php foreach ($chiavi as $ok): $ol = $defCampo['options'][$ok] ?? $ok; ?>
          <label class="scelta scelta--mini"><input type="checkbox" name="<?= Support::e($nome) ?>[]" value="<?= Support::e($ok) ?>" <?= in_array($ok, (array) $valore, true) ? 'checked' : '' ?>>
            <span><?= Icon::svg(Icon::amenita($ok), 16) ?> <?= Support::e($ol) ?></span></label>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </fieldset>
<?php elseif ($tipo === 'toggles'): /* una regola per riga: le due frasi che l'ospite può leggere nella guida, oppure «Non indicato» */ ?>
  <fieldset class="fieldset">
    <legend><?= Support::e($etichetta) ?><?= $facHtml ?></legend>
    <?php if ($aiuto !== ''): ?><p class="help"><?= Support::e($aiuto) ?></p><?php endif; ?>
    <div class="stack" style="gap:10px">
      <?php foreach ($defCampo['options'] as $ok => $ol): $ora = (string) (((array) $valore)[$ok] ?? ''); ?>
        <fieldset class="interruttore">
          <legend class="interruttore__nome"><?= Support::e($ol) ?></legend>
          <div class="scelte scelte--riga">
            <?php foreach (['si' => I18n::t('it', 'rule_' . $ok . '_si'), 'no' => I18n::t('it', 'rule_' . $ok . '_no'), '' => 'Non indicato'] as $tv => $tl): ?>
              <label class="scelta scelta--mini"><input type="radio" name="<?= Support::e($nome) ?>[<?= Support::e($ok) ?>]" value="<?= $tv ?>" <?= $ora === $tv ? 'checked' : '' ?>><span><?= Support::e($tl) ?></span></label>
            <?php endforeach; ?>
          </div>
        </fieldset>
      <?php endforeach; ?>
    </div>
  </fieldset>
<?php elseif ($tipo === 'time'): ?>
  <div class="field" style="margin:0">
    <label for="<?= Support::e($id) ?>"><?= Support::e($etichetta) ?><?= $facHtml ?></label>
    <?php if ($aiuto !== ''): ?><p class="help" style="margin:0 0 6px"><?= Support::e($aiuto) ?></p><?php endif; ?>
    <input id="<?= Support::e($id) ?>" name="<?= Support::e($nome) ?>" type="time" value="<?= Support::e((string) $valore) ?>" style="max-width:10rem">
  </div>
<?php elseif ($tipo === 'choice' && !empty($defCampo['icone'])): /* l'icona della sezione libera: radio veri, con il disegno */
      $valore = (string) $valore !== '' ? $valore : (string) array_key_first($defCampo['options']); ?>
  <fieldset class="fieldset">
    <legend><?= Support::e($etichetta) ?><?= $facHtml ?></legend>
    <?php if ($aiuto !== ''): ?><p class="help"><?= Support::e($aiuto) ?></p><?php endif; ?>
    <div class="scelte scelte--riga icone-scelta">
      <?php foreach ($defCampo['options'] as $ok => $ol): ?>
        <label class="scelta scelta--mini"><input type="radio" name="<?= Support::e($nome) ?>" value="<?= Support::e((string) $ok) ?>" <?= (string) $valore === (string) $ok ? 'checked' : '' ?>>
          <span><?= Icon::svg((string) $ok, 20, 1.8) ?><?= Support::e($ol) ?></span></label>
      <?php endforeach; ?>
    </div>
  </fieldset>
<?php elseif ($tipo === 'choice'): ?>
  <fieldset class="fieldset">
    <legend><?= Support::e($etichetta) ?><?= $facHtml ?></legend>
    <?php if ($aiuto !== ''): ?><p class="help"><?= Support::e($aiuto) ?></p><?php endif; ?>
    <div class="scelte scelte--riga">
      <?php foreach (['' => 'Non indicato'] + $defCampo['options'] as $ok => $ol): ?>
        <label class="scelta"><input type="radio" name="<?= Support::e($nome) ?>" value="<?= Support::e((string) $ok) ?>" <?= (string) $valore === (string) $ok ? 'checked' : '' ?>>
          <span class="scelta__testo"><?= Support::e($ol) ?></span></label>
      <?php endforeach; ?>
    </div>
  </fieldset>
<?php elseif ($tipo === 'list' && !empty($defCampo['pillole'])): /* le tue dotazioni: pillole con «×» e «+ Aggiungi» */
        $righe = array_values(array_filter((array) $valore, fn($x) => trim((string) $x) !== ''));
        $righe[] = ''; ?>
  <fieldset class="fieldset">
    <legend><?= Support::e($etichetta) ?><?= $facHtml ?></legend>
    <?php if ($aiuto !== ''): ?><p class="help"><?= Support::e($aiuto) ?></p><?php endif; ?>
    <div class="rows pillole-campo" id="<?= Support::e($id) ?>">
      <?php foreach ($righe as $i => $riga): ?>
        <div class="r pillola-r">
          <input type="text" name="<?= Support::e($nome) ?>[]" value="<?= Support::e((string) $riga) ?>" maxlength="80" size="<?= max(8, min(28, mb_strlen((string) $riga) + 2)) ?>"
                 aria-label="<?= Support::e($etichetta) ?>, voce <?= $i + 1 ?>" placeholder="<?= $riga === '' ? 'Per esempio: giochi da tavolo' : '' ?>">
          <button type="button" class="pillola-r__togli" data-togli-riga aria-label="Togli questa dotazione" hidden>&times;</button>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="button" class="linkbtn" data-aggiungi-riga="<?= Support::e($id) ?>" hidden>+ <?= Support::e($defCampo['add'] ?? 'Aggiungi') ?></button>
  </fieldset>
<?php elseif (in_array($tipo, ['steps', 'list'], true)):
        $righe = array_values(array_filter((array) $valore, fn($x) => trim((string) $x) !== ''));
        // Senza JavaScript servono righe vuote già pronte; con JavaScript se ne aggiungono altre.
        $righe = array_merge($righe, array_fill(0, count($righe) ? 1 : 3, '')); ?>
  <fieldset class="fieldset">
    <legend><?= Support::e($etichetta) ?><?= $facHtml ?></legend>
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
      + <?= $tipo === 'steps' ? 'Aggiungi un passaggio' : 'Aggiungi una voce' ?></button>
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
    <label for="<?= Support::e($id) ?>"><?= Support::e($etichetta) ?><?= $facHtml ?></label>
    <?php if ($aiuto !== ''): ?><p class="help" style="margin:0 0 6px"><?= Support::e($aiuto) ?></p><?php endif; ?>
    <textarea id="<?= Support::e($id) ?>" name="<?= Support::e($nome) ?>" rows="3" maxlength="2000"><?= Support::e((string) $valore) ?></textarea>
  </div>
<?php else: ?>
  <div class="field" style="margin:0">
    <label for="<?= Support::e($id) ?>"><?= Support::e($etichetta) ?><?= $facHtml ?></label>
    <?php if ($aiuto !== ''): ?><p class="help" style="margin:0 0 6px"><?= Support::e($aiuto) ?></p><?php endif; ?>
    <input id="<?= Support::e($id) ?>" name="<?= Support::e($nome) ?>" value="<?= Support::e((string) $valore) ?>"
           <?php /* Lo stesso limite del server (SectionCatalog::clean e sezione()): 500 i link, 200 i campi semplici, 300 i testi brevi. */ ?>
           type="<?= $tipo === 'url' ? 'url' : (($defCampo['tastiera'] ?? '') === 'tel' || $tipo === 'tel' ? 'tel' : 'text') ?>"
           maxlength="<?= $tipo === 'url' ? 500 : (!empty($defCampo['cifre']) ? 3 : (in_array($tipo, ['plain', 'secret', 'tel'], true) ? 200 : 300)) ?>"
           <?= !empty($defCampo['cifre']) ? 'inputmode="numeric" pattern="[0-9]*"' : '' ?>
           <?= $tipo === 'url' ? 'placeholder="https://"' : '' ?> <?= $tipo === 'secret' ? 'autocomplete="off" spellcheck="false"' : '' ?>>
  </div>
<?php endif; endforeach; ?>
