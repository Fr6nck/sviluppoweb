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

/*
 * Senza APP_URL il sito non sa in che cartella sta né qual è il suo indirizzo:
 * in una sottocartella ogni link si romperebbe in silenzio. In locale va bene
 * — il server di sviluppo sta sempre alla radice — ma su un server vero vuol
 * dire che il .env non è arrivato, o non è stato rinominato. Meglio dirlo.
 */
$host   = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$locale = $host === '' || preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/', $host) === 1;
if ((string) $config['app']['url'] === '' && !$locale) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('Retry-After: 600');
    echo "Arco del Vento: installazione incompleta.\n\n"
       . "Manca il file .env nella cartella del sito, oppure non contiene APP_URL.\n"
       . "Carica env-prova.txt (o env-produzione.txt sul dominio vero) accanto a\n"
       . ".htaccess e rinominalo .env, con il punto davanti e senza .txt.\n";
    exit;
}

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

    /*
     * Le sessioni stanno nella cartella del sito, non in quella di PHP.
     *
     * Su blackout.in ogni modulo rispondeva «sessione scaduta»: il cookie
     * arrivava, ma PHP non ritrovava i dati salvati alla pagina prima. Su un
     * hosting condiviso la cartella delle sessioni di PHP può non essere
     * scrivibile, o essere comune ad altri programmi che la svuotano coi loro
     * tempi — e con gli errori nascosti non se ne accorge nessuno. In
     * storage/sessions le sessioni sono nostre: scrivibili, protette da
     * storage/.htaccess, pulite da PHP stesso.
     */
    $sessioni = dirname(__DIR__) . '/storage/sessions';
    if (!is_dir($sessioni)) {
        @mkdir($sessioni, 0700, true);
    }
    if (is_dir($sessioni) && is_writable($sessioni)) {
        // File, e nella nostra cartella: se l'hosting avesse impostato un
        // altro archivio (memcached, redis) non funzionante, qui non conta.
        ini_set('session.save_handler', 'files');
        session_save_path($sessioni);
        // La pulizia delle sessioni vecchie la fa PHP, una volta ogni cento
        // richieste: in una cartella nostra nessun altro la farebbe.
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
    }
    // Dodici ore: quanto la durata massima di un accesso all'area riservata.
    ini_set('session.gc_maxlifetime', '43200');
    // Un identificativo di sessione mai emesso dal server non si accetta.
    ini_set('session.use_strict_mode', '1');
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

    // Le pagine portano il gettone anti-CSRF della sessione di chi le guarda:
    // una copia in cache, servita a un altro, gli farebbe fallire ogni modulo.
    // E su un server condiviso con WordPress, la sua cache non deve metterci
    // le mani — è successo in prova, con i rimandi di WordPress memorizzati.
    header('X-LiteSpeed-Cache-Control: no-cache');

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
