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

<?php if ($voce['tipo'] === 'prenotazione'): ?>
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
