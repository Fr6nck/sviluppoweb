<?php
/**
 * Arco del Vento — controllo prima della pubblicazione.
 *
 *     php tools/preflight.php
 *
 * Si lancia SUL SERVER, dopo aver caricato i file e compilato .env. Dice se
 * il sito è pronto a rispondere al pubblico, e dove non lo è dice cosa fare.
 *
 * Esiste perché gli errori di messa in linea si somigliano tutti — un file
 * .env dimenticato in sviluppo, una cartella che non si può scrivere, un
 * APP_URL rimasto su localhost — e si scoprono sempre dal visitatore, mai da
 * chi pubblica.
 *
 * Su Hostinger si esegue dal Terminale dell'hPanel, oppure via SSH. Chi ha
 * solo l'FTP può copiare questo file dentro public_html per un momento e
 * aprirlo nel browser: in quel caso serve un gettone, per non lasciare in
 * chiaro la configurazione del sito a chiunque passi.
 *
 *     PREFLIGHT_TOKEN=unaParolaLunga      nel file .env
 *     https://iltuodominio.it/preflight.php?token=unaParolaLunga
 *
 * E poi lo si cancella dalla cartella pubblica. Il controllo lo ricorda.
 *
 * Esce con 0 se si può pubblicare, con 1 se c'è almeno un problema bloccante.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/src/autoload.php';

use ArcoDelVento\Support\Env;

$daBrowser = PHP_SAPI !== 'cli';

Env::load($root . '/.env');

// ------------------------------------------------ chi può vedere il risultato
if ($daBrowser) {
    $atteso = (string) Env::get('PREFLIGHT_TOKEN', '');
    $dato   = (string) ($_GET['token'] ?? '');
    if ($atteso === '' || !hash_equals($atteso, $dato)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        exit("Per eseguire questo controllo dal browser serve PREFLIGHT_TOKEN nel file .env,\n"
           . "e va passato nell'indirizzo come ?token=...\n");
    }
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Robots-Tag: noindex');
}

/** @var list<array{0:string,1:string,2:string,3:string}> livello, area, esito, rimedio */
$esiti = [];
$aggiungi = static function (string $livello, string $area, string $esito, string $rimedio = '') use (&$esiti): void {
    $esiti[] = [$livello, $area, $esito, $rimedio];
};

// ------------------------------------------------------------------ PHP
$aggiungi(
    version_compare(PHP_VERSION, '8.1.0', '>=') ? 'ok' : 'bloccante',
    'PHP',
    'versione ' . PHP_VERSION,
    'Serve almeno PHP 8.1. Su Hostinger si cambia da hPanel → Avanzate → Configurazione PHP.'
);

foreach (['mbstring' => 'bloccante', 'json' => 'bloccante'] as $ext => $peso) {
    $aggiungi(
        extension_loaded($ext) ? 'ok' : $peso,
        'PHP',
        "estensione {$ext}" . (extension_loaded($ext) ? '' : ' assente'),
        "Attivala da hPanel → Avanzate → Configurazione PHP → Estensioni."
    );
}

// ------------------------------------------------------------- ambiente
$config = require $root . '/config/config.php';

$aggiungi(
    is_readable($root . '/.env') ? 'ok' : 'bloccante',
    'Ambiente',
    is_readable($root . '/.env') ? 'il file .env c’è' : 'il file .env manca',
    'Copia .env.example in .env e compilalo. Senza, il sito gira con i valori di sviluppo.'
);

$env   = (string) $config['app']['env'];
$debug = (bool) $config['app']['debug'];

$aggiungi($env === 'production' ? 'ok' : 'attenzione', 'Ambiente', "APP_ENV = {$env}",
    'In produzione va APP_ENV=production.');

$aggiungi(!$debug ? 'ok' : 'bloccante', 'Ambiente', 'APP_DEBUG = ' . ($debug ? 'true' : 'false'),
    'Con APP_DEBUG=true gli errori di PHP finiscono nella pagina, e mostrano i percorsi del server a chiunque.');

$url = (string) $config['app']['url'];
if ($url === '') {
    $aggiungi('bloccante', 'Ambiente', 'APP_URL vuoto',
        'Senza APP_URL gli indirizzi canonici, hreflang, Open Graph e la sitemap escono incompleti.');
} elseif (str_contains($url, 'localhost') || str_contains($url, '127.0.0.1')) {
    $aggiungi('bloccante', 'Ambiente', "APP_URL = {$url}",
        'È rimasto quello di sviluppo. Mettici il dominio vero, con https e senza barra finale.');
} else {
    $aggiungi(str_starts_with($url, 'https://') ? 'ok' : 'attenzione', 'Ambiente', "APP_URL = {$url}",
        'Con il certificato attivo, APP_URL va in https.');
    if (str_ends_with($url, '/')) {
        $aggiungi('attenzione', 'Ambiente', 'APP_URL finisce con una barra',
            'Toglila: gli indirizzi verrebbero costruiti con due barre di fila.');
    }
}

// ------------------------------------------------------------- prova o no
$inProva = (bool) ($config['app']['noindex'] ?? false);
$cartella = (string) ($config['app']['base'] ?? '');
$aggiungi('nota', 'Ambiente',
    $cartella === '' ? 'il sito sta alla radice del dominio' : "il sito sta nella cartella {$cartella}",
    'La ricava da APP_URL: link, reindirizzamenti e file statici la seguono da soli.');
$aggiungi($inProva ? 'attenzione' : 'ok', 'Ambiente',
    $inProva ? 'APP_NOINDEX=true: copia di prova, fuori dai motori di ricerca' : 'il sito si fa indicizzare',
    $inProva
        ? 'Giusto finché è una prova. Sul dominio vero va tolto, altrimenti Google non lo trova.'
        : 'Se questa è una copia di prova, metti APP_NOINDEX=true: indicizzata su un dominio non suo, '
          . 'il giorno della pubblicazione Google avrebbe due copie dello stesso sito.');

$aggiungi(date_default_timezone_get() !== '' ? 'ok' : 'attenzione', 'Ambiente',
    'fuso orario ' . date_default_timezone_get(),
    'Le date delle prenotazioni seguono questo fuso: per una struttura italiana va Europe/Rome.');

// ------------------------------------------------------------- scritture
foreach (['storage/logs', 'storage/mail'] as $cartella) {
    $percorso = $root . '/' . $cartella;
    if (!is_dir($percorso)) {
        $aggiungi('attenzione', 'Scritture', "{$cartella} non esiste",
            "Crea la cartella e rendila scrivibile (755, o 775 se il proprietario non è l'utente del web).");
        continue;
    }
    $aggiungi(is_writable($percorso) ? 'ok' : 'bloccante', 'Scritture',
        "{$cartella} " . (is_writable($percorso) ? 'scrivibile' : 'NON scrivibile'),
        'Senza permesso di scrittura i messaggi dei moduli si perdono in silenzio.');
}

// ------------------------------------------------ cartelle non pubbliche
$docRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: null;
if ($docRoot !== null) {
    $esposte = [];
    foreach (['src', 'views', 'content', 'config', 'storage', 'database', 'tools', 'docs'] as $d) {
        $vero = realpath($root . '/' . $d);
        if ($vero !== false && str_starts_with($vero, $docRoot)) {
            $esposte[] = $d;
        }
    }
    if ($esposte === []) {
        $aggiungi('ok', 'Esposizione', 'le cartelle dell’applicazione stanno fuori dalla cartella pubblica');
    } else {
        $protette = is_readable($root . '/.htaccess')
            && str_contains((string) file_get_contents($root . '/.htaccess'), 'RewriteRule ^(src|views');
        $aggiungi(
            $protette ? 'attenzione' : 'bloccante',
            'Esposizione',
            'dentro la cartella pubblica: ' . implode(', ', $esposte)
                . ($protette ? ' — bloccate da .htaccess' : ' — NON protette'),
            $protette
                ? 'Funziona, ma la strada migliore resta puntare il dominio su public/ dall’hPanel.'
                : 'Manca l’.htaccess nella radice del progetto: senza, src/ e .env sono leggibili dal web.'
        );
    }
}

if (is_readable($root . '/.env') && $docRoot !== null) {
    $envDentro = str_starts_with((string) realpath($root . '/.env'), $docRoot);
    $aggiungi($envDentro ? 'attenzione' : 'ok', 'Esposizione',
        '.env ' . ($envDentro ? 'è dentro la cartella pubblica' : 'sta fuori dalla cartella pubblica'),
        'Se è dentro, dipende solo dall’.htaccess. Meglio fuori: contiene le credenziali della posta e del database.');
}

// ---------------------------------------------------------- riscritture
if (function_exists('apache_get_modules')) {
    $rewrite = in_array('mod_rewrite', apache_get_modules(), true);
    $aggiungi($rewrite ? 'ok' : 'bloccante', 'Server', 'mod_rewrite ' . ($rewrite ? 'attivo' : 'NON attivo'),
        'Senza riscritture ogni indirizzo diverso da / restituisce 404.');
} else {
    $aggiungi('nota', 'Server', 'riscritture non verificabili da qui',
        'Controlla aprendo /it/camere: se dà 404, mod_rewrite non è attivo o .htaccess non viene letto.');
}

// ------------------------------------------------------------- database
$dsn = (string) $config['database']['dsn'];
if ($dsn === '') {
    $aggiungi('ok', 'Database', 'nessuno: il sito legge i contenuti dai file',
        'Va benissimo per cinque camere. Il database serve quando vuoi disponibilità e storico veri.');
} else {
    try {
        $pdo = new PDO($dsn, (string) $config['database']['user'], (string) $config['database']['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $tabelle = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $mancanti = array_diff(['rooms', 'room_translations', 'room_rates', 'bookings'], $tabelle);
        $aggiungi($mancanti === [] ? 'ok' : 'bloccante', 'Database',
            $mancanti === [] ? 'connesso, ' . count($tabelle) . ' tabelle'
                             : 'connesso, ma mancano: ' . implode(', ', $mancanti),
            'Importa database/schema.sql e poi database/seed.sql da phpMyAdmin.');
    } catch (Throwable $e) {
        $aggiungi('bloccante', 'Database', 'connessione fallita: ' . $e->getMessage(),
            'Controlla DB_DSN, DB_USER e DB_PASSWORD. Su Hostinger l’host è di solito localhost.');
    }
}

// ---------------------------------------------------------------- posta
$trasporto = (string) $config['mail']['transport'];
if ($trasporto === 'log') {
    $aggiungi('attenzione', 'Posta', 'MAIL_TRANSPORT=log: nessun messaggio viene spedito',
        'In produzione metti smtp. Con «log» le richieste di prenotazione restano in storage/mail e nessuno le legge.');
} elseif ($trasporto === 'smtp') {
    $smtp = $config['mail']['smtp'];
    $completo = $smtp['host'] !== '' && $smtp['user'] !== '' && $smtp['password'] !== '';
    $aggiungi($completo ? 'ok' : 'bloccante', 'Posta',
        $completo ? "SMTP su {$smtp['host']}:{$smtp['port']}" : 'SMTP incompleto',
        'Servono host, utente e password della casella del dominio. Su Hostinger: smtp.hostinger.com, porta 587.');
    if ($completo && !extension_loaded('openssl')) {
        $aggiungi('bloccante', 'Posta', 'openssl assente: STARTTLS non funziona',
            'Attiva l’estensione openssl da hPanel.');
    }
} else {
    $aggiungi('attenzione', 'Posta', 'MAIL_TRANSPORT=mail',
        'Funziona, ma i messaggi non autenticati finiscono spesso nella posta indesiderata. Meglio smtp.');
}

/**
 * Un indirizzo di prova è tale se sta su uno dei domini che la RFC 2606
 * riserva alla documentazione. Cercare la parola «example» dappertutto
 * segnalerebbe anche un dominio vero tipo «exampleinn.it», e un controllo
 * che grida al lupo su un dato buono smette di essere letto.
 */
$diProva = static function (string $indirizzo): bool {
    $dominio = strtolower(substr(strrchr($indirizzo, '@') ?: '', 1));
    if ($dominio === '') {
        return true;
    }
    foreach (['example.com', 'example.net', 'example.org', 'example.edu'] as $riservato) {
        if ($dominio === $riservato) {
            return true;
        }
    }
    foreach (['.example', '.test', '.invalid', '.localhost'] as $tld) {
        if (str_ends_with($dominio, $tld)) {
            return true;
        }
    }

    return false;
};

$a = (string) $config['mail']['to'];
$aggiungi(
    ($a !== '' && !$diProva($a)) ? 'ok' : 'bloccante',
    'Posta', 'le richieste arrivano a: ' . ($a !== '' ? $a : '(nessuno)'),
    'MAIL_TO_ADDRESS è ancora un indirizzo di prova: le richieste di prenotazione non arriverebbero a nessuno.'
);

// ----------------------------------------------------------- contenuti
$settings = require $root . '/content/settings.php';

// Il CIN sta a parte perché non è una cortesia verso l'ospite: è un obbligo
// di legge per chi affitta a chi viaggia, va esposto nel sito e negli
// annunci, e la sanzione parte da 800 euro. Senza, non si pubblica.
$obbligatori = [];
foreach (['cin' => 'Codice Identificativo Nazionale', 'vat' => 'partita IVA'] as $campo => $nome) {
    if (empty($settings['legal'][$campo])) {
        $obbligatori[] = $nome;
    }
}
// In prova, fuori dai motori di ricerca e prima di qualunque annuncio, il CIN
// mancante è da ricordare e non ancora da bloccare. Sul dominio vero sì.
$aggiungi($obbligatori === [] ? 'ok' : ($inProva ? 'attenzione' : 'bloccante'), 'Contenuti',
    $obbligatori === []
        ? 'gli identificativi di legge ci sono'
        : 'manca per legge: ' . implode(', ', $obbligatori),
    'Va esposto nel sito e in ogni annuncio. Il piè di pagina lo mostra come '
    . '«da confermare», che è onesto ma non basta a mettersi in regola.');

$vuoti = [];
foreach ([
    'contacts' => ['phone', 'email'],
    'stay'     => ['check_in_from', 'check_out_by'],
    'address'  => ['postal_code'],
] as $gruppo => $campi) {
    foreach ($campi as $campo) {
        if (empty($settings[$gruppo][$campo])) {
            $vuoti[] = "{$gruppo}.{$campo}";
        }
    }
}
$aggiungi($vuoti === [] ? 'ok' : 'attenzione', 'Contenuti',
    $vuoti === [] ? 'i dati pratici ci sono tutti' : 'ancora da confermare: ' . implode(', ', $vuoti),
    'Il sito li mostra come «da confermare»: è onesto, ma sono le prime cose '
    . 'che un ospite cerca, e chi non le trova scrive altrove.');

$camere = require $root . '/content/rooms.php';
$senzaFoto = array_values(array_filter($camere, static fn (array $c): bool => empty($c['photographed'])));
$aggiungi($senzaFoto === [] ? 'ok' : 'attenzione', 'Contenuti',
    $senzaFoto === []
        ? 'tutte le camere hanno una fotografia'
        : count($senzaFoto) . ' camere senza fotografia: ' . implode(', ', array_column($senzaFoto, 'ref')),
    'Restano con il segnaposto disegnato. Il sito lo dichiara, ma una camera senza fotografia si prenota meno.');

// ------------------------------------------------------------ materiali
$mancanti = [];
foreach (['assets/css/tokens.css', 'assets/css/bundle.css', 'assets/css/site.css', 'assets/css/fonts.css',
          'assets/js/site.js', 'assets/js/abilita-js.js', 'assets/fonts/prata-latin.woff2',
          'assets/fonts/figtree-latin.woff2', 'assets/fonts/allura-latin.woff2',
          'favicon.ico', '500.html', '.htaccess'] as $f) {
    if (!is_file($root . '/public/' . $f)) {
        $mancanti[] = $f;
    }
}
$aggiungi($mancanti === [] ? 'ok' : 'bloccante', 'Materiali',
    $mancanti === [] ? 'fogli, caratteri e file di servizio tutti al loro posto' : 'mancano: ' . implode(', ', $mancanti),
    'Ricarica via FTP la cartella public/ per intero, compresi i file che cominciano con un punto.');

// --------------------------------------------------------------- sessione
if (!$daBrowser || session_status() === PHP_SESSION_NONE) {
    $ok = @session_start();
    $aggiungi($ok ? 'ok' : 'bloccante', 'Sessione', $ok ? 'funziona' : 'non parte',
        'Senza sessione il gettone anti-CSRF non regge e i moduli rifiutano ogni invio.');
    if ($ok) { session_destroy(); }
}

// ------------------------------------------------------- questo file stesso
if ($daBrowser) {
    $aggiungi('attenzione', 'Pulizia', 'questo controllo è raggiungibile dal web',
        'Cancellalo da public_html appena hai finito: descrive la configurazione del sito.');
}

// ------------------------------------------------------------- resoconto
$colori = ['ok' => "\033[32m", 'attenzione' => "\033[33m", 'bloccante' => "\033[31m", 'nota' => "\033[36m"];
$segni  = ['ok' => 'ok        ', 'attenzione' => 'attenzione', 'bloccante' => 'BLOCCANTE ', 'nota' => 'nota      '];

$righe = [];
$righe[] = '';
$righe[] = 'ARCO DEL VENTO — controllo prima della pubblicazione';
$righe[] = str_repeat('=', 74);

$areaPrec = '';
foreach ($esiti as [$livello, $area, $esito, $rimedio]) {
    if ($area !== $areaPrec) {
        $righe[] = '';
        $righe[] = strtoupper($area);
        $areaPrec = $area;
    }
    $segno = $daBrowser ? $segni[$livello] : $colori[$livello] . $segni[$livello] . "\033[0m";
    $righe[] = sprintf('  %s  %s', $segno, $esito);
    if ($livello !== 'ok' && $rimedio !== '') {
        foreach (explode("\n", wordwrap($rimedio, 64)) as $r) {
            $righe[] = '                ' . $r;
        }
    }
}

$bloccanti = count(array_filter($esiti, static fn (array $e): bool => $e[0] === 'bloccante'));
$attenzioni = count(array_filter($esiti, static fn (array $e): bool => $e[0] === 'attenzione'));

$righe[] = '';
$righe[] = str_repeat('=', 74);
$righe[] = $bloccanti === 0
    ? ($inProva
        ? sprintf('LA PROVA PUÒ ANDARE ONLINE.  %d cose da guardare prima del dominio vero.', $attenzioni)
        : sprintf('SI PUÒ PUBBLICARE.  %d cose da guardare con calma.', $attenzioni))
    : sprintf('NON ANCORA.  %d problemi bloccanti, %d cose da guardare.', $bloccanti, $attenzioni);
$righe[] = '';

echo implode("\n", $righe);

exit($bloccanti === 0 ? 0 : 1);
