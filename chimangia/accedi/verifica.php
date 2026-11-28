<?php
/** Conferma dell'email del titolare dal collegamento ricevuto. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
pf_boot('staff', '../');

$token = get_param('t');
$row = pf_token_find('verifica', $token);
if ($row && pf_token_use('verifica', $token)) {
    q('UPDATE ' . t('venues') . ' SET email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?', [now(), $row['subject_id']]);
    pf_event((int) $row['subject_id'], 'email_confermata', '', 'sistema');
    pf_flash('ok', 'Email confermata. Grazie!');
    redirect(staff_current() ? '../gestione/' : './');
}
ui_auth_start('Collegamento *non valido*', ['photo' => 'mattina']);
echo '<p class="lead">Il collegamento è scaduto oppure è già stato usato. Dal pannello puoi fartene mandare uno nuovo.</p><p><a class="btn btn-dark" href="./">Vai all\'accesso</a></p>';
ui_auth_end();
