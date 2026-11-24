<?php
declare(strict_types=1);

defined('CM_ROOT') || exit;

const CM_SESSION_NAME = 'cm_admin';
const CM_SESSION_IDLE = 1800;          // 30 minuti di inattività
const CM_LOGIN_MAX_FAILS = 5;          // tentativi errati prima del blocco
const CM_LOGIN_LOCK_MINUTES = 15;      // durata del blocco
const CM_RESET_TTL = 3600;             // validità del link di recupero (1 ora)

const CM_ROLES = ['admin' => 'Amministratore', 'editor' => 'Redattore'];

/** Avvia la sessione del pannello con cookie HttpOnly, Secure (su HTTPS) e SameSite=Lax. */
function session_start_secure(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    // Sessioni in una cartella propria: sugli hosting condivisi quella predefinita
    // può non essere scrivibile o venire svuotata da altri siti.
    $dir = storage_path('sessions');
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        ini_set('session.save_handler', 'files');
        session_save_path($dir);
        ini_set('session.gc_maxlifetime', '7200');
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
    }
    session_cache_limiter('');
    session_name(CM_SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => request_base_path(),
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Il visitatore ha un cookie di sessione del pannello? (Il sito pubblico non crea mai sessioni.) */
function has_admin_cookie(): bool
{
    return isset($_COOKIE[CM_SESSION_NAME]) && preg_match('/^[a-zA-Z0-9,-]{16,128}$/', (string) $_COOKIE[CM_SESSION_NAME]) === 1;
}

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['uid'])) {
        return null;
    }
    $last = (int) ($_SESSION['last_activity'] ?? 0);
    if ($last > 0 && time() - $last > CM_SESSION_IDLE) {
        logout_user();
        $_SESSION['flash'][] = ['type' => 'info', 'text' => 'La sessione è scaduta per inattività. Accedi di nuovo.'];
        return null;
    }
    $row = q_one('SELECT id, name, email, role, active, updated_at FROM ' . t('users') . ' WHERE id = ?', [(int) $_SESSION['uid']]);
    if (!$row || !(int) $row['active']) {
        logout_user();
        return null;
    }
    $_SESSION['last_activity'] = time();
    $user = $row;
    return $user;
}

function is_admin(?array $u = null): bool
{
    $u ??= current_user();
    return $u !== null && $u['role'] === 'admin';
}

/** Rimanda al login se non autenticato. Da chiamare in cima a ogni pagina del pannello. */
function require_login(): array
{
    $u = current_user();
    if ($u === null) {
        $back = $_SERVER['REQUEST_URI'] ?? '';
        $_SESSION['after_login'] = is_string($back) ? $back : '';
        redirect('login.php');
    }
    return $u;
}

/** Controllo del ruolo lato server: 'admin' = solo Amministratore, 'editor' = entrambi. */
function require_role(string $role): array
{
    $u = require_login();
    if ($role === 'admin' && $u['role'] !== 'admin') {
        http_response_code(403);
        admin_forbidden();
        exit;
    }
    return $u;
}

/* ---------- Blocco dopo tentativi errati ---------- */

function login_locked(string $email): bool
{
    $since = date('Y-m-d H:i:s', time() - CM_LOGIN_LOCK_MINUTES * 60);
    $byEmail = (int) q_value(
        'SELECT COUNT(*) FROM ' . t('login_attempts') . ' WHERE email = ? AND success = 0 AND created_at > ?',
        [mb_strtolower($email), $since]
    );
    $byIp = (int) q_value(
        'SELECT COUNT(*) FROM ' . t('login_attempts') . ' WHERE ip_hash = ? AND success = 0 AND created_at > ?',
        [ip_hash(), $since]
    );
    return $byEmail >= CM_LOGIN_MAX_FAILS || $byIp >= CM_LOGIN_MAX_FAILS * 3;
}

function login_record(string $email, bool $success): void
{
    q(
        'INSERT INTO ' . t('login_attempts') . ' (email, ip_hash, success, created_at) VALUES (?, ?, ?, ?)',
        [mb_strtolower($email), ip_hash(), $success ? 1 : 0, now()]
    );
    if ($success) {
        // Un accesso riuscito azzera il conteggio per quell'email.
        q('DELETE FROM ' . t('login_attempts') . ' WHERE email = ? AND success = 0', [mb_strtolower($email)]);
    }
    // Pulizia periodica dei tentativi vecchi.
    if (random_int(1, 20) === 1) {
        q('DELETE FROM ' . t('login_attempts') . ' WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400 * 30)]);
    }
}

/**
 * Tenta l'accesso. Restituisce null se riuscito, altrimenti il messaggio d'errore.
 */
function attempt_login(string $email, string $password): ?string
{
    $email = mb_strtolower(trim($email));
    if (login_locked($email)) {
        audit('accesso_bloccato', $email);
        return 'Troppi tentativi errati. Per sicurezza l\'accesso è bloccato per ' . CM_LOGIN_LOCK_MINUTES . ' minuti.';
    }
    $u = q_one('SELECT * FROM ' . t('users') . ' WHERE email = ?', [$email]);
    // Verifica sempre un hash, anche se l'utente non esiste, per non rivelarlo dai tempi di risposta.
    $hash = $u['password_hash'] ?? password_hash(random_token(8), PASSWORD_DEFAULT);
    $ok = password_verify($password, $hash) && $u !== null;
    if (!$ok || !(int) $u['active']) {
        login_record($email, false);
        audit('accesso_fallito', $email, $u ? (int) $u['id'] : null);
        return 'Email o password non corretti.';
    }
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE ' . t('users') . ' SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    login_record($email, true);
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $u['id'];
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf'] = random_token();
    q('UPDATE ' . t('users') . ' SET last_login_at = ? WHERE id = ?', [now(), $u['id']]);
    audit('accesso', $email, (int) $u['id']);
    return null;
}

function logout_user(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $flash = $_SESSION['flash'] ?? [];
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['flash'] = $flash;
}

/* ---------- Password ---------- */

/** Regole minime per le password. Restituisce il messaggio d'errore o null. */
function password_problem(string $password): ?string
{
    if (mb_strlen($password) < 10) {
        return 'La password deve avere almeno 10 caratteri.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'La password deve contenere almeno una lettera e un numero.';
    }
    return null;
}

/** Crea un link di recupero (monouso, valido un'ora) e restituisce il token in chiaro. */
function password_reset_create(int $userId): string
{
    q('UPDATE ' . t('password_resets') . ' SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [now(), $userId]);
    $token = random_token(32);
    q(
        'INSERT INTO ' . t('password_resets') . ' (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)',
        [$userId, hash('sha256', $token), date('Y-m-d H:i:s', time() + CM_RESET_TTL), now()]
    );
    return $token;
}

/** Restituisce la riga del token se valido, non usato e non scaduto. */
function password_reset_find(string $token): ?array
{
    if ($token === '' || strlen($token) > 100) {
        return null;
    }
    return q_one(
        'SELECT r.*, u.email, u.name FROM ' . t('password_resets') . ' r JOIN ' . t('users') . ' u ON u.id = r.user_id
         WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > ? AND u.active = 1',
        [hash('sha256', $token), now()]
    );
}
