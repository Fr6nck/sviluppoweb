<?php
/**
 * La pagina di una singola camera.
 *
 * Il nome in grande, sotto la fotografia larga; poi tutto ciò che un ospite
 * deve sapere prima di scegliere — letti, occupazione, configurazioni, bagno,
 * esposizione, servizi — con accanto la scheda per prenotarla. In fondo la
 * galleria e le altre camere.
 *
 * La Camera 01 non ha ancora la sua fotografia: al suo posto c'è il
 * segnaposto disegnato, e la pagina lo dice.
 *
 * @var array $camera
 * @var array $altreCamere
 */

use ArcoDelVento\Support\RoomPresenter as R;

$lingua  = locale();
$nome    = R::name($camera, $lingua);
$foto    = R::isPhotographed($camera);
$massimo = (int) ($camera['occupancy']['max'] ?? 1);
$vista   = R::viewLabel($camera);

// L'ultima parola del nome va nella mano del logotipo, ma solo se è una
// parola: «02» in corsivo a mano si legge male, e «Camera 02» resta tutto in
// Prata. Un nome di una parola sola resta dritto.
$parole = preg_split('/\s+/u', trim($nome)) ?: [$nome];
$firma  = count($parole) > 1 && preg_match('/\p{L}/u', (string) end($parole)) ? array_pop($parole) : null;
?>

<header class="adv-contenuto adv-testa">
  <?= occhiello(mb_strtoupper(t('room.eyebrow') . ' · ' . R::typeLabel($camera)), 'adv-occhiello--centro') ?>
  <h1 class="adv-titolo adv-titolo--xl"><?= titolo(implode(' ', $parole), $firma) ?></h1>
  <ul class="adv-testa__meta">
    <li><?= icona('ospiti', 13) ?><?= $massimo === 1 ? te('rooms.card.guests_one') : te('rooms.card.guests_up_to', ['count' => $massimo]) ?></li>
    <li><?= icona('letto', 13) ?><?= e(R::beds($camera)) ?></li>
    <li><?= icona('bagno', 13) ?><?= te(!empty($camera['bathroom']['private']) ? 'bathroom.private' : 'bathroom.shared') ?></li>
    <?php if ($vista !== null): ?>
      <li><?= icona('finestra', 13) ?><?= e($vista) ?></li>
    <?php endif; ?>
  </ul>
</header>

<div class="adv-contenuto">
  <figure class="adv-banda" data-reveal="photo-reveal">
    <div class="adv-banda__cornice" data-parallax="soft">
      <?= component('picture', [
          'src'   => $camera['images']['hero']['src'],
          'alt'   => R::alt($camera, $lingua),
          'eager' => true,
          'sizes' => '(max-width: 1170px) 100vw, 1170px',
      ]) ?>
    </div>
    <?php if (!$foto): ?>
      <figcaption class="adv-chip-foto"><?= icona('finestra', 13) ?><?= te('room.photo_pending') ?></figcaption>
    <?php endif; ?>
  </figure>
</div>

<section class="adv-contenuto adv-sezione" aria-labelledby="titolo-caratteristiche">
  <div class="adv-due adv-due--scheda">

    <div>
      <?= component('section-header', [
          'occhiello' => t('room.eyebrow'),
          'titolo'    => t('room.characteristics'),
          'misura'    => 's',
          'id'        => 'titolo-caratteristiche',
      ]) ?>

      <dl class="adv-fatti">
        <div class="adv-fatti__riga">
          <dt><?= te('room.meta.occupancy') ?></dt>
          <dd><?= $massimo ?> <?= te($massimo === 1 ? 'common.guest' : 'common.guests') ?></dd>
        </div>
        <div class="adv-fatti__riga">
          <dt><?= te('room.meta.beds') ?></dt>
          <dd><?= e(R::beds($camera)) ?></dd>
        </div>
        <div class="adv-fatti__riga">
          <dt><?= te('room.meta.layouts') ?></dt>
          <dd><?= e(implode(' · ', array_map(static fn (string $l): string => t('layouts.' . $l), (array) $camera['layouts']))) ?></dd>
        </div>
        <div class="adv-fatti__riga">
          <dt><?= te('room.meta.size') ?></dt>
          <dd><?= $camera['size_sqm'] ? e($camera['size_sqm'] . ' m²') : daConfermare() ?></dd>
        </div>
        <div class="adv-fatti__riga">
          <dt><?= te('room.meta.floor') ?></dt>
          <dd><?= $camera['floor'] ? e((string) $camera['floor']) : daConfermare() ?></dd>
        </div>
        <div class="adv-fatti__riga">
          <dt><?= te('room.bathroom') ?></dt>
          <dd>
            <?= te(!empty($camera['bathroom']['private']) ? 'bathroom.private' : 'bathroom.shared') ?>
            <?php if (!empty($camera['bathroom']['shower'])): ?> · <?= te('bathroom.shower') ?><?php endif; ?>
          </dd>
        </div>
        <div class="adv-fatti__riga">
          <dt><?= te('room.view') ?></dt>
          <dd><?= $vista !== null ? e($vista) : daConfermare() ?></dd>
        </div>
      </dl>

      <h2 class="adv-titolo adv-titolo--xs adv-spazio-sopra"><?= te('room.amenities') ?></h2>
      <ul class="adv-chips adv-chips--sotto">
        <?php foreach ((array) $camera['amenities'] as $servizio): ?>
          <li class="adv-chip"><?= icona('spunta', 12) ?><?= te('amenities.' . $servizio) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <aside class="adv-prenota-camera" aria-labelledby="titolo-tariffa">
      <div class="adv-pannello adv-pannello--azioni">
        <h2 class="adv-titolo adv-titolo--xs" id="titolo-tariffa"><?= te('room.rate') ?></h2>
        <?php $tariffe = prezziPubblici() ? R::rates($camera) : []; ?>
        <?php if ($tariffe !== []): ?>
          <?php /* La tariffa dipende da quante persone dormono in camera, e
                   allora si scrivono tutte: una riga per occupazione. */ ?>
          <dl class="adv-tariffe-camera">
            <?php foreach ($tariffe as $n => $prezzo): ?>
              <div class="adv-tariffe-camera__riga">
                <dt><?= (int) $n ?> <?= te($n === 1 ? 'common.guest' : 'common.guests') ?></dt>
                <dd><?= e(euro($prezzo)) ?> <small>/ <?= te('common.night') ?></small></dd>
              </div>
            <?php endforeach; ?>
          </dl>
          <p class="adv-nota"><?= te('rooms.rates_caption') ?></p>
        <?php else: ?>
          <p class="adv-testo"><?= te('rooms.price_on_dates_long') ?></p>
        <?php endif; ?>

        <a class="adv-btn adv-btn--primario adv-btn--grande adv-btn--pieno"
           href="<?= e(url('book', [], ['camera' => $camera['ref']])) ?>">
          <?= te('cta.book_room') ?><?= icona('freccia-su-destra', 14) ?>
        </a>
        <a class="adv-btn adv-btn--contorno adv-btn--pieno" href="<?= e(url('contact')) ?>"><?= te('cta.write') ?></a>
      </div>
    </aside>

  </div>
</section>

<?php /* La galleria. Senza fotografie vere la nota lo dice: una camera che
         non esiste, presentata come la vostra, è la sola cosa che un ospite
         non perdona. */ ?>
<section class="adv-blocco adv-blocco--sabbia" aria-labelledby="titolo-galleria">
  <div class="adv-contenuto">
    <?= component('section-header', [
        'occhiello' => t('room.eyebrow'),
        'titolo'    => t('room.gallery'),
        'misura'    => 's',
        'id'        => 'titolo-galleria',
    ]) ?>

    <?php if (!$foto): ?>
      <?= component('alert', [
          'tipo'   => 'avviso',
          'titolo' => t('rooms.demo_notice_title'),
          'testo'  => t('room.gallery_note'),
      ]) ?>
    <?php endif; ?>

    <ul class="adv-galleria adv-spazio-sopra">
      <li>
        <figure>
          <?= component('picture', [
              'src'   => $camera['images']['card']['src'],
              'alt'   => R::alt($camera, $lingua),
              'sizes' => '(max-width: 760px) 100vw, 380px',
          ]) ?>
        </figure>
      </li>
      <?php foreach ((array) ($camera['images']['gallery'] ?? []) as $immagine): ?>
        <li>
          <figure>
            <?= component('picture', [
                'src'   => $immagine['src'],
                'alt'   => R::alt($camera, $lingua),
                'sizes' => '(max-width: 760px) 100vw, 380px',
            ]) ?>
          </figure>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<?php if ($altreCamere !== []): ?>
  <section class="adv-contenuto adv-sezione" aria-labelledby="titolo-altre" data-carosello>
    <div class="adv-sezione__testa">
      <div>
        <?= occhiello(t('rooms.eyebrow')) ?>
        <h2 class="adv-titolo adv-titolo--m" id="titolo-altre" data-reveal="title-reveal"><?= titolo(t('room.other_rooms')) ?></h2>
      </div>
      <div class="adv-sezione__controlli">
        <a class="adv-link" href="<?= e(url('rooms')) ?>"><?= te('home.rooms.discover') ?><?= icona('freccia-su-destra', 13) ?></a>
        <button class="adv-tondo" type="button" aria-label="<?= te('home.rooms.prev') ?>" data-carosello-prec><?= icona('freccia-sinistra', 16) ?></button>
        <button class="adv-tondo" type="button" aria-label="<?= te('home.rooms.next') ?>" data-carosello-succ><?= icona('freccia-destra', 16) ?></button>
      </div>
    </div>
    <ul class="adv-carosello__traccia" data-carosello-traccia>
      <?php foreach ($altreCamere as $altra): ?>
        <li><?= component('room-card', ['camera' => $altra]) ?></li>
      <?php endforeach; ?>
    </ul>
    <div class="adv-carosello__piede" aria-hidden="true">
      <span>01 — <?= sprintf('%02d', count($altreCamere)) ?></span>
      <span class="adv-carosello__barra"><i data-carosello-barra></i></span>
      <span><?= te('home.rooms.tagline') ?></span>
    </div>
  </section>
<?php endif; ?>
