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
$tassa    = $stay['city_tax'];
$scale    = $stay['min_nights'];

/**
 * Una riga: etichetta a sinistra, dato o marcatore a destra.
 *
 * Accetta qualunque tipo e lo normalizza, perché content/settings.php è un
 * file che il cliente modifica: un booleano al posto di una stringa non deve
 * poter mandare giù la pagina.
 */
$riga = static function (string $chiave, mixed $valore, ?string $etichetta = null): void {
    if (is_bool($valore)) {
        $valore = $valore ? t('common.yes') : t('common.no');
    } elseif (is_array($valore)) {
        // Una frase scritta per lingua: ['it' => '…', 'en' => '…']. Qualunque
        // altro array è un dato strutturato e si stampa a mano, non qui.
        $valore = testoLocale($valore);
    } elseif (is_object($valore)) {
        $valore = null;
    } elseif ($valore !== null) {
        $valore = (string) $valore;
    }

    printf(
        '<div class="adv-fatti__riga"><dt>%s</dt><dd>%s</dd></div>',
        $etichetta !== null ? e($etichetta) : te('info.items.' . $chiave),
        ($valore !== null && $valore !== '') ? e($valore) : daConfermare()
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

  <div class="adv-split adv-info-griglia">

    <div>
      <h2 class="adv-titolo-md"><?= te('info.sections.arrival') ?></h2>
      <dl class="adv-fatti">
        <?php
        $riga('check_in', $stay['check_in_from'] . '–' . $stay['check_in_to']);
        $riga('check_out', $stay['check_out_by']);
        $riga('welcome', t('info.known.welcome'));
        $riga('documents', t('info.known.documents'));
        $riga('contact_hours', $contatti['hours']['from'] . '–' . $contatti['hours']['to']);
        $riga('min_nights', t('info.known.min_nights', ['nights' => (int) $scale['saturday']]));
        $riga('payment', null);
        $riga('cancellation', null);
        ?>
      </dl>
    </div>

    <div>
      <?php /* «Come arrivare» raccoglie navigatore, parcheggio e mezzi. Il
               parcheggio è la voce che pesa di più su chi arriva, e sta in
               cima. */ ?>
      <h2 class="adv-titolo-md"><?= te('info.sections.getting') ?></h2>
      <dl class="adv-fatti">
        <?php
        $riga('navigator', t('info.known.navigator', ['place' => site('navigation.by_car')]));
        $riga('parking', $stay['parking']);
        $riga('car', t('info.known.car'));
        $riga('train', t('info.known.train'));
        $riga('plane', t('info.known.plane'));
        $riga('taxi', t('info.known.taxi', ['place' => site('navigation.by_car')]));
        ?>
      </dl>
      <p class="adv-nota"><?= te('info.known.bus_note') ?> <?= daConfermare() ?></p>
    </div>

    <div>
      <h2 class="adv-titolo-md"><?= te('info.sections.stay') ?></h2>
      <dl class="adv-fatti">
        <?php
        $riga('breakfast', t('info.known.no_meals'));
        $riga('kettle', $stay['kettle']);
        $riga('minibar', $stay['minibar']);
        $riga('fans', $stay['fans']);
        $riga('heating', $stay['heating']);
        $riga('air_conditioning', $stay['air_conditioning']);
        $riga('wifi', t('info.known.wifi', ['speed' => $stay['wifi_speed']]));
        $riga('cleaning', null);
        $riga('linen', null);
        $riga('city_tax', t('info.known.city_tax', [
            'amount' => euro((float) $tassa['amount']),
            'nights' => (int) $tassa['max_nights'],
            'age'    => (int) $tassa['exempt_under'],
        ]));
        ?>
      </dl>
    </div>

    <div>
      <h2 class="adv-titolo-md"><?= te('info.sections.house') ?></h2>
      <dl class="adv-fatti">
        <?php
        $riga('rooms', t('info.known.rooms'));
        $riga('floor', site('address.floor'));
        $riga('stairs', $stay['stairs']);
        $riga('lift', $stay['lift']);
        $riga('accessibility', $stay['accessibility']);
        $riga('common_areas', t('info.known.common_areas'));
        $riga('open', t('info.known.open_all_year'));
        $riga('languages', $stay['languages']);
        ?>
      </dl>
    </div>

    <div>
      <h2 class="adv-titolo-md"><?= te('info.sections.rules') ?></h2>
      <dl class="adv-fatti">
        <?php
        $riga('smoking', t('info.known.no_smoking'));
        $riga('pets', $stay['pets']);
        $riga('guest_contact', t('info.known.guest_contact'));
        $riga('children', null);
        ?>
      </dl>
      <p class="adv-nota"><?= te('info.known.remote_work') ?></p>
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
          <dt><?= te('cta.whatsapp') ?></dt>
          <dd><?= component('contact-line', ['tipo' => 'whatsapp', 'valore' => $contatti['whatsapp']]) ?></dd>
        </div>
        <div class="adv-fatti__riga">
          <dt><?= te('contact.form.email') ?></dt>
          <dd><?= component('contact-line', ['tipo' => 'email', 'valore' => $contatti['email']]) ?></dd>
        </div>
        <?php $riga('contact_hours', $contatti['hours']['from'] . '–' . $contatti['hours']['to']); ?>
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
