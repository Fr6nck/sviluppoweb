<?php
declare(strict_types=1);

defined('CM_ROOT') || exit;

/** Connessione PDO condivisa (MySQL/MariaDB, utf8mb4, query preparate reali). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $db = cfg('db', []);
    $pdo = db_connect(
        (string) ($db['host'] ?? 'localhost'),
        (int) ($db['port'] ?? 3306),
        (string) ($db['name'] ?? ''),
        (string) ($db['user'] ?? ''),
        (string) ($db['pass'] ?? '')
    );
    return $pdo;
}

function db_connect(string $host, int $port, string $name, string $user, string $pass): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("SET time_zone = '" . date('P') . "'");
    return $pdo;
}

/** Nome tabella con prefisso (il prefisso è validato in installazione: solo [a-z0-9_]). */
function t(string $table): string
{
    $prefix = (string) (cfg('db', [])['prefix'] ?? 'cm_');
    return '`' . preg_replace('/[^a-z0-9_]/i', '', $prefix . $table) . '`';
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function q_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function q_value(string $sql, array $params = []): mixed
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

/* ---------- Impostazioni (chiave/valore, non soggette a bozza) ---------- */

function setting(string $name, mixed $default = null): mixed
{
    static $cache = null;
    if ($name === '__reset__') {
        $cache = null;
        return null;
    }
    if ($cache === null) {
        $cache = [];
        foreach (q('SELECT name, value FROM ' . t('settings'))->fetchAll() as $r) {
            $cache[$r['name']] = $r['value'];
        }
    }
    if (!array_key_exists($name, $cache)) {
        return $default;
    }
    $decoded = json_decode((string) $cache[$name], true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $cache[$name];
}

function setting_set(string $name, mixed $value): void
{
    q(
        'INSERT INTO ' . t('settings') . ' (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
        [$name, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
    );
    setting('__reset__');
}

/* ---------- Registro delle azioni ---------- */

function audit(string $action, string $details = '', ?int $userId = null): void
{
    try {
        if ($userId === null && function_exists('current_user')) {
            $u = current_user();
            $userId = $u ? (int) $u['id'] : null;
        }
        q(
            'INSERT INTO ' . t('audit_log') . ' (user_id, action, details, ip_hash, created_at) VALUES (?, ?, ?, ?, ?)',
            [$userId, $action, mb_substr($details, 0, 2000), ip_hash(), now()]
        );
    } catch (Throwable $e) {
        log_message('Registro azioni non scritto: ' . $e->getMessage());
    }
}
