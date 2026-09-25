<?php
/**
 * La home.
 *
 * Il racconto va: il titolo → le fotografie, con la barra di prenotazione
 * che ci sale sopra → la casa in una riga → le cinque camere → il centro
 * storico a piedi → chi tiene aperto, e come si arriva → le domande. La
 * chiusura in terracotta, con l'invito a prenotare, sta nel piè di pagina.
 *
 * @var array $camere
 * @var array $luoghi
 * @var array $domande
 */

use ArcoDelVento\Support\RoomPresenter as R;

$lingua = locale();

// Le fotografie dell'apertura: Assisi, la casa, una camera vera.
$diapositive = [
    ['src' => 'img/foto/valle-panorama-16x9', 'alt' => t('home.position.frame_alt'), 'didascalia' => t('home.slides.valle')],
    ['src' => 'img/foto/vicolo-campanile-4x3', 'alt' => t('home.hero.image_alt'), 'didascalia' => t('home.slides.vicolo')],
    ['src' => 'img/foto/basilica-tramonto-3x4', 'alt' => t('home.walk.image_alt'), 'didascalia' => t('home.slides.basilica'), 'classe' => 'adv-diapositive__foto--alta'],
    ['src' => 'img/casa/casa-corridoio-4x3', 'alt' => t('rooms.corridor_alt'), 'didascalia' => t('home.slides.corridoio')],
];
foreach ($camere as $unaCamera) {
    // La prima camera fotografata che guarda su San Rufino, altrimenti la prima fotografata.
    if (R::isPhotographed($unaCamera) && R::viewLabel($unaCamera) !== null) {
        $diapositive[] = ['src' => $unaCamera['images']['hero']['src'], 'alt' => R::alt($unaCamera, $lingua), 'didascalia' => t('home.slides.camera')];
        break;
    }
}
$totale = count($diapositive);

// «Apri la mappa»: le coordinate se il titolare le ha inserite, altrimenti l'indirizzo.
$lat = site('geo.latitude');
$lng = site('geo.longitude');
$mappaCasa = ($lat !== null && $lng !== null && $lat !== '' && $lng !== '')
    ? mappa($lat . ',' . $lng)
    : mappa(site('address.street') . ', ' . site('address.city') . ' ' . site('address.province'));

$metri = null;
foreach ((array) site('parking_spots', []) as $posto) {
    if (!empty($posto['metres'])) {
        $metri = (int) $posto['metres'];
        break;
    }
}
?>

<?php /* ---------------------------------------------------- l'apertura */ ?>
<section class="adv-apertura" aria-labelledby="titolo-pagina">
  <?= occhiello(t('home.hero.eyebrow'), 'adv-occhiello--centro') ?>
  <h1 class="adv-titolo adv-titolo--xl" id="titolo-pagina"><?= titolo(t('home.hero.title'), t('home.hero.sign')) ?></h1>
  <p class="adv-apertura__testo"><?= righe(t('home.hero.lead')) ?></p>
  <ul class="adv-apertura__luoghi">
    <li><?= icona('pin', 13) ?><?= te('home.hero.place') ?></li>
    <li><?= e(site('address.street')) ?></li>
  </ul>
  <a class="adv-tondo adv-apertura__giu" href="#camere" aria-label="<?= te('home.hero.scroll') ?>"><?= icona('freccia-giu', 16) ?></a>
</section>

<?php /* ------------------------------------------------ le fotografie */ ?>
<section class="adv-diapositive" aria-roledescription="carosello" aria-label="<?= te('home.slides.label') ?>" data-diapositive>
  <div class="adv-diapositive__cornice" data-parallax="hero">
    <?php foreach ($diapositive as $i => $d): ?>
      <figure class="adv-diapositive__foto<?= $i === 0 ? ' is-attiva' : '' ?><?= !empty($d['classe']) ? ' ' . e($d['classe']) : '' ?>"
              data-diapositiva data-didascalia="<?= e($d['didascalia']) ?>"<?= $i > 0 ? ' aria-hidden="true"' : '' ?>>
        <?= component('picture', [
            'src'   => $d['src'],
            'alt'   => $d['alt'],
            'eager' => $i === 0,
            'differita' => $i > 0,
            'sizes' => '100vw',
        ]) ?>
      </figure>
    <?php endforeach; ?>
  </div>
  <div class="adv-diapositive__velo" aria-hidden="true"></div>

  <button class="adv-diapositive__freccia adv-diapositive__freccia--prec" type="button"
          aria-label="<?= te('home.slides.prev') ?>" data-diapositive-prec><?= icona('freccia-sinistra', 17) ?></button>
  <button class="adv-diapositive__freccia adv-diapositive__freccia--succ" type="button"
          aria-label="<?= te('home.slides.next') ?>" data-diapositive-succ><?= icona('freccia-destra', 17) ?></button>

  <div class="adv-diapositive__barra">
    <?php /* Le coordinate sono quelle della città, non della casa. */ ?>
    <span class="adv-diapositive__coord" aria-hidden="true">43°04′ N &nbsp; 12°37′ E</span>
    <p class="adv-diapositive__didascalia" data-diapositive-didascalia><?= e($diapositive[0]['didascalia']) ?></p>
    <div class="adv-diapositive__conta">
      <span aria-hidden="true" data-diapositive-numero>01</span><i aria-hidden="true"></i><span aria-hidden="true"><?= sprintf('%02d', $totale) ?></span>
      <button class="adv-diapositive__pausa" type="button" data-diapositive-pausa
              aria-label="<?= te('home.slides.pause') ?>"
              data-etichetta-pausa="<?= te('home.slides.pause') ?>" data-etichetta-avvia="<?= te('home.slides.play') ?>">
        <span data-icona-pausa><?= icona('pausa', 14) ?></span><span data-icona-avvia hidden><?= icona('avvia', 14) ?></span>
      </button>
    </div>
  </div>
</section>

<?= partial('booking-bar', ['id' => 'home', 'sospesa' => true]) ?>

<ul class="adv-fiducia">
  <?php foreach (tlist('home.trust') as $i => $fatto): ?>
    <li><?= $i === 0 ? icona('chiave', 13) : '' ?><?= e($fatto) ?></li>
  <?php endforeach; ?>
</ul>

<?php /* ----------------------------------------------- la casa in una riga */ ?>
<div class="adv-contenuto adv-intro" data-reveal="reveal">
  <span class="adv-intro__icona" aria-hidden="true"><?= icona('rosa', 30) ?></span>
  <p class="adv-intro__testo"><?= righe(t('home.manifesto.text')) ?> <em><?= te('home.manifesto.sign') ?></em></p>
  <span class="adv-intro__filo" aria-hidden="true"></span>
  <p class="adv-intro__firma"><?= righe(t('home.manifesto.author')) ?></p>
</div>

<?php /* --------------------------------------------------- le camere */ ?>
<section class="adv-contenuto adv-sezione" id="camere" aria-labelledby="titolo-camere" data-carosello>
  <div class="adv-sezione__testa">
    <div>
      <?= occhiello(t('home.rooms.eyebrow')) ?>
      <h2 class="adv-titolo adv-titolo--l" id="titolo-camere" data-reveal="title-reveal"><?= titolo(t('home.rooms.title'), t('home.rooms.sign')) ?></h2>
      <p class="adv-testo" data-reveal="reveal"><?= te('home.rooms.lead') ?></p>
    </div>
    <div class="adv-sezione__controlli">
      <a class="adv-link" href="<?= e(url('rooms')) ?>"><?= te('home.rooms.discover') ?><?= icona('freccia-su-destra', 13) ?></a>
      <button class="adv-tondo" type="button" aria-label="<?= te('home.rooms.prev') ?>" data-carosello-prec><?= icona('freccia-sinistra', 16) ?></button>
      <button class="adv-tondo" type="button" aria-label="<?= te('home.rooms.next') ?>" data-carosello-succ><?= icona('freccia-destra', 16) ?></button>
    </div>
  </div>

  <ul class="adv-carosello__traccia" data-carosello-traccia>
    <?php foreach ($camere as $camera): ?>
      <li><?= component('room-card', ['camera' => $camera]) ?></li>
    <?php endforeach; ?>
  </ul>

  <div class="adv-carosello__piede" aria-hidden="true">
    <span>01 — <?= sprintf('%02d', count($camere)) ?></span>
    <span class="adv-carosello__barra"><i data-carosello-barra></i></span>
    <span><?= te('home.rooms.tagline') ?></span>
  </div>
</section>

<?php /* ----------------------------------------- il centro storico a piedi */ ?>
<section class="adv-luogo" aria-labelledby="titolo-luogo">
  <div class="adv-contenuto adv-luogo__griglia">

    <figure class="adv-figura-alta" data-reveal="photo-reveal">
      <div class="adv-figura-alta__cornice" data-parallax="soft">
        <?= component('picture', [
            'src'   => 'img/foto/vicolo-campanile-3x4',
            'alt'   => t('home.hero.image_alt'),
            'sizes' => '(max-width: 760px) 100vw, 540px',
        ]) ?>
      </div>
      <span class="adv-figura-alta__verticale" aria-hidden="true"><?= te('home.position.vertical') ?></span>
      <figcaption class="adv-chip-foto"><?= icona('pin', 13) ?><?= te('home.position.chip') ?></figcaption>
    </figure>

    <div>
      <?= occhiello(t('home.position.eyebrow')) ?>
      <h2 class="adv-titolo adv-titolo--l" id="titolo-luogo" data-reveal="title-reveal"><?= titolo(t('home.position.title'), t('home.position.sign')) ?></h2>
      <p class="adv-testo" data-reveal="reveal"><?= te('home.position.lead') ?></p>
      <p class="adv-testo" data-reveal="reveal"><?= te('home.position.rufino_text') ?></p>

      <?= component('index-list', [
          'voci' => array_map(static function (array $luogo): array {
              $nome = t('places.' . $luogo['id'] . '.name');
              return [
                  'etichetta' => $nome,
                  'nota'      => t('places.' . $luogo['id'] . '.note'),
                  'href'      => mappa($nome . ', Assisi'),
                  'esterno'   => true,
              ];
          }, array_slice($luoghi, 0, 4)),
      ]) ?>

      <a class="adv-btn adv-btn--contorno" href="<?= e(url('assisi')) ?>"><?= te('cta.see_assisi') ?><?= icona('freccia-su-destra', 14) ?></a>
    </div>

  </div>
</section>

<?php /* ------------------------------------ chi tiene aperto, e come si arriva */ ?>
<section class="adv-contenuto adv-sezione adv-ospitalita" aria-labelledby="titolo-ospitalita">
  <div class="adv-ospitalita__griglia">
    <div>
      <?= occhiello(t('home.host.eyebrow')) ?>
      <h2 class="adv-titolo adv-titolo--m" id="titolo-ospitalita" data-reveal="title-reveal"><?= titolo(t('home.host.title'), t('home.host.sign')) ?></h2>
    </div>
    <div data-reveal="drift-in">
      <p class="adv-testo"><?= te('home.host.lead') ?></p>
      <ul class="adv-numeri">
        <?php foreach (tlist('home.host.numbers') as $numero): ?>
          <li class="adv-numeri__voce">
            <span class="adv-numeri__valore"><?= e($numero['value']) ?></span>
            <span class="adv-numeri__nome"><?= e($numero['name']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <?= component('reviews') ?>
      <p><a class="adv-link" href="<?= e(url('property')) ?>"><?= te('cta.see_property') ?><?= icona('freccia-su-destra', 13) ?></a></p>
    </div>
  </div>

  <div class="adv-mappa" id="come-arrivare">
    <div class="adv-mappa__area">
      <?php /* Un disegno, non una mappa: cerchi concentrici attorno al segno.
               La mappa vera si apre fuori, con le coordinate giuste. */ ?>
      <svg class="adv-mappa__disegno" viewBox="0 0 1200 380" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">
        <g fill="none" stroke="currentColor" stroke-width="1">
          <circle cx="600" cy="150" r="70"/><circle cx="600" cy="150" r="140"/>
          <circle cx="600" cy="150" r="220"/><circle cx="600" cy="150" r="310"/>
          <circle cx="600" cy="150" r="410"/><circle cx="600" cy="150" r="520"/>
          <path d="M0 250 C 220 210, 380 300, 600 150 S 980 60, 1200 110"/>
          <path d="M120 0 C 260 120, 420 90, 600 150 S 860 330, 1000 380"/>
        </g>
      </svg>
      <h3 class="adv-occhiello adv-occhiello--centro"><span class="adv-occhiello__filo" aria-hidden="true"></span><?= te('home.arrive.eyebrow') ?></h3>
      <?= icona('pin', 30, 'adv-mappa__pin') ?>
      <p class="adv-mappa__indirizzo"><?= e(site('address.street')) ?> · <?= e(site('address.city')) ?></p>
      <a class="adv-btn adv-btn--contorno" href="<?= e($mappaCasa) ?>" target="_blank" rel="noopener">
        <?= te('home.arrive.map') ?><span class="adv-visually-hidden">— <?= te('home.position.new_tab') ?></span><?= icona('freccia-su-destra', 14) ?>
      </a>
    </div>

    <ul class="adv-mappa__schede">
      <li class="adv-mappa__scheda">
        <span class="adv-mappa__numero" aria-hidden="true">01</span>
        <span><strong><?= te('home.arrive.navigator_label') ?></strong><span><?= e(site('navigation.by_car')) ?></span></span>
      </li>
      <?php if ($metri): ?>
        <li class="adv-mappa__scheda">
          <span class="adv-mappa__numero" aria-hidden="true">02</span>
          <span><strong><?= te('home.arrive.parking_label') ?></strong><span><?= te('home.arrive.parking_value', ['metres' => $metri]) ?></span></span>
        </li>
      <?php endif; ?>
      <li class="adv-mappa__scheda">
        <span class="adv-mappa__numero" aria-hidden="true"><?= $metri ? '03' : '02' ?></span>
        <span><strong><?= te('home.arrive.train_label') ?></strong><span><?= te('home.arrive.train_value') ?></span></span>
      </li>
      <li class="adv-mappa__scheda">
        <span class="adv-mappa__numero" aria-hidden="true"><?= $metri ? '04' : '03' ?></span>
        <span><strong><?= te('home.arrive.plane_label') ?></strong><span><?= te('home.arrive.plane_value') ?></span></span>
      </li>
    </ul>
    <p class="adv-mappa__nota">
      <?= te('info.known.car') ?>
      <a class="adv-contatto" href="<?= e(url('info')) ?>"><?= te('cta.see_info') ?></a>
    </p>
  </div>
</section>

<?php /* --------------------------------------------- le domande di sempre */ ?>
<section class="adv-blocco adv-blocco--sabbia" aria-labelledby="titolo-domande">
  <div class="adv-contenuto adv-due">
    <div>
      <?= component('section-header', [
          'occhiello' => t('home.faq.eyebrow'),
          'titolo'    => t('home.faq.title'),
          'firma'     => t('home.faq.sign'),
          'id'        => 'titolo-domande',
      ]) ?>
      <a class="adv-link" href="<?= e(url('info')) ?>"><?= te('cta.see_info') ?><?= icona('freccia-su-destra', 13) ?></a>
    </div>
    <?= component('faq', ['domande' => $domande]) ?>
  </div>
</section>
