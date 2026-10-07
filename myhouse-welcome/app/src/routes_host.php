<?php
/** Rotte dell'area host. $r è il Router creato in public/index.php. */

use MHW\{Auth, Config, Conversione, Db, Entitlements, Guide, LimitReached, Log, Mappe, Media, Migrator, NotFound, Palette, Plans, Properties,
         Qr, QrExport, SectionCatalog, Stats, Stripe, Subscriptions, Suggerimenti, Support, Traduttore, View, CambioAbbonamento, CambioPiano};

/* La procedura: cinque passi. Le lingue in più stanno in fondo a «Anteprima e
   pubblica», facoltative: le traduzioni non fermano mai la pubblicazione. */
const MHW_PASSI = ['struttura' => 'Struttura e contatti', 'arrivo' => 'Check-in & Check-out', 'sezioni' => 'Sezioni',
                   'aspetto' => 'Aspetto', 'pubblica' => 'Pubblica'];
/** I passi della v1 e dove sono finiti (migrazione 007 e redirect dei vecchi indirizzi). */
const MHW_PASSI_VECCHI = ['checkin' => 'arrivo', 'contenuti' => 'sezioni', 'lingue' => 'aspetto', 'anteprima' => 'pubblica'];

/** L'account di chi è connesso, per ogni rotta di quest'area. */
$host = function (): array {
    $u = Auth::requireUser();
    $acc = Auth::account();
    if (!$acc) Support::redirect('/admin');
    return [$u, $acc];
};

/**
 * La struttura chiesta, se appartiene all'account. Altrimenti non esiste.
 * Una struttura bloccata (Portfolio non ancora pagato, o oltre la quantità pagata)
 * non si apre e non si modifica: si torna alle guide con un avviso, non un errore.
 * Solo eliminarla resta possibile ($ancheBloccata).
 */
$mia = function (int $id, bool $ancheBloccata = false) use ($host): array {
    [$u, $acc] = $host();
    $p = Db::one('SELECT * FROM properties WHERE id = ? AND account_id = ?', [$id, $acc['id']]);
    if (!$p) { http_response_code(404); View::out('pub/404', []); }
    if (!$ancheBloccata && !Entitlements::editable((int) $acc['id'], (int) $p['id'])) {
        $avviso = $p['name'] . ' si attiva dopo il pagamento: per ora si configura una struttura alla volta.';
        if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) Support::json(['ok' => false, 'errore' => $avviso], 423);
        Support::flash($avviso, 'avviso');
        Support::redirect('/pannello');
    }
    return [$u, $acc, $p];
};

/**
 * Dopo un salvataggio dentro la procedura guidata si va al passo indicato dal
 * modulo — solo se è uno dei passi veri — altrimenti si resta sulla pagina.
 */
$dopo = function (array $p, string $restaQui): string {
    $passo = (string) ($_POST['dopo'] ?? '');
    return isset(MHW_PASSI[$passo]) ? '/pannello/' . $p['id'] . '/procedura/' . $passo : $restaQui;
};

/** Dall'editor aperto dentro la procedura si torna lì, con la stessa sezione aperta. */
$tornaSezione = function (array $p, int $sid, string $altrimenti): string {
    return (string) ($_POST['da'] ?? '') === 'procedura'
        ? '/pannello/' . $p['id'] . '/procedura/sezioni?apri=' . $sid . '#sez-' . $sid : $altrimenti;
};

/** Quello che serve all'editor di una sezione: titolo e campi nella lingua principale, luoghi. */
$datiSezione = function (array $p, array $s): array {
    $tr = Db::one('SELECT * FROM section_translations WHERE section_id = ? AND locale = ?', [$s['id'], $p['default_locale']]);
    $places = [];
    if (SectionCatalog::hasPlaces($s['kind'])) {
        foreach (Db::all('SELECT * FROM places WHERE section_id = ? ORDER BY position, id', [$s['id']]) as $pl) {
            $pl['tr'] = Db::one('SELECT * FROM place_translations WHERE place_id = ? AND locale = ?', [$pl['id'], $p['default_locale']])
                     ?: ['category' => '', 'description' => '', 'note' => '', 'badge' => ''];
            $places[] = $pl;
        }
    }
    // «Già in <struttura>»: luoghi e righe scritti nelle altre strutture dello stesso account.
    $aid = (int) $p['account_id'];
    return ['s' => $s, 'title' => $tr['title'] ?? '', 'dati' => json_decode((string) $s['data'], true) ?: [],
            'tdati' => json_decode((string) ($tr['data'] ?? ''), true) ?: [], 'places' => $places,
            'suggLuoghi' => Suggerimenti::luoghi($aid, (int) $p['id'], (int) $s['id'], $s['kind']),
            'suggRighe' => Suggerimenti::righe($aid, (int) $p['id'], (int) $s['id'], $s['kind'], $p['default_locale'])];
};

/** Una richiesta arrivata dal salvataggio automatico vuole JSON, non un redirect. */
$vuoleJson = fn(): bool => str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

/** Messaggio d'errore per l'utente: i RuntimeException sono già scritti per lui, il resto no. */
$messaggio = function (\Throwable $e, string $dove): string {
    if ($e instanceof \RuntimeException && !($e instanceof \PDOException)) return $e->getMessage();
    return 'Non è stato possibile salvare. Riprova tra poco (codice ' . Log::exception($e, $dove) . ').';
};

/** Le informazioni che ogni pagina di una struttura mostra in alto e nella colonna. */
$contesto = function (array $acc, array $p): array {
    $aid = (int) $acc['id'];
    $gov = Subscriptions::governingVersionId($aid);
    return [
        'prop' => $p, 'acc' => $acc, 'ent' => Entitlements::forAccount($aid),
        'sub' => Subscriptions::active($aid),
        'piano' => $gov ? Plans::version($gov) : null,
        'limite' => Entitlements::limit($aid, 'sections', 4),
        'attive' => Properties::activeCount((int) $p['id']),
    ];
};

// ------------------------------------------------------------------ le mie guide
$r->get('/pannello', function () use ($host) {
    [$u, $acc] = $host();
    $props = Db::all('SELECT * FROM properties WHERE account_id = ? ORDER BY id', [$acc['id']]);
    if (!$props) Support::redirect($acc['intended_package_version_id'] || Subscriptions::active((int) $acc['id']) ? '/pannello/nuova' : '/piano');
    foreach ($props as &$pr) $pr['online'] = Subscriptions::propertyOnline($pr);
    unset($pr);
    $gov = Subscriptions::governingVersionId((int) $acc['id']);
    View::out('host/properties', [
        'props' => $props, 'acc' => $acc, 'user' => $u, 'sub' => Subscriptions::active((int) $acc['id']),
        'piano' => $gov ? Plans::version($gov) : null,
        'maxProp' => Entitlements::limit((int) $acc['id'], 'properties', 1), 'nav' => 'guide',
        'bloccate' => Entitlements::lockedIds((int) $acc['id']),
        // Portfolio pagato e pieno: «Aggiungi una struttura» resta, e porta alla conferma col costo.
        'aggiungiPagando' => ($s = Subscriptions::active((int) $acc['id'])) && ($v = Plans::version((int) $s['package_version_id'])) && Plans::perProperty($v)
                             && (int) $s['quantity'] < (int) $v['max_quantity'],
    ], 'layout/cms');
});

/*
 * Una struttura nuova. Tre casi:
 *   - normale: nome e città, e se ce n'è già un'altra si può partire da quella
 *     («Crea da una struttura esistente», con Copia);
 *   - Portfolio scelto e non ancora pagato, una struttura già c'è: le altre (fino
 *     alla quantità scelta) nascono col solo nome e restano bloccate fino al pagamento;
 *   - Portfolio pagato e pieno: «Aggiungi una struttura» aggiunge una struttura
 *     all'abbonamento Stripe, dopo una conferma col costo.
 */
$r->any('/pannello/nuova', function () use ($host, $messaggio) {
    [$u, $acc] = $host();
    $aid = (int) $acc['id'];
    $sub = Subscriptions::active($aid);
    if (!$acc['intended_package_version_id'] && !$sub) Support::redirect('/piano');
    $max = Entitlements::limit($aid, 'properties', 1);
    $esistenti = Db::all('SELECT id, name, city FROM properties WHERE account_id = ? AND archived_at IS NULL ORDER BY id', [$aid]);
    $have = count($esistenti);
    $gov = Subscriptions::governingVersionId($aid);
    $pv = $gov ? Plans::version($gov) : null;
    $portfolio = $pv && Plans::perProperty($pv);
    $modo = 'normale';
    if ($portfolio && !$sub && !Subscriptions::latest($aid) && $have >= 1 && $have < $max) $modo = 'bloccata';
    elseif ($portfolio && $sub && $have >= $max) $modo = 'a-pagamento';
    // Le strutture da cui partire: solo quelle che si possono aprire.
    $bloccate = Entitlements::lockedIds($aid);
    $origini = $modo === 'normale' ? array_values(array_filter($esistenti, fn($x) => !in_array((int) $x['id'], $bloccate, true))) : [];
    $err = null;
    // Quanto costa una struttura in più: il prezzo annuo, e la parte che resta di quest'anno.
    $costo = null;
    if ($modo === 'a-pagamento') {
        $inizio = strtotime((string) $sub['current_period_start']) ?: time(); $fine = strtotime((string) $sub['current_period_end']) ?: time();
        $costo = ['anno' => (int) $pv['extra_price_cents'], 'ora' => CambioPiano::conguaglio(Plans::price($pv, (int) $sub['quantity']), Plans::price($pv, (int) $sub['quantity'] + 1), $inizio, $fine, time()),
                  'fine' => (string) $sub['current_period_end'], 'totale' => Plans::price($pv, (int) $sub['quantity'] + 1), 'quantita' => (int) $sub['quantity'] + 1,
                  'fuori' => (int) $sub['quantity'] + 1 > (int) $pv['max_quantity']];
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $nome = (string) ($_POST['name'] ?? '');
            if ($modo === 'bloccata') {
                $pid = Properties::create($aid, $nome, (string) ($_POST['city'] ?? ''), (string) $u['name']);
                Stats::funnelEvent('property_created');
                Support::flash(trim($nome) . ' creata. Si attiva dopo il pagamento: intanto completa e pubblica la prima.', 'avviso');
                Support::redirect('/pannello');
            }
            if ($modo === 'a-pagamento') {
                if (($_POST['conferma'] ?? '') !== '1') throw new RuntimeException('Spunta la conferma per aggiungere la struttura all\'abbonamento.');
                // Tutto quello che può far fallire la creazione si controlla PRIMA di toccare Stripe.
                if (trim($nome) === '') throw new RuntimeException('Scrivi il nome della struttura.');
                if (mb_strlen(trim($nome)) > 120) throw new RuntimeException('Il nome è troppo lungo.');
                if ($costo['fuori']) throw new RuntimeException('Hai raggiunto il numero massimo di strutture del Portfolio: scrivici.');
                if (!Stripe::enabled()) throw new RuntimeException('I pagamenti non sono attivi in questo momento: non possiamo aggiungere strutture all\'abbonamento. Riprova più tardi.');
                $st = CambioAbbonamento::stato($aid);
                if ($st['motivo'] !== '') throw new RuntimeException($st['motivo'] === CambioAbbonamento::STAFF ? 'Il tuo abbonamento non si modifica da qui: scrivici e aggiungiamo noi la struttura.' : $st['motivo']);
                if (!MHW\Fatturazione::completa($acc)) throw new RuntimeException('Prima di pagare la struttura in più servono i dati di fatturazione: compilali in Account & Fatturazione.');
                // Come ogni salita di piano (6H): la struttura nasce bloccata e si paga oggi la quota fino al rinnovo.
                $prev = CambioAbbonamento::preventivo($sub, $st['pv'], $pv, $costo['quantita']);
                $pid = Properties::create($aid, $nome, (string) ($_POST['city'] ?? ''), (string) $u['name'], 1);
                Stats::funnelEvent('property_created');
                Auth::audit('subscription.add_property', (int) $u['id'], ['property_id' => $pid, 'quantita' => $costo['quantita']]);
                if ($prev['conguaglio'] === 0) {
                    CambioAbbonamento::subito($sub, $pv, $costo['quantita']);
                    Support::flash(trim($nome) . ' aggiunta.');
                    Support::redirect('/pannello');
                }
                $o = CambioAbbonamento::ordine($acc, $sub, $pv, $costo['quantita'], $prev['conguaglio']);
                Support::redirect(Stripe::checkoutChange($o, CambioAbbonamento::descrizione($pv, (int) $sub['quantity'], $pv, $costo['quantita']), $acc, $u));
            }
            // Normale, con la copia facoltativa: struttura e copia nella stessa transazione.
            $origine = (int) ($_POST['origine'] ?? 0);
            if ($origine && !in_array($origine, array_map('intval', array_column($origini, 'id')), true)) throw new NotFound('Struttura di origine non trovata.');
            $pid = Db::tx(function () use ($aid, $u, $nome, $origine) {
                $pid = Properties::create($aid, $nome, (string) ($_POST['city'] ?? ''), (string) $u['name']);
                if ($origine) {
                    $lingua = (string) Db::val('SELECT default_locale FROM properties WHERE id = ?', [$origine], 'it');
                    MHW\Copia::esegui($aid, $origine, $pid, (array) ($_POST['copia'] ?? []), [], !empty($_POST['copia_aspetto']), !empty($_POST['copia_contatti']));
                    if ($lingua !== 'it') Properties::setDefaultLocale($aid, $pid, $lingua);
                }
                return $pid;
            });
            Db::update('properties', ['wizard_step' => 'arrivo'], 'id = :pid', ['pid' => $pid]);
            Stats::funnelEvent('property_created');
            if ($origine) Support::flash('Struttura creata partendo da ' . Db::val('SELECT name FROM properties WHERE id = ?', [$origine]) . '. Ora completa quello che è solo di questa struttura: indirizzo, check-in, Wi-Fi.');
            Support::redirect('/pannello/' . $pid . '/procedura/struttura');
        } catch (NotFound) { $err = 'Struttura di origine non trovata.'; }
        catch (\Throwable $e) { $err = $messaggio($e, 'nuova struttura'); }
    }
    // Il piano scelto (non ancora pagato), da ricordare in alto con «Cambia».
    $piano = !$sub && $acc['intended_package_version_id'] ? Plans::version((int) $acc['intended_package_version_id']) : null;
    View::out('host/new_property', ['err' => $err, 'have' => $have, 'max' => $max, 'nav' => 'guide', 'modo' => $modo, 'origini' => $origini,
        'costo' => $costo, 'pv' => $pv, 'piano' => $piano, 'quantita' => (int) ($acc['intended_quantity'] ?? 1)], 'layout/cms');
});

/* «Copia sezioni da…» su una struttura esistente (vedi Copia). */
$r->any('/pannello/{id}/copia', function (array $a) use ($mia, $contesto, $messaggio) {
    [$u, $acc, $p] = $mia((int) $a['id']);
    $aid = (int) $acc['id'];
    $bloccate = Entitlements::lockedIds($aid);
    $origini = array_values(array_filter(Db::all('SELECT id, name FROM properties WHERE account_id = ? AND id <> ? AND archived_at IS NULL ORDER BY id', [$aid, $p['id']]),
                                         fn($x) => !in_array((int) $x['id'], $bloccate, true)));
    if (!$origini) { Support::flash('Non c\'è un\'altra struttura da cui copiare.', 'avviso'); Support::redirect('/pannello/' . $p['id']); }
    $daId = (int) ($_POST['da'] ?? $_GET['da'] ?? $origini[0]['id']);
    $da = null; foreach ($origini as $o) if ((int) $o['id'] === $daId) $da = $o;
    $err = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $da) {
        try {
            $esito = MHW\Copia::esegui($aid, (int) $da['id'], (int) $p['id'], (array) ($_POST['copia'] ?? []), (array) ($_POST['esistenti'] ?? []),
                                       !empty($_POST['copia_aspetto']), !empty($_POST['copia_contatti']));
            Auth::audit('property.copy', (int) $u['id'], ['da' => (int) $da['id'], 'a' => (int) $p['id']] + $esito);
            $nomi = fn(array $k) => implode(', ', array_map(fn($x) => SectionCatalog::title($x, 'it'), $k));
            Support::flash('Copia da ' . $da['name'] . ' fatta.'
                . ($esito['copiate'] ? ' Copiate: ' . $nomi($esito['copiate']) . '.' : '')
                . ($esito['sostituite'] ? ' Sostituite: ' . $nomi($esito['sostituite']) . '.' : '')
                . ($esito['saltate'] ? ' Saltate, perché c\'erano già: ' . $nomi($esito['saltate']) . '.' : ''));
            Support::redirect('/pannello/' . $p['id']);
        } catch (\Throwable $e) { $err = $messaggio($e, 'copia'); }
    }
    View::out('host/copia', $contesto($acc, $p) + ['origini' => $origini, 'da' => $da, 'err' => $err,
        'proposta' => $da ? MHW\Copia::proposta((int) $da['id'], (int) $p['id']) : null, 'qui' => 'contenuti'], 'layout/cms');
});

$r->post('/pannello/{id}/elimina', function (array $a) use ($mia) {
    [$u, $acc, $p] = $mia((int) $a['id'], true);
    // Il nome si confronta senza badare a maiuscole e spazi doppi (il telefono mette la maiuscola da solo).
    $normale = fn(string $x) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($x)) ?? '');
    if ($normale((string) ($_POST['conferma'] ?? '')) !== $normale((string) $p['name'])) {
        Support::flash('Per eliminare scrivi il nome esatto della struttura.', 'err');
        Support::redirect(Entitlements::editable((int) $acc['id'], (int) $p['id']) ? '/pannello/' . $p['id'] . '/impostazioni' : '/pannello');
    }
    foreach (Db::all('SELECT id FROM media WHERE property_id = ?', [$p['id']]) as $m) Media::delete((int) $m['id'], (int) $acc['id']);
    Db::run('DELETE FROM properties WHERE id = ?', [$p['id']]);
    Auth::audit('property.delete', (int) $u['id'], ['name' => $p['name']]);
    Support::flash('Struttura eliminata, con la sua guida e il suo QR.');
    Support::redirect('/pannello');
});

// ---------------------------------------------------------------- contenuti
$r->get('/pannello/{id}', function (array $a) use ($mia, $contesto) {
    [$u, $acc, $p] = $mia((int) $a['id']);
    $sections = Db::all('SELECT * FROM sections WHERE property_id = ? ORDER BY is_core DESC, position, id', [$p['id']]);
    foreach ($sections as &$s) {
        $t = Db::one('SELECT * FROM section_translations WHERE section_id = ? AND locale = ?', [$s['id'], $p['default_locale']]);
        $s['title'] = $t['title'] ?? SectionCatalog::title($s['kind'], $p['default_locale']);
        $s['empty'] = SectionCatalog::isEmpty($s['kind'], json_decode((string) $s['data'], true) ?: [],
            json_decode((string) ($t['data'] ?? ''), true) ?: [],
            (int) Db::val('SELECT COUNT(*) FROM places WHERE section_id = ?', [$s['id']], 0));
    }
    unset($s);
    $presenti = array_column($sections, 'kind');
    $stats = Entitlements::can((int) $acc['id'], 'analytics') ? Stats::forProperty((int) $p['id']) : null;
    View::out('host/dashboard', $contesto($acc, $p) + [
        'user' => $u, 'sections' => $sections,
        'catalogo' => array_values(array_diff(SectionCatalog::selectable(), $presenti)),
        'pending' => $p['status'] === 'published' ? Guide::pendingChanges((int) $p['id']) : 1,
        'problemi' => Guide::problems((int) $acc['id'], (int) $p['id']),
        'online' => Subscriptions::propertyOnline($p), 'stats' => $stats, 'qui' => 'contenuti',
    ], 'layout/cms');
});

$r->post('/pannello/{id}/sezioni', function (array $a) use ($mia, $messaggio) {
    [, $acc, $p] = $mia((int) $a['id']);
    $torna = (string) ($_POST['torna'] ?? '') === 'procedura' ? '/pannello/' . $p['id'] . '/procedura/sezioni' : '/pannello/' . $p['id'];
    try {
        $sid = Properties::addSection((int) $acc['id'], (int) $p['id'], (string) ($_POST['kind'] ?? ''));
        Support::flash(SectionCatalog::nome((string) $_POST['kind']) . ' aggiunta: compilala qui sotto.');
        // Nella procedura «Aggiungi» apre subito l'editor sotto la card della sezione.
        Support::redirect($torna === '/pannello/' . $p['id'] ? '/pannello/' . $p['id'] . '/sezioni/' . $sid
                          : $torna . '?apri=' . $sid . '#sez-' . $sid);
    } catch (LimitReached $e) {
        Support::flash($e->getMessage(), 'limite');
    } catch (\Throwable $e) {
        Support::flash($messaggio($e, 'aggiungi sezione'), 'err');
    }
    Support::redirect($torna);
});

$r->post('/pannello/{id}/sezioni/{sid}/azione', function (array $a) use ($mia, $messaggio) {
    [, $acc, $p] = $mia((int) $a['id']);
    $sid = (int) $a['sid'];
    $torna = (string) ($_POST['torna'] ?? '') === 'procedura' ? '/pannello/' . $p['id'] . '/procedura/sezioni' : '/pannello/' . $p['id'];
    try {
        match ((string) ($_POST['fai'] ?? '')) {
            'attiva' => Properties::setActive((int) $acc['id'], (int) $p['id'], $sid, true),
            'disattiva' => Properties::setActive((int) $acc['id'], (int) $p['id'], $sid, false),
            'su' => Properties::move((int) $p['id'], $sid, 'su'),
            'giu' => Properties::move((int) $p['id'], $sid, 'giu'),
            'elimina' => Properties::deleteSection((int) $p['id'], $sid),
            default => throw new RuntimeException('Azione sconosciuta.'),
        };
    } catch (LimitReached $e) {
        Support::flash($e->getMessage(), 'limite');
    } catch (NotFound $e) {
        http_response_code(404); View::out('pub/404', []);
    } catch (\Throwable $e) {
        Support::flash($messaggio($e, 'azione sezione'), 'err');
    }
    Support::redirect($torna);
});

// ---------------------------------------------------------- editor di sezione
$r->any('/pannello/{id}/sezioni/{sid}', function (array $a) use ($mia, $contesto, $vuoleJson, $messaggio, $dopo, $tornaSezione, $datiSezione) {
    [, $acc, $p] = $mia((int) $a['id']);
    try { $s = Properties::section((int) $p['id'], (int) $a['sid']); }
    catch (NotFound) { http_response_code(404); View::out('pub/404', []); }
    $aid = (int) $acc['id'];
    $err = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $cosa = (string) ($_POST['azione'] ?? 'salva');
            if ($cosa === 'togli-foto') {
                if ($s['media_id']) Media::rilascia((int) $s['media_id'], $aid);
                Db::update('sections', ['media_id' => null], 'id = :sid', ['sid' => $s['id']]);
            } elseif ($cosa === 'togli-pdf') {
                if ($s['pdf_media_id']) Media::rilascia((int) $s['pdf_media_id'], $aid);
                Db::update('sections', ['pdf_media_id' => null], 'id = :sid', ['sid' => $s['id']]);
            } else {
                $post = Properties::saveRowMedia($aid, (int) $p['id'], $s['kind'], $_POST, $_FILES);
                Properties::saveSection((int) $p['id'], (int) $s['id'], $p['default_locale'], $post, true);
                $prima = json_decode((string) $s['data'], true) ?: [];
                $ora = json_decode((string) Db::val('SELECT data FROM sections WHERE id = ?', [$s['id']], ''), true) ?: [];
                // «Ripeti nel 2027» (eventi, 6G): dopo il salvataggio, quella riga riparte l'anno dopo, senza locandina.
                $ripeti = (string) ($_POST['ripeti'] ?? '');
                if ($s['kind'] === 'events' && $ripeti !== '') {
                    foreach ((array) ($ora['events'] ?? []) as $i => $ev) {
                        if (!is_array($ev) || (string) ($ev['id'] ?? '') !== $ripeti) continue;
                        $ora['events'][$i] = MHW\Eventi::ripeti($ev, MHW\Eventi::oggi());
                        Db::update('sections', ['data' => json_encode($ora, JSON_UNESCAPED_UNICODE)], 'id = :sid', ['sid' => $s['id']]);   // la locandina tolta la libera cleanRowMedia, qui sotto
                        $ripetuto = 'Date spostate al ' . substr((string) ($ora['events'][$i]['date_from'] ?? ''), 0, 4) . ': controllale, carica la nuova locandina e pubblica.';
                        break;
                    }
                }
                Properties::cleanRowMedia($aid, $s['kind'], $prima, $ora);
                // Il link di Maps di «Come arrivare» dà le coordinate della struttura (per i minuti a piedi dei luoghi).
                if ($s['kind'] === 'arrival' && ($ora['maps_url'] ?? '') !== ($prima['maps_url'] ?? '') && Migrator::columnExists('properties', 'lat')) {
                    $m = Mappe::leggi((string) ($ora['maps_url'] ?? ''));
                    Db::update('properties', ['lat' => $m['lat'], 'lng' => $m['lng']], 'id = :pid', ['pid' => $p['id']]);
                }
                if (($_FILES['foto']['error'] ?? 4) !== UPLOAD_ERR_NO_FILE) {
                    if (!Entitlements::can($aid, 'photos')) throw new RuntimeException('Le foto nelle sezioni sono disponibili con il piano Plus.');
                    $mid = Media::storeImage($_FILES['foto'], $aid, (int) $p['id'], (string) ($_POST['title'] ?? ''), 'section');
                    if ($s['media_id']) Media::rilascia((int) $s['media_id'], $aid);
                    Db::update('sections', ['media_id' => $mid], 'id = :sid', ['sid' => $s['id']]);
                }
                if (($_FILES['pdf']['error'] ?? 4) !== UPLOAD_ERR_NO_FILE) {
                    if (!Entitlements::can($aid, 'pdf')) throw new RuntimeException('I PDF nelle sezioni sono disponibili con il piano Plus.');
                    $mid = Media::storePdf($_FILES['pdf'], $aid, (int) $p['id'], (string) ($_POST['title'] ?? ''));
                    if ($s['pdf_media_id']) Media::rilascia((int) $s['pdf_media_id'], $aid);
                    Db::update('sections', ['pdf_media_id' => $mid], 'id = :sid', ['sid' => $s['id']]);
                }
            }
            if ($vuoleJson()) Support::json(['ok' => true, 'salvato' => Support::now()]);
            if (isset($ripetuto)) { Support::flash($ripetuto); Support::redirect('/pannello/' . $p['id'] . '/sezioni/' . $s['id']); }
            Support::flash('Salvato. Ricordati di pubblicare quando hai finito.');
            Support::redirect($tornaSezione($p, (int) $s['id'], $dopo($p, '/pannello/' . $p['id'] . '/sezioni/' . $s['id'])));
        } catch (\Throwable $e) {
            $err = $messaggio($e, 'salva sezione');
            if ($vuoleJson()) Support::json(['ok' => false, 'errore' => $err], 422);
        }
        $s = Properties::section((int) $p['id'], (int) $s['id']);
    }

    View::out('host/section', $contesto($acc, $p) + $datiSezione($p, $s) + [
        'err' => $err,
        'modifica' => (int) ($_GET['luogo'] ?? 0), 'procedura' => (string) ($_GET['da'] ?? '') === 'procedura',
        'qui' => 'contenuti',
    ], 'layout/cms');
});

$r->post('/pannello/{id}/sezioni/{sid}/luogo', function (array $a) use ($mia, $messaggio, $tornaSezione, $vuoleJson) {
    [, $acc, $p] = $mia((int) $a['id']);
    $aid = (int) $acc['id'];
    $torna = $tornaSezione($p, (int) $a['sid'], '/pannello/' . $p['id'] . '/sezioni/' . (int) $a['sid']);
    try {
        $plid = (int) ($_POST['place_id'] ?? 0) ?: null;
        $in = $_POST;
        // Dal link di Google Maps: coordinate, e se mancano il nome e i minuti a piedi (una stima).
        $mapsUrl = trim((string) ($in['maps_url'] ?? ''));
        $primaUrl = $plid ? (string) Db::val('SELECT maps_url FROM places WHERE id = ?', [$plid], '') : '';
        if (Migrator::columnExists('places', 'lat') && ($mapsUrl !== $primaUrl || !$plid)) {
            $m = Mappe::leggi($mapsUrl);
            $in['lat'] = $m['lat']; $in['lng'] = $m['lng'];
            if (trim((string) ($in['name'] ?? '')) === '' && $m['name'] !== '') $in['name'] = $m['name'];
            [$plat, $plng] = Mappe::struttura($p);
            if ((int) ($in['walk_minutes'] ?? 0) === 0 && ($stima = Mappe::minutiAPiedi($plat, $plng, $m['lat'], $m['lng']))) $in['walk_minutes'] = $stima;
        }
        $plid = Properties::savePlace($aid, (int) $p['id'], (int) $a['sid'], $plid, $p['default_locale'], true, $in);
        if (($_FILES['foto']['error'] ?? 4) !== UPLOAD_ERR_NO_FILE) {
            if (!Entitlements::can($aid, 'photos')) throw new RuntimeException('Le foto dei luoghi sono disponibili con il piano Plus.');
            $mid = Media::storeImage($_FILES['foto'], $aid, (int) $p['id'], (string) ($_POST['name'] ?? ''), 'place');
            $prima = Db::val('SELECT media_id FROM places WHERE id = ?', [$plid]);
            if ($prima) Media::rilascia((int) $prima, $aid);
            Db::update('places', ['media_id' => $mid], 'id = :pid', ['pid' => $plid]);
        }
        if ($vuoleJson()) Support::json(['ok' => true, 'salvato' => Support::now()]);
        Support::flash('Luogo salvato.');
    } catch (NotFound) { http_response_code(404); View::out('pub/404', []); }
    catch (\Throwable $e) {
        if ($vuoleJson()) Support::json(['ok' => false, 'errore' => $messaggio($e, 'salva luogo')], 422);
        // Il modulo torna com'era, con l'errore accanto al campo: niente di quello che si è scritto va perso.
        $err = $messaggio($e, 'salva luogo');
        $_SESSION['luogo_bozza'] = ['sid' => (int) $a['sid'], 'place_id' => (int) ($_POST['place_id'] ?? 0), 'errore' => $err,
            'campo' => trim((string) ($_POST['name'] ?? '')) === '' && str_contains($err, 'nome') ? 'name' : '',
            'in' => array_map(fn($v) => is_string($v) ? mb_substr($v, 0, 2000) : '', array_intersect_key($_POST, array_flip(
                ['name', 'address', 'maps_url', 'phone', 'website', 'booking_url', 'walk_minutes', 'drive_minutes', 'badge_tone',
                 'category_choice', 'category', 'badge_choice', 'badge', 'description', 'note'])))];
        $torna .= (str_contains($torna, '?') ? '&' : '?') . 'luogo=' . (int) ($_POST['place_id'] ?? 0) . '#luogo';
    }
    Support::redirect($torna);
});

/* «Già in <struttura>»: un luogo o una riga di un'altra struttura dello stesso
   account, copiati qui con un tocco (Suggerimenti). Si torna con il luogo aperto,
   da controllare (i minuti in auto restano vuoti). */
$r->post('/pannello/{id}/sezioni/{sid}/da-altra', function (array $a) use ($mia, $messaggio, $tornaSezione) {
    [, $acc, $p] = $mia((int) $a['id']);
    $sid = (int) $a['sid'];
    $torna = $tornaSezione($p, $sid, '/pannello/' . $p['id'] . '/sezioni/' . $sid);
    $aggiungi = fn(string $q) => $torna . (str_contains($torna, '?') ? '&' : '?') . $q;
    try {
        if (($plid = (int) ($_POST['luogo'] ?? 0)) > 0) {
            $nuovo = Suggerimenti::copiaLuogo((int) $acc['id'], $p, $sid, $plid);
            Support::flash('Luogo aggiunto: controlla i minuti e salva se cambi qualcosa.');
            // Il luogo nuovo si apre nel modulo; nella procedura l'ancora resta quella della sezione.
            Support::redirect(str_contains($torna, '#') ? str_replace('#', '&luogo=' . $nuovo . '#', $torna) : $aggiungi('luogo=' . $nuovo) . '#luogo');
        }
        $parti = explode('|', (string) ($_POST['riga'] ?? ''), 3);
        if (count($parti) !== 3) throw new NotFound('Riga non trovata.');
        Suggerimenti::copiaRiga((int) $acc['id'], $p, $sid, $parti[0], (int) $parti[1], $parti[2]);
        Support::flash('Aggiunto in fondo all\'elenco: controllalo.');
        if (!str_contains($torna, '#')) $torna .= '#rip-' . preg_replace('/[^a-z0-9]+/i', '-', $parti[0]);
    } catch (NotFound) { http_response_code(404); View::out('pub/404', []); }
    catch (\Throwable $e) { Support::flash($messaggio($e, 'copia da altra struttura'), 'err'); }
    Support::redirect($torna);
});

/* Il link di Google Maps incollato nella scheda di un luogo: nome, coordinate e
   minuti a piedi stimati, per compilare il modulo mentre si scrive. */
$r->post('/pannello/{id}/mappe', function (array $a) use ($mia) {
    [, $acc, $p] = $mia((int) $a['id']);
    // Ogni richiesta può far uscire il server verso Google: un tetto per account.
    if (!MHW\RateLimit::hit('mappe:' . (int) $acc['id'], 120, 3600)) Support::json(['ok' => false, 'name' => '', 'lat' => null, 'lng' => null, 'walk_minutes' => null], 429);
    $m = Mappe::leggi((string) ($_POST['url'] ?? ''));
    [$plat, $plng] = Mappe::struttura($p);
    Support::json(['ok' => $m['lat'] !== null || $m['name'] !== '', 'name' => $m['name'], 'lat' => $m['lat'], 'lng' => $m['lng'],
                   'walk_minutes' => Mappe::minutiAPiedi($plat, $plng, $m['lat'], $m['lng'])]);
});

$r->post('/pannello/{id}/sezioni/{sid}/luogo/{plid}/azione', function (array $a) use ($mia, $messaggio, $tornaSezione) {
    [, $acc, $p] = $mia((int) $a['id']);
    $sid = (int) $a['sid']; $plid = (int) $a['plid'];
    try {
        Properties::section((int) $p['id'], $sid);
        $pl = Db::one('SELECT * FROM places WHERE id = ? AND section_id = ?', [$plid, $sid]);
        if (!$pl) throw new NotFound('Luogo non trovato.');
        match ((string) ($_POST['fai'] ?? '')) {
            'su' => Properties::movePlace((int) $p['id'], $sid, $plid, 'su'),
            'giu' => Properties::movePlace((int) $p['id'], $sid, $plid, 'giu'),
            'elimina' => (function () use ($p, $sid, $plid, $pl, $acc) {
                if ($pl['media_id']) Media::rilascia((int) $pl['media_id'], (int) $acc['id']);
                Properties::deletePlace((int) $p['id'], $sid, $plid);
            })(),
            'togli-foto' => (function () use ($pl, $acc) {
                if ($pl['media_id']) Media::rilascia((int) $pl['media_id'], (int) $acc['id']);
                Db::update('places', ['media_id' => null], 'id = :pid', ['pid' => $pl['id']]);
            })(),
            default => throw new RuntimeException('Azione sconosciuta.'),
        };
    } catch (NotFound) { http_response_code(404); View::out('pub/404', []); }
    catch (\Throwable $e) { Support::flash($messaggio($e, 'azione luogo'), 'err'); }
    Support::redirect($tornaSezione($p, $sid, '/pannello/' . $p['id'] . '/sezioni/' . $sid));
});

// ------------------------------------------------------------------- lingue
$r->any('/pannello/{id}/lingue', function (array $a) use ($mia, $contesto, $messaggio, $dopo, $vuoleJson) {
    [, $acc, $p] = $mia((int) $a['id']);
    $err = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            Properties::setLocales((int) $acc['id'], (int) $p['id'], (array) ($_POST['locali'] ?? []));
            if ($vuoleJson()) Support::json(['ok' => true]);
            Support::flash('Lingue aggiornate.');
            Support::redirect($dopo($p, '/pannello/' . $p['id'] . '/lingue'));
        } catch (\Throwable $e) {
            $err = $messaggio($e, 'lingue');
            if ($vuoleJson()) Support::json(['ok' => false, 'errore' => $err], 422);
        }
    }
    $attive = array_column(Db::all('SELECT locale FROM property_locales WHERE property_id = ?', [$p['id']]), 'locale');
    // Quanto è tradotto, campo per campo («English 60%»).
    $copertura = [];
    foreach ($attive as $l) $copertura[$l] = Properties::translationCoverage((int) $p['id'], $l);
    $spiegazione = !empty($_SESSION['traduzioni_spiegazione']); unset($_SESSION['traduzioni_spiegazione']);
    $daControllare = [];
    foreach (Db::all('SELECT locale, COUNT(*) AS n FROM translation_suggestions WHERE property_id = ? GROUP BY locale', [$p['id']]) as $x) $daControllare[$x['locale']] = (int) $x['n'];
    View::out('host/languages', $contesto($acc, $p) + [
        'err' => $err, 'lingueAttive' => $attive, 'consentite' => Entitlements::allowedLocales((int) $acc['id']),
        'tutte' => Config::get('locales'), 'copertura' => $copertura, 'qui' => 'lingue',
        'trad' => ['piano' => Traduttore::nelPiano((int) $acc['id']), 'acceso' => !empty($p['translation_suggest']), 'fino' => Traduttore::omaggioFino((int) $acc['id']),
                   'finito' => Traduttore::omaggioFinito((int) $acc['id']), 'spiegazione' => $spiegazione, 'daControllare' => $daControllare],
    ], 'layout/cms');
});

/* Traduzioni suggerite (Traduttore): l'interruttore della struttura. La prima
   accensione nell'account fa partire l'anno in omaggio e mostra la spiegazione. */
$r->post('/pannello/{id}/lingue/suggerite', function (array $a) use ($mia, $messaggio) {
    [, $acc, $p] = $mia((int) $a['id']);
    try {
        $acceso = (string) ($_POST['acceso'] ?? '') === '1';
        if (Traduttore::interruttore((int) $acc['id'], (int) $p['id'], $acceso)) $_SESSION['traduzioni_spiegazione'] = true;
        Support::flash($acceso ? 'Traduzioni suggerite accese per questa struttura.' : 'Traduzioni suggerite spente. Quelle che hai approvato restano.');
    } catch (\Throwable $e) { Support::flash($messaggio($e, 'traduzioni suggerite'), 'err'); }
    Support::redirect('/pannello/' . $p['id'] . '/lingue#suggerite');
});

/* Le suggerite di una lingua: chiederle, approvarle (anche tutte), aprirle per
   correggerle, scartarle, rifarle quando il testo originale è cambiato. */
$r->post('/pannello/{id}/lingue/{loc}/suggerite', function (array $a) use ($mia, $messaggio) {
    [, $acc, $p] = $mia((int) $a['id']);
    $loc = (string) $a['loc'];
    $attive = array_column(Db::all('SELECT locale FROM property_locales WHERE property_id = ?', [$p['id']]), 'locale');
    if ($loc === $p['default_locale'] || !in_array($loc, $attive, true) || !in_array($loc, Entitlements::allowedLocales((int) $acc['id']), true)) {
        Support::redirect('/pannello/' . $p['id'] . '/lingue');
    }
    $torna = '/pannello/' . $p['id'] . '/lingue/' . $loc;
    $ancora = '#suggerite';
    try {
        if (!empty($_POST['suggerisci'])) {
            [$n, $stop] = Traduttore::suggerisci((int) $acc['id'], $p, $loc);
            if ($n) Support::flash(($n === 1 ? '1 traduzione suggerita' : "$n traduzioni suggerite") . ': controllale una per una e approvale.' . ($stop !== '' ? ' ' . $stop : ''), $stop !== '' ? 'avviso' : 'ok');
            else Support::flash($stop !== '' ? $stop : 'Non manca niente da suggerire.', $stop !== '' ? 'err' : 'ok');
        } elseif (($id = (int) ($_POST['rifai'] ?? 0)) > 0) {
            $s = Db::one('SELECT * FROM translation_suggestions WHERE id = ? AND property_id = ? AND locale = ?', [$id, $p['id'], $loc]);
            if ($s) {
                [$n, $stop] = Traduttore::suggerisci((int) $acc['id'], $p, $loc, [$s['target_type'] . ':' . $s['target_id'] . ':' . $s['field_path']]);
                Support::flash($n ? 'Suggerita rifatta sul testo nuovo: controllala.' : ($stop ?: 'Non c\'era niente da rifare.'), $n ? 'ok' : 'err');
            }
        } elseif (!empty($_POST['tutte'])) {
            $fatte = 0;
            foreach (Traduttore::suggerite($p, $loc) as $s) if (!$s['da_rifare'] && Traduttore::approva($p, $loc, (int) $s['id']) === '') $fatte++;
            Support::flash($fatte ? ($fatte === 1 ? '1 traduzione approvata' : "$fatte traduzioni approvate") . ': ora gli ospiti le vedono.' : 'Niente da approvare.');
            $ancora = '';
        } elseif (($id = (int) ($_POST['approva'] ?? $_POST['modifica'] ?? 0)) > 0) {
            $s = Db::one('SELECT * FROM translation_suggestions WHERE id = ? AND property_id = ?', [$id, $p['id']]);
            $no = Traduttore::approva($p, $loc, $id);
            if ($no !== '') Support::flash($no, 'err');
            elseif (isset($_POST['modifica'])) {
                Support::flash('Approvata: ora correggila nel campo e salva.');
                if ($s) $ancora = '#c-' . md5($s['target_type'] . ':' . $s['target_id'] . ':' . $s['field_path']);
            } else Support::flash('Traduzione approvata: ora gli ospiti la vedono.');
        } elseif (($id = (int) ($_POST['scarta'] ?? 0)) > 0) {
            Traduttore::scarta($p, $loc, $id);
            Support::flash('Suggerita scartata.');
        }
    } catch (\Throwable $e) { Support::flash($messaggio($e, 'traduzioni suggerite'), 'err'); }
    Support::redirect($torna . $ancora);
});

$r->any('/pannello/{id}/lingue/{loc}', function (array $a) use ($mia, $contesto, $messaggio, $vuoleJson) {
    [, $acc, $p] = $mia((int) $a['id']);
    $loc = (string) $a['loc'];
    $attive = array_column(Db::all('SELECT locale FROM property_locales WHERE property_id = ?', [$p['id']]), 'locale');
    if ($loc === $p['default_locale']) Support::redirect('/pannello/' . $p['id']);
    if (!in_array($loc, $attive, true) || !in_array($loc, Entitlements::allowedLocales((int) $acc['id']), true)) {
        Support::flash('Prima attiva questa lingua: deve essere compresa nel tuo piano.', 'err');
        Support::redirect('/pannello/' . $p['id'] . '/lingue');
    }
    $err = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            foreach ((array) ($_POST['s'] ?? []) as $sid => $campi) {
                Properties::saveSection((int) $p['id'], (int) $sid, $loc, (array) $campi, false);
            }
            foreach ((array) ($_POST['pl'] ?? []) as $plid => $campi) {
                $sid = (int) Db::val('SELECT section_id FROM places WHERE id = ?', [(int) $plid]);
                if ($sid) Properties::savePlace((int) $acc['id'], (int) $p['id'], $sid, (int) $plid, $loc, false, (array) $campi);
            }
            if ($vuoleJson()) Support::json(['ok' => true]);
            Support::flash('Traduzione salvata.');
            Support::redirect('/pannello/' . $p['id'] . '/lingue/' . $loc);
        } catch (NotFound) { http_response_code(404); View::out('pub/404', []); }
        catch (\Throwable $e) { $err = $messaggio($e, 'traduzione'); }
    }
    $sections = [];
    foreach (Db::all('SELECT * FROM sections WHERE property_id = ? AND is_active = 1 ORDER BY is_core DESC, position, id', [$p['id']]) as $s) {
        $orig = Db::one('SELECT * FROM section_translations WHERE section_id = ? AND locale = ?', [$s['id'], $p['default_locale']]);
        $trad = Db::one('SELECT * FROM section_translations WHERE section_id = ? AND locale = ?', [$s['id'], $loc]);
        $s['orig'] = ['title' => $orig['title'] ?? '', 'data' => json_decode((string) ($orig['data'] ?? ''), true) ?: []];
        $s['trad'] = ['title' => $trad['title'] ?? '', 'data' => json_decode((string) ($trad['data'] ?? ''), true) ?: []];
        $s['places'] = [];
        if (SectionCatalog::hasPlaces($s['kind'])) {
            foreach (Db::all('SELECT * FROM places WHERE section_id = ? ORDER BY position, id', [$s['id']]) as $pl) {
                $pl['orig'] = Db::one('SELECT * FROM place_translations WHERE place_id = ? AND locale = ?', [$pl['id'], $p['default_locale']]) ?: [];
                $pl['trad'] = Db::one('SELECT * FROM place_translations WHERE place_id = ? AND locale = ?', [$pl['id'], $loc]) ?: [];
                $s['places'][] = $pl;
            }
        }
        $sections[] = $s;
    }
    // Le traduzioni suggerite: quelle da controllare, quante se ne possono ancora chiedere, e se si può.
    $campiT = Traduttore::campi($p, $loc);
    $sugg = Traduttore::suggerite($p, $loc, $campiT);
    $daSuggerire = count(array_filter($campiT, fn($c, $k) => $c['tradotto'] === '' && (!isset($sugg[$k]) || $sugg[$k]['da_rifare']), ARRAY_FILTER_USE_BOTH));
    View::out('host/translate', $contesto($acc, $p) + [
        'loc' => $loc, 'nome' => Config::get('locales')[$loc] ?? $loc, 'sections' => $sections, 'err' => $err, 'qui' => 'lingue',
        'sugg' => $sugg, 'daSuggerire' => $daSuggerire, 'perche' => Traduttore::perche((int) $acc['id'], $p),
        'nelPiano' => Traduttore::nelPiano((int) $acc['id']),
    ], 'layout/cms');
});

// ------------------------------------------------------------------- aspetto
$r->any('/pannello/{id}/aspetto', function (array $a) use ($mia, $contesto, $messaggio, $vuoleJson, $dopo) {
    [, $acc, $p] = $mia((int) $a['id']);
    $aid = (int) $acc['id'];
    $err = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $cosa = (string) ($_POST['azione'] ?? 'salva');
            $campi = ['logo' => ['logo_media_id', 'logo', 'Il logo'], 'cover' => ['cover_media_id', 'cover', 'La foto di copertina'],
                      'profile' => ['profile_media_id', 'profile_image', 'La foto profilo']];
            if (str_starts_with($cosa, 'togli-')) {
                $quale = substr($cosa, 6);
                if (!isset($campi[$quale])) throw new RuntimeException('Azione sconosciuta.');
                $col = $campi[$quale][0];
                if ($p[$col]) Media::rilascia((int) $p[$col], $aid);
                Db::update('properties', [$col => null], 'id = :pid', ['pid' => $p['id']]);
            } else {
                $pal = (string) ($_POST['palette'] ?? $p['palette']);
                $tono = (string) ($_POST['text_tone'] ?? $p['text_tone']);
                if (!Palette::exists($pal)) throw new RuntimeException('Palette sconosciuta.');
                // Un tono che non passa il controllo di contrasto non si salva.
                if (!Palette::readable($pal, $tono)) throw new RuntimeException('Con questa palette il tema scelto non è abbastanza leggibile: scegli l\'altro.');
                if (!Entitlements::can($aid, 'palette')) $pal = Palette::DEFAULT;
                Db::update('properties', ['palette' => $pal, 'text_tone' => $tono], 'id = :pid', ['pid' => $p['id']]);
                foreach ($campi as $input => [$col, $feature, $nome]) {
                    if (($_FILES[$input]['error'] ?? 4) === UPLOAD_ERR_NO_FILE) continue;
                    if (!Entitlements::can($aid, $feature)) throw new RuntimeException("$nome non fa parte del tuo piano.");
                    $mid = Media::storeImage($_FILES[$input], $aid, (int) $p['id'], $p['name'], $input);
                    if ($p[$col]) Media::rilascia((int) $p[$col], $aid);
                    Db::update('properties', [$col => $mid], 'id = :pid', ['pid' => $p['id']]);
                }
            }
            if ($vuoleJson()) Support::json(['ok' => true]);
            Support::flash('Aspetto salvato.');
            Support::redirect($dopo($p, '/pannello/' . $p['id'] . '/aspetto'));
        } catch (\Throwable $e) {
            $err = $messaggio($e, 'aspetto');
            if ($vuoleJson()) Support::json(['ok' => false, 'errore' => $err], 422);
        }
        $p = Db::one('SELECT * FROM properties WHERE id = ?', [$p['id']]);
    }
    $palette = [];
    foreach (Palette::all() as $code => $nome) $palette[$code] = ['nome' => $nome, 'dati' => Palette::get($code), 'toni' => Palette::tones($code), 'css' => Palette::css($code)];
    View::out('host/appearance', $contesto($acc, $p) + ['err' => $err, 'palette' => $palette, 'qui' => 'aspetto'], 'layout/cms');
});

// -------------------------------------------------------------- impostazioni
$r->any('/pannello/{id}/impostazioni', function (array $a) use ($mia, $contesto, $messaggio, $dopo, $vuoleJson) {
    [, $acc, $p] = $mia((int) $a['id']);
    $err = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $ora = fn(string $v, string $d) => preg_match('/^([01]?\d|2[0-3])[:.][0-5]\d$/', trim($v)) ? str_replace('.', ':', trim($v)) : $d;
            $dati = [
                'name' => mb_substr(trim((string) $_POST['name']), 0, 120),
                'city' => mb_substr(trim((string) ($_POST['city'] ?? '')), 0, 120),
                'region' => mb_substr(trim((string) ($_POST['region'] ?? '')), 0, 120),
                'checkin_from' => $ora((string) ($_POST['checkin_from'] ?? ''), $p['checkin_from']),
                'checkout_by' => $ora((string) ($_POST['checkout_by'] ?? ''), $p['checkout_by']),
            ];
            // I campi nuovi della struttura (dalla 008), solo se il modulo li manda.
            if (array_key_exists('property_type', $_POST)) $dati['property_type'] = isset(Properties::TIPOLOGIE[(string) $_POST['property_type']]) ? (string) $_POST['property_type'] : '';
            // Con «Altro» si scrive che tipo di struttura è (dalla 015); con le altre tipologie si svuota.
            if (array_key_exists('property_type_other', $_POST) && Migrator::columnExists('properties', 'property_type_other')) {
                $dati['property_type_other'] = ($dati['property_type'] ?? $p['property_type']) === 'altro' ? mb_substr(trim((string) $_POST['property_type_other']), 0, 60) : '';
            }
            foreach (['address' => 255, 'postal_code' => 10, 'cin' => 40] as $campo => $max) {
                if (array_key_exists($campo, $_POST)) $dati[$campo] = mb_substr(trim((string) $_POST[$campo]), 0, $max);
            }
            if (array_key_exists('beds', $_POST)) $dati['beds'] = max(0, min(999, (int) $_POST['beds']));
            // Un modulo vecchio (senza contatti multipli) scrive ancora i tre campi dell'host.
            foreach (['host_name' => 120, 'host_phone' => 40, 'host_whatsapp' => 40] as $campo => $max) {
                if (array_key_exists($campo, $_POST)) $dati[$campo] = mb_substr(trim((string) $_POST[$campo]), 0, $max);
            }
            if ($dati['name'] === '') throw new RuntimeException('Scrivi il nome della struttura.');
            Db::update('properties', $dati, 'id = :pid', ['pid' => $p['id']]);
            if (isset($_POST['contacts']) && is_array($_POST['contacts'])) Properties::saveContacts((int) $p['id'], $_POST['contacts']);
            elseif (array_key_exists('host_name', $_POST)) {
                Properties::saveContacts((int) $p['id'], array_map(fn($c) => $c + ['id' => ''],
                    Conversione::contatti((string) $dati['host_name'], (string) ($dati['host_phone'] ?? ''), (string) ($dati['host_whatsapp'] ?? ''))));
            }
            if (array_key_exists('address', $dati)) Properties::fillArrivalAddress((int) $p['id']);
            // La lingua in cui si scrive la guida (primo passo della procedura).
            $lingua = (string) ($_POST['default_locale'] ?? '');
            if ($lingua !== '' && $lingua !== $p['default_locale']) Properties::setDefaultLocale((int) $acc['id'], (int) $p['id'], $lingua);
            if ($vuoleJson()) Support::json(['ok' => true]);
            Support::flash('Impostazioni salvate.');
            Support::redirect($dopo($p, '/pannello/' . $p['id'] . '/impostazioni'));
        } catch (\Throwable $e) {
            $err = $messaggio($e, 'impostazioni');
            if ($vuoleJson()) Support::json(['ok' => false, 'errore' => $err], 422);
        }
    }
    View::out('host/settings', $contesto($acc, $p) + ['err' => $err, 'qui' => 'impostazioni'], 'layout/cms');
});

/* Dopo il soggiorno: recensioni, prenotazione diretta, firma della guida.
   Tutto facoltativo: nel commiato compare solo quello che è compilato. */
$r->post('/pannello/{id}/dopo-il-soggiorno', function (array $a) use ($mia) {
    [$u, $acc, $p] = $mia((int) $a['id']);
    if (!Migrator::columnExists('properties', 'review_google')) Support::redirect('/pannello/' . $p['id'] . '/impostazioni');
    $url = fn(string $k) => Support::safeUrl(mb_substr(trim((string) ($_POST[$k] ?? '')), 0, 500));
    $dati = ['review_google' => $url('review_google'), 'review_booking' => $url('review_booking'), 'review_airbnb' => $url('review_airbnb'),
             'review_other' => $url('review_other'), 'direct_url' => $url('direct_url'),
             'direct_code' => mb_substr(preg_replace('/\s+/', '', (string) ($_POST['direct_code'] ?? '')), 0, 60),
             // La firma si nasconde solo se il piano lo comprende (Plus, Portfolio): controllato qui, non solo nel modulo.
             'hide_branding' => !empty($_POST['hide_branding']) && Entitlements::can((int) $acc['id'], 'hide_branding') ? 1 : 0];
    $scartati = array_filter(['review_google', 'review_booking', 'review_airbnb', 'review_other', 'direct_url'],
                             fn($k) => trim((string) ($_POST[$k] ?? '')) !== '' && $dati[$k] === '');
    Db::update('properties', $dati, 'id = :pid', ['pid' => $p['id']]);
    Support::flash($scartati ? 'Salvato, ma ' . count($scartati) . ' link non erano indirizzi web validi (cominciano con https://) e sono rimasti vuoti.'
                             : 'Salvato. Lo vedranno gli ospiti nel commiato quando pubblichi.', $scartati ? 'avviso' : 'ok');
    Support::redirect('/pannello/' . $p['id'] . '/impostazioni#dopo-il-soggiorno');
});

// ---------------------------------------------------------- procedura guidata
$r->get('/pannello/{id}/procedura/{passo}', function (array $a) use ($mia, $contesto, $datiSezione) {
    [$u, $acc, $p] = $mia((int) $a['id']);
    $passo = (string) $a['passo'];
    // Gli indirizzi della procedura a sette passi portano al passo nuovo che li contiene.
    if (isset(MHW_PASSI_VECCHI[$passo])) {
        header('Location: ' . Support::url('/pannello/' . $p['id'] . '/procedura/' . MHW_PASSI_VECCHI[$passo]), true, 301);
        exit;
    }
    if (!isset(MHW_PASSI[$passo])) Support::redirect('/pannello/' . $p['id'] . '/procedura/struttura');
    $indice = array_search($passo, array_keys(MHW_PASSI), true);
    $fatto = array_search($p['wizard_step'] ?: 'struttura', array_keys(MHW_PASSI), true);
    if ($fatto !== false && $indice > (int) $fatto) Db::update('properties', ['wizard_step' => $passo], 'id = :pid', ['pid' => $p['id']]);

    $aid = (int) $acc['id'];
    $lingue = ['lingueAttive' => array_column(Db::all('SELECT locale FROM property_locales WHERE property_id = ?', [$p['id']]), 'locale'),
               'consentite' => Entitlements::allowedLocales($aid), 'tutte' => Config::get('locales')];
    $extra = [];
    if ($passo === 'struttura') $extra = $lingue;
    if ($passo === 'arrivo') {
        $core = Db::one('SELECT * FROM sections WHERE property_id = ? AND is_core = 1', [$p['id']]);
        $t = Db::one('SELECT * FROM section_translations WHERE section_id = ? AND locale = ?', [$core['id'], $p['default_locale']]);
        $extra = ['core' => $core, 'tdati' => json_decode((string) ($t['data'] ?? ''), true) ?: []];
    }
    if ($passo === 'sezioni') {
        $sez = Db::all('SELECT * FROM sections WHERE property_id = ? AND is_core = 0 ORDER BY position, id', [$p['id']]);
        $aperta = null;
        foreach ($sez as &$s) {
            $t = Db::one('SELECT title, data FROM section_translations WHERE section_id = ? AND locale = ?', [$s['id'], $p['default_locale']]);
            $s['title'] = $t['title'] ?? SectionCatalog::title($s['kind'], $p['default_locale']);
            $s['empty'] = SectionCatalog::isEmpty($s['kind'], json_decode((string) $s['data'], true) ?: [], json_decode((string) ($t['data'] ?? ''), true) ?: [],
                (int) Db::val('SELECT COUNT(*) FROM places WHERE section_id = ?', [$s['id']], 0));
            if ((int) $s['id'] === (int) ($_GET['apri'] ?? 0)) $aperta = $s;
        }
        unset($s);
        // La sezione appena aggiunta (o scelta con «Modifica») si compila qui, sotto la sua card.
        $extra = ['sezioni' => $sez, 'aperta' => $aperta ? $datiSezione($p, Properties::section((int) $p['id'], (int) $aperta['id'])) : null,
                  'modifica' => (int) ($_GET['luogo'] ?? 0)];
    }
    if ($passo === 'aspetto') {
        $pal = [];
        foreach (Palette::all() as $code => $nome) $pal[$code] = ['nome' => $nome, 'dati' => Palette::get($code), 'toni' => Palette::tones($code), 'css' => Palette::css($code)];
        $extra = ['palette' => $pal];
    }
    if ($passo === 'pubblica') {
        $extra = $lingue + ['problemi' => Guide::problems($aid, (int) $p['id']), 'verificato' => Auth::isVerified($u),
                  'online' => Subscriptions::propertyOnline($p)];
    }
    View::out('host/wizard', $contesto($acc, $p) + $extra + ['passo' => $passo, 'passi' => MHW_PASSI, 'user' => $u, 'qui' => 'procedura'], 'layout/cms');
});

// ------------------------------------------------------------------ anteprima
$anteprima = function (array $a, string $pagina) use ($mia) {
    [, , $p] = $mia((int) $a['id']);
    $snap = Guide::normalize(Guide::build((int) $p['id']));
    $loc = in_array($_GET['l'] ?? '', $snap['locales'], true) ? $_GET['l'] : $snap['property']['default_locale'];
    $dati = ['snap' => $snap, 'loc' => $loc, 'base' => Support::url('/pannello/' . $p['id'] . '/anteprima'), 'anteprima' => true,
             'paletteCss' => Palette::css($snap['property']['palette']), 'tema' => Palette::themeFor($snap['property']['text_tone'])];
    header('X-Robots-Tag: noindex, nofollow');
    if ($pagina === 'sezione') {
        foreach ($snap['sections'] as $s) if ((string) $s['id'] === (string) $a['sid']) View::out('guest/section', $dati + ['sec' => $s], 'layout/guest');
        Support::redirect('/pannello/' . $p['id'] . '/anteprima');
    }
    View::out(['home' => 'guest/guide', 'benvenuto' => 'guest/splash', 'commiato' => 'guest/farewell'][$pagina], $dati,
              $pagina === 'home' ? 'layout/guest' : 'layout/full');
};
$r->get('/pannello/{id}/anteprima', fn(array $a) => $anteprima($a, 'home'));
$r->get('/pannello/{id}/anteprima/benvenuto', fn(array $a) => $anteprima($a, 'benvenuto'));
$r->get('/pannello/{id}/anteprima/commiato', fn(array $a) => $anteprima($a, 'commiato'));
$r->get('/pannello/{id}/anteprima/{sid}', fn(array $a) => $anteprima($a, 'sezione'));

// ---------------------------------------------------------------- pubblica
/**
 * Pubblica → c'è un abbonamento valido? Si pubblica subito una versione nuova.
 * Altrimenti: guida pubblicabile, email verificata, piano scelto → Stripe.
 * La guida va online SOLO quando arriva il webhook firmato del pagamento.
 */
$r->post('/pannello/{id}/pubblica', function (array $a) use ($mia) {
    [$u, $acc, $p] = $mia((int) $a['id']);
    $aid = (int) $acc['id'];
    $problemi = Guide::problems($aid, (int) $p['id']);
    if ($problemi) {
        Support::flash('Prima di pubblicare: ' . implode(' ', $problemi), 'err');
        Support::redirect('/pannello/' . $p['id'] . '/procedura/pubblica');
    }

    if (Subscriptions::active($aid)) {
        $v = Guide::publish((int) $p['id']);
        Auth::audit('guide.publish', (int) $u['id'], ['property_id' => (int) $p['id'], 'version' => $v]);
        Support::redirect('/pannello/' . $p['id'] . '/pubblicata');
    }

    if (!Auth::isVerified($u)) {
        Support::flash('Conferma prima la tua email: ti abbiamo scritto a ' . $u['email'] . '. Serve per attivare l\'abbonamento.', 'err');
        Support::redirect('/pannello/' . $p['id'] . '/procedura/pubblica');
    }
    $pv = $acc['intended_package_version_id'] ? Plans::currentVersion((int) $acc['intended_package_version_id']) : null;
    if (!$pv) { Support::flash('Scegli il piano con cui pubblicare.', 'err'); Support::redirect('/piano'); }
    // Il listino può essere cambiato dopo la scelta: si compra sempre la versione in vendita.
    if ((int) $pv['id'] !== (int) $acc['intended_package_version_id']) {
        Db::update('accounts', ['intended_package_version_id' => $pv['id']], 'id = :aid', ['aid' => $aid]);
        Entitlements::forget($aid);
        $problemi = Guide::problems($aid, (int) $p['id']);
        if ($problemi) { Support::flash('Il piano è stato aggiornato: ' . implode(' ', $problemi), 'err'); Support::redirect('/pannello/' . $p['id'] . '/procedura/pubblica'); }
    }
    if (!Stripe::enabled()) {
        Log::error('Pubblicazione richiesta ma Stripe non è configurato', ['account' => $aid]);
        Support::flash('I pagamenti non sono ancora attivi. La guida resta salvata in bozza: riprova più tardi.', 'err');
        Support::redirect('/pannello/' . $p['id'] . '/procedura/pubblica');
    }
    // Prima del primo pagamento: i dati per la fattura (azienda o privato, P.IVA o codice fiscale, SDI o PEC).
    if (!MHW\Fatturazione::completa($acc)) {
        Support::flash('Prima del pagamento servono i dati di fatturazione: li compili una volta sola.', 'err');
        Support::redirect('/account?torna=' . rawurlencode('/pannello/' . $p['id'] . '/procedura/pubblica') . '#fatturazione');
    }
    $pkg = Db::one('SELECT * FROM packages WHERE id = ?', [$pv['package_id']]);
    $quantita = Plans::quantity($pv, (int) ($acc['intended_quantity'] ?? 1)) ?? (int) $pv['min_quantity'];
    // Il codice sconto (6E) si rivalida adesso: se non vale più si toglie, si dice perché e non si crea l'ordine.
    $codiceSconto = null;
    if (!empty($acc['intended_discount_code_id']) && ($riga = MHW\Sconti::riga((int) $acc['intended_discount_code_id']))) {
        try { $codiceSconto = MHW\Sconti::valida($riga['code'], $acc, $pv, $quantita); }
        catch (\RuntimeException $e) {
            Db::update('accounts', ['intended_discount_code_id' => null], 'id = :aid', ['aid' => $aid]);
            Support::flash('Il codice ' . $riga['code'] . ' è stato tolto: ' . $e->getMessage() . ' Controlla il prezzo e pubblica di nuovo.', 'err');
            Support::redirect('/pannello/' . $p['id'] . '/procedura/pubblica');
        }
    }
    $oid = Db::insert('orders', [
        'account_id' => $aid, 'package_version_id' => $pv['id'], 'property_id' => $p['id'], 'quantity' => $quantita,
        'amount_cents' => Plans::price($pv, $quantita), 'currency' => $pv['currency'], 'status' => 'pending',
        'provider' => 'stripe', 'provider_session_id' => '', 'created_at' => Support::now(), 'updated_at' => Support::now(),
    ] + ($codiceSconto ? ['discount_code_id' => (int) $codiceSconto['id']] : []));
    try {
        $url = Stripe::checkoutSubscription(Db::one('SELECT * FROM orders WHERE id = ?', [$oid]), $pv, $pkg, $acc, $u);
    } catch (\Throwable $e) {
        Db::update('orders', ['status' => 'failed', 'updated_at' => Support::now()], 'id = :oid', ['oid' => $oid]);
        $codice = Log::exception($e, 'checkout');
        Support::flash('Il pagamento non è disponibile in questo momento. Riprova tra poco (codice ' . $codice . ').', 'err');
        Support::redirect('/pannello/' . $p['id'] . '/procedura/pubblica');
    }
    Support::redirect($url);
});

$r->get('/pannello/{id}/pubblicata', function (array $a) use ($mia, $contesto) {
    [, $acc, $p] = $mia((int) $a['id']);
    $qr = Db::one('SELECT * FROM qr_tokens WHERE property_id = ?', [$p['id']]);
    View::out('host/published', $contesto($acc, $p) + ['qr' => $qr, 'qui' => 'contenuti'], 'layout/cms');
});

// ----------------------------------------------------------------------- QR
$r->get('/pannello/{id}/qr', function (array $a) use ($mia, $contesto) {
    [, $acc, $p] = $mia((int) $a['id']);
    $qr = Db::one('SELECT * FROM qr_tokens WHERE property_id = ?', [$p['id']]);
    // Le lingue della guida, la principale per prima: una versione del messaggio di benvenuto per ognuna.
    $lingue = array_column(Db::all('SELECT locale FROM property_locales WHERE property_id = ?', [$p['id']]), 'locale');
    usort($lingue, fn($x, $y) => ($y === $p['default_locale']) <=> ($x === $p['default_locale']) ?: array_search($x, MHW\I18n::LOCALES) <=> array_search($y, MHW\I18n::LOCALES));
    View::out('host/qr', $contesto($acc, $p) + ['qr' => $qr, 'online' => Subscriptions::propertyOnline($p), 'qui' => 'qr', 'lingue' => $lingue], 'layout/cms');
});

$r->get('/pannello/{id}/qr.{formato}', function (array $a) use ($mia) {
    [, , $p] = $mia((int) $a['id']);
    $qr = Db::one('SELECT * FROM qr_tokens WHERE property_id = ?', [$p['id']]);
    $url = Support::baseUrl() . '/q/' . $qr['token'];
    $nome = 'qr-' . $p['slug'];
    header_remove('Cache-Control');
    header('Cache-Control: private, max-age=300');
    switch ($a['formato']) {
        case 'png': header('Content-Type: image/png'); header('Content-Disposition: attachment; filename="' . $nome . '.png"');
                    echo Qr::png($url, 8, 4, 1200); break;
        case 'svg': header('Content-Type: image/svg+xml'); header('Content-Disposition: attachment; filename="' . $nome . '.svg"');
                    echo QrExport::svg($url); break;
        case 'pdf': header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="' . $nome . '.pdf"');
                    echo QrExport::pdf($url, $p['name']); break;
        default: http_response_code(404);
    }
    exit;
});

// --------------------------------------------------------------- statistiche
$r->get('/pannello/{id}/statistiche', function (array $a) use ($mia, $contesto) {
    [, $acc, $p] = $mia((int) $a['id']);
    $ok = Entitlements::can((int) $acc['id'], 'analytics');
    View::out('host/stats', $contesto($acc, $p) + ['stats' => $ok ? Stats::forProperty((int) $p['id']) : null, 'qui' => 'statistiche'], 'layout/cms');
});

// ------------------------------------------------------ account e fatturazione
/* Dove si torna dopo i dati di fatturazione: la pubblicazione, oppure la conferma di un cambio di piano. */
$tornaValido = fn(string $t): bool => (bool) preg_match('#^(/pannello/\d+/procedura/pubblica|/account/piano/conferma\?piano=[a-z0-9_-]+(&strutture=\d{1,3})?)$#', $t);

$paginaAccount = function (array $u, array $acc, array $extra = []) use ($tornaValido): never {
    $ultimo = MHW\Subscriptions::latest((int) $acc['id']);
    $gov = Subscriptions::governingVersionId((int) $acc['id']);
    // Dopo i dati di fatturazione si torna dove si era (la pubblicazione), solo dentro il pannello.
    $torna = (string) ($_POST['torna'] ?? $_GET['torna'] ?? '');
    View::out('host/account', $extra + [
        'user' => $u, 'acc' => $acc, 'sub' => Subscriptions::active((int) $acc['id']), 'ultimo' => $ultimo,
        'piano' => $gov ? Plans::version($gov) : null,
        'ordini' => Db::all('SELECT o.*, pk.name AS package FROM orders o JOIN package_versions pv ON pv.id = o.package_version_id
                             JOIN packages pk ON pk.id = pv.package_id WHERE o.account_id = ? ORDER BY o.id DESC LIMIT 10', [$acc['id']]),
        'portale' => Stripe::enabled() && Config::get('stripe')['customer_portal'] && $acc['stripe_customer_id'] !== '',
        'fatt' => $acc, 'erroriFatt' => [], 'torna' => $tornaValido($torna) ? $torna : '',
        'nav' => 'account',
    ], 'layout/cms');
    exit;
};

$r->get('/account', function () use ($host, $paginaAccount) {
    [$u, $acc] = $host();
    $paginaAccount($u, $acc);
});

// ------------------------------------------------------------ Invita un amico
/* Il link personale, la barra degli inviti e gli amici invitati. Solo per chi può
   invitare: inviti accesi e abbonamento Stripe attivo. */
$r->get('/inviti', function () use ($host) {
    [$u, $acc] = $host();
    if (!MHW\Inviti::puoInvitare($acc)) {
        Support::flash('Gli inviti si attivano con il tuo abbonamento: pubblica la guida e potrai invitare i tuoi amici.', 'avviso');
        Support::redirect('/pannello');
    }
    View::out('host/inviti', [
        'user' => $u, 'acc' => $acc, 'inv' => MHW\Inviti::stato($acc), 'amici' => MHW\Inviti::amici((int) $acc['id']),
        'codice' => MHW\Inviti::codice($acc), 'nav' => 'inviti',
    ], 'layout/cms');
});

/*
 * Il tuo account: nome, password ed email. La password e l'email si cambiano solo
 * con la password attuale; l'email nuova vale dopo il link di conferma, e quella
 * vecchia riceve un avviso.
 */
$r->post('/account/profilo', function () use ($host, $paginaAccount) {
    [$u, $acc] = $host();
    $nome = trim(preg_replace('/\s+/u', ' ', (string) ($_POST['name'] ?? '')) ?? '');
    if ($nome === '' || mb_strlen($nome) > 120) $paginaAccount($u, $acc, ['erroriProfilo' => ['name' => $nome === '' ? 'Scrivi il tuo nome.' : 'Il nome può avere al massimo 120 caratteri.'], 'apri' => 'profilo']);
    Db::update('users', ['name' => $nome], 'id = :uid', ['uid' => $u['id']]);
    Auth::audit('account.name', (int) $u['id']);
    Support::flash('Nome salvato.');
    Support::redirect('/account#profilo');
});

$r->post('/account/password', function () use ($host, $paginaAccount) {
    [$u, $acc] = $host();
    $errori = [];
    if (!MHW\RateLimit::hit('password-cambio:' . $u['id'], 8, 900)) $errori['attuale'] = 'Troppi tentativi: riprova tra un quarto d\'ora.';
    elseif (!password_verify((string) ($_POST['attuale'] ?? ''), (string) Db::val('SELECT password_hash FROM users WHERE id = ?', [$u['id']], ''))) $errori['attuale'] = 'La password attuale non è giusta.';
    $nuova = (string) ($_POST['nuova'] ?? '');
    if (mb_strlen($nuova) < 8) $errori['nuova'] = 'La password nuova deve avere almeno 8 caratteri.';
    elseif (strlen($nuova) > 72) $errori['nuova'] = 'La password nuova può avere al massimo 72 caratteri.';
    elseif ($nuova !== (string) ($_POST['nuova2'] ?? '')) $errori['nuova2'] = 'Le due password non sono uguali: riscrivile.';
    if ($errori) $paginaAccount($u, $acc, ['erroriPassword' => $errori, 'apri' => 'password']);
    Db::update('users', ['password_hash' => password_hash($nuova, PASSWORD_DEFAULT)], 'id = :uid', ['uid' => $u['id']]);
    session_regenerate_id(true);
    Auth::audit('account.password', (int) $u['id']);
    Support::flash('Password cambiata.');
    Support::redirect('/account#profilo');
});

$r->post('/account/email', function () use ($host, $paginaAccount) {
    [$u, $acc] = $host();
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $errori = [];
    if (!MHW\RateLimit::hit('email-cambio:' . $u['id'], 5, 3600)) $errori['email'] = 'Troppi tentativi: riprova tra un\'ora.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) $errori['email'] = 'Questo indirizzo email non è valido.';
    elseif ($email === mb_strtolower((string) $u['email'])) $errori['email'] = 'È già la tua email.';
    elseif (Db::val('SELECT id FROM users WHERE email = ?', [$email])) $errori['email'] = 'Questa email è già usata da un altro account.';
    if (!$errori && !password_verify((string) ($_POST['attuale'] ?? ''), (string) Db::val('SELECT password_hash FROM users WHERE id = ?', [$u['id']], ''))) $errori['attuale'] = 'La password attuale non è giusta.';
    if ($errori) $paginaAccount($u, $acc, ['erroriEmail' => $errori, 'apri' => 'email', 'emailNuova' => $email]);
    Db::update('users', ['pending_email' => $email], 'id = :uid', ['uid' => $u['id']]);
    $link = Support::baseUrl() . '/account/email/' . MHW\Tokens::issue((int) $u['id'], MHW\Tokens::EMAIL, 48 * 3600);
    $partita = MHW\Mailer::send($email, 'Conferma la tua nuova email — MyHouse Welcome',
        "Ciao " . ($u['name'] ?: '') . ",\n\nper usare questo indirizzo nel tuo account MyHouse Welcome apri questo link:\n\n$link\n\n"
        . "Il link vale 48 ore. Finché non lo apri resta valida l'email di prima.\n\nSe non l'hai chiesto tu, ignora questo messaggio.\n\nMyHouse Welcome");
    if (!$partita) {
        Db::update('users', ['pending_email' => null], 'id = :uid', ['uid' => $u['id']]);
        $paginaAccount($u, $acc, ['erroriEmail' => ['email' => 'Non siamo riusciti a mandare l\'email di conferma: riprova tra poco.'], 'apri' => 'email', 'emailNuova' => $email]);
    }
    Auth::audit('account.email_request', (int) $u['id']);
    Support::flash('Ti abbiamo mandato un link a ' . $email . ': aprilo per confermare la nuova email.');
    Support::redirect('/account#profilo');
});

$r->get('/account/email/{token}', function (array $a) {
    $uid = MHW\Tokens::consume((string) $a['token'], MHW\Tokens::EMAIL);
    $u = $uid ? Db::one('SELECT * FROM users WHERE id = ?', [$uid]) : null;
    $nuova = (string) ($u['pending_email'] ?? '');
    if (!$u || $nuova === '' || Db::val('SELECT id FROM users WHERE email = ? AND id <> ?', [$nuova, $uid])) {
        Support::flash('Il link non vale più: chiedi di nuovo il cambio dell\'email dal tuo account.', 'err');
        Support::redirect(Auth::user() ? '/account' : '/accedi');
    }
    Db::update('users', ['email' => $nuova, 'pending_email' => null, 'email_verified_at' => Support::now()], 'id = :uid', ['uid' => $uid]);
    MHW\Mailer::send((string) $u['email'], 'La tua email è cambiata — MyHouse Welcome',
        "Ciao " . ($u['name'] ?: '') . ",\n\nl'email del tuo account MyHouse Welcome ora è $nuova.\n\n"
        . "Se non l'hai chiesto tu, scrivici subito rispondendo a questo messaggio.\n\nMyHouse Welcome");
    Auth::audit('account.email', (int) $uid, ['da' => $u['email']]);
    Support::flash('Email cambiata: ora accedi con ' . $nuova . '.');
    Support::redirect(Auth::user() ? '/account' : '/accedi');
});

/* Dati di fatturazione: si controllano qui (partita IVA, codice fiscale, SDI o PEC)
   e, se il cliente Stripe esiste già, si aggiornano anche lì. */
$r->post('/account/fatturazione', function () use ($host, $paginaAccount, $tornaValido) {
    [$u, $acc] = $host();
    [$dati, $errori] = MHW\Fatturazione::valida($_POST);
    if ($errori) $paginaAccount($u, $acc, ['fatt' => $dati + $acc, 'erroriFatt' => $errori, 'apriFatt' => true]);
    Db::update('accounts', $dati, 'id = :aid', ['aid' => $acc['id']]);
    Auth::audit('account.billing', (int) $u['id'], ['account_id' => (int) $acc['id']]);
    if (Stripe::enabled()) {
        try { Stripe::syncCustomer($dati + $acc); }
        catch (\Throwable $e) { Log::exception($e, 'dati di fatturazione su Stripe'); }
    }
    $torna = (string) ($_POST['torna'] ?? '');
    if ($tornaValido($torna)) {
        Support::flash(str_starts_with($torna, '/account/piano') ? 'Dati di fatturazione salvati. Ora puoi cambiare piano.' : 'Dati di fatturazione salvati. Ora puoi pubblicare.');
        Support::redirect($torna);
    }
    Support::flash('Dati di fatturazione salvati.');
    Support::redirect('/account#fatturazione');
});

$r->post('/account/rinnovo', function () use ($host) {
    [$u, $acc] = $host();
    $s = Subscriptions::active((int) $acc['id']);
    if (!$s || $s['provider'] !== 'stripe' || $s['provider_subscription_id'] === '') {
        Support::flash('Non c\'è un abbonamento con rinnovo da modificare.', 'err'); Support::redirect('/account');
    }
    $spegni = (string) ($_POST['rinnovo'] ?? '') === 'no';
    try {
        Stripe::setCancelAtPeriodEnd($s['provider_subscription_id'], $spegni);
        // Il webhook confermerà; intanto lo si scrive, perché la pagina lo mostri subito.
        Db::update('subscriptions', ['cancel_at_period_end' => $spegni ? 1 : 0, 'updated_at' => Support::now()], 'id = :sid', ['sid' => $s['id']]);
        Auth::audit($spegni ? 'subscription.renewal_off' : 'subscription.renewal_on', (int) $u['id']);
        Support::flash($spegni ? 'Rinnovo automatico disattivato. La guida resta online fino al ' . Support::date($s['current_period_end']) . '.'
                               : 'Rinnovo automatico riattivato.');
    } catch (\Throwable $e) {
        Support::flash('Non è stato possibile cambiare il rinnovo adesso (codice ' . Log::exception($e, 'rinnovo') . ').', 'err');
    }
    Support::redirect('/account');
});

$r->post('/account/portale', function () use ($host) {
    [, $acc] = $host();
    if (!Stripe::enabled() || $acc['stripe_customer_id'] === '') Support::redirect('/account');
    try { Support::redirect(Stripe::portalUrl($acc['stripe_customer_id'])); }
    catch (\Throwable $e) {
        Support::flash('Il portale di fatturazione non è disponibile adesso (codice ' . Log::exception($e, 'portale') . ').', 'err');
        Support::redirect('/account');
    }
});

// ------------------------------------------------------------ cambio di piano (6H)
/*
 * «Cambia piano» per chi ha già un abbonamento. Salire: si paga oggi la differenza
 * per i giorni che restano, su Stripe, e il piano nuovo vale al pagamento. Scendere:
 * niente da pagare né da rimborsare, il cambio parte dal rinnovo. Le regole e i conti
 * sono in CambioPiano, i passaggi in CambioAbbonamento.
 */
$cambioPossibile = function (array $acc): array {
    $st = CambioAbbonamento::stato((int) $acc['id']);
    if ($st['motivo'] === 'nessuno') Support::redirect('/piano');
    return $st;
};
/** Il piano scelto e il numero di strutture, ricontrollati: [versione, quantità] oppure null. */
$sceltaPiano = function (array $st, string $codice, mixed $strutture): ?array {
    $pv = CambioAbbonamento::versioneDi($codice);
    if (!$pv) return null;
    $q = Plans::perProperty($pv)
        ? Plans::quantity($pv, ($strutture === null || $strutture === '') ? ((string) $st['pv']['code'] === $codice ? (string) $st['quantita'] : '') : $strutture)
        : 1;
    return $q === null ? null : [$pv, $q];
};

$r->get('/account/piano', function () use ($host, $cambioPossibile) {
    [$u, $acc] = $host();
    $st = $cambioPossibile($acc);
    $piani = [];
    foreach (Plans::public() as $p) {
        $pv = Plans::version((int) $p['pv_id']);
        if (!$pv) continue;
        $min = Plans::perProperty($pv) ? max(1, (int) $pv['min_quantity']) : 1;
        $q = Plans::perProperty($pv) ? (Plans::quantity($pv, (string) ($_GET['strutture'] ?? '')) ?? ((string) $st['pv']['code'] === (string) $pv['code'] ? $st['quantita'] : $min)) : 1;
        $piani[] = ['p' => $p, 'pv' => $pv, 'q' => $q, 'prev' => $st['pv'] ? CambioAbbonamento::preventivo($st['sub'], $st['pv'], $pv, $q) : null,
                    'attuale' => $st['pv'] && (string) $st['pv']['code'] === (string) $pv['code']];
    }
    View::out('host/piani', ['user' => $u, 'acc' => $acc, 'st' => $st, 'piani' => $piani, 'nav' => 'account'], 'layout/cms');
});

$r->any('/account/piano/conferma', function () use ($host, $cambioPossibile, $sceltaPiano) {
    [$u, $acc] = $host();
    $st = $cambioPossibile($acc);
    if ($st['motivo'] !== '') { Support::flash($st['motivo'], 'err'); Support::redirect('/account'); }
    $codice = (string) ($_POST['piano'] ?? $_GET['piano'] ?? '');
    $scelta = $sceltaPiano($st, $codice, $_POST['strutture'] ?? $_GET['strutture'] ?? null);
    if (!$scelta) { Support::flash('Scegli un piano in vendita e un numero di strutture valido.', 'err'); Support::redirect('/account/piano'); }
    [$pv, $q] = $scelta;
    $sub = $st['sub'];
    $prev = CambioAbbonamento::preventivo($sub, $st['pv'], $pv, $q);
    if ($prev['stesso']) { Support::flash('È già il tuo piano.'); Support::redirect('/account/piano'); }
    $qui = '/account/piano/conferma?piano=' . rawurlencode($codice) . (Plans::perProperty($pv) ? '&strutture=' . $q : '');
    $cambia = $prev['tipo'] === CambioPiano::SCENDE ? CambioAbbonamento::cosaCambia((int) $acc['id'], $pv, $q) : null;
    $err = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            if (!Stripe::enabled()) throw new RuntimeException('I pagamenti non sono attivi in questo momento: riprova più tardi.');
            if ($prev['tipo'] === CambioPiano::SCENDE) {
                // Le scelte: sezioni da tenere per struttura, strutture da archiviare (esattamente quante servono).
                $scelte = ['sezioni' => [], 'archivia' => array_values(array_unique(array_map('intval', (array) ($_POST['archivia'] ?? []))))];
                foreach ($cambia['sezioni'] as $blocco) {
                    $pid = (int) $blocco['struttura']['id'];
                    $tenute = array_values(array_intersect(array_map('intval', (array) ($_POST['tieni'][$pid] ?? [])), array_map('intval', array_column($blocco['sezioni'], 'id'))));
                    if (count($tenute) > $cambia['maxSezioni']) throw new RuntimeException('In ' . $blocco['struttura']['name'] . ' scegli al massimo ' . $cambia['maxSezioni'] . ' sezioni da tenere.');
                    $scelte['sezioni'][$pid] = $tenute;
                }
                $ids = array_map('intval', array_column($cambia['strutture'], 'id'));
                if ($cambia['daArchiviare'] > 0 && (count($scelte['archivia']) !== $cambia['daArchiviare'] || array_diff($scelte['archivia'], $ids))) {
                    throw new RuntimeException('Scegli esattamente ' . $cambia['daArchiviare'] . ' struttur' . ($cambia['daArchiviare'] === 1 ? 'a' : 'e') . ' da archiviare.');
                }
                CambioAbbonamento::programma($sub, $pv, $q, $scelte);
                Support::flash('Fatto: dal ' . Support::date($prev['fine']) . ' passi a ' . $pv['name'] . '. Fino ad allora resti su ' . $st['pv']['name'] . '.');
                Support::redirect('/account');
            }
            if ($prev['conguaglio'] === 0) {
                CambioAbbonamento::subito($sub, $pv, $q);
                Support::flash('Fatto: ora sei su ' . $pv['name'] . (Plans::perProperty($pv) ? " ($q strutture)" : '') . '.');
                Support::redirect('/account');
            }
            if (!MHW\Fatturazione::completa($acc)) {
                Support::flash('Prima di pagare servono i dati di fatturazione: poi torni qui.', 'avviso');
                Support::redirect('/account?torna=' . rawurlencode($qui) . '#fatturazione');
            }
            $o = CambioAbbonamento::ordine($acc, $sub, $pv, $q, $prev['conguaglio']);
            Support::redirect(Stripe::checkoutChange($o, CambioAbbonamento::descrizione($st['pv'], $st['quantita'], $pv, $q), $acc, $u));
        } catch (\Throwable $e) {
            $err = $e instanceof RuntimeException && !($e instanceof \PDOException) ? $e->getMessage()
                 : 'Non è stato possibile cambiare piano adesso (codice ' . Log::exception($e, 'cambio piano') . '). Riprova tra poco.';
        }
    }
    View::out('host/piano_conferma', ['user' => $u, 'acc' => $acc, 'st' => $st, 'pv' => $pv, 'q' => $q, 'prev' => $prev, 'cambia' => $cambia,
        'codice' => $codice, 'err' => $err, 'iva' => !empty(Config::get('stripe')['automatic_tax']), 'nav' => 'account'], 'layout/cms');
});

$r->post('/account/piano/annulla', function () use ($host) {
    [, $acc] = $host();
    $st = CambioAbbonamento::stato((int) $acc['id']);
    if (!$st['sub'] || empty($st['sub']['next_package_version_id'])) Support::redirect('/account');
    try {
        CambioAbbonamento::annulla($st['sub']);
        Support::flash('Cambio annullato: resti su ' . $st['pv']['name'] . ' anche dopo il rinnovo.');
    } catch (\Throwable $e) {
        Support::flash('Non è stato possibile annullare il cambio adesso (codice ' . Log::exception($e, 'annulla cambio piano') . '). Riprova tra poco.', 'err');
    }
    Support::redirect('/account');
});

// ------------------------------------------------- Portfolio: numero di strutture
/**
 * Aumento: Stripe fattura subito il conguaglio e applica il cambio solo se il
 * pagamento riesce; il numero nuovo arriva col webhook. Riduzione: credito
 * sulla prossima fattura. Se si scende sotto le strutture che esistono, prima
 * si scelgono quelle da archiviare: niente si cancella, niente si sceglie da solo.
 */
$portfolioAttivo = function (array $acc): ?array {
    $s = Subscriptions::active((int) $acc['id']);
    if (!$s) return null;
    $pv = Plans::version((int) $s['package_version_id']);
    return $pv && Plans::perProperty($pv) ? [$s, $pv] : null;
};

// Il vecchio «Numero di strutture»: ora è un cambio di piano come gli altri (6H).
$r->any('/account/strutture', function () use ($host) {
    $host();
    $n = (int) ($_POST['strutture'] ?? $_GET['strutture'] ?? 0);
    Support::redirect('/account/piano/conferma?piano=portfolio' . ($n > 0 ? '&strutture=' . $n : ''));
});

$r->post('/pannello/{id}/riattiva', function (array $a) use ($mia) {
    [$u, $acc, $p] = $mia((int) $a['id']);
    $max = Entitlements::limit((int) $acc['id'], 'properties', 1);
    $attive = (int) Db::val('SELECT COUNT(*) FROM properties WHERE account_id = ? AND archived_at IS NULL AND is_demo < 2', [$acc['id']], 0);
    if ($attive >= $max) {
        Support::flash("Il tuo piano comprende $max struttur" . ($max === 1 ? 'a' : 'e') . ': per riattivarla aumenta il numero di strutture da Account & Fatturazione.', 'err');
    } else {
        Db::update('properties', ['archived_at' => null], 'id = :pid', ['pid' => $p['id']]);
        Auth::audit('property.unarchive', (int) $u['id'], ['property_id' => (int) $p['id']]);
        Support::flash('Struttura riattivata. Se era pubblicata, torna online.');
    }
    Support::redirect('/pannello');
});

// ------------------------------------------------------------- codici sconto (6E)
/*
 * «Hai un codice sconto?» nel riquadro del piano (/piano e passo «Pubblica»).
 * Un codice valido si salva nell'account e si rivalida alla pubblicazione; uno
 * non valido torna sotto il campo, con il motivo. Dieci tentativi ogni 15 minuti.
 */
$tornaSconto = fn(): string => preg_match('#^/(piano|pannello/\d+/procedura/pubblica)$#', (string) ($_POST['torna'] ?? '')) ? (string) $_POST['torna'] : '/piano';

$r->post('/sconto/applica', function () use ($host, $tornaSconto) {
    [$u, $acc] = $host();
    $codice = mb_substr(MHW\Sconti::normalizza((string) ($_POST['codice'] ?? '')), 0, 24);
    try {
        if (!MHW\RateLimit::hit('sconto:' . (int) $acc['id'], 10, 900)) throw new RuntimeException('Troppi tentativi: riprova tra un quarto d\'ora.');
        if ($codice === '') throw new RuntimeException('Scrivi il codice.');
        $pv = $acc['intended_package_version_id'] ? Plans::currentVersion((int) $acc['intended_package_version_id']) : null;
        $q = $pv ? (Plans::quantity($pv, (int) ($acc['intended_quantity'] ?? 1)) ?? 1) : 1;
        // Qui si può scrivere anche il codice di invito di un amico (Invita un amico).
        if (!MHW\Sconti::trova($codice) && ($chi = MHW\Inviti::applicaCodice($acc, $codice)) !== null) {
            Support::flash('Invito di ' . $chi . ' applicato: −' . MHW\Inviti::AMICO . '% sul primo anno.');
            Support::redirect($tornaSconto());
        }
        $riga = MHW\Sconti::valida($codice, $acc, $pv, $q);
        Db::update('accounts', ['intended_discount_code_id' => (int) $riga['id']], 'id = :aid', ['aid' => $acc['id']]);
        Support::flash('Codice ' . $riga['code'] . ' applicato.');
    } catch (RuntimeException $e) {
        // Il messaggio torna sotto il campo, con il codice ancora scritto.
        $_SESSION['sconto_errore'] = ['codice' => $codice, 'msg' => $e->getMessage()];
    }
    Support::redirect($tornaSconto());
});

$r->post('/sconto/togli', function () use ($host, $tornaSconto) {
    [, $acc] = $host();
    Db::update('accounts', ['intended_discount_code_id' => null], 'id = :aid', ['aid' => $acc['id']]);
    Support::flash('Codice sconto tolto.');
    Support::redirect($tornaSconto());
});
