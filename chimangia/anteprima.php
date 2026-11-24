<?php
/**
 * Anteprima della bozza. Accessibile agli utenti collegati al pannello
 * oppure con il link riservato (?t=…), rigenerabile dalle impostazioni.
 */
declare(strict_types=1);

define('CM_ROOT', __DIR__);
require __DIR__ . '/app/public.php';
require_once CM_APP . '/auth.php';

require_installed();
security_headers(public_csp());
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, private');
header('Content-Type: text/html; charset=utf-8');

$allowed = false;
$token = get_param('t');
if ($token !== '' && hash_equals((string) setting('preview_token', ''), $token)) {
    $allowed = true;
} elseif (has_admin_cookie()) {
    session_start_secure();
    $allowed = current_user() !== null;
    session_write_close();
}

if (!$allowed) {
    http_response_code(403);
    echo '<!doctype html><html lang="it"><meta charset="utf-8"><meta name="robots" content="noindex"><title>Anteprima riservata</title>'
        . '<link rel="stylesheet" href="assets/css/site.css"><main class="maintenance"><div class="maintenance-box">'
        . '<h1>Anteprima riservata</h1><p>Per vedere la bozza accedi al pannello oppure usa il link riservato.</p>'
        . '<p><a class="btn btn-dark" href="admin/login.php">Accedi al pannello</a></p></div></main></html>';
    exit;
}

$draft = content_all('draft');
$page = get_param('pagina');
if (in_array($page, ['privacy', 'cookie'], true)) {
    // Stessa struttura delle pagine pubbliche, ma servita da qui: i percorsi partono dalla radice.
    echo finalize_public_html(render_legal($draft, $page, ['prefix' => '', 'preview' => true]));
} else {
    echo finalize_public_html(render_site($draft, ['prefix' => '', 'preview' => true]));
}
