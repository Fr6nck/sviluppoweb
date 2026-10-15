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
    if (!Auth::isVerified($u)) return '/conferma-email';
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
/** Un link con il codice sconto (/?codice=BENVENUTO20, /registrati?codice=…): il codice resta in sessione. */
$codiceDalLink = function (): ?array {
    if (isset($_GET['codice']) && is_string($_GET['codice'])) $_SESSION['codice_sconto'] = mb_substr(MHW\Sconti::normalizza($_GET['codice']), 0, 24);
    $c = (string) ($_SESSION['codice_sconto'] ?? '');
    if ($c === '') return null;
    try { return MHW\Sconti::valida($c, null, null); } catch (\RuntimeException) { return null; }
};

/**
 * Invita un amico: il link personale (/i/CODICE) lascia il codice in sessione e
 * porta alla landing, che mostra chi invita. Chi è già dentro e non ha ancora
 * pagato resta legato subito all'invito.
 */
$chiInvita = function (): ?array {
    $c = (string) ($_SESSION['invito'] ?? '');
    if ($c === '' || !($chi = MHW\Inviti::daCodice($c)) || !MHW\Inviti::puoInvitare($chi)) return null;
    return $chi;
};

/*
 * 7D: il link diventa una pagina (noindex) con l'anteprima sicura della guida di chi invita:
 * copertina, nome, città e i nomi delle sezioni, mai i contenuti. Con la casella «Mostra la
 * copertina…» spenta, la pagina e l'anteprima di WhatsApp sono generiche.
 */
$r->get('/i/{codice}', function (array $a) use ($guard) {
    $guard();
    $codice = mb_substr(strtoupper((string) $a['codice']), 0, 12);
    $_SESSION['invito'] = $codice;
    if (($u = Auth::user()) && $u['role'] !== 'admin' && ($acc = Auth::account())) {
        try {
            $nome = MHW\Inviti::applicaCodice($acc, $codice);
            if ($nome !== null) Support::flash('Invito di ' . $nome . ' applicato: −' . MHW\Inviti::AMICO . '% sul primo anno.');
        } catch (\RuntimeException $e) { Support::flash($e->getMessage(), 'avviso'); }
        unset($_SESSION['invito']);
        Support::redirect('/pannello');
    }
    $chi = MHW\Inviti::daCodice($codice);
    if (!$chi || !MHW\Inviti::puoInvitare($chi)) Support::redirect('/');
    $mostra = MHW\Inviti::anteprimaAccesa($chi);
    $p = $mostra ? MHW\Inviti::strutturaDi((int) $chi['id']) : null;
    $sezioni = [];
    if ($p && ($snap = MHW\Guide::published((int) $p['id']))) {
        $loc = (string) ($snap['property']['default_locale'] ?? 'it');
        foreach ($snap['sections'] as $sec) $sezioni[] = ['kind' => (string) $sec['kind'], 'titolo' => MHW\Guide::title($sec, $loc, $loc)];
    }
    View::out('pub/invito', [
        'chi' => $chi, 'nome' => explode(' ', trim((string) $chi['user_name']))[0] ?: 'Un host', 'codice' => $codice,
        'p' => $p, 'sezioni' => $sezioni, 'copertina' => $p && $p['cover_media_id'] ? Media::url((int) $p['cover_media_id']) : null,
        'esempio' => MHW\Demo::esempio(null, $p ? (int) $p['id'] : 0),
        'ogTitolo' => $p ? $p['name'] . ' usa MyHouse Welcome' : null,
        'ogTesto' => 'Con questo invito hai il ' . MHW\Inviti::AMICO . '% di sconto sul primo anno.',
        'ogImmagine' => $p && $p['cover_media_id'] ? Support::baseUrl() . '/i/' . rawurlencode($codice) . '/anteprima.jpg' : null,
    ]);
});

/* L'immagine dell'anteprima dell'invito: solo la copertina di chi invita, 1200×630, fuori dai motori. */
$r->get('/i/{codice}/anteprima.jpg', function (array $a) use ($guard) {
    $guard();
    $chi = MHW\Inviti::daCodice((string) $a['codice']);
    $p = $chi && MHW\Inviti::puoInvitare($chi) && MHW\Inviti::anteprimaAccesa($chi) ? MHW\Inviti::strutturaDi((int) $chi['id']) : null;
    $f = $p && $p['cover_media_id'] ? MHW\Anteprima::file($p, 'invito') : null;
    if (!$f) { http_response_code(404); header('X-Robots-Tag: ' . Support::ROBOTS_GUIDE); exit; }
    MHW\Anteprima::manda($f);
});

/* La home, in italiano su «/» e in inglese su «/en» (8E): stesse sezioni, testi da TestiSito nella lingua giusta. */
$home = function (string $loc) use ($guard, $codiceDalLink, $chiInvita) {
    $guard();
    MHW\LinguaSito::imposta($loc);
    $codiceSconto = $codiceDalLink();
    $invito = $chiInvita();
    MHW\Stats::funnelEvent('landing_view');
    MHW\Demo::vetrinaAutomatica();   // una volta sola: poi costa un controllo su un file
    // La demo della landing: la vetrina creata dall'amministrazione, se c'è; altrimenti quella dei clienti di esempio.
    $demo = Db::one("SELECT * FROM properties WHERE is_demo >= 1 AND status = 'published' AND archived_at IS NULL ORDER BY is_demo DESC, id");
    $copertina = $demo ? Media::url($demo['cover_media_id'] ? (int) $demo['cover_media_id'] : null) : null;
    // Chi è già dentro vede in cima le sue guide, con lo stato vero (la fascia «Ciao, …»).
    $u = Auth::user(); $mie = [];
    // L'anteprima dal vivo di Amministrazione → Testi e foto: la home come la vede un visitatore,
    // con i segnaposto (data-testo, data-foto) che lo script dell'anteprima aggiorna mentre si scrive.
    $anteprimaTesti = ($_GET['anteprima'] ?? '') === 'testi' && $u && ($u['role'] ?? '') === 'admin';
    if ($anteprimaTesti) { Auth::comeVisitatore(); $u = null; }
    if ($u && $u['role'] !== 'admin' && ($acc = Auth::account())) {
        $mie = Db::all('SELECT id, account_id, is_demo, name, city, status, wizard_step, archived_at FROM properties WHERE account_id = ? AND archived_at IS NULL ORDER BY id', [$acc['id']]);
        foreach ($mie as &$pr) $pr['online'] = $pr['status'] === 'published' && MHW\Subscriptions::propertyOnline($pr);
        unset($pr);
    }
    View::out('pub/home', [
        'offers' => Plans::offers(), 'demo' => $demo,
        'copertina' => $copertina ?: MHW\a('/assets/foto/casa.jpg'),
        'user' => $u, 'mie' => $mie, 'codiceSconto' => $codiceSconto,
        'invitoDi' => $invito && !$u ? explode(' ', trim((string) $invito['user_name']))[0] : '',
        'testimonianze' => MHW\Testimonianze::visibili(), 'seoPagina' => $loc === 'en' ? 'en' : 'home', 'anteprimaTesti' => $anteprimaTesti,
    ]);
};
$r->get('/', fn() => $home('it'));
$r->get('/en', fn() => $home('en'));

/*
 * 8A: chi completa «Anche il tuo sito» e sceglie come sentirci. Arriva con sendBeacon e il token
 * CSRF della pagina; un limite per connessione; l'account solo se chi clicca è dentro (nessun dato
 * personale dei visitatori). Risponde 204: la pagina non aspetta niente.
 */
$r->post('/sito/interesse', function () use ($guard) {
    $guard();
    if (MHW\Migrator::tableExists('interessi_sito') && RateLimit::hit('sito-interesse:' . RateLimit::ip(), 20, 3600)) {
        $voci = ['aspetto', 'google', 'dirette', 'altro'];
        $bisogni = array_values(array_intersect($voci, explode(',', (string) ($_POST['bisogni'] ?? ''))));
        $u = Auth::user();
        $acc = $u && $u['role'] !== 'admin' ? Auth::account() : null;
        Db::insert('interessi_sito', [
            'account_id' => $acc ? (int) $acc['id'] : null,
            'ha_sito' => in_array($_POST['ha_sito'] ?? '', ['si', 'no'], true) ? $_POST['ha_sito'] : '',
            'bisogni' => implode(',', $bisogni),
            'altro' => mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) ($_POST['altro'] ?? '')) ?? ''), 0, 200),
            'canale' => in_array($_POST['canale'] ?? '', ['scopri', 'whatsapp', 'email'], true) ? $_POST['canale'] : '',
            'da' => in_array($_POST['da'] ?? '', ['home', 'pannello', 'impostazioni'], true) ? $_POST['da'] : 'home',
            'created_at' => Support::now(),
        ]);
    }
    http_response_code(204); exit;
});

// Tutte le domande frequenti, aperte e con il link diretto a ognuna (/domande#id).
$r->get('/domande', function () use ($guard) {
    $guard();
    View::out('pub/domande', ['faq' => MHW\Faq::tutte(), 'seoPagina' => 'domande']);
});
$r->get('/en/questions', function () use ($guard) {
    $guard();
    MHW\LinguaSito::imposta('en');
    View::out('pub/domande', ['faq' => MHW\Faq::tutte('en'), 'seoPagina' => 'en-domande']);
});

/* 7F: le pagine per tipologia (solo italiano): problemi tipici, sezioni utili, piano consigliato, 4 domande. */
foreach (MHW\Seo::tipologie() as $chiave => $tipoP) {
    $r->get($tipoP['percorso'], function () use ($guard, $chiave, $tipoP) {
        $guard();
        MHW\Stats::funnelEvent('landing_view');
        $piano = null;
        foreach (Plans::public() as $p) if ($p['code'] === $tipoP['piano']) $piano = $p;
        $demo = Db::one("SELECT id, slug, name FROM properties WHERE is_demo >= 1 AND status = 'published' AND archived_at IS NULL ORDER BY is_demo DESC, id");
        View::out('pub/tipologia', ['tp' => $tipoP, 'chiave' => $chiave, 'piano' => $piano, 'demo' => $demo, 'user' => Auth::user(), 'seoPagina' => $chiave]);
    });
}

$r->get('/termini', fn() => View::out('pub/legal', ['doc' => 'termini', 'seoPagina' => 'termini'], 'layout/bare'));
$r->get('/privacy', fn() => View::out('pub/legal', ['doc' => 'privacy', 'seoPagina' => 'privacy'], 'layout/bare'));

// I file per motori e assistenti: si rigenerano a ogni richiesta da Amministrazione → SEO e GEO.
$fileSeo = function (string $tipo, callable $testo) use ($guard): never {
    $guard();
    header('Content-Type: ' . $tipo . '; charset=utf-8');
    echo $testo(); exit;
};
$r->get('/robots.txt', fn() => $fileSeo('text/plain', [MHW\Seo::class, 'robots']));
// 7F: la sonda degli indirizzi puliti (Impostazioni::provaIndirizzi): una rotta che non è mai un file vero.
$r->get('/indirizzi-puliti', fn() => $fileSeo('text/plain', fn() => MHW\Seo::SONDA . "\n"));
$r->get('/sitemap.xml', fn() => $fileSeo('application/xml', [MHW\Seo::class, 'sitemap']));
$r->get('/llms.txt', fn() => $fileSeo('text/plain', [MHW\Seo::class, 'llms']));

// ------------------------------------------------------------------ registrazione
$r->any('/registrati', function () use ($guard, $doveComincia, $salvaPiano, $codiceDalLink, $chiInvita) {
    $guard();
    $codiceSconto = $codiceDalLink();
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
        $vecchi = ['name' => (string) ($_POST['name'] ?? ''), 'email' => (string) ($_POST['email'] ?? ''), 'email2' => (string) ($_POST['email2'] ?? '')];
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
            // 7A: l'email due volte, per non sbagliarla (incollarla è permesso).
            if (mb_strtolower(trim($vecchi['email'])) !== mb_strtolower(trim($vecchi['email2']))) {
                throw new RuntimeException('Le due email non sono uguali: controlla di averla scritta bene.');
            }
            $u = Auth::register((string) $_POST['email'], (string) $_POST['password'], (string) $_POST['name']);
            MHW\Stats::funnelEvent('signup');
            MHW\Statistiche::evento('registrazione');   // 8H: parte dalla pagina seguente («Controlla la tua email»)
            Auth::recordConsent((int) $u['user_id']);
            Auth::login((int) $u['user_id']);
            // Arrivato da un link con il codice sconto: dopo la registrazione è già applicato all'account.
            if (($c = (string) ($_SESSION['codice_sconto'] ?? '')) !== '') {
                try { $riga = MHW\Sconti::valida($c, Auth::account(), null);
                      Db::update('accounts', ['intended_discount_code_id' => (int) $riga['id']], 'id = :aid', ['aid' => (int) $u['account_id']]); }
                catch (\RuntimeException) {}
                unset($_SESSION['codice_sconto']);
            }
            // Arrivato dal link di un amico (/i/CODICE): resta legato a chi l'ha invitato, con il suo sconto.
            if (($inv = (string) ($_SESSION['invito'] ?? '')) !== '') {
                if ($chi = MHW\Inviti::daCodice($inv)) MHW\Inviti::collega(Auth::account(), $chi);
                unset($_SESSION['invito']);
            }
            Auth::sendVerification((int) $u['user_id']);
            MHW\RateLimit::hit('verifica-attesa:' . (int) $u['user_id'], 1, 60);
            if ($pvScelto) $salvaPiano(Auth::account(), (int) $u['user_id'], $pvScelto, $qScelta);
            // 7A: la guida si comincia dopo la conferma dell'email.
            if ($scelta && !$pvScelto) $_SESSION['dopo_conferma'] = $scelta;
            Support::redirect('/conferma-email');
        } catch (\RuntimeException $e) { $err = $e->getMessage(); }
    }
    // La stessa fascia della landing: codice sconto dal link o invito di un amico.
    $invito = $chiInvita();
    View::out('auth/register', ['err' => $err, 'piano' => $piano, 'strutture' => $strutture, 'vecchi' => $vecchi,
                                'pianoScelto' => $pvScelto, 'quantita' => $qScelta, 'codiceSconto' => $codiceSconto,
                                'invitoDi' => $invito ? explode(' ', trim((string) $invito['user_name']))[0] : ''], 'layout/bare');
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
/* Il link di conferma porta dentro la procedura, già connessi (7A): il link è monouso e vale 48 ore. */
$r->get('/verifica/{token}', function (array $a) use ($doveComincia) {
    $uid = Tokens::consume($a['token'], Tokens::VERIFY);
    if ($uid) {
        Db::update('users', ['email_verified_at' => Support::now()], 'id = :uid AND email_verified_at IS NULL', ['uid' => $uid]);
        $chi = Auth::user();
        if (!$chi || (int) $chi['id'] !== $uid) { if (!Auth::isImpersonating()) Auth::login($uid); }
        Support::flash('Email confermata: ora prepari la tua guida.');
        $dopo = (string) ($_SESSION['dopo_conferma'] ?? ''); unset($_SESSION['dopo_conferma']);
        $u = Db::one('SELECT * FROM users WHERE id = ?', [$uid]);
        $dove = str_starts_with($dopo, '/piano') ? $dopo : $doveComincia($u);
        // Dentro la procedura: chi non ha ancora una struttura comincia dal nome.
        if ($dove === '/pannello' && !Db::val('SELECT p.id FROM properties p JOIN accounts a ON a.id = p.account_id WHERE a.user_id = ? AND p.archived_at IS NULL', [$uid])) $dove = '/pannello/nuova';
        Support::redirect($dove);
    }
    View::out('auth/verify', ['ok' => false], 'layout/bare');
});

/** «Controlla la tua email»: chi non ha confermato resta qui (l'amministrazione e «Entra come cliente» no). */
$r->get('/conferma-email', function () use ($doveComincia) {
    $u = Auth::requireUser();
    if (Auth::isVerified($u) || $u['role'] === 'admin') Support::redirect($doveComincia($u));
    View::out('auth/check_email', ['email' => (string) $u['email'], 'attendi' => RateLimit::attesa('verifica-attesa:' . (int) $u['id'])], 'layout/bare');
});

/** «Ho sbagliato indirizzo»: si cambia prima della conferma, e parte una conferma nuova. */
$r->post('/conferma-email/cambia', function () {
    $u = Auth::requireUser();
    if (Auth::isVerified($u)) Support::redirect('/pannello');
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $err = null;
    if (!RateLimit::hit('verify:' . $u['id'], 5, 3600)) $err = 'Ti abbiamo già scritto più volte in un\'ora: riprova più tardi.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) $err = 'Questo indirizzo email non è valido.';
    elseif ($email === mb_strtolower((string) $u['email'])) $err = 'È lo stesso indirizzo di prima: controlla anche in Spam e Promozioni.';
    elseif (Db::val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $u['id']])) $err = 'Questa email è già usata da un altro account: accedi con quella, oppure recupera la password.';
    if ($err) View::out('auth/check_email', ['email' => (string) $u['email'], 'attendi' => RateLimit::attesa('verifica-attesa:' . (int) $u['id']), 'err' => $err], 'layout/bare');
    Db::update('users', ['email' => $email], 'id = :uid', ['uid' => $u['id']]);
    Auth::audit('account.email_prima_conferma', (int) $u['id'], ['da' => $u['email']]);
    Auth::sendVerification((int) $u['id']);
    RateLimit::hit('verifica-attesa:' . (int) $u['id'], 1, 60);
    Support::flash('Fatto: ti abbiamo scritto a ' . $email . '.');
    Support::redirect('/conferma-email');
});

$r->post('/verifica/invia', function () {
    $u = Auth::requireUser();
    if (Auth::isVerified($u)) Support::redirect('/pannello');
    $torna = (string) ($_POST['torna'] ?? '');
    if (RateLimit::attesa('verifica-attesa:' . (int) $u['id']) > 0) {
        Support::flash('Aspetta qualche secondo prima di rimandarla.', 'err');
    } elseif (!RateLimit::hit('verify:' . $u['id'], 5, 3600)) {
        Support::flash('Ti abbiamo già scritto più volte: controlla anche in Spam e Promozioni, oppure riprova tra un\'ora.', 'err');
    } elseif (Auth::sendVerification((int) $u['id'])) {
        RateLimit::hit('verifica-attesa:' . (int) $u['id'], 1, 60);
        Support::flash('Ti abbiamo mandato di nuovo l\'email di conferma, a ' . $u['email'] . '.');
    } else {
        Support::flash('L\'email non è partita. Riprova tra poco.', 'err');
    }
    Support::redirect($torna === 'account' ? '/account' : '/conferma-email');
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
        $chi = Db::one('SELECT email, name FROM users WHERE id = ?', [$uid]) ?? ['email' => '', 'name' => ''];
        try { Auth::controllaPassword($pw, (string) $chi['email'], (string) $chi['name']); } catch (\RuntimeException $e) { $err = $e->getMessage(); }
        if ($err !== null) {}
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
    if (!Auth::isVerified($u) && !Auth::isImpersonating()) Support::redirect('/conferma-email');
    $acc = Auth::account();
    $attivo = Subscriptions::active((int) $acc['id']);
    // Chi ha già un abbonamento che si può cambiare va a «Cambia piano» (6H); ?passa=plus porta dritto alla conferma.
    if ($attivo && $_SERVER['REQUEST_METHOD'] === 'GET' && MHW\CambioAbbonamento::stato((int) $acc['id'])['motivo'] === '') {
        $passa = preg_replace('/[^a-z0-9_-]/', '', (string) ($_GET['passa'] ?? ''));
        Support::redirect($passa !== '' && MHW\CambioAbbonamento::versioneDi($passa) ? '/account/piano/conferma?piano=' . $passa : '/account/piano');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($attivo) {
            Support::flash('Hai già un abbonamento attivo: il piano si cambia da Account & Fatturazione.', 'err');
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
        // 7B: «Passa a …» dal passo Pubblica torna lì.
        if (preg_match('#^/pannello/\d+/procedura/pubblica$#', (string) ($_POST['torna'] ?? ''))) Support::redirect((string) $_POST['torna']);
        $ha = Db::val('SELECT id FROM properties WHERE account_id = ?', [$acc['id']]);
        Support::redirect($ha ? '/pannello' : '/pannello/nuova');
    }
    View::out('pub/plan', [
        'offers' => Plans::offers(), 'scelto' => (int) ($acc['intended_package_version_id'] ?? 0),
        'preselezione' => (int) ($_GET['piano'] ?? 0), 'attivo' => $attivo, 'nav' => 'account',
        'strutture' => (int) ($_GET['strutture'] ?? 0) ?: (int) ($acc['intended_quantity'] ?? 0),
        'acc' => $acc, 'pvScelto' => $acc['intended_package_version_id'] ? Plans::currentVersion((int) $acc['intended_package_version_id']) : null,
    ]);
});

// ------------------------------------------------------------------ pagamento
$r->get('/pagamento/ok', function () {
    Auth::requireUser();
    $acc = Auth::account();
    $o = Db::one('SELECT * FROM orders WHERE id = ? AND account_id = ?', [(int) ($_GET['order'] ?? 0), $acc['id']]);
    if (!$o) Support::redirect('/pannello');
    // Il ritorno dal browser non prova nulla: la pagina aspetta il webhook firmato.
    // 8H: l'obiettivo «pagamento» una volta per ordine, con il nome del piano (mai importi).
    $vistiS = (array) ($_SESSION['mhw_stat_pagati'] ?? []);
    if (!in_array((int) $o['id'], $vistiS, true)) {
        $_SESSION['mhw_stat_pagati'] = array_slice(array_merge($vistiS, [(int) $o['id']]), -20);
        MHW\Statistiche::evento('pagamento', (string) (Plans::version((int) $o['package_version_id'])['name'] ?? ''));
    }
    View::out('pub/paid', ['order' => $o, 'piano' => ($o['kind'] ?? 'new') === 'change' ? Plans::version((int) $o['package_version_id']) : null]);
});

$r->get('/pagamento/stato', function () {
    Auth::requireUser();
    $acc = Auth::account();
    $o = Db::one('SELECT * FROM orders WHERE id = ? AND account_id = ?', [(int) ($_GET['order'] ?? 0), $acc['id']]);
    if (!$o) Support::json(['stato' => 'sconosciuto'], 404);
    $p = $o['property_id'] ? Db::one('SELECT slug, status FROM properties WHERE id = ?', [$o['property_id']]) : null;
    Support::json(['stato' => $o['status'], 'pubblicata' => $p && $p['status'] === 'published', 'applicato' => !empty($o['applied_at']),
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
        // pt-BR trova il portoghese del Brasile, pt-PT (o pt) quello del Portogallo.
        if (($l = I18n::dalTelefono($parte, $snap['locales'])) !== null) return [$l, false];
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
$telaio = function (array $snap, string $loc, string $base, bool $anteprima = false, string $ogTitolo = 'og_guide_title'): array {
    $pr = $snap['property'];
    // 8B: i meta dell'anteprima del link, nella lingua principale della guida.
    $principale = (string) ($pr['default_locale'] ?? 'it');
    $og = ['titolo' => I18n::t($principale, $ogTitolo, (string) $pr['name']), 'testo' => I18n::t($principale, 'og_guide_desc'),
           'immagine' => Support::baseUrl() . '/g/' . rawurlencode((string) $pr['slug']) . '/anteprima.jpg'];
    return ['snap' => $snap, 'loc' => $loc, 'base' => $base, 'anteprima' => $anteprima, 'og' => $og,
            'paletteCss' => Palette::css($pr['palette']), 'tema' => Palette::themeFor($pr['text_tone'])];
};

$r->get('/q/{token}', function (array $a) use ($nonDisponibile) {
    $t = Db::one('SELECT * FROM qr_tokens WHERE token = ?', [$a['token']]);
    // 7B: i QR delle vecchie camere già stampati aprono la guida della struttura (la tabella resta, non si scrive più).
    if (!$t && MHW\Migrator::tableExists('room_variants') && ($v = Db::one('SELECT property_id FROM room_variants WHERE token = ?', [$a['token']]))) {
        $p = Db::one('SELECT slug FROM properties WHERE id = ?', [$v['property_id']]);
        if ($p) { Guide::track((int) $v['property_id'], 'qr_open', null, '', 'qr'); Support::redirect('/g/' . $p['slug'] . '/benvenuto'); }
    }
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
    [$g, $snap, $loc] = $guida($a['slug']);
    // 7C: le stelle senza JavaScript arrivano qui con ?voto=N (con JavaScript, da /voto/N): solo un conteggio anonimo.
    $voto = (int) ($_GET['voto'] ?? 0);
    if ($voto >= 1 && $voto <= 5) Guide::track((int) $g['property']['id'], 'rating_' . $voto, null, $loc);
    View::out('guest/farewell', ['voto' => $voto >= 1 && $voto <= 5 ? $voto : 0] + $telaio($snap, $loc, Support::url('/g/' . $a['slug'])), 'layout/full');
});
/* 8C: la pagina delle recensioni, il link del messaggio di fine soggiorno. Le stelle senza JavaScript tornano qui
   con ?voto=N: lo stesso conteggio anonimo della pagina di saluto. Registrata prima di /g/{slug}/{sid}. */
$r->get('/g/{slug}/recensione', function (array $a) use ($guida, $telaio) {
    [$g, $snap, $loc] = $guida($a['slug']);
    $voto = (int) ($_GET['voto'] ?? 0);
    if ($voto >= 1 && $voto <= 5) Guide::track((int) $g['property']['id'], 'rating_' . $voto, null, $loc);
    View::out('guest/review', ['voto' => $voto >= 1 && $voto <= 5 ? $voto : 0]
        + $telaio($snap, $loc, Support::url('/g/' . $a['slug']), false, 'og_review_title'), 'layout/guest');
});
$r->get('/g/{slug}/voto/{n}', function (array $a) use ($guida) {
    [$g, , $loc] = $guida($a['slug']);
    $n = (int) $a['n'];
    if ($n >= 1 && $n <= 5) Guide::track((int) $g['property']['id'], 'rating_' . $n, null, $loc);
    http_response_code(204); exit;
});

/* 8B: l'immagine dell'anteprima del link della guida, 1200×630, dalla copertina pubblicata, con la sfumatura e
   il marchio. Registrata prima di /g/{slug}/{sid}, che altrimenti la prenderebbe. Fuori dai motori (index.php). */
$r->get('/g/{slug}/anteprima.jpg', function (array $a) use ($guida) {
    [$g, $snap] = $guida($a['slug']);
    $pr = $snap['property'];
    $f = MHW\Anteprima::file(['id' => (int) $g['property']['id'], 'cover_media_id' => $pr['cover_id'] ?? null, 'name' => (string) $pr['name'], 'palette' => (string) $pr['palette']], 'guida');
    if (!$f) { http_response_code(404); exit; }
    MHW\Anteprima::manda($f);
});

// «Aggiungi al calendario» di un evento (6G): il file .ics, dall'istantanea pubblicata.
$r->get('/g/{slug}/evento/{rid}.ics', function (array $a) use ($guida, $nonDisponibile) {
    [$g, $snap, $loc] = $guida($a['slug']);
    $riga = null;
    foreach ($snap['sections'] as $s) {
        if (($s['kind'] ?? '') !== 'events') continue;
        foreach (Guide::rows($s, 'events', $loc, $snap['property']['default_locale']) as $ev) if ((string) $ev['id'] === $a['rid']) $riga = $ev;
    }
    $ics = $riga ? MHW\Eventi::ics($riga, 'mhw-' . (int) $g['property']['id'] . '-' . $riga['id'] . '@' . (parse_url(Support::baseUrl(), PHP_URL_HOST) ?: 'localhost'), Support::url('/g/' . $a['slug'])) : '';
    if ($ics === '') $nonDisponibile($loc, false);
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="evento.ics"');
    echo $ics;
});

$r->get('/g/{slug}/{sid}', function (array $a) use ($guida, $telaio, $nonDisponibile) {
    [$g, $snap, $loc] = $guida($a['slug']);
    $sec = null;
    foreach ($snap['sections'] as $s) if ((string) $s['id'] === $a['sid']) $sec = $s;
    if (!$sec) $nonDisponibile($loc, false);
    Guide::track((int) $g['property']['id'], 'section_view', (int) $sec['id'], $loc);
    View::out('guest/section', ['sec' => $sec] + $telaio($snap, $loc, Support::url('/g/' . $a['slug'])), 'layout/guest');
});

/* 7B: i vecchi link delle camere (/g/{slug}/c/{token}…) aprono la stessa pagina della guida, senza camera. */
$r->get('/g/{slug}/c/{tok}', fn(array $a) => Support::redirect('/g/' . rawurlencode($a['slug'])));
$r->get('/g/{slug}/c/{tok}/{pagina}', fn(array $a) => Support::redirect('/g/' . rawurlencode($a['slug']) . '/' . rawurlencode($a['pagina'])));

// I file caricati su disco li serve PHP, così restano fuori dalla cartella pubblica.
// (Con S3 gli URL puntano direttamente al bucket o al CDN.)
$r->get('/media/{file}', function (array $a) {
    $name = basename($a['file']);
    $m = Db::one('SELECT * FROM media WHERE filename = ?', [$name]);
    $path = Config::get('uploads_dir') . '/' . $name;
    if (!$m && is_file($path) && preg_match('/^(seo|testimonianze|sito)-\d{4}-[0-9a-f]{32}\.(jpg|jpeg|png|webp)$/', $name)) {
        // Un'immagine caricata dall'amministrazione (Media::storeFreeImage): nessuna riga in media, nome casuale.
        $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][strtolower(pathinfo($name, PATHINFO_EXTENSION))];
        $m = ['kind' => 'image', 'mime' => $mime, 'original_name' => $name];
    }
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
    View::out('pub/email_stop', ['tipo' => MHW\Richiami::TIPI[$riga['kind']] ?? 'questa email', 'fatto' => $fatto], 'layout/bare');
});

/* «Cosa ti ha fermato?» (7A): il token dell'email fa da firma, non serve accedere. Il clic salva il motivo. */
$r->get('/parere/{token}/{motivo}', function (array $a) {
    $riga = MHW\Richiami::daToken((string) $a['token']);
    $motivo = (string) $a['motivo'];
    if (!$riga || $riga['kind'] !== 'parere' || !isset(MHW\Richiami::MOTIVI[$motivo])) { http_response_code(404); View::out('pub/404', []); }
    MHW\Richiami::rispondi((int) $riga['account_id'], $motivo, null, true);
    $c = Db::one('SELECT * FROM feedback WHERE account_id = ?', [$riga['account_id']]);
    View::out('pub/parere', ['token' => (string) $a['token'], 'motivo' => (string) ($c['motivo'] ?? $motivo),
                             'testo' => (string) ($c['testo'] ?? ''), 'salvato' => false], 'layout/bare');
});
$r->post('/parere/{token}', function (array $a) {
    $riga = MHW\Richiami::daToken((string) $a['token']);
    if (!$riga || $riga['kind'] !== 'parere') { http_response_code(404); View::out('pub/404', []); }
    if (!RateLimit::hit('parere:' . $riga['account_id'], 10, 3600)) { http_response_code(429); exit('Troppi invii: riprova più tardi.'); }
    $motivo = (string) ($_POST['motivo'] ?? '');
    $c = Db::one('SELECT motivo FROM feedback WHERE account_id = ?', [$riga['account_id']]);
    MHW\Richiami::rispondi((int) $riga['account_id'], isset(MHW\Richiami::MOTIVI[$motivo]) ? $motivo : (string) ($c['motivo'] ?? 'altro'), (string) ($_POST['testo'] ?? ''));
    View::out('pub/parere', ['token' => (string) $a['token'], 'motivo' => $motivo, 'testo' => '', 'salvato' => true], 'layout/bare');
});

/* Per un cron di cPanel: wget -q -O- https://…/cron/IL_TOKEN (token in MHW_CRON_TOKEN). */
$r->get('/cron/{token}', function (array $a) {
    $giusto = (string) Config::get('cron_token');
    if ($giusto === '' || !hash_equals($giusto, (string) $a['token'])) { http_response_code(404); View::out('pub/404', []); }
    Support::json(['ok' => true, 'email' => MHW\Richiami::esegui()]);
});
