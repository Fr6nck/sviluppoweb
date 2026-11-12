<?php
/**
 * Il calendario delle camere, mese per mese.
 *
 * In alto il mese, con le frecce, la legenda e «Blocca date». Poi:
 *   - sugli schermi larghi, la tabella di tutte le camere insieme: una riga
 *     per camera, una casella per notte;
 *   - camera per camera, una scheda con il mese a sette colonne (come un
 *     calendario da muro), i periodi occupati e lo stato di Booking.com. Sul
 *     telefono è la vista principale: la tabella di 31 colonne non ci sta.
 * In fondo, il modulo per bloccare una camera e i blocchi da togliere.
 *
 * Prenotazioni dirette, Booking.com e blocchi manuali hanno ognuna il suo
 * colore, la sua lettera e la sua etichetta: si distinguono anche senza
 * vedere i colori.
 *
 * @var \ArcoDelVento\Admin\Calendario $calendario
 * @var list<array<string,mixed>> $camere
 * @var array<string, array<string,mixed>|null> $ical  ref => stato del calendario di Booking.com
 * @var bool $channelScollegato
 * @var list<array<string,mixed>> $blocchi  i blocchi manuali, da oggi in poi
 * @var array<string,mixed> $valoriBlocco  quello scritto nel modulo, dopo un errore
 */
use ArcoDelVento\Admin\Calendario;
use ArcoDelVento\Support\Csrf;

$primo   = $calendario->primo();
$prima   = $primo->modify('-1 month');
$dopo    = $primo->modify('+1 month');
$oggi    = date('Y-m-d');
$nomi    = [];
foreach ($camere as $c) {
    $nomi[(string) $c['ref']] = (string) ($c['name']['it'] ?? $c['ref']);
}
$giorno  = static fn (string $iso): string => date('d/m/Y', strtotime($iso) ?: 0);
$valoriBlocco = $valoriBlocco ?? [];
$blocchi = $blocchi ?? [];
$giorni  = $calendario->giorni();
// Il mese a sette colonne comincia di lunedì: prima del primo giorno, caselle vuote.
$vuoteIniziali = (int) $giorni[0]->format('N') - 1;
$settimane = array_chunk([...array_fill(0, $vuoteIniziali, null), ...$giorni], 7);
$etichettaNotte = static fn (\DateTimeImmutable $g, ?array $f): string => (int) $g->format('j') . ' ' . Calendario::MESI[(int) $g->format('n') - 1]
    . ': ' . ($f['etichetta'] ?? 'libera');
?>
<p class="adm-intro">Le notti di ogni camera: libere, prenotate dal sito, prenotate su Booking.com, o bloccate a mano.
   Una prenotazione dal 7 al 10 occupa le notti del 7, 8 e 9: il 10 è il giorno di partenza, ed è libero. Questo
   calendario lo vede solo l'area riservata: chi visita il sito vede soltanto le camere libere per le date che chiede.</p>

<?php if (!empty($channelScollegato)): /* interruttore del channel manager, funzione di prova */ ?>
  <section class="adm-striscia" aria-labelledby="cal-channel">
    <span class="adm-striscia__icona" aria-hidden="true"><?= icona('attenzione', 20) ?></span>
    <div>
      <h2 id="cal-channel" class="adm-striscia__titolo">Il calendario di Booking.com è scollegato</h2>
      <p>Le notti prenotate su Booking non compaiono qui e non contano: il sito le vende come libere.</p>
    </div>
  </section>
<?php endif; ?>

<section class="adm-carta adm-cal-testa" aria-labelledby="cal-mese">
  <div class="adm-cal-mesi">
    <a class="adm-pillola" href="<?= e(adminUrl('calendario')) ?>?mese=<?= e($prima->format('Y-m')) ?>" aria-label="Mese prima: <?= e($calendario->nome($prima)) ?>"><?= icona('freccia-sinistra', 15) ?></a>
    <h2 id="cal-mese" class="adm-cal-mesi__titolo"><?= e(ucfirst($calendario->nome())) ?></h2>
    <a class="adm-pillola" href="<?= e(adminUrl('calendario')) ?>?mese=<?= e($dopo->format('Y-m')) ?>" aria-label="Mese dopo: <?= e($calendario->nome($dopo)) ?>"><?= icona('freccia-destra', 15) ?></a>
    <?php if ($primo->format('Y-m') !== date('Y-m')): ?>
      <a class="adm-link" href="<?= e(adminUrl('calendario')) ?>">Questo mese</a>
    <?php endif; ?>
    <a class="adm-bottone adm-bottone--piatto adm-cal-mesi__blocca" href="#blocca"><?= icona('chiave', 16) ?>Blocca date</a>
  </div>

  <ul class="adm-cal-legenda" aria-label="Legenda">
    <li><span class="adm-cal-segno adm-cal-segno--libera" aria-hidden="true"></span>Libera</li>
    <?php foreach (Calendario::FONTI as $fonte => $f): ?>
      <li><span class="adm-cal-segno adm-cal-segno--<?= e($fonte) ?>" aria-hidden="true"><?= e($f['lettera']) ?></span><?= e($f['etichetta']) ?></li>
    <?php endforeach; ?>
  </ul>

  <?php /* Tutte le camere insieme: solo sugli schermi larghi (il CSS la nasconde sotto i 1181 px). */ ?>
  <div class="adm-tabella-scorre adm-cal-panoramica">
    <table class="adm-cal">
      <caption class="adm-visually-hidden">Le notti di ogni camera, <?= e($calendario->nome()) ?></caption>
      <thead>
        <tr>
          <th scope="col" class="adm-cal__camera">Camera</th>
          <?php foreach ($giorni as $g): $w = (int) $g->format('w'); ?>
            <th scope="col" class="adm-cal__giorno<?= $g->format('Y-m-d') === $oggi ? ' adm-cal__giorno--oggi' : '' ?><?= $w === 0 || $w === 6 ? ' adm-cal__giorno--fine' : '' ?>">
              <span class="adm-cal__dow" aria-hidden="true"><?= e(mb_strtoupper(mb_substr(Calendario::GIORNI[$w], 0, 1))) ?></span><span aria-hidden="true"><?= (int) $g->format('j') ?></span>
              <span class="adm-visually-hidden"><?= e(Calendario::GIORNI[$w] . ' ' . (int) $g->format('j')) ?></span>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($camere as $c): $ref = (string) $c['ref']; ?>
          <tr data-camera="<?= e($ref) ?>">
            <th scope="row" class="adm-cal__camera"><a href="<?= e(adminUrl('camere/' . rawurlencode($ref))) ?>"><?= e($nomi[$ref]) ?></a></th>
            <?php foreach ($giorni as $g): $iso = $g->format('Y-m-d'); $n = $calendario->notte($ref, $iso); $f = $n !== null ? Calendario::FONTI[$n['fonte']] : null; ?>
              <td class="adm-cal__notte<?= $n !== null ? ' adm-cal__notte--' . e((string) $n['fonte']) : '' ?><?= $iso === $oggi ? ' adm-cal__notte--oggi' : '' ?>"
                  data-giorno="<?= e($iso) ?>" data-fonte="<?= $n !== null ? e((string) $n['fonte']) : 'libera' ?>"
                  title="<?= e($nomi[$ref] . ', notte del ' . (int) $g->format('j') . ' ' . Calendario::MESI[(int) $g->format('n') - 1] . ': ' . ($f['etichetta'] ?? 'libera')) ?>"><?php if ($f !== null): ?><span aria-hidden="true"><?= e($f['lettera']) ?></span><span class="adm-visually-hidden"><?= e($f['etichetta']) ?></span><?php else: ?><span class="adm-visually-hidden">libera</span><?php endif; ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="adm-cal-camere" aria-labelledby="cal-camere">
  <h2 id="cal-camere" class="adm-cal-camere__titolo">Camera per camera <span class="adm-tenue">· <?= e($calendario->nome()) ?></span></h2>
  <div class="adm-cal-camere__griglia">
    <?php foreach ($camere as $c): $ref = (string) $c['ref']; $periodi = $calendario->periodi($ref); $s = $ical[$ref] ?? null; $occupate = $calendario->occupate($ref); ?>
      <article class="adm-carta adm-cal-camera" data-periodi-camera="<?= e($ref) ?>" aria-labelledby="cal-camera-<?= e($ref) ?>">
        <h3 id="cal-camera-<?= e($ref) ?>" class="adm-cal-camera__nome">
          <a href="<?= e(adminUrl('camere/' . rawurlencode($ref))) ?>"><?= e($nomi[$ref]) ?></a>
          <span class="adm-cal-camera__conto<?= $occupate === 0 ? ' adm-cal-camera__conto--libera' : '' ?>"><?= $occupate === 0 ? 'tutto libero' : $occupate . ' ' . ($occupate === 1 ? 'notte occupata' : 'notti occupate') ?></span>
        </h3>

        <table class="adm-mini">
          <caption class="adm-visually-hidden"><?= e($nomi[$ref] . ', ' . $calendario->nome()) ?></caption>
          <thead>
            <tr>
              <?php foreach ([1, 2, 3, 4, 5, 6, 0] as $w): ?>
                <th scope="col"><abbr title="<?= e(Calendario::GIORNI[$w]) ?>"><?= e(mb_strtoupper(mb_substr(Calendario::GIORNI[$w], 0, 1))) ?></abbr></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($settimane as $settimana): ?>
              <tr>
                <?php foreach (array_pad($settimana, 7, null) as $g): ?>
                  <?php if ($g === null): ?>
                    <td class="adm-mini__vuota"></td>
                  <?php else: $iso = $g->format('Y-m-d'); $n = $calendario->notte($ref, $iso); $f = $n !== null ? Calendario::FONTI[$n['fonte']] : null; ?>
                    <td class="adm-mini__g<?= $n !== null ? ' adm-cal__notte--' . e((string) $n['fonte']) : '' ?><?= $iso === $oggi ? ' adm-mini__g--oggi' : '' ?><?= $iso < $oggi ? ' adm-mini__g--passato' : '' ?>"
                        data-mini-giorno="<?= e($iso) ?>" data-mini-fonte="<?= $n !== null ? e((string) $n['fonte']) : 'libera' ?>"
                        title="<?= e('Notte del ' . $etichettaNotte($g, $f)) ?>">
                      <span class="adm-mini__numero" aria-hidden="true"><?= (int) $g->format('j') ?></span>
                      <?php if ($f !== null): ?><span class="adm-mini__lettera" aria-hidden="true"><?= e($f['lettera']) ?></span><?php endif; ?>
                      <span class="adm-visually-hidden"><?= e($etichettaNotte($g, $f)) ?></span>
                    </td>
                  <?php endif; ?>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if ($periodi === []): ?>
          <p class="adm-tenue adm-cal-camera__vuoto">Nessuna notte occupata in <?= e($calendario->nome()) ?>.</p>
        <?php else: ?>
          <ul class="adm-cal-camera__periodi">
            <?php foreach ($periodi as $p): $f = Calendario::FONTI[$p['fonte']]; ?>
              <li data-fonte="<?= e((string) $p['fonte']) ?>">
                <span class="adm-cal-etichetta adm-cal-etichetta--<?= e((string) $p['fonte']) ?>"><?= e($f['breve']) ?></span>
                dal <?= e($giorno((string) $p['arrivo'])) ?> al <?= e($giorno((string) $p['partenza'])) ?>
                <?php if ($p['fonte'] === 'sito'): ?>
                  · <a class="adm-link" href="<?= e(adminUrl('richieste/' . rawurlencode((string) $p['id']))) ?>"><?= e((string) $p['id']) ?></a>
                <?php elseif ($p['fonte'] === 'manuale' && !empty($p['nota'])): ?>
                  · <?= e((string) $p['nota']) ?>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <p class="adm-nota adm-cal-camera__booking">Booking.com: <?php if ($s === null): ?>calendario non collegato.
          <?php elseif ($s['esito'] === 'sospeso'): ?>scollegato.
          <?php elseif ($s['esito'] === 'ok'): ?>letto il <?= e(dataOra((string) $s['aggiornato'])) ?>.
          <?php elseif ($s['esito'] === 'errore'): ?>non si legge (<?= e((string) $s['errore']) ?>)<?= !empty($s['aggiornato']) ? ', valgono le notti lette il ' . e(dataOra((string) $s['aggiornato'])) : '' ?>.
          <?php else: ?>da leggere.<?php endif; ?></p>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="adm-carta" id="blocca" aria-labelledby="cal-blocca" tabindex="-1">
  <div class="adm-carta__testa"><h2 id="cal-blocca">Blocca una camera</h2></div>
  <p class="adm-nota">Per una prenotazione arrivata da Booking o al telefono che il calendario non mostra ancora: da
     subito la camera non si prenota più dal sito, per quelle notti. Quando la stessa prenotazione arriva da Booking,
     le notti restano una volta sola, come Booking.</p>
  <p class="adm-nota">Il blocco va anche nel calendario che il sito pubblica per Booking.com, solo con le date: se Booking
     lo importa, chiude le stesse notti anche lì, di solito entro qualche ora. La nota resta qui.</p>
  <form method="post" action="<?= e(adminUrl('calendario')) ?>?mese=<?= e($primo->format('Y-m')) ?>" class="adm-modulo adm-cal-blocca" novalidate>
    <?= Csrf::field() ?>
    <input type="hidden" name="azione" value="blocca">
    <input type="hidden" name="mese" value="<?= e($primo->format('Y-m')) ?>">
    <div class="adm-campo">
      <label for="bl-camera">Camera</label>
      <select id="bl-camera" name="camera" required>
        <?php foreach ($camere as $c): ?>
          <option value="<?= e((string) $c['ref']) ?>"<?= ($valoriBlocco['camera'] ?? '') === $c['ref'] ? ' selected' : '' ?>><?= e($nomi[(string) $c['ref']]) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="adm-campo">
      <label for="bl-dal">Dal</label>
      <input type="date" id="bl-dal" name="dal" required min="<?= e($oggi) ?>" value="<?= e((string) ($valoriBlocco['dal'] ?? '')) ?>">
    </div>
    <div class="adm-campo">
      <label for="bl-al">Al (giorno di partenza)</label>
      <input type="date" id="bl-al" name="al" required min="<?= e($oggi) ?>" value="<?= e((string) ($valoriBlocco['al'] ?? '')) ?>" aria-describedby="bl-al-aiuto">
    </div>
    <div class="adm-campo adm-cal-blocca__nota">
      <label for="bl-nota">Nota (facoltativa)</label>
      <input type="text" id="bl-nota" name="nota" maxlength="200" value="<?= e((string) ($valoriBlocco['nota'] ?? '')) ?>" placeholder="per esempio: Booking, sig. Rossi">
    </div>
    <p class="adm-aiuto adm-cal-blocca__aiuto" id="bl-al-aiuto">Come una prenotazione: dal 7 al 10 blocca le notti del 7, 8 e 9.</p>
    <p class="adm-cal-blocca__invia"><button type="submit" class="adm-bottone"><?= icona('chiave', 16) ?>Blocca le date</button></p>
  </form>
</section>

<section class="adm-carta" id="blocchi" aria-labelledby="cal-blocchi">
  <div class="adm-carta__testa"><h2 id="cal-blocchi">Blocchi manuali</h2></div>
  <?php if ($blocchi === []): ?>
    <p class="adm-tenue">Nessun blocco, da oggi in poi.</p>
  <?php else: ?>
    <ul class="adm-cal-blocchi">
      <?php foreach ($blocchi as $bl): ?>
        <li data-blocco="<?= e((string) $bl['id']) ?>">
          <div>
            <strong><?= e($nomi[(string) $bl['ref']] ?? (string) $bl['ref']) ?></strong>, dal <?= e($giorno((string) $bl['arrivo'])) ?> al <?= e($giorno((string) $bl['partenza'])) ?>
            <?= !empty($bl['nota']) ? '· ' . e((string) $bl['nota']) : '' ?>
            <span class="adm-tenue">· messo da <?= e((string) ($bl['utente'] ?? '')) ?> il <?= e(dataOra((string) $bl['creato'])) ?></span>
            <?php if (!empty($bl['coperto_booking'])): ?>
              <p class="adm-cal-blocchi__coperto" data-coperto="1"><?= icona('spunta', 14) ?>Booking.com segna occupate anche queste notti: il calendario le conta una volta sola, come Booking.
                 Se il blocco era per quella prenotazione di Booking, si può togliere. Se era per una prenotazione presa al telefono, lascialo:
                 Booking potrebbe mostrare soltanto il blocco che il sito gli manda.</p>
            <?php elseif (!empty($bl['coperto'])): ?>
              <p class="adm-cal-blocchi__coperto" data-coperto="1"><?= icona('spunta', 14) ?>Queste notti sono già tutte occupate da prenotazioni dirette o di Booking.com: il calendario le conta una
                 volta sola. Se il blocco era per una di queste, si può togliere.</p>
            <?php elseif (!empty($bl['in_parte'])): ?>
              <p class="adm-nota" data-coperto="parte">Una parte di queste notti è già occupata da Booking.com o da una prenotazione diretta: il calendario la mostra così.</p>
            <?php endif; ?>
          </div>
          <form method="post" action="<?= e(adminUrl('calendario')) ?>?mese=<?= e($primo->format('Y-m')) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="azione" value="togli">
            <input type="hidden" name="mese" value="<?= e($primo->format('Y-m')) ?>">
            <input type="hidden" name="blocco" value="<?= e((string) $bl['id']) ?>">
            <button type="submit" class="adm-bottone adm-bottone--piatto">Togli<span class="adm-visually-hidden"> il blocco di <?= e($nomi[(string) $bl['ref']] ?? (string) $bl['ref']) ?> dal <?= e($giorno((string) $bl['arrivo'])) ?></span></button>
          </form>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
