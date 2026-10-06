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
    // PROVE_DUMP=cartella: la pagina dell'ultima risposta ($r) di ogni prova fallita, per capire cosa è cambiato.
    if (!$ok && ($d = getenv('PROVE_DUMP'))) @file_put_contents($d . '/' . count($esiti) . '.html', "<!-- $nome -->\n" . ($GLOBALS['r']['body'] ?? ''));
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
prova('Pricing: niente "CMS", niente frasi finali', !preg_match('/>\s*CMS\s*</', $r['body'])
      && !str_contains($r['body'], 'Meno domande ripetitive, più tempo per accogliere') && !str_contains($r['body'], 'concierge digitale'));
prova('Piani: headline e descrizioni nuove', str_contains($r['body'], 'Le informazioni importanti, sempre a disposizione.') && str_contains($r['body'], 'Una guida completa, senza limiti di sezioni.')
      && str_contains($r['body'], 'Il Plus per più strutture.') && str_contains($r['body'], 'Statistiche distinte per struttura'));
$carteListino = substr($r['body'], (int) strpos($r['body'], 'class="grid grid-3 piani"'), (int) strpos($r['body'], 'class="confronto"') - (int) strpos($r['body'], 'class="grid grid-3 piani"'));
prova('Plus: foto e PDF spiegati, niente "immagine profilo" nelle card, badge sobrio', str_contains($carteListino, 'Foto e PDF nelle sezioni')
      && !str_contains($carteListino, 'Immagine profilo') && str_contains($r['body'], 'Più completo') && !str_contains($r['body'], 'Più scelto'));
prova('Nota sul rinnovo automatico sotto i piani', str_contains($r['body'], 'Puoi disattivare il rinnovo dal tuo account'));
prova('Il prodotto si vede subito: telefono nella hero, prima della foto', strpos($r['body'], 'class="device"') !== false && strpos($r['body'], 'class="device"') < strpos($r['body'], 'class="scene"'));
prova('Il telefono è un link alla demo, con invito', preg_match('#<a class="device-link" href="([^"]+)"#', $r['body'], $mm) === 1 && str_ends_with($mm[1], '/benvenuto') && str_contains($r['body'], 'Scopri come la vedranno i tuoi ospiti'));
prova('Demo, telefono e QR portano alla stessa demo', isset($mm[1]) && substr_count($r['body'], 'href="' . $mm[1] . '"') >= 2 && str_contains($r['body'], 'Inquadra e prova la demo.'));
prova('Foto del borgo nel telefono, anche in WebP', str_contains($r['body'], '/assets/foto/borgo-telefono.jpg') && str_contains($r['body'], 'borgo-telefono-600.webp'));
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
      && substr_count($tempo, 'class="vantaggio"') >= 2 && str_contains($tempo, 'Meno dubbi all') && str_contains($tempo, 'Un piccolo investimento annuale'));
prova('…il prezzo di partenza viene dal listino', str_contains($tempo, "Da 87\u{00A0}€ + IVA all'anno"));
prova('…niente tono difensivo', !str_contains($r['body'], 'Non paghi una pagina con un QR'));
prova('…senza numeri di risparmio inventati', !preg_match('/\d+\s*(%|ore|messaggi in meno)|mai più|elimin/i', strip_tags($tempo)));
prova('CTA della hero verso la registrazione e verso la demo', preg_match('#<section class="hero2">.*?</section>#s', $r['body'], $mh) && str_contains($mh[0], '/registrati"') && str_contains($mh[0], 'Guarda la demo'));
prova('Fase 1 · piè di pagina con P.IVA, telefono e WhatsApp', str_contains($r['body'], 'P.IVA 02945910541') && str_contains($r['body'], 'href="tel:+393920061600"')
      && str_contains($r['body'], 'href="https://wa.me/393920061600"') && str_contains($r['body'], 'un progetto Blackout Agency')
      && str_contains($r['body'], 'Via Ariodante Fabretti 17, Perugia') && str_contains($r['body'], 'href="mailto:info@myhousewelcome.it"'));
prova('Fase 1 · favicon, icona Home e anteprima di condivisione', str_contains($r['body'], '/assets/favicon.svg') && str_contains($r['body'], '/assets/apple-touch-icon.png')
      && preg_match('#<meta property="og:image" content="https?://[^"]+/assets/og\.jpg">#', $r['body']) === 1);
foreach (['favicon.svg', 'apple-touch-icon.png', 'og.jpg'] as $f) prova("Fase 1 · $f presente", is_file("$DOVE/assets/$f") && filesize("$DOVE/assets/$f") > 300);
prova('Fase 1 · header del telefono: CTA e menu con tutte le voci', preg_match('#<div class="topbar__telefono">.*?</header>#s', $r['body'], $mt) === 1
      && str_contains($mt[0], 'Crea gratis') && str_contains($mt[0], 'Come funziona') && str_contains($mt[0], 'Prezzi') && str_contains($mt[0], 'Il QR') && str_contains($mt[0], 'Accedi'));
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
prova('Fase 2 · in alto il piano scelto, con «cambia»', str_contains($r['body'], 'class="chip-piano"') && str_contains($r['body'], 'Piano <b>Essential</b>')
      && str_contains($r['body'], "87\u{00A0}€ + IVA/anno") && str_contains($r['body'], '>Cambia</a>'));
prova('Fase 2 · «Nome e cognome», password con «Mostra» e barra di robustezza', str_contains($r['body'], 'Nome e cognome') && str_contains($r['body'], 'data-mostra-pw')
      && str_contains($r['body'], 'data-forza'));
prova('Fase 2 · una sola casella (Termini), la privacy è una riga sotto il bottone', substr_count($r['body'], 'type="checkbox"') === 1 && !str_contains($r['body'], 'name="privacy"')
      && str_contains($r['body'], "Creando l'account dichiari di aver letto l'") && strpos($r['body'], 'informativa privacy') > strpos($r['body'], 'Crea l\'account e inizia'));
$elena = new Browser('elena');
$r = $elena->get('/registrati');
prova('Fase 2 · «Crea gratis» generico: nessun piano in alto', !str_contains($r['body'], 'chip-piano'));
$r = $elena->post('/registrati', ['name' => 'Elena Senza Piano', 'email' => 'elena@prova.test', 'password' => 'ElenaProva123', 'termini' => '1']);
prova('Fase 2 · …e dopo la registrazione si passa da /piano', $r['code'] === 302 && str_ends_with($r['loc'], '/piano'), $r['loc']);
$r = $elena->get('/piano');
prova('Fase 2 · /piano: card intere cliccabili, senza la riga che ripete nome e prezzo', substr_count($r['body'], 'class="pianocard__velo"') >= 3
      && !str_contains($r['body'], 'class="swatch"') && str_contains($r['body'], 'data-sceglie=')
      && preg_match('#<div class="plan pianocard[^"]*">(?:(?!<div class="plan pianocard).)*?name="strutture"#s', $r['body']) === 1);
$r = $elena->post('/piano', ['pv' => pv('plus')]);
prova('Fase 2 · scelto il piano, alla struttura', $r['code'] === 302 && str_contains($r['loc'], '/pannello/nuova'));
$elena->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Casa Elena', 'city' => 'Todi']);
$epid = (int) val("SELECT p.id FROM properties p JOIN accounts a ON a.id = p.account_id JOIN users u ON u.id = a.user_id WHERE u.email = 'elena@prova.test'");
prova('Fase 2 · nuova struttura: si riprende da «Arrivo e partenza»', val('SELECT wizard_step FROM properties WHERE id = ?', [$epid]) === 'arrivo');
$r = $elena->modulo("/pannello/$epid/procedura/struttura", "/pannello/$epid/impostazioni", ['name' => 'Casa Elena', 'default_locale' => 'en'], ['Accept: application/json']);
prova('Fase 2 · la lingua principale si salva (anche col salvataggio automatico)', ($r['code'] === 200) && val('SELECT default_locale FROM properties WHERE id = ?', [$epid]) === 'en'
      && (bool) val("SELECT 1 FROM property_locales WHERE property_id = ? AND locale = 'en'", [$epid]) && (bool) val("SELECT 1 FROM property_locales WHERE property_id = ? AND locale = 'it'", [$epid]));
$r = $elena->post("/pannello/$epid/impostazioni", ['name' => 'Casa Elena', 'default_locale' => 'xx'], ['Accept: application/json']);
prova('…una lingua fuori piano no', $r['code'] === 422 && val('SELECT default_locale FROM properties WHERE id = ?', [$epid]) === 'en');
$r = $anna->post('/registrati', ['piano' => pv('essential'), 'name' => 'Anna Prova', 'email' => 'anna@prova.test', 'password' => 'AnnaProva123']);
prova('Senza Termini non si entra', $r['code'] === 200 && str_contains($r['body'], 'accettare i Termini'));
prova('Senza Termini nessun utente creato', !val("SELECT id FROM users WHERE email = 'anna@prova.test'"));
$r = $anna->post('/registrati', ['piano' => pv('essential'), 'name' => 'Anna Prova', 'email' => 'anna@prova.test',
                                  'password' => 'AnnaProva123', 'termini' => '1', 'privacy' => '1']);
prova('Fase 2 · registrazione con il piano → dritti al nome della struttura', $r['code'] === 302 && str_contains($r['loc'], '/pannello/nuova'), $r['loc']);
prova('…con il piano già salvato', (int) val("SELECT a.intended_package_version_id FROM accounts a JOIN users u ON u.id = a.user_id WHERE u.email = 'anna@prova.test'") === pv('essential'));
$r = $anna->get('/pannello/nuova');
prova('Fase 2 · «Piano Essential scelto · non paghi adesso · Cambia»', str_contains($r['body'], 'Piano <b>Essential</b>') && str_contains($r['body'], 'scelto · non paghi adesso')
      && preg_match('#href="[^"]*/piano">Cambia</a>#', $r['body']) === 1 && str_contains($r['body'], 'Passo 1 di 5'));
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
foreach (['struttura' => 'Struttura e contatti', 'arrivo' => 'Check-in &amp; Check-out', 'sezioni' => 'Sezioni', 'aspetto' => 'Aspetto', 'pubblica' => 'Pubblica'] as $passo => $nomePasso) {
    $r = $anna->get("/pannello/$pid/procedura/$passo");
    prova("Passo \"$passo\" si apre", $r['code'] === 200 && pulita($r) && str_contains($r['body'], $nomePasso) && str_contains($r['body'], 'di 5'));
}
foreach (['checkin' => 'arrivo', 'contenuti' => 'sezioni', 'lingue' => 'aspetto', 'anteprima' => 'pubblica'] as $vecchio => $nuovo) {
    $r = $anna->get("/pannello/$pid/procedura/$vecchio");
    prova("Fase 2 · vecchio indirizzo «{$vecchio}» → 301 a «{$nuovo}»", $r['code'] === 301 && str_ends_with($r['loc'], "/procedura/$nuovo"), $r['code'] . ' ' . $r['loc']);
}
$r = $anna->get("/pannello/$pid/procedura/struttura");
prova('Fase 2 · procedura: una sola navigazione (niente tab), «Continua dopo»', !str_contains($r['body'], 'aria-label="La guida"') && str_contains($r['body'], '>Continua dopo</a>')
      && str_contains($r['body'], 'class="verifica-riga"') && !str_contains($r['body'], 'banner banner--info'));
prova('Fase 2 · «In che lingua scrivi la guida?» con l\'italiano scelto', str_contains($r['body'], 'In che lingua scrivi la guida?')
      && preg_match('#name="default_locale" value="it" checked#', $r['body']) === 1);
prova('Fase 2 · fuori dalla procedura le tab restano', str_contains($anna->get("/pannello/$pid/lingue")['body'], 'aria-label="La guida"'));
$r = $anna->modulo("/pannello/$pid/procedura/struttura", "/pannello/$pid/impostazioni",
    ['name' => 'Casa Prova', 'city' => 'Lecce', 'region' => 'Puglia', 'checkin_from' => '15:00', 'checkout_by' => '10:00',
     'host_name' => 'Anna', 'host_phone' => '+39 333 1234567', 'host_whatsapp' => '+39 333 1234567', 'dopo' => 'arrivo']);
prova('Salva e continua porta al passo dopo', $r['code'] === 302 && str_contains($r['loc'], '/procedura/arrivo'));
$core = (int) val('SELECT id FROM sections WHERE property_id = ? AND is_core = 1', [$pid]);
$r = $anna->modulo("/pannello/$pid/procedura/arrivo", "/pannello/$pid/sezioni/$core",
    ['checkin_steps' => ['Il portone è quello verde.', 'Le chiavi te le consegno io.', ''], 'checkin_note' => 'Se arrivi tardi, scrivimi.',
     'checkout_steps' => ['Lascia le chiavi sul tavolo.', 'Umido nel bidone marrone.'], 'checkout_notes' => 'Buon viaggio!',
     'arrival_mode' => 'self', 'late_arrival' => 'Dopo le 21 scrivimi prima di partire.', 'documents' => 'Un documento per ogni ospite.',
     'tax_amount' => '2,00 €', 'tax_max_nights' => '5', 'tax_notes' => 'Sotto i 14 anni esenti. In contanti all\'arrivo.',
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
    if ($k === 'wifi') {
        prova('Fase 2 · «Aggiungi» apre subito l\'editor sotto la card', $r['code'] === 302 && str_contains($r['loc'], "/procedura/sezioni?apri={$ids['wifi']}#sez-{$ids['wifi']}"), $r['loc']);
        $ed = $anna->get("/pannello/$pid/procedura/sezioni?apri={$ids['wifi']}");
        prova('…con lo stesso modulo della sezione, nella pagina della procedura', pulita($ed) && str_contains($ed['body'], 'class="riga-editor"')
              && str_contains($ed['body'], 'Nome della rete') && str_contains($ed['body'], 'name="da" value="procedura"') && str_contains($ed['body'], 'Passo 3 di 5'));
        $r = $anna->post("/pannello/$pid/sezioni/{$ids['wifi']}", ['da' => 'procedura', 'network' => 'Prima_Rete', 'azione' => 'salva']);
        prova('…e salvando si resta nella procedura, sulla stessa sezione', $r['code'] === 302 && str_contains($r['loc'], "/procedura/sezioni?apri={$ids['wifi']}"));
    }
}
$r = $anna->post("/pannello/$pid/sezioni", ['kind' => 'transport', 'torna' => 'procedura']);
$r = $anna->segui($r);
prova('La quinta sezione è rifiutata dal server', !val('SELECT id FROM sections WHERE property_id = ? AND kind = ?', [$pid, 'transport']));
prova('Messaggio di limite elegante', str_contains($r['body'], 'Hai già 4 sezioni attive, il massimo del tuo piano') && str_contains($r['body'], 'Scopri Plus'));
prova('Il contatore dice 4 su 4', str_contains($r['body'], '4 su 4 sezioni attive'));
prova('Ordinamento: maniglia e menu ⋯ con Sposta su / giù', str_contains($r['body'], 'riga__maniglia') && str_contains($r['body'], 'Sposta su') && str_contains($r['body'], 'Sposta giù'));
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
    ['title' => 'Wi-Fi', 'networks' => [['id' => '', 'zone' => 'Casa principale', 'ssid' => 'CasaProva_5G', 'password' => 'mare;2026'],
                                        ['id' => '', 'zone' => 'Dependance', 'ssid' => 'Giardino', 'password' => 'fiori,2026'],
                                        ['id' => '', 'zone' => '', 'ssid' => '', 'password' => '']],
     'instructions' => 'Riavvia il router se serve.', 'router_location' => 'In salotto.']);
$reti = json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$ids['wifi']]), true)['networks'] ?? [];
prova('Wi-Fi salvato: due reti, la riga vuota scartata', $r['code'] === 302 && count($reti) === 2 && $reti[0]['ssid'] === 'CasaProva_5G'
      && preg_match('/^r[0-9a-f]{8}$/', $reti[0]['id']) === 1 && !isset($reti[0]['zone']));
$zone = json_decode((string) val("SELECT data FROM section_translations WHERE section_id = ? AND locale = 'it'", [$ids['wifi']]), true)['networks'] ?? [];
prova('Fase 3 · repeater: la zona (testo) sta nelle traduzioni, unita per id', ($zone[1]['id'] ?? '') === $reti[1]['id'] && ($zone[1]['zone'] ?? '') === 'Dependance');
$r = $anna->get("/pannello/$pid/sezioni/{$ids['wifi']}");
prova('Editor strutturato (niente mini-sintassi)', pulita($r) && str_contains($r['body'], 'Nome della rete') && !str_contains($r['body'], 'riga vuota'));
prova('Essential: niente caricamento immagini nelle sezioni', !str_contains($r['body'], 'name="foto"'));
$r = $anna->post("/pannello/$pid/sezioni/{$ids['wifi']}", ['network' => 'CasaProva_5G', 'foto' => file_(png(), 'image/png')]);
prova('Essential: immagine rifiutata anche forzando il modulo', str_contains($r['body'], 'disponibili con il piano Plus') && !val('SELECT media_id FROM sections WHERE id = ?', [$ids['wifi']]));
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
$anna->post("/pannello/$pid/lingue/en", ['s' => [$core => ['checkin_note' => 'If you arrive late, text me.']]]);
$r = $anna->get("/pannello/$pid/anteprima/$core?l=en");
prova('Fase 2 · campo non tradotto: l\'ospite legge la lingua principale, campo per campo', str_contains($r['body'], 'If you arrive late, text me.')
      && str_contains($r['body'], 'Il portone è quello verde.'));
$r = $anna->get("/pannello/$pid/lingue");
prova('Fase 2 · Lingue: percentuale per lingua e campi da tradurre', preg_match('#English <span class="perc">(\d+)%</span>#', $r['body'], $mp) === 1 && (int) $mp[1] > 0 && (int) $mp[1] < 100
      && str_contains($r['body'], 'da tradurre'), $mp[1] ?? '');
$r = $anna->get("/pannello/$pid/lingue/en");
prova('Fase 2 · traduzione: i campi mancanti sono evidenziati', str_contains($r['body'], 'da-tradurre') && str_contains($r['body'], 'Da tradurre'));
$r = $anna->get("/pannello/$pid/procedura/pubblica");
prova('Fase 2 · «Vuoi la guida anche in altre lingue?» in fondo, facoltativo', str_contains($r['body'], 'Vuoi la guida anche in altre lingue?') && str_contains($r['body'], 'Facoltativo')
      && preg_match('#name="locali\[\]" value="fr"[^>]*disabled#', $r['body']) === 1);
prova('Fase 2 · le traduzioni a metà non bloccano la pubblicazione', !str_contains($r['body'], 'Prima di pubblicare') || !preg_match('/tradu/i', (string) (preg_match('#Prima di pubblicare</b>(.*?)</div>#s', $r['body'], $mm) ? $mm[1] : '')));

// ================================================== FASE 3 · BLOCCO A
capitolo('Fase 3 · struttura, contatti, arrivo e partenza, Wi-Fi');
$r = $anna->modulo("/pannello/$pid/procedura/struttura", "/pannello/$pid/impostazioni",
    ['name' => 'Casa Prova', 'city' => 'Lecce', 'region' => 'Puglia', 'checkin_from' => '15:00', 'checkout_by' => '10:00',
     'property_type' => 'bnb', 'address' => 'Via San Francesco 12', 'postal_code' => '73100', 'cin' => 'IT075039C2XXXXXXXX', 'beds' => '4',
     'contacts' => [['id' => '', 'name' => 'Anna Prova', 'role' => 'host', 'phone' => '+39 333 1234567', 'whatsapp' => '1'],
                    ['id' => '', 'name' => 'Marco', 'role' => 'pulizie', 'phone' => '+39 347 9876543'],
                    ['id' => '', 'name' => '', 'role' => 'host', 'phone' => '']]]);
$pr = riga('SELECT * FROM properties WHERE id = ?', [$pid]);
prova('R2 · tipologia, indirizzo, CAP, CIN e posti letto salvati', $pr['property_type'] === 'bnb' && $pr['address'] === 'Via San Francesco 12' && $pr['postal_code'] === '73100'
      && $pr['cin'] === 'IT075039C2XXXXXXXX' && (int) $pr['beds'] === 4);
$cc = righe('SELECT name, role, phone, whatsapp FROM property_contacts WHERE property_id = ? ORDER BY position', [$pid]);
prova('R2 · due contatti, nell\'ordine, la riga vuota scartata', count($cc) === 2 && $cc[0]['name'] === 'Anna Prova' && (int) $cc[0]['whatsapp'] === 1 && $cc[1]['role'] === 'pulizie' && (int) $cc[1]['whatsapp'] === 0);
prova('R2 · il primo contatto resta anche nelle colonne di prima', $pr['host_name'] === 'Anna Prova' && $pr['host_whatsapp'] === '+39 333 1234567');
$r = $anna->get("/pannello/$pid/procedura/struttura");
prova('R2 · modulo: contatti come righe ripetibili, con Sposta su/giù', str_contains($r['body'], 'Chi risponde agli ospiti') && str_contains($r['body'], 'data-rip-su')
      && str_contains($r['body'], 'data-rip-modello') && str_contains($r['body'], 'value="Marco"'));
$r = $anna->get("/pannello/$pid/anteprima");
prova('R2 · guida: «Contatta Anna» apre il foglio con tutti i contatti', str_contains($r['body'], 'Contatta Anna') && str_contains($r['body'], 'class="contatti-foglio"')
      && str_contains($r['body'], 'Marco') && str_contains($r['body'], 'Pulizie e chiavi') && substr_count($r['body'], 'https://wa.me/') === 1
      && str_contains($r['body'], 'href="tel:+393479876543"'));
prova('R2 · CIN in piccolo nel piè di pagina', str_contains($r['body'], 'CIN IT075039C2XXXXXXXX'));
prova('R2 · in inglese «Contact Anna» e i ruoli tradotti', str_contains($anna->get("/pannello/$pid/anteprima?l=en")['body'], 'Contact Anna'));
// L'indirizzo precompila «Come arrivare» (sulla struttura di Elena, piano Plus).
$elena->modulo("/pannello/$epid/impostazioni", "/pannello/$epid/impostazioni", ['name' => 'Casa Elena', 'address' => 'Via Roma 1', 'postal_code' => '06059', 'city' => 'Todi']);
$elena->get("/pannello/$epid/procedura/sezioni");
$elena->post("/pannello/$epid/sezioni", ['kind' => 'arrival', 'torna' => 'procedura']);
$arr = json_decode((string) val("SELECT data FROM sections WHERE property_id = ? AND kind = 'arrival'", [$epid]), true);
prova('R2 · l\'indirizzo della struttura precompila «Come arrivare»', ($arr['address'] ?? '') === 'Via Roma 1, 06059 Todi', json_encode($arr));

// Arrivo e partenza
$r = $anna->get("/pannello/$pid/anteprima/$core");
prova('R3 · guida: modalità, arrivo tardivo, documenti', str_contains($r['body'], 'Self check-in') && str_contains($r['body'], 'Arrivo tardivo')
      && str_contains($r['body'], 'Dopo le 21 scrivimi') && str_contains($r['body'], 'Documenti da mostrare'));
prova('R3 · imposta di soggiorno: importo, notti, esenzioni', str_contains($r['body'], "2,00 € a notte") && str_contains($r['body'], 'Per un massimo di 5 notti.')
      && str_contains($r['body'], 'Sotto i 14 anni esenti'));
prova('R3 · partenza come lista', str_contains($r['body'], '<li>Lascia le chiavi sul tavolo.</li>'));
$r = $anna->get("/pannello/$pid/anteprima/$core?l=en");
prova('R3 · in inglese le etichette sono tradotte', str_contains($r['body'], 'Tourist tax') && str_contains($r['body'], '2,00 € per night') && str_contains($r['body'], 'Late arrival'));
$r = $anna->get("/pannello/$pid/procedura/arrivo");
prova('R3 · editor: suggerimenti a un tocco per «Prima di partire»', str_contains($r['body'], 'data-suggerisci=') && str_contains($r['body'], '+ Lavastoviglie')
      && str_contains($r['body'], 'data-testo="Avvia la lavastoviglie."') && str_contains($r['body'], 'name="arrival_mode" value="self" checked'));

// Wi-Fi: più reti, QR, riordino con le traduzioni al loro posto
$r = $anna->get("/pannello/$pid/anteprima/{$ids['wifi']}");
prova('R4 · due reti, ognuna con zona, password da copiare e QR', str_contains($r['body'], 'Casa principale') && str_contains($r['body'], 'Dependance')
      && substr_count($r['body'], 'src="data:image/png;base64,') === 2 && str_contains($r['body'], 'data-copia-di="mare;2026"'));
prova('R4 · la stringa del QR ha i caratteri speciali protetti', str_contains((string) shell_exec('php -r ' . escapeshellarg('require "' . $DOVE . '/app/src/Conversione.php"; echo MHW\Conversione::wifiQr("Casa;5G", "a,b\\\\c");')), 'WIFI:T:WPA;S:Casa\;5G;P:a\,b\\\\c;;'));
$anna->post("/pannello/$pid/lingue/en", ['s' => [$ids['wifi'] => ['networks' => [['id' => $reti[0]['id'], 'zone' => 'Main house'], ['id' => $reti[1]['id'], 'zone' => 'Annex']]]]]);
$anna->post("/pannello/$pid/sezioni/{$ids['wifi']}", ['networks' => [
    ['id' => $reti[1]['id'], 'zone' => 'Dependance', 'ssid' => 'Giardino', 'password' => 'fiori,2026'],
    ['id' => $reti[0]['id'], 'zone' => 'Casa principale', 'ssid' => 'CasaProva_5G', 'password' => 'mare;2026']]]);
$r = $anna->get("/pannello/$pid/anteprima/{$ids['wifi']}?l=en");
prova('R4 · riordinate le reti, le traduzioni restano sulla loro riga', strpos($r['body'], 'Annex') !== false && strpos($r['body'], 'Annex') < strpos($r['body'], 'Main house')
      && strpos($r['body'], 'Giardino') < strpos($r['body'], 'CasaProva_5G'));
prova('R4 · la percentuale di traduzione conta le zone', str_contains($anna->get("/pannello/$pid/lingue")['body'], 'English <span class="perc">'));
$anna->post("/pannello/$pid/sezioni/{$ids['wifi']}", ['networks' => [['id' => $reti[0]['id'], 'zone' => 'Casa principale', 'ssid' => 'CasaProva_5G', 'password' => 'mare;2026']]]);
$r = $anna->get("/pannello/$pid/anteprima/{$ids['wifi']}?l=en");
prova('R4 · tolta una rete, sparisce anche dalla traduzione', !str_contains($r['body'], 'Annex') && str_contains($r['body'], 'Main house') && substr_count($r['body'], 'data:image/png') === 1);

// ============================================== FASE 3 · BLOCCO B
capitolo('Fase 3 · sezioni strutturate, scheda luogo da Maps');
// Regole (Anna, Essential): interruttori, orari del silenzio, regole aggiuntive.
$r = $anna->get("/pannello/$pid/sezioni/{$ids['rules']}");
prova('R5 · regole: per ogni regola Ammesso / Non ammesso / Non indicato, con legenda', pulita($r) && substr_count($r['body'], 'class="interruttore"') === 4
      && str_contains($r['body'], '<legend class="interruttore__nome">Fumo</legend>') && str_contains($r['body'], 'name="flags[pets]" value="" checked')
      && str_contains($r['body'], 'type="time"'));
$anna->post("/pannello/$pid/sezioni/{$ids['rules']}", ['flags' => ['smoking' => 'no', 'pets' => 'si', 'parties' => '', 'visitors' => 'forse', 'altro' => 'si'],
    'quiet_from' => '22:00', 'quiet_to' => '8.00', 'items' => ['Niente scarpe in casa.']]);
$rd = json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$ids['rules']]), true);
prova('R5 · salvate solo le regole note, con sì o no; orari normalizzati', ($rd['flags'] ?? null) === ['smoking' => 'no', 'pets' => 'si']
      && $rd['quiet_from'] === '22:00' && $rd['quiet_to'] === '08:00', json_encode($rd));
$r = $anna->get("/pannello/$pid/anteprima/{$ids['rules']}");
prova('R5 · guida: Vietato fumare, Animali ammessi, silenzio, «Altre regole»', str_contains($r['body'], 'Vietato fumare') && str_contains($r['body'], 'Animali ammessi')
      && !str_contains($r['body'], 'Niente feste') && str_contains($r['body'], 'Silenzio dalle 22:00 alle 08:00') && str_contains($r['body'], 'Altre regole')
      && str_contains($r['body'], 'Niente scarpe in casa.'));
prova('R5 · …e in inglese', str_contains($anna->get("/pannello/$pid/anteprima/{$ids['rules']}?l=en")['body'], 'No smoking'));

// Parcheggio (Anna): più possibilità e ZTL; le foto nelle righe non sono nel piano Essential.
$r = $anna->get("/pannello/$pid/sezioni/{$ids['parking']}");
prova('R5 · parcheggio: righe ripetibili, senza caricamento foto (Essential)', str_contains($r['body'], 'Aggiungi un parcheggio') && !str_contains($r['body'], 'name="rip_file[options]')
      && str_contains($r['body'], 'ZTL'));
$r = $anna->post("/pannello/$pid/sezioni/{$ids['parking']}", ['options[0][id]' => '', 'options[0][type]' => 'privato', 'options[0][name]' => 'Posto in cortile',
    'options[0][address]' => 'Via San Francesco 12', 'options[0][photo]' => '', 'rip_file[options][0][photo]' => file_(png(), 'image/png'),
    'ztl' => 'Il centro è ZTL dalle 8 alle 20.']);
prova('R5 · foto in una riga rifiutata dal server senza il piano Plus', pulita($r) && str_contains($r['body'], 'disponibili con il piano Plus')
      && (int) val("SELECT COUNT(*) FROM media WHERE account_id = ?", [$acc['id']]) === 0);
$fotoAltrui = (int) val('SELECT media_id FROM sections WHERE media_id IS NOT NULL ORDER BY id LIMIT 1');
$anna->post("/pannello/$pid/sezioni/{$ids['parking']}", ['options' => [
    ['id' => '', 'type' => 'privato', 'name' => 'Posto in cortile', 'address' => 'Via San Francesco 12', 'photo' => (string) $fotoAltrui, 'cost_day' => '9'],
    ['id' => '', 'type' => 'pagamento', 'name' => 'Parcheggio del porto', 'cost_hour' => '1', 'cost_day' => '€ 8.5', 'walk_minutes' => '6 min', 'instructions' => 'Strisce blu.'],
    ['id' => '', 'type' => '', 'name' => '', 'address' => '']], 'ztl' => 'Il centro è ZTL dalle 8 alle 20.']);
$pk = json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$ids['parking']]), true);
prova('R5 · due parcheggi (la riga vuota scartata); la foto di un altro account non si aggancia', count($pk['options'] ?? []) === 2 && ($pk['options'][0]['photo'] ?? 'x') === ''
      && $pk['options'][1]['type'] === 'pagamento', json_encode($pk));
prova('6C · parcheggio: importi con la virgola, minuti solo cifre; i costi di un posto privato si svuotano',
      ($pk['options'][1]['cost_hour'] ?? '') === '1' && ($pk['options'][1]['cost_day'] ?? '') === '8,50' && ($pk['options'][1]['walk_minutes'] ?? '') === '6'
      && ($pk['options'][0]['cost_day'] ?? 'x') === '', json_encode($pk));
// Il parcheggio di Anna è disattivato (limite di Essential): per vederlo si scambia per un momento con «Dove mangiare».
$anna->post("/pannello/$pid/sezioni/{$ids['eat']}/azione", ['fai' => 'disattiva']);
$anna->post("/pannello/$pid/sezioni/{$ids['parking']}/azione", ['fai' => 'attiva']);
$r = $anna->get("/pannello/$pid/anteprima/{$ids['parking']}");
$anna->post("/pannello/$pid/sezioni/{$ids['parking']}/azione", ['fai' => 'disattiva']);
$anna->post("/pannello/$pid/sezioni/{$ids['eat']}/azione", ['fai' => 'attiva']);
prova('R5 · guida: tipo, nome, costo, istruzioni, Maps e ZTL', str_contains($r['body'], 'Posto privato') && str_contains($r['body'], 'Parcheggio a pagamento')
      && str_contains($r['body'], 'Parcheggio del porto') && str_contains($r['body'], '1 €/ora') && str_contains($r['body'], '8,50 €/giorno')
      && str_contains($r['body'], '6 min a piedi') && str_contains($r['body'], '>Gratuito<') && str_contains($r['body'], 'ZTL — zona a traffico limitato')
      && str_contains($r['body'], 'query=Via+San+Francesco+12') === false && str_contains($r['body'], 'Via%20San%20Francesco%2012'));

// Elena (Plus): servizi con foto e PDF nelle istruzioni, rifiuti, emergenze, come arrivare, luoghi da Maps.
$eacc = (int) val("SELECT a.id FROM accounts a JOIN users u ON u.id = a.user_id WHERE u.email = 'elena@prova.test'");
foreach (['services', 'waste', 'emergency', 'eat'] as $k) $elena->post("/pannello/$epid/sezioni", ['kind' => $k]);
// La guida di Elena è in inglese (lingua principale); le prove leggono anche italiano, tedesco e francese.
$elena->modulo("/pannello/$epid/lingue", "/pannello/$epid/lingue", ['locali' => ['en', 'it', 'de', 'fr']]);
$es = []; foreach (['services', 'waste', 'emergency', 'eat', 'arrival'] as $k) $es[$k] = (int) val('SELECT id FROM sections WHERE property_id = ? AND kind = ?', [$epid, $k]);
$r = $elena->get("/pannello/$epid/sezioni/{$es['services']}");
prova('R5 · servizi: le dotazioni da spuntare (35, a gruppi, dalla fase 6) e istruzioni con foto e PDF per riga', substr_count($r['body'], 'name="amenities[]" value="') === 36
      && str_contains($r['body'], '<p class="dotazioni__gruppo">Cucina</p>')
      && str_contains($r['body'], 'name="rip_file[manuals][0][photo]"') && str_contains($r['body'], 'name="rip_file[manuals][0][pdf]"')
      && str_contains($r['body'], 'name="rip_file[manuals][__K__][photo]"'));
$elena->post("/pannello/$epid/sezioni/{$es['services']}", ['amenities[0]' => '', 'amenities[1]' => 'washer', 'amenities[2]' => 'ac', 'amenities[3]' => 'jacuzzi',
    'manuals[0][id]' => '', 'manuals[0][title]' => 'La caldaia', 'manuals[0][steps]' => "Apri lo sportello.\nPremi il tasto rosso.", 'manuals[0][photo]' => '', 'manuals[0][pdf]' => '',
    'rip_file[manuals][0][photo]' => file_(png(), 'image/png'), 'rip_file[manuals][0][pdf]' => file_(pdfVero(), 'application/pdf', 'caldaia.pdf')]);
$sv = json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$es['services']]), true);
$man = $sv['manuals'][0] ?? [];
prova('R5 · dotazioni note salvate (nell\'ordine dei gruppi); foto e PDF caricati nella riga', ($sv['amenities'] ?? null) === ['ac', 'washer'] && (int) ($man['photo'] ?? 0) > 0 && (int) ($man['pdf'] ?? 0) > 0
      && val('SELECT kind FROM media WHERE id = ?', [(int) $man['photo']]) === 'image' && val('SELECT kind FROM media WHERE id = ?', [(int) $man['pdf']]) === 'pdf', json_encode($sv));
$r = $elena->get("/pannello/$epid/anteprima/{$es['services']}?l=it");
prova('R5 · guida: griglia delle dotazioni con icone, istruzione con passi numerati, foto e PDF', str_contains($r['body'], 'class="dotazioni-ospite"')
      && str_contains($r['body'], 'Lavatrice') && str_contains($r['body'], 'Aria condizionata') && str_contains($r['body'], 'La caldaia')
      && str_contains($r['body'], '<span class="n">2</span><p>Premi il tasto rosso.</p>') && str_contains($r['body'], 'Apri il PDF'));
prova('R5 · …in tedesco le dotazioni tradotte', str_contains($elena->get("/pannello/$epid/anteprima/{$es['services']}?l=de")['body'], 'Waschmaschine'));
$r = $elena->get("/pannello/$epid/sezioni/{$es['services']}");
prova('R5 · editor: la foto salvata si vede, con «Togli» e «Sostituisci»', str_contains($r['body'], 'class="rip__file"') && str_contains($r['body'], 'name="rip_togli[manuals][0][photo]"'));
$fotoMan = (int) $man['photo']; $pdfMan = (int) $man['pdf'];
$elena->post("/pannello/$epid/sezioni/{$es['services']}", ['amenities' => [''], 'manuals' => [['id' => $man['id'], 'title' => 'La caldaia', 'steps' => 'Apri.', 'photo' => (string) $fotoMan, 'pdf' => (string) $pdfMan]],
    'rip_togli' => ['manuals' => [0 => ['photo' => '1']]]]);
$sv = json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$es['services']]), true);
prova('R5 · «Togli» la foto: il file si cancella, il PDF resta; tolte tutte le spunte', ($sv['amenities'] ?? null) === [] && ($sv['manuals'][0]['photo'] ?? 'x') === ''
      && !val('SELECT id FROM media WHERE id = ?', [$fotoMan]) && val('SELECT id FROM media WHERE id = ?', [$pdfMan]));
$elena->post("/pannello/$epid/sezioni/{$es['services']}", ['manuals' => [['id' => '', 'title' => '', 'steps' => '', 'photo' => '', 'pdf' => '']]]);
prova('R5 · tolta la riga, anche il suo PDF si cancella', !val('SELECT id FROM media WHERE id = ?', [$pdfMan]));

// Rifiuti: «Oggi si butta» secondo il giorno di oggi in Italia.
$oggi = (int) (new DateTimeImmutable('now', new DateTimeZone('Europe/Rome')))->format('N');
$domani = $oggi % 7 + 1;
$elena->post("/pannello/$epid/sezioni/{$es['waste']}", ['bins' => [
    ['id' => '', 'type' => 'umido', 'days' => [(string) $oggi], 'color' => 'marrone', 'label' => '', 'where' => 'In cortile'],
    ['id' => '', 'type' => 'altro', 'days' => [(string) $domani, '9'], 'color' => '', 'label' => 'Olio esausto', 'where' => ''],
    ['id' => '', 'type' => 'altro', 'color' => '', 'label' => '', 'where' => '']]]);
$wd = json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$es['waste']]), true);
prova('R5 · rifiuti: due righe; giorni validi; la riga lasciata su «Altro» senza altro scartata', count($wd['bins'] ?? []) === 2 && $wd['bins'][1]['days'] === [$domani], json_encode($wd));
$r = $elena->get("/pannello/$epid/anteprima/{$es['waste']}?l=it");
$nomeGiorno = ['', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato', 'domenica'];
prova('R5 · guida: «Oggi si butta: Umido», giorni per esteso, colore con etichetta', str_contains($r['body'], 'Oggi si butta: Umido')
      && str_contains($r['body'], $nomeGiorno[$domani]) && str_contains($r['body'], 'aria-label="Colore del bidone: marrone"') && str_contains($r['body'], 'Olio esausto'));
prova('R5 · …in inglese', str_contains($elena->get("/pannello/$epid/anteprima/{$es['waste']}?l=en")['body'], 'Today&#039;s collection: Food waste'));

// Emergenze: righe pronte e «Chiama» per riga.
$r = $elena->get("/pannello/$epid/sezioni/{$es['emergency']}");
prova('R5 · emergenze: righe pronte (112, guardia medica, farmacia, veterinario) nella lingua della guida', str_contains($r['body'], 'data-rip-preset=') && str_contains($r['body'], '+ Out-of-hours doctor')
      && str_contains($r['body'], '+ Vet<') && str_contains($r['body'], '&quot;phone&quot;:&quot;112&quot;'));
$elena->post("/pannello/$epid/sezioni/{$es['emergency']}", ['emergency_number' => '112', 'contacts' => [
    ['id' => '', 'name' => 'Guardia medica', 'phone' => '075 123456', 'note' => 'Notti e festivi'], ['id' => '', 'name' => 'Farmacia di turno', 'phone' => '', 'note' => 'Turni sulla porta']]]);
$r = $elena->get("/pannello/$epid/anteprima/{$es['emergency']}?l=it");
prova('R5 · guida: un «Chiama» per riga con il numero, accessibile', str_contains($r['body'], 'href="tel:075123456"') && str_contains($r['body'], 'aria-label="Chiama Guardia medica"')
      && str_contains($r['body'], 'Farmacia di turno') && substr_count($r['body'], 'numero__chiama') === 1);

// Come arrivare: una scheda per mezzo; il link di Maps dà le coordinate della casa.
$elena->post("/pannello/$epid/sezioni/{$es['arrival']}", ['address' => 'Via Roma 1, 06059 Todi', 'maps_url' => 'https://www.google.com/maps/place/Todi/@42.7810,12.4070,17z',
    'routes' => [['id' => '', 'mode' => 'auto', 'steps' => "Uscita Todi.\nSegui per il centro."], ['id' => '', 'mode' => 'treno', 'steps' => 'Stazione di Ponte Rio, poi autobus.']]]);
prova('R6 · il link di Maps di «Come arrivare» dà le coordinate della struttura', abs((float) val('SELECT lat FROM properties WHERE id = ?', [$epid]) - 42.781) < 0.0001);
$r = $elena->get("/pannello/$epid/anteprima/{$es['arrival']}?l=it");
prova('R5 · guida: una scheda per mezzo, con i passi numerati', substr_count($r['body'], 'class="panel manuale"') === 2 && str_contains($r['body'], 'In auto') && str_contains($r['body'], 'In treno')
      && str_contains($r['body'], '<span class="n">2</span><p>Segui per il centro.</p>'));
prova('R5 · …in francese', str_contains($elena->get("/pannello/$epid/anteprima/{$es['arrival']}?l=fr")['body'], 'En voiture'));

// Scheda luogo: il link di Google Maps per primo; nome e minuti a piedi dal link.
$r = $elena->get("/pannello/$epid/sezioni/{$es['eat']}");
$posMaps = strpos($r['body'], 'Incolla il link di Google Maps'); $posNome = strpos($r['body'], 'for="pl-name"'); $posAltri = strpos($r['body'], 'Altri dettagli');
prova('R6 · modulo: prima il link, poi nome, categoria, perché lo consigli, etichetta; il resto in «Altri dettagli» chiuso',
      $posMaps !== false && $posMaps < $posNome && $posNome < strpos($r['body'], 'Perché lo consigli') && strpos($r['body'], 'for="pl-badge"') < $posAltri
      && $posAltri < strpos($r['body'], 'for="pl-desc"') && $posAltri < strpos($r['body'], 'for="pl-walk"') && str_contains($r['body'], '<details class="altri-dettagli">')
      && str_contains($r['body'], 'È una stima che puoi correggere'));
$link = 'https://www.google.com/maps/place/Trattoria+di+Prova/@42.7830,12.4100,17z/data=!4m6!3m5!8m2!3d42.7832!4d12.4098';
$j = json_decode($elena->post("/pannello/$epid/mappe", ['url' => $link])['body'], true);
prova('R6 · dal link: nome, coordinate e minuti a piedi stimati', ($j['name'] ?? '') === 'Trattoria di Prova' && abs(($j['lat'] ?? 0) - 42.7832) < 0.00001 && ($j['walk_minutes'] ?? 0) >= 3 && ($j['walk_minutes'] ?? 0) <= 6, json_encode($j));
$j = json_decode($elena->post("/pannello/$epid/mappe", ['url' => 'https://example.com/maps/place/Finto/@1,1'])['body'], true);
prova('R6 · un link che non è di Google non si legge (né si segue)', ($j['ok'] ?? true) === false && ($j['name'] ?? 'x') === '');
$j = json_decode($elena->post("/pannello/$epid/mappe", ['url' => 'https://maps.app.goo.gl/nonEsiste'])['body'], true);
prova('R6 · link breve che non porta da nessuna parte: nessun errore, campi vuoti', is_array($j) && ($j['ok'] ?? true) === false);
prova('R6 · la struttura di un altro non si interroga', $anna->post("/pannello/$epid/mappe", ['url' => $link])['code'] === 404);
$elena->post("/pannello/$epid/sezioni/{$es['eat']}/luogo", ['place_id' => '0', 'maps_url' => $link, 'name' => '', 'category' => 'Trattoria', 'note' => 'La torta al testo.', 'badge' => '']);
$pl = riga('SELECT * FROM places WHERE section_id = ?', [$es['eat']]);
prova('R6 · senza nome il luogo prende quello del link, con coordinate e minuti stimati', $pl && $pl['name'] === 'Trattoria di Prova' && abs((float) $pl['lat'] - 42.7832) < 0.00001
      && (int) $pl['walk_minutes'] >= 3 && (int) $pl['walk_minutes'] <= 6, json_encode($pl));
$elena->post("/pannello/$epid/sezioni/{$es['eat']}/luogo", ['place_id' => $pl['id'], 'maps_url' => $link, 'name' => 'Trattoria di Prova', 'walk_minutes' => '12']);
prova('R6 · la stima si corregge a mano e resta', (int) val('SELECT walk_minutes FROM places WHERE id = ?', [$pl['id']]) === 12);

// La conversione delle voci «una per riga» (usata da migrazione 011 e guide pubblicate).
$conv = shell_exec('php -r ' . escapeshellarg('spl_autoload_register(fn($c) => require "' . $DOVE . '/app/src/" . substr($c, 4) . ".php");
    echo json_encode(MHW\Conversione::separaTelefono("Guardia medica: 075 123456 (notti e festivi)")), json_encode(MHW\Conversione::separaTelefono("Emergenze 112")),
         json_encode(MHW\Conversione::sezione("waste", [], ["it" => ["items" => ["Umido martedì e venerdì"]]], "it")[0]["bins"][0]);'));
prova('R5 · conversione: nome, telefono e nota separati; tipo e giorni riconosciuti', str_contains((string) $conv, '["Guardia medica","075 123456","notti e festivi"]')
      && str_contains((string) $conv, '["Emergenze","112",""]') && str_contains((string) $conv, '"type":"umido","days":[2,5]'), (string) $conv);

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
prova('Immagine profilo rifiutata a Essential', str_contains($r['body'], 'non fa parte del tuo piano') && !val('SELECT profile_media_id FROM properties WHERE id = ?', [$pid]));
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
$r = $anna->modulo("/pannello/$pid/procedura/pubblica", "/pannello/$pid/pubblica", []);
$r = $anna->segui($r);
prova('Email non verificata: niente pagamento', str_contains($r['body'], 'Conferma prima la tua email') && (int) val('SELECT COUNT(*) FROM orders WHERE account_id = ?', [$acc['id']]) === 0);
$link = linkPosta('anna@prova.test', 'verifica');
$r = $anna->get(substr($link, strpos($link, '/verifica/')));
prova('Link di verifica funziona', $r['code'] === 302 && val("SELECT email_verified_at FROM users WHERE email = 'anna@prova.test'") !== null);
$r = $anna->get(substr($link, strpos($link, '/verifica/')));
prova('Il link di verifica vale una volta sola', $r['code'] === 200 && pulita($r));

// Fase 3 · R7: prima del primo pagamento, i dati di fatturazione italiani.
$r = $anna->modulo("/pannello/$pid/procedura/pubblica", "/pannello/$pid/pubblica", []);
prova('Fase 3 · senza dati di fatturazione niente pagamento: si va all\'account', $r['code'] === 302 && str_contains($r['loc'], '/account?torna=')
      && (int) val('SELECT COUNT(*) FROM orders WHERE account_id = ?', [$acc['id']]) === 0, $r['loc']);
$r = $anna->segui($r);
prova('…con il modulo aperto, ogni campo con la sua etichetta', $r['code'] === 200 && pulita($r) && str_contains($r['body'], 'Dati di fatturazione')
      && preg_match('#<details[^>]*open>\s*<summary>#', $r['body']) === 1
      && count(array_filter(['vat', 'cf', 'sdi', 'pec', 'billing_postal', 'billing_province'], fn($k) => str_contains($r['body'], 'for="f-' . $k . '"'))) === 6);
$fattura = ['billing_type' => 'azienda', 'billing_name' => 'Anna Prove srl', 'vat' => '12345678901', 'cf' => '', 'sdi' => 'AB12', 'pec' => '',
            'billing_address' => 'Via delle Prove 1', 'billing_postal' => '0612', 'billing_city' => 'Perugia', 'billing_province' => 'Perugia',
            'torna' => "/pannello/$pid/procedura/pubblica"];
$r = $anna->modulo("/account", '/account/fatturazione', $fattura);
prova('Fase 3 · P.IVA con la cifra di controllo sbagliata, SDI corto, CAP e provincia rifiutati', $r['code'] === 200
      && str_contains($r['body'], 'Questa partita IVA non torna') && str_contains($r['body'], 'Il codice destinatario è di 7 caratteri')
      && str_contains($r['body'], 'Il CAP è di 5 cifre') && str_contains($r['body'], 'sigla di 2 lettere')
      && substr_count($r['body'], 'aria-invalid="true"') >= 4 && val('SELECT vat FROM accounts WHERE id = ?', [$acc['id']]) === '');
$r = $anna->modulo("/account", '/account/fatturazione', ['billing_type' => 'privato', 'billing_name' => 'Anna Prova', 'cf' => 'RSSMRA85T10A562T',
            'billing_address' => 'Via delle Prove 1', 'billing_postal' => '06121', 'billing_city' => 'Perugia', 'billing_province' => 'PG']);
prova('…codice fiscale con il carattere di controllo sbagliato rifiutato', str_contains($r['body'], 'Questo codice fiscale non torna'));
$r = $anna->modulo("/account", '/account/fatturazione', ['billing_type' => 'azienda', 'billing_name' => 'Anna Prove srl', 'vat' => '02945910541', 'cf' => '', 'sdi' => '', 'pec' => '',
            'billing_address' => 'Via delle Prove 1', 'billing_postal' => '06121', 'billing_city' => 'Perugia', 'billing_province' => 'pg']);
prova('…azienda senza SDI né PEC rifiutata', str_contains($r['body'], 'Serve il codice destinatario SDI oppure la PEC'));
$fattura = ['billing_type' => 'azienda', 'billing_name' => 'Anna Prove srl', 'vat' => 'IT 02945910541', 'cf' => '', 'sdi' => 'm5uxcr1', 'pec' => '',
            'billing_address' => 'Via delle Prove 1', 'billing_postal' => '06121', 'billing_city' => 'Perugia', 'billing_province' => 'pg',
            'torna' => "/pannello/$pid/procedura/pubblica"];
$r = $anna->modulo("/account?torna=/pannello/$pid/procedura/pubblica", '/account/fatturazione', $fattura);
$af = riga('SELECT * FROM accounts WHERE id = ?', [$acc['id']]);
prova('Fase 3 · dati validi salvati (normalizzati) e si torna alla pubblicazione', $r['code'] === 302 && str_ends_with($r['loc'], "/pannello/$pid/procedura/pubblica")
      && $af['vat'] === '02945910541' && $af['sdi'] === 'M5UXCR1' && $af['billing_province'] === 'PG' && $af['billing_type'] === 'azienda');
$r = $anna->modulo("/account", '/account/fatturazione', $fattura + ['torna' => 'https://altrove.example/']);
prova('…«torna» porta solo dentro il pannello', $r['code'] === 302 && !str_contains($r['loc'], 'altrove'));

$r = $anna->modulo("/pannello/$pid/procedura/pubblica", "/pannello/$pid/pubblica", []);
prova('Pubblica → Stripe Checkout', $r['code'] === 302 && str_starts_with($r['loc'], 'https://checkout.stripe.test/'));
$cli = array_values(array_filter(richiesteStripe(), fn($x) => $x['percorso'] === '/v1/customers'));
$m = end($cli)['corpo']['metadata'] ?? [];
prova('Fase 3 · cliente Stripe con P.IVA e SDI nei metadati, intestatario e indirizzo', ($m['vat'] ?? '') === '02945910541' && ($m['sdi'] ?? '') === 'M5UXCR1'
      && !isset($m['pec']) && (end($cli)['corpo']['name'] ?? '') === 'Anna Prove srl' && (end($cli)['corpo']['address']['country'] ?? '') === 'IT');
prova('Adamo (collegamento Stripe): Fiscal_code (la P.IVA, senza codice fiscale), Fe_code e la P.IVA come tax id del cliente', ($m['Fiscal_code'] ?? '') === '02945910541'
      && ($m['Fe_code'] ?? '') === 'M5UXCR1' && !isset($m['Pec']) && (end($cli)['corpo']['tax_id_data'][0]['type'] ?? '') === 'eu_vat'
      && (end($cli)['corpo']['tax_id_data'][0]['value'] ?? '') === 'IT02945910541', json_encode(end($cli)['corpo'] ?? []));
$mp = json_decode((string) shell_exec('php -r ' . escapeshellarg('define("MHW_APP", "' . $DOVE . '/app"); spl_autoload_register(fn($c) => require "' . $DOVE . '/app/src/" . substr($c, 4) . ".php"); echo json_encode(MHW\\Fatturazione::metadati(["billing_type" => "privato", "cf" => "rssmra85t10a562s", "sdi" => "", "pec" => "mario@pec.example"]));')), true) ?: [];
prova('Adamo · privato: codice fiscale, PEC e codice destinatario 0000000', ($mp['metadata[Fiscal_code]'] ?? '') === 'RSSMRA85T10A562S'
      && ($mp['metadata[Pec]'] ?? '') === 'mario@pec.example' && ($mp['metadata[Fe_code]'] ?? '') === '0000000', json_encode($mp));
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
prova('…e dopo la registrazione dritti alla struttura, con 3 strutture salvate', $r['code'] === 302 && str_contains($r['loc'], '/pannello/nuova')
      && (int) val("SELECT a.intended_quantity FROM accounts a JOIN users u ON u.id = a.user_id WHERE u.email = 'carla@prova.test'") === 3);
prova('…e l\'avviso dice per quante strutture', str_contains($carla->get('/pannello/nuova')['body'], 'Piano <b>Portfolio</b> per 3 strutture scelto'));
$r = $carla->get("/piano?piano=$pp&strutture=3");
prova('…che la mostra già impostata, con il totale (237 €)', preg_match('#name="strutture" type="number"[^>]*value="3"#', $r['body']) === 1 && str_contains($r['body'], "237\u{00A0}€"));
$cacc = (int) val("SELECT a.id FROM accounts a JOIN users u ON u.id = a.user_id WHERE u.email = 'carla@prova.test'");
foreach (['1', '2.5', '0', 'tre', '51', '-3', '2e1'] as $x) {
    $r = $carla->post('/piano', ['pv' => $pp, 'strutture' => $x]);
    if (!($r['code'] === 302 && str_contains($r['loc'], "/piano?piano=$pp"))) prova("Quantità «{$x}» rifiutata", false, $r['loc']);
}
prova('Quantità non intere o fuori dai limiti rifiutate dal server', (int) val('SELECT intended_quantity FROM accounts WHERE id = ?', [$cacc]) === 3
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
$carla->modulo('/account', '/account/fatturazione', ['billing_type' => 'privato', 'billing_name' => 'Carla Portfolio', 'cf' => 'RSSMRA85T10A562S',
               'billing_address' => 'Via Roma 2', 'billing_postal' => '70121', 'billing_city' => 'Bari', 'billing_province' => 'BA']);
prova('Fase 3 · privato: basta il codice fiscale', val('SELECT cf FROM accounts WHERE id = ?', [$cacc]) === 'RSSMRA85T10A562S');
$r = $carla->modulo("/pannello/$c1/procedura/pubblica", "/pannello/$c1/pubblica", []);
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
$r = $carla->modulo("/pannello/$c1/procedura/pubblica", "/pannello/$c1/pubblica", []);
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

// ============================================================ FASE 4 · PORTFOLIO
capitolo('Fase 4 · Portfolio: una struttura prima di pagare, sblocco, copia');
$accDi = fn(string $email) => (int) val('SELECT a.id FROM accounts a JOIN users u ON u.id = a.user_id WHERE u.email = ?', [$email]);
// Marco (demo): Portfolio per 2 strutture, scelto e non pagato.
$marco = new Browser('marco');
$marco->get('/accedi');
$marco->post('/accedi', ['email' => 'marco@esempio.it', 'password' => 'dimostrazione1']);
$macc = $accDi('marco@esempio.it');
$rondini = (int) val("SELECT id FROM properties WHERE account_id = ? AND name = 'B&B Le Rondini'", [$macc]);
$mare = (int) val("SELECT id FROM properties WHERE account_id = ? AND name = 'Casa sul Mare'", [$macc]);
prova('Demo Marco: Portfolio per 2 strutture, non ancora pagato', $rondini > 0 && $mare > 0 && !val('SELECT id FROM subscriptions WHERE account_id = ?', [$macc])
      && (int) val('SELECT intended_quantity FROM accounts WHERE id = ?', [$macc]) === 2);
$r = $marco->get('/pannello');
prova('Le guide: Le Rondini si apre, Casa sul Mare è una card bloccata «Si attiva dopo il pagamento»', $r['code'] === 200 && pulita($r)
      && str_contains($r['body'], "/pannello/$rondini\"") && !str_contains($r['body'], "/pannello/$mare\"") && str_contains($r['body'], 'Si attiva dopo il pagamento')
      && str_contains($r['body'], 'class="panel stack bloccata"') && !str_contains($r['body'], '$stato') && str_contains($r['body'], 'Online'));
$fuori = [];
foreach (["/pannello/$mare", "/pannello/$mare/procedura/struttura", "/pannello/$mare/procedura/sezioni", "/pannello/$mare/anteprima", "/pannello/$mare/impostazioni",
          "/pannello/$mare/lingue", "/pannello/$mare/qr", "/pannello/$mare/copia", "/pannello/$mare/aspetto"] as $u) {
    $r = $marco->get($u);
    if (!($r['code'] === 302 && str_ends_with($r['loc'], '/pannello'))) $fuori[] = "$u {$r['code']}";
}
prova('Fase 4 · ogni pagina della struttura bloccata rimanda alle guide (anche a mano)', !$fuori, implode(', ', $fuori));
$r = $marco->segui($marco->get("/pannello/$mare/procedura/arrivo"));
prova('…con un avviso, non un errore', $r['code'] === 200 && str_contains($r['body'], 'Casa sul Mare si attiva dopo il pagamento') && !str_contains($r['body'], 'note--err'));
$mcore = (int) val('SELECT id FROM sections WHERE property_id = ? AND is_core = 1', [$mare]);
$marco->post("/pannello/$mare/sezioni", ['kind' => 'rules']);
$marco->post("/pannello/$mare/impostazioni", ['name' => 'Rinominata', 'city' => 'Lecce']);
$marco->post("/pannello/$mare/sezioni/$mcore", ['checkin_steps' => ['Scritto a mano.']]);
$marco->post("/pannello/$mare/pubblica", []);
$marco->post("/pannello/$mare/copia", ['da' => (string) $rondini, 'copia' => ['eat']]);
prova('Fase 4 · le modifiche alla struttura bloccata non passano (sezioni, nome, testi, pubblicazione, copia)',
      (int) val('SELECT COUNT(*) FROM sections WHERE property_id = ?', [$mare]) === 1 && val('SELECT name FROM properties WHERE id = ?', [$mare]) === 'Casa sul Mare'
      && !str_contains((string) val('SELECT data FROM section_translations WHERE section_id = ?', [$mcore]), 'Scritto a mano') && !val('SELECT id FROM orders WHERE property_id = ?', [$mare]));
$r = $marco->post("/pannello/$mare/sezioni/$mcore", ['checkin_note' => 'x'], ['Accept: application/json']);
prova('…e il salvataggio automatico riceve 423 con l\'avviso', $r['code'] === 423 && str_contains($r['body'], 'si attiva dopo il pagamento'));
prova('Le Rondini si modifica normalmente', $marco->get("/pannello/$rondini")['code'] === 200 && $marco->get("/pannello/$rondini/procedura/sezioni")['code'] === 200);
// Pagamento simulato con l'abbonamento manuale dall'amministrazione: si sblocca.
$admin->get('/admin/cliente/' . $macc);
$admin->post("/admin/cliente/$macc/abbonamento", ['pv' => (string) pv('portfolio'), 'mesi' => '12', 'nota' => 'Pagamento simulato (prova)', 'strutture' => '2']);
$r = $marco->get("/pannello/$mare");
prova('Fase 4 · abbonamento manuale dall\'admin: Casa sul Mare si sblocca', $r['code'] === 200 && pulita($r));
prova('…e nelle guide non è più bloccata', !str_contains($marco->get('/pannello')['body'], 'Si attiva dopo il pagamento'));

// Gino: Portfolio 3 da zero, fino al pagamento con il webhook.
$gino = new Browser('gino');
$gino->get('/registrati?piano=' . pv('portfolio') . '&strutture=3');
$gino->post('/registrati', ['piano' => pv('portfolio'), 'strutture' => '3', 'name' => 'Gino Portfolio', 'email' => 'gino@prova.test', 'password' => 'GinoProva123', 'termini' => '1']);
$gacc = $accDi('gino@prova.test');
$r = $gino->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Gino Uno', 'city' => 'Bari']);
$g1 = (int) val("SELECT id FROM properties WHERE account_id = ? AND name = 'Gino Uno'", [$gacc]);
prova('Fase 4 · la prima struttura si configura subito', $r['code'] === 302 && str_contains($r['loc'], "/pannello/$g1/procedura/struttura"));
$r = $gino->get('/pannello/nuova');
prova('…la seconda chiede solo il nome: si attiva dopo il pagamento', $r['code'] === 200 && pulita($r) && str_contains($r['body'], 'Per ora basta il nome')
      && !str_contains($r['body'], 'name="city"') && !str_contains($r['body'], 'Crea da una struttura esistente'));
$r = $gino->post('/pannello/nuova', ['name' => 'Gino Due']);
$gino->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Gino Tre']);
$gino->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Gino Quattro']);
$gids = array_map('intval', array_column(righe('SELECT id FROM properties WHERE account_id = ? ORDER BY id', [$gacc]), 'id'));
prova('…create col nome, fino alla quantità scelta (3), e si torna alle guide', $r['code'] === 302 && str_ends_with($r['loc'], '/pannello') && count($gids) === 3);
prova('…bloccate tutte e due', $gino->get("/pannello/{$gids[1]}")['code'] === 302 && $gino->get("/pannello/{$gids[2]}/procedura/struttura")['code'] === 302);
$link = linkPosta('gino@prova.test', 'verifica');
$gino->get(substr($link, strpos($link, '/verifica/')));
$gino->modulo('/account', '/account/fatturazione', ['billing_type' => 'privato', 'billing_name' => 'Gino Portfolio', 'cf' => 'RSSMRA85T10A562S',
               'billing_address' => 'Via Roma 3', 'billing_postal' => '70121', 'billing_city' => 'Bari', 'billing_province' => 'BA']);
$gcore = (int) val('SELECT id FROM sections WHERE property_id = ? AND is_core = 1', [$g1]);
$gino->post("/pannello/$g1/sezioni/$gcore", ['checkin_steps' => ['Suona al citofono.']]);
$r = $gino->modulo("/pannello/$g1/procedura/pubblica", "/pannello/$g1/pubblica", []);
$gord = riga('SELECT * FROM orders WHERE account_id = ? ORDER BY id DESC', [$gacc]);
prova('Pubblicando la prima: pagamento per 3 strutture', $r['code'] === 302 && str_starts_with($r['loc'], 'https://checkout.stripe.test/') && $gord && (int) $gord['quantity'] === 3);
file_put_contents("$STRIPE_DIR/extra-sub_prova_gino", '2');
$r = inviaWebhook(['id' => 'evt_gino_1', 'type' => 'checkout.session.completed', 'data' => ['object' => [
    'id' => $gord['provider_session_id'], 'mode' => 'subscription', 'payment_status' => 'paid', 'customer' => 'cus_gino',
    'subscription' => 'sub_prova_gino', 'client_reference_id' => (string) $gord['id'], 'metadata' => ['order_id' => (string) $gord['id'], 'account_id' => (string) $gacc]]]]);
prova('Fase 4 · webhook di pagamento: le tre strutture si sbloccano', $r['body'] === 'abbonamento-attivato'
      && $gino->get("/pannello/{$gids[1]}")['code'] === 200 && $gino->get("/pannello/{$gids[2]}/procedura/struttura")['code'] === 200, $r['body']);

// Una struttura oltre la quantità pagata: conferma col costo, poi Stripe con create_prorations.
$r = $gino->get('/pannello');
prova('Portfolio pieno: «Aggiungi una struttura» resta', str_contains($r['body'], 'Aggiungi una struttura'));
$r = $gino->get('/pannello/nuova');
prova('Fase 4 · conferma con il costo: 60 € l\'anno, la parte che resta di quest\'anno, il totale dal rinnovo', $r['code'] === 200 && pulita($r)
      && str_contains($r['body'], 'Ogni struttura in più') && str_contains($r['body'], "60\u{00A0}€") && str_contains($r['body'], 'circa')
      && str_contains($r['body'], "297\u{00A0}€") && str_contains($r['body'], 'name="conferma"'));
$prima = count(richiesteStripe());
$r = $gino->post('/pannello/nuova', ['name' => 'Gino Quattro', 'city' => 'Bari']);
prova('…senza conferma niente Stripe e niente struttura', count(richiesteStripe()) === $prima && (int) val('SELECT COUNT(*) FROM properties WHERE account_id = ?', [$gacc]) === 3
      && str_contains($r['body'], 'Spunta la conferma per aggiungere la struttura'));
$r = $gino->post('/pannello/nuova', ['name' => 'Gino Quattro', 'city' => 'Bari', 'conferma' => '1']);
$mod = array_values(array_filter(richiesteStripe(), fn($x) => $x['metodo'] === 'POST' && $x['percorso'] === '/v1/subscriptions/sub_prova_gino'));
$mc = end($mod)['corpo'] ?? [];
$g4 = (int) val("SELECT id FROM properties WHERE account_id = ? AND name = 'Gino Quattro'", [$gacc]);
prova('Fase 4 · Stripe: la voce delle aggiuntive passa a 3, proration_behavior=create_prorations', ($mc['items'][0]['id'] ?? '') === 'si_extra_sub_prova_gino'
      && ($mc['items'][0]['quantity'] ?? '') === '3' && ($mc['proration_behavior'] ?? '') === 'create_prorations' && !isset($mc['payment_behavior']), json_encode($mc));
prova('…la struttura c\'è, bloccata finché Stripe non conferma', $g4 > 0 && $gino->get("/pannello/$g4")['code'] === 302
      && (int) val("SELECT quantity FROM subscriptions WHERE provider_subscription_id = 'sub_prova_gino'") === 3);
inviaWebhook(['id' => 'evt_gino_2', 'type' => 'customer.subscription.updated', 'data' => ['object' => [
    'id' => 'sub_prova_gino', 'status' => 'active', 'cancel_at_period_end' => false, 'items' => ['data' => [
        ['id' => 'si_base_sub_prova_gino', 'quantity' => 1, 'current_period_start' => time(), 'current_period_end' => time() + 360 * 86400, 'price' => ['id' => 'price_finto_annuale', 'product' => 'prod_finto_base']],
        ['id' => 'si_extra_sub_prova_gino', 'quantity' => 3, 'current_period_start' => time(), 'current_period_end' => time() + 360 * 86400, 'price' => ['id' => 'price_finto_extra', 'product' => 'prod_finto_extra']]]]]]]);
prova('…il webhook porta l\'abbonamento a 4: la quarta si sblocca', (int) val("SELECT quantity FROM subscriptions WHERE provider_subscription_id = 'sub_prova_gino'") === 4
      && $gino->get("/pannello/$g4")['code'] === 200);

// Copia completa da Casa Lucia (demo): Lucia passa a un Portfolio per 2 strutture.
$lucia = new Browser('lucia');
$lucia->get('/accedi');
$lucia->post('/accedi', ['email' => 'lucia@esempio.it', 'password' => 'dimostrazione1']);
$lacc = $accDi('lucia@esempio.it');
$casa = (int) val("SELECT id FROM properties WHERE account_id = ? AND name = 'Casa Lucia'", [$lacc]);
db()->prepare("UPDATE properties SET address = 'Via del Teatro 4', cin = 'IT052015C2DEMO', postal_code = '53045' WHERE id = ?")->execute([$casa]);
$admin->get('/admin/cliente/' . $lacc);
$admin->post("/admin/cliente/$lacc/abbonamento", ['pv' => (string) pv('portfolio'), 'mesi' => '12', 'nota' => 'Prova della copia', 'strutture' => '2']);
$r = $lucia->get('/pannello/nuova');
prova('Fase 4 · «Crea da una struttura esistente»: Casa Lucia, con le sezioni già spuntate', $r['code'] === 200 && pulita($r) && str_contains($r['body'], 'Crea da una struttura esistente')
      && preg_match('#<option value="' . $casa . '">\s*Casa Lucia#', $r['body']) === 1 && substr_count($r['body'], 'name="copia[]"') === 13
      && str_contains($r['body'], 'Non si copiano mai, perché sono solo di una struttura: indirizzo, CIN, reti Wi-Fi'));
$mediaPrima = (int) val('SELECT COUNT(*) FROM media WHERE account_id = ?', [$lacc]);
$r = $lucia->post('/pannello/nuova', ['name' => 'Casa Lucia Due', 'city' => 'Pienza', 'origine' => (string) $casa, 'copia' => ['waste', 'eat', 'visit', 'todo', 'transport', 'emergency', 'info', 'rules', 'services', 'extras'], 'copia_aspetto' => '1', 'copia_contatti' => '1']);
$due = (int) val("SELECT id FROM properties WHERE account_id = ? AND name = 'Casa Lucia Due'", [$lacc]);
preg_match('#note--err" role="alert">([^<]*)#', $r['body'], $em); prova('Copia: struttura creata, si continua dalla procedura', $due > 0 && $r['code'] === 302 && str_contains($r['loc'], "/pannello/$due/procedura/struttura"), $r['loc'] . ' ' . ($em[1] ?? ''));
$kinds = fn(int $p) => array_column(righe('SELECT kind FROM sections WHERE property_id = ? ORDER BY is_core DESC, position, id', [$p]), 'kind');
prova('Fase 4 · copiate regole, rifiuti, emergenze, servizi extra, dove mangiare; NON Wi-Fi né i passaggi di arrivo', $kinds($due) === ['checkin', 'rules', 'waste', 'emergency', 'extras', 'transport', 'eat']
      && !str_contains((string) val("SELECT t.data FROM section_translations t JOIN sections s ON s.id = t.section_id WHERE s.property_id = ? AND s.is_core = 1", [$due]), 'portone'),
      json_encode($kinds($due)));
$pd = riga('SELECT * FROM properties WHERE id = ?', [$due]); $pc = riga('SELECT * FROM properties WHERE id = ?', [$casa]);
prova('…né indirizzo, CIN, copertina; sì palette, tono, lingua principale, contatti', $pd['address'] === '' && $pd['cin'] === '' && !$pd['cover_media_id']
      && $pd['palette'] === $pc['palette'] && $pd['text_tone'] === $pc['text_tone'] && $pd['default_locale'] === $pc['default_locale']
      && (int) val('SELECT COUNT(*) FROM property_contacts WHERE property_id = ?', [$due]) === (int) val('SELECT COUNT(*) FROM property_contacts WHERE property_id = ?', [$casa]));
prova('…con le traduzioni e le lingue (en, de)', (int) val("SELECT COUNT(*) FROM section_translations t JOIN sections s ON s.id = t.section_id WHERE s.property_id = ? AND t.locale = 'en'", [$due])
      === (int) val("SELECT COUNT(*) FROM section_translations t JOIN sections s ON s.id = t.section_id WHERE s.property_id = ? AND s.kind IN ('rules','waste','emergency','extras','transport','eat') AND t.locale = 'en'", [$casa])
      && (int) val('SELECT COUNT(*) FROM property_locales WHERE property_id = ?', [$due]) === 3);
$luoghi = fn(int $p) => righe("SELECT pl.* FROM places pl JOIN sections s ON s.id = pl.section_id WHERE s.property_id = ? AND s.kind = 'eat' ORDER BY pl.position, pl.id", [$p]);
$lo = $luoghi($casa); $lc = $luoghi($due);
prova('Fase 4 · i luoghi con le loro traduzioni (e, dalla 6B, le chiavi di categoria ed etichetta)', count($lc) === 3 && array_column($lc, 'name') === array_column($lo, 'name')
      && array_column($lc, 'category_key') === array_column($lo, 'category_key') && in_array('trattoria', array_column($lc, 'category_key'), true)
      && (int) val('SELECT COUNT(*) FROM place_translations WHERE place_id = ?', [$lc[0]['id']]) === (int) val('SELECT COUNT(*) FROM place_translations WHERE place_id = ?', [$lo[0]['id']]));
$mo = riga('SELECT * FROM media WHERE id = ?', [$lo[0]['media_id']]); $mc2 = riga('SELECT * FROM media WHERE id = ?', [$lc[0]['media_id']]);
prova('Fase 4 · le foto sono duplicate nello storage con nomi nuovi', $mo && $mc2 && $mo['id'] !== $mc2['id'] && $mo['object_key'] !== $mc2['object_key']
      && (int) $mc2['property_id'] === $due && (int) $mc2['bytes'] === (int) $mo['bytes'] && (int) val('SELECT COUNT(*) FROM media WHERE account_id = ?', [$lacc]) === $mediaPrima + 3);
$immagini = function (Browser $b, string $url): array {
    $r = $b->get($url);
    preg_match_all('#<img src="([^"]+)"#', $r['body'], $m);
    global $BASE;
    $radice = preg_replace('#^(https?://[^/]+).*$#', '$1', $BASE);   // gli URL relativi hanno già la sottocartella
    return array_map(fn($u) => $b->get(str_starts_with($u, '/') ? $radice . html_entity_decode($u) : html_entity_decode($u))['code'],
                     array_values(array_filter($m[1], fn($u) => !str_starts_with($u, 'data:'))));
};
$eatDue = (int) val("SELECT id FROM sections WHERE property_id = ? AND kind = 'eat'", [$due]);
$eatCasa = (int) val("SELECT id FROM sections WHERE property_id = ? AND kind = 'eat'", [$casa]);
$codici = $immagini($lucia, "/pannello/$due/anteprima/$eatDue");
prova('…e la guida nuova le mostra', count($codici) === 3 && array_unique($codici) === [200], json_encode($codici));
// Le due guide sono indipendenti.
$lucia->post("/pannello/$due/sezioni/$eatDue/luogo", ['place_id' => (string) $lc[0]['id'], 'name' => 'Osteria cambiata', 'maps_url' => (string) $lc[0]['maps_url']]);
prova('Fase 4 · modificare la copia non tocca l\'originale', val('SELECT name FROM places WHERE id = ?', [$lo[0]['id']]) === 'Osteria dell\'Arco'
      && val('SELECT name FROM places WHERE id = ?', [$lc[0]['id']]) === 'Osteria cambiata');
// «Copia sezioni da…» su una struttura che esiste: le regole si saltano, «dove mangiare» si sostituisce.
$rulesDue = (int) val("SELECT id FROM sections WHERE property_id = ? AND kind = 'rules'", [$due]);
$lucia->post("/pannello/$due/sezioni/$rulesDue", ['items' => ['Regola scritta solo qui.']]);
$r = $lucia->get("/pannello/$due/copia?da=$casa");
prova('Fase 4 · «Copia sezioni da…»: per le sezioni che ci sono già, Saltala / Sostituiscila', $r['code'] === 200 && pulita($r)
      && str_contains($r['body'], 'name="esistenti[rules]" value="salta" checked') && str_contains($r['body'], 'name="esistenti[eat]" value="sostituisci"'));
$fotoVecchie = array_map('intval', array_column($lc, 'media_id'));
$r = $lucia->post("/pannello/$due/copia", ['da' => (string) $casa, 'copia' => ['rules', 'eat'], 'esistenti' => ['rules' => 'salta', 'eat' => 'sostituisci']]);
$lc2 = $luoghi($due);
prova('…regole saltate (restano quelle scritte qui), «dove mangiare» sostituita come l\'originale', $r['code'] === 302
      && (int) val("SELECT COUNT(*) FROM sections WHERE property_id = ? AND kind = 'rules'", [$due]) === 1
      && str_contains((string) val("SELECT data FROM section_translations WHERE section_id = ? AND locale = 'it'", [$rulesDue]), 'Regola scritta solo qui')
      && count($lc2) === 3 && $lc2[0]['name'] === 'Osteria dell\'Arco' && (int) val("SELECT COUNT(*) FROM sections WHERE property_id = ? AND kind = 'eat'", [$due]) === 1);
prova('…le foto della sezione sostituita sono cancellate, le nuove sono copie', !val('SELECT COUNT(*) FROM media WHERE id IN (' . implode(',', $fotoVecchie) . ')')
      && !array_intersect(array_map('intval', array_column($lc2, 'media_id')), array_map('intval', array_column($lo, 'media_id'))));
// Tutto o niente: con un limite di sezioni che la copia supererebbe, non resta niente a metà.
$admin->post("/admin/cliente/$lacc/override", ['feature' => 'sections', 'valore' => '3', 'nota' => 'prova']);
$prima2 = array_column(righe('SELECT id FROM sections WHERE property_id = ? ORDER BY id', [$due]), 'id');
$mediaPrima = (int) val('SELECT COUNT(*) FROM media WHERE account_id = ?', [$lacc]);
$oggettiPrima = $S3_DIR !== '' && is_dir("$S3_DIR/oggetti") ? count(glob("$S3_DIR/oggetti/*")) : -1;
$r = $lucia->post("/pannello/$due/copia", ['da' => (string) $casa, 'copia' => ['eat'], 'esistenti' => ['eat' => 'sostituisci']]);
preg_match('#note--err" role="alert">([^<]*)#', $r['body'], $em);
prova('Fase 4 · copia che supera il limite del piano: rifiutata, niente a metà (sezioni, foto, file nello storage)', $r['code'] === 200 && str_contains($r['body'], 'sezioni attive')
      && array_column(righe('SELECT id FROM sections WHERE property_id = ? ORDER BY id', [$due]), 'id') === $prima2
      && (int) val('SELECT COUNT(*) FROM media WHERE account_id = ?', [$lacc]) === $mediaPrima
      && ($oggettiPrima < 0 || count(glob("$S3_DIR/oggetti/*")) === $oggettiPrima),
      $r['code'] . ' ' . ($em[1] ?? '') . ' media ' . val('SELECT COUNT(*) FROM media WHERE account_id = ?', [$lacc]) . "/$mediaPrima oggetti " . count(glob("$S3_DIR/oggetti/*")) . "/$oggettiPrima");
$admin->post("/admin/cliente/$lacc/override", ['feature' => 'sections', 'valore' => '']);
// Eliminare la copia non rompe l'originale.
$r = $lucia->post("/pannello/$due/elimina", ['conferma' => 'Casa Lucia Due']);
$codici = $immagini($lucia, "/pannello/$casa/anteprima/$eatCasa");
prova('Fase 4 · eliminata la copia, l\'originale ha ancora tutte le sue foto e la sua guida', !val('SELECT id FROM properties WHERE id = ?', [$due])
      && count($codici) === 3 && array_unique($codici) === [200] && $ospite->get('/g/' . $pc['slug'])['code'] === 200, json_encode($codici));

// ================================================================= FASE 5
capitolo('Fase 5 · listino, guida che fa guadagnare, FAQ, firma, email, funnel');
$posta = fn(string $a) => array_values(array_filter(array_map(fn($l) => json_decode($l, true), file("$DOVE/app/storage/logs/mail.log") ?: []), fn($m) => ($m['to'] ?? '') === $a));
$r = $ospite->get('/');
prova('K1 · sotto ogni prezzo l\'equivalente mensile (87/12, 117/12, Portfolio per 2: 177/12)', str_contains($r['body'], "circa 7,25\u{00A0}€ al mese")
      && str_contains($r['body'], "circa 9,75\u{00A0}€ al mese") && str_contains($r['body'], "<span data-mensile>14,75\u{00A0}€</span> al mese"));
preg_match_all('#<div class="plan[^"]*">(.*?)(?=<div class="plan[ "]|</div>\s*<details class="confronto")#s', $r['body'], $carte);
$ess = $carte[1][0] ?? ''; $plus = $carte[1][1] ?? '';
prova('K1 · Essential senza le voci comuni; Plus da «Tutto di Essential, e in più:» senza ripetere', !str_contains($ess, 'Pannello di controllo') && !str_contains($ess, 'Personalizzazione colori')
      && str_contains($plus, '<li class="plan__da">Tutto di Essential, e in più:</li>') && !str_contains($plus, 'Logo della struttura'), substr(strip_tags($plus), 0, 200));
prova('K1 · «Confronta tutti i piani»: tabella dalle funzioni dei pacchetti', str_contains($r['body'], '<summary>Confronta tutti i piani</summary>')
      && str_contains($r['body'], 'Firma «Guida creata con MyHouse Welcome» nascondibile') && str_contains($r['body'], '<th scope="row">Lingue pubblicabili</th>')
      && str_contains($r['body'], 'da 2 a 50'));
prova('K2 · in landing: «Una prenotazione diretta in più all\'anno paga l\'abbonamento.»', str_contains($r['body'], 'Una prenotazione diretta in più all&#039;anno paga l&#039;abbonamento.')
      || str_contains($r['body'], "Una prenotazione diretta in più all'anno paga l'abbonamento."));
prova('K3 · FAQ prima del listino: sei domande in un accordion accessibile', substr_count($r['body'], 'class="faq__voce"') >= 6 && str_contains($r['body'], 'Gli ospiti vengono tracciati?')
      && strpos($r['body'], 'id="domande"') < strpos($r['body'], 'id="piani"'));
prova('K3 · accanto a «Guarda la demo» le lingue della demo (IT / EN / FR)', preg_match_all('#href="[^"]+/benvenuto\?l=(it|en|fr)" hreflang#', $r['body'], $mm) >= 2);
prova('K3 · nessuna testimonianza: nessun blocco', !str_contains($r['body'], 'Le parole di chi ospita'));
prova('V6 · tre scene sotto l\'hero, al posto della foto grande, con le foto vere', substr_count($r['body'], 'class="scena"') === 3
      && substr_count($r['body'], 'scena__vuota') === 0 && !str_contains($r['body'], 'class="stage"')
      && str_contains($r['body'], '/assets/foto/scena-qr.jpg') && str_contains($r['body'], '/assets/foto/scena-host-1200.webp'));
// Una foto che manca: al suo posto il disegno, senza errori; le altre restano.
rename("$DOVE/assets/foto/scena-host.jpg", "$DOVE/assets/foto/scena-host.jpg.via");
$senza = $ospite->get('/')['body'];
rename("$DOVE/assets/foto/scena-host.jpg.via", "$DOVE/assets/foto/scena-host.jpg");
prova('V6 · foto mancante: riquadro colorato con il disegno, le altre due foto restano', substr_count($senza, '<span class="scena__vuota') === 1
      && substr_count($senza, 'viewBox="0 0 160 100"') === 1 && !str_contains($senza, 'scena-host-1200.webp') && str_contains($senza, 'scena-ospite-1200.webp'));
// Un .jpg caricato dopo le WebP vince finché non si rigenerano.
touch("$DOVE/assets/foto/scena-qr.jpg", time() + 60);
$nuova = $ospite->get('/')['body'];
touch("$DOVE/assets/foto/scena-qr.jpg", filemtime("$DOVE/assets/foto/scena-qr-1200.webp"));
prova('V6 · un .jpg nuovo si vede subito: le WebP più vecchie non lo coprono', !str_contains($nuova, 'scena-qr-1200.webp') && str_contains($nuova, '/assets/foto/scena-qr.jpg')
      && str_contains($nuova, 'scena-ospite-1200.webp'));
// Pacchetti: la funzione hide_branding in una versione NUOVA di Plus e Portfolio.
$hb = (int) val("SELECT id FROM features WHERE code = 'hide_branding'");
$vers = fn(string $c) => righe('SELECT pv.id, pv.version, pv.is_current, (SELECT value FROM package_features WHERE package_version_id = pv.id AND feature_id = ?) AS hb
                                FROM package_versions pv JOIN packages p ON p.id = pv.package_id WHERE p.code = ? ORDER BY pv.version', [$hb, $c]);
$vp = $vers('plus'); $ve = $vers('essential');
prova('K4 · migrazione: Plus ha una versione nuova con la firma nascondibile, la vecchia resta com\'era', count($vp) >= 2 && end($vp)['hb'] === '1' && (int) end($vp)['is_current'] === 1
      && $vp[count($vp) - 2]['hb'] !== '1' && end($ve)['hb'] !== '1', json_encode($vp));
// Testimonianze dall'amministrazione.
$admin->get('/admin/testimonianze');
$admin->post('/admin/testimonianze', ['id' => '0', 'name' => 'Persona Di Prova', 'property_name' => 'Struttura di prova', 'body' => 'Testo di prova.', 'position' => '0']);
prova('K3 · testimonianza nascosta: la landing non la mostra', !str_contains($ospite->get('/')['body'], 'Persona Di Prova'));
$tid = (int) val("SELECT id FROM testimonials WHERE name = 'Persona Di Prova'");
$admin->post('/admin/testimonianze', ['id' => (string) $tid, 'name' => 'Persona Di Prova', 'property_name' => 'Struttura di prova', 'body' => 'Testo di prova.', 'visible' => '1', 'position' => '0',
                                      'foto' => file_(png(200, 200), 'image/png')]);
$r = $ospite->get('/');
prova('K3 · visibile: compare il blocco, con la foto', str_contains($r['body'], 'Le parole di chi ospita') && str_contains($r['body'], 'Persona Di Prova') && str_contains($r['body'], 'class="panel voce"')
      && val('SELECT photo_key FROM testimonials WHERE id = ?', [$tid]) !== '');
$admin->post("/admin/testimonianze/$tid/elimina", []);
prova('…ed eliminata sparisce', !str_contains($ospite->get('/')['body'], 'Le parole di chi ospita') && !val('SELECT id FROM testimonials WHERE id = ?', [$tid]));

// La firma nella guida, recensioni e prenotazione diretta nel commiato (Casa Lucia).
$lslug = (string) val('SELECT slug FROM properties WHERE id = ?', [$casa]);
$r = $ospite->get("/g/$lslug");
prova('K4 · firma in fondo alla guida, con il link alla landing e ?ref=guida', str_contains($r['body'], 'Guida creata con MyHouse Welcome') && str_contains($r['body'], '/?ref=guida"'));
$r = $ospite->get("/g/$lslug/commiato");
prova('K2 · commiato senza recensioni né prenotazione diretta: nessun blocco', !str_contains($r['body'], 'Ti è piaciuto il soggiorno?') && !str_contains($r['body'], 'prenota da noi'));
$r = $lucia->get("/pannello/$casa/impostazioni");
prova('K2 · Impostazioni: recensioni, prenotazione diretta, firma nascondibile (Portfolio)', str_contains($r['body'], 'Dopo il soggiorno.') && str_contains($r['body'], 'name="review_booking"')
      && str_contains($r['body'], 'name="direct_code"') && str_contains($r['body'], 'name="hide_branding"'));
$r = $lucia->post("/pannello/$casa/dopo-il-soggiorno", ['review_google' => 'https://g.page/r/esempio/review', 'review_booking' => 'https://www.booking.com/esempio',
    'review_airbnb' => 'javascript:alert(1)', 'direct_url' => 'https://www.esempio.it/prenota', 'direct_code' => 'TORNA10', 'hide_branding' => '1']);
$pl = riga('SELECT * FROM properties WHERE id = ?', [$casa]);
prova('…un link non web resta vuoto, con un avviso', $pl['review_airbnb'] === '' && $pl['review_google'] === 'https://g.page/r/esempio/review' && (int) $pl['hide_branding'] === 1
      && str_contains($lucia->segui($r)['body'], 'non erano indirizzi web validi'));
$lucia->post("/pannello/$casa/pubblica", []);
$r = $ospite->get("/g/$lslug/commiato?l=en");
prova('K2 · commiato (en): «Did you enjoy your stay?» con Google e Booking, «book with us» con il codice', str_contains($r['body'], 'Did you enjoy your stay?')
      && str_contains($r['body'], 'href="https://g.page/r/esempio/review"') && str_contains($r['body'], '>Booking.com</a>') && !str_contains($r['body'], '>Airbnb</a>')
      && str_contains($r['body'], 'Next time, book with us directly') && str_contains($r['body'], 'Discount code: TORNA10') && str_contains($r['body'], 'href="https://www.esempio.it/prenota"'));
prova('K4 · firma nascosta (piano con hide_branding)', !str_contains($ospite->get("/g/$lslug")['body'], 'Guida creata con MyHouse Welcome'));
$anna->post("/pannello/$pid/dopo-il-soggiorno", ['hide_branding' => '1']);
prova('K4 · Essential non può nasconderla, nemmeno a mano', (int) val('SELECT hide_branding FROM properties WHERE id = ?', [$pid]) === 0
      && str_contains($anna->get("/pannello/$pid/impostazioni")['body'], 'Con Plus e Portfolio puoi nasconderla'));
$lucia->post("/pannello/$casa/dopo-il-soggiorno", ['review_google' => '', 'review_booking' => '', 'direct_url' => '', 'direct_code' => '']);
$lucia->post("/pannello/$casa/pubblica", []);
prova('…tolti i link e ripubblicato: commiato senza blocchi, firma di nuovo visibile', !str_contains($ospite->get("/g/$lslug/commiato")['body'], 'Ti è piaciuto il soggiorno?')
      && str_contains($ospite->get("/g/$lslug")['body'], 'Guida creata con MyHouse Welcome'));
// Servizi extra (demo): «Richiedi su WhatsApp» con il messaggio nella lingua dell'ospite.
$extraSid = (int) val("SELECT id FROM sections WHERE property_id = ? AND kind = 'extras'", [$casa]);
$r = $ospite->get("/g/$lslug/$extraSid?l=en");
prova('K2 · Servizi extra: «Request on WhatsApp» col messaggio in inglese', $r['code'] === 200 && str_contains($r['body'], 'Station transfer') && str_contains($r['body'], '15 € · per trip')
      && str_contains($r['body'], 'https://wa.me/390742000000?text=' . rawurlencode('Hi! I would like to request: Station transfer')) && str_contains($r['body'], 'Request on WhatsApp'));

// K5 · email di richiamo, una volta sola, con il link per smettere.
$rita = new Browser('rita');
$rita->get('/registrati?piano=' . pv('plus'));
$rita->post('/registrati', ['piano' => pv('plus'), 'name' => 'Rita Richiami', 'email' => 'rita@prova.test', 'password' => 'RitaProva1234', 'termini' => '1']);
$rita->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Casa Rita', 'city' => 'Assisi']);
$rpid = (int) val("SELECT id FROM properties WHERE name = 'Casa Rita'");
$indietro = fn(int $giorni) => db()->prepare('UPDATE properties SET created_at = ? WHERE id = ?')->execute([gmdate('Y-m-d\TH:i:s\Z', time() - $giorni * 86400 - 600), $rpid]);
prova('K5 · /cron con un token sbagliato: 404', $ospite->get('/cron/sbagliato')['code'] === 404);
$cron = fn() => json_decode($ospite->get('/cron/token-finto-per-le-prove')['body'], true);
$indietro(2); $c1 = $cron();
$m = $posta('rita@prova.test'); $ult = end($m) ?: [];
prova('K5 · dopo 1 giorno, arrivo vuoto: «Come si entra a Casa Rita?» con il pulsante giusto', ($c1['email']['arrivo'] ?? 0) >= 1 && str_contains((string) ($ult['subject'] ?? ''), 'Come si entra a Casa Rita?')
      && str_contains((string) $ult['text'], "/pannello/$rpid/procedura/arrivo") && str_contains((string) $ult['text'], '/email/stop/'), json_encode($c1));
$prima = count($posta('rita@prova.test')); $cron();
prova('…una volta sola', count($posta('rita@prova.test')) === $prima);
$indietro(4); $cron(); $m = $posta('rita@prova.test'); $ult = end($m);
prova('K5 · dopo 3 giorni senza sezioni', str_contains((string) $ult['subject'], 'aggiungili a Casa Rita') && str_contains((string) $ult['text'], "/pannello/$rpid/procedura/sezioni"));
$indietro(8); $cron(); $m = $posta('rita@prova.test'); $ult = end($m);
prova('K5 · dopo 7 giorni non pubblicata', str_contains((string) $ult['subject'], 'Casa Rita è quasi pronta') && str_contains((string) $ult['text'], "/pannello/$rpid/procedura/pubblica"));
preg_match('#/email/stop/[a-f0-9]+#', (string) $ult['text'], $stop);
$r = $rita->get($stop[0]);
prova('K5 · «non mandarmene più» chiede conferma con un bottone (i link aperti in anteprima non disiscrivono)', $r['code'] === 200 && pulita($r)
      && str_contains($r['body'], 'promemoria sulla pubblicazione') && !val("SELECT id FROM email_optout WHERE kind = 'pubblica'"));
$r = $rita->post($stop[0], []);
prova('…confermato, registrato', str_contains($r['body'], 'Fatto.') && (bool) val("SELECT id FROM email_optout WHERE kind = 'pubblica'"));
db()->prepare("DELETE FROM email_log WHERE kind = 'pubblica' AND account_id = (SELECT account_id FROM properties WHERE id = ?)")->execute([$rpid]);
$prima = count($posta('rita@prova.test')); $cron();
prova('…e quel tipo non riparte più, anche se il registro si svuota', count($posta('rita@prova.test')) === $prima);
$gsub = (int) val("SELECT id FROM subscriptions WHERE provider_subscription_id = 'sub_prova_gino'");
db()->prepare('UPDATE subscriptions SET current_period_end = ?, cancel_at_period_end = 0 WHERE id = ?')->execute([gmdate('Y-m-d\TH:i:s\Z', time() + 30 * 86400), $gsub]);
$cron(); $m = $posta('gino@prova.test'); $ult = end($m);
prova('K5 · 30 giorni prima del rinnovo, con le statistiche dell\'anno', str_contains((string) $ult['subject'], 'Il tuo abbonamento si rinnova il') && str_contains((string) $ult['text'], '/account')
      && (str_contains((string) $ult['text'], 'aperta') || str_contains((string) $ult['text'], 'aperte') || str_contains((string) $ult['text'], 'non abbiamo ancora registrato aperture')));
prova('K5 · controllo leggero senza cron: il file di blocco in storage/', is_file("$DOVE/app/storage/richiami.lock"));

// K6 · funnel senza cookie.
$fn = array_column(righe('SELECT kind, COUNT(*) AS n FROM analytics_events WHERE property_id IS NULL GROUP BY kind'), 'n', 'kind');
prova('K6 · eventi anonimi: landing_view, signup, property_created, published', ($fn['landing_view'] ?? 0) > 0 && ($fn['signup'] ?? 0) > 0 && ($fn['property_created'] ?? 0) > 0 && ($fn['published'] ?? 0) > 0, json_encode($fn));
$r = $admin->get('/admin');
prova('K6 · nel Quadro: ultimi 30 giorni e percentuali di passaggio', str_contains($r['body'], 'Dalla landing alla guida pubblicata') && substr_count($r['body'], 'class="stat"><b>') >= 4
      && str_contains($r['body'], '% dal passo prima'));
prova('K6 · nessun cookie sulla guida né sulla landing per chi non è entrato', intestazione($ospite->get("/g/$lslug"), 'Set-Cookie') === '');
$r = $admin->post('/admin/diagnostica/foto', []);
prova('V6 · Diagnostica: «Rigenera le foto WebP» con GD', str_contains($admin->segui($r)['body'], 'Foto WebP rigenerate'));

// I file della guida pubblicata: togliendoli dal pannello restano finché non si ripubblica.
capitolo('Revisione · i file della guida pubblicata');
$plFoto = riga("SELECT pl.id, pl.media_id, s.id AS sid FROM places pl JOIN sections s ON s.id = pl.section_id WHERE s.property_id = ? AND pl.media_id IS NOT NULL ORDER BY pl.id", [$casa]);
$lucia->post("/pannello/$casa/sezioni/{$plFoto['sid']}/luogo/{$plFoto['id']}/azione", ['fai' => 'togli-foto']);
$codici = $immagini($ospite, "/g/$lslug/{$plFoto['sid']}");
prova('Revisione · tolta la foto dal pannello, la guida pubblicata la mostra ancora', !val('SELECT media_id FROM places WHERE id = ?', [$plFoto['id']])
      && (bool) val('SELECT id FROM media WHERE id = ?', [$plFoto['media_id']]) && count($codici) === 3 && array_unique($codici) === [200], json_encode($codici));
db()->prepare('UPDATE media SET created_at = ? WHERE id = ?')->execute([gmdate('Y-m-d\\TH:i:s\\Z', time() - 7200), $plFoto['media_id']]);
$lucia->post("/pannello/$casa/pubblica", []);
prova('…ripubblicata la guida, il file non serve più e si cancella', !val('SELECT id FROM media WHERE id = ?', [$plFoto['media_id']])
      && count($immagini($ospite, "/g/$lslug/{$plFoto['sid']}")) === 2);
prova('Revisione · un link di Maps che inganna il controllo degli indirizzi non si segue', json_decode($elena->post("/pannello/$epid/mappe", ['url' => 'https://evil.example\\@www.google.com/maps/place/X/@1,1'])['body'], true)['ok'] === false);

// ==================================================================== SICUREZZA
capitolo('Sicurezza: proprietà dei dati, CSRF, amministrazione');
$bruno->get('/pannello');
foreach (["/pannello/$pid", "/pannello/$pid/sezioni/$core", "/pannello/$pid/anteprima", "/pannello/$pid/qr.png", "/pannello/$pid/lingue/en", "/pannello/$pid/procedura/arrivo"] as $p) {
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
prova('La landing mostra il prezzo nuovo', str_contains($home, "97\u{00A0}€") && str_contains($home, "Da 97\u{00A0}€ + IVA all'anno"));
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

// ========================================================== LANDING RIDISEGNATA
capitolo('Landing ridisegnata');
$r = $ospite->get('/');
prova('Foglio di stile e script con la versione (?v=): dopo un aggiornamento FTP il browser prende quelli nuovi',
      preg_match('#/assets/app\.css\?v=\d+"#', $r['body']) === 1 && preg_match('#/assets/prezzi\.js\?v=\d+"#', $r['body']) === 1
      && preg_match('#/assets/landing\.js\?v=\d+"#', $r['body']) === 1);
prova('…anche nella guida ospite e nel pannello', preg_match('#/assets/app\.css\?v=\d+"#', $ospite->get('/g/' . $demo['slug'])['body'] ?? '') === 1
      || preg_match('#/assets/app\.css\?v=\d+"#', $ospite->get('/accedi')['body']) === 1);
prova('Hero: demo e lingue in un solo gruppo, accanto alla CTA', preg_match('#<span class="demo-gruppo">.*?Guarda la demo.*?class="demo-lingue"#s', $r['body']) === 1);
prova('Scene: le foto hanno un testo alternativo e la versione piccola per il telefono', substr_count($r['body'], 'sizes="(max-width: 760px) 92px, 380px"') === 3
      && str_contains($r['body'], 'alt="Un ospite inquadra con il telefono il QR'));
prova('Come funziona: tre passi che sono link alle tre schermate vere', substr_count($r['body'], 'class="passo"') === 3
      && str_contains($r['body'], 'href="#schermata-1"') && str_contains($r['body'], 'id="schermata-3"') && str_contains($r['body'], 'data-passi')
      && is_file("$DOVE/assets/landing.js") && substr_count($r['body'], '/assets/foto/pannello-') === 3);
prova('Tempo: la guida risponde alle domande', str_contains($tempo = (preg_match('#<section id="il-tempo".*?</section>#s', $r['body'], $m) ? $m[0] : ''), 'class="risposta"')
      && substr_count($tempo, 'class="msg"') === 3);
prova('La frase sul valore, da sola', str_contains($r['body'], 'class="frase"') && str_contains($r['body'], 'È nel tempo che puoi dedicare ad altro.'));
prova('Guadagno: il commiato disegnato con le etichette vere della guida', str_contains($r['body'], 'class="congedo-mock"')
      && str_contains($r['body'], 'Ti è piaciuto il soggiorno?') && str_contains($r['body'], 'La prossima volta prenota da noi'));
prova('FAQ: chi non trova la risposta può scrivere (WhatsApp ed email dal config)', preg_match('#id="domande".*?href="https://wa\.me/393920061600".*?href="mailto:info@myhousewelcome\.it"#s', $r['body']) === 1);
prova('Chiusura: CTA e «chi c\'è dietro» nella stessa fascia, dopo i piani', preg_match('#<section class="chiusura".*?Crea gratis la tua guida.*?<aside class="chi"#s', $r['body']) === 1
      && strpos($r['body'], 'id="piani"') < strpos($r['body'], 'class="chiusura"'));
prova('Menu del sito: c\'è «Domande»', str_contains($r['body'], '/#domande'));
$js = (string) @file_get_contents("$DOVE/assets/landing.js");
prova('Animazioni: si attivano prima del disegno e mai con «riduci movimento»; senza landing.js entro 3 s la pagina torna ferma',
      str_contains($r['body'], "d.classList.add('anima')") && str_contains($r['body'], 'prefers-reduced-motion: reduce') && str_contains($r['body'], 'anima-pronta')
      && str_contains($js, 'prefers-reduced-motion: reduce') && str_contains($js, 'IntersectionObserver'));
prova('…le comparse sono nel CSS solo sotto html.anima: senza JavaScript non si nasconde niente',
      preg_match('/^\.anima :is\(/m', (string) file_get_contents("$DOVE/assets/app.css")) === 1 && !preg_match('/^\.comparsa\{[^}]*opacity:0/m', (string) file_get_contents("$DOVE/assets/app.css")));
prova('Scene: ognuna porta dove se ne parla (QR, demo, Come funziona)', substr_count($r['body'], 'class="scena__link"') === 3
      && str_contains($r['body'], 'class="scena__link" href="#qr"') && str_contains($r['body'], 'class="scena__link" href="#come-funziona"'));
prova('Portfolio: − e + accessibili (script, con etichetta)', str_contains((string) file_get_contents("$DOVE/assets/prezzi.js"), "'Una struttura in più'")
      && str_contains((string) file_get_contents("$DOVE/assets/prezzi.js"), "'Una struttura in meno'"));

// ============================================================ PANNELLO RIDISEGNATO
capitolo('Pannello ridisegnato');
$luc = new Browser('lucia-pannello');
$luc->modulo('/accedi', '/accedi', ['email' => 'lucia@esempio.it', 'password' => 'dimostrazione1']);
$r = $luc->get('/pannello');
prova('Barra laterale con le voci dell\'account, quella attiva segnata', $r['code'] === 200 && str_contains($r['body'], '<aside class="lato"')
      && preg_match('#class="lato__voce on" href="[^"]*/pannello" aria-current="page"#', $r['body']) === 1 && str_contains($r['body'], 'Account &amp; Fatturazione'));
prova('…con il saluto e i numeri veri (online, da finire, piano)', str_contains($r['body'], 'Ciao, Lucia.') && substr_count($r['body'], 'class="cifra ') + substr_count($r['body'], 'class="cifra"') >= 3);
prova('…«Vai al contenuto» per la tastiera, e il cassetto per il telefono', str_contains($r['body'], 'href="#contenuto"') && str_contains($r['body'], 'id="contenuto"')
      && str_contains($r['body'], 'class="cassetto"') && str_contains($r['body'], 'aria-label="Chiudi il menu"'));
$lpid = (int) val("SELECT id FROM properties WHERE account_id = ? AND name = 'Casa Lucia'", [$accDi('lucia@esempio.it')]);
$r = $luc->get("/pannello/$lpid/aspetto");
prova('Dentro una guida: il gruppo della guida nella barra, briciole e schede per il telefono', str_contains($r['body'], 'aria-label="La guida"')
      && preg_match('#class="lato__voce on" href="[^"]*/pannello/' . $lpid . '/aspetto" aria-current="page"#', $r['body']) === 1
      && str_contains($r['body'], 'aria-label="Sei qui"') && str_contains($r['body'], '<span aria-current="page">Aspetto</span>') && str_contains($r['body'], 'class="schede-guida"'));
$r = $luc->get("/pannello/$lpid/statistiche");
prova('Statistiche: l\'anello QR / link con il testo per chi non vede il grafico', preg_match('#class="anello__cerchio" style="--p:\d+" role="img" aria-label="\d+ aperture: \d+ dal QR, \d+ dal link"#', $r['body']) === 1);
$r = $ospite->get('/accedi');
prova('Senza accesso niente barra laterale', $r['code'] === 200 && !str_contains($r['body'], '<aside class="lato"'));

// ================================================================= FASE 6
capitolo('Fase 6A · campi in linea, silenzio, dotazioni, tipologia');
prova('6A · tassonomie nelle 5 lingue, unite al dizionario', trim((string) shell_exec('php -r ' . escapeshellarg('define("MHW_APP", "' . $DOVE . '/app"); spl_autoload_register(fn($c) => require "' . $DOVE . '/app/src/" . substr($c, 4) . ".php"); echo MHW\I18n::t("de", "cat.museum"), "|", MHW\I18n::t("es", "amen_pool"), "|", MHW\I18n::t("it", "kind.shop");'))) === 'Museum|Piscina|Negozi e spesa');
$sez = fn(string $k) => (int) val('SELECT id FROM sections WHERE property_id = ? AND kind = ?', [$lpid, $k]);
$r = $luc->get("/pannello/$lpid/sezioni/" . $sez('wifi'));
prova('6A · Wi-Fi in linea: nome rete e password affiancati, zona in fondo solo con più reti, «Mostra»', $r['code'] === 200
      && preg_match('#rip__c rip__c--w6"><div class="field"[^>]*><label[^>]*>Nome della rete#', $r['body']) === 1
      && str_contains($r['body'], 'class="rip__c rip__c--w12" data-rip-solo-piu') && str_contains($r['body'], 'data-segreto') && str_contains($r['body'], 'data-mostra-segreto')
      && str_contains($r['body'], '<span class="rip__nome">Rete <span data-rip-num>1</span></span>'));
prova('6A · campi delle righe senza compilazione automatica dei gestori di password', str_contains($r['body'], 'autocomplete="off" data-lpignore="true" data-1p-ignore'));
$regole = $sez('rules');
$r = $luc->get("/pannello/$lpid/sezioni/$regole");
prova('6A · orario del silenzio: interruttore acceso sulle sezioni di prima (c\'erano gli orari), «Dalle» e «Alle» sulla stessa riga', $r['code'] === 200
      && preg_match('#<input type="checkbox" role="switch" name="quiet_on" value="1" checked#', $r['body']) === 1 && str_contains($r['body'], 'class="grid grid-2 campi-legati"'));
$luc->post("/pannello/$lpid/sezioni/$regole", ['quiet_on' => '', 'quiet_from' => '22:00', 'quiet_to' => '08:00']);
$rd = json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$regole]), true);
prova('6A · spento: i due orari si svuotano al salvataggio', ($rd['quiet_on'] ?? null) === '' && ($rd['quiet_from'] ?? null) === '' && ($rd['quiet_to'] ?? null) === '');
$r = $luc->get("/pannello/$lpid/sezioni/$regole");
prova('6A · …e riaprendo, l\'interruttore è spento con gli orari consigliati pronti', preg_match('#name="quiet_on" value="1"\s+aria#', $r['body']) === 1 && str_contains($r['body'], 'value="22:00"') && str_contains($r['body'], 'value="08:00"'));
$luc->post("/pannello/$lpid/sezioni/$regole", ['quiet_on' => '1', 'quiet_from' => '23:00', 'quiet_to' => '07:30']);
$rd = json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$regole]), true);
prova('6A · acceso: gli orari restano', ($rd['quiet_on'] ?? null) === '1' && ($rd['quiet_from'] ?? null) === '23:00' && ($rd['quiet_to'] ?? null) === '07:30');
$serv = $sez('services');
if (!$serv) { $luc->post("/pannello/$lpid/sezioni", ['kind' => 'services']); $serv = $sez('services'); }
$r = $luc->get("/pannello/$lpid/sezioni/$serv");
prova('6A · dotazioni a gruppi e «Le tue dotazioni» come pillole', str_contains($r['body'], '<p class="dotazioni__gruppo">Esterni</p>') && str_contains($r['body'], 'class="rows pillole-campo"')
      && str_contains($r['body'], '+ Aggiungi una dotazione') && str_contains($r['body'], 'value="pool"'));
$luc->post("/pannello/$lpid/sezioni/$serv", ['amenities' => ['', 'pool', 'washer'], 'items' => ['Giochi da tavolo', '']]);
$r = $luc->get("/pannello/$lpid/anteprima/$serv");
prova('6A · nella guida dotazioni spuntate e scritte a mano in un solo elenco', $r['code'] === 200 && preg_match('#<ul class="dotazioni-ospite">.*?Piscina.*?Giochi da tavolo.*?</ul>#s', $r['body']) === 1
      && !str_contains($r['body'], 'Altre dotazioni'));
$r = $luc->get("/pannello/$lpid/impostazioni");
prova('6A · tipologia: Appartamento e Villa o casale, niente «Non indicata», campo per «Altro»', $r['code'] === 200 && str_contains($r['body'], 'Villa o casale')
      && str_contains($r['body'], 'value="appartamento"') && !str_contains($r['body'], 'Non indicata') && str_contains($r['body'], 'name="property_type_other"')
      && str_contains($r['body'], 'placeholder="Area camper, glamping, ostello…"'));
$luc->post("/pannello/$lpid/impostazioni", ['name' => 'Casa Lucia', 'property_type' => 'altro', 'property_type_other' => 'Glamping']);
$tp = riga('SELECT property_type, property_type_other FROM properties WHERE id = ?', [$lpid]);
prova('6A · «Altro» con il suo testo (migrazione 015)', ($tp['property_type'] ?? '') === 'altro' && ($tp['property_type_other'] ?? '') === 'Glamping');
$luc->get("/pannello/$lpid/impostazioni");
$luc->post("/pannello/$lpid/impostazioni", ['name' => 'Casa Lucia', 'property_type' => 'villa', 'property_type_other' => 'Glamping']);
$tp = riga('SELECT property_type, property_type_other FROM properties WHERE id = ?', [$lpid]);
prova('6A · cambiando tipologia il testo di «Altro» si svuota', ($tp['property_type'] ?? '') === 'villa' && ($tp['property_type_other'] ?? 'x') === '');

capitolo('Fase 6B · luoghi: categorie ed etichette tradotte, Negozi e spesa');
$dslug = $demo['slug'];
$dsez = fn(string $k) => (int) val('SELECT id FROM sections WHERE property_id = ? AND kind = ?', [$demo['id'], $k]);
$per = [];
foreach (['it' => 'Perfetto per cena', 'en' => 'Perfect for dinner', 'de' => 'Perfekt zum Abendessen'] as $l => $atteso) {
    $r = $ospite->get("/g/$dslug/" . $dsez('eat') . "?l=$l");
    $per[$l] = $r['code'] === 200 && str_contains($r['body'], $atteso);
}
prova('6B · la guida demo: l\'etichetta cambia lingua da sola (it, en, de)', !in_array(false, $per, true), json_encode($per));
$r = $ospite->get("/g/$dslug/" . $dsez('shop') . '?l=de');
prova('6B · «Negozi e spesa» nella demo, con due negozi; categorie in tedesco', $dsez('shop') > 0 && $r['code'] === 200 && str_contains($r['body'], 'Alimentari da Rita')
      && str_contains($r['body'], 'Forno del Borgo') && str_contains($r['body'], 'Lebensmittelgeschäft'));
prova('6B · i luoghi della demo hanno le chiavi, senza testo da tradurre', (int) val("SELECT COUNT(*) FROM places WHERE section_id = ? AND category_key <> '' AND badge_key <> ''", [$dsez('eat')]) === 3
      && (int) val("SELECT COUNT(*) FROM place_translations pt JOIN places pl ON pl.id = pt.place_id WHERE pl.section_id = ? AND (pt.category <> '' OR pt.badge <> '')", [$dsez('eat')]) === 0);
$luc->get("/pannello/$lpid");
$luc->post("/pannello/$lpid/sezioni", ['kind' => 'visit']);
$vis = $sez('visit');
$r = $luc->get("/pannello/$lpid/sezioni/$vis");
prova('6B · editor del luogo: pillole della sezione, «Altro…», «In evidenza» con «Nessuna» e «Personalizzata…», niente datalist', $r['code'] === 200
      && str_contains($r['body'], 'name="category_choice" value="museum"') && !str_contains($r['body'], 'value="restaurant"') && !str_contains($r['body'], 'value="trattoria"')
      && str_contains($r['body'], 'value="__altro" data-apre="pl-cat-box"') && str_contains($r['body'], '<legend>Etichetta')
      && str_contains($r['body'], 'Compare sulla scheda del luogo, in un bollino colorato.') && str_contains($r['body'], '<span>Personalizzata…</span>') && !str_contains($r['body'], '<datalist')
      && str_contains($r['body'], 'placeholder="Vai la mattina presto'));
$luc->post("/pannello/$lpid/sezioni/$vis/luogo", ['place_id' => '0', 'name' => 'Pinacoteca', 'category_choice' => 'museum', 'category' => 'scritto prima', 'badge_choice' => 'rainy', 'badge' => '']);
$pv = riga("SELECT * FROM places WHERE section_id = ? AND name = 'Pinacoteca'", [$vis]);
$pvt = riga('SELECT * FROM place_translations WHERE place_id = ?', [$pv['id'] ?? 0]);
prova('6B · scelta dall\'elenco: si salva la chiave e il testo si svuota', ($pv['category_key'] ?? '') === 'museum' && ($pv['badge_key'] ?? '') === 'rainy'
      && ($pvt['category'] ?? 'x') === '' && ($pvt['badge'] ?? 'x') === '');
$luc->post("/pannello/$lpid/sezioni/$vis/luogo", ['place_id' => (string) $pv['id'], 'name' => 'Pinacoteca', 'category_choice' => '__altro', 'category' => 'Collezione privata', 'badge_choice' => '__altra', 'badge' => 'Solo il sabato']);
$pv = riga('SELECT * FROM places WHERE id = ?', [$pv['id']]); $pvt = riga('SELECT * FROM place_translations WHERE place_id = ?', [$pv['id']]);
prova('6B · «Altro…» e «Personalizzata…»: si salva il testo e la chiave si svuota', $pv['category_key'] === '' && $pv['badge_key'] === ''
      && $pvt['category'] === 'Collezione privata' && $pvt['badge'] === 'Solo il sabato');
$r = $luc->get("/pannello/$lpid/lingue/en");
prova('6B · traduzioni: i luoghi con la chiave non chiedono la categoria, «Tradotta automaticamente»', $r['code'] === 200 && str_contains($r['body'], 'già tradotta in ogni lingua'));
prova('6B · Negozi e spesa nel catalogo, con la sua icona', str_contains((string) file_get_contents("$DOVE/app/src/SectionCatalog.php"), "'shop' => [") && str_contains((string) file_get_contents("$DOVE/app/src/Icon.php"), "'bag'"));

capitolo('Fase 6C · parcheggio, prezzi, muoversi in zona, home');
// Le conversioni, chiamate direttamente: costo del parcheggio, prezzo degli extra, vecchio elenco dei trasporti, istantanee del formato 5.
$codice = '<?php define("MHW_APP", ' . var_export("$DOVE/app", true) . '); spl_autoload_register(fn($c) => require MHW_APP . "/src/" . substr($c, 4) . ".php");
use MHW\{Conversione, Guide};
$o = [];
[$d, $t] = Conversione::sezione("parking", ["options" => [["id" => "r1", "type" => "pagamento"], ["id" => "r2", "type" => ""]]],
    ["it" => ["options" => [["id" => "r1", "cost" => "2 euro all\'ora, gratis la notte"], ["id" => "r2", "cost" => "da chiedere"]]],
     "en" => ["options" => [["id" => "r1", "cost" => "€2/hour, free at night"], ["id" => "r2", "cost" => "ask us"]]]], "it");
$o[] = $d["options"][0]["cost_hour"] === "2" && $d["options"][0]["cost_day"] === "" && $t["it"]["options"][0]["cost_note"] === "gratis la notte"
    && $t["en"]["options"][0]["cost_note"] === "free at night" && $d["options"][1]["cost_hour"] === "" && $t["it"]["options"][1]["cost_note"] === "da chiedere" && $t["en"]["options"][1]["cost_note"] === "ask us";
$o[] = Conversione::sezione("parking", $d, $t, "it") === [$d, $t];
[$d, $t] = Conversione::sezione("extras", ["items" => [["id" => "r1"], ["id" => "r2"], ["id" => "r3"]]],
    ["it" => ["items" => [["id" => "r1", "price" => "25 € a tratta"], ["id" => "r2", "price" => "12,5 €"], ["id" => "r3", "price" => "da concordare"]]],
     "en" => ["items" => [["id" => "r1", "price" => "€25 per trip"], ["id" => "r3", "price" => "to be agreed"]]]], "it");
$o[] = $d["items"][0]["amount"] === "25" && $d["items"][0]["unit"] === "per_trip" && $d["items"][1]["amount"] === "12,50" && $d["items"][1]["unit"] === ""
    && $d["items"][2]["amount"] === "" && $t["it"]["items"][2]["price_note"] === "da concordare" && $t["en"]["items"][1]["price_note"] === "to be agreed" && $t["en"]["items"][0]["price_note"] === "";
[$d, $t] = Conversione::sezione("transport", [], ["it" => ["items" => ["Autobus ogni ora", "Taxi in piazza"]], "en" => ["items" => ["Hourly bus", "Taxi in the square"]]], "it");
$o[] = count($d["options"]) === 2 && $d["options"][0]["type"] === "other" && $t["it"]["options"][1]["name"] === "Taxi in piazza" && $t["en"]["options"][0]["name"] === "Hourly bus"
    && $t["it"]["options"][0]["id"] === $d["options"][0]["id"] && $t["it"]["items"] === ["Autobus ogni ora", "Taxi in piazza"];
$snap = Guide::normalize(["format" => 5, "property" => ["default_locale" => "it"], "sections" => [["kind" => "extras", "data" => ["items" => [["id" => "r1"]]], "tr" => ["it" => ["data" => ["items" => [["id" => "r1", "price" => "8 € a persona"]]]]]]]]);
$o[] = $snap["format"] === Guide::FORMAT && Guide::FORMAT === 6 && $snap["sections"][0]["data"]["items"][0]["amount"] === "8" && $snap["sections"][0]["data"]["items"][0]["unit"] === "per_person";
echo json_encode($o);';
$f = tempnam(sys_get_temp_dir(), 'c6'); file_put_contents($f, $codice);
$esitoConv = json_decode((string) shell_exec('php ' . escapeshellarg($f)), true); unlink($f);
prova('6C · costo del parcheggio: importo all\'ora e resto nella nota, in ogni lingua; il non riconosciuto intero nella nota', ($esitoConv[0] ?? false) === true, json_encode($esitoConv));
prova('6C · conversione idempotente', ($esitoConv[1] ?? false) === true);
prova('6C · prezzo extra: «25 € a tratta» → 25 + a tratta, «12,5 €» → 12,50, il resto in «Nota sul prezzo»', ($esitoConv[2] ?? false) === true);
prova('6C · muoversi: ogni riga del vecchio elenco diventa una scheda «Altro», nelle due lingue; la vecchia lista resta nel JSON', ($esitoConv[3] ?? false) === true);
prova('6C · guide pubblicate in formato 5 lette nel formato 6', ($esitoConv[4] ?? false) === true);

$tr = $sez('transport');
$r = $luc->get("/pannello/$lpid/sezioni/$tr");
prova('6C · muoversi in zona: riquadro introduttivo, tipo a pillole (radio), righe pronte Taxi / Autobus / Noleggio bici', $r['code'] === 200
      && str_contains($r['body'], 'class="note note--quiet">Come ci si sposta durante il soggiorno') && str_contains($r['body'], 'type="radio" name="options[0][type]" value="bus" checked')
      && str_contains($r['body'], '<legend class="small">Tipo</legend>') && str_contains($r['body'], '+ Taxi o NCC') && str_contains($r['body'], '+ Noleggio bici')
      && str_contains($r['body'], '&quot;type&quot;:&quot;taxi&quot;') && str_contains($r['body'], 'Orari, biglietti, costi'));
$dt = json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$tr]), true);
$luc->post("/pannello/$lpid/sezioni/$tr", ['options' => [
    ['id' => $dt['options'][0]['id'], 'type' => 'taxi', 'name' => 'Taxi dalla stazione', 'phone' => '+39 0742 000001', 'url' => 'https://example.org/taxi', 'where' => '', 'note' => 'Prenota il giorno prima.'],
    ['id' => '', 'type' => 'nave', 'name' => 'Riga con un tipo inventato', 'phone' => '', 'url' => '', 'where' => '', 'note' => '']]]);
$dt = json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$tr]), true);
prova('6C · un tipo fuori elenco non passa', ($dt['options'][1]['type'] ?? 'x') === '' && $dt['options'][0]['type'] === 'taxi', json_encode($dt));
$r = $luc->get("/pannello/$lpid/anteprima/$tr");
prova('6C · guida: sottotitolo, una scheda per voce con «Chiama» e «Visita il sito»', $r['code'] === 200 && str_contains($r['body'], 'Come spostarsi durante il soggiorno.')
      && str_contains($r['body'], 'Taxi dalla stazione') && str_contains($r['body'], 'href="tel:+390742000001"') && str_contains($r['body'], 'href="https://example.org/taxi"')
      && str_contains($r['body'], 'Visita il sito') && str_contains($r['body'], 'Taxi o NCC'));
$r = $ospite->get("/g/$lslug/$tr?l=en");
prova('6C · guida pubblicata (demo): «Muoversi in zona» in inglese, con il sottotitolo', $r['code'] === 200 && str_contains($r['body'], 'Bus to Assisi and Foligno')
      && str_contains($r['body'], 'How to get around during your stay.'));
$ex = $sez('extras');
$r = $luc->get("/pannello/$lpid/sezioni/$ex");
prova('6C · servizi extra: importo con «€» fisso e unità a tendina (a tratta)', $r['code'] === 200 && str_contains($r['body'], 'class="soldi"') && str_contains($r['body'], 'class="soldi__euro" aria-hidden="true">€</span>')
      && str_contains($r['body'], '<span class="sr-only"> (euro)</span>') && str_contains($r['body'], '<option value="per_trip" selected>a tratta</option>') && str_contains($r['body'], 'Nota sul prezzo'));
if (!$sez('arrival')) $luc->post("/pannello/$lpid/sezioni", ['kind' => 'arrival']);
$r = $luc->get("/pannello/$lpid/sezioni/" . $sez('arrival'));
prova('6C · come arrivare: riquadro che rimanda a «Muoversi in zona»', $r['code'] === 200 && str_contains($r['body'], 'Gli spostamenti durante il soggiorno vanno in «Muoversi in zona».'));
$r = $ospite->get('/');
prova('6C · home: tutte le sezioni del catalogo nei tre gruppi, contate', $r['code'] === 200 && str_contains($r['body'], '16 sezioni pronte da compilare, più le sezioni libere')
      && !str_contains($r['body'], 'in base al piano') && str_contains($r['body'], 'id="gruppo-casa">La casa</h3>') && str_contains($r['body'], 'id="gruppo-arrivo">Arrivare e muoversi</h3>')
      && str_contains($r['body'], 'id="gruppo-territorio">Il territorio</h3>') && str_contains($r['body'], 'Muoversi in zona') && str_contains($r['body'], 'Negozi e spesa'));

capitolo('Fase 6D · sezione libera, righe compresse, messaggio di benvenuto');
$luc->get("/pannello/$lpid");
$luc->post("/pannello/$lpid/sezioni", ['kind' => 'custom']);
$r = $luc->get("/pannello/$lpid");
prova('S1 · aggiunta una sezione libera, e il catalogo la propone ancora', (int) val("SELECT COUNT(*) FROM sections WHERE property_id = ? AND kind = 'custom'", [$lpid]) === 1
      && str_contains($r['body'], '<b>Sezione libera</b>') && str_contains($r['body'], 'Aggiungi<span class="sr-only"> Sezione libera</span>'));
$luc->post("/pannello/$lpid/sezioni", ['kind' => 'custom']);
$liberi = array_column(righe("SELECT id FROM sections WHERE property_id = ? AND kind = 'custom' ORDER BY id", [$lpid]), 'id');
prova('S1 · …si aggiunge più volte', count($liberi) === 2);
$r = $luc->get("/pannello/$lpid/sezioni/{$liberi[0]}");
prova('S1 · editor: riquadro, 12 icone (radio con il disegno), testo ed elenco', $r['code'] === 200 && str_contains($r['body'], 'Per quello che non sta nelle altre sezioni')
      && substr_count($r['body'], 'type="radio" name="icona"') === 12 && str_contains($r['body'], 'name="icona" value="star" checked') && str_contains($r['body'], '>Testo</label>'));
$luc->post("/pannello/$lpid/sezioni/{$liberi[0]}", ['title' => 'La piscina', 'icona' => 'sun', 'text' => 'Aperta da giugno a settembre.', 'items' => ['Doccia prima di entrare', 'Niente vetro a bordo vasca']]);
$luc->post("/pannello/$lpid/sezioni/{$liberi[1]}", ['title' => '', 'icona' => 'razzo', 'text' => 'Una seconda sezione.']);
$dl = json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$liberi[0]]), true);
$dl2 = json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$liberi[1]]), true);
prova('S1 · icona salvata; una fuori elenco non passa', ($dl['icona'] ?? '') === 'sun' && ($dl2['icona'] ?? 'x') === '');
$r = $luc->get("/pannello/$lpid/anteprima/{$liberi[0]}");
$r2 = $luc->get("/pannello/$lpid/anteprima/{$liberi[1]}?l=de");
prova('S1 · guida: titolo dell\'host, testo ed elenco; senza titolo «Weitere Informationen» in tedesco', $r['code'] === 200 && str_contains($r['body'], 'La piscina')
      && str_contains($r['body'], 'Aperta da giugno a settembre.') && str_contains($r['body'], '<li>Niente vetro a bordo vasca</li>') && str_contains($r2['body'], 'Weitere Informationen'));
$r = $luc->get("/pannello/$lpid");
$sole = '<svg' ; $iconaSole = (string) shell_exec('php -r ' . escapeshellarg('define("MHW_APP", "' . $DOVE . '/app"); spl_autoload_register(fn($c) => require "' . $DOVE . '/app/src/" . substr($c, 4) . ".php"); echo MHW\SectionCatalog::iconaDi("custom", ["icona" => "sun"]), "|", MHW\SectionCatalog::iconaDi("custom", "{}"), "|", MHW\SectionCatalog::iconaDi("wifi", ["icona" => "sun"]);'));
prova('S1 · l\'icona scelta vale solo per la sezione libera', trim($iconaSole) === 'sun|star|wifi', $iconaSole);
foreach ($liberi as $x) $luc->post("/pannello/$lpid/sezioni/$x/azione", ['fai' => 'elimina']);
$r = $luc->get("/pannello/$lpid/sezioni/" . $sez('extras'));
prova('X2 · righe salvate compresse (con JavaScript), con il riepilogo apribile; la riga vuota no', $r['code'] === 200
      && preg_match('#<div class="rip__riga" data-rip-riga data-rip-chiusa>#', $r['body']) === 1
      && str_contains($r['body'], 'class="rip__apri" data-rip-apri aria-expanded="true" aria-controls="rip-items-0-campi" hidden')
      && str_contains($r['body'], 'class="rip__campi" id="rip-items-0-campi"') && preg_match('#data-rip-riga data-rip-vuota>#', $r['body']) === 1);
$r = $luc->get("/pannello/$lpid/qr");
prova('M2 · QR & Link: messaggio di benvenuto per lingua, col link nella lingua giusta, «Copia» e «Apri WhatsApp»', $r['code'] === 200
      && str_contains($r['body'], 'Messaggio di benvenuto') && preg_match('#id="benvenuto-it"[^>]*>Ciao! Benvenuti a Casa Lucia\.#', $r['body']) === 1
      && preg_match('#id="benvenuto-en"[^>]*>Hello! Welcome to Casa Lucia\.[^<]*/g/[a-z0-9-]+\?l=en#', $r['body']) === 1
      && str_contains($r['body'], 'data-copia-da="benvenuto-de"') && str_contains($r['body'], 'href="https://wa.me/?text=Ciao%21%20Benvenuti%20a%20Casa%20Lucia.'));
$r = $ospite->get('/');
prova('6D · la home conta ancora le sezioni pronte (la libera no)', str_contains($r['body'], '16 sezioni pronte da compilare'));

capitolo('Fase 6G · eventi');
$evSid = $sez('events');
$lslug6 = (string) val('SELECT slug FROM properties WHERE id = ?', [$lpid]);
$r = $ospite->get("/g/$lslug6/$evSid");
prova('6G · guida demo: «In questi giorni» con l\'evento di oggi e quello tra 5 giorni, «Ogni settimana» col mercato', $evSid > 0 && $r['code'] === 200
      && str_contains($r['body'], 'In questi giorni') && str_contains($r['body'], 'Concerto in piazza') && str_contains($r['body'], 'Festa delle infiorate')
      && str_contains($r['body'], 'Ogni settimana') && str_contains($r['body'], 'Mercato del sabato') && str_contains($r['body'], 'class="evento__data"')
      && str_contains($r['body'], 'Consigliato da Lucia') && str_contains($r['body'], 'data-filtro="Musica"'));
$r = $ospite->get("/g/$lslug6/$evSid?l=de");
prova('6G · ?l=de: categoria, gruppo e prezzo in tedesco', str_contains($r['body'], 'In diesen Tagen') && str_contains($r['body'], 'Musik') && str_contains($r['body'], 'Kostenlos'));
$r = $ospite->get("/g/$lslug6");
prova('6G · home della guida: la fascia «Oggi» e il numero sulla casella', preg_match('#class="oggi-eventi"[^>]*>.*?Oggi.*?Concerto in piazza#s', $r['body']) === 1
      && str_contains($r['body'], 'class="tile__conta">2 in questi giorni<'));
$evRighe = json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$evSid]), true)['events'] ?? [];
$ics = $ospite->get("/g/$lslug6/evento/{$evRighe[1]['id']}.ics");
$icsRic = $ospite->get("/g/$lslug6/evento/{$evRighe[2]['id']}.ics");
prova('6G · .ics: comincia con BEGIN:VCALENDAR; per un evento ricorrente niente file', $ics['code'] === 200 && str_starts_with($ics['body'], 'BEGIN:VCALENDAR')
      && str_contains(intestazione($ics, 'Content-Type'), 'text/calendar') && $icsRic['code'] === 404);
$r = $luc->get("/pannello/$lpid/sezioni/$evSid");
prova('6G · editor: stato degli eventi, «Quando» a pillole con «Un giorno» per la riga nuova, locandina immagine o PDF', $r['code'] === 200
      && str_contains($r['body'], 'badge badge--pine rip__stato">In corso<') && str_contains($r['body'], 'rip__stato">Tra 5 giorni<') && str_contains($r['body'], 'rip__stato">Ricorrente<')
      && preg_match('#name="events\[3\]\[when\]" value="day" checked#', $r['body']) === 1 && str_contains($r['body'], 'accept="image/jpeg,image/png,image/webp,application/pdf"')
      && str_contains($r['body'], 'data-solo-con="when" data-solo-valori="weekly"') && str_contains($r['body'], 'Gli eventi passati spariscono da soli dalla guida'));
// Un evento passato che torna ogni anno, e uno tra 90 giorni.
$g = fn(int $n) => (new DateTimeImmutable('now', new DateTimeZone('Europe/Rome')))->modify(($n >= 0 ? '+' : '') . $n . ' days')->format('Y-m-d');
$righePost = [];
foreach ($evRighe as $i => $ev) $righePost[] = $ev + ['name' => ['Concerto in piazza', 'Festa delle infiorate', 'Mercato del sabato'][$i]];
$righePost[] = ['id' => '', 'name' => 'Palio dell\'anno scorso', 'cat' => 'history', 'when' => 'day', 'date_from' => $g(-30), 'yearly' => '1'];
$righePost[] = ['id' => '', 'name' => 'Rassegna d\'autunno', 'cat' => 'theatre', 'when' => 'day', 'date_from' => $g(90)];
$luc->post("/pannello/$lpid/sezioni/$evSid", ['events' => $righePost]);
$r = $luc->get("/pannello/$lpid/sezioni/$evSid");
$palio = array_values(array_filter(json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$evSid]), true)['events'], fn($x) => ($x['date_from'] ?? '') === $g(-30)))[0] ?? [];
prova('6G · un evento passato e annuale: «Passato: nascosto» e «Ripeti nel …»', str_contains($r['body'], 'rip__stato">Passato: nascosto<')
      && str_contains($r['body'], 'name="ripeti" value="' . ($palio['id'] ?? 'x') . '">Ripeti nel ' . ((int) substr($g(-30), 0, 4) + 1) . '<'));
$rp = $ospite->get("/pannello/$lpid/anteprima/$evSid");
$rp = $luc->get("/pannello/$lpid/anteprima/$evSid");
prova('6G · l\'evento passato non si vede nella guida; quello tra 90 giorni è in «Più avanti»', !str_contains($rp['body'], 'Palio dell') && str_contains($rp['body'], 'Più avanti') && str_contains($rp['body'], 'Rassegna d&#039;autunno'));
$righePost2 = [];
foreach (json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$evSid]), true)['events'] as $ev) $righePost2[] = $ev + ['name' => 'x'];
$r = $luc->post("/pannello/$lpid/sezioni/$evSid", ['ripeti' => $palio['id'] ?? '']);
$dopo = array_values(array_filter(json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$evSid]), true)['events'], fn($x) => ($x['id'] ?? '') === ($palio['id'] ?? '')))[0] ?? [];
prova('6G · «Ripeti»: date spostate all\'anno dopo, avviso', ($dopo['date_from'] ?? '') > $g(0) && substr((string) ($dopo['date_from'] ?? ''), 5) === substr($g(-30), 5)
      && str_contains($luc->get("/pannello/$lpid/sezioni/$evSid")['body'], 'controllale, carica la nuova locandina e pubblica'), json_encode($dopo));
// Solo un evento tra 90 giorni: niente casella nella home della guida.
$solo = array_values(array_filter(json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$evSid]), true)['events'], fn($x) => ($x['date_from'] ?? '') === $g(90)));
$luc->post("/pannello/$lpid/sezioni/$evSid", ['events' => [['id' => $solo[0]['id'] ?? '', 'name' => 'Rassegna d\'autunno', 'cat' => 'theatre', 'when' => 'day', 'date_from' => $g(90)]]]);
$r = $luc->get("/pannello/$lpid/anteprima");
prova('6G · con il solo evento tra 90 giorni la casella non c\'è', $r['code'] === 200 && !str_contains($r['body'], "/anteprima/$evSid?") && !str_contains($r['body'], 'class="oggi-eventi"'));
$esitoR = trim((string) shell_exec('php -r ' . escapeshellarg('define("MHW_APP", "' . $DOVE . '/app"); spl_autoload_register(fn($c) => require "' . $DOVE . '/app/src/" . substr($c, 4) . ".php"); echo isset(MHW\Richiami::TIPI["eventi"]) ? "ok" : "no";')));
prova('6G · promemoria «eventi» tra i richiami (con il «non mandarmi più»)', $esitoR === 'ok');

capitolo('Sito: chi è già registrato, guida vetrina');
$r = $ospite->get('/');
prova('Fuori: «Accedi» anche nella testata del telefono, nessuna fascia di bentornato', $r['code'] === 200
      && preg_match('#<div class="topbar__telefono">\s*(?:<\?php[^>]*>)?\s*<a class="btn btn--ghost btn--sm" href="[^"]*/accedi">Accedi</a>#', $r['body']) === 1
      && !str_contains($r['body'], 'class="bentornato"') && !str_contains($r['body'], 'class="conto'));
$r = $luc->get('/');
prova('Dentro: testata con iniziale e nome, menu dell\'account con «Esci»', $r['code'] === 200 && str_contains($r['body'], '<span class="conto__nome">Lucia</span>')
      && str_contains($r['body'], 'aria-label="Il tuo account: lucia@esempio.it"') && substr_count($r['body'], 'class="conto__avatar" aria-hidden="true">L<') === 2
      && preg_match('#<form method="post" action="[^"]*/esci"><input type="hidden"[^>]*><button type="submit" class="conto__esci">#', $r['body']) === 1);
prova('Dentro: in home la fascia «Ciao, Lucia.» con le guide e lo stato', str_contains($r['body'], 'class="bentornato"') && str_contains($r['body'], 'Ciao, Lucia.</h2>')
      && str_contains($r['body'], 'Sei dentro come <b>lucia@esempio.it</b>') && preg_match('#class="bentornato__guida" href="[^"]*/pannello/' . $lpid . '">.*?Casa Lucia.*?badge badge--pine">Online<#s', $r['body']) === 1
      && str_contains($r['body'], 'Vai alle tue guide') && !str_contains($r['body'], 'Crea gratis la tua guida'));
$ag = new Browser('agenzia');
$ag->get('/registrati');
$r = $ag->post('/registrati', ['name' => 'Agenzia Prova', 'email' => 'blackout.agency@gmail.com', 'password' => 'AgenziaProva123', 'termini' => '1']);
$aacc = (int) val("SELECT a.id FROM accounts a JOIN users u ON u.id = a.user_id WHERE u.email = 'blackout.agency@gmail.com'");
$r = $ag->get('/');
preg_match('#note--err" role="alert">([^<]*)#', $r['body'], $em);
prova('Registrato senza guide: «Non hai ancora una guida» e «Crea la tua prima guida»', $aacc > 0 && str_contains($r['body'], 'Ciao, Agenzia.')
      && str_contains($r['body'], 'Non hai ancora una guida.') && str_contains($r['body'], 'Crea la tua prima guida'), ($em[1] ?? '') . ' ' . $aacc);
$r = $admin->get("/admin/cliente/$aacc");
prova('Amministrazione: riquadro «Guida vetrina», con Plus dimostrativo proposto (account senza piano)', $r['code'] === 200 && str_contains($r['body'], 'Crea la guida vetrina')
      && str_contains($r['body'], 'name="plus" value="1" checked'));
$r = $admin->post("/admin/cliente/$aacc/vetrina", ['plus' => '1']);
$vet = riga("SELECT * FROM properties WHERE account_id = ? AND name = 'Casa Checco'", [$aacc]);
prova('Vetrina creata e pubblicata come demo (is_demo = 2), con Plus dimostrativo', $vet && (int) $vet['is_demo'] === 2 && $vet['status'] === 'published'
      && (bool) val("SELECT 1 FROM subscriptions WHERE account_id = ? AND status = 'active'", [$aacc]), json_encode($vet));
$vslug = (string) ($vet['slug'] ?? '');
$r = $ospite->get("/g/$vslug");
$ren = $ospite->get("/g/$vslug/" . (int) val("SELECT id FROM sections WHERE property_id = ? AND is_core = 1", [$vet['id'] ?? 0]) . '?l=en');
prova('…la guida si apre, con «Demo», in italiano e in inglese; nomi diversi da Casa Lucia', $r['code'] === 200 && str_contains($r['body'], 'Casa Checco') && str_contains($r['body'], 'demo-tag')
      && !str_contains($r['body'], 'Lucia') && $ren['code'] === 200 && str_contains($ren['body'], 'Francesco will meet you there with the keys'));
$kindsV = array_column(righe('SELECT kind FROM sections WHERE property_id = ?', [$vet['id'] ?? 0]), 'kind');
$mancano = array_diff(['checkin', 'wifi', 'services', 'extras', 'rules', 'arrival', 'transport', 'parking', 'waste', 'eat', 'visit', 'todo', 'shop', 'events', 'custom', 'emergency', 'info'], $kindsV);
prova('…tutte le sezioni del catalogo compilate (eventi e sezione libera comprese), nessuna vuota', !$mancano
      && !val("SELECT 1 FROM sections WHERE property_id = ? AND is_core = 0 AND data = '{}'", [$vet['id'] ?? 0]), json_encode(array_values($mancano)));
$evV = (int) val("SELECT id FROM sections WHERE property_id = ? AND kind = 'events'", [$vet['id'] ?? 0]);
$r = $ospite->get("/g/$vslug/$evV");
prova('…eventi veri di Assisi: il mercato del sabato con la locandina, le feste dell\'anno con la prossima data, niente eventi di fantasia',
      str_contains($r['body'], 'Mercato del sabato') && str_contains($r['body'], 'Locandina: Mercato del sabato') && str_contains($r['body'], 'Calendimaggio')
      && str_contains($r['body'], 'Festa di San Francesco') && str_contains($r['body'], 'Festa del Perdono') && !str_contains($r['body'], 'Jazz sotto le volte'));
$evDati = json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$evV]), true);
$oggiV = (new DateTimeImmutable("now", new DateTimeZone("Europe/Rome")))->format("Y-m-d");
$dateV = array_filter(array_map(fn($e) => $e['date_to'] ?? $e['date_from'] ?? '', $evDati['events'] ?? []));
prova('…nessun evento della vetrina è già passato, e San Francesco cade il 3–4 ottobre', $dateV && min($dateV) >= $oggiV
      && (bool) array_filter($evDati['events'] ?? [], fn($e) => substr((string) $e['date_from'], 5) === '10-03' && substr((string) $e['date_to'], 5) === '10-04'));
prova('…con i luoghi (4 da mangiare, 10 da visitare, 5 da fare, 4 negozi)', (int) val("SELECT COUNT(*) FROM places pl JOIN sections s ON s.id = pl.section_id WHERE s.property_id = ?", [$vet['id'] ?? 0]) === 23);
$r = $ospite->get("/g/$vslug/" . (int) val("SELECT id FROM sections WHERE property_id = ? AND kind = 'parking'", [$vet['id'] ?? 0]));
$rc = $ospite->get("/g/$vslug/" . (int) val("SELECT id FROM sections WHERE property_id = ? AND is_core = 1", [$vet['id'] ?? 0]));
prova('…parcheggi e imposta di soggiorno con i dati veri di Assisi (2 € l\'ora, 14 € al giorno; 3 notti al massimo)', str_contains($r['body'], 'Parcheggio Mojano') && str_contains($r['body'], '14')
      && str_contains($rc['body'], '4 € a persona per notte') && str_contains($rc['body'], 'prime 3 notti'));
$arrV = json_decode((string) val("SELECT data FROM sections WHERE property_id = ? AND kind = 'arrival'", [$vet['id'] ?? 0]), true);
prova('…ad Assisi, geolocalizzata in Piazza Matteotti: coordinate della struttura e link di Maps', ($vet['city'] ?? '') === 'Assisi'
      && abs((float) val('SELECT lat FROM properties WHERE id = ?', [$vet['id'] ?? 0]) - 43.07025) < 0.0001 && abs((float) val('SELECT lng FROM properties WHERE id = ?', [$vet['id'] ?? 0]) - 12.61966) < 0.0001
      && str_contains((string) ($arrV['maps_url'] ?? ''), 'query=43.07025,12.61966'));
$r = $admin->post("/admin/cliente/$aacc/vetrina", ['plus' => '1']);
prova('…una sola vetrina per account', (int) val('SELECT COUNT(*) FROM properties WHERE account_id = ? AND is_demo = 2', [$aacc]) === 1);
$r = $ospite->get('/');
prova('La landing usa la vetrina come demo', str_contains($r['body'], 'Sfoglia la guida di Casa Checco') && str_contains($r['body'], "/g/$vslug/benvenuto"));
$ag->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'La mia casa vera', 'city' => 'Foligno']);
prova('La vetrina non occupa il posto del piano: il cliente crea la sua struttura (Plus, una struttura)', (bool) val("SELECT 1 FROM properties WHERE account_id = ? AND name = 'La mia casa vera'", [$aacc])
      && !str_contains($ag->get('/pannello')['body'], 'class="panel stack bloccata"'));

capitolo('Fase 6E · codici sconto del primo anno');
$oggiR = fn(int $n = 0) => (new DateTimeImmutable('now', new DateTimeZone('Europe/Rome')))->modify(($n >= 0 ? '+' : '') . $n . ' days')->format('Y-m-d');
$r = $admin->get('/admin/sconti');
prova('6E · Amministrazione → Codici sconto: voce di menu, cartellini, modulo con «Genera» e anteprima', $r['code'] === 200 && pulita($r)
      && str_contains($r['body'], '/admin/sconti" aria-current="page"') && str_contains($r['body'], 'Codici attivi') && str_contains($r['body'], 'data-genera')
      && preg_match('#name="code"[^>]*value="MHW-[A-HJ-NP-Z2-9]{6}"#', $r['body']) === 1 && str_contains($r['body'], 'data-anteprima-righe'));
$errori = [];
foreach ([[['code' => 'AB', 'value' => '20'], 'da 4 a 24 caratteri'], [['code' => 'TROPPO', 'kind' => 'percent', 'value' => '120'], 'da 1 a 100'],
          [['code' => 'DATESBAGLIATE', 'value' => '10', 'valid_from' => $oggiR(10), 'valid_until' => $oggiR(5)], 'prima di quella di inizio'],
          [['code' => 'IMPORTOALTO', 'kind' => 'amount', 'value' => '500'], 'minore del prezzo del piano meno caro']] as [$campi, $atteso]) {
    $admin->post('/admin/sconti', $campi + ['kind' => 'percent', 'valid_from' => $oggiR(), 'valid_until' => $oggiR(30), 'piani' => 'tutti']);
    $msg = $admin->get('/admin/sconti')['body'];
    if (!str_contains($msg, $atteso)) $errori[] = $atteso;
}
prova('6E · controlli del modulo: lunghezza, percentuale, date, importo', !$errori && !val("SELECT 1 FROM discount_codes WHERE code IN ('TROPPO','DATESBAGLIATE','IMPORTOALTO')"), implode(' | ', $errori));
$prima = count(richiesteStripe());
$admin->post('/admin/sconti', ['code' => 'benvenuto20', 'kind' => 'percent', 'value' => '20', 'valid_from' => $oggiR(), 'valid_until' => $oggiR(60), 'max_uses' => '100', 'piani' => 'tutti', 'note' => 'Prova']);
$bv = riga("SELECT * FROM discount_codes WHERE code = 'BENVENUTO20'");
$coupon = array_values(array_filter(array_slice(richiesteStripe(), $prima), fn($x) => $x['percorso'] === '/v1/coupons'))[0] ?? [];
prova('6E · codice creato (maiuscolo) e coupon su Stripe: una volta sola, 20%, scadenza, limite', $bv && $bv['stripe_coupon_id'] !== ''
      && ($coupon['corpo']['duration'] ?? '') === 'once' && ($coupon['corpo']['percent_off'] ?? '') === '20' && ($coupon['corpo']['max_redemptions'] ?? '') === '100'
      && (int) ($coupon['corpo']['redeem_by'] ?? 0) > time() + 59 * 86400 && ($coupon['corpo']['name'] ?? '') === 'BENVENUTO20', json_encode($coupon));
$r = $admin->get('/admin/sconti');
prova('6E · in elenco: −20%, 0 / 100, Attivo, «Copia il link»', str_contains($r['body'], "\u{2212}20%") && str_contains($r['body'], '0 / 100')
      && str_contains($r['body'], 'badge--pine">Attivo<') && str_contains($r['body'], '/?codice=BENVENUTO20'));
// Codici che non valgono, scritti direttamente: scaduto, futuro, esaurito, di un altro piano, non sincronizzato.
$cid = fn(string $c, array $x) => db()->prepare('INSERT INTO discount_codes (code, kind, value, valid_from, valid_until, max_uses, packages, note, active, stripe_coupon_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)')
       ->execute([$c, 'percent', 10, $x[0], $x[1], $x[2] ?? 0, $x[3] ?? '', '', $x[4] ?? 'coupon_prova', gmdate('Y-m-d\TH:i:s\Z')]);
$cid('SCADUTO1', [$oggiR(-30), $oggiR(-1)]); $cid('FUTURO1', [$oggiR(5), $oggiR(30)]); $cid('ESAURITO1', [$oggiR(-1), $oggiR(30), 1]);
$cid('SOLOESS', [$oggiR(-1), $oggiR(30), 0, 'essential']); $cid('NONSYNC', [$oggiR(-1), $oggiR(30), 0, '', '']);
db()->prepare('INSERT INTO discount_redemptions (discount_code_id, account_id, order_id, discount_cents, created_at) VALUES (?, 0, -1, 100, ?)')->execute([(int) val("SELECT id FROM discount_codes WHERE code = 'ESAURITO1'"), gmdate('Y-m-d\TH:i:s\Z')]);
// Dora arriva da un link con il codice e si registra: il codice è già applicato.
$dora = new Browser('dora');
$r = $dora->get('/?codice=benvenuto20');
prova('6E · link con il codice: in home la fascia «Codice BENVENUTO20: −20% sul primo anno, fino al …»', str_contains($r['body'], 'class="sconto-fascia"')
      && str_contains($r['body'], "Codice <b>BENVENUTO20</b>:\n    \u{2212}20% sul primo anno, fino al " . (function ($ymd) { return (int) substr($ymd, 8, 2) . ' ' . ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'][(int) substr($ymd, 5, 2) - 1] . ' ' . substr($ymd, 0, 4); })($oggiR(60))));
$dora->get('/registrati?piano=' . pv('plus'));
$dora->post('/registrati', ['piano' => pv('plus'), 'name' => 'Dora Sconto', 'email' => 'dora@prova.test', 'password' => 'DoraProva1234', 'termini' => '1']);
$dacc = $accDi('dora@prova.test');
prova('6E · dopo la registrazione il codice è già applicato all\'account', (int) val('SELECT intended_discount_code_id FROM accounts WHERE id = ?', [$dacc]) === (int) $bv['id']);
$dora->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Casa Dora', 'city' => 'Matera']);
$dpid = (int) val("SELECT id FROM properties WHERE account_id = ? AND name = 'Casa Dora'", [$dacc]);
$r = $dora->get("/pannello/$dpid/procedura/pubblica");
prova('6E · passo «Pubblica»: prezzo pieno barrato, 93,60 € il primo anno, «Dal secondo anno 117 € + IVA», «Togli»', $r['code'] === 200 && pulita($r)
      && str_contains($r['body'], "<s class=\"muted\" style=\"font-size:18px\">117\u{00A0}€</s>") && str_contains($r['body'], "93,60\u{00A0}€")
      && str_contains($r['body'], "Dal secondo anno 117\u{00A0}€ + IVA.") && str_contains($r['body'], '/sconto/togli'));
$dora->post('/sconto/togli', ['torna' => "/pannello/$dpid/procedura/pubblica"]);
$r = $dora->get("/pannello/$dpid/procedura/pubblica");
prova('6E · «Togli»: il codice va via e torna «Hai un codice sconto?»', !val('SELECT intended_discount_code_id FROM accounts WHERE id = ?', [$dacc])
      && str_contains($r['body'], 'Hai un codice sconto?') && str_contains($r['body'], "117\u{00A0}€"));
// Le date si scrivono per esteso (regole del copy): «5 dicembre 2026».
$estesa = fn(string $ymd) => (int) substr($ymd, 8, 2) . ' ' . ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'][(int) substr($ymd, 5, 2) - 1] . ' ' . substr($ymd, 0, 4);
$motivi = [];
foreach (['NONESISTE' => 'Questo codice non esiste. Controlla di averlo scritto bene.', 'SCADUTO1' => 'Questo codice è scaduto il ' . $estesa($oggiR(-1)) . '.',
          'FUTURO1' => 'Questo codice sarà valido dal ' . $estesa($oggiR(5)) . '.', 'ESAURITO1' => 'Questo codice ha raggiunto il numero massimo di utilizzi.',
          'SOLOESS' => 'Questo codice non vale per il piano Plus.', 'NONSYNC' => 'Questo codice non è ancora utilizzabile'] as $c => $atteso) {
    $dora->post('/sconto/applica', ['codice' => $c, 'torna' => "/pannello/$dpid/procedura/pubblica"]);
    $p = $dora->get("/pannello/$dpid/procedura/pubblica")['body'];
    if (!str_contains($p, htmlspecialchars($atteso, ENT_QUOTES)) || !preg_match('#role="alert"[^>]*>' . preg_quote(htmlspecialchars(substr($atteso, 0, 20), ENT_QUOTES), '#') . '#', $p)
        || !str_contains($p, 'value="' . $c . '"')) $motivi[] = $c;
}
prova('6E · ogni codice che non vale col suo messaggio, sotto il campo (role="alert"), con il codice ancora scritto', !$motivi, implode(', ', $motivi));
$dora->post('/sconto/applica', ['codice' => 'benvenuto20', 'torna' => "/pannello/$dpid/procedura/pubblica"]);
prova('…e con il codice buono si applica (anche scritto minuscolo)', (int) val('SELECT intended_discount_code_id FROM accounts WHERE id = ?', [$dacc]) === (int) $bv['id']);
// Gino ha già pagato: con l'abbonamento scaduto vede di nuovo i piani, ma il codice non vale.
db()->prepare("UPDATE subscriptions SET status = 'canceled' WHERE account_id = ?")->execute([$gacc]);
$gino->post('/sconto/applica', ['codice' => 'BENVENUTO20', 'torna' => '/piano']);
$r = $gino->get('/piano');
db()->prepare("UPDATE subscriptions SET status = 'active' WHERE account_id = ? AND provider = 'stripe'")->execute([$gacc]);
prova('6E · chi ha già pagato un abbonamento: «I codici sconto valgono solo per il primo abbonamento.»', str_contains($r['body'], 'I codici sconto valgono solo per il primo abbonamento.'));
// Il pagamento con il codice.
$dora->get(substr($l = linkPosta('dora@prova.test', 'verifica'), strpos($l, '/verifica/')));
$dora->modulo('/account', '/account/fatturazione', ['billing_type' => 'privato', 'billing_name' => 'Dora Sconto', 'cf' => 'RSSMRA85T10A562S',
               'billing_address' => 'Via Ridola 1', 'billing_postal' => '75100', 'billing_city' => 'Matera', 'billing_province' => 'MT']);
$dcore = (int) val('SELECT id FROM sections WHERE property_id = ? AND is_core = 1', [$dpid]);
$dora->post("/pannello/$dpid/sezioni/$dcore", ['checkin_steps' => ['Il portone verde.']]);
$prima = count(richiesteStripe());
$r = $dora->modulo("/pannello/$dpid/procedura/pubblica", "/pannello/$dpid/pubblica", []);
$dord = riga('SELECT * FROM orders WHERE account_id = ? ORDER BY id DESC', [$dacc]);
$sess = array_values(array_filter(array_slice(richiesteStripe(), $prima), fn($x) => $x['percorso'] === '/v1/checkout/sessions'))[0] ?? [];
prova('6E · ordine col codice (importo pieno), checkout con discounts[0][coupon] e niente allow_promotion_codes', $dord && (int) $dord['discount_code_id'] === (int) $bv['id']
      && (int) $dord['amount_cents'] === 11700 && ($sess['corpo']['discounts'][0]['coupon'] ?? '') === $bv['stripe_coupon_id']
      && ($sess['corpo']['metadata']['discount_code'] ?? '') === 'BENVENUTO20' && !isset($sess['corpo']['allow_promotion_codes']), json_encode($sess['corpo'] ?? []));
$ev = ['id' => 'evt_dora_1', 'type' => 'checkout.session.completed', 'data' => ['object' => [
    'id' => $dord['provider_session_id'], 'mode' => 'subscription', 'payment_status' => 'paid', 'customer' => 'cus_dora', 'subscription' => 'sub_prova_dora',
    'client_reference_id' => (string) $dord['id'], 'total_details' => ['amount_discount' => 2340], 'metadata' => ['order_id' => (string) $dord['id'], 'account_id' => (string) $dacc]]]];
$r = inviaWebhook($ev);
$r2 = inviaWebhook(['id' => 'evt_dora_1b'] + $ev);
prova('6E · pagato: un utilizzo con lo sconto di Stripe (23,40 €), anche con il webhook consegnato due volte', $r['body'] === 'abbonamento-attivato'
      && (int) val('SELECT COUNT(*) FROM discount_redemptions WHERE order_id = ?', [$dord['id']]) === 1
      && (int) val('SELECT discount_cents FROM discount_redemptions WHERE order_id = ?', [$dord['id']]) === 2340
      && (int) val('SELECT discount_cents FROM orders WHERE id = ?', [$dord['id']]) === 2340 && !val('SELECT intended_discount_code_id FROM accounts WHERE id = ?', [$dacc]), $r['body'] . ' / ' . $r2['body']);
$r = $dora->get('/account');
prova('6E · «Account & Fatturazione»: «Sconto del primo anno: −23,40 € (BENVENUTO20). Rinnovo a prezzo pieno il …»', str_contains($r['body'], "Sconto del primo anno: <b>\u{2212}23,40\u{00A0}€</b> (BENVENUTO20).")
      && str_contains($r['body'], 'Rinnovo a prezzo pieno il'));
$r = $admin->get('/admin/sconti/' . $bv['id']);
prova('6E · dettaglio del codice: coupon su Stripe, utilizzi con cliente, piano e totale', $r['code'] === 200 && str_contains($r['body'], $bv['stripe_coupon_id'])
      && str_contains($r['body'], 'dora@prova.test') && str_contains($r['body'], '>Plus<') && str_contains($r['body'], "\u{2212}23,40\u{00A0}€"));
$prima = count(richiesteStripe());
$admin->post('/admin/sconti/' . $bv['id'] . '/disattiva', []);
prova('6E · disattivato: il coupon si cancella su Stripe, stato «Disattivato», nel registro', !(int) val('SELECT active FROM discount_codes WHERE id = ?', [$bv['id']])
      && (bool) array_filter(array_slice(richiesteStripe(), $prima), fn($x) => $x['metodo'] === 'DELETE' && str_contains($x['percorso'], '/v1/coupons/'))
      && (bool) val("SELECT 1 FROM audit_log WHERE action = 'discount.disable'") && (bool) val("SELECT 1 FROM audit_log WHERE action = 'discount.create'"));

// ================================================================= VETRINA AUTOMATICA
capitolo('Vetrina automatica e «Rifai la vetrina»');
$segnoVetrina = $DOVE . '/app/storage/vetrina-automatica.txt';
db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'register:%'");   // le prove registrano molti account dallo stesso indirizzo
$va = new Browser('vetrina-auto');
$va->get('/registrati');
$va->post('/registrati', ['name' => 'Agenzia Automatica', 'email' => 'vetrina-auto@prova.test', 'password' => 'VetrinaProva123', 'termini' => '1']);
$vaAcc = (int) val("SELECT a.id FROM accounts a JOIN users u ON u.id = a.user_id WHERE u.email = 'vetrina-auto@prova.test'");
prova('Prima di aprire la landing l\'account non ha la vetrina', $vaAcc > 0 && !val('SELECT id FROM properties WHERE account_id = ? AND is_demo = 2', [$vaAcc]));
$ospite->get('/');
$vaV = riga('SELECT * FROM properties WHERE account_id = ? AND is_demo = 2', [$vaAcc]);
prova('Alla prima apertura della landing Casa Checco si crea da sola nell\'account indicato, pubblicata e con Plus dimostrativo',
      $vaV && $vaV['name'] === 'Casa Checco' && $vaV['status'] === 'published' && is_file($segnoVetrina) && str_contains((string) file_get_contents($segnoVetrina), 'Casa Checco creata')
      && (bool) val("SELECT 1 FROM subscriptions WHERE account_id = ? AND status = 'active' AND provider = 'manuale'", [$vaAcc]), (string) @file_get_contents($segnoVetrina));
$ospite->get('/'); $admin->get('/admin');
prova('…una volta sola: le visite dopo non ne creano un\'altra', (int) val('SELECT COUNT(*) FROM properties WHERE account_id = ? AND is_demo = 2', [$vaAcc]) === 1);
$r = $admin->get("/admin/cliente/$vaAcc");
prova('Amministrazione: con la vetrina c\'è «Rifai la vetrina»', str_contains($r['body'], 'Rifai la vetrina') && str_contains($r['body'], 'name="rifai" value="1"'));
$admin->post("/admin/cliente/$vaAcc/vetrina", ['rifai' => '1']);
$vaV2 = riga('SELECT * FROM properties WHERE account_id = ? AND is_demo = 2', [$vaAcc]);
prova('«Rifai la vetrina»: la vecchia si toglie, la nuova è pubblicata, sempre una sola', $vaV2 && (int) $vaV2['id'] !== (int) $vaV['id'] && $vaV2['status'] === 'published'
      && (int) val('SELECT COUNT(*) FROM properties WHERE account_id = ? AND is_demo = 2', [$vaAcc]) === 1 && !val('SELECT 1 FROM properties WHERE id = ?', [$vaV['id']]));

// ================================================================= CONTROLLO DEI FORM
capitolo('Controllo dei form e del pannello al telefono');
db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'register:%'");
$cf = new Browser('controllo-form');
$cf->get('/registrati');
$cf->post('/registrati', ['name' => 'Carla Form', 'email' => 'carla.form@prova.test', 'password' => 'CarlaProva123', 'termini' => '1']);
$cfAcc = (int) val("SELECT a.id FROM accounts a JOIN users u ON u.id = a.user_id WHERE u.email = 'carla.form@prova.test'");
$cfUid = (int) val("SELECT id FROM users WHERE email = 'carla.form@prova.test'");
// Plus manuale: servono i luoghi. La guida resta da pubblicare.
db()->prepare("INSERT INTO subscriptions (account_id, package_version_id, status, provider, current_period_start, current_period_end, payment_status, created_at, updated_at, quantity)
               VALUES (?, ?, 'active', 'manuale', ?, ?, 'manuale', ?, ?, 1)")->execute([$cfAcc, pv('plus'), gmdate('Y-m-d\TH:i:s\Z'), gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 year')), gmdate('Y-m-d\TH:i:s\Z'), gmdate('Y-m-d\TH:i:s\Z')]);
db()->exec("UPDATE users SET email_verified_at = '2026-01-01T00:00:00Z' WHERE email = 'carla.form@prova.test'");
$r = $cf->post('/registrati', ['name' => 'Troppo Lunga', 'email' => 'lunga@prova.test', 'password' => str_repeat('a', 73), 'termini' => '1']);
prova('Registrazione: una password oltre i 72 caratteri si rifiuta (bcrypt la taglierebbe)', !val("SELECT 1 FROM users WHERE email = 'lunga@prova.test'"));
$r = $cf->post('/pannello/nuova', ['name' => 'Casa Form', 'city' => 'Assisi']);
$cfPid = (int) val('SELECT id FROM properties WHERE account_id = ?', [$cfAcc]);
$cf->post("/pannello/$cfPid/sezioni", ['kind' => 'eat']);
$cfEat = (int) val("SELECT id FROM sections WHERE property_id = ? AND kind = 'eat'", [$cfPid]);
$r = $cf->segui($cf->post("/pannello/$cfPid/sezioni/$cfEat/luogo", ['place_id' => '0', 'name' => '', 'maps_url' => '', 'description' => 'Un testo da non perdere', 'walk_minutes' => '7']));
prova('Luogo senza nome: l\'errore sta sotto il campo e quello che era scritto resta', str_contains($r['body'], 'id="pl-name-err"') && str_contains($r['body'], 'aria-invalid="true"')
      && str_contains($r['body'], 'Un testo da non perdere') && str_contains($r['body'], 'value="7"') && !val('SELECT 1 FROM places WHERE section_id = ?', [$cfEat]));
$r = $cf->post("/pannello/$cfPid/sezioni/$cfEat/luogo", ['place_id' => '0', 'name' => 'Trattoria di prova', 'maps_url' => '']);
$cfPl = (int) val('SELECT id FROM places WHERE section_id = ?', [$cfEat]);
$r = $cf->post("/pannello/$cfPid/sezioni/$cfEat/luogo", ['place_id' => (string) $cfPl, 'name' => 'Trattoria rinominata', 'maps_url' => ''], ['Accept: application/json']);
prova('Luogo già salvato: il salvataggio automatico risponde in JSON e salva', str_contains($r['body'], '"ok":true') && val('SELECT name FROM places WHERE id = ?', [$cfPl]) === 'Trattoria rinominata');
$r = $cf->get("/pannello/$cfPid/sezioni/$cfEat?luogo=$cfPl");
prova('…e il modulo del luogo salvato ha il salvataggio automatico; i minuti si scrivono col tastierino', str_contains($r['body'], 'data-autosave><input type="hidden" name="_csrf"') || preg_match('#/luogo" enctype="multipart/form-data" class="stack" style="margin-top:12px" data-autosave>#', $r['body']) === 1
      && str_contains($r['body'], 'id="pl-walk" name="walk_minutes" type="text" inputmode="numeric"'));
$cf->post("/pannello/$cfPid/sezioni", ['kind' => 'emergency']);
$cfEm = (int) val("SELECT id FROM sections WHERE property_id = ? AND kind = 'emergency'", [$cfPid]);
$r = $cf->get("/pannello/$cfPid/sezioni/$cfEm");
prova('Numero di emergenza col tastierino del telefono', preg_match('#name="emergency_number"[^>]*type="tel"|type="tel"[^>]*name="emergency_number"#s', $r['body']) === 1);
$cfCore = (int) val('SELECT id FROM sections WHERE property_id = ? AND is_core = 1', [$cfPid]);
$cf->post("/pannello/$cfPid/sezioni/$cfCore", ['tax_max_nights' => '5 notti']);
prova('«Per quante notti al massimo» salva solo le cifre', (json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$cfCore]), true)['tax_max_nights'] ?? '') === '5');
$r = $cf->post('/account/profilo', ['name' => '  Carla   Nuova ']);
prova('Account: il nome si cambia', val('SELECT name FROM users WHERE id = ?', [$cfUid]) === 'Carla Nuova');
$r = $cf->post('/account/password', ['attuale' => 'sbagliata', 'nuova' => 'CarlaNuova123', 'nuova2' => 'CarlaNuova123']);
prova('…la password non cambia senza quella attuale giusta, e l\'errore sta sotto il campo', str_contains($r['body'], 'id="pw-attuale-err"') && password_verify('CarlaProva123', (string) val('SELECT password_hash FROM users WHERE id = ?', [$cfUid])));
$cf->post('/account/password', ['attuale' => 'CarlaProva123', 'nuova' => 'CarlaNuova123', 'nuova2' => 'CarlaNuova123']);
prova('…con quella giusta sì', password_verify('CarlaNuova123', (string) val('SELECT password_hash FROM users WHERE id = ?', [$cfUid])));
$r = $cf->post('/account/email', ['email' => 'lucia@esempio.it', 'attuale' => 'CarlaNuova123']);
prova('…l\'email di un altro account non si prende', str_contains($r['body'], 'già usata da un altro account') && !val('SELECT pending_email FROM users WHERE id = ?', [$cfUid]));
$cf->post('/account/email', ['email' => 'carla.nuova@prova.test', 'attuale' => 'CarlaNuova123']);
prova('…l\'email nuova resta in attesa finché non si apre il link', val('SELECT pending_email FROM users WHERE id = ?', [$cfUid]) === 'carla.nuova@prova.test'
      && val('SELECT email FROM users WHERE id = ?', [$cfUid]) === 'carla.form@prova.test');
$cfLink = linkPosta('carla.nuova@prova.test', 'account/email');
$cf->get(substr($cfLink, (int) strpos($cfLink, '/account/email/')));
prova('…e col link diventa quella dell\'account (già confermata)', val('SELECT email FROM users WHERE id = ?', [$cfUid]) === 'carla.nuova@prova.test'
      && !val('SELECT pending_email FROM users WHERE id = ?', [$cfUid]) && (bool) val('SELECT email_verified_at FROM users WHERE id = ?', [$cfUid]), $cfLink);
$cf->post("/pannello/$cfPid/elimina", ['conferma' => 'casa  form']);
prova('Eliminare una struttura: il nome si confronta senza maiuscole e spazi doppi', !val('SELECT 1 FROM properties WHERE id = ?', [$cfPid]));
prova('CSS: i riquadri delle righe non allargano la pagina al telefono', str_contains((string) file_get_contents("$DOVE/assets/app.css"), 'fieldset{min-width:0}'));

// ================================================================= IMPOSTAZIONI
capitolo('Amministrazione → Impostazioni (Stripe, posta, archivio delle foto)');
$fileLocale = $DOVE . '/app/config.local.php';
@unlink($fileLocale);
$r = $admin->get('/admin/impostazioni');
prova('La pagina si apre con i tre riquadri e l\'indirizzo del webhook da copiare', $r['code'] === 200 && pulita($r) && str_contains($r['body'], 'id="stripe"')
      && str_contains($r['body'], 'id="posta"') && str_contains($r['body'], 'id="archivio"') && str_contains($r['body'], '/webhook/stripe'));
prova('…i campi impostati dal server (variabili d\'ambiente) si vedono bloccati, e nessun segreto compare nella pagina',
      str_contains($r['body'], 'variabile d\'ambiente <code>STRIPE_SECRET_KEY</code>') && !str_contains($r['body'], 'sk_test_finto_solo_per_le_prove')
      && !str_contains($r['body'], 'whsec_finto_solo_per_le_prove') && !str_contains($r['body'], 'segreto-finto-per-le-prove'));
$r = $dora->get('/admin/impostazioni');
prova('…un cliente non ci entra', $r['code'] === 403);
$q = $admin->get('/admin');
prova('Il Quadro porta alle impostazioni dagli avvisi («Imposta ora»)', str_contains($q['body'], '/admin/impostazioni#posta') && str_contains($q['body'], 'Imposta ora'));
$campiPosta = ['mail__host' => 'smtp.prova.test', 'mail__port' => '587', 'mail__encryption' => 'tls', 'mail__user' => 'noreply@prova.test',
               'mail__pass' => 'segreto-smtp-di-prova', 'mail__from' => 'noreply@prova.test', 'mail__from_name' => "Casa d'Assisi'; system('id'); //"];
$r = $admin->post('/admin/impostazioni/posta', $campiPosta + ['password' => 'sbagliata']);
prova('Senza la password giusta non si salva niente', $r['code'] === 422 && !is_file($fileLocale) && str_contains($r['body'], 'La password non è giusta')
      && str_contains($r['body'], 'smtp.prova.test') && !str_contains($r['body'], 'segreto-smtp-di-prova'));
$r = $admin->post('/admin/impostazioni/posta', ['mail__port' => '99999', 'mail__from' => 'non-un-indirizzo'] + $campiPosta + ['password' => 'AdminProva123']);
prova('…i valori sbagliati si segnalano campo per campo', $r['code'] === 422 && str_contains($r['body'], 'Una porta è un numero tra 1 e 65535')
      && str_contains($r['body'], 'Questo indirizzo email non sembra valido') && !is_file($fileLocale));
$r = $admin->post('/admin/impostazioni/posta', $campiPosta + ['password' => 'AdminProva123']);
$scritto = is_file($fileLocale) ? (static fn() => require $fileLocale)() : [];
prova('Salvato in app/config.local.php, senza toccare il campo bloccato dal server (MAIL_TRANSPORT)', $r['code'] === 302 && str_contains($r['loc'], '/admin/impostazioni')
      && ($scritto['mail']['host'] ?? '') === 'smtp.prova.test' && ($scritto['mail']['port'] ?? 0) === 587 && !isset($scritto['mail']['transport'])
      && ($scritto['mail']['pass'] ?? '') === 'segreto-smtp-di-prova', json_encode(array_diff_key($scritto['mail'] ?? [], ['pass' => 1])));
prova('…un testo con apici e codice resta solo testo (il file si scrive con var_export)', ($scritto['mail']['from_name'] ?? '') === "Casa d'Assisi'; system('id'); //");
$r = $admin->segui($r);
prova('…il segreto non torna mai nella pagina: si vede solo come finisce', str_contains($r['body'], 'impostazioni salvate') && !str_contains($r['body'], 'segreto-smtp-di-prova')
      && str_contains($r['body'], '… rova'));
$admin->post('/admin/impostazioni/posta', ['mail__pass' => ''] + $campiPosta + ['password' => 'AdminProva123']);
$scritto = (static fn() => require $fileLocale)();
prova('…lasciato vuoto, il segreto resta com\'era; con «Togli» si cancella', ($scritto['mail']['pass'] ?? '') === 'segreto-smtp-di-prova'
      && (function () use ($admin, $campiPosta, $fileLocale) {
          $admin->post('/admin/impostazioni/posta', ['mail__pass' => '', 'togli' => ['mail__pass' => '1']] + $campiPosta + ['password' => 'AdminProva123']);
          $x = (static fn() => require $fileLocale)();
          return !isset($x['mail']['pass']) && is_file(dirname($fileLocale) . '/config.local.bak.php');
      })());
prova('…nel registro c\'è il salvataggio, senza i valori', (bool) val("SELECT 1 FROM audit_log WHERE action = 'impostazioni.posta'")
      && !val("SELECT 1 FROM audit_log WHERE meta LIKE '%segreto-smtp%'"));
$r = $admin->post('/admin/impostazioni/archivio', ['storage__s3__public_base_url' => 'http://senza-https.example', 'password' => 'AdminProva123']);
prova('Archivio: l\'indirizzo pubblico deve essere https', $r['code'] === 422 && str_contains($r['body'], 'con https://'));
$r = $admin->segui($admin->post('/admin/impostazioni/stripe/prova', []));
prova('«Prova la connessione» con Stripe: la chiave risponde (modalità di prova)', str_contains($r['body'], 'Stripe risponde') && str_contains($r['body'], 'modalità di prova'));
$r = $admin->segui($admin->post('/admin/impostazioni/archivio/prova', []));
prova('…con il bucket: scrittura, lettura e cancellazione', str_contains($r['body'], 'Il bucket risponde'));
$r = $admin->segui($admin->post('/admin/impostazioni/posta/prova', []));
prova('…con la posta: in modalità prova l\'email finisce in mail.log', str_contains($r['body'], 'mail.log') && str_contains((string) @file_get_contents($DOVE . '/app/storage/logs/mail.log'), 'Prova della posta di MyHouse Welcome'));
@unlink($fileLocale); @unlink(dirname($fileLocale) . '/config.local.bak.php');

// ================================================================= RIEPILOGO
echo implode("\n", $esiti), "\n\n";
$tot = count(array_filter($esiti, fn($e) => !str_starts_with($e, "\n")));
echo $falliti === 0 ? "Tutte le $tot prove superate.\n" : "$falliti prove NON superate su $tot.\n";
exec('rm -rf ' . escapeshellarg($TMP));
exit($falliti === 0 ? 0 : 1);
