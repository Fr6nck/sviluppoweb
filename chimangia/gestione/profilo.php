<?php
/** Pannello del locale — Il mio profilo: nome e password. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
require __DIR__ . '/_parts.php';
pf_boot('staff', '../');
$me = staff_require();
$v = $me['venue'];

if (is_post()) {
    pf_csrf_check();
    switch (post('azione')) {
        case 'verifica':
            if ($v['email_verified_at'] === null && (int) $me['is_owner'] === 1 && pf_rate_count('verifica-' . $v['id'], 3600) < 3) {
                pf_rate_hit('verifica-' . $v['id']);
                venue_verify_send((int) $v['id'], (string) $me['email'], (string) $me['name']);
            }
            pf_flash('ok', 'Ti abbiamo scritto di nuovo a ' . $me['email'] . '. Controlla anche la posta indesiderata.');
            redirect('index.php');
            // no break
        case 'nome':
            $name = clean_text(post('name'), 120);
            if (mb_strlen($name) >= 2) {
                q('UPDATE ' . t('staff') . ' SET name = ?, updated_at = ? WHERE id = ?', [$name, now(), $me['id']]);
                pf_flash('ok', 'Nome aggiornato.');
            }
            break;
        case 'password':
            $row = q_one('SELECT password_hash FROM ' . t('staff') . ' WHERE id = ?', [$me['id']]);
            $new = (string) ($_POST['nuova'] ?? '');
            if (!$row || !password_verify((string) ($_POST['attuale'] ?? ''), (string) $row['password_hash'])) {
                pf_flash('error', 'La password attuale non è corretta.');
            } elseif ($p = password_problem($new)) {
                pf_flash('error', $p);
            } elseif ($new !== (string) ($_POST['ripeti'] ?? '')) {
                pf_flash('error', 'Le due password nuove non coincidono.');
            } else {
                q('UPDATE ' . t('staff') . ' SET password_hash = ?, updated_at = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), now(), $me['id']]);
                pf_event((int) $v['id'], 'password_cambiata', '');
                pf_flash('ok', 'Password cambiata.');
            }
            break;
    }
    redirect('profilo.php');
}

ui_staff_start('Il mio *profilo*', 'profilo.php', ['eyebrow' => (int) $me['is_owner'] === 1 ? 'Titolare' : (pf_roles()[$me['role']] ?? ''), 'narrow' => true]);
echo '<section class="card"><div class="card-head"><h2>I miei dati</h2></div><form method="post" action="profilo.php" class="form">' . csrf_field() . '<input type="hidden" name="azione" value="nome">'
    . ui_field('name', 'Nome e cognome', (string) $me['name'], ['required' => true, 'autocomplete' => 'name'])
    . ui_field('email-ro', 'Email di accesso', (string) $me['email'], ['type' => 'email', 'attrs' => 'readonly', 'hint' => 'Per cambiarla scrivici: serve a riconoscerti.'])
    . '<div><button type="submit" class="btn btn-dark">Salva</button></div></form></section>';
echo '<section class="card"><div class="card-head"><h2>Cambia password</h2></div><form method="post" action="profilo.php" class="form">' . csrf_field() . '<input type="hidden" name="azione" value="password">'
    . ui_field('attuale', 'Password attuale', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password'])
    . ui_field('nuova', 'Nuova password', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'hint' => 'Almeno 10 caratteri, con lettere e numeri.'])
    . ui_field('ripeti', 'Ripeti la nuova password', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password'])
    . '<div><button type="submit" class="btn btn-dark">Cambia password</button></div></form></section>';
ui_shell_end();
