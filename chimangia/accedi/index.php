<?php
/** Accesso al pannello del locale (titolare, reception, cucina). */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
pf_boot('staff', '../');

if (staff_current()) {
    redirect('../gestione/');
}

/** Destinazione interna al pannello dopo l'accesso. */
function after_login_target(string $next): string
{
    return preg_match('#^[a-z0-9_-]+\.php(\?[A-Za-z0-9_=&%.-]*)?$#', $next) ? $next : 'index.php';
}

$error = '';
$email = '';
if (is_post()) {
    $email = post('email');
    if (!login_token_valid(post('_lt'), 'staff')) {
        $error = 'Il modulo è scaduto. Ricarica la pagina e riprova.';
    } elseif ($email === '' || post('password') === '') {
        $error = 'Inserisci email e password.';
    } else {
        $next = (string) ($_SESSION['after_login'] ?? '');
        $error = (string) staff_login($email, (string) ($_POST['password'] ?? ''));
        if ($error === '') {
            redirect('../gestione/' . after_login_target($next));
        }
    }
}

ui_auth_start('Bentornato *in cucina*', ['eyebrow' => 'Pannello del locale', 'lead' => 'Titolare, reception e cucina entrano da qui.', 'photo' => 'brigata']);
if ($error !== '') {
    echo '<div class="notice notice-error" role="alert" id="login-error">' . e($error) . '</div>';
}
?>
<form method="post" action="./" class="form" novalidate>
  <?= login_token_field('staff') ?>
  <?= ui_field('email', 'Email', $email, ['type' => 'email', 'required' => true, 'autocomplete' => 'username', 'attrs' => 'autofocus']) ?>
  <?= ui_field('password', 'Password', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
  <button type="submit" class="btn btn-dark btn-big btn-block">Accedi<?= icon('arrow', 20) ?></button>
  <p class="auth-links"><a href="recupero.php">Hai dimenticato la password?</a></p>
  <p class="auth-links">Non hai ancora un account? <a href="../registrati/">Prova gratis <?= (int) setting('pf_trial_days', PF_TRIAL_DAYS) ?> giorni</a></p>
</form>
<form method="get" action="../ospiti/" class="card card-cream stack-s">
  <h2>Sei un cliente di un locale?</h2>
  <p class="small muted">Apri il collegamento che ti ha dato il locale, oppure scrivi qui il suo codice.</p>
  <div class="row">
    <label class="sr-only" for="codice">Codice del locale</label>
    <input class="grow" type="text" id="codice" name="l" placeholder="es. trattoria-da-mario" autocapitalize="none" autocomplete="off" required pattern="[a-zA-Z0-9-]{2,60}">
    <button type="submit" class="btn btn-line">Vai</button>
  </div>
</form>
<?php
ui_auth_end();
