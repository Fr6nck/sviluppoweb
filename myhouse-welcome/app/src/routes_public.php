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
    // 9N · 10 bis: arrivato da un'offerta su misura (/offerta/{token}): dopo l'accesso o la registrazione si torna lì.
    if (!empty($_SESSION['offerta_dopo']) && $u['role'] === 'host') return '/offerta/' . $_SESSION['offerta_dopo'];
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
 * 9K/9L · I dati del questionario «Qual è il piano giusto per te?» (pub/_questionario.php), gli stessi per le pagine per
 * tipologia, la finestra della home e /piano: i piani, le risposte (senza JavaScript arrivano nell'indirizzo), le strutture
 * del Portfolio e gli sconti di chi guarda (l'account se è dentro, l'invito di un amico se è arrivato da un link).
 */
$datiQuestionario = function () use ($chiInvita): array {
    $piani = [];
    foreach (Plans::public() as $p) $piani[$p['code']] = $p;
    $risposte = [];
    foreach (['d1', 'd2', 'd3'] as $k) $risposte[$k] = in_array($_GET[$k] ?? '', ['si', 'no'], true) ? $_GET[$k] : '';
    $pf = $piani['portfolio'] ?? null;
    $strutture = $pf ? max(2, (int) $pf['min_quantity'], min((int) $pf['max_quantity'] ?: 10, (int) ($_GET['strutture'] ?? 2))) : 2;
    $u = Auth::user(); $acc = $u && ($u['role'] ?? '') !== 'admin' ? Auth::account() : null;
    $chi = $acc ? null : $chiInvita();
    $extra = $chi ? [['tipo' => 'invito', 'nome' => 'Invito di ' . explode(' ', trim((string) $chi['user_name']))[0], 'percento' => MHW\Inviti::AMICO]] : [];
    $riep = [];
    if (MHW\Sconti::disponibili()) foreach ($piani as $c => $p) $riep[$c] = MHW\Sconti::riepilogo($p, $c === 'portfolio' ? $strutture : 1, $acc, $extra);
    // 8H · L'esito senza JavaScript (le tre risposte nell'indirizzo) conta come quello con JavaScript.
    if (count(array_filter($risposte)) === 3) {
        $esito = $risposte['d3'] === 'no' ? 'portfolio' : ($risposte['d1'] === 'no' || $risposte['d2'] === 'no' ? 'plus' : 'essential');
        MHW\Statistiche::evento('piano_test', $esito);
    }
    return ['qPiani' => $piani, 'qRiep' => $riep, 'qRisposte' => $risposte, 'qStrutture' => $strutture];
};

/*
 * 9D · Il link di un codice omaggio (/omaggio/XXXXX, o con il prefisso): il codice resta in sessione, toglie un eventuale
 * invito in sessione e porta alla registrazione con il banner «Hai N mesi gratis». Chi è già dentro e non ha pagato
 * lo prende subito (Account & Fatturazione).
 */
$r->get('/omaggio/{codice}', function (array $a) use ($guard) {
    $guard();
    $codice = MHW\Omaggi::normalizza((string) $a['codice']);
    if (($u = Auth::user()) && $u['role'] !== 'admin' && ($acc = Auth::account())) {
        try { $c = MHW\Omaggi::valida($codice, $acc); MHW\Omaggi::applica($acc, $c); Support::flash('Codice omaggio applicato: ' . (int) $c['mesi'] . ' mesi gratis, primo addebito il ' . Support::date(MHW\Omaggi::fineIso((int) $c['mesi'])) . '.'); }
        catch (\RuntimeException $e) { Support::flash($e->getMessage(), 'avviso'); }
        Support::redirect('/account');
    }
    try { MHW\Omaggi::valida($codice, null); }
    catch (\RuntimeException $e) { Support::flash($e->getMessage(), 'err'); Support::redirect('/'); }
    $_SESSION['omaggio'] = $codice;
    unset($_SESSION['invito']);
    Support::redirect('/registrati');
});

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
$home = function (string $loc) use ($guard, $codiceDalLink, $chiInvita, $datiQuestionario) {
    $guard();
    MHW\LinguaSito::imposta($loc);
    $codiceSconto = $codiceDalLink();
    $invito = $chiInvita();
    // 9O · Il link di un partner (/?partner=PM-…): il codice resta 30 giorni; chi è già dentro e non ha pagato lo prende subito.
    if (($codP = (string) ($_GET['partner'] ?? '')) !== '' && ($pp = MHW\ProgrammaPartner::daLink($codP)) && ($uP = Auth::user()) && $uP['role'] !== 'admin' && ($accP = Auth::account())) {
        try { MHW\ProgrammaPartner::applica($accP, (string) $pp['codice']); Support::flash('Codice partner ' . $pp['codice'] . ' applicato.'); unset($_SESSION['partner']); }
        catch (\RuntimeException $e) { Support::flash($e->getMessage(), 'avviso'); }
    }
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
    ] + $datiQuestionario());   // 9L · la finestra «Qual è il piano giusto per te?» (anche su /en, in inglese)
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
    if (MHW\Migrator::tableExists('interessi_sito') && RateLimit::hit('sito-interesse:' . RateLimit::ipKey(), 20, 3600)) {
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
        // 9H · La notifica a noi va all'email commerciale (MyHouse siti). Niente dati del visitatore: solo le risposte.
        $etichette = ['aspetto' => 'più bello', 'google' => 'più visibile su Google', 'dirette' => 'prenotazioni dirette', 'altro' => 'altro'];
        $canali = ['scopri' => 'ha aperto «Scopri MyHouse»', 'whatsapp' => 'scrive su WhatsApp', 'email' => 'scrive per email'];
        $chi = $acc ? Db::one('SELECT u.name, u.email FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [(int) $acc['id']]) : null;
        try {
            MHW\Mailer::manda(Support::commerciale(), 'MyHouse siti: una risposta da «Anche il tuo sito»', 'Una risposta da «Anche il tuo sito»', array_values(array_filter([
                'Ha già un sito: ' . (['si' => 'sì', 'no' => 'no'][$_POST['ha_sito'] ?? ''] ?? 'non detto') . '.',
                $bisogni ? 'Cosa vuole: ' . implode(', ', array_map(fn($b) => $etichette[$b], $bisogni)) . '.' : '',
                ($canali[$_POST['canale'] ?? ''] ?? '') !== '' ? 'Poi: ' . $canali[$_POST['canale']] . '.' : '',
                $chi ? 'Cliente: ' . trim((string) $chi['name']) . ' · ' . (string) $chi['email'] . '.' : 'Visitatore del sito (nessun dato personale).',
            ])), ['Apri i prospetti', Support::baseUrl() . '/admin/prospetti']);
        } catch (\Throwable $e) { MHW\Log::exception($e, 'notifica «Anche il tuo sito»'); }
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

/* 7F: le pagine per tipologia: problemi tipici, sezioni utili, piano consigliato, 4 domande. 9N: anche in inglese (/en/…). */
foreach (['it' => MHW\Seo::tipologie(), 'en' => MHW\Seo::tipologie('en')] as $locT => $tipiT) foreach ($tipiT as $chiave => $tipoP) {
    $r->get($tipoP['percorso'], function () use ($guard, $chiave, $tipoP, $datiQuestionario, $locT) {
        $guard();
        MHW\LinguaSito::imposta($locT);
        MHW\Stats::funnelEvent('landing_view');
        $q = $datiQuestionario();   // 9K · il questionario, con i prezzi barrati per chi guarda
        $piano = $q['qPiani'][$tipoP['piano'] ?? ''] ?? null;
        $demo = Db::one("SELECT id, slug, name FROM properties WHERE is_demo >= 1 AND status = 'published' AND archived_at IS NULL ORDER BY is_demo DESC, id");
        View::out('pub/tipologia', ['tp' => $tipoP, 'chiave' => $chiave, 'piano' => $piano, 'demo' => $demo, 'user' => Auth::user(), 'seoPagina' => ($locT === 'en' ? 'en-' : '') . $chiave] + $q);
    });
}

// 8J · Termini, Privacy e DPA, in italiano e in inglese. ?versione=AAAA-MM-GG mostra una versione archiviata (noindex).
foreach (MHW\Legale::DOCUMENTI as $docL => [, , $pItL, $pEnL]) {
    foreach (['it' => $pItL, 'en' => $pEnL] as $locL => $percorsoL) {
        $r->get($percorsoL, function () use ($docL, $locL) {
            MHW\LinguaSito::imposta($locL);
            $v = (string) ($_GET['versione'] ?? '');
            if ($v !== '' && preg_match('/^[0-9-]{4,20}$/', $v) && $v !== MHW\Legale::versioni()[$docL] && ($vecchia = MHW\Legale::archiviata($docL, $v, $locL))) {
                View::out('pub/legal', ['doc' => $docL, 'archiviata' => $vecchia], 'layout/bare');
                return;
            }
            View::out('pub/legal', ['doc' => $docL, 'seoPagina' => ($locL === 'en' ? 'en-' : '') . $docL], 'layout/bare');
        });
    }
}

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
// GEO · la versione completa per gli assistenti AI, e la chiave IndexNow (Bing la legge per credere agli avvisi).
$r->get('/llms-full.txt', fn() => $fileSeo('text/plain', [MHW\Seo::class, 'llmsCompleto']));
$r->get('/indexnow.txt', fn() => $fileSeo('text/plain', [MHW\Seo::class, 'chiaveIndexNow']));

// ------------------------------------------------------------------ registrazione
// 9N · La registrazione, in italiano (/registrati, anche ?lang=en) e in inglese (/en/register).
$registrati = function () use ($guard, $doveComincia, $salvaPiano, $codiceDalLink, $chiInvita) {
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
    // 9A · Più strutture del massimo del Portfolio: niente piano, si chiede il preventivo (riquadro in cima).
    $oltre = $pvScelto && Plans::perProperty($pvScelto) && $strutture > (int) $pvScelto['max_quantity'] ? $strutture : 0;
    $pianoOltre = $oltre ? $pvScelto : null;   // la versione serve al riquadro del preventivo, anche se non si compra
    if ($qScelta === null) $pvScelto = null;
    // Chi è già dentro non si registra una seconda volta.
    if ($u = Auth::user()) Support::redirect($scelta ?: $doveComincia($u));

    // 9A · Il codice di invito: dal link /i/CODICE (in sessione, campo in sola lettura) o scritto a mano nel modulo.
    $invitoLink = (string) ($_SESSION['invito'] ?? '');
    if ($invitoLink !== '' && !(($chiL = MHW\Inviti::daCodice($invitoLink)) && MHW\Inviti::puoInvitare($chiL))) { $invitoLink = ''; unset($_SESSION['invito']); }
    // 9D · Il codice omaggio dal link (/omaggio/XXXXX): vale lui, l'invito no.
    $omaggioLink = null;
    if (($codO = (string) ($_SESSION['omaggio'] ?? '')) !== '') {
        try { $omaggioLink = MHW\Omaggi::valida($codO, null); $invitoLink = ''; } catch (\RuntimeException) { unset($_SESSION['omaggio']); }
    }
    $err = null; $vecchi = ['name' => '', 'email' => '', 'codice_invito' => ''];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $vecchi = ['name' => (string) ($_POST['name'] ?? ''), 'email' => (string) ($_POST['email'] ?? ''), 'email2' => (string) ($_POST['email2'] ?? ''),
                   'codice_invito' => strtoupper(trim((string) ($_POST['codice_invito'] ?? '')))];
        try {
            // 9A · Un codice di invito scritto a mano si controlla prima di creare l'account: se non vale, si corregge.
            // 9D · Nello stesso campo si può scrivere un codice omaggio (prefisso OMAGGIO-): si controlla con le sue regole.
            $codiceAMano = $invitoLink === '' && !$omaggioLink ? $vecchi['codice_invito'] : '';
            $omaggioAMano = null;
            if ($codiceAMano !== '' && MHW\Omaggi::sembra($codiceAMano)) { $omaggioAMano = MHW\Omaggi::valida($codiceAMano, null); $codiceAMano = ''; }
            // 9O · Nello stesso campo il codice di un partner (prefisso PM-).
            $partnerAMano = null;
            if ($codiceAMano !== '' && MHW\ProgrammaPartner::sembra($codiceAMano) && MHW\ProgrammaPartner::disponibili()) { $partnerAMano = MHW\ProgrammaPartner::valida($codiceAMano, null); $codiceAMano = ''; }
            if ($codiceAMano !== '' && !(($chiM = MHW\Inviti::daCodice($codiceAMano)) && MHW\Inviti::puoInvitare($chiM))) {
                throw new RuntimeException('Questo codice non esiste o non è più attivo. Controlla con chi te l\'ha dato.');
            }
            if (!RateLimit::hit('register:' . RateLimit::ipKey(), 10, 3600)) {
                throw new RuntimeException('Troppe registrazioni da questa connessione. Riprova tra un\'ora.');
            }
            // 8J: una sola casella, obbligatoria e non spuntata: Termini e Accordo sul trattamento dei dati accettati,
            // Informativa letta. recordConsent ne salva le tre versioni e la data (users e legal_acceptances).
            if (empty($_POST['termini'])) {
                throw new RuntimeException('Per creare l\'account devi accettare i Termini e l\'Accordo sul trattamento dei dati, dopo aver letto l\'Informativa privacy.');
            }
            // 9N · Niente più «Ripeti l'email» (attrito): l'indirizzo si controlla nella pagina dopo («Ho sbagliato indirizzo»).
            $u = Auth::register((string) $_POST['email'], (string) $_POST['password'], (string) $_POST['name']);
            MHW\Stats::funnelEvent('signup');
            MHW\Statistiche::evento('registrazione');   // 8H: parte dalla pagina seguente («Controlla la tua email»)
            Auth::recordConsent((int) $u['user_id']);
            if (MHW\LinguaSito::en()) MHW\Pannello::scegli((int) $u['user_id'], 'en');   // 9N: registrato in inglese → pannello ed email in inglese
            Auth::login((int) $u['user_id']);
            // Arrivato da un link con il codice sconto: dopo la registrazione è già applicato all'account.
            if (($c = (string) ($_SESSION['codice_sconto'] ?? '')) !== '') {
                try { $riga = MHW\Sconti::valida($c, Auth::account(), null);
                      Db::update('accounts', ['intended_discount_code_id' => (int) $riga['id']], 'id = :aid', ['aid' => (int) $u['account_id']]); }
                catch (\RuntimeException) {}
                unset($_SESSION['codice_sconto']);
            }
            // 9D · Il codice omaggio (dal link o scritto a mano) si prenota all'account: niente invito.
            if ($omaggioLink || $omaggioAMano) {
                try { MHW\Omaggi::applica(Auth::account(), MHW\Omaggi::valida((string) ($omaggioLink ?? $omaggioAMano)['codice'], Auth::account())); }
                catch (\RuntimeException $e) { Support::flash($e->getMessage(), 'avviso'); }
                unset($_SESSION['omaggio'], $_SESSION['invito']);
            }
            // 9O · Il codice di un partner: scritto nel modulo o arrivato dal link /?partner=PM-… (cookie di 30 giorni). Con l'omaggio no.
            $codP = $partnerAMano ? (string) $partnerAMano['codice'] : (string) ($_SESSION['partner'] ?? $_COOKIE[MHW\ProgrammaPartner::COOKIE] ?? '');
            if ($codP !== '' && MHW\ProgrammaPartner::disponibili()) {
                try { MHW\ProgrammaPartner::applica(Auth::account(), $codP); }
                catch (\RuntimeException $e) { if ($partnerAMano || !($omaggioLink || $omaggioAMano)) Support::flash($e->getMessage(), 'avviso'); }
                unset($_SESSION['partner']);
            }
            // Arrivato dal link di un amico (/i/CODICE): resta legato a chi l'ha invitato, con il suo sconto.
            if (($inv = (string) ($_SESSION['invito'] ?? '')) !== '') {
                if ($chi = MHW\Inviti::daCodice($inv)) MHW\Inviti::collega(Auth::account(), $chi);
                unset($_SESSION['invito']);
            } elseif ($codiceAMano !== '') {
                // 9A · Il codice scritto nel modulo: gli stessi controlli di chi lo scrive nel pannello.
                try { MHW\Inviti::applicaCodice(Auth::account(), $codiceAMano); }
                catch (\RuntimeException $e) { Support::flash($e->getMessage(), 'avviso'); }
            }
            Auth::sendVerification((int) $u['user_id']);
            MHW\RateLimit::hit('verifica-attesa:' . (int) $u['user_id'], 1, 60);
            if ($pvScelto) $salvaPiano(Auth::account(), (int) $u['user_id'], $pvScelto, $qScelta);
            // 7A: la guida si comincia dopo la conferma dell'email.
            if ($scelta && !$pvScelto) $_SESSION['dopo_conferma'] = $scelta;
            Support::redirect('/conferma-email');
        } catch (\RuntimeException $e) { $err = MHW\LinguaSito::tr($e->getMessage()); }
    }
    // La stessa fascia della landing: codice sconto dal link o invito di un amico.
    $invito = $chiInvita();
    // 9A · Lo sconto dell'invito, per i prezzi barrati: dal link, oppure dal codice scritto a mano se vale.
    $chiScritto = $invito || $vecchi['codice_invito'] === '' ? null : MHW\Inviti::daCodice($vecchi['codice_invito']);
    if ($chiScritto && !MHW\Inviti::puoInvitare($chiScritto)) $chiScritto = null;
    $nomeInvito = $invito ? explode(' ', trim((string) $invito['user_name']))[0] : ($chiScritto ? explode(' ', trim((string) $chiScritto['user_name']))[0] : '');
    if ($omaggioLink) $nomeInvito = '';
    View::out('auth/register', ['err' => $err, 'piano' => $piano, 'strutture' => $strutture, 'vecchi' => $vecchi,
                                'pianoScelto' => $pvScelto, 'quantita' => $qScelta, 'codiceSconto' => $codiceSconto, 'omaggio' => $omaggioLink,
                                'invitoDi' => $invito && !$omaggioLink ? $nomeInvito : '', 'invitoCodice' => $invitoLink, 'oltre' => $oltre, 'pianoOltre' => $pianoOltre,
                                'scontoInvito' => $nomeInvito !== '' ? [['tipo' => 'invito', 'nome' => 'Invito di ' . $nomeInvito, 'percento' => MHW\Inviti::AMICO]] : []], 'layout/bare');
};
/* 9N · 10 bis: ?offerta={token} su registrazione e accesso ricorda l'offerta, per tornarci dopo. */
$ricordaOfferta = function (): void {
    $t = (string) ($_GET['offerta'] ?? '');
    if ($t !== '' && MHW\Offerte::perToken($t)) $_SESSION['offerta_dopo'] = $t;
};
$r->any('/registrati', function () use ($registrati, $ricordaOfferta) { $ricordaOfferta(); if (($_GET['lang'] ?? $_POST['lang'] ?? '') === 'en') MHW\LinguaSito::imposta('en'); $registrati(); });
$r->any('/en/register', function () use ($registrati) { MHW\LinguaSito::imposta('en'); $registrati(); });

// 9N · L'accesso, in italiano (/accedi, anche ?lang=en) e in inglese (/en/login).
$accedi = function () use ($guard, $doveComincia) {
    $guard();
    if ($u = Auth::user()) Support::redirect($doveComincia($u));
    $err = null; $email = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = (string) ($_POST['email'] ?? '');
        $perEmail = RateLimit::key('login', $email);
        if (!RateLimit::hit('login-ip:' . RateLimit::ipKey(), 40, 900) || !RateLimit::hit($perEmail, 8, 900)) {
            $err = 'Troppi tentativi. Aspetta un quarto d\'ora, oppure recupera la password.';
        } elseif (Auth::attempt($email, (string) ($_POST['password'] ?? ''))) {
            RateLimit::clear($perEmail);
            // 9E · Una password provvisoria (impostata dall'amministrazione) vale 48 ore e va cambiata al primo accesso.
            $uL = Auth::user();
            if (!empty($uL['password_temp_until']) && strtotime((string) $uL['password_temp_until']) < time()) {
                Auth::logout();
                $err = 'La password provvisoria è scaduta: chiedi un link per reimpostarla.';
            } else {
                Support::redirect(!empty($uL['must_change_password']) ? '/password/cambia' : $doveComincia($uL));
            }
        } else {
            $err = 'Email o password non corrispondono.';
        }
    }
    View::out('auth/login', ['err' => $err !== null ? MHW\LinguaSito::tr($err) : null, 'email' => $email], 'layout/bare');
};
$r->any('/accedi', function () use ($accedi, $ricordaOfferta) { $ricordaOfferta(); if (($_GET['lang'] ?? $_POST['lang'] ?? '') === 'en') MHW\LinguaSito::imposta('en'); $accedi(); });

// 9N · 10 bis · L'offerta su misura: la pagina (senza account si vede lo stesso) e «Accetta e paga».
$r->get('/offerta/{token}', function (array $a) {
    $o = MHW\Offerte::perToken((string) $a['token']);
    if (!$o) { http_response_code(404); View::out('pub/404', []); }
    if ($o['lingua'] === 'en') MHW\LinguaSito::imposta('en');
    $u = Auth::user();
    $utente = $u && $u['role'] === 'host' ? $u : null;
    $acc = $utente ? Auth::account() : null;
    if ($utente && $o['stato'] !== 'inviata') unset($_SESSION['offerta_dopo']);
    View::out('pub/offerta', ['o' => $o, 'utente' => $utente, 'acc' => $acc, 'fatturazioneOk' => $acc ? MHW\Fatturazione::completa($acc) : false]);
});
$r->post('/offerta/{token}/accetta', function (array $a) {
    $u = Auth::requireUser();
    $o = MHW\Offerte::perToken((string) $a['token']);
    if (!$o || $u['role'] !== 'host') { http_response_code(404); View::out('pub/404', []); }
    if (!Auth::isVerified($u)) { Support::flash('Conferma prima la tua email: ti abbiamo scritto.', 'err'); Support::redirect('/offerta/' . $o['token']); }
    try {
        $url = MHW\Offerte::accetta($o, Auth::account(), $u);
        unset($_SESSION['offerta_dopo']);
    } catch (\RuntimeException $e) {
        Support::flash($e->getMessage(), 'err'); Support::redirect('/offerta/' . $o['token']);
    } catch (\Throwable $e) {
        Support::flash('Il pagamento non è disponibile in questo momento. Riprova tra poco (codice ' . Log::exception($e, 'offerta su misura') . ').', 'err');
        Support::redirect('/offerta/' . $o['token']);
    }
    Support::redirect($url);
});
/* 9R · Il cambio di piano proposto dall'amministrazione: si vede dal link, si conferma da cliente (mai da «Entra come cliente»). */
$r->get('/proposta/{token}', function (array $a) {
    $p = MHW\ProposteCambio::perToken((string) $a['token']);
    if (!$p) { http_response_code(404); View::out('pub/404', []); }
    $u = Auth::user();
    $acc = $u && $u['role'] === 'host' ? Auth::account() : null;
    View::out('pub/proposta', ['p' => $p, 'c' => MHW\ProposteCambio::conti($p), 'acc' => $acc, 'suo' => $acc && (int) $acc['id'] === (int) $p['account_id'],
                               'impersona' => Auth::isImpersonating()]);
});
$r->post('/proposta/{token}/accetta', function (array $a) {
    $u = Auth::requireUser();
    $p = MHW\ProposteCambio::perToken((string) $a['token']);
    if (!$p || $u['role'] !== 'host') { http_response_code(404); View::out('pub/404', []); }
    try {
        $url = MHW\ProposteCambio::accetta($p, Auth::account(), $u);
    } catch (\RuntimeException $e) {
        Support::flash($e->getMessage(), 'err'); Support::redirect('/proposta/' . $a['token']);
    } catch (\Throwable $e) {
        Support::flash('Il pagamento non è disponibile in questo momento. Riprova tra poco (codice ' . Log::exception($e, 'proposta di cambio') . ').', 'err');
        Support::redirect('/proposta/' . $a['token']);
    }
    if ($url !== null) Support::redirect($url);
    Support::flash('Fatto: il cambio parte dal rinnovo, con lo sconto proposto. Lo trovi in Account & Fatturazione.');
    Support::redirect('/account');
});
$r->any('/en/login', function () use ($accedi) { MHW\LinguaSito::imposta('en'); $accedi(); });

$r->post('/esci', function () { Auth::logout(); Support::redirect('/'); });

// 8J · «Accetto» i Termini, l'Informativa e il DPA aggiornati (la finestra nel pannello). Mai al posto del cliente.
$r->post('/legale/accetta', function () {
    $u = Auth::requireUser();
    if (Auth::isImpersonating()) { Support::flash('Durante l\'accesso dell\'assistenza non si accettano i documenti al posto del cliente.', 'err'); Support::redirect('/pannello'); }
    MHW\Legale::accetta((int) $u['id'], 'riaccettazione');
    Auth::audit('legale.accettati', (int) $u['id'], MHW\Legale::versioni());
    Support::flash('Grazie: hai accettato i documenti aggiornati. Puoi continuare a lavorare sulle tue guide.');
    $torna = Support::routePath((string) (parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH) ?: ''));
    Support::redirect(preg_match('#^/(pannello|account|piano)#', $torna) ? $torna : '/pannello');
});

// ------------------------------------------------------------ verifica email
/* Il link di conferma porta dentro la procedura, già connessi (7A): il link è monouso e vale 48 ore. */
$r->get('/verifica/{token}', function (array $a) use ($doveComincia) {
    $uid = Tokens::consume($a['token'], Tokens::VERIFY);
    if ($uid) {
        Db::update('users', ['email_verified_at' => Support::now()], 'id = :uid AND email_verified_at IS NULL', ['uid' => $uid]);
        $chi = Auth::user();
        if (!$chi || (int) $chi['id'] !== $uid) { if (!Auth::isImpersonating()) Auth::login($uid); }
        $u = Db::one('SELECT * FROM users WHERE id = ?', [$uid]);
        MHW\LinguaSito::imposta(($u['panel_lang'] ?? '') === 'en' ? 'en' : 'it'); // 9N: nella lingua del pannello
        Support::flash(MHW\LinguaSito::tr('Email confermata: ora prepari la tua guida.'));
        $dopo = (string) ($_SESSION['dopo_conferma'] ?? ''); unset($_SESSION['dopo_conferma']);
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
    MHW\LinguaSito::imposta(($u['panel_lang'] ?? '') === 'en' ? 'en' : 'it'); // 9N
    View::out('auth/check_email', ['email' => (string) $u['email'], 'attendi' => RateLimit::attesa('verifica-attesa:' . (int) $u['id'])], 'layout/bare');
});

/** «Ho sbagliato indirizzo»: si cambia prima della conferma, e parte una conferma nuova. */
$r->post('/conferma-email/cambia', function () {
    $u = Auth::requireUser();
    if (Auth::isVerified($u)) Support::redirect('/pannello');
    MHW\LinguaSito::imposta(($u['panel_lang'] ?? '') === 'en' ? 'en' : 'it'); // 9N
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $err = null;
    if (!RateLimit::hit('verify:' . $u['id'], 5, 3600)) $err = 'Ti abbiamo già scritto più volte in un\'ora: riprova più tardi.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) $err = 'Questo indirizzo email non è valido.';
    elseif ($email === mb_strtolower((string) $u['email'])) $err = 'È lo stesso indirizzo di prima: controlla anche in Spam e Promozioni.';
    elseif (Db::val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $u['id']])) $err = 'Questa email è già usata da un altro account: accedi con quella, oppure recupera la password.';
    if ($err) View::out('auth/check_email', ['email' => (string) $u['email'], 'attendi' => RateLimit::attesa('verifica-attesa:' . (int) $u['id']), 'err' => MHW\LinguaSito::tr($err)], 'layout/bare');
    Db::update('users', ['email' => $email], 'id = :uid', ['uid' => $u['id']]);
    Auth::audit('account.email_prima_conferma', (int) $u['id'], ['da' => $u['email']]);
    Auth::sendVerification((int) $u['id']);
    RateLimit::hit('verifica-attesa:' . (int) $u['id'], 1, 60);
    Support::flash(MHW\LinguaSito::t('Fatto: ti abbiamo scritto a ', 'Done: we have written to ') . $email . '.');
    Support::redirect('/conferma-email');
});

$r->post('/verifica/invia', function () {
    $u = Auth::requireUser();
    if (Auth::isVerified($u)) Support::redirect('/pannello');
    $torna = (string) ($_POST['torna'] ?? '');
    MHW\LinguaSito::imposta(($u['panel_lang'] ?? '') === 'en' ? 'en' : 'it'); // 9N
    $T = [MHW\LinguaSito::class, 'tr'];
    if (RateLimit::attesa('verifica-attesa:' . (int) $u['id']) > 0) {
        Support::flash($T('Aspetta qualche secondo prima di rimandarla.'), 'err');
    } elseif (!RateLimit::hit('verify:' . $u['id'], 5, 3600)) {
        Support::flash($T('Ti abbiamo già scritto più volte: controlla anche in Spam e Promozioni, oppure riprova tra un\'ora.'), 'err');
    } elseif (Auth::sendVerification((int) $u['id'])) {
        RateLimit::hit('verifica-attesa:' . (int) $u['id'], 1, 60);
        Support::flash(MHW\LinguaSito::t('Ti abbiamo mandato di nuovo l\'email di conferma, a ', 'We have sent the confirmation email again, to ') . $u['email'] . '.');
    } else {
        Support::flash($T('L\'email non è partita. Riprova tra poco.'), 'err');
    }
    Support::redirect($torna === 'account' ? '/account' : '/conferma-email');
});

// --------------------------------------------------------- recupero password
$r->any('/password/dimenticata', function () use ($guard) {
    $guard();
    if (($_GET['lang'] ?? '') === 'en') MHW\LinguaSito::imposta('en');   // 9N: dalla pagina di accesso inglese
    $inviata = false; $err = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = (string) ($_POST['email'] ?? '');
        if (!RateLimit::hit('reset-ip:' . RateLimit::ipKey(), 10, 3600) || !RateLimit::hit(RateLimit::key('reset', $email), 3, 3600)) {
            $err = 'Troppe richieste. Riprova tra un\'ora.';
        } else {
            Auth::sendPasswordReset($email);
            // Stessa risposta che l'account esista o no: nessuno scopre chi è iscritto.
            $inviata = true;
        }
    }
    View::out('auth/forgot', ['inviata' => $inviata, 'err' => $err !== null ? MHW\LinguaSito::tr($err) : null], 'layout/bare');
});

$r->any('/password/nuova/{token}', function (array $a) {
    $uid = Tokens::peek($a['token'], Tokens::RESET);
    if ($uid && Db::val("SELECT panel_lang FROM users WHERE id = ?", [$uid], '') === 'en') MHW\LinguaSito::imposta('en');   // 9N
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
            Support::flash(MHW\LinguaSito::tr('Password cambiata. Accedi con quella nuova.'));
            Support::redirect(MHW\LinguaSito::percorso('/accedi'));
        }
    }
    View::out('auth/reset', ['valido' => true, 'err' => $err !== null ? MHW\LinguaSito::tr($err) : null, 'token' => $a['token']], 'layout/bare');
});

/* 9E · Dopo una password provvisoria: si può solo scegliere la password nuova, poi si entra. */
$r->any('/password/cambia', function () use ($doveComincia) {
    $u = Auth::requireUser();
    if (empty($u['must_change_password'])) Support::redirect($doveComincia($u));
    MHW\LinguaSito::imposta(($u['panel_lang'] ?? '') === 'en' ? 'en' : 'it'); // 9N
    $err = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pw = (string) ($_POST['password'] ?? '');
        try { Auth::controllaPassword($pw, (string) $u['email'], (string) $u['name']); } catch (\RuntimeException $e) { $err = $e->getMessage(); }
        if ($err === null && $pw !== (string) ($_POST['password2'] ?? '')) $err = 'Le due password non coincidono.';
        if ($err === null && password_verify($pw, (string) $u['password_hash'])) $err = 'Scegli una password diversa da quella provvisoria.';
        if ($err === null) {
            Db::update('users', ['password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'must_change_password' => 0, 'password_temp_until' => null], 'id = :uid', ['uid' => (int) $u['id']]);
            session_regenerate_id(true);
            Auth::audit('password.changed_after_temp', (int) $u['id']);
            Support::flash(MHW\LinguaSito::tr('Password cambiata: da adesso entri con quella nuova.'));
            Support::redirect($doveComincia(Db::one('SELECT * FROM users WHERE id = ?', [(int) $u['id']]) ?: $u));
        }
    }
    View::out('auth/cambia', ['err' => $err !== null ? MHW\LinguaSito::tr($err) : null, 'user' => $u], 'layout/bare');
});

// ------------------------------------------------------------- 9A · preventivo oltre il massimo del Portfolio
/*
 * «N strutture? Ti facciamo una proposta su misura»: il modulo nella home, in /piano e in registrazione.
 * Funziona anche senza JavaScript. Si torna alla pagina di partenza con l'esito nel riquadro.
 */
/* 9O · La pagina del partner (/partner): si entra con un link senza password (48 ore) mandato alla sua email; la sessione è
   a parte da quella degli host. Vede codice, percentuali, i quattro numeri, i clienti (struttura, città, piano, date, la sua
   commissione), i materiali e il foglio da stampare. Mai email, importi pagati dai clienti o dati di pagamento. Noindex. */
$partnerDentro = function (): ?array {
    $id = (int) ($_SESSION['partner_area'] ?? 0);
    $p = $id ? MHW\ProgrammaPartner::riga($id) : null;
    if ($p && $p['stato'] === 'chiuso') { unset($_SESSION['partner_area']); return null; }
    return $p;
};
$r->get('/partner', function () use ($partnerDentro) {
    $p = $partnerDentro();
    if (!$p) View::out('pub/partner', ['p' => null]);
    View::out('pub/partner', ['p' => $p, 'clienti' => MHW\ProgrammaPartner::clienti((int) $p['id']), 'riep' => MHW\ProgrammaPartner::riepilogo((int) $p['id'])]);
});
$r->post('/partner/accedi', function () {
    $email = (string) ($_POST['email'] ?? '');
    if (MHW\RateLimit::hit('partner-link:' . MHW\RateLimit::ipKey(), 5, 3600) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try { MHW\ProgrammaPartner::mandaLink($email); } catch (\Throwable $e) { Log::exception($e, 'link del partner'); }
    }
    Support::flash('Se l\'email è quella di un partner, ti abbiamo scritto: apri il link (vale ' . MHW\ProgrammaPartner::ORE_LINK . ' ore, una volta sola).');
    Support::redirect('/partner');
});
$r->get('/partner/entra/{token}', function (array $a) {
    $p = MHW\ProgrammaPartner::entra((string) $a['token']);
    if (!$p) { Support::flash('Il link non vale più (dura ' . MHW\ProgrammaPartner::ORE_LINK . ' ore e si usa una volta): chiedine un altro.', 'err'); Support::redirect('/partner'); }
    session_regenerate_id(true);
    $_SESSION['partner_area'] = (int) $p['id'];
    Auth::audit('partner.accesso', null, ['partner' => (int) $p['id']]);
    Support::redirect('/partner');
});
$r->post('/partner/esci', function () { unset($_SESSION['partner_area']); Support::redirect('/partner'); });
$r->get('/partner/foglio.pdf', function () use ($partnerDentro) {
    $p = $partnerDentro();
    if (!$p) Support::redirect('/partner');
    $en = $p['lingua'] === 'en'; $sc = (int) $p['sconto_clienti_pct'];
    $pdf = MHW\QrExport::pdf(MHW\ProgrammaPartner::link($p), 'MyHouse Welcome', null,
        [$en ? 'Your guests\' guide, on their phone' : 'La guida per i tuoi ospiti, sul telefono', ($en ? 'Code ' : 'Codice ') . $p['codice'] . ($sc > 0 ? ($en ? ": $sc% off the first year" : ": $sc% sul primo anno") : ''),
         $en ? 'Scan the QR code to start' : 'Inquadra il QR per cominciare', (string) ($p['ragione_sociale'] ?: $p['referente'])],
        $en ? 'The digital guide for holiday homes, B&Bs and farm stays' : 'La guida digitale per case vacanza, B&B e agriturismi');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="myhouse-welcome-' . strtolower((string) $p['codice']) . '.pdf"');
    header('X-Robots-Tag: noindex');
    echo $pdf; exit;
});

/* 9O · «Sono un privato: mostra i prezzi IVA inclusa»: la scelta in sessione (lo script la tiene anche nel browser). */
$r->post('/prezzi/iva', function () {
    MHW\Iva::imposta(($_POST['si'] ?? '') === '1');
    if (($_POST['js'] ?? '') === '1') { http_response_code(204); exit; }
    $torna = (string) ($_POST['torna'] ?? '/');
    Support::redirect(preg_match('~^/[A-Za-z0-9/_?=&.%#-]*$~', $torna) && !str_starts_with($torna, '//') ? $torna : '/');
});

$r->post('/preventivo', function () {
    $torna = (string) ($_POST['torna'] ?? '/');
    if (!preg_match('#^/(en|piano|registrati|piu-strutture|property-manager)?(\?[a-z0-9=&_-]*)?$#i', $torna)) $torna = '/';
    $u = Auth::user();
    $acc = $u && $u['role'] !== 'admin' ? Auth::account() : null;
    $lingua = str_starts_with($torna, '/en') ? 'en' : 'it';
    try {
        MHW\Preventivi::salva($_POST, $acc, $lingua);
        $_SESSION['preventivo_ok'] = (int) ($_POST['strutture'] ?? 0);
        if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) Support::json(['ok' => true]);
    } catch (\RuntimeException $e) {
        if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) Support::json(['ok' => false, 'errore' => $e->getMessage()], 422);
        $_SESSION['preventivo_errore'] = ['msg' => $e->getMessage()] + array_map(fn($v) => is_string($v) ? mb_substr($v, 0, 2000) : '', array_intersect_key($_POST, array_flip(['nome', 'email', 'telefono', 'messaggio', 'strutture'])));
    }
    Support::redirect($torna . '#preventivo');
});

/* 9K · «Diventa partner» (/property-manager): la richiesta va all'email commerciale e in Amministrazione → Partner. */
$r->post('/property-manager/partner', function () {
    try {
        MHW\Partner::salva($_POST);
        $_SESSION['partner_ok'] = true;
    } catch (\RuntimeException $e) {
        $_SESSION['partner_errore'] = ['msg' => $e->getMessage()] + array_map(fn($v) => is_string($v) ? mb_substr($v, 0, 2000) : '',
            array_intersect_key($_POST, array_flip(['nome', 'societa', 'email', 'telefono', 'strutture', 'zone', 'messaggio'])));
    }
    Support::redirect((($_POST['lingua'] ?? '') === 'en' ? '/en/property-managers' : '/property-manager') . '#partner');   // 9N: si torna nella lingua del modulo
});

/* 9A · «Applica» accanto al codice di invito in registrazione: dice se il codice vale e di chi è. */
$r->post('/invito/verifica', function () {
    if (!RateLimit::hit('invito-verifica:' . RateLimit::ipKey(), 20, 900)) Support::json(['ok' => false, 'errore' => 'Troppi tentativi: riprova tra un quarto d\'ora.'], 429);
    // 9D · Un codice omaggio nello stesso campo: mesi gratis e primo addebito.
    if (MHW\Omaggi::sembra((string) ($_POST['codice'] ?? ''))) {
        try { $c = MHW\Omaggi::valida((string) $_POST['codice'], Auth::account()); }
        catch (\RuntimeException $e) { Support::json(['ok' => false, 'errore' => $e->getMessage()], 404); }
        Support::json(['ok' => true, 'tipo' => 'omaggio', 'mesi' => (int) $c['mesi'], 'primo_addebito' => Support::date(MHW\Omaggi::fineIso((int) $c['mesi'])), 'scadenza' => Support::date((string) $c['scade_il'])]);
    }
    $chi = MHW\Inviti::daCodice((string) ($_POST['codice'] ?? ''));
    if (!$chi || !MHW\Inviti::puoInvitare($chi)) Support::json(['ok' => false, 'errore' => 'Questo codice non esiste o non è più attivo. Controlla con chi te l\'ha dato.'], 404);
    Support::json(['ok' => true, 'nome' => explode(' ', trim((string) $chi['user_name']))[0] ?: 'un amico', 'percento' => MHW\Inviti::AMICO]);
});

/* 9L · «Tutte le guide»: le schede approvate di «Le guide dei nostri host», nell'ordine deciso in admin, 24 per pagina
   (le prime sono anche in home). 9N: esiste solo con la sezione accesa dall'admin e almeno una scheda approvata, altrimenti 404. */
$r->get('/guide-dei-nostri-host', function () use ($guard) {
    $guard();
    if (!MHW\Vetrina::guideVisibili()) { http_response_code(404); View::out('pub/404', []); return; }
    View::out('pub/guide_host', MHW\Vetrina::tutte(max(1, (int) ($_GET['pagina'] ?? 1))) + ['seoPagina' => 'guide-host']);
});

// ------------------------------------------------------------- scelta del piano
/* «Which plan is right for you?»: il questionario in inglese come pagina (il link della finestra di /en senza JavaScript).
   Per tutti, anche chi è dentro: la scelta del piano vera resta su /piano?piano=… (in italiano, come la registrazione). */
$r->get('/en/plan', function () use ($guard, $datiQuestionario) {
    $guard();
    MHW\LinguaSito::imposta('en');
    View::out('pub/piano_test', ['seoPagina' => 'en-piano', 'user' => Auth::user()] + $datiQuestionario());
});
$r->any('/piano', function () use ($salvaPiano, $guard, $datiQuestionario) {
    // 9L · Per chi non è dentro, /piano è «Quale piano scegliere»: il questionario come pagina (il link «Rispondi a 3 domande»
    // della home senza JavaScript). Con ?piano=… (un piano già scelto) resta la strada di sempre: accesso, poi la scelta.
    if (!Auth::user() && $_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['piano'])) {
        $guard();
        View::out('pub/piano_test', ['seoPagina' => 'piano', 'user' => null] + $datiQuestionario());
        return;
    }
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
            // 9A · Oltre il massimo non si compra: si chiede il preventivo (il riquadro si apre già compilato).
            if (Plans::perProperty($pv) && (int) ($_POST['strutture'] ?? 0) > (int) $pv['max_quantity']) Support::redirect('/piano?piano=' . (int) $pv['id'] . '&strutture=' . (int) $_POST['strutture'] . '#preventivo');
            Support::flash('Indica un numero intero di strutture tra ' . (int) $pv['min_quantity'] . ' e ' . (int) $pv['max_quantity'] . '.', 'err');
            Support::redirect('/piano?piano=' . (int) $pv['id']);
        }
        // 9R · Il Portfolio non si compra per meno strutture di quelle che l'account ha già: per ridurre se ne archivia una.
        $tornaP = preg_match('#^/pannello/\d+/procedura/pubblica$#', (string) ($_POST['torna'] ?? '')) ? (string) $_POST['torna'] : '/piano?piano=' . (int) $pv['id'];
        if (Plans::perProperty($pv) && $q < ($minP = Plans::minimoPer($pv, (int) $acc['id']))) {
            Support::flash(MHW\tf('Hai già {n} strutture: il minimo è {n}; per ridurre, archivia una struttura.', ['{n}' => $minP]), 'err');
            Support::redirect($tornaP);
        }
        $salvaPiano($acc, (int) $u['id'], $pv, $q);
        Support::flash(Plans::perProperty($pv) ? MHW\tf('Piano {piano} per {n} strutture scelto. Non paghi niente adesso: solo quando pubblichi.', ['{piano}' => $pv['name'], '{n}' => $q])
                                          : MHW\tf('Piano {piano} scelto. Non paghi niente adesso: solo quando pubblichi.', ['{piano}' => $pv['name']]));
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
    // Il ritorno dal browser non prova nulla: la pagina aspetta il webhook firmato (o il recupero da Stripe).
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
    // Se dopo 20 secondi di attesa il webhook non ha ancora confermato, il sito chiede a Stripe gli eventi della sessione.
    if ($o['status'] === 'pending' && (string) $o['provider_session_id'] !== '') {
        $primo = (int) ($_SESSION['mhw_attesa'][(int) $o['id']] ??= time());
        if (time() - $primo >= MHW\RecuperoPagamenti::ATTESA) {
            try { MHW\RecuperoPagamenti::ordine($o); } catch (\Throwable $e) { Log::exception($e, 'recupero del pagamento ' . $o['id']); }
            $o = Db::one('SELECT * FROM orders WHERE id = ?', [$o['id']]);
        }
    }
    $p = $o['property_id'] ? Db::one('SELECT slug, status FROM properties WHERE id = ?', [$o['property_id']]) : null;
    // La guida è online (confermato da Stripe): se è la prima dell'account, l'email di benvenuto, se non è già partita.
    if ($o['status'] === 'paid' && $p && $p['status'] === 'published') MHW\Benvenuto::forse((int) $acc['id']);
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
    // Il motivo del rifiuto resta nel registro del webhook (Quadro e Diagnostica lo dicono).
    if (($motivo = Stripe::motivoFirma($payload, $sig, $secret)) !== null) {
        MHW\RecuperoPagamenti::rifiutato($motivo, trim($sig) !== '');
        http_response_code(400); exit('firma non valida');
    }
    $event = json_decode($payload, true);
    if (!is_array($event)) { http_response_code(400); exit('payload illeggibile'); }
    MHW\RecuperoPagamenti::accettato((string) ($event['type'] ?? ''));
    try {
        $esito = Billing::handleEvent($event);
        header('Content-Type: text/plain'); echo $esito;
        // 9O · Le email del pagamento (la guida è online, l'addebito rifiutato), dopo aver risposto a Stripe.
        if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
        Billing::dopoRisposta();
        // Il pagamento ha messo online una prima guida: l'email di benvenuto.
        if (in_array($event['type'] ?? '', ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)
            && ($accB = (int) ($event['data']['object']['metadata']['account_id'] ?? 0)) > 0) {
            MHW\Benvenuto::forse($accB);
        }
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
    // 9C: i vecchi indirizzi della vetrina (casa-checco-N delle vetrine rifatte) portano a quella attuale, con un 301.
    if (!$g && preg_match('/^casa-checco(-[a-z0-9-]+)?$/', $slug) && ($v = MHW\Demo::vetrinaAttuale()) && $v['slug'] !== $slug) {
        $percorso = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $nuovo = preg_replace('#/g/' . preg_quote($slug, '#') . '(?=/|$)#', '/g/' . $v['slug'], $percorso, 1);
        header('Location: ' . ($nuovo !== null && $nuovo !== $percorso ? $nuovo : Support::url('/g/' . $v['slug'])), true, 301);
        exit;
    }
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
/* 9B · Un'immagine ridotta (lato massimo in px) per le schede della home: dalla cache o creata al volo. */
$r->get('/img/{id}/{max}', function (array $a) {
    $ris = MHW\Media::ridottaBytes((int) $a['id'], (int) $a['max']);
    if (!$ris) { http_response_code(404); exit; }
    header_remove('Cache-Control');
    header('Content-Type: ' . $ris[1]);
    header('Cache-Control: public, max-age=604800');
    header('X-Content-Type-Options: nosniff');
    echo $ris[0]; exit;
});

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

/* 9Q · Le strade dell'email del giorno 21 sulle bozze: il token dell'email fa da firma, non serve accedere.
   «Non ora» ferma le email sulla bozza (è l'ultima: un clic di un antivirus non toglie niente di più) e lo dice;
   «Scegli un orario» porta al link di Impostazioni → Sito. Tutte e due contano il clic in Matomo (bozza21_click). */
$r->get('/bozza/non-ora/{token}', function (array $a) {
    $riga = MHW\Richiami::daToken((string) $a['token']);
    if (!$riga || !in_array($riga['kind'], MHW\Bozze::TIPI, true)) { http_response_code(404); View::out('pub/404', []); }
    MHW\Richiami::smetti((int) $riga['account_id'], 'bozza');
    MHW\Statistiche::matomoDalServer($riga['kind'] . '_click', 'non-ora');
    View::out('pub/bozza_non_ora', ['en' => MHW\Richiami::lingua((int) $riga['account_id']) === 'en'], 'layout/bare');
});
$r->get('/bozza/chiamata/{token}', function (array $a) {
    $riga = MHW\Richiami::daToken((string) $a['token']);
    $link = trim((string) (Config::get('chiamata_url') ?? ''));
    if (!$riga || !in_array($riga['kind'], MHW\Bozze::TIPI, true) || !preg_match('#^https?://#', $link)) { http_response_code(404); View::out('pub/404', []); }
    MHW\Statistiche::matomoDalServer($riga['kind'] . '_click', 'chiamata');
    header('Location: ' . $link, true, 302);
    exit;
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
