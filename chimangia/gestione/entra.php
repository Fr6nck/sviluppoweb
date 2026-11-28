<?php
/**
 * Ingresso del fornitore nel pannello di un locale (assistenza).
 * Il super admin crea un token monouso valido un minuto: qui diventa una sessione dello staff,
 * segnata come "fornitore" in ogni registrazione.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
pf_boot('staff', '../');

$token = get_param('t');
$row = pf_token_find('entra', $token);
if (!$row || !pf_token_use('entra', $token)) {
    ui_die('Collegamento scaduto', 'Torna nel pannello del fornitore e riprova.', 403);
}
$owner = q_one('SELECT id, venue_id FROM ' . t('staff') . ' WHERE venue_id = ? AND active = 1 ORDER BY is_owner DESC, id LIMIT 1', [$row['subject_id']]);
if (!$owner) {
    ui_die('Locale senza utenti', 'Questo locale non ha utenti attivi.', 404);
}
staff_session_open((int) $owner['id'], 1);
pf_event((int) $owner['venue_id'], 'ingresso_fornitore', '', 'fornitore');
redirect('index.php');
