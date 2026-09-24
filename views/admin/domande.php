<?php
/**
 * @var list<array<string,mixed>> $voci
 * @var list<string> $lingue
 * @var list<array> $storico
 */
use ArcoDelVento\Support\Csrf;

// una riga vuota in fondo, per aggiungere una domanda
$righe   = $voci;
$righe[] = ['id' => '', 'confirmed' => true, 'q' => [], 'a' => [], 'nuova' => true];
?>
<p class="adm-intro">Le domande compaiono nella pagina Informazioni e nella home, e Google le legge come domande frequenti.
   Nella risposta, <code>{dc}</code> diventa il segno «da confermare».</p>

<form method="post" action="<?= e(adminUrl('domande')) ?>" class="adm-modulo" novalidate>
  <?= Csrf::field() ?>
  <?php foreach ($righe as $i => $v): $nuova = !empty($v['nuova']); ?>
    <fieldset class="adm-domanda<?= $nuova ? ' adm-domanda--nuova' : '' ?>">
      <legend><?= $nuova ? 'Nuova domanda' : 'Domanda ' . ($i + 1) ?></legend>
      <input type="hidden" name="d[<?= $i ?>][id]" value="<?= e((string) ($v['id'] ?? '')) ?>">
      <?php foreach ($lingue as $lingua): ?>
        <div class="adm-lingua">
          <label for="q-<?= $i ?>-<?= e($lingua) ?>"><span class="adm-lingua__sigla"><?= e(strtoupper($lingua)) ?></span> Domanda</label>
          <input type="text" id="q-<?= $i ?>-<?= e($lingua) ?>" name="d[<?= $i ?>][q][<?= e($lingua) ?>]" lang="<?= e($lingua) ?>"
                 value="<?= e((string) ($v['q'][$lingua] ?? '')) ?>">
        </div>
        <div class="adm-lingua">
          <label for="a-<?= $i ?>-<?= e($lingua) ?>"><span class="adm-lingua__sigla"><?= e(strtoupper($lingua)) ?></span> Risposta</label>
          <textarea id="a-<?= $i ?>-<?= e($lingua) ?>" name="d[<?= $i ?>][a][<?= e($lingua) ?>]" rows="3" lang="<?= e($lingua) ?>"><?= e((string) ($v['a'][$lingua] ?? '')) ?></textarea>
        </div>
      <?php endforeach; ?>
      <div class="adm-riga-opzioni">
        <div class="adm-campo adm-campo--corto">
          <label for="o-<?= $i ?>">Posizione</label>
          <input type="text" inputmode="numeric" id="o-<?= $i ?>" name="d[<?= $i ?>][ordine]" value="<?= $i + 1 ?>" class="adm-corto">
        </div>
        <div class="adm-campo adm-campo--spunta">
          <input type="hidden" name="d[<?= $i ?>][confermata]" value="0">
          <input type="checkbox" id="c-<?= $i ?>" name="d[<?= $i ?>][confermata]" value="1"<?= !empty($v['confirmed']) ? ' checked' : '' ?>>
          <label for="c-<?= $i ?>">Risposta verificata</label>
        </div>
        <?php if (!$nuova): ?>
          <div class="adm-campo adm-campo--spunta">
            <input type="checkbox" id="x-<?= $i ?>" name="d[<?= $i ?>][elimina]" value="1">
            <label for="x-<?= $i ?>">Elimina questa domanda</label>
          </div>
        <?php endif; ?>
      </div>
    </fieldset>
  <?php endforeach; ?>
  <div class="adm-salva">
    <button type="submit" class="adm-bottone">Salva le domande</button>
  </div>
</form>

<?= $this->render('admin/_storico', ['storico' => $storico, 'nome' => 'domande', 'ritorno' => 'domande']) ?>
