<?php
/**
 * Immagine del CAPTCHA (SVG) e nuova sfida.
 *   captcha.php?t=TOKEN   → immagine della sfida contenuta nel token
 *   captcha.php?new=1     → JSON con un token nuovo (pulsante "Cambia codice")
 * Nessuna sessione e nessun cookie.
 */
declare(strict_types=1);

define('CM_ROOT', __DIR__);
require __DIR__ . '/app/bootstrap.php';
require CM_APP . '/platform/captcha.php';

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex');

if (!is_installed()) {
    http_response_code(404);
    exit;
}

if (get_param('new') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['t' => captcha_new()]);
    exit;
}

$data = captcha_open(get_param('t'));
if ($data === null) {
    // Scaduto o non valido: un riquadro vuoto, così il modulo invita a cambiare codice.
    http_response_code(410);
    header('Content-Type: image/svg+xml; charset=utf-8');
    echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 76" width="240" height="76"><rect width="240" height="76" rx="14" fill="#FBF7EF"/>'
        . '<path d="M96 38h48" stroke="#13294B" stroke-width="3" stroke-linecap="round"/></svg>';
    exit;
}

header('Content-Type: image/svg+xml; charset=utf-8');
header("Content-Security-Policy: default-src 'none'");
echo captcha_svg((string) $data['c']);
