<?php
/**
 * @var array<string,mixed> $camera
 * @var list<array<string,mixed>> $schema
 * @var array<string,mixed> $valori
 * @var list<string> $lingue
 */
use ArcoDelVento\Admin\Form;
use ArcoDelVento\Support\Csrf;
use ArcoDelVento\Support\RoomPresenter as R;
?>
<p class="adm-intro">
  <?= e(R::typeLabel($camera)) ?> · <?= e(R::beds($camera)) ?> · fino a <?= (int) $camera['occupancy']['max'] ?>
  <?= (int) $camera['occupancy']['max'] === 1 ? 'ospite' : 'ospiti' ?>.
  <a class="adm-link" href="<?= e(url('room', ['slug' => $camera['slug']['it']], [], 'it')) ?>" target="_blank" rel="noopener">Vedi la pagina ↗</a>
</p>

<?php $foto = immagine('camera.' . $camera['ref']); $mini = anteprima($foto, 'foto'); ?>
<section class="adm-carta adm-camera-foto" aria-labelledby="c-foto">
  <?php if ($mini !== null): ?>
    <img class="adm-camera-foto__img" src="<?= e(asset($mini)) ?>" alt="" width="160" height="120">
  <?php endif; ?>
  <div>
    <h2 id="c-foto">Le fotografie</h2>
    <p class="adm-nota"><?= !empty($camera['photographed']) ? 'La foto principale e, se vuoi, due per la galleria.' : 'Questa camera non ha ancora una foto vera: sul sito compare un disegno.' ?>
      Il campo «Che cosa si vede nella fotografia», qui sotto, è quello che leggono i lettori di schermo: se cambi la foto, controllalo.</p>
    <p class="adm-azioni">
      <a class="adm-bottone adm-bottone--piatto" href="<?= e(adminUrl('immagini/camera.' . $camera['ref'])) ?>"><?= icona('immagine', 17) ?><?= !empty($camera['photographed']) ? 'Cambia la foto' : 'Carica la foto' ?></a>
      <a class="adm-link" href="<?= e(adminUrl('immagini/camera.' . $camera['ref'] . '.galleria-1')) ?>">Galleria 1</a>
      <a class="adm-link" href="<?= e(adminUrl('immagini/camera.' . $camera['ref'] . '.galleria-2')) ?>">Galleria 2</a>
    </p>
  </div>
</section>

<form method="post" action="<?= e(adminUrl('camere/' . rawurlencode((string) $camera['ref']))) ?>" class="adm-modulo adm-gruppo" novalidate>
  <?= Csrf::field() ?>
  <?php foreach ($schema as $campo): ?>
    <?= $this->render('admin/_campo', [
        'campo'  => $campo,
        'valore' => Form::get($valori, $campo['path']),
        'lingue' => $lingue,
        'nome'   => 'f[' . $campo['path'] . ']',
    ]) ?>
  <?php endforeach; ?>
  <div class="adm-salva">
    <button type="submit" class="adm-bottone">Salva la camera</button>
    <span class="adm-nota">Le tariffe valgono subito anche per le prenotazioni dal sito.</span>
  </div>
</form>

<p><a class="adm-link" href="<?= e(adminUrl('camere')) ?>">← Tutte le camere</a></p>
