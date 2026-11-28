<?php
/** Pannello del locale — Registrazioni da confermare. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
require __DIR__ . '/_parts.php';
pf_boot('staff', '../');
$me = staff_require('conferma');
$v = $me['venue'];
$vid = (int) $v['id'];

if (is_post()) {
    pf_csrf_check();
    $back = back_target('registrazioni.php');
    $id = (int) post('id');
    switch (post('azione')) {
        case 'conferma':
            $g = guest_get($vid, $id);
            if ($g && guest_confirm($v, $id)) {
                if (feature('hotel', $v) && can('camere', $me)) {
                    nights_assign($vid, $id, post('camera') !== '' ? (int) post('camera') : null);
                }
                pf_event($vid, 'ospite_confermato', guest_name($g));
                pf_flash('ok', guest_name($g) . ' è confermato: da ora conta in cucina.');
            }
            break;
        case 'rifiuta':
            $g = guest_get($vid, $id);
            if ($g && $g['status'] === 'attesa') {
                foreach (['bookings', 'nights', 'requests', 'notices'] as $tb) {
                    q('DELETE FROM ' . t($tb) . ' WHERE guest_id = ? AND venue_id = ?', [$id, $vid]);
                }
                q('DELETE FROM ' . t('guests') . ' WHERE id = ? AND venue_id = ?', [$id, $vid]);
                pf_event($vid, 'registrazione_rifiutata', guest_name($g));
                pf_flash('info', 'Registrazione di ' . guest_name($g) . ' eliminata.');
            }
            break;
        case 'unisci':
            if (can('societa', $me) && company_merge($vid, (int) post('da'), (int) post('a'))) {
                pf_event($vid, 'societa_unite', '');
                pf_flash('ok', 'Società unite: gli ospiti sono passati a quella già presente.');
            }
            break;
    }
    redirect($back);
}

$rows = pending_guests($v);
ui_staff_start('Registrazioni *da confermare*', 'registrazioni.php', ['lead' => 'Chi si registra dal telefono compare qui. Finché non lo confermi, le sue prenotazioni non arrivano in cucina.']);
echo '<section class="card">';
if ($rows) {
    echo registrations_table($me, $rows, 'registrazioni.php');
    echo '<details class="small"><summary>Una registrazione non è di un tuo cliente?</summary><div class="stack-s"><p class="muted">Eliminandola si cancellano anche le prenotazioni che ha inserito.</p><div class="row">';
    foreach ($rows as $r) {
        echo ui_post_btn('registrazioni.php', ['azione' => 'rifiuta', 'id' => $r['id']], 'Elimina ' . e(guest_name($r)), 'btn btn-small btn-danger', 'Eliminare la registrazione di ' . guest_name($r) . '?');
    }
    echo '</div></div></details>';
} else {
    echo ui_empty('Nessuna registrazione in attesa. I nuovi ospiti compaiono qui appena si registrano.');
}
echo '</section>';
if (!empty($v['settings']['auto_confirm'])) {
    echo '<p class="small muted">La conferma automatica è attiva: i nuovi ospiti entrano subito nei conteggi. Si cambia in Impostazioni.</p>';
}
ui_shell_end();
