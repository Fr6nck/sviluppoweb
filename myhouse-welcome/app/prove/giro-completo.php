<?php
/**
 * Giro completo sull'applicazione vera, via HTTP.
 *
 * Non si esegue da solo: lo lancia esegui.sh, che prima schiera una copia
 * dell'applicazione in una cartella temporanea e ci mette davanti un server.
 * Si prova quello che si carica sull'hosting, non il codice sorgente.
 *
 * Uso:  php giro-completo.php <indirizzo-base> <cartella-schierata>
 */
$BASE = rtrim($argv[1] ?? 'http://127.0.0.1:8088/welcomebook/index.php', '/');
$DOVE = rtrim($argv[2] ?? '/tmp/mhw-prova/welcomebook', '/');
$COOKIE = sys_get_temp_dir() . '/mhw-prova-cookies.txt';
@unlink($COOKIE);
$esiti = []; $falliti = 0;

function chiama(string $url, ?array $post = null, bool $segui = false): array {
    global $COOKIE;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $COOKIE, CURLOPT_COOKIEFILE => $COOKIE,
        CURLOPT_FOLLOWLOCATION => $segui, CURLOPT_TIMEOUT => 20,
    ]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hsize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $loc = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    return ['code' => $code, 'body' => substr($raw, $hsize), 'head' => substr($raw, 0, $hsize), 'loc' => $loc];
}
function token(string $html): string {
    return preg_match('/name="_csrf" value="([a-f0-9]+)"/', $html, $m) ? $m[1] : '';
}
function prova(string $nome, bool $ok, string $nota = ''): void {
    global $esiti, $falliti;
    if (!$ok) $falliti++;
    $esiti[] = ($ok ? '  ok  ' : ' NO   ') . $nome . ($nota !== '' ? '   — ' . $nota : '');
}
function senzaErrori(array $r): bool {
    return !str_contains($r['body'], 'Fatal error') && !str_contains($r['body'], 'Warning:')
        && !str_contains($r['body'], 'Notice:') && !str_contains($r['body'], 'si è fermata su un errore')
        && !str_contains($r['body'], 'Vista mancante');
}

// ------------------------------------------------------------ installazione
$r = chiama("$BASE/installa");
prova('La pagina di installazione si apre', $r['code'] === 200 && senzaErrori($r));
prova('Propone i dati di esempio', str_contains($r['body'], 'clienti di esempio'));
$t = token($r['body']);
$r = chiama("$BASE/installa", ['_csrf' => $t, 'email' => 'admin@blackout.in',
                               'password' => 'provaprova1', 'esempi' => '1']);
prova('Installazione riuscita, porta in amministrazione', $r['code'] === 302 && str_contains($r['loc'], '/admin'));

// ------------------------------------------------------- quadro amministrativo
$r = chiama("$BASE/admin");
prova('Il quadro si apre', $r['code'] === 200 && senzaErrori($r));
prova('Il quadro dice quanti clienti ci sono', str_contains($r['body'], 'con un piano attivo'));
prova('Il quadro elenca le versioni di piano', str_contains($r['body'], 'versione per versione'));
prova('Il quadro avvisa dei clienti di esempio', str_contains($r['body'], 'clienti di esempio'));
prova('Il quadro mostra un incasso', preg_match('/incassato, ordini confermati/', $r['body']) === 1);

$r = chiama("$BASE/admin/clienti");
prova('I clienti si elencano', $r['code'] === 200 && senzaErrori($r));
foreach (['Lucia Ferrante', 'Marco Bevilacqua', 'Agnese Ruta'] as $chi)
    prova("C'è $chi", str_contains($r['body'], $chi));
prova('Le strutture compaiono accanto al nome', str_contains($r['body'], 'Casa Lucia') && str_contains($r['body'], 'Montepulciano'));
prova('Lo stato "Mai pubblicata" si vede', str_contains($r['body'], 'Mai pubblicata'));

$r = chiama("$BASE/admin/clienti?q=Rondini");
prova('La ricerca per struttura funziona',
      str_contains($r['body'], 'Marco Bevilacqua') && !str_contains($r['body'], 'Agnese Ruta'));

$r = chiama("$BASE/admin/pacchetti");
prova('Il listino si apre', $r['code'] === 200 && senzaErrori($r));
prova('Il listino spiega le versioni congelate', str_contains($r['body'], 'versione nuova'));

$r = chiama("$BASE/admin/cliente/2");
prova('La scheda di un cliente si apre', $r['code'] === 200 && senzaErrori($r));
prova('La scheda mostra da dove vengono i diritti', str_contains($r['body'], 'dal piano'));

$r = chiama("$BASE/admin/diagnostica");
prova('La diagnostica si apre', $r['code'] === 200 && senzaErrori($r));

// ------------------------------------------------------------------ pubblico
$r = chiama("$BASE/");
prova('Il sito pubblico si apre', $r['code'] === 200 && senzaErrori($r));
prova('Il titolo grande è quello giusto', str_contains($r['body'], 'La casa risponde'));
prova('La fotografia grande viene da una guida vera', str_contains($r['body'], 'aperture questo mese'));
prova('I tre piani ci sono',
      str_contains($r['body'], 'Essential') && str_contains($r['body'], 'Plus') && str_contains($r['body'], 'Pro'));
prova('Il piano Plus è quello scuro', str_contains($r['body'], 'plan--dark'));
prova('I prezzi vengono dal database', str_contains($r['body'], '€'));

// -------------------------------------------------------------- guida ospite
$r = chiama("$BASE/g/casa-lucia");
prova('La guida di Casa Lucia si apre', $r['code'] === 200 && senzaErrori($r));
prova('La guida saluta per nome', str_contains($r['body'], 'Benvenuti'));
prova('La copertina è una fotografia vera', str_contains($r['body'], '/media/'));
prova('Il check-in è sulla foto', str_contains($r['body'], 'Check-in dalle 15:00'));
prova('Le sezioni sono riquadri colorati', substr_count($r['body'], 'class="tile ') >= 3);
prova('Si può cambiare lingua', str_contains($r['body'], 'langpick'));

$r = chiama("$BASE/g/casa-lucia/benvenuto");
prova('La soglia si apre', $r['code'] === 200 && senzaErrori($r));
prova('La soglia ha la foto a tutto schermo', str_contains($r['body'], 'full__photo'));
prova('La soglia ha il bottone Entra', str_contains($r['body'], 'id="entra"') && str_contains($r['body'], 'Entra <span class="go"'));
prova('La soglia elenca le lingue', str_contains($r['body'], 'Deutsch'));

$r = chiama("$BASE/g/casa-lucia/commiato");
prova('Il congedo si apre', $r['code'] === 200 && senzaErrori($r));
prova('Il congedo augura buon viaggio', str_contains($r['body'], 'Buon viaggio'));
prova('Il congedo ricorda il codice vero', str_contains($r['body'], '4729'));

// Le sezioni, una per una
preg_match_all('#/g/casa-lucia/(\d+)\?l=it#', $r['body'] ?: '', $m);
$r = chiama("$BASE/g/casa-lucia");
preg_match_all('#/g/casa-lucia/(\d+)\?l=it#', $r['body'], $m);
$ids = array_unique($m[1]);
prova('La guida rimanda a tutte le sezioni', count($ids) >= 3, count($ids) . ' sezioni');
$visto = ['wifi' => false, 'luoghi' => false, 'codice' => false];
foreach ($ids as $id) {
    $s = chiama("$BASE/g/casa-lucia/$id");
    prova("La sezione $id si apre", $s['code'] === 200 && senzaErrori($s));
    if (str_contains($s['body'], 'glicine2024')) { $visto['wifi'] = true;
        prova('Il Wi-Fi è in tema notte', str_contains($s['body'], 'class="night"'));
        prova('Il Wi-Fi si può copiare', str_contains($s['body'], 'data-copia-di="glicine2024"')); }
    if (str_contains($s['body'], 'Osteria del Ponte')) { $visto['luoghi'] = true;
        prova('I luoghi hanno la fotografia', substr_count($s['body'], 'class="thumb"') >= 3);
        prova('I luoghi hanno le etichette', str_contains($s['body'], 'Aperto fino alle 23'));
        prova('I luoghi si filtrano per categoria', str_contains($s['body'], 'data-filtro')); }
    if (str_contains($s['body'], '4 7 2 9')) { $visto['codice'] = true;
        prova('L\'arrivo è a passaggi numerati', str_contains($s['body'], 'class="step"')); }
}
prova('La sezione Wi-Fi esiste', $visto['wifi']);
prova('La sezione dei luoghi esiste', $visto['luoghi']);
prova('La sezione di arrivo mostra il codice', $visto['codice']);

// La guida in tedesco
$r = chiama("$BASE/g/casa-lucia?l=de");
prova('La guida esiste anche in tedesco', str_contains($r['body'], 'Ins Haus kommen'));

// La bozza non è pubblica
$r = chiama("$BASE/g/il-cortile");
prova('Una guida in bozza non si apre agli ospiti', $r['code'] === 404);

// Il QR
$db = glob($DOVE . '/app/storage/*.sqlite')[0];
$pdo = new PDO("sqlite:$db");
$tok = $pdo->query("SELECT token FROM qr_tokens t JOIN properties p ON p.id=t.property_id WHERE p.slug='casa-lucia'")->fetchColumn();
$r = chiama("$BASE/q/$tok");
prova('Il QR porta alla soglia', $r['code'] === 302 && str_contains($r['loc'], '/benvenuto'));
$r = chiama("$BASE/qr/$tok.png");
prova('L\'immagine del QR esce', $r['code'] === 200 && str_starts_with($r['body'], "\x89PNG"), strlen($r['body']) . ' byte');

// ------------------------------------------------- entrare come un cliente
$r = chiama("$BASE/admin/clienti");
$t = token($r['body']);
$uid = (int) $pdo->query("SELECT id FROM users WHERE email='lucia@esempio.it'")->fetchColumn();
$r = chiama("$BASE/admin/entra/$uid", ['_csrf' => $t]);
prova('L\'amministratore entra come Lucia', $r['code'] === 302 && str_contains($r['loc'], '/pannello'));
$r = chiama("$BASE/pannello");
prova('Vede le guide di Lucia', str_contains($r['body'], 'Casa Lucia'));
prova('La fascia rossa avvisa dell\'impersonazione', str_contains($r['body'], 'account di un cliente'));

$pid = (int) $pdo->query("SELECT id FROM properties WHERE slug='casa-lucia'")->fetchColumn();
$r = chiama("$BASE/pannello/$pid");
prova('Il pannello della guida si apre', $r['code'] === 200 && senzaErrori($r));
prova('Il pannello conta le aperture', str_contains($r['body'], 'aperture negli ultimi 30 giorni'));
prova('Il pannello dice la sezione più letta', str_contains($r['body'], 'la sezione più letta'));
prova('C\'è l\'anteprima dal telefono', str_contains($r['body'], 'Come la vedono gli ospiti'));
prova('C\'è il bottone per vedere come un ospite', str_contains($r['body'], 'Vedi come un ospite'));
prova('La navigazione della struttura è in alto', str_contains($r['body'], '>La guida</a>'));

foreach (['lingue' => 'Le traduzioni', 'qr' => 'Il QR della casa', 'impostazioni' => 'Foto di copertina'] as $dove => $atteso) {
    $r = chiama("$BASE/pannello/$pid/$dove");
    prova("La pagina $dove si apre", $r['code'] === 200 && senzaErrori($r) && str_contains($r['body'], $atteso));
}
$sid = (int) $pdo->query("SELECT id FROM sections WHERE property_id=$pid ORDER BY position")->fetchColumn();
$r = chiama("$BASE/pannello/$pid/sezioni/$sid");
prova('L\'editor di sezione si apre', $r['code'] === 200 && senzaErrori($r));
prova('L\'editor ha l\'anteprima', str_contains($r['body'], 'Anteprima dal telefono'));

$r = chiama("$BASE/admin/esci-da-cliente", ['_csrf' => token($r['body'])]);
prova('Si torna amministratore', $r['code'] === 302);

// ------------------------------------------------- togliere i dati di esempio
$r = chiama("$BASE/admin/clienti");
$r = chiama("$BASE/admin/dati-esempio", ['_csrf' => token($r['body']), 'cosa' => 'elimina']);
prova('I clienti di esempio si eliminano', $r['code'] === 302);
$restano = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email LIKE '%@esempio.it'")->fetchColumn();
prova('Non ne resta nessuno', $restano === 0, "restano $restano");
$foto = glob($DOVE . '/app/storage/uploads/demo-*');
prova('Nemmeno le loro fotografie', count($foto) === 0, count($foto) . ' file');
$orfane = (int) $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
prova('Né le loro guide', $orfane === 0, "$orfane strutture");

$r = chiama("$BASE/admin/clienti");
$r = chiama("$BASE/admin/dati-esempio", ['_csrf' => token($r['body']), 'cosa' => 'crea']);
prova('Si possono ricreare', $r['code'] === 302);
$rifatti = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email LIKE '%@esempio.it'")->fetchColumn();
prova('Sono di nuovo tre', $rifatti === 3, "$rifatti utenti");
$media = (int) $pdo->query("SELECT COUNT(*) FROM media")->fetchColumn();
prova('Con le loro fotografie', $media >= 6, "$media immagini");

// ---------------------------------------------------- sabbia, vetro e temi
$css = chiama(str_replace('/index.php', '', $BASE) . '/assets/app.css');
prova('Il foglio di stile esce', $css['code'] === 200);
prova('La sabbia è dichiarata sulle superfici', str_contains($css['body'], "--grana:url(\"grana.png\")"));
prova('Le superfici la usano davvero', str_contains($css['body'], 'background-image: var(--grana)'));
prova('Le fotografie restano pulite', str_contains($css['body'], 'background-image: none'));
prova('I comandi restano lisci',
      str_contains($css['body'], '.btn, .icon-btn, .lang, .chips a, .chips button, .btn--go .go,')
      && !str_contains($css['body'], '.plan,\n.btn, .icon-btn'));
$grana = chiama(str_replace('/index.php', '', $BASE) . '/assets/grana.png');
prova('L\'immagine della sabbia si scarica', $grana['code'] === 200 && str_starts_with($grana['body'], "\x89PNG"),
      strlen($grana['body']) . ' byte');
prova('Le due barre sono vetro al 40%', substr_count($css['body'], 'backdrop-filter:var(--vetro-filtro)') >= 2
      && str_contains($css['body'], '--vetro:rgba(250,245,236,.40)'));
prova('Senza sfocatura le barre tornano opache', str_contains($css['body'], '@supports not ((backdrop-filter'));
prova('Il tema scuro ha i suoi valori', str_contains($css['body'], ':root[data-theme="scuro"]')
      && str_contains($css['body'], 'prefers-color-scheme: dark'));
prova('L\'accento è ridichiarato in ogni tema',
      substr_count($css['body'], '--accent:var(--terracotta)') >= 3);

$r = chiama("$BASE/");
prova('La scelta del tema si applica prima di disegnare', str_contains($r['body'], "localStorage.getItem('mhw-tema')"));
prova('C\'è l\'interruttore del tema', str_contains($r['body'], 'class="icon-btn tema"'));
prova('Porta tutte e due le icone', str_contains($r['body'], 'i-luna') && str_contains($r['body'], 'i-sole'));
$r = chiama("$BASE/g/casa-lucia");
prova('L\'interruttore c\'è anche per l\'ospite', str_contains($r['body'], 'class="icon-btn tema"'));

echo implode("\n", $esiti), "\n\n";
echo $falliti === 0
    ? count($esiti) . " controlli, tutti superati.\n"
    : "$falliti controlli falliti su " . count($esiti) . ".\n";
exit($falliti === 0 ? 0 : 1);
