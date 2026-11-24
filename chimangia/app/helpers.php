<?php
declare(strict_types=1);

defined('CM_ROOT') || exit;

/** Valore di configurazione (config.php). */
function cfg(string $key, mixed $default = null): mixed
{
    $c = $GLOBALS['cm_config'] ?? null;
    return is_array($c) && array_key_exists($key, $c) ? $c[$key] : $default;
}

/** Escape per HTML (testo e attributi). */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Cartella dati privata (cache, log, lock). Preferibilmente fuori dalla web root. */
function storage_path(string $sub = ''): string
{
    $base = (string) cfg('storage_path', CM_ROOT . '/storage');
    return rtrim($base, '/\\') . ($sub !== '' ? '/' . ltrim($sub, '/') : '');
}

/** URL assoluto del sito, senza barra finale (es. https://www.chimangia.it o https://dominio.it/sotto). */
function site_url(string $path = ''): string
{
    $base = rtrim((string) cfg('site_url', ''), '/');
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

/** Percorso del sito sul dominio (es. "/" oppure "/sotto/"), usato per il cookie di sessione. */
function site_base_path(): string
{
    $p = parse_url(site_url(), PHP_URL_PATH);
    $p = is_string($p) ? $p : '';
    return rtrim($p, '/') . '/';
}

/**
 * Percorso del sito ricavato dalla richiesta in corso (es. "/" oppure "/sotto/").
 * Non dipende dall'indirizzo scritto in config.php: così il cookie di sessione
 * funziona anche se il sito viene aperto da un indirizzo diverso da quello configurato.
 */
function request_base_path(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $file = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) ?: '';
    $root = realpath(CM_ROOT) ?: CM_ROOT;
    if ($script !== '' && $file !== '' && str_starts_with($file, $root)) {
        $rel = str_replace('\\', '/', substr($file, strlen($root)));   // es. /admin/login.php
        if ($rel !== '' && str_ends_with($script, $rel)) {
            return rtrim(substr($script, 0, -strlen($rel)), '/') . '/';
        }
    }
    return site_base_path();
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https' || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on') {
        return true;
    }
    return str_contains((string) ($_SERVER['HTTP_CF_VISITOR'] ?? ''), '"https"');
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/** Hash dell'IP: non conserviamo l'indirizzo in chiaro. */
function ip_hash(?string $ip = null): string
{
    return hash_hmac('sha256', $ip ?? client_ip(), app_secret());
}

function app_secret(): string
{
    $s = (string) cfg('app_secret', '');
    return $s !== '' ? $s : 'chimangia-non-configurato';
}

function random_token(int $bytes = 32): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function redirect(string $url, int $code = 303): never
{
    header('Location: ' . $url, true, $code);
    exit;
}

function json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function get_param(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

/** Header di sicurezza comuni. $csp: politica Content-Security-Policy. */
function security_headers(string $csp): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Content-Security-Policy: ' . $csp);
}

function public_csp(): string
{
    return "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; font-src 'self'; "
        . "connect-src 'self'; form-action 'self'; frame-ancestors 'self'; base-uri 'self'; object-src 'none'";
}

function admin_csp(): string
{
    return "default-src 'self'; img-src 'self' data: blob:; style-src 'self'; script-src 'self'; font-src 'self'; "
        . "connect-src 'self'; form-action 'self'; frame-ancestors 'self'; base-uri 'self'; object-src 'none'";
}

/** Formato prezzo all'italiana: 1290 -> "1.290 €", 29.5 -> "29,50 €". */
function euro(float|int|string|null $value): string
{
    $v = (float) $value;
    $dec = abs($v - round($v)) < 0.005 ? 0 : 2;
    return number_format($v, $dec, ',', '.') . ' €';
}

function to_number(string $value): float
{
    $v = str_replace([' ', '€'], '', $value);
    if (str_contains($v, ',')) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    }
    return is_numeric($v) ? (float) $v : 0.0;
}

/** Data leggibile in italiano. */
function data_it(?string $datetime, bool $withTime = true): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '—';
    }
    return date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $ts);
}

function str_limit(string $s, int $len): string
{
    return mb_strlen($s) > $len ? mb_substr($s, 0, $len - 1) . '…' : $s;
}

/** Scrittura atomica di un file (scrive su un temporaneo e rinomina). */
function write_file_atomic(string $path, string $content): bool
{
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }
    $tmp = $dir . '/.' . basename($path) . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
        return false;
    }
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

function log_message(string $message): void
{
    error_log('[ChiMangia] ' . $message);
}

/** Simbolo del logo (da design/simbolo.svg). */
function logo_svg(int $width = 40, string $class = ''): string
{
    $h = (int) round($width * 116 / 108);
    return '<svg' . ($class ? ' class="' . e($class) . '"' : '') . ' aria-hidden="true" focusable="false" width="' . $width . '" height="' . $h . '" viewBox="0 0 108 116">'
        . '<path d="M32 36 C32 20 44 10 56 10 C70 10 80 20 80 34 C80 47 70 52 62 58 C57 62 56 66 56 70" fill="none" stroke="#13294B" stroke-width="12" stroke-linecap="round"/>'
        . '<circle cx="56" cy="100" r="12.5" fill="#F2A900"/><circle cx="56" cy="100" r="6.5" fill="none" stroke="#FBF7EF" stroke-width="2.4"/></svg>';
}
