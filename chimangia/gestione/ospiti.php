<?php
/** Pannello del locale — Ospiti: elenco, ricerca, nuovo ospite, esportazione. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
require __DIR__ . '/_parts.php';
pf_boot('staff', '../');
$me = staff_require('conferma');
$v = $me['venue'];
$vid = (int) $v['id'];
$statuses = ['confermato' => 'Confermato', 'attesa' => 'Da confermare', 'bloccato' => 'Sospeso'];

$new = ['first_name' => '', 'last_name' => '', 'phone' => '', 'email' => '', 'company' => ''];
$formError = '';
if (is_post()) {
    pf_csrf_check();
    $new = ['first_name' => post('first_name'), 'last_name' => post('last_name'), 'phone' => post('phone'), 'email' => post('email'), 'company' => post('company')];
    $password = (string) random_int(100000, 999999);
    [$id, $formError] = guest_create($v, $new + ['password' => $password, 'diets' => $_POST['diets'] ?? [], 'needs' => post('needs')], true);
    if ($id !== null) {
        pf_event($vid, 'ospite_creato', trim($new['first_name'] . ' ' . $new['last_name']));
        pf_flash('ok', 'Ospite aggiunto. Password provvisoria da comunicargli: ' . $password . ' (può cambiarla dal suo profilo).');
        redirect('ospite.php?id=' . $id);
    }
}

$qStr = get_param('q');
$fCompany = (int) get_param('s');
$fStatus = isset($statuses[get_param('st')]) ? get_param('st') : '';
$where = ['g.venue_id = ?'];
$args = [$vid];
if ($qStr !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $qStr) . '%';
    $where[] = '(g.first_name LIKE ? OR g.last_name LIKE ? OR CONCAT(g.first_name, \' \', g.last_name) LIKE ? OR g.phone LIKE ? OR g.email LIKE ?)';
    array_push($args, $like, $like, $like, '%' . preg_replace('/\D/', '', $qStr) . '%', $like);
}
if ($fCompany > 0) {
    $where[] = 'g.company_id = ?';
    $args[] = $fCompany;
}
if ($fStatus !== '') {
    $where[] = 'g.status = ?';
    $args[] = $fStatus;
}
$rows = q(
    'SELECT g.*, c.name AS company FROM ' . t('guests') . ' g LEFT JOIN ' . t('companies') . ' c ON c.id = g.company_id
     WHERE ' . implode(' AND ', $where) . ' ORDER BY g.last_name, g.first_name LIMIT 500',
    $args
)->fetchAll();

if (get_param('csv') === '1') {
    $out = [];
    foreach ($rows as $r) {
        $out[] = [$r['last_name'], $r['first_name'], phone_show((string) $r['phone']), $r['email'], (string) ($r['company'] ?? ''),
            can('note', $me) ? implode(', ', guest_diets($r)) : '', can('note', $me) ? $r['needs'] : '', $statuses[$r['status']] ?? $r['status'], data_it((string) $r['created_at'], false)];
    }
    pf_event($vid, 'ospiti_esportati', count($out) . ' righe');
    csv_download('ospiti-' . $v['slug'] . '-' . date('Ymd') . '.csv', ['Cognome', 'Nome', 'Cellulare', 'Email', 'Società', 'Diete', 'Altre esigenze', 'Stato', 'Registrato il'], $out);
}

$companies = feature('societa', $v) ? companies_all($vid) : [];
$actions = '<a class="btn btn-line" href="ospiti.php?' . e(http_build_query(['q' => $qStr, 's' => $fCompany ?: '', 'st' => $fStatus, 'csv' => 1])) . '">' . icon('download', 18) . 'Esporta CSV</a>';
ui_staff_start('I tuoi *ospiti*', 'ospiti.php', ['actions' => $actions]);

echo '<details class="card card-cream"' . ($formError ? ' open' : '') . '><summary class="btn btn-dark">' . icon('plus', 18) . 'Nuovo ospite</summary>';
echo '<form method="post" action="ospiti.php" class="form" novalidate>' . csrf_field() . '<p class="muted small">Per chi non si registra da solo. Riceve una password provvisoria che gli comunichi tu.</p>'
    . ($formError ? '<div class="notice notice-error" role="alert">' . e($formError) . '</div>' : '') . '<div class="form-grid">'
    . ui_field('first_name', 'Nome', $new['first_name'], ['required' => true]) . ui_field('last_name', 'Cognome', $new['last_name'], ['required' => true])
    . ui_field('phone', 'Cellulare', $new['phone'], ['type' => 'tel', 'required' => true, 'hint' => 'È il suo nome utente per entrare.']) . ui_field('email', 'Email', $new['email'], ['type' => 'email'])
    . (feature('societa', $v) ? ui_field('company', 'Società', $new['company'], ['attrs' => 'list="elenco-societa" autocomplete="off"', 'class' => 'full']) : '')
    . '</div><div class="field"><span class="label">Diete o intolleranze</span>' . ui_chips('diets[]', array_combine((array) $v['settings']['diets'], (array) $v['settings']['diets']) ?: [], (array) ($_POST['diets'] ?? [])) . '</div>'
    . ui_field('needs', 'Altre esigenze', post('needs'), ['attrs' => 'maxlength="500"'])
    . '<div><button type="submit" class="btn btn-dark">Aggiungi ospite</button></div></form></details>';
if ($companies) {
    echo '<datalist id="elenco-societa">';
    foreach ($companies as $c) {
        echo '<option value="' . e((string) $c['name']) . '">';
    }
    echo '</datalist>';
}

echo '<form method="get" action="ospiti.php" class="row" role="search">'
    . '<label class="sr-only" for="q">Cerca</label><input class="grow" type="search" id="q" name="q" value="' . e($qStr) . '" placeholder="Cerca per nome, cellulare o email">';
if ($companies) {
    echo '<label class="sr-only" for="s">Società</label><select class="grow" id="s" name="s" data-autosubmit><option value="">Tutte le società</option>';
    foreach ($companies as $c) {
        echo '<option value="' . (int) $c['id'] . '"' . ($fCompany === (int) $c['id'] ? ' selected' : '') . '>' . e((string) $c['name']) . '</option>';
    }
    echo '</select>';
}
echo '<label class="sr-only" for="st">Stato</label><select class="grow" id="st" name="st" data-autosubmit><option value="">Tutti gli stati</option>';
foreach ($statuses as $k => $lab) {
    echo '<option value="' . $k . '"' . ($fStatus === $k ? ' selected' : '') . '>' . e($lab) . '</option>';
}
echo '</select><button type="submit" class="btn btn-line">' . icon('search', 18) . 'Cerca</button></form>';

echo '<section class="card">';
if ($rows) {
    echo '<div class="table-wrap"><table class="table stack-sm"><thead><tr><th>Ospite</th><th>Cellulare</th>' . ($companies ? '<th>Società</th>' : '') . (can('note', $me) ? '<th>Diete e note</th>' : '') . '<th>Stato</th><th>Ultimo accesso</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr><td data-th="Ospite"><a href="ospite.php?id=' . (int) $r['id'] . '"><strong>' . e(guest_name($r)) . '</strong></a></td>'
            . '<td data-th="Cellulare" class="nowrap">' . e(phone_show((string) $r['phone'])) . '</td>'
            . ($companies ? '<td data-th="Società">' . e((string) ($r['company'] ?? '—')) . '</td>' : '')
            . (can('note', $me) ? '<td data-th="Diete e note"><div class="tags">' . (ui_guest_tags(guest_tags($r)) ?: '<span class="muted">—</span>') . '</div></td>' : '')
            . '<td data-th="Stato">' . ui_tag($statuses[$r['status']] ?? (string) $r['status'], ['confermato' => 'ok', 'attesa' => 'zaff', 'bloccato' => 'err'][$r['status']] ?? '') . '</td>'
            . '<td data-th="Ultimo accesso">' . e($r['last_login_at'] ? data_it((string) $r['last_login_at']) : 'mai') . '</td></tr>';
    }
    echo '</tbody></table></div><p class="small muted">' . count($rows) . (count($rows) === 1 ? ' ospite' : ' ospiti') . (count($rows) === 500 ? ' (primi 500: restringi la ricerca)' : '') . '</p>';
} else {
    echo ui_empty($qStr !== '' || $fCompany || $fStatus ? 'Nessun ospite corrisponde alla ricerca.' : 'Ancora nessun ospite. Manda il collegamento ai tuoi clienti oppure aggiungili da qui.', 'users');
}
echo '</section>';
ui_shell_end();
