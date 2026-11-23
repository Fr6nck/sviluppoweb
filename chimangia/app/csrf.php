<?php
declare(strict_types=1);

defined('CM_ROOT') || exit;

/* ---------- CSRF del pannello (legato alla sessione) ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = random_token();
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(?string $token = null): bool
{
    $token ??= (string) ($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals((string) $_SESSION['csrf'], $token);
}

/** Blocca la richiesta se il token non è valido. */
function csrf_check(): void
{
    if (!csrf_valid()) {
        http_response_code(400);
        if (function_exists('admin_message_page')) {
            admin_message_page('Richiesta non valida', 'Il modulo è scaduto o non è valido. Torna indietro, ricarica la pagina e riprova.');
        }
        echo 'Richiesta non valida.';
        exit;
    }
}

/* ---------- Token del modulo pubblico (senza cookie) ----------
 * Il sito pubblico non usa sessioni: il token è firmato con il segreto
 * dell'applicazione e contiene l'ora di generazione, così serve anche
 * per verificare il tempo minimo di compilazione.
 */

function form_token(): string
{
    $ts = (string) time();
    $nonce = bin2hex(random_bytes(6));
    return $ts . '.' . $nonce . '.' . hash_hmac('sha256', 'contatti|' . $ts . '|' . $nonce, app_secret());
}

/**
 * Verifica il token del modulo. Restituisce l'età in secondi oppure -1 se non valido.
 * I token valgono 6 ore.
 */
function form_token_age(string $token): int
{
    $parts = explode('.', $token);
    if (count($parts) !== 3 || !ctype_digit($parts[0])) {
        return -1;
    }
    [$ts, $nonce, $sig] = $parts;
    $expected = hash_hmac('sha256', 'contatti|' . $ts . '|' . $nonce, app_secret());
    if (!hash_equals($expected, $sig)) {
        return -1;
    }
    $age = time() - (int) $ts;
    return ($age < 0 || $age > 6 * 3600) ? -1 : $age;
}
