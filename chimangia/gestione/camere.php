<?php
/** Pannello del locale — Camere (modulo Hotel): occupazione della settimana, notti da assegnare, elenco camere. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
require __DIR__ . '/_parts.php';
pf_boot('staff', '../');
$me = staff_require('camere', 'hotel');
$v = $me['venue'];
$vid = (int) $v['id'];

if (is_post()) {
    pf_csrf_check();
    $id = (int) post('id');
    $name = clean_text(post('name'), 40);
    $beds = max(1, min(20, (int) post('beds')));
    switch (post('azione')) {
        case 'nuova':
            if ($name === '') {
                pf_flash('error', 'Scrivi il nome o il numero della camera.');
            } elseif (q_value('SELECT 1 FROM ' . t('rooms') . ' WHERE venue_id = ? AND name = ?', [$vid, $name])) {
                pf_flash('error', 'Questa camera c\'è già.');
            } else {
                q('INSERT INTO ' . t('rooms') . ' (venue_id, name, beds, note, active) VALUES (?, ?, ?, ?, 1)', [$vid, $name, $beds, clean_text(post('note'), 160)]);
                pf_flash('ok', 'Camera ' . $name . ' aggiunta.');
            }
            break;
        case 'salva':
            if ($name !== '') {
                q('UPDATE ' . t('rooms') . ' SET name = ?, beds = ?, note = ?, active = ? WHERE id = ? AND venue_id = ?', [$name, $beds, clean_text(post('note'), 160), post('active') === '1' ? 1 : 0, $id, $vid]);
                pf_flash('ok', 'Camera salvata.');
            }
            break;
        case 'elimina':
            q('UPDATE ' . t('nights') . ' SET room_id = NULL WHERE venue_id = ? AND room_id = ?', [$vid, $id]);
            q('DELETE FROM ' . t('rooms') . ' WHERE id = ? AND venue_id = ?', [$id, $vid]);
            pf_flash('info', 'Camera eliminata. Le notti che aveva tornano da assegnare.');
            break;
        case 'assegna':
            $g = guest_get($vid, (int) post('ospite'));
            if ($g) {
                nights_assign($vid, (int) $g['id'], post('camera') !== '' ? (int) post('camera') : null);
                pf_event($vid, 'camera_assegnata', guest_name($g));
                pf_flash('ok', 'Notti di ' . guest_name($g) . ' confermate.');
            }
            break;
    }
    redirect(back_target('camere.php'));
}

$from = ui_day_param();
$to = day_add($from, 6);
$rooms = rooms_all($vid, false);
$active = array_values(array_filter($rooms, static fn ($r) => (int) $r['active'] === 1));
$occ = room_occupancy($vid, $from, $to);

$actions = '<a class="btn btn-line" href="stampa.php?tipo=camere&amp;g=' . $from . '" target="_blank" rel="noopener">' . icon('printer', 18) . 'Stampa la settimana</a>';
ui_staff_start('Le *camere*', 'camere.php', ['actions' => can('stampe', $me) ? $actions : '']);

echo '<div class="row"><a class="round" href="camere.php?g=' . day_add($from, -7) . '" aria-label="Settimana precedente">' . icon('chev-left', 18) . '</a>'
    . '<span class="day-nav-label">' . e(day_it($from, 'short') . ' – ' . day_it($to, 'short')) . '</span>'
    . '<a class="round" href="camere.php?g=' . day_add($from, 7) . '" aria-label="Settimana successiva">' . icon('chev-right', 18) . '</a>'
    . ($from !== today() ? '<a class="btn btn-small btn-line" href="camere.php">Da oggi</a>' : '') . '</div>';

echo '<section class="card"><div class="card-head"><h2>Occupazione</h2><p>Blu: occupata · giallo: da confermare o da assegnare · rosso: più ospiti che posti</p></div>';
if (!$active && empty($occ[0])) {
    echo ui_empty('Aggiungi le camere qui sotto: poi le assegni agli ospiti con un tocco.', 'bed');
} else {
    echo '<div class="table-wrap"><table class="occ"><thead><tr><th scope="col">Camera</th>';
    for ($i = 0; $i < 7; $i++) {
        $d = day_add($from, $i);
        echo '<th scope="col"' . ($d === today() ? ' class="today"' : '') . '>' . e(PF_DAYS_SHORT[(int) date('N', (int) strtotime($d))]) . ' ' . (int) date('j', (int) strtotime($d)) . '</th>';
    }
    echo '</tr></thead><tbody>';
    $line = static function (string $label, int $roomId, int $beds) use ($occ, $from): string {
        $h = '<tr><th scope="row">' . $label . '</th>';
        for ($i = 0; $i < 7; $i++) {
            $list = $occ[$roomId][day_add($from, $i)] ?? [];
            $waiting = (bool) array_filter($list, static fn ($n) => $n['status'] === 'richiesta');
            $cls = !$list ? '' : ($roomId === 0 || $waiting ? 'wait' : (count($list) > $beds ? 'over' : 'busy'));
            $h .= '<td' . ($cls !== '' ? ' class="' . $cls . '"' : '') . '>';
            foreach ($list as $n) {
                $h .= '<span><a href="ospite.php?id=' . (int) $n['guest_id'] . '">' . e((string) $n['last_name']) . '</a></span>';
            }
            $h .= '</td>';
        }
        return $h . '</tr>';
    };
    foreach ($active as $r) {
        echo $line(e((string) $r['name']) . '<span class="sub">' . (int) $r['beds'] . ((int) $r['beds'] === 1 ? ' posto' : ' posti') . '</span>', (int) $r['id'], (int) $r['beds']);
    }
    if (!empty($occ[0])) {
        echo $line('Da assegnare', 0, 999);
    }
    echo '</tbody></table></div>';
}
echo '</section>';

/* ---------- Notti da assegnare ---------- */
$todo = q(
    'SELECT g.id, g.first_name, g.last_name, c.name AS company, MIN(n.day) AS dal, MAX(n.day) AS al, COUNT(*) AS notti FROM ' . t('nights') . ' n
     JOIN ' . t('guests') . ' g ON g.id = n.guest_id LEFT JOIN ' . t('companies') . " c ON c.id = g.company_id
     WHERE n.venue_id = ? AND n.day >= ? AND n.status <> 'disdetta' AND (n.room_id IS NULL OR n.status = 'richiesta') AND g.status = 'confermato'
     GROUP BY g.id, g.first_name, g.last_name, c.name ORDER BY dal",
    [$vid, today()]
)->fetchAll();
echo '<div class="grid-even"><section class="card"><div class="card-head"><h2>Notti da assegnare</h2><p>Ospiti confermati senza camera</p></div>';
if ($todo) {
    echo '<div class="list-line">';
    foreach ($todo as $g) {
        echo '<form method="post" action="camere.php" class="row">' . csrf_field() . '<input type="hidden" name="azione" value="assegna"><input type="hidden" name="ospite" value="' . (int) $g['id'] . '">'
            . '<span class="grow"><strong>' . e(guest_name($g)) . '</strong><span class="sub">' . e((string) ($g['company'] ?? '')) . ' ' . e(day_it((string) $g['dal'], 'short') . ((int) $g['notti'] > 1 ? ' – ' . day_it((string) $g['al'], 'short') : ''))
            . ' · ' . (int) $g['notti'] . ((int) $g['notti'] === 1 ? ' notte' : ' notti') . '</span></span>'
            . '<label class="sr-only" for="cam' . (int) $g['id'] . '">Camera di ' . e(guest_name($g)) . '</label><select id="cam' . (int) $g['id'] . '" name="camera"><option value="">—</option>';
        foreach ($active as $r) {
            echo '<option value="' . (int) $r['id'] . '">' . e((string) $r['name']) . '</option>';
        }
        echo '</select><button type="submit" class="btn btn-small btn-dark">Conferma</button></form>';
    }
    echo '</div>';
} else {
    echo ui_empty('Tutte le notti hanno la loro camera.');
}
echo '</section>';

/* ---------- Elenco camere ---------- */
echo '<section class="card card-cream"><div class="card-head"><h2>Le tue camere</h2><p>' . count($active) . ' attive</p></div><div class="list-line">';
foreach ($rooms as $r) {
    $rid = (int) $r['id'];
    echo '<details><summary class="row-between"><span><strong>' . e((string) $r['name']) . '</strong> <span class="muted small">' . (int) $r['beds'] . ((int) $r['beds'] === 1 ? ' posto' : ' posti')
        . ($r['note'] !== '' ? ' · ' . e((string) $r['note']) : '') . '</span></span>' . (!(int) $r['active'] ? ui_tag('Non in uso', 'soft') : '<span class="muted small">Modifica</span>') . '</summary>'
        . '<form method="post" action="camere.php" class="form">' . csrf_field() . '<input type="hidden" name="azione" value="salva"><input type="hidden" name="id" value="' . $rid . '"><div class="form-grid">'
        . ui_field('name', 'Nome o numero', (string) $r['name'], ['required' => true, 'id' => 'rn' . $rid]) . ui_field('beds', 'Posti letto', (string) $r['beds'], ['type' => 'number', 'id' => 'rb' . $rid, 'attrs' => 'min="1" max="20"'])
        . ui_field('note', 'Nota', (string) $r['note'], ['id' => 'ro' . $rid, 'class' => 'full']) . '</div>' . ui_switch('active', 'In uso', (int) $r['active'] === 1, 'Spegnila se è in manutenzione: resta nell\'elenco ma non si assegna.')
        . '<div class="row"><button type="submit" class="btn btn-small btn-dark">Salva</button></div></form>'
        . ui_post_btn('camere.php', ['azione' => 'elimina', 'id' => $rid], 'Elimina la camera', 'btn btn-small btn-danger', 'Eliminare la camera ' . $r['name'] . '?') . '</details>';
}
echo '<form method="post" action="camere.php" class="form">' . csrf_field() . '<input type="hidden" name="azione" value="nuova"><div class="form-grid">'
    . ui_field('name', 'Nuova camera', '', ['required' => true, 'placeholder' => 'Es. 12', 'attrs' => 'maxlength="40"']) . ui_field('beds', 'Posti letto', '2', ['type' => 'number', 'attrs' => 'min="1" max="20"'])
    . '</div><div><button type="submit" class="btn btn-dark">' . icon('plus', 18) . 'Aggiungi camera</button></div></form></div></section></div>';
ui_shell_end();
