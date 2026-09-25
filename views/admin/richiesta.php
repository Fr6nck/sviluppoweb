<?php
/** @var array<string,mixed> $voce */
use ArcoDelVento\Admin\Inbox;
use ArcoDelVento\Support\Csrf;

$d = (array) ($voce['dati'] ?? []);
$righe = $voce['tipo'] === 'prenotazione'
    ? [
        'Camera'   => $d['camera'] ?? '',
        'Arrivo'   => isset($d['arrivo']) ? date('d/m/Y', strtotime((string) $d['arrivo'])) : '',
        'Partenza' => isset($d['partenza']) ? date('d/m/Y', strtotime((string) $d['partenza'])) : '',
        'Notti'    => $d['notti'] ?? '',
        'Ospiti'   => $d['ospiti'] ?? '',
        'Totale indicato sul sito' => isset($d['totale']) ? '€ ' . number_format((float) $d['totale'], 2, ',', '.') : '',
        'Nome'     => $d['nome'] ?? '',
        'E-mail'   => $d['email'] ?? '',
        'Telefono' => $d['telefono'] ?? '',
        'Paese'    => $d['paese'] ?? '',
        'Lingua del sito' => strtoupper((string) ($d['lingua'] ?? '')),
        'Note'     => $d['note'] ?? '',
    ]
    : [
        'Oggetto'  => $d['oggetto'] ?? '',
        'Nome'     => $d['nome'] ?? '',
        'E-mail'   => $d['email'] ?? '',
        'Telefono' => $d['telefono'] ?? '',
        'Lingua del sito' => strtoupper((string) ($d['lingua'] ?? '')),
        'Messaggio' => $d['messaggio'] ?? '',
    ];
$pagamento = is_array($d['pagamento'] ?? null) ? (array) $d['pagamento'] : null;
$etichettaPagamento = \ArcoDelVento\Payment\Pagamenti::etichetta($d);
unset($righe['Totale indicato sul sito']);
if ($pagamento !== null) {
    $righe = array_slice($righe, 0, 5, true) + [
        'Da pagare' => '€ ' . number_format((float) ($pagamento['importo'] ?? 0), 2, ',', '.'),
        'Pagamento' => (string) ($etichettaPagamento['testo'] ?? ''),
        'Pagato'    => isset($pagamento['pagato']) ? '€ ' . number_format((float) $pagamento['pagato'], 2, ',', '.') . ' il ' . dataOra((string) ($pagamento['pagato_il'] ?? '')) : '',
        'Codice della transazione SumUp' => (string) ($pagamento['codice'] ?? ''),
        'Pagine di pagamento aperte' => (string) count((array) ($pagamento['tentativi'] ?? [])),
    ] + array_slice($righe, 5, null, true);
} elseif (isset($d['totale'])) {
    $righe = array_slice($righe, 0, 5, true) + ['Totale indicato sul sito' => '€ ' . number_format((float) $d['totale'], 2, ',', '.')] + array_slice($righe, 5, null, true);
}
$righe = array_filter($righe, static fn ($v, $k): bool => !in_array($k, ['Telefono', 'Paese', 'Note'], true) || ($v !== '' && $v !== null) || $pagamento === null, ARRAY_FILTER_USE_BOTH);
$telefono = preg_replace('/[^\d+]/', '', (string) ($d['telefono'] ?? ''));
?>
<p class="adm-intro">
  <?= $voce['tipo'] === 'prenotazione' ? 'Richiesta di prenotazione' : 'Messaggio dal modulo contatti' ?>
  arrivata il <?= e(dataOra((string) $voce['ricevuta'])) ?>.
  <span class="adm-stato adm-stato--<?= e((string) $voce['stato']) ?>"><?= e(Inbox::STATI[$voce['stato']] ?? $voce['stato']) ?></span>
</p>

<dl class="adm-dati">
  <?php foreach ($righe as $etichetta => $valore): ?>
    <div>
      <dt><?= e($etichetta) ?></dt>
      <dd><?= ($valore === '' || $valore === null) ? '<span class="adm-tenue">—</span>' : nl2br(e((string) $valore)) ?></dd>
    </div>
  <?php endforeach; ?>
</dl>

<p class="adm-azioni">
  <?php if (!empty($d['email'])): ?>
    <a class="adm-bottone" href="mailto:<?= e((string) $d['email']) ?>?subject=<?= rawurlencode('Arco del Vento — ' . $voce['id']) ?>">Rispondi per e-mail</a>
  <?php endif; ?>
  <?php if ($telefono !== ''): ?>
    <a class="adm-bottone adm-bottone--piatto" href="https://wa.me/<?= e(ltrim($telefono, '+')) ?>" rel="noopener">WhatsApp</a>
    <a class="adm-bottone adm-bottone--piatto" href="tel:<?= e($telefono) ?>">Chiama</a>
  <?php endif; ?>
</p>

<?php if ($pagamento !== null): ?>
  <div class="adm-avviso adm-avviso--<?= ($etichettaPagamento['tono'] ?? '') === 'ok' ? 'ok' : 'attenzione' ?>">
    <?= icona(($etichettaPagamento['tono'] ?? '') === 'ok' ? 'spunta' : 'attenzione', 18, 'adm-avviso__icona') ?>
    <div>
      <?php if (($pagamento['stato'] ?? '') === 'pagato'): ?>
        <p><strong>Pagata su SumUp.</strong> Il calendario del sito non conosce le prenotazioni di Booking: controlla che
           le date siano libere. Se non lo sono, il rimborso si fa dal pannello di SumUp; poi scrivi all'ospite.</p>
      <?php elseif (($pagamento['stato'] ?? '') === 'da controllare'): ?>
        <p><strong>SumUp dice pagata, ma l'importo non coincide.</strong> Guarda la transazione nel pannello di SumUp prima di confermare.</p>
      <?php else: ?>
        <p><strong>Non ancora pagata.</strong> L'ospite ha aperto la pagina di SumUp ma il pagamento non risulta. Non è una prenotazione:
           le date restano libere. Se credi che abbia pagato, controlla qui sotto.</p>
      <?php endif; ?>
      <?php if (!empty($pagamentoAttivo) && !in_array($pagamento['stato'] ?? '', ['pagato'], true)): ?>
        <form method="post" action="<?= e(adminUrl('richieste/' . rawurlencode((string) $voce['id']))) ?>" class="adm-spazio">
          <?= Csrf::field() ?>
          <input type="hidden" name="azione" value="verifica">
          <button type="submit" class="adm-bottone adm-bottone--piatto"><?= icona('ripristina', 16) ?>Controlla con SumUp</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
<?php elseif ($voce['tipo'] === 'prenotazione'): ?>
  <p class="adm-nota">Prima di confermare, controlla che le date siano libere su Booking: il calendario del sito è ancora dimostrativo.</p>
<?php endif; ?>

<form method="post" action="<?= e(adminUrl('richieste/' . rawurlencode((string) $voce['id']))) ?>" class="adm-modulo adm-in-linea">
  <?= Csrf::field() ?>
  <input type="hidden" name="azione" value="stato">
  <label for="stato">Stato</label>
  <select id="stato" name="stato">
    <?php foreach (Inbox::STATI as $chiave => $nome): ?>
      <option value="<?= e($chiave) ?>"<?= $voce['stato'] === $chiave ? ' selected' : '' ?>><?= e($nome) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="adm-bottone">Aggiorna</button>
</form>

<details class="adm-pericolo">
  <summary>Elimina la richiesta</summary>
  <form method="post" action="<?= e(adminUrl('richieste/' . rawurlencode((string) $voce['id']))) ?>" class="adm-modulo">
    <?= Csrf::field() ?>
    <input type="hidden" name="azione" value="elimina">
    <p>Si cancella davvero, senza storico. L'e-mail arrivata a suo tempo resta nella posta.</p>
    <div class="adm-campo adm-campo--spunta">
      <input type="checkbox" id="conferma" name="conferma" value="1">
      <label for="conferma">Sì, eliminala</label>
    </div>
    <button type="submit" class="adm-bottone adm-bottone--pericolo">Elimina</button>
  </form>
</details>

<p><a class="adm-link" href="<?= e(adminUrl('richieste')) ?>">← Tutte le richieste</a></p>
