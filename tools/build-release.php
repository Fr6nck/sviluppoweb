<?php
/**
 * Arco del Vento — prepara il pacchetto da caricare sul server.
 *
 *     php tools/build-release.php
 *     php tools/build-release.php --tutto        (non scarta nessuna immagine)
 *     php tools/build-release.php --out=/tmp/x   (un'altra cartella)
 *
 * Il sito non si compila: non c'è un passo di build, e questo strumento non
 * ne è uno. Fa tre cose che a mano si sbagliano sempre:
 *
 *   1. COPIA SOLO QUELLO CHE SERVE. La cronologia git, gli originali delle
 *      fotografie, i registri, gli strumenti da tavolo e il .env non vanno
 *      sul server. Il .env in particolare NON si copia mai: quello locale ha
 *      i valori di sviluppo, e copiarlo è il modo più rapido di pubblicare un
 *      sito in APP_DEBUG=true con l'indirizzo di prova per le e-mail.
 *
 *   2. VERIFICA CHE IL SITO RISPONDA DAVVERO. Rende ogni pagina delle due
 *      lingue in un processo a parte e si ferma se una non risponde 200 o
 *      contiene un errore di PHP. Un pacchetto che si carica e dà schermo
 *      bianco è peggio di nessun pacchetto.
 *
 *   3. SCARTA LE IMMAGINI CHE NESSUNA PAGINA CHIEDE. I tagli non usati e i
 *      segnaposto delle camere che adesso hanno una fotografia. La lista la
 *      calcola dalle pagine rese al punto 2, non da una lista scritta a
 *      mano: se una pagina non risponde, non scarta niente e si ferma.
 *
 * Alla fine scrive l'archivio e il suo SHA-256, e stampa cosa caricare dove.
 * Non carica niente da sé: la pubblicazione resta una decisione di chi la fa.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/src/autoload.php';

use ArcoDelVento\I18n\Routes;
use ArcoDelVento\Support\Env;

Env::load($root . '/.env');

// ------------------------------------------------------------------ opzioni
$opzioni = getopt('', ['out::', 'tutto', 'senza-zip', 'prova::']) ?: [];
$uscita  = rtrim((string) ($opzioni['out'] ?? $root . '/dist'), '/');
$tutto   = array_key_exists('tutto', $opzioni);
$senzaZip = array_key_exists('senza-zip', $opzioni);
// --prova=https://blackout.in/assisiapartment: scrive anche il .env della copia
// di prova, fuori dai motori di ricerca e senza posta vera.
$urlProva = rtrim((string) ($opzioni['prova'] ?? ''), '/');
if ($urlProva !== '' && !preg_match('#^https?://[^/]+#', $urlProva)) {
    fwrite(STDERR, "--prova vuole un indirizzo intero, per esempio --prova=https://blackout.in/assisiapartment\n");
    exit(1);
}

$VERDE = "\033[32m"; $ROSSO = "\033[31m"; $GIALLO = "\033[33m"; $FINE = "\033[0m";
$passo = static function (string $testo) use ($VERDE, $FINE): void {
    echo $VERDE . '  ok  ' . $FINE . $testo . PHP_EOL;
};
$muori = static function (string $testo) use ($ROSSO, $FINE): never {
    echo $ROSSO . ' NO   ' . $FINE . $testo . PHP_EOL;
    exit(1);
};
$nota = static function (string $testo) use ($GIALLO, $FINE): void {
    echo $GIALLO . ' nota ' . $FINE . $testo . PHP_EOL;
};

echo PHP_EOL . "Arco del Vento — pacchetto per il server" . PHP_EOL;
echo str_repeat('=', 74) . PHP_EOL . PHP_EOL;

// ============================================================== 1. le pagine
echo "PAGINE" . PHP_EOL;

/** @return list<string> ogni indirizzo pubblico del sito, nelle lingue attive */
$indirizzi = (static function () use ($root): array {
    $config = require $root . '/config/config.php';
    $lingue = (array) $config['i18n']['available'];
    $camere = require $root . '/content/rooms.php';

    $out = [];
    foreach ($lingue as $lingua) {
        foreach (Routes::pages() as $pagina) {
            $out[] = Routes::url($pagina, $lingua);
        }
        foreach ($camere as $camera) {
            $slug = $camera['slug'][$lingua] ?? null;
            if (!is_string($slug) || $slug === '') {
                continue;
            }
            $out[] = Routes::url('room', $lingua, ['slug' => $slug]);
        }
    }
    // Il percorso di prenotazione mostra immagini che l'elenco non mostra.
    $out[] = '/it/prenota?passo=camere&arrivo=2026-12-10&partenza=2026-12-13&ospiti=2';
    $out[] = '/sitemap.xml';
    $out[] = '/robots.txt';

    $out = array_values(array_unique($out));

    /* Quante devono essere: una pagina per lingua, una camera per lingua, più
       il passo camere della prenotazione, la sitemap e robots.txt. Il conto sta
       qui perché una lista che si accorcia in silenzio — un nome di rotta
       cambiato, uno slug mancante — farebbe sembrare non usate le fotografie
       delle camere, e il pacchetto partirebbe senza. Meglio fermarsi. */
    $attesi = count($lingue) * (count(Routes::pages()) + count($camere)) + 3;
    if (count($out) !== $attesi) {
        fwrite(STDERR, sprintf(
            "Gli indirizzi generati sono %d e dovrebbero essere %d.\n"
            . "Controlla src/I18n/Routes.php e gli slug in content/rooms.php.\n",
            count($out),
            $attesi
        ));
        exit(1);
    }

    return $out;
})();

/**
 * Rende un indirizzo in un processo a parte.
 *
 * A parte e non qui dentro perché public/index.php include le funzioni di
 * aiuto con require: due pagine nello stesso processo e PHP si ferma su una
 * funzione già dichiarata. Un processo per pagina costa qualche decimo di
 * secondo e in cambio ogni pagina parte pulita, che è anche una prova più
 * onesta.
 *
 * @return array{stato:int, corpo:string}
 */
$rendi = static function (string $indirizzo, string $cartella = '') use ($root): array {
    $script = <<<'CODICE'
        <?php
        $root = $argv[1];
        $uri  = $argv[2];
        $cartella = $argv[3] ?? '';
        if ($cartella !== '') {
            // Il sito finto in sottocartella: APP_URL la contiene, e la
            // richiesta arriva con la cartella davanti, come da Apache.
            $_SERVER['APP_URL'] = 'https://esempio.test' . $cartella;
        }
        $parti = parse_url($uri);
        $_SERVER['REQUEST_URI']    = $cartella . $uri;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HOST']      = 'localhost';
        $_SERVER['SCRIPT_NAME']    = '/index.php';
        $_GET = [];
        if (isset($parti['query'])) { parse_str($parti['query'], $_GET); }
        $_POST = [];
        ob_start();
        require $root . '/public/index.php';
        $corpo = ob_get_clean();
        echo json_encode(['stato' => http_response_code(), 'corpo' => $corpo]);
        CODICE;

    $tmp = tempnam(sys_get_temp_dir(), 'adv') . '.php';
    file_put_contents($tmp, $script);
    $cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($tmp)
         . ' ' . escapeshellarg($root) . ' ' . escapeshellarg($indirizzo)
         . ($cartella !== '' ? ' ' . escapeshellarg($cartella) : '') . ' 2>&1';
    $grezzo = (string) shell_exec($cmd);
    @unlink($tmp);

    $dati = json_decode($grezzo, true);
    if (!is_array($dati)) {
        return ['stato' => 0, 'corpo' => $grezzo];
    }

    return ['stato' => (int) ($dati['stato'] ?: 200), 'corpo' => (string) $dati['corpo']];
};

$riferite = [];   // ogni /assets/... che una pagina chiede
$guasti   = [];

foreach ($indirizzi as $indirizzo) {
    $r = $rendi($indirizzo);
    $male = $r['stato'] !== 200
        || preg_match('/Fatal error|Uncaught|Warning:|Notice:|Vista mancante/', $r['corpo']) === 1;
    if ($male) {
        $guasti[] = sprintf('%s → %d %s', $indirizzo, $r['stato'], substr(trim($r['corpo']), 0, 160));
        continue;
    }
    preg_match_all('#/assets/[A-Za-z0-9._/-]+#', $r['corpo'], $m);
    foreach ($m[0] as $rif) {
        $riferite[strtok($rif, '?')] = true;
    }
}

if ($guasti !== []) {
    foreach ($guasti as $g) {
        echo '       ' . $g . PHP_EOL;
    }
    $muori(count($guasti) . ' pagine su ' . count($indirizzi) . ' non rispondono: non si pubblica.');
}
$passo(count($indirizzi) . ' indirizzi resi, tutti 200, nessun errore di PHP');

/* Seconda passata: lo stesso sito, come se stesse in una sottocartella — è
   così che gira in prova, su blackout.in/assisiapartment. Ogni link, ogni
   file statico, ogni indirizzo di un modulo deve portarsi dietro la cartella:
   uno solo che comincia con «/» nudo chiede la pagina alla radice di un altro
   sito. È l'errore più facile da reintrodurre scrivendo una vista, e con il
   server di sviluppo alla radice non si vede mai. */
$cartellaProva = '/prova-sottocartella';
$fuoriCartella = [];
foreach ($indirizzi as $indirizzo) {
    $r = $rendi($indirizzo, $cartellaProva);
    if ($r['stato'] !== 200 || preg_match('/Fatal error|Uncaught|Warning:|Notice:/', $r['corpo']) === 1) {
        $fuoriCartella[] = $indirizzo . ' → ' . $r['stato'];
        continue;
    }
    $percorsi = [];
    preg_match_all('#\s(?:href|src|action)="(/[^"]*)"#', $r['corpo'], $m);
    $percorsi = $m[1];
    preg_match_all('#\ssrcset="([^"]*)"#', $r['corpo'], $m);
    foreach ($m[1] as $insieme) {
        foreach (explode(',', $insieme) as $voce) {
            $percorsi[] = strtok(trim($voce), ' ');
        }
    }
    foreach ($percorsi as $percorso) {
        if (str_starts_with((string) $percorso, '/') && !str_starts_with((string) $percorso, $cartellaProva . '/')) {
            $fuoriCartella[] = $indirizzo . ' → ' . $percorso;
        }
    }
    // gli indirizzi assoluti: canonical, hreflang, sitemap, Open Graph
    preg_match_all('#https://esempio\.test(/[^"<\s]*)#', $r['corpo'], $m);
    foreach ($m[1] as $percorso) {
        if (!str_starts_with($percorso, $cartellaProva . '/')) {
            $fuoriCartella[] = $indirizzo . ' → https://esempio.test' . $percorso;
        }
    }
}
if ($fuoriCartella !== []) {
    foreach (array_slice(array_unique($fuoriCartella), 0, 12) as $f) {
        echo '       ' . $f . PHP_EOL;
    }
    $muori('in una sottocartella il sito chiederebbe file e pagine fuori dalla sua cartella.');
}
$passo('rese di nuovo come in una sottocartella: ogni link e ogni file resta nella cartella');

// I fogli di stile chiedono i caratteri con percorsi relativi: si risolvono
// rispetto al foglio, altrimenti i .woff2 sembrano non usati e restano fuori.
foreach (array_keys($riferite) as $rif) {
    if (!str_ends_with($rif, '.css')) {
        continue;
    }
    $file = $root . '/public' . $rif;
    if (!is_file($file)) {
        continue;
    }
    preg_match_all('#url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)#', (string) file_get_contents($file), $m);
    foreach ($m[1] as $dentro) {
        if (str_starts_with($dentro, 'data:')) {
            continue;
        }
        $assoluto = '/' . ltrim($dentro, '/');
        if (!str_starts_with($dentro, '/')) {
            $base = dirname($rif);
            $assoluto = $base . '/' . $dentro;
        }
        // schiaccia i ../
        $pezzi = [];
        foreach (explode('/', $assoluto) as $pezzo) {
            if ($pezzo === '..') { array_pop($pezzi); } elseif ($pezzo !== '.' && $pezzo !== '') { $pezzi[] = $pezzo; }
        }
        $riferite['/' . implode('/', $pezzi)] = true;
    }
}
$passo(count($riferite) . ' file statici chiesti dalle pagine e dai fogli di stile');
echo PHP_EOL;

// =========================================================== 2. cosa si copia
echo "COPIA" . PHP_EOL;

/** Cartelle e file che vanno sul server. */
/* README.md non sale: al server non serve, e se l'.htaccess della radice
   mancasse sarebbe leggibile da chiunque. index.php sì: risponde solo quando
   quell'.htaccess manca, e dice che cosa fare invece di un «403» muto. */
$daCopiare = ['public', 'src', 'views', 'content', 'config', 'database', '.htaccess', 'index.php'];

/** Le cartelle private, ognuna con il suo .htaccess che la chiude. */
$private = ['src', 'views', 'content', 'config', 'database', 'storage', 'tools', 'docs'];

/** Strumenti che servono SUL server: gli altri vogliono GD o Node e restano qui. */
$strumentiServer = ['preflight.php', 'export-seed.php'];

/** Quello che non va mai sul server, con il motivo. */
$esclusi = [
    '.git'                 => 'la cronologia del progetto: non serve al sito',
    '.env'                 => 'va CREATO sul server, non copiato: questo ha i valori di sviluppo',
    'docs/foto-originali'  => 'gli originali delle fotografie: servono a te, non al sito',
    'storage/logs'         => 'si riempie da sola',
    'storage/mail'         => 'si riempie da sola',
    'dist'                 => 'i pacchetti precedenti',
    'tools/router.php'     => 'serve al server di sviluppo di PHP, in produzione mai',
    'tools/serve.sh'       => 'idem',
    'tools/build-photos.php'      => 'strumento da tavolo: vuole GD',
    'tools/build-tokens.php'      => 'strumento da tavolo',
    'tools/build-placeholders.php' => 'strumento da tavolo',
    'tools/build-release.php'     => 'questo stesso strumento',
];

if (is_dir($uscita)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uscita, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($uscita);
}
if (!@mkdir($uscita, 0o755, true) && !is_dir($uscita)) {
    $muori('non riesco a creare ' . $uscita);
}

$cartella = $uscita . '/arcodelvento';
@mkdir($cartella, 0o755, true);

$copiati = 0; $byte = 0; $scartate = [];

/** Copia un file creando le cartelle che servono. */
$copia = static function (string $da, string $a) use (&$copiati, &$byte, $muori): void {
    @mkdir(dirname($a), 0o755, true);
    if (!@copy($da, $a)) {
        $muori('non riesco a copiare ' . $da);
    }
    $copiati++;
    $byte += (int) filesize($da);
};

foreach ($daCopiare as $voce) {
    $sorgente = $root . '/' . $voce;
    if (!file_exists($sorgente)) {
        $muori('manca ' . $voce . ': il progetto non è completo');
    }
    if (is_file($sorgente)) {
        $copia($sorgente, $cartella . '/' . $voce);
        continue;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sorgente, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
        if (!$f->isFile()) {
            continue;
        }
        $relativo = substr($f->getPathname(), strlen($root) + 1);

        // Un'immagine che nessuna pagina chiede non sale: pesa e, nel caso dei
        // segnaposto delle camere fotografate, racconta una cosa falsa.
        if (!$tutto && str_starts_with($relativo, 'public/assets/')) {
            $chiave = substr($relativo, strlen('public'));
            if (!isset($riferite[$chiave])) {
                $scartate[$chiave] = (int) $f->getSize();
                continue;
            }
        }
        $copia($f->getPathname(), $cartella . '/' . $relativo);
    }
}

foreach ($strumentiServer as $s) {
    $copia($root . '/tools/' . $s, $cartella . '/tools/' . $s);
}
// storage, tools e docs salgono solo in parte: il loro .htaccess va copiato
// a mano, le altre cartelle private lo portano con sé.
foreach (['storage', 'tools', 'docs'] as $c) {
    $copia($root . '/' . $c . '/.htaccess', $cartella . '/' . $c . '/.htaccess');
}
$copia($root . '/.env.example', $cartella . '/.env.example');
$copia($root . '/docs/DEPLOY-HOSTINGER.md', $cartella . '/docs/DEPLOY-HOSTINGER.md');

// Le due cartelle scrivibili devono esistere: il sito ci scrive.
foreach (['storage/logs', 'storage/mail'] as $c) {
    @mkdir($cartella . '/' . $c, 0o755, true);
    file_put_contents($cartella . '/' . $c . '/.gitkeep', '');
}

$passo($copiati . ' file copiati, ' . round($byte / 1048576, 1) . ' MB');

if ($scartate !== []) {
    $pesoScartato = array_sum($scartate);
    $passo(count($scartate) . ' file statici lasciati fuori perché nessuna pagina li chiede ('
        . round($pesoScartato / 1024) . ' KB)');
    $gruppi = [];
    foreach (array_keys($scartate) as $s) {
        $gruppi[dirname($s)] = ($gruppi[dirname($s)] ?? 0) + 1;
    }
    foreach ($gruppi as $dir => $n) {
        echo '       ' . $dir . ': ' . $n . PHP_EOL;
    }
}
echo PHP_EOL;

// ============================================================ 3. le verifiche
echo "VERIFICHE" . PHP_EOL;

// 3a. nessun file che non deve esserci
$vietati = [];
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($cartella, FilesystemIterator::SKIP_DOTS)
);
$phpDaControllare = [];
$totali = 0;
foreach ($it as $f) {
    if (!$f->isFile()) {
        continue;
    }
    $totali++;
    $rel = substr($f->getPathname(), strlen($cartella) + 1);
    if ($rel === '.env' || str_starts_with($rel, '.git')) {
        $vietati[] = $rel;
    }
    if (str_ends_with($rel, '.php')) {
        $phpDaControllare[] = $f->getPathname();
    }
}
$vietati === []
    ? $passo('nessun .env e nessun .git nel pacchetto')
    : $muori('nel pacchetto ci sono file che non devono uscire: ' . implode(', ', $vietati));

// 3b. nessun segreto nel contenuto dei file
$segreti = array_filter([
    'OWNER_TAX_CODE' => (string) Env::get('OWNER_TAX_CODE', ''),
    'DB_PASSWORD'    => (string) Env::get('DB_PASSWORD', ''),
    'MAIL_PASSWORD'  => (string) Env::get('MAIL_PASSWORD', ''),
    'BOOKING_API_KEY' => (string) Env::get('BOOKING_API_KEY', ''),
], static fn (string $v): bool => strlen($v) >= 6);

$trovati = [];
foreach ($phpDaControllare as $file) {
    $testo = (string) file_get_contents($file);
    foreach ($segreti as $nome => $valore) {
        if (str_contains($testo, $valore)) {
            $trovati[] = $nome . ' in ' . substr($file, strlen($cartella) + 1);
        }
    }
}
$trovati === []
    ? $passo($segreti === []
        ? 'nessun segreto da cercare in .env'
        : (count($segreti) === 1
            ? 'il valore riservato di .env non compare nei file copiati'
            : 'nessuno dei ' . count($segreti) . ' valori riservati di .env compare nei file copiati'))
    : $muori('valori di .env finiti nei file: ' . implode(', ', $trovati));

// 3c. la sintassi di tutto quello che sale
$rotti = [];
foreach ($phpDaControllare as $file) {
    exec(escapeshellcmd(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $o, $esito);
    if ($esito !== 0) {
        $rotti[] = substr($file, strlen($cartella) + 1);
    }
}
$rotti === []
    ? $passo(count($phpDaControllare) . ' file PHP, sintassi pulita')
    : $muori('sintassi rotta in: ' . implode(', ', $rotti));

// 3d. i file che senza l'FTP giusto non arrivano
$chiuse = array_filter($private, static fn (string $c): bool => is_file($cartella . '/' . $c . '/.htaccess')
    && str_contains((string) file_get_contents($cartella . '/' . $c . '/.htaccess'), 'Require all denied'));
count($chiuse) === count($private)
    ? $passo('ogni cartella privata ha il suo .htaccess che la chiude (' . count($private) . ')')
    : $muori('cartelle private senza protezione: ' . implode(', ', array_diff($private, $chiuse)));
is_file($cartella . '/index.php')
    ? $passo('index.php nella radice: spiega che cosa manca se l\'.htaccess non arriva')
    : $muori('manca index.php nella radice del pacchetto');
foreach (['.htaccess', 'public/.htaccess'] as $nascosto) {
    is_file($cartella . '/' . $nascosto)
        ? $passo($nascosto . ' presente (i client FTP lo nascondono: controlla che salga)')
        : $muori($nascosto . ' manca: senza, ogni pagina tranne la home dà 404');
}

// 3e. l'ultima immagine chiesta esiste davvero nel pacchetto
$mancanti = [];
foreach (array_keys($riferite) as $rif) {
    if (!is_file($cartella . '/public' . $rif)) {
        $mancanti[] = $rif;
    }
}
$mancanti === []
    ? $passo('tutti i file statici chiesti dalle pagine sono nel pacchetto')
    : $muori('il pacchetto non ha: ' . implode(', ', array_slice($mancanti, 0, 8)));

echo PHP_EOL;

// =============================================================== 4. archivio
echo "ARCHIVIO" . PHP_EOL;

$data    = date('Ymd-Hi');
$nomeZip = $uscita . '/arcodelvento-' . $data . '.zip';

if ($senzaZip) {
    $nota('archivio non richiesto: la cartella pronta è ' . $cartella);
    $nomeZip = null;
} elseif (!class_exists(ZipArchive::class)) {
    $nota("l'estensione zip non c'è: carica la cartella " . $cartella . ' così com\'è');
    $nomeZip = null;
} else {
    $zip = new ZipArchive();
    if ($zip->open($nomeZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        $muori('non riesco a creare ' . $nomeZip);
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($cartella, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
        $rel = substr($f->getPathname(), strlen($cartella) + 1);
        $f->isDir() ? $zip->addEmptyDir($rel) : $zip->addFile($f->getPathname(), $rel);
    }
    $zip->close();
    $passo(basename($nomeZip) . ' — ' . round((int) filesize($nomeZip) / 1048576, 1) . ' MB, ' . $totali . ' file');
    $passo('SHA-256 ' . hash_file('sha256', $nomeZip));
}

// ======================================================== 5. file da rinominare
/* I file che cominciano con un punto — .htaccess, .env — il Finder del Mac li
   nasconde, e anche dentro lo .zip sembrano non esserci. Ci sono, ma chi non
   li vede pensa che manchino, e senza .htaccess ogni pagina tranne la home dà
   404. Quindi accanto allo .zip, e FUORI dalla cartella da caricare, ne esce
   una copia con un nome normale, da rinominare sul server.

   Il .env di produzione invece non esiste da nessuna parte, perché deve
   contenere la password della posta: qui esce già compilato con tutto quello
   che si sa, e con la password da scrivere. */
echo PHP_EOL . "FILE DA RINOMINARE" . PHP_EOL;

// lo .zip deve contenere davvero i due .htaccess: lo si controlla sull'archivio
if ($nomeZip !== null && !$senzaZip) {
    $zip = new ZipArchive();
    $zip->open($nomeZip);
    $daTrovare = array_merge(['.htaccess', 'public/.htaccess', '.env.example', 'index.php'],
        array_map(static fn (string $c): string => $c . '/.htaccess', $private));
    foreach ($daTrovare as $nascosto) {
        $zip->locateName($nascosto) !== false
            ? $passo('nello .zip c\'è ' . $nascosto)
            : $muori('nello .zip manca ' . $nascosto);
    }
    $zip->close();
}

$rinominare = $uscita . '/file-da-rinominare';
@mkdir($rinominare, 0o755, true);
copy($root . '/.htaccess', $rinominare . '/htaccess-radice.txt');
copy($root . '/public/.htaccess', $rinominare . '/htaccess-public.txt');

$impostazioni = require $root . '/content/settings.php';
$posta   = (string) ($impostazioni['contacts']['email'] ?? '');
$dominio = str_contains($posta, '@') ? substr($posta, strpos($posta, '@') + 1) : '';

/** @var array<string,array{0:string,1:string}> chiave => [valore, nota sopra la riga] */
$produzione = [
    'APP_ENV'   => ['production', ''],
    'APP_DEBUG' => ['false', ''],
    'APP_URL'   => [
        $dominio !== '' ? 'https://' . $dominio : '',
        'CONTROLLA: il dominio esattamente come lo apri nel browser, con o senza www, senza barra finale.',
    ],
    'PREFLIGHT_TOKEN' => [
        bin2hex(random_bytes(12)),
        'Serve una volta sola, per tools/preflight.php dal browser. Dopo il controllo svuotalo.',
    ],
    'MAIL_TRANSPORT'       => ['smtp', ''],
    'MAIL_FROM_ADDRESS'    => [$posta, ''],
    'MAIL_TO_ADDRESS'      => [$posta, 'Dove arrivano le richieste di prenotazione e i messaggi dei moduli.'],
    'MAIL_SMTP_HOST'       => ['smtp.hostinger.com', 'Valido se la casella è su Hostinger. Se è altrove, servono i dati di quel provider.'],
    'MAIL_SMTP_PORT'       => ['587', ''],
    'MAIL_SMTP_USER'       => [$posta, ''],
    'MAIL_SMTP_PASSWORD'   => ['', 'DA SCRIVERE: la password della casella ' . $posta . '. Senza, il controllo finale si ferma.'],
    'MAIL_SMTP_ENCRYPTION' => ['tls', ''],
];

/**
 * Scrive un .env partendo da .env.example: le chiavi indicate prendono il
 * valore dato (con la loro nota sopra), tutto il resto resta com'è, commenti
 * compresi — così chi lo apre trova le stesse spiegazioni dell'esempio.
 *
 * @param array<string,array{0:string,1:string}> $valori
 * @param list<string> $intestazione
 */
$scriviEnv = static function (string $file, array $valori, array $intestazione) use ($root): void {
    $righe = array_merge($intestazione, ['']);
    foreach (file($root . '/.env.example', FILE_IGNORE_NEW_LINES) ?: [] as $riga) {
        if (preg_match('/^([A-Z_]+)=/', $riga, $m) && isset($valori[$m[1]])) {
            [$valore, $sopra] = $valori[$m[1]];
            if ($sopra !== '') {
                $righe[] = '# ' . $sopra;
            }
            $righe[] = $m[1] . '=' . (str_contains($valore, ' ') ? '"' . $valore . '"' : $valore);
            continue;
        }
        $righe[] = $riga;
    }
    file_put_contents($file, implode(PHP_EOL, $righe) . PHP_EOL);
};

$scriviEnv($rinominare . '/env-produzione.txt', $produzione, [
    '# Arco del Vento — .env di produzione, per il dominio vero',
    '#',
    '# 1. Scrivi la password della posta (MAIL_SMTP_PASSWORD) e controlla APP_URL.',
    '# 2. Caricalo nella cartella del sito, accanto a .htaccess.',
    '# 3. Sul server rinominalo in .env (con il punto davanti, senza .txt).',
    '#',
    '# Non mandarlo per e-mail e non metterlo su GitHub: contiene una password.',
]);

$fileEnv = ['env-produzione.txt'];

if ($urlProva !== '') {
    /* La copia di prova: stessi file, un altro .env. Fuori dai motori di
       ricerca, e senza posta vera — le richieste di prova non devono arrivare
       nella casella del cliente. Restano in storage/mail, leggibili via FTP. */
    $prova = [
        'APP_ENV'     => ['production', 'Come in produzione: gli errori non si mostrano a chi visita la prova.'],
        'APP_DEBUG'   => ['false', ''],
        'APP_URL'     => [$urlProva, 'La copia di prova. La cartella fa parte dell\'indirizzo: il sito si regola da solo.'],
        'APP_NOINDEX' => ['true', 'Copia di prova: nessuna pagina nei motori di ricerca. Sul dominio vero va tolto.'],
        'PREFLIGHT_TOKEN' => [
            bin2hex(random_bytes(12)),
            'Serve una volta sola, per tools/preflight.php dal browser. Dopo il controllo svuotalo.',
        ],
        'MAIL_TRANSPORT' => [
            'log',
            'In prova nessuna e-mail parte: i messaggi dei moduli restano in storage/mail, li leggi via FTP. '
            . 'Per provare la posta vera metti smtp e i dati di una tua casella, non quella del cliente.',
        ],
        'MAIL_FROM_ADDRESS' => [$posta, ''],
        'MAIL_TO_ADDRESS'   => [$posta, ''],
    ];
    $scriviEnv($rinominare . '/env-prova.txt', $prova, [
        '# Arco del Vento — .env della copia di prova: ' . $urlProva,
        '#',
        '# Pronto così: non serve nessuna password.',
        '# 1. Caricalo nella cartella del sito, accanto a .htaccess.',
        '# 2. Sul server rinominalo in .env (con il punto davanti, senza .txt).',
        '#',
        '# Quando il sito passa al dominio vero, questo file si sostituisce con',
        '# env-produzione.txt: i file del sito restano gli stessi.',
    ]);
    $fileEnv[] = 'env-prova.txt';
}

file_put_contents($uscita . '/LEGGIMI-PRIMA.txt', implode(PHP_EOL, [
    'ARCO DEL VENTO — COME SI CARICA',
    str_repeat('=', 64),
    '',
    'Nello .zip ci sono anche due file che il tuo computer probabilmente',
    'nasconde, perché il nome comincia con un punto:',
    '',
    '    .htaccess           nella radice',
    '    public/.htaccess    dentro public',
    '',
    'Ci sono. Sul Mac, nel Finder, Cmd + Maiusc + . (punto) li fa vedere.',
    'FileZilla, nel pannello di sinistra, li mostra comunque.',
    '',
    'Se non ti fidi, o se dopo il caricamento sul server non ci sono, usa',
    'le copie nella cartella file-da-rinominare:',
    '',
    '    htaccess-radice.txt  → nella cartella del sito, rinominalo .htaccess',
    '    htaccess-public.txt  → nella sua cartella public, rinominalo .htaccess',
    '    env-produzione.txt   → nella cartella del sito, rinominalo .env',
    ...($urlProva !== '' ? [
        '    env-prova.txt        → per la copia di prova (' . $urlProva . '),',
        '                           al posto di env-produzione.txt, rinominalo .env',
    ] : []),
    '',
    'Il file .env NON è nello .zip, di proposito: contiene la password',
    'della posta. env-produzione.txt è già compilato con tutto il resto:',
    'scrivi la password, controlla il dominio, caricalo e rinominalo.',
    '',
    'La cartella del sito è public_html sul dominio vero, oppure la',
    'sottocartella della prova (per esempio public_html/assisiapartment).',
    '',
    'NON caricare la cartella file-da-rinominare così com\'è: solo i file',
    'che ti servono, ognuno al suo posto, e rinominati.',
    '',
    'La procedura completa è in docs/DEPLOY-HOSTINGER.md, dentro lo .zip.',
    '',
]));

$passo('file-da-rinominare/ — htaccess-radice.txt, htaccess-public.txt, ' . implode(', ', $fileEnv));
$passo('LEGGIMI-PRIMA.txt scritto');

// il manifesto, per sapere dopo cosa era dentro
$manifesto = $uscita . '/MANIFESTO.txt';
$righe = [
    'Arco del Vento — pacchetto del ' . date('d/m/Y H:i'),
    str_repeat('-', 64),
    'file: ' . $totali,
    'peso: ' . round($byte / 1048576, 2) . ' MB',
    'PHP di sviluppo: ' . PHP_VERSION,
    '',
    'DENTRO: ' . implode(' ', $daCopiare) . ' .env.example, un .htaccess di chiusura in ' . implode(' ', $private)
        . ' tools/{' . implode(',', $strumentiServer) . '} docs/DEPLOY-HOSTINGER.md'
        . ' storage/{logs,mail} (vuote)',
    '',
    'FUORI:',
];
foreach ($esclusi as $cosa => $perche) {
    $righe[] = sprintf('  %-32s %s', $cosa, $perche);
}
if ($scartate !== []) {
    $righe[] = '';
    $righe[] = 'FILE STATICI SCARTATI (nessuna pagina li chiede):';
    foreach (array_keys($scartate) as $s) {
        $righe[] = '  ' . $s;
    }
}
if ($nomeZip !== null) {
    $righe[] = '';
    $righe[] = 'archivio: ' . basename($nomeZip);
    $righe[] = 'sha256:   ' . hash_file('sha256', $nomeZip);
}
file_put_contents($manifesto, implode(PHP_EOL, $righe) . PHP_EOL);
$passo('MANIFESTO.txt scritto');

echo PHP_EOL . str_repeat('=', 74) . PHP_EOL;
echo 'PRONTO DA CARICARE. Il pacchetto non è pubblicato: caricarlo è una tua mossa.' . PHP_EOL . PHP_EOL;
echo "Poi, sul server, nell'ordine:" . PHP_EOL;
echo '  1. crea .env copiando .env.example — APP_ENV=production, APP_DEBUG=false,' . PHP_EOL;
echo '     APP_URL con il dominio vero in https, MAIL_TO_ADDRESS con un indirizzo che leggi' . PHP_EOL;
echo '  2. rendi scrivibili storage/logs e storage/mail (755, se non basta 775)' . PHP_EOL;
echo '  3. punta il dominio su public/ (hPanel → Cambia cartella radice del sito)' . PHP_EOL;
echo '  4. php tools/preflight.php — deve finire senza bloccanti' . PHP_EOL;
echo PHP_EOL . 'La procedura completa, passo per passo: docs/DEPLOY-HOSTINGER.md' . PHP_EOL . PHP_EOL;
