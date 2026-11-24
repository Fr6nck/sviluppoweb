<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

$token = is_post() ? post('token') : get_param('token');
$reset = password_reset_find($token);

if (!$reset) {
    admin_auth_page_start('Link non valido');
    echo '<p>Il link per reimpostare la password è scaduto, è già stato usato oppure non è corretto.</p>'
        . '<p class="auth-links"><a href="recupero.php">Chiedi un nuovo link</a></p>';
    admin_auth_page_end();
    exit;
}

$errors = [];
if (is_post()) {
    if (!csrf_valid() && !login_token_valid(post('_lt'), 'reimposta')) {
        $errors['password'] = 'Il modulo è scaduto. Riprova.';
    } else {
        $pass = (string) ($_POST['password'] ?? '');
        $pass2 = (string) ($_POST['password2'] ?? '');
        if ($p = password_problem($pass)) {
            $errors['password'] = $p;
        } elseif ($pass !== $pass2) {
            $errors['password2'] = 'Le due password non coincidono.';
        }
        if (!$errors) {
            q('UPDATE ' . t('users') . ' SET password_hash = ?, updated_at = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), now(), $reset['user_id']]);
            q('UPDATE ' . t('password_resets') . ' SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [now(), $reset['user_id']]);
            q('DELETE FROM ' . t('login_attempts') . ' WHERE email = ?', [mb_strtolower($reset['email'])]);
            audit('password_reimpostata', $reset['email'], (int) $reset['user_id']);
            flash('ok', 'Password aggiornata. Ora puoi accedere con la nuova password.');
            redirect('login.php');
        }
    }
}

admin_auth_page_start('Scegli una nuova password');
?>
<form method="post" action="reimposta.php" class="form" novalidate>
  <?= csrf_field() ?>
  <?= login_token_field('reimposta') ?>
  <input type="hidden" name="token" value="<?= e($token) ?>">
  <p>Account: <strong><?= e($reset['email']) ?></strong></p>
  <div class="field">
    <label for="password">Nuova password</label>
    <input type="password" id="password" name="password" autocomplete="new-password" required aria-describedby="pw-hint<?= isset($errors['password']) ? ' pw-err' : '' ?>"<?= isset($errors['password']) ? ' aria-invalid="true"' : '' ?>>
    <p class="hint" id="pw-hint">Almeno 10 caratteri, con lettere e numeri.</p>
    <?php if (isset($errors['password'])): ?><p class="field-error" id="pw-err"><?= e($errors['password']) ?></p><?php endif; ?>
  </div>
  <div class="field">
    <label for="password2">Ripeti la nuova password</label>
    <input type="password" id="password2" name="password2" autocomplete="new-password" required<?= isset($errors['password2']) ? ' aria-invalid="true" aria-describedby="pw2-err"' : '' ?>>
    <?php if (isset($errors['password2'])): ?><p class="field-error" id="pw2-err"><?= e($errors['password2']) ?></p><?php endif; ?>
  </div>
  <button type="submit" class="btn btn-dark btn-block">Salva la password</button>
</form>
<?php
admin_auth_page_end();
