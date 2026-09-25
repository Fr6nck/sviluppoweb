<?php
/**
 * Assisi a piedi.
 *
 * La valle in grande, poi l'elenco numerato dei sei posti che si raggiungono
 * camminando, ciascuno con il link alla mappa; poi San Rufino e come ci si
 * muove.
 *
 * I tempi a piedi restano marcati finché non li verifica qualcuno che li ha
 * camminati. Una mappa incorporata non c'è: le coordinate della casa non
 * erano nel materiale, e un segnaposto messo a occhio manda l'ospite alla
 * porta di un altro.
 *
 * @var array $luoghi
 */
?>

<header class="adv-contenuto adv-testa">
  <?= occhiello(t('assisi.eyebrow'), 'adv-occhiello--centro') ?>
  <h1 class="adv-titolo adv-titolo--xl"><?= titolo(t('assisi.title'), t('assisi.sign')) ?></h1>
  <p class="adv-testa__testo"><?= te('assisi.lead') ?></p>
</header>

<div class="adv-contenuto">
  <figure class="adv-banda" data-reveal="photo-reveal">
    <div class="adv-banda__cornice" data-parallax="soft">
      <?= component('picture', [
          'src'   => 'img/foto/valle-panorama-16x9',
          'alt'   => t('home.position.frame_alt'),
          'eager' => true,
          'sizes' => '(max-width: 1170px) 100vw, 1170px',
      ]) ?>
    </div>
    <figcaption class="adv-chip-foto"><?= icona('pin', 13) ?><?= te('home.hero.place') ?></figcaption>
  </figure>
</div>

<section class="adv-contenuto adv-sezione" aria-labelledby="titolo-luoghi">
  <div class="adv-due adv-due--centro">

    <figure class="adv-figura">
      <div class="adv-figura-alta" data-reveal="photo-reveal">
        <div class="adv-figura-alta__cornice" data-parallax="soft">
          <?= component('picture', [
              'src'   => 'img/foto/basilica-tramonto-3x4',
              'alt'   => t('home.walk.image_alt'),
              'sizes' => '(max-width: 760px) 100vw, 540px',
          ]) ?>
        </div>
      </div>
      <figcaption class="adv-figura__didascalia"><?= te('home.walk.image_credit') ?></figcaption>
    </figure>

    <div>
      <?= component('section-header', [
          'occhiello' => t('assisi.eyebrow'),
          'titolo'    => t('assisi.places_title'),
          'id'        => 'titolo-luoghi',
      ]) ?>

      <?= component('index-list', [
          'voci' => array_map(static function (array $luogo): array {
              $nome  = t('places.' . $luogo['id'] . '.name');
              $tempo = $luogo['walk_minutes']
                  ? e($luogo['walk_minutes'] . ' min ' . t('assisi.walk_label'))
                  : daConfermare();
              return [
                  'etichetta' => $nome,
                  'nota_html' => e(t('places.' . $luogo['id'] . '.note')) . ' · ' . $tempo,
                  'href'      => mappa($nome . ', Assisi'),
                  'esterno'   => true,
              ];
          }, $luoghi),
      ]) ?>

      <p class="adv-nota"><?= te('assisi.places_note') ?></p>
    </div>

  </div>
</section>

<section class="adv-blocco adv-blocco--sabbia" aria-labelledby="titolo-rufino">
  <div class="adv-contenuto adv-due">
    <div>
      <?= component('section-header', [
          'occhiello' => t('assisi.eyebrow'),
          'titolo'    => t('assisi.rufino_title'),
          'id'        => 'titolo-rufino',
      ]) ?>
      <p class="adv-testo adv-testo--grande"><?= te('assisi.rufino_text') ?></p>
    </div>

    <div class="adv-pannello" data-reveal="lift">
      <div class="adv-pannello__testa">
        <span class="adv-pannello__icona" aria-hidden="true"><?= icona('navigatore', 18) ?></span>
        <h3 class="adv-titolo adv-titolo--xs"><?= te('assisi.moving_title') ?></h3>
      </div>
      <p class="adv-testo"><?= te('assisi.moving_text') ?></p>
      <p class="adv-nota"><?= te('assisi.moving_note') ?> <?= daConfermare() ?></p>
      <div class="adv-azioni">
        <a class="adv-link" href="<?= e(url('info')) ?>"><?= te('cta.see_info') ?><?= icona('freccia-su-destra', 13) ?></a>
      </div>
    </div>
  </div>
</section>
