<?php
/**
 * Arco del Vento — punto d'ingresso unico.
 *
 * Tutte le richieste passano da qui: .htaccess riscrive ogni indirizzo su
 * questo file, che avvia l'applicazione e restituisce la risposta.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';
require dirname(__DIR__) . '/src/Support/helpers.php';

use ArcoDelVento\App;
use ArcoDelVento\Http\Kernel;
use ArcoDelVento\Http\Request;

$config = require dirname(__DIR__) . '/config/config.php';

// In produzione gli errori si scrivono nel registro, non nella pagina: un
// messaggio di errore di PHP mostrato a un ospite espone percorsi del server.
$debug = (bool) $config['app']['debug'];
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED);

$app = App::boot($config);

// La sessione serve al gettone anti-CSRF e alla sola pagina di conferma.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        // Su Hostinger il sito gira in HTTPS: il cookie non deve viaggiare in chiaro.
        'secure'   => (($_SERVER['HTTPS'] ?? '') !== '') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
    ]);
    session_name('advsess');
    session_start();
}

// Intestazioni di sicurezza. Il sito non carica nulla da domini terzi —
// caratteri, immagini e fogli di stile sono tutti nostri — quindi la
// politica può restare stretta senza rompere niente.
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header(
        "Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
        . "style-src 'self'; font-src 'self'; script-src 'self'; "
        . "form-action 'self'; base-uri 'self'; frame-ancestors 'self'"
    );
}

(new Kernel($app))->handle(Request::fromGlobals())->send();
