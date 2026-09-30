<?php
/**
 * Giro completo sull'applicazione vera, via HTTP, con Stripe e S3 finti.
 *
 * Non si esegue da solo: lo lancia esegui.sh, che schiera una copia
 * dell'applicazione come sull'hosting, le mette davanti un server e avvia
 * i due servizi finti. Si prova quello che si carica via FTP.
 *
 * Uso:  php giro-completo.php <base> <cartella-schierata> <cartella-stripe-finto> <cartella-s3-finto>
 *
 * Variabili d'ambiente attese (le stesse passate al server):
 *   STRIPE_WEBHOOK_SECRET   per firmare i webhook come fa Stripe
 */
$BASE = rtrim($argv[1] ?? '', '/');
$DOVE = rtrim($argv[2] ?? '', '/');
$STRIPE_DIR = rtrim($argv[3] ?? '', '/');
$S3_DIR = rtrim($argv[4] ?? '', '/');
$WHSEC = (string) getenv('STRIPE_WEBHOOK_SECRET');
$TMP = sys_get_temp_dir() . '/mhw-giro-' . getmypid();
@mkdir($TMP, 0777, true);

$esiti = []; $falliti = 0; $capitolo = '';

// ------------------------------------------------------------------ strumenti
function capitolo(string $t): void { global $esiti, $capitolo; $capitolo = $t; $esiti[] = "\n== $t"; }
function prova(string $nome, bool $ok, string $nota = ''): void {
    global $esiti, $falliti;
    if (!$ok) $falliti++;
    $esiti[] = ($ok ? '  ok  ' : ' NO   ') . $nome . ($nota !== '' ? '   — ' . $nota : '');
}
function pulita(array $r): bool {
    foreach (['Fatal error', 'Warning:', 'Notice:', 'Deprecated:', 'Uncaught', 'Vista mancante', 'Stack trace', 'PDOException', 'SQLSTATE'] as $x)
        if (str_contains($r['body'], $x)) return false;
    return true;
}
function csrf(string $html): string { return preg_match('/name="_csrf" value="([a-f0-9]+)"/', $html, $m) ? $m[1] : ''; }
function intestazione(array $r, string $nome): string {
    return preg_match('/^' . preg_quote($nome, '/') . ':\s*(.*)$/mi', $r['head'], $m) ? trim($m[1]) : '';
}

final class Browser
{
    public string $jar;
    public string $csrf = '';
    public function __construct(public string $nome) { global $TMP; $this->jar = "$TMP/$nome.cookies"; @unlink($this->jar); }

    public function get(string $p, array $h = []): array { return $this->req('GET', $p, null, $h); }

    /** POST con il token del modulo appena visto, se non ne viene dato uno. */
    public function post(string $p, array $data = [], array $h = [], bool $conToken = true): array
    {
        if ($conToken && !isset($data['_csrf'])) $data['_csrf'] = $this->csrf;
        return $this->req('POST', $p, $data, $h);
    }

    /** Apre la pagina, prende il token, invia. */
    public function modulo(string $pagina, string $azione, array $data, array $h = []): array
    {
        $this->get($pagina);
        return $this->post($azione, $data, $h);
    }

    public function req(string $metodo, string $p, ?array $data, array $h = []): array
    {
        global $BASE;
        $url = str_starts_with($p, 'http') ? $p : $BASE . $p;
        $ch = curl_init($url);
        $multipart = $data !== null && (bool) array_filter($data, fn($v) => $v instanceof CURLFile);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30, CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_HTTPHEADER => $h,
        ]);
        if ($data !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart ? $data : http_build_query($data));
        $raw = (string) curl_exec($ch);
        $r = ['code' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE), 'loc' => (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL)];
        $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $r['head'] = substr($raw, 0, $hs); $r['body'] = substr($raw, $hs);
        if ($t = csrf($r['body'])) $this->csrf = $t;
        return $r;
    }

    /** Segue i redirect fino alla pagina finale. */
    public function segui(array $r, int $max = 5): array
    {
        while ($max-- > 0 && in_array($r['code'], [301, 302, 303], true) && $r['loc'] !== '') $r = $this->get($r['loc']);
        return $r;
    }
}

function db(): PDO
{
    global $DOVE;
    static $pdo = null;
    if ($pdo) return $pdo;
    $f = glob("$DOVE/app/storage/*.sqlite")[0] ?? '';
    $pdo = new PDO('sqlite:' . $f, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    return $pdo;
}
function val(string $sql, array $a = []): mixed { $s = db()->prepare($sql); $s->execute($a); $v = $s->fetchColumn(); return $v === false ? null : $v; }
function riga(string $sql, array $a = []): ?array { $s = db()->prepare($sql); $s->execute($a); return $s->fetch() ?: null; }
function righe(string $sql, array $a = []): array { $s = db()->prepare($sql); $s->execute($a); return $s->fetchAll(); }

/** L'ultimo link di un certo tipo spedito a un indirizzo (la posta finisce in un file). */
function linkPosta(string $a, string $tipo): string
{
    global $DOVE;
    $trovato = '';
    foreach (file("$DOVE/app/storage/logs/mail.log") ?: [] as $l) {
        $m = json_decode($l, true);
        if (($m['to'] ?? '') === $a && preg_match('#https?://\S+/' . $tipo . '/[A-Za-z0-9_-]+#', (string) $m['text'], $x)) $trovato = $x[0];
    }
    return $trovato;
}

function richiesteStripe(): array
{
    global $STRIPE_DIR;
    return array_map(fn($l) => json_decode($l, true), file("$STRIPE_DIR/richieste.jsonl", FILE_IGNORE_NEW_LINES) ?: []);
}

/** Un webhook firmato come lo firma Stripe. */
function inviaWebhook(array $evento, ?string $segreto = null, ?int $quando = null): array
{
    global $BASE, $WHSEC;
    $corpo = json_encode($evento);
    $t = $quando ?? time();
    $firma = hash_hmac('sha256', $t . '.' . $corpo, $segreto ?? $WHSEC);
    $ch = curl_init("$BASE/webhook/stripe");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $corpo,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', "Stripe-Signature: t=$t,v1=$firma"]]);
    $body = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $body];
}

function png(int $w = 640, int $h = 420): string
{
    global $TMP;
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, 180, 69, 31));
    imagefilledrectangle($im, 40, 40, $w - 40, $h - 40, imagecolorallocate($im, 250, 245, 236));
    $f = "$TMP/prova-" . mt_rand() . '.png';
    imagepng($im, $f);
    return $f;
}
function pdfVero(): string
{
    global $TMP;
    $f = "$TMP/prova.pdf";
    $obj = ["<< /Type /Catalog /Pages 2 0 R >>", "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] >>"];
    $out = "%PDF-1.4\n"; $off = [];
    foreach ($obj as $i => $o) { $off[] = strlen($out); $out .= ($i + 1) . " 0 obj\n$o\nendobj\n"; }
    $x = strlen($out);
    $out .= "xref\n0 " . (count($obj) + 1) . "\n0000000000 65535 f \n";
    foreach ($off as $o) $out .= sprintf("%010d 00000 n \n", $o);
    $out .= "trailer\n<< /Size " . (count($obj) + 1) . " /Root 1 0 R >>\nstartxref\n$x\n%%EOF\n";
    file_put_contents($f, $out);
    return $f;
}
function file_(string $percorso, string $mime, ?string $nome = null): CURLFile { return new CURLFile($percorso, $mime, $nome ?? basename($percorso)); }
function pv(string $codice): int
{
    return (int) val('SELECT pv.id FROM package_versions pv JOIN packages p ON p.id = pv.package_id WHERE p.code = ? AND pv.is_current = 1', [$codice]);
}

// ================================================================= INSTALLAZIONE
capitolo('Installazione');
$admin = new Browser('admin');
$r = $admin->get('/');
prova('Senza installazione si va a /installa', $r['code'] === 302 && str_contains($r['loc'], '/installa'));
$r = $admin->get('/installa');
prova('La pagina di installazione si apre', $r['code'] === 200 && pulita($r));
$r = $admin->post('/installa', ['email' => 'admin@prova.test', 'password' => 'AdminProva123', 'esempi' => '1']);
prova('Installazione con i clienti di esempio', $r['code'] === 302 && str_contains($r['loc'], '/admin'));
prova('Migrazioni applicate tutte', (int) val("SELECT COUNT(*) FROM schema_migrations") >= 3);
prova('Il catalogo delle funzioni c\'è', (int) val('SELECT COUNT(*) FROM features') >= 20);
prova('Nessun codice porta nel database', (int) val("SELECT COUNT(*) FROM sections WHERE door_code <> ''") === 0);
prova('Nessun codice porta nelle guide pubblicate', (int) val("SELECT COUNT(*) FROM guide_versions WHERE snapshot LIKE '%door_code%'") === 0);

// ======================================================================= LANDING
capitolo('Landing e listino');
$ospite = new Browser('visitatore');
$r = $ospite->get('/');
prova('La landing si apre', $r['code'] === 200 && pulita($r));
prova('Titolo richiesto', str_contains($r['body'], 'La casa risponde') && str_contains($r['body'], 'prima che chiedano.'));
prova('CTA principale', str_contains($r['body'], 'Crea gratis la tua guida'));
prova('Demo dichiarata come demo', str_contains($r['body'], 'Guarda la demo') && str_contains($r['body'], 'demo-tag'));
prova('Microcopy', str_contains($r['body'], 'Paghi solo quando pubblichi'));
foreach (['87', '117', '177'] as $p) prova("Prezzo $p € dal listino", str_contains($r['body'], $p . "\u{00A0}€"));
$portfolio = preg_match('#<form class="plan__scelta".*?</form>#s', $r['body'], $m) ? $m[0] : '';
prova('Portfolio: campo numerico (min 2, solo interi) al posto del menu', str_contains($portfolio, 'Quante strutture vuoi gestire?') && preg_match('#<input[^>]*name="strutture"[^>]*type="number"[^>]*step="1"[^>]*min="2"[^>]*value="2"#', $portfolio) === 1
      && !str_contains($portfolio, '<select'));
prova('…regola del prezzo dal listino', str_contains($portfolio, "Prima struttura 117\u{00A0}€/anno.") && str_contains($portfolio, "Ogni struttura aggiuntiva +60\u{00A0}€/anno."));
prova('…base e costo aggiuntivo dal listino, totale iniziale 177 €', str_contains($portfolio, 'data-base="11700"') && str_contains($portfolio, 'data-extra="6000"')
      && preg_match("#<span data-totale>177\u{00A0}€</span><small> \+ IVA / anno</small>#u", $portfolio) === 1 && substr_count($portfolio, '+ IVA / anno') === 1);
prova('…la quantità va alla registrazione col piano', str_contains($portfolio, '/registrati"') && str_contains($portfolio, 'name="piano" value="' . pv('portfolio') . '"') && str_contains($portfolio, 'Crea gratis con Portfolio'));
prova('…label esplicita del campo', preg_match('#<label for="([a-z0-9-]+)"[^>]*>Quante strutture#', $portfolio, $mm) === 1 && str_contains($portfolio, 'id="' . $mm[1] . '"'));
prova('…il totale lo calcola uno script senza prezzi scritti dentro', str_contains($r['body'], '/assets/prezzi.js') && is_file("$DOVE/assets/prezzi.js") && !preg_match('/11700|6000|117|177/', (string) @file_get_contents("$DOVE/assets/prezzi.js")));
prova('Pricing: "Pannello di controllo" al posto di "CMS", niente frasi finali', !preg_match('/>\s*CMS\s*</', $r['body']) && str_contains($r['body'], 'Pannello di controllo')
      && !str_contains($r['body'], 'Meno domande ripetitive, più tempo per accogliere') && !str_contains($r['body'], 'concierge digitale'));
prova('Piani: headline e descrizioni nuove', str_contains($r['body'], 'Le informazioni importanti, sempre a disposizione.') && str_contains($r['body'], 'Una guida completa, senza limiti di sezioni.')
      && str_contains($r['body'], 'Il Plus per più strutture.') && str_contains($r['body'], 'statistiche distinti per ogni struttura'));
prova('Plus: foto e PDF spiegati, niente "immagine profilo", badge sobrio', str_contains($r['body'], 'Foto esplicative nelle sezioni') && str_contains($r['body'], 'Possibilità di allegare documenti PDF')
      && !str_contains($r['body'], 'Immagine profilo') && str_contains($r['body'], 'Più completo') && !str_contains($r['body'], 'Più scelto'));
prova('Nota sul rinnovo automatico sotto i piani', str_contains($r['body'], 'Puoi disattivare il rinnovo dal tuo account'));
prova('Il prodotto si vede subito: telefono nella hero, prima della foto', strpos($r['body'], 'class="device"') !== false && strpos($r['body'], 'class="device"') < strpos($r['body'], 'class="stage"'));
prova('Il telefono è un link alla demo, con invito', preg_match('#<a class="device-link" href="([^"]+)"#', $r['body'], $mm) === 1 && str_ends_with($mm[1], '/benvenuto') && str_contains($r['body'], 'Scopri come la vedranno i tuoi ospiti'));
prova('Demo, telefono e QR portano alla stessa demo', isset($mm[1]) && substr_count($r['body'], 'href="' . $mm[1] . '"') >= 2 && str_contains($r['body'], 'Inquadra e prova la demo.'));
prova('Nuova foto del borgo, anche in WebP', str_contains($r['body'], '/assets/foto/borgo.jpg') && str_contains($r['body'], 'borgo-1200.webp') && str_contains($r['body'], 'borgo-telefono-600.webp'));
foreach (['borgo.jpg', 'borgo-1200.webp', 'borgo-2000.webp', 'borgo-telefono.jpg', 'borgo-telefono-600.webp'] as $f) {
    $x = $ospite->get(preg_replace('#/index\.php$#', '', $BASE) . '/assets/foto/' . $f);
    prova("Immagine $f raggiungibile", $x['code'] === 200 && strlen($x['body']) > 10000);
}
prova('Cosa trova l\'ospite: c\'è la raccolta differenziata', str_contains($r['body'], 'Rifiuti e raccolta differenziata') && !str_contains($r['body'], 'Cosa fare e vedere'));
prova('Autorevolezza: Blackout Agency, con link', str_contains($r['body'], 'Pensata per chi ospita.') && str_contains($r['body'], 'href="https://blackout.in"'));
prova('Come funziona: "Inizia in pochi minuti"', str_contains($r['body'], 'Inizia in pochi minuti.') && !str_contains($r['body'], 'Pronta in pochi minuti'));
prova('Blocco QR fedele al prodotto (le modifiche si pubblicano)', str_contains($r['body'], 'pubblichi la nuova versione senza cambiare il QR') && !str_contains($r['body'], 'class="lined"'));
prova('Chiusura nuova', str_contains($r['body'], 'La tua struttura ha tanto da raccontare.') && !str_contains($r['body'], 'stagione tranquilla') && !str_contains($r['body'], 'stagione più tranquilla'));
prova('Prezzi + IVA', str_contains($r['body'], '+ IVA / anno'));
prova('Niente testi vecchi', !str_contains($r['body'], 'Guardane una vera') && !str_contains($r['body'], 'sei domande') && !preg_match('/\bavete\b|\bvostr[aoie]\b/i', $r['body']));
prova('Niente piano Pro in vendita', !preg_match('/>\s*Pro\s*</', $r['body']));
prova('Sezione QR', str_contains($r['body'], 'Un QR. Tutta la struttura.'));
prova('I link dei piani portano alla registrazione col piano', str_contains($r['body'], '/registrati?piano=' . pv('essential')));
$tempo = preg_match('#<section id="il-tempo".*?</section>#s', $r['body'], $m) ? $m[0] : '';
prova('Sezione "Il tempo che non vedi" presente', $tempo !== '' && str_contains($tempo, 'Ogni ospite è nuovo.'));
prova('…dopo il prodotto e prima di "Come funziona"', strpos($r['body'], 'class="prodotto"') < strpos($r['body'], 'id="il-tempo"') && strpos($r['body'], 'id="il-tempo"') < strpos($r['body'], 'id="come-funziona"'));
prova('…con le tre domande, la soluzione, due vantaggi e il valore annuale', substr_count($tempo, 'class="msg"') === 3 && str_contains($tempo, 'Le risposte sono già nella tua guida.')
      && substr_count($tempo, 'class="vantaggio"') === 2 && str_contains($tempo, 'Meno dubbi all') && str_contains($tempo, 'Un piccolo investimento annuale'));
prova('…il prezzo di partenza viene dal listino', str_contains($tempo, "Da 87\u{00A0}€ + IVA all'anno."));
prova('…niente tono difensivo', !str_contains($r['body'], 'Non paghi una pagina con un QR'));
prova('…senza numeri di risparmio inventati', !preg_match('/\d+\s*(%|ore|messaggi in meno)|mai più|elimin/i', strip_tags($tempo)));
prova('CTA della hero verso la registrazione e verso la demo', preg_match('#<section class="hero2">.*?</section>#s', $r['body'], $mh) && str_contains($mh[0], '/registrati"') && str_contains($mh[0], 'Guarda la demo'));
prova('Fase 1 · piè di pagina con P.IVA, telefono e WhatsApp', str_contains($r['body'], 'P.IVA 02945910541') && str_contains($r['body'], 'href="tel:+393920061600"')
      && str_contains($r['body'], 'href="https://wa.me/393920061600"') && str_contains($r['body'], 'un progetto Blackout Agency')
      && str_contains($r['body'], 'Via Ariodante Fabretti 17, Perugia') && str_contains($r['body'], 'href="mailto:info@myhousewelcome.it"'));
prova('Fase 1 · favicon, icona Home e anteprima di condivisione', str_contains($r['body'], '/assets/favicon.svg') && str_contains($r['body'], '/assets/apple-touch-icon.png')
      && preg_match('#<meta property="og:image" content="https?://[^"]+/assets/og\.jpg">#', $r['body']) === 1);
foreach (['favicon.svg', 'apple-touch-icon.png', 'og.jpg'] as $f) prova("Fase 1 · $f presente", is_file("$DOVE/assets/$f") && filesize("$DOVE/assets/$f") > 300);
prova('Fase 1 · header del telefono: CTA e menu con tutte le voci', preg_match('#<div class="topbar__telefono">.*?</div>\s*</div></header>#s', $r['body'], $mt) === 1
      && str_contains($mt[0], 'Crea gratis') && str_contains($mt[0], 'Come funziona') && str_contains($mt[0], 'Piani') && str_contains($mt[0], 'Il QR') && str_contains($mt[0], 'Accedi'));
prova('Fase 1 · simbolo del marchio nell\'header', str_contains($r['body'], 'class="brand"') && str_contains($r['body'], 'class="simbolo"'));
$icone = [];
foreach (['arrival', 'transport', 'parking', 'waste', 'visit', 'todo', 'rules', 'services'] as $k) {
    $icone[$k] = (string) shell_exec('php -r ' . escapeshellarg('require "' . $DOVE . '/app/src/SectionCatalog.php"; echo MHW\SectionCatalog::icon("' . $k . '");'));
}
prova('Fase 1 · un\'icona diversa per ogni sezione', $icone === ['arrival' => 'pin', 'transport' => 'bus', 'parking' => 'car', 'waste' => 'bin',
      'visit' => 'monument', 'todo' => 'compass', 'rules' => 'doc', 'services' => 'washer'], json_encode($icone));
$senzaTipo = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$DOVE/app/views")) as $f) {
    if ($f->isFile()) foreach (preg_split('/<input\b/', (string) file_get_contents($f)) as $i => $pezzo) if ($i && !preg_match('/^(?:\?>|[^>])*?\btype=/s', $pezzo)) $senzaTipo++;
}
prova('Fase 1 · ogni <input> dei modelli ha un type esplicito', $senzaTipo === 0, (string) $senzaTipo);
foreach (['/termini', '/privacy'] as $p) { $r = $ospite->get($p); prova("$p si apre", $r['code'] === 200 && pulita($r)); }

// ================================================================= REGISTRAZIONE
capitolo('Registrazione, consensi, verifica');
$anna = new Browser('anna');
$r = $anna->get('/registrati?piano=' . pv('essential'));
prova('Registrazione si apre', $r['code'] === 200 && str_contains($r['body'], 'Cominciamo.'));
$r = $anna->post('/registrati', ['piano' => pv('essential'), 'name' => 'Anna Prova', 'email' => 'anna@prova.test', 'password' => 'AnnaProva123']);
prova('Senza Termini non si entra', $r['code'] === 200 && str_contains($r['body'], 'accettare i Termini'));
prova('Senza Termini nessun utente creato', !val("SELECT id FROM users WHERE email = 'anna@prova.test'"));
$r = $anna->post('/registrati', ['piano' => pv('essential'), 'name' => 'Anna Prova', 'email' => 'anna@prova.test',
                                  'password' => 'AnnaProva123', 'termini' => '1', 'privacy' => '1']);
prova('Registrazione → scelta del piano', $r['code'] === 302 && str_contains($r['loc'], '/piano?piano=' . pv('essential')));
$u = riga("SELECT * FROM users WHERE email = 'anna@prova.test'");
prova('Consenso ai Termini registrato con versione e data', $u && $u['terms_version'] !== '' && $u['terms_accepted_at'] !== null);
prova('Presa visione privacy registrata', $u && $u['privacy_version'] !== '' && $u['privacy_accepted_at'] !== null);
prova('Email non ancora verificata', $u && $u['email_verified_at'] === null);
prova('Password salvata con hash', $u && str_starts_with($u['password_hash'], '$2y$') || str_starts_with((string) $u['password_hash'], '$argon'));
prova('Email di verifica spedita', linkPosta('anna@prova.test', 'verifica') !== '');
$r = $anna->get('/');
prova('Utente dentro: le CTA portano al pannello e alla scelta del piano, non alla registrazione', preg_match('#<section class="hero2">.*?</section>#s', $r['body'], $m) && str_contains($m[0], '/pannello"')
      && !str_contains($r['body'], '/registrati') && str_contains($r['body'], 'action="' . preg_replace('#^https?://[^/]+#', '', $BASE) . '/piano"'));
$r = $anna->get('/registrati?piano=' . pv('plus'));
prova('Chi è già dentro e clicca un piano non si registra di nuovo', $r['code'] === 302 && str_contains($r['loc'], '/piano?piano=' . pv('plus')));
$r = $anna->get('/piano?piano=' . pv('essential'));
prova('La scelta del piano si apre', $r['code'] === 200 && pulita($r));
prova('Dopo il clic, Portfolio chiede il numero di strutture', preg_match('#name="strutture" type="number"[^>]*min="2"[^>]*value="2"#', $r['body']) === 1 && str_contains($r['body'], "177\u{00A0}€")
      && !str_contains($r['body'], '3 strutture'));
$r = $anna->post('/piano', ['pv' => pv('essential')]);
prova('Scelta del piano senza pagare', $r['code'] === 302 && str_contains($r['loc'], '/pannello/nuova'));
$acc = riga("SELECT a.* FROM accounts a JOIN users u ON u.id = a.user_id WHERE u.email = 'anna@prova.test'");
prova('Piano scelto salvato', (int) $acc['intended_package_version_id'] === pv('essential'));
prova('Nessun ordine creato scegliendo il piano', (int) val('SELECT COUNT(*) FROM orders WHERE account_id = ?', [$acc['id']]) === 0);

// ====================================================== PROCEDURA GUIDATA
capitolo('Procedura guidata (Essential)');
$r = $anna->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Casa Prova', 'city' => 'Lecce']);
prova('Struttura creata → procedura', $r['code'] === 302 && str_contains($r['loc'], '/procedura/struttura'));
$pid = (int) val('SELECT id FROM properties WHERE account_id = ?', [$acc['id']]);
$prop = riga('SELECT * FROM properties WHERE id = ?', [$pid]);
prova('Check-in & Check-out creato come nucleo', (int) val('SELECT COUNT(*) FROM sections WHERE property_id = ? AND is_core = 1', [$pid]) === 1);
prova('QR creato subito', (bool) val('SELECT token FROM qr_tokens WHERE property_id = ?', [$pid]));
foreach (array_keys(['struttura' => 1, 'checkin' => 1, 'sezioni' => 1, 'contenuti' => 1, 'lingue' => 1, 'aspetto' => 1, 'anteprima' => 1]) as $passo) {
    $r = $anna->get("/pannello/$pid/procedura/$passo");
    prova("Passo \"$passo\" si apre", $r['code'] === 200 && pulita($r));
}
$r = $anna->modulo("/pannello/$pid/procedura/struttura", "/pannello/$pid/impostazioni",
    ['name' => 'Casa Prova', 'city' => 'Lecce', 'region' => 'Puglia', 'checkin_from' => '15:00', 'checkout_by' => '10:00',
     'host_name' => 'Anna', 'host_phone' => '+39 333 1234567', 'host_whatsapp' => '+39 333 1234567', 'dopo' => 'checkin']);
prova('Salva e continua porta al passo dopo', $r['code'] === 302 && str_contains($r['loc'], '/procedura/checkin'));
$core = (int) val('SELECT id FROM sections WHERE property_id = ? AND is_core = 1', [$pid]);
$r = $anna->modulo("/pannello/$pid/procedura/checkin", "/pannello/$pid/sezioni/$core",
    ['checkin_steps' => ['Il portone è quello verde.', 'Le chiavi te le consegno io.', ''], 'checkin_note' => 'Se arrivi tardi, scrivimi.',
     'checkout_keys' => 'Lascia le chiavi sul tavolo.', 'checkout_waste' => 'Umido nel bidone marrone.', 'checkout_notes' => 'Buon viaggio!',
     'door_code' => '4729', 'dopo' => 'sezioni']);
prova('Check-in salvato, avanti alle sezioni', $r['code'] === 302 && str_contains($r['loc'], '/procedura/sezioni'));
$t = json_decode((string) val('SELECT data FROM section_translations WHERE section_id = ?', [$core]), true);
prova('Passaggi salvati come elenco, senza righe vuote', ($t['checkin_steps'] ?? []) === ['Il portone è quello verde.', 'Le chiavi te le consegno io.']);
prova('Un campo "door_code" inventato non entra', !str_contains((string) val('SELECT data FROM sections WHERE id = ?', [$core]) . json_encode($t), '4729'));
$r = $anna->post("/pannello/$pid/sezioni/$core", ['checkin_note' => 'Salvataggio automatico', 'checkin_steps' => ['Il portone è quello verde.']],
                 ['Accept: application/json']);
prova('Salvataggio automatico risponde in JSON', $r['code'] === 200 && (json_decode($r['body'], true)['ok'] ?? false) === true);
prova('Il salvataggio automatico non cambia il titolo', val('SELECT title FROM section_translations WHERE section_id = ?', [$core]) === 'Check-in & Check-out');

// ================================================================== SEZIONI
capitolo('Sezioni e limite di Essential');
$anna->get("/pannello/$pid/procedura/sezioni");
$ids = [];
foreach (['wifi', 'rules', 'eat', 'parking'] as $k) {
    $r = $anna->post("/pannello/$pid/sezioni", ['kind' => $k, 'torna' => 'procedura']);
    $ids[$k] = (int) val('SELECT id FROM sections WHERE property_id = ? AND kind = ?', [$pid, $k]);
    prova("Aggiunta $k", $ids[$k] > 0);
}
$r = $anna->post("/pannello/$pid/sezioni", ['kind' => 'transport', 'torna' => 'procedura']);
$r = $anna->segui($r);
prova('La quinta sezione è rifiutata dal server', !val('SELECT id FROM sections WHERE property_id = ? AND kind = ?', [$pid, 'transport']));
prova('Messaggio di limite elegante', str_contains($r['body'], 'Hai utilizzato tutte le 4 sezioni incluse nel tuo piano') && str_contains($r['body'], 'Scopri Plus'));
prova('Il contatore dice 4 su 4', str_contains($r['body'], '4 sezioni su 4 utilizzate'));
prova('Niente maniglie di trascinamento', !str_contains($r['body'], 'grip') && !str_contains($r['body'], '⋮'));
prova('Ordinamento con Sposta su / giù', str_contains($r['body'], 'Sposta su') && str_contains($r['body'], 'Sposta giù'));
$anna->post("/pannello/$pid/sezioni/{$ids['parking']}/azione", ['fai' => 'disattiva', 'torna' => 'procedura']);
prova('Disattivare libera un posto', (int) val('SELECT COUNT(*) FROM sections WHERE property_id = ? AND is_core = 0 AND is_active = 1', [$pid]) === 3);
$anna->get("/pannello/$pid/procedura/sezioni");
$anna->post("/pannello/$pid/sezioni", ['kind' => 'transport', 'torna' => 'procedura']);
prova('…e il posto si può usare', (bool) val('SELECT id FROM sections WHERE property_id = ? AND kind = ?', [$pid, 'transport']));
$anna->post("/pannello/$pid/sezioni/{$ids['parking']}/azione", ['fai' => 'attiva', 'torna' => 'procedura']);
prova('Riattivarla oltre il limite non riesce', (int) val('SELECT is_active FROM sections WHERE id = ?', [$ids['parking']]) === 0);
$anna->post("/pannello/$pid/sezioni/$core/azione", ['fai' => 'disattiva']);
prova('Check-in & Check-out non si disattiva', (int) val('SELECT is_active FROM sections WHERE id = ?', [$core]) === 1);
$pos = fn() => array_column(righe('SELECT id FROM sections WHERE property_id = ? AND is_core = 0 ORDER BY position, id', [$pid]), 'id');
$prima = $pos();
$anna->post("/pannello/$pid/sezioni/{$prima[1]}/azione", ['fai' => 'su']);
$dopo = $pos();
prova('Sposta su scambia due sezioni', $dopo[0] == $prima[1] && $dopo[1] == $prima[0]);

$r = $anna->modulo("/pannello/$pid/sezioni/{$ids['wifi']}", "/pannello/$pid/sezioni/{$ids['wifi']}",
    ['title' => 'Wi-Fi', 'network' => 'CasaProva_5G', 'password' => 'mare2026', 'instructions' => 'Riavvia il router se serve.', 'router_location' => 'In salotto.']);
prova('Wi-Fi salvato', $r['code'] === 302 && json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$ids['wifi']]), true)['network'] === 'CasaProva_5G');
$r = $anna->get("/pannello/$pid/sezioni/{$ids['wifi']}");
prova('Editor strutturato (niente mini-sintassi)', pulita($r) && str_contains($r['body'], 'Nome della rete') && !str_contains($r['body'], 'riga vuota'));
prova('Essential: niente caricamento immagini nelle sezioni', !str_contains($r['body'], 'name="foto"'));
$r = $anna->post("/pannello/$pid/sezioni/{$ids['wifi']}", ['network' => 'CasaProva_5G', 'foto' => file_(png(), 'image/png')]);
prova('Essential: immagine rifiutata anche forzando il modulo', str_contains($r['body'], 'comprese dal piano Plus') && !val('SELECT media_id FROM sections WHERE id = ?', [$ids['wifi']]));
$r = $anna->post("/pannello/$pid/sezioni/{$ids['wifi']}", ['network' => 'CasaProva_5G', 'pdf' => file_(pdfVero(), 'application/pdf')]);
prova('Essential: PDF rifiutato dal server', str_contains($r['body'], 'PDF') && !val('SELECT pdf_media_id FROM sections WHERE id = ?', [$ids['wifi']]));

$r = $anna->modulo("/pannello/$pid/sezioni/{$ids['eat']}", "/pannello/$pid/sezioni/{$ids['eat']}/luogo",
    ['name' => 'Trattoria del Porto', 'category' => 'Trattoria', 'description' => 'Pesce del giorno.', 'address' => 'Via del Porto 1, Lecce',
     'maps_url' => 'https://maps.google.com/?q=porto', 'phone' => '+39 0832 000000', 'website' => 'trattoria.example', 'booking_url' => 'https://prenota.example/x',
     'walk_minutes' => '6', 'drive_minutes' => '', 'note' => 'Chiedi il tavolo fuori.', 'badge' => 'Perfetto per cena', 'badge_tone' => 'ochre']);
$pl = riga('SELECT * FROM places WHERE section_id = ?', [$ids['eat']]);
prova('Luogo salvato con tutti i campi', $pl && $pl['phone'] !== '' && $pl['website'] === 'https://trattoria.example' && (int) $pl['walk_minutes'] === 6);
$anna->post("/pannello/$pid/sezioni/{$ids['eat']}/luogo", ['name' => 'Evil', 'website' => 'javascript:alert(1)', 'maps_url' => 'data:text/html,x']);
prova('Link pericolosi scartati', !val("SELECT id FROM places WHERE website LIKE 'javascript%' OR maps_url LIKE 'data:%'"));
$anna->post("/pannello/$pid/sezioni/{$ids['eat']}/luogo/" . val("SELECT id FROM places WHERE name = 'Evil'") . '/azione', ['fai' => 'elimina']);

$r = $anna->get("/pannello/$pid/anteprima/{$ids['eat']}");
prova('Anteprima di una sezione', $r['code'] === 200 && pulita($r) && str_contains($r['body'], 'Anteprima'));
prova('La nota dell\'host sul luogo si vede (bug corretto)', str_contains($r['body'], 'Chiedi il tavolo fuori.'));
foreach (['Apri Maps', 'Chiama', 'Visita il sito', 'Prenota'] as $cta) prova("Bottone \"$cta\"", str_contains($r['body'], $cta));
prova('Etichetta editoriale', str_contains($r['body'], 'Perfetto per cena'));
prova('Niente dati "live" inventati', !preg_match('/aperto ora|chiude alle|\d+(\.\d)?\s*★/i', $r['body']));
$r = $anna->get("/pannello/$pid/anteprima/$core");
prova('Anteprima check-in senza codici', $r['code'] === 200 && !str_contains($r['body'], '4729') && str_contains($r['body'], 'Il portone è quello verde.'));
$r = $anna->get("/pannello/$pid/anteprima/commiato");
prova('Commiato: solo le istruzioni dell\'host', $r['code'] === 200 && str_contains($r['body'], 'Lascia le chiavi sul tavolo.') && str_contains($r['body'], 'Umido nel bidone marrone.'));
prova('Anteprima non indicizzabile', stripos(intestazione($r, 'X-Robots-Tag'), 'noindex') !== false);

// ================================================================== LINGUE
capitolo('Lingue (Essential: italiano e inglese)');
$r = $anna->modulo("/pannello/$pid/lingue", "/pannello/$pid/lingue", ['locali' => ['it', 'en', 'fr']]);
prova('Il francese non è concesso a Essential', str_contains($r['body'], 'non comprende') && !val("SELECT 1 FROM property_locales WHERE property_id = ? AND locale = 'fr'", [$pid]));
$r = $anna->post("/pannello/$pid/lingue", ['locali' => ['it', 'en']]);
prova('Inglese attivato', (bool) val("SELECT 1 FROM property_locales WHERE property_id = ? AND locale = 'en'", [$pid]));
$r = $anna->get("/pannello/$pid/lingue/en");
prova('Pagina di traduzione affiancata', $r['code'] === 200 && pulita($r) && str_contains($r['body'], 'class="orig"'));
$r = $anna->post("/pannello/$pid/lingue/en", ['s' => [$ids['wifi'] => ['title' => 'Wi-Fi', 'instructions' => 'Restart the router if needed.', 'router_location' => 'In the living room.']]]);
prova('Traduzione manuale salvata', str_contains((string) val("SELECT data FROM section_translations WHERE section_id = ? AND locale = 'en'", [$ids['wifi']]), 'Restart the router'));
prova('Nessuna traduzione automatica chiamata', !is_file("$DOVE/app/storage/logs/translator.log"));
$r = $anna->get("/pannello/$pid/anteprima/{$ids['wifi']}?l=en");
prova('Guida in inglese: testo e interfaccia', str_contains($r['body'], 'Restart the router') && str_contains($r['body'], 'Password') && str_contains($r['body'], 'lang="en"'));

// ================================================================== ASPETTO
capitolo('Aspetto e palette');
$r = $anna->get("/pannello/$pid/aspetto");
prova('Aspetto con i campioni', $r['code'] === 200 && pulita($r) && substr_count($r['body'], 'class="swatch"') === 6);
$anna->post("/pannello/$pid/aspetto", ['palette' => 'mare', 'text_tone' => 'scuro']);
prova('Palette salvata', val('SELECT palette FROM properties WHERE id = ?', [$pid]) === 'mare');
$anna->post("/pannello/$pid/aspetto", ['palette' => 'inventata', 'text_tone' => 'scuro']);
prova('Palette inventata rifiutata', val('SELECT palette FROM properties WHERE id = ?', [$pid]) === 'mare');
$r = $anna->post("/pannello/$pid/aspetto", ['palette' => 'mare', 'text_tone' => 'scuro', 'logo' => file_(png(300, 300), 'image/png', 'logo.png')]);
$logo = riga('SELECT m.* FROM media m JOIN properties p ON p.logo_media_id = m.id WHERE p.id = ?', [$pid]);
prova('Logo caricato (Essential lo comprende)', (bool) $logo);
if (getenv('MHW_STORAGE') === 's3') {
    prova('Logo su S3, chiave non prevedibile per account e struttura', $logo && $logo['storage'] === 's3' && preg_match('#^a\d+/p' . $pid . '/\d{4}/[0-9a-f]{32}\.(png|jpg)$#', $logo['object_key']));
    $put = array_values(array_filter(array_map(fn($l) => json_decode($l, true), file("$S3_DIR/richieste.jsonl", FILE_IGNORE_NEW_LINES) ?: []), fn($x) => $x['metodo'] === 'PUT'));
    prova('Il bucket ha ricevuto un PUT firmato SigV4 con hash del contenuto', $put && $put[0]['auth'] && strlen($put[0]['sha']) === 64);
} else {
    prova('Logo sul disco, nome non prevedibile', $logo && $logo['storage'] === 'local' && preg_match('/[0-9a-f]{32}/', $logo['object_key'] . $logo['filename']));
}
$r = $anna->post("/pannello/$pid/aspetto", ['palette' => 'mare', 'text_tone' => 'scuro',
    'profile' => file_(png(200, 200), 'image/png')]);
prova('Immagine profilo rifiutata a Essential', str_contains($r['body'], 'non è compres') && !val('SELECT profile_media_id FROM properties WHERE id = ?', [$pid]));
$svg = "$TMP/x.svg"; file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
$r = $anna->post("/pannello/$pid/aspetto", ['palette' => 'mare', 'text_tone' => 'scuro', 'cover' => file_($svg, 'image/png', 'finto.png')]);
prova('SVG travestito da PNG rifiutato', !val('SELECT cover_media_id FROM properties WHERE id = ?', [$pid]));
$r = $anna->get("/pannello/$pid/anteprima");
prova('Anteprima con la palette scelta', str_contains($r['body'], 'id="palette"') && str_contains($r['body'], '#1c5a78'));
preg_match('#<img[^>]+class="logo-guest"[^>]+src="([^"]+)"|src="([^"]+)"[^>]*class="logo-guest"#', $r['body'], $m);
$logoUrl = html_entity_decode($m[1] ?? $m[2] ?? '');
if (getenv('MHW_STORAGE') === 's3') prova('Il logo si vede con un URL prefirmato', str_contains($logoUrl, 'X-Amz-Signature='));
else prova('Il logo si serve da /media', str_contains($logoUrl, '/media/'));
if ($logoUrl && str_starts_with($logoUrl, '/')) $logoUrl = preg_replace('#^(https?://[^/]+).*$#', '$1', $BASE) . $logoUrl;
if ($logoUrl) { $ch = curl_init($logoUrl); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); $b = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    prova('…e il file arriva davvero', $code === 200 && str_starts_with((string) $b, "\x89PNG")); }

// ============================================================== PUBBLICAZIONE
capitolo('Pubblicazione e pagamento');
$slug = val('SELECT slug FROM properties WHERE id = ?', [$pid]);
$r = $anna->get("/g/$slug");
prova('Prima di pagare la guida non è online', $r['code'] === 404);
$r = $anna->modulo("/pannello/$pid/procedura/anteprima", "/pannello/$pid/pubblica", []);
$r = $anna->segui($r);
prova('Email non verificata: niente pagamento', str_contains($r['body'], 'Conferma prima la tua email') && (int) val('SELECT COUNT(*) FROM orders WHERE account_id = ?', [$acc['id']]) === 0);
$link = linkPosta('anna@prova.test', 'verifica');
$r = $anna->get(substr($link, strpos($link, '/verifica/')));
prova('Link di verifica funziona', $r['code'] === 302 && val("SELECT email_verified_at FROM users WHERE email = 'anna@prova.test'") !== null);
$r = $anna->get(substr($link, strpos($link, '/verifica/')));
prova('Il link di verifica vale una volta sola', $r['code'] === 200 && pulita($r));

$r = $anna->modulo("/pannello/$pid/procedura/anteprima", "/pannello/$pid/pubblica", []);
prova('Pubblica → Stripe Checkout', $r['code'] === 302 && str_starts_with($r['loc'], 'https://checkout.stripe.test/'));
$ordine = riga('SELECT * FROM orders WHERE account_id = ? ORDER BY id DESC', [$acc['id']]);
prova('Ordine in attesa, legato alla struttura', $ordine && $ordine['status'] === 'pending' && (int) $ordine['property_id'] === $pid && (int) $ordine['amount_cents'] === 8700);
$cs = array_values(array_filter(richiesteStripe(), fn($x) => $x['percorso'] === '/v1/checkout/sessions'));
$c = end($cs)['corpo'] ?? [];
prova('Checkout in modalità abbonamento', ($c['mode'] ?? '') === 'subscription');
prova('Rinnovo annuale', ($c['line_items'][0]['price_data']['recurring']['interval'] ?? '') === 'year');
prova('Prezzo IVA esclusa', ($c['line_items'][0]['price_data']['tax_behavior'] ?? '') === 'exclusive' && ($c['line_items'][0]['price_data']['unit_amount'] ?? '') === '8700');
prova('Dati di fatturazione e partita IVA raccolti', ($c['billing_address_collection'] ?? '') === 'required' && ($c['tax_id_collection']['enabled'] ?? '') === 'true');
prova('Metadati sull\'abbonamento', ($c['subscription_data']['metadata']['order_id'] ?? '') === (string) $ordine['id']);
prova('Chiave di idempotenza sul checkout', (end($cs)['idem'] ?? '') === 'mhw-checkout-order-' . $ordine['id']);
prova('Cliente Stripe creato una volta', count(array_filter(richiesteStripe(), fn($x) => $x['percorso'] === '/v1/customers')) === 1);

$r = $anna->get('/pagamento/ok?order=' . $ordine['id']);
prova('Ritorno dal pagamento: pagina di attesa', $r['code'] === 200 && pulita($r));
$r = $anna->get("/g/$slug");
prova('Il ritorno dal browser NON pubblica niente', $r['code'] === 404);
$st = json_decode($anna->get('/pagamento/stato?order=' . $ordine['id'])['body'], true);
prova('Lo stato dice "non ancora"', ($st['stato'] ?? '') === 'pending' && ($st['pubblicata'] ?? true) === false);

$acc = riga('SELECT * FROM accounts WHERE id = ?', [$acc['id']]);
prova('Cliente Stripe salvato sull\'account', str_starts_with((string) $acc['stripe_customer_id'], 'cus_finto'));
$evento = ['id' => 'evt_prova_1', 'type' => 'checkout.session.completed', 'data' => ['object' => [
    'id' => $ordine['provider_session_id'], 'mode' => 'subscription', 'payment_status' => 'paid', 'customer' => $acc['stripe_customer_id'] ?: 'cus_x',
    'subscription' => 'sub_prova_anna', 'client_reference_id' => (string) $ordine['id'],
    'metadata' => ['order_id' => (string) $ordine['id'], 'account_id' => (string) $acc['id']]]]];
$r = inviaWebhook($evento, 'whsec_sbagliato');
prova('Webhook con firma sbagliata respinto', $r['code'] === 400);
$r = inviaWebhook($evento, null, time() - 3600);
prova('Webhook troppo vecchio respinto', $r['code'] === 400);
prova('…e niente attivato', val('SELECT status FROM orders WHERE id = ?', [$ordine['id']]) === 'pending');
$r = inviaWebhook($evento);
prova('Webhook firmato accettato (senza CSRF né sessione)', $r['code'] === 200 && $r['body'] === 'abbonamento-attivato', $r['body']);
$sub = riga("SELECT * FROM subscriptions WHERE provider_subscription_id = 'sub_prova_anna'");
prova('Abbonamento attivo con periodo e price', $sub && $sub['status'] === 'active' && $sub['current_period_end'] > gmdate('Y-m-d', strtotime('+360 days')) && $sub['provider_price_id'] === 'price_finto_annuale');
prova('Ordine pagato', val('SELECT status FROM orders WHERE id = ?', [$ordine['id']]) === 'paid');
$r = $anna->get("/g/$slug");
prova('Guida online dopo il webhook', $r['code'] === 200 && pulita($r));
prova('Guida non indicizzabile', stripos(intestazione($r, 'X-Robots-Tag'), 'noindex') !== false && str_contains($r['body'], 'noindex'));
prova('La guida pubblica non apre sessioni', intestazione($r, 'Set-Cookie') === '');
$venduti = (int) val('SELECT sold_count FROM package_versions WHERE id = ?', [pv('essential')]);
$r = inviaWebhook($evento);
prova('Lo stesso evento due volte non fa danni', $r['code'] === 200 && $r['body'] === 'gia-elaborato' && (int) val('SELECT sold_count FROM package_versions WHERE id = ?', [pv('essential')]) === $venduti);
prova('Una sola riga di abbonamento', (int) val('SELECT COUNT(*) FROM subscriptions WHERE account_id = ?', [$acc['id']]) === 1);
$st = json_decode($anna->get('/pagamento/stato?order=' . $ordine['id'])['body'], true);
prova('La pagina di attesa ora vede la guida online', ($st['stato'] ?? '') === 'paid' && ($st['pubblicata'] ?? false) === true);

$r = $anna->get("/g/$slug/{$ids['wifi']}", ['Accept-Language: en-GB,en;q=0.9']);
prova('La lingua del telefono sceglie l\'inglese', str_contains($r['body'], 'Restart the router'));
$r = $anna->get("/g/$slug/{$ids['wifi']}", ['Accept-Language: de-DE']);
prova('Lingua non pubblicata → lingua principale', str_contains($r['body'], 'Riavvia il router') && str_contains($r['body'], 'lang="it"'));
$qr = val('SELECT token FROM qr_tokens WHERE property_id = ?', [$pid]);
$r = $anna->get("/q/$qr");
prova('Il QR porta alla guida', $r['code'] === 302 && str_contains($r['loc'], "/g/$slug/benvenuto"));
foreach (['png' => "\x89PNG", 'svg' => '<svg', 'pdf' => '%PDF'] as $fmt => $magia) {
    $r = $anna->get("/pannello/$pid/qr.$fmt");
    prova("QR scaricabile in $fmt", $r['code'] === 200 && str_contains(substr($r['body'], 0, 200), $magia) && str_contains(intestazione($r, 'Content-Disposition'), 'attachment'));
}
$r = $anna->get("/pannello/$pid/qr");
prova('Pagina QR & Link con copia link', $r['code'] === 200 && pulita($r) && str_contains($r['body'], 'data-copia'));
$r = $anna->get("/pannello/$pid/statistiche");
prova('Statistiche: Essential vede l\'invito a Plus, non i numeri', str_contains($r['body'], 'Scopri Plus') && !str_contains($r['body'], 'aperture della guida'));

// Ripubblicazione con abbonamento attivo: nessun nuovo pagamento.
$anna->modulo("/pannello/$pid/sezioni/{$ids['rules']}", "/pannello/$pid/sezioni/{$ids['rules']}", ['title' => 'Regole', 'items' => ['Niente feste.', 'Silenzio dopo le 23.']]);
$ordini = (int) val('SELECT COUNT(*) FROM orders WHERE account_id = ?', [$acc['id']]);
$r = $anna->get("/pannello/$pid");
prova('Il pannello segnala le modifiche non pubblicate', str_contains($r['body'], 'modifiche non ancora pubblicate'));
$r = $anna->post("/pannello/$pid/pubblica", []);
prova('Ripubblicare con abbonamento attivo non passa da Stripe', $r['code'] === 302 && str_contains($r['loc'], '/pubblicata') && (int) val('SELECT COUNT(*) FROM orders WHERE account_id = ?', [$acc['id']]) === $ordini);
prova('Nuova versione della guida', (int) val('SELECT MAX(version) FROM guide_versions WHERE property_id = ?', [$pid]) === 2);
prova('Gli ospiti vedono la modifica', str_contains($anna->get("/g/$slug/{$ids['rules']}")['body'], 'Silenzio dopo le 23.'));
$r = $anna->get('/piano');
$r2 = $anna->post('/piano', ['pv' => pv('plus')]);
prova('Con un abbonamento attivo non si ricompra dal listino', $r2['code'] === 302 && str_contains($r2['loc'], '/account'));

// ========================================================== RINNOVO E SCADENZA
capitolo('Rinnovo, scadenza, pagamento fallito');
$r = $anna->get('/account');
prova('Account: piano, prezzo, rinnovo', $r['code'] === 200 && pulita($r) && str_contains($r['body'], 'Essential') && str_contains($r['body'], '+ IVA / anno') && str_contains($r['body'], 'si rinnova il'));
$r = $anna->post('/account/rinnovo', ['rinnovo' => 'no']);
$ultima = array_values(array_filter(richiesteStripe(), fn($x) => str_starts_with($x['percorso'], '/v1/subscriptions/sub_prova_anna') && $x['metodo'] === 'POST'));
prova('Rinnovo spento su Stripe', ($ultima[0]['corpo']['cancel_at_period_end'] ?? '') === 'true');
prova('…e segnato subito', (int) val("SELECT cancel_at_period_end FROM subscriptions WHERE provider_subscription_id = 'sub_prova_anna'") === 1);
prova('La guida resta online fino a fine periodo', $anna->get("/g/$slug")['code'] === 200);
$r = $anna->segui($anna->get('/account'));
prova('Account dice fino a quando resta online', str_contains($r['body'], 'Rinnovo automatico disattivato'));
$anna->post('/account/rinnovo', ['rinnovo' => 'si']);
prova('Rinnovo riattivabile', (int) val("SELECT cancel_at_period_end FROM subscriptions WHERE provider_subscription_id = 'sub_prova_anna'") === 0);
$r = $anna->post('/account/portale', []);
prova('Portale clienti Stripe', $r['code'] === 302 && str_starts_with($r['loc'], 'https://billing.stripe.test/'));

$scaduto = time() - 86400;
$r = inviaWebhook(['id' => 'evt_prova_2', 'type' => 'customer.subscription.updated', 'data' => ['object' => [
    'id' => 'sub_prova_anna', 'status' => 'active', 'cancel_at_period_end' => false,
    'items' => ['data' => [['current_period_start' => $scaduto - 365 * 86400, 'current_period_end' => $scaduto, 'price' => ['id' => 'price_finto_annuale']]]]]]]);
prova('Periodo finito (anche se Stripe dice ancora "active")', $r['code'] === 200);
$r = $anna->get("/g/$slug");
prova('A scadenza la guida va offline', $r['code'] === 404 && str_contains($r['body'], 'non è disponibile'));
prova('…senza perdere i dati', (int) val('SELECT COUNT(*) FROM sections WHERE property_id = ?', [$pid]) >= 5);
prova('…e con un messaggio pulito, senza dettagli tecnici', pulita($r) && !str_contains($r['body'], 'abbonamento'));
$r = inviaWebhook(['id' => 'evt_prova_3', 'type' => 'invoice.paid', 'data' => ['object' => [
    'id' => 'in_1', 'subscription' => 'sub_prova_anna', 'customer' => 'cus_x',
    'lines' => ['data' => [['period' => ['start' => time(), 'end' => time() + 365 * 86400]]]]]]]);
prova('Rinnovo pagato → di nuovo online', $r['body'] === 'rinnovo-pagato' && $anna->get("/g/$slug")['code'] === 200);
$r = inviaWebhook(['id' => 'evt_prova_4', 'type' => 'invoice.payment_failed', 'data' => ['object' => ['id' => 'in_2', 'subscription' => 'sub_prova_anna']]]);
prova('Pagamento fallito → past_due → offline (nessuna tolleranza configurata)', $r['body'] === 'pagamento-fallito' && $anna->get("/g/$slug")['code'] === 404);
$r = $anna->segui($anna->get("/pannello/$pid"));
prova('Il pannello resta accessibile a guida offline', $r['code'] === 200 && pulita($r) && str_contains($r['body'], 'Offline'));
inviaWebhook(['id' => 'evt_prova_5', 'type' => 'invoice.paid', 'data' => ['object' => ['id' => 'in_3', 'subscription' => 'sub_prova_anna',
    'lines' => ['data' => [['period' => ['start' => time(), 'end' => time() + 365 * 86400]]]]]]]);
$r = inviaWebhook(['id' => 'evt_prova_6', 'type' => 'customer.subscription.deleted', 'data' => ['object' => ['id' => 'sub_prova_anna', 'status' => 'canceled']]]);
prova('Abbonamento chiuso → offline', $r['body'] === 'abbonamento-chiuso' && $anna->get("/g/$slug")['code'] === 404);
$r = $anna->segui($anna->get('/account'));
prova('Account dice che non è attivo e che i dati restano', str_contains($r['body'], 'Non attivo') && str_contains($r['body'], 'niente è stato cancellato'));
$r = inviaWebhook(['id' => 'evt_prova_7', 'type' => 'invoice.paid', 'data' => ['object' => ['id' => 'in_x', 'subscription' => 'sub_sconosciuto']]]);
prova('Evento per un abbonamento sconosciuto: ignorato con calma', $r['code'] === 200);
$r = inviaWebhook(['id' => 'evt_prova_8', 'type' => 'checkout.session.completed', 'data' => ['object' => [
    'mode' => 'subscription', 'payment_status' => 'paid', 'subscription' => 'sub_y', 'metadata' => ['order_id' => (string) $ordine['id'], 'account_id' => '999']]]]);
prova('Checkout con account incoerente: rifiutato', $r['body'] === 'incoerente');

// Ripubblicare dopo la chiusura passa di nuovo da Stripe.
$r = $anna->post("/pannello/$pid/pubblica", []);
prova('Senza abbonamento, ripubblicare richiede un nuovo pagamento', $r['code'] === 302 && str_starts_with($r['loc'], 'https://checkout.stripe.test/'));
$o2 = riga('SELECT * FROM orders WHERE account_id = ? ORDER BY id DESC', [$acc['id']]);
$r = $anna->get('/pagamento/annullato?order=' . $o2['id']);
prova('Pagamento annullato: bozza intatta, ordine annullato', val('SELECT status FROM orders WHERE id = ?', [$o2['id']]) === 'canceled' && val('SELECT status FROM properties WHERE id = ?', [$pid]) === 'published');
file_put_contents("$STRIPE_DIR/guasto", '1');
$r = $anna->segui($anna->post("/pannello/$pid/pubblica", []));
@unlink("$STRIPE_DIR/guasto");
prova('Stripe irraggiungibile: messaggio umano con codice, nessun dettaglio tecnico', str_contains($r['body'], 'Il pagamento non è disponibile in questo momento') && !str_contains($r['body'], 'Simulated outage'));

// ===================================================================== PLUS
capitolo('Plus: sezioni illimitate, 5 lingue, immagini, PDF, statistiche');
$bruno = new Browser('bruno');
$bruno->get('/registrati?piano=' . pv('plus'));
$bruno->post('/registrati', ['piano' => pv('plus'), 'name' => 'Bruno Plus', 'email' => 'bruno@prova.test', 'password' => 'BrunoProva123', 'termini' => '1', 'privacy' => '1']);
$bruno->get('/piano'); $bruno->post('/piano', ['pv' => pv('plus')]);
$bruno->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Villa Plus', 'city' => 'Otranto']);
$bpid = (int) val("SELECT id FROM properties WHERE name = 'Villa Plus'");
$bruno->get("/pannello/$bpid");
foreach (['wifi', 'rules', 'eat', 'parking', 'transport', 'waste', 'visit', 'emergency'] as $k) $bruno->post("/pannello/$bpid/sezioni", ['kind' => $k]);
prova('Plus: più di 4 sezioni', (int) val('SELECT COUNT(*) FROM sections WHERE property_id = ? AND is_core = 0 AND is_active = 1', [$bpid]) === 8);
$bruno->modulo("/pannello/$bpid/lingue", "/pannello/$bpid/lingue", ['locali' => ['it', 'en', 'fr', 'de', 'es']]);
prova('Plus: cinque lingue', (int) val('SELECT COUNT(*) FROM property_locales WHERE property_id = ?', [$bpid]) === 5);
$bw = (int) val("SELECT id FROM sections WHERE property_id = ? AND kind = 'wifi'", [$bpid]);
$r = $bruno->modulo("/pannello/$bpid/sezioni/$bw", "/pannello/$bpid/sezioni/$bw", ['network' => 'Villa', 'password' => 'x', 'foto' => file_(png(), 'image/png')]);
prova('Plus: immagine nella sezione', (bool) val('SELECT media_id FROM sections WHERE id = ?', [$bw]));
$r = $bruno->post("/pannello/$bpid/sezioni/$bw", ['network' => 'Villa', 'pdf' => file_(pdfVero(), 'application/pdf', 'manuale.pdf')]);
prova('Plus: PDF vero accettato', (bool) val('SELECT pdf_media_id FROM sections WHERE id = ?', [$bw]));
$finto = "$TMP/finto.pdf"; file_put_contents($finto, "%PDF-1.4\n1 0 obj << /OpenAction << /S /JavaScript /JS (app.alert(1)) >> >> endobj\n%%EOF");
$prima = val('SELECT pdf_media_id FROM sections WHERE id = ?', [$bw]);
$r = $bruno->post("/pannello/$bpid/sezioni/$bw", ['network' => 'Villa', 'pdf' => file_($finto, 'application/pdf')]);
prova('PDF con JavaScript rifiutato', val('SELECT pdf_media_id FROM sections WHERE id = ?', [$bw]) == $prima && pulita($r));
$txt = "$TMP/nonpdf.pdf"; file_put_contents($txt, 'ciao');
$r = $bruno->post("/pannello/$bpid/sezioni/$bw", ['network' => 'Villa', 'pdf' => file_($txt, 'application/pdf')]);
prova('File di testo travestito da PDF rifiutato', val('SELECT pdf_media_id FROM sections WHERE id = ?', [$bw]) == $prima);
$r = $bruno->post("/pannello/$bpid/aspetto", ['palette' => 'oliva', 'text_tone' => 'scuro', 'profile' => file_(png(200, 200), 'image/png')]);
prova('Plus: immagine profilo', (bool) val('SELECT profile_media_id FROM properties WHERE id = ?', [$bpid]));
$r = $bruno->get("/pannello/$bpid/statistiche");
prova('Plus: statistiche di base', $r['code'] === 200 && str_contains($r['body'], 'aperture della guida'));
prova('Plus: voce Statistiche nel menu', str_contains($bruno->get("/pannello/$bpid")['body'], '/statistiche"'));
// Plus senza verificare l'email non paga.
$bcore = (int) val('SELECT id FROM sections WHERE property_id = ? AND is_core = 1', [$bpid]);
$bruno->post("/pannello/$bpid/sezioni/$bcore", ['checkin_steps' => ['Suona al citofono.']]);
$r = $bruno->segui($bruno->post("/pannello/$bpid/pubblica", []));
prova('Plus non verificato: pubblicazione ferma', str_contains($r['body'], 'Conferma prima la tua email'));

// =================================================================== PORTFOLIO
capitolo('Portfolio');
$pp = pv('portfolio');
$pubblici = righe("SELECT p.code FROM packages p WHERE p.public = 1 AND p.active = 1 ORDER BY p.sort");
prova('Un solo Portfolio in vendita; i vecchi 2 e 3 restano, fuori listino', in_array('portfolio', array_column($pubblici, 'code'), true)
      && !array_intersect(['portfolio2', 'portfolio3'], array_column($pubblici, 'code')) && pv('portfolio2') > 0 && pv('portfolio3') > 0);
prova('Portfolio: 117 € la prima struttura, 60 € ogni altra, da 2 strutture', (bool) val('SELECT id FROM package_versions WHERE id = ? AND per_property = 1 AND price_cents = 11700 AND extra_price_cents = 6000 AND min_quantity = 2', [$pp]));
$carla = new Browser('carla');
$r = $carla->get("/registrati?piano=$pp&strutture=3");
prova('La quantità scelta sulla landing arriva alla registrazione', str_contains($r['body'], 'name="strutture" value="3"'));
$r = $carla->post('/registrati', ['piano' => $pp, 'strutture' => '3', 'name' => 'Carla Portfolio', 'email' => 'carla@prova.test', 'password' => 'CarlaProva123', 'termini' => '1', 'privacy' => '1']);
prova('…e dopo la registrazione alla scelta del piano', $r['code'] === 302 && str_contains($r['loc'], "/piano?piano=$pp&strutture=3"));
$r = $carla->get("/piano?piano=$pp&strutture=3");
prova('…che la mostra già impostata, con il totale (237 €)', preg_match('#name="strutture" type="number"[^>]*value="3"#', $r['body']) === 1 && str_contains($r['body'], "237\u{00A0}€"));
$cacc = (int) val("SELECT a.id FROM accounts a JOIN users u ON u.id = a.user_id WHERE u.email = 'carla@prova.test'");
foreach (['1', '2.5', '0', 'tre', '51', '-3', '2e1'] as $x) {
    $r = $carla->post('/piano', ['pv' => $pp, 'strutture' => $x]);
    if (!($r['code'] === 302 && str_contains($r['loc'], "/piano?piano=$pp"))) prova("Quantità «{$x}» rifiutata", false, $r['loc']);
}
prova('Quantità non intere o fuori dai limiti rifiutate dal server', (int) val('SELECT COALESCE(intended_package_version_id, 0) FROM accounts WHERE id = ?', [$cacc]) !== $pp
      && str_contains($carla->get("/piano?piano=$pp")['body'], 'Indica un numero intero di strutture tra 2 e 50.'));
$r = $carla->post('/piano', ['pv' => $pp, 'strutture' => '3']);
prova('Portfolio per 3 strutture scelto', $r['code'] === 302 && (int) val('SELECT intended_quantity FROM accounts WHERE id = ?', [$cacc]) === 3
      && (int) val('SELECT intended_package_version_id FROM accounts WHERE id = ?', [$cacc]) === $pp);
foreach (['Casa Uno', 'Casa Due', 'Casa Tre', 'Casa Quattro'] as $n) $carla->modulo('/pannello/nuova', '/pannello/nuova', ['name' => $n, 'city' => 'Bari']);
prova('Limite lato server: tre strutture, la quarta no', (int) val('SELECT COUNT(*) FROM properties WHERE account_id = ?', [$cacc]) === 3);
$cp = array_map('intval', array_column(righe('SELECT id FROM properties WHERE account_id = ? ORDER BY id', [$cacc]), 'id'));
$c1 = $cp[0];
$carla->get("/pannello/$c1");
foreach (['wifi', 'rules', 'eat', 'parking', 'transport'] as $k) $carla->post("/pannello/$c1/sezioni", ['kind' => $k]);
prova('Portfolio: funzioni Plus per struttura (più di 4 sezioni)', (int) val('SELECT COUNT(*) FROM sections WHERE property_id = ? AND is_core = 0', [$c1]) === 5);

// Checkout: un abbonamento, due voci.
$link = linkPosta('carla@prova.test', 'verifica');
$carla->get(substr($link, strpos($link, '/verifica/')));
$ccore = (int) val('SELECT id FROM sections WHERE property_id = ? AND is_core = 1', [$c1]);
$carla->post("/pannello/$c1/sezioni/$ccore", ['checkin_steps' => ['Suona al citofono.']]);
$r = $carla->modulo("/pannello/$c1/procedura/anteprima", "/pannello/$c1/pubblica", []);
prova('Pubblica → Stripe Checkout', $r['code'] === 302 && str_starts_with($r['loc'], 'https://checkout.stripe.test/'), $r['loc']);
$cord = riga('SELECT * FROM orders WHERE account_id = ? ORDER BY id DESC', [$cacc]);
prova('Ordine per 3 strutture: 117 + 2 × 60 = 237 €', $cord && (int) $cord['quantity'] === 3 && (int) $cord['amount_cents'] === 23700);
$cs = array_values(array_filter(richiesteStripe(), fn($x) => $x['percorso'] === '/v1/checkout/sessions'));
$c = end($cs)['corpo'] ?? [];
$li = $c['line_items'] ?? [];
prova('Checkout: un solo abbonamento annuale con due voci', ($c['mode'] ?? '') === 'subscription' && count($li) === 2
      && ($li[0]['price_data']['recurring']['interval'] ?? '') === 'year' && ($li[1]['price_data']['recurring']['interval'] ?? '') === 'year');
prova('…prima struttura 117 € × 1', ($li[0]['quantity'] ?? '') === '1' && ($li[0]['price_data']['unit_amount'] ?? '') === '11700' && ($li[0]['price_data']['tax_behavior'] ?? '') === 'exclusive');
prova('…strutture aggiuntive 60 € × 2, IVA esclusa', ($li[1]['quantity'] ?? '') === '2' && ($li[1]['price_data']['unit_amount'] ?? '') === '6000' && ($li[1]['price_data']['tax_behavior'] ?? '') === 'exclusive'
      && ($li[1]['price_data']['product_data']['metadata']['ruolo'] ?? '') === 'aggiuntiva');
prova('…quantità nei metadati', ($c['subscription_data']['metadata']['quantity'] ?? '') === '3');
prova('Prima del pagamento: ancora nessun abbonamento', !val('SELECT id FROM subscriptions WHERE account_id = ?', [$cacc]));
file_put_contents("$STRIPE_DIR/extra-sub_prova_carla", '2');
$r = inviaWebhook(['id' => 'evt_carla_1', 'type' => 'checkout.session.completed', 'data' => ['object' => [
    'id' => $cord['provider_session_id'], 'mode' => 'subscription', 'payment_status' => 'paid', 'customer' => 'cus_carla',
    'subscription' => 'sub_prova_carla', 'client_reference_id' => (string) $cord['id'], 'metadata' => ['order_id' => (string) $cord['id'], 'account_id' => (string) $cacc]]]]);
$csub = riga("SELECT * FROM subscriptions WHERE provider_subscription_id = 'sub_prova_carla'");
prova('Webhook: abbonamento attivo per 3 strutture, letto dalle voci di Stripe', $r['body'] === 'abbonamento-attivato' && $csub && (int) $csub['quantity'] === 3
      && $csub['provider_extra_item_id'] === 'si_extra_sub_prova_carla', $r['body']);
$ult = array_values(array_filter(richiesteStripe(), fn($x) => $x['percorso'] === '/v1/subscriptions/sub_prova_carla'));
prova('…chiedendo a Stripe il prodotto di ogni voce', str_contains((string) ($ult[0]['percorso'] ?? ''), 'sub_prova_carla'));
prova('Guida online dopo il pagamento', $carla->get('/g/' . val('SELECT slug FROM properties WHERE id = ?', [$c1]))['code'] === 200);
$carla->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Casa Quattro', 'city' => 'Bari']);
prova('Con l\'abbonamento attivo il limite resta 3', (int) val('SELECT COUNT(*) FROM properties WHERE account_id = ?', [$cacc]) === 3);
$r = $carla->get('/account');
prova('Account: Portfolio · 3 strutture, 237 €, e il campo per cambiarle', str_contains($r['body'], 'Portfolio · 3 strutture') && str_contains($r['body'], "237\u{00A0}€") && str_contains($r['body'], 'action="' . preg_replace('#^https?://[^/]+#', '', $BASE) . '/account/strutture"'));

// Aumento: conguaglio pagato subito, quantità nuova solo col webhook.
$r = $carla->post('/account/strutture', ['strutture' => '5']);
prova('Aumento a 5: conferma con il nuovo prezzo (357 €)', $r['code'] === 200 && str_contains($r['body'], "237\u{00A0}€ → <b>357\u{00A0}€</b>") && str_contains($r['body'], 'differenza'));
$r = $carla->post('/account/strutture', ['strutture' => '5', 'conferma' => '1']);
$mod = array_values(array_filter(richiesteStripe(), fn($x) => $x['metodo'] === 'POST' && $x['percorso'] === '/v1/subscriptions/sub_prova_carla'));
$mc = end($mod)['corpo'] ?? [];
prova('Stripe: stessa voce, 4 strutture aggiuntive, conguaglio fatturato subito', ($mc['items'][0]['id'] ?? '') === 'si_extra_sub_prova_carla' && ($mc['items'][0]['quantity'] ?? '') === '4'
      && ($mc['proration_behavior'] ?? '') === 'always_invoice' && ($mc['payment_behavior'] ?? '') === 'pending_if_incomplete' && (end($mod)['idem'] ?? '') !== '');
prova('…ma la quantità non cambia prima della conferma', (int) val("SELECT quantity FROM subscriptions WHERE provider_subscription_id = 'sub_prova_carla'") === 3);
$voci = fn(int $extra) => ['data' => [
    ['id' => 'si_base_sub_prova_carla', 'quantity' => 1, 'current_period_start' => time(), 'current_period_end' => time() + 360 * 86400, 'price' => ['id' => 'price_finto_annuale', 'product' => 'prod_finto_base']],
    ['id' => 'si_extra_sub_prova_carla', 'quantity' => $extra, 'current_period_start' => time(), 'current_period_end' => time() + 360 * 86400, 'price' => ['id' => 'price_finto_extra', 'product' => 'prod_finto_extra']]]];
inviaWebhook(['id' => 'evt_carla_2', 'type' => 'customer.subscription.updated', 'data' => ['object' => [
    'id' => 'sub_prova_carla', 'status' => 'active', 'cancel_at_period_end' => false, 'items' => $voci(2), 'pending_update' => ['subscription_items' => [['id' => 'si_extra_sub_prova_carla', 'quantity' => 4]]]]]]);
prova('Pagamento del conguaglio non riuscito: restano 3 strutture', (int) val("SELECT quantity FROM subscriptions WHERE provider_subscription_id = 'sub_prova_carla'") === 3);
inviaWebhook(['id' => 'evt_carla_3', 'type' => 'customer.subscription.updated', 'data' => ['object' => [
    'id' => 'sub_prova_carla', 'status' => 'active', 'cancel_at_period_end' => false, 'items' => $voci(4)]]]);
prova('Pagamento confermato dal webhook: 5 strutture', (int) val("SELECT quantity FROM subscriptions WHERE provider_subscription_id = 'sub_prova_carla'") === 5);
foreach (['Casa Quattro', 'Casa Cinque', 'Casa Sei'] as $n) $carla->modulo('/pannello/nuova', '/pannello/nuova', ['name' => $n, 'city' => 'Bari']);
prova('…la quarta e la quinta entrano, la sesta no', (int) val('SELECT COUNT(*) FROM properties WHERE account_id = ?', [$cacc]) === 5);

// Riduzione: prima si sceglie cosa archiviare; niente si cancella.
$sezioniPrima = (int) val('SELECT COUNT(*) FROM sections s JOIN properties p ON p.id = s.property_id WHERE p.account_id = ?', [$cacc]);
$richiestePrima = count(richiesteStripe());
$r = $carla->post('/account/strutture', ['strutture' => '3']);
prova('Riduzione a 3: chiede quali 2 strutture archiviare', $r['code'] === 200 && str_contains($r['body'], 'Scegli 2 strutture da archiviare') && substr_count($r['body'], 'name="archivia[]"') === 5);
$r = $carla->post('/account/strutture', ['strutture' => '3', 'conferma' => '1', 'archivia' => [$c1]]);
prova('…una sola scelta non basta, e Stripe non viene toccato', str_contains($r['loc'], '/account/strutture?strutture=3') && count(richiesteStripe()) === $richiestePrima
      && (int) val('SELECT COUNT(*) FROM properties WHERE account_id = ? AND archived_at IS NOT NULL', [$cacc]) === 0);
$altro = (int) val('SELECT id FROM properties WHERE id NOT IN (SELECT id FROM properties WHERE account_id = ?) LIMIT 1', [$cacc]);
$r = $carla->post('/account/strutture', ['strutture' => '3', 'conferma' => '1', 'archivia' => [$c1, $altro]]);
prova('…né una struttura di un altro account', count(richiesteStripe()) === $richiestePrima && val('SELECT archived_at FROM properties WHERE id = ?', [$altro]) === null);
$r = $carla->post('/account/strutture', ['strutture' => '3', 'conferma' => '1', 'archivia' => [$c1, $cp[1]]]);
$mod = array_values(array_filter(richiesteStripe(), fn($x) => $x['metodo'] === 'POST' && $x['percorso'] === '/v1/subscriptions/sub_prova_carla'));
$mc = end($mod)['corpo'] ?? [];
prova('Stripe: 2 strutture aggiuntive, credito proporzionale', ($mc['items'][0]['quantity'] ?? '') === '2' && ($mc['proration_behavior'] ?? '') === 'create_prorations' && !isset($mc['payment_behavior']));
prova('Le due strutture scelte sono archiviate, non cancellate', (int) val('SELECT COUNT(*) FROM properties WHERE account_id = ? AND archived_at IS NOT NULL', [$cacc]) === 2
      && (int) val('SELECT COUNT(*) FROM properties WHERE account_id = ?', [$cacc]) === 5
      && (int) val('SELECT COUNT(*) FROM sections s JOIN properties p ON p.id = s.property_id WHERE p.account_id = ?', [$cacc]) === $sezioniPrima);
prova('La guida archiviata va offline (il QR resta)', $carla->get('/g/' . val('SELECT slug FROM properties WHERE id = ?', [$c1]))['code'] === 404
      && (bool) val('SELECT id FROM qr_tokens WHERE property_id = ?', [$c1]));
inviaWebhook(['id' => 'evt_carla_4', 'type' => 'customer.subscription.updated', 'data' => ['object' => [
    'id' => 'sub_prova_carla', 'status' => 'active', 'cancel_at_period_end' => false, 'items' => $voci(2)]]]);
prova('Webhook: 3 strutture', (int) val("SELECT quantity FROM subscriptions WHERE provider_subscription_id = 'sub_prova_carla'") === 3);
$r = $carla->get('/pannello');
prova('Le guide mostrano le archiviate, con "Riattiva"', str_contains($r['body'], 'Archiviata') && str_contains($r['body'], "/pannello/$c1/riattiva"));
$carla->post("/pannello/$c1/riattiva", []);
prova('Riattivare oltre il limite non si può', val('SELECT archived_at FROM properties WHERE id = ?', [$c1]) !== null);
$carla->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Casa Sette', 'city' => 'Bari']);
prova('…né crearne una nuova', (int) val('SELECT COUNT(*) FROM properties WHERE account_id = ?', [$cacc]) === 5);
$r = $carla->modulo("/pannello/$c1/procedura/anteprima", "/pannello/$c1/pubblica", []);
prova('…né pubblicare un\'archiviata', $carla->get('/g/' . val('SELECT slug FROM properties WHERE id = ?', [$c1]))['code'] === 404);
$nome3 = (string) val('SELECT name FROM properties WHERE id = ?', [$cp[2]]);
$carla->modulo("/pannello/{$cp[2]}/impostazioni", "/pannello/{$cp[2]}/elimina", ['conferma' => $nome3]);
$carla->get('/pannello');
$carla->post("/pannello/$c1/riattiva", []);
prova('Liberato un posto, l\'archiviata si riattiva e torna online', val('SELECT archived_at FROM properties WHERE id = ?', [$c1]) === null
      && $carla->get('/g/' . val('SELECT slug FROM properties WHERE id = ?', [$c1]))['code'] === 200);

// Chi ha un Portfolio 2 o 3 di prima tiene le sue strutture.
$dario = new Browser('dario');
$dario->get('/registrati?piano=' . pv('plus'));
$dario->post('/registrati', ['piano' => pv('plus'), 'name' => 'Dario Vecchio', 'email' => 'dario@prova.test', 'password' => 'DarioProva123', 'termini' => '1', 'privacy' => '1']);
$dario->get('/piano'); $dario->post('/piano', ['pv' => pv('plus')]);
$dacc = (int) val("SELECT a.id FROM accounts a JOIN users u ON u.id = a.user_id WHERE u.email = 'dario@prova.test'");
$admin->get('/admin/cliente/' . $dacc);
$admin->post("/admin/cliente/$dacc/abbonamento", ['pv' => pv('portfolio2'), 'mesi' => '12', 'nota' => 'Portfolio 2 venduto prima del cambio']);
foreach (['D1', 'D2', 'D3'] as $n) $dario->modulo('/pannello/nuova', '/pannello/nuova', ['name' => $n, 'city' => 'Lecce']);
prova('Portfolio 2 di prima: restano 2 strutture, senza quantità da cambiare', (int) val('SELECT COUNT(*) FROM properties WHERE account_id = ?', [$dacc]) === 2
      && !str_contains($dario->get('/account')['body'], '/account/strutture'));
$r = $dario->post('/account/strutture', ['strutture' => '4']);
prova('…e la modifica della quantità non si applica', $r['code'] === 302 && (int) val("SELECT COUNT(*) FROM subscriptions WHERE account_id = ? AND status = 'active'", [$dacc]) === 1);
$admin->post("/admin/cliente/$dacc/abbonamento", ['pv' => $pp, 'mesi' => '12', 'nota' => 'Portfolio a quantità', 'strutture' => '4']);
$dario->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'D3', 'city' => 'Lecce']);
$dario->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'D4', 'city' => 'Lecce']);
$dario->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'D5', 'city' => 'Lecce']);
prova('Abbonamento manuale Portfolio per 4 strutture: il limite è 4', (int) val('SELECT COUNT(*) FROM properties WHERE account_id = ?', [$dacc]) === 4
      && (int) val("SELECT quantity FROM subscriptions WHERE account_id = ? AND status = 'active'", [$dacc]) === 4);

// ==================================================================== SICUREZZA
capitolo('Sicurezza: proprietà dei dati, CSRF, amministrazione');
$bruno->get('/pannello');
foreach (["/pannello/$pid", "/pannello/$pid/sezioni/$core", "/pannello/$pid/anteprima", "/pannello/$pid/qr.png", "/pannello/$pid/lingue/en", "/pannello/$pid/procedura/checkin"] as $p) {
    $r = $bruno->get($p);
    prova("Un altro cliente non vede $p", $r['code'] === 404);
}
$r = $bruno->post("/pannello/$pid/sezioni/$core", ['checkin_note' => 'ciao']);
prova('Un altro cliente non scrive nelle sezioni altrui', $r['code'] === 404 && !str_contains((string) val('SELECT data FROM section_translations WHERE section_id = ?', [$core]), 'ciao'));
$r = $bruno->post("/pannello/$pid/pubblica", []);
prova('Un altro cliente non pubblica la guida altrui', $r['code'] === 404);
$r = $bruno->post("/pannello/$bpid/sezioni/$core/azione", ['fai' => 'elimina']);
prova('Sezione di un\'altra struttura: non trovata', (bool) val('SELECT id FROM sections WHERE id = ?', [$core]));
$r = $bruno->post("/pannello/$bpid/sezioni", ['kind' => 'info', '_csrf' => 'sbagliato']);
prova('Senza token CSRF valido: rifiutato', $r['code'] === 419 || $r['code'] === 403);
$r = $bruno->get('/admin');
prova('Un cliente non entra in amministrazione', in_array($r['code'], [302, 403, 404], true) && !str_contains($r['body'], 'Quadro.'));
$r = $ospite->get('/pannello');
prova('Senza accesso si va al login', $r['code'] === 302 && str_contains($r['loc'], '/accedi'));

$falsi = new Browser('scassinatore');
for ($i = 0; $i < 9; $i++) { $falsi->get('/accedi'); $r = $falsi->post('/accedi', ['email' => 'nessuno@prova.test', 'password' => 'sbagliata' . $i]); }
prova('Troppi tentativi di accesso: blocco', str_contains($r['body'], 'Troppi tentativi'));

$r = $ospite->modulo('/password/dimenticata', '/password/dimenticata', ['email' => 'anna@prova.test']);
prova('Recupero password: stessa risposta per tutti', $r['code'] === 200 && pulita($r));
$link = linkPosta('anna@prova.test', 'password/nuova');
prova('Link di recupero spedito', $link !== '');
$p = substr($link, strpos($link, '/password/nuova/'));
$r = $ospite->modulo($p, $p, ['password' => 'NuovaPassword1', 'password2' => 'NuovaPassword1']);
prova('Password cambiata', $r['code'] === 302);
$r = $ospite->get($p);
prova('Il link di recupero vale una volta sola', !str_contains($r['body'], 'name="password2"'));
$nuovo = new Browser('anna2');
$r = $nuovo->modulo('/accedi', '/accedi', ['email' => 'anna@prova.test', 'password' => 'NuovaPassword1']);
prova('Accesso con la password nuova', $r['code'] === 302 && str_contains($r['loc'], '/pannello'));
$r = $nuovo->post('/esci', []);
prova('Uscita', $r['code'] === 302 && $nuovo->get('/pannello')['code'] === 302);

// ================================================================ AMMINISTRAZIONE
capitolo('Amministrazione');
foreach (['/admin', '/admin/clienti', '/admin/abbonamenti', '/admin/guide', '/admin/pacchetti', '/admin/registro', '/admin/diagnostica', '/admin/cliente/' . $acc['id']] as $p) {
    $r = $admin->get($p);
    prova("$p si apre", $r['code'] === 200 && pulita($r));
}
$r = $admin->get('/admin/cliente/' . $acc['id']);
prova('Scheda cliente con gli ID Stripe', str_contains($r['body'], 'sub_prova_anna') && str_contains($r['body'], 'cus_finto'));
$r = $admin->get('/admin');
prova('L\'incasso conta solo Stripe (87 €)', str_contains($r['body'], "87\u{00A0}€"));
$admin->get('/admin/cliente/' . $acc['id']);
$admin->post('/admin/cliente/' . $acc['id'] . '/override', ['feature' => 'sections', 'valore' => '6', 'nota' => 'prova']);
prova('Eccezione sul limite delle sezioni', (int) val("SELECT o.value FROM entitlement_overrides o JOIN features f ON f.id = o.feature_id WHERE o.account_id = ? AND f.code = 'sections'", [$acc['id']]) === 6);
$admin->post('/admin/cliente/' . $acc['id'] . '/abbonamento', ['pv' => pv('plus'), 'mesi' => '2', 'nota' => 'omaggio di prova']);
prova('Abbonamento manuale registrato', (bool) val("SELECT id FROM subscriptions WHERE account_id = ? AND provider = 'manuale' AND status = 'active'", [$acc['id']]));
prova('…con voce nel registro', (bool) val("SELECT id FROM audit_log WHERE action = 'subscription.manual'"));
$admin->get('/admin/pacchetti');
$essential = (int) val("SELECT id FROM packages WHERE code = 'essential'");
$feat = []; foreach (righe('SELECT f.code, pf.value FROM package_features pf JOIN features f ON f.id = pf.feature_id WHERE pf.package_version_id = ?', [pv('essential')]) as $x) $feat[$x['code']] = $x['value'];
$vecchia = pv('essential');
$admin->post("/admin/pacchetti/$essential/nuova-versione", ['nome' => 'Essential', 'prezzo' => '97', 'stripe_price_id' => '', 'f' => $feat,
    'headline' => 'Tutto quello che serve', 'tagline' => '', 'description' => 'x', 'bullets' => "Una struttura\nCheck-in & Check-out", 'badge' => '', 'cta_label' => 'Crea gratis', 'public' => '1', 'active' => '1']);
prova('Nuovo prezzo = nuova versione', pv('essential') !== $vecchia && (int) val('SELECT price_cents FROM package_versions WHERE id = ?', [pv('essential')]) === 9700);
prova('La versione venduta resta com\'era', (int) val('SELECT price_cents FROM package_versions WHERE id = ?', [$vecchia]) === 8700);
$home = $ospite->get('/')['body'];
prova('La landing mostra il prezzo nuovo', str_contains($home, "97\u{00A0}€") && str_contains($home, "Da 97\u{00A0}€ + IVA all'anno."));
$pf = (int) val("SELECT id FROM packages WHERE code = 'portfolio'");
$featP = []; foreach (righe('SELECT f.code, pf.value FROM package_features pf JOIN features f ON f.id = pf.feature_id WHERE pf.package_version_id = ?', [pv('portfolio')]) as $x) $featP[$x['code']] = $x['value'];
$pkP = riga('SELECT * FROM packages WHERE id = ?', [$pf]);
$vecchiaP = pv('portfolio');
$admin->get('/admin/pacchetti');
$pag = $admin->get('/admin/pacchetti')['body'];
prova('Fase 1 · funzioni dei pacchetti leggibili', str_contains($pag, '<span class="etichetta">4 sezioni</span>') && str_contains($pag, '<span class="etichetta">2 lingue</span>')
      && !str_contains($pag, 'sections=') && !str_contains($pag, 'locales='));
prova('Fase 1 · Portfolio 2 e 3 in fondo, nel blocco dei nascosti', preg_match('#<details class="fieldset nascosti">.*Portfolio 2.*Portfolio 3#s', $pag) === 1
      && strpos($pag, 'nascosti') > strpos($pag, '>Portfolio<'));
prova('Pacchetti: Portfolio con prezzo per struttura aggiuntiva modificabile', str_contains($admin->get('/admin/pacchetti')['body'], 'name="prezzo_extra"'));
$admin->post("/admin/pacchetti/$pf/nuova-versione", ['nome' => $pkP['name'], 'prezzo' => '119', 'prezzo_extra' => '65', 'min_quantita' => '2', 'max_quantita' => '40', 'stripe_price_id' => '', 'stripe_extra_price_id' => '',
    'f' => $featP, 'headline' => $pkP['headline'], 'tagline' => $pkP['tagline'], 'description' => $pkP['description'], 'bullets' => $pkP['bullets'], 'badge' => $pkP['badge'], 'cta_label' => $pkP['cta_label'], 'public' => '1', 'active' => '1']);
$nuovaP = riga('SELECT * FROM package_versions WHERE id = ?', [pv('portfolio')]);
prova('Nuovi prezzi Portfolio = nuova versione per struttura', pv('portfolio') !== $vecchiaP && (int) $nuovaP['per_property'] === 1 && (int) $nuovaP['price_cents'] === 11900
      && (int) $nuovaP['extra_price_cents'] === 6500 && (int) $nuovaP['max_quantity'] === 40);
prova('…la versione venduta a Carla resta com\'era', (int) val('SELECT extra_price_cents FROM package_versions WHERE id = ?', [$vecchiaP]) === 6000
      && (int) val("SELECT package_version_id FROM subscriptions WHERE provider_subscription_id = 'sub_prova_carla'") === $vecchiaP);
$home = $ospite->get('/')['body'];
prova('La landing segue i prezzi dell\'amministrazione (119 + 65 = 184 €)', str_contains($home, 'data-base="11900"') && str_contains($home, 'data-extra="6500"') && str_contains($home, "<span data-totale>184\u{00A0}€</span>"));
$admin->post("/admin/pacchetti/$pf/nuova-versione", ['nome' => $pkP['name'], 'prezzo' => '119', 'prezzo_extra' => '65', 'min_quantita' => '2', 'max_quantita' => '40', 'stripe_price_id' => '', 'stripe_extra_price_id' => 'sbagliato', 'f' => $featP]);
prova('Price ID delle aggiuntive malformato rifiutato', !val("SELECT id FROM package_versions WHERE stripe_extra_price_id = 'sbagliato'"));
$r = $admin->post("/admin/pacchetti/$essential/nuova-versione", ['prezzo' => '97', 'stripe_price_id' => 'prezzo-sbagliato', 'f' => $feat]);
prova('Price ID Stripe malformato rifiutato', pv('essential') === (int) val('SELECT MAX(id) FROM package_versions WHERE package_id = ?', [$essential]) && !val("SELECT id FROM package_versions WHERE stripe_price_id = 'prezzo-sbagliato'"));
$uid = (int) val("SELECT id FROM users WHERE email = 'bruno@prova.test'");
$admin->get('/admin/clienti');
$r = $admin->post("/admin/entra/$uid", []);
prova('Entrare come cliente', $r['code'] === 302 && str_contains($admin->get('/pannello')['body'], 'Villa Plus'));
prova('…tracciato nel registro', (bool) val("SELECT id FROM audit_log WHERE action LIKE 'impersonate%' AND target_user_id = ?", [$uid]));
prova('…con il banner ben visibile', str_contains($admin->get('/pannello')['body'], 'account di un cliente'));
$admin->post('/admin/esci-da-cliente', []);
prova('Tornare al proprio account', $admin->get('/admin')['code'] === 200);
$r = $admin->post('/admin/entra/' . val("SELECT id FROM users WHERE role = 'admin'"), []);
prova('Non si entra come un altro amministratore', !str_contains($admin->segui($r)['body'], 'account di un cliente'));

// ======================================================================= DEMO
capitolo('Demo e guida ospite');
$demo = riga("SELECT * FROM properties WHERE is_demo = 1 AND status = 'published' ORDER BY id");
$r = $ospite->get('/g/' . $demo['slug']);
prova('La demo è online e dichiarata', $r['code'] === 200 && pulita($r) && str_contains($r['body'], 'demo-tag'));
prova('Fase 1 · senza logo la guida mostra il simbolo, non le iniziali', str_contains($r['body'], 'simbolo--ospite') && !str_contains($r['body'], 'avatar avatar--sm'));
prova('La demo non inventa statistiche', !preg_match('/\d+\s+(ospiti|aperture|recensioni)/i', $r['body']));
foreach (['en' => 'lang="en"', 'de' => 'lang="de"'] as $l => $segno) {
    $r = $ospite->get('/g/' . $demo['slug'] . '?l=' . $l);
    prova("Demo in $l", $r['code'] === 200 && str_contains($r['body'], $segno));
}
$r = $ospite->get('/g/' . $demo['slug'] . '/benvenuto');
prova('Schermata di benvenuto', $r['code'] === 200 && pulita($r));
prova('Fase 1 · splash: niente og:image né cookie, «Entra» nel terracotta del marchio', !str_contains($r['body'], 'og:image') && intestazione($r, 'Set-Cookie') === ''
      && str_contains((string) file_get_contents("$DOVE/assets/app.css"), '.full .btn{background:var(--tile-terracotta)'));
$r = $ospite->get('/g/' . $demo['slug'] . '/commiato');
prova('Schermata di commiato', $r['code'] === 200 && pulita($r));
$r = $ospite->get('/g/non-esiste');
prova('Guida inesistente: 404 pulito', $r['code'] === 404 && pulita($r));
$r = $ospite->get('/pagina/che/non/esiste');
prova('Pagina inesistente: 404 pulito', $r['code'] === 404 && pulita($r));

// ================================================================= RIEPILOGO
echo implode("\n", $esiti), "\n\n";
$tot = count(array_filter($esiti, fn($e) => !str_starts_with($e, "\n")));
echo $falliti === 0 ? "Tutte le $tot prove superate.\n" : "$falliti prove NON superate su $tot.\n";
exec('rm -rf ' . escapeshellarg($TMP));
exit($falliti === 0 ? 0 : 1);
