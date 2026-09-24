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

<form method="post" action="<?= e(adminUrl('camere/' . rawurlencode((string) $camera['ref']))) ?>" class="adm-modulo" novalidate>
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
