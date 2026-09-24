<?php
/**
 * @var array<string, list<array<string,mixed>>> $schema
 * @var array<string,mixed> $valori
 * @var list<string> $lingue
 * @var list<array> $storico
 */
use ArcoDelVento\Admin\Form;
use ArcoDelVento\Support\Csrf;
?>
<p class="adm-intro">I dati della casa che compaiono sul sito: contatti, orari, regole, obblighi di legge.
   Un campo vuoto sul sito diventa «da confermare»: meglio vuoto che sbagliato.</p>

<form method="post" action="<?= e(adminUrl('struttura')) ?>" class="adm-modulo" novalidate>
  <?= Csrf::field() ?>
  <?php foreach ($schema as $gruppo => $campi): ?>
    <section class="adm-gruppo" aria-labelledby="g-<?= e(md5($gruppo)) ?>">
      <h2 id="g-<?= e(md5($gruppo)) ?>"><?= e($gruppo) ?></h2>
      <?php foreach ($campi as $campo): ?>
        <?= $this->render('admin/_campo', [
            'campo'  => $campo,
            'valore' => Form::get($valori, $campo['path']),
            'lingue' => $lingue,
            'nome'   => 'f[' . $campo['path'] . ']',
        ]) ?>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>
  <div class="adm-salva">
    <button type="submit" class="adm-bottone">Salva</button>
    <span class="adm-nota">Le modifiche vanno subito sul sito.</span>
  </div>
</form>

<?= $this->render('admin/_storico', ['storico' => $storico, 'nome' => 'impostazioni', 'ritorno' => 'struttura']) ?>
