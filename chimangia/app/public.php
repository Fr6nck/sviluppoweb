<?php
/**
 * Avvio delle pagine pubbliche (home, privacy, cookie).
 * Le pagine arrivano dalla cache HTML: il database si interroga solo se la cache manca.
 * Nessuna sessione viene creata per i visitatori.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once CM_APP . '/content.php';
require_once CM_APP . '/render.php';

/** Pubblica la pagina richiesta, rispettando la modalità manutenzione. */
function serve_public_page(string $name, string $prefix): never
{
    require_installed($prefix);
    security_headers(public_csp());
    header('Content-Type: text/html; charset=utf-8');

    if (is_file(maintenance_flag()) && !maintenance_bypass()) {
        http_response_code(503);
        header('Retry-After: 3600');
        header('Cache-Control: no-store');
        $msg = trim((string) @file_get_contents(maintenance_flag()));
        echo render_maintenance($msg !== '' ? $msg : 'Stiamo aggiornando il sito. Torna a trovarci tra poco.', $prefix);
        exit;
    }

    $html = serve_cached($name, $prefix);
    // Il token del modulo cambia a ogni visita: la pagina non va messa in cache dal browser.
    header('Cache-Control: no-cache, must-revalidate');
    echo $name === 'index' ? finalize_public_html($html) : $html;
    exit;
}

/** In manutenzione gli utenti collegati al pannello vedono comunque il sito. */
function maintenance_bypass(): bool
{
    require_once CM_APP . '/auth.php';
    if (!has_admin_cookie()) {
        return false;
    }
    session_start_secure();
    $ok = current_user() !== null;
    session_write_close();
    return $ok;
}
