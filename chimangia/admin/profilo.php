<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

$user = require_login();
$full = q_one('SELECT * FROM ' . t('users') . ' WHERE id = ?', [$user['id']]);
$errors = [];
$name = $full['name'];
$email = $full['email'];

if (is_post()) {
    csrf_check();
    $name = post('name');
    $email = mb_strtolower(post('email'));
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $new2 = (string) ($_POST['new_password2'] ?? '');

    if ($name === '' || mb_strlen($name) > 120) {
        $errors['name'] = 'Scrivi il nome (massimo 120 caratteri).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Scrivi un indirizzo email valido.';
    } elseif ((int) q_value('SELECT COUNT(*) FROM ' . t('users') . ' WHERE email = ? AND id <> ?', [$email, $user['id']]) > 0) {
        $errors['email'] = 'Questa email è già usata da un altro utente.';
    }
    $emailChanged = $email !== $full['email'];
    if ($new !== '' || $emailChanged) {
        if (!password_verify($current, $full['password_hash'])) {
            $errors['current_password'] = 'Per cambiare email o password scrivi la password attuale.';
        }
    }
    if ($new !== '') {
        if ($p = password_problem($new)) {
            $errors['new_password'] = $p;
        } elseif ($new !== $new2) {
            $errors['new_password2'] = 'Le due password non coincidono.';
        }
    }

    if (!$errors) {
        q('UPDATE ' . t('users') . ' SET name = ?, email = ?, updated_at = ? WHERE id = ?', [$name, $email, now(), $user['id']]);
        if ($new !== '') {
            q('UPDATE ' . t('users') . ' SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            session_regenerate_id(true);
        }
        audit('profilo_modificato', ($emailChanged ? 'email cambiata; ' : '') . ($new !== '' ? 'password cambiata' : 'nome/dati'));
        flash('ok', 'Profilo aggiornato.');
        redirect('profilo.php');
    }
    flash('error', 'Controlla i campi evidenziati.');
}

admin_header('Il mio profilo', 'profilo.php');
?>
<form method="post" action="profilo.php" class="form panel" novalidate data-dirty-check>
  <?= csrf_field() ?>
  <fieldset>
    <legend>Dati personali</legend>
    <?= field('name', 'Nome', $name, ['required' => true, 'error' => $errors['name'] ?? '', 'attrs' => 'autocomplete="name"']) ?>
    <?= field('email', 'Email', $email, ['type' => 'email', 'required' => true, 'error' => $errors['email'] ?? '', 'attrs' => 'autocomplete="email"']) ?>
    <p class="hint">Ruolo: <strong><?= e(CM_ROLES[$full['role']] ?? $full['role']) ?></strong>. Ultimo accesso: <?= e(data_it($full['last_login_at'])) ?>.</p>
  </fieldset>
  <fieldset>
    <legend>Cambia password</legend>
    <p class="hint">Lascia vuoti questi campi se non vuoi cambiare la password.</p>
    <?= field('new_password', 'Nuova password', '', ['type' => 'password', 'hint' => 'Almeno 10 caratteri, con lettere e numeri.', 'error' => $errors['new_password'] ?? '', 'attrs' => 'autocomplete="new-password"']) ?>
    <?= field('new_password2', 'Ripeti la nuova password', '', ['type' => 'password', 'error' => $errors['new_password2'] ?? '', 'attrs' => 'autocomplete="new-password"']) ?>
  </fieldset>
  <fieldset>
    <legend>Conferma</legend>
    <?= field('current_password', 'Password attuale', '', ['type' => 'password', 'hint' => 'Necessaria solo se cambi email o password.', 'error' => $errors['current_password'] ?? '', 'attrs' => 'autocomplete="current-password"']) ?>
  </fieldset>
  <div class="actions-bar"><button type="submit" class="btn btn-dark">Salva il profilo</button></div>
</form>
<?php
admin_footer();
