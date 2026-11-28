<?php
/**
 * Super admin · Configurazione di un cliente: dati e stato, funzioni attive, permessi dei ruoli,
 * utilizzo, staff, registro, ingresso nel pannello per assistenza, esportazione ed eliminazione.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
require_once CM_APP . '/platform/maint.php';
pf_boot('super', '../');
require __DIR__ . '/_guard.php';

$id = (int) (is_post() ? post('id', get_param('id')) : get_param('id'));
$v = venue_get($id);
if (!$v) {
    http_response_code(404);
    ui_plain_page('Cliente non trovato', '<p class="lead">Questo locale non esiste oppure è stato eliminato.</p><p><a class="btn btn-line" href="index.php">' . icon('back', 16) . 'Torna ai clienti</a></p>');
    exit;
}
$self = 'cliente.php?id=' . $id;
$plans = pf_plans();
$form = [
    'name' => (string) $v['name'], 'kind' => (string) $v['kind'], 'email' => (string) $v['email'], 'phone' => (string) $v['phone'],
    'address' => (string) $v['address'], 'vat' => (string) $v['vat'], 'plan' => (string) $v['plan'], 'billing' => (string) $v['billing'],
    'status' => (string) $v['status'], 'trial' => $v['trial_ends_at'] ? substr((string) $v['trial_ends_at'], 0, 10) : '', 'notes' => (string) $v['notes'],
];
$error = '';

if (is_post()) {
    pf_csrf_check();
    $setStatus = static function (string $status, ?string $trialEnd = null) use ($id, $v): void {
        if ($trialEnd !== null) {
            q('UPDATE ' . t('venues') . ' SET status = ?, trial_ends_at = ?, updated_at = ? WHERE id = ?', [$status, $trialEnd, now(), $id]);
        } else {
            q('UPDATE ' . t('venues') . ' SET status = ?, updated_at = ? WHERE id = ?', [$status, now(), $id]);
        }
        $labels = pf_statuses();
        pf_event($id, 'stato_cambiato', ($labels[$v['status']] ?? $v['status']) . ' → ' . ($labels[$status] ?? $status) . ($trialEnd !== null ? ' · prova fino al ' . data_it($trialEnd, false) : ''));
        audit('piattaforma_stato', $v['name'] . ': ' . $v['status'] . ' → ' . $status);
    };
    switch (post('azione')) {
        case 'dati':
            $form = [
                'name' => clean_text(post('name'), 160), 'kind' => post('kind'), 'email' => mb_strtolower(post('email')), 'phone' => clean_text(post('phone'), 40),
                'address' => clean_text(post('address'), 255), 'vat' => clean_text(post('vat'), 40), 'plan' => post('plan'), 'billing' => post('billing'),
                'status' => post('status'), 'trial' => post('trial'), 'notes' => clean_lines(post('notes'), 5000),
            ];
            if (mb_strlen($form['name']) < 2) {
                $error = 'Scrivi il nome del locale.';
            } elseif (!isset(pf_kinds()[$form['kind']]) || !isset($plans[$form['plan']]) || !isset(pf_statuses()[$form['status']]) || !in_array($form['billing'], ['mensile', 'annuale'], true)) {
                $error = 'Controlla tipo, piano, fatturazione e stato.';
            } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || strlen($form['email']) > 190) {
                $error = 'L\'indirizzo email del locale non è valido.';
            } elseif ($form['trial'] !== '' && !valid_day($form['trial'])) {
                $error = 'La data di fine prova non è valida.';
            } elseif ($form['status'] === 'prova' && $form['trial'] === '') {
                $error = 'Per un locale in prova indica la data di fine prova.';
            } else {
                q(
                    'UPDATE ' . t('venues') . ' SET name = ?, kind = ?, email = ?, phone = ?, address = ?, vat = ?, plan = ?, billing = ?, status = ?, trial_ends_at = ?, notes = ?, updated_at = ? WHERE id = ?',
                    [
                        $form['name'], $form['kind'], $form['email'], $form['phone'], $form['address'], $form['vat'], $form['plan'], $form['billing'],
                        $form['status'], $form['trial'] !== '' ? $form['trial'] . ' 23:59:59' : null, $form['notes'], now(), $id,
                    ]
                );
                $changes = [];
                foreach (['name' => 'nome', 'status' => 'stato', 'plan' => 'piano', 'billing' => 'fatturazione'] as $k => $label) {
                    if ($form[$k] !== (string) $v[$k]) {
                        $changes[] = $label . ': ' . $v[$k] . ' → ' . $form[$k];
                    }
                }
                pf_event($id, 'dati_aggiornati', $changes ? implode(' · ', $changes) : 'dati del locale');
                if ($form['status'] !== (string) $v['status']) {
                    audit('piattaforma_stato', $form['name'] . ': ' . $v['status'] . ' → ' . $form['status']);
                }
                pf_flash('ok', 'Dati salvati.');
                redirect($self);
            }
            break;

        case 'prova7':
            if ($v['status'] !== 'prova') {
                pf_flash('error', 'Il locale non è in prova: per riportarlo in prova usa "Dati e stato".');
            } else {
                $base = max(time(), $v['trial_ends_at'] ? (int) strtotime((string) $v['trial_ends_at']) : 0);
                $setStatus('prova', date('Y-m-d H:i:s', $base + 7 * 86400));
                pf_flash('ok', 'Prova prolungata di 7 giorni.');
            }
            redirect($self);
            // no break
        case 'attiva':
            $setStatus('attivo');
            pf_flash('ok', 'Locale attivo: staff e ospiti possono lavorare senza limiti di tempo.');
            redirect($self);
            // no break
        case 'sospendi':
            $setStatus('sospeso');
            pf_flash('ok', 'Locale sospeso: lo staff vede solo la pagina dell\'abbonamento, gli ospiti un avviso.');
            redirect($self);
            // no break
        case 'verifica_email':
            q('UPDATE ' . t('venues') . ' SET email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?', [now(), $id]);
            pf_event($id, 'email_confermata', 'segnata dal fornitore');
            pf_flash('ok', 'Email segnata come confermata.');
            redirect($self);
            // no break
        case 'entra':
            $token = pf_token_create('entra', $id, 60);
            audit('piattaforma_ingresso', $v['name']);
            redirect('../gestione/entra.php?t=' . rawurlencode($token));
            // no break
        case 'funzioni':
            $features = super_read_features();
            venue_patch($id, 'features', $features);
            $on = array_keys(array_filter($features));
            pf_event($id, 'funzioni_aggiornate', count($on) . ' su ' . count($features) . ' accese: ' . implode(', ', $on));
            pf_flash('ok', 'Funzioni aggiornate: ' . count($on) . ' su ' . count($features) . ' accese.');
            redirect($self . '#funzioni');
            // no break
        case 'permessi':
            venue_patch($id, 'perms', super_read_perms());
            pf_event($id, 'permessi_aggiornati', 'matrice dei ruoli');
            pf_flash('ok', 'Permessi dei ruoli aggiornati.');
            redirect($self . '#permessi');
            // no break
        case 'password':
            $s = q_one('SELECT * FROM ' . t('staff') . ' WHERE id = ? AND venue_id = ? AND active = 1', [(int) post('staff'), $id]);
            if ($s) {
                staff_reset_send($s);
                pf_event($id, 'collegamento_password', (string) $s['email']);
                pf_flash('ok', 'Collegamento per la nuova password inviato a ' . $s['email'] . ' (vale un\'ora).');
            } else {
                pf_flash('error', 'Utente non trovato o disattivato.');
            }
            redirect($self . '#staff');
            // no break
        case 'esporta':
            $data = venue_export($id);
            pf_event($id, 'dati_esportati', 'esportazione JSON');
            audit('piattaforma_esportazione', $v['name']);
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="chimangia-' . $v['slug'] . '-' . date('Ymd-Hi') . '.json"');
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            exit;

        case 'elimina':
            if (post('conferma') !== (string) $v['name']) {
                pf_flash('error', 'Il nome scritto non corrisponde: il locale non è stato eliminato.');
                redirect($self . '#elimina');
            }
            venue_delete($id);
            pf_event(null, 'locale_eliminato', $v['name'] . ' (' . $v['slug'] . ')');
            audit('piattaforma_locale_eliminato', $v['name'] . ' (' . $v['slug'] . ', ' . $v['email'] . ')');
            pf_flash('ok', 'Il locale «' . $v['name'] . '» è stato eliminato con tutti i suoi dati.');
            redirect('index.php');
            // no break
        default:
            redirect($self);
    }
}

/* ---------- Numeri del locale ---------- */
$limit = (int) ($plans[$v['plan']][1] ?? 0);
$used = venue_usage($id);
$guests = q_one('SELECT COUNT(*) AS n, COALESCE(SUM(status = \'attesa\'), 0) AS wait FROM ' . t('guests') . ' WHERE venue_id = ?', [$id]);
$meals = (int) q_value(
    'SELECT COUNT(*) FROM ' . t('bookings') . " WHERE venue_id = ? AND day BETWEEN ? AND ? AND status IN ('prenotato', 'confermato')",
    [$id, day_add(today(), -30), today()]
);
$staff = q('SELECT id, name, email, role, is_owner, active, last_login_at FROM ' . t('staff') . ' WHERE venue_id = ? ORDER BY is_owner DESC, active DESC, name', [$id])->fetchAll();
$lastLogin = (string) (q_value('SELECT MAX(last_login_at) FROM ' . t('staff') . ' WHERE venue_id = ?', [$id]) ?? '');
$events = q('SELECT * FROM ' . t('events') . ' WHERE venue_id = ? ORDER BY id DESC LIMIT 30', [$id])->fetchAll();
$days = venue_trial_days($v);
$featuresOn = count(array_filter($v['features']));

$enter = '<form method="post" action="cliente.php" class="inline-form">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="azione" value="entra">'
    . '<button type="submit" class="btn btn-dark">' . icon('eye', 18) . 'Entra nel pannello</button></form>';
ui_super_start($v['name'], 'index.php', [
    'eyebrow' => 'Configurazione del cliente',
    'actions' => '<a class="btn btn-line" href="index.php">' . icon('back', 18) . 'Clienti</a>' . $enter,
]);
$quick = static fn (string $az, string $label, string $class = 'btn btn-small btn-line', string $confirm = ''): string
    => ui_post_btn('cliente.php', ['id' => $id, 'azione' => $az], $label, $class, $confirm);

echo '<div class="kpis">'
    . ui_kpi('Stato', pf_statuses()[$v['status']] ?? (string) $v['status'], $days !== null ? ($days > 0 ? ($days === 1 ? 'manca 1 giorno' : 'mancano ' . $days . ' giorni') : 'prova scaduta') : ($plans[$v['plan']][0] ?? ''), $v['status'] === 'attivo' ? 'navy' : ($v['status'] === 'prova' ? 'zaff' : ''))
    . ui_kpi('Clienti del mese', $used, 'su ' . $limit . ' compresi nel piano')
    . ui_kpi('Ospiti registrati', (int) $guests['n'], (int) $guests['wait'] > 0 ? (int) $guests['wait'] . ' da confermare' : 'tutti confermati')
    . ui_kpi('Pasti prenotati', number_format($meals, 0, ',', '.'), 'negli ultimi 30 giorni')
    . '</div>';
?>
<div class="grid-2">
  <section class="card card-white" id="dati">
    <div class="card-head"><h2>Dati e stato</h2><p>Codice: <?= e($v['slug']) ?></p></div>
    <?php if ($error !== ''): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="cliente.php" class="form" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="azione" value="dati">
      <div class="form-grid">
        <?= ui_field('name', 'Nome del locale', $form['name'], ['required' => true, 'attrs' => 'maxlength="160"']) ?>
        <?= ui_field('kind', 'Tipo di locale', $form['kind'], ['type' => 'select', 'options' => pf_kinds()]) ?>
        <?= ui_field('email', 'Email del locale', $form['email'], ['type' => 'email', 'required' => true]) ?>
        <?= ui_field('phone', 'Telefono', $form['phone'], ['type' => 'tel', 'attrs' => 'maxlength="40"']) ?>
        <?= ui_field('address', 'Indirizzo', $form['address'], ['attrs' => 'maxlength="255"']) ?>
        <?= ui_field('vat', 'Partita IVA', $form['vat'], ['attrs' => 'maxlength="40"']) ?>
        <?= ui_field('plan', 'Piano', $form['plan'], ['type' => 'select', 'options' => array_map(static fn ($p) => $p[0], $plans)]) ?>
        <?= ui_field('billing', 'Fatturazione', $form['billing'], ['type' => 'select', 'options' => ['mensile' => 'Mensile', 'annuale' => 'Annuale']]) ?>
        <?= ui_field('status', 'Stato', $form['status'], ['type' => 'select', 'options' => pf_statuses()]) ?>
        <?= ui_field('trial', 'Fine della prova', $form['trial'], ['type' => 'date', 'hint' => 'Conta solo per i locali in prova.']) ?>
        <?= ui_field('notes', 'Note interne', $form['notes'], ['type' => 'textarea', 'rows' => 4, 'class' => 'full', 'hint' => 'Le vede solo il fornitore, mai il locale.']) ?>
      </div>
      <div class="row"><button type="submit" class="btn btn-dark">Salva i dati</button></div>
    </form>
  </section>

  <div class="stack">
    <section class="card card-navy">
      <div class="card-head"><h2>Azioni rapide</h2><span><?= super_status_tag($v) ?></span></div>
      <div class="row">
        <?php if ($v['status'] === 'prova'): ?>
          <?= $quick('prova7', icon('clock', 16) . 'Prolunga la prova di 7 giorni', 'btn btn-small btn-zaff') ?>
          <?= $quick('attiva', icon('check', 16) . 'Attiva', 'btn btn-small btn-ghost') ?>
          <?= $quick('sospendi', 'Sospendi', 'btn btn-small btn-ghost', 'Sospendere ' . $v['name'] . '? Staff e ospiti non potranno più usarlo finché non lo riattivi.') ?>
        <?php elseif ($v['status'] === 'attivo'): ?>
          <?= $quick('sospendi', 'Sospendi', 'btn btn-small btn-ghost', 'Sospendere ' . $v['name'] . '? Staff e ospiti non potranno più usarlo finché non lo riattivi.') ?>
        <?php else: ?>
          <?= $quick('attiva', icon('check', 16) . 'Riattiva', 'btn btn-small btn-zaff') ?>
        <?php endif; ?>
        <?php if ($v['email_verified_at'] === null): ?>
          <?= $quick('verifica_email', 'Segna l\'email come confermata', 'btn btn-small btn-ghost') ?>
        <?php endif; ?>
      </div>
      <p class="hint">«Entra nel pannello» apre la gestione del locale come fornitore, per assistenza: ogni ingresso resta nel registro.</p>
    </section>

    <section class="card">
      <div class="card-head"><h2>Utilizzo</h2></div>
      <dl class="dl">
        <dt>Clienti del mese</dt><dd><?= $limit > 0 && $used > $limit ? ui_tag($used . ' su ' . $limit . ': oltre il piano', 'err') : e($used . ' su ' . $limit) ?></dd>
        <dt>Ospiti registrati</dt><dd><?= (int) $guests['n'] ?></dd>
        <dt>Pasti, ultimi 30 giorni</dt><dd><?= number_format($meals, 0, ',', '.') ?></dd>
        <dt>Ultimo accesso dello staff</dt><dd><?= e($lastLogin !== '' ? data_it($lastLogin) : 'mai') ?></dd>
        <dt>Email del titolare</dt><dd><?= $v['email_verified_at'] !== null ? 'confermata il ' . e(data_it((string) $v['email_verified_at'], false)) : 'non ancora confermata' ?></dd>
        <dt>Cliente dal</dt><dd><?= e(data_it((string) $v['created_at'], false)) ?></dd>
      </dl>
      <p class="label">Collegamento per gli ospiti</p>
      <div class="link-box"><code id="guest-link"><?= e(guest_link($v)) ?></code><button type="button" class="btn btn-small btn-line" data-copy="#guest-link">Copia</button></div>
    </section>
  </div>
</div>

<section class="card card-white" id="funzioni">
  <div class="card-head"><h2>Funzioni attive</h2><p><?= $featuresOn ?> su <?= count(pf_features()) ?> accese</p></div>
  <form method="post" action="cliente.php" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="azione" value="funzioni">
    <?= super_feature_switches($v['features']) ?>
    <div class="row"><button type="submit" class="btn btn-dark">Salva le funzioni</button></div>
  </form>
</section>

<section class="card card-white" id="permessi">
  <div class="card-head"><h2>Permessi dei ruoli</h2><p>Spunta una casella per dare il permesso, toglila per negarlo</p></div>
  <form method="post" action="cliente.php" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="azione" value="permessi">
    <?= super_perm_matrix($v['perms']) ?>
    <p class="hint">Il titolare ha sempre tutti i permessi, qualunque cosa sia scritta qui: non può chiudersi fuori dal proprio pannello.</p>
    <div class="row"><button type="submit" class="btn btn-dark">Salva i permessi</button></div>
  </form>
</section>

<section class="card card-white" id="staff">
  <div class="card-head"><h2>Staff del locale</h2><p><?= count($staff) ?> <?= count($staff) === 1 ? 'utente' : 'utenti' ?></p></div>
  <?php if (!$staff): ?>
    <?= ui_empty('Questo locale non ha utenti.', 'users') ?>
  <?php else: ?>
  <div class="table-wrap">
    <table class="table stack-sm">
      <thead><tr><th scope="col">Nome</th><th scope="col">Email</th><th scope="col">Ruolo</th><th scope="col">Ultimo accesso</th><th scope="col"><span class="sr-only">Azioni</span></th></tr></thead>
      <tbody>
      <?php foreach ($staff as $s): ?>
        <tr>
          <td data-th="Nome"><strong><?= e($s['name']) ?></strong></td>
          <td data-th="Email"><?= e($s['email']) ?></td>
          <td data-th="Ruolo"><span class="tags"><?= (int) $s['is_owner'] === 1 ? ui_tag('Titolare', 'zaff') : ui_tag(pf_roles()[$s['role']] ?? (string) $s['role']) ?><?= (int) $s['active'] === 1 ? '' : ui_tag('Disattivato', 'soft') ?></span></td>
          <td data-th="Ultimo accesso"><?= e($s['last_login_at'] ? data_it((string) $s['last_login_at']) : 'mai') ?></td>
          <td class="actions"><?= (int) $s['active'] === 1 ? ui_post_btn('cliente.php', ['id' => $id, 'azione' => 'password', 'staff' => (int) $s['id']], icon('key', 16) . 'Invia collegamento password') : '' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>

<section class="card" id="registro">
  <div class="card-head"><h2>Ultimi eventi</h2><p><a href="registro.php?locale=<?= $id ?>">Vedi tutto il registro</a></p></div>
  <?php if (!$events): ?>
    <?= ui_empty('Ancora nessun evento registrato.', 'history') ?>
  <?php else: ?>
  <div class="table-wrap">
    <table class="table stack-sm">
      <thead><tr><th scope="col">Quando</th><th scope="col">Chi</th><th scope="col">Azione</th><th scope="col">Dettagli</th></tr></thead>
      <tbody>
      <?php foreach ($events as $ev): ?>
        <tr>
          <td data-th="Quando" class="nowrap"><?= e(data_it((string) $ev['created_at'])) ?></td>
          <td data-th="Chi"><?= e(super_actor_label($ev)) ?></td>
          <td data-th="Azione"><?= e(super_action_label((string) $ev['action'])) ?></td>
          <td data-th="Dettagli"><?= e((string) $ev['details']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>

<div class="grid-even">
  <section class="card">
    <div class="card-head"><h2>Esporta i dati</h2></div>
    <p class="small muted">Un file JSON con tutto ciò che riguarda il locale: staff, ospiti, società, camere, menù, prenotazioni, richieste e registro. Le password non sono comprese.</p>
    <div class="row actions-row"><?= $quick('esporta', icon('download', 16) . 'Scarica il file JSON') ?></div>
  </section>
  <section class="card danger" id="elimina">
    <div class="card-head"><h2>Elimina il locale</h2></div>
    <p class="small">Cancella per sempre il locale con staff, ospiti, menù, prenotazioni e foto. Non si può annullare: prima scarica i dati.</p>
    <form method="post" action="cliente.php" class="form" data-confirm="Eliminare per sempre <?= e($v['name']) ?> con tutti i suoi dati?">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="azione" value="elimina">
      <?= ui_field('conferma', 'Per confermare scrivi il nome esatto del locale', '', ['required' => true, 'autocomplete' => 'off', 'placeholder' => $v['name']]) ?>
      <div class="row"><button type="submit" class="btn btn-danger"><?= icon('trash', 18) ?>Elimina per sempre</button></div>
    </form>
  </section>
</div>
<?php
ui_shell_end();
