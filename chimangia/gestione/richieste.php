<?php
/** Pannello del locale — Richieste da approvare (disdette e aggiunte dopo l'orario, varianti, notti). */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
require __DIR__ . '/_parts.php';
pf_boot('staff', '../');
$me = staff_require('richieste');
$v = $me['venue'];
$vid = (int) $v['id'];

if (is_post()) {
    pf_csrf_check();
    $ok = post('esito') === 'si';
    if (request_handle($v, (int) post('id'), $ok, post('nota'), (int) $me['id'])) {
        pf_event($vid, $ok ? 'richiesta_approvata' : 'richiesta_rifiutata', '#' . (int) post('id'));
        pf_flash('ok', $ok ? 'Richiesta approvata: l\'ospite è stato avvisato.' : 'Richiesta rifiutata: l\'ospite è stato avvisato.');
    }
    redirect(back_target('richieste.php'));
}

$tab = get_param('v') === 'storico' ? 'storico' : 'attesa';
$rows = requests_list($vid, $tab === 'attesa' ? 'attesa' : 'storico', 100);
ui_staff_start('Richieste *da approvare*', 'richieste.php', ['lead' => 'Disdette e aggiunte arrivate dopo l\'orario limite, varianti al menù. L\'ospite riceve la risposta nella sua area.']);
echo ui_seg([['richieste.php', 'Da approvare', $tab === 'attesa', requests_pending_count($vid)], ['richieste.php?v=storico', 'Già gestite', $tab === 'storico']]);
echo '<section class="card">';
if (!$rows) {
    echo ui_empty($tab === 'attesa' ? 'Nessuna richiesta in sospeso.' : 'Ancora nessuna richiesta gestita.');
} elseif ($tab === 'attesa') {
    echo '<div class="list-line">';
    foreach ($rows as $r) {
        echo request_card($r, 'richieste.php');
    }
    echo '</div>';
} else {
    echo '<div class="table-wrap"><table class="table stack-sm"><thead><tr><th>Ospite</th><th>Richiesta</th><th>Esito</th><th>Nota interna</th><th>Gestita da</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr><td data-th="Ospite"><strong>' . e(guest_name($r)) . '</strong><span class="sub">' . e((string) ($r['company'] ?? '')) . '</span></td>'
            . '<td data-th="Richiesta">' . e(request_kinds()[$r['kind']] ?? '') . '<span class="sub">' . e(request_text($r)) . '</span></td>'
            . '<td data-th="Esito">' . ui_tag($r['status'] === 'approvata' ? 'Approvata' : 'Rifiutata', $r['status'] === 'approvata' ? 'ok' : 'err') . '</td>'
            . '<td data-th="Nota interna">' . e((string) $r['staff_note']) . '</td>'
            . '<td data-th="Gestita da">' . e((string) ($r['staff_name'] ?? 'automatico')) . '<span class="sub">' . e(data_it((string) $r['handled_at'])) . '</span></td></tr>';
    }
    echo '</tbody></table></div>';
}
echo '</section>';
ui_shell_end();
