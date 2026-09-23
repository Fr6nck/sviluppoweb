<?php
/**
 * Le cinque camere.
 *
 * L'apertura è solo tipografica — è il pezzo «Hero tipografico» del design
 * system: «nessuna immagine, solo il titolo; l'immagine arriva dopo». Qui non
 * è una scelta di stile, è la situazione reale: le fotografie degli interni
 * non ci sono ancora, e un'apertura con un segnaposto grande sarebbe peggio
 * di un'apertura senza immagine.
 *
 * L'elenco è editoriale e non una griglia di schede: cinque righe a tutta
 * larghezza danno a ogni camera il suo momento, dove cinque riquadri in fila
 * farebbero sembrare il catalogo più povero di quello che è.
 *
 * @var array $camere
 */

use ArcoDelVento\Support\RoomPresenter as R;

$lingua = locale();
?>

<div class="adv-contenuto">
  <header class="adv-heroT">
    <span class="adv-heroT__occhiello"><?= te('rooms.eyebrow') ?></span>
    <h1 class="adv-heroT__titolo">
      <?= te('rooms.title') ?> <span class="adv-firma-inline"><?= te('rooms.sign') ?></span>
    </h1>
    <p class="adv-heroT__spalla"><?= te('rooms.lead') ?></p>
    <ul class="adv-heroT__meta">
      <li><?= e(site('rooms_count')) ?> <?= te('nav.rooms') ?></li>
      <li><?= e(site('address.street')) ?></li>
      <li><?= e(site('address.city')) ?></li>
    </ul>
  </header>
</div>

<div class="adv-contenuto">
  <?= partial('booking-bar', ['id' => 'camere']) ?>
</div>

<section class="adv-contenuto adv-editoriale">

  <?= component('alert', [
      'tipo'   => 'info',
      'titolo' => t('rooms.demo_notice_title'),
      'testo'  => t('rooms.demo_notice_text'),
  ]) ?>

  <?php /* Il titolo di sezione non è decorazione: senza di lui la pagina
           passa da h1 ai nomi delle camere, che sono h3, e chi naviga per
           titoli con uno screen reader perde un gradino. */ ?>
  <?= component('section-header', [
      'occhiello' => t('rooms.eyebrow'),
      'titolo'    => t('rooms.list_title'),
      'firma'     => t('rooms.list_sign'),
  ]) ?>

  <ul class="adv-elenco adv-elenco--pagina">
    <?php foreach ($camere as $camera): ?>
      <?= component('room-row', ['camera' => $camera]) ?>
    <?php endforeach; ?>
  </ul>

  <?= component('ornament') ?>

  <?php /* Le tariffe a confronto: è una tabella di dati, e va in <table>
           con le intestazioni al posto giusto — anche perché è la sola
           schermata in cui si confrontano cinque camere in un colpo. */ ?>
  <div class="adv-tariffe">
    <?= component('section-header', [
        'occhiello' => t('rooms.eyebrow'),
        'titolo'    => t('rooms.rates_title'),
        'piccolo'   => true,
        'filetto'   => false,
    ]) ?>

    <?php
    // Le colonne degli ospiti sono quelle che esistono davvero in almeno una
    // camera: con una tripla in casa sono tre, con solo doppie sarebbero due.
    $occupazioni = [];
    foreach ($camere as $c) { $occupazioni = array_merge($occupazioni, array_keys(R::rates($c))); }
    $occupazioni = array_unique($occupazioni); sort($occupazioni);
    ?>
    <p class="adv-testo"><?= te('rooms.rates_note') ?></p>

    <div class="adv-tabella-avvolta">
      <table class="adv-tabella">
        <caption><?= te('rooms.rates_caption') ?></caption>
        <thead>
          <tr>
            <th scope="col"><?= te('rooms.table.room') ?></th>
            <th scope="col"><?= te('rooms.table.type') ?></th>
            <th scope="col"><?= te('rooms.table.beds') ?></th>
            <?php foreach ($occupazioni as $n): ?>
              <th scope="col" class="adv-tabella__prezzo">
                <?= $n ?> <?= te($n === 1 ? 'common.guest' : 'common.guests') ?>
              </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($camere as $camera): $tariffe = R::rates($camera); ?>
            <tr>
              <th scope="row">
                <a class="adv-camera__link" href="<?= e(R::href($camera, $lingua)) ?>"><?= e(R::name($camera, $lingua)) ?></a>
              </th>
              <td><?= e(R::typeLabel($camera)) ?></td>
              <td><?= e(R::beds($camera)) ?></td>
              <?php foreach ($occupazioni as $n): ?>
                <td class="adv-tabella__prezzo">
                  <?php if (isset($tariffe[$n])): ?>
                    <?= e(euro($tariffe[$n])) ?>
                  <?php else: ?>
                    <span class="adv-tabella__vuoto" aria-label="<?= te('rooms.table.not_available') ?>">&mdash;</span>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</section>

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
  </div>
</section>
