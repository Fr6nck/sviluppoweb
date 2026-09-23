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

<?php if (R::isPhotographed($camera)): ?>
  <?php /* Con una fotografia vera l'apertura è quella silenziosa del design
           system: la foto larga e, sotto, il titolo. A parlare è la camera,
           non il marchio, che sta già nella barra. */ ?>
  <div class="adv-contenuto">
    <section class="adv-hero-s">
      <figure class="adv-hero-s__figura">
        <?= component('picture', [
            'src'   => $camera['images']['hero']['src'],
            'alt'   => R::alt($camera, $lingua),
            'eager' => true,
            'sizes' => '(max-width: 1120px) 100vw, 1120px',
        ]) ?>
      </figure>

      <div class="adv-hero-s__testo">
        <div>
          <span class="adv-hero-s__occhiello"><?= te('room.eyebrow') ?></span>
          <h1 class="adv-hero-s__titolo"><?= e($nome) ?></h1>
        </div>
        <div>
          <?php /* Solo la riga di attributi: la tipologia dice già i letti
                   («Doppia a due letti · 2 singoli» ripete sé stessa), e il
                   dettaglio completo sta nella tabella qui sotto. */ ?>
          <p class="adv-hero-s__spalla"><?= e(R::metaLine($camera)) ?></p>
          <div class="adv-hero-s__azioni">
            <a class="adv-btn adv-btn--primario"
               href="<?= e(url('book', [], ['camera' => $camera['ref']])) ?>">
              <?= te('cta.book_room') ?>
            </a>
            <?php /* La seconda azione è un link, non un pulsante: due pulsanti
                     affiancati in un'apertura silenziosa sono uno di troppo. */ ?>
            <a class="adv-elenco__link" href="<?= e(url('rooms')) ?>">
              <?= te('cta.all_rooms') ?><span aria-hidden="true">&rarr;</span>
            </a>
          </div>
        </div>
      </div>
    </section>
  </div>
<?php else: ?>
  <div class="adv-contenuto">
    <header class="adv-heroT adv-heroT--camera">
      <span class="adv-heroT__occhiello"><?= te('room.eyebrow') ?></span>
      <h1 class="adv-heroT__titolo"><?= e($nome) ?></h1>
      <p class="adv-heroT__spalla"><?= e(R::metaLine($camera)) ?></p>
      <ul class="adv-heroT__meta">
        <li><?= e(R::beds($camera)) ?></li>
        <li><?= te('room.photo_pending') ?></li>
      </ul>
    </header>
  </div>
<?php endif; ?>

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
          <dd><?= ($esposizione = R::viewLabel($camera)) !== null ? e($esposizione) : daConfermare() ?></dd>
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
        <?php $tariffe = prezziPubblici() ? R::rates($camera) : []; ?>
        <?php if ($tariffe !== []): ?>
          <?php /* La tariffa dipende da quante persone dormono in camera, e
                   allora si scrivono tutte: una riga per occupazione, invece
                   di un «da € …» che fa cercare altrove il prezzo vero. */ ?>
          <dl class="adv-tariffe-camera">
            <?php foreach ($tariffe as $n => $prezzo): ?>
              <div class="adv-tariffe-camera__riga">
                <dt><?= (int) $n ?> <?= te($n === 1 ? 'common.guest' : 'common.guests') ?></dt>
                <dd><?= e(euro($prezzo)) ?> <small><?= te('common.per_night') ?></small></dd>
              </div>
            <?php endforeach; ?>
          </dl>
          <p class="adv-nota"><?= te('rooms.rates_caption') ?></p>
        <?php else: ?>
          <p class="adv-nota adv-nota--prezzo"><?= te('rooms.price_on_dates_long') ?></p>
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

    <?php if (!R::isPhotographed($camera)): ?>
      <?= component('alert', [
          'tipo'   => 'avviso',
          'titolo' => t('rooms.demo_notice_title'),
          'testo'  => t('room.gallery_note'),
      ]) ?>
    <?php endif; ?>

    <ul class="adv-galleria">
      <li>
        <figure>
          <?= component('picture', [
              'src'   => $camera['images']['card']['src'],
              'alt'   => R::alt($camera, $lingua),
              'sizes' => '(max-width: 720px) 100vw, 340px',
          ]) ?>
        </figure>
      </li>
      <?php foreach ((array) ($camera['images']['gallery'] ?? []) as $immagine): ?>
        <li>
          <figure>
            <?= component('picture', [
                'src'   => $immagine['src'],
                'alt'   => R::alt($camera, $lingua),
                'sizes' => '(max-width: 720px) 100vw, 340px',
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
