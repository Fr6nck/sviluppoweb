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
 * @var int    $massimo
 */
use ArcoDelVento\Support\Csrf;

$dalPannello = array_filter($scelti, static fn (string $v): bool => $v !== '') !== [];
$comeSpedisce = match ($trasporto) {
    'smtp'  => 'SMTP, dalla casella ' . ($smtpUtente !== '' ? $smtpUtente : $mittente) . ($smtpHost !== '' ? ' su ' . $smtpHost : ''),
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
    <form method="post" action="<?= e(adminUrl('posta')) ?>" class="adm-modulo">
      <?= Csrf::field() ?>
      <input type="hidden" name="azione" value="prova">
      <button type="submit" class="adm-bottone adm-bottone--piatto"<?= $effettivi === [] ? ' disabled' : '' ?>>
        <?= icona('posta', 17) ?>Manda un messaggio di prova
      </button>
      <p class="adm-aiuto">Arriva agli indirizzi di adesso. Se non arriva, il problema è nella casella che spedisce:
         nel <code>.env</code> controlla <code>MAIL_SMTP_USER</code> e <code>MAIL_SMTP_PASSWORD</code>
         (i passi sono nella guida, «La posta»).</p>
    </form>
  </section>
</div>
