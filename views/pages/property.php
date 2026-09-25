<?php
/**
 * La struttura.
 *
 * Racconta la casa con quello che si sa e segna quello che non si sa: che
 * cosa vuol dire affittacamere, chi la tiene aperta, com'è stare dentro la
 * città. La fotografia è quella del corridoio, con la rosa dei venti
 * intarsiata nel pavimento.
 */
?>

<header class="adv-contenuto adv-testa">
  <?= occhiello(t('property.eyebrow'), 'adv-occhiello--centro') ?>
  <h1 class="adv-titolo adv-titolo--xl"><?= titolo(t('property.title'), t('property.sign')) ?></h1>
  <p class="adv-testa__testo"><?= te('property.lead') ?></p>
  <ul class="adv-testa__meta">
    <li><?= icona('casa', 13) ?><?= e((string) site('rooms_count')) ?> <?= e(mb_strtolower(t('nav.rooms'))) ?></li>
    <li><?= icona('chiave', 13) ?><?= te('property.host_role') ?></li>
    <li><?= icona('pin', 13) ?><?= e(site('address.street')) ?></li>
  </ul>
</header>

<section class="adv-contenuto adv-sezione" aria-labelledby="titolo-cosa">
  <div class="adv-due adv-due--centro">
    <div>
      <?= component('section-header', [
          'occhiello' => t('property.eyebrow'),
          'titolo'    => t('property.what_title'),
          'id'        => 'titolo-cosa',
      ]) ?>
      <p class="adv-testo adv-testo--grande" data-reveal="reveal"><?= te('property.what_text') ?></p>

      <h3 class="adv-titolo adv-titolo--xs adv-spazio-sopra"><?= te('property.city_title') ?></h3>
      <p class="adv-testo adv-testo--sotto"><?= te('property.city_text') ?></p>
    </div>

    <?php /* Il corridoio della casa, con la rosa dei venti intarsiata nel
             pavimento: è il marchio della casa, messo lì da chi la casa
             l'ha fatta, molto prima che esistesse questo sito. */ ?>
    <figure class="adv-figura">
      <div class="adv-figura-alta" data-reveal="photo-reveal">
        <div class="adv-figura-alta__cornice" data-parallax="soft">
          <?= component('picture', [
              'src'   => 'img/casa/casa-corridoio-3x4',
              'alt'   => t('rooms.corridor_alt'),
              'eager' => true,
              'sizes' => '(max-width: 760px) 100vw, 540px',
          ]) ?>
        </div>
      </div>
      <figcaption class="adv-figura__didascalia"><?= te('rooms.corridor_caption') ?></figcaption>
    </figure>
  </div>
</section>

<section class="adv-blocco adv-blocco--sabbia" aria-labelledby="titolo-daniele">
  <div class="adv-contenuto adv-due">
    <div>
      <?= component('section-header', [
          'occhiello' => t('home.host.eyebrow'),
          'titolo'    => t('property.host_title'),
          'id'        => 'titolo-daniele',
      ]) ?>
      <p class="adv-testo adv-testo--grande"><?= te('property.host_text') ?></p>

      <ul class="adv-numeri">
        <?php foreach (tlist('home.host.numbers') as $numero): ?>
          <li class="adv-numeri__voce">
            <span class="adv-numeri__valore"><?= e($numero['value']) ?></span>
            <span class="adv-numeri__nome"><?= e($numero['name']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>

      <h3 class="adv-titolo adv-titolo--xs adv-spazio-sopra"><?= te('property.building_title') ?></h3>
      <p class="adv-testo adv-testo--sotto"><?= te('property.building_text') ?></p>
      <p class="adv-nota"><?= te('property.host_more') ?> <?= daConfermare() ?></p>
    </div>

    <div class="adv-pannello" data-reveal="lift">
      <?= component('person', [
          'nome'     => site('owner'),
          'ruolo'    => t('property.host_role'),
          'bio'      => t('property.host_bio'),
          'contatti' => ['email' => site('contacts.email'), 'phone' => site('contacts.phone')],
      ]) ?>
    </div>
  </div>
</section>

<section class="adv-contenuto adv-sezione" aria-labelledby="titolo-camere">
  <div class="adv-due adv-due--centro">
    <figure class="adv-figura">
      <div class="adv-figura-alta" data-reveal="photo-reveal">
        <div class="adv-figura-alta__cornice" data-parallax="soft">
          <?= component('picture', [
              'src'   => 'img/foto/vicolo-campanile-3x4',
              'alt'   => t('assisi.image_alt'),
              'sizes' => '(max-width: 760px) 100vw, 540px',
          ]) ?>
        </div>
        <span class="adv-chip-foto"><?= icona('pin', 13) ?><?= te('home.position.chip') ?></span>
      </div>
      <figcaption class="adv-figura__didascalia"><?= te('home.hero.image_credit') ?></figcaption>
    </figure>

    <div>
      <?= component('section-header', [
          'occhiello' => t('rooms.eyebrow'),
          'titolo'    => t('home.rooms.title'),
          'firma'     => t('home.rooms.sign'),
          'testo'     => t('home.rooms.lead'),
          'id'        => 'titolo-camere',
      ]) ?>
      <div class="adv-azioni">
        <a class="adv-btn adv-btn--primario" href="<?= e(url('rooms')) ?>"><?= te('cta.all_rooms') ?><?= icona('freccia-su-destra', 14) ?></a>
        <a class="adv-link" href="<?= e(url('assisi')) ?>"><?= te('cta.see_assisi') ?><?= icona('freccia-su-destra', 13) ?></a>
      </div>
    </div>
  </div>
</section>
