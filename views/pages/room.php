<?php
/**
 * La pagina di una singola camera.
 *
 * Tutto ciò che un ospite deve sapere prima di scegliere: letti, occupazione,
 * configurazioni possibili, bagno, esposizione, servizi, tariffa, azione. E
 * un posto già pronto per la galleria, che oggi mostra segnaposto e domani
 * mostra le fotografie vere senza che cambi nient'altro.
 *
 * @var array $camera
 * @var array $altreCamere
 */

use ArcoDelVento\Support\RoomPresenter as R;

$lingua  = locale();
$nome    = R::name($camera, $lingua);
$tariffa = R::fromRate($camera);
?>

<div class="adv-contenuto">
  <header class="adv-heroT adv-heroT--camera">
    <span class="adv-heroT__occhiello"><?= te('room.eyebrow') ?></span>
    <h1 class="adv-heroT__titolo"><?= e($nome) ?></h1>
    <p class="adv-heroT__spalla"><?= e(R::metaLine($camera)) ?></p>
    <ul class="adv-heroT__meta">
      <li><?= e(R::beds($camera)) ?></li>
      <?php if (!$camera['name_confirmed']): ?>
        <?php /* Dire «CAMERA [da confermare]» non spiega niente: quello che
                 manca è il nome, e la riga deve dirlo con le sue parole. */ ?>
        <li><?= te('room.name_to_confirm') ?> <?= daConfermare() ?></li>
      <?php endif; ?>
    </ul>
  </header>
</div>

<section class="adv-contenuto adv-editoriale">
  <div class="adv-split">

    <div>
      <?= component('section-header', [
          'occhiello' => t('room.eyebrow'),
          'titolo'    => t('room.characteristics'),
          'piccolo'   => true,
      ]) ?>

      <dl class="adv-fatti">
        <div class="adv-fatti__riga">
          <dt><?= te('room.meta.occupancy') ?></dt>
          <dd><?= (int) $camera['occupancy']['max'] ?> <?= te('common.guests') ?></dd>
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
          <dd><?= $camera['floor'] ? e($camera['floor']) : daConfermare() ?></dd>
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
          <dd><?= daConfermare() ?></dd>
        </div>
      </dl>

      <div class="adv-servizi">
        <h2 class="adv-titolo-md"><?= te('room.amenities') ?></h2>
        <ul class="adv-camera__servizi">
          <?php foreach ((array) $camera['amenities'] as $servizio): ?>
            <li><span class="adv-badge adv-badge--servizio"><?= te('amenities.' . $servizio) ?></span></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <aside class="adv-prenota-camera">
      <div class="adv-barra adv-barra--verticale">
        <p class="adv-camera__prezzo">
          <?php if ($tariffa !== null): ?>
            <?= e(t('common.from')) ?> <?= e(euro($tariffa)) ?>
            <small><?= te('common.per_night') ?></small>
          <?php else: ?>
            <?= prezzoDaConfermare() ?>
          <?php endif; ?>
        </p>
        <?php if ($tariffa !== null): ?>
          <p class="adv-nota"><?= prezzoDaConfermare() ?></p>
        <?php endif; ?>

        <a class="adv-btn adv-btn--primario adv-btn--pieno"
           href="<?= e(url('book', [], ['camera' => $camera['ref']])) ?>">
          <?= te('cta.book_room') ?>
        </a>
        <a class="adv-btn adv-btn--contorno adv-btn--pieno" href="<?= e(url('contact')) ?>">
          <?= te('cta.write') ?>
        </a>
      </div>
    </aside>

  </div>
</section>

<?php /* La galleria. Oggi sono segnaposto disegnati, e la nota lo dice: una
         camera che non esiste, presentata come la vostra, è la sola cosa che
         un ospite non perdona. */ ?>
<section class="adv-sezione-alt">
  <div class="adv-contenuto adv-editoriale">
    <?= component('section-header', [
        'occhiello' => t('room.eyebrow'),
        'titolo'    => t('room.gallery'),
        'piccolo'   => true,
    ]) ?>

    <?= component('alert', ['tipo' => 'avviso', 'titolo' => t('rooms.demo_notice_title'), 'testo' => t('room.gallery_note')]) ?>

    <ul class="adv-galleria">
      <li>
        <figure>
          <?= component('picture', [
              'src' => $camera['images']['card']['src'], 'alt' => t('rooms.image_alt'),
              'width' => 800, 'height' => 600,
          ]) ?>
        </figure>
      </li>
      <?php foreach ((array) ($camera['images']['gallery'] ?? []) as $immagine): ?>
        <li>
          <figure>
            <?= component('picture', [
                'src' => $immagine['src'], 'alt' => t('rooms.image_alt'),
                'width' => 700, 'height' => 700,
            ]) ?>
          </figure>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<section class="adv-contenuto adv-editoriale">
  <?= component('section-header', [
      'occhiello' => t('rooms.eyebrow'),
      'titolo'    => t('room.other_rooms'),
      'piccolo'   => true,
  ]) ?>

  <?= component('index-list', [
      'voci' => array_map(static function (array $altra) use ($lingua): array {
          return [
              'etichetta' => R::name($altra, $lingua),
              'nota'      => R::metaLine($altra),
              'href'      => R::href($altra, $lingua),
          ];
      }, $altreCamere),
  ]) ?>
</section>
