<?php
/**
 * La home.
 *
 * Il racconto va: si arriva → si capisce dove si è → si vedono le camere →
 * si conosce chi tiene aperto → si misura la città → si tolgono i dubbi →
 * si prenota.
 *
 * Il mattone pieno compare due volte in tutta la pagina — l'apertura e la
 * chiusura — perché il design system lo vuole prezioso. In mezzo si alternano
 * «surface» e «surface-alt», e il ritmo lo tengono l'aria e i filetti.
 *
 * @var array $camere
 * @var array $luoghi
 * @var array $domande
 */

use ArcoDelVento\Support\RoomPresenter as R;

$lingua = locale();
?>

<?php /* ---------------------------------------------- i quattro fatti */ ?>
<div class="adv-contenuto adv-fascia-fiducia">
  <div class="adv-fiducia">
    <ul class="adv-fiducia__lista">
      <?php foreach (tlist('home.trust') as $fatto): ?>
        <li>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M20 6L9 17l-5-5"/>
          </svg>
          <?= e($fatto) ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>

<?php /* --------------------------------------------------- l'apertura */ ?>
<section class="adv-hero adv-rientrata">
  <div class="adv-contenuto">
    <div class="adv-hero__interno">

      <div>
        <span class="adv-hero__occhiello"><?= te('home.hero.eyebrow') ?></span>
        <h1 class="adv-hero__titolo"><?= te('home.hero.title') ?></h1>
        <span class="adv-hero__firma"><?= te('home.hero.sign') ?></span>
        <p class="adv-hero__testo"><?= te('home.hero.lead') ?></p>

        <div class="adv-hero__azioni">
          <?php /* Sul mattone l'azione principale passa alla terracotta:
                   mattone su mattone non si stacca. */ ?>
          <a class="adv-btn adv-btn--secondario adv-btn--lg" href="<?= e(url('book')) ?>"><?= te('cta.book') ?></a>
          <a class="adv-btn adv-btn--contorno adv-btn--lg" href="<?= e(url('rooms')) ?>"><?= te('cta.see_rooms') ?></a>
        </div>
      </div>

      <figure class="adv-hero__figura adv-arco">
        <?= component('picture', [
            'src'    => 'img/foto/vicolo-campanile',
            'alt'    => t('home.hero.image_alt'),
            'width'  => 900,
            'height' => 1200,
            'eager'  => true,
        ]) ?>
      </figure>

    </div>
  </div>
</section>

<?php /* La barra sale sull'apertura di 48px: dice che la prenotazione viene
         prima della contemplazione. Su schermo stretto torna in fila. */ ?>
<div class="adv-contenuto adv-barra-sospesa">
  <?= partial('booking-bar', ['id' => 'home']) ?>
</div>

<?php /* --------------------------------------------------- il manifesto */ ?>
<section class="adv-contenuto adv-editoriale adv-editoriale--stretto">
  <div class="adv-manifesto">
    <p class="adv-manifesto__testo">
      <?= te('home.manifesto.text') ?>
      <span class="adv-manifesto__firma"><?= te('home.manifesto.sign') ?></span>
    </p>
    <p class="adv-manifesto__autore"><?= te('home.manifesto.author') ?></p>
  </div>
</section>

<?php /* ------------------------------------- dove si è, e San Rufino */ ?>
<section class="adv-sezione-alt" aria-labelledby="posizione">
  <div class="adv-contenuto adv-editoriale">

    <div class="adv-split">
      <div>
        <?= component('section-header', [
            'occhiello' => t('home.position.eyebrow'),
            'titolo'    => t('home.position.title'),
            'firma'     => t('home.position.sign'),
        ]) ?>
        <p class="adv-testo"><?= te('home.position.lead') ?></p>
      </div>

      <div class="adv-split__nota">
        <h3 class="adv-titolo-sm" id="posizione"><?= te('home.position.rufino_title') ?></h3>
        <p class="adv-testo"><?= te('home.position.rufino_text') ?></p>
        <p class="adv-nota"><?= te('home.position.rufino_note') ?> <?= daConfermare() ?></p>
      </div>
    </div>

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

  </div>
</section>

<?php /* ------------------------------------------------- le cinque camere */ ?>
<section class="adv-contenuto adv-editoriale" aria-labelledby="camere">
  <?= component('section-header', [
      'occhiello' => t('home.rooms.eyebrow'),
      'titolo'    => t('home.rooms.title'),
      'firma'     => t('home.rooms.sign'),
      'testo'     => t('home.rooms.lead'),
  ]) ?>

  <ul class="adv-elenco" id="camere">
    <?php foreach ($camere as $camera): ?>
      <?= component('room-row', ['camera' => $camera]) ?>
    <?php endforeach; ?>
  </ul>

  <p class="adv-azione-coda">
    <a class="adv-elenco__link" href="<?= e(url('rooms')) ?>">
      <?= te('cta.all_rooms') ?><span aria-hidden="true">&rarr;</span>
    </a>
  </p>
</section>

<?php /* ------------------------------------------------------ chi c'è */ ?>
<section class="adv-sezione-alt" aria-labelledby="daniele">
  <div class="adv-contenuto adv-editoriale">
    <div class="adv-split adv-split--stretta">

      <div>
        <?= component('section-header', [
            'occhiello' => t('home.host.eyebrow'),
            'titolo'    => t('home.host.title'),
            'firma'     => t('home.host.sign'),
            'testo'     => t('home.host.lead'),
        ]) ?>

        <ul class="adv-numeri" id="daniele">
          <?php foreach (tlist('home.host.numbers') as $numero): ?>
            <li>
              <span class="adv-numeri__valore"><?= e($numero['value']) ?></span>
              <span class="adv-numeri__nome"><?= e($numero['name']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>

        <p class="adv-azione-coda">
          <a class="adv-elenco__link" href="<?= e(url('property')) ?>">
            <?= te('cta.see_property') ?><span aria-hidden="true">&rarr;</span>
          </a>
        </p>
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

<?php /* ------------------------------------------------ Assisi a piedi */ ?>
<section class="adv-contenuto adv-editoriale" aria-labelledby="a-piedi">
  <div class="adv-split adv-split--immagine">

    <div>
      <?= component('section-header', [
          'occhiello' => t('home.walk.eyebrow'),
          'titolo'    => t('home.walk.title'),
          'firma'     => t('home.walk.sign'),
          'testo'     => t('home.walk.lead'),
      ]) ?>

      <div id="a-piedi">
        <?= component('index-list', [
            'voci' => array_map(static function (array $luogo): array {
                return [
                    'etichetta' => t('places.' . $luogo['id'] . '.name'),
                    'nota_html' => $luogo['walk_minutes']
                        ? e($luogo['walk_minutes'] . ' min ' . t('assisi.walk_label'))
                        : daConfermare(),
                ];
            }, array_slice($luoghi, 0, 5)),
        ]) ?>
      </div>

      <p class="adv-azione-coda">
        <a class="adv-elenco__link" href="<?= e(url('assisi')) ?>">
          <?= te('cta.see_assisi') ?><span aria-hidden="true">&rarr;</span>
        </a>
      </p>
    </div>

    <figure class="adv-figura-verticale adv-rivela">
      <?= component('picture', [
          'src'    => 'img/foto/basilica-tramonto',
          'alt'    => t('home.walk.image_alt'),
          'width'  => 900,
          'height' => 1200,
      ]) ?>
      <figcaption class="adv-didascalia"><?= te('home.walk.image_credit') ?></figcaption>
    </figure>

  </div>
</section>

<?php /* ------------------------------------------- le domande di sempre */ ?>
<section class="adv-sezione-alt" aria-labelledby="domande">
  <div class="adv-contenuto adv-editoriale">
    <?= component('section-header', [
        'occhiello' => t('home.faq.eyebrow'),
        'titolo'    => t('home.faq.title'),
        'firma'     => t('home.faq.sign'),
    ]) ?>
    <div id="domande">
      <?= component('faq', ['domande' => $domande]) ?>
    </div>
    <p class="adv-azione-coda">
      <a class="adv-elenco__link" href="<?= e(url('info')) ?>">
        <?= te('cta.see_info') ?><span aria-hidden="true">&rarr;</span>
      </a>
    </p>
  </div>
</section>

<?php /* --------------------------------------------------- la chiusura */ ?>
<section class="adv-fondo-mattone adv-rientrata adv-chiusura">
  <div class="adv-contenuto">
    <?= component('section-header', [
        'occhiello' => t('home.closing.eyebrow'),
        'titolo'    => t('home.closing.title'),
        'firma'     => t('home.closing.sign'),
        'testo'     => t('home.closing.lead'),
    ]) ?>
    <div class="adv-hero__azioni adv-chiusura__azioni">
      <a class="adv-btn adv-btn--secondario adv-btn--lg" href="<?= e(url('book')) ?>"><?= te('cta.book') ?></a>
      <a class="adv-btn adv-btn--contorno adv-btn--lg" href="<?= e(url('contact')) ?>"><?= te('cta.write') ?></a>
    </div>
    <?= component('ornament') ?>
  </div>
</section>
