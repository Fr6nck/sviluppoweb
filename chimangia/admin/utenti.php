<?php
/** Utenti del pannello: creazione, modifica, ruolo, disattivazione. Solo Amministratore. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

$me = require_role('admin');
$editId = (int) (is_post() ? post('id') : get_param('id'));
$isNew = get_param('nuovo') === '1' || (is_post() && $editId === 0);
$errors = [];
$form = ['name' => '', 'email' => '', 'role' => 'editor', 'active' => true];

if ($editId > 0) {
    $u = q_one('SELECT * FROM ' . t('users') . ' WHERE id = ?', [$editId]);
    if (!$u) {
        admin_message_page('Utente non trovato', 'L\'utente richiesto non esiste.');
    }
    $form = ['name' => $u['name'], 'email' => $u['email'], 'role' => $u['role'], 'active' => (bool) $u['active']];
}

if (is_post()) {
    csrf_check();
    $form = [
        'name' => clean_text(post('name'), 120),
        'email' => mb_strtolower(clean_text(post('email'), 190)),
        'role' => isset(CM_ROLES[post('role')]) ? post('role') : 'editor',
        'active' => post('active') === '1',
    ];
    $pass = (string) ($_POST['password'] ?? '');
    if ($form['name'] === '') {
        $errors['name'] = 'Scrivi il nome.';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Indirizzo email non valido.';
    } elseif ((int) q_value('SELECT COUNT(*) FROM ' . t('users') . ' WHERE email = ? AND id <> ?', [$form['email'], $editId]) > 0) {
        $errors['email'] = 'Esiste già un utente con questa email.';
    }
    if ($editId === 0 || $pass !== '') {
        if ($p = password_problem($pass)) {
            $errors['password'] = $p;
        }
    }
    if ($editId === (int) $me['id'] && ($form['role'] !== 'admin' || !$form['active'])) {
        $errors['role'] = 'Non puoi togliere a te stesso il ruolo di Amministratore né disattivarti.';
        $form['role'] = 'admin';
        $form['active'] = true;
    }
    if ($editId > 0 && ($form['role'] !== 'admin' || !$form['active'])) {
        $admins = (int) q_value('SELECT COUNT(*) FROM ' . t('users') . " WHERE role = 'admin' AND active = 1 AND id <> ?", [$editId]);
        if ($admins === 0) {
            $errors['role'] = 'Deve restare almeno un Amministratore attivo.';
        }
    }

    if (!$errors) {
        if ($editId === 0) {
            q(
                'INSERT INTO ' . t('users') . ' (name, email, password_hash, role, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$form['name'], $form['email'], password_hash($pass, PASSWORD_DEFAULT), $form['role'], $form['active'] ? 1 : 0, now(), now()]
            );
            audit('utente_creato', $form['email'] . ' (' . CM_ROLES[$form['role']] . ')');
            flash('ok', 'Utente creato. Comunicagli email e password in modo sicuro: potrà cambiarla dal suo profilo.');
        } else {
            q('UPDATE ' . t('users') . ' SET name = ?, email = ?, role = ?, active = ?, updated_at = ? WHERE id = ?', [$form['name'], $form['email'], $form['role'], $form['active'] ? 1 : 0, now(), $editId]);
            if ($pass !== '') {
                q('UPDATE ' . t('users') . ' SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $editId]);
            }
            audit('utente_modificato', $form['email'] . ': ' . CM_ROLES[$form['role']] . ', ' . ($form['active'] ? 'attivo' : 'disattivato') . ($pass !== '' ? ', password cambiata' : ''));
            flash('ok', 'Utente aggiornato.');
        }
        redirect('utenti.php');
    }
    flash('error', 'Controlla i campi evidenziati.');
}

admin_header('Utenti', 'utenti.php');

if ($editId > 0 || $isNew): ?>
  <p><a href="utenti.php">← Torna agli utenti</a></p>
  <form method="post" action="utenti.php" class="form panel" novalidate data-dirty-check>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $editId ?>">
    <h2><?= $editId > 0 ? 'Modifica utente' : 'Nuovo utente' ?></h2>
    <?= field('name', 'Nome', $form['name'], ['required' => true, 'error' => $errors['name'] ?? '']) ?>
    <?= field('email', 'Email', $form['email'], ['type' => 'email', 'required' => true, 'error' => $errors['email'] ?? '']) ?>
    <?= field('role', 'Ruolo', $form['role'], ['type' => 'select', 'options' => CM_ROLES, 'error' => $errors['role'] ?? '',
        'hint' => 'Amministratore: tutto, compresi utenti e impostazioni. Redattore: contenuti, prezzi, domande frequenti, richieste.']) ?>
    <?= field('active', 'Utente attivo (può accedere)', $form['active'], ['type' => 'switch']) ?>
    <?= field('password', $editId > 0 ? 'Nuova password (lascia vuoto per non cambiarla)' : 'Password', '', ['type' => 'password', 'required' => $editId === 0,
        'hint' => 'Almeno 10 caratteri, con lettere e numeri.', 'error' => $errors['password'] ?? '', 'attrs' => 'autocomplete="new-password"']) ?>
    <div class="actions-bar"><button type="submit" class="btn btn-dark">Salva</button><a class="btn btn-line" href="utenti.php">Annulla</a></div>
  </form>
<?php else:
    $rows = q('SELECT * FROM ' . t('users') . ' ORDER BY active DESC, name')->fetchAll(); ?>
  <p><a class="btn btn-dark" href="utenti.php?nuovo=1">+ Nuovo utente</a></p>
  <div class="table-wrap"><table>
    <thead><tr><th scope="col">Nome</th><th scope="col">Email</th><th scope="col">Ruolo</th><th scope="col">Stato</th><th scope="col">Ultimo accesso</th><th scope="col"><span class="sr-only">Azioni</span></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['name']) ?><?= (int) $r['id'] === (int) $me['id'] ? ' (tu)' : '' ?></td>
        <td><?= e($r['email']) ?></td>
        <td><?= e(CM_ROLES[$r['role']] ?? $r['role']) ?></td>
        <td><?= (int) $r['active'] ? 'Attivo' : 'Disattivato' ?></td>
        <td><?= e(data_it($r['last_login_at'])) ?></td>
        <td><a class="btn btn-line btn-small" href="utenti.php?id=<?= (int) $r['id'] ?>">Modifica<span class="sr-only"> <?= e($r['name']) ?></span></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif;
admin_footer();
