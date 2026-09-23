<?php
/**
 * La prenotazione, in quattro passi.
 *
 *     date e ospiti → camere libere → i tuoi dati → richiesta inviata
 *
 * Funziona senza JavaScript: le date sono campi nativi, ogni passo è una
 * pagina con il suo indirizzo, e il tasto «indietro» fa quello che promette.
 *
 * @var string $passo   dates | rooms | details | done
 * @var \ArcoDelVento\Booking\SearchCriteria|null      $criteri
 * @var \ArcoDelVento\Booking\AvailabilityResult|null  $disponibilita
 * @var \ArcoDelVento\Booking\RoomOffer|null           $offerta
 * @var array $conferma
 * @var array $errori
 * @var array $valori
 */

use ArcoDelVento\Support\Csrf;
use ArcoDelVento\Support\RoomPresenter as R;

$lingua  = locale();
$errori  = $errori ?? [];
$valori  = $valori ?? [];
$passi   = ['dates', 'rooms', 'details', 'done'];
$indice  = array_search($passo, $passi, true);
$indice  = $indice === false ? 0 : $indice;

/** Il messaggio d'errore di un campo, tradotto. */
$errore = static function (string $campo) use ($errori): ?string {
    if (!isset($errori[$campo])) {
        return null;
    }
    $voce = $errori[$campo];
    [$chiave, $sost] = is_array($voce) ? $voce : [$voce, []];

    return t('errors.' . $chiave, $sost);
};

/** Il valore già digitato, per non far riscrivere tutto dopo un errore. */
$valore = static fn (string $campo): string => (string) ($valori[$campo] ?? '');
?>

<div class="adv-contenuto">
  <header class="adv-heroT adv-heroT--modulo">
    <span class="adv-heroT__occhiello"><?= te('book.eyebrow') ?></span>
    <h1 class="adv-heroT__titolo">
      <?= te('book.title') ?> <span class="adv-firma-inline"><?= te('book.sign') ?></span>
    </h1>
    <p class="adv-heroT__spalla"><?= te('book.lead') ?></p>
  </header>
</div>

<section class="adv-contenuto adv-editoriale adv-editoriale--stretto">

  <ol class="adv-passi">
    <?php foreach ($passi as $i => $chiave): ?>
      <li<?= $i === $indice ? ' aria-current="step"' : '' ?>>
        <span class="adv-passi__numero"><?= sprintf('%02d', $i + 1) ?></span>
        <?= te('book.steps.' . ['dates' => 'dates', 'rooms' => 'rooms', 'details' => 'details', 'done' => 'done'][$chiave]) ?>
      </li>
    <?php endforeach; ?>
  </ol>

  <?php if ($passo !== 'done'): ?>
    <?= component('alert', [
        'tipo'   => 'avviso',
        'titolo' => t('book.demo_title'),
        'testo'  => t('book.demo_text'),
    ]) ?>
  <?php endif; ?>

  <?php if ($messaggio = $errore('_form')): ?>
    <?= component('alert', ['tipo' => 'errore', 'titolo' => t('contact.error_title'), 'testo' => $messaggio]) ?>
  <?php endif; ?>


  <?php /* ------------------------------------------- 1. date e ospiti */ ?>
  <?php if ($passo === 'dates'): ?>

    <?= partial('booking-bar', ['id' => 'prenota', 'criteri' => $criteri, 'errori' => $errori, 'ospiti' => $ospiti ?? 2]) ?>


  <?php /* ---------------------------------------- 2. le camere libere */ ?>
  <?php elseif ($passo === 'rooms' && $disponibilita !== null): ?>

    <?= component('section-header', [
        'occhiello' => t('book.eyebrow'),
        'titolo'    => t('book.results.title'),
        'piccolo'   => true,
        'testo'     => t('book.results.for_dates', [
            'from'   => dataEstesa($criteri->arrivalIso()),
            'to'     => dataEstesa($criteri->departureIso()),
            'guests' => $criteri->guests . ' ' . t($criteri->guests === 1 ? 'common.guest' : 'common.guests'),
        ]),
    ]) ?>

    <p class="adv-azione-coda">
      <a class="adv-elenco__link" href="<?= e(url('book')) ?>">
        <?= te('book.results.change') ?><span aria-hidden="true">&rarr;</span>
      </a>
    </p>

    <?php
    /* Due casi diversi, e vanno detti in due modi diversi: «queste date sono
       piene» invita a spostarsi di qualche giorno; «nessuna camera ospita
       tante persone» no — spostare le date non cambierebbe niente, e
       suggerirlo farebbe perdere tempo a chi legge. */
    $capienzaMassima = 0;
    foreach ($disponibilita->offers as $o) {
        $capienzaMassima = max($capienzaMassima, (int) ($o->room['occupancy']['max'] ?? 0));
    }
    $troppiOspiti = $criteri->guests > $capienzaMassima;
    ?>

    <?php if ($troppiOspiti): ?>

      <?= component('alert', [
          'tipo'   => 'avviso',
          'titolo' => t('book.results.too_many_title', ['guests' => $criteri->guests]),
          'testo'  => t('book.results.too_many_text', ['max' => $capienzaMassima]),
      ]) ?>

      <p class="adv-azione-coda">
        <a class="adv-btn adv-btn--primario" href="<?= e(url('contact')) ?>"><?= te('cta.write') ?></a>
      </p>

    <?php elseif (!$disponibilita->hasAvailability()): ?>

      <?php
      /* Il design system è esplicito: «Quando non c'è disponibilità, la
         risposta non è un vuoto: mostra le date libere più vicine.» */
      $alternative = $disponibilita->alternatives;
      $html = e(t('book.results.none_text'));
      if ($alternative !== []) {
          $html .= '<br><br><strong>' . e(t('book.results.nearest')) . '</strong><br>';
          foreach ($alternative as $a) {
              $href = url('book', [], [
                  'passo' => 'camere', 'arrivo' => $a['arrival'],
                  'partenza' => $a['departure'], 'ospiti' => (string) $criteri->guests,
              ]);
              $html .= sprintf(
                  '<a class="adv-elenco__link adv-alternativa" href="%s">%s &rarr; %s</a>',
                  e($href), e(dataEstesa($a['arrival'])), e(dataEstesa($a['departure']))
              );
          }
      }
      ?>
      <?= component('alert', [
          'tipo'      => 'errore',
          'titolo'    => t('book.results.none_title'),
          'testoHtml' => $html,
      ]) ?>

    <?php endif; ?>

    <?php $libere = $disponibilita->available(); ?>
    <?php if ($libere !== []): ?>
      <ul class="adv-griglia-camere">
        <?php foreach ($libere as $offerta): ?>
          <li>
            <?= component('room-card', [
                'camera'  => $offerta->room,
                'offerta' => $offerta,
                'azione'  => sprintf(
                    '<a class="adv-btn adv-btn--primario adv-btn--sm" href="%s">%s</a>',
                    e(url('book', [], [
                        'passo'    => 'dati',
                        'arrivo'   => $criteri->arrivalIso(),
                        'partenza' => $criteri->departureIso(),
                        'ospiti'   => (string) $criteri->guests,
                        'camera'   => $offerta->ref(),
                    ])),
                    e(t('book.results.choose'))
                ),
            ]) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php
    /* Le camere non libere restano visibili: nasconderle lascia l'ospite a
       chiedersi se il sito è rotto o se la casa ha davvero cinque camere. */
    $occupate = array_values(array_filter(
        $disponibilita->offers,
        static fn ($o): bool => !$o->available
    ));
    ?>
    <?php if ($occupate !== []): ?>
      <div class="adv-spazio-sopra">
        <h3 class="adv-titolo-md"><?= te('book.results.unavailable_room') ?></h3>
        <?= component('index-list', [
            'voci' => array_map(static function ($o) use ($lingua): array {
                return [
                    'etichetta' => R::name($o->room, $lingua),
                    'nota'      => R::metaLine($o->room),
                    'href'      => R::href($o->room, $lingua),
                ];
            }, $occupate),
        ]) ?>
      </div>
    <?php endif; ?>


  <?php /* ------------------------------------------- 3. i tuoi dati */ ?>
  <?php elseif ($passo === 'details' && $offerta !== null): ?>

    <div class="adv-split adv-split--stretta">

      <form class="adv-modulo" method="post" action="<?= e(url('book')) ?>" novalidate>
        <?= Csrf::field() ?>
        <input type="hidden" name="arrivo"   value="<?= e($criteri->arrivalIso()) ?>">
        <input type="hidden" name="partenza" value="<?= e($criteri->departureIso()) ?>">
        <input type="hidden" name="ospiti"   value="<?= e((string) $criteri->guests) ?>">
        <input type="hidden" name="camera"   value="<?= e($offerta->ref()) ?>">

        <fieldset>
          <legend><?= te('book.details.legend') ?></legend>
          <p class="adv-nota"><?= te('common.required_note') ?></p>

          <div class="adv-modulo__coppia">
            <?= component('field', [
                'nome' => 'nome', 'etichetta' => t('book.details.first_name'),
                'valore' => $valore('nome'), 'errore' => $errore('nome'),
                'autocomplete' => 'given-name', 'obbligatorio' => true,
            ]) ?>
            <?= component('field', [
                'nome' => 'cognome', 'etichetta' => t('book.details.last_name'),
                'valore' => $valore('cognome'), 'errore' => $errore('cognome'),
                'autocomplete' => 'family-name', 'obbligatorio' => true,
            ]) ?>
          </div>

          <div class="adv-modulo__coppia">
            <?= component('field', [
                'nome' => 'email', 'tipo' => 'email', 'etichetta' => t('book.details.email'),
                'valore' => $valore('email'), 'errore' => $errore('email'),
                'autocomplete' => 'email', 'obbligatorio' => true,
            ]) ?>
            <?= component('field', [
                'nome' => 'telefono', 'tipo' => 'tel', 'etichetta' => t('book.details.phone'),
                'valore' => $valore('telefono'), 'errore' => $errore('telefono'),
                'autocomplete' => 'tel', 'facoltativo' => true,
            ]) ?>
          </div>

          <?= component('field', [
              'nome' => 'paese', 'etichetta' => t('book.details.country'),
              'valore' => $valore('paese'), 'errore' => $errore('paese'),
              'autocomplete' => 'country-name', 'facoltativo' => true,
          ]) ?>

          <?= component('field', [
              'nome' => 'note', 'tipo' => 'textarea', 'etichetta' => t('book.details.notes'),
              'valore' => $valore('note'), 'errore' => $errore('note'),
              'aiuto' => t('book.details.notes_help'), 'facoltativo' => true,
          ]) ?>

          <div class="adv-modulo__spunta">
            <input type="checkbox" id="privacy" name="privacy" value="1"
                   <?= $valore('privacy') !== '' ? 'checked' : '' ?>
                   <?= $errore('privacy') ? 'aria-invalid="true" aria-describedby="err-privacy"' : '' ?>>
            <label for="privacy">
              <?= te('book.details.privacy') ?>
              <a href="<?= e(url('privacy')) ?>"><?= te('nav.privacy') ?></a>
            </label>
          </div>
          <?php if ($messaggio = $errore('privacy')): ?>
            <p class="adv-campo__errore" id="err-privacy"><span aria-hidden="true">&#9888;</span><?= e($messaggio) ?></p>
          <?php endif; ?>
        </fieldset>

        <div>
          <button class="adv-btn adv-btn--primario adv-btn--lg" type="submit"><?= te('book.details.submit') ?></button>
        </div>
      </form>

      <aside class="adv-split__nota">
        <h2 class="adv-titolo-md"><?= te('book.details.summary') ?></h2>
        <?= component('booking-summary', [
            'camera'       => R::name($offerta->room, $lingua),
            'arrivo'       => $criteri->arrivalIso(),
            'partenza'     => $criteri->departureIso(),
            'notti'        => $offerta->nights,
            'ospiti'       => $criteri->guests,
            'tariffa'      => $offerta->nightlyRate,
            'totale'       => $offerta->total,
            'dimostrativa' => $offerta->rateIsDemo,
        ]) ?>
        <p class="adv-azione-coda">
          <a class="adv-elenco__link" href="<?= e(url('book', [], [
              'passo' => 'camere', 'arrivo' => $criteri->arrivalIso(),
              'partenza' => $criteri->departureIso(), 'ospiti' => (string) $criteri->guests,
          ])) ?>">
            <?= te('book.details.change_room') ?><span aria-hidden="true">&rarr;</span>
          </a>
        </p>
      </aside>

    </div>


  <?php /* ------------------------------------- 4. richiesta inviata */ ?>
  <?php elseif ($passo === 'done'): ?>

    <?= component('alert', [
        'tipo'   => 'successo',
        'titolo' => t('book.done.title'),
        'testo'  => t('book.done.text'),
    ]) ?>

    <div class="adv-split adv-split--stretta adv-spazio-sopra">

      <div>
        <?= component('section-header', [
            'occhiello' => t('book.done.reference'),
            'titolo'    => $conferma['reference'],
            'piccolo'   => true,
        ]) ?>

        <h3 class="adv-titolo-sm adv-spazio-sopra"><?= te('book.done.next') ?></h3>
        <p class="adv-testo"><?= te('book.done.next_text') ?></p>

        <?php if (!empty($conferma['pretend'])): ?>
          <p class="adv-nota"><?= te('book.done.demo_note') ?></p>
        <?php endif; ?>

        <p class="adv-azione-coda">
          <a class="adv-btn adv-btn--contorno" href="<?= e(url('home')) ?>"><?= te('book.done.home') ?></a>
        </p>
      </div>

      <aside class="adv-split__nota">
        <h2 class="adv-titolo-md"><?= te('book.details.summary') ?></h2>
        <?= component('booking-summary', [
            'camera'       => $conferma['room'],
            'arrivo'       => $conferma['arrival'],
            'partenza'     => $conferma['departure'],
            'notti'        => $conferma['nights'],
            'ospiti'       => $conferma['guests'],
            'tariffa'      => $conferma['rate'],
            'totale'       => $conferma['total'],
            'dimostrativa' => $conferma['rateIsDemo'],
        ]) ?>
      </aside>

    </div>

  <?php endif; ?>

</section>
