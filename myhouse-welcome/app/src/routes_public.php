<?php
/** Rotte pubbliche e dell'ospite. $r è il Router creato in public/index.php. */

use MHW\{Auth, Billing, Config, Db, Demo, Guide, I18n, Installer, Log, Media, Palette, Plans, Qr, RateLimit,
         Stripe, Subscriptions, Support, Tokens, View};

$guard = function (): void {
    if (!Installer::installed()) Support::redirect('/installa');
};

/**
 * Il piano che l'host ha in mente (prima di pagare): versione in vendita e,
 * per Portfolio, numero di strutture. Lo usano la registrazione e /piano.
 */
$salvaPiano = function (array $acc, int $userId, array $pv, int $q): void {
    Db::update('accounts', ['intended_package_version_id' => $pv['id'], 'intended_quantity' => $q], 'id = :aid', ['aid' => $acc['id']]);
    MHW\Entitlements::forget((int) $acc['id']);
    Auth::audit('plan.intended', $userId, ['package_version_id' => (int) $pv['id'], 'quantity' => $q]);
};

/** Dove porta "Crea la tua guida" per chi è già dentro: niente seconda registrazione. */
$doveComincia = function (array $u): string {
    if ($u['role'] === 'admin') return '/admin';
    $acc = Db::one('SELECT * FROM accounts WHERE user_id = ?', [$u['id']]);
    if (!$acc['intended_package_version_id'] && !Subscriptions::active((int) $acc['id'])) return '/piano';
    return '/pannello';
};

// ------------------------------------------------------------------ installazione
$r->any('/installa', function () {
    if (Installer::installed()) Support::redirect('/');
    $err = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            Installer::install(trim((string) $_POST['email']), (string) $_POST['password']);
            $msg = 'Installazione completata.';
            if (!empty($_POST['esempi'])) {
                $creati = Demo::popola();
                $msg .= ' Creati ' . count($creati) . ' clienti di esempio (password: ' . Demo::PASSWORD
                      . '). Toglili prima di aprire al pubblico.';
            }
            Support::flash($msg);
            Auth::attempt(trim((string) $_POST['email']), (string) $_POST['password']);
            Support::redirect('/admin');
        } catch (\Throwable $e) {
            $err = $e instanceof \RuntimeException && !($e instanceof \PDOException) ? $e->getMessage()
                 : 'Installazione non riuscita (codice ' . Log::exception($e, 'installa') . ').';
        }
    }
    View::out('pub/install', ['err' => $err], 'layout/bare');
});

// ------------------------------------------------------------------------ landing
$r->get('/', function () use ($guard) {
    $guard();
    MHW\Stats::funnelEvent('landing_view');
    // La demo della landing: la vetrina creata dall'amministrazione, se c'è; altrimenti quella dei clienti di esempio.
    $demo = Db::one("SELECT * FROM properties WHERE is_demo >= 1 AND status = 'published' AND archived_at IS NULL ORDER BY is_demo DESC, id");
    $copertina = $demo ? Media::url($demo['cover_media_id'] ? (int) $demo['cover_media_id'] : null) : null;
    // Chi è già dentro vede in cima le sue guide, con lo stato vero (la fascia «Ciao, …»).
    $u = Auth::user(); $mie = [];
    if ($u && $u['role'] !== 'admin' && ($acc = Auth::account())) {
        $mie = Db::all('SELECT id, account_id, is_demo, name, city, status, wizard_step, archived_at FROM properties WHERE account_id = ? AND archived_at IS NULL ORDER BY id', [$acc['id']]);
        foreach ($mie as &$pr) $pr['online'] = $pr['status'] === 'published' && MHW\Subscriptions::propertyOnline($pr);
        unset($pr);
    }
    View::out('pub/home', [
        'offers' => Plans::offers(), 'demo' => $demo,
        'copertina' => $copertina ?: MHW\a('/assets/foto/casa.jpg'),
        'user' => $u, 'mie' => $mie,
        'testimonianze' => MHW\Testimonianze::visibili(),
    ]);
});

$r->get('/termini', fn() => View::out('pub/legal', ['doc' => 'termini'], 'layout/bare'));
$r->get('/privacy', fn() => View::out('pub/legal', ['doc' => 'privacy'], 'layout/bare'));

// ------------------------------------------------------------------ registrazione
$r->any('/registrati', function () use ($guard, $doveComincia, $salvaPiano) {
    $guard();
    $piano = (int) ($_GET['piano'] ?? $_POST['piano'] ?? 0);
    // Il numero di strutture scelto (Portfolio) viaggia con il piano fino al checkout.
    $strutture = (int) ($_GET['strutture'] ?? $_POST['strutture'] ?? 0);
    $scelta = $piano ? '/piano?piano=' . $piano . ($strutture > 0 ? '&strutture=' . $strutture : '') : '';
    // Il piano arrivato dalla landing, se è in vendita e la quantità va bene: si
    // mostra in alto e, a registrazione fatta, si salva senza ripassare da /piano.
    $pvScelto = $piano ? Plans::currentVersion($piano) : null;
    $qScelta = $pvScelto ? Plans::quantity($pvScelto, Plans::perProperty($pvScelto) ? ($strutture ?: null) : 1) : null;
    if ($qScelta === null) $pvScelto = null;
    // Chi è già dentro non si registra una seconda volta.
    if ($u = Auth::user()) Support::redirect($scelta ?: $doveComincia($u));

    $err = null; $vecchi = ['name' => '', 'email' => ''];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $vecchi = ['name' => (string) ($_POST['name'] ?? ''), 'email' => (string) ($_POST['email'] ?? '')];
        try {
            if (!RateLimit::hit('register:' . RateLimit::ip(), 10, 3600)) {
                throw new RuntimeException('Troppe registrazioni da questa connessione. Riprova tra un\'ora.');
            }
            // Un solo consenso esplicito, sui Termini. La privacy si prende in visione:
            // la riga sotto il bottone lo dichiara e recordConsent ne salva versione e
            // data. Formulazione da far verificare al consulente privacy.
            if (empty($_POST['termini'])) {
                throw new RuntimeException('Per creare l\'account devi accettare i Termini e condizioni.');
            }
            $u = Auth::register((string) $_POST['email'], (string) $_POST['password'], (string) $_POST['name']);
            MHW\Stats::funnelEvent('signup');
            Auth::recordConsent((int) $u['user_id']);
            Auth::login((int) $u['user_id']);
            Auth::sendVerification((int) $u['user_id']);
            Support::flash('Account creato. Ti abbiamo scritto per confermare l\'email: intanto puoi preparare la guida.');
            if ($pvScelto) {
                $salvaPiano(Auth::account(), (int) $u['user_id'], $pvScelto, $qScelta);
                Support::redirect('/pannello/nuova');
            }
            Support::redirect($scelta ?: '/piano');
        } catch (\RuntimeException $e) { $err = $e->getMessage(); }
    }
    View::out('auth/register', ['err' => $err, 'piano' => $piano, 'strutture' => $strutture, 'vecchi' => $vecchi,
                                'pianoScelto' => $pvScelto, 'quantita' => $qScelta], 'layout/bare');
});

$r->any('/accedi', function () use ($guard, $doveComincia) {
    $guard();
    if ($u = Auth::user()) Support::redirect($doveComincia($u));
    $err = null; $email = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = (string) ($_POST['email'] ?? '');
        $perEmail = RateLimit::key('login', $email);
        if (!RateLimit::hit('login-ip:' . RateLimit::ip(), 40, 900) || !RateLimit::hit($perEmail, 8, 900)) {
            $err = 'Troppi tentativi. Aspetta un quarto d\'ora, oppure recupera la password.';
        } elseif (Auth::attempt($email, (string) ($_POST['password'] ?? ''))) {
            RateLimit::clear($perEmail);
            Support::redirect($doveComincia(Auth::user()));
        } else {
            $err = 'Email o password non corrispondono.';
        }
    }
    View::out('auth/login', ['err' => $err, 'email' => $email], 'layout/bare');
});

$r->post('/esci', function () { Auth::logout(); Support::redirect('/'); });

// ------------------------------------------------------------ verifica email
$r->get('/verifica/{token}', function (array $a) {
    $uid = Tokens::consume($a['token'], Tokens::VERIFY);
    if ($uid) {
        Db::update('users', ['email_verified_at' => Support::now()], 'id = :uid AND email_verified_at IS NULL', ['uid' => $uid]);
        Support::flash('Email confermata. Ora puoi pubblicare la tua guida quando vuoi.');
        Support::redirect(Auth::user() ? '/pannello' : '/accedi');
    }
    View::out('auth/verify', ['ok' => false], 'layout/bare');
});

$r->post('/verifica/invia', function () {
    $u = Auth::requireUser();
    if (Auth::isVerified($u)) Support::redirect('/pannello');
    if (!RateLimit::hit('verify:' . $u['id'], 5, 3600)) {
        Support::flash('Ti abbiamo già scritto più volte: controlla anche nella posta indesiderata.', 'err');
    } elseif (Auth::sendVerification((int) $u['id'])) {
        Support::flash('Ti abbiamo mandato di nuovo l\'email di conferma, a ' . $u['email'] . '.');
    } else {
        Support::flash('L\'email non è partita. Riprova tra poco.', 'err');
    }
    Support::redirect((string) ($_POST['torna'] ?? '') === 'account' ? '/account' : '/pannello');
});

// --------------------------------------------------------- recupero password
$r->any('/password/dimenticata', function () use ($guard) {
    $guard();
    $inviata = false; $err = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = (string) ($_POST['email'] ?? '');
        if (!RateLimit::hit('reset-ip:' . RateLimit::ip(), 10, 3600) || !RateLimit::hit(RateLimit::key('reset', $email), 3, 3600)) {
            $err = 'Troppe richieste. Riprova tra un\'ora.';
        } else {
            Auth::sendPasswordReset($email);
            // Stessa risposta che l'account esista o no: nessuno scopre chi è iscritto.
            $inviata = true;
        }
    }
    View::out('auth/forgot', ['inviata' => $inviata, 'err' => $err], 'layout/bare');
});

$r->any('/password/nuova/{token}', function (array $a) {
    $uid = Tokens::peek($a['token'], Tokens::RESET);
    if (!$uid) View::out('auth/reset', ['valido' => false, 'err' => null], 'layout/bare');
    $err = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pw = (string) ($_POST['password'] ?? '');
        if (mb_strlen($pw) < 8) $err = 'La password deve avere almeno 8 caratteri.';
        elseif ($pw !== (string) ($_POST['password2'] ?? '')) $err = 'Le due password non coincidono.';
        elseif (Tokens::consume($a['token'], Tokens::RESET) !== $uid) $err = 'Il link è già stato usato.';
        else {
            Db::update('users', ['password_hash' => password_hash($pw, PASSWORD_DEFAULT)], 'id = :uid', ['uid' => $uid]);
            // Chi apre il link dalla propria casella dimostra anche di possederla.
            Db::update('users', ['email_verified_at' => Support::now()], 'id = :uid AND email_verified_at IS NULL', ['uid' => $uid]);
            Db::insert('audit_log', ['actor_user_id' => $uid, 'target_user_id' => $uid, 'action' => 'password.reset',
                                     'meta' => '', 'created_at' => Support::now()]);
            Support::flash('Password cambiata. Accedi con quella nuova.');
            Support::redirect('/accedi');
        }
    }
    View::out('auth/reset', ['valido' => true, 'err' => $err, 'token' => $a['token']], 'layout/bare');
});

// ------------------------------------------------------------- scelta del piano
$r->any('/piano', function () use ($salvaPiano) {
    $u = Auth::requireUser();
    if ($u['role'] === 'admin') Support::redirect('/admin');
    $acc = Auth::account();
    $attivo = Subscriptions::active((int) $acc['id']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($attivo) {
            Support::flash('Hai già un abbonamento attivo: il piano si cambia da Account e fatturazione.', 'err');
            Support::redirect('/account');
        }
        $pv = Plans::currentVersion((int) ($_POST['pv'] ?? 0));
        if (!$pv) { Support::flash('Scegli uno dei piani in elenco.', 'err'); Support::redirect('/piano'); }
        $q = Plans::quantity($pv, Plans::perProperty($pv) ? (string) ($_POST['strutture'] ?? '') : 1);
        if ($q === null) {
            Support::flash('Indica un numero intero di strutture tra ' . (int) $pv['min_quantity'] . ' e ' . (int) $pv['max_quantity'] . '.', 'err');
            Support::redirect('/piano?piano=' . (int) $pv['id']);
        }
        $salvaPiano($acc, (int) $u['id'], $pv, $q);
        Support::flash('Piano ' . $pv['name'] . (Plans::perProperty($pv) ? ' per ' . $q . ' strutture' : '') . ' scelto. Non paghi niente adesso: solo quando pubblichi.');
        $ha = Db::val('SELECT id FROM properties WHERE account_id = ?', [$acc['id']]);
        Support::redirect($ha ? '/pannello' : '/pannello/nuova');
    }
    View::out('pub/plan', [
        'offers' => Plans::offers(), 'scelto' => (int) ($acc['intended_package_version_id'] ?? 0),
        'preselezione' => (int) ($_GET['piano'] ?? 0), 'attivo' => $attivo, 'nav' => 'account',
        'strutture' => (int) ($_GET['strutture'] ?? 0) ?: (int) ($acc['intended_quantity'] ?? 0),
    ]);
});

// ------------------------------------------------------------------ pagamento
$r->get('/pagamento/ok', function () {
    Auth::requireUser();
    $acc = Auth::account();
    $o = Db::one('SELECT * FROM orders WHERE id = ? AND account_id = ?', [(int) ($_GET['order'] ?? 0), $acc['id']]);
    if (!$o) Support::redirect('/pannello');
    // Il ritorno dal browser non prova nulla: la pagina aspetta il webhook firmato.
    View::out('pub/paid', ['order' => $o]);
});

$r->get('/pagamento/stato', function () {
    Auth::requireUser();
    $acc = Auth::account();
    $o = Db::one('SELECT * FROM orders WHERE id = ? AND account_id = ?', [(int) ($_GET['order'] ?? 0), $acc['id']]);
    if (!$o) Support::json(['stato' => 'sconosciuto'], 404);
    $p = $o['property_id'] ? Db::one('SELECT slug, status FROM properties WHERE id = ?', [$o['property_id']]) : null;
    Support::json(['stato' => $o['status'], 'pubblicata' => $p && $p['status'] === 'published',
                   'guida' => $p ? Support::url('/g/' . $p['slug']) : null]);
});

$r->get('/pagamento/annullato', function () {
    Auth::requireUser();
    $acc = Auth::account();
    Db::run("UPDATE orders SET status = 'canceled', updated_at = ? WHERE id = ? AND account_id = ? AND status = 'pending'",
            [Support::now(), (int) ($_GET['order'] ?? 0), $acc['id']]);
    Support::flash('Pagamento annullato: non ti è stato addebitato niente e la guida resta in bozza.', 'err');
    Support::redirect('/pannello');
});

$r->post('/webhook/stripe', function () {
    $payload = (string) file_get_contents('php://input');
    $secret = (string) Config::get('stripe')['webhook_secret'];
    $sig = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
    if ($secret === '' || !Stripe::verifySignature($payload, $sig, $secret)) {
        http_response_code(400); exit('firma non valida');
    }
    $event = json_decode($payload, true);
    if (!is_array($event)) { http_response_code(400); exit('payload illeggibile'); }
    try {
        $esito = Billing::handleEvent($event);
        header('Content-Type: text/plain'); echo $esito;
    } catch (\Throwable $e) {
        // Un 500 fa ritentare Stripe: l'evento non è stato segnato come elaborato.
        Log::exception($e, 'webhook ' . ($event['type'] ?? '?'));
        http_response_code(500); echo 'errore, riprovare';
    }
});

// ---------------------------------------------------------------- guida ospite

/** La lingua dell'ospite: quella chiesta, poi quella del telefono, poi quella della casa. */
$linguaOspite = function (array $snap): array {
    $chiesta = (string) ($_GET['l'] ?? '');
    if (in_array($chiesta, $snap['locales'], true)) return [$chiesta, true];
    foreach (explode(',', (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $parte) {
        $l = strtolower(substr(trim($parte), 0, 2));
        if (in_array($l, $snap['locales'], true)) return [$l, false];
    }
    return [$snap['property']['default_locale'], false];
};

$nonDisponibile = function (string $loc, bool $esiste): never {
    http_response_code(404);
    View::out('guest/unavailable', ['loc' => $loc, 'esiste' => $esiste, 'title' => I18n::t($loc, $esiste ? 'unavailable' : 'not_found')], 'layout/guest');
};

$guida = function (string $slug) use ($linguaOspite, $nonDisponibile): array {
    $g = Guide::bySlug($slug);
    if (!$g) $nonDisponibile('it', false);
    [$loc, $scelta] = $linguaOspite($g['snapshot']);
    if (!$g['online']) $nonDisponibile($loc, true);
    return [$g, $g['snapshot'], $loc, $scelta];
};

/** Le variabili che ogni pagina ospite passa al telaio: palette, tema, collegamenti. */
$telaio = function (array $snap, string $loc, string $base, bool $anteprima = false): array {
    $pr = $snap['property'];
    return ['snap' => $snap, 'loc' => $loc, 'base' => $base, 'anteprima' => $anteprima,
            'paletteCss' => Palette::css($pr['palette']), 'tema' => Palette::themeFor($pr['text_tone'])];
};

$r->get('/q/{token}', function (array $a) use ($nonDisponibile) {
    $t = Db::one('SELECT * FROM qr_tokens WHERE token = ?', [$a['token']]);
    if (!$t) $nonDisponibile('it', false);
    $p = Db::one('SELECT slug FROM properties WHERE id = ?', [$t['property_id']]);
    Db::run('UPDATE qr_tokens SET scans = scans + 1 WHERE id = ?', [$t['id']]);
    Guide::track((int) $t['property_id'], 'qr_open', null, '', 'qr');
    Support::redirect('/g/' . $p['slug'] . '/benvenuto');         // il token non cambia mai, lo slug sì
});

$r->get('/g/{slug}', function (array $a) use ($guida, $telaio) {
    [$g, $snap, $loc, $scelta] = $guida($a['slug']);
    Guide::track((int) $g['property']['id'], 'guide_view', null, $loc);
    if ($scelta && $loc !== $snap['property']['default_locale']) Guide::track((int) $g['property']['id'], 'language_selected', null, $loc);
    View::out('guest/guide', $telaio($snap, $loc, Support::url('/g/' . $a['slug'])), 'layout/guest');
});

$r->get('/g/{slug}/benvenuto', function (array $a) use ($guida, $telaio) {
    [, $snap, $loc] = $guida($a['slug']);
    View::out('guest/splash', $telaio($snap, $loc, Support::url('/g/' . $a['slug'])), 'layout/full');
});

$r->get('/g/{slug}/commiato', function (array $a) use ($guida, $telaio) {
    [, $snap, $loc] = $guida($a['slug']);
    View::out('guest/farewell', $telaio($snap, $loc, Support::url('/g/' . $a['slug'])), 'layout/full');
});

$r->get('/g/{slug}/{sid}', function (array $a) use ($guida, $telaio, $nonDisponibile) {
    [$g, $snap, $loc] = $guida($a['slug']);
    $sec = null;
    foreach ($snap['sections'] as $s) if ((string) $s['id'] === $a['sid']) $sec = $s;
    if (!$sec) $nonDisponibile($loc, false);
    Guide::track((int) $g['property']['id'], 'section_view', (int) $sec['id'], $loc);
    View::out('guest/section', ['sec' => $sec] + $telaio($snap, $loc, Support::url('/g/' . $a['slug'])), 'layout/guest');
});

// I file caricati su disco li serve PHP, così restano fuori dalla cartella pubblica.
// (Con S3 gli URL puntano direttamente al bucket o al CDN.)
$r->get('/media/{file}', function (array $a) {
    $name = basename($a['file']);
    $m = Db::one('SELECT * FROM media WHERE filename = ?', [$name]);
    $path = Config::get('uploads_dir') . '/' . $name;
    if (!$m || !is_file($path)) { http_response_code(404); exit; }
    header_remove('Cache-Control');
    header('Content-Type: ' . ($m['kind'] === 'pdf' ? 'application/pdf' : $m['mime']));
    header('Cache-Control: public, max-age=31536000, immutable');
    header('X-Content-Type-Options: nosniff');
    if ($m['kind'] === 'pdf') {
        header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '-', $m['original_name'] ?: 'documento.pdf') . '"');
        header("Content-Security-Policy: sandbox; default-src 'none'");
    }
    header('Content-Length: ' . filesize($path));
    readfile($path);
});

$r->get('/qr/{token}.png', function (array $a) {
    $t = Db::one('SELECT * FROM qr_tokens WHERE token = ?', [$a['token']]);
    if (!$t) { http_response_code(404); exit; }
    header('Content-Type: image/png');
    echo Qr::png(Support::baseUrl() . '/q/' . $t['token'], 8, 4, 640);
});

// ------------------------------------------------------- email di richiamo
/* «Non mandarmene più»: il link arriva nell'email. Si conferma con un bottone,
   così i programmi che aprono i link in anteprima non disiscrivono nessuno. */
$r->any('/email/stop/{token}', function (array $a) {
    $riga = MHW\Richiami::daToken((string) $a['token']);
    if (!$riga) { http_response_code(404); View::out('pub/404', []); }
    $fatto = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { MHW\Richiami::smetti((int) $riga['account_id'], (string) $riga['kind']); $fatto = true; }
    View::out('pub/email_stop', ['tipo' => MHW\Richiami::TIPI[$riga['kind']] ?? 'email', 'fatto' => $fatto], 'layout/bare');
});

/* Per un cron di cPanel: wget -q -O- https://…/cron/IL_TOKEN (token in MHW_CRON_TOKEN). */
$r->get('/cron/{token}', function (array $a) {
    $giusto = (string) Config::get('cron_token');
    if ($giusto === '' || !hash_equals($giusto, (string) $a['token'])) { http_response_code(404); View::out('pub/404', []); }
    Support::json(['ok' => true, 'email' => MHW\Richiami::esegui()]);
});
