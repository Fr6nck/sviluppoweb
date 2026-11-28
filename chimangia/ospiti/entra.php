<?php
/**
 * Ingresso dell'ospite arrivato dalla pagina di accesso comune (accedi/), dove ha scritto
 * cellulare e password. Là la password è già stata verificata: qui un token monouso,
 * valido due minuti, apre la sessione dell'area ospiti.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
pf_boot('guest', '../');

$token = get_param('t');
$row = pf_token_find('guest_entry', $token);
if (!$row || !pf_token_use('guest_entry', $token)) {
    pf_flash('error', 'Il collegamento per entrare è scaduto. Accedi di nuovo con cellulare e password.');
    redirect('index.php');
}
$g = q_one('SELECT id, venue_id, status FROM ' . t('guests') . ' WHERE id = ?', [(int) $row['subject_id']]);
$venue = $g ? venue_get((int) $g['venue_id']) : null;
if (!$g || !$venue || $g['status'] === 'bloccato') {
    pf_flash('error', 'Questo accesso non è disponibile. Rivolgiti alla reception.');
    redirect('index.php');
}
guest_session_open((int) $g['id'], (string) $venue['slug']);
redirect('index.php');
