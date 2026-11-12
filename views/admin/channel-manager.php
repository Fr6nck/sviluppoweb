<?php
/**
 * L'interruttore del channel manager — FUNZIONE DI PROVA, solo per il super
 * admin. Vedi Admin\InterruttoreChannelManager: è isolata per toglierla
 * quando non serve più.
 *
 * @var bool        $collegato
 * @var string|null $scollegatoDal
 * @var list<array{ref: string, nome: string, url: string, stato: array<string,mixed>|null}> $camere
 * @var list<array<string,mixed>> $restano  prenotazioni dirette e blocchi manuali, che lo scollegamento non tocca
 * @var array<string,string> $nomi  ref => nome della camera
 */
use ArcoDelVento\Support\Csrf;

$giorno = static fn (string $iso): string => date('d/m/Y', strtotime($iso) ?: 0);
$fonti  = ['sito' => 'Prenotazione diretta', 'manuale' => 'Blocco manuale'];
?>
<p class="adm-intro">Funzione di prova. Scollega il calendario iCal di Booking.com da tutte le camere: le notti
   importate da Booking si tolgono e la disponibilità torna quella del solo sito, come se su Booking nessuno avesse
   prenotato. I link iCal restano salvati nelle camere: ricollegando, si rileggono subito.</p>

<section class="adm-carta" id="cm-stato" aria-labelledby="cm-stato-titolo">
  <div class="adm-carta__testa">
    <h2 id="cm-stato-titolo">Stato</h2>
    <span class="adm-pagamento adm-pagamento--<?= $collegato ? 'ok' : 'avviso' ?>" data-stato-channel="<?= $collegato ? 'collegato' : 'scollegato' ?>"><?= $collegato ? 'Collegato' : 'Scollegato' ?></span>
  </div>
  <?php if ($collegato): ?>
    <p>Il sito legge il calendario di Booking.com di ogni camera con il link iCal, e tiene occupate le notti prenotate lì.</p>
    <form method="post" action="<?= e(adminUrl('channel-manager')) ?>" class="adm-modulo adm-modulo--stretto">
      <?= Csrf::field() ?>
      <input type="hidden" name="azione" value="scollega">
      <div class="adm-campo adm-campo--spunta">
        <input type="checkbox" id="cm-conferma" name="conferma" value="1" aria-describedby="cm-conferma-aiuto">
        <label for="cm-conferma">Ho capito: finché è scollegato, il sito vende anche le notti prenotate su Booking.com</label>
        <p class="adm-aiuto" id="cm-conferma-aiuto">Va bene su un sito di prova. Su quello vero, solo per pochi minuti.</p>
      </div>
      <p><button type="submit" class="adm-bottone adm-bottone--pericolo">Scollega da tutte le camere</button></p>
    </form>
  <?php else: ?>
    <p><strong>Scollegato<?= $scollegatoDal ? ' dal ' . e(dataOra($scollegatoDal)) : '' ?>.</strong> Le notti prenotate su
       Booking.com non contano: il sito le vende come libere. I link iCal sono ancora nelle camere.</p>
    <form method="post" action="<?= e(adminUrl('channel-manager')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="azione" value="ricollega">
      <p><button type="submit" class="adm-bottone">Ricollega e sincronizza adesso</button></p>
    </form>
  <?php endif; ?>
</section>

<section class="adm-carta" aria-labelledby="cm-camere">
  <div class="adm-carta__testa"><h2 id="cm-camere">Le camere</h2></div>
  <div class="adm-tabella-scorre">
    <table class="adm-tabella adm-tabella--righe">
      <caption class="adm-visually-hidden">Il calendario di Booking.com di ogni camera</caption>
      <thead><tr><th scope="col">Camera</th><th scope="col">Link iCal</th><th scope="col">Lettura</th><th scope="col">Periodi di Booking</th></tr></thead>
      <tbody>
        <?php foreach ($camere as $c): $s = $c['stato']; ?>
          <tr data-camera="<?= e($c['ref']) ?>">
            <th scope="row"><a href="<?= e(adminUrl('camere/' . rawurlencode($c['ref']))) ?>#c-ical"><?= e($c['nome']) ?></a></th>
            <td><?= $c['url'] !== '' ? 'salvato' : '<span class="adm-tenue">nessuno</span>' ?></td>
            <td><?php if ($s === null): ?><span class="adm-tenue">—</span>
              <?php elseif ($s['esito'] === 'sospeso'): ?>sospesa
              <?php elseif ($s['esito'] === 'ok'): ?>letto il <?= e(dataOra((string) $s['aggiornato'])) ?>
              <?php elseif ($s['esito'] === 'errore'): ?>errore: <?= e((string) $s['errore']) ?>
              <?php else: ?>da leggere<?php endif; ?></td>
            <td data-periodi="<?= $s === null ? 0 : count((array) $s['periodi']) ?>"><?= $s === null ? '<span class="adm-tenue">—</span>' : count((array) $s['periodi']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="adm-carta" aria-labelledby="cm-restano">
  <div class="adm-carta__testa"><h2 id="cm-restano">Non toccate dallo scollegamento</h2></div>
  <p class="adm-nota">Prenotazioni dirette e blocchi manuali, da oggi in poi. Scollegare il channel manager non le
     cancella mai: se vanno tolte, lo decide una persona, dalla richiesta o dal Calendario.</p>
  <?php if ($restano === []): ?>
    <p data-restano="0">Nessuna prenotazione diretta e nessun blocco manuale.</p>
  <?php else: ?>
    <ul class="adm-elenco" data-restano="<?= count($restano) ?>">
      <?php foreach ($restano as $r): ?>
        <li>
          <strong><?= e($fonti[$r['fonte']] ?? (string) $r['fonte']) ?></strong> ·
          <?= e($nomi[$r['ref']] ?? (string) $r['ref']) ?>, dal <?= e($giorno((string) $r['arrivo'])) ?> al <?= e($giorno((string) $r['partenza'])) ?>
          <?php if (($r['fonte'] ?? '') === 'sito'): ?>
            · <a class="adm-link" href="<?= e(adminUrl('richieste/' . rawurlencode((string) $r['id']))) ?>"><?= e((string) $r['id']) ?></a>
          <?php elseif (!empty($r['nota'])): ?>
            · <?= e((string) $r['nota']) ?>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
