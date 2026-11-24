<?php
/** Impostazioni: email, conservazione delle richieste, manutenzione, anteprima, anti-spam. Solo Amministratore. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

$me = require_role('admin');
$errors = [];

if (is_post()) {
    csrf_check();
    $action = post('azione');

    if ($action === 'prova') {
        $to = (string) $me['email'];
        $err = send_mail([$to], 'Email di prova dal pannello ChiMangia', "Ciao {$me['name']},\n\nquesta è un'email di prova inviata dal pannello di ChiMangia il " . date('d/m/Y \a\l\l\e H:i') . ".\nSe la stai leggendo, l'invio delle email funziona.\n");
        audit('email_prova', $err === null ? 'Inviata a ' . $to : 'Errore: ' . $err);
        flash($err === null ? 'ok' : 'error', $err === null ? 'Email di prova inviata a ' . $to . '. Controlla la posta (anche lo spam).' : 'Invio non riuscito: ' . $err);
        redirect('impostazioni.php');
    }

    if ($action === 'nuovo_link') {
        setting_set('preview_token', random_token(24));
        audit('impostazioni', 'Nuovo link di anteprima');
        flash('ok', 'Creato un nuovo link di anteprima: quello precedente non funziona più.');
        redirect('impostazioni.php');
    }

    if ($action === 'pulisci') {
        $n = leads_retention_cleanup(true);
        flash('ok', $n === 1 ? 'Eliminata 1 richiesta chiusa.' : 'Eliminate ' . $n . ' richieste chiuse.');
        redirect('impostazioni.php');
    }

    if ($action === 'salva') {
        $transport = post('mail_transport') === 'smtp' ? 'smtp' : 'mail';
        $from = trim(post('mail_from_email'));
        if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $errors['mail_from_email'] = 'Indirizzo non valido.';
        }
        $recipients = [];
        foreach (preg_split('/[\s,;]+/', post('notify_recipients')) ?: [] as $r) {
            if ($r === '') {
                continue;
            }
            if (!filter_var($r, FILTER_VALIDATE_EMAIL)) {
                $errors['notify_recipients'] = 'Questo indirizzo non è valido: ' . $r;
            }
            $recipients[] = $r;
        }
        $port = (int) post('smtp_port');
        $enc = in_array(post('smtp_encryption'), ['tls', 'ssl', 'none'], true) ? post('smtp_encryption') : 'tls';
        if ($transport === 'smtp') {
            if (post('smtp_host') === '' || !preg_match('/^[a-z0-9.-]+$/i', post('smtp_host'))) {
                $errors['smtp_host'] = 'Indica il server SMTP (es. smtp.hostinger.com).';
            }
            if ($port < 1 || $port > 65535) {
                $errors['smtp_port'] = 'Porta non valida (di solito 465 o 587).';
            }
        }
        $months = (int) post('retention_months');
        if ($months < 0 || $months > 120) {
            $errors['retention_months'] = 'Scrivi un numero di mesi tra 0 e 120.';
        }
        $minSec = max(0, min(60, (int) post('contact_min_seconds')));
        $maxHour = max(1, min(100, (int) post('contact_max_per_hour')));
        $maint = post('maintenance') === '1';
        $maintMsg = clean_text(post('maintenance_message'), 300);

        if (!$errors) {
            setting_set('mail_transport', $transport);
            setting_set('mail_from_email', $from);
            setting_set('mail_from_name', clean_text(post('mail_from_name'), 80));
            setting_set('notify_recipients', implode("\n", $recipients));
            setting_set('smtp_host', strtolower(post('smtp_host')));
            setting_set('smtp_port', $port ?: 587);
            setting_set('smtp_encryption', $enc);
            setting_set('smtp_user', clean_text(post('smtp_user'), 190));
            $newPass = (string) ($_POST['smtp_pass'] ?? '');
            if (post('smtp_pass_clear') === '1') {
                setting_set('smtp_pass', '');
            } elseif ($newPass !== '') {
                setting_set('smtp_pass', secret_encrypt($newPass)); // cifrata, mai più mostrata
            }
            setting_set('retention_months', $months);
            setting_set('contact_min_seconds', $minSec);
            setting_set('contact_max_per_hour', $maxHour);
            setting_set('maintenance_message', $maintMsg);
            $wasMaint = (bool) setting('maintenance', false);
            setting_set('maintenance', $maint);
            // Il sito pubblico legge solo questo file, senza interrogare il database.
            if ($maint) {
                write_file_atomic(maintenance_flag(), $maintMsg);
            } elseif (is_file(maintenance_flag())) {
                @unlink(maintenance_flag());
            }
            audit('impostazioni', 'Email: ' . $transport . '; conservazione: ' . $months . ' mesi' . ($wasMaint !== $maint ? '; manutenzione ' . ($maint ? 'attivata' : 'disattivata') : ''));
            flash('ok', 'Impostazioni salvate.');
            redirect('impostazioni.php');
        }
        flash('error', 'Controlla i campi evidenziati.');
    }
}

$s = static fn (string $k, mixed $d = '') => is_post() && post('azione') === 'salva' && $k !== 'smtp_pass' ? post($k, (string) $d) : setting($k, $d);
$hasPass = (string) setting('smtp_pass', '') !== '';
$previewLink = site_url('anteprima.php?t=' . rawurlencode((string) setting('preview_token', '')));

admin_header('Email e impostazioni', 'impostazioni.php');
?>
<form method="post" action="impostazioni.php" class="form" novalidate data-dirty-check>
  <?= csrf_field() ?>
  <input type="hidden" name="azione" value="salva">

  <fieldset>
    <legend>Invio delle email</legend>
    <?= field('mail_transport', 'Metodo di invio', $s('mail_transport', 'mail'), ['type' => 'select', 'options' => ['mail' => 'Funzione mail() del server', 'smtp' => 'SMTP (consigliato)'],
        'hint' => 'Su Hostinger: crea una casella email in hPanel e usa SMTP con server smtp.hostinger.com, porta 465, cifratura SSL.']) ?>
    <div class="grid-2">
      <?= field('mail_from_email', 'Indirizzo mittente', $s('mail_from_email'), ['type' => 'email', 'error' => $errors['mail_from_email'] ?? '', 'hint' => 'Deve essere una casella del tuo dominio. Vuoto: noreply@dominio.']) ?>
      <?= field('mail_from_name', 'Nome mittente', $s('mail_from_name', 'ChiMangia')) ?>
    </div>
    <?= field('notify_recipients', 'Destinatari delle notifiche delle richieste', $s('notify_recipients'), ['type' => 'textarea', 'rows' => 3, 'error' => $errors['notify_recipients'] ?? '', 'hint' => 'Un indirizzo per riga.']) ?>
  </fieldset>

  <fieldset>
    <legend>Server SMTP</legend>
    <div class="grid-3">
      <?= field('smtp_host', 'Server (host)', $s('smtp_host'), ['error' => $errors['smtp_host'] ?? '']) ?>
      <?= field('smtp_port', 'Porta', (string) $s('smtp_port', 587), ['type' => 'number', 'attrs' => 'min="1" max="65535"', 'error' => $errors['smtp_port'] ?? '']) ?>
      <?= field('smtp_encryption', 'Cifratura', $s('smtp_encryption', 'tls'), ['type' => 'select', 'options' => ['ssl' => 'SSL (porta 465)', 'tls' => 'STARTTLS (porta 587)', 'none' => 'Nessuna']]) ?>
    </div>
    <div class="grid-2">
      <?= field('smtp_user', 'Utente', $s('smtp_user'), ['attrs' => 'autocomplete="off"']) ?>
      <?= field('smtp_pass', 'Password', '', ['type' => 'password', 'attrs' => 'autocomplete="new-password"',
          'hint' => $hasPass ? 'Una password è già salvata (cifrata) e non viene mostrata. Scrivine una nuova solo per sostituirla.' : 'Nessuna password salvata.']) ?>
    </div>
    <?php if ($hasPass): ?><?= field('smtp_pass_clear', 'Cancella la password salvata', false, ['type' => 'checkbox']) ?><?php endif; ?>
  </fieldset>

  <fieldset>
    <legend>Richieste e privacy</legend>
    <?= field('retention_months', 'Mesi di conservazione delle richieste chiuse', (string) $s('retention_months', 12), ['type' => 'number', 'attrs' => 'min="0" max="120"',
        'error' => $errors['retention_months'] ?? '', 'hint' => 'Le richieste con stato "Chiusa" più vecchie vengono cancellate in automatico. 0 = mai.']) ?>
    <div class="grid-2">
      <?= field('contact_min_seconds', 'Tempo minimo di compilazione del modulo (secondi)', (string) $s('contact_min_seconds', 4), ['type' => 'number', 'attrs' => 'min="0" max="60"']) ?>
      <?= field('contact_max_per_hour', 'Invii massimi per ora dallo stesso IP', (string) $s('contact_max_per_hour', 5), ['type' => 'number', 'attrs' => 'min="1" max="100"']) ?>
    </div>
  </fieldset>

  <fieldset>
    <legend>Modalità manutenzione</legend>
    <?= field('maintenance', 'Attiva la manutenzione', (bool) $s('maintenance', false), ['type' => 'switch', 'hint' => 'Il pubblico vede una pagina d\'attesa; chi è collegato al pannello vede il sito.']) ?>
    <?= field('maintenance_message', 'Messaggio della pagina d\'attesa', $s('maintenance_message'), ['type' => 'textarea', 'rows' => 2]) ?>
  </fieldset>

  <div class="actions-bar"><button type="submit" class="btn btn-dark">Salva le impostazioni</button></div>
</form>

<section class="panel" aria-labelledby="h-test">
  <h2 id="h-test">Email di prova</h2>
  <p>Invia un messaggio di prova a <strong><?= e($me['email']) ?></strong> con le impostazioni salvate.</p>
  <form method="post" action="impostazioni.php"><?= csrf_field() ?><input type="hidden" name="azione" value="prova">
    <button type="submit" class="btn btn-line">Invia email di prova</button></form>
</section>

<section class="panel" aria-labelledby="h-prev">
  <h2 id="h-prev">Link riservato per l'anteprima</h2>
  <p>Chi ha questo link vede la bozza anche senza accedere al pannello (la pagina non viene indicizzata).</p>
  <p><a href="<?= e($previewLink) ?>" target="_blank" rel="noopener"><code><?= e($previewLink) ?></code></a></p>
  <form method="post" action="impostazioni.php"><?= csrf_field() ?><input type="hidden" name="azione" value="nuovo_link">
    <button type="submit" class="btn btn-line" data-confirm="Il link attuale smetterà di funzionare. Continuare?">Crea un nuovo link</button></form>
</section>

<section class="panel" aria-labelledby="h-clean">
  <h2 id="h-clean">Pulizia delle richieste</h2>
  <p>La pulizia parte da sola una volta al giorno. Puoi avviarla subito.</p>
  <form method="post" action="impostazioni.php"><?= csrf_field() ?><input type="hidden" name="azione" value="pulisci">
    <button type="submit" class="btn btn-line">Elimina ora le richieste chiuse scadute</button></form>
</section>
<?php
admin_footer();
