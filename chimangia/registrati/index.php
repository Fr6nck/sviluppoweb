<?php
/**
 * Registrazione di un nuovo locale: crea il locale in prova gratuita e il suo titolare.
 * Protezioni: CAPTCHA senza servizi esterni, campo esca, tempo minimo, limite per connessione.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
pf_boot('staff', '../', true);

if (staff_current()) {
    redirect('../gestione/');
}

$trialDays = (int) setting('pf_trial_days', PF_TRIAL_DAYS);
$open = (bool) setting('pf_signup_open', true);
$d = ['venue' => '', 'kind' => 'ristorante', 'name' => '', 'email' => '', 'phone' => '', 'hotel' => false];
$error = '';
$captchaError = false;

if ($open && is_post()) {
    $d = [
        'venue' => clean_text(post('venue'), 160), 'kind' => post('kind'), 'name' => clean_text(post('name'), 120),
        'email' => mb_strtolower(post('email')), 'phone' => phone_normalize(post('phone')), 'hotel' => post('hotel') === '1',
    ];
    $password = (string) ($_POST['password'] ?? '');
    if ($e = spam_guard_check('signup', 5)) {
        $error = $e;
    } elseif (mb_strlen($d['venue']) < 2) {
        $error = 'Scrivi il nome del locale.';
    } elseif (!isset(pf_kinds()[$d['kind']])) {
        $error = 'Scegli il tipo di locale.';
    } elseif (mb_strlen($d['name']) < 2) {
        $error = 'Scrivi il tuo nome e cognome.';
    } elseif (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || strlen($d['email']) > 190) {
        $error = 'L\'indirizzo email non è valido.';
    } elseif (post('phone') !== '' && $d['phone'] === '') {
        $error = 'Il numero di cellulare non è valido.';
    } elseif ($p = password_problem($password)) {
        $error = $p;
    } elseif (post('privacy') !== '1') {
        $error = 'Per continuare serve il consenso al trattamento dei dati.';
    } elseif (q_value('SELECT 1 FROM ' . t('staff') . ' WHERE email = ?', [$d['email']])) {
        $error = 'Con questa email esiste già un account: accedi oppure recupera la password.';
    } elseif (!captcha_check(post('captcha_t'), post('captcha'))) {
        $error = 'Le cifre di verifica non corrispondono. Riprova con il nuovo codice.';
        $captchaError = true;
    } else {
        pf_rate_hit('signup');
        [$venueId, $staffId] = venue_create([
            'name' => $d['venue'], 'kind' => $d['kind'], 'email' => $d['email'], 'phone' => $d['phone'], 'owner_name' => $d['name'],
            'password' => $password, 'hotel' => $d['hotel'] || $d['kind'] === 'hotel', 'trial_days' => $trialDays,
        ]);
        staff_session_open($staffId);
        pf_event($venueId, 'locale_creato', $d['venue'] . ' · ' . pf_kinds()[$d['kind']], 'staff', $staffId, $d['name']);
        venue_verify_send($venueId, $d['email'], $d['name']);
        foreach (notify_recipients() as $to) {
            pf_mail_queue(null, $to, 'Nuovo locale in prova: ' . $d['venue'], "Locale: {$d['venue']} (" . pf_kinds()[$d['kind']] . ")\nTitolare: {$d['name']}\nEmail: {$d['email']}\nCellulare: " . phone_show($d['phone']) . "\n\nPannello: " . site_url('superadmin/cliente.php?id=' . $venueId));
        }
        pf_flash('ok', 'Benvenuto! La prova gratuita di ' . $trialDays . ' giorni è partita adesso.');
        redirect('../gestione/');
    }
}

ui_auth_start($open ? 'Prova ChiMangia *gratis*' : 'Registrazioni *in pausa*', [
    'eyebrow' => $open ? $trialDays . ' giorni di prova, senza carta' : 'Torniamo presto',
    'lead' => $open ? 'Crea il tuo locale in due minuti. Poi condividi il collegamento con i tuoi clienti: prenotano dal telefono.' : 'In questo momento non accettiamo nuove registrazioni. Scrivici e ti attiviamo noi il locale.',
    'photo' => 'reception', 'quote' => 'Domani mattina, *sai già chi mangia.*', 'indexable' => true,
    'description' => 'Registra il tuo locale su ChiMangia e prova gratis la prenotazione dei pasti: i clienti prenotano dal telefono, la cucina trova i conteggi pronti.',
]);

if (!$open) {
    echo '<p><a class="btn btn-dark" href="../#contatti">Scrivici' . icon('arrow', 18) . '</a></p><p class="auth-links">Hai già un account? <a href="../accedi/">Accedi</a></p>';
    ui_auth_end();
    exit;
}
if ($error !== '') {
    echo '<div class="notice notice-error" role="alert">' . e($error) . '</div>';
}
?>
<form method="post" action="./" class="form" novalidate data-once>
  <?= spam_guard_fields() ?>
  <div class="form-grid">
    <?= ui_field('venue', 'Nome del locale', $d['venue'], ['required' => true, 'autocomplete' => 'organization', 'attrs' => 'maxlength="160"', 'class' => 'full']) ?>
    <?= ui_field('kind', 'Tipo di locale', $d['kind'], ['type' => 'select', 'options' => pf_kinds()]) ?>
    <?= ui_field('name', 'Il tuo nome e cognome', $d['name'], ['required' => true, 'autocomplete' => 'name', 'attrs' => 'maxlength="120"']) ?>
    <?= ui_field('email', 'Email', $d['email'], ['type' => 'email', 'required' => true, 'autocomplete' => 'email', 'hint' => 'La userai per entrare nel pannello.']) ?>
    <?= ui_field('phone', 'Cellulare', post('phone'), ['type' => 'tel', 'autocomplete' => 'tel', 'hint' => 'Facoltativo: ci serve solo per aiutarti nella configurazione.']) ?>
    <?= ui_field('password', 'Password', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'hint' => 'Almeno 10 caratteri, con lettere e numeri.', 'class' => 'full']) ?>
  </div>
  <?= ui_switch('hotel', 'Ho anche le camere', $d['hotel'], 'Attiva il modulo Hotel: notti di pernotto e assegnazione delle camere.') ?>
  <?= captcha_field('../', $captchaError) ?>
  <?= ui_check('privacy', 'Ho letto l\'<a href="../privacy/" target="_blank" rel="noopener">informativa privacy</a> e acconsento al trattamento dei dati per usare il servizio. <span class="req" aria-hidden="true">*</span>', post('privacy') === '1', true) ?>
  <button type="submit" class="btn btn-dark btn-big btn-block">Inizia la prova gratuita<?= icon('arrow', 20) ?></button>
  <p class="auth-links">Hai già un account? <a href="../accedi/">Accedi</a></p>
</form>
<?php
ui_auth_end();
