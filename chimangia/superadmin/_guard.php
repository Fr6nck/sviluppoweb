<?php
/**
 * Super admin — controllo d'accesso e funzioni comuni alle pagine del fornitore.
 * Va incluso da ogni pagina subito dopo pf_boot('super'): usa la stessa sessione
 * del pannello del sito (admin/) e lascia passare solo il ruolo Amministratore.
 */
declare(strict_types=1);

defined('CM_ROOT') || exit;

$u = current_user();
if ($u === null) {
    $_SESSION['after_login'] = 'piattaforma.php';   // dopo l'accesso si torna qui, passando da admin/piattaforma.php
    redirect('../admin/login.php');
}
if ($u['role'] !== 'admin') {
    http_response_code(403);
    ui_plain_page('Accesso non consentito', '<p class="lead">Questa area è riservata al fornitore di ChiMangia.</p>'
        . '<p><a class="btn btn-line" href="../admin/">' . icon('back', 16) . 'Torna al pannello del sito</a></p>');
    exit;
}

/** Etichetta colorata dello stato di un locale (la prova scaduta è segnalata a parte). */
function super_status_tag(array $v): string
{
    $s = (string) $v['status'];
    if ($s === 'prova' && !venue_live($v)) {
        return ui_tag('Prova scaduta', 'err');
    }
    return ui_tag(pf_statuses()[$s] ?? $s, ['attivo' => 'ok', 'prova' => 'zaff', 'sospeso' => 'err', 'chiuso' => 'soft'][$s] ?? '');
}

/** Nome leggibile di un'azione del registro: "locale_creato" → "Locale creato". */
function super_action_label(string $action): string
{
    $s = str_replace('_', ' ', $action);
    return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
}

function super_actor_label(array $ev): string
{
    $who = ['staff' => 'Staff', 'ospite' => 'Ospite', 'fornitore' => 'Fornitore', 'sistema' => 'Sistema'][(string) $ev['actor']] ?? (string) $ev['actor'];
    return $who . ((string) $ev['actor_name'] !== '' ? ' · ' . $ev['actor_name'] : '');
}

/** Interruttori delle funzioni. $on: chiave => bool. I campi si chiamano f[chiave]. */
function super_feature_switches(array $on): string
{
    $html = '<div class="features">';
    foreach (pf_features() as $k => [$label, $desc]) {
        $html .= ui_switch('f[' . $k . ']', $label, !empty($on[$k]), $desc);
    }
    return $html . '</div>';
}

/** Legge gli interruttori inviati: le caselle non spuntate diventano false. */
function super_read_features(): array
{
    $in = is_array($_POST['f'] ?? null) ? $_POST['f'] : [];
    $out = [];
    foreach (array_keys(pf_features()) as $k) {
        $out[$k] = isset($in[$k]);
    }
    return $out;
}

/** Tabella permessi × ruoli. $perms: "permesso:ruolo" => bool. I campi si chiamano perm[permesso:ruolo]. */
function super_perm_matrix(array $perms): string
{
    $html = '<div class="table-wrap"><table class="table matrix"><thead><tr><th scope="col">Permesso</th>';
    foreach (pf_roles() as $role) {
        // Su schermi stretti resta la sigla; il nome intero rimane per i lettori di schermo.
        $html .= '<th scope="col"><span class="m-long">' . e($role) . '</span><span class="m-short" aria-hidden="true">' . e(mb_substr($role, 0, 3)) . '.</span></th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach (pf_perms() as $p => $label) {
        $html .= '<tr><td>' . e($label) . '</td>';
        foreach (pf_roles() as $r => $role) {
            $k = $p . ':' . $r;
            $html .= '<td>' . ui_check('perm[' . $k . ']', '<span class="sr-only">' . e($label . ' · ' . $role) . '</span>', !empty($perms[$k])) . '</td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div>';
}

function super_read_perms(): array
{
    $in = is_array($_POST['perm'] ?? null) ? $_POST['perm'] : [];
    $out = [];
    foreach (array_keys(pf_perms()) as $p) {
        foreach (array_keys(pf_roles()) as $r) {
            $out[$p . ':' . $r] = isset($in[$p . ':' . $r]);
        }
    }
    return $out;
}

/**
 * Email di benvenuto al titolare di un locale creato dal fornitore:
 * collegamento per scegliere la password, valido tre giorni.
 */
function super_welcome_send(array $staff, array $venue): void
{
    $token = pf_token_create('staff_reset', (int) $staff['id'], 3 * 86400);
    pf_mail_queue(
        (int) $venue['id'],
        (string) $staff['email'],
        'Il tuo locale su ChiMangia è pronto',
        'Ciao ' . $staff['name'] . ",\n\nabbiamo creato il pannello di " . $venue['name'] . " su ChiMangia.\n"
        . "Per entrare scegli la tua password da questo collegamento (vale tre giorni, si usa una sola volta):\n"
        . site_url('accedi/reimposta.php?t=' . $token) . "\n\nPoi accedi da " . site_url('accedi/') . " con questa email.\n\nChiMangia"
    );
}

/** Invia un file come download e termina. Il percorso deve essere già stato controllato. */
function super_send_file(string $path, string $downloadName, string $type): never
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: ' . $type);
    header('Content-Length: ' . (string) filesize($path));
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName) . '"');
    readfile($path);
    exit;
}
