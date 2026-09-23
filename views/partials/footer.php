<?php
/**
 * Il piè di pagina, su fascia bruna.
 *
 * Il design system è netto: il mattone resta per uno o due momenti per
 * pagina, il piede va in bruno. E gli orari di check-in stanno qui in ogni
 * pagina, non solo nei contatti, perché è la domanda che arriva più spesso.
 */

$contatti = site('contacts');
$stay     = site('stay');
$legal    = site('legal');
?>
<footer class="adv-contenuto adv-piede-fuori">
  <div class="adv-piede adv-piede__interno">
    <div class="adv-contenuto">

      <div class="adv-piede__griglia">

        <div>
          <h2 class="adv-piede__titolo"><?= te('footer.where') ?></h2>
          <address class="adv-piede__testo adv-piede__indirizzo">
            <?= te('common.brand_full') ?><br>
            <?= e(site('address.street')) ?><br>
            <?= e(site('address.city')) ?> (<?= e(site('address.province')) ?>)<?php
              if (site('address.postal_code')) { echo ' &middot; ' . e(site('address.postal_code')); }
            ?>
          </address>
        </div>

        <div>
          <h2 class="adv-piede__titolo"><?= te('footer.contacts') ?></h2>
          <ul class="adv-piede__lista">
            <li><?= component('contact-line', ['tipo' => 'phone', 'valore' => $contatti['phone']]) ?></li>
            <li><?= component('contact-line', ['tipo' => 'email', 'valore' => $contatti['email']]) ?></li>
            <li><?= component('contact-line', ['tipo' => 'whatsapp', 'valore' => $contatti['whatsapp']]) ?></li>
          </ul>
        </div>

        <div>
          <h2 class="adv-piede__titolo"><?= te('footer.stay') ?></h2>
          <ul class="adv-piede__lista adv-piede__lista--fatti">
            <li><?= te('info.items.check_in') ?>: <?= $stay['check_in_from']
                  ? e($stay['check_in_from'] . '–' . $stay['check_in_to'])
                  : daConfermare() ?></li>
            <li><?= te('info.items.check_out') ?>: <?= $stay['check_out_by']
                  ? e($stay['check_out_by'])
                  : daConfermare() ?></li>
            <?php $parcheggio = testoLocale($stay['parking'] ?? null); ?>
            <li><?= te('info.items.parking') ?>: <?= $parcheggio
                  ? e($parcheggio)
                  : daConfermare() ?></li>
          </ul>
        </div>

        <div>
          <h2 class="adv-piede__titolo"><?= te('footer.pages') ?></h2>
          <ul class="adv-piede__lista">
            <li><a href="<?= e(url('rooms')) ?>"><?= te('nav.rooms') ?></a></li>
            <li><a href="<?= e(url('property')) ?>"><?= te('nav.property') ?></a></li>
            <li><a href="<?= e(url('assisi')) ?>"><?= te('nav.assisi') ?></a></li>
            <li><a href="<?= e(url('info')) ?>"><?= te('nav.info') ?></a></li>
            <li><a href="<?= e(url('contact')) ?>"><?= te('nav.contact') ?></a></li>
            <li><a href="<?= e(url('privacy')) ?>"><?= te('nav.privacy') ?></a></li>
          </ul>
        </div>

      </div>

      <div class="adv-piede__riga">
        <p class="adv-piede__nota">
          <?= te('footer.rights', ['year' => date('Y')]) ?>
          &middot;
          <?php if ($legal['cin']): ?>
            CIN <?= e($legal['cin']) ?>
          <?php else: ?>
            CIN <?= daConfermare() ?>
          <?php endif; ?>
          &middot;
          <?php if ($legal['vat']): ?>
            P. IVA <?= e($legal['vat']) ?>
          <?php else: ?>
            P. IVA <?= daConfermare() ?>
          <?php endif; ?>
        </p>
        <p class="adv-piede__nota"><?= te('footer.photo_credits') ?></p>
      </div>

    </div>
  </div>
</footer>
