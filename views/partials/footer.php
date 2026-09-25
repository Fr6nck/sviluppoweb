<?php
/**
 * La chiusura di ogni pagina, tutta in terracotta: prima l'invito a
 * prenotare, poi il piè di pagina con il marchio, le pagine, i contatti e
 * gli orari.
 *
 * Nella pagina di prenotazione l'invito non c'è: lì si sta già prenotando,
 * e un secondo «Prenota» in fondo manderebbe solo al passo uno.
 *
 * I dati identificativi — CIN e partita IVA — stanno qui in ogni pagina,
 * come chiede la legge per una struttura ricettiva.
 */

$contatti = site('contacts');
$stay     = site('stay');
$legal    = site('legal');
$invito   = ($paginaCorrente ?? '') !== 'book';
?>
<footer class="adv-piede">
  <div class="adv-contenuto">

    <?php if ($invito): ?>
      <div class="adv-piede__invito">
        <div>
          <?= occhiello(t('home.closing.eyebrow'), 'adv-occhiello--chiaro') ?>
          <h2 class="adv-titolo"><?= titolo(t('home.closing.title'), t('home.closing.sign')) ?></h2>
          <p class="adv-piede__invito-testo"><?= te('home.closing.lead') ?></p>
        </div>
        <a class="adv-btn adv-btn--crema adv-btn--grande" href="<?= e(url('book')) ?>">
          <?= te('cta.book') ?><?= icona('freccia-su-destra', 14) ?>
        </a>
      </div>
    <?php endif; ?>

    <div class="adv-piede__griglia" data-reveal="lift">

      <div>
        <a class="adv-piede__marchio" href="<?= e(url('home')) ?>">
          <?= te('common.brand') ?><small><?= te('nav.brand_sub') ?></small>
        </a>
        <p class="adv-piede__tagline"><?= righe(t('footer.tagline')) ?></p>
        <address class="adv-piede__indirizzo">
          <?= e(site('address.street')) ?><br>
          <?= e(site('address.city')) ?> (<?= e(site('address.province')) ?>)<?php
            if (site('address.postal_code')) { echo ' &middot; ' . e(site('address.postal_code')); }
          ?>
        </address>
      </div>

      <div>
        <h2 class="adv-piede__titolo"><?= te('footer.pages') ?></h2>
        <ul class="adv-piede__lista">
          <li><a href="<?= e(url('rooms')) ?>"><?= te('nav.rooms') ?></a></li>
          <li><a href="<?= e(url('property')) ?>"><?= te('nav.property') ?></a></li>
          <li><a href="<?= e(url('assisi')) ?>"><?= te('nav.assisi') ?></a></li>
          <li><a href="<?= e(url('info')) ?>"><?= te('nav.info') ?></a></li>
          <li><a href="<?= e(url('book')) ?>"><?= te('nav.book') ?></a></li>
        </ul>
      </div>

      <div>
        <h2 class="adv-piede__titolo"><?= te('footer.contacts') ?></h2>
        <ul class="adv-piede__lista">
          <li><?= component('contact-line', ['tipo' => 'phone', 'valore' => $contatti['phone']]) ?></li>
          <li><?= component('contact-line', ['tipo' => 'email', 'valore' => $contatti['email']]) ?></li>
          <li><?= component('contact-line', ['tipo' => 'whatsapp', 'valore' => $contatti['whatsapp']]) ?></li>
          <li><a href="<?= e(url('contact')) ?>"><?= te('nav.contact') ?></a></li>
        </ul>
      </div>

      <div>
        <h2 class="adv-piede__titolo"><?= te('footer.stay') ?></h2>
        <ul class="adv-piede__lista">
          <li><?= te('info.items.check_in') ?>: <?= $stay['check_in_from']
                ? e($stay['check_in_from'] . '–' . $stay['check_in_to'])
                : daConfermare() ?></li>
          <li><?= te('info.items.check_out') ?>: <?= $stay['check_out_by']
                ? e($stay['check_out_by'])
                : daConfermare() ?></li>
          <?php $parcheggio = testoLocale($stay['parking'] ?? null); ?>
          <li><?= te('info.items.parking') ?>: <?= $parcheggio ? e($parcheggio) : daConfermare() ?></li>
        </ul>
      </div>

    </div>

    <div class="adv-piede__riga">
      <p>
        <?= te('footer.rights', ['year' => date('Y')]) ?>
        &middot; CIN <?= $legal['cin'] ? e($legal['cin']) : daConfermare() ?>
        &middot; P. IVA <?= $legal['vat'] ? e($legal['vat']) : daConfermare() ?>
      </p>
      <p class="adv-piede__crediti"><?= te('footer.photo_credits') ?></p>
      <a href="<?= e(url('privacy')) ?>"><?= te('nav.privacy') ?></a>
      <a class="adv-piede__su" href="#inizio"><?= te('footer.back_top') ?><?= icona('freccia-su', 12) ?></a>
    </div>

  </div>
</footer>
