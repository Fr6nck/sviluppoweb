<?php
declare(strict_types=1);

use MHW\{Auth, Config, Csrf, Installer, Log, Migrator, Router, Support};

/**
 * Dove sta l'applicazione.
 *
 * Si controlla prima il caso senza ambiguità — una cartella "app" accanto a
 * index.php — e poi la disposizione consigliata, con l'applicazione sopra la
 * radice pubblica. In tutti e due i casi si pretendono TRE segni
 * caratteristici: una cartella "src" qualsiasi, lasciata lì da un altro
 * progetto, non deve poter dirottare l'applicazione.
 */
function mhw_trova_app(string $qui): ?string
{
    foreach ([$qui . '/app', dirname($qui)] as $c) {
        if (is_file($c . '/config.php') && is_file($c . '/src/Config.php') && is_dir($c . '/views')) {
            return $c;
        }
    }
    return null;
}

/**
 * Un errore fatale su un hosting con display_errors spento dà una pagina
 * bianca e un 500 muto. Qui diventa un messaggio leggibile per chi usa il
 * sito, con un codice da citare; i dettagli tecnici vanno nel registro
 * (storage/logs/app.log) e si vedono a schermo solo con debug acceso.
 */
function mhw_fatale(string $titolo, string $dettaglio = '', string $codice = ''): never
{
    if (!headers_sent()) { http_response_code(500); header('Content-Type: text/html; charset=utf-8'); }
    echo '<!doctype html><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1"><title>Errore</title>'
       . '<div style="font-family:system-ui,sans-serif;max-width:640px;margin:48px auto;padding:24px;'
       . 'background:#f9edd2;color:#231b12;border-radius:14px;line-height:1.55">'
       . '<strong style="font-size:18px">' . htmlspecialchars($titolo) . '</strong>';
    if ($dettaglio !== '') echo '<p style="margin:12px 0 0">' . htmlspecialchars($dettaglio) . '</p>';
    if ($codice !== '') echo '<p style="margin:12px 0 0;font-size:14px">Codice per l\'assistenza: <strong>' . htmlspecialchars($codice) . '</strong></p>';
    echo '</div>';
    exit;
}

$APP = mhw_trova_app(__DIR__);

set_exception_handler(function (\Throwable $e): void {
    $codice = '';
    if (defined('MHW_APP') && class_exists(Log::class)) $codice = Log::exception($e, 'non gestita');
    $debug = class_exists(Config::class, false) && (bool) Config::get('debug');
    mhw_fatale('Qualcosa non ha funzionato.',
        $debug ? get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
               : 'Riprova tra qualche istante. Se il problema resta, scrivici indicando il codice qui sotto.',
        $codice);
});

if ($APP === null) {
    mhw_fatale('Non trovo la cartella dell\'applicazione.',
        'Accanto a index.php deve esserci una cartella "app" che contiene config.php, src/ e views/. '
        . 'Se hai appena caricato i file via FTP, apri controllo.php: dice cosa manca.');
}
define('MHW_APP', $APP);
// La cartella servita dal web: foglio di stile e fotografie stanno qui.
define('MHW_PUBLIC', __DIR__);

foreach (['config.php', 'src/Config.php', 'src/Support.php', 'src/Router.php', 'src/routes_public.php',
          'src/routes_host.php', 'src/routes_admin.php', 'views/layout/app.php'] as $necessario) {
    if (!is_file($APP . '/' . $necessario)) {
        mhw_fatale('Manca un file dell\'applicazione: ' . $necessario,
                   'Ricarica la cartella app/ per intero: il trasferimento FTP non è arrivato in fondo.');
    }
}

spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'MHW\\')) return;
    $file = MHW_APP . '/src/' . substr($class, 4) . '.php';
    if (is_file($file)) require $file;
});

Config::load(MHW_APP . '/config.php');

// Un aggiornamento caricato via FTP porta con sé le sue migrazioni: si
// applicano da sole alla prima richiesta. Quando non c'è niente di nuovo
// costa la lettura di un file.
if (!Migrator::upToDate() && Installer::installed()) Migrator::run();

$route = Support::routePath();
$ospite = Support::isGuestPath($route);

// Le guide degli ospiti non aprono sessioni: niente cookie, niente dati
// personali. Hanno solo pagine in lettura, quindi non serve nemmeno il CSRF.
if (!$ospite) Auth::start();

// Una pagina con un modulo dentro non va mai messa in cache: servirebbe a
// qualcun altro un token di sessione ormai scaduto.
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('X-LiteSpeed-Cache-Control: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
// Le guide contengono informazioni operative della casa: non si indicizzano.
if ($ospite) header('X-Robots-Tag: noindex, nofollow, noarchive');

// Il webhook di Stripe non arriva da un browser e non porta un token di
// sessione: la sua autenticazione è la firma, verificata dentro la rotta.
// Si confronta il percorso DI ROTTA, lo stesso che vede il router: così
// funziona in una sottocartella e con o senza index.php nell'indirizzo.
if ($route !== '/webhook/stripe' && !$ospite) Csrf::check();

// Le email di richiamo (Richiami): un controllo leggero dopo le pagine del pannello e della
// landing, al massimo ogni 15 minuti. Gira a pagina già mandata: chi naviga non aspetta.
if (($route === '/' || str_starts_with($route, '/pannello')) && Installer::installed()) {
    register_shutdown_function([MHW\Richiami::class, 'forse']);
}

$r = new Router();
require MHW_APP . '/src/routes_public.php';
require MHW_APP . '/src/routes_host.php';
require MHW_APP . '/src/routes_admin.php';
$r->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
