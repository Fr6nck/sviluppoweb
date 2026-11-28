<?php
/**
 * Piattaforma ChiMangia — avvio comune delle tre aree:
 *   ospiti/      area degli ospiti (prenotazione di notti e pasti)
 *   gestione/    pannello del locale (titolare, reception, cucina)
 *   superadmin/  pannello del fornitore (clienti, funzioni, manutenzione, backup)
 *
 * Ogni area ha la propria sessione: cookie e cartella diversi, così le
 * scadenze di un'area non toccano le altre.
 */
declare(strict_types=1);

if (!defined('CM_ROOT')) {
    define('CM_ROOT', dirname(__DIR__, 2));
}
require_once CM_ROOT . '/app/bootstrap.php';
require_once CM_APP . '/auth.php';
require_once CM_APP . '/csrf.php';
require_once CM_APP . '/mail.php';
require_once CM_APP . '/sanitize.php';
require_once CM_APP . '/icons.php';
require_once CM_APP . '/platform/captcha.php';
require_once CM_APP . '/platform/domain.php';
require_once CM_APP . '/platform/ui.php';

const PF_STAFF_IDLE = 28800;        // 8 ore: i tablet di cucina e reception restano aperti a lungo
const PF_GUEST_LIFE = 5184000;      // 60 giorni: l'ospite non deve rifare l'accesso ogni giorno
const PF_TRIAL_DAYS = 7;

const PF_DAYS = [1 => 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato', 'domenica'];
const PF_DAYS_SHORT = [1 => 'lun', 'mar', 'mer', 'gio', 'ven', 'sab', 'dom'];
const PF_MONTHS = [1 => 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
const PF_MEALS = ['pranzo' => 'Pranzo', 'cena' => 'Cena'];
const PF_COURSES = ['primo' => 'Primi', 'secondo' => 'Secondi', 'contorno' => 'Contorni'];
const PF_COURSE_ONE = ['primo' => 'Primo', 'secondo' => 'Secondo', 'contorno' => 'Contorno'];

/* =====================================================================
 * Avvio
 * ===================================================================== */

/**
 * Da chiamare in cima a ogni pagina della piattaforma.
 * $area: 'staff' (gestione, accesso, registrazione del locale), 'guest' (ospiti), 'super' (fornitore).
 * $prefix: percorso relativo verso la radice del sito (es. '../').
 */
function pf_boot(string $area, string $prefix = '../', bool $indexable = false): void
{
    $GLOBALS['pf_area'] = $area;
    $GLOBALS['pf_prefix'] = $prefix;
    require_installed($prefix);

    if ((string) setting('db_version', '') !== CM_VERSION) {
        require_once CM_APP . '/content.php';
        require_once CM_APP . '/schema.php';
        require_once CM_APP . '/render.php';
        cm_migrate();
    }

    security_headers(admin_csp());
    if (!$indexable) {
        header('X-Robots-Tag: noindex, nofollow');
    }
    header('Cache-Control: no-store, private, max-age=0');
    header('X-LiteSpeed-Cache-Control: no-cache');
    header('Pragma: no-cache');
    header('Content-Type: text/html; charset=utf-8');

    pf_session_start($area);
    if ($area !== 'super') {
        pf_maintenance_gate();
    }
    register_shutdown_function('pf_tick');
}

function pf_area(): string
{
    return (string) ($GLOBALS['pf_area'] ?? 'staff');
}

function pf_prefix(): string
{
    return (string) ($GLOBALS['pf_prefix'] ?? '../');
}

/** Nome del file in esecuzione (es. "menu.php"). */
function pf_script(): string
{
    return basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
}

function pf_session_start(string $area): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if ($area === 'super') {
        session_start_secure();     // la stessa sessione del pannello del sito (admin/)
        return;
    }
    [$name, $folder, $gc, $life] = $area === 'guest'
        ? ['cm_guest', 'sessions-guest', PF_GUEST_LIFE, PF_GUEST_LIFE]
        : ['cm_staff', 'sessions-staff', PF_STAFF_IDLE + 3600, 0];
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    $dir = storage_path($folder);
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        ini_set('session.save_handler', 'files');
        session_save_path($dir);
        ini_set('session.gc_maxlifetime', (string) $gc);
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '200');
    }
    session_cache_limiter('');
    session_name($name);
    $params = ['lifetime' => $life, 'path' => request_base_path(), 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax'];
    session_set_cookie_params($params);
    session_start();
    // Per gli ospiti la scadenza del cookie si rinnova a ogni visita.
    if ($area === 'guest' && !empty($_SESSION['guest_id']) && !headers_sent()) {
        setcookie($name, (string) session_id(), ['expires' => time() + $life] + array_diff_key($params, ['lifetime' => 1]));
    }
}

/* ---------- Messaggi a comparsa ---------- */

function pf_flash(string $type, string $text): void
{
    $_SESSION['flash'][] = ['type' => $type, 'text' => $text];
}

function pf_flash_html(): string
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    $out = '';
    foreach ($items as $f) {
        $type = in_array($f['type'] ?? '', ['ok', 'error', 'info', 'warn'], true) ? $f['type'] : 'info';
        $out .= '<div class="notice notice-' . $type . '" role="' . ($type === 'error' ? 'alert' : 'status') . '">' . e($f['text'] ?? '') . '</div>';
    }
    return $out;
}

/** Controllo CSRF per tutte le azioni della piattaforma. */
function pf_csrf_check(): void
{
    if (!csrf_valid()) {
        ui_die('Richiesta non valida', 'Il modulo è scaduto o non è valido. Torna indietro, ricarica la pagina e riprova.', 400);
    }
}

/* ---------- Manutenzione della piattaforma ---------- */

function pf_maintenance(): array
{
    $m = setting('pf_maintenance', []);
    return ['on' => !empty($m['on']), 'text' => (string) ($m['text'] ?? '')];
}

function pf_maintenance_gate(): void
{
    $m = pf_maintenance();
    if (!$m['on'] || !empty($_SESSION['impersonated_by'])) {
        return;
    }
    http_response_code(503);
    header('Retry-After: 1800');
    ui_plain_page('Torniamo tra poco', '<p class="lead">' . e($m['text'] !== '' ? $m['text'] : 'Stiamo aggiornando ChiMangia. Riprova tra qualche minuto: prenotazioni e dati sono al sicuro.') . '</p>');
    exit;
}

/* =====================================================================
 * Funzioni attivabili, permessi, piani
 * ===================================================================== */

/** chiave => [etichetta, descrizione, attiva per i nuovi locali] */
function pf_features(): array
{
    return [
        'hotel' => ['Modulo Hotel', 'Notti di pernotto e camere', false],
        'scelta' => ['Menù a scelta', 'Alternative tra primi, secondi, contorni', true],
        'foto' => ['Menù in foto', 'Pubblicazione rapida con una foto', true],
        'varianti' => ['Varianti al menù', "Richieste dell'ospite da approvare", true],
        'societa' => ['Società o gruppi', "L'ospite indica a chi appartiene", true],
        'formule' => ['Formule a prezzo fisso', 'Base, completo, con bevanda', true],
        'prezzi' => ['Prezzi per piatto visibili', 'Prezzo di ogni piatto e totale', false],
        'riepilogo' => ['Riepilogo mensile per società', "Visibile solo all'amministrazione", true],
        'notifiche' => ["Avvisi nell'area ospiti", 'Campanella con le novità del locale', true],
        'email' => ['Avvisi via email', 'Conferme e recupero password', true],
        'whatsapp' => ['Messaggi WhatsApp pronti', 'Invio a mano al singolo ospite', true],
    ];
}

function pf_feature_defaults(): array
{
    $saved = setting('pf_feature_defaults', []);
    $out = [];
    foreach (pf_features() as $k => [, , $on]) {
        $out[$k] = is_array($saved) && array_key_exists($k, $saved) ? (bool) $saved[$k] : $on;
    }
    return $out;
}

function pf_roles(): array
{
    return ['admin' => 'Amministrazione', 'reception' => 'Reception', 'cucina' => 'Cucina'];
}

function pf_perms(): array
{
    return [
        'presenti' => 'Vedere presenti e totali per piatto',
        'note' => 'Vedere diete e note interne',
        'conferma' => 'Confermare le registrazioni e gestire gli ospiti',
        'camere' => 'Assegnare le camere',
        'richieste' => 'Approvare richieste e varianti',
        'menu' => 'Comporre e pubblicare il menù',
        'societa' => 'Gestire società e unire doppioni',
        'prezzi' => 'Vedere prezzi e riepiloghi',
        'stampe' => 'Stampare i prospetti',
        'impostazioni' => 'Modificare orari limite e impostazioni',
        'staff' => 'Gestire gli utenti dello staff',
    ];
}

/** Permessi iniziali: "permesso:ruolo" => bool. Il titolare ha sempre tutto. */
function pf_default_perms(): array
{
    $saved = setting('pf_perm_defaults', []);
    $kitchen = ['presenti', 'note', 'stampe'];
    $out = [];
    foreach (array_keys(pf_perms()) as $p) {
        $out[$p . ':admin'] = true;
        $out[$p . ':reception'] = $p !== 'staff';
        $out[$p . ':cucina'] = in_array($p, $kitchen, true);
    }
    foreach ($out as $k => $v) {
        if (is_array($saved) && array_key_exists($k, $saved)) {
            $out[$k] = (bool) $saved[$k];
        }
    }
    return $out;
}

function pf_default_settings(): array
{
    return [
        'cutoff_pranzo' => '10:30',
        'cutoff_cena' => '16:30',
        'meals' => ['pranzo', 'cena'],
        'closed_days' => [],                 // 1 = lunedì … 7 = domenica
        'horizon' => 14,                     // giorni prenotabili in anticipo
        'price_mode' => 'formule',           // formule | piatto | nessuno
        'formulas' => [
            ['id' => 'base', 'name' => 'Base', 'desc' => 'Primo oppure secondo con contorno', 'price' => 0, 'type' => 'scelta'],
            ['id' => 'completo', 'name' => 'Completo', 'desc' => 'Primo, secondo e contorno', 'price' => 0, 'type' => 'completo'],
        ],
        'drinks' => [
            ['id' => 'acqua', 'label' => 'Acqua', 'price' => 0],
            ['id' => 'vino', 'label' => 'Calice di vino', 'price' => 0],
            ['id' => 'bibita', 'label' => 'Bibita', 'price' => 0],
        ],
        'extras' => [
            ['id' => 'dolce', 'label' => 'Dolce', 'price' => 0],
            ['id' => 'caffe', 'label' => 'Caffè', 'price' => 0],
            ['id' => 'amaro', 'label' => 'Ammazzacaffè', 'price' => 0],
        ],
        'diets' => ['No maiale', 'Vegetariano', 'Senza glutine', 'Senza lattosio', 'No pesce'],
        'auto_confirm' => false,             // gli ospiti nuovi vanno confermati dalla reception
        'auto_cancel' => true,               // chi non risponde entro l'orario viene disdetto
        'welcome' => '',
        'wa_text' => 'Ciao! È pronto il menù {pasto} di {giorno}. Guardalo e rispondi entro le {orario}: {link}',
    ];
}

/** Tipi di formula: quali portate comprende. */
function pf_formula_types(): array
{
    return [
        'completo' => ['Primo, secondo e contorno', [['primo', 'secondo', 'contorno']]],
        'scelta' => ['Primo oppure secondo con contorno', [['primo'], ['secondo', 'contorno']]],
        'primo' => ['Solo il primo', [['primo']]],
        'secondo' => ['Secondo e contorno', [['secondo', 'contorno']]],
    ];
}

/** Piani: chiave => [etichetta, clienti compresi nel mese] */
function pf_plans(): array
{
    return ['p40' => ['Fino a 40 clienti', 40], 'p80' => ['Fino a 80 clienti', 80], 'p150' => ['Oltre 80 clienti', 150]];
}

function pf_statuses(): array
{
    return ['prova' => 'In prova', 'attivo' => 'Attivo', 'sospeso' => 'Sospeso', 'chiuso' => 'Chiuso'];
}

function pf_kinds(): array
{
    return ['ristorante' => 'Trattoria o ristorante', 'mensa' => 'Mensa aziendale', 'convitto' => 'Convitto o residenza', 'hotel' => 'Struttura con ristorante'];
}

/* =====================================================================
 * Locali
 * ===================================================================== */

function venue_decode(array $row): array
{
    $j = static fn (string $s): array => is_array($d = json_decode($s, true)) ? $d : [];
    $row['features'] = array_merge(pf_feature_defaults(), array_map('boolval', $j((string) $row['features'])));
    $row['perms'] = array_merge(pf_default_perms(), array_map('boolval', $j((string) $row['perms'])));
    $row['settings'] = array_merge(pf_default_settings(), $j((string) $row['settings']));
    return $row;
}

function venue_get(int $id, bool $fresh = false): ?array
{
    static $cache = [];
    if (!$fresh && isset($cache[$id])) {
        return $cache[$id];
    }
    $row = q_one('SELECT * FROM ' . t('venues') . ' WHERE id = ?', [$id]);
    return $row ? ($cache[$id] = venue_decode($row)) : null;
}

function venue_by_slug(string $slug): ?array
{
    if (!preg_match('/^[a-z0-9-]{2,60}$/', $slug)) {
        return null;
    }
    $row = q_one('SELECT * FROM ' . t('venues') . ' WHERE slug = ?', [$slug]);
    return $row ? venue_decode($row) : null;
}

/** Il locale è utilizzabile? (attivo, oppure in prova non ancora scaduta) */
function venue_live(array $v): bool
{
    if ($v['status'] === 'attivo') {
        return true;
    }
    return $v['status'] === 'prova' && ($v['trial_ends_at'] === null || strtotime((string) $v['trial_ends_at']) > time());
}

/** Giorni di prova rimasti (0 se scaduta, null se non in prova). */
function venue_trial_days(array $v): ?int
{
    if ($v['status'] !== 'prova' || $v['trial_ends_at'] === null) {
        return null;
    }
    return max(0, (int) ceil((strtotime((string) $v['trial_ends_at']) - time()) / 86400));
}

function venue_slug_make(string $name): string
{
    $map = ['à' => 'a', 'á' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'í' => 'i', 'ò' => 'o', 'ó' => 'o', 'ù' => 'u', 'ú' => 'u', 'ç' => 'c', 'ñ' => 'n', "'" => '-', '’' => '-', '&' => 'e'];
    $s = strtr(mb_strtolower(trim($name)), $map);
    $s = trim((string) preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    $s = substr($s !== '' ? $s : 'locale', 0, 40);
    $s = rtrim($s, '-');
    if (strlen($s) < 2) {
        $s .= '-locale';
    }
    $base = $s;
    $n = 1;
    while (q_value('SELECT 1 FROM ' . t('venues') . ' WHERE slug = ?', [$s]) || in_array($s, ['admin', 'ospiti', 'gestione', 'superadmin', 'accedi', 'registrati'], true)) {
        $s = $base . '-' . (++$n);
    }
    return $s;
}

/**
 * Crea un locale con il suo titolare. $d: name, kind, email, phone, owner_name, password, hotel (bool), status, trial_days.
 * Restituisce [id del locale, id del titolare].
 */
function venue_create(array $d): array
{
    $features = pf_feature_defaults();
    $features['hotel'] = !empty($d['hotel']);
    $status = (string) ($d['status'] ?? 'prova');
    $days = (int) ($d['trial_days'] ?? PF_TRIAL_DAYS);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q(
            'INSERT INTO ' . t('venues') . ' (slug, name, kind, email, phone, status, plan, trial_ends_at, features, perms, settings, notes, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                venue_slug_make((string) $d['name']), (string) $d['name'], (string) ($d['kind'] ?? 'ristorante'), mb_strtolower((string) $d['email']), (string) ($d['phone'] ?? ''),
                $status, (string) ($d['plan'] ?? 'p40'), $status === 'prova' ? date('Y-m-d H:i:s', time() + $days * 86400) : null,
                json_encode($features), json_encode(pf_default_perms()), json_encode(pf_default_settings(), JSON_UNESCAPED_UNICODE), '', now(), now(),
            ]
        );
        $venueId = (int) $pdo->lastInsertId();
        q(
            'INSERT INTO ' . t('staff') . ' (venue_id, name, email, password_hash, role, is_owner, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, 1, ?, ?)',
            [$venueId, (string) $d['owner_name'], mb_strtolower((string) $d['email']), password_hash((string) $d['password'], PASSWORD_DEFAULT), 'admin', now(), now()]
        );
        $staffId = (int) $pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return [$venueId, $staffId];
}

/** Aggiorna una colonna JSON del locale (features, perms, settings) fondendo i valori nuovi. */
function venue_patch(int $id, string $col, array $values): void
{
    if (!in_array($col, ['features', 'perms', 'settings'], true)) {
        return;
    }
    $row = q_one('SELECT ' . $col . ' FROM ' . t('venues') . ' WHERE id = ?', [$id]);
    $cur = $row && is_array($d = json_decode((string) $row[$col], true)) ? $d : [];
    q('UPDATE ' . t('venues') . ' SET ' . $col . ' = ?, updated_at = ? WHERE id = ?', [json_encode(array_merge($cur, $values), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), now(), $id]);
    venue_get($id, true);
}

/** Indirizzo dell'area ospiti del locale, da condividere. */
function guest_link(array $venue): string
{
    return site_url('ospiti/?l=' . $venue['slug']);
}

/** Il locale della richiesta in corso (staff o ospite). */
function pf_venue(): ?array
{
    if (pf_area() === 'guest') {
        return guest_current()['venue'] ?? ($GLOBALS['pf_guest_venue'] ?? null);
    }
    return staff_current()['venue'] ?? null;
}

function feature(string $key, ?array $venue = null): bool
{
    $venue ??= pf_venue();
    return $venue !== null && !empty($venue['features'][$key]);
}

/* =====================================================================
 * Staff del locale
 * ===================================================================== */

function staff_current(): ?array
{
    static $me = false;
    if ($me !== false) {
        return $me;
    }
    $me = null;
    if (pf_area() !== 'staff' || empty($_SESSION['staff_id'])) {
        return null;
    }
    if (time() - (int) ($_SESSION['seen'] ?? 0) > PF_STAFF_IDLE) {
        staff_logout();
        pf_flash('info', 'La sessione è scaduta per inattività. Accedi di nuovo.');
        return null;
    }
    $row = q_one('SELECT id, venue_id, name, email, role, is_owner, active FROM ' . t('staff') . ' WHERE id = ?', [(int) $_SESSION['staff_id']]);
    $venue = $row && (int) $row['active'] ? venue_get((int) $row['venue_id']) : null;
    if (!$venue) {
        staff_logout();
        return null;
    }
    $_SESSION['seen'] = time();
    $row['venue'] = $venue;
    $row['impersonated'] = !empty($_SESSION['impersonated_by']);
    return $me = $row;
}

/**
 * Richiede l'accesso dello staff e, se indicati, un permesso e una funzione attiva.
 * I controlli sono sempre lato server.
 */
function staff_require(?string $perm = null, ?string $feature = null): array
{
    $me = staff_current();
    if (!$me) {
        $_SESSION['after_login'] = pf_script() . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
        redirect(pf_prefix() . 'accedi/');
    }
    $free = ['abbonamento.php', 'profilo.php', 'logout.php'];
    if (!venue_live($me['venue']) && !$me['impersonated'] && !in_array(pf_script(), $free, true)) {
        redirect('abbonamento.php');
    }
    if ($feature !== null && !feature($feature, $me['venue'])) {
        ui_die('Funzione non attiva', 'Questa funzione non è compresa nel tuo piano. Scrivici per attivarla.', 403);
    }
    if ($perm !== null && !can($perm, $me)) {
        ui_die('Accesso non consentito', 'Il tuo ruolo non ha il permesso per questa pagina. Chiedi al titolare.', 403);
    }
    venue_sync($me['venue']);
    return $me;
}

function can(string $perm, ?array $me = null): bool
{
    $me ??= staff_current();
    if (!$me) {
        return false;
    }
    return (int) $me['is_owner'] === 1 || !empty($me['venue']['perms'][$perm . ':' . $me['role']]);
}

/** Tenta l'accesso dello staff. Restituisce null se riuscito, altrimenti il messaggio d'errore. */
function staff_login(string $email, string $password): ?string
{
    $email = mb_strtolower(trim($email));
    $key = 's:' . $email;
    if (login_locked($key)) {
        return 'Troppi tentativi errati. Per sicurezza l\'accesso è bloccato per ' . CM_LOGIN_LOCK_MINUTES . ' minuti.';
    }
    $u = q_one('SELECT * FROM ' . t('staff') . ' WHERE email = ?', [$email]);
    $hash = $u['password_hash'] ?? password_hash(random_token(8), PASSWORD_DEFAULT);
    if (!(password_verify($password, $hash) && $u !== null) || !(int) $u['active']) {
        login_record($key, false);
        return 'Email o password non corretti.';
    }
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE ' . t('staff') . ' SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    login_record($key, true);
    staff_session_open((int) $u['id']);
    pf_event((int) $u['venue_id'], 'accesso', '', 'staff', (int) $u['id'], (string) $u['name']);
    return null;
}

function staff_session_open(int $staffId, ?int $impersonatedBy = null): void
{
    session_regenerate_id(true);
    $flash = $_SESSION['flash'] ?? [];
    $_SESSION = ['staff_id' => $staffId, 'seen' => time(), 'csrf' => random_token(), 'flash' => $flash];
    if ($impersonatedBy !== null) {
        $_SESSION['impersonated_by'] = $impersonatedBy;
    }
    q('UPDATE ' . t('staff') . ' SET last_login_at = ? WHERE id = ?', [now(), $staffId]);
}

function staff_logout(): void
{
    $flash = $_SESSION['flash'] ?? [];
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['flash'] = $flash;
}

/* =====================================================================
 * Ospiti: sessione
 * ===================================================================== */

function guest_current(): ?array
{
    static $me = false;
    if ($me !== false) {
        return $me;
    }
    $me = null;
    if (pf_area() !== 'guest' || empty($_SESSION['guest_id'])) {
        return null;
    }
    $row = q_one('SELECT * FROM ' . t('guests') . ' WHERE id = ?', [(int) $_SESSION['guest_id']]);
    $venue = $row && $row['status'] !== 'bloccato' ? venue_get((int) $row['venue_id']) : null;
    if (!$venue) {
        guest_logout();
        return null;
    }
    unset($row['password_hash']);
    $row['venue'] = $venue;
    return $me = $row;
}

function guest_require(): array
{
    $me = guest_current();
    if (!$me) {
        $slug = (string) ($_SESSION['venue_slug'] ?? '');
        redirect('index.php' . ($slug !== '' ? '?l=' . rawurlencode($slug) : ''));
    }
    if (!venue_live($me['venue'])) {
        ui_die('Prenotazioni sospese', 'Al momento le prenotazioni di ' . $me['venue']['name'] . ' non sono attive. Per pranzo e cena rivolgiti direttamente al locale.');
    }
    venue_sync($me['venue']);
    return $me;
}

function guest_login(array $venue, string $phone, string $password): ?string
{
    $phone = phone_normalize($phone);
    $key = 'g:' . $venue['id'] . ':' . $phone;
    if (login_locked($key)) {
        return 'Troppi tentativi errati. Riprova tra ' . CM_LOGIN_LOCK_MINUTES . ' minuti oppure chiedi alla reception.';
    }
    $g = $phone !== '' ? q_one('SELECT * FROM ' . t('guests') . ' WHERE venue_id = ? AND phone = ?', [$venue['id'], $phone]) : null;
    $hash = $g['password_hash'] ?? password_hash(random_token(8), PASSWORD_DEFAULT);
    if (!(password_verify($password, $hash) && $g !== null)) {
        login_record($key, false);
        return 'Cellulare o password non corretti.';
    }
    if ($g['status'] === 'bloccato') {
        return 'Questo accesso è stato sospeso. Rivolgiti alla reception.';
    }
    login_record($key, true);
    guest_session_open((int) $g['id'], (string) $venue['slug']);
    return null;
}

function guest_session_open(int $guestId, string $slug): void
{
    session_regenerate_id(true);
    $flash = $_SESSION['flash'] ?? [];
    $_SESSION = ['guest_id' => $guestId, 'venue_slug' => $slug, 'csrf' => random_token(), 'flash' => $flash];
    q('UPDATE ' . t('guests') . ' SET last_login_at = ? WHERE id = ?', [now(), $guestId]);
}

function guest_logout(): void
{
    $keep = ['flash' => $_SESSION['flash'] ?? [], 'venue_slug' => $_SESSION['venue_slug'] ?? ''];
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION = $keep;
}

/* =====================================================================
 * Date
 * ===================================================================== */

function today(): string
{
    return date('Y-m-d');
}

function valid_day(string $s): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $s);
    return $d !== false && $d->format('Y-m-d') === $s;
}

function day_add(string $ymd, int $days): string
{
    return date('Y-m-d', strtotime($ymd . ' ' . ($days >= 0 ? '+' : '') . $days . ' day'));
}

/** Data in italiano. long: "Martedì 24 novembre" · full: con l'anno · short: "mar 24 nov" · rel: "oggi", "domani" o long. */
function day_it(string $ymd, string $fmt = 'long'): string
{
    $ts = strtotime($ymd);
    if ($ts === false) {
        return '—';
    }
    $dow = (int) date('N', $ts);
    $d = (int) date('j', $ts);
    $m = (int) date('n', $ts);
    if ($fmt === 'rel') {
        if ($ymd === today()) {
            return 'oggi';
        }
        if ($ymd === day_add(today(), 1)) {
            return 'domani';
        }
        return PF_DAYS[$dow] . ' ' . $d . ' ' . PF_MONTHS[$m];
    }
    if ($fmt === 'short') {
        return PF_DAYS_SHORT[$dow] . ' ' . $d . ' ' . mb_substr(PF_MONTHS[$m], 0, 3);
    }
    $s = mb_strtoupper(mb_substr(PF_DAYS[$dow], 0, 1)) . mb_substr(PF_DAYS[$dow], 1) . ' ' . $d . ' ' . PF_MONTHS[$m];
    return $fmt === 'full' ? $s . ' ' . date('Y', $ts) : $s;
}

function month_it(string $ym): string
{
    $ts = strtotime($ym . '-01');
    return $ts === false ? '—' : PF_MONTHS[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

function hm(?string $time): string
{
    return $time ? substr($time, 0, 5) : '';
}

function valid_time(string $s): bool
{
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $s);
}

/* =====================================================================
 * Telefono
 * ===================================================================== */

/** Numero in forma unica: solo cifre, senza prefisso italiano; i numeri esteri tengono il "+". */
function phone_normalize(string $raw): string
{
    $s = preg_replace('/[^0-9+]/', '', $raw) ?? '';
    if (str_starts_with($s, '00')) {
        $s = '+' . substr($s, 2);
    }
    if (str_starts_with($s, '+39')) {
        $s = substr($s, 3);
    }
    $digits = preg_replace('/\D/', '', $s) ?? '';
    if (strlen($digits) < 6 || strlen($digits) > 15) {
        return '';
    }
    return (str_starts_with($s, '+') ? '+' : '') . $digits;
}

function phone_show(string $p): string
{
    if (str_starts_with($p, '+') || strlen($p) < 9) {
        return $p;
    }
    return substr($p, 0, 3) . ' ' . substr($p, 3, 3) . ' ' . substr($p, 6);
}

/** Collegamento WhatsApp con testo già pronto (si apre solo quando lo staff lo tocca). */
function phone_wa(string $p, string $text = ''): string
{
    $n = str_starts_with($p, '+') ? substr($p, 1) : '39' . $p;
    return 'https://wa.me/' . $n . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

/* =====================================================================
 * Registro degli eventi, limiti, token monouso
 * ===================================================================== */

function pf_event(?int $venueId, string $action, string $details = '', ?string $actor = null, ?int $actorId = null, string $actorName = ''): void
{
    try {
        if ($actor === null) {
            if (pf_area() === 'super') {
                $u = current_user();
                [$actor, $actorId, $actorName] = ['fornitore', $u ? (int) $u['id'] : null, (string) ($u['name'] ?? '')];
            } elseif (pf_area() === 'guest' && ($g = guest_current())) {
                [$actor, $actorId, $actorName] = ['ospite', (int) $g['id'], guest_name($g)];
            } elseif (pf_area() === 'staff' && ($s = staff_current())) {
                [$actor, $actorId, $actorName] = [$s['impersonated'] ? 'fornitore' : 'staff', (int) $s['id'], (string) $s['name']];
            } else {
                $actor = 'sistema';
            }
        }
        q(
            'INSERT INTO ' . t('events') . ' (venue_id, actor, actor_id, actor_name, action, details, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$venueId, $actor, $actorId, mb_substr($actorName, 0, 120), $action, mb_substr($details, 0, 500), now()]
        );
    } catch (Throwable $e) {
        log_message('Evento non registrato: ' . $e->getMessage());
    }
}

function pf_rate_count(string $bucket, int $seconds): int
{
    return (int) q_value(
        'SELECT COUNT(*) FROM ' . t('pf_rate') . ' WHERE bucket = ? AND ip_hash = ? AND created_at > ?',
        [$bucket, ip_hash(), date('Y-m-d H:i:s', time() - $seconds)]
    );
}

function pf_rate_hit(string $bucket): void
{
    q('INSERT INTO ' . t('pf_rate') . ' (bucket, ip_hash, created_at) VALUES (?, ?, ?)', [$bucket, ip_hash(), now()]);
}

/** Token monouso (recupero password, verifica email, ingresso del fornitore). Restituisce il token in chiaro. */
function pf_token_create(string $kind, int $subjectId, int $ttl): string
{
    $token = random_token(32);
    q(
        'INSERT INTO ' . t('pf_tokens') . ' (token_hash, kind, subject_id, expires_at, created_at) VALUES (?, ?, ?, ?, ?)',
        [hash('sha256', $token), $kind, $subjectId, date('Y-m-d H:i:s', time() + $ttl), now()]
    );
    return $token;
}

function pf_token_find(string $kind, string $token): ?array
{
    if ($token === '' || strlen($token) > 100) {
        return null;
    }
    return q_one(
        'SELECT * FROM ' . t('pf_tokens') . ' WHERE token_hash = ? AND kind = ? AND used_at IS NULL AND expires_at > ?',
        [hash('sha256', $token), $kind, now()]
    );
}

/** Segna il token come usato. Restituisce false se era già stato usato (o non esiste). */
function pf_token_use(string $kind, string $token): bool
{
    return q(
        'UPDATE ' . t('pf_tokens') . ' SET used_at = ? WHERE token_hash = ? AND kind = ? AND used_at IS NULL AND expires_at > ?',
        [now(), hash('sha256', $token), $kind, now()]
    )->rowCount() === 1;
}

/* =====================================================================
 * Email in coda e lavori periodici
 * ===================================================================== */

/** Mette un'email in coda: parte a fine richiesta, senza far aspettare chi sta usando il pannello. */
function pf_mail_queue(?int $venueId, string $to, string $subject, string $body): void
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    q(
        'INSERT INTO ' . t('outbox') . ' (venue_id, to_email, subject, body, created_at) VALUES (?, ?, ?, ?, ?)',
        [$venueId, mb_strtolower($to), mb_substr($subject, 0, 190), $body, now()]
    );
}

function pf_mail_flush(int $max = 8): int
{
    $rows = q('SELECT * FROM ' . t('outbox') . ' WHERE sent_at IS NULL AND attempts < 3 ORDER BY id LIMIT ' . max(1, $max))->fetchAll();
    $sent = 0;
    foreach ($rows as $r) {
        // Prenota la riga prima dell'invio: due richieste contemporanee non spediscono due volte.
        if (q('UPDATE ' . t('outbox') . ' SET attempts = attempts + 1 WHERE id = ? AND attempts = ?', [$r['id'], $r['attempts']])->rowCount() !== 1) {
            continue;
        }
        $err = send_mail([(string) $r['to_email']], (string) $r['subject'], (string) $r['body']);
        if ($err === null) {
            q('UPDATE ' . t('outbox') . ' SET sent_at = ?, error = ? WHERE id = ?', [now(), '', $r['id']]);
            $sent++;
        } else {
            q('UPDATE ' . t('outbox') . ' SET error = ? WHERE id = ?', [mb_substr($err, 0, 255), $r['id']]);
        }
    }
    return $sent;
}

/**
 * Lavori periodici senza cron: partono a fine richiesta, al più una volta al minuto.
 * Chi ha un cron può chiamare cron.php per farli girare anche senza visite.
 */
function pf_tick(bool $force = false): void
{
    try {
        if (!is_installed()) {
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
        if (!$force && time() - (int) setting('pf_tick_at', 0) < 60) {
            return;
        }
        setting_set('pf_tick_at', time());
        @set_time_limit(60);
        menus_close_due(null);
        pf_mail_flush(10);
        if ((string) setting('pf_daily_at', '') !== today()) {
            setting_set('pf_daily_at', today());
            pf_daily();
        }
    } catch (Throwable $e) {
        log_message('Lavori periodici: ' . $e->getMessage());
    }
}

function pf_daily(): void
{
    $old = static fn (int $days): string => date('Y-m-d H:i:s', time() - $days * 86400);
    q('DELETE FROM ' . t('pf_tokens') . ' WHERE expires_at < ?', [$old(2)]);
    q('DELETE FROM ' . t('pf_rate') . ' WHERE created_at < ?', [$old(2)]);
    q('DELETE FROM ' . t('outbox') . ' WHERE created_at < ?', [$old(30)]);
    q('DELETE FROM ' . t('notices') . ' WHERE created_at < ?', [$old(120)]);
    q('DELETE FROM ' . t('events') . ' WHERE created_at < ?', [$old(400)]);
    if (setting('pf_backup_auto', true)) {
        require_once CM_APP . '/platform/maint.php';
        try {
            backup_create('automatico');
            backup_prune((int) setting('pf_backup_keep', 7));
        } catch (Throwable $e) {
            log_message('Backup automatico non riuscito: ' . $e->getMessage());
        }
    }
}
