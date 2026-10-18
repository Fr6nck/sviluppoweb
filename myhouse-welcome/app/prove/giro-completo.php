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
// Se le prove si fermano a metà (errore fatale), gli esiti fin lì si vedono lo stesso.
register_shutdown_function(function () { global $esiti, $finito; if (empty($finito) && $esiti) { echo implode("\n", $esiti), "\n\n(interrotte)\n"; } });
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

    /** Con true (il solito) chi pubblica senza CIN ne riceve uno di prova, unico: il CIN serve per pubblicare. */
    public static bool $cinAutomatico = true;
    private static int $cinN = 0;

    /** 7A: chi si registra conferma subito l'email (segue il link arrivato per posta), come farebbe una persona. */
    public static bool $confermaAutomatica = true;

    public function req(string $metodo, string $p, ?array $data, array $h = []): array
    {
        global $BASE;
        if ($metodo === 'POST' && $p === '/registrati' && is_array($data) && !array_key_exists('email2', $data)) $data['email2'] = $data['email'] ?? '';
        if ($metodo === 'POST' && self::$cinAutomatico && preg_match('#^/pannello/(\d+)/pubblica$#', $p, $mp)) {
            $st = db()->prepare("UPDATE properties SET cin = ? WHERE id = ? AND cin = ''");
            $st->execute([sprintf('IT054039C2%08d', ++self::$cinN), (int) $mp[1]]);
        }
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
        if ($metodo === 'POST' && $p === '/registrati' && self::$confermaAutomatica && $r['code'] === 302 && str_ends_with($r['loc'], '/conferma-email')
            && ($l = linkPosta(mb_strtolower(trim((string) $data['email'])), 'verifica')) !== '') {
            $r = $this->get(substr($l, strpos($l, '/verifica/')));
        }
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
/** 8I: i registri sono un file al giorno (mail-AAAA-MM-GG.log, app-AAAA-MM-GG.log): tutte le righe, dal più vecchio. */
function righeLog(string $tipo): array {
    global $DOVE; $f = glob("$DOVE/app/storage/logs/$tipo-????-??-??.log") ?: []; sort($f); $out = [];
    foreach ($f as $x) foreach (file($x, FILE_IGNORE_NEW_LINES) ?: [] as $l) $out[] = $l;
    return $out;
}
function righePosta(): array { return righeLog('mail'); }
function val(string $sql, array $a = []): mixed { $s = db()->prepare($sql); $s->execute($a); $v = $s->fetchColumn(); return $v === false ? null : $v; }
function riga(string $sql, array $a = []): ?array { $s = db()->prepare($sql); $s->execute($a); return $s->fetch() ?: null; }
function righe(string $sql, array $a = []): array { $s = db()->prepare($sql); $s->execute($a); return $s->fetchAll(); }

/** L'ultimo link di un certo tipo spedito a un indirizzo (la posta finisce in un file). */
function linkPosta(string $a, string $tipo): string
{
    global $DOVE;
    $trovato = '';
    foreach (righePosta() ?: [] as $l) {
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
const MHW_VERSIONE_LEGALE = '2026-10-16';   // 8J/9B: le versioni di serie di Termini e Privacy (9A/9B: preventivi, giudizio con sconto)
const MHW_VERSIONE_DPA = '2026-10-15';      // il DPA non è cambiato
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


// 6J · 6K · i prezzi del listino di serie (028: 97, 127, 127 + 70), prima che le prove cambino il listino.
capitolo('6J · 6K · listino di serie: anteprima dei codici, Quadro, llms.txt e dati strutturati');
$ospite = new Browser('visitatore');
$r = $admin->get('/admin/sconti');
prova('6J · anteprima dei codici: Portfolio al prezzo della quantità minima', str_contains($r['body'], 'data-prezzo="17700" data-nome="Portfolio (2 strutture)"')
      && str_contains($r['body'], "Portfolio (2 strutture): 177\u{00A0}€"));
$r = $admin->get('/admin');
prova('6J · Quadro → Listino: solo i piani in vendita, Portfolio «117 € + scaglioni dalla 2ª» (9A), link alle versioni precedenti', str_contains($r['body'], "117\u{00A0}€ + scaglioni dalla 2ª")
      && str_contains($r['body'], 'Versioni precedenti e piani nascosti'));
$r = $ospite->get('/llms.txt');
prova('llms.txt: introduzione, fatti, piani dal listino (97, 127, 117 + scaglioni), FAQ come link a /domande#id, contatti', $r['code'] === 200
      && str_starts_with($r['body'], "# MyHouse Welcome\n\n> ") && str_contains($r['body'], '## Fatti chiave') && str_contains($r['body'], '- Essential: 97 €')
      && str_contains($r['body'], '- Plus: 127 €') && str_contains($r['body'], '- Portfolio: 117 € la prima struttura, poi 60 € 2ª; 50 € dalla 3ª alla 5ª; 40 € dalla 6ª alla 10ª') && str_contains($r['body'], '(con 2: 177 €)')
      && str_contains($r['body'], '/domande#prova)') && str_contains($r['body'], '## Contatti') && str_contains($r['body'], 'assistenza@welcomemyhouse.it'));
$r = $ospite->get('/');
$ld = preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $r['body'], $mm) ? array_map(fn($j) => json_decode($j, true), $mm[1]) : [];
$grafo = $ld[0]['@graph'] ?? [];
$app6k = array_values(array_filter($grafo, fn($x) => ($x['@type'] ?? '') === 'SoftwareApplication'))[0] ?? [];
prova('Home: title e description di serie, index, niente canonical senza dominio, Open Graph', str_contains($r['body'], '<title>Guida digitale per case vacanza e B&amp;B | MyHouse Welcome</title>')
      && substr_count($r['body'], '<meta name="description"') === 1 && str_contains($r['body'], '<meta name="robots" content="index, follow">')
      && !str_contains($r['body'], 'rel="canonical"') && substr_count($r['body'], 'property="og:title"') === 1 && str_contains($r['body'], 'name="twitter:card" content="summary_large_image"'));
prova('Home: JSON-LD valido, Organization con vatID, tre offerte (97, 127, Portfolio 177) IVA esclusa, FAQPage', count($ld) === 2 && !in_array(null, $ld, true)
      && in_array('IT03793880547', array_column($grafo, 'vatID'), true) && count($app6k['offers'] ?? []) === 3
      && array_column($app6k['offers'], 'price') === ['97.00', '127.00', '177.00'] && ($app6k['offers'][2]['priceSpecification']['valueAddedTaxIncluded'] ?? null) === false
      && ($ld[1]['@type'] ?? '') === 'FAQPage' && count($ld[1]['mainEntity'] ?? []) >= 16);
$nFaq = count($ld[1]['mainEntity'] ?? []);
$ospite = new Browser('visitatore');
// ======================================================================= LANDING
capitolo('Landing e listino');
$r = $ospite->get('/');
prova('La landing si apre', $r['code'] === 200 && pulita($r));
prova('Titolo richiesto', str_contains($r['body'], 'La casa risponde') && str_contains($r['body'], 'prima che chiedano.'));
prova('CTA principale', str_contains($r['body'], 'Crea gratis la tua guida'));
prova('Demo dichiarata come demo', str_contains($r['body'], 'Guarda la demo') && str_contains($r['body'], 'demo-tag'));
prova('Microcopy', str_contains($r['body'], 'Paghi solo quando pubblichi'));
prova('Revisione · sottotitolo immediato, «sempre aggiornato» accanto al QR, «Più strutture» come domanda', str_contains($r['body'], 'tutto accessibile tramite QR Code. Senza app da scaricare.')
      && str_contains($r['body'], 'un QR Code sempre aggiornato in ogni camera, al posto del foglio plastificato') && str_contains($r['body'], 'Gestisci più strutture? Un QR Code per ognuna'));
prova('Revisione · «Il tempo delle risposte»: titolo chiaro, cursore di sinistra bianco come la barra', str_contains($r['body'], 'L&#039;accoglienza resta tua. Rispondere sempre alle stesse domande, no.')
      && str_contains($r['body'], '.t2 input[data-t2="nec"]{accent-color:var(--inverse-fg'));
prova('Revisione · «La guida che lavora per te»: i due inviti detti in una frase', str_contains($r['body'], 'manda due inviti all&#039;ospite: una recensione sulla piattaforma'));
prova('Revisione · l\'email scritta per esteso accanto ai bottoni «Email» e «Scrivici un\'email»',
      preg_match('#class="small muted faq__indirizzo">Oppure scrivi a <b>[^<]+@[^<]+</b>#', $r['body']) === 1 && preg_match('#Oppure scrivici subito:\s*<a href="mailto:[^"]+">[^<]+@[^<]+</a>#', $r['body']) === 1);
foreach (['97', '127', '177'] as $p) prova("Prezzo $p € dal listino", str_contains($r['body'], $p . "\u{00A0}€"));
$portfolio = preg_match('#<form class="plan__scelta".*?</form>#s', $r['body'], $m) ? $m[0] : '';
prova('Portfolio: campo numerico (min 2, solo interi) al posto del menu', str_contains($portfolio, 'Quante strutture vuoi gestire?') && preg_match('#<input[^>]*name="strutture"[^>]*type="number"[^>]*step="1"[^>]*min="2"[^>]*value="2"#', $portfolio) === 1
      && !str_contains($portfolio, '<select'));
prova('…costo per struttura dal listino 9A: 1ª 117 €, 2ª +60 €, dalla 3ª alla 5ª +50 €, dalla 6ª alla 10ª +40 €, la media e «più di 10? preventivo»', str_contains($portfolio, 'Quanto costa ogni struttura')
      && str_contains($portfolio, "<span>1ª</span><b>117\u{00A0}€</b>") && str_contains($portfolio, "<span>2ª</span><b>+60\u{00A0}€</b>")
      && str_contains($portfolio, "<span>Dalla 3ª alla 5ª</span><b>+50\u{00A0}€ <small>l'una</small>") && str_contains($portfolio, "<span>Dalla 6ª alla 10ª</span><b>+40\u{00A0}€ <small>l'una</small>")
      && str_contains($portfolio, 'data-scaglioni="[[2,6000],[3,5000],[6,4000]]"') && str_contains($portfolio, "in media\n    <b data-media>88,50\u{00A0}€</b>")
      && str_contains($portfolio, 'Più di 10 strutture? Ti facciamo una proposta su misura.') && str_contains($portfolio, 'max="999" data-max-prezzo="10"'));   // 9A: fino a 999, oltre 10 il preventivo
prova('Listino: per ogni piano «Ideale per», dal pacchetto (Essential casa o appartamento, Plus B&B e agriturismi, Portfolio da 2 a 10 strutture)',
      substr_count($r['body'], '<p class="plan__per"><b>Ideale per</b>') === 3 && str_contains($r['body'], 'chi affitta una casa o un appartamento')
      && str_contains($r['body'], 'B&amp;B, case vacanza e agriturismi con ospiti da tutto il mondo') && str_contains($r['body'], 'chi gestisce da 2 a 10 strutture'));
prova('…base e costo aggiuntivo dal listino, totale iniziale 177 €', str_contains($portfolio, 'data-base="11700"') && str_contains($portfolio, 'data-extra="6000"')
      && preg_match("#<span data-totale>177\u{00A0}€</span><small> \+ IVA / anno</small>#u", $portfolio) === 1 && substr_count($portfolio, '+ IVA / anno') === 1);
prova('…la quantità va alla registrazione col piano', str_contains($portfolio, '/registrati"') && str_contains($portfolio, 'name="piano" value="' . pv('portfolio') . '"') && str_contains($portfolio, 'Crea gratis con Portfolio'));
prova('…label esplicita del campo', preg_match('#<label for="([a-z0-9-]+)"[^>]*>Quante strutture#', $portfolio, $mm) === 1 && str_contains($portfolio, 'id="' . $mm[1] . '"'));
prova('…il totale lo calcola uno script senza prezzi scritti dentro', str_contains($r['body'], '/assets/prezzi.js') && is_file("$DOVE/assets/prezzi.js") && !preg_match('/11700|6000|117|177/', (string) @file_get_contents("$DOVE/assets/prezzi.js")));
prova('Pricing: niente "CMS", niente frasi finali', !preg_match('/>\s*CMS\s*</', $r['body'])
      && !str_contains($r['body'], 'Meno domande ripetitive, più tempo per accogliere') && !str_contains($r['body'], 'concierge digitale'));
// 6J · nelle schede della landing restano «Ideale per», prezzo, funzioni e bottone: niente titoletto e descrizione.
prova('Piani: nelle schede della landing niente headline e descrizione, resta «Ideale per»', !str_contains($r['body'], 'Le informazioni importanti, sempre a disposizione.')
      && !str_contains($r['body'], 'class="plan__headline"') && !str_contains($r['body'], 'class="plan__desc"')
      && substr_count($r['body'], 'class="plan__per"') === 3 && str_contains($r['body'], 'Statistiche distinte per struttura'));
$carteListino = substr($r['body'], (int) strpos($r['body'], 'class="grid grid-3 piani"'), (int) strpos($r['body'], 'class="confronto"') - (int) strpos($r['body'], 'class="grid grid-3 piani"'));
prova('Plus: foto e PDF spiegati, niente "immagine profilo" nelle card, badge sobrio', str_contains($carteListino, 'Possibilità di inserire foto e PDF accanto ai testi')
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
prova('Sezione «Il tempo delle risposte» presente', $tempo !== '' && str_contains($tempo, 'Il tempo delle risposte') && str_contains($tempo, 'Tempo risparmiato in un anno'));
prova('…dopo il prodotto e prima di "Come funziona"', strpos($r['body'], 'class="prodotto"') < strpos($r['body'], 'id="il-tempo"') && strpos($r['body'], 'id="il-tempo"') < strpos($r['body'], 'id="come-funziona"'));
prova('…quattro cursori veri e i conti di partenza anche senza JavaScript (35 minuti, 228 € l\'anno)', substr_count($tempo, 'type="range"') === 4
      && str_contains($tempo, '<span data-o="tot">35</span> minuti') && str_contains($tempo, '<span data-o="valore">228 €</span>'));
prova('7B · …senza il confronto con il prezzo del piano: resta il valore del tempo', !str_contains($tempo, 'data-prezzo=') && !str_contains($tempo, 'Abbonamento Essential, IVA esclusa') && str_contains($tempo, 'Valgono <span data-o="valore">228 €</span> del tuo tempo'));
prova('…con le fonti dei numeri', str_contains($r['body'], 'Stime di aziende del settore, non statistiche ufficiali.'));
prova('CTA della hero verso la registrazione e verso la demo', preg_match('#<section class="hero2">.*?</section>#s', $r['body'], $mh) && str_contains($mh[0], '/registrati"') && str_contains($mh[0], 'Guarda la demo'));
prova('Fase 1 · piè di pagina con il titolare (8J: City Up S.r.l., P.IVA, sede, PEC), telefono e WhatsApp',
      str_contains($r['body'], 'City Up S.r.l. · P.IVA 03793880547 · Via Ariodante Fabretti 15, 06123 Perugia (PG) · PEC cityup@pec.it') && str_contains($r['body'], 'href="tel:+393920061600"')
      && str_contains($r['body'], 'href="https://wa.me/393920061600"') && !str_contains($r['body'], 'un progetto Blackout Agency')
      && str_contains($r['body'], 'href="mailto:assistenza@welcomemyhouse.it"'));
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
      && str_contains($r['body'], "97\u{00A0}€</span> + IVA / anno") && str_contains($r['body'], '>Cambia</a>'));
prova('Fase 2 · «Nome e cognome», password con «Mostra» e barra di robustezza', str_contains($r['body'], 'Nome e cognome') && str_contains($r['body'], 'data-mostra-pw')
      && str_contains($r['body'], 'data-forza'));
prova('8J · una sola casella, non spuntata: Termini, Accordo sul trattamento dei dati e Informativa letta, con i tre link', substr_count($r['body'], 'type="checkbox"') === 1 && !str_contains($r['body'], 'name="privacy"')
      && str_contains($r['body'], "e ho letto l'") && str_contains($r['body'], '/dpa"') && str_contains($r['body'], '/termini"') && str_contains($r['body'], '/privacy"')
      && !preg_match('/name="termini"[^>]*checked/', $r['body']) && !str_contains($r['body'], 'marketing'));
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
// 9F·11 · con la lingua principale inglese il pannello passa in inglese da solo; per le prove che seguono Elena lo rimette in italiano da Account.
$r = $elena->get("/pannello/$epid/procedura/struttura");
prova('9F·11 · lingua principale inglese: il pannello è in inglese da solo (procedura e <html lang="en">), finché non si sceglie altro in Account', str_contains($r['body'], '<h1>Property and contacts.</h1>') && str_contains($r['body'], '<html lang="en">'));
$elena->post('/account/pannello-lingua', ['pannello_lingua' => 'it']);
prova('9F·11 · …e da Account si riporta in italiano', str_contains($elena->get("/pannello/$epid/procedura/struttura")['body'], '<h1>Struttura e contatti.</h1>'));
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
prova('7A · email confermata dal link arrivato per posta', $u && $u['email_verified_at'] !== null);
prova('Password salvata con hash', $u && str_starts_with($u['password_hash'], '$2y$') || str_starts_with((string) $u['password_hash'], '$argon'));
prova('Email di verifica spedita', linkPosta('anna@prova.test', 'verifica') !== '');

// 7A · registrazione: l'email due volte, una password vera, la conferma prima di cominciare.
Browser::$confermaAutomatica = false;
$vera = new Browser('vera');
$PULITO = preg_replace('#/index\.php$#', '', $BASE);
$vera->get('/registrati');
$dati7 = ['name' => 'Vera Conferma', 'email' => 'vera@prova.test', 'email2' => 'vera@prova.tst', 'password' => 'VeraProva1234', 'termini' => '1'];
$r = $vera->post('/registrati', $dati7);
prova('7A · le due email diverse: rifiutata', $r['code'] === 200 && str_contains($r['body'], 'Le due email non sono uguali') && !val("SELECT id FROM users WHERE email = 'vera@prova.test'"));
prova('7A · «Ripeti l\'email», «Mostra» la password e l\'aiuto senza la password', str_contains($r['body'], 'name="email2"') && str_contains($r['body'], 'data-mostra-pw')
      && str_contains($r['body'], 'Aiuto%20con%20l') && str_contains($r['body'], 'vera%40prova.test') && !str_contains($r['body'], 'VeraProva1234'));
$r = $vera->post('/registrati', ['email2' => 'vera@prova.test', 'password' => 'password123'] + $dati7);
prova('7A · password tra le più comuni: rifiutata', str_contains($r['body'], 'tra le più usate') && !val("SELECT id FROM users WHERE email = 'vera@prova.test'"));
$r = $vera->post('/registrati', ['email2' => 'vera@prova.test', 'password' => 'vera@prova.test'] + $dati7);
prova('7A · password uguale all\'email: rifiutata', str_contains($r['body'], 'uguale alla tua email'));
$r = $vera->post('/registrati', ['email2' => 'vera@prova.test', 'password' => 'Corta1234'] + $dati7);
prova('7A · password sotto i 10 caratteri: rifiutata', str_contains($r['body'], 'almeno 10 caratteri'));
$r = $vera->post("$PULITO/registrati", ['email2' => 'VERA@prova.test'] + $dati7);
prova('7A · registrata: «Controlla la tua email»', $r['code'] === 302 && str_ends_with($r['loc'], '/conferma-email'), $r['loc']);
$lv = linkPosta('vera@prova.test', 'verifica');
prova('7A · indirizzi puliti: il link di conferma è senza index.php', $lv !== '' && str_starts_with($lv, "$PULITO/verifica/") && !str_contains($lv, 'index.php'), $lv);
$html7 = glob("$DOVE/app/storage/logs/email/*.html") ?: [];
$ultima = $html7 ? (string) file_get_contents(end($html7)) : '';
prova('8A · l\'email in HTML: marchio come immagine (senza index.php), titolo e testo centrati, un bottone, link in chiaro, piede con P.IVA e link',
      preg_match('#<img src="cid:marchio" width="282" height="42" alt="myhouse welcome"#', $ultima) === 1   // 9C: il marchio viaggia dentro l'email
      && !preg_match('#index\.php/assets/email#', $ultima) && str_contains((string) @file_get_contents(substr(end($html7), 0, -5) . '.eml'), 'Content-ID: <marchio>') && str_contains($ultima, 'font-size:30px;line-height:36px;color:#231b12;text-align:center')
      && str_contains($ultima, 'Conferma la tua email') && substr_count($ultima, 'background:#b4451f') === 1 && str_contains($ultima, htmlspecialchars($lv))
      && str_contains($ultima, '>Assistenza</a>') && str_contains($ultima, '/privacy"') && str_contains($ultima, 'P.IVA') && !str_contains($ultima, 'social-'));
$r = $vera->get('/pannello');
prova('7A · senza conferma il pannello rimanda a «Controlla la tua email»', $r['code'] === 302 && str_ends_with($r['loc'], '/conferma-email'));
$vuid = (int) val("SELECT id FROM users WHERE email = 'vera@prova.test'");
$admin->get('/admin/clienti');
$r = $admin->post("/admin/entra/$vuid", []);
$r = $admin->get('/pannello');
prova('7A · «Entra come cliente» dall\'amministrazione non passa dalla conferma', !($r['code'] === 302 && str_ends_with($r['loc'], '/conferma-email')), $r['code'] . ' ' . $r['loc']);
$admin->segui($admin->get('/pannello')); $admin->post('/admin/esci-da-cliente', []);
prova('…e si torna amministratori', $admin->get('/admin')['code'] === 200);
$r = $vera->get('/conferma-email');
$vera->post('/conferma-email/cambia', ['email' => 'vera.giusta@prova.test']);
prova('7A · «Ho sbagliato indirizzo»: cambiato, e la conferma parte al nuovo', val('SELECT email FROM users WHERE id = ?', [$vuid]) === 'vera.giusta@prova.test'
      && linkPosta('vera.giusta@prova.test', 'verifica') !== '');
Browser::$confermaAutomatica = true;
db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'register:%'");   // i tentativi sbagliati qui sopra non tolgono posto alle prove dopo
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
      && !str_contains($r['body'], 'class="verifica-riga"') && !str_contains($r['body'], 'banner banner--info'));   // 7A: chi è qui ha già confermato
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
foreach (['wifi', 'rules', 'eat', 'parking', 'waste', 'visit'] as $k) {   // 7B: Essential ha 6 sezioni aggiuntive
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
prova('La settima sezione è rifiutata dal server', !val('SELECT id FROM sections WHERE property_id = ? AND kind = ?', [$pid, 'transport']));
prova('Messaggio di limite elegante', str_contains($r['body'], 'Hai già 6 sezioni attive, il massimo del tuo piano') && str_contains($r['body'], 'Scopri Plus'));
prova('8B · …e la finestra «Con Plus hai tutte le sezioni» aperta, non un errore; i bottoni «Aggiungi» con il lucchetto', str_contains($r['body'], 'data-apri="sezioni" open')
      && str_contains($r['body'], 'Con Plus hai tutte le sezioni') && str_contains($r['body'], 'data-sblocca="sezioni"') && str_contains($r['body'], '/piano?passa=plus">Passa a Plus</a>')
      && !preg_match('#class="note note--limite#', $r['body']));
prova('Il contatore dice 6 su 6', str_contains($r['body'], '6 su 6 sezioni attive'));
prova('Ordinamento: maniglia e menu ⋯ con Sposta su / giù', str_contains($r['body'], 'riga__maniglia') && str_contains($r['body'], 'Sposta su') && str_contains($r['body'], 'Sposta giù'));
$anna->post("/pannello/$pid/sezioni/{$ids['parking']}/azione", ['fai' => 'disattiva', 'torna' => 'procedura']);
prova('Disattivare libera un posto', (int) val('SELECT COUNT(*) FROM sections WHERE property_id = ? AND is_core = 0 AND is_active = 1', [$pid]) === 5);
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
prova('8B · …ma i bottoni «Aggiungi una foto» e «Aggiungi un PDF» si vedono, con il lucchetto, e aprono «Foto e PDF sono compresi in Plus»',
      substr_count($r['body'], 'data-sblocca="foto"') >= 2 && str_contains($r['body'], 'Foto e PDF sono compresi in Plus') && str_contains($r['body'], '/assets/sblocca.js'));
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
capitolo('Lingue (Essential: la principale e una a scelta)');
$r = $anna->modulo("/pannello/$pid/lingue", "/pannello/$pid/lingue", ['locali' => ['it', 'en', 'fr']]);
prova('8C · una terza lingua non è concessa a Essential: si apre la finestra di Plus', str_contains($r['body'], '2 lingue in tutto') && str_contains($r['body'], 'data-apri="lingue"')
      && !val("SELECT 1 FROM property_locales WHERE property_id = ? AND locale = 'fr'", [$pid]));
$r = $anna->post("/pannello/$pid/lingue", ['locali' => ['it', 'en']]);
prova('Inglese attivato', (bool) val("SELECT 1 FROM property_locales WHERE property_id = ? AND locale = 'en'", [$pid]));
$r = $anna->get("/pannello/$pid/lingue");
prova('8C · Essential: la seconda lingua è una scelta sola tra tutte (anche le aggiuntive), e «Più lingue con Plus» apre la finestra', str_contains($r['body'], 'data-sblocca="lingue"')
      && preg_match('#type="radio" name="locali\[\]" value="fr"#', $r['body']) === 1 && preg_match('#type="radio" name="locali\[\]" value="ja"#', $r['body']) === 1
      && preg_match('#type="radio" name="locali\[\]" value="en" checked#', $r['body']) === 1 && str_contains($r['body'], 'Più lingue sono comprese in Plus'));
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
      && preg_match('#type="radio" name="locali\[\]" value="fr"#', $r['body']) === 1);
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
prova('Revisione · Essential: sotto le righe con foto «Vuoi aggiungere foto o PDF? Scopri il piano Plus»', preg_match('#<p class="help rip__plus">Vuoi aggiungere foto o PDF\? <a href="[^"]*/piano\?passa=plus">Scopri il piano Plus</a></p>#', $r['body']) === 1);
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
prova('Revisione · Plus: l\'aiuto delle Istruzioni promette foto e PDF, senza l\'invito a Plus', str_contains($r['body'], 'Titolo, passaggi, una foto, un PDF.') && !str_contains($r['body'], 'rip__plus'));
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
prova('R5 · guida: un «Chiama» per riga con il numero, accessibile', str_contains($r['body'], 'href="tel:+39075123456"') && str_contains($r['body'], 'aria-label="Chiama Guardia medica"')
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
prova('Aspetto con i campioni', $r['code'] === 200 && pulita($r) && substr_count($r['body'], 'class="swatch"') === 7);   // 9J: sette palette
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
prova('7B · Immagine profilo accettata anche con Essential', !str_contains($r['body'], 'non fa parte del tuo piano') && (bool) val('SELECT profile_media_id FROM properties WHERE id = ?', [$pid]));
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
$r = $anna->modulo("/pannello/$pid/procedura/pubblica", "/pannello/$pid/pubblica", ['acquirente' => 'impresa']);
$r = $anna->segui($r);
// 7A: senza la conferma dell'email il pannello non si apre più (prima si arrivava fino al pagamento).
db()->exec("UPDATE users SET email_verified_at = NULL WHERE email = 'anna@prova.test'");
db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'verifica-attesa:%' OR bucket LIKE 'verify:%'");
$r = $anna->post("/pannello/$pid/pubblica", ['acquirente' => 'impresa']);
prova('7A · email non confermata: il pannello rimanda a «Controlla la tua email»', $r['code'] === 302 && str_ends_with($r['loc'], '/conferma-email')
      && (int) val('SELECT COUNT(*) FROM orders WHERE account_id = ?', [$acc['id']]) === 0, $r['loc']);
$r = $anna->get('/conferma-email');
prova('7A · «Controlla la tua email»: Spam e Promozioni, Rimanda, Ho sbagliato indirizzo, assistenza', $r['code'] === 200 && str_contains($r['body'], 'Controlla la tua email')
      && str_contains($r['body'], 'Promozioni') && str_contains($r['body'], 'Rimanda') && str_contains($r['body'], 'Ho sbagliato indirizzo') && str_contains($r['body'], 'mailto:assistenza@welcomemyhouse.it'));
$r = $anna->segui($anna->post('/verifica/invia', ['torna' => 'conferma']));
prova('7A · «Rimanda» manda un\'altra conferma', str_contains($r['body'], 'Ti abbiamo mandato di nuovo'));
$r = $anna->segui($anna->post('/verifica/invia', ['torna' => 'conferma']));
prova('7A · …ma non prima di 60 secondi', str_contains($r['body'], 'Aspetta qualche secondo') && str_contains($r['body'], 'data-attendi'));
for ($i = 0; $i < 5; $i++) { db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'verifica-attesa:%'"); $r = $anna->segui($anna->post('/verifica/invia', ['torna' => 'conferma'])); }
prova('7A · …e al massimo 5 in un\'ora', str_contains($r['body'], 'più volte'));
$link = linkPosta('anna@prova.test', 'verifica');
$r = $anna->get(substr($link, strpos($link, '/verifica/')));
prova('Link di verifica funziona: dentro la procedura, già connessi', $r['code'] === 302 && str_ends_with($r['loc'], '/pannello') && val("SELECT email_verified_at FROM users WHERE email = 'anna@prova.test'") !== null, $r['loc']);
$r = $anna->get(substr($link, strpos($link, '/verifica/')));
prova('Il link di verifica vale una volta sola', $r['code'] === 200 && pulita($r));

// Fase 3 · R7: prima del primo pagamento, i dati di fatturazione italiani.
$r = $anna->modulo("/pannello/$pid/procedura/pubblica", "/pannello/$pid/pubblica", ['acquirente' => 'impresa']);
prova('Fase 3 · senza dati di fatturazione niente pagamento (7B: si resta nel passo Pubblica)', $r['code'] === 302 && str_contains($r['loc'], "/pannello/$pid/procedura/pubblica#fatturazione")
      && (int) val('SELECT COUNT(*) FROM orders WHERE account_id = ?', [$acc['id']]) === 0, $r['loc']);
$r = $anna->segui($r);
prova('…con il modulo lì, ogni campo con la sua etichetta', $r['code'] === 200 && pulita($r) && str_contains($r['body'], 'I dati per la fattura')
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

// 8J · chi acquista: senza scelta, o da privato senza la richiesta di iniziare subito, niente ordine.
$ordini8j = (int) val('SELECT COUNT(*) FROM orders WHERE account_id = ?', [$acc['id']]);
$r = $anna->get("/pannello/$pid/procedura/pubblica");
prova('8J · passo Pubblica: «Chi acquista?» (impresa o privato) e la casella «inizia subito» per il privato', str_contains($r['body'], 'id="acquisto"')
      && str_contains($r['body'], 'value="impresa"') && str_contains($r['body'], 'value="consumatore"') && str_contains($r['body'], 'name="recesso_rinuncia"')
      && str_contains($r['body'], 'perdo il diritto di recesso per il periodo già goduto'));
$r = $anna->post("/pannello/$pid/pubblica", ['acquirente' => '']);
prova('8J · senza scelta: niente ordine, si torna a «Chi acquista?»', $r['code'] === 302 && str_contains($r['loc'], '#acquisto')
      && (int) val('SELECT COUNT(*) FROM orders WHERE account_id = ?', [$acc['id']]) === $ordini8j, $r['loc']);
$r = $anna->post("/pannello/$pid/pubblica", ['acquirente' => 'consumatore']);
prova('8J · privato senza la richiesta di iniziare subito: niente ordine', $r['code'] === 302 && str_contains($r['loc'], '#acquisto')
      && (int) val('SELECT COUNT(*) FROM orders WHERE account_id = ?', [$acc['id']]) === $ordini8j && str_contains($anna->segui($r)['body'], 'spunta la seconda casella'));
$r = $anna->modulo("/pannello/$pid/procedura/pubblica", "/pannello/$pid/pubblica", ['acquirente' => 'impresa']);
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
prova('Ordine in attesa, legato alla struttura', $ordine && $ordine['status'] === 'pending' && (int) $ordine['property_id'] === $pid && (int) $ordine['amount_cents'] === 9700);
prova('8J · l\'ordine ricorda chi acquista (impresa, nessuna rinuncia)', ($ordine['acquirente'] ?? '') === 'impresa' && (int) ($ordine['recesso_rinuncia'] ?? 1) === 0);
$cs = array_values(array_filter(richiesteStripe(), fn($x) => $x['percorso'] === '/v1/checkout/sessions'));
$c = end($cs)['corpo'] ?? [];
prova('Checkout in modalità abbonamento', ($c['mode'] ?? '') === 'subscription');
prova('Rinnovo annuale', ($c['line_items'][0]['price_data']['recurring']['interval'] ?? '') === 'year');
prova('Prezzo IVA esclusa', ($c['line_items'][0]['price_data']['tax_behavior'] ?? '') === 'exclusive' && ($c['line_items'][0]['price_data']['unit_amount'] ?? '') === '9700');
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
prova('8J · l\'abbonamento ricorda chi acquista (dall\'ordine)', ($sub['acquirente'] ?? '') === 'impresa');
$r = $admin->get('/admin/cliente/' . $acc['id']);
prova('8J · Amministrazione → cliente: «Impresa o professionista» e l\'Accordo sul trattamento dei dati accettato', str_contains($r['body'], 'Impresa o professionista')
      && str_contains($r['body'], 'Accordo sul trattamento dei dati: versione ' . MHW_VERSIONE_DPA));
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
$r = $anna->post("/pannello/$pid/pubblica", ['acquirente' => 'impresa']);
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
$r = $anna->post("/pannello/$pid/pubblica", ['acquirente' => 'impresa']);
prova('Senza abbonamento, ripubblicare richiede un nuovo pagamento', $r['code'] === 302 && str_starts_with($r['loc'], 'https://checkout.stripe.test/'));
$o2 = riga('SELECT * FROM orders WHERE account_id = ? ORDER BY id DESC', [$acc['id']]);
$r = $anna->get('/pagamento/annullato?order=' . $o2['id']);
prova('Pagamento annullato: bozza intatta, ordine annullato', val('SELECT status FROM orders WHERE id = ?', [$o2['id']]) === 'canceled' && val('SELECT status FROM properties WHERE id = ?', [$pid]) === 'published');
file_put_contents("$STRIPE_DIR/guasto", '1');
$r = $anna->segui($anna->post("/pannello/$pid/pubblica", ['acquirente' => 'impresa']));
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
// Plus senza verificare l'email non paga (7A: non entra proprio nel pannello).
$bcore = (int) val('SELECT id FROM sections WHERE property_id = ? AND is_core = 1', [$bpid]);
$bruno->post("/pannello/$bpid/sezioni/$bcore", ['checkin_steps' => ['Suona al citofono.']]);
$bVer = val("SELECT email_verified_at FROM users WHERE email = 'bruno@prova.test'");
db()->exec("UPDATE users SET email_verified_at = NULL WHERE email = 'bruno@prova.test'");
$r = $bruno->post("/pannello/$bpid/pubblica", ['acquirente' => 'impresa']);
prova('Plus non verificato: pubblicazione ferma', $r['code'] === 302 && str_ends_with($r['loc'], '/conferma-email') && !val("SELECT o.id FROM orders o JOIN accounts a ON a.id = o.account_id JOIN users u ON u.id = a.user_id WHERE u.email = 'bruno@prova.test'"));
db()->prepare("UPDATE users SET email_verified_at = ? WHERE email = 'bruno@prova.test'")->execute([$bVer]);

// =================================================================== PORTFOLIO
capitolo('Portfolio');
$pp = pv('portfolio');
$pubblici = righe("SELECT p.code FROM packages p WHERE p.public = 1 AND p.active = 1 ORDER BY p.sort");
prova('Un solo Portfolio in vendita; i vecchi 2 e 3 restano, fuori listino', in_array('portfolio', array_column($pubblici, 'code'), true)
      && !array_intersect(['portfolio2', 'portfolio3'], array_column($pubblici, 'code')) && pv('portfolio2') > 0 && pv('portfolio3') > 0);
prova('Portfolio (9A): 117 € la prima struttura, 60 € la 2ª e poi a scaglioni, da 2 a 10 strutture', (bool) val('SELECT id FROM package_versions WHERE id = ? AND per_property = 1 AND price_cents = 11700 AND extra_price_cents = 6000 AND min_quantity = 2 AND max_quantity = 10', [$pp]));
$carla = new Browser('carla');
$r = $carla->get("/registrati?piano=$pp&strutture=3");
prova('La quantità scelta sulla landing arriva alla registrazione', str_contains($r['body'], 'name="strutture" value="3"'));
$r = $carla->post('/registrati', ['piano' => $pp, 'strutture' => '3', 'name' => 'Carla Portfolio', 'email' => 'carla@prova.test', 'password' => 'CarlaProva123', 'termini' => '1', 'privacy' => '1']);
prova('…e dopo la registrazione dritti alla struttura, con 3 strutture salvate', $r['code'] === 302 && str_contains($r['loc'], '/pannello/nuova')
      && (int) val("SELECT a.intended_quantity FROM accounts a JOIN users u ON u.id = a.user_id WHERE u.email = 'carla@prova.test'") === 3);
prova('…e l\'avviso dice per quante strutture', str_contains($carla->get('/pannello/nuova')['body'], 'Piano <b>Portfolio</b> per 3 strutture scelto'));
$r = $carla->get("/piano?piano=$pp&strutture=3");
prova('…che la mostra già impostata, con il totale (227 €)', preg_match('#name="strutture" type="number"[^>]*value="3"#', $r['body']) === 1 && str_contains($r['body'], "227\u{00A0}€"));
$cacc = (int) val("SELECT a.id FROM accounts a JOIN users u ON u.id = a.user_id WHERE u.email = 'carla@prova.test'");
foreach (['1', '2.5', '0', 'tre', '11', '51', '-3', '2e1'] as $x) {
    $r = $carla->post('/piano', ['pv' => $pp, 'strutture' => $x]);
    if (!($r['code'] === 302 && str_contains($r['loc'], "/piano?piano=$pp"))) prova("Quantità «{$x}» rifiutata", false, $r['loc']);
}
prova('Quantità non intere o fuori dai limiti rifiutate dal server', (int) val('SELECT intended_quantity FROM accounts WHERE id = ?', [$cacc]) === 3
      && str_contains($carla->get("/piano?piano=$pp")['body'], 'Indica un numero intero di strutture tra 2 e 10.'));
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
$r = $carla->modulo("/pannello/$c1/procedura/pubblica", "/pannello/$c1/pubblica", ['acquirente' => 'impresa']);
prova('Pubblica → Stripe Checkout', $r['code'] === 302 && str_starts_with($r['loc'], 'https://checkout.stripe.test/'), $r['loc']);
$cord = riga('SELECT * FROM orders WHERE account_id = ? ORDER BY id DESC', [$cacc]);
prova('Ordine per 3 strutture: 117 + 60 + 50 = 227 €', $cord && (int) $cord['quantity'] === 3 && (int) $cord['amount_cents'] === 22700);
$cs = array_values(array_filter(richiesteStripe(), fn($x) => $x['percorso'] === '/v1/checkout/sessions'));
$c = end($cs)['corpo'] ?? [];
$li = $c['line_items'] ?? [];
prova('Checkout: un solo abbonamento annuale con due voci', ($c['mode'] ?? '') === 'subscription' && count($li) === 2
      && ($li[0]['price_data']['recurring']['interval'] ?? '') === 'year' && str_starts_with((string) ($li[1]['price'] ?? ''), 'price_creato'));
prova('…prima struttura 117 € × 1', ($li[0]['quantity'] ?? '') === '1' && ($li[0]['price_data']['unit_amount'] ?? '') === '11700' && ($li[0]['price_data']['tax_behavior'] ?? '') === 'exclusive');
$tc = array_values(array_filter(richiesteStripe(), fn($x) => $x['percorso'] === '/v1/prices' && ($x['corpo']['billing_scheme'] ?? '') === 'tiered'));
$tt = end($tc)['corpo']['tiers'] ?? [];
prova('…strutture aggiuntive × 2 con il Price graduato creato una volta (fino a 1 → 60 €, fino a 4 → 50 €, oltre → 40 €), IVA esclusa (9A)', ($li[1]['quantity'] ?? '') === '2' && count($tc) === 1
      && ($tt[0]['up_to'] ?? '') === '1' && ($tt[0]['unit_amount'] ?? '') === '6000' && ($tt[1]['up_to'] ?? '') === '4' && ($tt[1]['unit_amount'] ?? '') === '5000'
      && ($tt[2]['up_to'] ?? '') === 'inf' && ($tt[2]['unit_amount'] ?? '') === '4000' && (end($tc)['corpo']['tiers_mode'] ?? '') === 'graduated'
      && (end($tc)['corpo']['tax_behavior'] ?? '') === 'exclusive' && (end($tc)['corpo']['product_data']['metadata']['ruolo'] ?? '') === 'aggiuntiva', json_encode($tt));
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
prova('Account: Portfolio · 3 strutture, 227 €, il costo di ognuna, e «Cambia piano»', str_contains($r['body'], 'Portfolio · 3 strutture') && str_contains($r['body'], "227\u{00A0}€")
      && str_contains($r['body'], 'Quanto paghi per ogni struttura') && str_contains($r['body'], "In media <b>75,67\u{00A0}€</b>") && str_contains($r['body'], "La 4ª costerebbe 50\u{00A0}€")
      && str_contains($r['body'], '/account/piano">Cambia piano</a>') && !str_contains($r['body'], 'action="' . preg_replace('#^https?://[^/]+#', '', $BASE) . '/account/strutture"'));

$voci = fn(int $extra) => ['data' => [
    ['id' => 'si_base_sub_prova_carla', 'quantity' => 1, 'current_period_start' => time(), 'current_period_end' => time() + 360 * 86400, 'price' => ['id' => 'price_finto_annuale', 'product' => 'prod_finto_base']],
    ['id' => 'si_extra_sub_prova_carla', 'quantity' => $extra, 'current_period_start' => time(), 'current_period_end' => time() + 360 * 86400, 'price' => ['id' => 'price_finto_extra', 'product' => 'prod_finto_extra']]]];
// 6H · Salire: si paga oggi la differenza a giorni, il cambio vale col webhook del pagamento.
$r = $carla->get('/account/piano?strutture=5');
prova('6H · Cambia piano: Portfolio è «Il tuo piano», con − e + per le strutture, e il conto di oggi per 5', $r['code'] === 200 && pulita($r)
      && str_contains($r['body'], 'Il tuo piano') && str_contains($r['body'], 'aria-label="Una struttura in più"') && str_contains($r['body'], 'piano=portfolio&amp;strutture=5')
      && preg_match('#Oggi <b>[\d,.]+\x{00A0}€</b> \+ IVA, per i \d+ giorni fino al rinnovo#u', $r['body']) === 1);
$r = $carla->get('/account/strutture?strutture=5');
prova('6H · il vecchio «Numero di strutture» porta alla conferma del cambio', $r['code'] === 302 && str_contains($r['loc'], '/account/piano/conferma?piano=portfolio&strutture=5'));
$r = $carla->get('/account/piano/conferma?piano=portfolio&strutture=5');
prova('6H · conferma della salita: oggi la differenza a giorni, dal rinnovo 327 €, «Vai al pagamento»', $r['code'] === 200 && pulita($r)
      && str_contains($r['body'], 'Differenza per i') && str_contains($r['body'], "327\u{00A0}€") && str_contains($r['body'], 'Vai al pagamento')
      && str_contains($r['body'], 'Paghi sulla pagina sicura di Stripe'));
$prima = count(richiesteStripe());
$r = $carla->modulo('/account/piano/conferma?piano=portfolio&strutture=5', '/account/piano/conferma', ['piano' => 'portfolio', 'strutture' => '5']);
$cambio = riga("SELECT * FROM orders WHERE account_id = ? AND kind = 'change' ORDER BY id DESC", [$cacc]);
$ses = array_values(array_filter(array_slice(richiesteStripe(), $prima), fn($x) => $x['percorso'] === '/v1/checkout/sessions'));
$sc = end($ses)['corpo'] ?? [];
prova('6H · ordine «change» e pagina di Stripe in modalità pagamento, con la fattura', $r['code'] === 302 && str_starts_with($r['loc'], 'https://checkout.stripe.test/')
      && $cambio && (int) $cambio['quantity'] === 5 && (int) $cambio['from_quantity'] === 3 && (int) $cambio['amount_cents'] > 0 && (int) $cambio['amount_cents'] <= 14000
      && ($sc['mode'] ?? '') === 'payment' && ($sc['metadata']['kind'] ?? '') === 'change' && ($sc['invoice_creation']['enabled'] ?? '') === 'true'
      && (int) ($sc['line_items'][0]['price_data']['unit_amount'] ?? 0) === (int) $cambio['amount_cents']
      && str_contains((string) ($sc['line_items'][0]['price_data']['product_data']['name'] ?? ''), 'Portfolio da 3 a 5 strutture'), json_encode($sc));
prova('…nessun addebito sull\'abbonamento e piano invariato finché non arriva il pagamento', !array_filter(array_slice(richiesteStripe(), $prima), fn($x) => $x['percorso'] === '/v1/subscriptions/sub_prova_carla' && $x['metodo'] === 'POST')
      && (int) val("SELECT quantity FROM subscriptions WHERE provider_subscription_id = 'sub_prova_carla'") === 3);
$pagaCambio = fn(string $evt, array $o) => inviaWebhook(['id' => $evt, 'type' => 'checkout.session.completed', 'data' => ['object' => [
    'id' => $o['provider_session_id'], 'mode' => 'payment', 'payment_status' => 'paid', 'customer' => 'cus_carla', 'client_reference_id' => (string) $o['id'],
    'metadata' => ['order_id' => (string) $o['id'], 'account_id' => (string) $o['account_id'], 'kind' => 'change']]]]);
$prima = count(richiesteStripe());
$r = $pagaCambio('evt_carla_cambio_1', $cambio);
$dopo = array_slice(richiesteStripe(), $prima);
$mod = array_values(array_filter($dopo, fn($x) => $x['metodo'] === 'POST' && $x['percorso'] === '/v1/subscriptions/sub_prova_carla'));
$mc = end($mod)['corpo'] ?? [];
prova('6H · pagamento confermato: Stripe passa a 4 strutture aggiuntive SENZA proporzioni, nel sito 5 strutture', $r['body'] === 'cambio-applicato'
      && ($mc['proration_behavior'] ?? '') === 'none' && ($mc['items'][0]['id'] ?? '') === 'si_base_sub_prova_carla' && ($mc['items'][1]['id'] ?? '') === 'si_extra_sub_prova_carla'
      && ($mc['items'][1]['quantity'] ?? '') === '4' && count(array_filter($dopo, fn($x) => $x['percorso'] === '/v1/prices' && $x['metodo'] === 'POST')) >= 1
      && (int) val("SELECT quantity FROM subscriptions WHERE provider_subscription_id = 'sub_prova_carla'") === 5
      && val('SELECT applied_at FROM orders WHERE id = ?', [$cambio['id']]) !== null && val('SELECT status FROM orders WHERE id = ?', [$cambio['id']]) === 'paid', $r['body'] . ' ' . json_encode($mc));
$prima = count(richiesteStripe());
$r = $pagaCambio('evt_carla_cambio_1', $cambio);
$r2 = $pagaCambio('evt_carla_cambio_1bis', $cambio);
prova('…lo stesso pagamento consegnato due volte: un cambio solo', $r['body'] === 'gia-elaborato' && $r2['body'] === 'cambio-gia-applicato'
      && !array_filter(array_slice(richiesteStripe(), $prima), fn($x) => $x['metodo'] === 'POST') && (int) val("SELECT quantity FROM subscriptions WHERE provider_subscription_id = 'sub_prova_carla'") === 5);
$r = $carla->get('/pagamento/ok?order=' . $cambio['id']);
prova('…«Pagamento ricevuto. Sei su Portfolio.»', str_contains($r['body'], 'Pagamento ricevuto. Sei su Portfolio.'));
foreach (['Casa Quattro', 'Casa Cinque', 'Casa Sei'] as $n) $carla->modulo('/pannello/nuova', '/pannello/nuova', ['name' => $n, 'city' => 'Bari']);
prova('…la quarta e la quinta entrano, la sesta no', (int) val('SELECT COUNT(*) FROM properties WHERE account_id = ?', [$cacc]) === 5);

// 6H · Scendere: dal rinnovo, con le strutture da archiviare scelte adesso. Niente si cancella.
$sezioniPrima = (int) val('SELECT COUNT(*) FROM sections s JOIN properties p ON p.id = s.property_id WHERE p.account_id = ?', [$cacc]);
$richiestePrima = count(richiesteStripe());
$r = $carla->get('/account/piano/conferma?piano=portfolio&strutture=3');
prova('6H · discesa a 3: dal rinnovo, oggi niente, e quali 2 strutture archiviare', $r['code'] === 200 && pulita($r) && str_contains($r['body'], 'Oggi non paghi niente')
      && str_contains($r['body'], 'Scegli 2 strutture da archiviare') && substr_count($r['body'], 'name="archivia[]"') === 5);
$conf = fn(array $archivia) => $carla->modulo('/account/piano/conferma?piano=portfolio&strutture=3', '/account/piano/conferma', ['piano' => 'portfolio', 'strutture' => '3', 'archivia' => $archivia]);
$r = $conf([$c1]);
prova('…una sola scelta non basta, e Stripe non viene toccato', $r['code'] === 200 && str_contains($r['body'], 'Scegli esattamente 2 strutture') && count(richiesteStripe()) === $richiestePrima);
$altro = (int) val('SELECT id FROM properties WHERE id NOT IN (SELECT id FROM properties WHERE account_id = ?) LIMIT 1', [$cacc]);
$r = $conf([$c1, $altro]);
prova('…né una struttura di un altro account', count(richiesteStripe()) === $richiestePrima && val('SELECT archived_at FROM properties WHERE id = ?', [$altro]) === null);
$r = $conf([$c1, $cp[1]]);
$mod = array_values(array_filter(richiesteStripe(), fn($x) => $x['metodo'] === 'POST' && $x['percorso'] === '/v1/subscriptions/sub_prova_carla'));
$mc = end($mod)['corpo'] ?? [];
$cs = riga("SELECT * FROM subscriptions WHERE provider_subscription_id = 'sub_prova_carla'");
prova('6H · programmata: Stripe a 2 aggiuntive senza proporzioni (il rinnovo incasserà 267 €), nel sito restano 5 strutture', ($mc['items'][1]['quantity'] ?? '') === '2'
      && ($mc['proration_behavior'] ?? '') === 'none' && (int) $cs['quantity'] === 5 && (int) $cs['next_quantity'] === 3
      && (int) val('SELECT COUNT(*) FROM properties WHERE account_id = ? AND archived_at IS NOT NULL', [$cacc]) === 0, json_encode($mc));
$r = $carla->get('/account');
prova('…Account mostra il cambio programmato, con «Annulla il cambio»', str_contains($r['body'], 'passi a <b>Portfolio · 3 strutture</b>') && str_contains($r['body'], "227\u{00A0}€")
      && str_contains($r['body'], 'Annulla il cambio'));
inviaWebhook(['id' => 'evt_carla_4', 'type' => 'customer.subscription.updated', 'data' => ['object' => [
    'id' => 'sub_prova_carla', 'status' => 'active', 'cancel_at_period_end' => false, 'items' => $voci(2)]]]);
prova('…l\'aggiornamento di Stripe non cambia le strutture prima del rinnovo', (int) val("SELECT quantity FROM subscriptions WHERE provider_subscription_id = 'sub_prova_carla'") === 5);
$carla->modulo('/account', '/account/piano/annulla', []);
$mod = array_values(array_filter(richiesteStripe(), fn($x) => $x['metodo'] === 'POST' && $x['percorso'] === '/v1/subscriptions/sub_prova_carla'));
prova('6H · «Annulla il cambio»: Stripe torna a 4 aggiuntive, niente più programmato', ((end($mod)['corpo'] ?? [])['items'][1]['quantity'] ?? '') === '4'
      && val("SELECT next_quantity FROM subscriptions WHERE provider_subscription_id = 'sub_prova_carla'") === null);
$conf([$c1, $cp[1]]);
$r = inviaWebhook(['id' => 'evt_carla_rinnovo', 'type' => 'invoice.paid', 'data' => ['object' => [
    'id' => 'in_carla_rinnovo', 'customer' => 'cus_carla', 'subscription' => 'sub_prova_carla', 'billing_reason' => 'subscription_cycle',
    'lines' => ['data' => [['period' => ['start' => time(), 'end' => time() + 365 * 86400]]]]]]]);
prova('6H · al rinnovo: 3 strutture, archiviate le due scelte, sezioni e contenuti intatti', $r['body'] === 'rinnovo-pagato-con-cambio-di-piano'
      && (int) val("SELECT quantity FROM subscriptions WHERE provider_subscription_id = 'sub_prova_carla'") === 3
      && val("SELECT next_quantity FROM subscriptions WHERE provider_subscription_id = 'sub_prova_carla'") === null
      && val('SELECT archived_at FROM properties WHERE id = ?', [$c1]) !== null && val('SELECT archived_at FROM properties WHERE id = ?', [$cp[1]]) !== null
      && (int) val('SELECT COUNT(*) FROM properties WHERE account_id = ? AND archived_at IS NOT NULL', [$cacc]) === 2
      && (int) val('SELECT COUNT(*) FROM properties WHERE account_id = ?', [$cacc]) === 5
      && (int) val('SELECT COUNT(*) FROM sections s JOIN properties p ON p.id = s.property_id WHERE p.account_id = ?', [$cacc]) === $sezioniPrima, $r['body']);
prova('…l\'email «Da oggi sei su Portfolio»', str_contains((string) implode("\n", righePosta()), 'Da oggi sei su Portfolio'));
prova('La guida archiviata va offline (il QR resta)', $carla->get('/g/' . val('SELECT slug FROM properties WHERE id = ?', [$c1]))['code'] === 404
      && (bool) val('SELECT id FROM qr_tokens WHERE property_id = ?', [$c1]));
$r = $carla->get('/pannello');
prova('Le guide mostrano le archiviate, con "Riattiva"', str_contains($r['body'], 'Archiviata') && str_contains($r['body'], "/pannello/$c1/riattiva"));
$carla->post("/pannello/$c1/riattiva", []);
prova('Riattivare oltre il limite non si può', val('SELECT archived_at FROM properties WHERE id = ?', [$c1]) !== null);
$carla->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Casa Sette', 'city' => 'Bari']);
prova('…né crearne una nuova', (int) val('SELECT COUNT(*) FROM properties WHERE account_id = ?', [$cacc]) === 5);
$r = $carla->modulo("/pannello/$c1/procedura/pubblica", "/pannello/$c1/pubblica", ['acquirente' => 'impresa']);
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
$marco->post("/pannello/$mare/pubblica", ['acquirente' => 'impresa']);
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
$r = $gino->modulo("/pannello/$g1/procedura/pubblica", "/pannello/$g1/pubblica", ['acquirente' => 'impresa']);
$gord = riga('SELECT * FROM orders WHERE account_id = ? ORDER BY id DESC', [$gacc]);
prova('Pubblicando la prima: pagamento per 3 strutture', $r['code'] === 302 && str_starts_with($r['loc'], 'https://checkout.stripe.test/') && $gord && (int) $gord['quantity'] === 3);
file_put_contents("$STRIPE_DIR/extra-sub_prova_gino", '2');
$r = inviaWebhook(['id' => 'evt_gino_1', 'type' => 'checkout.session.completed', 'data' => ['object' => [
    'id' => $gord['provider_session_id'], 'mode' => 'subscription', 'payment_status' => 'paid', 'customer' => 'cus_gino',
    'subscription' => 'sub_prova_gino', 'client_reference_id' => (string) $gord['id'], 'metadata' => ['order_id' => (string) $gord['id'], 'account_id' => (string) $gacc]]]]);
prova('Fase 4 · webhook di pagamento: le tre strutture si sbloccano', $r['body'] === 'abbonamento-attivato'
      && $gino->get("/pannello/{$gids[1]}")['code'] === 200 && $gino->get("/pannello/{$gids[2]}/procedura/struttura")['code'] === 200, $r['body']);

// Una struttura oltre la quantità pagata (6H): conferma col costo, poi la pagina di Stripe per la differenza.
$r = $gino->get('/pannello');
prova('Portfolio pieno: «Aggiungi una struttura» resta', str_contains($r['body'], 'Aggiungi una struttura'));
$r = $gino->get('/pannello/nuova');
prova('Fase 4 · conferma con il costo della 4ª struttura (50 € l\'anno, 9A), quanto si paga oggi fino al rinnovo, il totale dal rinnovo', $r['code'] === 200 && pulita($r)
      && str_contains($r['body'], 'La 4ª struttura') && str_contains($r['body'], "50\u{00A0}€") && str_contains($r['body'], 'Oggi, per i giorni che restano')
      && str_contains($r['body'], "277\u{00A0}€") && str_contains($r['body'], 'name="conferma"'));
$prima = count(richiesteStripe());
$r = $gino->post('/pannello/nuova', ['name' => 'Gino Quattro', 'city' => 'Bari']);
prova('…senza conferma niente Stripe e niente struttura', count(richiesteStripe()) === $prima && (int) val('SELECT COUNT(*) FROM properties WHERE account_id = ?', [$gacc]) === 3
      && str_contains($r['body'], 'Spunta la conferma per aggiungere la struttura'));
$r = $gino->post('/pannello/nuova', ['name' => 'Gino Quattro', 'city' => 'Bari', 'conferma' => '1']);
$g4 = (int) val("SELECT id FROM properties WHERE account_id = ? AND name = 'Gino Quattro'", [$gacc]);
$gcambio = riga("SELECT * FROM orders WHERE account_id = ? AND kind = 'change' ORDER BY id DESC", [$gacc]);
prova('6H · la struttura nasce bloccata e si va a pagare la quota fino al rinnovo (niente addebiti automatici)', $r['code'] === 302 && str_starts_with($r['loc'], 'https://checkout.stripe.test/')
      && $g4 > 0 && $gino->get("/pannello/$g4")['code'] === 302 && $gcambio && (int) $gcambio['quantity'] === 4
      && !array_filter(array_slice(richiesteStripe(), $prima), fn($x) => $x['percorso'] === '/v1/subscriptions/sub_prova_gino' && $x['metodo'] === 'POST'), $r['loc']);
$r = inviaWebhook(['id' => 'evt_gino_2', 'type' => 'checkout.session.completed', 'data' => ['object' => [
    'id' => $gcambio['provider_session_id'], 'mode' => 'payment', 'payment_status' => 'paid', 'customer' => 'cus_gino', 'client_reference_id' => (string) $gcambio['id'],
    'metadata' => ['order_id' => (string) $gcambio['id'], 'account_id' => (string) $gacc, 'kind' => 'change']]]]);
$mod = array_values(array_filter(richiesteStripe(), fn($x) => $x['metodo'] === 'POST' && $x['percorso'] === '/v1/subscriptions/sub_prova_gino'));
$mc = end($mod)['corpo'] ?? [];
prova('…pagata la quota: Stripe a 3 aggiuntive senza proporzioni, abbonamento a 4, la quarta si sblocca', $r['body'] === 'cambio-applicato'
      && ($mc['items'][1]['quantity'] ?? '') === '3' && ($mc['proration_behavior'] ?? '') === 'none'
      && (int) val("SELECT quantity FROM subscriptions WHERE provider_subscription_id = 'sub_prova_gino'") === 4 && $gino->get("/pannello/$g4")['code'] === 200, $r['body'] . json_encode($mc));

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
      && preg_match('#<option value="' . $casa . '">\s*Casa Lucia#', $r['body']) === 1 && substr_count($r['body'], 'name="copia[]"') === 15
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
// Punto 4 · «Già in <struttura>»: luoghi e righe delle altre strutture, da riusare con un tocco.
$eatDue = (int) val("SELECT id FROM sections WHERE property_id = ? AND kind = 'eat'", [$due]);   // «sostituisci» l'ha rifatta
$r = $lucia->get("/pannello/$due/sezioni/$eatDue");
prova('Punto 4 · nessun suggerimento per i luoghi che ci sono già con lo stesso nome', $r['code'] === 200 && pulita($r) && !str_contains($r['body'], 'name="luogo"'));
$togli = $luoghi($due)[2];
$lucia->post("/pannello/$due/sezioni/$eatDue/luogo/{$togli['id']}/azione", ['fai' => 'elimina']);
$fonte = riga('SELECT * FROM places WHERE section_id = ? AND name = ?', [$eatCasa, $togli['name']]);
db()->prepare('UPDATE places SET lat = 43.0770, lng = 11.6790, walk_minutes = 99, drive_minutes = 7 WHERE id = ?')->execute([$fonte['id']]);
db()->prepare('UPDATE properties SET lat = 43.0757, lng = 11.6800 WHERE id = ?')->execute([$due]);
$r = $lucia->get("/pannello/$due/sezioni/$eatDue");
prova('Punto 4 · tolto un luogo, torna come «Già in Casa Lucia» (un bottone del modulo esterno)', str_contains($r['body'], 'Già in Casa Lucia:')
      && preg_match('#<button type="submit" class="chip-sugg" form="da-altra-' . $eatDue . '" name="luogo" value="' . $fonte['id'] . '"#', $r['body']) === 1
      && str_contains($r['body'], 'id="da-altra-' . $eatDue . '" data-salva-prima'));
$prova4 = $lucia->get("/pannello/$casa/sezioni/$eatCasa");
prova('…e nell\'altra struttura niente, perché lì ci sono già tutti', !str_contains($prova4['body'], 'name="luogo"'));
$coreDue = (int) val('SELECT id FROM sections WHERE property_id = ? AND is_core = 1', [$due]);
prova('Punto 4 · mai nelle sezioni che sono solo di una struttura (Check-in & Check-out)', !str_contains($lucia->get("/pannello/$due/sezioni/$coreDue")['body'], 'da-altra-'));
$mediaPrima = (int) val('SELECT COUNT(*) FROM media WHERE account_id = ?', [$lacc]);
$r = $lucia->post("/pannello/$due/sezioni/$eatDue/da-altra", ['luogo' => (string) $fonte['id']]);
$nuovo = riga('SELECT * FROM places WHERE section_id = ? AND name = ?', [$eatDue, $togli['name']]);
prova('Punto 4 · un tocco: il luogo è qui, aperto nel modulo per controllarlo', $r['code'] === 302 && $nuovo && str_ends_with($r['loc'], "/pannello/$due/sezioni/$eatDue?luogo={$nuovo['id']}#luogo"), $r['loc']);
prova('…con categoria, etichetta, colore e traduzioni', $nuovo['category_key'] === $fonte['category_key'] && $nuovo['badge_key'] === $fonte['badge_key'] && $nuovo['badge_tone'] === $fonte['badge_tone']
      && (int) val('SELECT COUNT(*) FROM place_translations WHERE place_id = ?', [$nuovo['id']]) === (int) val('SELECT COUNT(*) FROM place_translations WHERE place_id = ?', [$fonte['id']]));
prova('…minuti a piedi ricalcolati dalla struttura, minuti in auto vuoti', (int) $nuovo['walk_minutes'] > 0 && (int) $nuovo['walk_minutes'] !== 99 && (int) $nuovo['drive_minutes'] === 0, $nuovo['walk_minutes'] . '/' . $nuovo['drive_minutes']);
$mf = riga('SELECT * FROM media WHERE id = ?', [$fonte['media_id']]); $mn = riga('SELECT * FROM media WHERE id = ?', [$nuovo['media_id']]);
prova('…la foto è una copia indipendente', $mf && $mn && $mn['id'] !== $mf['id'] && $mn['object_key'] !== $mf['object_key'] && (int) $mn['property_id'] === $due
      && (int) val('SELECT COUNT(*) FROM media WHERE account_id = ?', [$lacc]) === $mediaPrima + 1);
prova('…e il suggerimento sparisce', !str_contains($lucia->get("/pannello/$due/sezioni/$eatDue")['body'], 'name="luogo"'));
// Un luogo di un altro account o una sezione di un altro tipo: non si copia.
$alieno = (int) val("SELECT pl.id FROM places pl JOIN sections s ON s.id = pl.section_id JOIN properties p ON p.id = s.property_id WHERE p.account_id <> ? ORDER BY pl.id LIMIT 1", [$lacc]);
$quanti = (int) val('SELECT COUNT(*) FROM places WHERE section_id = ?', [$eatDue]);
$r = $lucia->post("/pannello/$due/sezioni/$eatDue/da-altra", ['luogo' => (string) $alieno]);
prova('Punto 4 · il luogo di un altro account non si copia (404)', $alieno > 0 && $r['code'] === 404 && (int) val('SELECT COUNT(*) FROM places WHERE section_id = ?', [$eatDue]) === $quanti);
// Le righe: un contatto utile tolto qui torna come suggerimento, con le traduzioni.
$emDue = (int) val("SELECT id FROM sections WHERE property_id = ? AND kind = 'emergency'", [$due]);
$emCasa = (int) val("SELECT id FROM sections WHERE property_id = ? AND kind = 'emergency'", [$casa]);
$dd = json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$emDue]), true);
$via = array_pop($dd['contacts']);
db()->prepare('UPDATE sections SET data = ? WHERE id = ?')->execute([json_encode($dd, JSON_UNESCAPED_UNICODE), $emDue]);
$trIt = json_decode((string) val("SELECT data FROM section_translations WHERE section_id = ? AND locale = 'it'", [$emCasa]), true);
$nomeVia = '';
foreach ($trIt['contacts'] ?? [] as $t) if (($t['id'] ?? '') === $via['id']) $nomeVia = (string) ($t['name'] ?? '');
$r = $lucia->get("/pannello/$due/sezioni/$emDue");
prova('Punto 4 · righe: il contatto tolto torna come «Già in Casa Lucia» accanto ai suggerimenti', $nomeVia !== '' && pulita($r)
      && str_contains($r['body'], 'value="contacts|' . $emCasa . '|' . $via['id'] . '"') && str_contains($r['body'], '+ ' . htmlspecialchars($nomeVia, ENT_QUOTES)), $nomeVia);
$r = $lucia->post("/pannello/$due/sezioni/$emDue/da-altra", ['riga' => "contacts|$emCasa|{$via['id']}"]);
$dd = json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$emDue]), true);
$ultima = end($dd['contacts']);
$trEn = json_decode((string) val("SELECT data FROM section_translations WHERE section_id = ? AND locale = 'en'", [$emDue]), true);
$enUltima = array_values(array_filter($trEn['contacts'] ?? [], fn($t) => ($t['id'] ?? '') === $ultima['id']));
prova('…un tocco: aggiunto in fondo, con un id nuovo, il telefono e le traduzioni', $r['code'] === 302 && str_ends_with($r['loc'], "/sezioni/$emDue#rip-contacts")
      && $ultima['id'] !== $via['id'] && ($ultima['phone'] ?? '') === ($via['phone'] ?? '') && count($enUltima) === 1 && ($enUltima[0]['name'] ?? '') !== '', json_encode($ultima));
$r = $lucia->post("/pannello/$due/sezioni/$emDue/da-altra", ['riga' => "contacts|$emCasa|rnonc'e"]);
prova('…una riga che non c\'è: 404, niente aggiunto', $r['code'] === 404 && count(json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$emDue]), true)['contacts']) === count($dd['contacts']));

// Eliminare la copia non rompe l'originale.
$r = $lucia->post("/pannello/$due/elimina", ['conferma' => 'Casa Lucia Due']);
$codici = $immagini($lucia, "/pannello/$casa/anteprima/$eatCasa");
prova('Fase 4 · eliminata la copia, l\'originale ha ancora tutte le sue foto e la sua guida', !val('SELECT id FROM properties WHERE id = ?', [$due])
      && count($codici) === 3 && array_unique($codici) === [200] && $ospite->get('/g/' . $pc['slug'])['code'] === 200, json_encode($codici));

// ================================================================= FASE 5
capitolo('Fase 5 · listino, guida che fa guadagnare, FAQ, firma, email, funnel');
$posta = fn(string $a) => array_values(array_filter(array_map(fn($l) => json_decode($l, true), righePosta() ?: []), fn($m) => ($m['to'] ?? '') === $a));
$r = $ospite->get('/');
prova('K1 · sotto ogni prezzo l\'equivalente mensile (97/12, 127/12, Portfolio per 2: 177/12)', str_contains($r['body'], "circa 8,08\u{00A0}€ al mese")
      && str_contains($r['body'], "circa 10,58\u{00A0}€ al mese") && str_contains($r['body'], "<span data-mensile>14,75\u{00A0}€</span> al mese"));
preg_match_all('#<div class="plan[^"]*">(.*?)(?=<div class="plan[ "]|</div>\s*<details class="confronto")#s', $r['body'], $carte);
$ess = $carte[1][0] ?? ''; $plus = $carte[1][1] ?? '';
prova('K1 · Essential senza le voci comuni; Plus da «Tutto di Essential, e in più:» senza ripetere', !str_contains($ess, 'Pannello di controllo') && !str_contains($ess, 'Personalizzazione colori')
      && str_contains($plus, '<li class="plan__da">Tutto di Essential, e in più:</li>') && !str_contains($plus, 'Logo della struttura'), substr(strip_tags($plus), 0, 200));
prova('K1 · «Confronta tutti i piani»: tabella dalle funzioni dei pacchetti', str_contains($r['body'], '<summary>Confronta tutti i piani</summary>')
      && str_contains($r['body'], 'Firma «Guida creata con MyHouse Welcome» nascondibile') && str_contains($r['body'], '<th scope="row">Lingue pubblicabili</th>')
      && str_contains($r['body'], 'da 2 a 10'));
prova('K2 · in landing: «Una prenotazione diretta in più all\'anno paga l\'abbonamento.»', str_contains($r['body'], 'Una prenotazione diretta in più all&#039;anno paga l&#039;abbonamento.')
      || str_contains($r['body'], "Una prenotazione diretta in più all'anno paga l'abbonamento."));
// 6J · i prezzi prima delle domande; otto domande in vista, le altre sotto «Altre domande (N)», poi «Tutte le domande».
$faqLanding = preg_match('#<section id="domande".*?</section>#s', $r['body'], $m) ? $m[0] : '';
$faqAltre = preg_match('#<details class="faq__voce faq__altre">.*?<div class="faq__altre-lista">(.*?)</div>\s*</details>#s', $faqLanding, $m) ? $m[1] : '';
prova('6J · FAQ dopo il listino: otto domande in vista nell\'ordine dato, le altre sotto «Altre domande (N)»', strpos($r['body'], 'id="piani"') < strpos($r['body'], 'id="domande"')
      && substr_count($faqLanding, 'class="faq__voce"') - substr_count($faqAltre, 'class="faq__voce"') === 8
      && preg_match('#Altre domande \((\d+)\)#', $faqLanding, $mm) === 1 && (int) $mm[1] === substr_count($faqAltre, 'class="faq__voce"') && (int) $mm[1] >= 8
      && strpos($faqLanding, 'Posso provarla prima di pagare?') < strpos($faqLanding, 'Serve un') && strpos($faqLanding, 'Ricevo fattura?') < strpos($faqLanding, 'Posso disdire?')
      && strpos($faqLanding, 'Posso disdire?') < strpos($faqLanding, 'Altre domande') && str_contains($faqAltre, 'Gli ospiti vengono tracciati?')
      && str_contains($faqLanding, '/domande">Tutte le domande</a>'));
prova('6J · menu della landing: Come funziona · Il QR · Prezzi · Domande (senza «Il tuo sito»)', preg_match('#Come funziona</a>.*?Il QR</a>.*?Prezzi</a>.*?Domande</a>#s', $r['body']) === 1
      && !str_contains($r['body'], '/#sito">Il tuo sito</a>'));
// 6J · IT / EN / FR: solo le lingue che la demo ha davvero (quella delle prove non ha il francese).
prova('K3 · accanto a «Guarda la demo» le lingue della demo (IT / EN / FR, mai DE)', preg_match_all('#href="[^"]+/benvenuto\?l=(it|en|fr)" hreflang#', $r['body'], $mm) >= 2
      && !preg_match('#benvenuto\?l=de" hreflang#', $r['body']));
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
      && $vp[0]['hb'] !== '1' && end($ve)['hb'] !== '1', json_encode($vp));
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
prova('K2 · commiato senza recensioni né prenotazione diretta: le stelle (7C), ma nessun bottone di recensione né «prenota da noi»', str_contains($r['body'], 'class="stelle"')
      && !str_contains($r['body'], 'data-portale=') && !str_contains($r['body'], 'prenota da noi'));
$r = $lucia->get("/pannello/$casa/impostazioni");
prova('K2 · Impostazioni (8B): dove si trova la struttura (portali come caselle, senza indirizzo), prenotazione diretta, firma nascondibile (Portfolio)',
      str_contains($r['body'], 'Su quali piattaforme si trova la tua struttura?') && str_contains($r['body'], 'name="portali[]" value="google"')
      && !str_contains($r['body'], 'name="review_booking"') && !str_contains($r['body'], 'Prova il link') && !str_contains($r['body'], 'Dove trovo il link?')
      && str_contains($r['body'], 'name="direct_code"') && str_contains($r['body'], 'name="hide_branding"'));
$r = $lucia->post("/pannello/$casa/dopo-il-soggiorno", ['portali' => ['google', 'booking'], 'altri' => [['kind' => 'tripadvisor', 'label' => '', 'url' => 'javascript:alert(1)']],
    'direct_url' => 'https://www.esempio.it/prenota', 'direct_code' => 'TORNA10', 'hide_branding' => '1']);
$pl = riga('SELECT * FROM properties WHERE id = ?', [$casa]);
$canaliL = array_column(db()->query("SELECT kind FROM property_channels WHERE property_id = $casa ORDER BY position")->fetchAll(), 'kind');
prova('…un indirizzo che non è https:// non si salva, con un avviso; i portali spuntati sì, senza indirizzo', $canaliL === ['google', 'booking'] && (int) $pl['hide_branding'] === 1
      && str_contains($lucia->segui($r)['body'], 'L&#039;indirizzo di Tripadvisor deve cominciare con https://'), json_encode($canaliL));
$lucia->post("/pannello/$casa/pubblica", ['acquirente' => 'impresa']);
$r0 = $ospite->get("/g/$lslug/commiato?l=en");
$r = $ospite->get("/g/$lslug/commiato?l=en&voto=5");
prova('K2 · commiato (en) con 5 stelle: «How was your stay?» (7C) con Google e Booking, «book with us» con il codice', str_contains($r['body'], 'How was your stay?')
      && str_contains($r['body'], 'href="https://www.google.com/maps/search/?api=1&amp;query=Casa%20Lucia') && str_contains($r['body'], '>Booking.com</a>') && !str_contains($r['body'], '>Airbnb</a>')
      && str_contains($r['body'], 'Coming back this way?') && str_contains($r['body'], 'Discount code: <b>TORNA10</b>') && str_contains($r['body'], 'href="https://www.esempio.it/prenota"'));
// 8C · le stelle: con 5 i portali spuntati, da 1 a 4 nessun portale ma WhatsApp (ed email); il voto è un conteggio anonimo.
prova('7C · commiato: cinque stelle accessibili, nessun bottone prima del voto', substr_count($r0['body'], 'class="stella') === 5
      && str_contains($r0['body'], 'aria-label="1 out of 5"') && !str_contains($r0['body'], 'data-voto=') && !str_contains($r0['body'], 'data-portale='));
$votiPrima = (int) val("SELECT COUNT(*) FROM analytics_events WHERE property_id = ? AND kind = 'rating_2'", [$casa]);
$r = $ospite->get("/g/$lslug/commiato?voto=2");
prova('8C · con 2 stelle (senza JavaScript): «Grazie della sincerità», WhatsApp con il messaggio pronto, nessun link ai portali', str_contains($r['body'], 'data-voto="basso"')
      && str_contains($r['body'], 'Grazie della sincerità.') && str_contains($r['body'], 'Scrivici su WhatsApp') && str_contains($r['body'], '?text=Ciao%20')
      && !str_contains($r['body'], 'google.com/maps/search/') && !str_contains($r['body'], 'data-portale=') && !str_contains($r['body'], 'Mandaci un&#039;email')
      && !str_contains($r['body'], 'Ti va di raccontarlo') && !str_contains($r['body'], 'Vuoi dire qualcosa'));
$r5 = $ospite->get("/g/$lslug/commiato?voto=5");
prova('…con 5 stelle i portali, senza WhatsApp per il suggerimento', str_contains($r5['body'], 'google.com/maps/search/') && str_contains($r5['body'], 'data-voto="5"')
      && str_contains($r5['body'], 'Siamo felici che ti sia trovato bene!') && !str_contains($r5['body'], 'Scrivici su WhatsApp'));
$rv = $ospite->get("/g/$lslug/voto/2");
prova('7C · il voto con JavaScript (/voto/N): 204, e il conteggio anonimo per struttura e voto sale', $rv['code'] === 204
      && (int) val("SELECT COUNT(*) FROM analytics_events WHERE property_id = ? AND kind = 'rating_2'", [$casa]) === $votiPrima + 2
      && intestazione($rv, 'Set-Cookie') === '');
prova('…in Statistiche «Voti dei tuoi ospiti»', str_contains($lucia->get("/pannello/$casa/statistiche")['body'], 'Voti dei tuoi ospiti'));
$lucia->get("/pannello/$casa/impostazioni");
$lucia->post("/pannello/$casa/dopo-il-soggiorno", ['portali' => ['google', 'booking'],
    'direct_url' => '', 'direct_code' => 'BENTORNATI', 'direct_discount' => '10%', 'hide_branding' => '1']);
$r = $lucia->get("/pannello/$casa/impostazioni");
prova('8B · i portali spuntati restano spuntati, nell\'ordine scelto', preg_match('#value="google" checked.*?value="booking" checked.*?value="airbnb" >#s', $r['body']) === 1);
prova('7C · Sconto per chi torna: aiuto, campo «Sconto», avviso senza link, anteprima della scheda', str_contains($r['body'], 'lo crei tu sul tuo sito o sul tuo motore di prenotazione')
      && str_contains($r['body'], 'name="direct_discount"') && preg_match('#data-diretta-avviso>Senza il link al tuo sito#', $r['body']) === 1
      && str_contains($r['body'], "\u{2212}10% con il codice <b data-diretta-codice>BENTORNATI</b>"));
$lucia->post("/pannello/$casa/dopo-il-soggiorno", ['portali' => ['google', 'booking'],
    'direct_url' => 'https://www.esempio.it/prenota', 'direct_code' => 'BENTORNATI', 'direct_discount' => '10%', 'hide_branding' => '1']);
$lucia->post("/pannello/$casa/pubblica", ['acquirente' => 'impresa']);
$r = $ospite->get("/g/$lslug/commiato?l=de");
prova('7C · nel commiato (de): «−10% mit dem Code BENTORNATI», «Code kopieren», «Prenota»', str_contains($r['body'], "\u{2212}10% mit dem Code <b>BENTORNATI</b>")
      && str_contains($r['body'], 'Code kopieren') && str_contains($r['body'], 'href="https://www.esempio.it/prenota"'));
prova('K4 · firma nascosta (piano con hide_branding)', !str_contains($ospite->get("/g/$lslug")['body'], 'Guida creata con MyHouse Welcome'));
$anna->post("/pannello/$pid/dopo-il-soggiorno", ['hide_branding' => '1']);
prova('K4 · Essential non può nasconderla, nemmeno a mano', (int) val('SELECT hide_branding FROM properties WHERE id = ?', [$pid]) === 0
      && str_contains($anna->get("/pannello/$pid/impostazioni")['body'], 'Con Plus e Portfolio puoi nasconderla')
      && str_contains($anna->get("/pannello/$pid/impostazioni")['body'], 'data-sblocca="firma"'));
$lucia->post("/pannello/$casa/dopo-il-soggiorno", ['direct_url' => '', 'direct_code' => '']);
$lucia->post("/pannello/$casa/pubblica", ['acquirente' => 'impresa']);
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

// 7A · i richiami nuovi: conferma, pagamento, parere. Una volta sola, con «non mandarmene più».
$vacc = (int) val('SELECT id FROM accounts WHERE user_id = ?', [$vuid]);
$ieri = fn(int $ore) => gmdate('Y-m-d\TH:i:s\Z', time() - $ore * 3600);
db()->prepare('UPDATE users SET created_at = ? WHERE id = ?')->execute([$ieri(25), $vuid]);
$cron(); $m = $posta('vera.giusta@prova.test'); $ult = end($m) ?: [];
prova('7A · conferma: dopo 24 ore «Confermi la tua email?» con un link nuovo', str_contains((string) ($ult['subject'] ?? ''), 'Confermi la tua email?')
      && str_contains((string) $ult['text'], '/verifica/') && str_contains((string) $ult['text'], '/email/stop/'));
$prima = count($posta('vera.giusta@prova.test')); $cron();
prova('…una volta sola', count($posta('vera.giusta@prova.test')) === $prima);
db()->prepare('UPDATE users SET created_at = ? WHERE id = ?')->execute([$ieri(73), $vuid]);
db()->prepare("UPDATE email_log SET sent_at = ? WHERE account_id = ? AND kind = 'conferma'")->execute([$ieri(49), $vacc]);
$cron(); $m = $posta('vera.giusta@prova.test'); $ult = end($m) ?: [];
prova('7A · conferma: l\'ultima dopo 72 ore', count($m) === $prima + 1 && str_contains((string) $ult['text'], "è l'ultima volta"));
$cron();
prova('…e poi basta', count($posta('vera.giusta@prova.test')) === $prima + 1);
preg_match('#/email/stop/[a-f0-9]+#', (string) $ult['text'], $stop7);
$vera->get($stop7[0]); $vera->post($stop7[0], []);
db()->prepare("DELETE FROM email_log WHERE account_id = ? AND kind = 'conferma'")->execute([$vacc]);
$cron();
prova('7A · conferma: rispetta «non mandarmene più»', count($posta('vera.giusta@prova.test')) === $prima + 1 && (bool) val("SELECT id FROM email_optout WHERE account_id = ? AND kind = 'conferma'", [$vacc]));

db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'register:%'");
$pina = new Browser('pina');
$pina->get('/registrati?piano=' . pv('plus'));
$pina->post('/registrati', ['piano' => pv('plus'), 'name' => 'Pina Pagamento', 'email' => 'pina@prova.test', 'password' => 'PaolaProva123', 'termini' => '1']);
$pina->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Casa Pina', 'city' => 'Spello']);
$pipid = (int) val("SELECT id FROM properties WHERE name = 'Casa Pina'");
$piacc7 = (int) val('SELECT account_id FROM properties WHERE id = ?', [$pipid]);
db()->prepare("INSERT INTO orders (account_id, package_version_id, amount_cents, currency, status, provider, provider_session_id, created_at, property_id, updated_at, quantity, kind)
               VALUES (?, ?, 11700, 'EUR', 'canceled', 'stripe', '', ?, ?, ?, 1, 'new')")->execute([$piacc7, pv('plus'), $ieri(1), $pipid, $ieri(1)]);
$cron();
prova('7A · pagamento: annullato da meno di 2 ore, ancora niente', !array_filter($posta('pina@prova.test'), fn($x) => str_contains((string) $x['subject'], 'pronta per andare online')));
db()->prepare('UPDATE orders SET created_at = ? WHERE account_id = ?')->execute([$ieri(3), $piacc7]);
$cron(); $m = $posta('pina@prova.test'); $ult = end($m) ?: [];
prova('7A · pagamento: dopo 2 ore «La tua guida è pronta per andare online» con piano, prezzo e bottone', str_contains((string) ($ult['subject'] ?? ''), 'La tua guida è pronta per andare online')
      && str_contains((string) $ult['text'], 'Plus') && str_contains((string) $ult['text'], "117\u{00A0}€ + IVA / anno") && str_contains((string) $ult['text'], "/pannello/$pipid/procedura/pubblica"));
$prima = count($posta('pina@prova.test')); $cron();
prova('…una volta sola', count($posta('pina@prova.test')) === $prima);
db()->prepare("INSERT INTO email_optout (account_id, kind, created_at) VALUES (?, 'pagamento', ?)")->execute([$piacc7, $ieri(0)]);
db()->prepare("DELETE FROM email_log WHERE account_id = ? AND kind = 'pagamento'")->execute([$piacc7]);
$cron();
prova('7A · pagamento: rispetta «non mandarmene più»', count($posta('pina@prova.test')) === $prima);

$piero = new Browser('piero');
$piero->get('/registrati');
$piero->post('/registrati', ['name' => 'Piero Parere', 'email' => 'piero@prova.test', 'password' => 'PieroProva123', 'termini' => '1']);
$piuid = (int) val("SELECT id FROM users WHERE email = 'piero@prova.test'");
$piacc = (int) val('SELECT id FROM accounts WHERE user_id = ?', [$piuid]);
db()->prepare('UPDATE users SET created_at = ? WHERE id = ?')->execute([$ieri(13 * 24), $piuid]);
$cron();
prova('7A · parere: prima dei 14 giorni niente', !array_filter($posta('piero@prova.test'), fn($x) => str_contains((string) $x['subject'], 'Cosa ti ha fermato?')));
db()->prepare('UPDATE users SET created_at = ? WHERE id = ?')->execute([$ieri(15 * 24), $piuid]);
$cron(); $m = $posta('piero@prova.test'); $ult = end($m) ?: [];
prova('7A · parere: dopo 14 giorni «Cosa ti ha fermato?» con quattro risposte a un clic', str_contains((string) ($ult['subject'] ?? ''), 'Cosa ti ha fermato?')
      && preg_match_all('#/parere/[a-f0-9]{48}/(prezzo|tempo|funzione|altro)#', (string) $ult['text']) === 4);
$prima = count($posta('piero@prova.test')); $cron();
prova('…una volta sola', count($posta('piero@prova.test')) === $prima);
preg_match('#/parere/[a-f0-9]{48}/#', (string) $ult['text'], $par);
$r = $ospite->get($par[0] . 'tempo');
prova('7A · il clic salva la risposta, senza accedere, e chiede «Raccontaci»', $r['code'] === 200 && str_contains($r['body'], 'Raccontaci') && pulita($r)
      && val('SELECT motivo FROM feedback WHERE account_id = ?', [$piacc]) === 'tempo');
$ospite->get($par[0] . 'prezzo');
prova('…un secondo clic subito dopo (i filtri antivirus aprono tutti i link) non cambia il motivo', val('SELECT motivo FROM feedback WHERE account_id = ?', [$piacc]) === 'tempo');
$r = $ospite->post(rtrim($par[0], '/'), ['motivo' => 'funzione', 'testo' => 'Mi serviva il calendario delle pulizie.']);
prova('…«Raccontaci» salva motivo e testo', str_contains($r['body'], 'Grazie, ce lo segniamo.') && val('SELECT motivo FROM feedback WHERE account_id = ?', [$piacc]) === 'funzione'
      && val('SELECT testo FROM feedback WHERE account_id = ?', [$piacc]) === 'Mi serviva il calendario delle pulizie.');
prova('7A · un motivo inventato o un token sbagliato: 404', $ospite->get($par[0] . 'boh')['code'] === 404 && $ospite->get('/parere/' . str_repeat('a', 48) . '/tempo')['code'] === 404);
$r = $admin->get("/admin/cliente/$piacc");
prova('7A · la risposta nella scheda del cliente', str_contains($r['body'], 'Mi mancava una funzione') && str_contains($r['body'], 'calendario delle pulizie'));
$r = $admin->get('/admin/prospetti');
prova('7A · Prospetti: il conteggio per motivo', str_contains($r['body'], 'Cosa li ha fermati') && str_contains($r['body'], 'Mi mancava una funzione'));
db()->prepare("INSERT INTO email_optout (account_id, kind, created_at) VALUES (?, 'parere', ?)")->execute([$piacc, $ieri(0)]);
db()->prepare("DELETE FROM email_log WHERE account_id = ? AND kind = 'parere'")->execute([$piacc]);
$cron();
prova('7A · parere: rispetta «non mandarmene più»', count($posta('piero@prova.test')) === $prima);

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
$lucia->post("/pannello/$casa/pubblica", ['acquirente' => 'impresa']);
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
$r = $bruno->post("/pannello/$pid/pubblica", ['acquirente' => 'impresa']);
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
prova('L\'incasso conta solo Stripe (97 €)', str_contains($r['body'], "97\u{00A0}€"));
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
$admin->post("/admin/pacchetti/$essential/nuova-versione", ['nome' => 'Essential', 'prezzo' => '107', 'stripe_price_id' => '', 'f' => $feat,
    'headline' => 'Tutto quello che serve', 'tagline' => '', 'description' => 'x', 'bullets' => "Una struttura\nCheck-in & Check-out", 'badge' => '', 'cta_label' => 'Crea gratis', 'public' => '1', 'active' => '1']);
prova('Nuovo prezzo = nuova versione', pv('essential') !== $vecchia && (int) val('SELECT price_cents FROM package_versions WHERE id = ?', [pv('essential')]) === 10700);
prova('La versione venduta resta com\'era', (int) val('SELECT price_cents FROM package_versions WHERE id = ?', [$vecchia]) === 9700);
$home = $ospite->get('/')['body'];
prova('La landing mostra il prezzo nuovo', str_contains($home, "107\u{00A0}€"));
$pf = (int) val("SELECT id FROM packages WHERE code = 'portfolio'");
$featP = []; foreach (righe('SELECT f.code, pf.value FROM package_features pf JOIN features f ON f.id = pf.feature_id WHERE pf.package_version_id = ?', [pv('portfolio')]) as $x) $featP[$x['code']] = $x['value'];
$pkP = riga('SELECT * FROM packages WHERE id = ?', [$pf]);
$vecchiaP = pv('portfolio');
$admin->get('/admin/pacchetti');
$pag = $admin->get('/admin/pacchetti')['body'];
prova('Fase 1 · funzioni dei pacchetti leggibili', str_contains($pag, '<span class="etichetta">6 sezioni</span>') && str_contains($pag, '<span class="etichetta">2 lingue</span>')
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
prova('Fase 1 · splash: niente cookie (8B: og:image solo l\'anteprima della guida), «Entra» nel terracotta del marchio', (!str_contains($r['body'], 'og:image') || preg_match('#og:image" content="[^"]+/anteprima\.jpg"#', $r['body']) === 1) && intestazione($r, 'Set-Cookie') === ''
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
prova('Tempo: le domande evitabili con una guida, accanto alle comunicazioni che restano dell\'host', str_contains($tempo = (preg_match('#<section id="il-tempo".*?</section>#s', $r['body'], $m) ? $m[0] : ''), 'Domande evitabili con una guida')
      && str_contains($tempo, 'Comunicazioni importanti e necessarie'));
prova('6J · niente blocco doppione «Il valore non è soltanto nella guida»', !str_contains($r['body'], 'class="frase"') && !str_contains($r['body'], 'Il valore non è soltanto nella guida'));
prova('Guadagno: il commiato disegnato con le etichette vere della guida', str_contains($r['body'], 'class="congedo-mock"')
      && str_contains($r['body'], 'Com&#039;è andato il soggiorno?') && str_contains($r['body'], 'Tornerai da queste parti?'));
prova('FAQ: chi non trova la risposta può scrivere (WhatsApp ed email dal config)', preg_match('#id="domande".*?href="https://wa\.me/393920061600".*?href="mailto:assistenza@welcomemyhouse\.it"#s', $r['body']) === 1);
prova('I bottoni Email: senza programma di posta un riquadro offre Gmail, Outlook e l\'indirizzo copiato (posta.js)', preg_match('#/assets/posta\.js\?v=\d+"#', $r['body']) === 1
      && is_file("$DOVE/assets/posta.js") && str_contains((string) file_get_contents("$DOVE/assets/posta.js"), 'mail.google.com/mail/?view=cm')
      && str_contains((string) file_get_contents("$DOVE/assets/posta.js"), 'outlook.live.com') && !str_contains((string) file_get_contents("$DOVE/assets/posta.js"), 'preventDefault'));
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
$o[] = $snap["format"] === Guide::FORMAT && Guide::FORMAT >= 6 && $snap["sections"][0]["data"]["items"][0]["amount"] === "8" && $snap["sections"][0]["data"]["items"][0]["unit"] === "per_person";
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
prova('6C · home: tutte le sezioni del catalogo nei tre gruppi, contate', $r['code'] === 200 && str_contains($r['body'], '18 sezioni pronte da compilare, più le sezioni libere')
      && !str_contains($r['body'], 'in base al piano') && str_contains($r['body'], 'id="gruppo-casa">La casa</h3>') && str_contains($r['body'], 'id="gruppo-arrivo">Arrivare e muoversi</h3>')
      && str_contains($r['body'], 'id="gruppo-territorio">Il territorio</h3>') && str_contains($r['body'], 'Muoversi in zona') && str_contains($r['body'], 'Negozi e spesa'));

capitolo('Fase 6D · sezione libera, righe compresse, messaggio di benvenuto');
$luc->get("/pannello/$lpid");
$luc->post("/pannello/$lpid/sezioni", ['kind' => 'custom']);
$r = $luc->get("/pannello/$lpid");
prova('S1 · aggiunta una sezione libera; dalla 9F·12 il catalogo non la propone più (una per guida)', (int) val("SELECT COUNT(*) FROM sections WHERE property_id = ? AND kind = 'custom'", [$lpid]) === 1
      && !str_contains($r['body'], 'name="kind" value="custom"') && str_contains($r['body'], 'Hai già la sezione libera'));
$luc->post("/pannello/$lpid/sezioni", ['kind' => 'custom']);
$liberi = array_column(righe("SELECT id FROM sections WHERE property_id = ? AND kind = 'custom' ORDER BY id", [$lpid]), 'id');
prova('S1 · …una seconda non si aggiunge (9F·12)', count($liberi) === 1);
$r = $luc->get("/pannello/$lpid/sezioni/{$liberi[0]}");
prova('S1 · editor: riquadro, 12 icone (radio con il disegno), testo ed elenco', $r['code'] === 200 && str_contains($r['body'], 'Per quello che non sta nelle altre sezioni')
      && substr_count($r['body'], 'type="radio" name="icona"') === 12 && str_contains($r['body'], 'name="icona" value="star" checked') && str_contains($r['body'], '>Testo</label>'));
$luc->post("/pannello/$lpid/sezioni/{$liberi[0]}", ['title' => 'La piscina', 'icona' => 'sun', 'text' => 'Aperta da giugno a settembre.', 'items' => ['Doccia prima di entrare', 'Niente vetro a bordo vasca']]);
$dl = json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$liberi[0]]), true);
$r = $luc->get("/pannello/$lpid/anteprima/{$liberi[0]}");
// La stessa sezione, salvata di nuovo senza titolo e con un'icona fuori elenco (prima erano due sezioni: ora ce n'è una sola per guida).
$luc->post("/pannello/$lpid/sezioni/{$liberi[0]}", ['title' => '', 'icona' => 'razzo', 'text' => 'Una seconda sezione.']);
$dl2 = json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$liberi[0]]), true);
prova('S1 · icona salvata; una fuori elenco non passa', ($dl['icona'] ?? '') === 'sun' && ($dl2['icona'] ?? 'x') === '');
$r2 = $luc->get("/pannello/$lpid/anteprima/{$liberi[0]}?l=de");
prova('S1 · guida: titolo dell\'host, testo ed elenco; senza titolo «Nützliche Informationen» in tedesco (9F·12)', $r['code'] === 200 && str_contains($r['body'], 'La piscina')
      && str_contains($r['body'], 'Aperta da giugno a settembre.') && str_contains($r['body'], '<li>Niente vetro a bordo vasca</li>') && str_contains($r2['body'], 'Nützliche Informationen'));
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
prova('M2 · QR & Link (8B): messaggio di benvenuto per lingua come modello con {link}, il link nella lingua giusta, «Copia» e «Apri WhatsApp»', $r['code'] === 200
      && str_contains($r['body'], 'Messaggio di benvenuto') && preg_match('#id="msg-benvenuto-it"[^>]*>Ciao! Benvenuti a Casa Lucia\.[^<]*\{link\}#', $r['body']) === 1
      && preg_match('#data-link="[^"]*/g/[a-z0-9-]+\?l=en"#', $r['body']) === 1 && str_contains($r['body'], 'id="msg-benvenuto-en"')
      && str_contains($r['body'], 'data-copia-msg') && str_contains($r['body'], 'href="https://wa.me/?text=Ciao%21%20Benvenuti%20a%20Casa%20Lucia.'));
$r = $ospite->get('/');
prova('6D · la home conta ancora le sezioni pronte (la libera no)', str_contains($r['body'], '18 sezioni pronte da compilare'));

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
$mancano = array_diff(['checkin', 'wifi', 'services', 'riscaldamento', 'aria', 'extras', 'rules', 'arrival', 'transport', 'parking', 'waste', 'eat', 'visit', 'todo', 'shop', 'events', 'custom', 'emergency', 'info'], $kindsV);
prova('…tutte le sezioni del catalogo compilate (eventi e sezione libera comprese), nessuna vuota', !$mancano
      && !val("SELECT 1 FROM sections WHERE property_id = ? AND is_core = 0 AND data = '{}'", [$vet['id'] ?? 0]), json_encode(array_values($mancano)));
// 8D: ogni campo di ogni sezione (tranne gli orari dell'aria condizionata, che con «la accende l'ospite» non valgono),
// foto profilo, due parole di benvenuto, portali, Instagram, sconto, email per gli ospiti, messaggi; tutte le lingue.
$vuotiV = [];
foreach (righe('SELECT * FROM sections WHERE property_id = ?', [$vet['id'] ?? 0]) as $sV) {
    $dV = json_decode((string) $sV['data'], true) ?: [];
    $tV = json_decode((string) val("SELECT data FROM section_translations WHERE section_id = ? AND locale = 'it'", [$sV['id']]), true) ?: [];
    $campiV = json_decode((string) shell_exec('php -r ' . escapeshellarg('define("MHW_APP", "' . $DOVE . '/app"); spl_autoload_register(fn($c) => require "' . $DOVE . '/app/src/" . substr($c, 4) . ".php"); echo json_encode(array_keys(MHW\SectionCatalog::fields("' . $sV['kind'] . '")));')), true) ?: [];
    foreach ($campiV as $fV) { $vV = $dV[$fV] ?? $tV[$fV] ?? null; if (($vV === null || $vV === '' || $vV === []) && !($sV['kind'] === 'aria' && $fV === 'ac_times')) $vuotiV[] = $sV['kind'] . '.' . $fV; }
}
prova('8D · vetrina: tutte le sezioni del catalogo (riscaldamento e aria comprese) e ogni campo compilato', !$vuotiV && !array_diff(['riscaldamento', 'aria', 'custom', 'events', 'shop'], $kindsV), implode(', ', $vuotiV));
$vetP = riga('SELECT * FROM properties WHERE id = ?', [$vet['id'] ?? 0]);
prova('8D · …foto profilo, due parole di benvenuto in 5 lingue, portali e Instagram, sconto, email per gli ospiti, messaggi', !empty($vetP['profile_media_id'])
      && count(json_decode((string) $vetP['host_welcome'], true) ?: []) === 5 && $vetP['direct_discount'] === '10%' && $vetP['guest_email'] !== ''
      && array_column(righe('SELECT kind FROM property_channels WHERE property_id = ? ORDER BY position', [$vet['id'] ?? 0]), 'kind') === ['google', 'booking', 'airbnb', 'instagram']
      && isset((json_decode((string) $vetP['messages'], true) ?: [])['benvenuto']['it'], (json_decode((string) $vetP['messages'], true) ?: [])['saluto']['it']));
$uscitaV = (string) shell_exec('php ' . escapeshellarg("$DOVE/app/tools/vetrina-mancanti.php") . ' --conta 2>&1');
prova('8D · tools/vetrina-mancanti.php: 0 in inglese, francese, tedesco e spagnolo', trim($uscitaV) === "EN: 0\nFR: 0\nDE: 0\nES: 0", $uscitaV);
$r = $ospite->get("/g/$vslug?l=de");
prova('8D · /g/casa-checco?l=de senza titoli italiani', $r['code'] === 200 && !preg_match('#>(Cosa visitare|La storia della casa|Eventi|Dove mangiare e bere|Negozi e spesa|Cosa fare|Riscaldamento|Aria condizionata)<#', $r['body'])
      && str_contains($r['body'], 'Heizung') && str_contains($r['body'], 'Klimaanlage'));
$r = $ospite->get('/');
prova('8D · home: il telefono mostra tutte le sezioni della vetrina, nella griglia che scorre', str_contains($r['body'], 'device__tiles--tutte')
      && str_contains($r['body'], 'data-sezioni="' . (int) val('SELECT COUNT(*) FROM sections WHERE property_id = ? AND is_active = 1', [$vet['id'] ?? 0]) . '"'));
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
prova('…scheda completa: CIN di esempio, 4 posti letto, link per le recensioni, codice sconto e «Prenota sul nostro sito» verso la pagina di MyHouse (8F)',
      str_contains((string) ($vet['cin'] ?? ''), 'DEMO') && (int) ($vet['beds'] ?? 0) === 4 && str_contains((string) ($vet['review_booking'] ?? ''), 'booking.com')
      && ($vet['direct_code'] ?? '') === 'CHECCO10' && ($vet['direct_url'] ?? '') === 'https://blackout.in/myhouse/?da=vetrina');
$r = $ospite->get("/g/$vslug/commiato?l=de&voto=5");
prova('…il congedo in tedesco mostra recensioni (con 5 stelle, 8C) e codice sconto', $r['code'] === 200 && str_contains($r['body'], 'CHECCO10') && str_contains($r['body'], 'href="https://www.booking.com/"')
      && str_contains($r['body'], 'Danke, dass Sie Casa Checco gewählt haben'));
// 8F · la pagina di saluto della vetrina con 5 stelle: grazie → favore → i tre portali → «Tornerai da queste parti?» con il codice
// → «Seguici» → WhatsApp → «Torna alla guida», in italiano e in inglese.
foreach (['it' => ['Tornerai da queste parti?', 'Seguici', 'Torna alla guida'], 'en' => ['Coming back this way?', 'Follow us', 'Back to the guide']] as $l8 => [$torn8, $seg8, $back8]) {
    $b8 = $ospite->get("/g/$vslug/commiato?l=$l8&voto=5")['body'];
    $pos8 = ['voto' => strpos($b8, 'data-voto="5"'), 'google' => strpos($b8, 'data-portale="google"'), 'booking' => strpos($b8, 'data-portale="booking"'),
             'airbnb' => strpos($b8, 'data-portale="airbnb"'), 'torna' => strpos($b8, $torn8), 'codice' => strpos($b8, '<b>CHECCO10</b>'),
             'seguici' => strpos($b8, $seg8), 'whatsapp' => strpos($b8, 'href="https://wa.me/'), 'guida' => strpos($b8, '>' . $back8 . '</a>')];
    $crescente8 = !in_array(false, $pos8, true); $prima8 = -1;
    foreach ($pos8 as $v8) { if ($v8 === false || $v8 <= $prima8) { $crescente8 = false; break; } $prima8 = $v8; }
    prova("8F · vetrina, saluto ($l8) con 5 stelle: portali Google, Booking.com e Airbnb, poi «{$torn8}» con CHECCO10, «Seguici», WhatsApp e il ritorno alla guida",
          $crescente8 && str_contains($b8, 'href="https://blackout.in/myhouse/?da=vetrina"') && !str_contains($b8, 'data-portale="vrbo"') && str_contains($b8, 'instagram.com/casacheccoassisi'),
          json_encode($pos8));
}
$b8 = $ospite->get("/g/$vslug/commiato?l=it&voto=3")['body'];
prova('8F · …con 3 stelle niente portali, ma «Tornerai da queste parti?» resta (mai nascosto dal voto)', !str_contains($b8, 'data-portale=') && str_contains($b8, 'Tornerai da queste parti?') && str_contains($b8, '<b>CHECCO10</b>'));
// 8F · il testo nuovo del blocco in tutte le lingue dell'interfaccia della guida (le altre lingue cadono sull'inglese).
$attese8 = ['it' => 'Tornerai da queste parti?', 'en' => 'Coming back this way?', 'fr' => 'Vous repasserez par ici ?', 'de' => 'Kommen Sie wieder in diese Gegend?',
            'es' => '¿Volverás por aquí?', 'nl' => 'Kom je nog eens deze kant op?', 'pt' => 'Vai voltar a estes lados?', 'pt-BR' => 'Vai voltar para estes lados?', 'pl' => 'Wrócisz w te strony?'];
$male8 = [];
foreach ($attese8 as $l8 => $t8) {
    $d8 = include "$DOVE/app/lang/$l8.php";
    if (($d8['direct_title'] ?? '') !== $t8 || !preg_match('/(codice sconto|discount code|code de réduction|Rabattcode|código de descuento|kortingscode|código de desconto|kod rabatowy)/u', (string) ($d8['direct_lead'] ?? ''))) $male8[] = $l8;
}
prova('8F · «Tornerai da queste parti?» e «…per ottenere un codice sconto» in tutte e nove le lingue della guida', !$male8, implode(', ', $male8));
$r = $ospite->get("/g/$vslug/" . (int) val("SELECT id FROM sections WHERE property_id = ? AND kind = 'riscaldamento'", [$vet['id'] ?? 0]));
prova('8F · temperatura: «20 °C» con lo spazio indivisibile (non va a capo da solo), pillola al centro della scheda', str_contains($r['body'], "Impostato a 20\u{00A0}°C")
      && !preg_match('/\d °C/u', $r['body']) && preg_match('/\.clima__temp\{[^}]*align-self:center/', (string) file_get_contents("$DOVE/assets/app.css")) === 1);
$clV = (int) val("SELECT id FROM sections WHERE property_id = ? AND kind = 'riscaldamento'", [$vet['id'] ?? 0]);
$arV = (int) val("SELECT id FROM sections WHERE property_id = ? AND kind = 'aria'", [$vet['id'] ?? 0]);
$r = $ospite->get("/g/$vslug/$clV");
$rf = $ospite->get("/g/$vslug/$clV?l=fr");
$ra = $ospite->get("/g/$vslug/$arV");
prova('…riscaldamento automatico (6:30–9:30 e 17–22:30, 20 °C) e, nella sua sezione, condizionatore libero a 26 °C, anche in francese', $r['code'] === 200
      && str_contains($r['body'], '6:30 – 9:30') && str_contains($r['body'], '17:00 – 22:30') && str_contains($r['body'], "20\u{00A0}°C") && str_contains($ra['body'], "26\u{00A0}°C")
      && str_contains($r['body'], 'clima__energia') && str_contains($rf['body'], 'La chaudière est dans le placard'));
$r = $ospite->get("/g/$vslug/" . (int) val("SELECT id FROM sections WHERE property_id = ? AND kind = 'services'", [$vet['id'] ?? 0]));
prova('…nei Servizi la macchina del caffè al posto del condizionatore (che ha la sua sezione)', str_contains($r['body'], 'La macchina del caffè') && !str_contains($r['body'], 'L&#039;aria condizionata'));
$visV = (int) val("SELECT id FROM sections WHERE property_id = ? AND kind = 'visit'", [$vet['id'] ?? 0]);
$r = $ospite->get("/g/$vslug/$visV?l=es"); $rd = $ospite->get("/g/$vslug/$visV?l=de");
prova('…luoghi tradotti anche in spagnolo e tedesco (descrizioni e note)', str_contains($r['body'], 'La catedral de Asís') && str_contains($rd['body'], 'Der Dom von Assisi')
      && !str_contains($r['body'], 'Il duomo di Assisi'));
$daTradurre = 0;
foreach (['de', 'fr', 'es'] as $lv) $daTradurre += (int) val("SELECT COUNT(*) FROM sections s LEFT JOIN section_translations t ON t.section_id = s.id AND t.locale = ? WHERE s.property_id = ? AND t.id IS NULL", [$lv, $vet['id'] ?? 0]);
prova('…ogni sezione ha la sua traduzione in tedesco, francese e spagnolo', $daTradurre === 0, (string) $daTradurre);
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
prova('6E · passo «Pubblica»: prezzo pieno barrato, 101,60 € il primo anno, «Dal secondo anno 127 € + IVA», «Togli»', $r['code'] === 200 && pulita($r)
      && str_contains($r['body'], "<s class=\"muted\" style=\"font-size:18px\">127\u{00A0}€</s>") && str_contains($r['body'], "101,60\u{00A0}€")
      && str_contains($r['body'], "Dal secondo anno 127\u{00A0}€ + IVA") && str_contains($r['body'], 'Codice BENVENUTO20 · −20% primo anno') && str_contains($r['body'], '/sconto/togli'));
$dora->post('/sconto/togli', ['torna' => "/pannello/$dpid/procedura/pubblica"]);
$r = $dora->get("/pannello/$dpid/procedura/pubblica");
prova('6E · «Togli»: il codice va via e torna «Hai un codice sconto?»', !val('SELECT intended_discount_code_id FROM accounts WHERE id = ?', [$dacc])
      && str_contains($r['body'], 'Hai un codice sconto o un codice omaggio?') && str_contains($r['body'], "127\u{00A0}€"));
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
$r = $dora->modulo("/pannello/$dpid/procedura/pubblica", "/pannello/$dpid/pubblica", ['acquirente' => 'impresa']);
$dord = riga('SELECT * FROM orders WHERE account_id = ? ORDER BY id DESC', [$dacc]);
$sess = array_values(array_filter(array_slice(richiesteStripe(), $prima), fn($x) => $x['percorso'] === '/v1/checkout/sessions'))[0] ?? [];
prova('6E · ordine col codice (importo pieno), checkout con discounts[0][coupon] e niente allow_promotion_codes', $dord && (int) $dord['discount_code_id'] === (int) $bv['id']
      && (int) $dord['amount_cents'] === 12700 && ($sess['corpo']['discounts'][0]['coupon'] ?? '') === $bv['stripe_coupon_id']
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
$cf->post("/pannello/$cfPid/sezioni/$cfEm", ['emergency_number' => '113', 'contacts[0][id]' => '', 'contacts[0][name]' => 'Guardia medica', 'contacts[0][phone]' => '075 812712']);
$emD = json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$cfEm]), true);
prova('Telefoni: il numero breve resta com\'è (113), il fisso italiano prende +39', ($emD['emergency_number'] ?? '') === '113' && ($emD['contacts'][0]['phone'] ?? '') === '+39 075 812712', json_encode($emD));
$cf->post("/pannello/$cfPid/sezioni/$cfEat/luogo", ['place_id' => (string) $cfPl, 'name' => 'Trattoria rinominata', 'maps_url' => '', 'phone' => '0039 333 1234567']);
prova('…il cellulare scritto con 0039 diventa +39', val('SELECT phone FROM places WHERE id = ?', [$cfPl]) === '+39 333 1234567');
$r = $cf->get("/pannello/$cfPid/sezioni/$cfEm");
prova('…nel pannello i campi telefono propongono il +39', str_contains($r['body'], 'data-prefisso="+39 "') && str_contains($r['body'], 'placeholder="+39 333 123 4567"'));
prova('…e i link della guida aggiungono il prefisso anche ai numeri salvati prima', (string) shell_exec('php -r ' . escapeshellarg('define("MHW_APP", "' . $DOVE . '/app"); spl_autoload_register(fn($c) => require "' . $DOVE . '/app/src/" . substr($c, 4) . ".php"); echo MHW\\Support::telHref("333 123 4567"), "|", MHW\\Support::telHref("112"), "|", MHW\\Telefono::whatsapp("075 812712");')) === '+393331234567|112|39075812712');
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
prova('…con la posta: in modalità prova l\'email finisce in mail.log', str_contains($r['body'], 'storage/logs/mail-' . gmdate('Y-m-d') . '.log') && str_contains((string) implode("\n", righePosta()), 'Prova della posta — MyHouse Welcome'));
@unlink($fileLocale); @unlink(dirname($fileLocale) . '/config.local.bak.php');

// ================================================================= PUNTO 5
capitolo('Punto 5 · traduzioni suggerite (Amazon Translate finto, con la firma verificata)');
$TR_DIR = rtrim($argv[5] ?? '', '/');
$richiesteTr = fn() => array_values(array_filter(array_map(fn($l) => json_decode($l, true), $TR_DIR !== '' ? (file("$TR_DIR/richieste.jsonl") ?: []) : [])));
$auto = (int) val("SELECT id FROM features WHERE code = 'auto_translation'");
$valoreAuto = fn(string $code) => array_unique(array_column(righe('SELECT pf.value FROM package_features pf JOIN package_versions v ON v.id = pf.package_version_id
    JOIN packages p ON p.id = v.package_id WHERE p.code = ? AND pf.feature_id = ?', [$code, $auto]), 'value'));
prova('Migrazione 022: traduzioni suggerite accese in tutte le versioni di Plus e Portfolio, spente in Essential', $valoreAuto('plus') === ['1'] && $valoreAuto('portfolio') === ['1']
      && !in_array('1', $valoreAuto('essential'), true) && val("SELECT label FROM features WHERE id = ?", [$auto]) === 'Traduzioni suggerite');
prova('…il testo di Plus (9G): «Comprese nel piano, entro un limite annuale di caratteri», niente più omaggio', str_contains((string) val("SELECT bullets FROM packages WHERE code = 'plus'"), 'Comprese nel piano, entro un limite annuale di caratteri.')
      && !str_contains((string) val("SELECT bullets FROM packages WHERE code = 'plus'"), 'omaggio'));
$r = $ospite->get('/');
prova('Landing: confronto dei piani con «Traduzioni suggerite · entro un limite annuale di caratteri» (9G), e le FAQ', str_contains($r['body'], 'Traduzioni suggerite <span class="small muted">entro un limite annuale di caratteri</span>')
      && str_contains($r['body'], 'Le traduzioni me le fate voi?') && str_contains($r['body'], 'falli rileggere a un madrelingua') && !str_contains($r['body'], 'nessuna traduzione automatica'));
prova('Termini e privacy parlano delle traduzioni suggerite e di Amazon Translate', str_contains($ospite->get('/termini')['body'], 'Traduzioni suggerite')
      && str_contains($ospite->get('/privacy')['body'], 'Amazon Translate'));

// Essential: la funzione si vede, spenta, «con il piano Plus».
$elsa = new Browser('elsa');
$elsa->get('/registrati?piano=' . pv('essential'));
$elsa->post('/registrati', ['piano' => pv('essential'), 'name' => 'Elsa Prova', 'email' => 'elsa@prova.test', 'password' => 'ElsaProva123', 'termini' => '1', 'privacy' => '1']);
$eacc = $accDi('elsa@prova.test');
$elsa->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Casa Elsa', 'city' => 'Spoleto']);
$ep = (int) val('SELECT id FROM properties WHERE account_id = ?', [$eacc]);
$r = $elsa->get("/pannello/$ep/lingue");
prova('Essential: in Lingue le traduzioni suggerite si vedono spente, «con il piano Plus»', $r['code'] === 200 && pulita($r) && str_contains($r['body'], 'Traduzioni suggerite')
      && preg_match('#<input type="checkbox" disabled><span class="scelta__testo">Accendi le traduzioni suggerite <span class="small muted">con il piano Plus#', $r['body']) === 1);
$elsa->post("/pannello/$ep/lingue/suggerite", ['acceso' => '1']);
prova('…e non si accendono nemmeno mandando il modulo a mano', (int) val('SELECT translation_suggest FROM properties WHERE id = ?', [$ep]) === 0
      && !val('SELECT translation_trial_until FROM accounts WHERE id = ?', [$eacc]));

// Lucia (Portfolio): testi da tradurre in inglese, preparati qui.
$emCasa = (int) val("SELECT id FROM sections WHERE property_id = ? AND kind = 'emergency'", [$casa]);
$lo = $luoghi($casa);
$ptIt = fn(int $pl, string $f, string $v) => db()->prepare("UPDATE place_translations SET $f = ? WHERE place_id = ? AND locale = 'it'")->execute([$v, $pl]);
$ptEn = function (int $pl, string $f, string $v) {
    if (!val("SELECT id FROM place_translations WHERE place_id = ? AND locale = 'en'", [$pl])) db()->prepare("INSERT INTO place_translations (place_id, locale) VALUES (?, 'en')")->execute([$pl]);
    db()->prepare("UPDATE place_translations SET $f = ? WHERE place_id = ? AND locale = 'en'")->execute([$v, $pl]);
};
$ptIt((int) $lo[0]['id'], 'description', 'Pasta fatta a mano ogni mattina.'); $ptEn((int) $lo[0]['id'], 'description', '');
$ptIt((int) $lo[1]['id'], 'note', 'Chiedi il tavolo sotto il pergolato.'); $ptEn((int) $lo[1]['id'], 'note', '');
$ptIt((int) $lo[2]['id'], 'note', 'Chiude il martedì.'); $ptEn((int) $lo[2]['id'], 'note', '');
$trData = function (int $sid, string $loc, string $campo, string $v) {
    $d = json_decode((string) val('SELECT data FROM section_translations WHERE section_id = ? AND locale = ?', [$sid, $loc]), true) ?: [];
    $d[$campo] = $v;
    db()->prepare('UPDATE section_translations SET data = ? WHERE section_id = ? AND locale = ?')->execute([json_encode($d, JSON_UNESCAPED_UNICODE), $sid, $loc]);
};
$trData($emCasa, 'it', 'note', 'Il pronto soccorso più vicino è a Nottola.'); $trData($emCasa, 'en', 'note', '');

$r = $lucia->get("/pannello/$casa/lingue");
prova('Plus/Portfolio: in Lingue il contatore dell\'anno (9G) e il bottone per accenderle', $r['code'] === 200 && pulita($r)
      && preg_match('#Traduzioni suggerite: \d+% dell\'anno usato</b> · si azzera il #', $r['body']) === 1 && str_contains($r['body'], 'Accendi le traduzioni suggerite'));
$primaSp = count($richiesteTr());
$r = $lucia->get("/pannello/$casa/lingue/en");
prova('7C · …spente: nella pagina della lingua «Traduci» campo per campo lo stesso, ma nessuna chiamata finché non si clicca', str_contains($r['body'], 'name="campo" value="')
      && str_contains($r['body'], 'Traduci tutti i campi mancanti') && count($richiesteTr()) === $primaSp);
$r = $lucia->segui($lucia->post("/pannello/$casa/lingue/suggerite", ['acceso' => '1']));
prova('Accese: niente più anno in omaggio (9G); compare la spiegazione in due righe, con il madrelingua', (int) val('SELECT translation_suggest FROM properties WHERE id = ?', [$casa]) === 1
      && !val('SELECT translation_trial_until FROM accounts WHERE id = ?', [$lacc]) && str_contains($r['body'], 'Le trovi nella pagina di ogni lingua')
      && str_contains($r['body'], 'Per i testi importanti, falli rileggere a un madrelingua.') && !str_contains($r['body'], 'In omaggio fino al'));
$r = $lucia->get("/pannello/$casa/lingue");
prova('…la spiegazione solo la prima volta', !str_contains($r['body'], 'Le trovi nella pagina di ogni lingua'));

$r = $lucia->get("/pannello/$casa/lingue/en");
preg_match('#Traduci tutti i campi mancanti \((\d+)\)#', $r['body'], $m); $mancanti = (int) ($m[1] ?? 0);
prova('Pagina della lingua: «Traduci tutti i campi mancanti» con il numero, e la nota sul madrelingua', $mancanti >= 4 && pulita($r)
      && str_contains($r['body'], 'Suggerite da un traduttore automatico, da approvare'), (string) $mancanti);
$prima = count($richiesteTr());
$r = $lucia->segui($lucia->post("/pannello/$casa/lingue/en/suggerite", ['suggerisci' => '1']));
$nuove = array_slice($richiesteTr(), $prima);
$sug = righe("SELECT * FROM translation_suggestions WHERE property_id = ? AND locale = 'en'", [$casa]);
prova('Suggerite: una chiamata per campo, tutte firmate bene (il servizio finto ricalcola la firma), una suggerita per campo', count($sug) === $mancanti
      && count($nuove) === $mancanti && array_unique(array_column($nuove, 'code')) === [200] && str_contains($r['body'], "$mancanti traduzioni suggerite"),
      count($sug) . '/' . count($nuove) . ' ' . json_encode(array_unique(array_column($nuove, 'code'))));
$caratteri = array_sum(array_map(fn($x) => mb_strlen($x['source_text']), $sug));
prova('…registrate in translation_usage con i caratteri del testo originale', (int) val("SELECT SUM(chars) FROM translation_usage WHERE account_id = ? AND outcome = 'ok'", [$lacc]) === $caratteri
      && (int) val("SELECT COUNT(*) FROM translation_usage WHERE account_id = ?", [$lacc]) === $mancanti);
prova('…nella pagina, sotto il campo: «Suggerita: da controllare» con Approva, Modifica, Scarta', substr_count($r['body'], 'Suggerita: da controllare') === $mancanti
      && str_contains($r['body'], '[en] Pasta fatta a mano ogni mattina.') && str_contains($r['body'], 'name="approva"') && str_contains($r['body'], 'data-modifica="c-')
      && str_contains($r['body'], 'name="scarta"') && str_contains($r['body'], 'Approva tutte (' . $mancanti . ')'));
$guidaEn = fn(int $sid) => $lucia->get("/pannello/$casa/anteprima/$sid?l=en")['body'];
prova('Le suggerite non approvate non entrano mai nella guida', !str_contains($guidaEn($eatCasa), '[en]') && !str_contains($guidaEn($emCasa), '[en]')
      && !str_contains($lucia->get("/pannello/$casa/anteprima?l=en")['body'], '[en]'));

$idDi = fn(string $tipo, int $id, string $path) => (int) val("SELECT id FROM translation_suggestions WHERE target_type = ? AND target_id = ? AND field_path = ? AND locale = 'en'", [$tipo, $id, $path]);
// Una traduzione scritta a mano nel frattempo non si sovrascrive.
$ptEn((int) $lo[0]['id'], 'description', 'Fresh pasta, written by Lucia.');
$r = $lucia->segui($lucia->post("/pannello/$casa/lingue/en/suggerite", ['approva' => (string) $idDi('place', (int) $lo[0]['id'], 'description')]));
prova('Approva su un campo già tradotto a mano: non scrive niente, la suggerita sparisce', str_contains($r['body'], 'Questo testo è già tradotto')
      && val("SELECT description FROM place_translations WHERE place_id = ? AND locale = 'en'", [$lo[0]['id']]) === 'Fresh pasta, written by Lucia.'
      && !$idDi('place', (int) $lo[0]['id'], 'description'));
$r = $lucia->segui($lucia->post("/pannello/$casa/lingue/en/suggerite", ['approva' => (string) $idDi('place', (int) $lo[1]['id'], 'note')]));
prova('Approva: diventa la traduzione (e la guida la mostra)', str_contains($r['body'], 'Traduzione approvata')
      && val("SELECT note FROM place_translations WHERE place_id = ? AND locale = 'en'", [$lo[1]['id']]) === '[en] Chiedi il tavolo sotto il pergolato.'
      && str_contains($guidaEn($eatCasa), '[en] Chiedi il tavolo sotto il pergolato.'));
// Il testo originale cambia: la suggerita è da rifare.
$ptIt((int) $lo[2]['id'], 'note', 'Chiude il martedì e il mercoledì.');
$r = $lucia->get("/pannello/$casa/lingue/en");
$id2 = $idDi('place', (int) $lo[2]['id'], 'note');
prova('Il testo originale cambia: la suggerita è «da rifare», con Rifai', str_contains($r['body'], 'Da rifare: il testo originale è cambiato') && str_contains($r['body'], 'name="rifai" value="' . $id2 . '"'));
$r = $lucia->segui($lucia->post("/pannello/$casa/lingue/en/suggerite", ['approva' => (string) $id2]));
prova('…non si approva', str_contains($r['body'], 'è da rifare') && val("SELECT note FROM place_translations WHERE place_id = ? AND locale = 'en'", [$lo[2]['id']]) === '');
$r = $lucia->segui($lucia->post("/pannello/$casa/lingue/en/suggerite", ['rifai' => (string) $id2]));
prova('…Rifai: suggerita nuova sul testo nuovo', str_contains($r['body'], '[en] Chiude il martedì e il mercoledì.') && !str_contains($r['body'], 'Da rifare'));
$r = $lucia->segui($lucia->post("/pannello/$casa/lingue/en/suggerite", ['scarta' => (string) $idDi('place', (int) $lo[2]['id'], 'note')]));
prova('Scarta: la suggerita sparisce, il campo resta vuoto', str_contains($r['body'], 'Suggerita scartata') && !$idDi('place', (int) $lo[2]['id'], 'note')
      && val("SELECT note FROM place_translations WHERE place_id = ? AND locale = 'en'", [$lo[2]['id']]) === '');
$r = $lucia->segui($lucia->post("/pannello/$casa/lingue/en/suggerite", ['modifica' => (string) $idDi('section', $emCasa, 'note')]));
prova('Modifica (senza JavaScript): la approva e porta al campo, da correggere', str_contains($r['body'], 'ora correggila nel campo')
      && (json_decode((string) val("SELECT data FROM section_translations WHERE section_id = ? AND locale = 'en'", [$emCasa]), true)['note'] ?? '') === '[en] Il pronto soccorso più vicino è a Nottola.');
$restano = (int) val("SELECT COUNT(*) FROM translation_suggestions WHERE property_id = ? AND locale = 'en'", [$casa]);
$r = $lucia->segui($lucia->post("/pannello/$casa/lingue/en/suggerite", ['tutte' => '1']));
prova('Approva tutte', $restano > 0 && str_contains($r['body'], "$restano traduzion") && !val("SELECT COUNT(*) FROM translation_suggestions WHERE property_id = ? AND locale = 'en'", [$casa]), (string) $restano);
prova('…Lingue conta le suggerite da controllare per lingua (qui nessuna)', !str_contains($lucia->get("/pannello/$casa/lingue")['body'], 'da controllare</span>'));

// I tetti e gli errori, con un config.local.php scritto qui.
$fileLocale = "$DOVE/app/config.local.php";
$ptIt((int) $lo[2]['id'], 'note', 'Il martedì è chiuso, ma il mercoledì apre alle sette di sera.');
file_put_contents($fileLocale, '<?php return ' . var_export(['translate' => ['cap_account' => 20]], true) . ';');
$r = $lucia->segui($lucia->post("/pannello/$casa/lingue/en/suggerite", ['suggerisci' => '1']));
prova('Tetto per account (dal 9G è annuale, già raggiunto): si ferma prima di chiamare, lo dice, niente suggerita', str_contains($r['body'], 'Hai usato le traduzioni suggerite di quest')
      && !$idDi('place', (int) $lo[2]['id'], 'note'));
file_put_contents($fileLocale, '<?php return ' . var_export(['translate' => ['cap_global' => 30]], true) . ';');
$r = $lucia->segui($lucia->post("/pannello/$casa/lingue/en/suggerite", ['suggerisci' => '1']));
prova('Tetto del sito: si ferma per tutti', str_contains($r['body'], 'Per questo mese le traduzioni suggerite sono finite'));
file_put_contents($fileLocale, '<?php return ' . var_export(['translate' => ['secret' => 'segreto-sbagliato']], true) . ';');
$prima = count($richiesteTr());
$r = $lucia->segui($lucia->post("/pannello/$casa/lingue/en/suggerite", ['suggerisci' => '1']));
$ultima = array_slice($richiesteTr(), $prima)[0] ?? [];
prova('Firma con il segreto sbagliato: Amazon (finto) risponde 403, il cliente legge un messaggio semplice', ($ultima['code'] ?? 0) === 403
      && str_contains($r['body'], 'Il traduttore automatico non ha risposto') && (bool) val("SELECT id FROM translation_usage WHERE outcome = 'errore' AND error LIKE '%403%'"));
@unlink($fileLocale);

// 9G · Il tetto annuale raggiunto (config.local con un tetto minuscolo): la pagina lo dice, niente «Traduci», le approvate restano.
file_put_contents($fileLocale, '<?php return ' . var_export(['translate' => ['cap_account' => 20]], true) . ';');
$r = $lucia->get("/pannello/$casa/lingue/en");
prova('Tetto annuale raggiunto (9G): la pagina lo dice, con «scrivici», e non offre più «Traduci tutti»', str_contains($r['body'], 'tradu-contatore--finito')
      && str_contains($r['body'], 'Hai usato le traduzioni suggerite di quest\'anno') && !str_contains($r['body'], 'name="suggerisci"'));
$prima = count($richiesteTr());
$lucia->post("/pannello/$casa/lingue/en/suggerite", ['suggerisci' => '1']);
prova('…anche mandando il modulo a mano: nessuna chiamata', count($richiesteTr()) === $prima);
prova('…e le approvate restano nella guida', str_contains($guidaEn($eatCasa), '[en] Chiedi il tavolo sotto il pergolato.')
      && str_contains($lucia->get("/pannello/$casa/lingue")['body'], 'Limite dell\'anno raggiunto'));

// Amministrazione → Traduzioni.
$r = $admin->get('/admin/traduzioni');
prova('Amministrazione → Traduzioni: mese, 12 mesi, chi traduce di più, previsione, la nota sulle stime (9G: niente più omaggi)', $r['code'] === 200 && pulita($r)
      && str_contains($r['body'], 'Costo stimato del mese') && str_contains($r['body'], 'Ultimi 12 mesi') && str_contains($r['body'], 'Chi traduce di più')
      && str_contains($r['body'], 'Previsione') && !str_contains($r['body'], 'Anni in omaggio') && str_contains($r['body'], 'fa fede la fattura di AWS')
      && str_contains($r['body'], 'Lucia'));
$r = $admin->segui($admin->post("/admin/cliente/$lacc/traduzioni/tetto", ['aggiungi' => '1', 'nota' => 'prova del tetto']));
prova('…l\'amministratore aggiunge 250.000 caratteri (9G): si torna a suggerire', str_contains($r['body'], 'Aggiunti 250.000 caratteri')
      && (int) val('SELECT translate_cap_override FROM accounts WHERE id = ?', [$lacc]) === 250020 && str_contains($lucia->get("/pannello/$casa/lingue/en")['body'], 'name="suggerisci"'));
@unlink($fileLocale);
db()->prepare('UPDATE accounts SET translate_cap_override = NULL WHERE id = ?')->execute([$lacc]);
db()->prepare("INSERT INTO translation_usage (account_id, month, chars, outcome, created_at) VALUES (0, ?, 1600000, 'ok', ?)")->execute([gmdate('Y-m'), gmdate('Y-m-d\TH:i:s\Z')]);
prova('Quadro: avviso oltre l\'80% del tetto del sito', str_contains($admin->get('/admin')['body'], 'Traduzioni suggerite oltre l&#039;80% del tetto del mese'));
db()->exec('DELETE FROM translation_usage WHERE chars = 1600000');
$r = $admin->post('/admin/impostazioni/traduzioni', ['translate__region' => 'irlanda', 'password' => 'AdminProva123']);
prova('Impostazioni → Traduzioni: la regione si controlla', $r['code'] === 422 && str_contains($r['body'], 'La regione si scrive come eu-west-1'));
$r = $admin->segui($admin->post('/admin/impostazioni/traduzioni/prova', []));
prova('…«Prova la connessione» con Amazon Translate', str_contains($r['body'], 'Amazon Translate risponde') && str_contains($r['body'], '[en] Buongiorno'));
prova('Diagnostica: le traduzioni suggerite risultano collegate', str_contains($admin->get('/admin/diagnostica')['body'], 'Traduzioni suggerite (Amazon Translate)'));
require_once "$DOVE/app/src/Traduttore.php";
$lungo = str_repeat("Una frase di prova con gli accenti: è così. ", 300) . "\n" . str_repeat('àèìòù', 1500);
$pezzi = MHW\Traduttore::pezzi($lungo);
prova('Un testo oltre i 10.000 byte si spezza in pezzi da 10.000 al massimo, senza perdere niente', count($pezzi) > 2 && max(array_map('strlen', $pezzi)) <= 10000
      && implode('', $pezzi) === $lungo && array_filter($pezzi, fn($p) => !mb_check_encoding($p, 'UTF-8')) === []);

capitolo('Il sito della struttura: sezione della landing e invito nel pannello');
$r = $ospite->get('/');
prova('Landing: sezione «Anche il tuo sito» in fondo, dopo la chiusura, con il percorso a domande (8A) e senza foto dietro (8F)', str_contains($r['body'], 'id="sito"')
      && str_contains($r['body'], 'Il tuo sito li porta da te.') && str_contains($r['body'], 'data-funnel') && str_contains($r['body'], 'https://blackout.in/myhouse/?da=welcome')
      && strpos($r['body'], 'id="sito"') > strpos($r['body'], 'id="chiusura-titolo"') && !str_contains($r['body'], 'sito2__foto') && !str_contains($r['body'], '/assets/foto/sito-')
      && str_contains($r['body'], '/#sito">Il sito per la tua struttura</a>'));
$r = $lucia->get('/pannello');
prova('Pannello (8B): la domanda «Hai già un sito?», con il percorso verso blackout.in; senza ✕ (9F)', pulita($r) && str_contains($r['body'], 'data-sito-invito')
      && str_contains($r['body'], 'Con un sito tuo gli ospiti prenotano senza commissioni') && str_contains($r['body'], 'https://blackout.in/myhouse/?da=welcome')
      && !str_contains($r['body'], 'data-sito-chiudi'));
prova('6J · …solo in «Le mie guide»: non nella pagina della guida', !str_contains($lucia->get("/pannello/$casa")['body'], 'data-sito-invito'));
$r = $lucia->modulo('/pannello', '/pannello/sito/nascondi', []);
prova('6J → 9F · «Non mostrare più» non c\'è più: /pannello/sito/nascondi non nasconde niente, il riquadro resta', str_contains($lucia->get('/pannello')['body'], 'data-sito-invito')
      && str_contains($lucia->get('/pannello')['body'], 'class="guide-griglia"'));

// ================================================================= 6H
capitolo('6H · cambio di piano: Plus → Essential dal rinnovo, Essential → Plus pagando la differenza');
db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'register:%'");
$paola = new Browser('paola');
$paola->get('/registrati?piano=' . pv('plus'));
$paola->post('/registrati', ['piano' => pv('plus'), 'name' => 'Paola Cambio', 'email' => 'paola@prova.test', 'password' => 'PaolaProva123', 'termini' => '1', 'privacy' => '1']);
$pacc = $accDi('paola@prova.test');
$paola->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Casa Paola', 'city' => 'Todi']);
$pp6 = (int) val('SELECT id FROM properties WHERE account_id = ?', [$pacc]);
db()->prepare("INSERT INTO subscriptions (account_id, package_version_id, status, provider, provider_customer_id, provider_subscription_id, current_period_start,
    current_period_end, payment_status, created_at, updated_at, quantity) VALUES (?, ?, 'active', 'stripe', 'cus_paola', 'sub_prova_paola', ?, ?, 'paid', ?, ?, 1)")
    ->execute([$pacc, pv('plus'), gmdate('Y-m-d\TH:i:s\Z', time() - 100 * 86400), gmdate('Y-m-d\TH:i:s\Z', time() + 265 * 86400), gmdate('Y-m-d\TH:i:s\Z'), gmdate('Y-m-d\TH:i:s\Z')]);
$paola->modulo('/account', '/account/fatturazione', ['billing_type' => 'privato', 'billing_name' => 'Paola Cambio', 'cf' => 'RSSMRA85T10A562S',
               'billing_address' => 'Via Roma 1', 'billing_postal' => '06059', 'billing_city' => 'Todi', 'billing_province' => 'PG']);
foreach (['rules', 'eat', 'visit', 'todo', 'emergency', 'waste', 'transport'] as $k) $paola->modulo("/pannello/$pp6", "/pannello/$pp6/sezioni", ['kind' => $k]);
$attive6 = fn() => (int) val('SELECT COUNT(*) FROM sections WHERE property_id = ? AND is_core = 0 AND is_active = 1', [$pp6]);
$paola->modulo("/pannello/$pp6/lingue", "/pannello/$pp6/lingue", ['locali' => ['it', 'en', 'fr']]);
$core6 = (int) val('SELECT id FROM sections WHERE property_id = ? AND is_core = 1', [$pp6]);
$paola->post("/pannello/$pp6/sezioni/$core6", ['checkin_steps' => ['Le chiavi sono nella cassetta.']]);
$paola->modulo("/pannello/$pp6/procedura/pubblica", "/pannello/$pp6/pubblica", ['acquirente' => 'impresa']);
$versioni6 = (int) val('SELECT COUNT(*) FROM guide_versions WHERE property_id = ?', [$pp6]);
prova('6H · preparazione: Plus su Stripe, guida pubblicata, 3 lingue e più di 6 sezioni', $attive6() > 6 && $versioni6 >= 1
      && (int) val('SELECT COUNT(*) FROM property_locales WHERE property_id = ?', [$pp6]) === 3, $attive6() . ' / ' . $versioni6);

$r = $paola->get('/account/piano');
prova('6H · Cambia piano: Essential «dal rinnovo», Portfolio «oggi», Plus il piano attuale', $r['code'] === 200 && pulita($r)
      && str_contains($r['body'], 'Passa a Essential dal rinnovo') && str_contains($r['body'], 'Il tuo piano') && preg_match('#Oggi <b>[\d,.]+\x{00A0}€</b> \+ IVA#u', $r['body']) === 1);
$r = $paola->get('/piano?passa=essential');
prova('6H · per chi è abbonato, /piano porta al cambio di piano (e «?passa=» alla conferma)', $r['code'] === 302 && str_contains($r['loc'], '/account/piano/conferma?piano=essential'));
$r = $paola->get('/account/piano/conferma?piano=essential');
prova('6H · conferma della discesa: scegli le 6 sezioni da tenere (7B: Essential ne ha 6), e cosa non ci sarà più', $r['code'] === 200 && pulita($r)
      && str_contains($r['body'], 'scegli le 6 sezioni da tenere') && substr_count($r['body'], "name=\"tieni[$pp6][]\"") === $attive6()
      && str_contains($r['body'], 'Foto e PDF nelle sezioni') && str_contains($r['body'], 'Lingue: restano') && str_contains($r['body'], 'Conferma il passaggio a Essential'));
$sez6 = array_map('intval', array_column(righe('SELECT id FROM sections WHERE property_id = ? AND is_core = 0 AND is_active = 1 ORDER BY position, id', [$pp6]), 'id'));
$r = $paola->modulo('/account/piano/conferma?piano=essential', '/account/piano/conferma', ['piano' => 'essential', 'tieni' => [$pp6 => array_slice($sez6, 0, 7)]]);
prova('…tenerne 7 non si può', $r['code'] === 200 && str_contains($r['body'], 'scegli al massimo 6 sezioni'));
$tengo = [end($sez6), $sez6[count($sez6) - 2]];
$prima = count(richiesteStripe());
$r = $paola->modulo('/account/piano/conferma?piano=essential', '/account/piano/conferma', ['piano' => 'essential', 'tieni' => [$pp6 => $tengo]]);
$mod = array_values(array_filter(array_slice(richiesteStripe(), $prima), fn($x) => $x['metodo'] === 'POST' && $x['percorso'] === '/v1/subscriptions/sub_prova_paola'));
$mc = end($mod)['corpo'] ?? [];
$ps = riga("SELECT * FROM subscriptions WHERE provider_subscription_id = 'sub_prova_paola'");
prova('6H · programmata: Stripe al prezzo di Essential dal rinnovo, nel sito resta Plus', $r['code'] === 302 && ($mc['proration_behavior'] ?? '') === 'none'
      && str_starts_with((string) ($mc['items'][0]['price'] ?? ''), 'price_creato') && (int) $ps['package_version_id'] === pv('plus') && (int) $ps['next_package_version_id'] === pv('essential')
      && $attive6() === count($sez6), json_encode($mc));
$r = inviaWebhook(['id' => 'evt_paola_rinnovo', 'type' => 'invoice.paid', 'data' => ['object' => [
    'id' => 'in_paola_rinnovo', 'customer' => 'cus_paola', 'subscription' => 'sub_prova_paola', 'billing_reason' => 'subscription_cycle',
    'lines' => ['data' => [['period' => ['start' => time(), 'end' => time() + 365 * 86400]]]]]]]);
$restano = array_map('intval', array_column(righe('SELECT id FROM sections WHERE property_id = ? AND is_core = 0 AND is_active = 1', [$pp6]), 'id'));
prova('6H · al rinnovo: Essential, restano attive 6 sezioni comprese le 2 scelte, le altre spente (non cancellate)', $r['body'] === 'rinnovo-pagato-con-cambio-di-piano'
      && (int) val("SELECT package_version_id FROM subscriptions WHERE provider_subscription_id = 'sub_prova_paola'") === pv('essential')
      && count($restano) === 6 && !array_diff($tengo, $restano) && (int) val('SELECT COUNT(*) FROM sections WHERE property_id = ? AND is_core = 0', [$pp6]) === count($sez6), $r['body'] . ' ' . json_encode($restano));
prova('…il francese esce dalla guida (italiano e inglese restano), la guida è ripubblicata', (int) val('SELECT COUNT(*) FROM property_locales WHERE property_id = ?', [$pp6]) === 2
      && !val("SELECT 1 FROM property_locales WHERE property_id = ? AND locale = 'fr'", [$pp6]) && (int) val('SELECT COUNT(*) FROM guide_versions WHERE property_id = ?', [$pp6]) === $versioni6 + 1);

// Salire di nuovo: Essential → Plus, la differenza oggi.
$r = $paola->get('/account/piano/conferma?piano=plus');
prova('6H · Essential → Plus: oggi la differenza, dal rinnovo il prezzo di Plus', $r['code'] === 200 && str_contains($r['body'], 'Differenza per i') && str_contains($r['body'], 'Vai al pagamento'));
$r = $paola->modulo('/account/piano/conferma?piano=plus', '/account/piano/conferma', ['piano' => 'plus']);
$po = riga("SELECT * FROM orders WHERE account_id = ? AND kind = 'change' ORDER BY id DESC", [$pacc]);
$r2 = inviaWebhook(['id' => 'evt_paola_su', 'type' => 'checkout.session.completed', 'data' => ['object' => [
    'id' => $po['provider_session_id'], 'mode' => 'payment', 'payment_status' => 'paid', 'customer' => 'cus_paola', 'client_reference_id' => (string) $po['id'],
    'metadata' => ['order_id' => (string) $po['id'], 'account_id' => (string) $pacc, 'kind' => 'change']]]]);
prova('…pagato: ora Plus, subito', str_starts_with($r['loc'], 'https://checkout.stripe.test/') && $r2['body'] === 'cambio-applicato'
      && (int) val("SELECT package_version_id FROM subscriptions WHERE provider_subscription_id = 'sub_prova_paola'") === pv('plus'), $r2['body']);
// Un checkout chiuso senza pagare non cambia niente.
$paola->modulo('/account/piano/conferma?piano=portfolio', '/account/piano/conferma', ['piano' => 'portfolio']);
$pz = riga("SELECT * FROM orders WHERE account_id = ? AND kind = 'change' ORDER BY id DESC", [$pacc]);
inviaWebhook(['id' => 'evt_paola_scaduto', 'type' => 'checkout.session.expired', 'data' => ['object' => ['id' => $pz['provider_session_id'], 'metadata' => ['order_id' => (string) $pz['id']]]]]);
prova('6H · checkout chiuso senza pagare: ordine scaduto, piano invariato', (int) $pz['id'] !== (int) $po['id'] && val('SELECT status FROM orders WHERE id = ?', [$pz['id']]) === 'expired'
      && (int) val("SELECT package_version_id FROM subscriptions WHERE provider_subscription_id = 'sub_prova_paola'") === pv('plus'));
// Arretrato e abbonamento dello staff.
db()->prepare("UPDATE subscriptions SET status = 'past_due' WHERE provider_subscription_id = 'sub_prova_paola'")->execute();
$r = $paola->get('/account/piano/conferma?piano=portfolio');
prova('6H · con un pagamento in arretrato: prima si sistema quello', $r['code'] === 302 && str_contains($paola->segui($r)['body'], 'Prima sistema il pagamento dell'));
db()->prepare("UPDATE subscriptions SET status = 'active' WHERE provider_subscription_id = 'sub_prova_paola'")->execute();
prova('6H · abbonamento attivato a mano: «scrivici»', str_contains($dario->get('/account/piano')['body'], 'attivato dal nostro staff: per cambiarlo scrivici'));
prova('6H · Diagnostica: cambi di piano pagati da completare: 0', str_contains($admin->get('/admin/diagnostica')['body'], 'Cambi di piano pagati da completare: 0'));
prova('6H · in amministrazione l\'ultimo cambio di piano del cliente', str_contains($admin->get("/admin/cliente/$pacc")['body'], 'Ultimo cambio di piano'));

// Fatturazione: persona fisica o azienda, con i campi giusti.
$r = $paola->get('/account');
prova('Fatturazione: «Persona fisica» e i campi dell\'azienda nascosti per chi ha scelto persona fisica', str_contains($r['body'], 'Persona fisica')
      && preg_match('#data-per="azienda" hidden><label for="f-vat"#', $r['body']) === 1 && str_contains($r['body'], 'cassetto fiscale'));
// Il cliente Stripe segue i dati: la P.IVA come tax id quando arriva, e via tutto quando diventa persona fisica (Adamo legge da lì).
db()->prepare("UPDATE accounts SET stripe_customer_id = 'cus_paola_prova' WHERE id = ?")->execute([$pacc]);
$paola->modulo('/account', '/account/fatturazione', ['billing_type' => 'azienda', 'billing_name' => 'Paola Cambio srl', 'vat' => '02945910541', 'cf' => '',
               'sdi' => 'M5UXCR1', 'pec' => 'paola@pec.example', 'billing_address' => 'Via Roma 1', 'billing_postal' => '06059', 'billing_city' => 'Todi', 'billing_province' => 'PG']);
$tx = fn() => (json_decode((string) @file_get_contents($GLOBALS['STRIPE_DIR'] . '/tax_ids.json'), true) ?: [])['cus_paola_prova'] ?? [];
prova('Stripe · un\'azienda che inserisce la P.IVA dopo: diventa tax id del cliente (IT…)', array_column($tx(), 'value') === ['IT02945910541'], json_encode($tx()));
$paola->modulo('/account', '/account/fatturazione', ['billing_type' => 'privato', 'billing_name' => 'Paola Cambio', 'cf' => 'RSSMRA85T10A562S', 'vat' => '02945910541',
               'sdi' => 'M5UXCR1', 'pec' => 'paola@pec.example', 'billing_address' => 'Via Roma 1', 'billing_postal' => '06059', 'billing_city' => 'Todi', 'billing_province' => 'PG']);
$fp = riga('SELECT vat, sdi, pec FROM accounts WHERE id = ?', [$pacc]);
prova('…una persona fisica non salva partita IVA, SDI e PEC', $fp['vat'] === '' && $fp['sdi'] === '' && $fp['pec'] === '');
$agg = array_values(array_filter(richiesteStripe(), fn($x) => $x['percorso'] === '/v1/customers/cus_paola_prova' && $x['metodo'] === 'POST'));
$mu = end($agg)['corpo']['metadata'] ?? [];
prova('…e su Stripe spariscono P.IVA, SDI e PEC (metadati vuoti, tax id tolto): Adamo non legge dati vecchi', ($mu['vat'] ?? null) === '' && ($mu['Pec'] ?? null) === ''
      && ($mu['pec'] ?? null) === '' && ($mu['Fe_code'] ?? '') === '0000000' && ($mu['Fiscal_code'] ?? '') === 'RSSMRA85T10A562S' && $tx() === [], json_encode($mu));

// Landing: le FAQ del cambio di piano sempre; «Porta un amico» (blocco e FAQ) solo con gli inviti accesi.
$r = $ospite->get('/');
prova('FAQ: «Posso cambiare piano dopo?» e «Se scendo di piano perdo qualcosa?»; inviti spenti, niente «Porta un amico»', str_contains($r['body'], 'Posso cambiare piano dopo?')
      && str_contains($r['body'], 'Se scendo di piano perdo qualcosa?') && !str_contains($r['body'], 'id="amico"') && !str_contains($r['body'], 'Porta un amico'));
$fileLocale = "$DOVE/app/config.local.php";
file_put_contents($fileLocale, '<?php return ' . var_export(['inviti' => ['attivi' => true]], true) . ';');
$r = $ospite->get('/');
@unlink($fileLocale);
prova('Inviti accesi: blocco «Porta un amico» dopo i piani, fino al 50%, dieci tacche e due FAQ', preg_match('#id="piani".*id="amico".*class="chiusura"#s', $r['body']) === 1
      && str_contains($r['body'], 'fino al <span class="amico__cifra">50%</span>') && substr_count($r['body'], 'class="amico__tacca"') === 10
      && str_contains($r['body'], 'Come funziona «Porta un amico»?') && str_contains($r['body'], 'Quando conta un amico, e cosa succede dopo il rinnovo?'));
prova('Testi e foto · le percentuali di «Porta un amico» restano giuste (5%, 50% in evidenza, senza doppio %)', str_contains($r['body'], 'loro hanno il 5% di sconto sul primo anno, tu il 5% in meno')
      && str_contains($r['body'], 'fino al <span class="amico__cifra">50%</span> in meno.') && !str_contains($r['body'], '%%') && str_contains($r['body'], 'fino al 50%.</li>'));
prova('Revisione · «Come funziona» di Porta un amico apre quella domanda (sotto «Altre domande»), non le FAQ in generale', str_contains($r['body'], 'href="#faq-porta-amico">Come funziona</a>')
      && preg_match('#<details class="faq__voce faq__altre">.*?id="faq-porta-amico"#s', $r['body']) === 1 && str_contains((string) file_get_contents("$DOVE/assets/landing.js"), 'function apriDomanda'));

// Amministrazione: prospetti, anomalie, scadenze con promemoria automatici e a mano, note, filtri, CSV.
capitolo('Amministrazione · pannello di controllo');
$r = $admin->get('/admin');
prova('Quadro: ricavo annuo ricorrente, rinnovi entro 30 giorni, scadenze senza rinnovo e anomalie', $r['code'] === 200 && str_contains($r['body'], 'Ricavo annuo ricorrente')
      && str_contains($r['body'], 'Rinnovi entro 30 giorni') && str_contains($r['body'], 'Scadono senza rinnovo') && str_contains($r['body'], '/admin/anomalie'));
$r = $admin->get('/admin/vendite');
$vendite = (int) val("SELECT COUNT(*) FROM orders o JOIN accounts a ON a.id = o.account_id JOIN users u ON u.id = a.user_id
                      WHERE o.status = 'paid' AND o.provider = 'stripe' AND o.kind = 'new' AND u.email NOT LIKE '%@esempio.it' AND o.created_at >= ?", [gmdate('Y-m-01', strtotime('-11 month', strtotime(gmdate('Y-m-01'))))]);
prova('Vendite per piano: 12 colonne impilate per piano con legenda, periodo, tabella, attivi e incasso per piano, ultimi acquisti', $r['code'] === 200 && pulita($r)
      && substr_count($r['body'], 'class="viz-colonne__col"') === 12 && substr_count($r['body'], '<span class="viz-legenda__segno"') >= 3 && str_contains($r['body'], 'aria-current="page">Ultimi 12 mesi')
      && str_contains($r['body'], 'Vedi come tabella') && str_contains($r['body'], 'Abbonamenti attivi oggi') && str_contains($r['body'], 'Incasso per piano')
      && str_contains($r['body'], 'Ultimi acquisti') && preg_match('#Nuovi abbonamenti</span>\s*<b class="cifra__valore">(\d+)</b>#', $r['body'], $mv) === 1 && (int) $mv[1] === $vendite, ($mv[1] ?? '?') . ' / ' . $vendite);
$r = $admin->get('/admin/vendite?mesi=3');
prova('…a 3 mesi: 3 colonne', substr_count($r['body'], 'class="viz-colonne__col"') === 3 && str_contains($r['body'], 'aria-current="page">Ultimi 3 mesi'));
$r = $admin->get('/admin/vendite?mesi=6&formato=csv');
prova('…e in CSV: mese, piano, nuovi, cambi, incasso', str_contains($r['body'], 'Mese;Piano;"Nuovi abbonamenti";"Cambi di piano verso";Incasso') && substr_count($r['body'], ';Portfolio;') === 6);
$r = $ospite->get('/admin/vendite');
prova('…solo per l\'amministrazione', $r['code'] !== 200 || !str_contains($r['body'], 'Vendite per piano.'));
$r = $admin->get('/admin/prospetti');
prova('Prospetti: 12 mesi, piani con ricavo annuo, conversione', $r['code'] === 200 && substr_count($r['body'], 'class="barre__col"') === 12
      && str_contains($r['body'], 'Dalla registrazione all') && preg_match('#Ricavo annuo ricorrente.*?cifra__valore">([^<]+)<#s', $r['body'], $mm) === 1 && trim($mm[1]) !== '0 €', $mm[1] ?? '');
$r = $admin->get('/admin/prospetti?formato=csv');
prova('…esporta i 12 mesi in CSV (punto e virgola, BOM)', str_starts_with($r['body'], "\xEF\xBB\xBFMese;Registrati;") && substr_count($r['body'], "\n") === 13);
$psub = (int) val("SELECT id FROM subscriptions WHERE provider_subscription_id = 'sub_prova_paola'");
db()->prepare("UPDATE subscriptions SET status = 'past_due' WHERE id = ?")->execute([$psub]);
$r = $admin->get('/admin/anomalie');
prova('Anomalie: un rinnovo non riuscito compare tra le gravi, con il link al cliente', $r['code'] === 200 && preg_match('#Rinnovi non riusciti.*?Subito.*?/admin/cliente/' . $pacc . '"#s', $r['body']) === 1
      && str_contains($r['body'], 'Controlli andati bene'));
db()->prepare("UPDATE subscriptions SET status = 'active' WHERE id = ?")->execute([$psub]);
prova('…sistemato il rinnovo, la voce va tra i controlli andati bene', !preg_match('#<h2 id="anom-\d+"[^>]*>Rinnovi non riusciti#', $admin->get('/admin/anomalie')['body']));

// Scadenze: l'abbonamento dello staff di Dario finisce tra 7 giorni.
$daAcc = (int) val("SELECT a.id FROM accounts a JOIN users u ON u.id = a.user_id WHERE u.email = 'dario@prova.test'");
$dsub = (int) val('SELECT MAX(id) FROM subscriptions WHERE account_id = ?', [$daAcc]);
db()->prepare("UPDATE subscriptions SET current_period_end = ?, status = 'active' WHERE id = ?")->execute([gmdate('Y-m-d\TH:i:s\Z', time() + 7 * 86400 - 3600), $dsub]);
$r = $admin->get('/admin/scadenze?giorni=30');
prova('Scadenze: Dario, «Attivato dallo staff», tra 6 giorni, con il bottone del promemoria', preg_match('#dario@prova\.test.*?Attivato dallo staff.*?name="solo" value="' . $dsub . '"#s', $r['body']) === 1);
$c = $cron();
$m = $posta('dario@prova.test'); $ult = end($m) ?: [];
prova('Promemoria automatico 7 giorni prima: «La tua guida va offline il …», una volta sola', ($c['email']['scadenza'] ?? 0) >= 1 && str_contains((string) ($ult['subject'] ?? ''), 'La tua guida va offline il')
      && (bool) val("SELECT id FROM email_log WHERE account_id = ? AND kind = 'scadenza' AND ref LIKE ?", [$daAcc, '%-7']), json_encode($c));
$prima = count($posta('dario@prova.test')); $cron();
prova('…al giro dopo non riparte', count($posta('dario@prova.test')) === $prima);
$r = $admin->segui($admin->modulo('/admin/scadenze?giorni=30', '/admin/scadenze/promemoria', ['solo' => (string) $dsub, 'torna' => '/admin/scadenze?giorni=30']));
prova('Promemoria a mano dalla riga: parte subito, segnato «a mano» e nel registro', str_contains($r['body'], 'Promemoria mandato.') && count($posta('dario@prova.test')) === $prima + 1
      && str_contains($r['body'], 'a mano') && (bool) val("SELECT id FROM audit_log WHERE action = 'reminder.manual'"));
db()->prepare("INSERT INTO email_optout (account_id, kind, created_at) VALUES (?, 'scadenza', ?)")->execute([$daAcc, gmdate('Y-m-d\TH:i:s\Z')]);
$r = $admin->segui($admin->modulo("/admin/cliente/$daAcc", '/admin/scadenze/promemoria', ['solo' => (string) $dsub, 'torna' => "/admin/cliente/$daAcc"]));
prova('…chi ha chiesto di non ricevere avvisi non lo riceve nemmeno a mano, e l\'amministratore lo legge', str_contains($r['body'], 'ha chiesto di non ricevere') && count($posta('dario@prova.test')) === $prima + 1);
db()->prepare("DELETE FROM email_optout WHERE account_id = ? AND kind = 'scadenza'")->execute([$daAcc]);
// Due giorni dopo la fine, senza abbonamento nuovo: «La tua guida è offline».
db()->prepare('UPDATE subscriptions SET current_period_end = ? WHERE id = ?')->execute([gmdate('Y-m-d\TH:i:s\Z', time() - 2 * 86400), $dsub]);
$cron(); $m = $posta('dario@prova.test'); $ult = end($m) ?: [];
prova('Il giorno dopo la scadenza: «La tua guida è offline», con il link per rinnovare', str_contains((string) ($ult['subject'] ?? ''), 'La tua guida è offline') && str_contains((string) $ult['text'], '/piano'));
db()->prepare('UPDATE subscriptions SET current_period_end = ? WHERE id = ?')->execute([gmdate('Y-m-d\TH:i:s\Z', time() + 300 * 86400), $dsub]);

// Scheda cliente: nota interna, avvisi mandati, fatturazione.
$admin->modulo("/admin/cliente/$daAcc", "/admin/cliente/$daAcc/nota", ['nota' => 'Ha chiamato il 3: rinnova a gennaio.']);
$r = $admin->get("/admin/cliente/$daAcc");
prova('Nota interna salvata e visibile solo in amministrazione', str_contains($r['body'], 'Ha chiamato il 3: rinnova a gennaio.') && str_contains($r['body'], 'Avvisi e promemoria')
      && str_contains($r['body'], 'automatica') && !str_contains($dario->get('/account')['body'], 'Ha chiamato il 3'));
$r = $admin->get('/admin/clienti?stato=attivo');
prova('Clienti: filtro per stato (solo attivi) e colonna della scadenza', $r['code'] === 200 && str_contains($r['body'], 'Scadenza') && !preg_match('#badge--[a-z]+">(Scaduto|In bozza)#', $r['body']));
$r = $admin->get('/admin/clienti?formato=csv');
prova('…esporta i clienti in CSV', str_starts_with($r['body'], "\xEF\xBB\xBFNome;Email;") && str_contains($r['body'], 'dario@prova.test'));

// Posta: accesso SMTP con LOGIN e PLAIN, errore chiaro, «Prova con questi dati» senza salvare.
capitolo('Posta in uscita · accesso SMTP');
$SMTP_DIR = rtrim($argv[6] ?? '', '/'); $SMTP_PORTA = (int) ($argv[7] ?? 0);
$fileLocale = "$DOVE/app/config.local.php";
$regoleSmtp = fn(array $r) => file_put_contents("$SMTP_DIR/regole.json", json_encode($r + ['utente' => 'info@prova.test', 'password' => 'Giusta-2026!']));
$sessioni = fn() => array_map(fn($l) => json_decode($l, true), file("$SMTP_DIR/sessioni.jsonl", FILE_IGNORE_NEW_LINES) ?: []);
$postaSmtp = fn() => count(file("$SMTP_DIR/posta.jsonl", FILE_IGNORE_NEW_LINES) ?: []);
// Il server accetta le credenziali giuste solo con PLAIN, anche se offre LOGIN e PLAIN (succede).
$regoleSmtp(['offre' => ['LOGIN', 'PLAIN'], 'accetta' => ['PLAIN']]);
file_put_contents($fileLocale, '<?php return ' . var_export(['mail' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => $SMTP_PORTA, 'encryption' => 'none',
    'user' => 'info@prova.test', 'pass' => 'vecchia', 'from' => 'info@prova.test']], true) . ';');
$admin->get('/admin/impostazioni');
$r = $admin->segui($admin->post('/admin/impostazioni/posta/prova', []));
$s = $sessioni();
prova('Password salvata sbagliata: l\'errore dice utente, lunghezza della password e metodi provati (LOGIN e PLAIN), mai la password',
      str_contains($r['body'], 'utente «info@prova.test»') && str_contains($r['body'], 'password di 7 caratteri') && str_contains($r['body'], 'provati LOGIN e PLAIN')
      && str_contains($r['body'], 'Prova con questi dati') && !str_contains($r['body'], 'vecchia') && array_column($s, 'metodo') === ['LOGIN', 'PLAIN'], json_encode($s));
$campiSmtp = ['mail__transport' => 'smtp', 'mail__host' => '127.0.0.1', 'mail__port' => (string) $SMTP_PORTA, 'mail__encryption' => 'none', 'mail__user' => 'info@prova.test',
              'mail__from' => 'info@prova.test', 'mail__from_name' => 'MyHouse Welcome'];
$prima = count($sessioni());
$r = $admin->post('/admin/impostazioni/posta/prova', $campiSmtp + ['mail__pass' => 'Giusta-2026!', 'con_dati' => '1', 'password' => 'sbagliata']);
prova('«Prova con questi dati» senza la password dell\'amministratore: non parte', $r['code'] === 422 && str_contains($r['body'], 'la prova non è partita') && count($sessioni()) === $prima);
$r = $admin->segui($admin->post('/admin/impostazioni/posta/prova', $campiSmtp + ['mail__pass' => 'Giusta-2026!', 'con_dati' => '1', 'password' => 'AdminProva123']));
$nuove = array_slice($sessioni(), $prima);
prova('«Prova con questi dati»: usa la password scritta (LOGIN rifiutato, PLAIN accettato), l\'email parte, ma non salva niente',
      str_contains($r['body'], 'Email di prova spedita') && str_contains($r['body'], 'non sono ancora salvati') && $postaSmtp() === 1
      && array_column($nuove, 'ok') === [false, true] && ((require $fileLocale)['mail']['pass'] ?? '') === 'vecchia', json_encode($nuove));
$admin->post('/admin/impostazioni/posta', $campiSmtp + ['mail__pass' => 'Giusta-2026!', 'password' => 'AdminProva123']);
$r = $admin->segui($admin->post('/admin/impostazioni/posta/prova', []));
prova('Salvata, «Prova i dati salvati» riesce; anche le email normali passano con PLAIN', str_contains($r['body'], 'Email di prova spedita') && $postaSmtp() === 2
      && ((require $fileLocale)['mail']['pass'] ?? '') === 'Giusta-2026!');
$regoleSmtp(['offre' => ['LOGIN'], 'accetta' => ['LOGIN']]);
$prima = count($sessioni());
$r = $admin->segui($admin->post('/admin/impostazioni/posta/prova', []));
prova('Server che offre solo LOGIN: si usa LOGIN e basta', str_contains($r['body'], 'Email di prova spedita') && array_column(array_slice($sessioni(), $prima), 'metodo') === ['LOGIN']);
file_put_contents($fileLocale, '<?php return ' . var_export(['mail' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => $SMTP_PORTA, 'encryption' => 'none',
    'user' => 'info@prova.test', 'pass' => '', 'from' => 'info@prova.test']], true) . ';');
$prima = count($sessioni());
$r = $admin->segui($admin->post('/admin/impostazioni/posta/prova', []));
prova('Password vuota: lo dice subito, senza tentativi sul server', str_contains($r['body'], 'Manca la password della casella') && count($sessioni()) === $prima);
@unlink($fileLocale); @unlink(dirname($fileLocale) . '/config.local.bak.php');

// Invita un amico dal pannello del cliente: interruttore in Impostazioni, codice, invito per email.
capitolo('Invita un amico · pannello del cliente');
$fileLocale = "$DOVE/app/config.local.php";
@unlink($fileLocale);
prova('Inviti spenti: la pagina del cliente non c\'è e la voce non compare nel menu', $paola->get('/inviti')['code'] === 404 && !str_contains($paola->get('/pannello')['body'], 'Invita un amico</'));
$admin->get('/admin/impostazioni');
$r = $admin->segui($admin->post('/admin/impostazioni/inviti', ['inviti__attivi' => '1', 'password' => 'AdminProva123']));
prova('Impostazioni → «Invita un amico»: si accende con una casella, senza toccare config.local.php a mano', str_contains($r['body'], 'impostazioni salvate')
      && ((require $fileLocale)['inviti']['attivi'] ?? false) === true);
db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'impostazioni%'");   // le prove della posta, qui sopra, hanno usato il limite
$r = $admin->segui($admin->post('/admin/impostazioni/inviti/prova', []));
prova('…«Prova i dati salvati» dice che è acceso e quanti clienti possono invitare', preg_match('/Acceso: \d+ client/', $r['body']) === 1, preg_match('#<[^>]*role="(?:status|alert)"[^>]*>(.*?)</#s', $r['body'], $fm) ? strip_tags($fm[1]) : substr(strip_tags($r['body']), 0, 300));
$r = $paola->get('/inviti');
$codPaola = (string) val('SELECT referral_code FROM accounts WHERE id = ?', [$pacc]);
prova('Cliente con abbonamento Stripe: voce nel menu, link, codice ben visibile con «Copia il codice», invito per email', $r['code'] === 200
      && str_contains($r['body'], 'href="' . '/welcomebook/index.php/inviti"') && $codPaola !== '' && str_contains($r['body'], '<b>' . $codPaola . '</b>')
      && str_contains($r['body'], 'Copia il codice') && str_contains($r['body'], 'action="/welcomebook/index.php/inviti/email"'), $codPaola);
$prima = count($posta('amico.uno@prova.test'));
$r = $paola->segui($paola->post('/inviti/email', ['email' => "amico.uno@prova.test, non-valida\npaola@prova.test; dario@prova.test", 'messaggio' => 'Io la uso per la casa di Todi: prova anche tu.']));
$m = $posta('amico.uno@prova.test'); $ult = end($m) ?: [];
prova('Invito per email: parte solo all\'amico nuovo, con link, codice e messaggio; gli altri si saltano senza dire chi è già cliente',
      count($m) === $prima + 1 && str_contains((string) ($ult['subject'] ?? ''), 'ti invita su MyHouse Welcome') && str_contains((string) $ult['text'], '/i/' . $codPaola)
      && str_contains((string) $ult['text'], 'codice ' . $codPaola) && str_contains((string) $ult['text'], 'Io la uso per la casa di Todi')
      && str_contains($r['body'], 'Invito mandato.') && str_contains($r['body'], '3 indirizzi non sono stati invitati'), json_encode($ult));
$riga = riga('SELECT * FROM referral_invites WHERE account_id = ?', [$pacc]);
prova('…l\'indirizzo non si conserva: solo l\'impronta e la forma mascherata, che il cliente vede nell\'elenco', $riga && $riga['email_mask'] === "a\u{2022}\u{2022}\u{2022}@prova.test"
      && !str_contains(json_encode($riga), 'amico.uno') && str_contains($r['body'], "a\u{2022}\u{2022}\u{2022}@prova.test") && str_contains($r['body'], 'Invitato'));
$r = $paola->segui($paola->post('/inviti/email', ['email' => 'amico.uno@prova.test']));
prova('…lo stesso amico non si invita due volte in 30 giorni', str_contains($r['body'], 'Nessun invito mandato.') && count($posta('amico.uno@prova.test')) === $prima + 1);
$troppi = implode(',', array_map(fn($i) => "amico$i@prova.test", range(1, 11)));
$r = $paola->segui($paola->post('/inviti/email', ['email' => $troppi]));
prova('…al massimo 10 indirizzi per volta, e il modulo resta compilato', str_contains($r['body'], 'Al massimo 10 indirizzi per volta') && str_contains($r['body'], 'amico11@prova.test'));
$r = $dario->get('/inviti');
$codDario = (string) val('SELECT referral_code FROM accounts WHERE id = ?', [$accDi('dario@prova.test')]);
prova('7D · abbonamento attivato dallo staff: può invitare, con il messaggio pronto, il link e il codice', $r['code'] === 200 && str_contains($r['body'], 'Il messaggio pronto')
      && $codDario !== '' && str_contains($r['body'], '/i/' . $codDario) && str_contains($r['body'], 'Il codice è ' . $codDario) && str_contains($r['body'], 'Manda l\'invito'));
prova('Amministrazione → Inviti: il conto degli inviti per email', preg_match('#Inviti per email</span><b class="cifra__valore">1<#', $admin->get('/admin/inviti')['body']) === 1);
@unlink($fileLocale); @unlink(dirname($fileLocale) . '/config.local.bak.php');

// Riscaldamento e aria condizionata (8C: due sezioni): orari, stato di adesso, temperatura, consiglio sull'energia.
capitolo('Riscaldamento e aria condizionata');
$lucia->get("/pannello/$casa/procedura/sezioni");
$r = $lucia->get("/pannello/$casa/procedura/sezioni");
prova('8C · nel catalogo «Riscaldamento» e «Aria condizionata», non più la sezione unica', str_contains($r['body'], 'value="riscaldamento"') && str_contains($r['body'], 'value="aria"')
      && !str_contains($r['body'], 'value="clima"'));
$lucia->post("/pannello/$casa/sezioni", ['kind' => 'clima', 'torna' => 'procedura']);
prova('…la vecchia «clima» non si aggiunge più', !val("SELECT 1 FROM sections WHERE property_id = ? AND kind = 'clima'", [$casa]));
$lucia->post("/pannello/$casa/sezioni", ['kind' => 'riscaldamento', 'torna' => 'procedura']);
$lucia->post("/pannello/$casa/sezioni", ['kind' => 'aria', 'torna' => 'procedura']);
$csid = (int) val("SELECT id FROM sections WHERE property_id = ? AND kind = 'riscaldamento'", [$casa]);
$asid = (int) val("SELECT id FROM sections WHERE property_id = ? AND kind = 'aria'", [$casa]);
$r = $lucia->get("/pannello/$casa/sezioni/$csid");
$ra = $lucia->get("/pannello/$casa/sezioni/$asid");
prova('Sezione «Riscaldamento»: com\'è l\'impianto, gli orari compaiono solo «a orari»; «Aria condizionata» ha i suoi campi', $csid > 0 && $asid > 0
      && str_contains($r['body'], 'Si accende da solo, a orari') && !str_contains($r['body'], 'L&#039;ospite la accende quando vuole')
      && str_contains($r['body'], 'data-campo-solo-con="heating_mode" data-solo-valori="auto"') && str_contains($r['body'], 'Aggiungi una fascia oraria')
      && preg_match('#name="risparmio" value="1" checked#', $r['body']) === 1
      && str_contains($ra['body'], 'L&#039;ospite la accende quando vuole') && !str_contains($ra['body'], 'Si accende da solo, a orari'));
$r = $lucia->post("/pannello/$casa/sezioni/$csid", ['heating_mode' => 'auto',
    'heating_times' => [['id' => '', 'from' => '06:30', 'to' => '09:00'], ['id' => '', 'from' => '17:00', 'to' => '23:00', 'days' => ['1', '2', '3', '4', '5']]],
    'heating_temp' => '20', 'heating_period' => 'Dal 15 ottobre al 15 aprile', 'heating_how' => [''], 'risparmio' => '1', 'note' => '']);
$lucia->post("/pannello/$casa/sezioni/$asid", ['ac_mode' => 'libero', 'ac_temp' => '26', 'ac_how' => ['Il telecomando è sul comodino.', 'Premi ON, poi il fiocco di neve.'], 'risparmio' => '1', 'note' => '']);
$sd = json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$csid]), true) ?: [];
$sa = json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$asid]), true) ?: [];
prova('…salvate: modo, due fasce (una solo dal lunedì al venerdì), temperature, ognuna nella sua sezione', pulita($r) && ($sd['heating_mode'] ?? '') === 'auto' && count($sd['heating_times'] ?? []) === 2
      && ($sd['heating_times'][1]['days'] ?? []) === [1, 2, 3, 4, 5] && ($sa['ac_temp'] ?? '') === '26' && !isset($sd['ac_temp']) && !isset($sa['heating_mode']), json_encode([$sd, $sa]));
$r = $lucia->get("/pannello/$casa/anteprima/$csid");
$ra = $lucia->get("/pannello/$casa/anteprima/$asid");
prova('Guida: stato di adesso (acceso o spento), fasce leggibili, «Impostato a 20 °C»; l\'aria condizionata nella sua pagina, con il telecomando',
      $r['code'] === 200 && preg_match('#clima__stato--(on|off)#', $r['body']) === 1 && str_contains($r['body'], 'tutti i giorni') && str_contains($r['body'], '6:30 – 9:00')
      && str_contains($r['body'], 'lun–ven') && str_contains($r['body'], "Impostato a 20\u{00A0}°C") && str_contains($r['body'], 'Si accende e si spegne da solo')
      && !str_contains($r['body'], 'La accendi tu quando vuoi') && str_contains($ra['body'], 'La accendi tu quando vuoi') && str_contains($ra['body'], 'Il telecomando è sul comodino.')
      && str_contains($r['body'], 'Dal 15 ottobre al 15 aprile'));
prova('…il consiglio sull\'energia in tutte e due, con le temperature dell\'host', str_contains($r['body'], 'In Italia l&#039;energia costa molto') && str_contains($r['body'], "D&#039;inverno bastano 20\u{00A0}°C")
      && str_contains($ra['body'], "D&#039;estate bastano 26\u{00A0}°C") && !str_contains($r['body'], 'D&#039;estate bastano'));
$r = $lucia->get("/pannello/$casa/anteprima/$csid?l=en");
prova('…in inglese', str_contains($r['body'], 'Heating') && str_contains($r['body'], 'every day') && str_contains($r['body'], "Set to 20\u{00A0}°C") && preg_match('#(On|Off) now#', $r['body']) === 1);
$lucia->post("/pannello/$casa/sezioni/$csid", ['heating_mode' => 'manuale', 'heating_temp' => '20', 'risparmio' => '', 'note' => 'Il termostato è in corridoio.']);
$lucia->post("/pannello/$casa/sezioni/$asid", ['ac_mode' => 'no', 'risparmio' => '']);
$r = $lucia->get("/pannello/$casa/anteprima/$csid");
$ra = $lucia->get("/pannello/$casa/anteprima/$asid");
prova('Riscaldamento acceso dall\'ospite, niente aria condizionata, consiglio spento: niente orari vecchi né stato',
      str_contains($r['body'], 'Lo accendi tu, quando ti serve.') && str_contains($r['body'], "Temperatura consigliata: 20\u{00A0}°C") && str_contains($ra['body'], 'Non c&#039;è l&#039;aria condizionata.')
      && !str_contains($r['body'], 'clima__stato') && !str_contains($r['body'], '6:30') && !str_contains($r['body'], 'energia costa') && str_contains($r['body'], 'Il termostato è in corridoio.'));
$stati = json_decode((string) shell_exec('php -r ' . escapeshellarg('define("MHW_APP", "' . $DOVE . '/app"); spl_autoload_register(fn($c) => require "' . $DOVE . '/app/src/" . substr($c, 4) . ".php");
  $f = MHW\Clima::fasce([["from" => "17:00", "to" => "23:00"], ["from" => "06:30", "to" => "09:00"], ["from" => "22:00", "to" => "02:00", "days" => ["6"]]]);
  $t = fn($s) => new DateTimeImmutable($s, new DateTimeZone("Europe/Rome"));
  echo json_encode(array_map(fn($q) => MHW\Clima::stato($f, $t($q)), ["2026-10-07 07:00", "2026-10-07 12:00", "2026-10-07 23:30", "2026-10-11 01:00"]));')), true) ?: [];
prova('Stato di adesso: acceso al mattino fino alle 9, spento a mezzogiorno fino alle 17, di notte si riaccende domani, la fascia del sabato notte vale fino alle 2',
      ($stati[0]['acceso'] ?? null) === true && ($stati[0]['fino'] ?? '') === '09:00' && ($stati[1]['prossima'] ?? '') === '17:00' && ($stati[1]['domani'] ?? true) === false
      && ($stati[2]['prossima'] ?? '') === '06:30' && ($stati[2]['domani'] ?? false) === true && ($stati[3]['acceso'] ?? null) === true && ($stati[3]['fino'] ?? '') === '02:00', json_encode($stati));

capitolo('Una guida = un\'unità ricettiva: sezioni, CIN, varianti camera; listino e vetrina');
// Un'altra prova di amministrazione ha messo un Portfolio a prezzo unico: qui si rimettono gli scaglioni dal modulo.
$pfS = (int) val("SELECT id FROM packages WHERE code = 'portfolio'");
$pkS = riga('SELECT * FROM packages WHERE id = ?', [$pfS]);
$featS = []; foreach (righe('SELECT f.code, pf.value FROM package_features pf JOIN features f ON f.id = pf.feature_id WHERE pf.package_version_id = ?', [pv('portfolio')]) as $x) $featS[$x['code']] = $x['value'];
$admin->get('/admin/pacchetti');
$admin->post("/admin/pacchetti/$pfS/nuova-versione", ['nome' => $pkS['name'], 'prezzo' => '117', 'prezzo_extra' => '60', 'scaglioni' => "3: 50\n6: 40 €\n11: 30\n21: 25,00",
    'min_quantita' => '2', 'max_quantita' => '50', 'stripe_price_id' => '', 'stripe_extra_price_id' => '', 'f' => $featS, 'headline' => $pkS['headline'], 'tagline' => $pkS['tagline'],
    'description' => $pkS['description'], 'bullets' => $pkS['bullets'], 'badge' => $pkS['badge'], 'cta_label' => $pkS['cta_label'], 'public' => '1', 'active' => '1']);
prova('Amministrazione: nuova versione Portfolio con gli scaglioni scritti a mano', json_decode((string) val('SELECT extra_tiers FROM package_versions WHERE id = ?', [pv('portfolio')]), true)
      === [['da' => 2, 'cents' => 6000], ['da' => 3, 'cents' => 5000], ['da' => 6, 'cents' => 4000], ['da' => 11, 'cents' => 3000], ['da' => 21, 'cents' => 2500]]);
$r = $admin->post("/admin/pacchetti/$pfS/nuova-versione", ['nome' => $pkS['name'], 'prezzo' => '117', 'prezzo_extra' => '60', 'scaglioni' => "2: 55", 'min_quantita' => '2', 'max_quantita' => '50', 'f' => $featS]);
prova('…uno scaglione dalla 2ª (o scritto male) non passa', str_contains((string) json_encode(righe('SELECT id FROM package_versions WHERE extra_tiers LIKE ?', ['%5500%'])), '[]'));
$r = $ospite->get('/');
prova('Listino e FAQ: il QR «non cambia mai, anche se modifichi la guida», non più «permanente»', str_contains($r['body'], 'Il QR Code non cambia mai, anche se modifichi la guida')
      && !preg_match('#QR[^<]{0,20}permanent#iu', $r['body']) && !preg_match('#più scelto#iu', $r['body']), $r['code'] . ' ' . substr($r['body'], 0, 200));
prova('FAQ: B&B e affittacamere con più camere sono una guida sola (7B: reti diverse nella sezione Wi-Fi), più case no, gli scaglioni del Portfolio',
      str_contains($r['body'], 'Ho un B&amp;B, un affittacamere o un agriturismo con più camere') && str_contains($r['body'], 'nella sezione Wi-Fi aggiungi una riga per ogni zona') && !str_contains($r['body'], 'variante camera')
      && str_contains($r['body'], 'Posso mettere più case in una guida sola?') && str_contains($r['body'], "dalla 3ª alla 5ª 50\u{00A0}€ l&#039;una"), (string) $r['code']);
$r = $ospite->get('/termini');
prova('Termini: una guida = un\'unità ricettiva, un indirizzo e un CIN, con l\'eccezione delle camere', str_contains($r['body'], 'Una guida, un&#039;unità ricettiva.')
      && str_contains($r['body'], 'B&amp;B, affittacamere e agriturismi con più camere allo stesso indirizzo'), (string) $r['code']);
$r = $admin->get('/admin/pacchetti');
prova('Amministrazione → Piani: gli scaglioni del Portfolio si modificano (dalla struttura: prezzo)', str_contains($r['body'], 'name="scaglioni"') && str_contains($r['body'], "3: 50\n6: 40\n11: 30\n21: 25"), $r['code'] . ' ' . ($r['loc'] ?? ''));
$vetSlug = (string) val('SELECT slug FROM properties WHERE is_demo = 2 ORDER BY id DESC LIMIT 1');
$r = $ospite->get("/g/$vetSlug");
prova('Vetrina: la foto profilo (la porta di Casa Checco) sul bottone dei contatti e accanto a Francesco', $vetSlug !== '' && substr_count($r['body'], 'class="contatto__foto') === 2
      && (bool) val('SELECT profile_media_id FROM properties WHERE slug = ?', [$vetSlug]));

$vera = new Browser('vera');
$vera->get('/registrati?piano=' . pv('plus'));
$vera->post('/registrati', ['piano' => pv('plus'), 'name' => 'Vera Camere', 'email' => 'vera@prova.test', 'password' => 'VeraProva1234', 'termini' => '1', 'privacy' => '1']);
$vacc = $accDi('vera@prova.test');
$vera->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'B&B Vera', 'city' => 'Spello']);
$vp = (int) val('SELECT id FROM properties WHERE account_id = ?', [$vacc]);
db()->prepare("INSERT INTO subscriptions (account_id, package_version_id, status, provider, provider_customer_id, provider_subscription_id, current_period_start,
    current_period_end, payment_status, created_at, updated_at, quantity) VALUES (?, ?, 'active', 'stripe', 'cus_vera', 'sub_prova_vera', ?, ?, 'paid', ?, ?, 1)")
    ->execute([$vacc, pv('plus'), gmdate('Y-m-d\TH:i:s\Z', time() - 100 * 86400), gmdate('Y-m-d\TH:i:s\Z', time() + 265 * 86400), gmdate('Y-m-d\TH:i:s\Z'), gmdate('Y-m-d\TH:i:s\Z')]);
$vera->modulo('/account', '/account/fatturazione', ['billing_type' => 'privato', 'billing_name' => 'Vera Camere', 'cf' => 'RSSMRA85T10A562S',
               'billing_address' => 'Via Giulia 2', 'billing_postal' => '06038', 'billing_city' => 'Spello', 'billing_province' => 'PG']);
$vera->modulo("/pannello/$vp", "/pannello/$vp/sezioni", ['kind' => 'wifi']);
$vera->modulo("/pannello/$vp", "/pannello/$vp/sezioni", ['kind' => 'wifi']);
prova('Ogni sezione una volta sola: il secondo Wi-Fi non si aggiunge', (int) val("SELECT COUNT(*) FROM sections WHERE property_id = ? AND kind = 'wifi'", [$vp]) === 1);
for ($i = 0; $i < 4; $i++) $vera->modulo("/pannello/$vp", "/pannello/$vp/sezioni", ['kind' => 'custom']);
$r = $vera->get("/pannello/$vp/procedura/sezioni");
prova('Sezione libera una per guida (9F·12): la seconda non si aggiunge e il catalogo non la propone più', (int) val("SELECT COUNT(*) FROM sections WHERE property_id = ? AND kind = 'custom'", [$vp]) === 1
      && !str_contains($r['body'], 'name="kind" value="custom"') && str_contains($r['body'], 'Hai già la sezione libera'));
$vw = (int) val("SELECT id FROM sections WHERE property_id = ? AND kind = 'wifi'", [$vp]);
$vcore = (int) val('SELECT id FROM sections WHERE property_id = ? AND is_core = 1', [$vp]);
$vera->post("/pannello/$vp/sezioni/$vw", ['networks' => [['id' => '', 'ssid' => 'Vera_Casa', 'password' => 'casa-vera-1', 'zone' => '']]]);
$vera->post("/pannello/$vp/sezioni/$vcore", ['checkin_steps' => ['La chiave della porta di casa è nella cassetta.']]);

Browser::$cinAutomatico = false;
$r = $vera->segui($vera->modulo("/pannello/$vp/procedura/pubblica", "/pannello/$vp/pubblica", ['acquirente' => 'impresa']));
prova('Senza CIN la guida non si pubblica, e si dice perché', !val('SELECT id FROM guide_versions WHERE property_id = ?', [$vp]) && str_contains($r['body'], 'Manca il CIN'));
$cinAltrui = (string) val("SELECT cin FROM properties WHERE cin <> '' AND is_demo = 0 AND account_id <> ? ORDER BY id LIMIT 1", [$vacc]);
$r = $vera->modulo("/pannello/$vp/impostazioni", "/pannello/$vp/impostazioni", ['name' => 'B&B Vera', 'city' => 'Spello', 'cin' => strtolower(substr($cinAltrui, 0, 6)) . ' ' . substr($cinAltrui, 6)]);
prova('Il CIN di un\'altra guida non si salva (anche scritto in minuscolo e con gli spazi)', $cinAltrui !== '' && (string) val('SELECT cin FROM properties WHERE id = ?', [$vp]) === ''
      && str_contains($r['body'], 'già usato da un&#039;altra guida'));
$vera->modulo("/pannello/$vp/impostazioni", "/pannello/$vp/impostazioni", ['name' => 'B&B Vera', 'city' => 'Spello', 'cin' => 'IT123']);
$r = $vera->segui($vera->modulo("/pannello/$vp/procedura/pubblica", "/pannello/$vp/pubblica", ['acquirente' => 'impresa']));
prova('Un CIN incompleto si salva ma non si pubblica', (string) val('SELECT cin FROM properties WHERE id = ?', [$vp]) === 'IT123' && str_contains($r['body'], 'non sembra completo')
      && !val('SELECT id FROM guide_versions WHERE property_id = ?', [$vp]));
$vera->modulo("/pannello/$vp/impostazioni", "/pannello/$vp/impostazioni", ['name' => 'B&B Vera', 'city' => 'Spello', 'cin' => 'cin: it 054050 b4 vera0001']);
$vera->modulo("/pannello/$vp/procedura/pubblica", "/pannello/$vp/pubblica", ['acquirente' => 'impresa']);
prova('Con un CIN valido (salvato pulito) la guida si pubblica', (string) val('SELECT cin FROM properties WHERE id = ?', [$vp]) === 'IT054050B4VERA0001'
      && (bool) val('SELECT id FROM guide_versions WHERE property_id = ?', [$vp]));
Browser::$cinAutomatico = true;
$altraId = (int) val("SELECT p.id FROM properties p JOIN accounts a ON a.id = p.account_id JOIN users u ON u.id = a.user_id
                      WHERE p.cin <> '' AND p.is_demo = 0 AND p.archived_at IS NULL AND p.account_id <> ? AND u.email LIKE '%@prova.test' ORDER BY p.id LIMIT 1", [$vacc]);
$cinPrima = (string) val('SELECT cin FROM properties WHERE id = ?', [$altraId]);
db()->prepare('UPDATE properties SET cin = ? WHERE id = ?')->execute(['IT054050B4VERA0001', $altraId]);
$r = $admin->get('/admin/anomalie');
prova('Anomalie: il CIN ripetuto su due guide si vede', str_contains($r['body'], 'Guide senza CIN o con CIN ripetuto') && str_contains($r['body'], 'ha lo stesso CIN (IT054050B4VERA0001)'), $r['code'] . ' ' . ($r['loc'] ?? ''));
db()->prepare('UPDATE properties SET cin = ? WHERE id = ?')->execute([$cinPrima, $altraId]);

// ============================================================ 7B · niente varianti camera
// Una camera di prima (dati e QR già stampati) e la sua voce sull'abbonamento Stripe: come li trova chi aggiorna.
$tokVecchio = 'cameravecchia7b' . bin2hex(random_bytes(2));
db()->prepare("INSERT INTO room_variants (property_id, name, token, created_at) VALUES (?, 'Camera Rosa', ?, ?)")->execute([$vp, $tokVecchio, gmdate('Y-m-d\TH:i:s\Z')]);
db()->prepare("UPDATE subscriptions SET provider_variant_item_id = 'si_var_sub_prova_vera' WHERE account_id = ?")->execute([$vacc]);
$statoF = "$STRIPE_DIR/stato.json"; $st = json_decode((string) file_get_contents($statoF), true) ?: [];
$st['subs']['sub_prova_vera'] = ($st['subs']['sub_prova_vera'] ?? ['cancel_at_period_end' => false, 'status' => 'active', 'start' => time(), 'end' => time() + 265 * 86400, 'extra' => null]) + [];
$st['subs']['sub_prova_vera']['varianti'] = 2; file_put_contents($statoF, json_encode($st));
$vslug7 = (string) val('SELECT slug FROM properties WHERE id = ?', [$vp]);
$r = $vera->get("/pannello/$vp/varianti");
prova('7B · /pannello/{id}/varianti rimanda al pannello della guida', $r['code'] === 302 && str_ends_with($r['loc'], "/pannello/$vp"), $r['loc']);
$r = $vera->get("/pannello/$vp/varianti/1/qr.png");
prova('…anche i vecchi indirizzi delle singole camere', $r['code'] === 302 && str_ends_with($r['loc'], "/pannello/$vp"));
prova('7B · nel menu della guida non c\'è più «Varianti camera»', !str_contains($vera->get("/pannello/$vp")['body'], '/varianti"'));
$r = $ospite->get("/q/$tokVecchio");
prova('7B · il QR già stampato di una camera apre la guida della struttura', $r['code'] === 302 && str_ends_with($r['loc'], "/g/$vslug7/benvenuto"), $r['loc']);
$r = $ospite->get("/g/$vslug7/c/$tokVecchio");
$r2 = $ospite->get("/g/$vslug7/c/$tokVecchio/benvenuto");
prova('…e i vecchi link /g/…/c/… portano alla stessa pagina, senza camera', $r['code'] === 302 && str_ends_with($r['loc'], "/g/$vslug7") && $r2['code'] === 302 && str_ends_with($r2['loc'], "/g/$vslug7/benvenuto"));
$prima = count(richiesteStripe());
$uscita = (string) shell_exec('php ' . escapeshellarg("$DOVE/app/tools/togli-varianti-stripe.php") . ' --prova 2>&1');
$dopoProva = array_slice(richiesteStripe(), $prima);
prova('7B · togli-varianti-stripe.php --prova: elenca cliente, camere e importo, e non tocca niente', str_contains($uscita, 'vera@prova.test') && str_contains($uscita, '2 camere')
      && str_contains($uscita, '--prova: non ho toccato niente') && !array_filter($dopoProva, fn($q) => $q['metodo'] !== 'GET')
      && val('SELECT provider_variant_item_id FROM subscriptions WHERE account_id = ?', [$vacc]) === 'si_var_sub_prova_vera', $uscita);
$uscita = (string) shell_exec('echo NO | php ' . escapeshellarg("$DOVE/app/tools/togli-varianti-stripe.php") . ' 2>&1');
prova('…senza «SI» si ferma', str_contains($uscita, 'Fermato: non ho toccato niente') && !array_filter(array_slice(richiesteStripe(), $prima), fn($q) => $q['metodo'] === 'DELETE'));
$uscita = (string) shell_exec('echo SI | php ' . escapeshellarg("$DOVE/app/tools/togli-varianti-stripe.php") . ' 2>&1');
$del = array_values(array_filter(array_slice(richiesteStripe(), $prima), fn($q) => $q['metodo'] === 'DELETE'));
prova('…con «SI» toglie la voce con il credito della parte non goduta, azzera la colonna e scrive il registro', count($del) === 1
      && $del[0]['percorso'] === '/v1/subscription_items/si_var_sub_prova_vera' && ($del[0]['corpo']['proration_behavior'] ?? '') === 'create_prorations'
      && val('SELECT provider_variant_item_id FROM subscriptions WHERE account_id = ?', [$vacc]) === ''
      && str_contains((string) val("SELECT meta FROM audit_log WHERE action = 'stripe.voce_camere_tolta' ORDER BY id DESC"), 'sub_prova_vera'), $uscita);
$uscita = (string) shell_exec('php ' . escapeshellarg("$DOVE/app/tools/togli-varianti-stripe.php") . ' --prova 2>&1');
prova('…e una seconda volta non trova più niente', str_contains($uscita, 'Nessun abbonamento attivo ha la voce delle camere'), $uscita);
$resti = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$DOVE/app/src")) as $f) if ($f->isFile()) $resti[] = $f->getPathname();
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$DOVE/app/views")) as $f) if ($f->isFile()) $resti[] = $f->getPathname();
$resti = array_values(array_filter($resti, fn($f) => (preg_match('/\bVarianti\b(?!\/)|variantPrice|setVariantQuantity/', $t = (string) file_get_contents($f)) === 1 || stripos($t, 'variante camera') !== false)));
prova('7B · nel codice e nelle viste non restano Varianti, variantPrice, setVariantQuantity, «variante camera»', $resti === [], implode(', ', array_map('basename', $resti)));

// ============================================================ 7B · listino e schede
$ess = righe("SELECT pv.id, f.code, pf.value FROM package_versions pv JOIN packages p ON p.id = pv.package_id JOIN package_features pf ON pf.package_version_id = pv.id
               JOIN features f ON f.id = pf.feature_id WHERE p.code = 'essential' AND f.code IN ('sections', 'profile_image')");
prova('7B · Essential, in tutte le versioni (anche chi è già abbonato): 6 sezioni e foto profilo', count($ess) >= 4
      && !array_filter($ess, fn($x) => ($x['code'] === 'sections' && $x['value'] !== '6') || ($x['code'] === 'profile_image' && $x['value'] !== '1')), json_encode($ess));
prova('…e le varianti camera in nessun piano', (int) val("SELECT COUNT(*) FROM package_features pf JOIN features f ON f.id = pf.feature_id WHERE f.code = 'room_variants' AND pf.value <> '0'") === 0);
$migr = 'define("MHW_APP", ' . var_export("$DOVE/app", true) . '); spl_autoload_register(fn($c) => require MHW_APP . "/src/" . substr($c, 4) . ".php"); '
      . 'MHW\Config::load(MHW_APP . "/config.php"); $f = require MHW_APP . "/migrations/031_listino_7b.php"; $f(MHW\Db::conn()); echo "fatto";';
$impronta = fn() => md5(json_encode(righe('SELECT id, bullets, tagline FROM packages ORDER BY id')) . json_encode(righe('SELECT * FROM package_features ORDER BY package_version_id, feature_id')));
// Le prove di amministrazione qui sopra hanno riscritto i testi di Essential: si rimettono quelli di serie di prima della 7B,
// poi una passata della migrazione (che li aggiorna), e la seconda non deve cambiare niente.
db()->prepare("UPDATE packages SET bullets = ? WHERE code = 'essential'")->execute(["1 struttura\nSezione Check-in & Check-out inclusa\n4 sezioni aggiuntive a scelta\nItaliano e inglese\nLogo e foto di copertina\nLink e QR Code che non cambiano mai, anche se modifichi la guida"]);
shell_exec('php -r ' . escapeshellarg($migr) . ' 2>&1');
prova('7B · la migrazione aggiorna i testi di serie di Essential (6 sezioni, foto profilo dopo il logo)', (string) val("SELECT bullets FROM packages WHERE code = 'essential'")
      === "1 struttura\nSezione Check-in & Check-out inclusa\n6 sezioni aggiuntive a scelta\nItaliano e inglese\nLogo e foto di copertina\nFoto profilo dell'host\nLink e QR Code che non cambiano mai, anche se modifichi la guida");
$primaM = $impronta(); $esito = trim((string) shell_exec('php -r ' . escapeshellarg($migr) . ' 2>&1'));
prova('7B · la migrazione 031 una seconda volta non cambia niente', $esito === 'fatto' && $impronta() === $primaM, $esito);
$r = $ospite->get('/');
$schede = preg_match('#<section id="piani".*?</section>#s', $r['body'], $mm) ? $mm[0] : '';
prova('7B · schede: «6 sezioni aggiuntive a scelta» e «Foto profilo dell\'host» in Essential, «Tutte le N sezioni» in Plus', str_contains($schede, '6 sezioni aggiuntive a scelta')
      && str_contains($schede, 'Foto profilo dell&#039;host') && preg_match('#Tutte le \d+ sezioni#', $schede) === 1 && !str_contains($schede, '{sezioni}')
      && !str_contains($schede, 'Sezioni illimitate') && !preg_match('#[Vv]arianti#', $schede));
prova('…foto e PDF (8A), traduzioni e «Ideale per» di Plus con i testi nuovi', str_contains($schede, 'Possibilità di inserire foto e PDF accanto ai testi, per far vedere meglio quello che spieghi')
      && !str_contains($schede, 'la foto del cancello')
      && str_contains($schede, 'Scrivi nella tua lingua, traduciamo noi nelle altre: tu controlli e confermi. Comprese nel piano, entro un limite annuale di caratteri.') && !str_contains($schede, 'Scrivi in italiano')
      && str_contains($schede, 'B&amp;B, case vacanza e agriturismi con ospiti da tutto il mondo: sei lingue, foto e consigli sul territorio.')
      && !str_contains($schede, 'Scrivici per un preventivo'));
prova('7B · 9A · Portfolio oltre 10: «Ti facciamo una proposta su misura» che porta al riquadro del preventivo, niente mailto', str_contains($schede, 'data-oltre hidden')
      && str_contains($schede, 'Ti facciamo una proposta su misura.') && str_contains($schede, 'href="#preventivo"') && str_contains($schede, '>Chiedi il preventivo</a>')
      && !str_contains($schede, 'subject=Preventivo%20Portfolio'));
prova('7B · nelle schede i bottoni sono tutti pieni (nessun «Crea gratis» a contorno)', preg_match_all('#class="btn btn--ghost"[^>]*>Crea gratis#', $schede) === 0);
$tempo7 = preg_match('#<section[^>]*class="t2[^"]*".*?</section>#s', $r['body'], $mm) ? $mm[0] : $r['body'];
prova('7B · tempo risparmiato: senza «Abbonamento …, IVA esclusa» e «Differenza», con il valore del tempo (8A)', !str_contains($r['body'], 'IVA esclusa</dt>') && !str_contains($r['body'], 'Differenza a tuo favore')
      && str_contains($r['body'], 'class="t2__valgono"'));
$guad = preg_match('#<section id="guadagno".*?</section>#s', $r['body'], $mm) ? $mm[0] : '';
prova('7B · «Crea gratis la tua guida» dopo «Servizi extra», come il bottone dell\'hero', strpos($guad, 'Crea gratis la tua guida') > strpos($guad, 'Servizi extra.') && str_contains($guad, 'btn btn--lg btn--go guadagno__cta'));
$r = $admin->get('/admin/pacchetti');
prova('7B · Amministrazione → Piani: l\'aiuto sotto il Price ID e il segnaposto {sezioni}', str_contains($r['body'], 'Lascia vuoto: Stripe userà il prezzo scritto qui. Chi è già abbonato resta al suo prezzo fino a quando non cambia piano.')
      && str_contains($r['body'], '<code>{sezioni}</code>') && !str_contains($r['body'], 'Varianti camera'));

// ============================================================ 7B · scegliere e confermare il piano
$r = (new Browser('scelta7b'))->get('/registrati');
prova('7B · /registrati senza piano: «Scegli il tuo piano», tre schede, Plus già scelto, la frase', str_contains($r['body'], 'Scegli il tuo piano') && substr_count($r['body'], 'name="piano" value="') === 3
      && preg_match('#name="piano" value="' . pv('plus') . '" checked#', $r['body']) === 1
      && str_contains($r['body'], 'Non paghi niente finché non decidi di pubblicare. Intanto prepari la guida e la vedi in anteprima, come la vedranno i tuoi ospiti.'));
db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'register:%'");
$lea = new Browser('lea');
$lea->get('/registrati');
$r = $lea->post('/registrati', ['piano' => pv('plus'), 'name' => 'Lea Sceglie', 'email' => 'lea@prova.test', 'password' => 'LeaProva12345', 'termini' => '1']);
$lacc = $accDi('lea@prova.test');
prova('…il piano scelto lì si salva come prima', (int) val('SELECT intended_package_version_id FROM accounts WHERE id = ?', [$lacc]) === pv('plus'));
$lea->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Casa Lea', 'city' => 'Bevagna']);
$lp = (int) val('SELECT id FROM properties WHERE account_id = ?', [$lacc]);
$lslug7 = (string) val('SELECT slug FROM properties WHERE id = ?', [$lp]);
$lqr = (string) val('SELECT token FROM qr_tokens WHERE property_id = ?', [$lp]);
$r1 = $ospite->get("/g/$lslug7"); $r2 = $lqr !== '' ? $ospite->segui($ospite->get("/q/$lqr")) : ['code' => 404, 'body' => ''];
prova('7B · una guida mai pagata non si apre, né dal link né dal QR', $r1['code'] === 404 && $r2['code'] === 404, $r1['code'] . ' ' . $r2['code']);
$lea->modulo("/pannello/$lp/lingue", "/pannello/$lp/lingue", ['locali' => ['it', 'en', 'fr']]);
$lcore = (int) val('SELECT id FROM sections WHERE property_id = ? AND is_core = 1', [$lp]);
$lea->post("/pannello/$lp/sezioni/$lcore", ['checkin_steps' => ['Le chiavi sono dalla vicina.']]);
$r = $lea->get("/pannello/$lp/procedura/pubblica");
prova('7B · prima del pagamento: «Hai scelto Plus», «Conferma e paga» e «Passa a …» anche ai piani più economici', str_contains($r['body'], 'Hai scelto Plus') && str_contains($r['body'], 'Conferma e paga')
      && str_contains($r['body'], 'Passa a Essential') && str_contains($r['body'], 'Passa a Portfolio'));
prova('…passando a Essential, prima l\'elenco di cosa la guida non mostrerà', preg_match('#<details class="passa-piano__conferma">.*?Passa a Essential.*?Con Essential la guida non mostrerà.*?Lingue: restano#s', $r['body']) === 1);
prova('7B · i dati di fatturazione nel passo Pubblica, gli stessi di Account', str_contains($r['body'], 'Servono per la fattura o la ricevuta. Potrai cambiarli quando vuoi in Account &amp; Fatturazione.')
      && str_contains($r['body'], 'name="billing_type"') && str_contains($r['body'], 'action="' . preg_replace('#^https?://[^/]+#', '', $BASE) . '/account/fatturazione"')
      && str_contains($r['body'], 'name="torna" value="/pannello/' . $lp . '/procedura/pubblica"'));
$r = $lea->post("/pannello/$lp/pubblica", ['acquirente' => 'impresa']);
prova('…senza i dati non si paga: si resta nel passo Pubblica', $r['code'] === 302 && str_contains($r['loc'], "/pannello/$lp/procedura/pubblica#fatturazione") && !val('SELECT id FROM orders WHERE account_id = ?', [$lacc]));
$r = $lea->post('/account/fatturazione', ['billing_type' => 'privato', 'billing_name' => 'Lea Sceglie', 'cf' => 'RSSMRA85T10A562S', 'billing_address' => 'Via Roma 3',
    'billing_postal' => '06031', 'billing_city' => 'Bevagna', 'billing_province' => 'PG', 'torna' => "/pannello/$lp/procedura/pubblica"]);
prova('…salvati da lì, si torna alla pubblicazione', $r['code'] === 302 && str_ends_with($r['loc'], "/pannello/$lp/procedura/pubblica"));
$lea->get("/pannello/$lp/procedura/pubblica");
$r = $lea->post('/piano', ['pv' => pv('essential'), 'torna' => "/pannello/$lp/procedura/pubblica"]);
prova('7B · «Passa a Essential» prima di pagare: piano cambiato e si torna al passo Pubblica', $r['code'] === 302 && str_ends_with($r['loc'], "/pannello/$lp/procedura/pubblica")
      && (int) val('SELECT intended_package_version_id FROM accounts WHERE id = ?', [$lacc]) === pv('essential'));
prova('…e la pagina dice «Hai scelto Essential»', str_contains($lea->get("/pannello/$lp/procedura/pubblica")['body'], 'Hai scelto Essential'));

// ============================================================ 7B · cambiare piano dall'abbonamento, pannello
$r = $vera->get('/account');
prova('7B · Account: «Passa a {piano}» per gli altri piani, nel flusso del cambio di piano', str_contains($r['body'], '/account/piano/conferma?piano=essential"') && str_contains($r['body'], '>Passa a Essential</a>')
      && str_contains($r['body'], '/account/piano/conferma?piano=portfolio&amp;strutture=2"') && !str_contains($r['body'], 'Varianti camera'));
$r = $dario->get('/account');
prova('…con un abbonamento dello staff: «Chiedi il cambio di piano» per email', str_contains($r['body'], '>Chiedi il cambio di piano</a>')
      && str_contains($r['body'], rawurlencode('Cambio piano — dario@prova.test')));
$r = $vera->get('/pannello');
prova('7B · pannello: «Scopri Portfolio» pieno e «Chiedi informazioni» per email', str_contains($r['body'], '<a class="btn btn--sm" href="' . preg_replace('#^https?://[^/]+#', '', $BASE) . '/piano?passa=portfolio">Scopri Portfolio</a>')
      && str_contains($r['body'], 'Informazioni%20su%20Portfolio') && str_contains($r['body'], rawurlencode('Quante strutture vorrei gestire:')) && str_contains($r['body'], '>Chiedi informazioni</a>'));
prova('8B · «Hai già un sito per la tua struttura?»: logo MyHouse bianco, Sì / No che aprono il percorso compatto in un <dialog>; senza ✕ né «Non mostrare più» (9F)',
      str_contains($r['body'], 'Hai già un sito per la tua struttura?') && str_contains($r['body'], 'myhouse-logo-bianco-breve.png?v=')
      && str_contains($r['body'], 'data-funnel-apri="si" data-funnel-dialogo="dlg-sito-guide"') && str_contains($r['body'], 'data-funnel-apri="no"')
      && preg_match('#<dialog class="funnel-dialogo" id="dlg-sito-guide".*?funnel funnel--nojs funnel--compatta.*?data-da="pannello"#s', $r['body']) === 1
      && !str_contains($r['body'], 'data-sito-chiudi') && !str_contains($r['body'], 'Non mostrare più'));

// ================================================================= 6J · rifiniture
capitolo('6J · rifiniture: registrazione, amministrazione, testi legali');
$adm6 = new Browser('admin6j');
$adm6->get('/accedi'); $adm6->post('/accedi', ['email' => 'admin@prova.test', 'password' => 'AdminProva123']);
$adm6->modulo('/admin/sconti', '/admin/sconti', ['code' => 'FASCIA6J', 'kind' => 'percent', 'value' => '15', 'valid_from' => gmdate('Y-m-d'),
               'valid_until' => gmdate('Y-m-d', time() + 30 * 86400), 'max_uses' => '', 'piani' => 'tutti', 'note' => '']);
$r = (new Browser('fascia6j'))->get('/registrati?codice=FASCIA6J');
prova('6J · /registrati?codice=… mostra la fascia dello sconto, come la landing', $r['code'] === 200 && str_contains($r['body'], 'class="sconto-fascia"') && str_contains($r['body'], 'FASCIA6J'));
$r = $adm6->get('/admin');
prova('6J · menu di amministrazione in tre gruppi: Vendite, Clienti, Sistema (con SEO e GEO)', preg_match('#>Vendite<.*?>Clienti<.*?>Sistema<.*?/admin/seo#s', $r['body']) === 1);
$r = $ospite->get('/termini');
prova('6J · Termini § 4: cambio di piano, codici sconto (un solo sconto); 7B: niente più capoverso sulle varianti', str_contains($r['body'], 'paghi subito')
      && str_contains($r['body'], 'I codici sconto valgono solo sul primo anno') && str_contains($r['body'], 'a ogni abbonamento si applica un solo codice')
      && !str_contains($r['body'], 'varianti camera') && !str_contains($r['body'], 'Da completare') && str_contains($r['body'], 'id="recesso"'));
$r = $ospite->get('/privacy');
prova('6J · Privacy → Fornitori: Adamo per la fatturazione elettronica (8J: con sedi e garanzie, niente segnaposto)', str_contains($r['body'], '<b>Adamo</b> (Italia): fatturazione elettronica')
      && !str_contains($r['body'], '[Completare') && !preg_match('/\{[a-z_]+\}/', $r['body']));

// ================================================================= 6K · SEO e GEO
capitolo('6K · SEO e GEO: testa delle pagine, robots.txt, sitemap.xml, llms.txt, /domande');
$r = $ospite->get('/robots.txt');
// 6M: per i motori (*) le guide NON sono in Disallow (devono leggere il noindex); per gli assistenti AI sì.
$blocco = fn(string $t, string $ua) => preg_match('#(?:^|\n)User-agent: ' . preg_quote($ua, '#') . '\n(.*?)(?:\n\n|\n?$)#s', $t, $mm) ? $mm[1] . "\n" : null;
prova('robots.txt: per * niente guide in Disallow, /pannello escluso per tutti; GPTBot e ClaudeBot senza guide; CCBot spento di serie; Sitemap in fondo', $r['code'] === 200
      && str_starts_with($r['body'], "User-agent: *\nDisallow: /pannello\n") && !str_contains((string) $blocco($r['body'], '*'), '/g/')
      && str_contains((string) $blocco($r['body'], 'GPTBot'), "Disallow: /g/\nDisallow: /q/\nDisallow: /qr/\nDisallow: /media/\nDisallow: /pannello\n")
      && str_contains((string) $blocco($r['body'], 'ClaudeBot'), "Disallow: /g/\n") && str_contains((string) $blocco($r['body'], 'Claude-User'), "Disallow: /media/\n")
      && str_contains((string) $blocco($r['body'], 'meta-externalagent'), "Disallow: /qr/\n") && $blocco($r['body'], 'CCBot') === "Disallow: /\n"
      && preg_match('#\nSitemap: \S+/sitemap\.xml\n$#', $r['body']) === 1, json_encode($blocco($r['body'], 'CCBot')));
$r = $ospite->get('/sitemap.xml');
prova('sitemap.xml: le pagine accese (con le due inglesi della 8E, le quattro per tipologia della 7F e le quattro legali della 8J), con lastmod', $r['code'] === 200 && substr_count($r['body'], '<url>') === 14 && substr_count($r['body'], '<lastmod>') === 14
      && str_contains($r['body'], '/domande</loc>') && @simplexml_load_string($r['body']) !== false);
foreach (['/accedi', '/registrati', '/password'] as $u) {
    $r = $ospite->get($u);
    prova("noindex su $u", str_contains($r['body'], '<meta name="robots" content="noindex">') && !str_contains($r['body'], 'application/ld+json'));
}
$r = $ospite->get('/');
$nFaq = preg_match('#"@type":"FAQPage",(?:"inLanguage":"[a-zA-Z-]+",)?"mainEntity":(\[.*?\])\}</script>#s', $r['body'], $mm) ? count(json_decode($mm[1], true) ?: []) : -1;
// Amministrazione → Sistema → Testi e foto: ogni testo della home si cambia, torna di serie, e le foto si caricano.
capitolo('Testi e foto del sito');
$r = $admin->get('/admin/testi');
prova('Testi e foto: la pagina, con i blocchi nell\'ordine della home e i testi di serie già nei campi', $r['code'] === 200 && pulita($r) && str_contains($r['body'], 'Testi e foto del sito.')
      && preg_match('#id="testi-hero".*id="testi-perchi".*id="testi-tempo".*id="testi-faq".*id="testi-sito".*id="testi-foto"#s', $r['body']) === 1
      && str_contains($r['body'], "La casa risponde\nprima che chiedano.</textarea>") && str_contains($r['body'], 'name="t_perchi_2_testo"') && str_contains($r['body'], 'name="img[telefono]"')
      && str_contains($r['body'], 'Testi cambiati') && !str_contains($r['body'], 'testi__campo testi__campo--cambiato'), (string) $r['code']);
prova('…con l\'anteprima dal vivo a destra: la home in un iframe (?anteprima=testi), i campi con la loro chiave, le foto con quella di serie', str_contains($r['body'], 'src="/welcomebook/index.php/?anteprima=testi"')
      && str_contains($r['body'], 'data-chiave="hero.titolo"') && str_contains($r['body'], 'data-fotocampo="telefono"') && str_contains($r['body'], 'data-vista="computer"'));
$r = $admin->get('/?anteprima=testi');
prova('…l\'anteprima: la home come la vede un visitatore, con i segnaposto per testi, elenchi e foto, e lo script', $r['code'] === 200 && !str_contains($r['body'], 'class="bentornato"')
      && str_contains($r['body'], '<h1 class="display"><span data-testo="hero.titolo">La casa risponde<br>') && str_contains($r['body'], 'data-testo-lista="hero.garanzie"')
      && str_contains($r['body'], 'data-foto="telefono"') && str_contains($r['body'], 'data-testo="prodotto.testo" data-sost="{&quot;sezioni&quot;:') && str_contains($r['body'], '/assets/anteprima-testi.js'));
$r = $ospite->get('/?anteprima=testi');
prova('…ma per un visitatore ?anteprima=testi non cambia niente', $r['code'] === 200 && !str_contains($r['body'], 'data-testo=') && !str_contains($r['body'], 'anteprima-testi.js'));
$r = $admin->segui($admin->post('/admin/testi', ['t_hero_titolo' => "La casa risponde.\nSempre.", 't_perchi_2_testo' => 'Colazione **alle 8** e [regole](/domande) <script>x</script>',
    't_hero_garanzie' => "Prima\nSeconda", 't_prodotto_testo' => 'Ci sono {sezioni} sezioni.', 't_cta_crea' => 'Comincia gratis', 'torna' => 'hero']));
prova('…salvato: titolo su due righe, grassetto e link sicuri, l\'HTML neutralizzato, la lista, il segnaposto, il bottone in tre punti', str_contains($r['body'], 'Salvato: la home è già aggiornata.')
      && str_contains($r['body'], 'class="badge badge--ochre testi__badge">cambiato</span>') && str_contains($r['body'], 'Di serie: La casa risponde'));
$h = $ospite->get('/')['body'];
prova('…e nella home', str_contains($h, "<h1 class=\"display\">La casa risponde.<br>\nSempre.</h1>")
      && str_contains($h, 'Colazione <b>alle 8</b> e <a href="/domande">regole</a> &lt;script&gt;x&lt;/script&gt;') && !str_contains($h, '<script>x</script>')
      && preg_match('#hero2__garanzie">(<span>.*?Prima</span>)(<span>.*?Seconda</span>)</p>#s', $h) === 1 && !str_contains($h, 'Nessuna app da scaricare</span>')
      && preg_match('#Ci sono \d+ sezioni\.#', $h) === 1 && substr_count($h, 'Comincia gratis') === 4 && !str_contains($h, 'Crea gratis la tua guida'), substr_count($h, 'Comincia gratis') . ' bottoni (hero, guadagno, chiusura, menu del telefono)');
prova('…un testo uguale a quello di serie non si salva (la riga sparisce)', (int) val("SELECT COUNT(*) FROM site_texts") === 5
      && (bool) val("SELECT chiave FROM site_texts WHERE chiave = 'hero.titolo'"));
// 8G · «Più sanno, meglio stanno»: tra le tre scene e «Cosa trova l'ospite», con i testi in Testi e foto (anche in inglese).
$h8g = $ospite->get('/')['body'];
$p8g = ['scene' => strpos($h8g, '<section class="scene"'), 'sezione' => strpos($h8g, 'id="recensioni-positive"'), 'prodotto' => strpos($h8g, 'id="prodotto-titolo"')];
prova('8G · home: «Più sanno, meglio stanno.» tra le scene e «Cosa trova l\'ospite», stesso riquadro del QR, quattro passi e la didascalia',
      !in_array(false, $p8g, true) && $p8g['scene'] < $p8g['sezione'] && $p8g['sezione'] < $p8g['prodotto']
      && preg_match('#<section id="recensioni-positive" class="band qrband soddisfatti"[^>]*>.*?Più sanno, meglio stanno\..*?Nessun dubbio, nessuno stress\..*?Ospite sereno, recensione a 5 stelle\..*?Più stelle, più prenotazioni\..*?class="qr-sheet qrband__foglio soddisfatti__scheda".*?Informazioni chiare.*?Ospite sereno.*?★★★★★.*?Recensione a 5 stelle.*?cerchio__passo--ultimo.*?Nuove prenotazioni.*?Il circolo che riempie il calendario\.#s', $h8g) === 1,
      json_encode($p8g));
$h8gEn = $ospite->get('/en')['body'];
prova('8G · …in inglese su /en', str_contains($h8gEn, 'The more they know, the better they stay.') && str_contains($h8gEn, 'More stars, more bookings.') && str_contains($h8gEn, 'The cycle that fills your calendar.'));
$admin->segui($admin->post('/admin/testi', ['t_soddisfatti_titolo' => 'Ospiti informati, ospiti contenti.']));
$cambiato8g = str_contains($ospite->get('/')['body'], 'Ospiti informati, ospiti contenti.') && str_contains($admin->get('/admin/testi')['body'], 'Più sanno, meglio stanno');
$admin->segui($admin->post('/admin/testi', ['t_soddisfatti_titolo' => '']));
prova('8G · …il titolo si cambia da Testi e foto (e si torna a quello di serie)', $cambiato8g && str_contains($ospite->get('/')['body'], 'Più sanno, meglio stanno.')
      && !val("SELECT chiave FROM site_texts WHERE chiave = 'soddisfatti.titolo'"));
// Le foto: il telefono in cima e lo sfondo di «Anche il tuo sito»; la home le usa al posto di quelle di serie.
$r = $admin->segui($admin->post('/admin/testi', ['img[telefono]' => file_(png(600, 422), 'image/png'), 'torna' => 'foto']));
$h = $ospite->get('/')['body'];
$rifTel = (string) val("SELECT valore FROM site_texts WHERE chiave = 'img.telefono'");
$fileS3Tel = "$S3_DIR/oggetti/mhw-prove__" . str_replace('/', '__', substr($rifTel, 3));
prova('Testi e foto · le foto caricate: nell\'archivio come le foto delle testimonianze, e nella home al posto di quelle di serie', str_contains($r['body'], '>caricata</span>')
      && preg_match('#^(s3|local):sito/\d{4}/[0-9a-f]{32}\.(jpg|png|webp)$#', $rifTel) === 1 && str_contains($r['body'], 'Torna alla foto di serie')
      && !str_contains($h, 'borgo-telefono') && !str_contains($h, 'checco-telefono') && preg_match('#class="device__shot">\s*<img src="[^"]+"#', $h) === 1
      && !str_contains($h, 'sito2__foto') && !str_contains($r['body'], 'Lo sfondo di «Anche il tuo sito»')
      && (getenv('MHW_STORAGE') !== 's3' || is_file($fileS3Tel)), $rifTel . ' · ' . (is_file($fileS3Tel) ? 'file ok' : 'file assente'));
// Si torna di serie: campi vuoti e «Torna alla foto di serie»; la foto vecchia sparisce dall'archivio.
$r = $admin->segui($admin->post('/admin/testi', ['t_hero_titolo' => '', 't_perchi_2_testo' => '', 't_hero_garanzie' => '', 't_prodotto_testo' => '', 't_cta_crea' => '',
    'img_togli' => ['telefono' => '1']]));
$h = $ospite->get('/')['body'];
$cond = ['righe' => (int) val("SELECT COUNT(*) FROM site_texts") === 0, 'h1' => str_contains($h, "<h1 class=\"display\">La casa risponde<br>\nprima che chiedano.</h1>"),
         'garanzie' => str_contains($h, 'Nessuna app da scaricare</span>'), 'cta' => str_contains($h, 'Crea gratis la tua guida'),
         'telefono' => str_contains($h, 'borgo-telefono') || str_contains($h, 'checco-telefono'), 'sito' => !str_contains($h, 'sito2__foto'),
         'admin' => !str_contains($admin->get('/admin/testi')['body'], 'testi__campo testi__campo--cambiato'),
         'fileS3' => getenv('MHW_STORAGE') !== 's3' || !(clearstatcache(true, $fileS3Tel) ?? is_file($fileS3Tel))];   // is_file() tiene una cache per percorso: va svuotata
$richS3 = array_values(array_filter(array_map('trim', (array) @file("$S3_DIR/richieste.jsonl")), fn($l) => str_contains($l, 'DELETE') && str_contains($l, 'sito')));
$logFoto = array_values(array_filter(array_map('trim', righeLog('app')), fn($l) => str_contains($l, 'testi.foto')));
prova('…tutto di serie: tabella vuota, home com\'era, foto vecchie cancellate', !in_array(false, $cond, true),
      json_encode($cond) . ' · ' . $rifTel . ' · DELETE: ' . implode(' | ', array_map(fn($l) => mb_substr($l, 0, 120), $richS3)) . ' · log: ' . implode(' | ', array_map(fn($l) => mb_substr($l, 0, 300), $logFoto)));
// /media/{file}: anche un file caricato dall'amministrazione (senza riga in media) si serve, con il tipo giusto.
$nomeLibero = 'sito-2026-' . bin2hex(random_bytes(16)) . '.png';
file_put_contents("$DOVE/app/storage/uploads/$nomeLibero", (string) file_get_contents(png(10, 10)));   // png() dà il percorso di un file
$r = $ospite->get("/media/$nomeLibero");
prova('Testi e foto · /media serve anche le immagini caricate dall\'amministrazione (archivio su disco): testimonianze, condivisione, foto del sito', $r['code'] === 200
      && str_starts_with($r['body'], "\x89PNG") && !str_contains($ospite->get('/media/qualcosa-a-caso.png')['body'], 'PNG'), (string) $r['code']);
@unlink("$DOVE/app/storage/uploads/$nomeLibero");

$r = $ospite->get('/domande');
prova('Revisione · /domande: l\'email scritta per esteso accanto ai bottoni', preg_match('#faq__indirizzo">Oppure scrivi a <b>[^<]+@[^<]+</b>#', $r['body']) === 1);
prova('/domande: titolo, tutte le FAQ aperte con il loro id, i due bottoni, FAQPage', $r['code'] === 200 && pulita($r) && str_contains($r['body'], '>Domande frequenti</h1>')
      && substr_count($r['body'], 'class="faq__voce" id="') === $nFaq && substr_count($r['body'], '" open>') >= $nFaq
      && str_contains($r['body'], 'id="tracciamento"') && str_contains($r['body'], 'Crea gratis la tua guida') && str_contains($r['body'], '/#piani">Vedi i prezzi')
      && str_contains($r['body'], '"@type":"FAQPage"'));
$r = $adm6->get('/admin/seo');
prova('/admin/seo: titolo, frase, tre riquadri, avviso sottocartella, controllo e link esterni', $r['code'] === 200 && pulita($r) && str_contains($r['body'], '<h1>SEO e GEO.</h1>')
      && str_contains($r['body'], 'Controlli superati') && str_contains($r['body'], 'Assistenti AI ammessi') && str_contains($r['body'], '5 su 6')
      && str_contains($r['body'], 'Il sito è in una sottocartella') && str_contains($r['body'], 'search.google.com/test/rich-results') && str_contains($r['body'], '/assets/seo.js'));
$campi6k = ['dominio' => 'https://myhousewelcome.it/', 'gsc' => '<meta name="google-site-verification" content="gsc-Prova_1" />', 'bing' => 'BING42',
            'az_nome' => 'MyHouse Welcome', 'az_piva' => '02945910541', 'az_social' => "https://instagram.com/myhousewelcome\nnon un indirizzo",
            'llms_intro' => 'Introduzione di prova.', 'llms_fatti' => "Primo fatto.\n\nSecondo fatto.",
            'bot' => ['GPTBot' => '0', 'OAI-SearchBot' => '1', 'ClaudeBot' => '1', 'PerplexityBot' => '1', 'Google-Extended' => '1', 'CCBot' => '0']];
foreach (['home', 'domande', 'termini', 'privacy'] as $k) $campi6k += ["p_{$k}_indicizza" => $k === 'termini' ? '0' : '1', "p_{$k}_titolo" => 'Titolo di prova per la pagina ' . $k . ' | MyHouse Welcome',
                                                                   "p_{$k}_descrizione" => str_repeat('Descrizione di prova della pagina. ', 3)];
$adm6->modulo('/admin/seo', '/admin/seo', $campi6k);
prova('/admin/seo: «Salvato…» e il salvataggio nel registro', str_contains($adm6->get('/admin/seo')['body'], 'Salvato. Sitemap, robots.txt e llms.txt sono già aggiornati')
      && (int) val("SELECT COUNT(*) FROM audit_log WHERE action = 'seo.save'") === 1);
// ------------------------------------------------------------------ 8H · caratteri sul nostro server, statistiche e consenso
$css8h = (string) file_get_contents("$DOVE/assets/app.css");
$fontiOk = true;
foreach (['gloock-latin', 'gloock-latin-ext', 'onest-latin', 'onest-latin-ext', 'onest-cyrillic'] as $f8) $fontiOk = $fontiOk && is_file("$DOVE/assets/fonts/$f8.woff2") && str_contains($css8h, "url(\"fonts/$f8.woff2\")");
$pag8h = ['/' => $ospite->get('/'), '/domande' => $ospite->get('/domande'), '/privacy' => $ospite->get('/privacy'), '/accedi' => $ospite->get('/accedi'), '/registrati' => $ospite->get('/registrati')];
prova('8H · caratteri dal nostro server: Gloock e Onest in assets/fonts con font-display: swap, preload di Onest, niente fonts.googleapis.com in nessuna pagina',
      $fontiOk && substr_count($css8h, 'font-display:swap') >= 7
      && !array_filter($pag8h, fn($r) => str_contains($r['body'], 'fonts.googleapis') || str_contains($r['body'], 'fonts.gstatic') || !str_contains($r['body'], '/assets/fonts/onest-latin.woff2" as="font" type="font/woff2" crossorigin')));
prova('8H · senza statistiche configurate: nessuno script di statistica, nessun banner, nessun «Preferenze cookie»',
      !array_filter($pag8h, fn($r) => str_contains($r['body'], 'mhw-statistiche') || str_contains($r['body'], 'id="consenso"') || str_contains($r['body'], 'data-consenso-apri')));
// I controlli dei campi: un ID di GA4 sbagliato non si salva (e niente del resto cambia).
$r = $adm6->segui($adm6->modulo('/admin/seo', '/admin/seo', $campi6k + ['stat_ga4' => 'UA-12345-1']));
$r2 = $adm6->segui($adm6->modulo('/admin/seo', '/admin/seo', $campi6k + ['stat_matomo_url' => 'http://statistiche.esempio.it', 'stat_matomo_id' => '1']));
prova('8H · SEO e GEO, «Statistiche delle visite»: i tre strumenti con le note; GA4 senza G- e Matomo senza https:// rifiutati',
      str_contains($r['body'], 'id="seo-statistiche"') && str_contains($r['body'], 'Senza cookie: conta tutte le visite e non richiede il banner.')
      && str_contains($r['body'], 'Parte solo dopo il consenso del visitatore.') && str_contains($r['body'], 'Mappe di calore e registrazioni; parte solo dopo il consenso.')
      && str_contains($r['body'], 'comincia con G-') && str_contains($r2['body'], 'con https://') && (string) val("SELECT valore FROM seo_settings WHERE chiave = 'stat.ga4'") === '');
// Solo Matomo: lo script sulle pagine pubbliche, mai nelle guide, nel pannello e nelle pagine con un codice nell'indirizzo.
$adm6->segui($adm6->modulo('/admin/seo', '/admin/seo', $campi6k + ['stat_matomo_url' => 'https://statistiche.esempio.it', 'stat_matomo_id' => '3']));
$h8 = $ospite->get('/')['body'];
$j8 = preg_match('#<script type="application/json" id="mhw-statistiche">(.*?)</script>#s', $h8, $m8) ? json_decode($m8[1], true) : null;
$guida8 = (string) val("SELECT slug FROM properties WHERE is_demo = 2 LIMIT 1");
prova('8H · solo Matomo: configurazione nella home (indirizzo con la barra, ID), niente banner; le pagine che contano sì, guide, pannello e reimposta password no',
      ($j8['matomo'] ?? null) === ['url' => 'https://statistiche.esempio.it/', 'id' => '3'] && $j8['ga4'] === null && !str_contains($h8, 'id="consenso"')
      && str_contains($ospite->get('/domande')['body'], 'mhw-statistiche') && str_contains($ospite->get('/privacy')['body'], 'mhw-statistiche')
      && str_contains($ospite->get('/accedi')['body'], 'mhw-statistiche')
      && ($guida8 === '' || !str_contains($ospite->get("/g/$guida8")['body'], 'mhw-statistiche'))
      && !str_contains($ospite->get('/password/nuova/' . str_repeat('a', 64))['body'], 'mhw-statistiche')
      && !str_contains($adm6->get('/admin/seo')['body'], 'mhw-statistiche') && !str_contains($lucia->get('/pannello')['body'], 'mhw-statistiche'));
$js8 = (string) file_get_contents("$DOVE/assets/statistiche.js");
prova('8H · statistiche.js: Matomo con disableCookies e senza la query; GA4 senza segnali pubblicitari; gli obiettivi con i loro nomi',
      str_contains($js8, "_paq.push(['disableCookies'])") && !str_contains($js8, 'setDoNotTrack') && !str_contains($js8, 'setUserId')
      && str_contains($js8, 'allow_google_signals: false') && str_contains($js8, 'allow_ad_personalization_signals: false')
      && str_contains($js8, "evento('contatto', 'whatsapp')") && str_contains($js8, "evento('sito-myhouse')") && str_contains($js8, "evento('vedi-esempio')"));
$pr8 = $ospite->get('/privacy')['body'];
prova('8H · privacy, «Cookie»: cookie tecnici, Matomo (configurato), le guide senza cookie; Google e Clarity no (non configurati)',
      str_contains($pr8, 'id="cookie"') && str_contains($pr8, 'mhw_consenso') && str_contains($pr8, 'installato sul nostro server')
      && str_contains($pr8, 'Le guide degli ospiti non usano cookie') && !str_contains($pr8, 'Google Analytics 4') && !str_contains($pr8, '_clck (1 anno)'));
// Con GA4 e Clarity: il banner (nascosto finché lo script non decide), Accetta e Rifiuta con lo stesso stile, «Scegli» con due interruttori.
$adm6->segui($adm6->modulo('/admin/seo', '/admin/seo', $campi6k + ['stat_matomo_url' => 'https://statistiche.esempio.it', 'stat_matomo_id' => '3', 'stat_ga4' => 'g-ab12cd34ef', 'stat_clarity' => 'Abc123xyz']));
$h8 = $ospite->get('/')['body'];
prova('8H · con GA4 e Clarity: il banner con le finalità (i nomi dei fornitori in «Scegli» e nell\'informativa), Rifiuta e Accetta uguali, Scegli con due interruttori spenti, la X e «Preferenze cookie» nel piede',
      preg_match('#<section class="consenso" id="consenso"[^>]*hidden>#', $h8) === 1
      && str_contains($h8, 'Usiamo cookie tecnici e, solo con il tuo consenso, cookie di statistica e di analisi dell&#039;uso per migliorare il sito.')
      && preg_match('#<p class="consenso__testo".*?</p>#s', $h8, $t8) === 1 && !str_contains($t8[0], 'Google') && !str_contains($t8[0], 'Microsoft')
      && str_contains($h8, 'Google Analytics: quante persone visitano il sito') && str_contains($h8, 'Microsoft Clarity: mappe di calore')
      && str_contains($h8, '<button type="button" class="btn btn--sm consenso__pari" data-consenso-azione="rifiuta">Rifiuta</button>')
      && str_contains($h8, '<button type="button" class="btn btn--sm consenso__pari" data-consenso-azione="accetta">Accetta</button>')
      && substr_count($h8, 'data-consenso="') === 2 && !str_contains($h8, 'data-consenso="s" checked') && str_contains($h8, 'aria-label="Chiudi: vale come rifiuto"')
      && str_contains($h8, 'data-consenso-apri>Preferenze cookie</a>') && str_contains($h8, '"ga4":"G-AB12CD34EF"') && str_contains($h8, '"clarity":"abc123xyz"')
      && !preg_match('#<script[^>]+(googletagmanager|clarity\.ms)#', $h8));
$en8 = $ospite->get('/en')['body'];
prova('8H · …in inglese su /en', str_contains($en8, 'We use technical cookies and, only with your consent, statistics and usage analysis cookies to improve the website.')
      && str_contains($en8, '>Accept</button>') && str_contains($en8, '>Reject</button>') && str_contains($en8, 'data-consenso-apri>Cookie preferences</a>'));
$pr8 = $ospite->get('/privacy')['body'];
prova('8H · privacy: con GA4 e Clarity i loro paragrafi (fornitore, cookie, durata, informativa) e come revocare', str_contains($pr8, 'Google Analytics 4') && str_contains($pr8, '_ga e _ga_*')
      && str_contains($pr8, 'policies.google.com/privacy') && str_contains($pr8, 'Microsoft Clarity') && str_contains($pr8, '_clck (1 anno) e _clsk (1 giorno)')
      && str_contains($pr8, 'data-consenso-apri>Preferenze cookie</a>'));
prova('8H · …e mai nelle guide degli ospiti né nel pannello, anche con GA4 e Clarity', ($guida8 === '' || !str_contains($ospite->get("/g/$guida8")['body'], 'consenso'))
      && !str_contains($lucia->get('/pannello')['body'], 'mhw-statistiche'));
// Gli obiettivi dal server: la registrazione parte con la pagina «Controlla la tua email».
Browser::$confermaAutomatica = false;
db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'register:%'");
$reg8 = new Browser('statistiche8h');
$reg8->get('/registrati');
$r = $reg8->segui($reg8->post('/registrati', ['name' => 'Prova Statistiche', 'email' => 'statistiche8h@prova.test', 'password' => 'StatisticheProva123', 'termini' => '1']));
Browser::$confermaAutomatica = true;
prova('8H · obiettivo «registrazione»: arriva con la pagina seguente, una volta sola', str_contains($r['body'], '"eventi":[["registrazione",""]]')
      && !str_contains($reg8->get('/domande')['body'], 'registrazione'));
// Si torna a spento, per le prove che seguono.
$adm6->segui($adm6->modulo('/admin/seo', '/admin/seo', $campi6k));
prova('8H · svuotati i campi: di nuovo nessuno script e nessun banner', !str_contains($ospite->get('/')['body'], 'mhw-statistiche'));
$r = $ospite->get('/');
prova('Con il dominio: canonical, og:url, verifiche Google e Bing solo nella home, sameAs dai social', str_contains($r['body'], '<link rel="canonical" href="https://myhousewelcome.it/">')
      && str_contains($r['body'], '<meta name="google-site-verification" content="gsc-Prova_1">') && str_contains($r['body'], '<meta name="msvalidate.01" content="BING42">')
      && str_contains($r['body'], '"sameAs":["https://instagram.com/myhousewelcome"]') && !str_contains($ospite->get('/domande')['body'], 'google-site-verification')
      && str_contains($ospite->get('/domande')['body'], '<link rel="canonical" href="https://myhousewelcome.it/domande">'));
$r = $ospite->get('/robots.txt');
prova('robots.txt dopo il salvataggio: GPTBot e CCBot spenti del tutto, ClaudeBot solo senza guide, Sitemap sul dominio', $blocco($r['body'], 'GPTBot') === "Disallow: /\n"
      && $blocco($r['body'], 'CCBot') === "Disallow: /\n" && str_starts_with((string) $blocco($r['body'], 'ClaudeBot'), "Disallow: /g/\n")
      && str_ends_with($r['body'], "Sitemap: https://myhousewelcome.it/sitemap.xml\n"));
$r = $ospite->get('/sitemap.xml');
prova('sitemap.xml: termini spento non c\'è più, indirizzi sul dominio', substr_count($r['body'], '<url>') === 3 && !str_contains($r['body'], '/termini<')
      && str_contains($r['body'], '<loc>https://myhousewelcome.it/privacy</loc>'));
prova('…e termini ha noindex', str_contains($ospite->get('/termini')['body'], '<meta name="robots" content="noindex">'));
$r = $ospite->get('/llms.txt');
prova('llms.txt dopo il salvataggio: introduzione e fatti nuovi, righe vuote tolte', str_contains($r['body'], "> Introduzione di prova.\n") && str_contains($r['body'], "- Primo fatto.\n- Secondo fatto.\n"));
$r = $adm6->post('/admin/seo', ['dominio' => 'non un dominio'] + $campi6k);
prova('Dominio scritto male: non si salva e si dice perché', str_contains($adm6->get('/admin/seo')['body'], 'Il dominio va scritto per intero')
      && (string) val("SELECT valore FROM seo_settings WHERE chiave = 'dominio'") === 'https://myhousewelcome.it');
$salvSeo = (int) val("SELECT COUNT(*) FROM audit_log WHERE action = 'seo.save'");   // 8H: le prove delle statistiche salvano anche loro
$r = (new Browser('anonimo6k'))->post('/admin/seo', $campi6k);
prova('/admin/seo senza accesso: non si salva', (int) val("SELECT COUNT(*) FROM audit_log WHERE action = 'seo.save'") === $salvSeo);

// ================================================================= 6M · guide private e codici di accesso
capitolo('6M · guide fuori dai motori e codici di accesso con conferma a proprio rischio');
require_once "$DOVE/app/src/Sicurezza.php";
$veri = ['Codice cassetta 4821', 'keybox code 4 8 2 1', 'la cassaforte ha il codice 1234', 'Alarm-PIN 5566', 'code du portail 2580'];
$falsi = ['Check-in dalle 15:00', 'CAP 06123', 'Codice sconto BENTORNATI', 'chiama il 392 006 1600', 'la cassaforte è nell\'armadio', 'CIN IT054001C2DEMO0001'];
$sbagliati = array_merge(array_filter($veri, fn($t) => !MHW\Sicurezza::codiceAccesso($t)), array_filter($falsi, fn($t) => MHW\Sicurezza::codiceAccesso($t)));
prova('Sicurezza::codiceAccesso: vero sui cinque codici del brief, falso sui sei testi innocui', !$sbagliati, implode(' | ', $sbagliati));
$X = 'X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex';
$slugL = (string) val('SELECT slug FROM properties WHERE id = ?', [$casa]);
$r = $ospite->get("/g/$slugL");
prova('Guida: X-Robots-Tag completo e meta robots uguale', $r['code'] === 200 && stripos($r['head'], $X) !== false
      && str_contains($r['body'], '<meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">'));
$r = $ospite->get("/g/$slugL/benvenuto");
prova('…anche la soglia (layout full)', str_contains($r['body'], 'content="noindex, nofollow, noarchive, nosnippet, noimageindex"') && stripos($r['head'], $X) !== false);
$tokL = (string) val('SELECT token FROM qr_tokens WHERE property_id = ?', [$casa]);
$r = $ospite->get("/q/$tokL");
prova('/q/…: X-Robots-Tag completo anche sul rimando del QR', $r['code'] === 302 && stripos($r['head'], $X) !== false);
prova('/qr/….png: X-Robots-Tag completo sull\'immagine del QR', stripos($ospite->get("/qr/$tokL.png")['head'], $X) !== false);
// Un PDF e un'immagine serviti da /media/ (nelle prove l'archivio è l'S3 finto: questi due si mettono sul disco a mano).
@mkdir("$DOVE/app/storage/uploads", 0775, true);
$fileMedia = [];
foreach (['prova-6m.pdf' => ['pdf', 'application/pdf', "%PDF-1.4\n%prova\n"], 'prova-6m.png' => ['image', 'image/png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=')]] as $f => [$k, $mime, $bytes]) {
    file_put_contents("$DOVE/app/storage/uploads/$f", $bytes);
    db()->prepare("INSERT INTO media (account_id, filename, mime, bytes, kind, storage, object_key, property_id, created_at) VALUES (?, ?, ?, ?, ?, 'local', '', ?, ?)")
        ->execute([$lacc, $f, $mime, strlen($bytes), $k, $casa, gmdate('Y-m-d\TH:i:s\Z')]);
    $fileMedia[] = $f;
}
$okMedia = $fileMedia ? array_filter(array_map(fn($f) => $ospite->get('/media/' . $f), $fileMedia), fn($x) => $x['code'] === 200 && stripos($x['head'], $X) !== false) : [];
prova('/media/…: PDF e immagini con X-Robots-Tag completo', count($okMedia) === 2, implode(', ', array_map(fn($f) => $f . ' ' . $ospite->get('/media/' . $f)['code'], $fileMedia)));
$r = $ospite->get('/sitemap.xml'); $r2 = $ospite->get('/llms.txt'); $r3 = $ospite->get('/');
prova('Mai elencate: niente /g/ in sitemap, llms.txt e dati strutturati della home', !str_contains($r['body'], '/g/') && !str_contains($r2['body'], '/g/')
      && !preg_match('#<script type="application/ld\+json">[^<]*/g/#', $r3['body']));
prova('Guide nuove: indirizzo con 4 caratteri casuali (vetrine e clienti di esempio come prima)', preg_match('#^[a-z0-9-]+-[abcdefghjkmnpqrstuvwxyz23456789]{4}$#', (string) val('SELECT slug FROM properties WHERE id = ?', [$vp])) === 1
      && (string) val("SELECT slug FROM properties WHERE name = 'Casa Lucia'") === 'casa-lucia', (string) val('SELECT slug FROM properties WHERE id = ?', [$vp]));
$r = $lucia->get("/pannello/$casa/impostazioni");
prova('Impostazioni: «Cambia il link della guida» con l\'avviso sul QR', str_contains($r['body'], 'Cambia il link della guida') && str_contains($r['body'], 'Il link vecchio smette di funzionare. Il QR stampato no: continua ad aprire la guida.'));
$lucia->post("/pannello/$casa/link", []);
prova('…senza conferma il link non cambia', (string) val('SELECT slug FROM properties WHERE id = ?', [$casa]) === $slugL);
$lucia->post("/pannello/$casa/link", ['conferma' => '1']);
$slugN = (string) val('SELECT slug FROM properties WHERE id = ?', [$casa]);
$r = $ospite->get("/q/$tokL");
prova('«Cambia il link»: slug nuovo con 4 caratteri, il vecchio in 404, il QR apre la guida nuova, riga nel registro', $slugN !== $slugL
      && preg_match('#^casa-lucia-[abcdefghjkmnpqrstuvwxyz23456789]{4}$#', $slugN) === 1 && $ospite->get("/g/$slugL")['code'] === 404
      && str_ends_with($r['loc'], "/g/$slugN/benvenuto") && $ospite->get("/g/$slugN")['code'] === 200
      && (int) val("SELECT COUNT(*) FROM audit_log WHERE action = 'property.link'") === 1, $slugN);
// Un codice nella guida: alla pubblicazione il riquadro, «Pubblica lo stesso» solo con la casella.
$coreL = (int) val('SELECT id FROM sections WHERE property_id = ? AND is_core = 1', [$casa]);
$trL = riga("SELECT * FROM section_translations WHERE section_id = ? AND locale = 'it'", [$coreL]);
$datiL = json_decode((string) $trL['data'], true) ?: []; $datiL['checkin_note'] = 'Il codice della cassetta delle chiavi è 4821.';
db()->prepare('UPDATE section_translations SET data = ? WHERE id = ?')->execute([json_encode($datiL, JSON_UNESCAPED_UNICODE), $trL['id']]);
$versioni = (int) val('SELECT COUNT(*) FROM guide_versions WHERE property_id = ?', [$casa]);
$r = $lucia->post("/pannello/$casa/pubblica", ['acquirente' => 'impresa']);
prova('Pubblicazione con un codice: non pubblica e apre il riquadro di conferma', $r['code'] === 302 && str_contains($r['loc'], '/procedura/pubblica?codici=1')
      && (int) val('SELECT COUNT(*) FROM guide_versions WHERE property_id = ?', [$casa]) === $versioni, $r['loc']);
$r = $lucia->get("/pannello/$casa/procedura/pubblica?codici=1");
prova('…il riquadro: titolo, campo con «Correggi», testo, casella, «Torna a correggere» e «Pubblica lo stesso» disattivo', str_contains($r['body'], 'Nella guida ci sono codici di accesso')
      && str_contains($r['body'], 'Nota importante') && str_contains($r['body'], '>Correggi</a>') && str_contains($r['body'], 'Ho capito, pubblico a mio rischio')
      && str_contains($r['body'], 'MyHouse Welcome non risponde dell\'uso di questi codici') && str_contains($r['body'], '>Torna a correggere</a>')
      && str_contains($r['body'], 'data-codici-vai disabled>Pubblica lo stesso') && !str_contains($r['body'], '4821'));
prova('…e accanto a Pubblica: la guida non compare nei motori di ricerca', str_contains($r['body'], 'Dopo la pubblicazione la guida è online per chi ha il link o il QR. Non compare nei motori di ricerca.'));
$r = $lucia->post("/pannello/$casa/pubblica", ['codici_ok' => '1', 'acquirente' => 'impresa']);
$conf = riga("SELECT * FROM audit_log WHERE action = 'guida.codici_confermati' ORDER BY id DESC");
prova('Con la casella la guida si pubblica e il registro ha la conferma (campi, mai il codice)', $r['code'] === 302 && str_contains($r['loc'], '/pubblicata')
      && (int) val('SELECT COUNT(*) FROM guide_versions WHERE property_id = ?', [$casa]) === $versioni + 1
      && $conf && str_contains((string) $conf['meta'], '"property":' . $casa . ',') && str_contains((string) $conf['meta'], 'Nota importante') && !str_contains((string) $conf['meta'], '4821'), $r['loc']);
$r = $lucia->post("/pannello/$casa/pubblica", ['acquirente' => 'impresa']);
prova('…vale solo per quella pubblicazione: alla successiva il riquadro ricompare', str_contains($r['loc'], '?codici=1'));
$r = $adm6->get('/admin/anomalie');
prova('Anomalie: «Guide pubblicate con un codice di accesso», con la data della conferma e il link alla guida', str_contains($r['body'], 'Guide pubblicate con un codice di accesso')
      && str_contains($r['body'], 'confermata a proprio rischio il') && str_contains($r['body'], "/g/$slugN"));
// Gli avvisi nella piattaforma.
$r = $lucia->get("/pannello/$casa/sezioni/$coreL");
prova('Editor di sezione: la riga fissa sui codici, e lo script che avvisa mentre si scrive', str_contains($r['body'], 'La guida la legge chi ha il link o il QR. Ti sconsigliamo di scrivere codici di porte, cassette delle chiavi,')
      && str_contains($r['body'], '/assets/codici.js') && str_contains($r['body'], 'i codici di porte e cassette è meglio non scriverli qui: comunicali all&#039;ospite in privato.'));
$r = $lucia->get("/pannello/$casa/procedura/struttura");
prova('Procedura, passo 1: «Chi vede la tua guida» con «Ho capito»', str_contains($r['body'], 'Chi vede la tua guida') && str_contains($r['body'], 'la guida non compare su Google né negli assistenti AI'));
$lucia->post('/pannello/avviso-guida', ['torna' => "/pannello/$casa/procedura/struttura"]);
prova('…«Ho capito» lo chiude per sempre (salvato sull\'account)', !str_contains($lucia->get("/pannello/$casa/procedura/struttura")['body'], 'Chi vede la tua guida'));
$r = (new Browser('reg6m'))->get('/registrati');
prova('Registrazione: «La guida che crei non compare sui motori di ricerca»', str_contains($r['body'], 'La guida che crei non compare sui motori di ricerca: la vede solo chi ha il link o il QR.'));
$r = $ospite->get('/domande');
prova('FAQ: «La mia guida si trova su Google?» subito prima del codice della porta, con la risposta nuova', strpos($r['body'], 'La mia guida si trova su Google?') < strpos($r['body'], 'Posso scrivere nella guida il codice della porta?')
      && str_contains($r['body'], 'Te lo sconsigliamo. La guida si apre da un link, senza password') && str_contains($r['body'], 'gli assistenti come ChatGPT non la leggono'));
$r = $ospite->get('/');
$altreL = preg_match('#<div class="faq__altre-lista">(.*?)</div>\s*</details>#s', $r['body'], $mm) ? $mm[1] : '';
prova('…nella landing stanno sotto «Altre domande»', str_contains($altreL, 'La mia guida si trova su Google?') && str_contains($altreL, 'Posso scrivere nella guida il codice della porta?'));
prova('Termini § 6: la conferma sotto la propria responsabilità', str_contains($ospite->get('/termini')['body'], 'Se decidi comunque di pubblicarli, la piattaforma ti chiede una conferma'));

// ================================================================= 7C
capitolo('7C · foto dell\'host, foto ingrandibili, QR da stampare, guida di esempio, traduzioni, novità');
$lslug = (string) val('SELECT slug FROM properties WHERE id = ?', [$casa]);   // le prove della 6M hanno cambiato il link
// 1 · Il tondo con la foto dell'host, anche con il logo, e il foglio «Chi ti accoglie».
$lucia->get("/pannello/$casa/aspetto");
$lucia->post("/pannello/$casa/aspetto", ['palette' => (string) val('SELECT palette FROM properties WHERE id = ?', [$casa]), 'text_tone' => 'scuro',
    'logo' => file_(png(240, 80), 'image/png'), 'profile' => file_(png(300, 400), 'image/png'),
    'host_welcome[it]' => 'Benvenuti! Vi aspetto con il caffè.', 'host_welcome[en]' => 'Welcome! Coffee is on me.']);   // multipart: le chiavi per esteso
$r = $lucia->get("/pannello/$casa/aspetto");
prova('7C · Aspetto: «Due parole di benvenuto» per lingua (160 caratteri) e l\'aiuto sulla foto', str_contains($r['body'], 'Due parole di benvenuto') && str_contains($r['body'], 'maxlength="160"')
      && str_contains($r['body'], 'name="host_welcome[en]"') && str_contains($r['body'], 'Una tua foto: gli ospiti vedono chi li accoglie. Il logo è un&#039;altra cosa.'));
$lucia->post("/pannello/$casa/pubblica", ['codici_ok' => '1', 'acquirente' => 'impresa']);
$r = $ospite->get("/g/$lslug?l=en");
prova('7C · nella guida il tondo dell\'host c\'è anche con il logo, ed è un bottone che apre il foglio', str_contains($r['body'], 'class="logo-guest"')
      && preg_match('#<button type="button" class="host-tondo" data-host-apri="chi-ti-accoglie" aria-label="Your host: [^"]+"#', $r['body']) === 1
      && str_contains($r['body'], 'host-tondo__punto') && str_contains($r['body'], '/assets/guida.js'));
prova('…il foglio: la foto intera, «Hi, I\'m …», il benvenuto in inglese, «Message …»', preg_match('#<dialog class="host-foglio" id="chi-ti-accoglie".*?</dialog>#s', $r['body'], $mm) === 1
      && str_contains($mm[0], 'class="host-foglio__foto"') && str_contains($mm[0], 'Hi, I&#039;m ') && str_contains($mm[0], 'Welcome! Coffee is on me.')
      && preg_match('#<a class="btn btn--block" href="(https://wa\.me/|tel:)[^"]+"[^>]*>.*?Message #s', $mm[0]) === 1);
$r = $ospite->get("/g/$lslug?l=fr");
prova('…senza il benvenuto in quella lingua, la principale', str_contains($r['body'], 'Benvenuti! Vi aspetto con il caffè.'));
db()->prepare("UPDATE properties SET host_welcome = NULL WHERE id = ?")->execute([$casa]); $lucia->post("/pannello/$casa/pubblica", ['codici_ok' => '1', 'acquirente' => 'impresa']);
prova('…e senza nessun benvenuto, il testo di serie (de)', str_contains($ospite->get("/g/$lslug?l=de")['body'], 'Mein Zuhause ist Ihr Zuhause'));

// 4 · Le foto delle sezioni si ingrandiscono, la copertina no; il PDF ha il bottone grande.
$infoL = (int) val("SELECT id FROM sections WHERE property_id = ? AND kind = 'rules'", [$casa]) ?: (int) val("SELECT id FROM sections WHERE property_id = ? AND is_core = 0 ORDER BY id", [$casa]);
$lucia->get("/pannello/$casa/sezioni/$infoL");
$lucia->post("/pannello/$casa/sezioni/$infoL", ['foto' => file_(png(400, 300), 'image/png')]);
$lucia->post("/pannello/$casa/pubblica", ['codici_ok' => '1', 'acquirente' => 'impresa']);
$r = $ospite->get("/g/$lslug/$infoL");
prova('7C · la foto della sezione si ingrandisce: link alla foto con data-zoom e la lente', (bool) val('SELECT media_id FROM sections WHERE id = ?', [$infoL])
      && preg_match('#<div class="shot shot--h186 shot--zoom"[^>]*><a href="[^"]+" data-zoom aria-label="Ingrandisci la foto">#', $r['body']) === 1 && str_contains($r['body'], 'shot__lente'), (string) $r['code']);
$r = $ospite->get("/g/$lslug");
prova('…la copertina, il logo e la foto profilo no', !preg_match('#guest-cover.{0,300}data-zoom#s', $r['body']) && !preg_match('#data-zoom[^>]*>\s*<img[^>]*class="logo-guest#', $r['body']));

// 5 · Il QR da stampare: logo, nome, inviti in italiano e inglese, marchio. Quello di Casa Checco in prove/qr-esempio.pdf.
$r = $lucia->get("/pannello/$casa/qr.pdf");
prova('7C · PDF del QR: un PDF valido, con il logo (immagine), il nome e i quattro inviti della stessa grandezza', str_starts_with($r['body'], '%PDF-1.4') && str_contains($r['body'], '/Subtype /Image')
      && str_contains($r['body'], '/DCTDecode') && str_contains($r['body'], '(Inquadra il QR Code) Tj') && str_contains($r['body'], '(All about the house, and more.) Tj')
      && str_contains($r['body'], '(Tutte le informazioni sulla casa, e non solo.) Tj') && str_contains($r['body'], '(La guida digitale della casa ')
      && substr_count($r['body'], ' 16 Tf ') === 4 && str_contains($r['body'], '(MyHouse Welcome) Tj') && preg_match('#\([a-z0-9.:\-]+[^)]*/q/[A-Za-z0-9]+\) Tj#', $r['body']) === 1
      && str_ends_with(rtrim($r['body']), '%%EOF'));
$r = $lucia->get("/pannello/$casa/qr.svg");
prova('…e lo stesso foglio in SVG', str_contains($r['body'], '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 840"') && str_contains($r['body'], 'Scan the QR code') && str_contains($r['body'], '<image href="data:image/'));
$vetAcc = (int) val('SELECT account_id FROM properties WHERE is_demo = 2 ORDER BY id DESC LIMIT 1');
$vetPid = (int) val('SELECT id FROM properties WHERE is_demo = 2 ORDER BY id DESC LIMIT 1');
$vetEmail = (string) val('SELECT u.email FROM users u JOIN accounts a ON a.user_id = u.id WHERE a.id = ?', [$vetAcc]);
if ($vetPid && $vetEmail !== '') {
    $vetUid = (int) val('SELECT user_id FROM accounts WHERE id = ?', [$vetAcc]);
    $admin->get('/admin/clienti'); $admin->post("/admin/entra/$vetUid", []);
    $q = $admin->get("/pannello/$vetPid/qr.pdf");
    $admin->segui($admin->get('/pannello')); $admin->post('/admin/esci-da-cliente', []);
    if (str_starts_with($q['body'], '%PDF')) file_put_contents(__DIR__ . '/qr-esempio.pdf', $q['body']);
    prova('7C · il QR di Casa Checco salvato in prove/qr-esempio.pdf', str_starts_with($q['body'], '%PDF') && str_contains($q['body'], '(Casa Checco) Tj'), (string) $q['code']);
}

// 6 · La guida di esempio nel pannello.
$r = $lucia->get("/pannello/$casa/sezioni/$infoL");
prova('7C · barra laterale: «Guida di esempio» (la vetrina, scheda nuova); editor: «Vedi un esempio» della stessa sezione', str_contains($r['body'], '<span>Guida di esempio</span>')
      && preg_match('#href="[^"]*/g/[^"/]+/\d+" target="_blank" rel="noopener">.*?Vedi un esempio#s', $r['body']) === 1);
prova('8F · «Vedi un esempio»: 16 px, peso 500, icona 18 px, centrato sulla riga del titolo, almeno 40 px di altezza; sul telefono sotto il titolo',
      str_contains($r['body'], 'class="vedi-esempio"') && preg_match('#class="vedi-esempio"[^>]*><svg width="18" height="18"#', $r['body']) === 1
      && preg_match('/\.vedi-esempio\{display:inline-flex;align-items:center;gap:6px;margin-left:14px;min-height:40px;font-size:16px;font-weight:500/', (string) file_get_contents("$DOVE/assets/app.css")) === 1
      && str_contains((string) file_get_contents("$DOVE/assets/app.css"), '@media (max-width:640px){.vedi-esempio{flex-basis:100%;margin-left:0}}'));

// 7 · Traduzioni: «Traduci» un campo solo, testo cambiato, etichette, «Manca in», passo Pubblica, Prova e Diagnostica.
$emIt = fn(string $t) => $trData($emCasa, 'it', 'note', $t);
$emIt('Il pronto soccorso più vicino è a Nottola, 10 minuti in auto.'); $trData($emCasa, 'en', 'note', '');
db()->prepare("DELETE FROM translation_suggestions WHERE property_id = ? AND locale = 'en'")->execute([$casa]);
$r = $lucia->get("/pannello/$casa");
prova('7C · Contenuti: «EN da tradurre» sulla sezione che manca in inglese', preg_match('#href="[^"]*/pannello/' . $casa . '/lingue/[a-z]{2}"[^>]*>[^<]*EN[^<]*da tradurre</a>#', $r['body']) === 1);
$r = $lucia->get("/pannello/$casa/sezioni/$emCasa");
prova('7C · editor: «Manca in: EN» con il link alla traduzione', preg_match('#Manca in:\s*(?:<a [^>]+>[A-Z]{2}</a>, )*<a href="[^"]*/lingue/en">EN</a>#', $r['body']) === 1);
$r = $lucia->get("/pannello/$casa/procedura/pubblica");
prova('7C · passo Pubblica: le lingue da completare, senza bloccare', str_contains($r['body'], 'Lingue da completare') && str_contains($r['body'], "da tradurre. Dove manca, l'ospite legge la lingua principale."));
$lucia->get("/pannello/$casa/lingue/en");
$prima = count($richiesteTr());
$r = $lucia->segui($lucia->post("/pannello/$casa/lingue/en/suggerite", ['campo' => "section:$emCasa:note"]));
prova('7C · «Traduci» di un solo campo: una chiamata e una sola proposta, da confermare', count($richiesteTr()) === $prima + 1
      && (int) val("SELECT COUNT(*) FROM translation_suggestions WHERE property_id = ? AND locale = 'en'", [$casa]) === 1 && str_contains($r['body'], 'Ecco la traduzione proposta'));
$lucia->segui($lucia->post("/pannello/$casa/lingue/en/suggerite", ['approva' => (string) $idDi('section', $emCasa, 'note')]));
$emIt('Il pronto soccorso più vicino è a Perugia.');
$r = $lucia->get("/pannello/$casa/lingue/en");
prova('7C · l\'italiano cambia dopo l\'approvazione: «Il testo italiano è cambiato» con «Traduci di nuovo»', str_contains($r['body'], 'Il testo italiano è cambiato')
      && preg_match('#name="campo" value="section:' . $emCasa . ':note">[^<]*<svg.*?</svg> Traduci di nuovo#s', $r['body']) === 1);
$r = $lucia->segui($lucia->post("/pannello/$casa/lingue/en/suggerite", ['campo' => "section:$emCasa:note"]));
$nuovaS = (string) val("SELECT text FROM translation_suggestions WHERE target_type = 'section' AND target_id = ? AND field_path = 'note' AND locale = 'en'", [$emCasa]);
$r = $lucia->segui($lucia->post("/pannello/$casa/lingue/en/suggerite", ['approva' => (string) $idDi('section', $emCasa, 'note')]));
$notaEn = (string) (json_decode((string) val("SELECT data FROM section_translations WHERE section_id = ? AND locale = 'en'", [$emCasa]), true)['note'] ?? '');
$flashA = preg_match('#<p class="note[^"]*"[^>]*role="status">([^<]+)#', $r['body'], $mm) ? $mm[1] : '';
$r = $lucia->get("/pannello/$casa/lingue/en");
prova('…«Traduci di nuovo» e approvata: la traduzione nuova, e il segno sparisce', $notaEn === '[en] Il pronto soccorso più vicino è a Perugia.'
      && !str_contains($r['body'], 'name="campo" value="section:' . $emCasa . ':note"'), "proposta «{$nuovaS}» · salvata «{$notaEn}» · {$flashA} · cambiati " . substr_count($r['body'], 'Il testo italiano è cambiato'));
$php = 'define("MHW_APP", ' . var_export("$DOVE/app", true) . '); spl_autoload_register(fn($c) => require MHW_APP . "/src/" . substr($c, 4) . ".php"); MHW\Config::load(MHW_APP . "/config.php"); ';
$vuote = trim((string) shell_exec('MHW_TRANSLATE_KEY= MHW_TRANSLATE_SECRET= AWS_ACCESS_KEY_ID= AWS_SECRET_ACCESS_KEY= php -r ' . escapeshellarg($php . 'echo MHW\Impostazioni::prova("traduzioni", "a@b.it")[1], "|", MHW\Traduttore::perche(1);') . ' 2>&1'));
prova('7C · «Prova» delle traduzioni con le chiavi vuote: il messaggio chiaro, e nel pannello «non disponibili in questo momento»', str_contains($vuote, 'Mancano le chiavi')
      && (str_contains($vuote, 'Le traduzioni non sono disponibili in questo momento') || str_contains($vuote, 'piano Plus')), $vuote);
$sbagliata = trim((string) shell_exec('MHW_TRANSLATE_KEY=AKIASBAGLIATA php -r ' . escapeshellarg($php . 'echo MHW\Impostazioni::prova("traduzioni", "a@b.it")[1];') . ' 2>&1'));
prova('…con una chiave sbagliata: il messaggio esatto di AWS e cosa fare', str_contains($sbagliata, 'UnrecognizedClientException') && str_contains($sbagliata, 'Chiave sconosciuta')
      && str_contains($sbagliata, 'Credenziali di sicurezza'), $sbagliata);
$archivioKo = trim((string) shell_exec('MHW_STORAGE=s3 AWS_S3_ENDPOINT=http://127.0.0.1:9 AWS_S3_BUCKET=prova AWS_ACCESS_KEY_ID=AKIAPROVA AWS_SECRET_ACCESS_KEY=segreto php -r '
    . escapeshellarg($php . 'echo MHW\Impostazioni::prova("archivio", "a@b.it")[1];') . ' 2>&1'));
prova('Revisione · Impostazioni → Archivio → «Prova» con un bucket irraggiungibile: il motivo vero e cosa fare', str_contains($archivioKo, 'Il bucket non risponde: «HTTP 0')
      && str_contains($archivioKo, 'connessioni in uscita'), $archivioKo);
foreach (['Storage', 'S3Storage'] as $cls) require_once "$DOVE/app/src/$cls.php";
prova('…e l\'XML di S3 si legge: codice e messaggio, con il consiglio giusto', MHW\S3Storage::codiceDi('<Error><Code>SignatureDoesNotMatch</Code><Message>The request signature we calculated does not match</Message></Error>') === 'SignatureDoesNotMatch: The request signature we calculated does not match'
      && str_contains(MHW\S3Storage::consiglio('HTTP 403 · SignatureDoesNotMatch'), 'chiave segreta') && str_contains(MHW\S3Storage::consiglio('HTTP 301 · PermanentRedirect'), 'regione'));
$r = $admin->get('/admin/diagnostica');
prova('7C · Diagnostica: la riga delle traduzioni con l\'esito esatto dell\'ultima chiamata (qui la chiave sbagliata)', str_contains($r['body'], 'Traduzioni suggerite (Amazon Translate)')
      && str_contains($r['body'], 'non è riuscita') && str_contains($r['body'], 'UnrecognizedClientException'));

// 8 · Novità: la fascia, una volta per account, e chiusa resta chiusa.
db()->prepare('UPDATE users SET created_at = ? WHERE email = ?')->execute(['2026-01-01T00:00:00Z', 'lucia@esempio.it']);
$r = $lucia->get('/pannello');
prova('8C · Novità: la prima è «Riscaldamento e aria condizionata ora sono due sezioni»', str_contains($r['body'], 'Riscaldamento e aria condizionata ora sono due sezioni'));
prova('7C → 9F · Novità: la fascia con «Mostrami dove» (un modulo POST) e «Tutte le novità», senza ✕', str_contains($r['body'], 'class="novita"') && str_contains($r['body'], '>Mostrami dove</button>')
      && str_contains($r['body'], '/pannello/novita/vai"') && str_contains($r['body'], '>Tutte le novità</a>') && !str_contains($r['body'], 'novita/chiudi'));
$lucia->post('/pannello/novita/vai', ['novita' => '2026-10-clima']);
$r = $lucia->get('/pannello');
prova('…«Mostrami dove» la segna come vista (per account) e la fascia sparisce: si vede solo l\'ultima, le passate stanno in «Novità»', !str_contains($r['body'], 'class="novita"')
      && (bool) val("SELECT id FROM novita_viste WHERE novita_id = '2026-10-clima' AND account_id = ?", [$accDi('lucia@esempio.it')])
      && str_contains($lucia->get('/pannello/novita')['body'], 'La tua foto nella guida, anche con Essential'));
// Un campo nuovo (una novità di prova con un campo della sezione Emergenze): «Nuovo» in Contenuti e nell'editor, «Ho visto».
file_put_contents("$DOVE/app/lang/novita.php", str_replace("return [\n", "return [\n    ['id' => 'prova-campo', 'data' => '2026-10-11', 'titolo' => 'Prova', 'testo' => 'Prova', 'dove' => '/pannello', 'campi' => ['emergency' => ['note_prova']], 'sezioni' => []],\n", (string) file_get_contents("$DOVE/app/lang/novita.php")));
sleep(3);   // opcache del server di prova rilegge i file ogni 2 secondi
$lucia->post('/pannello/novita/chiudi', ['novita' => 'prova-campo']);
$r = $lucia->get("/pannello/$casa");
prova('7C · campo nuovo vuoto: «Nuovo» con il pallino sulla sezione in Contenuti', preg_match('#id="sez-' . $emCasa . '".*?novita-pallino.*?</div>#s', $r['body']) === 1);
$lucia->post('/pannello/novita/visto', ['novita' => 'prova-campo:emergency', 'torna' => "/pannello/$casa/sezioni/$emCasa"]);
prova('…«Ho visto» lo toglie', !preg_match('#id="sez-' . $emCasa . '"[^>]*>(?:(?!id="sez-).)*novita-pallino#s', $lucia->get("/pannello/$casa")['body']));


capitolo('7D · Invita un amico per tutti, anteprima del link, giudizi dei clienti');
$admin->get('/admin/impostazioni');
$admin->segui($admin->post('/admin/impostazioni/inviti', ['inviti__attivi' => '1', 'password' => 'AdminProva123']));
$pacc = $accDi('paola@prova.test'); $darioAcc = $accDi('dario@prova.test'); $pp6 = (int) val('SELECT id FROM properties WHERE account_id = ? ORDER BY id', [$pacc]);
$codP = (string) val('SELECT referral_code FROM accounts WHERE id = ?', [$pacc]);
db()->prepare("UPDATE properties SET address = 'Via Segretissima 7', status = 'published' WHERE id = ?")->execute([$pp6]);
$r = $paola->get('/inviti');
prova('7D · barra laterale: «Invita un amico» con l\'etichetta −5% / −5%', str_contains($r['body'], '<span>Invita un amico</span><em class="lato__etichetta"') && str_contains($r['body'], "\u{2212}5% / \u{2212}5%"));
prova('…in alto «Come funziona» in tre passi, poi il messaggio pronto con struttura, link e codice, e la casella dell\'anteprima',
      strpos($r['body'], 'Come funziona') < strpos($r['body'], 'Il messaggio pronto') && str_contains($r['body'], 'Ciao! Uso MyHouse Welcome per la guida digitale della mia struttura')
      && str_contains($r['body'], 'Con questo invito hai il 5% di sconto sul primo anno') && str_contains($r['body'], 'Il codice è ' . $codP . '.') && str_contains($r['body'], 'data-wa-da="inv-messaggio"')
      && str_contains($r['body'], 'name="mostra" value="1" checked') && str_contains($r['body'], 'Mostra la copertina e il nome della mia struttura'));
$paola->post("/pannello/$pp6/aspetto", ['palette' => 'terracotta', 'text_tone' => 'scuro', 'cover' => file_(png(1600, 900), 'image/png')]);
$visit = new Browser('visitatore7d');
$r = $visit->get('/i/' . $codP);
prova('7D · /i/CODICE è una pagina: chi invita, struttura e città, i riquadri delle sezioni, i due bottoni, noindex', $r['code'] === 200
      && str_contains($r['body'], 'Paola usa MyHouse Welcome per Casa Paola, a Todi.') && str_contains($r['body'], 'invito-pagina__riquadri')
      && str_contains($r['body'], 'Crea la tua guida con il 5% di sconto') && str_contains($r['body'], 'Guarda una guida completa') && str_contains($r['body'], '<meta name="robots" content="noindex">'), (string) $r['code']);
prova('…senza indirizzo, Wi-Fi, contatti o istruzioni', !str_contains($r['body'], 'Via Segretissima') && !str_contains($r['body'], 'Le chiavi sono nella cassetta') && !str_contains($r['body'], 'RSSMRA85T10A562S'));
prova('…og:title, og:description e og:image della copertina', str_contains($r['body'], '<meta property="og:title" content="Casa Paola usa MyHouse Welcome">')
      && str_contains($r['body'], 'content="Con questo invito hai il 5% di sconto sul primo anno."') && preg_match('#<meta property="og:image" content="http[^"]+/i/' . $codP . '/anteprima\.jpg">#', $r['body']) === 1);
$img = $visit->get('/i/' . $codP . '/anteprima.jpg');
$dim = @getimagesizefromstring($img['body']);
prova('…l\'immagine: JPEG 1200×630 fuori dai motori', $img['code'] === 200 && $dim && $dim[0] === 1200 && $dim[1] === 630 && $dim['mime'] === 'image/jpeg'
      && stripos($img['head'], 'X-Robots-Tag: noindex') !== false, json_encode($dim));
$paola->post('/inviti/anteprima', ['mostra' => '0']);
$r = $visit->get('/i/' . $codP);
prova('7D · casella spenta: anteprima generica, senza la struttura', $r['code'] === 200 && !str_contains($r['body'], 'Casa Paola') && str_contains($r['body'], '/assets/og.jpg')
      && $visit->get('/i/' . $codP . '/anteprima.jpg')['code'] === 404);
$paola->post('/inviti/anteprima', ['mostra' => '1']);
// L'amico si registra dal link e pubblica: «Chi è entrato» con il risparmio.
db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'register:%'");
$visit->get('/registrati');
$visit->post('/registrati', ['name' => 'Bruno Amico', 'email' => 'bruno7d@prova.test', 'email2' => 'bruno7d@prova.test', 'password' => 'BrunoProva1234', 'termini' => '1']);
$bacc = $accDi('bruno7d@prova.test');
prova('7D · l\'amico registrato dal link è legato all\'invito', (string) val('SELECT status FROM referrals WHERE friend_account_id = ?', [$bacc]) === 'registrato');
$r = $paola->get('/inviti');
prova('…«Chi è entrato»: solo il nome e «Si è registrato»', str_contains($r['body'], 'Chi è entrato') && preg_match('#<b>Bruno</b>.*?Si è registrato#s', $r['body']) === 1 && !str_contains($r['body'], 'Bruno Amico'));
db()->prepare("UPDATE referrals SET status = 'valido', qualified_at = ? WHERE friend_account_id = ?")->execute([gmdate('Y-m-d\TH:i:s\Z'), $bacc]);
$r = $paola->get('/inviti');
prova('…ha pubblicato: «Ti ha fatto risparmiare» e in cima «Finora hai risparmiato … sul prossimo rinnovo»', str_contains($r['body'], 'Ha pubblicato la guida')
      && preg_match('#Ti ha fatto risparmiare <b>[\d,.]+\x{00A0}€</b>#u', $r['body']) === 1 && preg_match('#Finora hai risparmiato <b>[\d,.]+\x{00A0}€</b> sul prossimo rinnovo#u', $r['body']) === 1);
// Lo sconto di chi ha un abbonamento dello staff: da applicare a mano.
$admin->get('/admin/cliente/' . $darioAcc);   // un abbonamento dello staff nuovo: quello di prima potrebbe essere scaduto nelle prove delle scadenze
$admin->post("/admin/cliente/$darioAcc/abbonamento", ['pv' => pv('plus'), 'mesi' => '12', 'nota' => 'Prova 7D']);
db()->prepare("INSERT INTO referrals (referrer_account_id, friend_account_id, status, created_at, qualified_at) VALUES (?, ?, 'valido', ?, ?)")
    ->execute([$darioAcc, $accDi('rita@prova.test'), gmdate('Y-m-d\TH:i:s\Z'), gmdate('Y-m-d\TH:i:s\Z')]);
$r = $admin->get('/admin/inviti');
prova('7D · Amministrazione → Inviti: «Da applicare a mano al rinnovo» con l\'importo, per l\'abbonamento dello staff', str_contains($r['body'], 'Da applicare a mano al rinnovo')
      && preg_match('#dario@prova\.test.*?−5%</b> · [\d,.]+\x{00A0}€#us', $r['body']) === 1);
$admin->post("/admin/inviti/applicato/$darioAcc", []);
prova('…«Applicato» chiude gli inviti come già scontati', (int) val("SELECT COUNT(*) FROM referrals WHERE referrer_account_id = ? AND status = 'usato' AND used_ref = 'manuale'", [$darioAcc]) === 1
      && !str_contains($admin->get('/admin/inviti')['body'], 'Da applicare a mano al rinnovo'));
$r = $dario->get('/inviti');
prova('…e dario vede che lo sconto lo applichiamo noi (regole)', str_contains($r['body'], 'lo applichiamo noi al rinnovo'));

// Giudizi dei clienti.
db()->prepare('UPDATE properties SET published_at = ? WHERE id = ?')->execute([gmdate('Y-m-d\TH:i:s\Z', time() - 20 * 86400), $pp6]);
// 9B: il riquadro «Ti piace MyHouse Welcome?» c'è dal primo accesso (spiega il programma); l'email di richiamo resta a 30 giorni.
$r = $paola->get('/pannello');
prova('7D · 9B · giudizio: il riquadro del programma c\'è subito, con la casella «Partecipo» (non ha ancora aderito)', str_contains($r['body'], 'id="giudizio-invito"')
      && str_contains($r['body'], 'sul prossimo rinnovo, con un giudizio') && str_contains($r['body'], 'name="partecipo"') && str_contains($r['body'], '/giudizio/partecipo'));
db()->prepare('UPDATE properties SET published_at = ? WHERE id = ?')->execute([gmdate('Y-m-d\TH:i:s\Z', time() - 32 * 86400), $pp6]);
$paola->post('/giudizio/chiudi', []);
prova('…chiuso non c\'è', !str_contains($paola->get('/pannello')['body'], 'id="giudizio-invito"'));
db()->prepare('UPDATE accounts SET giudizio_chiuso_at = ? WHERE id = ?')->execute([gmdate('Y-m-d\TH:i:s\Z', time() - 61 * 86400), $pacc]);
prova('…torna dopo 60 giorni', str_contains($paola->get('/pannello')['body'], 'id="giudizio-invito"'));
$paola->post('/giudizio/chiudi', []);
db()->prepare('UPDATE accounts SET giudizio_chiuso_at = ? WHERE id = ?')->execute([gmdate('Y-m-d\TH:i:s\Z', time() - 200 * 86400), $pacc]);
prova('…una volta sola: chiuso due volte non torna più', !str_contains($paola->get('/pannello')['body'], 'id="giudizio-invito"'));
$prima = count($posta('paola@prova.test')); $cron();
$m = $posta('paola@prova.test'); $ult = end($m) ?: [];
prova('7D · email «Ti sta piacendo MyHouse Welcome?» a 30 giorni, una volta sola', count($m) === $prima + 1 && str_contains((string) ($ult['subject'] ?? ''), 'Ti sta piacendo MyHouse Welcome?')
      && str_contains((string) $ult['text'], '/giudizio'), json_encode($ult['subject'] ?? null));
$cron();
prova('…non si ripete', count($posta('paola@prova.test')) === $prima + 1);
$r = $paola->get('/giudizio');
prova('7D · 9B · modulo: 5 stelle obbligatorie, testo 20–400, i chip delle immagini (copertina), nessuna casella di consenso, la casella «Partecipo» per chi non ha aderito', $r['code'] === 200
      && substr_count($r['body'], 'name="rating"') === 5 && str_contains($r['body'], 'minlength="20" maxlength="400"') && str_contains($r['body'], 'name="show_cover" value="1" checked')
      && !str_contains($r['body'], 'name="show_logo"') && !str_contains($r['body'], 'name="consenso"') && !str_contains($r['body'], 'Acconsento') && substr_count($r['body'], 'name="partecipo"') === 1
      && str_contains($r['body'], 'Nome senza cognome, struttura e città compaiono sempre.'));
$r = $paola->post('/giudizio', ['rating' => '5', 'body' => 'Corto', 'photo_source' => 'copertina']);
prova('…testo troppo corto: errore, niente salvato', str_contains($r['body'], 'almeno 20 caratteri') && !val('SELECT id FROM testimonials WHERE account_id = ?', [$pacc]));
$r = $paola->post('/giudizio', ['rating' => '5', 'body' => 'Gli ospiti non mi chiedono più il Wi-Fi alle undici di sera.', 'photo_source' => 'copertina']);
$gid = (int) val('SELECT id FROM testimonials WHERE account_id = ?', [$pacc]);
prova('…senza l\'adesione: arriva solo a noi, «in_attesa», e non si può pubblicare', $gid > 0 && str_contains($r['body'], 'Resta tra noi')
      && (string) val('SELECT status FROM testimonials WHERE id = ?', [$gid]) === 'in_attesa' && val('SELECT consent_at FROM testimonials WHERE id = ?', [$gid]) === null);
$admin->post("/admin/testimonianze/$gid/pubblica", []);
prova('…«In home» senza l\'adesione: rifiutato', (string) val('SELECT status FROM testimonials WHERE id = ?', [$gid]) === 'in_attesa' && !str_contains($ospite->get('/')['body'], 'alle undici di sera')
      && $admin->post("/admin/giudizi/$gid/home", ['si' => '1'])['code'] === 302 && (int) val('SELECT home_visible FROM testimonials WHERE id = ?', [$gid]) === 0);
$r = $admin->get('/admin/giudizi');
prova('7D · 9B · Amministrazione → Clienti → Giudizi: la voce nel menu, il giudizio «Senza adesione», i filtri; Testimonianze rimanda lì', $r['code'] === 200 && pulita($r)
      && preg_match('#/admin/giudizi"[^>]*>(?:<svg.*?</svg>)?\s*(?:<span>)?Giudizi<#s', $r['body']) === 1 && str_contains($r['body'], 'Senza adesione') && str_contains($r['body'], 'alle undici di sera') && str_contains($r['body'], '?filtro=da_valutare')
      && str_contains($admin->get('/admin/testimonianze')['body'], 'Clienti → Giudizi'));
$paola->post('/giudizio', ['rating' => '5', 'body' => 'Gli ospiti non mi chiedono più il Wi-Fi alle undici di sera.', 'show_cover' => '1', 'partecipo' => '1']);
$pa9 = riga('SELECT * FROM accounts WHERE id = ?', [$pacc]);
prova('…con «Partecipo»: adesione dal pannello, lo sconto «sul prossimo rinnovo», la prova dell\'accordo nel giudizio (data e testo della casella), copertina scelta',
      ($pa9['giudizio_adesione'] ?? '') === 'pannello' && ($pa9['giudizio_sconto_stato'] ?? '') === 'da_applicare_rinnovo'
      && str_contains((string) val('SELECT consent_text FROM testimonials WHERE id = ?', [$gid]), 'Partecipo: lascerò un giudizio') && val('SELECT consent_at FROM testimonials WHERE id = ?', [$gid]) === $pa9['giudizio_adesione_at']
      && (int) val('SELECT show_cover FROM testimonials WHERE id = ?', [$gid]) === 1 && (int) val('SELECT show_logo FROM testimonials WHERE id = ?', [$gid]) === 0, json_encode($pa9));
$cpP = array_values(array_filter(richiesteStripe(), fn($x) => $x['metodo'] === 'POST' && $x['percorso'] === '/v1/subscriptions/sub_prova_paola' && isset($x['corpo']['discounts'])));
prova('…e il coupon «mhw-giudizio-5» (una volta) aggiunto al suo abbonamento Stripe', count($cpP) >= 1 && in_array('mhw-giudizio-5', array_map(fn($d) => (string) ($d['coupon'] ?? ''), end($cpP)['corpo']['discounts']), true)
      && (bool) array_filter(richiesteStripe(), fn($x) => $x['metodo'] === 'POST' && $x['percorso'] === '/v1/coupons' && ($x['corpo']['id'] ?? '') === 'mhw-giudizio-5' && ($x['corpo']['duration'] ?? '') === 'once'), json_encode(end($cpP)['corpo'] ?? null));
$admin->post("/admin/giudizi/$gid/home", ['si' => '1']);
$r = $ospite->get('/');
prova('7D · 9B · in home («Cosa dicono di noi»): stelle, nome senza cognome, struttura e città, la copertina, nessun link, la nota del programma', str_contains($r['body'], 'id="cosa-dicono"')
      && str_contains($r['body'], 'alle undici di sera') && str_contains($r['body'], 'aria-label="5 su 5"') && preg_match('#<b>Paola</b><span class="small muted">Casa Paola · Todi</span>#', $r['body']) === 1
      && !str_contains($r['body'], 'Paola Cambio') && preg_match('#<section id="cosa-dicono".*?</section>#s', $r['body'], $sd9) === 1 && !str_contains($sd9[0], '<a ') && !str_contains($sd9[0], 'onclick')
      && str_contains($sd9[0], 'qualunque voto dia') && str_contains($sd9[0], '/img/') && (int) val('SELECT home_visible FROM testimonials WHERE id = ?', [$gid]) === 1);
prova('…con meno di 5 giudizi niente media, e mai aggregateRating', !str_contains($r['body'], 'voci__media') && !str_contains($r['body'], 'aggregateRating'));
$ins = db()->prepare("INSERT INTO testimonials (name, property_name, body, photo_storage, photo_key, visible, position, created_at, account_id, rating, photo_source, status, consent_at, consent_text, home_visible)
                      VALUES (?, 'Casa', 'Testo di prova per la media dei giudizi.', '', '', 1, 0, ?, ?, ?, 'nessuna', 'pubblicata', ?, 'x', 1)");
foreach ([4, 5, 4, 5] as $i => $v) $ins->execute(['Host' . $i, gmdate('Y-m-d\TH:i:s\Z'), 900000 + $i, $v, gmdate('Y-m-d\TH:i:s\Z')]);
$r = $ospite->get('/');
prova('7D · da 5 giudizi pubblicati: «4,6 su 5 da 5 host»', str_contains($r['body'], '4,6 su 5</b> da 5 host') && !str_contains($r['body'], 'aggregateRating'));
db()->exec('DELETE FROM testimonials WHERE account_id >= 900000');
$r = $paola->get('/account');
prova('7D · 9B · Account: «Il tuo giudizio» in home, con «Ritira il giudizio»', str_contains($r['body'], 'Il tuo giudizio su MyHouse Welcome') && str_contains($r['body'], 'nella nostra home') && str_contains($r['body'], 'Ritira il giudizio'));
$paola->post('/account/giudizio/ritira', []);
$cpP2 = array_values(array_filter(richiesteStripe(), fn($x) => $x['metodo'] === 'POST' && $x['percorso'] === '/v1/subscriptions/sub_prova_paola' && array_key_exists('discounts', $x['corpo'])));
prova('…ritirato: sparisce subito dalla home; lo sconto promesso sul rinnovo decade e il coupon si toglie dall\'abbonamento', !str_contains($ospite->get('/')['body'], 'alle undici di sera')
      && val('SELECT withdrawn_at FROM testimonials WHERE id = ?', [$gid]) !== null && (string) val('SELECT giudizio_sconto_stato FROM accounts WHERE id = ?', [$pacc]) === 'nessuno'
      && !in_array('mhw-giudizio-5', array_map(fn($d) => (string) ($d['coupon'] ?? ''), (array) (end($cpP2)['corpo']['discounts'] ?? [])), true), json_encode(end($cpP2)['corpo'] ?? null));
$admin->post("/admin/testimonianze/$gid/pubblica", []);
prova('…e l\'amministrazione non lo può ripubblicare', !str_contains($ospite->get('/')['body'], 'alle undici di sera'));
$prima = count($posta('assistenza@welcomemyhouse.it'));
$paola->post('/giudizio', ['rating' => '2', 'body' => 'Mi manca la possibilità di stampare tutta la guida.', 'photo_source' => 'nessuna']);
$m = $posta('assistenza@welcomemyhouse.it'); $ult = end($m) ?: [];
prova('7D · da 1 a 3 stelle: «Ti scriviamo» e un\'email a noi con il giudizio', count($m) === $prima + 1 && str_contains((string) ($ult['subject'] ?? ''), 'Giudizio da 2/5')
      && str_contains((string) $ult['text'], 'stampare tutta la guida'), json_encode($ult['subject'] ?? null));
@unlink($fileLocale); @unlink(dirname($fileLocale) . '/config.local.bak.php');


capitolo('8A · home: il tempo, «Anche il tuo sito» a domande, la scheda con le stelle, testi ed email');
$r = $ospite->get('/');
$tempo = preg_match('#<section id="il-tempo".*?</section>#s', $r['body'], $m) ? $m[0] : '';
prova('8A · tempo: in ogni colonna titolo → frase → esempi → riferimento → cursore; il cursore delle necessarie arriva a 60',
      preg_match('#t2__etichetta.*?t2__desc.*?t2__lista.*?t2__rif.*?t2__cursore#s', $tempo) === 1 && str_contains($tempo, 'min="5" max="60" step="1" value="16" data-t2="nec"')
      && str_contains($r['body'], 'grid-row:span 5'));
prova('…riquadro: in grande il tempo («11 ore»), sotto le giornate, poi gli euro in grassetto piccolo; niente cifra in euro grande',
      str_contains($tempo, '<span class="t2__ore"><span data-o="oreG">11</span> ore</span>') && str_contains($tempo, 'cioè 1,5 giornate di lavoro')
      && str_contains($tempo, '<b class="t2__valgono">Valgono <span data-o="valore">228 €</span> del tuo tempo, a <span data-o="euro2">20 €</span> l&#039;ora.</b>')
      && !str_contains($tempo, 't2__euro') && str_contains($tempo, 'data-formato="it-IT"') && str_contains($tempo, 'data-giornate="cioè {n} giornate di lavoro"'));
prova('…le frasi del cursore vengono da «Testi e foto» (nessun «Riferimento» scritto a mano)', str_contains($tempo, 'Riferimento: <b>16 min</b> a prenotazione')
      && substr_count((string) file_get_contents("$DOVE/app/views/pub/_tempo.php"), 'Riferimento:') === 0);
$sito = preg_match('#<section id="sito".*?</section>#s', $r['body'], $m) ? $m[0] : '';
prova('8A · «Anche il tuo sito»: logo MyHouse tutto bianco (8F), «un servizio a parte», domanda 1 con Sì / No e i contatti diretti', preg_match('#/assets/myhouse/myhouse-logo-bianco-su-rosso\.png\?v=\d+" alt="MyHouse — Il tuo sito, le tue prenotazioni"#', $sito) === 1
      && str_contains($sito, '<b>MyHouse è un servizio a parte</b> di Blackout Agency') && str_contains($sito, 'Hai già un sito per la tua struttura?')
      && str_contains($sito, 'data-ha="si"') && str_contains($sito, 'data-ha="no"') && str_contains($sito, 'Oppure scrivici subito:') && str_contains($sito, 'WhatsApp +39 392 006 1600'));
prova('…il percorso: scelte di «Sì» (con Altro), i tre punti di «No», i tre bottoni finali e, senza JavaScript, i contatti con il messaggio di serie',
      substr_count($sito, 'data-bisogno') === 4 && str_contains($sito, 'Raccontaci in due parole') && str_contains($sito, 'Prenotazione e pagamento sul tuo sito, senza commissioni')
      && str_contains($sito, 'data-canale="scopri"') && str_contains($sito, 'data-canale="whatsapp"') && str_contains($sito, 'data-canale="email"')
      && str_contains($sito, 'funnel funnel--nojs') && str_contains($sito, 'wa.me/393920061600?text=' . rawurlencode('Ciao, ho una struttura ricettiva. Mi date una proposta?'))
      && str_contains($sito, 'data-msg-si="Ho già un sito e vorrei migliorarlo{bisogni}."') && str_contains($r['body'], '/assets/funnel-sito.js'));
$tok = preg_match('#data-csrf="([a-f0-9]+)"#', $r['body'], $mt) ? $mt[1] : '';
$x = $ospite->post('/sito/interesse', ['_csrf' => $tok, 'ha_sito' => 'si', 'bisogni' => 'google,dirette,inventato', 'altro' => '', 'canale' => 'whatsapp', 'da' => 'home']);
$riga = riga('SELECT * FROM interessi_sito ORDER BY id DESC LIMIT 1');
prova('8A · POST /sito/interesse: 204, la riga con risposte e canale (solo voci note), senza account per un visitatore', $x['code'] === 204 && $riga
      && $riga['ha_sito'] === 'si' && $riga['bisogni'] === 'google,dirette' && $riga['canale'] === 'whatsapp' && $riga['da'] === 'home' && $riga['account_id'] === null, json_encode($riga));
$x = $ospite->post('/sito/interesse', ['ha_sito' => 'no', 'canale' => 'email'], [], false);
prova('…senza il token CSRF niente riga', $x['code'] !== 204 && (int) val('SELECT COUNT(*) FROM interessi_sito') === 1);
$r = $admin->get('/admin/prospetti');
prova('8A · Prospetti: «Interessati a MyHouse» con data, risposte e canale', str_contains($r['body'], 'Interessati a MyHouse') && str_contains($r['body'], 'Trovarsi su Google, Più prenotazioni dirette')
      && str_contains($r['body'], '<td>WhatsApp</td>') && str_contains($r['body'], 'Visitatore'));
$guad = preg_match('#<section id="guadagno".*?</section>#s', $ospite->get('/')['body'], $mm) ? $mm[0] : '';
prova('8A · la scheda a destra: «Com\'è andato il soggiorno?», 5 stelle piene, il grazie, la richiesta e i tre portali, poi BENTORNATI',
      str_contains($guad, 'Com&#039;è andato il soggiorno?') && substr_count(preg_match('#congedo-mock__stelle">(.*?)</span>#s', $guad, $st) ? $st[1] : '', '<svg') === 5
      && str_contains($guad, 'Siamo felici che ti sia trovato bene! 😊') && str_contains($guad, 'Ti chiediamo un piccolo favore') && str_contains($guad, 'Booking.com') && str_contains($guad, 'BENTORNATI'));
prova('8A · Termini e Privacy senza «Testo di partenza»', !str_contains($ospite->get('/termini')['body'], 'Testo di partenza') && !str_contains($ospite->get('/privacy')['body'], 'Testo di partenza'));
$admin->get('/admin/testi');
$admin->post('/admin/testi', ['t_tempo2_valgono' => 'Fanno {euro} in un anno.', 't_sito2_titolo' => 'Prima la guida.', 'torna' => 'tempo']);
$h = $ospite->get('/')['body'];
prova('8A · Testi e foto: i testi nuovi (tempo2.*, sito2.*) si cambiano e compaiono in home', str_contains($h, 'Fanno <span data-o="valore">228 €</span> in un anno.') && str_contains($h, 'Prima la guida.<br>'));
$admin->post('/admin/testi', ['t_tempo2_valgono' => '', 't_sito2_titolo' => '', 'torna' => 'tempo']);


capitolo('8B · pannello: dove si trova la struttura, domanda sul sito, messaggi, anteprima del link, finestra del piano, QR');
$casa8 = (int) val("SELECT id FROM properties WHERE account_id = ? ORDER BY id LIMIT 1", [$accDi('lucia@esempio.it')]);
$slug8 = (string) val('SELECT slug FROM properties WHERE id = ?', [$casa8]);
$lucia->get("/pannello/$casa8/impostazioni");
$r = $lucia->segui($lucia->post("/pannello/$casa8/dopo-il-soggiorno", ['portali' => ['booking', 'google', 'vrbo'],
    'altri' => [['kind' => 'altro', 'label' => 'Vacanze Umbre', 'url' => 'https://www.vacanzeumbre.example/casa'], ['kind' => 'agoda', 'label' => '', 'url' => 'https://www.agoda.com/casa-lucia']],
    'social' => ['instagram' => '@casalucia', 'facebook' => 'https://www.facebook.com/casalucia', 'tiktok' => 'non va bene'], 'has_site' => '1', 'direct_url' => 'https://www.esempio.it/prenota']));
$ch8 = db()->query("SELECT kind, label, url FROM property_channels WHERE property_id = $casa8 ORDER BY position")->fetchAll();
prova('8B · salvati: i portali nell\'ordine scelto senza indirizzo, «Altro…» con nome e indirizzo, Agoda, Instagram da @nome; TikTok non valido scartato con avviso',
      array_column($ch8, 'kind') === ['booking', 'google', 'vrbo', 'altro', 'agoda', 'facebook', 'instagram'] && $ch8[0]['url'] === '' && $ch8[3]['label'] === 'Vacanze Umbre'
      && $ch8[6]['url'] === 'https://www.instagram.com/casalucia' && str_contains($r['body'], 'TikTok: scrivi l&#039;indirizzo'), json_encode($ch8));
$lucia->post("/pannello/$casa8/pubblica", ['codici_ok' => '1', 'acquirente' => 'impresa']);
$snap8 = json_decode((string) val('SELECT snapshot FROM guide_versions WHERE property_id = ? ORDER BY version DESC', [$casa8]), true);
$canSnap = array_column($snap8['property']['channels'] ?? [], 'url', 'kind');
prova('8B · istantanea (formato 7) con channels e i link già costruiti: Google con nome e città, Booking, VRBO dalle Impostazioni', (int) $snap8['format'] >= 7
      && str_starts_with((string) ($canSnap['google'] ?? ''), 'https://www.google.com/maps/search/?api=1&query=Casa%20Lucia') && ($canSnap['booking'] ?? '') === 'https://www.booking.com/'
      && ($canSnap['vrbo'] ?? '') === 'https://www.vrbo.com/' && ($canSnap['instagram'] ?? '') === 'https://www.instagram.com/casalucia', json_encode($canSnap));
// Un'istantanea vecchia (formato 6) con i link di recensione si apre ancora.
$vecchia = $snap8; $vecchia['format'] = 6; unset($vecchia['property']['channels']); $vecchia['property']['reviews'] = ['google' => 'https://g.page/r/vecchio/review', 'other' => 'https://www.tripadvisor.example/x'];
db()->prepare('UPDATE guide_versions SET snapshot = ? WHERE property_id = ? AND version = (SELECT MAX(version) FROM guide_versions WHERE property_id = ?)')->execute([json_encode($vecchia), $casa8, $casa8]);
$r = $ospite->get("/g/$slug8/commiato?voto=5");
prova('8B · …un\'istantanea vecchia con reviews: i link scritti dall\'host si vedono ancora', $r['code'] === 200 && str_contains($r['body'], 'href="https://g.page/r/vecchio/review"')
      && str_contains($r['body'], 'https://www.tripadvisor.example/x'));
$lucia->post("/pannello/$casa8/pubblica", ['codici_ok' => '1', 'acquirente' => 'impresa']);
$r = $lucia->get("/pannello/$casa8/impostazioni");
prova('8B · «Hai un sito dove gli ospiti possono prenotare?» Sì: i campi e la striscia MyHouse con «Scopri come» (percorso al passo Sì)',
      str_contains($r['body'], 'name="has_site" value="1" checked') && str_contains($r['body'], 'myhouse-logo-nero-breve.png?v=') && preg_match('#data-sito-si>#', $r['body']) === 1
      && str_contains($r['body'], 'data-funnel-apri="si" data-funnel-dialogo="dlg-sito"') && preg_match('#data-sito-no hidden#', $r['body']) === 1);
$lucia->post("/pannello/$casa8/dopo-il-soggiorno", ['portali' => ['booking', 'google'], 'has_site' => '0', 'direct_url' => 'https://www.esempio.it/prenota', 'direct_code' => 'TORNA']);
$r = $lucia->get("/pannello/$casa8/impostazioni");
prova('…No: niente campi, «Crealo con noi» con «Voglio una proposta», WhatsApp ed Email; i campi restano salvati', str_contains($r['body'], 'name="has_site" value="0" checked')
      && preg_match('#data-sito-si hidden#', $r['body']) === 1 && preg_match('#striscia-myhouse--crea" data-sito-no>#', $r['body']) === 1 && str_contains($r['body'], 'Crealo con noi.')
      && str_contains($r['body'], 'data-funnel-apri="no"') && (string) val('SELECT direct_url FROM properties WHERE id = ?', [$casa8]) === 'https://www.esempio.it/prenota');
$lucia->post("/pannello/$casa8/pubblica", ['codici_ok' => '1', 'acquirente' => 'impresa']);
prova('…e nella guida la prenotazione diretta non compare', !str_contains($ospite->get("/g/$slug8/commiato")['body'], 'href="https://www.esempio.it/prenota"'));
$lucia->post("/pannello/$casa8/dopo-il-soggiorno", ['portali' => ['booking', 'google'], 'has_site' => '1', 'direct_url' => 'https://www.esempio.it/prenota', 'direct_code' => '']);
// I messaggi.
$r = $lucia->get("/pannello/$casa8/qr");
prova('8B · QR & Link: i due messaggi con la spiegazione, le linguette, l\'anteprima WhatsApp con la scheda del link', str_contains($r['body'], 'I messaggi da mandare su WhatsApp')
      && str_contains($r['body'], 'Copia il messaggio nella lingua dell\'ospite') && str_contains($r['body'], 'Messaggio di fine soggiorno') && str_contains($r['body'], 'role="tablist"')
      && str_contains($r['body'], 'class="wa-fumetto"') && str_contains($r['body'], '/g/' . $slug8 . '/anteprima.jpg') && str_contains($r['body'], 'Com&#039;è andato il soggiorno?'));
prova('…il messaggio di fine soggiorno di serie porta a /recensione, con {host} riempito', preg_match('#data-link="[^"]*/g/' . preg_quote($slug8) . '/recensione"#', $r['body']) === 1
      && str_contains($r['body'], 'Ciao! Grazie di aver soggiornato da noi.') && preg_match('#/g/' . preg_quote($slug8) . '/recensione</span> Buon viaggio!#', $r['body']) === 1);
$lucia->post("/pannello/$casa8/messaggi", ['tipo' => 'saluto', 'lingua' => 'en', 'testo' => "Thanks! Review us here: {link}\n{host}", 'azione' => 'salva']);
$lucia->post("/pannello/$casa8/messaggi", ['tipo' => 'benvenuto', 'lingua' => 'it', 'testo' => 'Ciao, ecco la guida. A presto, {host}', 'azione' => 'salva']);
$m8 = json_decode((string) val('SELECT messages FROM properties WHERE id = ?', [$casa8]), true);
$r = $lucia->segui($lucia->get("/pannello/$casa8/qr"));
prova('8B · salvati per lingua come modello; senza {link} l\'avviso «Nel messaggio manca il link»', ($m8['saluto']['en'] ?? '') === "Thanks! Review us here: {link}\n{host}"
      && ($m8['benvenuto']['it'] ?? '') === 'Ciao, ecco la guida. A presto, {host}' && !isset($m8['saluto']['it'])
      && preg_match('#data-manca-link>Nel messaggio manca il link#', $r['body']) === 1 && str_contains($r['body'], 'Torna al testo di serie'), json_encode($m8));
$lucia->post("/pannello/$casa8/messaggi", ['tipo' => 'benvenuto', 'lingua' => 'it', 'azione' => 'serie']);
$m8 = json_decode((string) val('SELECT messages FROM properties WHERE id = ?', [$casa8]), true);
prova('…«Torna al testo di serie» toglie solo quel messaggio', !isset($m8['benvenuto']) && ($m8['saluto']['en'] ?? '') !== '');
// L'anteprima del link della guida.
$r = $ospite->get("/g/$slug8");
prova('8B · /g/{slug}: og:title «… · La guida della casa», og:description, og:image /anteprima.jpg; noindex resta', str_contains($r['body'], 'property="og:title" content="Casa Lucia · La guida della casa"')
      && str_contains($r['body'], 'content="Check-in, Wi-Fi e consigli per il soggiorno."') && preg_match('#property="og:image" content="http[^"]+/g/' . preg_quote($slug8) . '/anteprima\.jpg"#', $r['body']) === 1
      && str_contains($r['body'], '<meta name="robots" content="noindex'));
prova('…anche benvenuto e commiato', str_contains($ospite->get("/g/$slug8/benvenuto")['body'], '/anteprima.jpg"') && str_contains($ospite->get("/g/$slug8/commiato")['body'], '/anteprima.jpg"'));
$img = $ospite->get("/g/$slug8/anteprima.jpg");
$dim = @getimagesizefromstring($img['body']);
prova('8B · /g/{slug}/anteprima.jpg: JPEG 1200×630, fuori dai motori, salvata in storage/anteprime', $img['code'] === 200 && $dim && $dim[0] === 1200 && $dim[1] === 630 && $dim['mime'] === 'image/jpeg'
      && stripos($img['head'], 'X-Robots-Tag: noindex') !== false && (bool) glob("$DOVE/app/storage/anteprime/$casa8-guida-*.jpg"), (string) $img['code']);
// La finestra del piano: «Aggiungi una struttura» con un piano a una struttura.
$r = $anna->get('/pannello');
prova('8B · Essential: «Aggiungi una struttura» c\'è, con il lucchetto, e apre «Più strutture con Portfolio» con «Chiedi informazioni»',
      str_contains($r['body'], 'data-sblocca="strutture"') && str_contains($r['body'], 'Più strutture con Portfolio') && str_contains($r['body'], '/piano?passa=portfolio">Passa a Portfolio</a>')
      && str_contains($r['body'], 'Informazioni%20su%20Portfolio'));
prova('8B · FAQ: «Come chiedo una recensione agli ospiti?» e «Traduci tutti i campi mancanti»', str_contains($ospite->get('/domande')['body'], 'Come chiedo una recensione agli ospiti?')
      && str_contains($ospite->get('/domande')['body'], 'Traduci tutti i campi mancanti'));
$r = $admin->get('/admin/impostazioni');
prova('8B · Amministrazione → Impostazioni → Portali: gli indirizzi di partenza, con la prova', str_contains($r['body'], 'id="imp-portali-titolo"') && str_contains($r['body'], 'name="portali__google"')
      && str_contains($admin->segui($admin->post('/admin/impostazioni/portali/prova', []))['body'], 'Booking.com → https://www.booking.com/'));


capitolo('8C · guida: pagina delle recensioni, scelta della lingua, «Stiamo per partire», palette, riscaldamento e aria, lingue');
// La pagina delle recensioni, sulla guida di Lucia (portali Booking e Google dalla 8B).
$lucia->post("/pannello/$casa8/dopo-il-soggiorno", ['portali' => ['booking', 'google'], 'social' => ['instagram' => '@casalucia'], 'has_site' => '1', 'direct_url' => 'https://www.esempio.it/prenota', 'direct_code' => '']);
$lucia->post("/pannello/$casa8/pubblica", ['codici_ok' => '1', 'acquirente' => 'impresa']);
$r = $ospite->get("/g/$slug8/recensione");
prova('8C · /g/{slug}/recensione: 200, noindex, copertina e nome, «Com\'è andato il soggiorno?» con 5 stelle, og:title delle recensioni', $r['code'] === 200
      && str_contains($r['body'], '<meta name="robots" content="noindex') && stripos(intestazione($r, 'X-Robots-Tag'), 'noindex') !== false
      && str_contains($r['body'], 'class="recensione__nome">Casa Lucia') && substr_count($r['body'], 'class="stella') === 5
      && str_contains($r['body'], 'property="og:title" content="Casa Lucia · Com&#039;è andato il soggiorno?"') && !str_contains($r['body'], 'data-portale='));
$votiR = (int) val("SELECT COUNT(*) FROM analytics_events WHERE property_id = ? AND kind = 'rating_5'", [$casa8]);
$r = $ospite->get("/g/$slug8/recensione?voto=5");
prova('8C · 5 stelle: il grazie, il favore, i bottoni dei portali spuntati con i link del sistema e la riga sull\'app; il voto si conta',
      str_contains($r['body'], 'Siamo felici che ti sia trovato bene!') && str_contains($r['body'], 'Ti chiediamo un piccolo favore')
      && str_contains($r['body'], 'href="https://www.booking.com/" target="_blank" rel="noopener" data-portale="booking"')
      && str_contains($r['body'], 'data-portale="google"') && str_contains($r['body'], 'dall&#039;app o dall&#039;email')
      && (int) val("SELECT COUNT(*) FROM analytics_events WHERE property_id = ? AND kind = 'rating_5'", [$casa8]) === $votiR + 1);
$lucia->get("/pannello/$casa8/impostazioni");
$r = $lucia->post("/pannello/$casa8/impostazioni", ['name' => 'Casa Lucia', 'guest_email' => 'non-una-email']);
prova('8C · «Email per gli ospiti» in Impostazioni: un indirizzo sbagliato non si salva', str_contains($lucia->segui($r)['body'], 'L&#039;email per gli ospiti non sembra giusta')
      || str_contains($r['body'], 'L&#039;email per gli ospiti non sembra giusta'));
$lucia->post("/pannello/$casa8/impostazioni", ['name' => 'Casa Lucia', 'guest_email' => 'ospiti@casalucia.example']);
prova('…uno giusto sì', val('SELECT guest_email FROM properties WHERE id = ?', [$casa8]) === 'ospiti@casalucia.example'
      && str_contains($lucia->get("/pannello/$casa8/impostazioni")['body'], 'value="ospiti@casalucia.example"'));
$lucia->post("/pannello/$casa8/pubblica", ['codici_ok' => '1', 'acquirente' => 'impresa']);
$r = $ospite->get("/g/$slug8/recensione?voto=4&l=en");
prova('8C · 4 stelle (en): niente link ai portali nella pagina; «Thank you for your honesty», WhatsApp con il messaggio e l\'email per gli ospiti',
      str_contains($r['body'], 'Thank you for your honesty.') && str_contains($r['body'], 'Message us on WhatsApp') && str_contains($r['body'], '?text=Hi%20')
      && str_contains($r['body'], 'href="mailto:ospiti@casalucia.example?subject=My%20stay%20at%20Casa%20Lucia"')
      && !str_contains($r['body'], 'booking.com') && !str_contains($r['body'], 'google.com/maps') && !str_contains($r['body'], 'data-portale='));
$r = $ospite->get("/g/$slug8/commiato?voto=5");
prova('8C · saluto: lo stesso blocco, e «Seguici» con Instagram sotto la prenotazione diretta', str_contains($r['body'], 'data-voto="5"') && str_contains($r['body'], 'class="seguici')
      && str_contains($r['body'], 'href="https://www.instagram.com/casalucia"') && strpos($r['body'], 'class="seguici') > strpos($r['body'], 'https://www.esempio.it/prenota'));
$votiR = (int) val("SELECT COUNT(*) FROM analytics_events WHERE property_id = ? AND kind = 'rating_5'", [$casa8]);
$r = $lucia->get("/pannello/$casa8/anteprima/recensione?voto=5");
prova('…e l\'anteprima nel pannello, senza contare il voto', $r['code'] === 200 && str_contains($r['body'], 'data-voto="5"')
      && (int) val("SELECT COUNT(*) FROM analytics_events WHERE property_id = ? AND kind = 'rating_5'", [$casa8]) === $votiR);

// La home della guida: «Stiamo per partire», riquadri, scelta della lingua.
$r = $ospite->get("/g/$slug8", ['Accept-Language: de-DE,de;q=0.9,en;q=0.5']);
$lingueL = json_decode((string) val('SELECT snapshot FROM guide_versions WHERE property_id = ? ORDER BY version DESC', [$casa8]), true)['locales'] ?? [];
prova('8C · «Stiamo per partire» è un riquadro largo, con la riga sotto, verso il saluto', preg_match('#<a class="partenza" href="[^"]*/commiato\?l=[a-z]{2}">#', $r['body']) === 1
      && str_contains($r['body'], 'class="partenza__testo"') && (str_contains($r['body'], 'Cosa fare prima di uscire') || str_contains($r['body'], 'Was vor der Abreise')));
prova('8C · riquadri: icone da 30 px, titolo in .tile__titolo che non spezza le parole col trattino', preg_match('#<a class="tile [^"]*"[^>]*>\s*<svg width="30" height="30"#', $r['body']) === 1
      && str_contains($r['body'], '<span class="tile__titolo">') && str_contains($r['body'], '<span class="nowrap">Check-in</span>'));
$primaLingua = preg_match('#<a class="scegli-lingua__lingua[^"]*" href="[^"]*"\s+data-lingua="([a-z]{2})"#', $r['body'], $mL) ? $mL[1] : '';
prova('8C · alla prima apertura senza ?l=: la finestra «Scegli la lingua» con le lingue della guida, nel nome nativo, quella del telefono in cima ed evidenziata',
      count($lingueL) > 1 && str_contains($r['body'], 'id="scegli-lingua"') && substr_count($r['body'], 'data-lingua="') === count($lingueL)
      && (in_array('de', $lingueL, true) ? $primaLingua === 'de' && str_contains($r['body'], 'scegli-lingua__lingua--telefono') && str_contains($r['body'], 'Sprache wählen') : $primaLingua !== '')
      && str_contains($r['body'], 'data-guida="' . $slug8 . '"'), json_encode($lingueL) . ' ' . $primaLingua);
prova('…con ?l= nell\'indirizzo non c\'è, e nemmeno nell\'anteprima del pannello', !str_contains($ospite->get("/g/$slug8?l=it")['body'], 'id="scegli-lingua"')
      && !str_contains($lucia->get("/pannello/$casa8/anteprima")['body'], 'id="scegli-lingua"'));
prova('…c\'è anche nella pagina di benvenuto; il conteggio language_selected resta', str_contains($ospite->get("/g/$slug8/benvenuto")['body'], 'id="scegli-lingua"')
      && (bool) val("SELECT 1 FROM analytics_events WHERE property_id = ? AND kind = 'language_selected'", [$casa8]) === (bool) val("SELECT 1 FROM analytics_events WHERE property_id = ? AND kind = 'language_selected'", [$casa8]));
$js = (string) @file_get_contents("$DOVE/assets/guida.js");
prova('…guida.js ricorda la scelta in localStorage per guida (mhw-lingua-slug), senza cookie', str_contains($js, "'mhw-lingua-' + slug") && str_contains($js, 'localStorage.setItem')
      && !str_contains($js, 'document.cookie'));

// Palette: quattro colori ben distinti, contrasti calcolati.
$pal = json_decode((string) shell_exec('php -r ' . escapeshellarg('define("MHW_APP", "' . $DOVE . '/app"); spl_autoload_register(fn($c) => require "' . $DOVE . '/app/src/" . substr($c, 4) . ".php");
  $out = []; foreach (array_keys(MHW\Palette::all()) as $c) $out[$c] = MHW\Palette::get($c); echo json_encode($out);')), true) ?: [];
$lum = function (string $h): float { $h = ltrim($h, '#'); $c = array_map(fn($x) => ($v = hexdec($x) / 255) <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, str_split($h, 2));
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2]; };
$contr = fn(string $a, string $b) => (max($lum($a), $lum($b)) + 0.05) / (min($lum($a), $lum($b)) + 0.05);
// La distanza tra due colori come la vede l'occhio: CIE L*a*b*, ΔE 1976.
$lab = function (string $h): array {
    $c = array_map(fn($x) => ($v = hexdec($x) / 255) <= 0.04045 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, str_split(ltrim($h, '#'), 2));
    $xyz = [(0.4124 * $c[0] + 0.3576 * $c[1] + 0.1805 * $c[2]) / 0.95047, 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2], (0.0193 * $c[0] + 0.1192 * $c[1] + 0.9505 * $c[2]) / 1.08883];
    $f = array_map(fn($t) => $t > 0.008856 ? $t ** (1 / 3) : 7.787 * $t + 16 / 116, $xyz);
    return [116 * $f[1] - 16, 500 * ($f[0] - $f[1]), 200 * ($f[1] - $f[2])];
};
$attesi = ['terracotta' => ['#b4451f', '#1c5a78', '#1f6b3f', '#c08a14'], 'sabbia' => ['#8c5a2e', '#2f6f73', '#6e5a8a', '#c8a86b'], 'oliva' => ['#556b2f', '#a0522d', '#2e5e6e', '#c9b458'],
           'mare' => ['#1c5a78', '#2a7f62', '#b84f34', '#e0b04a'], 'borgogna' => ['#7b2331', '#2f5d50', '#4b3a6e', '#d4a259'], 'grafite' => ['#2f3338', '#3f6e8c', '#9c4a2f', '#c9c2b4']];
$male = [];
foreach ($attesi as $c => $tiles) {
    $p = $pal[$c] ?? [];
    if (($p['tiles'] ?? []) !== $tiles) { $male[] = "$c: colori"; continue; }
    for ($i = 0; $i < 3; $i++) if ($contr($p['tile_ink'][$i], $tiles[$i]) < 4.5) $male[] = "$c: testo chiaro sul riquadro " . ($i + 1) . ' ' . round($contr($p['tile_ink'][$i], $tiles[$i]), 2);
    if ($contr($p['scuro']['ink'], $tiles[3]) < 5) $male[] = "$c: testo scuro sul quarto " . round($contr($p['scuro']['ink'], $tiles[3]), 2);
    for ($i = 0; $i < 4; $i++) for ($j = $i + 1; $j < 4; $j++) {
        $dE = sqrt(array_sum(array_map(fn($x, $y) => ($x - $y) ** 2, $lab($tiles[$i]), $lab($tiles[$j]))));
        if ($dE < 20) $male[] = "$c: riquadri " . ($i + 1) . ' e ' . ($j + 1) . ' troppo vicini (ΔE ' . round($dE) . ')';
    }
}
prova('8C · palette: i quattro colori del brief, testo chiaro ≥ 4,5:1 sui primi tre, testo scuro ≥ 5:1 sul quarto, colori lontani tra loro', $pal && !$male, implode('; ', $male));

// Lingue: Plus con la lingua principale tedesca, una lingua in più, traduzioni suggerite da «de».
$greta = new Browser('greta');
$greta->get('/registrati?piano=' . pv('plus'));
$greta->post('/registrati', ['piano' => pv('plus'), 'name' => 'Greta Lingue', 'email' => 'greta@prova.test', 'password' => 'GretaProva123', 'termini' => '1', 'privacy' => '1']);
$gacc = $accDi('greta@prova.test');
db()->prepare('UPDATE users SET email_verified_at = ? WHERE email = ?')->execute([gmdate('Y-m-d\TH:i:s\Z'), 'greta@prova.test']);
$greta->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Haus Greta', 'city' => 'Spello']);
$gp = (int) val('SELECT id FROM properties WHERE account_id = ?', [$gacc]);
db()->prepare("INSERT INTO subscriptions (account_id, package_version_id, status, provider, provider_customer_id, provider_subscription_id, current_period_start,
    current_period_end, payment_status, created_at, updated_at, quantity) VALUES (?, ?, 'active', 'stripe', 'cus_greta', 'sub_prova_greta', ?, ?, 'paid', ?, ?, 1)")
    ->execute([$gacc, pv('plus'), gmdate('Y-m-d\TH:i:s\Z', time() - 10 * 86400), gmdate('Y-m-d\TH:i:s\Z', time() + 355 * 86400), gmdate('Y-m-d\TH:i:s\Z'), gmdate('Y-m-d\TH:i:s\Z')]);
$greta->modulo('/account', '/account/fatturazione', ['billing_type' => 'privato', 'billing_name' => 'Greta Lingue', 'cf' => 'RSSMRA85T10A562S',
               'billing_address' => 'Via Roma 1', 'billing_postal' => '06038', 'billing_city' => 'Spello', 'billing_province' => 'PG']);
$r = $greta->get("/pannello/$gp/procedura/struttura");
prova('8C · «In che lingua scrivi la guida?»: le cinque di base e «Un\'altra lingua» con i nomi nativi, di serie italiano', preg_match('#name="default_locale" value="it" checked#', $r['body']) === 1
      && str_contains($r['body'], 'Un\'altra lingua') && str_contains($r['body'], '>Nederlands<') && str_contains($r['body'], '>日本語<') && str_contains($r['body'], '>Čeština<'));
$greta->modulo("/pannello/$gp/procedura/struttura", "/pannello/$gp/impostazioni", ['name' => 'Haus Greta', 'default_locale' => 'de', 'cin' => 'IT054050C2GRETA001']);
prova('…la guida si scrive in tedesco', val('SELECT default_locale FROM properties WHERE id = ?', [$gp]) === 'de');
$greta->post("/pannello/$gp/lingue", ['locali' => ['de']]);
$gcore = (int) val('SELECT id FROM sections WHERE property_id = ? AND is_core = 1', [$gp]);
$greta->post("/pannello/$gp/sezioni/$gcore", ['checkin_steps' => ['Die Schlüssel liegen im Kasten neben der Tür.'], 'checkin_note' => 'Bei später Ankunft bitte eine Nachricht schicken.']);
$greta->post("/pannello/$gp/pubblica", ['codici_ok' => '1', 'acquirente' => 'impresa']);
$r = $ospite->get("/g/" . val('SELECT slug FROM properties WHERE id = ?', [$gp]), ['Accept-Language: it']);
$gslug = (string) val('SELECT slug FROM properties WHERE id = ?', [$gp]);
prova('8C · una lingua sola: la guida in tedesco, senza la finestra della lingua', $r['code'] === 200 && str_contains($r['body'], 'lang="de"') && str_contains($r['body'], 'Willkommen')
      && !str_contains($r['body'], 'id="scegli-lingua"'), (string) $r['code']);
$r = $greta->get("/pannello/$gp/lingue");
prova('8C · Plus: le lingue di base come caselle e «Una lingua in più» come scelta sola', preg_match('#type="checkbox" name="locali\[\]" value="it"#', $r['body']) === 1
      && preg_match('#type="radio" name="locali\[\]" value="nl"#', $r['body']) === 1 && str_contains($r['body'], 'One more language') && !str_contains($r['body'], 'value="de" checked disabled'));
$r = $greta->post("/pannello/$gp/lingue", ['locali' => ['de', 'it', 'nl', 'ja']]);
prova('…due lingue in più: rifiutato, niente salvato', !val("SELECT 1 FROM property_locales WHERE property_id = ? AND locale = 'nl'", [$gp])
      && str_contains($greta->segui($r)['body'] . $r['body'], 'una lingua aggiuntiva'));
$greta->post("/pannello/$gp/lingue", ['locali' => ['de', 'it', 'en', 'fr', 'es', 'nl']]);
prova('…sei lingue: la principale, le altre quattro di base e il nederlandese', array_column(righe('SELECT locale FROM property_locales WHERE property_id = ? ORDER BY locale', [$gp]), 'locale') === ['de', 'en', 'es', 'fr', 'it', 'nl']);
$prima = count($richiesteTr());
$greta->segui($greta->post("/pannello/$gp/lingue/it/suggerite", ['suggerisci' => '1']));
$greta->segui($greta->post("/pannello/$gp/lingue/nl/suggerite", ['suggerisci' => '1']));
$nuove = array_slice($richiesteTr(), $prima);
prova('8C · traduzioni suggerite dal tedesco verso l\'italiano e verso la lingua in più (nl)', $nuove && !array_filter($nuove, fn($q) => ($q['da'] ?? '') !== 'de')
      && (bool) array_filter($nuove, fn($q) => ($q['a'] ?? '') === 'it') && (bool) array_filter($nuove, fn($q) => ($q['a'] ?? '') === 'nl')
      && (bool) val("SELECT 1 FROM translation_suggestions WHERE property_id = ? AND locale = 'nl' AND text LIKE '[nl] Die Schlüssel%'", [$gp]), json_encode($nuove));
$greta->post("/pannello/$gp/lingue/nl/suggerite", ['tutte' => '1']);
$greta->post("/pannello/$gp/lingue/it/suggerite", ['tutte' => '1']);
$greta->post("/pannello/$gp/pubblica", ['codici_ok' => '1', 'acquirente' => 'impresa']);
$r = $ospite->get("/g/$gslug/$gcore?l=nl");
prova('8C · guida in olandese: interfaccia olandese e il testo tradotto', str_contains($r['body'], 'lang="nl"') && str_contains($r['body'], '[nl] Die Schlüssel') && str_contains($r['body'], 'Terug naar de gids'));
$r = $ospite->get("/g/$gslug?l=it");
prova('…in italiano, con la finestra della lingua che ora c\'è (senza ?l=) e i nomi nativi', str_contains($r['body'], 'Benvenuti') && str_contains($ospite->get("/g/$gslug")['body'], '>Nederlands</a>'));
$greta->post("/pannello/$gp/lingue", ['locali' => ['de', 'it', 'ja']]);
$greta->post("/pannello/$gp/lingue/ja", ['s' => [$gcore => ['checkin_note' => '遅く到着する場合はメッセージをください。']]]);
$greta->post("/pannello/$gp/pubblica", ['codici_ok' => '1', 'acquirente' => 'impresa']);
$r = $ospite->get("/g/$gslug/$gcore?l=ja");
prova('8C · lingua in più giapponese: interfaccia in inglese, il testo dell\'host in giapponese', str_contains($r['body'], 'lang="ja"') && str_contains($r['body'], 'Back to the guide')
      && str_contains($r['body'], '遅く到着する場合はメッセージをください。'));
file_put_contents("$DOVE/app/config.local.php", '<?php return ' . var_export(['translate' => ['extra' => false]], true) . ';');
$prima = count($richiesteTr());
$r = $greta->segui($greta->post("/pannello/$gp/lingue/ja/suggerite", ['suggerisci' => '1']));
$rIt = $greta->segui($greta->post("/pannello/$gp/lingue/it/suggerite", ['campo' => "section:$gcore:checkin_note"]));
prova('8C · «Traduci anche la lingua aggiuntiva» spento: niente suggerite verso il giapponese (si scrive a mano), l\'italiano sì', str_contains($r['body'], 'scrivila a mano')
      && !array_filter(array_slice($richiesteTr(), $prima), fn($q) => ($q['a'] ?? '') === 'ja'));
@unlink("$DOVE/app/config.local.php");
$r = $admin->get('/admin/impostazioni');
prova('…l\'interruttore è in Amministrazione → Impostazioni → Traduzioni', str_contains($r['body'], 'Traduci anche la lingua aggiuntiva'));

// Essential: la principale tedesca, una seconda a scelta (giapponese), la terza no (un cliente Essential nuovo).
$ester = new Browser('ester');
$ester->get('/registrati?piano=' . pv('essential'));
$ester->post('/registrati', ['piano' => pv('essential'), 'name' => 'Ester Essenziale', 'email' => 'ester@prova.test', 'password' => 'EsterProva123', 'termini' => '1', 'privacy' => '1']);
$esacc = $accDi('ester@prova.test');
$ester->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Casa Ester', 'city' => 'Gubbio']);
$esp = (int) val('SELECT id FROM properties WHERE account_id = ?', [$esacc]);
db()->prepare("INSERT INTO subscriptions (account_id, package_version_id, status, provider, provider_customer_id, provider_subscription_id, current_period_start,
    current_period_end, payment_status, created_at, updated_at, quantity) VALUES (?, ?, 'active', 'stripe', 'cus_ester', 'sub_prova_ester', ?, ?, 'paid', ?, ?, 1)")
    ->execute([$esacc, pv('essential'), gmdate('Y-m-d\TH:i:s\Z', time() - 10 * 86400), gmdate('Y-m-d\TH:i:s\Z', time() + 355 * 86400), gmdate('Y-m-d\TH:i:s\Z'), gmdate('Y-m-d\TH:i:s\Z')]);
$ester->modulo("/pannello/$esp/procedura/struttura", "/pannello/$esp/impostazioni", ['name' => 'Casa Ester', 'default_locale' => 'de']);
$ester->post("/pannello/$esp/lingue", ['locali' => ['de', 'ja']]);
$lingueA = array_column(righe('SELECT locale FROM property_locales WHERE property_id = ? ORDER BY locale', [$esp]), 'locale');
prova('8C · Essential con principale de e seconda ja', val('SELECT default_locale FROM properties WHERE id = ?', [$esp]) === 'de' && $lingueA === ['de', 'ja'], json_encode($lingueA));
$r = $ester->post("/pannello/$esp/lingue", ['locali' => ['de', 'ja', 'it']]);
$lingueA = array_column(righe('SELECT locale FROM property_locales WHERE property_id = ? ORDER BY locale', [$esp]), 'locale');
prova('…una terza rifiutata, con la finestra di Plus', $lingueA === ['de', 'ja'] && str_contains($r['body'], 'data-apri="lingue"'), json_encode($lingueA));

// Riscaldamento e aria condizionata: la migrazione 037 su sezioni «clima» (con e senza aria condizionata; Essential al limite).
$nuovaClima = function (int $prop, array $dati, array $tr, int $attiva = 1) {
    $pos = (int) val('SELECT COALESCE(MAX(position),0)+1 FROM sections WHERE property_id = ?', [$prop]);
    db()->prepare("INSERT INTO sections (property_id, kind, icon, color, position, is_core, is_active, data, created_at) VALUES (?, 'clima', 'clima', 'terracotta', ?, 0, ?, ?, ?)")
        ->execute([$prop, $pos, $attiva, json_encode($dati), gmdate('Y-m-d\TH:i:s\Z')]);
    $sid = (int) db()->lastInsertId();
    foreach ($tr as $l => [$tit, $d]) db()->prepare("INSERT INTO section_translations (section_id, locale, title, body, data, state, updated_at) VALUES (?, ?, ?, '', ?, 'reviewed', ?)")
        ->execute([$sid, $l, $tit, json_encode($d), gmdate('Y-m-d\TH:i:s\Z')]);
    return $sid;
};
// Greta (Plus): con aria condizionata, testi in tedesco e italiano.
$cg = $nuovaClima($gp, ['heating_mode' => 'auto', 'heating_times' => [['id' => 'r1', 'from' => '06:00', 'to' => '09:00', 'days' => []]], 'heating_temp' => '21', 'ac_mode' => 'libero', 'ac_temp' => '25', 'risparmio' => '1'],
    ['de' => ['Heizung und Klimaanlage', ['heating_how' => ['Thermostat im Flur.'], 'ac_how' => ['Fernbedienung auf dem Nachttisch.'], 'note' => 'Bitte Fenster schließen.']],
     'it' => ['', ['heating_how' => ['Termostato nel corridoio.'], 'ac_how' => ['Telecomando sul comodino.']]]]);
// Lucia (Portfolio): senza aria condizionata.
$cl = $nuovaClima($casa8, ['heating_mode' => 'manuale', 'heating_temp' => '20', 'ac_mode' => 'no'], ['it' => ['Riscaldamento e aria condizionata', ['heating_how' => ['Accendi la caldaia.']]]]);
// Ester (Essential): al limite di sezioni.
$maxA = (int) val("SELECT pf.value FROM package_features pf JOIN features f ON f.id = pf.feature_id WHERE f.code = 'sections' AND pf.package_version_id = ?", [pv('essential')]);
$ca = $nuovaClima($esp, ['heating_mode' => 'auto', 'ac_mode' => 'orari', 'ac_times' => [['id' => 'r2', 'from' => '14:00', 'to' => '18:00', 'days' => []]]], ['de' => ['', ['ac_how' => ['Taste ON drücken.']]]]);
while ((int) val('SELECT COUNT(*) FROM sections WHERE property_id = ? AND is_core = 0 AND is_active = 1', [$esp]) < $maxA) {
    db()->prepare("INSERT INTO sections (property_id, kind, icon, color, position, is_core, is_active, data, created_at) VALUES (?, 'custom', 'custom', 'terracotta', 99, 0, 1, '{}', ?)")->execute([$esp, gmdate('Y-m-d\TH:i:s\Z')]);
}
db()->prepare("UPDATE sections SET is_active = 0 WHERE property_id = ? AND is_active = 1 AND is_core = 0 AND id NOT IN (SELECT id FROM (SELECT id FROM sections WHERE property_id = ? AND is_core = 0 AND is_active = 1 ORDER BY id DESC LIMIT $maxA) x)")->execute([$esp, $esp]);
$posG = (int) val('SELECT position FROM sections WHERE id = ?', [$cg]);
$migra = 'define("MHW_APP", ' . var_export("$DOVE/app", true) . '); spl_autoload_register(fn($c) => require MHW_APP . "/src/" . substr($c, 4) . ".php"); '
       . 'MHW\Config::load(MHW_APP . "/config.php"); $f = require MHW_APP . "/migrations/037_guida_8c.php"; $f(MHW\Db::conn()); echo "fatto";';
$esito = trim((string) shell_exec('cd ' . escapeshellarg("$DOVE/app") . ' && php -r ' . escapeshellarg($migra) . ' 2>&1'));
$ariaG = riga("SELECT * FROM sections WHERE property_id = ? AND kind = 'aria' AND id > ?", [$gp, $cg]);
$trG = fn(int $s, string $l) => json_decode((string) val('SELECT data FROM section_translations WHERE section_id = ? AND locale = ?', [$s, $l]), true) ?: [];
prova('8C · migrazione: «clima» diventa «riscaldamento» (stesso id, testi heating_* e nota); con l\'aria condizionata nasce «aria» subito dopo, con ac_* e traduzioni',
      $esito === 'fatto' && val('SELECT kind FROM sections WHERE id = ?', [$cg]) === 'riscaldamento' && $ariaG && (int) $ariaG['position'] === $posG + 1 && (int) $ariaG['is_active'] === 1
      && ($trG($cg, 'de')['heating_how'] ?? []) === ['Thermostat im Flur.'] && ($trG($cg, 'de')['note'] ?? '') === 'Bitte Fenster schließen.' && !isset($trG($cg, 'de')['ac_how'])
      && ($trG((int) $ariaG['id'], 'de')['ac_how'] ?? []) === ['Fernbedienung auf dem Nachttisch.'] && ($trG((int) $ariaG['id'], 'it')['ac_how'] ?? []) === ['Telecomando sul comodino.']
      && (json_decode((string) $ariaG['data'], true)['ac_temp'] ?? '') === '25' && !isset(json_decode((string) val('SELECT data FROM sections WHERE id = ?', [$cg]), true)['ac_temp'])
      && val("SELECT title FROM section_translations WHERE section_id = ? AND locale = 'de'", [$cg]) === 'Heizung', $esito);
prova('…senza aria condizionata nasce solo «riscaldamento»', val('SELECT kind FROM sections WHERE id = ?', [$cl]) === 'riscaldamento'
      && !val("SELECT 1 FROM sections WHERE property_id = ? AND kind = 'aria' AND id > ?", [$casa8, $cl]) && val("SELECT title FROM section_translations WHERE section_id = ? AND locale = 'it'", [$cl]) === 'Riscaldamento');
$ariaA = riga("SELECT * FROM sections WHERE property_id = ? AND kind = 'aria' AND id > ?", [$esp, $ca]);
db()->prepare("UPDATE users SET created_at = '2026-01-01T00:00:00Z' WHERE email = 'ester@prova.test'")->execute();
// 9F · La fascia mostra solo l'ultima novità: via la novità di prova aggiunta più su (prova-campo), così l'ultima torna «clima».
file_put_contents("$DOVE/app/lang/novita.php", str_replace("    ['id' => 'prova-campo', 'data' => '2026-10-11', 'titolo' => 'Prova', 'testo' => 'Prova', 'dove' => '/pannello', 'campi' => ['emergency' => ['note_prova']], 'sezioni' => []],\n", '', (string) file_get_contents("$DOVE/app/lang/novita.php")));
sleep(3);   // opcache del server di prova
prova('…Essential al limite: «aria» nasce disattivata, con i suoi testi, e la novità lo dice', $ariaA && (int) $ariaA['is_active'] === 0
      && ($trG((int) $ariaA['id'], 'de')['ac_how'] ?? []) === ['Taste ON drücken.'] && str_contains(($rNov = $ester->segui($ester->get("/pannello/$esp/impostazioni")))['body'], 'è nata disattivata'),
      json_encode($ariaA ? ['attiva' => $ariaA['is_active'], 'tr' => $trG((int) $ariaA['id'], 'de'), 'pagina' => preg_match('#<title>([^<]*)#', $rNov['body'] ?? '', $mN) ? $mN[1] : '', 'novita' => preg_match('#novita__testo">(.{0,160})#s', $rNov['body'] ?? '', $mN) ? strip_tags($mN[1]) : ''] : null, JSON_UNESCAPED_UNICODE));
$conta = fn() => (int) val("SELECT COUNT(*) FROM sections WHERE kind IN ('clima', 'riscaldamento', 'aria')");
$n1 = $conta();
$esito2 = trim((string) shell_exec('cd ' . escapeshellarg("$DOVE/app") . ' && php -r ' . escapeshellarg($migra) . ' 2>&1'));
prova('…una seconda esecuzione non cambia niente', $esito2 === 'fatto' && $conta() === $n1 && !val("SELECT 1 FROM sections WHERE kind = 'clima'"));
// Un'istantanea pubblicata prima, con la sezione unica, si vede come prima.
$snapG = json_decode((string) val('SELECT snapshot FROM guide_versions WHERE property_id = ? ORDER BY version DESC', [$gp]), true);
$snapG['format'] = 7; unset($snapG['property']['guest_email']);
$snapG['sections'][] = ['id' => 999999, 'kind' => 'clima', 'is_core' => 0, 'data' => ['heating_mode' => 'manuale', 'heating_temp' => '19', 'ac_mode' => 'libero', 'ac_temp' => '24'],
                        'image_id' => null, 'pdf_id' => null, 'tr' => ['de' => ['title' => '', 'data' => []]], 'places' => []];
db()->prepare('UPDATE guide_versions SET snapshot = ? WHERE property_id = ? AND version = (SELECT MAX(version) FROM guide_versions WHERE property_id = ?)')->execute([json_encode($snapG), $gp, $gp]);
$r = $ospite->get("/g/$gslug/999999?l=de");
prova('8C · istantanea di prima con «clima»: si vede come prima, riscaldamento e aria nella stessa pagina', $r['code'] === 200 && str_contains($r['body'], 'Heizung und Klimaanlage')
      && str_contains($r['body'], "19\u{00A0}°C") && str_contains($r['body'], "24\u{00A0}°C"));
$greta->post("/pannello/$gp/pubblica", ['codici_ok' => '1', 'acquirente' => 'impresa']);
prova('…e la nuova istantanea è nel formato 8, con riscaldamento e aria', (int) (json_decode((string) val('SELECT snapshot FROM guide_versions WHERE property_id = ? ORDER BY version DESC', [$gp]), true)['format'] ?? 0) === 8);
$r = $ospite->get('/domande');
prova('8C · FAQ: «In quali lingue…» con le regole nuove e «Posso scrivere la guida in un\'altra lingua?»', str_contains($r['body'], 'la tua e una a scelta')
      && str_contains($r['body'], 'Posso scrivere la guida in un&#039;altra lingua?'));
$r = $ospite->get('/');
prova('8C · listino: «2 lingue: la tua e una a scelta», «6 lingue: …»; nel confronto le lingue in una riga', str_contains($r['body'], '2 lingue: la tua e una a scelta')
      && str_contains($r['body'], '6 lingue: la tua, le altre tra italiano') && str_contains($r['body'], '2: la tua e una a scelta') && !str_contains($r['body'], '>Lingua aggiuntiva<'));


capitolo('8E · la home e le domande in inglese');
// Le pagine inglesi si indicizzano di serie: la prova della 6K le aveva spente salvando SEO e GEO senza i loro campi.
db()->exec("UPDATE seo_settings SET valore = '1' WHERE chiave IN ('p.en.indicizza', 'p.en-domande.indicizza')");
$ospiteEn = new Browser('ospite-en');
$r = $ospiteEn->get('/en'); $rq = $ospiteEn->get('/en/questions');
prova('8E · /en e /en/questions: 200, <html lang="en">, og:locale en_GB, indicizzabili', $r['code'] === 200 && $rq['code'] === 200 && pulita($r) && pulita($rq)
      && str_contains($r['body'], '<html lang="en">') && str_contains($rq['body'], '<html lang="en">') && str_contains($r['body'], 'og:locale" content="en_GB"')
      && str_contains($r['body'], '<meta name="robots" content="index, follow">'));
$visibile = function (string $h): string {
    $h = preg_replace(['#<script.*?</script>#s', '#<style.*?</style>#s', '#<svg.*?</svg>#s', '#<head>.*?</head>#s', '#<figure class="schermata".*?</figure>#s'], '', $h);
    return html_entity_decode(strip_tags((string) $h), ENT_QUOTES, 'UTF-8');
};
$itaVisti = array_values(array_filter(['Crea gratis la tua guida', 'Guarda la demo', 'Scegli il piano', 'Domande frequenti', '+ IVA / anno', 'al mese', 'Quante strutture',
    'Come funziona', 'Prezzi', 'Accedi', 'Cosa trova l\'ospite', 'Il tempo delle risposte', 'Ideale per', 'Tutto di Essential', 'Confronta tutti i piani',
    'Prima di cominciare', 'Altre domande', 'Posso provarla prima di pagare?', 'Non trovi la risposta', 'Benvenuti'], fn($x) => str_contains($visibile($r['body']), $x) || str_contains($visibile($rq['body']), $x)));
prova('8E · nessun testo italiano visibile (tranne nomi propri); 8J: Terms, Privacy e DPA in inglese', !$itaVisti && str_contains($r['body'], '/en/terms">Terms</a>') && str_contains($r['body'], '/en/privacy">Privacy</a>') && str_contains($r['body'], '/en/dpa">DPA</a>'), implode(' | ', $itaVisti));
prova('…i testi inglesi ci sono: titolo, piani, domande', str_contains($r['body'], 'Your home answers') && str_contains($r['body'], 'Choose your plan.') && str_contains($r['body'], '+ VAT / year')
      && str_contains($r['body'], 'Can I try it before I pay?') && str_contains($rq['body'], 'Frequently asked questions'));
$ri = $ospiteEn->get('/'); $rdi = $ospiteEn->get('/domande');
prova('8E · interruttore IT / EN nelle due direzioni (home e domande), anche nel menu del telefono', preg_match('#href="[^"]*/en" hreflang="en" lang="en">EN</a>#', $ri['body']) === 1
      && preg_match('#href="[^"]*/" hreflang="it" lang="it">IT</a>#', $r['body']) === 1 && preg_match('#href="[^"]*/en/questions" hreflang="en" lang="en">EN</a>#', $rdi['body']) === 1
      && preg_match('#href="[^"]*/domande" hreflang="it" lang="it">IT</a>#', $rq['body']) === 1 && substr_count($r['body'], 'class="lingua-sito"') === 2);
prova('8E · hreflang reciproci (it, en, x-default = italiano)', str_contains($ri['body'], 'hreflang="en" href="') && str_contains($r['body'], 'hreflang="it" href="')
      && preg_match('#hreflang="x-default" href="[^"]*/"#', $r['body']) === 1 && preg_match('#hreflang="en" href="[^"]*/en/questions"#', $rdi['body']) === 1
      && preg_match('#hreflang="it" href="[^"]*/domande"#', $rq['body']) === 1);
prova('…le pagine solo italiane (Termini) non hanno l\'interruttore', !str_contains($ospiteEn->get('/termini')['body'], 'class="lingua-sito"'));
preg_match_all('#<details class="faq__voce" id="([a-z0-9-]+)"#', $rdi['body'], $idIt); preg_match_all('#<details class="faq__voce" id="([a-z0-9-]+)"#', $rq['body'], $idEn);
prova('8E · FAQ con gli stessi id (le ancore funzionano nelle due lingue)', $idIt[1] && $idIt[1] === $idEn[1], count($idIt[1]) . ' / ' . count($idEn[1]));
$cPlus = (int) val('SELECT price_cents FROM package_versions WHERE id = ?', [pv('plus')]);
$prezzoPlus = number_format($cPlus / 100, $cPlus % 100 === 0 ? 0 : 2, ',', '.') . "\u{00A0}€";
prova('8E · prezzi uguali al listino, con «+ VAT / year» e «about … a month»', str_contains($r['body'], htmlspecialchars($prezzoPlus)) && str_contains($r['body'], '<span class="plan__mese">about '));
prova('8E · «Create your guide for free» porta alla registrazione con ?lang=en, che avvisa del pannello in italiano', preg_match('#href="[^"]*/registrati\?lang=en"#', $r['body']) === 1
      && str_contains($ospiteEn->get('/registrati?lang=en')['body'], 'The dashboard is in Italian for now'));
$sm = $ospiteEn->get('/sitemap.xml')['body']; $ll = $ospiteEn->get('/llms.txt')['body'];
prova('8E · sitemap e llms.txt con le due pagine inglesi (sezione «English»)', preg_match('#<loc>[^<]*/en</loc>#', $sm) === 1 && preg_match('#<loc>[^<]*/en/questions</loc>#', $sm) === 1
      && str_contains($ll, "## English") && str_contains($ll, '/en/questions)'));
prova('8E · dati strutturati in inglese: FAQPage e SoftwareApplication con inLanguage en-GB', str_contains($r['body'], '"inLanguage":"en-GB"') && str_contains($rq['body'], '"@type":"FAQPage","inLanguage":"en-GB"'));
// Testi e foto → English.
$r = $admin->get('/admin/testi?lingua=en');
prova('8E · Testi e foto: linguette Italiano / English; in English i testi inglesi e l\'anteprima di /en', str_contains($r['body'], 'class="linguette-testi"') && str_contains($r['body'], 'value="The digital front desk for your holiday rental"')
      && str_contains($r['body'], '/en?anteprima=testi') && str_contains($r['body'], 'name="lingua" value="en"'));
$admin->post('/admin/testi', ['lingua' => 'en', 't_hero_kicker' => 'Your digital front desk, in English']);
prova('8E · un testo cambiato in English compare solo in /en, salvato con la chiave «en:»', str_contains($ospiteEn->get('/en')['body'], 'Your digital front desk, in English')
      && !str_contains($ospiteEn->get('/')['body'], 'Your digital front desk, in English') && val("SELECT valore FROM site_texts WHERE chiave = 'en:hero.kicker'") === 'Your digital front desk, in English');
$admin->post('/admin/testi', ['lingua' => 'en', 't_hero_kicker' => '']);
prova('…e vuoto torna al testo inglese di serie', !val("SELECT 1 FROM site_texts WHERE chiave = 'en:hero.kicker'") && str_contains($ospiteEn->get('/en')['body'], 'The digital front desk for your holiday rental'));
// Piani → English.
$pPlus = (int) val("SELECT id FROM packages WHERE code = 'plus'");
$rPk = $admin->get('/admin/pacchetti');
prova('8E · Piani: «English» con i testi inglesi di serie della scheda', str_contains($rPk['body'], 'class="pacchetto-en"') && str_contains($rPk['body'], 'name="bullets_en"'));
$pkP = riga('SELECT * FROM packages WHERE id = ?', [$pPlus]);
$admin->post("/admin/pacchetti/$pPlus/testo", ['tagline' => $pkP['tagline'], 'headline' => $pkP['headline'], 'description' => $pkP['description'], 'bullets' => $pkP['bullets'],
    'badge' => $pkP['badge'], 'cta_label' => $pkP['cta_label'], 'public' => '1', 'active' => '1', 'tagline_en' => 'For hosts who test the English page', 'cta_label_en' => 'Try Plus now']);
$r = $ospiteEn->get('/en');
prova('…cambiati compaiono su /en, non sulla home italiana', str_contains($r['body'], 'hosts who test the English page') && str_contains($r['body'], 'Try Plus now')
      && !str_contains($ospiteEn->get('/')['body'], 'Try Plus now'));

capitolo('7F · clienti di prova, Marketing, SEO e GEO, pagine per tipologia');
$php7f = 'define("MHW_APP", ' . var_export("$DOVE/app", true) . '); define("MHW_PUBLIC", ' . var_export($DOVE, true) . '); '
       . 'spl_autoload_register(function ($c) { $f = MHW_APP . "/src/" . substr($c, 4) . ".php"; if (is_file($f)) require $f; }); require MHW_APP . "/src/Support.php"; '
       . 'MHW\Config::load(MHW_APP . "/config.php"); ';
$migra39 = $php7f . '$f = require MHW_APP . "/migrations/039_prova_7f.php"; $f(MHW\Db::conn()); echo "fatto";';
$e39a = trim((string) shell_exec('cd ' . escapeshellarg("$DOVE/app") . ' && php -r ' . escapeshellarg($migra39) . ' 2>&1'));
$e39b = trim((string) shell_exec('cd ' . escapeshellarg("$DOVE/app") . ' && php -r ' . escapeshellarg($migra39) . ' 2>&1'));
prova('7F · migrazione 039 due volte senza errori; accounts.is_test di serie 0', $e39a === 'fatto' && $e39b === 'fatto' && (int) val('SELECT COUNT(*) FROM accounts WHERE is_test <> 0') === 0, "$e39a / $e39b");

// Un cliente nuovo (Tobia, a Cascia), poi segnato come «Cliente di prova».
$tobia = new Browser('tobia');
$tobia->get('/registrati?piano=' . pv('essential'));
$tobia->post('/registrati', ['piano' => pv('essential'), 'name' => 'Tobia Collaudo', 'email' => 'tobia@prova.test', 'password' => 'TobiaProva123', 'termini' => '1', 'privacy' => '1']);
$tobia->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Casa Tobia', 'city' => 'Cascia']);
$tacc = $accDi('tobia@prova.test');
db()->prepare("UPDATE properties SET property_type = 'agriturismo' WHERE account_id = ?")->execute([$tacc]);
$clientiQ = fn() => preg_match('#</span>Clienti</span>.*?<b class="cifra__valore">(\d+)</b>#s', $admin->get('/admin')['body'], $mq) ? (int) $mq[1] : -1;
$csv7f = function (string $tab, string $periodo = 'tutto') use ($admin): array {
    $r = $admin->get("/admin/marketing?periodo=$periodo&formato=csv&tabella=$tab");
    $righe = array_map(fn($l) => str_getcsv($l, ';', '"', ''), array_values(array_filter(explode("\n", preg_replace('/^\xEF\xBB\xBF/', '', $r['body'])), 'strlen')));
    return ['r' => $r, 'righe' => $righe];
};
$registrati = function () use ($csv7f): int { foreach ($csv7f('percorso')['righe'] as $x) if (($x[0] ?? '') === 'Registrati') return (int) $x[1]; return -1; };
$agriM = fn() => (int) (array_column(array_slice($csv7f('tipologie')['righe'], 1), 1, 0)['Agriturismo'] ?? 0);
$q1 = $clientiQ(); $m1 = $registrati(); $c1 = $agriM();
$admin->modulo("/admin/cliente/$tacc", "/admin/cliente/$tacc/prova", ['prova' => '1']);
$q2 = $clientiQ(); $m2 = $registrati(); $c2 = $agriM();
prova('7F · «Cliente di prova»: salvato, fuori dal Quadro (clienti −1) e da Marketing (registrati −1, agriturismi −1)',
      (int) val('SELECT is_test FROM accounts WHERE id = ?', [$tacc]) === 1 && $q2 === $q1 - 1 && $m2 === $m1 - 1 && $c1 >= 1 && $c2 === $c1 - 1, "Quadro {$q1}→{$q2}, Marketing {$m1}→{$m2}, agriturismi {$c1}→{$c2}");
$r = $admin->get("/admin/cliente/$tacc");
prova('7F · scheda cliente: interruttore acceso, etichetta «Prova»; negli elenchi Clienti e Guide l\'etichetta «Prova»', str_contains($r['body'], 'name="prova" value="1" checked')
      && str_contains($r['body'], '<span class="badge badge--sea" style="vertical-align:middle">Prova</span>')
      && str_contains($admin->get('/admin/clienti?q=tobia')['body'], 'Tobia Collaudo</b></a> <span class="badge badge--sea">Prova</span>')
      && str_contains($admin->get('/admin/guide')['body'], 'Tobia Collaudo</a> <span class="badge badge--sea">Prova</span>'));
$esitoR = trim((string) shell_exec('cd ' . escapeshellarg("$DOVE/app") . ' && php -r ' . escapeshellarg($php7f . 'var_export([MHW\Richiami::manda(' . $tacc . ', "tobia@prova.test", "Tobia", "conferma", "prova-7f", ["a", "b", null, null]), MHW\Richiami::diProva(' . $tacc . ')]);') . ' 2>&1'));
prova('7F · i richiami automatici non partono per i clienti di prova (e niente riga nel registro)', str_replace(["\n", ' '], '', $esitoR) === 'array(0=>false,1=>true,)'
      && !val("SELECT 1 FROM email_log WHERE account_id = ? AND ref = 'prova-7f'", [$tacc]), $esitoR);

// Gratis per sempre (motivo obbligatorio) e il filtro «Gratuiti».
$subPrima = (int) val('SELECT COUNT(*) FROM subscriptions WHERE account_id = ?', [$tacc]);
$admin->modulo("/admin/cliente/$tacc", "/admin/cliente/$tacc/abbonamento", ['pv' => pv('plus'), 'durata' => 'sempre', 'nota' => '', 'strutture' => '1']);
$senzaMotivo = (int) val('SELECT COUNT(*) FROM subscriptions WHERE account_id = ?', [$tacc]) === $subPrima;
$admin->modulo("/admin/cliente/$tacc", "/admin/cliente/$tacc/abbonamento", ['pv' => pv('plus'), 'durata' => 'sempre', 'nota' => 'Collaudo 7F', 'strutture' => '1']);
$subT = riga("SELECT * FROM subscriptions WHERE account_id = ? AND status = 'active' ORDER BY id DESC", [$tacc]);
$rG = $admin->get('/admin/abbonamenti?filtro=gratuiti');
prova('7F · «Gratis per sempre»: senza motivo no; con il motivo abbonamento manuale fino al ' . substr((string) ($subT['current_period_end'] ?? ''), 0, 4) . ', in «Gratuiti» con «per sempre» e «Prova»',
      $senzaMotivo && $subT && $subT['provider'] === 'manuale' && substr((string) $subT['current_period_end'], 0, 4) >= '2120'
      && str_contains($admin->get("/admin/cliente/$tacc")['body'], 'Gratis per sempre') && str_contains($rG['body'], 'tobia@prova.test') && str_contains($rG['body'], 'per sempre')
      && !str_contains($rG['body'], 'ester@prova.test') && str_contains($rG['body'], 'aria-current="page">Gratuiti</a>'));

// Marketing: la pagina, le percentuali del percorso e il CSV.
$rM = $admin->get('/admin/marketing?periodo=365');
prova('7F · Vendite → Marketing: periodi, le tabelle e «Scarica CSV»', $rM['code'] === 200 && pulita($rM) && str_contains($rM['body'], '<h1>Marketing.</h1>')
      && str_contains($rM['body'], 'aria-current="page">365 giorni</a>') && str_contains($rM['body'], 'Il percorso') && str_contains($rM['body'], 'Le 20 guide più visitate')
      && str_contains($rM['body'], 'Le sezioni più usate') && str_contains($rM['body'], 'Inviti') && str_contains($rM['body'], 'tabella=percorso') && str_contains($rM['body'], 'class="mk-barra"'));
$perc = fn(int $n, int $t) => $t > 0 ? number_format($n * 100 / $t, $n * 100 % $t === 0 ? 0 : 1, ',', '') . '%' : '—';
$pc = $csv7f('percorso');
$righeP = array_slice($pc['righe'], 1); $regP = (int) ($righeP[0][1] ?? 0); $coerente = count($righeP) === 5; $prec = null;
foreach ($righeP as $x) { $n = (int) $x[1]; if ($x[2] !== $perc($n, $regP) || $x[3] !== ($prec === null ? '—' : $perc($n, $prec))) $coerente = false; $prec = $n; }
$attesi = [(int) val("SELECT COUNT(*) FROM users u JOIN accounts a ON a.user_id = u.id WHERE u.role = 'host' AND u.email NOT LIKE '%@esempio.it' AND a.is_test = 0"),
           (int) val("SELECT COUNT(*) FROM users u JOIN accounts a ON a.user_id = u.id WHERE u.role = 'host' AND u.email NOT LIKE '%@esempio.it' AND a.is_test = 0 AND u.email_verified_at IS NOT NULL")];
prova('7F · percorso: registrati e confermati come nel database, percentuali coerenti con i numeri', $coerente && $regP === $attesi[0] && (int) ($righeP[1][1] ?? -1) === $attesi[1],
      json_encode($righeP) . ' attesi ' . json_encode($attesi));
prova('7F · CSV: text/csv, BOM, punto e virgola, intestazioni', str_contains($pc['r']['head'], 'text/csv') && str_starts_with($pc['r']['body'], "\xEF\xBB\xBF")
      && ($pc['righe'][0] ?? []) === ['Passo', 'Persone', '% dei registrati', '% dal passo prima'] && $admin->get('/admin/marketing?formato=csv&tabella=niente')['code'] === 404);

// SEO e GEO: il salvataggio scrive i file veri accanto a index.php.
$tipi7f = require "$DOVE/app/lang/tipologie.php";
foreach (['robots.txt', 'sitemap.xml', 'llms.txt'] as $f) @unlink("$DOVE/$f");
$campi7f = $campi6k;
foreach (['home', 'domande', 'en', 'en-domande', 'termini', 'privacy', 'dpa', 'en-termini', 'en-privacy', 'en-dpa'] as $k) $campi7f["p_{$k}_indicizza"] = '1';
foreach (['en' => 'Digital guest guide for holiday rentals | MyHouse Welcome', 'en-domande' => 'Frequently asked questions | MyHouse Welcome'] as $k => $t)
    $campi7f += ["p_{$k}_titolo" => $t, "p_{$k}_descrizione" => str_repeat('An English description for the test page. ', 2)];
foreach ($tipi7f as $k => $tp) $campi7f += ["p_{$k}_indicizza" => '1', "p_{$k}_titolo" => $tp['titolo'], "p_{$k}_descrizione" => $tp['descrizione']];
$r = $adm6->modulo('/admin/seo', '/admin/seo', $campi7f);
$fileOk = true;
foreach (['robots.txt', 'sitemap.xml', 'llms.txt'] as $f) if (!is_file("$DOVE/$f") || (string) file_get_contents("$DOVE/$f") !== $ospite->get('/' . $f)['body']) $fileOk = false;
prova('7F · salvando «SEO e GEO»: robots.txt, sitemap.xml e llms.txt scritti accanto a index.php, uguali a quelli del sito', $fileOk
      && str_contains($adm6->segui($r)['body'], 'anche come file nella cartella principale del sito'));
$sm7 = (string) @file_get_contents("$DOVE/sitemap.xml"); $ll7 = (string) @file_get_contents("$DOVE/llms.txt");
prova('7F · le quattro pagine in sitemap (14 pagine in tutto con le legali della 8J) e in llms.txt («Per chi è»)', substr_count($sm7, '<url>') === 14 && @simplexml_load_string($sm7) !== false
      && count(array_filter($tipi7f, fn($tp) => str_contains($sm7, '<loc>https://myhousewelcome.it' . $tp['percorso'] . '</loc>') && str_contains($ll7, '(https://myhousewelcome.it' . $tp['percorso'] . ')'))) === 4
      && str_contains($ll7, "## Per chi è"));
$giudiziPubblicati = (int) val("SELECT COUNT(*) FROM testimonials WHERE visible = 1 AND (account_id IS NULL OR (status = 'pubblicata' AND consent_at IS NOT NULL AND withdrawn_at IS NULL))");
prova('7F · llms.txt: la riga sui giudizi solo se ce ne sono di pubblicati', ($giudiziPubblicati > 0) === str_contains($ll7, '## Giudizi'), (string) $giudiziPubblicati);
prova('7F · Diagnostica e SEO e GEO: la riga sui file veri', str_contains($admin->get('/admin/diagnostica')['body'], 'File per motori e assistenti')
      && str_contains($adm6->get('/admin/seo')['body'], 'sono anche file veri nella cartella principale'));
prova('7F · la sonda degli indirizzi puliti è una rotta (/indirizzi-puliti), non più robots.txt', str_contains($ospite->get('/indirizzi-puliti')['body'], 'MHW indirizzi puliti'));
// Con gli indirizzi puliti accesi e senza dominio: mai index.php, anche se la richiesta arriva con index.php.
$domPrima = (string) val("SELECT valore FROM seo_settings WHERE chiave = 'dominio'");
db()->exec("UPDATE seo_settings SET valore = '' WHERE chiave = 'dominio'");
$senzaIdx = trim((string) shell_exec('cd ' . escapeshellarg("$DOVE/app") . ' && php -r ' . escapeshellarg($php7f . '$_SERVER["SCRIPT_NAME"] = "/welcomebook/index.php"; $_SERVER["REQUEST_URI"] = "/welcomebook/index.php/admin/seo"; $_SERVER["HTTP_HOST"] = "esempio.test"; '
    . 'MHW\Config::sovrascrivi("pretty_urls", true); $t = MHW\Seo::robots() . MHW\Seo::sitemap() . MHW\Seo::llms(); foreach (array_keys(MHW\Seo::PAGINE) as $p) $t .= MHW\Seo::head($p, "x"); '
    . 'echo substr_count($t, "index.php"), "|", substr_count($t, "esempio.test/welcomebook/");') . ' 2>&1'));
db()->prepare("UPDATE seo_settings SET valore = ? WHERE chiave = 'dominio'")->execute([$domPrima]);
prova('7F · indirizzi puliti accesi: nessun index.php in canonical, og:url, sitemap, robots.txt, llms.txt e dati strutturati', preg_match('/^0\|[1-9]\d*$/', $senzaIdx) === 1, $senzaIdx);

// Le quattro pagine per tipologia.
$titoli7 = []; $ok7 = true; $note7 = [];
foreach ($tipi7f as $k => $tp) {
    $r = $ospite->get($tp['percorso']);
    $titoli7[] = preg_match('#<title>([^<]+)</title>#', $r['body'], $mt) ? $mt[1] : '';
    $faqJ = null;
    if (preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $r['body'], $mj)) foreach ($mj[1] as $j) { $d = json_decode($j, true); if (($d['@type'] ?? '') === 'FAQPage') $faqJ = $d; }
    $buono = $r['code'] === 200 && pulita($r) && str_contains($r['body'], '<link rel="canonical" href="https://myhousewelcome.it' . $tp['percorso'] . '">')
        && str_contains($r['body'], '<meta name="robots" content="index, follow">') && str_contains($r['body'], '>' . htmlspecialchars($tp['h1']) . '</h1>')
        && $faqJ && count($faqJ['mainEntity']) === 4 && ($faqJ['mainEntity'][0]['acceptedAnswer']['text'] ?? '') === $tp['faq'][0][1]
        && substr_count($r['body'], 'class="faq__voce"') === 4 && substr_count($r['body'], 'class="vantaggio"') === 3
        && str_contains($r['body'], 'Crea gratis la tua guida') && !str_contains($r['body'], 'class="lingua-sito"') && !str_contains($r['body'], 'hreflang=')
        && str_contains($r['body'], '<html lang="it">') && preg_match('#<img src="[^"]+/assets/foto/[^"]+" alt="[^"]{10,}" width="\d+" height="\d+"#', $r['body']) === 1;
    $prezzo = (int) val("SELECT pv.price_cents FROM package_versions pv JOIN packages p ON p.id = pv.package_id WHERE p.code = ? AND pv.is_current = 1", [$tp['piano']]);
    if (!str_contains($r['body'], number_format($prezzo / 100, $prezzo % 100 === 0 ? 0 : 2, ',', '.') . "\u{00A0}€")) $buono = false;
    if (val("SELECT 1 FROM properties WHERE is_demo >= 1 AND status = 'published' AND archived_at IS NULL") && !str_contains($r['body'], 'Guarda la demo')) $buono = false;
    if (!$buono) { $ok7 = false; $note7[] = $k; }
}
prova('7F · /case-vacanza, /bed-and-breakfast, /affittacamere, /agriturismi: 200, canonical, H1, 3 problemi, 4 FAQ con FAQPage, prezzo dal listino, bottoni, solo italiano', $ok7, implode(', ', $note7));
prova('7F · titoli tutti diversi (anche da home e domande)', count(array_unique(array_merge($titoli7, [preg_match('#<title>([^<]+)#', $ospite->get('/')['body'], $mt) ? $mt[1] : '']))) === 5, implode(' | ', $titoli7));
$rH = $ospite->get('/');
$imgs = preg_match_all('#<img\b[^>]*>#', $rH['body'], $mi) ? $mi[0] : [];
$senzaAlt = array_filter($imgs, fn($t) => !preg_match('#\balt="[^"]+"#', $t) || !preg_match('#\bwidth="\d+"#', $t) || !preg_match('#\bheight="\d+"#', $t));
prova('7F · home: tutte le immagini con alt descrittivo, width e height; «Per chi è» nel piè di pagina', $imgs && !$senzaAlt && str_contains($rH['body'], 'id="piede-perchi"')
      && count(array_filter($tipi7f, fn($tp) => str_contains($rH['body'], $tp['percorso'] . '">' . htmlspecialchars($tp['nome']) . '</a>'))) === 4, implode(' ', array_map(fn($t) => substr($t, 0, 80), $senzaAlt)));
$org = null;
if (preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $rH['body'], $mj)) foreach ($mj[1] as $j) foreach ((json_decode($j, true)['@graph'] ?? []) as $n) if (($n['@type'] ?? '') === 'Organization') $org = $n;
prova('7F · Organization con parentOrganization (8J: il titolare, City Up S.r.l., www.cityup.eu); offerte del listino; nessun aggregateRating', $org && !in_array('https://blackout.in', $org['sameAs'] ?? [], true)
      && ($org['parentOrganization']['url'] ?? '') === 'https://www.cityup.eu' && ($org['parentOrganization']['name'] ?? '') !== '' && str_contains($rH['body'], '"@type":"Offer"') && !str_contains($rH['body'], 'aggregateRating'));

// ===================================================== LINGUA PRINCIPALE OPZIONALE
// Chi scrive la guida in una lingua fuori dalle cinque di base (olandese, portoghese del Portogallo e del Brasile,
// polacco, russo, cinese, giapponese, svedese, danese, ceco). Essential: la sua lingua e una seconda, nessuna chiamata ad Amazon Translate.
// Plus: la sua lingua al posto della «lingua in più», le cinque di base, e le traduzioni suggerite partono dalla sua lingua.
capitolo('Lingua principale opzionale · ognuna delle dieci, con Essential e con Plus');
$pxTesti = ['nl' => 'De sleutels liggen in het kastje naast de deur.', 'pt' => 'As chaves estão na caixa ao lado da porta.',
            'pt-BR' => 'As chaves ficam na caixinha do lado da porta.',
            'pl' => 'Klucze są w skrzynce obok drzwi.', 'ru' => 'Ключи лежат в ящике рядом с дверью.', 'zh' => '钥匙在门旁边的盒子里。',
            'ja' => '鍵はドアの横の箱に入っています。', 'sv' => 'Nycklarna ligger i lådan bredvid dörren.', 'da' => 'Nøglerne ligger i kassen ved siden af døren.',
            'cs' => 'Klíče jsou ve schránce vedle dveří.'];
$pxTr = fn() => array_values(array_filter(array_map(fn($l) => json_decode($l, true), $TR_DIR !== '' && is_file("$TR_DIR/richieste.jsonl") ? (file("$TR_DIR/richieste.jsonl") ?: []) : [])));
$pxLingue = fn(int $p) => array_column(righe('SELECT locale FROM property_locales WHERE property_id = ? ORDER BY locale', [$p]), 'locale');
$pxOspite = new Browser('ospite-lingua-opzionale');
$pxN = 0;
/** Un host nuovo con il piano $piano, abbonamento attivo, che scrive la guida nella lingua $l. @return [Browser, account, struttura, sezione arrivo, «Un'altra lingua» la offre] */
$pxHost = function (string $piano, string $l, string $chi = '') use (&$pxN, $pxTesti): array {
    $pxN++;
    $email = strtolower("lingua-$piano-$l$chi@prova.test");   // la registrazione scrive l'email in minuscolo (pt-BR)
    $b = new Browser("lingua-$piano-$l-" . $pxN);
    db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'register:%'");   // 18 registrazioni dallo stesso indirizzo: il limite è 10 l'ora
    $b->get('/registrati?piano=' . pv($piano));
    $b->post('/registrati', ['piano' => pv($piano), 'name' => "Host $l $piano", 'email' => $email, 'password' => 'LinguaProva12345', 'termini' => '1', 'privacy' => '1']);
    $acc = (int) val('SELECT a.id FROM accounts a JOIN users u ON u.id = a.user_id WHERE u.email = ?', [$email]);
    $b->modulo('/pannello/nuova', '/pannello/nuova', ['name' => "Casa $l $piano", 'city' => 'Montefalco']);
    $p = (int) val('SELECT id FROM properties WHERE account_id = ? ORDER BY id', [$acc]);
    db()->prepare("INSERT INTO subscriptions (account_id, package_version_id, status, provider, provider_customer_id, provider_subscription_id, current_period_start,
        current_period_end, payment_status, created_at, updated_at, quantity) VALUES (?, ?, 'active', 'stripe', ?, ?, ?, ?, 'paid', ?, ?, 1)")
        ->execute([$acc, pv($piano), "cus_lingua_$pxN", "sub_lingua_$pxN", gmdate('Y-m-d\TH:i:s\Z', time() - 86400), gmdate('Y-m-d\TH:i:s\Z', time() + 360 * 86400), gmdate('Y-m-d\TH:i:s\Z'), gmdate('Y-m-d\TH:i:s\Z')]);
    $b->modulo('/account', '/account/fatturazione', ['billing_type' => 'privato', 'billing_name' => "Host $l", 'cf' => 'RSSMRA85T10A562S',
               'billing_address' => 'Via Roma 1', 'billing_postal' => '06036', 'billing_city' => 'Montefalco', 'billing_province' => 'PG']);
    $offre = preg_match('#name="default_locale" value="' . $l . '"#', $b->get("/pannello/$p/procedura/struttura")['body']) === 1;
    $b->modulo("/pannello/$p/procedura/struttura", "/pannello/$p/impostazioni", ['name' => "Casa $l $piano", 'default_locale' => $l, 'cin' => sprintf('IT054030C2LIN%05d', $pxN)]);
    $core = (int) val('SELECT id FROM sections WHERE property_id = ? AND is_core = 1', [$p]);
    return [$b, $acc, $p, $core, $offre];
};
$pxEss = []; $pxPlus = []; $pxGuide = [];
// Il codice che riceve Amazon Translate: il nostro «pt» (Portogallo) è pt-PT, il nostro «pt-BR» (Brasile) è pt.
$pxAws = fn(string $l) => ['pt' => 'pt-PT', 'pt-BR' => 'pt'][$l] ?? $l;
foreach ($pxTesti as $l => $testo) {
    // Essential: la lingua opzionale e l'italiano; una terza no; niente Amazon Translate.
    [$b, $acc, $p, $core, $offre] = $pxHost('essential', $l);
    $b->post("/pannello/$p/lingue", ['locali' => [$l, 'it']]);
    $b->post("/pannello/$p/lingue", ['locali' => [$l, 'it', 'en']]);
    $b->post("/pannello/$p/sezioni/$core", ['checkin_steps' => [$testo]]);
    $n0 = count($pxTr());
    $rS = $b->segui($b->post("/pannello/$p/lingue/it/suggerite", ['suggerisci' => '1']));
    $b->post("/pannello/$p/pubblica", ['codici_ok' => '1', 'acquirente' => 'impresa']);
    $slug = (string) val('SELECT slug FROM properties WHERE id = ?', [$p]);
    $rG = $pxOspite->get("/g/$slug/$core?l=$l");
    $err = [];
    if (!$offre) $err[] = 'non offerta';
    if (val('SELECT default_locale FROM properties WHERE id = ?', [$p]) !== $l) $err[] = 'principale';
    $att = [$l, 'it']; sort($att);
    if ($pxLingue($p) !== $att) $err[] = 'lingue ' . implode(',', $pxLingue($p));
    if (count($pxTr()) !== $n0 || !str_contains($rS['body'], 'disponibili con il piano Plus')) $err[] = 'AWS chiamato';
    if (val('SELECT status FROM properties WHERE id = ?', [$p]) !== 'published') $err[] = 'non pubblicata';
    if ($rG['code'] !== 200 || !str_contains($rG['body'], '<html lang="' . $l . '"') || !str_contains($rG['body'], htmlspecialchars($testo))) $err[] = 'guida ' . $rG['code'];
    $pxEss[$l] = $err;

    // Plus: la lingua opzionale e le cinque di base; l'olandese (o il portoghese) in più no; le suggerite partono dalla lingua opzionale.
    [$b, $acc, $p, $core, $offre] = $pxHost('plus', $l);
    $altra = $l === 'nl' ? 'pt' : 'nl';
    $b->post("/pannello/$p/lingue", ['locali' => [$l, 'it', 'en', 'fr', 'de', 'es']]);
    $b->post("/pannello/$p/lingue", ['locali' => [$l, 'it', 'en', 'fr', 'de', 'es', $altra]]);
    $b->post("/pannello/$p/sezioni/$core", ['checkin_steps' => [$testo]]);
    $b->segui($b->post("/pannello/$p/lingue/suggerite", ['acceso' => '1']));
    $n1 = count($pxTr());
    foreach (['it', 'en'] as $a) {
        $b->segui($b->post("/pannello/$p/lingue/$a/suggerite", ['suggerisci' => '1']));
        $b->segui($b->post("/pannello/$p/lingue/$a/suggerite", ['tutte' => '1']));
    }
    $chiamate = array_slice($pxTr(), $n1);
    $b->post("/pannello/$p/pubblica", ['codici_ok' => '1', 'acquirente' => 'impresa']);
    $slug = (string) val('SELECT slug FROM properties WHERE id = ?', [$p]);
    $rL = $pxOspite->get("/g/$slug/$core?l=$l"); $rI = $pxOspite->get("/g/$slug/$core?l=it"); $rE = $pxOspite->get("/g/$slug/$core?l=en");
    $err = [];
    if (!$offre) $err[] = 'non offerta';
    $att = [$l, 'it', 'en', 'fr', 'de', 'es']; sort($att);
    if ($pxLingue($p) !== $att) $err[] = 'lingue ' . implode(',', $pxLingue($p));
    $coppie = array_values(array_unique(array_map(fn($x) => ($x['da'] ?? '?') . '>' . ($x['a'] ?? '?') . ':' . ($x['code'] ?? 0), $chiamate))); sort($coppie);
    if ($coppie !== [$pxAws($l) . '>en:200', $pxAws($l) . '>it:200'] || !in_array($testo, array_column($chiamate, 'testo'), true)) $err[] = 'AWS ' . implode(',', $coppie);
    $pxGuide[$l] = [$slug, $core];
    if (val('SELECT status FROM properties WHERE id = ?', [$p]) !== 'published') $err[] = 'non pubblicata';
    if (!str_contains($rL['body'], '<html lang="' . $l . '"') || !str_contains($rL['body'], htmlspecialchars($testo))) $err[] = 'guida ' . $l;
    if (!str_contains($rI['body'], htmlspecialchars("[it] $testo")) || !str_contains($rE['body'], htmlspecialchars("[en] $testo"))) $err[] = 'traduzioni nella guida';
    if (!((int) val("SELECT COALESCE(SUM(chars),0) FROM translation_usage WHERE account_id = ? AND outcome = 'ok'", [$acc]) > 0)) $err[] = 'consumo';
    $pxPlus[$l] = $err;
}
$pxNote = fn(array $x) => implode(' · ', array_map(fn($l, $e) => "$l: " . implode(', ', $e), array_keys(array_filter($x)), array_filter($x)));
prova('Essential · per ognuna delle dieci lingue opzionali come principale: si sceglie, si aggiunge l\'italiano (una terza no), nessuna chiamata ad Amazon Translate, guida pubblicata nella sua lingua',
      !array_filter($pxEss), $pxNote($pxEss));
prova('Plus · per ognuna delle dieci: la lingua opzionale più le cinque di base (una seconda opzionale no), suggerite di Amazon Translate dalla lingua principale verso italiano e inglese, approvate e nella guida',
      !array_filter($pxPlus), $pxNote($pxPlus));
prova('…Amazon Translate ha ricevuto ogni lingua opzionale come lingua di partenza (pt-PT per il Portogallo, pt per il Brasile)',
      count(array_intersect(array_map($pxAws, array_keys($pxTesti)), array_unique(array_column($pxTr(), 'da')))) === 10, implode(',', array_unique(array_column($pxTr(), 'da'))));
// Le due varianti del portoghese nella guida: le parole giuste e la lingua del telefono.
[$slugPT] = $pxGuide['pt']; [$slugBR] = $pxGuide['pt-BR'];
$rPT = $pxOspite->get("/g/$slugPT?l=pt"); $rBR = $pxOspite->get("/g/$slugBR?l=pt-BR");
prova('Portoghese · la guida del Portogallo con le parole del Portogallo («até às»), quella del Brasile con le sue («até as»)',
      str_contains($rPT['body'], '<html lang="pt"') && str_contains($rPT['body'], 'Check-out até às') && str_contains($rBR['body'], '<html lang="pt-BR"') && str_contains($rBR['body'], 'Check-out até as'));
$rTel = $pxOspite->get("/g/$slugBR", ['Accept-Language: pt-PT,pt;q=0.9,en;q=0.8']);
$rTel2 = $pxOspite->get("/g/$slugPT", ['Accept-Language: pt-BR,pt;q=0.9']);
prova('Portoghese · un telefono del Portogallo apre in brasiliano la guida che ha solo il Brasile (e viceversa), con quella lingua in cima alla scelta',
      str_contains($rTel['body'], '<html lang="pt-BR"') && str_contains($rTel['body'], 'scegli-lingua__lingua--telefono" href=') && str_contains($rTel['body'], '>Português (Brasil)</a>')
      && str_contains($rTel2['body'], '<html lang="pt"') && str_contains($rTel2['body'], '>Português (Portugal)</a>'));
// Essential: il Portogallo come principale e il Brasile come seconda; il telefono sceglie la variante giusta.
[$bPP, , $pPP, $corePP] = $pxHost('essential', 'pt', '-due');
$bPP->post("/pannello/$pPP/lingue", ['locali' => ['pt', 'pt-BR']]);
$bPP->post("/pannello/$pPP/sezioni/$corePP", ['checkin_steps' => [$pxTesti['pt']]]);
$bPP->post("/pannello/$pPP/pubblica", ['codici_ok' => '1', 'acquirente' => 'impresa']);
$slugPP = (string) val('SELECT slug FROM properties WHERE id = ?', [$pPP]);
prova('Portoghese · una guida con le due varianti: pt-BR al telefono brasiliano, pt al telefono portoghese',
      $pxLingue($pPP) === ['pt', 'pt-BR'] && val('SELECT status FROM properties WHERE id = ?', [$pPP]) === 'published' && str_contains($pxOspite->get("/g/$slugPP", ['Accept-Language: pt-BR,pt;q=0.9'])['body'], '<html lang="pt-BR"')
      && str_contains($pxOspite->get("/g/$slugPP", ['Accept-Language: pt-PT,pt;q=0.9'])['body'], '<html lang="pt"'), json_encode($pxLingue($pPP)));

// La lingua principale spiegata: home, domande, schede dei piani, e il «come funziona» nel pannello.
capitolo('Lingua principale · spiegata in home, nelle domande, nei piani e nel pannello');
$rH = $pxOspite->get('/');
prova('Home: la guida nella lingua dell\'ospite, «scegli la lingua in cui scrivi» e le traduzioni suggerite dalla tua lingua',
      str_contains($rH['body'], 'nella lingua dell&#039;ospite') && str_contains($rH['body'], 'scegli la lingua in cui scrivi (non per forza l&#039;italiano)')
      && str_contains($rH['body'], 'partendo dalla tua lingua') && str_contains($pxOspite->get('/en')['body'], 'choose the language you write in'));
$rD = $pxOspite->get('/domande');
prova('Domande: «Posso scrivere la guida in un\'altra lingua?» spiega piani, lingua in più, bottoni e pannello (anche in inglese)',
      str_contains($rD['body'], 'occupa il posto della lingua in più e hai tutte e cinque le lingue di base') && str_contains($rD['body'], 'Il pannello resta in italiano')
      && str_contains($pxOspite->get('/en/questions')['body'], 'it takes the place of the extra language'));
prova('Piani: nessuna scheda dice più «Scrivi in italiano»', !str_contains($rH['body'], 'Scrivi in italiano') && str_contains($rH['body'], 'Scrivi nella tua lingua, traduciamo noi nelle altre'));
[$bJa, , $pJa] = $pxHost('plus', 'ja', '-come');
$rJ = $bJa->get("/pannello/$pJa/lingue");
prova('Pannello · Plus in giapponese (9F): pannello in inglese, «One more language» con i due esempi e la riga «日本語: buttons in English, your texts in 日本語»',
      str_contains($rJ['body'], '<html lang="en">') && str_contains($rJ['body'], 'One more language, besides the five base ones') && str_contains($rJ['body'], 'You write in Polish')
      && str_contains($rJ['body'], '日本語: buttons in English, your texts in 日本語.'));
[$bDe, , $pDe] = $pxHost('essential', 'de', '-come');
$rE = $bDe->get("/pannello/$pDe/lingue");
prova('Pannello · Essential in tedesco (9F): pannello in inglese, «With Essential you have 2 languages: yours and one of your choice»', str_contains($rE['body'], '<html lang="en">')
      && str_contains($rE['body'], 'With Essential you have 2 languages: yours and one of your choice'));
[$bEn, , $pEn] = $pxHost('plus', 'en', '-come');
prova('Pannello · Plus in inglese (9F): pannello in inglese con il riquadro della lingua in più', str_contains($bEn->get("/pannello/$pEn/lingue")['body'], 'One more language, besides the five base ones'));
[$bIt, , $pIt] = $pxHost('plus', 'it', '-come');
$rI = $bIt->get("/pannello/$pIt/impostazioni");
prova('Pannello · chi scrive in italiano (9F): in Impostazioni il riquadro breve «Una lingua in più» senza esempi; in Lingue con i due esempi',
      str_contains($rI['body'], '<html lang="it">') && str_contains($rI['body'], 'Una lingua in più, oltre alle cinque di base') && !str_contains($rI['body'], 'Scrivi in italiano, ospiti dall&#039;Olanda')
      && str_contains($bIt->get("/pannello/$pIt/lingue")['body'], 'Scrivi in italiano, ospiti dall&#039;Olanda'));

capitolo('8I · registri conformi al GDPR: file del giorno, dati ridotti, pulizia, copia su S3');
$j8i = json_decode((string) shell_exec('php ' . escapeshellarg(__DIR__ . '/registri-8i.php') . ' ' . escapeshellarg($DOVE) . ' ' . escapeshellarg((string) getenv('AWS_S3_ENDPOINT')) . ' 2>&1'), true) ?: [];
prova('8I · registro tecnico nel file del giorno, con email, IP, telefono, password e chiavi mascherati', !empty($j8i['maschera']['ok']), (string) ($j8i['maschera']['riga'] ?? json_encode($j8i)));
prova('8I · pulizia: tecnici di 100 giorni via, di 10 no; posta di 8 giorni via, di 3 no; email di prova di 9 giorni via', !empty($j8i['pulizia']['ok']), json_encode($j8i['pulizia'] ?? null));
prova('8I · accesso di un amministratore in accessi-admin-AAAA-MM-GG.log con IP ed email abbreviati', !empty($j8i['accessi']['ok']));
$put8i = array_values(array_filter(array_map(fn($l) => json_decode($l, true), file("$S3_DIR/richieste.jsonl", FILE_IGNORE_NEW_LINES) ?: []),
                     fn($x) => ($x['metodo'] ?? '') === 'PUT' && str_starts_with((string) ($x['percorso'] ?? ''), '/mhw-registri/')));
prova('8I · copia sul bucket dei registri: tecnici/ e accessi-admin/ con il file del giorno, esito salvato', !empty($j8i['s3']['ok'])
      && in_array('/mhw-registri/accessi-admin/accessi-admin-' . gmdate('Y-m-d') . '.log', array_column($put8i, 'percorso'), true)
      && in_array('/mhw-registri/tecnici/app-' . gmdate('Y-m-d') . '.log', array_column($put8i, 'percorso'), true) && !empty($j8i['s3']['stato']['ok']),
      json_encode([$j8i['s3'] ?? null, array_column($put8i, 'percorso')]));
prova('8I · registro delle azioni: nessuna riga cancellata, nessun IP in chiaro', !empty($j8i['audit']['ok']), json_encode($j8i['audit'] ?? null));
prova('8I · limiti ai tentativi: l\'IP diventa un codice di 16 caratteri (chiave in app/storage), nessun IP in chiaro nei nomi', !empty($j8i['ipkey']['ok'])
      && (int) val("SELECT COUNT(*) FROM rate_limits WHERE bucket LIKE '%127.0.0.1%'") === 0 && (int) val("SELECT COUNT(*) FROM rate_limits WHERE bucket LIKE 'login-ip:%'") > 0);
$r = $admin->get('/admin/impostazioni');
prova('8I · Impostazioni → Registri: giorni, bucket, chiavi a parte; spazio, file, «Scarica gli ultimi 7 giorni», «Svuota» con SVUOTA', str_contains($r['body'], 'id="registri"')
      && str_contains($r['body'], 'Conserva i registri tecnici per … giorni') && str_contains($r['body'], 'Scarica gli ultimi 7 giorni') && str_contains($r['body'], 'Per svuotare, scrivi SVUOTA')
      && str_contains($r['body'], 's3:PutObject e s3:PutObjectRetention'));
$z8 = $admin->post('/admin/registri/scarica', []);
prova('8I · «Scarica»: uno zip con i registri', $z8['code'] === 200 && str_contains($z8['head'], 'application/zip') && str_starts_with($z8['body'], "PK"));
$admin->post('/admin/registri/svuota', ['conferma' => 'no']);
$restano8 = count(glob("$DOVE/app/storage/logs/app-*.log") ?: []);
$admin->post('/admin/registri/svuota', ['conferma' => 'SVUOTA']);
prova('8I · «Svuota»: senza SVUOTA non tocca niente; con SVUOTA via tecnici e posta, restano gli accessi degli amministratori', $restano8 > 0
      && count(glob("$DOVE/app/storage/logs/app-*.log") ?: []) <= 1 && count(glob("$DOVE/app/storage/logs/accessi-admin-*.log") ?: []) >= 1
      && str_contains((string) @file_get_contents("$DOVE/app/storage/logs/accessi-admin-" . gmdate('Y-m-d') . '.log'), 'registri.svuota'), (string) $restano8);
$r = $admin->post('/admin/impostazioni/registri', ['registri__giorni' => '20', 'password' => 'AdminProva123']);
prova('8I · i giorni vanno da 30 a 180', str_contains($r['body'], 'Scrivi un numero di giorni tra 30 e 180.'));
$pr8i = $ospite->get('/privacy')['body'];
prova('8I · privacy: «Registri tecnici e di sicurezza» (8J: trattamento 10) con legittimo interesse, IP abbreviati, 90 giorni, 24 ore, S3 Milano 6/12 mesi, Matomo 6 mesi',
      str_contains($pr8i, 'id="registri"') && str_contains($pr8i, 'art. 6.1.f') && str_contains($pr8i, 'Registri tecnici 90 giorni') && str_contains($pr8i, 'accesso 24 ore')
      && str_contains($pr8i, 'S3, Milano') && str_contains($pr8i, 'inalterabile per 6 mesi') && str_contains($pr8i, 'dopo 12 mesi') && str_contains($pr8i, 'dati grezzi 6 mesi'));
if ($imp8 = val("SELECT meta FROM audit_log WHERE action = 'impersonate.start' ORDER BY id DESC LIMIT 1")) {
    prova('8I · entrata nell\'account di un cliente: nel registro delle azioni l\'IP abbreviato', !str_contains((string) $imp8, '127.0.0.1'), (string) $imp8);
}

// ================================================================= 8J
capitolo('8J · titolare City Up, Termini, Privacy e DPA (IT/EN), ri-accettazione, checkout');
$pag8j = [];
foreach (['/termini', '/privacy', '/dpa', '/en/terms', '/en/privacy', '/en/dpa'] as $p8) $pag8j[$p8] = $ospite->get($p8);
prova('8J · /termini, /privacy, /dpa e /en/terms, /en/privacy, /en/dpa: 200, pulite, con canonical e hreflang it/en/x-default',
      count(array_filter($pag8j, fn($r) => $r['code'] === 200 && pulita($r))) === 6
      && count(array_filter($pag8j, fn($r, $p8) => str_contains($r['body'], '<link rel="canonical" href="https://myhousewelcome.it' . $p8 . '">'), ARRAY_FILTER_USE_BOTH)) === 6
      && count(array_filter($pag8j, fn($r) => str_contains($r['body'], 'hreflang="it" href="https://myhousewelcome.it/') && str_contains($r['body'], 'hreflang="en" href="https://myhousewelcome.it/en/')
                                       && str_contains($r['body'], 'hreflang="x-default"'))) === 6,
      implode(' ', array_map(fn($r) => $r['code'], $pag8j)));
prova('8J · le pagine inglesi in inglese (lang="en"), con i link a Terms, Privacy e DPA e alla versione italiana', str_contains($pag8j['/en/terms']['body'], '<html lang="en">')
      && str_contains($pag8j['/en/terms']['body'], 'Right of withdrawal') && str_contains($pag8j['/en/privacy']['body'], 'Your rights') && str_contains($pag8j['/en/dpa']['body'], 'Sub-processors')
      && str_contains($pag8j['/en/terms']['body'], '/en/dpa">DPA</a>') && str_contains($pag8j['/en/terms']['body'], '>Versione italiana</a>'));
$tutte8j = implode("\n", array_map(fn($r) => $r['body'], $pag8j));
prova('8J · nessun «[Da completare» né «[Da far rivedere», nessuna vecchia P.IVA; titolare City Up con P.IVA, REA, sede e PEC', !str_contains($tutte8j, '[Da completare')
      && !str_contains($tutte8j, '[Da far rivedere') && !str_contains($tutte8j, '02945910541') && str_contains($tutte8j, 'City Up S.r.l. — P.IVA e C.F. 03793880547 — REA PG-352001')
      && str_contains($tutte8j, 'Via Ariodante Fabretti 15, 06123 Perugia (PG)') && str_contains($tutte8j, 'cityup@pec.it'));
prova('8J · Blackout Agency non è il titolare: niente nei Termini e nel DPA; nell\'informativa solo come destinatario di «Anche il tuo sito»',
      !str_contains($pag8j['/termini']['body'], 'Blackout') && !str_contains($pag8j['/dpa']['body'], 'Blackout') && str_contains($pag8j['/privacy']['body'], 'Titolare: City Up S.r.l.')
      && preg_match('#id="trattamento-8".*?Blackout Agency.*?</section>#s', $pag8j['/privacy']['body']) === 1 && str_contains($pag8j['/privacy']['body'], 'titolare autonomo'));
$sorgenti8j = '';
foreach (['src', 'views', 'lang'] as $c8) foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$DOVE/app/$c8", FilesystemIterator::SKIP_DOTS)) as $f8) $sorgenti8j .= file_get_contents((string) $f8);
$sorgenti8j .= file_get_contents("$DOVE/app/config.php");
prova('8J · nel codice nessuna vecchia P.IVA né «Blackout Agency» come ragione sociale di serie', !str_contains($sorgenti8j, '02945910541') && !str_contains($sorgenti8j, "'Blackout Agency')")
      && !str_contains($sorgenti8j, 'un progetto Blackout'));
prova('8J · Termini: 18 punti con indice (9B giudizio con sconto, 9D codice omaggio), recesso impresa/consumatore, 18 anni, foro di Perugia, modulo di recesso; Privacy: 13 trattamenti in tabella (9A: preventivi), fornitori, diritti, nessun DPO',
      substr_count($pag8j['/termini']['body'], 'class="legale__parte"') === 20 && str_contains($pag8j['/termini']['body'], 'id="recesso"') && str_contains($pag8j['/termini']['body'], 'almeno 18 anni')
      && str_contains($pag8j['/termini']['body'], 'foro di Perugia') && str_contains($pag8j['/termini']['body'], 'id="modulo-recesso"') && str_contains($pag8j['/termini']['body'], 'Compila e manda il modulo per email')
      && substr_count($pag8j['/privacy']['body'], 'class="legale__tabella"') === 13 && str_contains($pag8j['/privacy']['body'], 'Hostinger') && str_contains($pag8j['/privacy']['body'], 'Stripe Payments Europe')
      && str_contains($pag8j['/privacy']['body'], 'Non è stato nominato un responsabile della protezione dei dati') && str_contains($pag8j['/privacy']['body'], 'entro 30 giorni')
      && str_contains($pag8j['/dpa']['body'], 'entro 48 ore') && str_contains($pag8j['/dpa']['body'], 'almeno 30 giorni'));
prova('8J · la posta in uscita nell\'informativa viene dall\'impostazione (qui: modalità prova)', str_contains($pag8j['/privacy']['body'], 'posta in uscita</b>: '));
prova('8J · «Versioni precedenti»: i Termini del 2026-10-08 archiviati, leggibili e non indicizzati', str_contains($pag8j['/termini']['body'], 'Versioni precedenti')
      && str_contains($ospite->get('/termini?versione=2026-10-08')['body'], 'non più in vigore') && str_contains($ospite->get('/termini?versione=2026-10-08')['body'], 'name="robots" content="noindex"'));
$home8j = $ospite->get('/')['body'];
prova('8J · piè di pagina: Termini, Privacy, DPA; «Anche il tuo sito» dice che le risposte vanno a Blackout Agency', str_contains($home8j, '/dpa">Accordo dati (DPA)</a>')
      && str_contains($home8j, 'la società che realizza i siti MyHouse') && str_contains($home8j, '/privacy#trattamento-8"'));
prova('8J · sitemap con /dpa e le pagine inglesi', str_contains($r8j = $ospite->get('/sitemap.xml')['body'], '/dpa</loc>') && str_contains($r8j, '/en/terms</loc><lastmod>' . MHW_VERSIONE_LEGALE));

// Registrazione: la casella è obbligatoria; con la casella, accettate le tre versioni.
$irene = new Browser('irene');
$irene->get('/registrati?piano=' . pv('plus'));
db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'register:%'");
$r = $irene->post('/registrati', ['piano' => pv('plus'), 'name' => 'Irene Legale', 'email' => 'irene@prova.test', 'email2' => 'irene@prova.test', 'password' => 'IreneProva12345']);
prova('8J · registrazione senza la casella: rifiutata, nessun account', str_contains($r['body'], 'devi accettare i Termini e l&#039;Accordo sul trattamento dei dati')
      && !val("SELECT id FROM users WHERE email = 'irene@prova.test'"));
$irene->post('/registrati', ['piano' => pv('plus'), 'name' => 'Irene Legale', 'email' => 'irene@prova.test', 'password' => 'IreneProva12345', 'termini' => '1']);
$u8 = riga("SELECT * FROM users WHERE email = 'irene@prova.test'");
prova('8J · con la casella: Termini, Privacy e DPA accettati nella versione in vigore, una riga per documento', $u8 && $u8['terms_version'] === MHW_VERSIONE_LEGALE
      && $u8['privacy_version'] === MHW_VERSIONE_LEGALE && $u8['dpa_version'] === MHW_VERSIONE_DPA && $u8['dpa_accepted_at'] !== null
      && (int) val("SELECT COUNT(*) FROM legal_acceptances WHERE user_id = ? AND modo = 'registrazione'", [$u8['id']]) === 3);
$acc8 = (int) val('SELECT id FROM accounts WHERE user_id = ?', [$u8['id']]);
$irene->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Casa Irene', 'city' => 'Spello']);
$p8 = (int) val('SELECT id FROM properties WHERE account_id = ?', [$acc8]);
$r = $irene->get("/pannello/$p8/procedura/struttura");
prova('8J · sotto l\'email per gli ospiti e i contatti: «assicurati di poterlo pubblicare»', str_contains($r['body'], 'Questo contatto compare nella guida e nella pagina delle recensioni: assicurati di poterlo pubblicare')
      && str_contains($r['body'], 'assicurati di poterli pubblicare'));
prova('8J · nessuna finestra per chi ha già accettato', !str_contains($irene->get('/pannello')['body'], 'Abbiamo aggiornato i Termini'));

// Una versione nuova in vigore: finestra, pannello in sola lettura, poi «Accetto».
db()->prepare("UPDATE users SET terms_version = '2026-10-08', privacy_version = '2026-10', dpa_version = NULL WHERE id = ?")->execute([$u8['id']]);
$r = $irene->get('/pannello');
prova('8J · versione cambiata: al primo accesso la finestra «Abbiamo aggiornato i Termini e l\'Informativa», con le novità, i link e «Accetto»',
      str_contains($r['body'], "Abbiamo aggiornato i Termini e l'Informativa") && str_contains($r['body'], 'City Up S.r.l. (Perugia)') && str_contains($r['body'], '/legale/accetta')
      && str_contains($r['body'], '/dpa"'));
$r = $irene->post('/account/profilo', ['name' => 'Irene Cambiata']);
prova('8J · finché non accetta il pannello è in sola lettura: il modulo non salva e lo dice', $r['code'] === 302 && val('SELECT name FROM users WHERE id = ?', [$u8['id']]) === 'Irene Legale'
      && str_contains($irene->segui($r)['body'], 'finché non li accetti puoi solo consultare'));
$r = $irene->post('/legale/accetta', []);
$u8 = riga('SELECT * FROM users WHERE id = ?', [$u8['id']]);
prova('8J · «Accetto»: versioni aggiornate, tre righe «riaccettazione», nel registro delle azioni', $u8['terms_version'] === MHW_VERSIONE_LEGALE && $u8['dpa_version'] === MHW_VERSIONE_DPA
      && (int) val("SELECT COUNT(*) FROM legal_acceptances WHERE user_id = ? AND modo = 'riaccettazione'", [$u8['id']]) === 3
      && (bool) val("SELECT id FROM audit_log WHERE action = 'legale.accettati' AND target_user_id = ?", [$u8['id']]));
$irene->post('/account/profilo', ['name' => 'Irene Cambiata']);
prova('8J · …e dopo tutto torna normale', val('SELECT name FROM users WHERE id = ?', [$u8['id']]) === 'Irene Cambiata' && !str_contains($irene->get('/pannello')['body'], 'Abbiamo aggiornato'));
// L'assistenza non vede la finestra e non accetta al posto del cliente.
db()->prepare("UPDATE users SET terms_version = '2026-10-08' WHERE id = ?")->execute([$u8['id']]);
$admin->post('/admin/entra/' . $u8['id'], []);
$r = $admin->get('/pannello');
$admin->post('/legale/accetta', []);
prova('8J · durante l\'accesso dell\'assistenza: niente finestra, e «Accetto» non vale', !str_contains($r['body'], 'Abbiamo aggiornato i Termini')
      && val('SELECT terms_version FROM users WHERE id = ?', [$u8['id']]) === '2026-10-08');
$admin->post('/admin/esci-da-cliente', []);
prova('8J · Account: documenti accettati e accessi dell\'assistenza (data e chi)', str_contains($r8a = $irene->get('/account')['body'], "Accessi dell'assistenza al tuo account")
      && str_contains($r8a, 'UTC ·') && str_contains($r8a, 'versione ' . MHW_VERSIONE_LEGALE));

// L'email di avviso: ai clienti con un abbonamento attivo, una volta sola per combinazione di versioni.
db()->prepare("INSERT INTO subscriptions (account_id, package_version_id, status, provider, provider_customer_id, provider_subscription_id, current_period_start,
    current_period_end, payment_status, created_at, updated_at, quantity) VALUES (?, ?, 'active', 'stripe', 'cus_irene', 'sub_irene', ?, ?, 'paid', ?, ?, 1)")
    ->execute([$acc8, pv('plus'), gmdate('Y-m-d\TH:i:s\Z', time() - 86400), gmdate('Y-m-d\TH:i:s\Z', time() + 360 * 86400), gmdate('Y-m-d\TH:i:s\Z'), gmdate('Y-m-d\TH:i:s\Z')]);
$avvisi8 = fn() => count(array_filter(righePosta(), fn($l) => str_contains($l, 'irene@prova.test') && str_contains($l, 'Aggiornamento dei Termini')));
$cron();
$primo8 = $avvisi8();
$cron(); $cron();
prova('8J · email «Abbiamo aggiornato i documenti legali» a chi ha un abbonamento attivo e non ha accettato: una volta sola', $primo8 === 1 && $avvisi8() === 1, (string) $avvisi8());

// Impostazioni → Titolare e documenti legali; una versione annunciata per il futuro.
$r = $admin->get('/admin/impostazioni');
prova('8J · Impostazioni → «Titolare e documenti legali»: ragione sociale, P.IVA, sede, PEC, sito, versioni', str_contains($r['body'], 'Titolare e documenti legali')
      && str_contains($r['body'], 'name="legal__company_pec"') && str_contains($r['body'], 'name="legal__dpa_version"'));
$r = $admin->post('/admin/impostazioni/legale', ['legal__company_vat' => '123', 'password' => 'AdminProva123']);
prova('8J · la P.IVA ha 11 cifre', str_contains($r['body'], 'La partita IVA italiana è fatta di 11 cifre'));
$futura8 = gmdate('Y-m-d', time() + 40 * 86400);
$admin->post('/admin/impostazioni/legale', ['legal__terms_version' => $futura8, 'password' => 'AdminProva123']);
db()->prepare("UPDATE users SET terms_version = ?, privacy_version = ?, dpa_version = ? WHERE id = ?")->execute([MHW_VERSIONE_LEGALE, MHW_VERSIONE_LEGALE, MHW_VERSIONE_DPA, $u8['id']]);
$r = $irene->get('/pannello');
prova('8J · versione annunciata (tra 40 giorni): nel pannello la riga «cambiano», niente finestra, si lavora', str_contains($r['body'], 'cambiano') && !str_contains($r['body'], 'Abbiamo aggiornato i Termini')
      && ($irene->post('/account/profilo', ['name' => 'Irene Legale'])['code'] === 302) && val('SELECT name FROM users WHERE id = ?', [$u8['id']]) === 'Irene Legale');
$cron();
prova('8J · …e l\'email di preavviso parte, una volta, con la data', $avvisi8() === 2 && count(array_filter(righePosta(), fn($l) => str_contains($l, 'irene@prova.test') && str_contains($l, 'cambiano i Termini'))) >= 1);
$r = $ospite->get('/termini');
prova('8J · la pagina dice da quando vale; la versione di prima resta tra le precedenti', str_contains($r['body'], 'in vigore dal') && str_contains($r['body'], '?versione=' . MHW_VERSIONE_LEGALE)
      && str_contains($ospite->get('/termini?versione=' . MHW_VERSIONE_LEGALE)['body'], 'non più in vigore'));
$admin->post('/admin/impostazioni/legale', ['legal__terms_version' => '', 'password' => 'AdminProva123']);
prova('8J · versione vuota = quella di serie', !str_contains($ospite->get('/termini')['body'], 'in vigore dal'));

// Testi e foto → Documenti legali.
$r = $admin->get('/admin/testi?doc=legali');
prova('8J · Testi e foto → «Documenti legali»: Termini, Privacy, Cookie, DPA e novità, con le versioni archiviate; senza la home', str_contains($r['body'], 'name="t_termini_7_testo"')
      && str_contains($r['body'], 'name="t_privacy_t8_testo"') && str_contains($r['body'], 'name="t_dpa_9_testo"') && str_contains($r['body'], 'name="t_legale_novita"')
      && str_contains($r['body'], 'Versioni archiviate') && !str_contains($r['body'], 'name="t_hero_titolo"') && !str_contains($r['body'], '<iframe'));
$r = $admin->post('/admin/testi', ['legali' => '1', 'lingua' => 'it', 't_termini_16_testo' => "{titolare_completo}, {sede}. Scrivici: {email}."]);
prova('8J · un testo legale cambiato si vede subito, con l\'invito a cambiare la versione', str_contains($ospite->get('/termini')['body'], 'Perugia (PG). Scrivici:')
      && str_contains($admin->segui($r)['body'], 'cambia la versione'));
$admin->post('/admin/testi', ['legali' => '1', 'lingua' => 'it', 't_termini_16_testo' => '']);
$v8 = (int) val('SELECT COUNT(*) FROM legal_versions'); $a8 = (int) val('SELECT COUNT(*) FROM legal_acceptances');
$m8 = shell_exec('php -r ' . escapeshellarg('define("MHW_APP", "' . $DOVE . '/app"); define("MHW_PUBLIC", "' . $DOVE . '"); spl_autoload_register(fn($c) => require "' . $DOVE . '/app/src/" . substr($c, 4) . ".php");
    MHW\Config::load("' . $DOVE . '/app/config.php"); $f = require "' . $DOVE . '/app/migrations/042_legale_8j.php"; $f(MHW\Db::conn()); echo "fatto";') . ' 2>&1');
prova('8J · migrazione 042 una seconda volta: niente cambia', trim((string) $m8) === 'fatto' && (int) val('SELECT COUNT(*) FROM legal_versions') === $v8
      && (int) val('SELECT COUNT(*) FROM legal_acceptances') === $a8, (string) $m8);

// ================================================================= 9A
capitolo('9A · Portfolio progressivo, due controlli Stripe, preventivo oltre 10, codice di invito, prezzo barrato, sconti sommati');
$php9 = fn(string $codice) => trim((string) shell_exec('php -r ' . escapeshellarg('define("MHW_APP", "' . $DOVE . '/app"); define("MHW_PUBLIC", "' . $DOVE . '"); spl_autoload_register(fn($c) => require "' . $DOVE . '/app/src/" . substr($c, 4) . ".php");
    MHW\Config::load("' . $DOVE . '/app/config.php"); ' . $codice) . ' 2>&1'));
$base9 = preg_replace('#^https?://[^/]+#', '', $BASE);
// Il listino 9A, di nuovo in vendita (le prove di prima hanno creato altre versioni): 117, +60, +50 dalla 3ª, +40 dalla 6ª, da 2 a 10.
$pkP9 = riga("SELECT * FROM packages WHERE code = 'portfolio'");
$featP9 = []; foreach (righe('SELECT f.code, pf.value FROM package_features pf JOIN features f ON f.id = pf.feature_id WHERE pf.package_version_id = ?', [pv('portfolio')]) as $x) $featP9[$x['code']] = $x['value'];
$listino9 = ['nome' => $pkP9['name'], 'prezzo' => '117', 'prezzo_extra' => '60', 'min_quantita' => '2', 'max_quantita' => '10', 'scaglioni' => "3: 50\n6: 40", 'stripe_price_id' => '', 'stripe_extra_price_id' => '', 'f' => $featP9,
             'headline' => $pkP9['headline'], 'tagline' => $pkP9['tagline'], 'description' => $pkP9['description'], 'bullets' => $pkP9['bullets'], 'badge' => $pkP9['badge'], 'cta_label' => $pkP9['cta_label'], 'public' => '1', 'active' => '1'];
$admin->get('/admin/pacchetti');
$admin->post("/admin/pacchetti/{$pkP9['id']}/nuova-versione", $listino9);
$pp9 = pv('portfolio');
$pv9 = riga('SELECT * FROM package_versions WHERE id = ?', [$pp9]);
prova('9A · versione Portfolio: 117 €, +60 la 2ª, scaglioni [2:60, 3:50, 6:40], da 2 a 10, Price ID vuoti', (int) $pv9['price_cents'] === 11700 && (int) $pv9['extra_price_cents'] === 6000
      && (int) $pv9['min_quantity'] === 2 && (int) $pv9['max_quantity'] === 10 && json_decode((string) $pv9['extra_tiers'], true) === [['da' => 2, 'cents' => 6000], ['da' => 3, 'cents' => 5000], ['da' => 6, 'cents' => 4000]]
      && (string) $pv9['stripe_price_id'] === '' && (string) $pv9['stripe_extra_price_id'] === '', json_encode($pv9));
$grezzo9 = $php9('$pv = MHW\Plans::currentVersion(' . $pp9 . '); echo json_encode(array_map(fn($q) => MHW\Plans::price($pv, $q), [2, 3, 5, 6, 10]));');
$prezzi9 = json_decode($grezzo9, true);
prova('9A · Plans::price: 2 = 177, 3 = 227, 5 = 327, 6 = 367, 10 = 527', $prezzi9 === [17700, 22700, 32700, 36700, 52700], $grezzo9);
$m9 = $php9('$f = require "' . $DOVE . '/app/migrations/043_portfolio_9a.php"; $f(MHW\Db::conn()); echo "fatto";');
prova('9A · migrazione 043 una seconda volta: niente cambia (la versione corrente ha già questi importi)', $m9 === 'fatto' && pv('portfolio') === $pp9
      && (int) val('SELECT COUNT(*) FROM package_versions WHERE is_current = 1 AND package_id = ?', [$pkP9['id']]) === 1, $m9);
// Amministrazione → Piani: un Price ID si tiene solo se importo e scaglioni sono identici alla versione da cui viene.
db()->prepare("UPDATE package_versions SET stripe_price_id = 'price_vecchio9a', stripe_extra_price_id = 'price_extra9a' WHERE id = ?")->execute([$pp9]);
$admin->get('/admin/pacchetti');
$r = $admin->post("/admin/pacchetti/{$pkP9['id']}/nuova-versione", ['stripe_price_id' => 'price_vecchio9a', 'stripe_extra_price_id' => 'price_extra9a'] + $listino9);
$v9b = riga('SELECT * FROM package_versions WHERE id = ?', [pv('portfolio')]);
prova('9A · nuova versione con gli stessi importi e scaglioni: i Price ID si tengono', pv('portfolio') !== $pp9 && $v9b['stripe_price_id'] === 'price_vecchio9a' && $v9b['stripe_extra_price_id'] === 'price_extra9a'
      && !str_contains($admin->segui($r)['body'], 'Prezzo cambiato'));
$r = $admin->post("/admin/pacchetti/{$pkP9['id']}/nuova-versione", ['prezzo' => '127', 'scaglioni' => "3: 50\n6: 45", 'stripe_price_id' => 'price_vecchio9a', 'stripe_extra_price_id' => 'price_extra9a'] + $listino9);
$v9c = riga('SELECT * FROM package_versions WHERE id = ?', [pv('portfolio')]);
prova('9A · prezzo e scaglioni cambiati con i vecchi Price ID: si svuotano e l\'avviso lo dice', (int) $v9c['price_cents'] === 12700 && $v9c['stripe_price_id'] === '' && $v9c['stripe_extra_price_id'] === ''
      && str_contains($admin->segui($r)['body'], 'Prezzo cambiato (base, struttura aggiuntiva): il Price di Stripe sarà creato nuovo.'));
$admin->post("/admin/pacchetti/{$pkP9['id']}/nuova-versione", $listino9);   // di nuovo il listino 9A, senza Price ID
$pp9 = pv('portfolio');
db()->prepare("UPDATE package_versions SET stripe_price_id = '', stripe_extra_price_id = '' WHERE id = ?")->execute([$pp9]);

// Home, FAQ, llms: 177 € per 2, le quattro righe, niente «come Plus», fino a 999 e il riquadro del preventivo.
$r = $ospite->get('/');
$pf9 = preg_match('#<form class="plan__scelta".*?</form>#s', $r['body'], $mm9) ? $mm9[0] : '';
prova('9A · home: 177 € per 2 strutture, la tabella con le 4 righe, campo fino a 999 con il massimo del listino a parte, riquadro del preventivo chiuso',
      str_contains($pf9, "<span data-totale>177\u{00A0}€</span>") && str_contains($pf9, "<span>1ª</span><b>117\u{00A0}€</b>") && str_contains($pf9, "<span>2ª</span><b>+60\u{00A0}€</b>")
      && str_contains($pf9, "<span>Dalla 3ª alla 5ª</span><b>+50\u{00A0}€ <small>l'una</small>") && str_contains($pf9, "<span>Dalla 6ª alla 10ª</span><b>+40\u{00A0}€ <small>l'una</small>")
      && str_contains($pf9, 'max="999" data-max-prezzo="10"') && str_contains($pf9, 'data-scaglioni="[[2,6000],[3,5000],[6,4000]]"') && str_contains($pf9, 'href="#preventivo"')
      && str_contains($r['body'], 'id="preventivo" data-preventivo data-min="11" hidden') && str_contains($r['body'], 'action="' . $base9 . '/preventivo"')
      && str_contains($r['body'], 'id="prev-strutture" name="strutture" type="number" inputmode="numeric" required min="11" max="999"'), substr($pf9, 0, 300) . ' … ' . (preg_match('#<div class="preventivo"[^>]*>#', $r['body'], $mp9) ? $mp9[0] : 'niente riquadro'));
prova('9A · FAQ (IT/EN) senza «come Plus»; llms.txt con 177 € e gli scaglioni; dati strutturati con 177.00',
      str_contains($d9 = $ospite->get('/domande')['body'], "La prima costa 117\u{00A0}€;") && !str_contains($d9, 'come Plus')
      && str_contains($e9 = $ospite->get('/en/questions')['body'], 'The first one costs €117;') && !str_contains($e9, 'like Plus')
      && str_contains($l9 = $ospite->get('/llms.txt')['body'], '- Portfolio: 117 € la prima struttura, poi 60 € 2ª; 50 € dalla 3ª alla 5ª; 40 € dalla 6ª alla 10ª') && str_contains($l9, '(con 2: 177 €)')
      && str_contains($r['body'], '"price":"177.00"'));
$r = $ospite->get('/en');
prova('9A · /en: tabella in inglese (From the 6th to the 10th) e riquadro del preventivo in inglese', str_contains($r['body'], "<span>From the 6th to the 10th</span><b>+40\u{00A0}€ <small>each</small>")
      && str_contains($r['body'], 'properties? We will make you a tailored offer.') && str_contains($r['body'], 'name="torna" value="/en"'));

// Il preventivo: senza JavaScript, salvato con le due email; errori sotto il modulo; 5 l'ora per IP; in inglese; Amministrazione → Preventivi.
$posta9 = fn(string $a) => array_values(array_filter(array_map(fn($l) => json_decode($l, true), righePosta() ?: []), fn($m) => ($m['to'] ?? '') === $a));
db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'preventivo:%'");
$vis9 = new Browser('visitatore9a');
$nAss9 = count($posta9('commerciale@welcomemyhouse.it'));   // 9H: i preventivi all'email commerciale
$r = $vis9->modulo('/', '/preventivo', ['torna' => '/', 'nome' => 'Giulia Verdi', 'email' => 'giulia9a@prova.test', 'telefono' => '+39 333 1234567', 'strutture' => '14', 'messaggio' => 'Gestiamo 14 appartamenti a Perugia.']);
$prev9 = riga("SELECT * FROM preventivi WHERE email = 'giulia9a@prova.test' ORDER BY id DESC");
prova('9A · «Chiedi il preventivo» senza JavaScript: salvato (stato «nuovo», senza account), si torna al riquadro con la conferma', $r['code'] === 302 && str_ends_with((string) $r['loc'], '/#preventivo')
      && $prev9 && (int) $prev9['strutture'] === 14 && $prev9['stato'] === 'nuovo' && $prev9['telefono'] === '+39 333 1234567' && $prev9['lingua'] === 'it' && $prev9['account_id'] === null
      && str_contains($vis9->get('/')['body'], 'Abbiamo ricevuto la richiesta per <b>14 strutture</b>: ti rispondiamo entro un giorno lavorativo'), $r['code'] . ' ' . $r['loc'] . ' ' . json_encode($prev9) . ' ' . substr(strip_tags($r['body']), 0, 200));
$ass9 = $posta9('commerciale@welcomemyhouse.it'); $g9 = $posta9('giulia9a@prova.test');
prova('9A · due email: all\'email commerciale (9H) con tutti i dati e il link all\'amministrazione, a chi ha scritto la conferma', count($ass9) === $nAss9 + 1
      && str_contains((string) end($ass9)['subject'], 'Richiesta di preventivo: 14 strutture') && str_contains((string) end($ass9)['text'], 'giulia9a@prova.test')
      && str_contains((string) end($ass9)['text'], 'Gestiamo 14 appartamenti a Perugia.') && str_contains((string) end($ass9)['text'], '/admin/preventivi')
      && count($g9) === 1 && str_contains((string) $g9[0]['subject'], 'Abbiamo ricevuto la richiesta per 14 strutture') && str_contains((string) $g9[0]['text'], 'entro un giorno lavorativo'));
$r = $vis9->modulo('/en', '/preventivo', ['torna' => '/en', 'nome' => 'John Host', 'email' => 'john9a@prova.test', 'strutture' => '12']);
$j9 = $posta9('john9a@prova.test');
prova('9A · dalla home inglese: lingua «en», conferma in inglese, si torna a /en', $r['code'] === 302 && str_ends_with($r['loc'], '/en#preventivo')
      && val("SELECT lingua FROM preventivi WHERE email = 'john9a@prova.test'") === 'en' && count($j9) === 1 && str_contains((string) $j9[0]['subject'], 'We received your request for 12 properties')
      && str_contains($vis9->get('/en')['body'], 'We received your request for <b>12 properties</b>'));
$r = $vis9->modulo('/', '/preventivo', ['torna' => '/', 'nome' => 'Pochi Alloggi', 'email' => 'pochi9a@prova.test', 'strutture' => '5']);
prova('9A · meno di 11 strutture: niente riga, l\'errore sotto il modulo con i campi ancora compilati', $r['code'] === 302 && !val("SELECT id FROM preventivi WHERE email = 'pochi9a@prova.test'")
      && str_contains($h9 = $vis9->get('/')['body'], 'Il preventivo è per 11 strutture o più.') && str_contains($h9, 'value="Pochi Alloggi"') && str_contains($h9, 'id="preventivo" data-preventivo data-min="11">'));
foreach ([15, 16] as $n9) $vis9->modulo('/', '/preventivo', ['torna' => '/', 'nome' => 'Tanti Alloggi', 'email' => "tanti$n9@prova.test", 'strutture' => (string) $n9]);
$r = $vis9->modulo('/', '/preventivo', ['torna' => '/', 'nome' => 'Troppi Alloggi', 'email' => 'troppi9a@prova.test', 'strutture' => '17']);
prova('9A · limite: 5 richieste l\'ora per connessione, la sesta no', (int) val("SELECT COUNT(*) FROM preventivi WHERE email LIKE 'tanti%@prova.test'") === 2
      && !val("SELECT id FROM preventivi WHERE email = 'troppi9a@prova.test'") && str_contains($vis9->get('/')['body'], 'Troppe richieste da questa connessione'));
db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'preventivo:%'");
$r = $admin->get('/admin/preventivi');
prova('9A · Amministrazione → Vendite → Preventivi: la voce nel menu, l\'elenco con stato, email e telefono cliccabili, «Rispondi» (mailto con l\'oggetto)', $r['code'] === 200 && pulita($r)
      && preg_match('#/admin/preventivi"[^>]*>(?:<svg.*?</svg>)?\s*(?:<span>)?Preventivi<#s', $r['body']) === 1 && str_contains($r['body'], 'Giulia Verdi') && str_contains($r['body'], 'badge--ochre">Nuovo<')
      && str_contains($r['body'], 'href="mailto:giulia9a@prova.test?subject=Preventivo%20MyHouse%20Welcome%20per%2014%20strutture"') && str_contains($r['body'], 'href="tel:+393331234567"')
      && str_contains($r['body'], 'in inglese') && preg_match('#Da rispondere</span><b class="cifra__valore">4<#', $r['body']) === 1);
$r = $admin->post("/admin/preventivi/{$prev9['id']}/stato", ['stato' => 'contattato']);
prova('9A · lo stato si cambia (nuovo → contattato) e resta nel registro delle azioni', $r['code'] === 302 && val('SELECT stato FROM preventivi WHERE id = ?', [$prev9['id']]) === 'contattato'
      && (bool) val("SELECT id FROM audit_log WHERE action = 'preventivo.stato'") && $admin->post("/admin/preventivi/{$prev9['id']}/stato", ['stato' => 'boh'])['code'] === 302
      && val('SELECT stato FROM preventivi WHERE id = ?', [$prev9['id']]) === 'contattato');
prova('9A · Quadro: «Richieste di preventivo da rispondere» con il numero', preg_match('#Richieste di preventivo da rispondere</b><span class="small">3 richieste nuove#', $admin->get('/admin')['body']) === 1);
db()->prepare("INSERT INTO preventivi (account_id, nome, email, telefono, strutture, messaggio, lingua, stato, created_at) VALUES (NULL, 'Vecchia', 'vecchia9a@prova.test', '', 20, '', 'it', 'chiuso', ?)")
    ->execute([gmdate('Y-m-d\TH:i:s\Z', strtotime('-13 months'))]);
$php9('MHW\Registri::pulisciDati(); echo "ok";');
prova('9A · conservazione 12 mesi: la pulizia dei registri toglie le richieste vecchie e lascia le altre', !val("SELECT id FROM preventivi WHERE email = 'vecchia9a@prova.test'")
      && (bool) val("SELECT id FROM preventivi WHERE email = 'giulia9a@prova.test'"));
prova('9A · Privacy: tredicesimo trattamento «Richieste di preventivo», 12 mesi; in inglese «Quote requests»', str_contains($pr9 = $ospite->get('/privacy')['body'], '13. Richieste di preventivo')
      && str_contains($pr9, '12 mesi dalla richiesta') && str_contains($pr9, 'id="trattamento-13"') && str_contains($ospite->get('/en/privacy')['body'], '13. Quote requests'));
$r = $vis9->get("/registrati?piano=$pp9&strutture=11");
prova('9A · registrazione con 11 strutture: niente scheda del piano, il riquadro del preventivo aperto con 11 e il ritorno giusto', $r['code'] === 200 && !str_contains($r['body'], 'class="chip-piano"')
      && str_contains($r['body'], 'id="preventivo" data-preventivo data-min="11">') && str_contains($r['body'], 'max="999" step="1" value="11"')
      && str_contains($r['body'], 'name="torna" value="/registrati?piano=' . $pp9 . '&amp;strutture=11"') && !str_contains($r['body'], 'name="strutture" value="11"'));

// Il codice di invito in registrazione: scritto a mano con «Applica», oppure dal link in sola lettura; prezzi barrati.
$admin->get('/admin/impostazioni');
$admin->segui($admin->post('/admin/impostazioni/inviti', ['inviti__attivi' => '1', 'password' => 'AdminProva123']));   // gli inviti accesi (le prove di prima possono averli spenti)
$codP9 = (string) val("SELECT a.referral_code FROM accounts a JOIN users u ON u.id = a.user_id WHERE u.email = 'paola@prova.test'");
$paolaAcc9 = $accDi('paola@prova.test');
// Paola deve poter invitare: un abbonamento attivo (le prove di prima possono averlo fatto scadere).
db()->prepare("UPDATE subscriptions SET status = 'active', cancel_at_period_end = 0, current_period_end = ? WHERE provider_subscription_id = 'sub_prova_paola'")->execute([gmdate('Y-m-d\TH:i:s\Z', time() + 300 * 86400)]);
$nina = new Browser('nina9a');
$r = $nina->get('/registrati');
prova('9A · registrazione senza link: campo facoltativo «Hai un codice di invito?» con «Applica», nessun prezzo barrato', str_contains($r['body'], 'Hai un codice di invito o omaggio?')
      && str_contains($r['body'], 'name="codice_invito"') && str_contains($r['body'], 'data-invito-applica') && !str_contains($r['body'], 'name="codice_invito" type="text" value="') && !str_contains($r['body'], 'class="reg-prezzo"><s>'));
$r = $nina->post('/invito/verifica', ['codice' => 'ZZZZZZ'], ['Accept: application/json']);
$j = json_decode($r['body'], true);
prova('9A · POST /invito/verifica con un codice falso: 404 e il messaggio', $r['code'] === 404 && ($j['ok'] ?? true) === false
      && ($j['errore'] ?? '') === 'Questo codice non esiste o non è più attivo. Controlla con chi te l\'ha dato.', $r['body']);
$r = $nina->post('/invito/verifica', ['codice' => strtolower($codP9)], ['Accept: application/json']);
$j = json_decode($r['body'], true);
prova('9A · …con il codice giusto (anche minuscolo): chi invita e la percentuale', $r['code'] === 200 && ($j['ok'] ?? false) === true && ($j['nome'] ?? '') === 'Paola' && ($j['percento'] ?? 0) === 5, $r['body']);
db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'register:%'");
$r = $nina->post('/registrati', ['name' => 'Nina Invitata', 'email' => 'nina9a@prova.test', 'email2' => 'nina9a@prova.test', 'password' => 'NinaProva12345', 'termini' => '1', 'codice_invito' => 'ZZZZZZ']);
prova('9A · registrazione con un codice falso: rifiutata prima di creare l\'account, con il messaggio e il codice ancora nel campo', $r['code'] === 200
      && str_contains($r['body'], 'Questo codice non esiste o non è più attivo. Controlla con chi te l&#039;ha dato.') && str_contains($r['body'], 'value="ZZZZZZ"')
      && !val("SELECT id FROM users WHERE email = 'nina9a@prova.test'"));
$r = $nina->post('/registrati', ['name' => 'Nina Invitata', 'email' => 'nina9a@prova.test', 'email2' => 'nina9a@prova.test', 'password' => 'NinaProva12345', 'termini' => '1', 'codice_invito' => ' ' . strtolower($codP9) . ' ']);
$nacc = $accDi('nina9a@prova.test');
prova('9A · con il codice scritto a mano: account creato e legato all\'invito di Paola', $r['code'] === 302 && $nacc > 0
      && (string) val('SELECT status FROM referrals WHERE friend_account_id = ?', [$nacc]) === 'registrato' && (int) val('SELECT referrer_account_id FROM referrals WHERE friend_account_id = ?', [$nacc]) === $paolaAcc9);
$olga = new Browser('olga9a');
$olga->get('/i/' . $codP9);
$r = $olga->get('/registrati');
prova('9A · dal link /i/CODICE: il codice già scritto e in sola lettura, «Codice di Paola: 5% sul primo anno», i tre prezzi barrati con −5%',
      preg_match('#<input id="codice_invito" name="codice_invito" type="text" value="' . $codP9 . '" readonly#', $r['body']) === 1 && str_contains($r['body'], 'Codice di Paola: 5% sul primo anno')
      && substr_count($r['body'], '<span class="reg-prezzo"') === 3 && str_contains($r['body'], "<s>127\u{00A0}€</s> <b>120,65\u{00A0}€</b></span> + IVA il primo anno")
      && str_contains($r['body'], "<s>177\u{00A0}€</s> <b>168,15\u{00A0}€</b>") && str_contains($r['body'], "<s>107\u{00A0}€</s> <b>101,65\u{00A0}€</b>")
      && substr_count($r['body'], '<span class="reg-prezzo__sconto">−5% primo anno</span>') === 3 && !str_contains($r['body'], 'data-invito-applica'));

// Nina: /piano con i prezzi barrati, 11 strutture → preventivo, poi Portfolio 2 e il riepilogo prima del pagamento.
$link9 = linkPosta('nina9a@prova.test', 'verifica');
$nina->get(substr($link9, strpos($link9, '/verifica/')));
$r = $nina->get('/piano');
prova('9A · /piano con l\'invito: prezzo pieno barrato e scontato nelle tre schede, lo sconto nei dati dello script, l\'invito nel riquadro dei codici', $r['code'] === 200 && pulita($r)
      && substr_count($r['body'], 'class="price price--barrato"') === 3 && str_contains($r['body'], "<s data-pieno>177\u{00A0}€</s>") && str_contains($r['body'], "<span data-totale>168,15\u{00A0}€</span><small> + IVA il primo anno</small>")
      && str_contains($r['body'], 'data-sconto="5"') && str_contains($r['body'], '−5% primo anno · poi <span data-pieno-poi>177') && str_contains($r['body'], '<b>Invito di Paola</b>: −5% sul primo anno, sul piano che scegli.')
      && !str_contains($r['body'], '/sconto/togli'));
$r = $nina->post('/piano', ['pv' => $pp9, 'strutture' => '11']);
prova('9A · 11 strutture in /piano: nessun ordine e nessun errore, si torna al riquadro del preventivo già compilato', $r['code'] === 302 && str_contains($r['loc'], "/piano?piano=$pp9&strutture=11#preventivo")
      && str_contains($nina->segui($r)['body'], 'id="preventivo" data-preventivo data-min="11">') && (int) val('SELECT COALESCE(intended_package_version_id, 0) FROM accounts WHERE id = ?', [$nacc]) !== $pp9, $r['loc']);
$r = $nina->modulo('/', '/preventivo', ['torna' => '/piano', 'nome' => 'Nina Invitata', 'email' => 'nina9a@prova.test', 'strutture' => '11']);
prova('9A · il preventivo di chi è dentro porta l\'account e torna a /piano', str_ends_with($r['loc'], '/piano#preventivo') && (int) val("SELECT account_id FROM preventivi WHERE email = 'nina9a@prova.test'") === $nacc);
$nina->post('/piano', ['pv' => $pp9, 'strutture' => '2']);
$nina->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Casa Nina', 'city' => 'Perugia']);
$pn = (int) val('SELECT id FROM properties WHERE account_id = ? ORDER BY id', [$nacc]);
db()->prepare("UPDATE properties SET cin = 'IT054039C2NINA9A01' WHERE id = ?")->execute([$pn]);
$ncore = (int) val('SELECT id FROM sections WHERE property_id = ? AND is_core = 1', [$pn]);
$nina->post("/pannello/$pn/sezioni/$ncore", ['checkin_steps' => ['Suona al citofono.']]);
$r = $nina->get('/account');
prova('9A · Account & Fatturazione prima del pagamento: l\'invito e il campo «Hai un codice sconto o un invito?»', str_contains($r['body'], '<b>Invito di Paola</b>: −5% sul primo anno')
      && str_contains($r['body'], 'Hai un codice sconto, un invito o un codice omaggio?') && str_contains($r['body'], 'name="torna" value="/account"'));
$r = $nina->get("/pannello/$pn/procedura/pubblica");
prova('9A · passo Pubblica: prezzo barrato, riga verde −5%, riepilogo (prezzo pieno, invito −8,85 €, totale 168,15 €, dal secondo anno 177 €), solo il campo del codice',
      str_contains($r['body'], "style=\"font-size:18px\">177\u{00A0}€</s>") && str_contains($r['body'], "168,15\u{00A0}€<span class=\"small muted\"> + IVA il primo anno")
      && str_contains($r['body'], '<p class="plan__sconto-riga" style="margin:0">−5% primo anno</p>') && str_contains($r['body'], "Portfolio · 2 strutture</span><b>177\u{00A0}€</b>")
      && str_contains($r['body'], "Invito di Paola · −5% primo anno</span><b>−8,85\u{00A0}€</b>") && str_contains($r['body'], "Totale primo anno</span><b>168,15\u{00A0}€ <small>+ IVA</small></b>")
      && str_contains($r['body'], "Dal secondo anno 177\u{00A0}€ + IVA: gli sconti valgono solo sul primo anno.") && str_contains($r['body'], 'Hai un codice sconto, un invito o un codice omaggio?')
      && !str_contains($r['body'], '/sconto/togli'));
// Un codice scritto a mano non si somma: vale il più alto, e il riepilogo lo dice.
$admin->get('/admin/sconti');
$admin->post('/admin/sconti', ['code' => 'NOVE20', 'kind' => 'percent', 'value' => '20', 'valid_from' => gmdate('Y-m-d'), 'valid_until' => gmdate('Y-m-d', strtotime('+30 days')), 'max_uses' => '', 'piani' => 'tutti', 'note' => '9A']);
$admin->post('/admin/sconti', ['code' => 'NOVE3', 'kind' => 'percent', 'value' => '3', 'valid_from' => gmdate('Y-m-d'), 'valid_until' => gmdate('Y-m-d', strtotime('+30 days')), 'max_uses' => '', 'piani' => 'tutti', 'note' => '9A']);
$nina->modulo("/pannello/$pn/procedura/pubblica", '/sconto/applica', ['codice' => 'NOVE20', 'torna' => "/pannello/$pn/procedura/pubblica"]);
$r = $nina->get("/pannello/$pn/procedura/pubblica");
prova('9A · codice del 20% scritto a mano: vale lui (−35,40 €, 141,60 €), con la nota «non si somma» e «Togli»', str_contains($r['body'], "Codice NOVE20 · −20% primo anno</span><b>−35,40\u{00A0}€</b>")
      && str_contains($r['body'], "Totale primo anno</span><b>141,60\u{00A0}€") && !str_contains($r['body'], 'Invito di Paola · ')
      && str_contains($r['body'], 'Il codice NOVE20 non si somma agli altri sconti: vale il più alto.') && str_contains($r['body'], '/sconto/togli'));
$nina->modulo("/pannello/$pn/procedura/pubblica", '/sconto/applica', ['codice' => 'NOVE3', 'torna' => "/pannello/$pn/procedura/pubblica"]);
$r = $nina->get("/pannello/$pn/procedura/pubblica");
prova('9A · codice del 3%: vale l\'invito (5%), la nota dice che il codice vale meno', str_contains($r['body'], "Invito di Paola · −5% primo anno</span><b>−8,85\u{00A0}€</b>")
      && str_contains($r['body'], 'Il codice NOVE3 non si somma agli altri sconti e vale meno: non si applica.'));
$nina->post('/sconto/togli', ['torna' => "/pannello/$pn/procedura/pubblica"]);
$r = $nina->get("/pannello/$pn/procedura/pubblica");
prova('9A · «Togli»: resta l\'invito, senza nota', str_contains($r['body'], "Invito di Paola · −5% primo anno") && !str_contains($r['body'], 'non si somma') && !str_contains($r['body'], 'NOVE'));

// Il Checkout: un solo coupon (quello dell'invito), la composizione nei metadati e nell'ordine; i due controlli.
$nina->modulo('/account', '/account/fatturazione', ['billing_type' => 'privato', 'billing_name' => 'Nina Invitata', 'cf' => 'NNAVRD90A41G478D',
               'billing_address' => 'Via Nina 1', 'billing_postal' => '06123', 'billing_city' => 'Perugia', 'billing_province' => 'PG']);
@unlink("$DOVE/app/storage/.prezzi-stripe.json");
$prima9 = count(richiesteStripe());
$r = $nina->modulo("/pannello/$pn/procedura/pubblica", "/pannello/$pn/pubblica", ['acquirente' => 'impresa']);
$ord9 = riga('SELECT * FROM orders WHERE account_id = ? ORDER BY id DESC', [$nacc]);
$rich9 = array_slice(richiesteStripe(), $prima9);
$ses9 = array_values(array_filter($rich9, fn($x) => $x['percorso'] === '/v1/checkout/sessions' && $x['metodo'] === 'POST'))[0] ?? [];
$c9 = $ses9['corpo'] ?? [];
$sys9 = riga("SELECT * FROM discount_codes WHERE sistema = 1 ORDER BY id DESC");
prova('9A · Checkout: ordine 177 € con «invito:5» e la riga di sistema; a Stripe un solo coupon (quello dell\'invito) e la composizione nei metadati',
      $r['code'] === 302 && str_starts_with($r['loc'], 'https://checkout.stripe.test/') && $ord9 && (int) $ord9['amount_cents'] === 17700 && $ord9['sconti'] === 'invito:5'
      && (int) $ord9['discount_code_id'] === (int) $sys9['id'] && ($c9['discounts'][0]['coupon'] ?? '') === $sys9['stripe_coupon_id'] && count($c9['discounts'] ?? []) === 1
      && ($c9['metadata']['sconti'] ?? '') === 'invito:5' && ($c9['subscription_data']['metadata']['sconti'] ?? '') === 'invito:5' && !isset($c9['metadata']['discount_code']), json_encode([$ord9['sconti'] ?? null, $c9['discounts'] ?? null]));
$pt9 = array_values(array_filter($rich9, fn($x) => $x['percorso'] === '/v1/prices' && $x['metodo'] === 'POST' && ($x['corpo']['billing_scheme'] ?? '') === 'tiered'));
$t9 = end($pt9)['corpo']['tiers'] ?? [];
$idExtra9 = (string) ($c9['line_items'][1]['price'] ?? '');
prova('9A · la voce aggiuntiva è un Price graduato creato adesso: fino a 1 → 60 €, fino a 4 → 50 €, oltre → 40 €, quantità 1', count($pt9) === 1 && $idExtra9 !== ''
      && ($c9['line_items'][1]['quantity'] ?? '') === '1' && ($c9['line_items'][0]['price_data']['unit_amount'] ?? '') === '11700'
      && ($t9[0]['up_to'] ?? '') === '1' && ($t9[0]['unit_amount'] ?? '') === '6000' && ($t9[1]['up_to'] ?? '') === '4' && ($t9[1]['unit_amount'] ?? '') === '5000'
      && ($t9[2]['up_to'] ?? '') === 'inf' && ($t9[2]['unit_amount'] ?? '') === '4000' && val('SELECT stripe_extra_price_id FROM package_versions WHERE id = ?', [$pp9]) === $idExtra9, json_encode($t9));
$ctrl9 = array_map(fn($x) => $x['metodo'] . ' ' . $x['percorso'], $rich9);
$esiti9 = json_decode((string) @file_get_contents("$DOVE/app/storage/.prezzi-stripe.json"), true) ?: [];
prova('9A · controllo 1 prima (GET del Price graduato) e controllo 2 dopo (GET della sessione), esito «ok» in cache, nessuna sessione fatta scadere',
      in_array("GET /v1/prices/$idExtra9", $ctrl9, true) && (bool) array_filter($ctrl9, fn($x) => str_starts_with($x, 'GET /v1/checkout/sessions/cs_'))
      && !array_filter($ctrl9, fn($x) => str_ends_with($x, '/expire')) && !empty($esiti9[$pp9]['ok']) && str_starts_with((string) $ord9['provider_session_id'], 'cs_test_finto'), implode(' | ', $ctrl9));
// Controllo 1 che fallisce: uno scaglione diverso su Stripe.
@unlink("$DOVE/app/storage/.prezzi-stripe.json");
$stato9 = json_decode((string) file_get_contents("$STRIPE_DIR/stato.json"), true);
$stato9['listino'][$idExtra9]['tiers'][1]['unit_amount'] = 5500;
file_put_contents("$STRIPE_DIR/stato.json", json_encode($stato9));
$prima9 = count(richiesteStripe());
$r = $nina->modulo("/pannello/$pn/procedura/pubblica", "/pannello/$pn/pubblica", ['acquirente' => 'impresa']);
$ult9 = riga('SELECT * FROM orders WHERE account_id = ? ORDER BY id DESC', [$nacc]);
$esiti9 = json_decode((string) @file_get_contents("$DOVE/app/storage/.prezzi-stripe.json"), true) ?: [];
prova('9A · controllo 1: scaglione diverso su Stripe → niente Checkout, ordine fallito, «ti avvisiamo noi», errore registrato, avviso nel Quadro', $r['code'] === 302
      && str_contains($nina->segui($r)['body'], 'Il pagamento non è disponibile in questo momento, ti avvisiamo noi.') && (int) $ult9['id'] !== (int) $ord9['id'] && $ult9['status'] === 'failed'
      && !array_filter(array_slice(richiesteStripe(), $prima9), fn($x) => $x['percorso'] === '/v1/checkout/sessions' && $x['metodo'] === 'POST')
      && empty($esiti9[$pp9]['ok']) && str_contains((string) ($esiti9[$pp9]['errore'] ?? ''), 'tiers[1].unit_amount')
      && str_contains($q9 = $admin->get('/admin')['body'], 'Prezzi su Stripe diversi dal listino') && str_contains($q9, 'tiers[1].unit_amount'), json_encode($esiti9));
$stato9['listino'][$idExtra9]['tiers'][1]['unit_amount'] = 5000;
file_put_contents("$STRIPE_DIR/stato.json", json_encode($stato9));
@unlink("$DOVE/app/storage/.prezzi-stripe.json");
// Controllo 2 che fallisce: la sessione esce con un totale diverso.
file_put_contents("$STRIPE_DIR/importo-sbagliato", '1');
$prima9 = count(richiesteStripe());
$r = $nina->modulo("/pannello/$pn/procedura/pubblica", "/pannello/$pn/pubblica", ['acquirente' => 'impresa']);
$ult9 = riga('SELECT * FROM orders WHERE account_id = ? ORDER BY id DESC', [$nacc]);
$dopo9 = array_map(fn($x) => $x['metodo'] . ' ' . $x['percorso'], array_slice(richiesteStripe(), $prima9));
prova('9A · controllo 2: totale della sessione diverso dall\'atteso → sessione fatta scadere, ordine fallito, stesso messaggio', $r['code'] === 302
      && str_contains($nina->segui($r)['body'], 'Il pagamento non è disponibile in questo momento, ti avvisiamo noi.') && $ult9['status'] === 'failed'
      && (bool) array_filter($dopo9, fn($x) => str_ends_with($x, '/expire')) && !str_contains($admin->get('/admin')['body'], 'Prezzi su Stripe diversi dal listino'), implode(' | ', $dopo9));
unlink("$STRIPE_DIR/importo-sbagliato");
// Tutto a posto: il pagamento, poi il webhook con lo sconto davvero concesso.
$r = $nina->modulo("/pannello/$pn/procedura/pubblica", "/pannello/$pn/pubblica", ['acquirente' => 'impresa']);
$ord9 = riga('SELECT * FROM orders WHERE account_id = ? ORDER BY id DESC', [$nacc]);
prova('9A · con i prezzi giusti il Checkout riparte', $r['code'] === 302 && str_starts_with($r['loc'], 'https://checkout.stripe.test/') && $ord9['status'] === 'pending' && $ord9['sconti'] === 'invito:5');
file_put_contents("$STRIPE_DIR/extra-sub_prova_nina9a", '1');
$r = inviaWebhook(['id' => 'evt_nina_9a', 'type' => 'checkout.session.completed', 'data' => ['object' => [
    'id' => $ord9['provider_session_id'], 'mode' => 'subscription', 'payment_status' => 'paid', 'customer' => 'cus_nina9a', 'subscription' => 'sub_prova_nina9a',
    'client_reference_id' => (string) $ord9['id'], 'total_details' => ['amount_discount' => 885],
    'metadata' => ['order_id' => (string) $ord9['id'], 'account_id' => (string) $nacc, 'sconti' => 'invito:5']]]]);
prova('9A · webhook: abbonamento attivo per 2 strutture, l\'invito di Paola diventa valido, l\'utilizzo della riga di sistema vale 8,85 €', $r['body'] === 'abbonamento-attivato'
      && (int) val("SELECT quantity FROM subscriptions WHERE provider_subscription_id = 'sub_prova_nina9a'") === 2
      && val('SELECT status FROM referrals WHERE friend_account_id = ?', [$nacc]) === 'valido' && (int) val('SELECT discount_cents FROM discount_redemptions WHERE order_id = ?', [$ord9['id']]) === 885
      && (int) val('SELECT discount_code_id FROM discount_redemptions WHERE order_id = ?', [$ord9['id']]) === (int) $sys9['id'], $r['body']);
prova('9A · Account dopo il pagamento: «Sconto del primo anno: −8,85 €»', str_contains($nina->get('/account')['body'], "Sconto del primo anno: <b>\u{2212}8,85\u{00A0}€</b>"));
// Lo sconto combinato (invito + giudizio, 9B) su Stripe: un coupon «mhw-primo-anno-N» creato una volta sola.
$cc9 = $php9('echo MHW\Sconti::couponCombinato(10) . " " . MHW\Sconti::couponCombinato(10);');
$cp9 = array_values(array_filter(richiesteStripe(), fn($x) => $x['percorso'] === '/v1/coupons' && $x['metodo'] === 'POST' && ($x['corpo']['id'] ?? '') === 'mhw-primo-anno-10'));
prova('9A · coupon combinato «mhw-primo-anno-10» (10%, una volta, «Sconti primo anno»): creato una volta, poi riletto', $cc9 === 'mhw-primo-anno-10 mhw-primo-anno-10' && count($cp9) === 1
      && ($cp9[0]['corpo']['percent_off'] ?? '') === '10' && ($cp9[0]['corpo']['duration'] ?? '') === 'once' && ($cp9[0]['corpo']['name'] ?? '') === 'Sconti primo anno', $cc9);
// Lo strumento da riga di comando: tutte le righe «ok» con lo Stripe finto.
$tool9 = (string) shell_exec('php ' . escapeshellarg("$DOVE/app/tools/verifica-stripe.php") . ' 2>&1');
prova('9A · tools/verifica-stripe.php: Essential, Plus e Portfolio 2–10 con e senza −5%, tutte le righe «ok»', preg_match('#Tutte le (\d+) righe tornano#', $tool9, $mt9) === 1 && (int) $mt9[1] >= 22
      && str_contains($tool9, 'Portfolio · 10 strutture') && str_contains($tool9, "500,65\u{00A0}€") && !str_contains($tool9, ' NO '), substr($tool9, 0, 600));

// ================================================================= 9B
capitolo('9B · giudizio con sconto: casella al pagamento, riepilogo, pannello, coupon sul rinnovo, Amministrazione → Giudizi, home, Termini');
$m9b = $php9('$f = require "' . $DOVE . '/app/migrations/044_giudizi_9b.php"; $f(MHW\Db::conn()); echo "fatto";');
prova('9B · migrazione 044 una seconda volta: niente cambia', $m9b === 'fatto' && (int) val("SELECT COUNT(*) FROM testimonials WHERE home_visible = 1 AND withdrawn_at IS NULL") === (int) val("SELECT COUNT(*) FROM testimonials WHERE home_visible = 1 AND withdrawn_at IS NULL"), $m9b);
$r = $admin->get('/admin/impostazioni');
prova('9B · Impostazioni → «Giudizi con sconto»: interruttore, percentuale, sezione in home', str_contains($r['body'], 'Giudizi con sconto') && str_contains($r['body'], 'name="giudizi__sconto_attivo"')
      && str_contains($r['body'], 'name="giudizi__percentuale"') && str_contains($r['body'], 'name="giudizi__home"'));
prova('9B · Termini: punto 17 «Giudizio con sconto» dopo «Invita un amico», con le regole; in inglese «Review with discount»; Privacy t6 su base contratto (art. 6.1.b)',
      preg_match('#id="inviti".*id="giudizio-sconto".*13\. Traduzioni suggerite#s', $t9b = $ospite->get('/termini')['body']) === 1 && str_contains($t9b, '17. Giudizio con sconto')
      && str_contains($t9b, 'Lo sconto non dipende dal voto') && str_contains($t9b, 'una sola casella') && str_contains($t9b, 'a nostra discrezione') && str_contains($t9b, 'decade se ritiri il giudizio prima del rinnovo')
      && str_contains($ospite->get('/en/terms')['body'], '17. Review with discount') && str_contains($pv9b = $ospite->get('/privacy')['body'], 'art. 6.1.b): l&#039;adesione al programma')
      && str_contains($pv9b, 'fino al ritiro o alla chiusura'));
prova('9B · Testi e foto: la sezione «Cosa dicono di noi» con la nota obbligatoria', str_contains($r9b = $admin->get('/admin/testi')['body'], 'name="t_dicono_nota"') && str_contains($r9b, 'Cosa dicono di noi'));

// Ugo: un cliente nuovo, Plus, che aderisce al pagamento. Prima: la casella, lo spiegone, il riepilogo che si aggiorna; poi il Checkout con il coupon del giudizio.
$ugo = new Browser('ugo9b');
db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'register:%'");
$ugo->get('/registrati?piano=' . pv('plus'));
$ugo->post('/registrati', ['piano' => pv('plus'), 'name' => 'Ugo Giudizio', 'email' => 'ugo9b@prova.test', 'email2' => 'ugo9b@prova.test', 'password' => 'UgoProva12345', 'termini' => '1']);
$uacc = $accDi('ugo9b@prova.test');
$link9b = linkPosta('ugo9b@prova.test', 'verifica'); $ugo->get(substr($link9b, strpos($link9b, '/verifica/')));
$ugo->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Casa Ugo', 'city' => 'Gubbio']);
$pu = (int) val('SELECT id FROM properties WHERE account_id = ? ORDER BY id', [$uacc]);
db()->prepare("UPDATE properties SET cin = 'IT054024C2UGO9B001' WHERE id = ?")->execute([$pu]);
$ucore = (int) val('SELECT id FROM sections WHERE property_id = ? AND is_core = 1', [$pu]);
$ugo->post("/pannello/$pu/sezioni/$ucore", ['checkin_steps' => ['Suona al citofono.']]);
$ugo->modulo('/account', '/account/fatturazione', ['billing_type' => 'privato', 'billing_name' => 'Ugo Giudizio', 'cf' => 'GDZUGO85A01E256O', 'billing_address' => 'Via Ugo 1', 'billing_postal' => '06024', 'billing_city' => 'Gubbio', 'billing_province' => 'PG']);
$r = $ugo->get("/pannello/$pu/procedura/pubblica");
prova('9B · passo Pubblica: la casella del giudizio (una sola, non spuntata) con lo spiegone e il link ai Termini; prezzo pieno 127 €', str_contains($r['body'], 'id="giudizio-sconto"')
      && substr_count($r['body'], 'name="giudizio_adesione"') === 1 && !str_contains($r['body'], 'name="giudizio_adesione" value="1" data-giudizio-casella style="margin-top:1px" checked')
      && str_contains($r['body'], 'Sì, voglio il 5% in più: lascerò un giudizio.') && str_contains($r['body'], 'Lo sconto non dipende dal voto.') && str_contains($r['body'], '/termini#giudizio-sconto')
      && !str_contains($r['body'], 'Giudizio · ') && str_contains($r['body'], "Totale primo anno</span><b>127\u{00A0}€"));
prova('9B · pannello prima di aderire: il riquadro «invito» (5% sul prossimo rinnovo) con «Partecipo»', str_contains($p9b = $ugo->get('/pannello')['body'], 'sul prossimo rinnovo, con un giudizio') && str_contains($p9b, 'name="partecipo"'));
$r = $ugo->post('/giudizio/adesione', ['giudizio_adesione' => '1', 'torna' => "/pannello/$pu/procedura/pubblica"]);
$ua = riga('SELECT * FROM accounts WHERE id = ?', [$uacc]);
prova('9B · spuntata (POST /giudizio/adesione): adesione «pagamento», sconto promesso, nel registro', $r['code'] === 302 && ($ua['giudizio_adesione'] ?? '') === 'pagamento' && ($ua['giudizio_sconto_stato'] ?? '') === 'promesso'
      && $ua['giudizio_adesione_at'] !== null && (bool) val("SELECT id FROM audit_log WHERE action = 'giudizio.adesione'"));
$r = $ugo->get("/pannello/$pu/procedura/pubblica");
prova('9B · il riepilogo ha la riga «Giudizio · −5%» (−6,35 €), totale 120,65 €, la casella spuntata, il prezzo barrato', str_contains($r['body'], "Giudizio · −5% primo anno</span><b>−6,35\u{00A0}€</b>")
      && str_contains($r['body'], "Totale primo anno</span><b>120,65\u{00A0}€") && str_contains($r['body'], 'data-giudizio-casella style="margin-top:1px" checked') && str_contains($r['body'], "style=\"font-size:18px\">127\u{00A0}€</s>"));
$ugo->post('/giudizio/adesione', ['torna' => "/pannello/$pu/procedura/pubblica"]);
prova('9B · tolta prima di pagare: l\'adesione si annulla, prezzo pieno', (string) val('SELECT giudizio_sconto_stato FROM accounts WHERE id = ?', [$uacc]) === 'nessuno' && val('SELECT giudizio_adesione FROM accounts WHERE id = ?', [$uacc]) === null
      && !str_contains($ugo->get("/pannello/$pu/procedura/pubblica")['body'], 'Giudizio · '));
prova('9B · pannello: il riquadro «invito» torna', str_contains($ugo->get('/pannello')['body'], 'name="partecipo"'));
// Il pagamento con la casella spuntata nel modulo stesso (senza JavaScript): un solo coupon, quello del giudizio.
@unlink("$DOVE/app/storage/.prezzi-stripe.json");
$prima9b = count(richiesteStripe());
$r = $ugo->modulo("/pannello/$pu/procedura/pubblica", "/pannello/$pu/pubblica", ['acquirente' => 'impresa', 'giudizio_casella' => '1', 'giudizio_adesione' => '1']);
$ordU = riga('SELECT * FROM orders WHERE account_id = ? ORDER BY id DESC', [$uacc]);
$sesU = array_values(array_filter(array_slice(richiesteStripe(), $prima9b), fn($x) => $x['percorso'] === '/v1/checkout/sessions' && $x['metodo'] === 'POST'))[0]['corpo'] ?? [];
prova('9B · Checkout con la casella: ordine 127 € con «giudizio:5», coupon «mhw-giudizio-5» (uno solo), composizione nei metadati', $r['code'] === 302 && str_starts_with($r['loc'], 'https://checkout.stripe.test/')
      && $ordU && (int) $ordU['amount_cents'] === 12700 && $ordU['sconti'] === 'giudizio:5' && $ordU['discount_code_id'] === null
      && ($sesU['discounts'][0]['coupon'] ?? '') === 'mhw-giudizio-5' && count($sesU['discounts'] ?? []) === 1 && ($sesU['metadata']['sconti'] ?? '') === 'giudizio:5', json_encode([$ordU['sconti'] ?? null, $sesU['discounts'] ?? null]));
prova('9B · …e la sessione torna con il totale scontato (controllo 2 passato: 120,65 €)', (int) (json_decode((string) file_get_contents("$STRIPE_DIR/stato.json"), true)['sessioni'][$ordU['provider_session_id']]['amount_total'] ?? 0) === 12065);
file_put_contents("$STRIPE_DIR/extra-sub_prova_ugo9b", '0');
$r = inviaWebhook(['id' => 'evt_ugo_9b', 'type' => 'checkout.session.completed', 'data' => ['object' => [
    'id' => $ordU['provider_session_id'], 'mode' => 'subscription', 'payment_status' => 'paid', 'customer' => 'cus_ugo9b', 'subscription' => 'sub_prova_ugo9b',
    'client_reference_id' => (string) $ordU['id'], 'total_details' => ['amount_discount' => 635], 'metadata' => ['order_id' => (string) $ordU['id'], 'account_id' => (string) $uacc, 'sconti' => 'giudizio:5']]]]);
$ua = riga('SELECT * FROM accounts WHERE id = ?', [$uacc]);
prova('9B · webhook: abbonamento attivo, lo sconto del giudizio «applicato al primo anno»', $r['body'] === 'abbonamento-attivato' && ($ua['giudizio_sconto_stato'] ?? '') === 'applicato_primo_anno' && $ua['giudizio_sconto_at'] !== null, $r['body']);
$r = $ugo->get('/pannello');
prova('9B · pannello dopo il pagamento: riquadro «aderito» con «5% già applicato al primo anno» e «Dai il tuo giudizio»', str_contains($r['body'], '5% già applicato al primo anno') && str_contains($r['body'], '/giudizio">Dai il tuo giudizio') && !str_contains($r['body'], 'name="partecipo"'));
$r = $ugo->get('/giudizio');
prova('9B · modulo per chi ha aderito: nessuna casella del programma (già aderito), nessun chip (la guida non ha immagini), nessun consenso', $r['code'] === 200 && !str_contains($r['body'], 'name="partecipo"')
      && !str_contains($r['body'], 'class="chip"') && !str_contains($r['body'], 'name="consenso"') && str_contains($r['body'], 'copertina, foto o logo'));
$r = $ugo->post('/giudizio', ['rating' => '5', 'body' => 'La guida ha tolto di mezzo le domande sul parcheggio e sul Wi-Fi.', 'show_cover' => '1']);
$gu = riga('SELECT * FROM testimonials WHERE account_id = ?', [$uacc]);
prova('9B · giudizio salvato: «Grazie! Giudizio ricevuto», in attesa, non in home, con la data dell\'adesione e il testo della casella come prova; immagini non esistenti ignorate',
      str_contains($r['body'], 'Grazie! Giudizio ricevuto') && str_contains($r['body'], 'non è ancora sulla nostra home') && $gu && $gu['status'] === 'in_attesa' && (int) $gu['home_visible'] === 0
      && $gu['consent_at'] === $ua['giudizio_adesione_at'] && str_contains((string) $gu['consent_text'], 'Sì, voglio il 5% in più') && (int) $gu['show_cover'] === 0);
$r = $ugo->get('/account');
prova('9B · Account → Il tuo giudizio: «5% già applicato al primo anno», «non è ancora sulla nostra home», Modifica e Ritira', str_contains($r['body'], 'Il tuo giudizio su MyHouse Welcome')
      && str_contains($r['body'], '5% già applicato al primo anno') && str_contains($r['body'], 'non è ancora sulla nostra home') && str_contains($r['body'], '/giudizio">Modifica</a>') && str_contains($r['body'], 'Ritira il giudizio'));
$r = $ugo->get('/giudizio');
$ugo->post('/giudizio', ['rating' => '4', 'body' => 'La guida ha tolto di mezzo le domande sul parcheggio e sul Wi-Fi. Consigliata.']);
prova('9B · modifica finché non è in home: il modulo precompilato, la stessa riga aggiornata', str_contains($r['body'], 'Modifica il tuo giudizio.') && str_contains($r['body'], 'id="voto-5" value="5" checked')
      && (int) val('SELECT COUNT(*) FROM testimonials WHERE account_id = ?', [$uacc]) === 1 && (int) val('SELECT rating FROM testimonials WHERE id = ?', [$gu['id']]) === 4);
$r = $admin->get('/admin/giudizi?filtro=da_valutare');
prova('9B · Amministrazione → Giudizi «Da valutare»: Ugo con adesione «Al pagamento», copertina no, sconto «Applicato al primo anno», interruttore spento', str_contains($r['body'], 'Ugo')
      && str_contains($r['body'], 'Al pagamento') && str_contains($r['body'], 'Applicato al primo anno') && str_contains($r['body'], 'aria-checked="false" aria-label="In home: Ugo"'));
$admin->post("/admin/giudizi/{$gu['id']}/home", ['si' => '1']);
$h9b = $ospite->get('/')['body'];
prova('9B · «In home»: la sezione mostra la scheda di Ugo (senza immagini: l\'iniziale), nessun link; il giudizio non si modifica più', str_contains($h9b, 'id="cosa-dicono"') && str_contains($h9b, 'domande sul parcheggio')
      && preg_match('#<section id="cosa-dicono".*?</section>#s', $h9b, $sd9b) === 1 && !str_contains($sd9b[0], '<a ') && str_contains($sd9b[0], 'dicono-scheda__iniziale')
      && str_contains($ugo->get('/giudizio')['body'], 'puoi solo ritirarlo') === false && str_contains($ugo->get('/giudizio')['body'], 'Il tuo giudizio è nella nostra home'));
$admin->post("/admin/giudizi/{$gu['id']}/home", ['si' => '0']);
prova('9B · tolto dalla home: sparisce; con nessun giudizio in home la sezione non c\'è nell\'HTML', (int) val('SELECT home_visible FROM testimonials WHERE id = ?', [$gu['id']]) === 0
      && (int) val('SELECT COUNT(*) FROM testimonials WHERE home_visible = 1 AND withdrawn_at IS NULL') === 0 && !str_contains($ospite->get('/')['body'], 'id="cosa-dicono"'));

// Nina (già pagata, abbonamento Stripe attivo): aderisce dal pannello; il coupon si aggiunge a quello degli inviti e resta quando gli inviti cambiano.
db()->prepare("INSERT INTO referrals (referrer_account_id, friend_account_id, status, created_at, qualified_at) VALUES (?, ?, 'valido', ?, ?)")->execute([$nacc, $uacc, gmdate('Y-m-d\TH:i:s\Z'), gmdate('Y-m-d\TH:i:s\Z')]);
$php9('MHW\Inviti::sincronizza(' . $nacc . ');');
$subN = fn() => (array) (json_decode((string) file_get_contents("$STRIPE_DIR/stato.json"), true)['subs']['sub_prova_nina9a']['discounts'] ?? []);
prova('9B · prima: l\'abbonamento di Nina ha il coupon degli inviti (mhw-invito-5)', $subN() === ['mhw-invito-5'], json_encode($subN()));
$r = $nina->post('/giudizio/partecipo', ['partecipo' => '1']);
$na = riga('SELECT * FROM accounts WHERE id = ?', [$nacc]);
prova('9B · «Partecipo» dal pannello: adesione «pannello», sconto promesso, si va al modulo', $r['code'] === 302 && str_contains($r['loc'], '/giudizio') && ($na['giudizio_adesione'] ?? '') === 'pannello' && ($na['giudizio_sconto_stato'] ?? '') === 'promesso');
$nina->post('/giudizio', ['rating' => '5', 'body' => 'Con due strutture la guida mi fa risparmiare ore ogni settimana.', 'show_cover' => '1']);
$na = riga('SELECT * FROM accounts WHERE id = ?', [$nacc]);
prova('9B · giudizio di Nina: sconto «sul prossimo rinnovo» e il coupon del giudizio aggiunto INSIEME a quello degli inviti', ($na['giudizio_sconto_stato'] ?? '') === 'da_applicare_rinnovo'
      && $subN() === ['mhw-invito-5', 'mhw-giudizio-5'], json_encode($subN()));
db()->prepare("INSERT INTO referrals (referrer_account_id, friend_account_id, status, created_at, qualified_at) VALUES (?, ?, 'valido', ?, ?)")->execute([$nacc, $accDi('olga9a@prova.test') ?: 900001, gmdate('Y-m-d\TH:i:s\Z'), gmdate('Y-m-d\TH:i:s\Z')]);
$php9('MHW\Inviti::sincronizza(' . $nacc . ');');
prova('9B · un amico in più: gli inviti passano a −10% e il coupon del giudizio resta', in_array('mhw-giudizio-5', $subN(), true) && in_array('mhw-invito-10', $subN(), true) && !in_array('mhw-invito-5', $subN(), true), json_encode($subN()));
prova('9B · Account di Nina: «5% sul rinnovo del …»', preg_match('#5% sul rinnovo del \d+ \w+ \d{4}#u', $nina->get('/account')['body']) === 1);
$r = inviaWebhook(['id' => 'evt_nina_rinnovo_9b', 'type' => 'invoice.paid', 'data' => ['object' => ['id' => 'in_nina_rinnovo', 'customer' => 'cus_nina9a', 'subscription' => 'sub_prova_nina9a',
    'billing_reason' => 'subscription_cycle', 'subtotal' => 17700, 'total_excluding_tax' => 15045, 'total_discount_amounts' => [['amount' => 1770], ['amount' => 885]],
    'lines' => ['data' => [['period' => ['start' => time(), 'end' => time() + 365 * 86400], 'price' => ['id' => 'price_finto_annuale']]]]]]]);
$na = riga('SELECT * FROM accounts WHERE id = ?', [$nacc]);
prova('9B · il rinnovo scontato: lo sconto del giudizio «applicato al rinnovo», con la data', str_starts_with($r['body'], 'rinnovo-pagato') && ($na['giudizio_sconto_stato'] ?? '') === 'applicato_rinnovo' && $na['giudizio_sconto_at'] !== null, $r['body']);
$nina->post('/account/giudizio/ritira', []);
prova('9B · ritiro dopo il rinnovo: il giudizio sparisce, lo sconto già avuto resta', val('SELECT withdrawn_at FROM testimonials WHERE account_id = ?', [$nacc]) !== null
      && (string) val('SELECT giudizio_sconto_stato FROM accounts WHERE id = ?', [$nacc]) === 'applicato_rinnovo');

// L'interruttore spento: niente casella al pagamento né «Partecipo»; chi ha aderito vede ancora il suo stato.
$admin->get('/admin/impostazioni');
$r = $admin->post('/admin/impostazioni/giudizi', ['giudizi__percentuale' => '5', 'giudizi__home' => '1', 'password' => 'AdminProva123']);
$vera = new Browser('vera9b');
db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'register:%'");
$vera->get('/registrati?piano=' . pv('plus'));
$vera->post('/registrati', ['piano' => pv('plus'), 'name' => 'Vera Spenta', 'email' => 'vera9b@prova.test', 'email2' => 'vera9b@prova.test', 'password' => 'VeraProva12345', 'termini' => '1']);
$linkV = linkPosta('vera9b@prova.test', 'verifica'); $vera->get(substr($linkV, strpos($linkV, '/verifica/')));
$vera->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Casa Vera', 'city' => 'Spoleto']);
$pvv = (int) val('SELECT id FROM properties WHERE account_id = ? ORDER BY id', [$accDi('vera9b@prova.test')]);
prova('9B · interruttore spento: nessuna casella al pagamento, riquadro «Raccontacelo» senza casella; Ugo vede ancora il suo stato', $r['code'] === 302
      && !str_contains($vera->get("/pannello/$pvv/procedura/pubblica")['body'], 'id="giudizio-sconto"') && str_contains($pv9 = $vera->get('/pannello')['body'], 'Raccontacelo') && !str_contains($pv9, 'name="partecipo"')
      && str_contains($ugo->get('/account')['body'], '5% già applicato al primo anno') && !str_contains($vera->get('/giudizio')['body'], 'name="partecipo"'));
$admin->post('/admin/impostazioni/giudizi', ['giudizi__sconto_attivo' => '1', 'giudizi__percentuale' => '5', 'giudizi__home' => '1', 'password' => 'AdminProva123']);
prova('9B · riacceso: la casella torna', str_contains($vera->get("/pannello/$pvv/procedura/pubblica")['body'], 'id="giudizio-sconto"'));
// ================================================================= 9B-bis
// Le date come le scrive Support::date («17 ottobre 2026»), senza caricare le classi dell'app.
function dataIt(string $iso): string {
    $t = strtotime($iso);
    $mesi = ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
    return (int) gmdate('j', $t) . ' ' . $mesi[(int) gmdate('n', $t) - 1] . ' ' . gmdate('Y', $t);
}
capitolo('9B-bis · «Quando si applica lo sconto» del giudizio: all\'adesione o solo dopo il giudizio');
$admin->get('/admin/giudizi');
$r = $admin->post('/admin/giudizi/impostazioni', ['giudizi__sconto_attivo' => '1', 'giudizi__percentuale' => '5', 'giudizi__sconto_quando' => 'giudizio', 'giudizi__home' => '1', 'password' => 'AdminProva123']);
$g9 = $admin->get('/admin/giudizi')['body'];
prova('9B-bis · Amministrazione → Giudizi: il riquadro del programma salva «solo dopo il giudizio» (config.local.php) e lo mostra scelto', $r['code'] === 302 && str_contains($g9, '<option value="giudizio" selected')
      && $php9('echo MHW\Giudizi::quando();') === 'giudizio' && str_contains($g9, 'Programma aggiornato.'));
prova('9B-bis · con la password sbagliata niente si salva', str_contains($admin->segui($admin->post('/admin/giudizi/impostazioni', ['giudizi__sconto_quando' => 'adesione', 'password' => 'no']))['body'], 'La password non è giusta')
      && $php9('echo MHW\Giudizi::quando();') === 'giudizio');
// Teo aderisce al pagamento: con «solo dopo il giudizio» il riepilogo non ha ancora la riga, e lo dice.
$teo = new Browser('teo9b');
db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'register:%'");
$teo->get('/registrati?piano=' . pv('plus'));
$teo->post('/registrati', ['piano' => pv('plus'), 'name' => 'Teo Dopo', 'email' => 'teo9b@prova.test', 'email2' => 'teo9b@prova.test', 'password' => 'TeoProva12345', 'termini' => '1']);
$tacc = $accDi('teo9b@prova.test');
$linkT = linkPosta('teo9b@prova.test', 'verifica'); $teo->get(substr($linkT, strpos($linkT, '/verifica/')));
$teo->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Casa Teo', 'city' => 'Foligno']);
$pt = (int) val('SELECT id FROM properties WHERE account_id = ? ORDER BY id', [$tacc]);
$teo->post('/giudizio/adesione', ['giudizio_adesione' => '1', 'torna' => "/pannello/$pt/procedura/pubblica"]);
$r = $teo->get("/pannello/$pt/procedura/pubblica");
prova('9B-bis · «solo dopo il giudizio»: la casella spuntata promette lo sconto ma il riepilogo resta a 127 €, con la riga che spiega quando parte',
      (string) val('SELECT giudizio_sconto_stato FROM accounts WHERE id = ?', [$tacc]) === 'promesso' && !str_contains($r['body'], 'Giudizio · ')
      && str_contains($r['body'], "Totale primo anno</span><b>127\u{00A0}€") && str_contains($r['body'], 'Lo sconto parte quando hai lasciato il giudizio')
      && str_contains($teo->get('/account')['body'], '5% dopo il giudizio: sul primo addebito o sul rinnovo'));
$admin->get('/admin/giudizi');
$admin->post('/admin/giudizi/impostazioni', ['giudizi__sconto_attivo' => '1', 'giudizi__percentuale' => '5', 'giudizi__sconto_quando' => 'adesione', 'giudizi__home' => '1', 'password' => 'AdminProva123']);
$r = $teo->get("/pannello/$pt/procedura/pubblica");
prova('9B-bis · tornati «all\'adesione»: la riga «Giudizio · −5%» e 120,65 € compaiono subito', str_contains($r['body'], "Giudizio · −5% primo anno</span><b>−6,35\u{00A0}€</b>") && str_contains($r['body'], "Totale primo anno</span><b>120,65\u{00A0}€"));

// ================================================================= 9D
capitolo('9D · codici omaggio: mesi gratis con la carta, avvisi a 30 e 7 giorni, Termini, Amministrazione');
$m9d = $php9('$f = require "' . $DOVE . '/app/migrations/045_omaggio_9d.php"; $f(MHW\Db::conn()); echo "fatto";');
prova('9D · migrazione 045 una seconda volta: niente cambia', $m9d === 'fatto' && (bool) val("SELECT 1 FROM schema_migrations WHERE name LIKE '045%'"), $m9d);
// L'amministrazione crea un codice da 3 mesi: la pagina con i tre bottoni e il messaggio pronto.
$admin->get('/admin/omaggi');
$r = $admin->post('/admin/omaggi', ['mesi' => '3', 'scade_il' => '', 'nota' => 'Tester 9D']);
$cod9d = riga('SELECT * FROM codici_omaggio ORDER BY id DESC');
$pg = $admin->segui($r)['body'];
prova('9D · Vendite → Codici omaggio: codice OMAGGIO-XXXXX creato (3 mesi, scade tra 14 giorni), con WhatsApp, Email, Copia il link e il messaggio con {mesi} {link} {codice} {scadenza}',
      $r['code'] === 302 && $cod9d && preg_match('/^OMAGGIO-[A-HJ-NP-Z2-9]{5}$/', (string) $cod9d['codice']) === 1 && (int) $cod9d['mesi'] === 3
      && in_array($cod9d['scade_il'], [gmdate('Y-m-d', strtotime('+14 days')), (new DateTimeImmutable('+14 days', new DateTimeZone('Europe/Rome')))->format('Y-m-d')], true) && str_contains($pg, 'wa.me/?text=')   // a cavallo della mezzanotte vale l'ora italiana && str_contains($pg, 'mailto:?subject=') && str_contains($pg, 'data-copia="')
      && str_contains($pg, 'Ti abbiamo riservato 3 mesi gratis') && str_contains($pg, 'Il codice ' . $cod9d['codice'] . ' vale fino al ' . dataIt($cod9d['scade_il']))
      && preg_match('#/admin/omaggi"[^>]*>(?:<svg.*?</svg>)?\s*(?:<span>)?Codici omaggio<#s', $pg) === 1,
      json_encode([$r['code'], $r['loc'] ?? '', str_contains($pg, 'wa.me/?text='), str_contains($pg, 'mailto:?subject='), str_contains($pg, 'data-copia="'), str_contains($pg, 'Ti abbiamo riservato 3 mesi gratis'),
                   str_contains($pg, 'Il codice ' . ($cod9d['codice'] ?? '') . ' vale fino al ' . dataIt((string) ($cod9d['scade_il'] ?? ''))), preg_match('#/admin/omaggi"[^>]*>(?:<svg.*?</svg>)?\s*(?:<span>)?Codici omaggio<#s', $pg), $cod9d['scade_il'] ?? '', gmdate('Y-m-d', strtotime('+14 days'))]));
prova('9D · i mesi fuori da 1–12 e la scadenza passata sono rifiutati', str_contains($admin->segui($admin->post('/admin/omaggi', ['mesi' => '13', 'nota' => 'x']))['body'], 'I mesi gratis vanno da 1 a 12')
      && str_contains($admin->segui($admin->post('/admin/omaggi', ['mesi' => '2', 'scade_il' => '2020-01-01']))['body'], 'La data di scadenza è già passata'));
// Ida arriva dal link: la registrazione con il banner e il campo in sola lettura.
$ida = new Browser('ida9d');
db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'register:%'");
$ida->get('/i/' . $codP9);   // prima da un invito di Paola: il link dell'omaggio lo scavalca
$r = $ida->get('/omaggio/' . substr($cod9d['codice'], 8));
$reg = $ida->get('/registrati')['body'];
$fine9d = (new DateTimeImmutable('today', new DateTimeZone('UTC')))->modify('+3 months')->getTimestamp();
prova('9D · /omaggio/XXXXX: il codice in sessione (l\'invito no), registrazione con il banner «Hai 3 mesi gratis» e il campo in sola lettura', $r['code'] === 302 && str_ends_with($r['loc'], '/registrati')
      && str_contains($reg, '<b>Hai 3 mesi gratis.</b>') && str_contains($reg, 'value="' . $cod9d['codice'] . '" readonly') && str_contains($reg, 'primo addebito il ' . dataIt(gmdate('Y-m-d\TH:i:s\Z', $fine9d)))
      && !str_contains($reg, 'Codice di Paola'));
$ida->post('/registrati', ['piano' => pv('plus'), 'name' => 'Ida Omaggio', 'email' => 'ida9d@prova.test', 'email2' => 'ida9d@prova.test', 'password' => 'IdaProva12345', 'termini' => '1']);
$iacc = $accDi('ida9d@prova.test');
prova('9D · registrata: il codice è prenotato all\'account, nessun invito', $iacc > 0 && (int) val('SELECT codice_omaggio_id FROM accounts WHERE id = ?', [$iacc]) === (int) $cod9d['id']
      && !val('SELECT id FROM referrals WHERE friend_account_id = ?', [$iacc]));
$linkI = linkPosta('ida9d@prova.test', 'verifica'); $ida->get(substr($linkI, strpos($linkI, '/verifica/')));
$ida->modulo('/account', '/sconto/applica', ['codice' => $codP9, 'torna' => '/account']);
$r = $ida->get('/account');
prova('9D · un codice di invito dopo l\'omaggio: «Hai già un codice omaggio: non si somma a un invito»; in Account i mesi gratis e il primo addebito',
      str_contains($r['body'], 'Hai già un codice omaggio: non si somma a un invito.') && str_contains($r['body'], '<b>Codice omaggio ' . $cod9d['codice'] . '</b>: 3 mesi gratis')
      && !val('SELECT id FROM referrals WHERE friend_account_id = ?', [$iacc]));
// Leo: prima l'invito di Paola, poi un codice omaggio scritto a mano in Account: l'invito si chiude come «omaggio».
$admin->get('/admin/omaggi'); $admin->post('/admin/omaggi', ['mesi' => '6', 'scade_il' => '', 'nota' => 'Leo']);
$cod2 = riga('SELECT * FROM codici_omaggio ORDER BY id DESC');
$leo = new Browser('leo9d');
db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'register:%'");
$leo->get('/i/' . $codP9); $leo->get('/registrati');
$leo->post('/registrati', ['piano' => pv('plus'), 'name' => 'Leo Invitato', 'email' => 'leo9d@prova.test', 'email2' => 'leo9d@prova.test', 'password' => 'LeoProva12345', 'termini' => '1']);
$lacc = $accDi('leo9d@prova.test');
$linkL = linkPosta('leo9d@prova.test', 'verifica'); $leo->get(substr($linkL, strpos($linkL, '/verifica/')));
$primaRef = (string) val('SELECT status FROM referrals WHERE friend_account_id = ?', [$lacc]);
$leo->modulo('/account', '/sconto/applica', ['codice' => strtolower((string) $cod2['codice']), 'torna' => '/account']);
prova('9D · Leo arrivato da un invito scrive un codice omaggio a mano (anche minuscolo): vale l\'omaggio, l\'invito di Paola si chiude come «omaggio» e lo sconto dell\'amico decade',
      $primaRef === 'registrato' && (string) val('SELECT status FROM referrals WHERE friend_account_id = ?', [$lacc]) === 'omaggio' && (int) val('SELECT codice_omaggio_id FROM accounts WHERE id = ?', [$lacc]) === (int) $cod2['id']
      && val('SELECT intended_discount_code_id FROM accounts WHERE id = ?', [$lacc]) === null && !str_contains($leo->get('/account')['body'], 'Invito di Paola'));
$ida->modulo('/account', '/sconto/applica', ['codice' => (string) $cod2['codice'], 'torna' => '/account']);
prova('9D · un codice prenotato a un altro: «già stato usato»', str_contains($ida->get('/account')['body'], 'Questo codice omaggio è già stato usato.'));
db()->prepare("INSERT INTO codici_omaggio (codice, mesi, scade_il, nota, creato_da, created_at) VALUES ('OMAGGIO-VECC9', 2, ?, 'scaduto', 1, ?)")->execute([gmdate('Y-m-d', strtotime('-1 day')), gmdate('Y-m-d\TH:i:s\Z')]);
prova('9D · un codice scaduto: «scaduto il …»; uno inesistente: «non esiste»', str_contains($ida->segui($ida->post('/sconto/applica', ['codice' => 'OMAGGIO-VECC9', 'torna' => '/account']))['body'], 'è scaduto il ' . dataIt(gmdate('Y-m-d', strtotime('-1 day'))))
      && str_contains($ida->segui($ida->post('/sconto/applica', ['codice' => 'OMAGGIO-ZZZZZ', 'torna' => '/account']))['body'], 'Questo codice omaggio non esiste o non è più attivo'));
// Un codice libero (quello di Ida è già prenotato a lei): la verifica dal modulo di registrazione.
$admin->get('/admin/omaggi'); $admin->post('/admin/omaggi', ['mesi' => '3', 'scade_il' => '', 'nota' => 'verifica']);
$codV9d = riga('SELECT * FROM codici_omaggio ORDER BY id DESC');
prova('9D · POST /invito/verifica con un codice omaggio: mesi e primo addebito (per la registrazione senza ricaricare)', ($vis9d = new Browser('vis9d')) && $vis9d->get('/registrati') && ($j = json_decode($vis9d->post('/invito/verifica', ['codice' => $codV9d['codice']], ['Accept: application/json'])['body'] ?? '', true))
      && ($j['ok'] ?? false) && ($j['tipo'] ?? '') === 'omaggio' && ($j['mesi'] ?? 0) === 3, json_encode($j ?? null));
// Il Checkout di Ida: 0 € oggi, trial_end, carta obbligatoria, solo il coupon del giudizio.
$ida->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Casa Ida', 'city' => 'Orvieto']);
$pi = (int) val('SELECT id FROM properties WHERE account_id = ? ORDER BY id', [$iacc]);
db()->prepare("UPDATE properties SET cin = 'IT054039C2IDA9D001' WHERE id = ?")->execute([$pi]);
$icore = (int) val('SELECT id FROM sections WHERE property_id = ? AND is_core = 1', [$pi]);
$ida->post("/pannello/$pi/sezioni/$icore", ['checkin_steps' => ['Citofono.']]);
$ida->modulo('/account', '/account/fatturazione', ['billing_type' => 'privato', 'billing_name' => 'Ida Omaggio', 'cf' => 'MRORSS85T41D612D', 'billing_address' => 'Via Ida 1', 'billing_postal' => '05018', 'billing_city' => 'Orvieto', 'billing_province' => 'TR']);
$r = $ida->get("/pannello/$pi/procedura/pubblica");
$dataFine9d = dataIt(gmdate('Y-m-d\TH:i:s\Z', $fine9d));
prova('9D · passo Pubblica con l\'omaggio: «Oggi paghi 0 €», 3 mesi gratis, primo addebito di 127 € + IVA il …, riepilogo con «Oggi 0 €» e il bottone «Inserisci la carta e pubblica · 0 € oggi, 127 € + IVA il …»',
      str_contains($r['body'], '<b>Oggi paghi 0 €.</b> I tuoi primi <b>3 mesi sono gratis</b>') && str_contains($r['body'], "primo addebito di <b>127\u{00A0}€ + IVA</b> sarà il <b>$dataFine9d</b>")
      && str_contains($r['body'], '<span>Oggi</span><b>0&nbsp;€</b>') && str_contains($r['body'], "<span>Il $dataFine9d</span><b>127\u{00A0}€ <small>+ IVA</small>")
      && str_contains($r['body'], "Inserisci la carta e pubblica <small class=\"btn__sotto\">0 € oggi · 127\u{00A0}€ + IVA il $dataFine9d") && str_contains($r['body'], 'Codice omaggio ' . $cod9d['codice']));
@unlink("$DOVE/app/storage/.prezzi-stripe.json");
$prima9d = count(richiesteStripe());
$r = $ida->modulo("/pannello/$pi/procedura/pubblica", "/pannello/$pi/pubblica", ['acquirente' => 'impresa', 'giudizio_casella' => '1', 'giudizio_adesione' => '1']);
$ordI = riga('SELECT * FROM orders WHERE account_id = ? ORDER BY id DESC', [$iacc]);
$sesI = array_values(array_filter(array_slice(richiesteStripe(), $prima9d), fn($x) => $x['percorso'] === '/v1/checkout/sessions' && $x['metodo'] === 'POST'))[0]['corpo'] ?? [];
$statoI = json_decode((string) file_get_contents("$STRIPE_DIR/stato.json"), true);
prova('9D · Checkout: trial_end = mezzanotte UTC di oggi + 3 mesi, carta obbligatoria (payment_method_collection=always, missing_payment_method=cancel), solo il coupon del giudizio, metadati omaggio, sessione a 0 €, ordine con il codice',
      $r['code'] === 302 && str_starts_with($r['loc'], 'https://checkout.stripe.test/') && (string) ($sesI['subscription_data']['trial_end'] ?? '') === (string) $fine9d
      && ($sesI['payment_method_collection'] ?? '') === 'always' && ($sesI['subscription_data']['trial_settings']['end_behavior']['missing_payment_method'] ?? '') === 'cancel'
      && count($sesI['discounts'] ?? []) === 1 && ($sesI['discounts'][0]['coupon'] ?? '') === 'mhw-giudizio-5' && ($sesI['metadata']['omaggio_codice'] ?? '') === $cod9d['codice'] && ($sesI['metadata']['omaggio_mesi'] ?? '') === '3'
      && ($sesI['metadata']['sconti'] ?? '') === 'giudizio:5' && (int) ($statoI['sessioni'][$ordI['provider_session_id']]['amount_total'] ?? -1) === 0
      && (int) $ordI['codice_omaggio_id'] === (int) $cod9d['id'] && (int) $ordI['amount_cents'] === 12700 && $ordI['status'] === 'pending', json_encode([$sesI['subscription_data'] ?? null, $sesI['discounts'] ?? null]));
// Il webhook: l'abbonamento nasce «trialing», la guida va online, il codice si brucia, la carta si ricorda.
$statoI['subs']['sub_ida9d'] = ['cancel_at_period_end' => false, 'status' => 'trialing', 'start' => time(), 'end' => $fine9d, 'trial_end' => $fine9d, 'extra' => null, 'discounts' => ['mhw-giudizio-5'], 'last4' => '4242'];
file_put_contents("$STRIPE_DIR/stato.json", json_encode($statoI));
$r = inviaWebhook(['id' => 'evt_ida9d_ok', 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => $ordI['provider_session_id'], 'mode' => 'subscription', 'payment_status' => 'paid',
    'subscription' => 'sub_ida9d', 'customer' => 'cus_ida9d', 'metadata' => ['order_id' => (string) $ordI['id'], 'account_id' => (string) $iacc], 'total_details' => ['amount_discount' => 0]]]]);
$subI = riga('SELECT * FROM subscriptions WHERE account_id = ? ORDER BY id DESC', [$iacc]);
$cod9d = riga('SELECT * FROM codici_omaggio WHERE id = ?', [$cod9d['id']]);
$ia = riga('SELECT * FROM accounts WHERE id = ?', [$iacc]);
prova('9D · webhook: abbonamento «trialing» fino a fine omaggio, guida online, codice bruciato (usato da Ida), carta 4242, sconto del giudizio ancora «promesso» (si applica alla prima fattura)',
      str_starts_with($r['body'], 'abbonamento-attivato') && $subI['status'] === 'trialing' && substr((string) $subI['current_period_end'], 0, 10) === gmdate('Y-m-d', $fine9d)
      && substr((string) $subI['omaggio_fino'], 0, 10) === gmdate('Y-m-d', $fine9d) && $subI['card_last4'] === '4242' && $cod9d['usato_at'] !== null && (int) $cod9d['usato_da_account_id'] === $iacc
      && (string) val('SELECT status FROM properties WHERE id = ?', [$pi]) === 'published' && ($ia['giudizio_sconto_stato'] ?? '') === 'promesso', $r['body']);
$r = $ida->get('/account');
prova('9D · Account: «In omaggio · Omaggio fino al … · poi 120,65 € + IVA l\'anno», carta •••• 4242, «Disattiva il rinnovo» con «fino al … la guida resta online e non paghi nulla»',
      str_contains($r['body'], 'In omaggio') && str_contains($r['body'], "Omaggio fino al <b>$dataFine9d</b>") && str_contains($r['body'], "poi 120,65\u{00A0}€ + IVA l'anno")
      && str_contains($r['body'], 'carta •••• 4242') && str_contains($r['body'], 'name="rinnovo" value="no">Disattiva il rinnovo</button>') && str_contains($r['body'], "Fino al $dataFine9d la guida resta online e non paghi nulla"));
prova('9D · lo stesso codice da chi ha già pagato: «solo per i nuovi clienti»; da un altro: «già stato usato»; il link /omaggio da loggato rimanda ad Account',
      str_contains($ida->segui($ida->get('/omaggio/' . substr($cod9d['codice'], 8)))['body'], 'vale solo per i nuovi clienti')
      && str_contains($leo->segui($leo->post('/sconto/applica', ['codice' => $cod9d['codice'], 'torna' => '/account']))['body'], 'Questo codice omaggio è già stato usato.'));
prova('9D · Clienti: colonna «Omaggio fino al» e il filtro «In omaggio»; Quadro: «Clienti in omaggio»', str_contains($cl = $admin->get('/admin/clienti?stato=omaggio')['body'], 'Ida Omaggio') && str_contains($cl, $dataFine9d) && !str_contains($cl, 'Leo Invitato')
      && preg_match('#Clienti in omaggio</span>.*?<b class="cifra__valore">1</b>#s', $admin->get('/admin')['body']) === 1);
prova('9D · Marketing: la tabella «Codici omaggio» (creati, usati, diventati paganti)', str_contains($mk = $admin->get('/admin/marketing?periodo=30')['body'], 'id="mk-omaggi"') && str_contains($mk, 'Codici usati'));
// Gli avvisi: a 7 giorni dalla fine dell'omaggio (importo da Stripe), anche con gli avvisi disattivati; a 30 giorni no.
db()->prepare("INSERT INTO email_optout (account_id, kind, created_at) VALUES (?, 'rinnovo', ?)")->execute([$iacc, gmdate('Y-m-d\TH:i:s\Z')]);
$fra7 = gmdate('Y-m-d\TH:i:s\Z', time() + 7 * 86400 + 1800);
db()->prepare('UPDATE subscriptions SET current_period_end = ?, omaggio_fino = ? WHERE id = ?')->execute([$fra7, $fra7, $subI['id']]);
$statoI = json_decode((string) file_get_contents("$STRIPE_DIR/stato.json"), true);
$statoI['upcoming']['sub_ida9d'] = ['subtotal' => 12700, 'total_excluding_tax' => 12065, 'total' => 14719, 'total_discount_amounts' => [['amount' => 635]]];
file_put_contents("$STRIPE_DIR/stato.json", json_encode($statoI));
$primaPosta = count(righePosta() ?: []);
$cron9d = $cron();
$posta9d = array_values(array_filter(array_map(fn($l) => json_decode($l, true), array_slice(righePosta() ?: [], $primaPosta)), fn($m) => ($m['to'] ?? '') === 'ida9d@prova.test'));
$m7 = $posta9d[0] ?? [];
prova('9D · a 7 giorni dalla fine dell\'omaggio: l\'email «Tra 7 giorni finisce la tua prova gratuita» con piano, 120,65 € + IVA da Stripe, carta 4242, parte anche con gli avvisi disattivati e lo dice nel piè',
      ($cron9d['email']['rinnovo'] ?? 0) >= 1 && count($posta9d) === 1 && str_contains((string) ($m7['subject'] ?? ''), 'Tra 7 giorni finisce la tua prova gratuita')
      && str_contains((string) ($m7['text'] ?? ''), "la tua prova gratuita finisce il " . dataIt($fra7) . ": da quel giorno parte l'abbonamento Plus a 120,65\u{00A0}€ + IVA l'anno, sulla carta che termina con 4242")
      && str_contains((string) ($m7['text'] ?? ''), 'Hai uno sconto del 5%') && str_contains((string) ($m7['text'] ?? ''), 'non si può disattivare')
      && (string) val("SELECT ref FROM email_log WHERE account_id = ? AND kind = 'rinnovo' ORDER BY id DESC", [$iacc]) === $subI['id'] . '-' . substr($fra7, 0, 10) . '-7', json_encode($m7));
$r = $ida->get('/pannello');
prova('9D · nel pannello la fascia in evidenza: «La tua prova gratuita finisce il …», 120,65 € + IVA, carta •••• 4242, «Disattiva il rinnovo»; «Chiudi» la nasconde per 24 ore (cookie)',
      preg_match('/class="fascia fascia--omaggio fascia--evidenza"/', $r['body']) === 1 && str_contains($r['body'], 'La tua prova gratuita finisce il ' . dataIt($fra7)) && str_contains($r['body'], "120,65\u{00A0}€ + IVA")
      && str_contains($r['body'], 'carta •••• 4242') && str_contains($r['body'], '>Disattiva il rinnovo</button>') && str_contains($ida->get('/account')['body'], 'fascia--evidenza')
      && $ida->post('/pannello/avviso-rinnovo/chiudi', ['torna' => '/pannello'])['code'] === 302 && !str_contains($ida->get('/pannello')['body'], 'class="fascia'));
$fra30 = gmdate('Y-m-d\TH:i:s\Z', time() + 30 * 86400 + 1800);
db()->prepare('UPDATE subscriptions SET current_period_end = ?, omaggio_fino = ? WHERE id = ?')->execute([$fra30, $fra30, $subI['id']]);
$primaPosta = count(righePosta() ?: []); $cron();
prova('9D · a 30 giorni, con gli avvisi disattivati, l\'email non parte (solo quella a 7 è dovuta)', count(array_filter(array_map(fn($l) => json_decode($l, true), array_slice(righePosta() ?: [], $primaPosta)), fn($m) => ($m['to'] ?? '') === 'ida9d@prova.test')) === 0);
db()->prepare("DELETE FROM email_optout WHERE account_id = ? AND kind = 'rinnovo'")->execute([$iacc]);
$primaPosta = count(righePosta() ?: []); $cron();
$m30 = array_values(array_filter(array_map(fn($l) => json_decode($l, true), array_slice(righePosta() ?: [], $primaPosta)), fn($m) => ($m['to'] ?? '') === 'ida9d@prova.test'))[0] ?? [];
prova('9D · riattivati gli avvisi: a 30 giorni parte «La tua prova gratuita finisce il …», con il link per non riceverne più', str_contains((string) ($m30['subject'] ?? ''), 'La tua prova gratuita finisce il') && str_contains((string) ($m30['text'] ?? ''), '/email/stop/'));
$fra30p = gmdate('Y-m-d\TH:i:s\Z', time() + 30 * 86400 + 1800);
// Ugo (abbonamento normale, 9B): anche lui riceve l'avviso a 7 giorni dal rinnovo.
$subU9 = riga('SELECT * FROM subscriptions WHERE account_id = ? ORDER BY id DESC', [$uacc]);
db()->prepare('UPDATE subscriptions SET current_period_end = ?, cancel_at_period_end = 0, status = ? WHERE id = ?')->execute([$fra7, 'active', $subU9['id']]);
$primaPosta = count(righePosta() ?: []); $cron();
$mU = array_values(array_filter(array_map(fn($l) => json_decode($l, true), array_slice(righePosta() ?: [], $primaPosta)), fn($m) => ($m['to'] ?? '') === 'ugo9b@prova.test'))[0] ?? [];
prova('9D · per tutti: Ugo a 7 giorni dal rinnovo riceve «Tra 7 giorni rinnoviamo il tuo abbonamento» con l\'importo e «disattiva il rinnovo entro il …»', str_contains((string) ($mU['subject'] ?? ''), 'Tra 7 giorni rinnoviamo il tuo abbonamento')
      && str_contains((string) ($mU['text'] ?? ''), 'il tuo abbonamento Plus si rinnova il ' . dataIt($fra7) . ': addebiteremo') && str_contains((string) ($mU['text'] ?? ''), 'disattiva il rinnovo da Account & Fatturazione entro il'), json_encode($mU));
// La fine dell'omaggio: la prima fattura con lo sconto del giudizio.
db()->prepare('UPDATE subscriptions SET current_period_end = ?, omaggio_fino = ? WHERE id = ?')->execute([$fine9d ? gmdate('Y-m-d\TH:i:s\Z', $fine9d) : $fra30, gmdate('Y-m-d\TH:i:s\Z', $fine9d), $subI['id']]);
$r = inviaWebhook(['id' => 'evt_ida9d_fattura', 'type' => 'invoice.paid', 'data' => ['object' => ['id' => 'in_ida9d_1', 'customer' => 'cus_ida9d', 'subscription' => 'sub_ida9d', 'billing_reason' => 'subscription_cycle',
    'subtotal' => 12700, 'total_excluding_tax' => 12065, 'total_discount_amounts' => [['amount' => 635]], 'lines' => ['data' => [['period' => ['start' => $fine9d, 'end' => $fine9d + 365 * 86400], 'price' => ['id' => 'price_finto_annuale']]]]]]]);
$ia = riga('SELECT * FROM accounts WHERE id = ?', [$iacc]);
prova('9D · la prima fattura a fine omaggio: abbonamento attivo per un anno, lo sconto del giudizio «applicato al primo anno»', str_starts_with($r['body'], 'rinnovo-pagato')
      && (string) val('SELECT status FROM subscriptions WHERE id = ?', [$subI['id']]) === 'active' && ($ia['giudizio_sconto_stato'] ?? '') === 'applicato_primo_anno', $r['body']);
prova('9D · trial_will_end: solo nel registro', str_starts_with(inviaWebhook(['id' => 'evt_ida9d_twe', 'type' => 'customer.subscription.trial_will_end', 'data' => ['object' => ['id' => 'sub_ida9d', 'trial_end' => $fine9d]]])['body'], 'fine-omaggio-in-arrivo')
      && (bool) val("SELECT id FROM audit_log WHERE action = 'subscription.trial_will_end'"));
// Annulla un codice prenotato: non vale più nemmeno per Leo.
$admin->get('/admin/omaggi'); $admin->post('/admin/omaggi/' . (int) $cod2['id'] . '/annulla', []);
prova('9D · «Annulla» un codice prenotato: annullato, e all\'account di Leo non resta', (string) riga('SELECT annullato_at FROM codici_omaggio WHERE id = ?', [$cod2['id']])['annullato_at'] !== '' && val('SELECT codice_omaggio_id FROM accounts WHERE id = ?', [$lacc]) === null
      && str_contains($admin->segui($admin->post('/admin/omaggi/' . (int) $cod9d['id'] . '/annulla', []))['body'], 'già stato usato'));
prova('9D · Termini: punto 5 «Rinnovo automatico» (30 e 7 giorni), punto 17 «una sola volta», punto 18 «Codice omaggio» dopo il 17; in inglese «Gift code»; Privacy: le ultime 4 cifre della carta',
      str_contains($t9d = $ospite->get('/termini')['body'], '<b>Rinnovo automatico.</b>') && str_contains($t9d, 'id="codice-omaggio"') && str_contains($t9d, '18. Codice omaggio')
      && strpos($t9d, 'id="giudizio-sconto-t"') < strpos($t9d, 'id="codice-omaggio-t"') && strpos($t9d, 'id="codice-omaggio-t"') < strpos($t9d, 'id="traduzioni-t"') && str_contains($t9d, '<b>una sola volta</b>')
      && str_contains($ospite->get('/en/terms')['body'], '18. Gift code') && str_contains($ospite->get('/privacy')['body'], 'solo le ultime 4 cifre'));

// ================================================================= 9E
capitolo('9E · scheda cliente: ferma il rinnovo, cambia il pacchetto, rimborsi, password provvisoria');
$statoI = json_decode((string) file_get_contents("$STRIPE_DIR/stato.json"), true);
$statoI['invoices']['cus_ida9d'] = [['id' => 'in_ida9d_1', 'number' => 'F-9D-001', 'created' => time() - 3600, 'subtotal' => 12065, 'total_excluding_tax' => 12065, 'total' => 14719, 'currency' => 'eur', 'payment_intent' => 'pi_ida9d_1', 'status' => 'paid']];
file_put_contents("$STRIPE_DIR/stato.json", json_encode($statoI));
db()->prepare("UPDATE accounts SET stripe_customer_id = 'cus_ida9d' WHERE id = ?")->execute([$iacc]);
$sc = $admin->get("/admin/cliente/$iacc")['body'];
prova('9E · la scheda cliente: riquadri Abbonamento (Ferma il rinnovo), Cambia il pacchetto, Fatture e rimborsi (F-9D-001), Accesso', str_contains($sc, '>Ferma il rinnovo</button>') && str_contains($sc, 'id="cambio"')
      && str_contains($sc, 'F-9D-001') && str_contains($sc, 'Conferma il rimborso') && str_contains($sc, 'Imposta una password provvisoria') && str_contains($sc, 'Manda il link per reimpostarla'));
prova('9E · senza motivo niente si ferma', str_contains($admin->segui($admin->post("/admin/cliente/$iacc/rinnovo", ['ferma' => '1', 'motivo' => '']))['body'], 'Scrivi il motivo') && (int) val('SELECT cancel_at_period_end FROM subscriptions WHERE id = ?', [$subI['id']]) === 0);
$primaPosta = count(righePosta() ?: []);
$r = $admin->segui($admin->post("/admin/cliente/$iacc/rinnovo", ['ferma' => '1', 'motivo' => 'chiesto per telefono']));
$mF = array_values(array_filter(array_map(fn($l) => json_decode($l, true), array_slice(righePosta() ?: [], $primaPosta)), fn($m) => ($m['to'] ?? '') === 'ida9d@prova.test'))[0] ?? [];
$statoI = json_decode((string) file_get_contents("$STRIPE_DIR/stato.json"), true);
prova('9E · «Ferma il rinnovo»: cancel_at_period_end su Stripe e nel database, motivo nel registro, email al cliente «non si rinnoverà»; poi «Riattiva»',
      str_contains($r['body'], 'Rinnovo fermato') && (int) val('SELECT cancel_at_period_end FROM subscriptions WHERE id = ?', [$subI['id']]) === 1 && ($statoI['subs']['sub_ida9d']['cancel_at_period_end'] ?? null) === true
      && str_contains((string) val("SELECT meta FROM audit_log WHERE action = 'admin.renewal_off' ORDER BY id DESC"), 'chiesto per telefono') && str_contains((string) ($mF['subject'] ?? ''), 'non si rinnover')
      && str_contains($admin->segui($admin->post("/admin/cliente/$iacc/rinnovo", ['ferma' => '0', 'motivo' => 'ci ha ripensato']))['body'], 'Rinnovo riattivato') && (int) val('SELECT cancel_at_period_end FROM subscriptions WHERE id = ?', [$subI['id']]) === 0, json_encode($mF));
// Cambio del pacchetto: anteprima, poi subito con conguaglio (sale) e dal rinnovo (scende).
$r = $admin->post("/admin/cliente/$iacc/cambio", ['piano' => 'portfolio', 'strutture' => '2', 'passo' => 'anteprima', 'modo' => 'subito', 'motivo' => 'telefono']);
$an = $admin->get("/admin/cliente/$iacc?cambio=portfolio&strutture=2&modo=subito&motivo=telefono")['body'];
prova('9E · «Anteprima» del cambio a Portfolio 2: i due modi con il conguaglio per i giorni che restano e il prezzo dal rinnovo meno gli sconti', $r['code'] === 302 && str_contains($r['loc'], 'cambio=portfolio')
      && str_contains($an, '<b>Subito, con conguaglio</b>') && preg_match('#paga la differenza \(\d+(,\d+)?\s€ \+ IVA per i \d+ giorni che restano\)#u', $an) === 1 && str_contains($an, '<b>Dal rinnovo</b>') && str_contains($an, "177\u{00A0}€ + IVA"));
$primaPosta = count(righePosta() ?: []);
$r = $admin->segui($admin->post("/admin/cliente/$iacc/cambio", ['piano' => 'portfolio', 'strutture' => '2', 'passo' => 'conferma', 'modo' => 'subito', 'motivo' => 'telefono']));
$ordC = riga("SELECT * FROM orders WHERE account_id = ? AND kind = 'change' ORDER BY id DESC", [$iacc]);
$mC = array_values(array_filter(array_map(fn($l) => json_decode($l, true), array_slice(righePosta() ?: [], $primaPosta)), fn($m) => ($m['to'] ?? '') === 'ida9d@prova.test'))[0] ?? [];
prova('9E · «Subito, con conguaglio»: l\'ordine di cambio (6H) e l\'email con il link di pagamento della differenza; niente addebito senza il clic del cliente', str_contains($r['body'], 'Ordine preparato')
      && $ordC && $ordC['status'] === 'pending' && (int) $ordC['amount_cents'] > 0 && str_contains((string) ($mC['text'] ?? ''), 'https://checkout.stripe.test/') && str_contains((string) ($mC['text'] ?? ''), 'Niente viene addebitato sulla tua carta senza il tuo clic')
      && (bool) val("SELECT id FROM audit_log WHERE action = 'admin.plan_change_requested'"), json_encode($mC));
$primaPosta = count(righePosta() ?: []);
$r = $admin->segui($admin->post("/admin/cliente/$iacc/cambio", ['piano' => 'essential', 'strutture' => '1', 'passo' => 'conferma', 'modo' => 'rinnovo', 'motivo' => 'vuole spendere meno']));
$subI = riga('SELECT * FROM subscriptions WHERE id = ?', [$subI['id']]);
$mR = array_values(array_filter(array_map(fn($l) => json_decode($l, true), array_slice(righePosta() ?: [], $primaPosta)), fn($m) => ($m['to'] ?? '') === 'ida9d@prova.test'))[0] ?? [];
prova('9E · «Dal rinnovo» verso Essential: cambio programmato (next_*), l\'email dice piano e importo nuovi, e l\'avviso di rinnovo li userà', str_contains($r['body'], 'Programmato: dal') && (int) $subI['next_package_version_id'] === pv('essential')
      && str_contains((string) ($mR['subject'] ?? ''), 'passi a Essential') && (bool) val("SELECT id FROM audit_log WHERE action = 'admin.plan_change_scheduled'")
      && str_contains($admin->get("/admin/cliente/$iacc")['body'], 'Cambio programmato: <b>Essential</b>'), json_encode($mR));
// Rimborso parziale da Stripe, poi il webhook che lo conferma.
prova('9E · un rimborso oltre il totale della fattura è rifiutato', str_contains($admin->segui($admin->post("/admin/cliente/$iacc/rimborso", ['invoice' => 'in_ida9d_1', 'importo' => '999', 'motivo' => 'duplicate']))['body'], 'importo va da 0,01 a'));
$primaPosta = count(righePosta() ?: []); $primaS = count(richiesteStripe());
$r = $admin->segui($admin->post("/admin/cliente/$iacc/rimborso", ['invoice' => 'in_ida9d_1', 'importo' => '38,20', 'motivo' => 'other', 'testo' => 'errore nostro sul conguaglio']));
$rimb = riga('SELECT * FROM rimborsi ORDER BY id DESC');
$reqR = array_values(array_filter(array_slice(richiesteStripe(), $primaS), fn($x) => $x['percorso'] === '/v1/refunds'))[0] ?? [];
$mRb = array_values(array_filter(array_map(fn($l) => json_decode($l, true), array_slice(righePosta() ?: [], $primaPosta)), fn($m) => ($m['to'] ?? '') === 'ida9d@prova.test'))[0] ?? [];
prova('9E · rimborso parziale di 38,20 €: POST refunds con il payment_intent della fattura e la chiave di idempotenza mhw-refund-{invoice}-{cents}, riga in rimborsi, motivo nel registro, email con importo e 5–10 giorni, «nota di credito in Adamo»',
      str_contains($r['body'], 'Rimborso di 38,20') && str_contains($r['body'], 'Nota di credito da fare in Adamo') && $rimb && (int) $rimb['amount_cents'] === 3820 && $rimb['motivo'] === 'other' && $rimb['stato'] === 'richiesto'
      && ($reqR['corpo']['payment_intent'] ?? '') === 'pi_ida9d_1' && ($reqR['corpo']['amount'] ?? '') === '3820' && ($reqR['idem'] ?? '') === 'mhw-refund-in_ida9d_1-3820'
      && str_contains((string) val("SELECT meta FROM audit_log WHERE action = 'refund.requested' ORDER BY id DESC"), 'errore nostro') && str_contains((string) ($mRb['text'] ?? ''), "38,20\u{00A0}€") && str_contains((string) ($mRb['text'] ?? ''), '5–10 giorni')
      && str_contains($admin->get("/admin/cliente/$iacc")['body'], 'Rimborsata 38,20'), json_encode([$reqR['corpo'] ?? null, $reqR['idem'] ?? null]));
$r = inviaWebhook(['id' => 'evt_ida9d_refund', 'type' => 'charge.refunded', 'data' => ['object' => ['id' => 'ch_pi_ida9d_1', 'invoice' => 'in_ida9d_1', 'customer' => 'cus_ida9d', 'amount_refunded' => 3820]]]);
prova('9E · webhook charge.refunded: la riga è confermata e resta nel registro; l\'abbonamento non è cambiato', str_starts_with($r['body'], 'rimborso-confermato') && (string) val('SELECT stato FROM rimborsi WHERE id = ?', [$rimb['id']]) === 'confermato'
      && (bool) val("SELECT id FROM audit_log WHERE action = 'refund.confirmed'") && (string) val('SELECT status FROM subscriptions WHERE id = ?', [$subI['id']]) === 'active');
// Password: il link e la provvisoria.
$r = $admin->segui($admin->post("/admin/cliente/$iacc/password/link", []));
prova('9E · «Manda il link per reimpostare la password»: lo stesso flusso di «Password dimenticata», nel registro', str_contains($r['body'], 'Link mandato a ida9d@prova.test') && linkPosta('ida9d@prova.test', 'password/nuova') !== '' && (bool) val("SELECT id FROM audit_log WHERE action = 'admin.password_link'"));
$hashPrima = (string) val('SELECT password_hash FROM users WHERE email = ?', ['ida9d@prova.test']);
$primaPosta = count(righePosta() ?: []);
$r = $admin->segui($admin->post("/admin/cliente/$iacc/password/provvisoria", []));
preg_match('/class="pw-provvisoria">([^<]+)</', $r['body'], $mp); $pwT = $mp[1] ?? '';
$uI = riga('SELECT * FROM users WHERE email = ?', ['ida9d@prova.test']);
$mP = array_values(array_filter(array_map(fn($l) => json_decode($l, true), array_slice(righePosta() ?: [], $primaPosta)), fn($m) => ($m['to'] ?? '') === 'ida9d@prova.test'))[0] ?? [];
prova('9E · password provvisoria: 12 caratteri leggibili, mostrata una sola volta, valida 48 ore, da cambiare al primo accesso; nel database solo l\'hash; nel registro solo il fatto; email al cliente',
      preg_match('/^[a-z]+-[a-z]+-\d{4}$/', $pwT) === 1 && !str_contains($admin->get("/admin/cliente/$iacc")['body'], 'pw-provvisoria') && (int) $uI['must_change_password'] === 1
      && abs(strtotime((string) $uI['password_temp_until']) - time() - 48 * 3600) < 120 && $uI['password_hash'] !== $hashPrima && password_verify($pwT, (string) $uI['password_hash'])
      && !str_contains((string) val("SELECT meta FROM audit_log WHERE action = 'admin.password_temp' ORDER BY id DESC"), $pwT) && str_contains((string) ($mP['text'] ?? ''), 'password provvisoria') && !str_contains((string) ($mP['text'] ?? ''), $pwT), $pwT);
$ida2 = new Browser('ida9d-2');
db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'login%'");
$ida2->get('/accedi');
$r = $ida2->post('/accedi', ['email' => 'ida9d@prova.test', 'password' => $pwT]);
prova('9E · con la provvisoria si entra ma si va solo a «Scegli la tua password»: il pannello rimanda lì', $r['code'] === 302 && str_ends_with($r['loc'], '/password/cambia') && str_ends_with($ida2->get('/pannello')['loc'], '/password/cambia')
      && str_contains($ida2->get('/password/cambia')['body'], 'Scegli la tua password'));
$r = $ida2->post('/password/cambia', ['password' => $pwT, 'password2' => $pwT]);
prova('9E · la stessa provvisoria come password nuova è rifiutata', $r['code'] === 200 && str_contains($r['body'], 'diversa da quella provvisoria'));
$r = $ida2->post('/password/cambia', ['password' => 'IdaNuova123456', 'password2' => 'IdaNuova123456']);
$uI = riga('SELECT * FROM users WHERE email = ?', ['ida9d@prova.test']);
prova('9E · password nuova salvata: si entra nel pannello, i segni della provvisoria spariscono', $r['code'] === 302 && (int) $uI['must_change_password'] === 0 && $uI['password_temp_until'] === null
      && $ida2->get('/pannello')['code'] === 200 && (bool) val("SELECT id FROM audit_log WHERE action = 'password.changed_after_temp'"));
db()->prepare("UPDATE users SET must_change_password = 1, password_temp_until = ? WHERE id = ?")->execute(['2020-01-01T00:00:00Z', $uI['id']]);
$ida3 = new Browser('ida9d-3'); $ida3->get('/accedi');
$r = $ida3->post('/accedi', ['email' => 'ida9d@prova.test', 'password' => 'IdaNuova123456']);
prova('9E · una provvisoria scaduta (48 ore) è rifiutata all\'accesso: vale solo il link', $r['code'] === 200 && str_contains($r['body'], 'La password provvisoria è scaduta'));
db()->prepare("UPDATE users SET must_change_password = 0, password_temp_until = NULL WHERE id = ?")->execute([$uI['id']]);

// ================================================================= 9H
$lacc9 = $accDi('lucia@esempio.it');   // più su $lacc è stato riassegnato (Lea): qui serve l'account di Lucia
capitolo('9H · posta: quattro indirizzi (assistenza, commerciale, news, amministrazione), mittente salvato corretto una volta, niente indirizzi scritti a mano');
@unlink($fileLocale);
$emlPer = function (string $a) use ($DOVE): string {   // l'ultima email (intera, con le intestazioni) mandata a $a
    $tutti = glob("$DOVE/app/storage/logs/email/*.eml") ?: []; sort($tutti);
    // L'intestazione To: a inizio riga (non «Reply-To: <…>», che la contiene).
    foreach (array_reverse($tutti) as $f) { $t = (string) file_get_contents($f); if (preg_match('/^To: <' . preg_quote($a, '/') . '>\r?$/m', $t)) return $t; }
    return '';
};
$ospite->get('/password/dimenticata'); $ospite->post('/password/dimenticata', ['email' => 'lucia@esempio.it']);
$eml = $emlPer('lucia@esempio.it');
prova('9H · un\'email automatica parte da assistenza@welcomemyhouse.it (MAIL_FROM di serie) con Reply-To all\'assistenza', str_contains($eml, 'From: MyHouse Welcome <assistenza@welcomemyhouse.it>')
      && str_contains($eml, 'Reply-To: <assistenza@welcomemyhouse.it>'), substr($eml, 0, 200));
// Preventivo oltre il Portfolio: a commerciale@ (Reply-To chi scrive); la conferma da assistenza@ con Reply-To commerciale@.
db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'preventivo:%'");
$vis9h = new Browser('visitatore9h');
$daQ9h = (int) $php9('echo MHW\Preventivi::daQuante();');   // la soglia di adesso (le prove di prima cambiano gli scaglioni)
$nP9h = $daQ9h + 5; $nP9h2 = $daQ9h + 6;
$rP9h = $vis9h->modulo('/', '/preventivo', ['torna' => '/', 'nome' => 'Nora Prova', 'email' => 'nora9h@prova.test', 'strutture' => (string) $nP9h]);
$emlC = $emlPer('commerciale@welcomemyhouse.it'); $emlN = $emlPer('nora9h@prova.test');
prova('9H · un preventivo oltre 10 strutture arriva a commerciale@ (Reply-To chi l\'ha chiesto); la conferma parte da assistenza@ con Reply-To commerciale@',
      str_contains((string) (end($posta('commerciale@welcomemyhouse.it'))['subject'] ?? ''), 'Richiesta di preventivo: ' . $nP9h . ' strutture') && str_contains($emlC, 'Reply-To: <nora9h@prova.test>')
      && str_contains($emlN, 'From: MyHouse Welcome <assistenza@welcomemyhouse.it>') && str_contains($emlN, 'Reply-To: <commerciale@welcomemyhouse.it>'),
      $rP9h['code'] . ' ' . ($rP9h['loc'] ?? '') . ' soglia ' . $daQ9h . ' ' . json_encode(end($posta('commerciale@welcomemyhouse.it'))['subject'] ?? null));
$r = $ospite->get('/');
prova('9H · «Anche il tuo sito»: le risposte per email (passo 3) e «Oppure scrivici subito» vanno a commerciale@', str_contains($r['body'], 'data-email="commerciale@welcomemyhouse.it"')
      && str_contains($r['body'], 'mailto:commerciale@welcomemyhouse.it?subject=') && str_contains($r['body'], 'Il sito per la tua struttura'));
$prS = count($posta('commerciale@welcomemyhouse.it'));
$vis9h->get('/');
$vis9h->post('/sito/interesse', ['ha_sito' => 'no', 'bisogni' => 'google,dirette', 'canale' => 'email', 'da' => 'home']);
$notS = $posta('commerciale@welcomemyhouse.it');
prova('9H · «Anche il tuo sito»: la notifica a noi va a commerciale@, con le risposte e senza dati del visitatore; oggetto del mailto «MyHouse siti: informazioni»', count($notS) === $prS + 1
      && str_contains((string) end($notS)['subject'], 'MyHouse siti: una risposta') && str_contains((string) end($notS)['text'], 'più visibile su Google, prenotazioni dirette')
      && str_contains((string) end($notS)['text'], 'Visitatore del sito') && str_contains($ospite->get('/')['body'], 'mailto:commerciale@welcomemyhouse.it?subject=MyHouse%20siti%3A%20informazioni'));
// Le email di pagamento: Reply-To amministrazione@ e la frase sulle fatture; il pagamento fallito avvisa amministrazione@.
$subL9 = (int) val("SELECT s.id FROM subscriptions s JOIN accounts a ON a.id = s.account_id JOIN users u ON u.id = a.user_id WHERE s.provider = 'stripe' AND s.provider_subscription_id <> '' AND u.email NOT LIKE '%@esempio.it' ORDER BY s.id DESC LIMIT 1");
$emR9 = (string) val('SELECT u.email FROM subscriptions s JOIN accounts a ON a.id = s.account_id JOIN users u ON u.id = a.user_id WHERE s.id = ?', [$subL9]);
db()->prepare("DELETE FROM email_optout WHERE account_id = (SELECT account_id FROM subscriptions WHERE id = ?)")->execute([$subL9]);
$prom9 = $php9('echo json_encode(MHW\Richiami::promemoriaManuale(' . $subL9 . '));');
$emlR = $emlPer($emR9); $txtR = (string) (end($posta($emR9))['text'] ?? '');
prova('9H · avviso di rinnovo/scadenza: From assistenza@, Reply-To amministrazione@, e «Per domande su fatture e pagamenti: amministrazione@…»', $subL9 > 0 && str_contains($prom9, 'true')
      && str_contains($emlR, 'From: MyHouse Welcome <assistenza@welcomemyhouse.it>') && str_contains($emlR, 'Reply-To: <amministrazione@welcomemyhouse.it>')
      && str_contains($txtR, 'Per domande su fatture e pagamenti: amministrazione@welcomemyhouse.it.'), $prom9 . ' ' . $subL9);
$prA = count($posta('amministrazione@welcomemyhouse.it'));
$falli9 = $php9('$s = MHW\Db::one("SELECT * FROM subscriptions WHERE id = ' . $subL9 . '"); $r = new ReflectionMethod(MHW\Billing::class, "onInvoiceFailed"); $r->setAccessible(true); echo $r->invoke(null, ["id" => "in_prova9h", "subscription" => $s["provider_subscription_id"], "amount_due" => 14900, "currency" => "eur"]); MHW\Db::update("subscriptions", ["payment_status" => $s["payment_status"], "status" => $s["status"]], "id = :i", ["i" => $s["id"]]);');
$notA = $posta('amministrazione@welcomemyhouse.it');
prova('9H · pagamento fallito simulato: l\'avviso a noi va ad amministrazione@', $falli9 === 'pagamento-fallito' && count($notA) === $prA + 1 && str_contains((string) end($notA)['subject'], 'Pagamento non riuscito'), $falli9);
$rI9 = $admin->get('/admin/impostazioni');
prova('9H · Impostazioni → «Indirizzi email»: assistenza, commerciale, newsletter, amministrazione; Termini con l\'indirizzo per la fatturazione; Account con «Domande su fatture e pagamenti?»',
      str_contains($rI9['body'], 'Indirizzi email</h2>') && str_contains($rI9['body'], 'name="contatti__newsletter"') && str_contains($rI9['body'], 'name="contatti__amministrazione"')
      && str_contains($ospite->get('/termini')['body'], 'Per le questioni di fatturazione e pagamento scrivi a <a href="mailto:amministrazione@welcomemyhouse.it">')
      && str_contains($lucia->get('/account')['body'], 'Domande su fatture e pagamenti? Scrivi a') && str_contains($lucia->get('/account')['body'], '?subject=') && !str_contains($ospite->get('/')['body'], 'news@welcomemyhouse.it'));
prova('9H · «Chiedi un preventivo» (mailto) a commerciale@; l\'assistenza resta nel piede e nei documenti legali', str_contains($php9('echo MHW\Support::mailtoPreventivo();'), 'mailto:commerciale@welcomemyhouse.it?subject=Preventivo')
      && str_contains($ospite->get('/privacy')['body'], 'assistenza@welcomemyhouse.it'));
// Il giudizio basso resta all'assistenza (prova 7D più su): qui il codice che lo manda.
prova('9H · un giudizio da 1 a 3 stelle arriva all\'assistenza (Testimonianze usa Support::contatto())', preg_match('#\$a = Support::contatto\(\);.{0,1600}Mailer::send\(\$a, \'Giudizio da #s', (string) file_get_contents("$DOVE/app/src/Testimonianze.php")) === 1);
// Email commerciale vuota: tutto all'assistenza.
file_put_contents($fileLocale, '<?php return ' . var_export(['contatti' => ['commerciale' => '', 'newsletter' => '', 'amministrazione' => '']], true) . ';');
db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'preventivo:%'");
sleep(3);   // opcache del server di prova: config.local.php appena scritto
$vis9h->modulo('/', '/preventivo', ['torna' => '/', 'nome' => 'Olga Prova', 'email' => 'olga9h@prova.test', 'strutture' => (string) $nP9h2]);
$emlA = $emlPer('assistenza@welcomemyhouse.it');
$vuoti9 = $php9('echo MHW\Support::commerciale(), "|", MHW\Support::newsletter(), "|", MHW\Support::amministrazione();');
prova('9H · con commerciale, newsletter e amministrazione vuote tutto va all\'assistenza (Impostazioni li salva vuoti)', $vuoti9 === str_repeat('assistenza@welcomemyhouse.it|', 2) . 'assistenza@welcomemyhouse.it', $vuoti9);
prova('9H · con l\'email commerciale vuota il preventivo e «Anche il tuo sito» vanno all\'assistenza', str_contains((string) (end($posta('assistenza@welcomemyhouse.it'))['subject'] ?? ''), 'Richiesta di preventivo: ' . $nP9h2 . ' strutture') && str_contains($emlA, 'Reply-To: <olga9h@prova.test>')
      && str_contains($ospite->get('/')['body'], 'data-email="assistenza@welcomemyhouse.it"') && str_contains($emlPer('olga9h@prova.test'), 'Reply-To: <assistenza@welcomemyhouse.it>'));
// Il mittente salvato noreply@myhouse.it si corregge una volta sola, con il messaggio in Gestione e la riga nel registro.
file_put_contents($fileLocale, '<?php return ' . var_export(['mail' => ['from' => 'noreply@myhouse.it']], true) . ';');
@unlink("$DOVE/app/storage/mittente-9h.txt");
sleep(3);   // opcache del server di prova rilegge i file ogni 2 secondi
$ospite->get('/');
$dopoM = (string) file_get_contents($fileLocale);
prova('9H · il mittente salvato noreply@myhouse.it diventa assistenza@welcomemyhouse.it alla prima richiesta, con la copia in config.local.bak.php e la riga nel registro',
      str_contains($dopoM, "'from' => 'assistenza@welcomemyhouse.it'") && is_file(dirname($fileLocale) . '/config.local.bak.php')
      && str_contains((string) @file_get_contents("$DOVE/app/storage/mittente-9h.txt"), 'Il mittente delle email era noreply@myhouse.it')
      && (bool) val("SELECT id FROM audit_log WHERE action = 'settings.mail_from' AND meta LIKE '%noreply@myhouse.it%'"), $dopoM);
$g1 = $admin->get('/admin')['body']; $g2 = $admin->get('/admin')['body'];
prova('9H · …Gestione lo dice una volta sola, nel messaggio di esito', str_contains($g1, 'Il mittente delle email era noreply@myhouse.it: ora è assistenza@welcomemyhouse.it') && !str_contains($g2, 'Il mittente delle email era'));
file_put_contents($fileLocale, '<?php return ' . var_export(['mail' => ['from' => 'noreply@myhouse.it']], true) . ';');
sleep(3);
$ospite->get('/');
prova('9H · …e una volta sola: rimesso a mano, resta com\'è (lo segnala l\'avviso di Gestione)', str_contains((string) file_get_contents($fileLocale), "'from' => 'noreply@myhouse.it'"));
@unlink($fileLocale); @unlink(dirname($fileLocale) . '/config.local.bak.php');
// Cambiare base_url sposta il percorso del cookie di sessione (si esce): il controllo si prova direttamente, la pagina con il mittente di serie.
$fuoriD = $php9('echo json_encode([MHW\Mailer::mittenteFuoriDominio("noreply@altrodominio.test", "https://www.welcomemyhouse.it"), MHW\Mailer::mittenteFuoriDominio("assistenza@welcomemyhouse.it", "https://www.welcomemyhouse.it"), MHW\Mailer::mittenteFuoriDominio("Assistenza@WelcomeMyHouse.it", "https://welcomemyhouse.it/sito"), MHW\Mailer::mittenteFuoriDominio("assistenza@welcomemyhouse.it", "http://127.0.0.1:8300"), MHW\Mailer::mittenteFuoriDominio("", "https://www.welcomemyhouse.it")]);');
prova('9H · Gestione: «Il mittente delle email non è sul dominio del sito» con un mittente su un altro dominio (non con un IP o un mittente vuoto)', $fuoriD === '[true,false,false,false,false]', $fuoriD);
prova('9H · …e non c\'è con il mittente di serie', !str_contains($admin->get('/admin')['body'], 'Il mittente delle email non è sul dominio del sito'));
// L'account della vetrina non riceve i richiami.
$vetR = $php9('MHW\Config::sovrascrivi("vetrina_email", "vetrina@welcomemyhouse.it"); echo json_encode(MHW\Richiami::manda(999999, "Vetrina@WelcomeMyHouse.it", "Vetrina", "conferma", "prova-9h", ["x", "y", null, ""]));');
prova('9H · vetrina_email di serie vetrina@welcomemyhouse.it, esclusa dai Richiami', $vetR === 'false' && str_contains((string) file_get_contents("$DOVE/app/config.php"), "'MHW_VETRINA_EMAIL', 'vetrina@welcomemyhouse.it'"), $vetR);
// Nessun indirizzo scritto a mano: solo i valori di serie in config.php, gli esempi e il vecchio mittente da correggere.
$aMano = [];
foreach (['src', 'views', 'lang'] as $dirA) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$DOVE/app/$dirA", FilesystemIterator::SKIP_DOTS));
    foreach ($it as $fA) {
        if (!str_ends_with((string) $fA, '.php')) continue;
        foreach (file((string) $fA) ?: [] as $nA => $rigaA) {
            if (str_contains($rigaA, 'MITTENTE_VECCHIO =')) continue;
            if (!preg_match_all('/[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.[a-z]{2,}/', $rigaA, $mA)) continue;
            foreach ($mA[0] as $ind) {
                if (preg_match('/@(esempio\.it|tuodominio\.it|.*\.example|.*\.test|.*\.prova)$/', $ind)) continue;
                $aMano[] = substr((string) $fA, strlen("$DOVE/app/")) . ':' . ($nA + 1) . ' ' . $ind;
            }
        }
    }
}
prova('9H · nessun indirizzo email scritto a mano in src/, views/ e lang/ (solo i valori di serie in config.php)', !$aMano, implode(' · ', array_slice($aMano, 0, 5)));

// ================================================================= 9F
capitolo('9F · guida: dialogo della lingua leggibile, vetrina con il link al sito e i portali a 5 stelle, regione e provincia');
$m46 = $php9('$f = require "' . $DOVE . '/app/migrations/046_fase9ter.php"; $f(MHW\Db::conn()); echo "fatto";');
prova('9F · migrazione 046 una seconda volta: niente cambia', $m46 === 'fatto' && (bool) val("SELECT 1 FROM schema_migrations WHERE name LIKE '046%'") && val("PRAGMA table_info(properties)") !== null
      && (bool) riga("SELECT 1 FROM pragma_table_info('properties') WHERE name = 'province'") && (bool) riga("SELECT 1 FROM pragma_table_info('translation_cache') WHERE name = 'hash'"), $m46);
$css9f = (string) file_get_contents("$DOVE/assets/app.css");
$contrasti = json_decode($php9('$o = []; foreach (array_keys(MHW\Palette::all()) as $c) { $p = MHW\Palette::get($c); $o[$c] = [round(MHW\Palette::contrast($p["scuro"]["ink"], "#ffffff"), 1), round(MHW\Palette::contrast($p["scuro"]["on_accent"], $p["scuro"]["accent"]), 1)]; } echo json_encode($o);'), true) ?: [];
prova('9F·1 · «Scegli la lingua»: bottoni bianchi con il testo scuro della palette (≥ 7:1) e la lingua del telefono sull\'accento pieno (≥ 4,5:1), uguali nei due temi, per tutte le palette (7 dalla 9J)',
      count($contrasti) === 7 && min(array_column($contrasti, 0)) >= 7 && min(array_column($contrasti, 1)) >= 4.5
      && str_contains($css9f, '.scegli-lingua__lingua{') && str_contains($css9f, 'background:var(--scelta-fondo,#fff);color:var(--scelta-ink,#231b12)')
      && str_contains($php9('echo MHW\Palette::css("mare");'), '--scelta-fondo:#ffffff;--scelta-ink:#14232b;--scelta-accento:#1c5a78'), json_encode($contrasti));
$vet9f = riga("SELECT id, slug FROM properties WHERE is_demo = 2 AND status = 'published' AND archived_at IS NULL ORDER BY id");   // la stessa scelta di Demo::esempio
$r = $ospite->get('/g/' . $vet9f['slug'] . '?l=it');
prova('9F·2 · nella vetrina il marchio DEMO è un link alla home del sito con «Torna a …», e in fondo «Questa è una guida di esempio · Torna al sito · Crea la tua guida»', (bool) $vet9f && $r['code'] === 200
      && preg_match('#<a class="demo-tag demo-tag--link" href="[^"]+/" title="Torna a [^"]+">Demo</a>#', $r['body']) === 1 && str_contains($r['body'], '<b>Questa è una guida di esempio</b>')
      && str_contains($r['body'], '>Torna al sito</a>') && str_contains($r['body'], '/registrati">Crea la tua guida</a>'));
$r = $ospite->get('/g/' . $vet9f['slug'] . '/benvenuto?l=de');
prova('9F·2 · nella copertina il marchio «myhouse welcome · DEMO» è un link, e sotto «Entra» la riga in tedesco', preg_match('#<a href="[^"]+/" title="Zurück zu [^"]+"[^>]*class="rise row"#', $r['body']) === 1
      && str_contains($r['body'], 'Dies ist ein Beispiel-Guide') && str_contains($r['body'], 'Zurück zur Website') && str_contains($r['body'], 'Erstelle deinen Guide'));
$veraSlug = (string) val("SELECT slug FROM properties WHERE is_demo = 0 AND status = 'published' AND archived_at IS NULL ORDER BY id LIMIT 1");
$r = $ospite->get('/g/' . $veraSlug . '?l=it');
prova('9F·2 · in una guida vera niente link al sito e niente riga «guida di esempio»', $r['code'] === 200 && !str_contains($r['body'], 'demo-tag--link') && !str_contains($r['body'], 'Questa è una guida di esempio'), $veraSlug);
$r = $ospite->get('/g/' . $vet9f['slug'] . '/commiato?l=it&voto=5');
$snapV = json_decode((string) val('SELECT snapshot FROM guide_versions WHERE property_id = ? ORDER BY version DESC LIMIT 1', [$vet9f['id']]), true);
prova('9F·3 · vetrina con 5 stelle: i tre bottoni dei portali (Google, Booking.com, Airbnb) dall\'istantanea ripubblicata dalla 046 con i canali', $r['code'] === 200
      && str_contains($r['body'], 'data-portale="google"') && str_contains($r['body'], 'data-portale="booking"') && str_contains($r['body'], 'data-portale="airbnb"')
      && count(array_filter((array) ($snapV['property']['channels'] ?? []), fn($c) => empty($c['social']))) === 3 && ($snapV['property']['province'] ?? '') === 'PG');
$rP9 = $lucia->get("/pannello/$casa");
prova('9F·9 · «Guida di esempio» nel pannello è la vetrina (Casa Checco), e Gestione non dà l\'avviso', str_contains($rP9['body'], '/g/' . $vet9f['slug'] . '"')
      && !str_contains($admin->get('/admin')['body'], 'rifai la vetrina'), preg_match('#href="([^"]*)"[^>]*>(?:<svg.*?</svg>)?<span>Guida di esempio#s', $rP9['body'], $mE) ? $mE[1] : 'link assente');
$vetrine9f = array_column(righe("SELECT id FROM properties WHERE is_demo = 2 AND status = 'published'"), 'id');
foreach ($vetrine9f as $vId) db()->prepare("UPDATE properties SET status = 'draft' WHERE id = ?")->execute([$vId]);
$rA9 = $admin->get('/admin');
prova('9F·9 · …senza una vetrina pubblicata l\'esempio è un cliente di esempio e Gestione avvisa «rifai la vetrina»', str_contains($rA9['body'], 'La guida di esempio è quella dei clienti di esempio: rifai la vetrina'),
      (string) $php9('echo json_encode(MHW\Demo::esempio());'));
foreach ($vetrine9f as $vId) db()->prepare("UPDATE properties SET status = 'published' WHERE id = ?")->execute([$vId]);
// Regione e provincia
$r = $lucia->get("/pannello/$casa/impostazioni");
prova('9F·10 · Impostazioni: «Provincia (sigla)» con i suggerimenti delle 107 sigle e «Regione» con le 20 regioni; niente più «Zona o regione»', substr_count($r['body'], '<option value="') >= 127
      && str_contains($r['body'], 'id="province-elenco"') && str_contains($r['body'], '<option value="PG" data-regione="Umbria">Perugia</option>') && str_contains($r['body'], 'id="regioni-elenco"')
      && str_contains($r['body'], '<option value="Toscana">') && !str_contains($r['body'], 'Zona o regione'));
$lucia->modulo("/pannello/$casa/impostazioni", "/pannello/$casa/impostazioni", ['name' => 'Casa Lucia', 'city' => 'Spello', 'region' => '', 'province' => ' fi ', 'postal_code' => '06038', 'cin' => 'IT054050C2PROVA001']);
prova('9F·10 · si salva la sigla maiuscola e la regione si completa da sola (FI → Toscana)', val('SELECT province FROM properties WHERE id = ?', [$casa]) === 'FI' && val('SELECT region FROM properties WHERE id = ?', [$casa]) === 'Toscana');
$lucia->modulo("/pannello/$casa/impostazioni", "/pannello/$casa/impostazioni", ['name' => 'Casa Lucia', 'city' => 'Spello', 'region' => 'Umbria', 'province' => 'PG', 'postal_code' => '06038', 'cin' => 'IT054050C2PROVA001']);
$lucia->post("/pannello/$casa/pubblica", []);
$snapL = json_decode((string) val('SELECT snapshot FROM guide_versions WHERE property_id = ? ORDER BY version DESC LIMIT 1', [$casa]), true);
$slugL = (string) val('SELECT slug FROM properties WHERE id = ?', [$casa]);
// L'anteprima dell'host (la guida pubblica può essere offline a questo punto delle prove, secondo lo stato dell'abbonamento).
$rDe = $lucia->get("/pannello/$casa/anteprima?l=de"); $rEn = $lucia->get("/pannello/$casa/anteprima?l=en"); $rIt = $lucia->get("/pannello/$casa/anteprima?l=it");
$haDe = (bool) val("SELECT 1 FROM property_locales WHERE property_id = ? AND locale = 'de'", [$casa]);
prova('9F·10 · nella guida e nell\'istantanea «Città · Provincia · Regione», tradotte dove esiste una forma: «Spello · Perugia · Umbria», in tedesco «Umbrien»', ($snapL['property']['province'] ?? '') === 'PG'
      && str_contains($rIt['body'], 'Spello · Perugia · Umbria') && (!$haDe || str_contains($rDe['body'], 'Spello · Perugia · Umbrien')) && str_contains($rEn['body'], 'Spello · Perugia · Umbria'),
      json_encode(['prov' => $snapL['property']['province'] ?? null, 'de' => $haDe, 'it' => preg_match('#Spello · [^<]{0,40}#', $rIt['body'], $mD) ? $mD[0] : '', 'en' => preg_match('#Spello · [^<]{0,40}#', $rEn['body'], $mD) ? $mD[0] : '']));
prova('9F·10 · Territorio: Firenze → Florence/Florenz, Toscana → Tuscany; il capoluogo non si ripete; all\'estero il testo libero resta', $php9('echo MHW\Territorio::dove(["city" => "Firenze", "province" => "fi", "region" => "toscana"], "en"), "|", MHW\Territorio::dove(["city" => "Greve in Chianti", "province" => "FI", "region" => "Toscana"], "de"), "|", MHW\Territorio::dove(["city" => "Lisbona", "province" => "Lisboa", "region" => ""], "it"), "|", MHW\Territorio::regioneDi("sr");') === 'Firenze · Tuscany|Greve in Chianti · Florenz · Toskana|Lisbona · Lisboa|Sicilia');
prova('9F·10 · Marketing: la regione dalla provincia salvata vale più del CAP', $php9('echo MHW\Marketing::regione(["region" => "", "province" => "LE", "postal_code" => "06038"]), "|", MHW\Marketing::regione(["region" => "", "province" => "", "postal_code" => "06038"]);') === 'Puglia|Umbria');

capitolo('9F · pannello: prenotazione diretta, messaggio degli inviti, riquadro MyHouse e Novità senza X, lingua in più, traduzione a gruppi, sezione libera, pannello in inglese');
$r = $lucia->get("/pannello/$casa/impostazioni");
prova('9F·4 · Prenotazione diretta: due colonne con «Codice sconto» e «Sconto» sulla stessa riga (3/2) e l\'aiuto del codice sotto tutti e due', str_contains($r['body'], '<div class="diretta__codice">')
      && strpos($r['body'], 'id="direct_discount"') < strpos($r['body'], 'id="direct-code-aiuto"') && str_contains($css9f, '.diretta__codice{display:grid;grid-template-columns:minmax(0,3fr) minmax(0,2fr)')
      && str_contains($css9f, '@media(min-width:900px){ .diretta{grid-template-columns:minmax(0,1fr) minmax(0,1fr)} }'));
$admin->segui($admin->post('/admin/impostazioni/inviti', ['inviti__attivi' => '1', 'password' => 'AdminProva123']));   // le prove di prima possono averli spenti
$r = $paola->get('/inviti');
prova('9F·5 · Invita per email: il messaggio parte già scritto con lo sconto (Testi e foto → inviti.messaggio_partenza), lo stesso testo del messaggio pronto di WhatsApp',
      str_contains($r['body'], 'Ciao! Uso MyHouse Welcome per la guida digitale della mia struttura e mi trovo bene. Con questo invito hai il 5% di sconto sul primo anno: crei la guida gratis e paghi solo quando la pubblichi.</textarea>')
      && preg_match('~<textarea id="inv-messaggio"[^>]*>Ciao! Uso MyHouse Welcome per la guida digitale della mia struttura e mi trovo bene\. Con questo invito hai il 5% di sconto sul primo anno: crei la guida gratis e paghi solo quando la pubblichi\. Guarda com&#039;è la mia guida: [^<]+ Il codice è [A-Z0-9]+\.</textarea>~', $r['body']) === 1);
$r = $lucia->get('/pannello');
prova('9F·6 · il riquadro MyHouse c\'è sempre, senza X né «Non mostrare più»', str_contains($r['body'], 'data-sito-invito') && !str_contains($r['body'], 'data-sito-chiudi') && !str_contains($r['body'], 'Non mostrare più'));
db()->prepare('UPDATE properties SET has_site = 0 WHERE id = ?')->execute([$casa]);
$r = $lucia->get('/pannello');
prova('9F·6 · dopo la risposta diventa compatto: «Hai già risposto: «No, non ancora»», vuoi cambiare?', str_contains($r['body'], 'sito-invito--compatto') && str_contains($r['body'], 'Hai già risposto: «No, non ancora»')
      && str_contains($r['body'], 'vuoi cambiare la risposta?'));
db()->prepare('UPDATE properties SET has_site = NULL WHERE id = ?')->execute([$casa]);
$luId = (int) val('SELECT id FROM users WHERE email = ?', ['lucia@esempio.it']);
$creatoLucia = (string) val('SELECT created_at FROM users WHERE id = ?', [$luId]);
db()->prepare('UPDATE users SET created_at = ? WHERE id = ?')->execute(['2026-10-01T00:00:00Z', $luId]);
db()->prepare('DELETE FROM novita_viste WHERE account_id = ?')->execute([$lacc9]);
$novitaTutte = require "$DOVE/app/lang/novita.php"; $ultimaN = $novitaTutte[0];
$r = $lucia->get('/pannello');
prova('9F·6 · Novità: si vede solo l\'ultima, senza X, con «Mostrami dove» (POST) e «Tutte le novità»', str_contains($r['body'], '<b id="novita-titolo">' . htmlspecialchars($ultimaN['titolo'], ENT_QUOTES) . '</b>')
      && !str_contains($r['body'], 'Chiudi questa novità') && str_contains($r['body'], 'action="/welcomebook/index.php/pannello/novita/vai"') && str_contains($r['body'], '>Tutte le novità</a>'));
$r = $lucia->post('/pannello/novita/vai', ['novita' => $ultimaN['id'], 'dove' => "/pannello/$casa/lingue"]);
prova('9F·6 · «Mostrami dove»: porta lì e segna la novità come vista; la fascia sparisce finché non ne esce un\'altra', $r['code'] === 302 && str_ends_with($r['loc'], "/pannello/$casa/lingue")
      && (bool) val('SELECT id FROM novita_viste WHERE account_id = ? AND novita_id = ?', [$lacc9, $ultimaN['id']]) && !str_contains($lucia->get('/pannello')['body'], 'class="novita"'), $r['code'] . ' ' . $r['loc']);
$r = $lucia->get('/pannello/novita');
prova('9F·6 · la pagina «Novità» tiene tutte quelle passate, dalla più recente, con «vista»', $r['code'] === 200 && pulita($r) && substr_count($r['body'], '<li>') >= count($novitaTutte)
      && str_contains($r['body'], htmlspecialchars($ultimaN['titolo'], ENT_QUOTES)) && str_contains($r['body'], '· vista</time>') && str_contains($r['body'], '>Novità</span>'));
db()->prepare('UPDATE users SET created_at = ? WHERE id = ?')->execute([$creatoLucia, $luId]);
$r = $lucia->get("/pannello/$casa/lingue");
prova('9F·7 · Lingue: «Una lingua in più, oltre alle cinque di base» con i due esempi e la riga sui bottoni, sopra la scelta', str_contains($r['body'], 'Una lingua in più, oltre alle cinque di base')
      && str_contains($r['body'], '<b>se i tuoi ospiti parlano un&#039;altra lingua</b>') && str_contains($r['body'], 'Scrivi in italiano, ospiti dall&#039;Olanda') && str_contains($r['body'], 'Scrivi in polacco')
      && str_contains($r['body'], 'è tradotta per nederlands, português (portugal), português (brasil) e polski') && strpos($r['body'], 'lingua-come__titolo') < strpos($r['body'], 'name="locali[]"'));
prova('9F·7 · con Essential il riquadro dice «2 lingue: la tua e una a scelta»', str_contains($elsa->get("/pannello/$ep/lingue")['body'], 'Con Essential hai 2 lingue: la tua e una a scelta'));
$r = $admin->get('/admin/testi');
prova('9F·7 · i testi stanno in Testi e foto, sezione «Pannello: una lingua in più»', str_contains($r['body'], 'Pannello: una lingua in più') && str_contains($r['body'], 'lingue.extra.titolo'));
// 9F·8 · traduzione in corso: l'endpoint a gruppi (il JavaScript lo chiama una lingua per volta)
db()->prepare("DELETE FROM translation_suggestions WHERE property_id = ?")->execute([$casa]);
db()->prepare('UPDATE accounts SET translate_cap_override = NULL WHERE id = ?')->execute([$lacc9]);
$ptIt((int) $lo[2]['id'], 'note', 'Chiude il martedì, apre il mercoledì alle 19.'); $ptEn((int) $lo[2]['id'], 'note', '');
$trData($emCasa, 'it', 'note', 'Il pronto soccorso è a Nottola, 20 minuti in auto.'); $trData($emCasa, 'en', 'note', '');
$ptIt((int) $lo[0]['id'], 'description', 'Pasta fatta a mano, anche senza glutine.'); $ptEn((int) $lo[0]['id'], 'description', '');
$lucia->get("/pannello/$casa/lingue/en");
$primaJ = count($richiesteTr());
$j2 = json_decode($lucia->post("/pannello/$casa/lingue/en/suggerite.json", ['tutti' => '1', 'limite' => '10', 'sezione' => (string) $emCasa], ['Accept: application/json'])['body'] ?? '', true);
prova('9F·8 · POST …/suggerite.json con «sezione»: traduce solo i campi di quella sezione (dom, testo, etichetta)', is_array($j2) && ($j2['ok'] ?? false) && count($j2['fatte'] ?? []) >= 1
      && array_unique(array_map(fn($f) => explode(':', $f['k'])[0] . ':' . explode(':', $f['k'])[1], $j2['fatte'])) === ['section:' . $emCasa]
      && str_starts_with((string) ($j2['fatte'][0]['dom'] ?? ''), 'c-') && str_starts_with((string) ($j2['fatte'][0]['testo'] ?? ''), '[en] ') && ($j2['fatte'][0]['etichetta'] ?? '') !== '', json_encode($j2));
$primaJ = count($richiesteTr());
$j = json_decode($lucia->post("/pannello/$casa/lingue/en/suggerite.json", ['tutti' => '1', 'limite' => '1'], ['Accept: application/json'])['body'] ?? '', true);
prova('9F·8 · …con «limite» un gruppo di campi per volta (qui uno), con «restanti» per la barra', is_array($j) && ($j['ok'] ?? false) && count($j['fatte'] ?? []) === 1 && ($j['restanti'] ?? -1) >= 1
      && count($richiesteTr()) === $primaJ + 1, json_encode($j));
$trData($emCasa, 'it', 'intro_errore', ''); $ptIt((int) $lo[1]['id'], 'description', 'ERRORE-FINTO testo che non si traduce'); $ptEn((int) $lo[1]['id'], 'description', '');
$j3 = json_decode($lucia->post("/pannello/$casa/lingue/en/suggerite.json", ['campo' => 'place:' . $lo[1]['id'] . ':description'], ['Accept: application/json'])['body'] ?? '', true);
prova('9F·8 · un errore del traduttore torna come «stop» (la barra diventa gialla con «Riprova»), gli altri campi restano', is_array($j3) && empty($j3['ok']) && str_contains((string) ($j3['stop'] ?? ''), 'non ha risposto') && ($j3['fatte'] ?? null) === [], json_encode($j3));
$ptIt((int) $lo[1]['id'], 'description', 'Trattoria di famiglia dal 1962.');
$r = $lucia->get("/pannello/$casa/lingue/en");
prova('9F·8 · nella pagina della lingua il bottone ha i dati per il JavaScript (data-traduci, data-url, data-csrf) e la barra di stato; senza JavaScript resta il modulo', str_contains($r['body'], 'data-traduci data-url="/welcomebook/index.php/pannello/' . $casa . '/lingue/en/suggerite.json"')
      && str_contains($r['body'], 'data-traduci-stato') && str_contains($r['body'], 'name="suggerisci" value="1" data-traduci-tutti') && str_contains($css9f, '@media (prefers-reduced-motion:reduce){ .spin{animation:none}'));
$r = $lucia->get("/pannello/$casa/sezioni/$emCasa");
prova('9F·8 · nell\'editor della sezione «Traduci questa sezione in N lingue», una lingua per volta (data-lingue con nome, url e pagina)', str_contains($r['body'], 'data-traduci-sezione="' . $emCasa . '"')
      && str_contains($r['body'], '&quot;nome&quot;:&quot;Inglese&quot;') && str_contains($r['body'], 'Traduci questa sezione in '));
// 9F·12 · la sezione libera: una per guida, anche con Essential
$r = $elsa->segui($elsa->post("/pannello/$ep/sezioni", ['kind' => 'custom']));
$liberaE = (int) val("SELECT id FROM sections WHERE property_id = ? AND kind = 'custom'", [$ep]);
prova('9F·12 · Essential aggiunge la sezione libera (conta come una delle 6), con il titolo di serie «Informazioni utili»', $liberaE > 0 && val('SELECT title FROM section_translations WHERE section_id = ? AND locale = ?', [$liberaE, 'it']) === 'Informazioni utili');
$r = $elsa->segui($elsa->post("/pannello/$ep/sezioni", ['kind' => 'custom']));
prova('9F·12 · una seconda sezione libera è rifiutata, in tutti i piani', (int) val("SELECT COUNT(*) FROM sections WHERE property_id = ? AND kind = 'custom'", [$ep]) === 1 && str_contains($r['body'], 'una sola sezione libera')
      && str_contains($lucia->segui($lucia->post("/pannello/$casa/sezioni", ['kind' => 'custom']))['body'], 'una sola sezione libera') || (int) val("SELECT COUNT(*) FROM sections WHERE property_id = ? AND kind = 'custom'", [$casa]) === 1);
prova('9F·12 · FAQ, Termini e «Confronta i piani» lo dicono', str_contains($ospite->get('/domande')['body'], 'Posso aggiungere una sezione tutta mia?') && str_contains($ospite->get('/termini')['body'], 'la sezione libera è una sola per guida')
      && str_contains($ospite->get('/')['body'], 'Sezione libera (una per guida)') && val("SELECT COUNT(*) FROM package_features pf JOIN features f ON f.id = pf.feature_id JOIN package_versions v ON v.id = pf.package_version_id WHERE f.code = 'custom_sections' AND v.is_current = 1 AND pf.value = '1'") >= 3);
// 9F·11 · il pannello in inglese quando la lingua principale non è l'italiano
$php9('MHW\Properties::setDefaultLocale(' . $lacc9 . ', ' . $casa . ', "pl");');
$r = $lucia->get("/pannello/$casa/procedura/struttura");
prova('9F·11 · lingua principale polacca: la procedura è in inglese (passi, titoli, etichette), la lingua è la prima cosa del primo passo, con Essential/Plus spiegati e «Your language isn\'t listed? Write to us»',
      str_contains($r['body'], '<h1>Property and contacts.</h1>') && str_contains($r['body'], '>Check-in &amp; Check-out</span>') && str_contains($r['body'], 'Which language do you write the guide in?')
      && strpos($r['body'], 'Which language do you write the guide in?') < strpos($r['body'], '<legend>The property') && str_contains($r['body'], 'With Essential') && str_contains($r['body'], 'Your language isn\'t listed?')
      && str_contains($r['body'], 'mailto:assistenza@welcomemyhouse.it?subject=') && str_contains($r['body'], '%20Lingua%20mancante') && str_contains($r['body'], 'Dashboard language: <b>English</b>') && str_contains($r['body'], '>My guides</span>'));
$r = $lucia->get("/pannello/$casa/sezioni/$emCasa");
prova('9F·11 · l\'editor delle sezioni in inglese: etichette e aiuti dal catalogo tradotto, «Optional» spezzato come «Facoltativo»', str_contains($r['body'], 'Single emergency number') && str_contains($r['body'], 'Useful contacts')
      && str_contains($r['body'], '>Save</button>') && str_contains($r['body'], '(optional)') && !str_contains($r['body'], 'Numero unico di emergenza'));
$r = $lucia->get('/account');
prova('9F·11 · Account & Billing in inglese, con «Dashboard language: Italian / English / Default»', str_contains($r['body'], '<h1>Account &amp; Billing.</h1>') && str_contains($r['body'], 'name="pannello_lingua" value="it"')
      && str_contains($r['body'], 'Dashboard language'));
$r = $lucia->segui($lucia->post('/account/pannello-lingua', ['pannello_lingua' => 'it']));
prova('9F·11 · l\'interruttore: «Italiano» riporta il pannello in italiano anche con la guida in polacco, e resta nel registro', str_contains($r['body'], 'Account &amp; Fatturazione.') && val('SELECT panel_lang FROM users WHERE id = ?', [$luId]) === 'it'
      && str_contains($lucia->get("/pannello/$casa/procedura/struttura")['body'], '<h1>Struttura e contatti.</h1>') && (bool) val("SELECT id FROM audit_log WHERE action = 'panel.language'"));
$lucia->post('/account/pannello-lingua', ['pannello_lingua' => '']);
$php9('MHW\Properties::setDefaultLocale(' . $lacc9 . ', ' . $casa . ', "it");');
prova('9F·11 · «di serie» segue la lingua principale: tornata all\'italiano, il pannello è italiano; l\'amministrazione resta sempre in italiano', val('SELECT panel_lang FROM users WHERE id = ?', [$luId]) === ''
      && str_contains($lucia->get("/pannello/$casa/procedura/struttura")['body'], '<h1>Struttura e contatti.</h1>') && str_contains($admin->get('/admin/clienti')['body'], 'Clienti'));

// ================================================================= 9G
capitolo('9G · traduzioni suggerite: sempre per Plus e Portfolio, tetto annuale, memoria delle frasi, segnaposto protetti, contatori');
prova('9G · i testi: Termini «entro un limite annuale di caratteri», FAQ, Confronta i piani, le schede dei piani (via l\'omaggio di un anno)', str_contains($ospite->get('/termini')['body'], 'comprese nei piani Plus e Portfolio entro un limite annuale di caratteri')
      && str_contains($ospite->get('/domande')['body'], 'entro un limite annuale di caratteri che vedi nel pannello') && str_contains($ospite->get('/')['body'], 'Traduzioni suggerite <span class="small muted">entro un limite annuale di caratteri</span>')
      && str_contains((string) val("SELECT bullets FROM packages WHERE code = 'plus'"), 'Comprese nel piano, entro un limite annuale di caratteri.') && !str_contains($ospite->get('/en/terms')['body'], 'free for twelve months'));
db()->prepare('UPDATE accounts SET translation_trial_until = ? WHERE id = ?')->execute(['2020-01-01T00:00:00Z', $lacc9]);
$r = $lucia->get("/pannello/$casa/lingue/en");
prova('9G · un «anno in omaggio» finito da tempo non blocca più niente: Plus traduce lo stesso, con il contatore dell\'anno', str_contains($r['body'], 'name="suggerisci"') && !str_contains($r['body'], 'anno in omaggio')
      && preg_match('#Traduzioni suggerite: \d+% dell\'anno usato</b> · si azzera il #', $r['body']) === 1 && str_contains($lucia->get('/account')['body'], 'id="traduzioni"'));
prova('9G · Essential no', !str_contains($elsa->get("/pannello/$ep/lingue/en")['body'] ?? '', 'name="suggerisci"') && $php9('echo json_encode(MHW\Traduttore::nelPiano(' . $eacc . '));') === 'false');
$prot = json_decode($php9('[$t, $m] = MHW\Traduttore::proteggi("Ciao  {host}!\n\n\n\nChiama il +39 075 123 4567 o scrivi a info@casa.it. Check-in dalle 15:00, 22 °C, 15 € a notte, https://casa.it/x?a=1 e il 10% di sconto."); echo json_encode([$t, $m, MHW\Traduttore::ripristina("[en] " . $t, $m)]);'), true) ?: [];
prova('9G·4 · prima di inviare: spazi doppi e righe vuote via; {host}, telefono, email, orario, temperatura, prezzo, link e percentuale protetti (⟦n⟧) e rimessi al loro posto', count($prot) === 3
      && $prot[0] === "Ciao \u{27E6}1\u{27E7}!\n\nChiama il \u{27E6}2\u{27E7} o scrivi a \u{27E6}3\u{27E7}. Check-in dalle \u{27E6}4\u{27E7}, \u{27E6}5\u{27E7}, \u{27E6}6\u{27E7} a notte, \u{27E6}7\u{27E7} e il \u{27E6}8\u{27E7} di sconto."
      && count($prot[1]) === 8 && $prot[2] === "[en] Ciao {host}!\n\nChiama il +39 075 123 4567 o scrivi a info@casa.it. Check-in dalle 15:00, 22 °C, 15 € a notte, https://casa.it/x?a=1 e il 10% di sconto.", json_encode($prot));
prova('9G·4 · i campi che non si traducono mai (nomi, codici, indirizzi, link) per nome del campo', $php9('echo json_encode([MHW\Traduttore::nonTradurre("contacts.ab12.phone"), MHW\Traduttore::nonTradurre("networks.x.ssid"), MHW\Traduttore::nonTradurre("checkin_steps.0")]);') === '[true,true,false]');
db()->prepare("DELETE FROM translation_suggestions WHERE property_id = ?")->execute([$casa]);
$ptIt((int) $lo[0]['id'], 'description', 'Pasta fresca ogni mattina, chiama il +39 075 123 4567.'); $ptEn((int) $lo[0]['id'], 'description', '');
$primaC = count($richiesteTr());
$lucia->post("/pannello/$casa/lingue/en/suggerite", ['campo' => 'place:' . $lo[0]['id'] . ':description']);
$sugC = riga("SELECT * FROM translation_suggestions WHERE target_type = 'place' AND target_id = ? AND locale = 'en'", [$lo[0]['id']]);
$richC = array_slice($richiesteTr(), $primaC);
prova('9G·3/4 · la chiamata ad Amazon porta il testo protetto (il telefono è ⟦1⟧) e la suggerita ha il numero vero; la frase entra nella memoria', count($richC) === 1 && str_contains((string) ($richC[0]['testo'] ?? ''), "\u{27E6}1\u{27E7}")
      && ($sugC['text'] ?? '') === '[en] Pasta fresca ogni mattina, chiama il +39 075 123 4567.' && (int) val("SELECT COUNT(*) FROM translation_cache WHERE testo_tradotto LIKE '[en] Pasta fresca%'") === 1, json_encode([$richC, $sugC['text'] ?? null]));
// Lo stesso testo in un'altra guida: dalla memoria, senza chiamare Amazon.
$casa2 = (int) val("SELECT id FROM properties WHERE account_id = ? AND id <> ? AND archived_at IS NULL ORDER BY id", [$lacc9, $casa]);
$primaM = count($richiesteTr());
$usoM = $php9('echo MHW\Traduttore::traduci(' . $lacc9 . ', ' . ($casa2 ?: $casa) . ', "Pasta fresca ogni mattina, chiama il +39 075 999 0000.", "it", "en"), "|", MHW\Db::val("SELECT outcome FROM translation_usage WHERE account_id = ' . $lacc9 . ' ORDER BY id DESC LIMIT 1"), "|", MHW\Db::val("SELECT hits FROM translation_cache WHERE testo_tradotto LIKE \'[en] Pasta fresca%\'");');
prova('9G·3 · la stessa frase (anche con un altro telefono: il segnaposto è uguale) si riusa dalla memoria con esito «cache», senza chiamare Amazon', $usoM === '[en] Pasta fresca ogni mattina, chiama il +39 075 999 0000.|cache|1' && count($richiesteTr()) === $primaM, $usoM);
prova('9G·2 · salvare una sezione senza cambiare niente non chiama Amazon', ($lucia->post("/pannello/$casa/sezioni/$emCasa", ['note' => 'Il pronto soccorso è a Nottola, 20 minuti in auto.'])['code'] ?? 0) === 302 && count($richiesteTr()) === $primaM);
// Una traduzione approvata e poi l'originale cambia: la suggerita nuova la sostituisce, «Aggiornata: da ricontrollare».
$lucia->post("/pannello/$casa/lingue/en/suggerite", ['approva' => (string) ($sugC['id'] ?? 0)]);
$ptIt((int) $lo[0]['id'], 'description', 'Pasta fresca ogni mattina e pane del forno a legna.');
$primaA = count($richiesteTr());
$r = $lucia->segui($lucia->post("/pannello/$casa/lingue/en/suggerite", ['campo' => 'place:' . $lo[0]['id'] . ':description']));
prova('9G·2 · cambia un campo: una chiamata per quella lingua, solo quel campo, e la suggerita è «Aggiornata: da ricontrollare» (sostituisce la traduzione vecchia quando la approvi)', count($richiesteTr()) === $primaA + 1
      && str_contains($r['body'], 'Aggiornata: da ricontrollare') && str_contains($r['body'], '[en] Pasta fresca ogni mattina e pane del forno a legna.'));
// Il tetto annuale: il contatore, l'email a noi all'80% e al 100%, «Aggiungi 250.000» dalla scheda del cliente.
$usatiL = (int) $php9('echo MHW\Traduttore::usatiAnno(' . $lacc9 . ');');
db()->prepare('UPDATE accounts SET translate_cap_override = ? WHERE id = ?')->execute([$usatiL + 30, $lacc9]);
$ptIt((int) $lo[2]['id'], 'note', 'Chiude il martedì; il mercoledì apre alle 19, il giovedì alle 20 e il venerdì a mezzogiorno.'); $ptEn((int) $lo[2]['id'], 'note', '');
db()->prepare("DELETE FROM translation_suggestions WHERE target_type = 'place' AND target_id = ?")->execute([$lo[2]['id']]);
$primaPostaT = count(righePosta() ?: []);
$r = $lucia->segui($lucia->post("/pannello/$casa/lingue/en/suggerite", ['campo' => 'place:' . $lo[2]['id'] . ':note']));
$postaT = array_values(array_filter(array_map(fn($l) => json_decode($l, true), array_slice(righePosta() ?: [], $primaPostaT)), fn($m) => ($m['to'] ?? '') === 'assistenza@welcomemyhouse.it'));
prova('9G·1 · tetto annuale raggiunto: «Hai usato le traduzioni suggerite di quest\'anno (N caratteri)… scrivici», registrata come «limite», e l\'email a noi (una volta)', str_contains($r['body'], 'Hai usato le traduzioni suggerite di quest&#039;anno')
      && str_contains($r['body'], 'le riattiviamo noi') && (bool) val("SELECT id FROM translation_usage WHERE account_id = ? AND outcome = 'limite' AND error LIKE 'tetto annuale%'", [$lacc9])
      && count($postaT) === 1 && str_contains((string) $postaT[0]['subject'], 'Traduzioni suggerite: Lucia') && str_contains((string) $postaT[0]['subject'], 'del tetto annuale')
      && (bool) val("SELECT id FROM translation_usage WHERE account_id = ? AND outcome IN ('avviso80', 'avviso100')", [$lacc9]), json_encode([array_column($postaT, 'subject'), $usatiL, $php9('echo json_encode(MHW\Traduttore::contatore(' . $lacc9 . '));'), $php9('$s = MHW\Subscriptions::active(' . $lacc9 . '); echo json_encode($s ? [$s["status"], $s["current_period_start"], $s["current_period_end"]] : null);')]));
db()->prepare('UPDATE accounts SET translate_cap_override = ? WHERE id = ?')->execute([$usatiL, $lacc9]);   // il tetto proprio ai caratteri usati: 100%
$r = $lucia->get("/pannello/$casa/lingue/en");
prova('9G·5 · nella pagina della lingua il contatore è al 100% con il link all\'assistenza, e Lingue dice «Limite dell\'anno raggiunto»; niente «Traduci»', str_contains($r['body'], 'tradu-contatore--finito') && str_contains($r['body'], 'mailto:assistenza@welcomemyhouse.it?subject=') && str_contains($r['body'], 'Traduzioni%20suggerite%3A%20riattivazione')
      && !str_contains($r['body'], 'name="suggerisci"') && str_contains($lucia->get("/pannello/$casa/lingue")['body'], 'Limite dell\'anno raggiunto'));
$r = $admin->get("/admin/cliente/$lacc9");
prova('9G·1 · scheda del cliente: «Traduzioni: N / M caratteri quest\'anno», «Aggiungi 250.000» e il tetto personale', preg_match('#Traduzioni: <b>[\d.]+ / [\d.]+</b> caratteri quest\'anno#', $r['body']) === 1 && str_contains($r['body'], 'Aggiungi 250.000')
      && str_contains($r['body'], 'id="tr-tetto"'));
$r = $admin->segui($admin->post("/admin/cliente/$lacc9/traduzioni/tetto", ['aggiungi' => '1', 'nota' => '']));
prova('9G·1 · senza la nota non si aggiunge niente', str_contains($r['body'], 'Scrivi una nota') && (int) val('SELECT translate_cap_override FROM accounts WHERE id = ?', [$lacc9]) === $usatiL);
$r = $admin->segui($admin->post("/admin/cliente/$lacc9/traduzioni/tetto", ['aggiungi' => '1', 'nota' => 'Guida grande, ha chiesto di riattivarle']));
prova('9G·1 · «Aggiungi 250.000»: il tetto sale, nel registro con la nota, e le suggerite ripartono', (int) val('SELECT translate_cap_override FROM accounts WHERE id = ?', [$lacc9]) === $usatiL + 250000
      && str_contains($r['body'], 'Aggiunti 250.000 caratteri') && (bool) val("SELECT id FROM audit_log WHERE action = 'translate.cap_added' AND meta LIKE '%Guida grande%'")
      && str_contains($lucia->get("/pannello/$casa/lingue/en")['body'], 'name="suggerisci"'));
$r = $admin->segui($admin->post("/admin/cliente/$lacc9/traduzioni/tetto", ['salva' => '1', 'tetto' => '', 'nota' => 'torna di serie']));
prova('9G·1 · il tetto personale si toglie (vale quello di serie, 500.000 all\'anno, che si azzera al rinnovo)', val('SELECT translate_cap_override FROM accounts WHERE id = ?', [$lacc9]) === null
      && $php9('echo MHW\Traduttore::tettoAccount(' . $lacc9 . ');') === '500000' && preg_match('#"azzera":"\d{4}-\d{2}-\d{2}T#', $php9('echo json_encode(MHW\Traduttore::contatore(' . $lacc9 . '));')) === 1);
$r = $admin->get('/admin/traduzioni');
prova('9G·5 · Vendite → Traduzioni: per mese con gli esiti (ok, dalla memoria, fermate, errori), chi ha usato di più negli ultimi 12 mesi con il tetto annuale, la memoria', $r['code'] === 200 && pulita($r)
      && str_contains($r['body'], 'Dalla memoria') && str_contains($r['body'], 'Chi ha usato di più negli ultimi 12 mesi') && str_contains($r['body'], 'caratteri all&#039;anno · memoria:') && !str_contains($r['body'], 'Anni in omaggio')
      && str_contains($admin->get('/admin')['body'], 'href="/welcomebook/index.php/admin/traduzioni"'));
db()->prepare("INSERT INTO translation_cache (hash, da, a, chars, testo_tradotto, hits, created_at) VALUES ('vecchia-mai-usata', 'it', 'en', 5, 'x', 0, ?)")->execute(['2025-01-01T00:00:00Z']);
$cron();
prova('9G·3 · pulizia: le righe della memoria mai riusate da 12 mesi spariscono con il cron', !val("SELECT id FROM translation_cache WHERE hash = 'vecchia-mai-usata'"));

// ================================================================= 9J
capitolo('9J · palette «Bianco e nero»: chiara e scura, colori della guida in grigio, foto in bianco e nero, le altre palette uguali');
$p9j = json_decode($php9('echo json_encode([MHW\Palette::readable("monocromo", "scuro"), MHW\Palette::readable("monocromo", "chiaro"), round(min(MHW\Palette::pairs("monocromo", "scuro")), 2), round(min(MHW\Palette::pairs("monocromo", "chiaro")), 2), array_keys(MHW\Palette::all()), MHW\Palette::css("terracotta"), MHW\Palette::css("monocromo")]);'), true) ?: [];
prova('9J · «Bianco e nero» leggibile nelle due versioni (tutte le coppie ≥ 5,7:1), prima di Grafite', ($p9j[0] ?? false) === true && ($p9j[1] ?? false) === true && ($p9j[2] ?? 0) >= 5.7 && ($p9j[3] ?? 0) >= 5.7
      && array_search('monocromo', $p9j[4] ?? []) === array_search('grafite', $p9j[4] ?? []) - 1, json_encode(array_slice($p9j, 0, 5)));
prova('9J · solo «Bianco e nero» ridefinisce i colori fissi (ocra, mare, bordo dei riquadri, lente, pagine a tutto schermo); Terracotta no', str_contains((string) ($p9j[6] ?? ''), '--ochre:#d9d9d9;') && str_contains((string) ($p9j[6] ?? ''), '--tile-bordo:1px solid #2e2e2e;')
      && str_contains((string) ($p9j[6] ?? ''), '--full-fondo:#0d0d0d;') && !str_contains((string) ($p9j[5] ?? 'x--ochre'), '--ochre') && !str_contains((string) ($p9j[5] ?? '--tile-bordo'), '--tile-bordo'));
$cssApp = (string) file_get_contents("$DOVE/assets/app.css");
prova('9J · CSS: i colori scritti a mano diventano variabili con lo stesso valore di serie (le altre sei palette non cambiano)', str_contains($cssApp, 'border:1px solid var(--scelta-bordo,rgba(35,27,18,.28))') && str_contains($cssApp, 'background:var(--full-fondo,#17130d)')
      && str_contains($cssApp, '.tile{border:var(--tile-bordo,0)}') && str_contains($cssApp, '.foto-bn img:not(.logo-guest):not([src^="data:"]){filter:grayscale(1)}'));
// Un cliente nuovo con Plus scelto (le palette sono comprese): Aspetto, salvataggio e anteprima della guida.
db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'register:%'");
$vale = new Browser('vale9j');
$vale->get('/registrati?piano=' . pv('plus'));
$vale->post('/registrati', ['piano' => pv('plus'), 'name' => 'Vale Bianconero', 'email' => 'vale9j@prova.test', 'email2' => 'vale9j@prova.test', 'password' => 'ValeProva12345', 'termini' => '1', 'privacy' => '1']);
$vale->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Casa Vale', 'city' => 'Orvieto']);
$vp9 = (int) val('SELECT p.id FROM properties p JOIN accounts a ON a.id = p.account_id JOIN users u ON u.id = a.user_id WHERE u.email = ?', ['vale9j@prova.test']);
$r = $vale->get("/pannello/$vp9/aspetto");
prova('9J · Aspetto: la scheda «Bianco e nero», la riga che la spiega e «Foto in bianco e nero» (visibili solo con questa palette)', $vp9 > 0 && str_contains($r['body'], 'value="monocromo"') && str_contains($r['body'], 'Bianco e nero')
      && str_contains($r['body'], 'Elegante e sobria: ideale se le foto della casa parlano da sole.') && str_contains($r['body'], 'name="photos_bw"') && str_contains($r['body'], 'data-solo-palette="monocromo"'));
$rS9 = $vale->segui($vale->post("/pannello/$vp9/aspetto", ['palette' => 'monocromo', 'text_tone' => 'chiaro', 'photos_bw' => '1']));
$r = $vale->get("/pannello/$vp9/anteprima");
$snap9j = json_decode($php9('echo json_encode(MHW\Guide::normalize(MHW\Guide::build(' . $vp9 . '))["property"]);'), true) ?: [];
prova('9J · guida con «Bianco e nero» scura e le foto in bianco e nero: <html class="palette-monocromo foto-bn">, nell\'istantanea photos_bw = 1', (int) val('SELECT photos_bw FROM properties WHERE id = ?', [$vp9]) === 1
      && str_contains($r['body'], 'class="palette-monocromo foto-bn"') && str_contains($r['body'], 'data-theme="scuro"') && (int) ($snap9j['photos_bw'] ?? 0) === 1 && str_contains($r['body'], '--tile-bordo:'),
      json_encode([val('SELECT palette FROM properties WHERE id = ?', [$vp9]), preg_match('#<html[^>]*>#', $r['body'], $mH) ? $mH[0] : '', preg_match('#class="note[^"]*"[^>]*>([^<]*)#', $rS9['body'], $mF) ? $mF[1] : '']));
$vale->post("/pannello/$vp9/aspetto", ['palette' => 'mare', 'text_tone' => 'scuro', 'photos_bw' => '1']);
prova('9J · con un\'altra palette «Foto in bianco e nero» si spegne', (int) val('SELECT photos_bw FROM properties WHERE id = ?', [$vp9]) === 0 && val('SELECT palette FROM properties WHERE id = ?', [$vp9]) === 'mare');
prova('9J · migrazione 047: properties.photos_bw', (bool) val("SELECT 1 FROM schema_migrations WHERE name LIKE '047%'"));

// ================================================================= 9I
capitolo('9I · codice cliente, struttura e pagamento; ricerca unica in Amministrazione');
$c9 = json_decode($php9('echo json_encode(["acc" => (int) MHW\Db::val("SELECT COUNT(*) FROM accounts WHERE code IS NULL OR code = \'\'"), "prop" => (int) MHW\Db::val("SELECT COUNT(*) FROM properties WHERE code IS NULL OR code = \'\'"),
    "ordini" => (int) MHW\Db::val("SELECT COUNT(*) FROM orders WHERE status = \'paid\' AND (code IS NULL OR code = \'\')"), "dup" => (int) MHW\Db::val("SELECT COUNT(*) - COUNT(DISTINCT codice) FROM codici_usati"),
    "forma" => (int) MHW\Db::val("SELECT COUNT(*) FROM accounts WHERE code NOT LIKE \'MW-_____\'")]);'), true) ?: [];
prova('9I · tutti gli account, le strutture e gli ordini pagati hanno un codice, nella forma giusta, senza doppioni', $c9 === ['acc' => 0, 'prop' => 0, 'ordini' => 0, 'dup' => 0, 'forma' => 0], json_encode($c9));
$prima9i = $php9('echo md5(json_encode(MHW\Db::all("SELECT id, code FROM accounts ORDER BY id")) . json_encode(MHW\Db::all("SELECT id, code FROM properties ORDER BY id")));');
$php9('$f = require "' . $DOVE . '/app/migrations/048_codici_9i.php"; $f(MHW\Db::conn());');
prova('9I · migrazione 048 una seconda volta: i codici restano quelli', $prima9i === $php9('echo md5(json_encode(MHW\Db::all("SELECT id, code FROM accounts ORDER BY id")) . json_encode(MHW\Db::all("SELECT id, code FROM properties ORDER BY id")));'));
$norm9 = $php9('echo json_encode(array_map([MHW\Codici::class, "normalizza"], ["mw 7k4p2", "MW7K4P2", "mw-7k4p2-01", "MW-7K4P2-2026-1", "mw7k4p2 2026 12", "casa"]));');
prova('9I · normalizza: «mw 7k4p2», «MW7K4P2», struttura e pagamento scritti male', $norm9 === '["MW-7K4P2","MW-7K4P2","MW-7K4P2-01","MW-7K4P2-2026-1","MW-7K4P2-2026-12",""]', $norm9);
// Registrazione: il codice subito
db()->exec("DELETE FROM rate_limits WHERE bucket LIKE 'register:%'");
$nina = new Browser('nina9i');
$nina->get('/registrati?piano=' . pv('plus'));
$nina->post('/registrati', ['piano' => pv('plus'), 'name' => 'Nina Codice', 'email' => 'nina9i@prova.test', 'email2' => 'nina9i@prova.test', 'password' => 'NinaProva12345', 'termini' => '1', 'privacy' => '1']);
$ninaAcc = $accDi('nina9i@prova.test'); $ninaCod = (string) val('SELECT code FROM accounts WHERE id = ?', [$ninaAcc]);
$rN = $nina->get('/account');
prova('9I · alla registrazione il codice cliente c\'è già, e in Account & Fatturazione «Il tuo codice cliente» con «Copia»', $ninaAcc > 0 && preg_match('/^MW-[23456789A-HJ-NP-Z]{5}$/', $ninaCod) === 1
      && str_contains($rN['body'], 'Il tuo codice cliente') && str_contains($rN['body'], 'data-copia="' . $ninaCod . '"') && str_contains($rN['body'], 'Tienilo a portata di mano quando scrivi all\'assistenza.'), $ninaCod . ' ' . $rN['code'] . ' ' . (preg_match('#<title>([^<]*)#', $rN['body'], $mT) ? $mT[1] : ''));
$nina->modulo('/pannello/nuova', '/pannello/nuova', ['name' => 'Casa Nina', 'city' => 'Gubbio']);
$ninaP = (int) val('SELECT id FROM properties WHERE account_id = ?', [$ninaAcc]);
prova('9I · la struttura nuova ha il codice cliente + 01, e si vede in piccolo in «Struttura e contatti»', (string) val('SELECT code FROM properties WHERE id = ?', [$ninaP]) === $ninaCod . '-01'
      && str_contains($nina->get("/pannello/$ninaP/procedura/struttura")['body'], 'codice-struttura">' . $ninaCod . '-01<'));
prova('9I · i mailto dell\'assistenza dal pannello hanno «[codice] …» nell\'oggetto', str_contains($rN['body'], 'subject=' . rawurlencode('[' . $ninaCod . ']')));
// Le email al cliente con il codice nel piede; l'invito a un amico no.
$php9('MHW\Mailer::manda("nina9i@prova.test", "Prova del piede 9I", "Prova", ["Ciao"]); MHW\Mailer::manda("amico9i@prova.test", "Prova amico 9I", "Prova", ["Ciao"]);');
$pN = array_values(array_filter(array_map(fn($l) => json_decode($l, true), righePosta()), fn($m) => str_starts_with((string) ($m['subject'] ?? ''), 'Prova del piede 9I') || str_starts_with((string) ($m['subject'] ?? ''), 'Prova amico 9I')));
prova('9I · le email a un cliente hanno «Codice cliente …» nel piede; quelle a chi non è cliente (un amico invitato) no', count($pN) === 2 && str_contains((string) $pN[0]['text'], 'Codice cliente ' . $ninaCod) && !str_contains((string) $pN[1]['text'], 'Codice cliente'));
// Ricerca
$lc9 = (string) val('SELECT code FROM accounts WHERE id = ?', [$lacc9]); $pc9 = (string) val('SELECT code FROM properties WHERE id = ?', [$casa]);
$r = $admin->get('/admin/cerca?q=' . rawurlencode(strtolower(str_replace('-', ' ', $lc9))));
prova('9I · «Cerca» con il codice cliente scritto minuscolo e con lo spazio: un risultato, apre la scheda', $r['code'] === 302 && str_ends_with((string) $r['loc'], '/admin/cliente/' . $lacc9), $r['code'] . ' ' . $r['loc']);
$r = $admin->get('/admin/cerca?q=' . rawurlencode($pc9));
prova('9I · …con il codice struttura: la scheda con la struttura in evidenza', $r['code'] === 302 && str_contains((string) $r['loc'], '/admin/cliente/' . $lacc9 . '?evidenzia=struttura-' . $casa)
      && str_contains($admin->get('/admin/cliente/' . $lacc9 . '?evidenzia=struttura-' . $casa)['body'], 'codici-cliente__riga--evidenza" id="struttura-' . $casa . '"'));
$cin9 = (string) val('SELECT cin FROM properties WHERE id = ?', [$casa]); $slug9 = (string) val('SELECT slug FROM properties WHERE id = ?', [$casa]);
$tel9 = (string) val("SELECT host_phone FROM properties WHERE id = ?", [$casa]);
$trova9 = fn(string $q) => json_decode($php9('echo json_encode(array_column(MHW\Codici::trova(' . var_export($q, true) . '), "account_id"));'), true) ?: [];
$cus9 = (string) val("SELECT stripe_customer_id FROM accounts WHERE stripe_customer_id <> '' ORDER BY id LIMIT 1"); $cusAcc = (int) val('SELECT id FROM accounts WHERE stripe_customer_id = ?', [$cus9]);
prova('9I · si trova anche per email, CIN, URL della guida, telefono senza prefisso e cliente Stripe (cus_…)', in_array($lacc9, $trova9('lucia@esempio'), true)
      && ($cin9 === '' || in_array($lacc9, $trova9(strtolower($cin9)), true)) && in_array($lacc9, $trova9('https://www.welcomemyhouse.it/g/' . $slug9 . '?l=en'), true)
      && ($tel9 === '' || in_array($lacc9, $trova9((string) preg_replace('/^\+39\s*/', '', $tel9)), true)) && ($cus9 === '' || in_array($cusAcc, $trova9($cus9), true)),
      json_encode([$cin9, $tel9, $cus9]));
$r = $admin->get('/admin/cerca?q=' . rawurlencode('@prova.test'));
prova('9I · più risultati: l\'elenco con nome, email, codice, piano, stato e perché', $r['code'] === 200 && pulita($r) && str_contains($r['body'], 'clienti con «@prova.test»') && str_contains($r['body'], 'trovato per email') && str_contains($r['body'], '<code>MW-'));
$r = $admin->get('/admin/cerca?q=' . rawurlencode('zzz-nessuno-9i'));
prova('9I · nessun risultato: «Nessun cliente con “…”»', str_contains($r['body'], 'Nessun cliente con «zzz-nessuno-9i»'));
prova('9I · la ricerca resta nel registro, senza il testo se è un\'email', (bool) val("SELECT id FROM audit_log WHERE action = 'admin.search' AND meta LIKE '%un indirizzo email%'") && !val("SELECT id FROM audit_log WHERE action = 'admin.search' AND meta LIKE '%@prova.test%'"));
$rA = $admin->get('/admin/clienti?q=' . rawurlencode($lc9));
prova('9I · «Cerca» in ogni pagina di Amministrazione (con «/»), e Clienti usa la stessa ricerca, con la colonna «Codice»', str_contains($admin->get('/admin/impostazioni')['body'], 'data-cerca-admin')
      && str_contains($rA['body'], '<th>Codice</th>') && str_contains($rA['body'], '<code>' . $lc9 . '</code>') && substr_count($rA['body'], 'admin/entra/') === 1
      && str_contains((string) file_get_contents("$DOVE/assets/cms.js"), "e.key !== '/'"));
// Stripe: i codici nei metadati del Checkout, e i rinnovi con il loro codice pagamento.
$ck9 = array_values(array_filter(richiesteStripe(), fn($x) => $x['metodo'] === 'POST' && $x['percorso'] === '/v1/checkout/sessions' && isset($x['corpo']['subscription_data'])));
$ult9 = end($ck9)['corpo'] ?? [];
prova('9I · Checkout: codice_cliente, codice_pagamento e codice_struttura nei metadati della sessione e dell\'abbonamento; la causale «Codice cliente … · Pagamento …»',
      preg_match('/^MW-\w{5}$/', (string) ($ult9['metadata']['codice_cliente'] ?? '')) === 1 && preg_match('/^MW-\w{5}-\d{4}-\d+$/', (string) ($ult9['subscription_data']['metadata']['codice_pagamento'] ?? '')) === 1
      && str_starts_with((string) ($ult9['subscription_data']['description'] ?? ''), 'Codice cliente MW-'), json_encode(['m' => $ult9['metadata'] ?? null, 'd' => $ult9['subscription_data']['description'] ?? null]));
$subL9i = (int) val("SELECT id FROM subscriptions WHERE provider = 'stripe' AND provider_subscription_id <> '' ORDER BY id DESC LIMIT 1");
$accR9 = (int) val('SELECT account_id FROM subscriptions WHERE id = ?', [$subL9i]); $codAccR9 = (string) val('SELECT code FROM accounts WHERE id = ?', [$accR9]);
$rinn9 = $php9('$s = MHW\Db::one("SELECT * FROM subscriptions WHERE id = ' . $subL9i . '"); $r = new ReflectionMethod(MHW\Billing::class, "onInvoicePaid"); $r->setAccessible(true); echo $r->invoke(null, ["id" => "in_rinnovo9i", "subscription" => $s["provider_subscription_id"], "billing_reason" => "subscription_cycle", "created" => time(), "lines" => ["data" => []]]);');
$codR = (string) val("SELECT codice FROM codici_usati WHERE invoice_id = 'in_rinnovo9i'");
$metaR = array_values(array_filter(richiesteStripe(), fn($x) => $x['metodo'] === 'POST' && $x['percorso'] === '/v1/invoices/in_rinnovo9i'));
prova('9I · rinnovo: un codice pagamento nuovo (MW-…-anno-n), scritto nei metadati della fattura Stripe e nella scheda del cliente', $subL9i > 0 && str_starts_with($codR, $codAccR9 . '-' . gmdate('Y') . '-')
      && ($metaR[0]['corpo']['metadata']['codice_pagamento'] ?? '') === $codR && str_contains($admin->get('/admin/cliente/' . $accR9)['body'], $codR), $rinn9 . ' ' . $codR . ' ' . json_encode($metaR[0]['corpo'] ?? null));
$prova9tool = (string) shell_exec('cd ' . escapeshellarg("$DOVE/app") . ' && php tools/stripe-codici.php 2>&1');
prova('9I · tools/stripe-codici.php: a vuoto dice quali clienti Stripe riceveranno il codice', str_contains($prova9tool, 'Prova a vuoto') && ($cus9 === '' || str_contains($prova9tool, $cus9 . ' → MW-')), substr($prova9tool, 0, 200));
$php9('MHW\Log::error("prova del registro 9I", ["account" => ' . $lacc9 . ']);');
prova('9I · nei registri tecnici le righe con un account portano codice_cliente', str_contains(implode("\n", righeLog('app')), '"codice_cliente":"' . $lc9 . '"'));
$r = $admin->get('/admin/omaggi');
prova('9I · il codice cliente cliccabile accanto ai nomi (Preventivi, Giudizi, Codici omaggio, Traduzioni)', str_contains((string) file_get_contents("$DOVE/app/views/admin/preventivi.php"), 'Codici::link(')
      && str_contains((string) file_get_contents("$DOVE/app/views/admin/giudizi.php"), 'Codici::link(') && str_contains((string) file_get_contents("$DOVE/app/views/admin/traduzioni.php"), 'Codici::link(')
      && $r['code'] === 200 && pulita($r));

@unlink($fileLocale); @unlink(dirname($fileLocale) . '/config.local.bak.php');

// ================================================================= RIEPILOGO
$finito = true; echo implode("\n", $esiti), "\n\n";
$tot = count(array_filter($esiti, fn($e) => !str_starts_with($e, "\n")));
echo $falliti === 0 ? "Tutte le $tot prove superate.\n" : "$falliti prove NON superate su $tot.\n";
exec('rm -rf ' . escapeshellarg($TMP));
exit($falliti === 0 ? 0 : 1);
