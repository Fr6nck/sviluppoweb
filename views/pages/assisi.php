<?php
/**
 * Assisi a piedi.
 *
 * Non è una pagina con una mappa incorporata e sei riquadri con l'icona: è un
 * indice numerato, una fotografia e due blocchi di testo. L'indice dice «in
 * che ordine vanno le cose», che è quello che serve a chi ha due giorni.
 *
 * I tempi a piedi restano marcati finché non li verifica qualcuno che li ha
 * camminati. Una mappa con il segnaposto messo a occhio non c'è: le coordinate
 * non erano nel materiale, e un segnaposto sbagliato manda l'ospite alla porta
 * di un altro.
 *
 * @var array $luoghi
 */
?>

<div class="adv-contenuto">
  <header class="adv-heroT">
    <span class="adv-heroT__occhiello"><?= te('assisi.eyebrow') ?></span>
    <h1 class="adv-heroT__titolo">
      <?= te('assisi.title') ?> <span class="adv-firma-inline"><?= te('assisi.sign') ?></span>
    </h1>
    <p class="adv-heroT__spalla"><?= te('assisi.lead') ?></p>
  </header>
</div>

<section class="adv-contenuto adv-editoriale">
  <div class="adv-split adv-split--immagine">

    <div>
      <?= component('section-header', [
          'occhiello' => t('assisi.eyebrow'),
          'titolo'    => t('assisi.places_title'),
          'piccolo'   => true,
      ]) ?>

      <?= component('index-list', [
          'voci' => array_map(static function (array $luogo): array {
              return [
                  'etichetta' => t('places.' . $luogo['id'] . '.name'),
                  'nota_html' => $luogo['walk_minutes']
                      ? e($luogo['walk_minutes'] . ' min ' . t('assisi.walk_label'))
                      : daConfermare(),
              ];
          }, $luoghi),
      ]) ?>

      <p class="adv-nota"><?= te('assisi.places_note') ?></p>
    </div>

    <figure class="adv-figura-verticale adv-rivela">
      <?= component('picture', [
          'src'    => 'img/foto/basilica-tramonto',
          'alt'    => t('home.walk.image_alt'),
          'width'  => 900,
          'height' => 1200,
          'eager'  => true,
      ]) ?>
      <figcaption class="adv-didascalia"><?= te('home.walk.image_credit') ?></figcaption>
    </figure>

  </div>
</section>

<section class="adv-sezione-alt">
  <div class="adv-contenuto adv-editoriale">
    <div class="adv-split">

      <div>
        <?= component('section-header', [
            'occhiello' => t('home.position.eyebrow'),
            'titolo'    => t('assisi.rufino_title'),
            'piccolo'   => true,
        ]) ?>
        <p class="adv-testo adv-testo--grande"><?= te('assisi.rufino_text') ?></p>
      </div>

      <div class="adv-split__nota">
        <h3 class="adv-titolo-sm"><?= te('assisi.moving_title') ?></h3>
        <p class="adv-testo"><?= te('assisi.moving_text') ?></p>
        <p class="adv-nota"><?= te('assisi.moving_note') ?> <?= daConfermare() ?></p>
      </div>

    </div>
  </div>
</section>

<section class="adv-contenuto adv-editoriale">
  <figure class="adv-cornice adv-rivela">
    <?= component('picture', [
        'src'    => 'img/foto/valle-panorama',
        'alt'    => t('home.position.frame_alt'),
        'width'  => 1400,
        'height' => 788,
    ]) ?>
    <figcaption class="adv-cornice__barra">
      <span class="adv-cornice__didascalia"><?= te('home.position.frame_caption') ?></span>
      <span class="adv-cornice__conta"><i aria-hidden="true"></i><?= te('home.position.frame_count') ?></span>
    </figcaption>
  </figure>

  <p class="adv-azione-coda">
    <a class="adv-elenco__link" href="<?= e(url('info')) ?>">
      <?= te('cta.see_info') ?><span aria-hidden="true">&rarr;</span>
    </a>
  </p>
</section>
