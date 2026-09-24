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
use ArcoDelVento\I18n\Routes;

$config = require dirname(__DIR__) . '/config/config.php';

// In produzione gli errori si scrivono nel registro, non nella pagina: un
// messaggio di errore di PHP mostrato a un ospite espone percorsi del server.
$debug = (bool) $config['app']['debug'];
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED);

$app = App::boot($config);

/*
 * Quando qualcosa si rompe in produzione, l'ospite vede la pagina «il sito non
 * risponde» e non uno schermo bianco. Prima questo lo faceva solo .htaccess
 * con ErrorDocument, che vuole un percorso fisso dalla radice del dominio: in
 * una sottocartella — blackout.in/assisiapartment — puntava alla pagina di
 * errore di un altro sito. Qui la cartella si conosce.
 */
$paginaErrore = static function (): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        header('Retry-After: 120');
        // La pagina porta gli stili dentro di sé, apposta: se il sito è a terra
        // un foglio esterno potrebbe non arrivare. La politica generale li
        // vieterebbe, quindi per lei se ne scrive una sua, più stretta.
        header_remove('Content-Security-Policy');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
    }
    $html = (string) @file_get_contents(__DIR__ . '/500.html');
    echo str_replace('href="/"', 'href="' . htmlspecialchars(Routes::base() . '/', ENT_QUOTES) . '"', $html);
};

register_shutdown_function(static function () use ($debug, $paginaErrore): void {
    $errore = error_get_last();
    if ($debug || $errore === null) {
        return;
    }
    if (in_array($errore['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        $paginaErrore();
    }
});

// La sessione serve al gettone anti-CSRF e alla sola pagina di conferma.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        // Il cookie vale solo per la cartella del sito: in prova, su
        // blackout.in, non deve viaggiare verso le altre pagine del dominio.
        'path'     => Routes::base() . '/',
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

    // Copia di prova: fuori dai motori di ricerca, pagine e risposte tutte.
    if ((bool) $config['app']['noindex']) {
        header('X-Robots-Tag: noindex, nofollow');
    }
}

try {
    (new Kernel($app))->handle(Request::fromGlobals())->send();
} catch (\Throwable $eccezione) {
    if ($debug) {
        throw $eccezione;
    }
    error_log((string) $eccezione);
    $paginaErrore();
}
