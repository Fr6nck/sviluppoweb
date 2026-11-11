<?php
/**
 * @var array<string,mixed> $camera
 * @var list<array<string,mixed>> $schema
 * @var array<string,mixed> $valori
 * @var list<string> $lingue
 * @var array{stato: array<string,mixed>|null, esporta: string} $ical  il calendario di Booking.com
 */
use ArcoDelVento\Admin\Form;
use ArcoDelVento\Support\Csrf;
use ArcoDelVento\Support\RoomPresenter as R;
?>
<?php
$capienza = (int) ($camera['occupancy']['capacity'] ?? $camera['occupancy']['max']);
$inVendita = (int) $camera['occupancy']['max'];
?>
<p class="adm-intro">
  <?= e(R::typeLabel($camera)) ?> · <?= e(R::beds($camera)) ?> · fino a <?= $capienza ?>
  <?= $capienza === 1 ? 'ospite' : 'ospiti' ?>.
  <?php if ($inVendita < $capienza): ?>
    <strong>Sul sito si vende fino a <?= $inVendita ?> <?= $inVendita === 1 ? 'ospite' : 'ospiti' ?>:</strong>
    per aprirla a <?= $capienza ?>, scrivi la tariffa per <?= $capienza ?> qui sotto.
  <?php endif; ?>
  Un campo «Vuoto» sul sito non compare, o diventa «da confermare».
  <a class="adm-link" href="<?= e(url('room', ['slug' => $camera['slug']['it']], [], 'it')) ?>" target="_blank" rel="noopener">Vedi la pagina ↗</a>
</p>

<?php $foto = immagine('camera.' . $camera['ref']); $mini = anteprima($foto, 'foto'); ?>
<section class="adm-carta adm-camera-foto" aria-labelledby="c-foto">
  <?php if ($mini !== null): ?>
    <img class="adm-camera-foto__img" src="<?= e(asset($mini)) ?>" alt="" width="160" height="120">
  <?php endif; ?>
  <div>
    <h2 id="c-foto">Le fotografie</h2>
    <p class="adm-nota"><?= !empty($camera['photographed']) ? 'La foto principale e, se vuoi, due per la galleria.' : 'Questa camera non ha ancora una foto vera: sul sito compare un disegno.' ?>
      Il campo «Che cosa si vede nella fotografia», qui sotto, è quello che leggono i lettori di schermo: se cambi la foto, controllalo.</p>
    <p class="adm-azioni">
      <a class="adm-bottone adm-bottone--piatto" href="<?= e(adminUrl('immagini/camera.' . $camera['ref'])) ?>"><?= icona('immagine', 17) ?><?= !empty($camera['photographed']) ? 'Cambia la foto' : 'Carica la foto' ?></a>
      <a class="adm-link" href="<?= e(adminUrl('immagini/camera.' . $camera['ref'] . '.galleria-1')) ?>">Galleria 1</a>
      <a class="adm-link" href="<?= e(adminUrl('immagini/camera.' . $camera['ref'] . '.galleria-2')) ?>">Galleria 2</a>
    </p>
  </div>
</section>

<?php
/* Il calendario di Booking.com: quello che il sito legge (il link si scrive
   nel modulo qui sotto) e quello che il sito pubblica per Booking. */
$statoIcal = $ical['stato'] ?? null;
$quandoIcal = static fn (?string $iso): string => $iso ? dataOra($iso) : '—';
$giorno = static fn (string $iso): string => date('d/m/Y', strtotime($iso) ?: 0);
?>
<section class="adm-carta" id="c-ical" aria-labelledby="c-ical-titolo" tabindex="-1">
  <div class="adm-carta__testa">
    <h2 id="c-ical-titolo">Calendario di Booking.com</h2>
    <?php if ($statoIcal !== null): ?>
      <span class="adm-etichetta"><?= $statoIcal['esito'] === 'ok' ? 'collegato' : ($statoIcal['esito'] === 'errore' ? 'errore' : 'da leggere') ?></span>
    <?php endif; ?>
  </div>
  <?php if ($statoIcal === null): ?>
    <p>Non collegato: il sito non sa quali notti sono prenotate su Booking. Incolla il link iCal di Booking.com nel
       campo «Calendario di Booking.com» qui sotto e salva.</p>
  <?php else: ?>
    <dl class="adm-dati adm-dati--piatta">
      <div><dt>Letto l'ultima volta</dt><dd><?= e($quandoIcal($statoIcal['aggiornato'])) ?><?= $statoIcal['esito'] === 'errore' ? ' · <strong>ultimo tentativo fallito</strong> (' . e($quandoIcal($statoIcal['tentato'])) . '): ' . e((string) $statoIcal['errore']) : '' ?></dd></div>
      <div><dt>Notti occupate su Booking</dt>
        <dd><?php if ($statoIcal['periodi'] === []): ?>nessuna, da oggi in poi<?php else: ?>
          <?= count($statoIcal['periodi']) ?> <?= count($statoIcal['periodi']) === 1 ? 'periodo' : 'periodi' ?>:
          <?= e(implode(', ', array_map(static fn (array $p): string => $giorno($p['arrivo']) . '–' . $giorno($p['partenza']), array_slice($statoIcal['periodi'], 0, 6)))) ?><?= count($statoIcal['periodi']) > 6 ? '…' : '' ?>
        <?php endif; ?></dd></div>
    </dl>
    <p class="adm-nota">Si rilegge da solo ogni <?= (int) (\ArcoDelVento\Booking\CalendariEsterni::OGNI / 60) ?> minuti, quando qualcuno cerca una data sul sito.
       Una prenotazione fatta su Booking può restare prenotabile qui per quei minuti: per le ultime ore conta sempre Booking.</p>
    <form method="post" action="<?= e(adminUrl('camere/' . rawurlencode((string) $camera['ref']))) ?>" class="adm-in-linea">
      <?= Csrf::field() ?>
      <input type="hidden" name="azione" value="ical-aggiorna">
      <button type="submit" class="adm-bottone adm-bottone--piatto"><?= icona('ripristina', 16) ?>Aggiorna adesso</button>
    </form>
  <?php endif; ?>
  <h3 class="adm-sottotitolo-carta">Il calendario del sito, per Booking.com</h3>
  <p>Su Booking.com, in Sincronizza calendari → <strong>Importa calendario</strong>, incolla questo link: Booking terrà chiuse le
     notti prenotate o confermate qui. Il link è segreto: non pubblicarlo.</p>
  <p><code class="adm-codice"><?= e((string) $ical['esporta']) ?></code></p>
</section>

<form method="post" action="<?= e(adminUrl('camere/' . rawurlencode((string) $camera['ref']))) ?>" class="adm-modulo adm-gruppo" novalidate>
  <?= Csrf::field() ?>
  <?php foreach ($schema as $campo): ?>
    <?= $this->render('admin/_campo', [
        'campo'  => $campo,
        'valore' => Form::get($valori, $campo['path']),
        'lingue' => $lingue,
        'nome'   => 'f[' . $campo['path'] . ']',
    ]) ?>
  <?php endforeach; ?>
  <div class="adm-salva">
    <button type="submit" class="adm-bottone">Salva la camera</button>
    <span class="adm-nota">Le tariffe valgono subito anche per le prenotazioni dal sito.</span>
  </div>
</form>

<p><a class="adm-link" href="<?= e(adminUrl('camere')) ?>">← Tutte le camere</a></p>
