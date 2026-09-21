<?php
declare(strict_types=1);

use MHW\{Auth, Billing, Config, Csrf, Db, Entitlements, Guide, Installer, Media, Qr, Router, Stripe, Support, Translator, View};

// L'applicazione può stare sopra la cartella pubblica (disposizione consigliata)
// oppure dentro una sottocartella "app", come serve sugli hosting con solo FTP.
/**
 * Dove sta l'applicazione.
 *
 * Si controlla prima il caso senza ambiguità — una cartella "app" accanto a
 * index.php — e poi la disposizione consigliata, con l'applicazione sopra la
 * radice pubblica. In tutti e due i casi si pretendono DUE file
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

$APP = mhw_trova_app(__DIR__);


/**
 * Un errore fatale su un hosting con display_errors spento dà una pagina
 * bianca e un 500 muto, impossibile da diagnosticare via FTP. Qui lo
 * trasformiamo in un messaggio leggibile, senza mai rivelare i percorsi
 * del server a chi passa di lì per caso.
 */
function mhw_fatale(string $titolo, string $dettaglio = ''): never
{
    if (!headers_sent()) { http_response_code(500); header('Content-Type: text/html; charset=utf-8'); }
    echo '<!doctype html><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<div style="font-family:system-ui,sans-serif;max-width:640px;margin:48px auto;padding:24px;'
       . 'background:#f8e3df;color:#9c2b20;border-radius:14px;line-height:1.55">'
       . '<strong style="font-size:18px">' . htmlspecialchars($titolo) . '</strong>';
    if ($dettaglio !== '') echo '<p style="margin:12px 0 0">' . htmlspecialchars($dettaglio) . '</p>';
    echo '<p style="margin:14px 0 0;font-size:14px">Caricate <code>controllo.php</code> accanto a '
       . '<code>index.php</code> e apritelo: dice esattamente cosa manca.</p></div>';
    exit;
}

set_exception_handler(function (\Throwable $e): void {
    mhw_fatale('L\'applicazione si è fermata su un errore.', get_class($e) . ': ' . $e->getMessage());
});

if ($APP === null) {
    mhw_fatale(
        'Non trovo la cartella dell\'applicazione.',
        'Accanto a index.php deve esserci una cartella "app" che contiene config.php, '
        . 'src/Config.php e views/. Cercata in: ' . basename(__DIR__) . '/app'
    );
}
define('MHW_APP', $APP);

foreach (['config.php', 'src/Config.php', 'src/Support.php', 'src/Router.php',
          'src/routes_host.php', 'src/routes_admin.php', 'views/layout/app.php'] as $necessario) {
    if (!is_file($APP . '/' . $necessario)) {
        mhw_fatale('Manca un file dell\'applicazione: ' . $necessario,
                   'Ricaricate la cartella app/ per intero: il trasferimento FTP non è arrivato in fondo.');
    }
}

spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'MHW\\')) return;
    $file = MHW_APP . '/src/' . substr($class, 4) . '.php';
    if (is_file($file)) require $file;
});

Config::load(MHW_APP . '/config.php');
Auth::start();

// Una pagina con un modulo dentro non va mai messa in cache: servirebbe a
// qualcun altro un token di sessione ormai scaduto. Su questo hosting c'e'
// un livello di cache davanti (x-nginx-cache), quindi lo diciamo esplicitamente.
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('X-LiteSpeed-Cache-Control: no-cache');

// Il webhook non arriva da un browser e non può portare un token di sessione:
// la sua autenticazione è la firma di Stripe, verificata dentro la rotta.
$path = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
if ($path !== '/webhook/stripe') Csrf::check();

$r = new Router();

// ---------------------------------------------------------------- installazione
$r->any('/installa', function () {
    if (Installer::installed()) Support::redirect('/');
    $err = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            Installer::install(trim((string) $_POST['email']), (string) $_POST['password']);
            Support::flash('Installazione completata. Siete dentro.');
            Auth::attempt(trim((string) $_POST['email']), (string) $_POST['password']);
            Support::redirect('/pannello');
        } catch (\Throwable $e) { $err = $e->getMessage(); }
    }
    View::out('pub/install', ['err' => $err], 'layout/bare');
});

$guard = function () {
    if (!Installer::installed()) Support::redirect('/installa');
};

// ---------------------------------------------------------------------- pubblico
$r->get('/', function () use ($guard) {
    $guard();
    $packages = Db::all(
        'SELECT p.*, pv.id AS pv_id, pv.price_cents, pv.currency FROM packages p
         JOIN package_versions pv ON pv.package_id = p.id AND pv.is_current = 1
         WHERE p.active = 1 ORDER BY p.sort'
    );
    foreach ($packages as &$pk) {
        $pk['features'] = Db::all(
            'SELECT f.code, f.label, f.kind, pf.value FROM package_features pf
             JOIN features f ON f.id = pf.feature_id WHERE pf.package_version_id = ? ORDER BY f.id',
            [$pk['pv_id']]
        );
    }
    unset($pk);
    View::out('pub/home', ['packages' => $packages]);
});

// ------------------------------------------------------------------ registrazione
$r->any('/registrati', function () use ($guard) {
    $guard();
    $err = null; $pv = (int) ($_GET['piano'] ?? $_POST['piano'] ?? 0);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $u = Auth::register((string) $_POST['email'], (string) $_POST['password'], trim((string) $_POST['name']));
            Auth::login($u['user_id']);
            Support::redirect($pv ? '/acquista/' . $pv : '/pannello');
        } catch (\Throwable $e) { $err = $e->getMessage(); }
    }
    View::out('auth/register', ['err' => $err, 'piano' => $pv], 'layout/bare');
});

$r->any('/accedi', function () use ($guard) {
    $guard();
    $err = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (Auth::attempt((string) $_POST['email'], (string) $_POST['password'])) {
            $u = Auth::user();
            Support::redirect(($u['role'] ?? '') === 'admin' ? '/admin' : '/pannello');
        }
        $err = 'Email o password non corrispondono.';
    }
    View::out('auth/login', ['err' => $err], 'layout/bare');
});

$r->post('/esci', function () { Auth::logout(); Support::redirect('/'); });

// ------------------------------------------------------------------- pagamento
$r->any('/acquista/{pv}', function (array $a) {
    Auth::requireUser();
    $acc = Auth::account();
    $pv = Db::one('SELECT * FROM package_versions WHERE id = ?', [(int) $a['pv']]);
    if (!$pv) { http_response_code(404); exit('Piano inesistente.'); }
    $pkg = Db::one('SELECT * FROM packages WHERE id = ?', [$pv['package_id']]);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $orderId = Db::insert('orders', [
            'account_id' => $acc['id'], 'package_version_id' => $pv['id'],
            'amount_cents' => $pv['price_cents'], 'currency' => $pv['currency'],
            'status' => 'pending', 'provider' => Stripe::enabled() ? 'stripe' : 'prova',
            'provider_session_id' => '', 'created_at' => Support::now(),
        ]);
        $order = Db::one('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if (Stripe::enabled()) {
            try { Support::redirect(Stripe::checkout($order, $pv, $pkg, Auth::user()['email'])); }
            catch (\Throwable $e) { Support::flash('Stripe: ' . $e->getMessage(), 'err'); Support::redirect('/acquista/' . $pv['id']); }
        }
        // Senza chiavi Stripe: ordine confermato localmente, dichiarato come prova.
        Billing::markPaid($orderId, 'prova');
        Support::flash('Piano ' . $pkg['name'] . ' attivato in modalità prova (nessun pagamento reale).');
        Support::redirect('/pannello');
    }
    View::out('pub/checkout', ['pv' => $pv, 'pkg' => $pkg], 'layout/bare');
});

$r->get('/pagamento/ok', function () {
    Auth::requireUser();
    // Il ritorno dal browser non prova nulla: la verità arriva dal webhook firmato.
    View::out('pub/paid', ['order' => (int) ($_GET['order'] ?? 0)], 'layout/bare');
});
$r->get('/pagamento/annullato', function () {
    Support::flash('Pagamento annullato. Non è stato addebitato nulla.', 'err');
    Support::redirect('/');
});

$r->post('/webhook/stripe', function () {
    $payload = (string) file_get_contents('php://input');
    $secret = Config::get('stripe')['webhook_secret'];
    $sig = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    if ($secret === '' || !Stripe::verifySignature($payload, $sig, $secret)) {
        http_response_code(400); exit('firma non valida');
    }
    $event = json_decode($payload, true);
    if (!is_array($event)) { http_response_code(400); exit('payload illeggibile'); }
    echo Stripe::handleEvent($event);
});

// -------------------------------------------------------------- guida pubblica
$r->get('/q/{token}', function (array $a) {
    $t = Db::one('SELECT * FROM qr_tokens WHERE token = ?', [$a['token']]);
    if (!$t) { http_response_code(404); echo View::render('pub/404'); return; }
    Db::run('UPDATE qr_tokens SET scans = scans + 1 WHERE id = ?', [$t['id']]);
    $p = Db::one('SELECT slug FROM properties WHERE id = ?', [$t['property_id']]);
    Guide::track((int) $t['property_id'], 'qr');
    Support::redirect('/g/' . $p['slug']);          // il token non cambia mai, lo slug sì
});

$r->get('/g/{slug}', function (array $a) {
    $g = Guide::bySlug($a['slug']);
    if (!$g) { http_response_code(404); echo View::render('pub/404'); return; }
    $snap = $g['snapshot'];
    $loc = (string) ($_GET['l'] ?? '');
    if (!in_array($loc, $snap['locales'], true)) $loc = $snap['property']['default_locale'];
    Guide::track((int) $g['property']['id'], 'open', null, $loc);
    View::out('guest/guide', ['snap' => $snap, 'loc' => $loc, 'slug' => $a['slug']], 'layout/guest');
});

$r->get('/g/{slug}/{sid}', function (array $a) {
    $g = Guide::bySlug($a['slug']);
    if (!$g) { http_response_code(404); echo View::render('pub/404'); return; }
    $snap = $g['snapshot'];
    $loc = (string) ($_GET['l'] ?? '');
    if (!in_array($loc, $snap['locales'], true)) $loc = $snap['property']['default_locale'];
    $section = null;
    foreach ($snap['sections'] as $s) if ((string) $s['id'] === $a['sid']) $section = $s;
    if (!$section) { http_response_code(404); echo View::render('pub/404'); return; }
    Guide::track((int) $g['property']['id'], 'section', (int) $section['id'], $loc);
    View::out('guest/section', ['snap' => $snap, 'sec' => $section, 'loc' => $loc, 'slug' => $a['slug']], 'layout/guest');
});

// I file caricati sono serviti da PHP, così restano fuori dalla cartella pubblica.
$r->get('/media/{file}', function (array $a) {
    $name = basename($a['file']);
    $path = Config::get('uploads_dir') . '/' . $name;
    if (!is_file($path)) { http_response_code(404); exit; }
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=31536000, immutable');
    readfile($path);
});

$r->get('/qr/{token}.png', function (array $a) {
    $t = Db::one('SELECT * FROM qr_tokens WHERE token = ?', [$a['token']]);
    if (!$t) { http_response_code(404); exit; }
    header('Content-Type: image/png');
    echo Qr::png(Support::baseUrl() . '/q/' . $t['token'], 8, 4, 640);
});

require MHW_APP . '/src/routes_host.php';
require MHW_APP . '/src/routes_admin.php';

$r->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
