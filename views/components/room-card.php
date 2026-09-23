<?php
/**
 * Una camera come scheda (SchedaCamera): registro caldo.
 *
 * Si usa dove si decide — il passo «camere libere» della prenotazione — e non
 * nell'elenco editoriale, dove la cornice è proprio ciò che si toglie.
 *
 * @var array            $camera
 * @var \ArcoDelVento\Booking\RoomOffer|null $offerta
 * @var string|null      $azione   markup del pulsante
 */

use ArcoDelVento\Support\RoomPresenter as R;

$lingua  = locale();
$offerta = $offerta ?? null;
$libera  = $offerta?->available ?? null;
?>
<article class="adv-camera">

  <figure class="adv-camera__figura">
    <?= component('picture', [
        'src'   => $camera['images']['card']['src'],
        'alt'   => R::alt($camera, $lingua),
        'sizes' => '(max-width: 900px) 100vw, 360px',
    ]) ?>
    <?php if ($libera !== null): ?>
      <span class="adv-camera__etichetta">
        <span class="adv-badge <?= $libera ? 'adv-badge--libera' : 'adv-badge--occupata' ?>">
          <span class="adv-badge__punto" aria-hidden="true"></span>
          <?= $libera ? te('rooms.status.free') : te('rooms.status.busy') ?>
        </span>
      </span>
    <?php endif; ?>
  </figure>

  <div class="adv-camera__corpo">
    <h3 class="adv-camera__nome">
      <a class="adv-camera__link" href="<?= e(R::href($camera, $lingua)) ?>"><?= e(R::name($camera, $lingua)) ?></a>
    </h3>

    <p class="adv-camera__meta"><?= e(R::metaLine($camera)) ?></p>
    <p class="adv-camera__testo"><?= e(R::beds($camera)) ?></p>

    <ul class="adv-camera__servizi">
      <?php foreach (array_slice((array) $camera['amenities'], 0, 3) as $servizio): ?>
        <li><span class="adv-badge adv-badge--servizio"><?= te('amenities.' . $servizio) ?></span></li>
      <?php endforeach; ?>
    </ul>

    <div class="adv-camera__piede">
      <p class="adv-camera__prezzo">
        <?php if ($offerta !== null): ?>
          <?= e(euro($offerta->total)) ?>
          <small>
            <?= te('book.results.total', ['nights' => $offerta->nights]) ?>
            · <?= te('book.results.per_night', ['amount' => euro($offerta->nightlyRate)]) ?>
            <?php if ($offerta->rateIsDemo) { echo ' · ' . te('common.price_to_confirm'); } ?>
          </small>
        <?php else: ?>
          <?php if (!R::hasSingleRate($camera)): ?><?= e(t('common.from')) ?> <?php endif; ?>
          <?= e(euro((int) R::fromRate($camera))) ?>
          <small><?= te('common.per_night') ?></small>
        <?php endif; ?>
      </p>

      <?= $azione ?? '' ?>
    </div>
  </div>
</article>
