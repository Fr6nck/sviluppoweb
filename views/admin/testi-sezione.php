<?php
/**
 * @var string $sezione
 * @var list<string> $chiavi
 * @var array<string, array<string,string>> $base         lingua → chiave → testo originale
 * @var array<string, array<string,?string>> $modificati  lingua → chiave → testo salvato, o null
 * @var list<string> $lingue
 * @var list<array> $storico
 */
use ArcoDelVento\Support\Csrf;

// I nomi delle chiavi più comuni, detti come li direbbe chi scrive i testi.
$nomi = [
    'seo_title' => 'Titolo per Google', 'seo_description' => 'Descrizione per Google',
    'eyebrow' => 'Occhiello (la riga piccola sopra il titolo)', 'title' => 'Titolo',
    'sign' => 'Parola in corsivo del titolo', 'lead' => 'Introduzione', 'text' => 'Testo',
    'image_alt' => 'Descrizione dell\'immagine', 'image_credit' => 'Crediti della fotografia',
    'caption' => 'Didascalia', 'note' => 'Nota', 'label' => 'Etichetta', 'cta' => 'Pulsante',
    'button' => 'Pulsante', 'help' => 'Aiuto sotto il campo', 'placeholder' => 'Esempio nel campo',
    'intro' => 'Introduzione', 'subtitle' => 'Sottotitolo', 'name' => 'Nome',
];
$etichetta = static function (string $chiave) use ($nomi, $sezione): array {
    $resto  = substr($chiave, strlen($sezione) + 1);
    $ultima = substr($resto, (int) strrpos('.' . $resto, '.'));

    return [$nomi[$ultima] ?? null, $resto];
};
?>
<p class="adm-intro">Scrivi sopra il testo per cambiarlo. <strong>Svuota il campo</strong> per tornare al testo originale.
   Le parole che cominciano con i due punti — <code>:nights</code>, <code>:count</code> — le riempie il sito: vanno lasciate.</p>

<form method="post" action="<?= e(adminUrl('testi/' . $sezione)) ?>" class="adm-modulo" novalidate>
  <?= Csrf::field() ?>
  <?php foreach ($chiavi as $n => $chiave): $id = 't-' . $n; ?>
    <fieldset class="adm-testo<?= array_filter(array_map(static fn ($l) => $modificati[$l][$chiave] ?? null, $lingue)) ? ' adm-testo--modificato' : '' ?>">
      <?php [$umano, $tecnico] = $etichetta($chiave); ?>
      <legend>
        <?php if ($umano !== null): ?><strong><?= e($umano) ?></strong><?php endif; ?>
        <code class="adm-chiave"><?= e($tecnico) ?></code>
      </legend>
      <?php foreach ($lingue as $lingua):
          $originale = $base[$lingua][$chiave] ?? '';
          $salvato   = $modificati[$lingua][$chiave] ?? null;
          $lungo     = mb_strlen($originale) > 90 || str_contains($originale, "\n"); ?>
        <div class="adm-lingua">
          <label for="<?= e($id . '-' . $lingua) ?>"><span class="adm-lingua__sigla"><?= e(strtoupper($lingua)) ?></span></label>
          <?php if ($lungo): ?>
            <textarea id="<?= e($id . '-' . $lingua) ?>" name="t[<?= e($lingua) ?>][<?= e($chiave) ?>]" lang="<?= e($lingua) ?>"
                      rows="<?= min(8, max(2, (int) ceil(mb_strlen($salvato ?? $originale) / 90))) ?>"><?= e($salvato ?? $originale) ?></textarea>
          <?php else: ?>
            <input type="text" id="<?= e($id . '-' . $lingua) ?>" name="t[<?= e($lingua) ?>][<?= e($chiave) ?>]" lang="<?= e($lingua) ?>"
                   value="<?= e($salvato ?? $originale) ?>">
          <?php endif; ?>
          <?php if ($salvato !== null): ?>
            <p class="adm-originale">Modificato. Originale: «<?= e($originale) ?>»</p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </fieldset>
  <?php endforeach; ?>
  <div class="adm-salva">
    <button type="submit" class="adm-bottone">Salva i testi</button>
  </div>
</form>

<?= $this->render('admin/_storico', ['storico' => $storico, 'nome' => '', 'ritorno' => 'testi/' . $sezione]) ?>
<p><a class="adm-link" href="<?= e(adminUrl('testi')) ?>">← Tutte le sezioni</a></p>
