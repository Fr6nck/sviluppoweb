<?php
/**
 * Una camera come riga editoriale (ElencoCamere).
 *
 * Niente scheda, niente ombra, niente pastiglie: i servizi sono testo
 * separato da punto medio, lo stato è una riga in maiuscoletto con il
 * pallino, e la sola cornice che resta è quella della fotografia.
 *
 * @var array $camera
 */

use ArcoDelVento\Support\RoomPresenter as R;

$lingua = locale();
$tariffa = R::fromRate($camera);
?>
<li class="adv-elenco__voce adv-rivela">
  <div class="adv-elenco__riga">

    <figure class="adv-elenco__figura">
      <?= component('picture', [
          'src'   => $camera['images']['list']['src'],
          'alt'   => R::alt($camera, $lingua),
          // Nell'elenco la figura è una colonna di 300px su schermo largo e
          // tutta la riga su mobile: dirlo evita di scaricare 1400px per
          // riempirne 300.
          'sizes' => '(max-width: 900px) 100vw, 300px',
      ]) ?>
    </figure>

    <div class="adv-elenco__corpo">
      <?php /* Niente riga di stato qui. Senza date la disponibilità non si sa, e
               cinque righe che ripetono «disponibilità da verificare» non
               informano nessuno: dicono solo che il sito non ha la risposta.
               Lo stato compare dove diventa vero, cioè fra le camere libere
               del percorso di prenotazione. */ ?>
      <h3 class="adv-elenco__nome">
        <a href="<?= e(R::href($camera, $lingua)) ?>"><?= e(R::name($camera, $lingua)) ?></a>
      </h3>

      <p class="adv-elenco__testo"><?= e(R::metaLine($camera)) ?></p>
      <p class="adv-elenco__servizi"><?= e(R::amenitiesLine($camera)) ?></p>
    </div>

    <div class="adv-elenco__aside">
      <p class="adv-elenco__prezzo">
        <?php if ($tariffa !== null): ?>
          <?php /* «da» solo dove c'è davvero più di una tariffa: su una camera
                   con un prezzo solo prometterebbe uno sconto che non esiste. */ ?>
          <?php if (!R::hasSingleRate($camera)): ?><?= e(t('common.from')) ?> <?php endif; ?>
          <?= e(euro($tariffa)) ?>
          <small><?= te('common.per_night') ?></small>
        <?php else: ?>
          <?= prezzoDaConfermare() ?>
        <?php endif; ?>
      </p>

      <a class="adv-elenco__link" href="<?= e(R::href($camera, $lingua)) ?>">
        <?= te('cta.see_room') ?><span aria-hidden="true">&rarr;</span>
      </a>
    </div>

  </div>
</li>
