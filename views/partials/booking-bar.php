<?php
/**
 * Arrivo, partenza, ospiti e il pulsante: il primo gesto del sito.
 *
 * È un form GET, non POST: il risultato di una ricerca è un indirizzo che si
 * può salvare, rimandare a qualcuno e ricaricare. Le date sono
 * <input type="date"> nativi — il calendario del telefono è già tradotto, già
 * accessibile e già noto a chi lo usa.
 *
 * Le condizioni — soggiorno minimo, tassa di soggiorno — si dichiarano prima
 * della ricerca, non in una schermata di errore a valle.
 *
 * @var \ArcoDelVento\Booking\SearchCriteria|null $criteri
 * @var array  $errori
 * @var string $id
 */

$criteri   = $criteri ?? null;
$errori    = $errori ?? [];
$id        = $id ?? 'barra';
$minNights = \ArcoDelVento\App::instance()->booking()->minNights();

$oggi     = (new DateTimeImmutable('today'))->format('Y-m-d');

/* Il massimo non è un numero scritto a mano: è la capienza della camera più
   grande. Offrire «4 ospiti» quando nessuna stanza ne ospita quattro manda
   l'utente in un vicolo cieco che il sito conosceva già in partenza. Se un
   giorno si aggiunge una camera più grande, il selettore cresce da solo. */
$capienza = 1;
foreach (\ArcoDelVento\App::instance()->rooms()->all() as $unaCamera) {
    $capienza = max($capienza, (int) ($unaCamera['occupancy']['max'] ?? 1));
}
$arrivo   = $criteri?->arrivalIso()   ?? '';
$partenza = $criteri?->departureIso() ?? '';
$ospiti   = $criteri?->guests ?? ($ospiti ?? 2);

/** Traduce la coppia [chiave, sostituzioni] che arriva dal servizio. */
$errore = static function (string $campo) use ($errori): ?string {
    if (!isset($errori[$campo])) {
        return null;
    }
    [$chiave, $sostituzioni] = $errori[$campo];

    return t('errors.' . $chiave, $sostituzioni);
};
?>
<form class="adv-barra" role="search" aria-label="<?= te('book.search.legend') ?>"
      method="get" action="<?= e(url('book')) ?>">
  <input type="hidden" name="passo" value="camere">

  <div class="adv-campo">
    <label class="adv-campo__label" for="arrivo-<?= e($id) ?>"><?= te('book.search.arrival') ?></label>
    <input class="adv-input" type="date" id="arrivo-<?= e($id) ?>" name="arrivo"
           value="<?= e($arrivo) ?>" min="<?= e($oggi) ?>" required
           <?= $errore('arrival') ? 'aria-invalid="true" aria-describedby="err-arrivo-' . e($id) . '"' : '' ?>>
    <?php if ($messaggio = $errore('arrival')): ?>
      <p class="adv-campo__errore" id="err-arrivo-<?= e($id) ?>">
        <span aria-hidden="true">&#9888;</span><?= e($messaggio) ?>
      </p>
    <?php endif; ?>
  </div>

  <div class="adv-campo">
    <label class="adv-campo__label" for="partenza-<?= e($id) ?>"><?= te('book.search.departure') ?></label>
    <input class="adv-input" type="date" id="partenza-<?= e($id) ?>" name="partenza"
           value="<?= e($partenza) ?>" min="<?= e($oggi) ?>" required
           <?= $errore('departure') ? 'aria-invalid="true" aria-describedby="err-partenza-' . e($id) . '"' : '' ?>>
    <?php if ($messaggio = $errore('departure')): ?>
      <p class="adv-campo__errore" id="err-partenza-<?= e($id) ?>">
        <span aria-hidden="true">&#9888;</span><?= e($messaggio) ?>
      </p>
    <?php endif; ?>
  </div>

  <div class="adv-campo">
    <label class="adv-campo__label" for="ospiti-<?= e($id) ?>"><?= te('book.search.guests') ?></label>
    <select class="adv-select" id="ospiti-<?= e($id) ?>" name="ospiti">
      <?php foreach (range(1, $capienza) as $n): ?>
        <option value="<?= $n ?>"<?= (int) $ospiti === $n ? ' selected' : '' ?>>
          <?= $n ?> <?= te($n === 1 ? 'common.guest' : 'common.guests') ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="adv-campo adv-barra__azione">
    <button class="adv-btn adv-btn--primario" type="submit"><?= te('book.search.submit') ?></button>
  </div>

  <?php /* Il soggiorno minimo si dichiara solo se esiste davvero: il titolare
           non ne ha indicato uno, e inventarne uno bloccherebbe prenotazioni
           vere. Con BOOKING_MIN_NIGHTS a 1 la riga parla solo del prezzo —
           «soggiorno minimo 1 notti» non è una condizione, è un refuso. */ ?>
  <p class="adv-barra__nota">
    <?= $minNights > 1
        ? te('book.search.note', ['nights' => $minNights])
        : te('book.search.note_no_min') ?>
  </p>
</form>
