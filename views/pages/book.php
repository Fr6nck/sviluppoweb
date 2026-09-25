<?php
/**
 * La prenotazione, in quattro passi.
 *
 *     date e ospiti → camere libere → i tuoi dati → richiesta inviata
 *
 * Funziona senza JavaScript: le date sono campi nativi, ogni passo è una
 * pagina con il suo indirizzo, e il tasto «indietro» fa quello che promette.
 *
 * @var string $passo   dates | rooms | details | done — con il pagamento: pay | result
 * @var bool   $pagamento  il pagamento online è acceso
 * @var bool   $calendarioDemo
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
$pagamento = $pagamento ?? false;
$passi   = $pagamento ? ['dates', 'rooms', 'details', 'pay'] : ['dates', 'rooms', 'details', 'done'];
$indice  = in_array($passo, ['pay', 'result'], true) ? 3 : array_search($passo, $passi, true);
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

<header class="adv-contenuto adv-testa">
  <?= occhiello(t('book.eyebrow'), 'adv-occhiello--centro') ?>
  <h1 class="adv-titolo adv-titolo--l"><?= titolo(t('book.title'), t('book.sign')) ?></h1>
  <p class="adv-testa__testo"><?= te(!empty($pagamento) ? 'book.lead_pay' : 'book.lead') ?></p>
</header>

<section class="adv-contenuto adv-sezione adv-sezione--stretta-sopra">

  <ol class="adv-passi">
    <?php foreach ($passi as $i => $chiave): ?>
      <li<?= $i === $indice ? ' aria-current="step"' : '' ?>>
        <span class="adv-passi__numero"><?= sprintf('%02d', $i + 1) ?></span>
        <?= $chiave === 'pay' ? te('book.pay.step') : te('book.steps.' . $chiave) ?>
      </li>
    <?php endforeach; ?>
  </ol>

  <?php if (!in_array($passo, ['done', 'pay', 'result'], true) && ($calendarioDemo ?? true)): ?>
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
        'misura'    => 's',
        'testo'     => t('book.results.for_dates', [
            'from'   => dataEstesa($criteri->arrivalIso()),
            'to'     => dataEstesa($criteri->departureIso()),
            'guests' => $criteri->guests . ' ' . t($criteri->guests === 1 ? 'common.guest' : 'common.guests'),
        ]),
    ]) ?>

    <p>
      <a class="adv-link" href="<?= e(url('book')) ?>"><?= te('book.results.change') ?><?= icona('freccia-su-destra', 13) ?></a>
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

      <div class="adv-azioni">
        <a class="adv-btn adv-btn--primario" href="<?= e(url('contact')) ?>"><?= te('cta.write') ?><?= icona('freccia-su-destra', 14) ?></a>
      </div>

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
                  '<a class="adv-link adv-alternativa" href="%s">%s &rarr; %s</a>',
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
      <ul class="adv-griglia-camere adv-spazio-sopra">
        <?php foreach ($libere as $offerta): ?>
          <li>
            <?= component('room-card', [
                'camera'  => $offerta->room,
                'offerta' => $offerta,
                'azione'  => sprintf(
                    '<a class="adv-btn adv-btn--primario adv-btn--pieno" href="%s">%s</a>',
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
        <h3 class="adv-titolo adv-titolo--xs"><?= te('book.results.unavailable_room') ?></h3>
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
  <?php elseif ($passo === 'details' && $offerta !== null && $pagamento): ?>

    <?php
    /* Con il pagamento online: nome, e-mail, la spunta sulle condizioni, e
       il pulsante dice quanto si paga. Le condizioni stanno accanto, prima
       del pulsante: chi paga deve sapere che cosa succede se annulla. */
    $cancellazione = site('stay.cancellation');
    $cancellazione = is_array($cancellazione) ? trim((string) ($cancellazione[$lingua] ?? '')) : trim((string) $cancellazione);
    ?>
    <div class="adv-due adv-due--modulo">

      <form class="adv-modulo adv-pannello" method="post" action="<?= e(url('book')) ?>" novalidate>
        <?= Csrf::field() ?>
        <input type="hidden" name="arrivo"   value="<?= e($criteri->arrivalIso()) ?>">
        <input type="hidden" name="partenza" value="<?= e($criteri->departureIso()) ?>">
        <input type="hidden" name="ospiti"   value="<?= e((string) $criteri->guests) ?>">
        <input type="hidden" name="camera"   value="<?= e($offerta->ref()) ?>">

        <fieldset>
          <legend><?= te('book.pay.legend') ?></legend>

          <?= component('field', [
              'nome' => 'nome', 'etichetta' => t('book.pay.name'),
              'valore' => $valore('nome'), 'errore' => $errore('nome'),
              'autocomplete' => 'name', 'obbligatorio' => true,
          ]) ?>
          <?= component('field', [
              'nome' => 'email', 'tipo' => 'email', 'etichetta' => t('book.pay.email'),
              'valore' => $valore('email'), 'errore' => $errore('email'),
              'aiuto' => t('book.pay.email_help'),
              'autocomplete' => 'email', 'obbligatorio' => true,
          ]) ?>

          <div class="adv-modulo__spunta">
            <input type="checkbox" id="condizioni" name="condizioni" value="1"
                   <?= $valore('condizioni') !== '' ? 'checked' : '' ?>
                   <?= $errore('condizioni') ? 'aria-invalid="true" aria-describedby="err-condizioni"' : '' ?>>
            <label for="condizioni">
              <?= te('book.pay.accept') ?>
              <a href="<?= e(url('privacy')) ?>"><?= te('nav.privacy') ?></a>
            </label>
          </div>
          <?php if ($messaggio = $errore('condizioni')): ?>
            <p class="adv-campo__errore" id="err-condizioni"><span aria-hidden="true">&#9888;</span><?= e($messaggio) ?></p>
          <?php endif; ?>
        </fieldset>

        <div>
          <button class="adv-btn adv-btn--primario adv-btn--grande" type="submit"><?= te('book.pay.submit', ['amount' => euro($offerta->total)]) ?><?= icona('freccia-su-destra', 14) ?></button>
          <p class="adv-nota adv-spazio-sopra-s"><?= te('book.pay.secure') ?></p>
        </div>
      </form>

      <aside class="adv-pannello adv-prenota-camera">
        <h2 class="adv-titolo adv-titolo--xs"><?= te('book.details.summary') ?></h2>
        <?= component('booking-summary', [
            'camera'       => R::name($offerta->room, $lingua),
            'arrivo'       => $criteri->arrivalIso(),
            'partenza'     => $criteri->departureIso(),
            'notti'        => $offerta->nights,
            'ospiti'       => $criteri->guests,
            'tariffa'      => $offerta->nightlyRate,
            'totale'       => $offerta->total,
            'dimostrativa' => $offerta->rateIsDemo,
            'modo'         => 'paga',
        ]) ?>
        <h3 class="adv-titolo adv-titolo--xs adv-spazio-sopra"><?= te('book.pay.conditions') ?></h3>
        <ul class="adv-condizioni">
          <li><?= te('book.pay.full_amount') ?></li>
          <li><strong><?= te('book.pay.cancellation') ?>:</strong>
            <?= $cancellazione !== '' ? nl2br(e($cancellazione)) : daConfermare() ?></li>
        </ul>
        <p>
          <a class="adv-link" href="<?= e(url('book', [], [
              'passo' => 'camere', 'arrivo' => $criteri->arrivalIso(),
              'partenza' => $criteri->departureIso(), 'ospiti' => (string) $criteri->guests,
          ])) ?>"><?= te('book.details.change_room') ?><?= icona('freccia-su-destra', 13) ?></a>
        </p>
      </aside>

    </div>


  <?php elseif ($passo === 'details' && $offerta !== null): ?>

    <div class="adv-due adv-due--modulo">

      <form class="adv-modulo adv-pannello" method="post" action="<?= e(url('book')) ?>" novalidate>
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
          <button class="adv-btn adv-btn--primario adv-btn--grande" type="submit"><?= te('book.details.submit') ?><?= icona('freccia-su-destra', 14) ?></button>
        </div>
      </form>

      <aside class="adv-pannello adv-prenota-camera">
        <h2 class="adv-titolo adv-titolo--xs"><?= te('book.details.summary') ?></h2>
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
        <p>
          <a class="adv-link" href="<?= e(url('book', [], [
              'passo' => 'camere', 'arrivo' => $criteri->arrivalIso(),
              'partenza' => $criteri->departureIso(), 'ospiti' => (string) $criteri->guests,
          ])) ?>"><?= te('book.details.change_room') ?><?= icona('freccia-su-destra', 13) ?></a>
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

    <div class="adv-due adv-due--modulo adv-spazio-sopra">

      <div>
        <?= occhiello(mb_strtoupper(t('book.done.reference'))) ?>
        <h2 class="adv-titolo adv-titolo--m adv-riferimento"><?= e($conferma['reference']) ?></h2>

        <h3 class="adv-titolo adv-titolo--xs adv-spazio-sopra"><?= te('book.done.next') ?></h3>
        <p class="adv-testo"><?= te('book.done.next_text') ?></p>

        <?php if (!empty($conferma['pretend'])): ?>
          <p class="adv-nota"><?= te('book.done.demo_note') ?></p>
        <?php endif; ?>

        <div class="adv-azioni">
          <a class="adv-btn adv-btn--contorno" href="<?= e(url('home')) ?>"><?= te('book.done.home') ?></a>
        </div>
      </div>

      <aside class="adv-pannello">
        <h2 class="adv-titolo adv-titolo--xs"><?= te('book.details.summary') ?></h2>
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

  <?php /* ------------------------------------- 4. verso SumUp */ ?>
  <?php elseif ($passo === 'pay' && !empty($voce)): ?>

    <?php
    $d = (array) $voce['dati'];
    $unaCamera = \ArcoDelVento\App::instance()->rooms()->findByRef((string) ($d['ref'] ?? ''));
    ?>
    <div class="adv-due adv-due--modulo">
      <div class="adv-pannello adv-vai-pagamento">
        <h2 class="adv-titolo adv-titolo--s"><?= te('book.pay.going_title') ?></h2>
        <p class="adv-testo"><?= te('book.pay.going_text', ['amount' => euro((float) $d['pagamento']['importo'])]) ?></p>
        <p class="adv-azioni">
          <a class="adv-btn adv-btn--primario adv-btn--grande" href="<?= e($urlPagamento) ?>" data-vai-al-pagamento><?= te('book.pay.going_button') ?><?= icona('freccia-su-destra', 14) ?></a>
        </p>
        <p class="adv-nota"><?= te('book.pay.going_note') ?></p>
        <p class="adv-nota"><?= te('book.pay.secure') ?></p>
      </div>
      <aside class="adv-pannello">
        <h2 class="adv-titolo adv-titolo--xs"><?= te('book.details.summary') ?></h2>
        <?= component('booking-summary', [
            'camera'       => $unaCamera ? R::name($unaCamera, $lingua) : (string) ($d['camera'] ?? ''),
            'arrivo'       => (string) $d['arrivo'],
            'partenza'     => (string) $d['partenza'],
            'notti'        => (int) $d['notti'],
            'ospiti'       => (int) $d['ospiti'],
            'tariffa'      => (float) $d['totale'] / max(1, (int) $d['notti']),
            'totale'       => (float) $d['pagamento']['importo'],
            'dimostrativa' => false,
            'modo'         => 'paga',
        ]) ?>
      </aside>
    </div>


  <?php /* ------------------------------------- 4. com'è andata */ ?>
  <?php elseif ($passo === 'result'): ?>

    <?php
    $email = (string) site('contacts.email');
    [$tipo, $titolo, $testo] = match (true) {
        ($avviso ?? null) === 'taken' => ['errore', t('book.pay.taken_title'), t('book.pay.taken')],
        ($avviso ?? null) === 'error' => ['errore', t('book.pay.error_title'), t('book.pay.error', ['email' => $email])],
        $stato === 'pagato'           => ['successo', t('book.pay.paid_title'), t('book.pay.paid_text')],
        $stato === 'da controllare'   => ['avviso', t('book.pay.check_title'), t('book.pay.check_text')],
        $stato === 'non riuscito'     => ['errore', t('book.pay.failed_title'), t('book.pay.failed_text')],
        $stato === 'scaduto'          => ['avviso', t('book.pay.expired_title'), t('book.pay.expired_text')],
        $stato === 'in attesa'        => ['avviso', t('book.pay.pending_title'), t('book.pay.pending_text')],
        default                       => ['errore', t('book.pay.unknown_title'), t('book.pay.unknown_text', ['email' => $email])],
    };
    $siRiprova = !empty($voce) && in_array($stato, ['in attesa', 'non riuscito', 'scaduto'], true) && ($avviso ?? null) !== 'taken';
    $d = !empty($voce) ? (array) $voce['dati'] : null;
    $unaCamera = $d ? \ArcoDelVento\App::instance()->rooms()->findByRef((string) ($d['ref'] ?? '')) : null;
    ?>
    <?= component('alert', ['tipo' => $tipo, 'titolo' => $titolo, 'testo' => $testo]) ?>

    <div class="adv-due adv-due--modulo adv-spazio-sopra">
      <div>
        <?php if ($riferimento !== '' && $d !== null): ?>
          <?= occhiello(mb_strtoupper(t('book.done.reference'))) ?>
          <h2 class="adv-titolo adv-titolo--m adv-riferimento"><?= e($riferimento) ?></h2>
        <?php endif; ?>

        <?php if ($siRiprova): ?>
          <form method="post" action="<?= e(url('book')) ?>" class="adv-azioni">
            <?= Csrf::field() ?>
            <input type="hidden" name="azione" value="riprova">
            <input type="hidden" name="rif" value="<?= e($riferimento) ?>">
            <button class="adv-btn adv-btn--primario" type="submit"><?= te('book.pay.retry') ?><?= icona('freccia-su-destra', 14) ?></button>
            <?php if ($stato === 'in attesa'): ?>
              <a class="adv-btn adv-btn--contorno" href="<?= e(url('book', [], ['passo' => 'esito', 'rif' => $riferimento])) ?>"><?= te('book.pay.reload') ?></a>
            <?php endif; ?>
          </form>
        <?php else: ?>
          <div class="adv-azioni">
            <?php if (($avviso ?? null) === 'taken'): ?>
              <a class="adv-btn adv-btn--primario" href="<?= e(url('book')) ?>"><?= te('book.results.change') ?><?= icona('freccia-su-destra', 14) ?></a>
            <?php endif; ?>
            <a class="adv-btn adv-btn--contorno" href="<?= e(url('home')) ?>"><?= te('book.done.home') ?></a>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($d !== null): ?>
        <aside class="adv-pannello">
          <h2 class="adv-titolo adv-titolo--xs"><?= te('book.details.summary') ?></h2>
          <?= component('booking-summary', [
              'camera'       => $unaCamera ? R::name($unaCamera, $lingua) : (string) ($d['camera'] ?? ''),
              'arrivo'       => (string) $d['arrivo'],
              'partenza'     => (string) $d['partenza'],
              'notti'        => (int) $d['notti'],
              'ospiti'       => (int) $d['ospiti'],
              'tariffa'      => (float) $d['totale'] / max(1, (int) $d['notti']),
              'totale'       => (float) ($d['pagamento']['importo'] ?? $d['totale']),
              'dimostrativa' => false,
              'modo'         => $stato === 'pagato' ? 'pagato' : 'paga',
          ]) ?>
        </aside>
      <?php endif; ?>
    </div>

  <?php endif; ?>

</section>
