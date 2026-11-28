<?php
/** Scelta della nuova password dal collegamento ricevuto per email. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
pf_boot('staff', '../');

$token = is_post() ? post('t') : get_param('t');
$row = pf_token_find('staff_reset', $token);
$error = '';
if ($row && is_post()) {
    $password = (string) ($_POST['password'] ?? '');
    if ($p = password_problem($password)) {
        $error = $p;
    } elseif ($password !== (string) ($_POST['password2'] ?? '')) {
        $error = 'Le due password non coincidono.';
    } elseif (pf_token_use('staff_reset', $token)) {
        q('UPDATE ' . t('staff') . ' SET password_hash = ?, updated_at = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), now(), $row['subject_id']]);
        pf_flash('ok', 'Password aggiornata. Ora puoi accedere.');
        redirect('./');
    } else {
        $row = null;
    }
}

ui_auth_start('Nuova *password*', ['eyebrow' => 'Pannello del locale', 'photo' => 'mattina']);
if (!$row) {
    echo '<div class="notice notice-error" role="alert">Il collegamento non è valido, è scaduto oppure è già stato usato.</div><p class="auth-links"><a href="recupero.php">Chiedine uno nuovo</a></p>';
    ui_auth_end();
    exit;
}
if ($error !== '') {
    echo '<div class="notice notice-error" role="alert">' . e($error) . '</div>';
}
?>
<form method="post" action="reimposta.php" class="form" novalidate>
  <input type="hidden" name="t" value="<?= e($token) ?>">
  <?= ui_field('password', 'Nuova password', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'hint' => 'Almeno 10 caratteri, con lettere e numeri.']) ?>
  <?= ui_field('password2', 'Ripeti la password', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
  <button type="submit" class="btn btn-dark btn-block">Salva la password<?= icon('arrow', 18) ?></button>
</form>
<?php
ui_auth_end();
