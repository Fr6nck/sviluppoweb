<?php
/**
 * Registrazione dell'ospite (passo 1 di 2): dati, società, diete.
 * Protezioni: CAPTCHA senza servizi esterni, campo esca, tempo minimo, limite per connessione.
 * Il passo 2 è la pagina "Notti e pasti".
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
pf_boot('guest', '../');
require __DIR__ . '/_comuni.php';

if (guest_current()) {
    redirect('pasti.php');
}
$venue = g_venue_pick();
if (!$venue || !venue_live($venue)) {
    redirect('index.php' . ($venue ? '?l=' . rawurlencode((string) $venue['slug']) : ''));
}

$withCompany = feature('societa', $venue);
$dietList = array_values(array_map('strval', (array) $venue['settings']['diets']));
$d = ['first_name' => '', 'last_name' => '', 'phone' => '', 'email' => '', 'company' => '', 'diets' => [], 'needs' => '', 'consent' => false];
$error = '';
$captchaError = false;

if (is_post()) {
    $d = [
        'first_name' => clean_text(post('first_name'), 80), 'last_name' => clean_text(post('last_name'), 80),
        'phone' => post('phone'), 'email' => mb_strtolower(post('email')), 'company' => $withCompany ? clean_text(post('company'), 160) : '',
        'diets' => g_post_diets(), 'needs' => clean_text(post('needs'), 500), 'consent' => post('consent') === '1',
    ];
    $password = (string) ($_POST['password'] ?? '');
    $phone = phone_normalize($d['phone']);
    $hasDiet = diets_clean($venue, $d['diets']) !== '' || $d['needs'] !== '';
    if ($e = spam_guard_check('guest-signup', 8)) {
        $error = $e;
    } elseif ($d['first_name'] === '' || $d['last_name'] === '') {
        $error = 'Scrivi nome e cognome.';
    } elseif ($phone === '') {
        $error = 'Scrivi un numero di cellulare valido.';
    } elseif ($d['email'] !== '' && (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || strlen($d['email']) > 190)) {
        $error = 'L\'indirizzo email non è valido.';
    } elseif ($p = guest_password_problem($password)) {
        $error = $p;
    } elseif ($hasDiet && !$d['consent']) {
        $error = 'Per indicare diete o intolleranze serve il tuo consenso al loro utilizzo.';
    } elseif (q_value('SELECT 1 FROM ' . t('guests') . ' WHERE venue_id = ? AND phone = ?', [$venue['id'], $phone])) {
        $error = 'Con questo cellulare esiste già una registrazione: accedi con la tua password.';
    } elseif (!captcha_check(post('captcha_t'), post('captcha'))) {
        $error = 'Le cifre di verifica non corrispondono. Riprova con il nuovo codice.';
        $captchaError = true;
    } else {
        [$id, $err] = guest_create($venue, $d + ['password' => $password]);
        if ($id === null) {
            $error = (string) $err;
        } else {
            pf_rate_hit('guest-signup');
            guest_session_open((int) $id, (string) $venue['slug']);
            $name = trim($d['first_name'] . ' ' . $d['last_name']);
            pf_event((int) $venue['id'], 'ospite_registrato', $name . ($d['company'] !== '' ? ' · ' . $d['company'] : ''), 'ospite', (int) $id, $name);
            redirect('pasti.php?benvenuto=1');
        }
    }
}

ui_guest_start('Registrati', '', [
    'eyebrow' => 'Passo 1 di 2', 'lead' => 'Ti serve solo la prima volta. Poi entri con cellulare e password.',
    'back' => 'index.php?l=' . rawurlencode((string) $venue['slug']), 'back_label' => (string) $venue['name'],
], $venue);
if ($error !== '') {
    echo '<div class="notice notice-error" role="alert">' . e($error) . '</div>';
}
?>
<form method="post" action="registrati.php" class="form" novalidate data-once>
  <?= spam_guard_fields() ?>
  <div class="form-grid g-two">
    <?= ui_field('first_name', 'Nome', $d['first_name'], ['required' => true, 'autocomplete' => 'given-name', 'attrs' => 'maxlength="80"']) ?>
    <?= ui_field('last_name', 'Cognome', $d['last_name'], ['required' => true, 'autocomplete' => 'family-name', 'attrs' => 'maxlength="80"']) ?>
  </div>
  <?= ui_field('phone', 'Cellulare', $d['phone'], ['type' => 'tel', 'required' => true, 'autocomplete' => 'tel', 'placeholder' => '333 123 4567', 'hint' => 'È il numero con cui entrerai. Il locale lo usa anche per avvisarti del menù.']) ?>
  <?= ui_field('email', 'Email', $d['email'], ['type' => 'email', 'autocomplete' => 'email', 'placeholder' => 'nome@email.it', 'hint' => 'Facoltativa: serve per recuperare la password.']) ?>
  <?= ui_field('password', 'Password', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'hint' => 'Almeno 6 caratteri.']) ?>
  <?= $withCompany ? g_company_field($venue, $d['company']) : '' ?>
  <?php if ($dietList): ?>
  <div class="field">
    <span class="label" id="diete-l">Diete o intolleranze</span>
    <?= ui_chips('diets[]', array_combine($dietList, $dietList), $d['diets']) ?>
  </div>
  <?php endif; ?>
  <?= ui_field('needs', 'Altre esigenze (facoltativo)', $d['needs'], ['type' => 'textarea', 'rows' => 2, 'placeholder' => 'Es. allergia alla frutta secca', 'attrs' => 'maxlength="500"']) ?>
  <?= ui_check('consent', 'Acconsento che ' . e((string) $venue['name']) . ' usi i dati su diete e intolleranze solo per preparare i miei pasti. Serve solo se ne hai indicate. <a href="../privacy/" target="_blank" rel="noopener">Informativa privacy</a>', $d['consent']) ?>
  <?= captcha_field('../', $captchaError) ?>
  <button type="submit" class="btn btn-dark btn-big btn-block">Continua: <?= feature('hotel', $venue) ? 'notti e pasti' : 'i tuoi pasti' ?><?= icon('arrow', 20) ?></button>
  <p class="small muted g-center">Hai già un account? <a href="index.php?l=<?= e(rawurlencode((string) $venue['slug'])) ?>">Accedi</a></p>
</form>
<?php
ui_guest_end();
