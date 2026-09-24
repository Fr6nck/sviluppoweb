<?php
/**
 * Un campo di un modulo dell'area riservata, costruito dallo Schema.
 *
 * @var array<string,mixed> $campo
 * @var mixed               $valore
 * @var list<string>        $lingue
 * @var string              $nome    il nome del campo nel modulo, es. f[legal.cin]
 */

use ArcoDelVento\Admin\Form;

$id    = 'c-' . preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $campo['path']));
$aiuto = (string) ($campo['help'] ?? '');
$descr = $aiuto !== '' ? ' aria-describedby="' . e($id) . '-aiuto"' : '';
$tipiHtml = ['text' => 'text', 'email' => 'email', 'tel' => 'tel', 'url' => 'url', 'time' => 'text',
             'int' => 'text', 'money' => 'text', 'score' => 'text', 'coord' => 'text'];
$modoTastiera = match ($campo['type']) {
    'int' => ' inputmode="numeric"',
    'money', 'score', 'coord' => ' inputmode="decimal"',
    default => '',
};
?>
<div class="adm-campo<?= $campo['type'] === 'bool' ? ' adm-campo--spunta' : '' ?><?= $campo['type'] === 'prose' ? ' adm-campo--lingue' : '' ?>">
  <?php if ($campo['type'] === 'bool'): ?>
    <input type="hidden" name="<?= e($nome) ?>" value="0">
    <input type="checkbox" id="<?= e($id) ?>" name="<?= e($nome) ?>" value="1"<?= $valore === true ? ' checked' : '' ?><?= $descr ?>>
    <label for="<?= e($id) ?>"><?= e($campo['label']) ?></label>

  <?php elseif ($campo['type'] === 'prose'): ?>
    <fieldset>
      <legend><?= e($campo['label']) ?></legend>
      <?php foreach ($lingue as $lingua): ?>
        <div class="adm-lingua">
          <label for="<?= e($id . '-' . $lingua) ?>"><span class="adm-lingua__sigla"><?= e(strtoupper($lingua)) ?></span></label>
          <?php if ((int) ($campo['rows'] ?? 2) <= 1): ?>
            <input type="text" id="<?= e($id . '-' . $lingua) ?>" name="<?= e($nome) ?>[<?= e($lingua) ?>]"
                   value="<?= e(Form::display($campo, $valore, $lingua)) ?>" lang="<?= e($lingua) ?>"<?= $descr ?>>
          <?php else: ?>
            <textarea id="<?= e($id . '-' . $lingua) ?>" name="<?= e($nome) ?>[<?= e($lingua) ?>]"
                      rows="<?= (int) $campo['rows'] ?>" lang="<?= e($lingua) ?>"<?= $descr ?>><?= e(Form::display($campo, $valore, $lingua)) ?></textarea>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </fieldset>

  <?php elseif ($campo['type'] === 'select'): ?>
    <label for="<?= e($id) ?>"><?= e($campo['label']) ?></label>
    <select id="<?= e($id) ?>" name="<?= e($nome) ?>"<?= $descr ?>>
      <?php foreach ((array) $campo['options'] as $chiave => $etichetta): ?>
        <option value="<?= e((string) $chiave) ?>"<?= (string) ($valore ?? '') === (string) $chiave ? ' selected' : '' ?>><?= e($etichetta) ?></option>
      <?php endforeach; ?>
    </select>

  <?php else: ?>
    <label for="<?= e($id) ?>"><?= e($campo['label']) ?></label>
    <input type="<?= e($tipiHtml[$campo['type']] ?? 'text') ?>" id="<?= e($id) ?>" name="<?= e($nome) ?>"
           value="<?= e(Form::display($campo, $valore)) ?>"<?= $modoTastiera ?><?= $descr ?>
           <?= $campo['type'] === 'time' ? 'placeholder="13:00" class="adm-corto"' : '' ?>
           <?= in_array($campo['type'], ['int', 'money', 'score'], true) ? 'class="adm-corto"' : '' ?>>
  <?php endif; ?>

  <?php if ($aiuto !== ''): ?>
    <p class="adm-aiuto" id="<?= e($id) ?>-aiuto"><?= e($aiuto) ?></p>
  <?php endif; ?>
  <?php if ($campo['type'] !== 'bool' && ($valore === null || $valore === '' || (is_array($valore) && array_filter($valore, static fn ($x) => $x !== null && $x !== '') === []))): ?>
    <p class="adm-dc">Vuoto: sul sito compare «da confermare».</p>
  <?php endif; ?>
</div>
