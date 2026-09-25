<?php
/**
 * Le cinque camere.
 *
 * L'apertura è tipografica, poi la barra per le date, poi le cinque schede
 * — le stesse del carosello della home, qui tutte in vista. In fondo, su
 * fondo sabbia, dove stanno le tariffe e che cosa le determina.
 *
 * @var array $camere
 */

use ArcoDelVento\Support\RoomPresenter as R;

$lingua = locale();
$corridoio = immagine('pagine.corridoio');
?>

<header class="adv-contenuto adv-testa">
  <?= occhiello(t('rooms.eyebrow'), 'adv-occhiello--centro') ?>
  <h1 class="adv-titolo adv-titolo--xl"><?= titolo(t('rooms.title'), t('rooms.sign')) ?></h1>
  <p class="adv-testa__testo"><?= te('rooms.lead') ?></p>
  <ul class="adv-testa__meta">
    <li><?= icona('casa', 13) ?><?= e((string) site('rooms_count')) ?> <?= e(mb_strtolower(t('nav.rooms'))) ?></li>
    <li><?= icona('pin', 13) ?><?= e(site('address.street')) ?></li>
    <li><?= e(site('address.city')) ?></li>
  </ul>
</header>

<div class="adv-contenuto">
  <?= partial('booking-bar', ['id' => 'camere']) ?>
</div>

<section class="adv-contenuto adv-sezione" aria-labelledby="titolo-elenco">
  <?php /* Il titolo di sezione non è decorazione: senza, la pagina passa da
           h1 ai nomi delle camere, che sono h3, e chi naviga per titoli con
           uno screen reader perde un gradino. */ ?>
  <?= component('section-header', [
      'occhiello' => t('rooms.eyebrow'),
      'titolo'    => t('rooms.list_title'),
      'firma'     => t('rooms.list_sign'),
      'id'        => 'titolo-elenco',
  ]) ?>

  <?= component('alert', [
      'tipo'   => 'info',
      'titolo' => t('rooms.demo_notice_title'),
      'testo'  => t('rooms.demo_notice_text'),
  ]) ?>

  <ul class="adv-griglia-camere adv-spazio-sopra">
    <?php foreach ($camere as $camera): ?>
      <li><?= component('room-card', ['camera' => $camera]) ?></li>
    <?php endforeach; ?>
  </ul>
</section>

<section class="adv-blocco adv-blocco--sabbia" aria-labelledby="titolo-tariffe">
  <div class="adv-contenuto">
    <div class="adv-due adv-due--centro">

      <figure class="adv-figura">
        <div class="adv-figura-alta" data-reveal="photo-reveal">
          <div class="adv-figura-alta__cornice" data-parallax="soft">
            <?= component('picture', [
                'src'   => $corridoio['src'],
                'alt'   => immagineAlt($corridoio, 'rooms.corridor_alt'),
                'sizes' => '(max-width: 760px) 100vw, 540px',
            ]) ?>
          </div>
        </div>
        <?php if ($didascalia = immagineCredito($corridoio, 'rooms.corridor_caption')): ?>
          <figcaption class="adv-figura__didascalia"><?= e($didascalia) ?></figcaption>
        <?php endif; ?>
      </figure>

      <div>
        <?= component('section-header', [
            'occhiello' => t('rooms.eyebrow'),
            'titolo'    => t('rooms.rates_title'),
            'id'        => 'titolo-tariffe',
        ]) ?>
        <?php if (prezziPubblici()): ?>
          <p class="adv-testo"><?= te('rooms.rates_note') ?></p>
        <?php else: ?>
          <?php /* Senza listino in pagina, l'ospite deve sapere dove sono i
                   prezzi e che cosa li determina. Dirlo costa tre righe e
                   toglie la sensazione che il sito nasconda qualcosa. */ ?>
          <p class="adv-testo"><?= te('rooms.rates_hidden') ?></p>
          <p class="adv-testo"><?= te('rooms.rates_note') ?></p>
        <?php endif; ?>
        <div class="adv-azioni">
          <a class="adv-btn adv-btn--primario" href="<?= e(url('book')) ?>"><?= te('cta.check') ?><?= icona('freccia-su-destra', 14) ?></a>
          <a class="adv-link" href="<?= e(url('info')) ?>"><?= te('cta.see_info') ?><?= icona('freccia-su-destra', 13) ?></a>
        </div>
      </div>

    </div>

    <?php if (prezziPubblici()): ?>
      <?php
      // Le colonne degli ospiti sono quelle che esistono davvero in almeno una
      // camera: con una tripla in casa sono tre, con solo doppie sarebbero due.
      $occupazioni = [];
      foreach ($camere as $c) { $occupazioni = array_merge($occupazioni, array_keys(R::rates($c))); }
      $occupazioni = array_unique($occupazioni); sort($occupazioni);
      ?>
      <div class="adv-tabella-avvolta adv-spazio-sopra">
        <table class="adv-tabella">
          <caption><?= te('rooms.rates_caption') ?></caption>
          <thead>
            <tr>
              <th scope="col"><?= te('rooms.table.room') ?></th>
              <th scope="col"><?= te('rooms.table.type') ?></th>
              <th scope="col"><?= te('rooms.table.beds') ?></th>
              <?php foreach ($occupazioni as $n): ?>
                <th scope="col" class="adv-tabella__prezzo"><?= $n ?> <?= te($n === 1 ? 'common.guest' : 'common.guests') ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($camere as $camera): $tariffe = R::rates($camera); ?>
              <tr>
                <th scope="row"><a href="<?= e(R::href($camera, $lingua)) ?>"><?= e(R::name($camera, $lingua)) ?></a></th>
                <td><?= e(R::typeLabel($camera)) ?></td>
                <td><?= e(R::beds($camera)) ?></td>
                <?php foreach ($occupazioni as $n): ?>
                  <td class="adv-tabella__prezzo">
                    <?php if (isset($tariffe[$n])): ?>
                      <?= e(euro($tariffe[$n])) ?>
                    <?php else: ?>
                      <span aria-label="<?= te('rooms.table.not_available') ?>">&mdash;</span>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>
