<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

if (current_user()) {
    redirect('index.php');
}

$error = '';
$email = '';
if (is_post()) {
    $email = post('email');
    if (!csrf_valid()) {
        $error = 'Il modulo è scaduto. Riprova.';
    } elseif ($email === '' || post('password') === '') {
        $error = 'Inserisci email e password.';
    } else {
        $problem = attempt_login($email, (string) ($_POST['password'] ?? ''));
        if ($problem === null) {
            $next = (string) ($_SESSION['after_login'] ?? '');
            unset($_SESSION['after_login']);
            // Solo destinazioni interne al pannello.
            $target = 'index.php';
            if (preg_match('#/admin/([a-z0-9_-]+\.php(\?[^"\'<>\s]*)?)$#i', $next, $m)) {
                $target = $m[1];
            }
            redirect($target);
        }
        $error = $problem;
    }
}

admin_auth_page_start('Accedi al pannello');
?>
<form method="post" action="login.php" class="form" novalidate>
  <?= csrf_field() ?>
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
