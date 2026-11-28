<?php
/** Pannello del locale — Staff: chi entra nel pannello e con quale ruolo. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
require __DIR__ . '/_parts.php';
pf_boot('staff', '../');
$me = staff_require('staff');
$v = $me['venue'];
$vid = (int) $v['id'];
$roles = pf_roles();

if (is_post()) {
    pf_csrf_check();
    $id = (int) post('id');
    $target = $id ? q_one('SELECT * FROM ' . t('staff') . ' WHERE id = ? AND venue_id = ?', [$id, $vid]) : null;
    switch (post('azione')) {
        case 'nuovo':
            $name = clean_text(post('name'), 120);
            $email = mb_strtolower(post('email'));
            $role = isset($roles[post('role')]) ? post('role') : 'reception';
            if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                pf_flash('error', 'Servono nome e un indirizzo email valido.');
            } elseif (q_value('SELECT 1 FROM ' . t('staff') . ' WHERE email = ?', [$email])) {
                pf_flash('error', 'Questa email è già usata da un altro accesso.');
            } else {
                q('INSERT INTO ' . t('staff') . ' (venue_id, name, email, password_hash, role, is_owner, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 0, 1, ?, ?)',
                    [$vid, $name, $email, password_hash(random_token(24), PASSWORD_DEFAULT), $role, now(), now()]);
                $new = q_one('SELECT * FROM ' . t('staff') . ' WHERE email = ?', [$email]);
                $token = pf_token_create('staff_reset', (int) $new['id'], 7 * 86400);
                $link = site_url('accedi/reimposta.php?t=' . $token);
                pf_mail_queue($vid, $email, 'Il tuo accesso a ' . $v['name'], "Ciao $name,\n\n" . $me['name'] . ' ti ha aggiunto al pannello di ' . $v['name'] . ' su ChiMangia (' . $roles[$role] . ").\nScegli la tua password da qui (vale 7 giorni):\n$link\n\nPoi entri da: " . site_url('accedi/') . "\n\nChiMangia");
                pf_event($vid, 'staff_aggiunto', $name . ' · ' . $roles[$role]);
                pf_flash('ok', $name . ' aggiunto. Gli abbiamo scritto per scegliere la password. Se l\'email non arriva, mandagli tu questo collegamento (vale 7 giorni): ' . $link);
            }
            break;
        case 'salva':
            if ($target && !(int) $target['is_owner']) {
                $role = isset($roles[post('role')]) ? post('role') : (string) $target['role'];
                $active = post('active') === '1' || (int) $target['id'] === (int) $me['id'] ? 1 : 0;   // non ci si spegne da soli
                q('UPDATE ' . t('staff') . ' SET name = ?, role = ?, active = ?, updated_at = ? WHERE id = ?', [clean_text(post('name'), 120) ?: $target['name'], $role, $active, now(), $id]);
                pf_event($vid, 'staff_modificato', (string) $target['name']);
                pf_flash('ok', 'Accesso aggiornato.');
            }
            break;
        case 'password':
            if ($target) {
                staff_reset_send($target);
                pf_flash('ok', 'Abbiamo scritto a ' . $target['email'] . ' con il collegamento per scegliere una nuova password.');
            }
            break;
        case 'elimina':
            if ($target && !(int) $target['is_owner'] && (int) $target['id'] !== (int) $me['id']) {
                q('DELETE FROM ' . t('staff') . ' WHERE id = ?', [$id]);
                pf_event($vid, 'staff_eliminato', (string) $target['name']);
                pf_flash('info', 'Accesso di ' . $target['name'] . ' eliminato.');
            }
            break;
    }
    redirect('staff.php');
}

$rows = q('SELECT * FROM ' . t('staff') . ' WHERE venue_id = ? ORDER BY is_owner DESC, name', [$vid])->fetchAll();
ui_staff_start('Lo *staff*', 'staff.php', ['lead' => 'Ognuno entra con la sua email e vede solo quello che gli serve.']);

echo '<div class="grid-2"><section class="card"><div class="card-head"><h2>Chi può entrare</h2></div><div class="list-line">';
foreach ($rows as $r) {
    $rid = (int) $r['id'];
    $owner = (int) $r['is_owner'] === 1;
    $head = '<span><strong>' . e((string) $r['name']) . '</strong> ' . ui_tag($owner ? 'Titolare' : ($roles[$r['role']] ?? ''), $owner ? 'diet' : '') . (!(int) $r['active'] ? ' ' . ui_tag('Spento', 'err') : '')
        . '<span class="sub">' . e((string) $r['email']) . ' · ultimo accesso: ' . e($r['last_login_at'] ? data_it((string) $r['last_login_at']) : 'mai') . '</span></span>';
    if ($owner) {
        echo '<div class="row-between">' . $head . '<span class="muted small">Ha sempre tutti i permessi</span></div>';
        continue;
    }
    echo '<details><summary class="row-between">' . $head . '<span class="muted small">Modifica</span></summary><form method="post" action="staff.php" class="form">' . csrf_field()
        . '<input type="hidden" name="azione" value="salva"><input type="hidden" name="id" value="' . $rid . '"><div class="form-grid">'
        . ui_field('name', 'Nome', (string) $r['name'], ['id' => 'sn' . $rid]) . ui_field('role', 'Ruolo', (string) $r['role'], ['type' => 'select', 'options' => $roles, 'id' => 'sr' . $rid]) . '</div>'
        . ui_switch('active', 'Accesso attivo', (int) $r['active'] === 1, 'Spegnilo quando una persona non lavora più con te.', '1')
        . '<div class="row"><button type="submit" class="btn btn-small btn-dark">Salva</button></div></form><div class="row">'
        . ui_post_btn('staff.php', ['azione' => 'password', 'id' => $rid], 'Manda il collegamento per la password')
        . ($rid !== (int) $me['id'] ? ui_post_btn('staff.php', ['azione' => 'elimina', 'id' => $rid], 'Elimina', 'btn btn-small btn-danger', 'Eliminare l\'accesso di ' . $r['name'] . '?') : '') . '</div></details>';
}
echo '</div></section>';

echo '<div class="stack"><section class="card card-cream"><div class="card-head"><h2>Aggiungi una persona</h2></div><form method="post" action="staff.php" class="form">' . csrf_field()
    . '<input type="hidden" name="azione" value="nuovo">' . ui_field('name', 'Nome e cognome', '', ['required' => true, 'attrs' => 'maxlength="120"'])
    . ui_field('email', 'Email', '', ['type' => 'email', 'required' => true, 'hint' => 'Riceve un collegamento per scegliere la password.'])
    . ui_field('role', 'Ruolo', 'reception', ['type' => 'select', 'options' => $roles]) . '<div><button type="submit" class="btn btn-dark">' . icon('plus', 18) . 'Aggiungi</button></div></form></section>';

echo '<section class="card"><div class="card-head"><h2>Cosa può fare ogni ruolo</h2></div><div class="table-wrap"><table class="table matrix"><thead><tr><th>Permesso</th>';
foreach ($roles as $lab) {
    echo '<th>' . e($lab) . '</th>';
}
echo '</tr></thead><tbody>';
foreach (pf_perms() as $p => $lab) {
    echo '<tr><td>' . e($lab) . '</td>';
    foreach (array_keys($roles) as $role) {
        echo '<td>' . (!empty($v['perms'][$p . ':' . $role]) ? icon('check', 18) . '<span class="sr-only">sì</span>' : '<span class="muted">—</span><span class="sr-only">no</span>') . '</td>';
    }
    echo '</tr>';
}
echo '</tbody></table></div><p class="small muted">Per cambiare i permessi di un ruolo scrivici: li regoliamo noi per il tuo locale.</p></section></div></div>';
ui_shell_end();
