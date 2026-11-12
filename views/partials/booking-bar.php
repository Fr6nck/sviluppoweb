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
 * @var bool   $sospesa  true sulla home, dove la barra sale sulle fotografie
 */

$criteri   = $criteri ?? null;
$errori    = $errori ?? [];
$id        = $id ?? 'barra';
$sospesa   = $sospesa ?? false;
$minNights = \ArcoDelVento\App::instance()->booking()->minNights();
$stay      = site('stay');

$oggi = (new DateTimeImmutable('today'))->format('Y-m-d');

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

$aria = static function (string $campo, string $idErrore) use ($errore): string {
    return $errore($campo) ? ' aria-invalid="true" aria-describedby="' . e($idErrore) . '"' : '';
};
?>
<form class="adv-barra<?= $sospesa ? ' adv-barra--sospesa' : '' ?>" id="disponibilita-<?= e($id) ?>" role="search"
      aria-label="<?= te('book.search.legend') ?>" method="get" action="<?= e(url('book')) ?>" data-barra>
  <input type="hidden" name="passo" value="camere">

  <div class="adv-barra__campo">
    <label class="adv-barra__label" for="arrivo-<?= e($id) ?>"><?= te('book.search.arrival') ?></label>
    <input class="adv-barra__controllo" type="date" id="arrivo-<?= e($id) ?>" name="arrivo"
           value="<?= e($arrivo) ?>" min="<?= e($oggi) ?>" required<?= $aria('arrival', 'err-arrivo-' . $id) ?>>
    <?php if ($messaggio = $errore('arrival')): ?>
      <p class="adv-barra__errore" id="err-arrivo-<?= e($id) ?>"><span aria-hidden="true">&#9888;</span><?= e($messaggio) ?></p>
    <?php elseif (!empty($stay['check_in_from'])): ?>
      <span class="adv-barra__sotto"><?= te('info.items.check_in') ?> <?= e($stay['check_in_from'] . '–' . $stay['check_in_to']) ?></span>
    <?php endif; ?>
  </div>

  <div class="adv-barra__campo">
    <label class="adv-barra__label" for="partenza-<?= e($id) ?>"><?= te('book.search.departure') ?></label>
    <input class="adv-barra__controllo" type="date" id="partenza-<?= e($id) ?>" name="partenza"
           value="<?= e($partenza) ?>" min="<?= e($oggi) ?>" required<?= $aria('departure', 'err-partenza-' . $id) ?>>
    <?php if ($messaggio = $errore('departure')): ?>
      <p class="adv-barra__errore" id="err-partenza-<?= e($id) ?>"><span aria-hidden="true">&#9888;</span><?= e($messaggio) ?></p>
    <?php endif; ?>
  </div>

  <div class="adv-barra__campo adv-barra__campo--ultimo">
    <label class="adv-barra__label" for="ospiti-<?= e($id) ?>"><?= te('book.search.guests') ?></label>
    <span class="adv-barra__scelta">
      <select class="adv-barra__controllo" id="ospiti-<?= e($id) ?>" name="ospiti">
        <?php foreach (range(1, $capienza) as $n): ?>
          <option value="<?= $n ?>"<?= (int) $ospiti === $n ? ' selected' : '' ?>>
            <?= $n ?> <?= te($n === 1 ? 'common.guest' : 'common.guests') ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?= icona('giu', 14) ?>
    </span>
    <span class="adv-barra__sotto"><?= te('rooms.card.guests_up_to', ['count' => $capienza]) ?></span>
  </div>

  <div class="adv-barra__azione">
    <button class="adv-btn adv-btn--primario" type="submit">
      <?= te('book.search.submit') ?><?= icona('freccia-su-destra', 14) ?>
    </button>
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
