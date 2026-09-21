<?php
/**
 * File di controllo, da caricare accanto a index.php.
 *
 * Serve solo a capire perché il server risponde 500 con una pagina vuota:
 * accende la visualizzazione degli errori SOLO qui dentro, prova a caricare
 * l'applicazione un pezzo alla volta e dice esattamente dove si rompe.
 *
 * TOGLIETELO dal server appena l'applicazione funziona.
 */

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

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

$APP = mhw_trova_app(__DIR__) ?? (__DIR__ . '/app');   // se non la trova, mostra dove ha cercato
$trovata = mhw_trova_app(__DIR__) !== null;
define('MHW_APP', $APP);   // le viste e l'installatore la usano per trovarsi

function riga(string $etichetta, bool $ok, string $dettaglio = ''): void
{
    $colore = $ok ? '#1f6b3f' : '#9c2b20';
    $segno  = $ok ? 'OK' : 'NO';
    echo '<tr><td style="padding:6px 12px;border-bottom:1px solid #e2d7c4">'
       . htmlspecialchars($etichetta)
       . '</td><td style="padding:6px 12px;border-bottom:1px solid #e2d7c4;color:' . $colore
       . ';font-weight:700">' . $segno
       . '</td><td style="padding:6px 12px;border-bottom:1px solid #e2d7c4;color:#6a5b48">'
       . htmlspecialchars($dettaglio) . '</td></tr>';
}

echo '<!doctype html><html lang="it"><head><meta charset="utf-8">'
   . '<meta name="viewport" content="width=device-width,initial-scale=1">'
   . '<title>Controllo MyHouse Welcome</title></head>'
   . '<body style="font-family:system-ui,sans-serif;background:#faf5ec;color:#231b12;margin:0;padding:24px">'
   . '<h1 style="font-size:24px">Controllo dell\'installazione</h1>'
   . '<table style="width:100%;max-width:900px;border-collapse:collapse;background:#fff;'
   . 'border:1px solid #e2d7c4;border-radius:12px;overflow:hidden">';

// ------------------------------------------------------------------ ambiente
riga('Versione di PHP', version_compare(PHP_VERSION, '8.1', '>='), PHP_VERSION . ' (serve almeno 8.1)');
riga('Interfaccia del server', true, PHP_SAPI . ' — ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'sconosciuto'));

foreach (['pdo' => 'PDO', 'pdo_sqlite' => 'PDO SQLite', 'gd' => 'GD (immagini e QR)',
          'mbstring' => 'mbstring', 'session' => 'sessioni', 'json' => 'JSON',
          'openssl' => 'OpenSSL', 'curl' => 'cURL', 'fileinfo' => 'fileinfo'] as $ext => $nome) {
    $ok = extension_loaded($ext);
    riga('Estensione: ' . $nome, $ok, $ok ? 'presente' : 'MANCANTE — chiedete all\'assistenza di attivarla');
}
riga('Driver SQLite per PDO', in_array('sqlite', \PDO::getAvailableDrivers(), true),
     'driver disponibili: ' . implode(', ', \PDO::getAvailableDrivers()));

// -------------------------------------------------------------------- i file
riga('Cartella dell\'applicazione trovata', $trovata,
     $trovata ? $APP
              : 'NON TROVATA. Accanto a index.php serve una cartella "app" con dentro '
                . 'config.php, src/ e views/. Ho guardato in: ' . __DIR__ . '/app');

$attesi = [
    'config.php', 'migrations/001_schema.sql',
    'src/Auth.php', 'src/Billing.php', 'src/Config.php', 'src/Csrf.php', 'src/Db.php',
    'src/Entitlements.php', 'src/Guide.php', 'src/Installer.php', 'src/Media.php',
    'src/Qr.php', 'src/Router.php', 'src/Stripe.php', 'src/Support.php',
    'src/Translator.php', 'src/View.php', 'src/routes_admin.php', 'src/routes_host.php',
    'views/layout/app.php', 'views/layout/bare.php', 'views/layout/guest.php',
    'views/pub/home.php', 'views/pub/install.php', 'views/pub/404.php',
    'views/pub/checkout.php', 'views/pub/paid.php',
    'views/auth/login.php', 'views/auth/register.php',
    'views/host/dashboard.php', 'views/host/languages.php', 'views/host/new_property.php',
    'views/host/properties.php', 'views/host/qr.php', 'views/host/section.php',
    'views/host/settings.php',
    'views/admin/customer.php', 'views/admin/customers.php', 'views/admin/packages.php',
    'views/admin/diagnostics.php',
    'views/guest/guide.php', 'views/guest/section.php',
];
$mancanti = [];
$vuoti = [];
foreach ($attesi as $rel) {
    $f = $APP . '/' . $rel;
    if (!is_file($f)) { $mancanti[] = $rel; continue; }
    if (filesize($f) === 0) $vuoti[] = $rel;
}
riga('Tutti i file dell\'applicazione presenti', $mancanti === [],
     $mancanti === [] ? count($attesi) . ' file trovati'
                      : 'MANCANO: ' . implode(', ', array_slice($mancanti, 0, 8))
                        . (count($mancanti) > 8 ? ' e altri ' . (count($mancanti) - 8) : ''));
riga('Nessun file arrivato vuoto', $vuoti === [],
     $vuoti === [] ? 'tutti con contenuto' : 'VUOTI: ' . implode(', ', $vuoti));

// ----------------------------------------------------------- permessi e dati
$storage = $APP . '/storage';
riga('Cartella storage presente', is_dir($storage), $storage);
riga('Cartella storage scrivibile', is_dir($storage) && is_writable($storage),
     is_writable($storage) ? 'si può scrivere' : 'DATE PERMESSI 755 o 775 a app/storage');
$up = $storage . '/uploads';
riga('Cartella uploads scrivibile', is_dir($up) && is_writable($up),
     is_writable($up) ? 'si può scrivere' : 'DATE PERMESSI 755 o 775 a app/storage/uploads');

// --------------------------------------------------------------- sessioni
// Il guasto piu' insidioso: il cookie regge ma il contenuto della sessione
// si perde, e ogni modulo viene respinto come "sessione scaduta".
$sessDir = $storage . '/sessions';
if (!is_dir($sessDir)) @mkdir($sessDir, 0770, true);
$sessOk = is_dir($sessDir) && is_writable($sessDir);
if ($sessOk) session_save_path($sessDir);
@session_start();
$giro = (int) ($_GET['giro'] ?? 0);
if ($giro === 0) {
    $_SESSION['prova'] = 'valore-di-prova';
    riga('Cartella delle sessioni scrivibile', $sessOk,
         $sessOk ? $sessDir : 'NON scrivibile: ' . $sessDir);
    riga('Sessione da verificare', false,
         'Ho scritto un valore in sessione. RICARICATE questa pagina aggiungendo ?giro=1 '
         . 'in fondo all\'indirizzo per sapere se e\' sopravvissuto.');
} else {
    $sopravvissuto = ($_SESSION['prova'] ?? null) === 'valore-di-prova';
    riga('Cartella delle sessioni scrivibile', $sessOk, $sessOk ? $sessDir : 'NON scrivibile');
    riga('La sessione sopravvive fra due richieste', $sopravvissuto,
         $sopravvissuto ? 'sì: i moduli funzioneranno'
                        : 'NO: e\' questo che fa respingere i moduli con "sessione scaduta"');
}
riga('Dove PHP salva le sessioni', true, session_save_path() ?: '(predefinita del server)');

$prova = $storage . '/prova-scrittura.tmp';
$scritto = @file_put_contents($prova, 'prova');
riga('Scrittura reale nella cartella storage', $scritto !== false,
     $scritto !== false ? 'riuscita' : 'FALLITA: il database non si può creare');
if ($scritto !== false) @unlink($prova);

// -------------------------------------- caricamento dell'applicazione, a pezzi
echo '</table><h2 style="font-size:20px;margin-top:28px">Caricamento dell\'applicazione</h2>'
   . '<table style="width:100%;max-width:900px;border-collapse:collapse;background:#fff;'
   . 'border:1px solid #e2d7c4;border-radius:12px;overflow:hidden">';

$fatale = null;
try {
    spl_autoload_register(function (string $class) use ($APP): void {
        if (!str_starts_with($class, 'MHW\\')) return;
        $file = $APP . '/src/' . substr($class, 4) . '.php';
        if (is_file($file)) require $file;
    });

    $cfg = require $APP . '/config.php';
    riga('config.php si carica', is_array($cfg), 'restituisce ' . gettype($cfg));

    foreach (['Config', 'Support', 'Db', 'Auth', 'Csrf', 'Router', 'View',
              'Entitlements', 'Guide', 'Media', 'Qr', 'Stripe', 'Billing',
              'Translator', 'Installer'] as $c) {
        $nome = 'MHW\\' . $c;
        class_exists($nome);
        riga('Classe ' . $c, class_exists($nome, false), class_exists($nome, false) ? 'caricata' : 'NON caricata');
    }

    \MHW\Config::load($APP . '/config.php');
    riga('Configurazione caricata', true, 'driver: ' . \MHW\Config::get('db')['driver']);

    $pdo = \MHW\Db::conn();
    riga('Connessione al database', true, 'riuscita');
    riga('Database già installato', \MHW\Installer::installed(),
         \MHW\Installer::installed() ? 'sì: aprite /installa solo se volete ricominciare' : 'no: aprite /installa');

    ob_start(); $vista = \MHW\View::render('pub/install', ['err' => null], 'layout/bare'); ob_end_clean();
    riga('Le pagine si disegnano', str_contains($vista, 'Installazione'), strlen($vista) . ' byte prodotti');

    // I due file di rotte si caricano davvero? Un file troncato dal
    // trasferimento FTP si manifesta qui, con file e riga esatti.
    $r = new \MHW\Router();
    require $APP . '/src/routes_host.php';
    riga('routes_host.php si carica', true, 'nessun errore di sintassi');
    require $APP . '/src/routes_admin.php';
    riga('routes_admin.php si carica', true, 'nessun errore di sintassi');

} catch (\Throwable $e) {
    $fatale = $e;
}

echo '</table>';

if ($fatale) {
    echo '<div style="max-width:900px;margin-top:24px;padding:20px;background:#f8e3df;'
       . 'border-radius:12px;color:#9c2b20">'
       . '<strong style="font-size:18px">Ecco l\'errore che il server nascondeva:</strong>'
       . '<p style="margin:12px 0 0;font-family:ui-monospace,monospace;font-size:14px;'
       . 'white-space:pre-wrap;word-break:break-word">'
       . htmlspecialchars(get_class($fatale) . ': ' . $fatale->getMessage()) . "\n\n"
       . htmlspecialchars('in ' . $fatale->getFile() . ' alla riga ' . $fatale->getLine())
       . '</p></div>';
} else {
    echo '<p style="max-width:900px;margin-top:24px;padding:20px;background:#e1f0e5;'
       . 'border-radius:12px;color:#1f6b3f"><strong>L\'applicazione si carica senza errori.</strong> '
       . 'Se le pagine danno ancora 500, il problema è nella riscrittura degli indirizzi '
       . 'o in un file .htaccess: provate <code>index.php/installa</code>.</p>';
}

echo '<p style="max-width:900px;margin-top:20px;color:#6a5b48;font-size:14px">'
   . 'Quando tutto funziona, <strong>cancellate questo file dal server</strong>.</p>'
   . '</body></html>';
