<?php
/** Pannello del locale — Scheda di un ospite: dati, diete, pasti e notti inseriti per suo conto. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
require __DIR__ . '/_parts.php';
pf_boot('staff', '../');
$me = staff_require('conferma');
$v = $me['venue'];
$vid = (int) $v['id'];
$id = (int) (is_post() ? post('id') : get_param('id'));
$g = guest_get($vid, $id);
if (!$g) {
    ui_die('Ospite non trovato', 'Forse è stato eliminato.', 404);
}
$hotel = feature('hotel', $v);
$days = [];
for ($i = 0; $i < 10; $i++) {
    $days[] = day_add(today(), $i);
}
$meals = array_values(array_intersect(array_keys(PF_MEALS), (array) $v['settings']['meals']));

if (is_post()) {
    pf_csrf_check();
    $self = 'ospite.php?id=' . $id;
    switch (post('azione')) {
        case 'salva':
            $first = clean_text(post('first_name'), 80);
            $last = clean_text(post('last_name'), 80);
            $phone = phone_normalize(post('phone'));
            $email = mb_strtolower(post('email'));
            if ($first === '' || $last === '' || $phone === '') {
                pf_flash('error', 'Servono nome, cognome e un cellulare valido.');
            } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                pf_flash('error', 'L\'indirizzo email non è valido.');
            } elseif (q_value('SELECT 1 FROM ' . t('guests') . ' WHERE venue_id = ? AND phone = ? AND id <> ?', [$vid, $phone, $id])) {
                pf_flash('error', 'Un altro ospite usa già questo cellulare.');
            } else {
                $sets = ['first_name = ?', 'last_name = ?', 'phone = ?', 'email = ?', 'updated_at = ?'];
                $args = [$first, $last, $phone, $email, now()];
                if (feature('societa', $v)) {
                    $sets[] = 'company_id = ?';
                    $args[] = company_resolve($vid, post('company'), true);
                }
                if (can('note', $me)) {
                    array_push($sets, 'diets = ?', 'needs = ?', 'kitchen_note = ?', 'staff_note = ?');
                    array_push($args, diets_clean($v, $_POST['diets'] ?? []), clean_text(post('needs'), 500), clean_text(post('kitchen_note'), 160), clean_lines(post('staff_note'), 2000));
                }
                $args[] = $id;
                q('UPDATE ' . t('guests') . ' SET ' . implode(', ', $sets) . ' WHERE id = ?', $args);
                pf_event($vid, 'ospite_modificato', $first . ' ' . $last);
                pf_flash('ok', 'Dati salvati.');
            }
            break;
        case 'conferma':
            if (guest_confirm($v, $id)) {
                pf_event($vid, 'ospite_confermato', guest_name($g));
                pf_flash('ok', 'Ospite confermato.');
            }
            break;
        case 'sospendi':
        case 'riattiva':
            $to = post('azione') === 'sospendi' ? 'bloccato' : 'confermato';
            q('UPDATE ' . t('guests') . ' SET status = ?, updated_at = ? WHERE id = ?', [$to, now(), $id]);
            pf_event($vid, 'ospite_' . post('azione'), guest_name($g));
            pf_flash('ok', $to === 'bloccato' ? 'Accesso sospeso: l\'ospite non può più entrare né prenotare.' : 'Accesso riattivato.');
            break;
        case 'password':
            $pw = (string) random_int(100000, 999999);
            q('UPDATE ' . t('guests') . ' SET password_hash = ?, updated_at = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), now(), $id]);
            pf_event($vid, 'password_ospite_reimpostata', guest_name($g));
            pf_flash('ok', 'Nuova password provvisoria per ' . guest_name($g) . ': ' . $pw . '. Comunicagliela: potrà cambiarla dal suo profilo.');
            break;
        case 'elimina':
            foreach (['bookings', 'nights', 'requests', 'notices'] as $tb) {
                q('DELETE FROM ' . t($tb) . ' WHERE guest_id = ? AND venue_id = ?', [$id, $vid]);
            }
            q('DELETE FROM ' . t('guests') . ' WHERE id = ? AND venue_id = ?', [$id, $vid]);
            pf_event($vid, 'ospite_eliminato', guest_name($g));
            pf_flash('info', guest_name($g) . ' è stato eliminato con tutte le sue prenotazioni.');
            redirect('ospiti.php');
            // no break
        case 'pasti':
            $want = is_array($_POST['pasto'] ?? null) ? $_POST['pasto'] : [];
            $n = 0;
            foreach ($days as $d) {
                foreach ($meals as $m) {
                    if (booking_toggle($v, $g, $d, $m, isset($want[$d . '|' . $m]), true) === 'ok') {
                        $n++;
                    }
                }
            }
            if ($n > 0) {
                pf_event($vid, 'pasti_modificati_dallo_staff', guest_name($g) . ': ' . $n);
            }
            pf_flash('ok', $n > 0 ? 'Pasti aggiornati (' . $n . ($n === 1 ? ' modifica' : ' modifiche') . ').' : 'Nessuna modifica.');
            break;
        case 'notti':
            if ($hotel && can('camere', $me) && valid_day(post('dal')) && valid_day(post('al')) && post('al') >= post('dal')) {
                for ($d = post('dal'), $i = 0; $d <= post('al') && $i < 62; $d = day_add($d, 1), $i++) {
                    night_toggle($v, $g, $d, true, true);
                }
                nights_assign($vid, $id, post('camera') !== '' ? (int) post('camera') : null);
                pf_event($vid, 'notti_assegnate', guest_name($g) . ' ' . post('dal') . ' → ' . post('al'));
                pf_flash('ok', 'Notti salvate.');
            } else {
                pf_flash('error', 'Controlla le date: la partenza non può venire prima dell\'arrivo.');
            }
            break;
        case 'togli_notte':
            if ($hotel && can('camere', $me) && valid_day(post('giorno'))) {
                night_toggle($v, $g, post('giorno'), false, true);
                pf_flash('ok', 'Notte tolta.');
            }
            break;
    }
    redirect($self);
}

$statusTag = ui_tag(['confermato' => 'Confermato', 'attesa' => 'Da confermare', 'bloccato' => 'Sospeso'][$g['status']] ?? '', ['confermato' => 'ok', 'attesa' => 'zaff', 'bloccato' => 'err'][$g['status']] ?? '');
$actions = '<a class="btn btn-line" href="ospiti.php">' . icon('back', 18) . 'Tutti gli ospiti</a>';
ui_staff_start(guest_name($g), 'ospiti.php', ['eyebrow' => 'Scheda ospite' . ($g['company'] ? ' · ' . $g['company'] : ''), 'actions' => $actions]);
echo '<div class="row">' . $statusTag . '<span class="muted small">Registrato il ' . e(data_it((string) $g['created_at'], false)) . ' · ultimo accesso: ' . e($g['last_login_at'] ? data_it((string) $g['last_login_at']) : 'mai') . '</span></div>';

echo '<div class="grid-2"><div class="stack">';

/* ---------- Dati ---------- */
echo '<section class="card"><div class="card-head"><h2>Dati</h2></div><form method="post" action="ospite.php" class="form" novalidate>' . csrf_field()
    . '<input type="hidden" name="azione" value="salva"><input type="hidden" name="id" value="' . $id . '"><div class="form-grid">'
    . ui_field('first_name', 'Nome', (string) $g['first_name'], ['required' => true]) . ui_field('last_name', 'Cognome', (string) $g['last_name'], ['required' => true])
    . ui_field('phone', 'Cellulare', phone_show((string) $g['phone']), ['type' => 'tel', 'required' => true, 'hint' => 'È il suo nome utente.']) . ui_field('email', 'Email', (string) $g['email'], ['type' => 'email'])
    . (feature('societa', $v) ? ui_field('company', 'Società', (string) ($g['company'] ?? ''), ['attrs' => 'list="elenco-societa" autocomplete="off"', 'class' => 'full']) : '') . '</div>';
if (can('note', $me)) {
    $dl = (array) $v['settings']['diets'];
    echo '<div class="field"><span class="label">Diete o intolleranze</span>' . ui_chips('diets[]', $dl ? array_combine($dl, $dl) : [], guest_diets($g)) . '</div>'
        . ui_field('needs', 'Altre esigenze scritte dall\'ospite', (string) $g['needs'], ['attrs' => 'maxlength="500"'])
        . ui_field('kitchen_note', 'Nota per la cucina', (string) $g['kitchen_note'], ['attrs' => 'maxlength="160"', 'placeholder' => 'Es. porzioni abbondanti', 'hint' => 'Compare accanto al nome in cucina e sul foglio stampato.'])
        . ui_field('staff_note', 'Nota interna', (string) $g['staff_note'], ['type' => 'textarea', 'hint' => 'La vede solo lo staff.']);
}
echo '<div><button type="submit" class="btn btn-dark">Salva i dati</button></div></form></section>';
if (feature('societa', $v)) {
    echo '<datalist id="elenco-societa">';
    foreach (companies_all($vid) as $c) {
        echo '<option value="' . e((string) $c['name']) . '">';
    }
    echo '</datalist>';
}

/* ---------- Pasti per suo conto ---------- */
$bk = bookings_of_guest($id, $days[0], $days[count($days) - 1]);
echo '<section class="card"><div class="card-head"><h2>Pasti dei prossimi giorni</h2><p>Per chi prenota per telefono: valgono anche dopo l\'orario limite</p></div>'
    . '<form method="post" action="ospite.php">' . csrf_field() . '<input type="hidden" name="azione" value="pasti"><input type="hidden" name="id" value="' . $id . '"><div class="meal-list">';
foreach ($days as $d) {
    echo '<div class="meal-day"><div class="meal-date"><b>' . e(PF_DAYS_SHORT[(int) date('N', (int) strtotime($d))]) . ' ' . (int) date('j', (int) strtotime($d)) . '</b>' . e(mb_substr(PF_MONTHS[(int) date('n', (int) strtotime($d))], 0, 3)) . '</div>';
    foreach (array_keys(PF_MEALS) as $m) {
        if (!in_array($m, $meals, true)) {
            echo '<span></span>';
            continue;
        }
        $b = $bk[$d . '|' . $m] ?? null;
        $on = booking_active($b);
        $state = $b ? ['confermato' => 'confermato', 'prenotato' => 'prenotato', 'disdetto' => 'disdetto', 'scaduto' => 'senza risposta'][$b['status']] ?? '' : '';
        echo '<label class="mealbox' . ($b && $b['status'] === 'confermato' ? ' answered' : '') . '"><input type="checkbox" name="pasto[' . $d . '|' . $m . ']" value="1"' . ($on ? ' checked' : '') . '>'
            . '<span>' . PF_MEALS[$m] . ($state !== '' ? '<small>' . e($state) . '</small>' : '') . '</span></label>';
    }
    echo '</div>';
}
echo '</div><p><button type="submit" class="btn btn-dark">Salva i pasti</button></p></form></section>';

/* ---------- Notti ---------- */
if ($hotel) {
    $nights = q('SELECT n.*, r.name AS room FROM ' . t('nights') . ' n LEFT JOIN ' . t('rooms') . " r ON r.id = n.room_id WHERE n.guest_id = ? AND n.day >= ? AND n.status <> 'disdetta' ORDER BY n.day", [$id, today()])->fetchAll();
    echo '<section class="card"><div class="card-head"><h2>Notti</h2><p>Da oggi in poi</p></div>';
    if ($nights) {
        echo '<div class="tags">';
        foreach ($nights as $n) {
            $label = day_it((string) $n['day'], 'short') . ($n['room'] ? ' · cam. ' . $n['room'] : '') . ($n['status'] === 'richiesta' ? ' · da confermare' : '');
            echo can('camere', $me)
                ? ui_post_btn('ospite.php', ['azione' => 'togli_notte', 'id' => $id, 'giorno' => $n['day']], e($label) . ' ' . icon('x', 14), 'tag ' . ($n['status'] === 'richiesta' ? 'tag-zaff' : 'tag-diet') . ' link-tag', 'Togliere la notte di ' . day_it((string) $n['day'], 'short') . '?')
                : ui_tag($label, $n['status'] === 'richiesta' ? 'zaff' : 'diet');
        }
        echo '</div>';
    } else {
        echo '<p class="muted small">Nessuna notte in programma.</p>';
    }
    if (can('camere', $me)) {
        echo '<form method="post" action="ospite.php" class="form">' . csrf_field() . '<input type="hidden" name="azione" value="notti"><input type="hidden" name="id" value="' . $id . '"><div class="form-grid">'
            . ui_field('dal', 'Prima notte', today(), ['type' => 'date', 'required' => true]) . ui_field('al', 'Ultima notte', today(), ['type' => 'date', 'required' => true]);
        $opts = ['' => '— da assegnare'];
        foreach (rooms_all($vid) as $room) {
            $opts[(string) $room['id']] = (string) $room['name'] . ' (' . (int) $room['beds'] . ((int) $room['beds'] === 1 ? ' posto' : ' posti') . ')';
        }
        echo ui_field('camera', 'Camera', (string) ($nights[0]['room_id'] ?? ''), ['type' => 'select', 'options' => $opts, 'class' => 'full', 'hint' => 'Vale per tutte le notti da oggi in poi.'])
            . '</div><div><button type="submit" class="btn btn-dark">Salva notti e camera</button></div></form>';
    }
    echo '</section>';
}

echo '</div><div class="stack">';

/* ---------- Azioni ---------- */
echo '<section class="card card-cream"><div class="card-head"><h2>Azioni</h2></div><div class="stack-s">';
if ($g['status'] === 'attesa') {
    echo ui_post_btn('ospite.php', ['azione' => 'conferma', 'id' => $id], 'Conferma la registrazione', 'btn btn-dark btn-block');
}
if (feature('whatsapp', $v)) {
    echo '<a class="btn btn-line btn-block" target="_blank" rel="noopener" href="' . e(phone_wa((string) $g['phone'])) . '">' . icon('message', 18) . 'Scrivi su WhatsApp</a>';
}
echo '<a class="btn btn-line btn-block" href="tel:' . e((string) $g['phone']) . '">' . icon('phone', 18) . 'Chiama ' . e(phone_show((string) $g['phone'])) . '</a>'
    . ui_post_btn('ospite.php', ['azione' => 'password', 'id' => $id], 'Dai una nuova password', 'btn btn-line btn-block', 'Creare una nuova password provvisoria? Quella attuale smette di funzionare.')
    . ($g['status'] === 'bloccato'
        ? ui_post_btn('ospite.php', ['azione' => 'riattiva', 'id' => $id], 'Riattiva l\'accesso', 'btn btn-line btn-block')
        : ui_post_btn('ospite.php', ['azione' => 'sospendi', 'id' => $id], 'Sospendi l\'accesso', 'btn btn-line btn-block', 'Sospendere l\'accesso di ' . guest_name($g) . '?'))
    . ui_post_btn('ospite.php', ['azione' => 'elimina', 'id' => $id], 'Elimina l\'ospite', 'btn btn-danger btn-block', 'Eliminare ' . guest_name($g) . ' con tutte le sue prenotazioni? Non si può annullare.')
    . '</div></section>';

/* ---------- Storico ---------- */
$hist = q('SELECT * FROM ' . t('bookings') . ' WHERE guest_id = ? AND day < ? ORDER BY day DESC, meal LIMIT 30', [$id, today()])->fetchAll();
echo '<section class="card"><div class="card-head"><h2>Ultimi pasti</h2></div>';
if ($hist) {
    echo '<div class="list-line">';
    foreach ($hist as $b) {
        $c = choices_decode((string) $b['choices']);
        echo '<div><div class="row-between"><strong>' . e(day_it((string) $b['day'], 'short')) . ' · ' . PF_MEALS[$b['meal']] . '</strong>'
            . ui_tag(['confermato' => 'Confermato', 'prenotato' => 'Prenotato', 'disdetto' => 'Disdetto', 'scaduto' => 'Senza risposta'][$b['status']] ?? '', in_array($b['status'], ['confermato', 'prenotato'], true) ? 'ok' : 'soft') . '</div>'
            . (choices_text($c) !== '' ? '<p class="small muted">' . e(choices_text($c)) . '</p>' : '')
            . ($b['amount'] !== null && can('prezzi', $me) && booking_active($b) ? '<p class="small">' . e(euro((float) $b['amount'])) . '</p>' : '') . '</div>';
    }
    echo '</div>';
} else {
    echo '<p class="muted small">Ancora nessun pasto passato.</p>';
}
echo '</section></div></div>';
ui_shell_end();
