<?php
/**
 * La struttura.
 *
 * Racconta la casa con quello che si sa, e segna quello che non si sa. La
 * fotografia della casa non è ancora arrivata: al suo posto c'è il segnaposto
 * dentro la cornice, che dice dove andrà e in che formato.
 */
?>

<div class="adv-contenuto">
  <header class="adv-heroT">
    <span class="adv-heroT__occhiello"><?= te('property.eyebrow') ?></span>
    <h1 class="adv-heroT__titolo">
      <?= te('property.title') ?> <span class="adv-firma-inline"><?= te('property.sign') ?></span>
    </h1>
    <p class="adv-heroT__spalla"><?= te('property.lead') ?></p>
    <ul class="adv-heroT__meta">
      <li><?= e(site('rooms_count')) ?> <?= te('nav.rooms') ?></li>
      <li><?= te('property.host_role') ?></li>
      <li><?= e(site('address.street')) ?></li>
    </ul>
  </header>
</div>

<section class="adv-contenuto adv-editoriale">
  <div class="adv-split adv-split--immagine">
    <div>
      <?= component('section-header', [
          'occhiello' => t('property.eyebrow'),
          'titolo'    => t('property.what_title'),
          'piccolo'   => true,
      ]) ?>
      <p class="adv-testo adv-testo--grande"><?= te('property.what_text') ?></p>

      <h3 class="adv-titolo-sm adv-spazio-sopra"><?= te('property.city_title') ?></h3>
      <p class="adv-testo"><?= te('property.city_text') ?></p>
    </div>

    <?php /* Il corridoio della casa, con la rosa dei venti intarsiata nel
             pavimento: è il marchio della casa, messo lì da chi la casa
             l'ha fatta, molto prima che esistesse questo sito. */ ?>
    <figure class="adv-figura-verticale adv-rivela">
      <?= component('picture', [
          'src'   => 'img/casa/casa-corridoio-3x4',
          'alt'   => t('property.corridor_alt'),
          'eager' => true,
          'sizes' => '(max-width: 900px) 100vw, 480px',
      ]) ?>
      <figcaption class="adv-didascalia"><?= te('property.corridor_caption') ?></figcaption>
    </figure>
  </div>

  <?= component('ornament', ['corto' => true]) ?>
</section>

<section class="adv-sezione-alt">
  <div class="adv-contenuto adv-editoriale">
    <div class="adv-split adv-split--stretta">

      <div>
        <?= component('section-header', [
            'occhiello' => t('home.host.eyebrow'),
            'titolo'    => t('property.host_title'),
            'piccolo'   => true,
        ]) ?>
        <p class="adv-testo adv-testo--grande"><?= te('property.host_text') ?></p>

        <h3 class="adv-titolo-sm adv-spazio-sopra"><?= te('property.building_title') ?></h3>
        <p class="adv-testo"><?= te('property.building_text') ?></p>
        <p class="adv-nota"><?= te('property.host_more') ?> <?= daConfermare() ?></p>
      </div>

      <div class="adv-persone adv-persone--una">
        <?= component('person', [
            'nome'  => site('owner'),
            'ruolo' => t('property.host_role'),
            'bio'   => t('property.host_bio'),
            'contatti' => ['email' => site('contacts.email'), 'phone' => site('contacts.phone')],
        ]) ?>
      </div>

    </div>
  </div>
</section>

<section class="adv-contenuto adv-editoriale">
  <div class="adv-split adv-split--immagine">
    <div>
      <?= component('section-header', [
          'occhiello' => t('rooms.eyebrow'),
          'titolo'    => t('home.rooms.title'),
          'firma'     => t('home.rooms.sign'),
          'testo'     => t('home.rooms.lead'),
      ]) ?>
      <p class="adv-azione-coda">
        <a class="adv-elenco__link" href="<?= e(url('rooms')) ?>">
          <?= te('cta.all_rooms') ?><span aria-hidden="true">&rarr;</span>
        </a>
      </p>
    </div>

    <figure class="adv-figura-verticale">
      <?= component('picture', [
          'src'    => 'img/foto/vicolo-campanile',
          'alt'    => t('assisi.image_alt'),
          'width'  => 900,
          'height' => 1200,
      ]) ?>
      <figcaption class="adv-didascalia"><?= te('home.hero.image_credit') ?></figcaption>
    </figure>
  </div>
</section>
