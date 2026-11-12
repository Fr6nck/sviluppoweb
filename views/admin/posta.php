<?php
/**
 * A chi arrivano le richieste.
 *
 * Si cambiano solo gli indirizzi che ricevono. La casella che spedisce, con
 * la sua password, resta nel .env: qui si vede quale è, la password mai.
 *
 * @var list<string> $scelti     gli indirizzi scritti nel pannello (o quelli appena inviati, con un errore)
 * @var list<string> $effettivi  a chi arrivano adesso
 * @var list<string> $dalFile    MAIL_TO_ADDRESS del .env
 * @var string $trasporto  log | mail | smtp
 * @var string $mittente
 * @var string $smtpHost
 * @var string $smtpUtente
 * @var int    $smtpPorta
 * @var string $smtpCifratura
 * @var bool   $smtpPassword  se la password c'è (il valore non arriva mai qui)
 * @var int    $massimo
 */
use ArcoDelVento\Support\Csrf;

$dalPannello = array_filter($scelti, static fn (string $v): bool => $v !== '') !== [];

// Quello che nella casella che spedisce si vede già storto, prima di provare.
$storto = [];
if ($trasporto === 'smtp') {
    if ($smtpHost === '') {
        $storto[] = 'MAIL_SMTP_HOST è vuoto: manca il server di posta (su Hostinger: smtp.hostinger.com).';
    }
    if ($smtpUtente === '' && !$smtpPassword) {
        $storto[] = 'Utente SMTP (MAIL_SMTP_USER) e password della casella (MAIL_SMTP_PASSWORD) sono vuoti: '
                  . 'senza accesso Hostinger non spedisce («530 Authentication required»). Scrivi l\'indirizzo della casella e la sua password.';
    } elseif ($smtpUtente !== '' && strcasecmp($smtpUtente, $mittente) !== 0) {
        $storto[] = 'Il mittente (' . $mittente . ') non è la casella con cui il sito accede (' . $smtpUtente . '): '
                  . 'Hostinger lo rifiuta. Scrivi lo stesso indirizzo in MAIL_FROM_ADDRESS e MAIL_SMTP_USER.';
    }
    if (!$smtpPassword && $smtpUtente !== '') {
        $storto[] = 'MAIL_SMTP_PASSWORD è vuota: serve la password della casella.';
    }
    // Porta e cifratura: per la 465 e la 587 la sceglie il sito (SmtpMailer::cifratura), lo dice la riga «Spedisce».
    if (\ArcoDelVento\Mail\SmtpMailer::cifratura($smtpPorta, $smtpCifratura) === '' && $smtpHost !== '' && !in_array($smtpHost, ['127.0.0.1', 'localhost'], true)) {
        $storto[] = 'La posta parte senza cifratura: la password viaggerebbe in chiaro. Usa la porta 465 oppure la 587.';
    }
}
// Con l'utente vuoto e la password scritta, per l'accesso si usa il mittente (SmtpMailer).
$accesso = $smtpUtente !== '' ? $smtpUtente : ($smtpPassword ? $mittente : '');
$comeSpedisce = match ($trasporto) {
    'smtp'  => 'SMTP' . ($smtpHost !== '' ? ' su ' . $smtpHost : '')
        . ', porta ' . $smtpPorta . match (\ArcoDelVento\Mail\SmtpMailer::cifratura($smtpPorta, $smtpCifratura)) {
            'ssl' => ' cifrata dall\'inizio (ssl)', 'tls' => ' cifrata dopo il saluto (tls)', default => ' senza cifratura' }
        . (\ArcoDelVento\Mail\SmtpMailer::cifratura($smtpPorta, $smtpCifratura) !== $smtpCifratura && in_array($smtpPorta, [465, 587], true)
            ? ' — scelta dalla porta, anche se in Configurazione c\'è «' . ($smtpCifratura !== '' ? $smtpCifratura : 'nessuna') . '»' : '')
        . ($accesso !== ''
        ? ', con l\'accesso della casella ' . $accesso . ($smtpUtente === '' ? ' (l\'utente SMTP è vuoto: si usa il mittente)' : '')
        : ', senza accesso: utente e password della casella sono vuoti'),
    'mail'  => 'con la funzione mail() di PHP, senza casella autenticata',
    default => 'non spedisce: i messaggi restano scritti in storage/mail/ (è il modo della copia di prova)',
};
?>
<p class="adm-intro">Qui scegli chi riceve le richieste di prenotazione e i messaggi del modulo contatti.
   La casella che spedisce e la sua password restano nel file <code>.env</code> sul server: da qui non si vedono
   e non si cambiano.</p>

<div class="adm-due">
  <section class="adm-carta" aria-labelledby="p-chi">
    <div class="adm-carta__testa">
      <h2 id="p-chi">Chi riceve</h2>
      <span class="adm-etichetta"><?= $dalPannello ? 'scelti qui' : 'dal file .env' ?></span>
    </div>
    <form method="post" action="<?= e(adminUrl('posta')) ?>" class="adm-modulo" novalidate>
      <?= Csrf::field() ?>
      <input type="hidden" name="azione" value="salva">
      <?php for ($i = 0; $i < $massimo; $i++): ?>
        <div class="adm-campo">
          <label for="destinatario-<?= $i ?>">Indirizzo <?= $i + 1 ?><?= $i === 0 ? '' : ' (facoltativo)' ?></label>
          <input type="email" id="destinatario-<?= $i ?>" name="destinatari[<?= $i ?>]" autocomplete="off" spellcheck="false"
                 value="<?= e((string) ($scelti[$i] ?? '')) ?>"
                 <?= $i === 0 && $dalFile !== [] ? 'placeholder="' . e($dalFile[0]) . '"' : '' ?>>
        </div>
      <?php endfor; ?>
      <p class="adm-aiuto">Ogni richiesta arriva a tutti gli indirizzi scritti. Lasciali tutti vuoti per tornare a
         quello del file <code>.env</code><?= $dalFile !== [] ? ' (' . e(implode(', ', $dalFile)) . ')' : '' ?>.</p>
      <p><button type="submit" class="adm-bottone">Salva gli indirizzi</button></p>
    </form>
  </section>

  <section class="adm-carta" aria-labelledby="p-come">
    <div class="adm-carta__testa">
      <h2 id="p-come">Com'è impostata la posta</h2>
    </div>
    <dl class="adm-dati adm-dati--piatta">
      <div>
        <dt>Adesso ricevono</dt>
        <dd><?= $effettivi !== [] ? e(implode(', ', $effettivi)) : '<strong>nessuno</strong>: scrivi un indirizzo qui accanto' ?></dd>
      </div>
      <div>
        <dt>Spedisce</dt>
        <dd><?= e($comeSpedisce) ?></dd>
      </div>
      <div>
        <dt>Mittente</dt>
        <dd><?= e($mittente) ?></dd>
      </div>
    </dl>
    <?php if ($storto !== []): ?>
      <div class="adm-avviso adm-avviso--attenzione" role="note" data-posta-storta>
        <?= icona('attenzione', 18, 'adm-avviso__icona') ?>
        <div>
          <p><strong>Nella casella che spedisce c'è qualcosa che non va:</strong></p>
          <ul>
            <?php foreach ($storto as $riga): ?><li><?= e($riga) ?></li><?php endforeach; ?>
          </ul>
          <p>Si corregge in <a href="<?= e(adminUrl('configurazione')) ?>">Configurazione</a>, gruppo della posta.</p>
        </div>
      </div>
    <?php endif; ?>
    <form method="post" action="<?= e(adminUrl('posta')) ?>" class="adm-modulo">
      <?= Csrf::field() ?>
      <input type="hidden" name="azione" value="prova">
      <button type="submit" class="adm-bottone adm-bottone--piatto"<?= $effettivi === [] ? ' disabled' : '' ?>>
        <?= icona('posta', 17) ?>Manda un messaggio di prova
      </button>
      <p class="adm-aiuto">Arriva agli indirizzi di adesso. Se non parte, l'avviso dice dove si ferma e che cosa risponde
         il server di posta. Se non arriva, il problema è nella casella che spedisce:
         nel <code>.env</code> controlla <code>MAIL_SMTP_USER</code> e <code>MAIL_SMTP_PASSWORD</code>
         (i passi sono nella guida, «La posta»).</p>
    </form>
  </section>
</div>
