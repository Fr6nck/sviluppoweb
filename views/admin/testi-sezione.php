<?php
/**
 * I testi di una sezione, divisi come li legge chi li scrive.
 *
 * A sinistra i campi: prima i testi importanti, blocco per blocco nell'ordine
 * della pagina; poi, chiusi, i testi per Google e quelli secondari. A destra
 * l'anteprima della pagina vera: scegliendo un campo il testo si illumina
 * nella pagina, e cliccando un testo nella pagina si arriva al suo campo.
 * Senza JavaScript restano i campi, tutti, e il salvataggio funziona uguale.
 *
 * @var string $sezione
 * @var array  $definizione
 * @var list<string> $chiavi
 * @var array<string, array<string,string>> $base         lingua → chiave → testo originale
 * @var array<string, array<string,?string>> $modificati  lingua → chiave → testo salvato, o null
 * @var array<string,string> $anteprima                  lingua → indirizzo della pagina
 * @var list<array> $storico
 */
use ArcoDelVento\Admin\Testi;
use ArcoDelVento\Support\Csrf;

$lingue = array_keys($base);
$nomiLingua = ['it' => 'Italiano', 'en' => 'English', 'es' => 'Español'];

// I testi divisi per livello e, dentro, per blocco della pagina.
$livelli = ['importanti' => [], 'google' => [], 'secondari' => []];
foreach ($chiavi as $chiave) {
    $livelli[Testi::livello($chiave)][Testi::blocco($chiave)][] = $chiave;
}
$conta = static fn (array $blocchi): int => array_sum(array_map('count', $blocchi));
$modificato = static function (string $chiave) use ($lingue, $modificati): bool {
    foreach ($lingue as $l) {
        if (($modificati[$l][$chiave] ?? null) !== null) {
            return true;
        }
    }
    return false;
};

$campo = static function (string $chiave) use ($lingue, $base, $modificati, $modificato, $nomiLingua): string {
    $id = 'k-' . str_replace(['.', '_'], '-', $chiave);
    ob_start(); ?>
    <fieldset class="adm-testo<?= $modificato($chiave) ? ' adm-testo--modificato' : '' ?>" id="<?= e($id) ?>" data-testo="<?= e($chiave) ?>">
      <legend>
        <strong><?= e(Testi::nome($chiave)) ?></strong>
        <?php if ($modificato($chiave)): ?><span class="adm-stato adm-stato--accento">Modificato</span><?php endif; ?>
        <button type="button" class="adm-testo__trova" data-trova hidden title="Mostra dove sta nella pagina">
          <?= icona('occhio', 14) ?><span>Mostra nella pagina</span>
        </button>
      </legend>
      <?php foreach ($lingue as $lingua):
          $originale = $base[$lingua][$chiave] ?? '';
          $salvato   = $modificati[$lingua][$chiave] ?? null;
          $valore    = $salvato ?? $originale;
          $lungo     = mb_strlen($originale) > 80 || str_contains($originale, "\n");
          $idCampo   = $id . '-' . $lingua; ?>
        <div class="adm-lingua" data-lingua="<?= e($lingua) ?>">
          <label for="<?= e($idCampo) ?>"><span class="adm-lingua__sigla" title="<?= e($nomiLingua[$lingua] ?? $lingua) ?>"><?= e(strtoupper($lingua)) ?></span></label>
          <?php if ($lungo): ?>
            <textarea id="<?= e($idCampo) ?>" name="t[<?= e($lingua) ?>][<?= e($chiave) ?>]" lang="<?= e($lingua) ?>" data-campo
                      rows="<?= min(8, max(2, (int) ceil(mb_strlen($valore) / 85))) ?>"><?= e($valore) ?></textarea>
          <?php else: ?>
            <input type="text" id="<?= e($idCampo) ?>" name="t[<?= e($lingua) ?>][<?= e($chiave) ?>]" lang="<?= e($lingua) ?>" data-campo
                   value="<?= e($valore) ?>">
          <?php endif; ?>
          <?php if ($salvato !== null): ?>
            <p class="adm-originale">Il testo originale era: «<?= e($originale) ?>». Svuota il campo per rimetterlo.</p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <p class="adm-testo__chiave" title="Il nome tecnico del testo"><?= e($chiave) ?></p>
    </fieldset>
    <?php
    return (string) ob_get_clean();
};

$blocchi = static function (array $perBlocco) use ($campo): string {
    $html = '';
    foreach ($perBlocco as $nome => $chiaviBlocco) {
        $html .= '<div class="adm-blocco"><h3 class="adm-blocco__titolo">' . e($nome) . '</h3>';
        foreach ($chiaviBlocco as $k) {
            $html .= $campo($k);
        }
        $html .= '</div>';
    }
    return $html;
};
?>
<p class="adm-intro"><?= e($definizione['descrizione']) ?>
   Scrivi sopra il testo per cambiarlo; <strong>svuota il campo</strong> per tornare all’originale.
   Le parole con i due punti davanti — <code>:nights</code>, <code>:metres</code> — le riempie il sito: vanno lasciate.
   Il segno <code>|</code> va a capo.</p>

<div class="adm-editor" data-editor data-lingua="tutte"
     <?php foreach ($anteprima as $l => $u): ?>data-anteprima-<?= e($l) ?>="<?= e($u) ?>" <?php endforeach; ?>>

  <div class="adm-editor__campi">
    <div class="adm-editor__barra" role="group" aria-label="Lingua da modificare">
      <span class="adm-editor__etichetta">Lingua</span>
      <?php foreach ($lingue as $l): ?>
        <button type="button" class="adm-pillola" data-mostra-lingua="<?= e($l) ?>"><?= e($nomiLingua[$l] ?? strtoupper($l)) ?></button>
      <?php endforeach; ?>
      <button type="button" class="adm-pillola" data-mostra-lingua="tutte">Tutte</button>
    </div>

    <form method="post" action="<?= e(adminUrl('testi/' . $sezione)) ?>" class="adm-modulo" novalidate data-modulo-testi>
      <?= Csrf::field() ?>

      <?php if ($livelli['importanti'] !== []): ?>
        <section class="adm-livello">
          <h2 class="adm-livello__titolo">Testi importanti <span class="adm-tenue">· <?= $conta($livelli['importanti']) ?></span></h2>
          <p class="adm-livello__nota">Titoli, introduzioni e testi che gli ospiti leggono, nell’ordine della pagina.</p>
          <?= $blocchi($livelli['importanti']) ?>
        </section>
      <?php endif; ?>

      <?php if ($livelli['google'] !== []): ?>
        <details class="adm-livello adm-livello--chiuso">
          <summary><span class="adm-livello__titolo">Google</span> <span class="adm-tenue">· <?= $conta($livelli['google']) ?></span>
            <span class="adm-livello__nota">Il titolo e la descrizione che compaiono nei risultati di ricerca.</span></summary>
          <?= $blocchi($livelli['google']) ?>
        </details>
      <?php endif; ?>

      <?php if ($livelli['secondari'] !== []): ?>
        <details class="adm-livello adm-livello--chiuso"<?= $livelli['importanti'] === [] ? ' open' : '' ?>>
          <summary><span class="adm-livello__titolo">Testi secondari</span> <span class="adm-tenue">· <?= $conta($livelli['secondari']) ?></span>
            <span class="adm-livello__nota">Descrizioni delle foto, etichette per gli screen reader, messaggi e avvisi: si toccano di rado.</span></summary>
          <?= $blocchi($livelli['secondari']) ?>
        </details>
      <?php endif; ?>

      <div class="adm-salva">
        <button type="submit" class="adm-bottone">Salva i testi</button>
        <p class="adm-nota" data-da-salvare hidden>Ci sono modifiche non salvate.</p>
      </div>
    </form>
  </div>

  <aside class="adm-editor__anteprima" data-anteprima hidden aria-label="Anteprima della pagina">
    <div class="adm-anteprima__barra">
      <strong>Anteprima — <?= e($definizione['nome']) ?></strong>
      <span class="adm-anteprima__comandi">
        <button type="button" class="adm-pillola" data-schermo="computer" aria-pressed="true">Computer</button>
        <button type="button" class="adm-pillola" data-schermo="telefono" aria-pressed="false">Telefono</button>
        <a class="adm-link" href="<?= e($anteprima['it'] ?? '') ?>" target="_blank" rel="noopener" data-apri-pagina>Apri <?= icona('freccia-su-destra', 13) ?></a>
      </span>
    </div>
    <div class="adm-anteprima__schermo" data-schermo-cornice>
      <iframe title="Anteprima della pagina del sito" data-anteprima-cornice src="about:blank"></iframe>
    </div>
    <p class="adm-anteprima__nota" data-anteprima-stato>Scegli un campo: il testo si illumina nella pagina. Clicca un testo nella pagina per trovare il suo campo.
       Quello che scrivi si vede subito qui; sul sito, dopo «Salva».</p>
  </aside>
</div>

<?= $this->render('admin/_storico', ['storico' => $storico, 'nome' => '', 'ritorno' => 'testi/' . $sezione]) ?>
<p><a class="adm-link" href="<?= e(adminUrl('testi')) ?>">← Tutte le sezioni</a></p>
