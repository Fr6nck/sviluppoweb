<?php
/**
 * Un campo di modulo: etichetta visibile, controllo nativo, aiuto, errore.
 *
 * Regole del design system applicate qui:
 *  — l'etichetta è sempre una <label for> visibile, mai un segnaposto al suo posto;
 *  — si segnano i campi facoltativi, non quelli obbligatori: in un modulo di
 *    prenotazione quasi tutto serve, e un muro di asterischi non informa nessuno;
 *  — il corpo è a 16px, perché sotto quella misura iOS ingrandisce la pagina
 *    a ogni tocco (lo impone bundle.css, qui non si tocca);
 *  — il messaggio d'errore dice cosa fare, ed è collegato al controllo con
 *    aria-describedby, così chi usa uno screen reader lo sente al fuoco.
 *
 * @var string      $nome
 * @var string      $etichetta
 * @var string      $tipo         text | email | tel | date | textarea | select
 * @var string      $valore
 * @var string|null $errore
 * @var string|null $aiuto
 * @var bool        $facoltativo
 * @var bool        $obbligatorio
 * @var array       $opzioni      per select: valore => etichetta
 * @var string|null $autocomplete
 */

$tipo         = $tipo ?? 'text';
$valore       = $valore ?? '';
$errore       = $errore ?? null;
$aiuto        = $aiuto ?? null;
$facoltativo  = $facoltativo ?? false;
$obbligatorio = $obbligatorio ?? false;
$opzioni      = $opzioni ?? [];

$idErrore = 'err-' . $nome;
$idAiuto  = 'aiuto-' . $nome;
$descritto = array_filter([$errore ? $idErrore : null, $aiuto ? $idAiuto : null]);

$comuni = attrs([
    'id'           => $nome,
    'name'         => $nome,
    'required'     => $obbligatorio,
    'aria-invalid' => $errore ? 'true' : null,
    'aria-describedby' => $descritto !== [] ? implode(' ', $descritto) : null,
    'autocomplete' => $autocomplete ?? null,
]);
?>
<div class="adv-campo">
  <label class="adv-campo__label" for="<?= e($nome) ?>">
    <?= e($etichetta) ?><?php if ($facoltativo): ?> <span class="adv-campo__facoltativo">(<?= te('common.optional') ?>)</span><?php endif; ?>
  </label>

  <?php if ($tipo === 'textarea'): ?>
    <textarea class="adv-textarea" rows="5"<?= $comuni ?>><?= e($valore) ?></textarea>

  <?php elseif ($tipo === 'select'): ?>
    <select class="adv-select"<?= $comuni ?>>
      <?php foreach ($opzioni as $chiave => $etichettaOpzione): ?>
        <option value="<?= e((string) $chiave) ?>"<?= (string) $chiave === $valore ? ' selected' : '' ?>>
          <?= e($etichettaOpzione) ?>
        </option>
      <?php endforeach; ?>
    </select>

  <?php else: ?>
    <input class="adv-input" type="<?= e($tipo) ?>" value="<?= e($valore) ?>"<?= $comuni ?>>
  <?php endif; ?>

  <?php if ($aiuto): ?>
    <p class="adv-campo__aiuto" id="<?= e($idAiuto) ?>"><?= e($aiuto) ?></p>
  <?php endif; ?>

  <?php if ($errore): ?>
    <p class="adv-campo__errore" id="<?= e($idErrore) ?>">
      <span aria-hidden="true">&#9888;</span><?= e($errore) ?>
    </p>
  <?php endif; ?>
</div>
