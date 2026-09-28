<?php
/* I campi di una sezione, disegnati dal catalogo. Passaggi ed elenchi sono
   righe vere: una casella per voce, "+ Aggiungi" per la prossima. Niente
   sintassi da imparare.
   Riceve: $kind, $dati (campi uguali in ogni lingua), $tdati (campi tradotti). */
use MHW\{Support, SectionCatalog};
$uid = $uid ?? 'c';
foreach (SectionCatalog::fields($kind) as $nome => [$tipo, $etichetta, $aiuto]):
    $id = $uid . '-' . $nome;
    $valore = SectionCatalog::isTranslated($tipo) ? ($tdati[$nome] ?? '') : ($dati[$nome] ?? '');
    if (in_array($tipo, ['steps', 'list'], true)):
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
