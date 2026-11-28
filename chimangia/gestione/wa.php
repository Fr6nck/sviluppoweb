<?php
/** Apre WhatsApp con il messaggio pronto per un ospite e segna l'avviso come inviato. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
require __DIR__ . '/_parts.php';
pf_boot('staff', '../');
$me = staff_require('menu', 'whatsapp');
$v = $me['venue'];

if (!hash_equals(csrf_token(), get_param('k'))) {
    ui_die('Richiesta non valida', 'Torna al pannello e riprova.', 400);
}
$b = q_one(
    'SELECT b.id, b.day, b.meal, g.phone FROM ' . t('bookings') . ' b JOIN ' . t('guests') . ' g ON g.id = b.guest_id WHERE b.id = ? AND b.venue_id = ?',
    [(int) get_param('b'), $v['id']]
);
if (!$b) {
    ui_die('Prenotazione non trovata', 'Forse è stata disdetta nel frattempo.', 404);
}
q('UPDATE ' . t('bookings') . ' SET wa_sent_at = ? WHERE id = ?', [now(), $b['id']]);
redirect(phone_wa((string) $b['phone'], wa_message($v, (string) $b['day'], (string) $b['meal'])), 302);
