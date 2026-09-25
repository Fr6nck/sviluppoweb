<?php
/**
 * Una camera come scheda: la fotografia con la freccia, il nome, i letti e
 * gli ospiti con le loro icone, i servizi, e in fondo le due celle —
 * configurazioni e tariffa.
 *
 * La stessa scheda serve il carosello della home, l'elenco delle camere e
 * le camere libere della prenotazione; lì porta anche lo stato e il
 * pulsante per sceglierla.
 *
 * @var array            $camera
 * @var \ArcoDelVento\Booking\RoomOffer|null $offerta
 * @var string|null      $azione   markup del pulsante
 * @var string           $sizes
 */

use ArcoDelVento\Support\RoomPresenter as R;

$lingua  = locale();
$offerta = $offerta ?? null;
$libera  = $offerta?->available ?? null;
$nome    = R::name($camera, $lingua);
$href    = R::href($camera, $lingua);
$massimo = (int) ($camera['occupancy']['max'] ?? 1);
$vista   = R::viewLabel($camera);
$tariffa = R::fromRate($camera);
?>
<article class="adv-camera">

  <?php /* La fotografia porta alla camera come il nome, ma fuori dal giro del
           tabulatore: da tastiera il link è uno solo, quello del titolo. */ ?>
  <a class="adv-camera__figura" href="<?= e($href) ?>" tabindex="-1">
    <?= component('picture', [
        'src'   => $camera['images']['card']['src'],
        'alt'   => R::alt($camera, $lingua),
        'sizes' => $sizes ?? '(max-width: 760px) 90vw, 580px',
    ]) ?>
    <?php if (!R::isPhotographed($camera)): ?>
      <span class="adv-camera__badge"><?= te('rooms.card.photo_soon') ?></span>
    <?php elseif ($vista !== null): ?>
      <span class="adv-camera__badge"><?= e($vista) ?></span>
    <?php endif; ?>
    <?php if ($libera !== null): ?>
      <span class="adv-camera__stato<?= $libera ? '' : ' adv-camera__stato--occupata' ?>">
        <i aria-hidden="true"></i><?= $libera ? te('rooms.status.free') : te('rooms.status.busy') ?>
      </span>
    <?php endif; ?>
    <span class="adv-camera__vai" aria-hidden="true"><?= icona('freccia-su-destra', 15) ?></span>
  </a>

  <div class="adv-camera__corpo">
    <div class="adv-camera__riga">
      <h3 class="adv-camera__nome"><a href="<?= e($href) ?>"><?= e($nome) ?></a></h3>
      <span class="adv-camera__tipo"><?= e(R::typeLabel($camera)) ?></span>
    </div>

    <ul class="adv-camera__meta">
      <?php if (!empty($camera['size_sqm'])): ?>
        <li><?= icona('metratura', 13) ?><?= e($camera['size_sqm'] . ' m²') ?></li>
      <?php endif; ?>
      <li><?= icona('letto', 13) ?><?= e(R::beds($camera)) ?></li>
      <li><?= icona('ospiti', 13) ?><?= $massimo === 1
            ? te('rooms.card.guests_one')
            : te('rooms.card.guests_up_to', ['count' => $massimo]) ?></li>
    </ul>

    <p class="adv-camera__testo"><?= e(R::amenitiesLine($camera, 3)) ?></p>

    <div class="adv-camera__piede">
      <div class="adv-camera__cella">
        <span class="adv-camera__etichetta"><?= te('rooms.card.layouts') ?></span>
        <span class="adv-camera__valore"><?= e(implode(' · ', array_map(
            static fn (string $l): string => t('layouts.' . $l),
            (array) $camera['layouts']
        ))) ?></span>
      </div>
      <div class="adv-camera__cella">
        <span class="adv-camera__etichetta"><?= te('rooms.card.rate') ?></span>
        <span class="adv-camera__valore">
          <?php if ($offerta !== null): ?>
            <strong class="adv-camera__prezzo"><?= e(euro($offerta->total)) ?></strong><br>
            <small>
              <?= te('book.results.total', ['nights' => $offerta->nights]) ?>
              · <?= te('book.results.per_night', ['amount' => euro($offerta->nightlyRate)]) ?>
              <?php if ($offerta->rateIsDemo) { echo ' · ' . te('common.price_to_confirm'); } ?>
            </small>
          <?php elseif (prezziPubblici() && $tariffa !== null): ?>
            <?php if (!R::hasSingleRate($camera)): ?><small><?= te('common.from') ?></small> <?php endif; ?>
            <strong class="adv-camera__prezzo"><?= e(euro($tariffa)) ?></strong>
            <small>/ <?= te('common.night') ?></small>
          <?php else: ?>
            <?= te('rooms.price_on_dates') ?>
          <?php endif; ?>
        </span>
      </div>
    </div>

    <?php if (!empty($azione)): ?>
      <div class="adv-camera__azione"><?= $azione ?></div>
    <?php endif; ?>
  </div>
</article>
