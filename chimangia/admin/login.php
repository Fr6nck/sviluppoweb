<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

/** Destinazione interna al pannello dopo l'accesso. */
function login_target(string $next): string
{
    return preg_match('#(?:^|/admin/)([a-z0-9_-]+\.php(\?[^"\'<>\s]*)?)$#i', $next, $m) ? $m[1] : 'index.php';
}

// Verifica dopo l'accesso: il browser ha conservato il cookie di sessione?
if (get_param('verifica') === '1') {
    if (current_user()) {
        redirect(login_target(get_param('to')));
    }
    $hasCookie = isset($_COOKIE[CM_SESSION_NAME]);
    admin_auth_page_start('Accesso non completato');
    echo '<div class="notice notice-error" role="alert">Email e password sono corrette, ma la sessione non è stata conservata, quindi il pannello non può riconoscerti.</div>'
        . '<p>Cosa provare:</p><ul>'
        . '<li>apri il pannello sempre dallo stesso indirizzo, con <strong>https://</strong>;</li>'
        . '<li>controlla che il browser accetti i cookie per questo sito (anche in navigazione privata);</li>'
        . '<li>se usi una cache o un CDN sull\'hosting, escludi la cartella <code>/admin/</code>.</li></ul>'
        . '<p class="hint">Dati per l\'assistenza: cookie ricevuto: ' . ($hasCookie ? 'sì' : 'no') . ' · HTTPS rilevato: ' . (is_https() ? 'sì' : 'no')
        . ' · percorso del cookie: ' . e(request_base_path()) . ' · cartella sessioni scrivibile: ' . (is_writable(storage_path('sessions')) ? 'sì' : 'no') . '</p>'
        . '<p class="auth-links"><a href="login.php">Riprova</a></p>';
    admin_auth_page_end();
    exit;
}

if (current_user()) {
    redirect('index.php');
}

$error = '';
$email = '';
if (is_post()) {
    $email = post('email');
    if (!csrf_valid() && !login_token_valid(post('_lt'))) {
        $error = 'Il modulo è scaduto. Ricarica la pagina e riprova.';
    } elseif ($email === '' || post('password') === '') {
        $error = 'Inserisci email e password.';
    } else {
        $problem = attempt_login($email, (string) ($_POST['password'] ?? ''));
        if ($problem === null) {
            $next = (string) ($_SESSION['after_login'] ?? '');
            unset($_SESSION['after_login']);
            redirect('login.php?verifica=1&to=' . rawurlencode(login_target($next)));
        }
        $error = $problem;
    }
}

admin_auth_page_start('Accedi al pannello');
?>
<form method="post" action="login.php" class="form" novalidate>
  <?= csrf_field() ?>
  <?= login_token_field() ?>
  <?php if ($error): ?><div class="notice notice-error" role="alert" id="login-error"><?= e($error) ?></div><?php endif; ?>
  <div class="field">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="username" required<?= $error ? ' aria-describedby="login-error"' : '' ?> autofocus>
  </div>
  <div class="field">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>
  </div>
  <button type="submit" class="btn btn-dark btn-block">Accedi<span class="arr" aria-hidden="true">→</span></button>
  <p class="auth-links"><a href="recupero.php">Hai dimenticato la password?</a></p>
</form>
<?php
admin_auth_page_end();
