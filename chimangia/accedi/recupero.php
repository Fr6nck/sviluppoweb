<?php
/** Recupero della password dello staff: invia un collegamento monouso, valido un'ora. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
pf_boot('staff', '../');

$sent = false;
$error = '';
if (is_post()) {
    $email = mb_strtolower(post('email'));
    if (!login_token_valid(post('_lt'), 'staff-reset')) {
        $error = 'Il modulo è scaduto. Ricarica la pagina e riprova.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Scrivi un indirizzo email valido.';
    } elseif (pf_rate_count('staff-reset', 3600) >= 5) {
        $error = 'Troppe richieste. Riprova tra un\'ora.';
    } else {
        pf_rate_hit('staff-reset');
        $u = q_one('SELECT * FROM ' . t('staff') . ' WHERE email = ? AND active = 1', [$email]);
        if ($u) {
            staff_reset_send($u);
        }
        $sent = true;   // stessa risposta in ogni caso: non riveliamo se l'email esiste
    }
}

ui_auth_start('Password *dimenticata*', ['eyebrow' => 'Pannello del locale', 'photo' => 'mattina']);
if ($sent) {
    echo '<div class="notice notice-ok" role="status">Se l\'indirizzo è registrato, tra poco riceverai un\'email con il collegamento per scegliere una nuova password.</div>'
        . '<p class="auth-links"><a href="./">Torna all\'accesso</a></p>';
    ui_auth_end();
    exit;
}
if ($error !== '') {
    echo '<div class="notice notice-error" role="alert">' . e($error) . '</div>';
}
?>
<form method="post" action="recupero.php" class="form" novalidate>
  <?= login_token_field('staff-reset') ?>
  <?= ui_field('email', 'La tua email', post('email'), ['type' => 'email', 'required' => true, 'autocomplete' => 'username', 'hint' => 'Ti mandiamo un collegamento valido un\'ora.']) ?>
  <button type="submit" class="btn btn-dark btn-block">Invia il collegamento<?= icon('arrow', 18) ?></button>
  <p class="auth-links"><a href="./">Torna all'accesso</a></p>
</form>
<?php
ui_auth_end();
