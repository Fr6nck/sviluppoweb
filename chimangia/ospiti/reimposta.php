<?php
/** Nuova password dell'ospite dal collegamento ricevuto per email (monouso, un'ora). */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
pf_boot('guest', '../');
require __DIR__ . '/_comuni.php';

$token = is_post() ? post('t') : get_param('t');
$row = pf_token_find('guest_reset', $token);
$guest = $row ? q_one('SELECT id, venue_id, first_name FROM ' . t('guests') . " WHERE id = ? AND status <> 'bloccato'", [$row['subject_id']]) : null;
$venue = $guest ? venue_get((int) $guest['venue_id']) : null;
if ($venue) {
    $GLOBALS['pf_guest_venue'] = $venue;
    $_SESSION['venue_slug'] = (string) $venue['slug'];
}
$error = '';

if ($venue && is_post()) {
    $password = (string) ($_POST['password'] ?? '');
    if ($p = guest_password_problem($password)) {
        $error = $p;
    } elseif ($password !== (string) ($_POST['password2'] ?? '')) {
        $error = 'Le due password non coincidono.';
    } elseif (pf_token_use('guest_reset', $token)) {
        q('UPDATE ' . t('guests') . ' SET password_hash = ?, updated_at = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), now(), $guest['id']]);
        pf_event((int) $venue['id'], 'ospite_nuova_password', '', 'ospite', (int) $guest['id'], (string) $guest['first_name']);
        pf_flash('ok', 'Password aggiornata. Ora puoi entrare.');
        redirect('index.php?l=' . rawurlencode((string) $venue['slug']));
    } else {
        $venue = null;
    }
}

ui_guest_start('Nuova *password*', '', [], $venue);
if (!$venue) {
    echo '<div class="notice notice-error" role="alert">Il collegamento non è valido, è scaduto oppure è già stato usato.</div>'
        . '<a class="btn btn-line btn-block" href="recupero.php">Chiedine uno nuovo</a>';
    ui_guest_end();
    exit;
}
if ($error !== '') {
    echo '<div class="notice notice-error" role="alert">' . e($error) . '</div>';
}
echo '<form method="post" action="reimposta.php" class="card stack-s" novalidate><input type="hidden" name="t" value="' . e($token) . '">'
    . ui_field('password', 'Nuova password', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'hint' => 'Almeno 6 caratteri.'])
    . ui_field('password2', 'Ripeti la password', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password'])
    . '<button type="submit" class="btn btn-dark btn-block">Salva la password' . icon('arrow', 18) . '</button></form>';
ui_guest_end();
