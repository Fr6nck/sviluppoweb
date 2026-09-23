<?php
/**
 * Informazioni pratiche.
 *
 * L'organizzazione segue i concetti che un ospite ha in testa — prima di
 * partire, come arrivare, il soggiorno, la casa, le regole — e non le
 * categorie di un gestionale.
 *
 * Ogni voce ha il suo dato o il suo marcatore. Non esiste una terza via: un
 * orario arrotondato è una telefonata in più, e un servizio promesso e non
 * trovato è una recensione in meno.
 *
 * @var array $domande
 */

$stay     = site('stay');
$contatti = site('contacts');
$minNotti = \ArcoDelVento\App::instance()->booking()->minNights();

/** Una riga: etichetta a sinistra, dato o marcatore a destra. */
$riga = static function (string $chiave, ?string $valore, bool $confermato = true): void {
    printf(
        '<div class="adv-fatti__riga"><dt>%s</dt><dd>%s</dd></div>',
        te('info.items.' . $chiave),
        ($valore !== null && $valore !== '')
            ? ($confermato ? e($valore) : daConfermare($valore))
            : daConfermare()
    );
};
?>

<div class="adv-contenuto">
  <header class="adv-heroT">
    <span class="adv-heroT__occhiello"><?= te('info.eyebrow') ?></span>
    <h1 class="adv-heroT__titolo">
      <?= te('info.title') ?> <span class="adv-firma-inline"><?= te('info.sign') ?></span>
    </h1>
    <p class="adv-heroT__spalla"><?= te('info.lead') ?></p>
  </header>
</div>

<section class="adv-contenuto adv-editoriale">

  <?= component('alert', [
      'tipo'   => 'info',
      'titolo' => t('info.notice_title'),
      'testo'  => t('info.notice_text'),
  ]) ?>

  <div class="adv-split adv-info-griglia">

    <div>
      <h2 class="adv-titolo-md"><?= te('info.sections.arrival') ?></h2>
      <dl class="adv-fatti">
        <?php
        $riga('check_in', $stay['check_in_from'] ? $stay['check_in_from'] . '–' . $stay['check_in_to'] : null);
        $riga('check_out', $stay['check_out_by']);
        $riga('late_arrival', null);
        $riga('keys', t('info.known.keys'));
        $riga('min_nights', $minNotti . ' ' . t('common.nights'), false);
        $riga('payment', null);
        $riga('cancellation', null);
        ?>
      </dl>
    </div>

    <div>
      <?php /* «Come arrivare» raccoglie l'arrivo in auto, il parcheggio, il
               treno e gli autobus: il parcheggio resta la voce che pesa di più
               nell'esperienza di chi arriva, e sta qui in cima. */ ?>
      <h2 class="adv-titolo-md"><?= te('info.sections.getting') ?></h2>
      <dl class="adv-fatti">
        <?php
        $riga('parking', $stay['parking']);
        $riga('car', null);
        $riga('train', null);
        $riga('bus', null);
        ?>
      </dl>
    </div>

    <div>
      <h2 class="adv-titolo-md"><?= te('info.sections.stay') ?></h2>
      <dl class="adv-fatti">
        <?php
        $riga('breakfast', $stay['breakfast'] === null ? null : ($stay['breakfast'] ? t('common.brand') : '—'));
        $riga('wifi', $stay['wifi'] === null ? null : ($stay['wifi'] ? 'Wi-Fi' : '—'));
        $riga('heating', null);
        $riga('cleaning', null);
        $riga('linen', null);
        $riga('city_tax', $stay['city_tax']);
        ?>
      </dl>
    </div>

    <div>
      <h2 class="adv-titolo-md"><?= te('info.sections.house') ?></h2>
      <dl class="adv-fatti">
        <?php
        $riga('stairs', $stay['stairs']);
        $riga('lift', $stay['lift'] === null ? null : ($stay['lift'] ? 'Sì' : 'No'));
        $riga('accessibility', null);
        $riga('languages', $stay['languages']);
        ?>
      </dl>
    </div>

    <div>
      <h2 class="adv-titolo-md"><?= te('info.sections.rules') ?></h2>
      <dl class="adv-fatti">
        <?php
        $riga('pets', $stay['pets']);
        $riga('smoking', $stay['smoking']);
        $riga('children', null);
        ?>
      </dl>
    </div>

    <div>
      <h2 class="adv-titolo-md"><?= te('info.sections.contact') ?></h2>
      <dl class="adv-fatti">
        <div class="adv-fatti__riga">
          <dt><?= te('footer.where') ?></dt>
          <dd><?= e(site('address.street')) ?>, <?= e(site('address.city')) ?></dd>
        </div>
        <div class="adv-fatti__riga">
          <dt><?= te('contact.form.phone') ?></dt>
          <dd><?= component('contact-line', ['tipo' => 'phone', 'valore' => $contatti['phone']]) ?></dd>
        </div>
        <div class="adv-fatti__riga">
          <dt><?= te('contact.form.email') ?></dt>
          <dd><?= component('contact-line', ['tipo' => 'email', 'valore' => $contatti['email']]) ?></dd>
        </div>
        <div class="adv-fatti__riga">
          <dt><?= te('cta.whatsapp') ?></dt>
          <dd><?= component('contact-line', ['tipo' => 'whatsapp', 'valore' => $contatti['whatsapp']]) ?></dd>
        </div>
      </dl>
    </div>

  </div>
</section>

<section class="adv-sezione-alt">
  <div class="adv-contenuto adv-editoriale">
    <?= component('section-header', [
        'occhiello' => t('home.faq.eyebrow'),
        'titolo'    => t('home.faq.title'),
        'firma'     => t('home.faq.sign'),
    ]) ?>
    <?= component('faq', ['domande' => $domande]) ?>
  </div>
</section>

<section class="adv-contenuto adv-editoriale">
  <div class="adv-split">
    <div>
      <?= component('section-header', [
          'occhiello' => t('contact.eyebrow'),
          'titolo'    => t('contact.title'),
          'firma'     => t('contact.sign'),
          'testo'     => t('contact.lead'),
      ]) ?>
      <p class="adv-azione-coda">
        <a class="adv-btn adv-btn--primario" href="<?= e(url('contact')) ?>"><?= te('cta.write') ?></a>
      </p>
    </div>
  </div>
</section>
