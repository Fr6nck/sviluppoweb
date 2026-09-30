<?php
/** Rotte dell'area host. $r è il Router creato in public/index.php. */

use MHW\{Auth, Config, Db, Entitlements, Guide, LimitReached, Log, Media, NotFound, Palette, Plans, Properties,
         Qr, QrExport, SectionCatalog, Stats, Stripe, Subscriptions, Support, View};

/* La procedura: cinque passi. Le lingue in più stanno in fondo a «Anteprima e
   pubblica», facoltative: le traduzioni non fermano mai la pubblicazione. */
const MHW_PASSI = ['struttura' => 'Struttura e contatti', 'arrivo' => 'Arrivo e partenza', 'sezioni' => 'Sezioni',
                   'aspetto' => 'Aspetto', 'pubblica' => 'Anteprima e pubblica'];
/** I passi della v1 e dove sono finiti (migrazione 007 e redirect dei vecchi indirizzi). */
const MHW_PASSI_VECCHI = ['checkin' => 'arrivo', 'contenuti' => 'sezioni', 'lingue' => 'aspetto', 'anteprima' => 'pubblica'];

/** L'account di chi è connesso, per ogni rotta di quest'area. */
$host = function (): array {
    $u = Auth::requireUser();
    $acc = Auth::account();
    if (!$acc) Support::redirect('/admin');
    return [$u, $acc];
};

/** La struttura chiesta, se appartiene all'account. Altrimenti non esiste. */
$mia = function (int $id) use ($host): array {
    [$u, $acc] = $host();
    $p = Db::one('SELECT * FROM properties WHERE id = ? AND account_id = ?', [$id, $acc['id']]);
    if (!$p) { http_response_code(404); View::out('pub/404', []); }
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
    return ['s' => $s, 'title' => $tr['title'] ?? '', 'dati' => json_decode((string) $s['data'], true) ?: [],
            'tdati' => json_decode((string) ($tr['data'] ?? ''), true) ?: [], 'places' => $places];
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
    ], 'layout/cms');
});

$r->any('/pannello/nuova', function () use ($host, $messaggio) {
    [$u, $acc] = $host();
    if (!$acc['intended_package_version_id'] && !Subscriptions::active((int) $acc['id'])) Support::redirect('/piano');
    $max = Entitlements::limit((int) $acc['id'], 'properties', 1);
    $have = (int) Db::val('SELECT COUNT(*) FROM properties WHERE account_id = ? AND archived_at IS NULL', [$acc['id']], 0);
    $err = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $pid = Properties::create((int) $acc['id'], (string) ($_POST['name'] ?? ''), (string) ($_POST['city'] ?? ''), (string) $u['name']);
            Db::update('properties', ['wizard_step' => 'arrivo'], 'id = :pid', ['pid' => $pid]);
            Support::redirect('/pannello/' . $pid . '/procedura/struttura');
        } catch (\Throwable $e) { $err = $messaggio($e, 'nuova struttura'); }
    }
    // Il piano scelto (non ancora pagato), da ricordare in alto con «Cambia».
    $piano = !Subscriptions::active((int) $acc['id']) && $acc['intended_package_version_id']
        ? Plans::version((int) $acc['intended_package_version_id']) : null;
    View::out('host/new_property', ['err' => $err, 'have' => $have, 'max' => $max, 'nav' => 'guide',
        'piano' => $piano, 'quantita' => (int) ($acc['intended_quantity'] ?? 1)], 'layout/cms');
});

$r->post('/pannello/{id}/elimina', function (array $a) use ($mia) {
    [$u, $acc, $p] = $mia((int) $a['id']);
    if (trim((string) ($_POST['conferma'] ?? '')) !== $p['name']) {
        Support::flash('Per eliminare scrivi il nome esatto della struttura.', 'err');
        Support::redirect('/pannello/' . $p['id'] . '/impostazioni');
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
        Support::flash(SectionCatalog::title((string) $_POST['kind'], 'it') . ' aggiunta: compilala qui sotto.');
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
                if ($s['media_id']) Media::delete((int) $s['media_id'], $aid);
                Db::update('sections', ['media_id' => null], 'id = :sid', ['sid' => $s['id']]);
            } elseif ($cosa === 'togli-pdf') {
                if ($s['pdf_media_id']) Media::delete((int) $s['pdf_media_id'], $aid);
                Db::update('sections', ['pdf_media_id' => null], 'id = :sid', ['sid' => $s['id']]);
            } else {
                Properties::saveSection((int) $p['id'], (int) $s['id'], $p['default_locale'], $_POST, true);
                if (($_FILES['foto']['error'] ?? 4) !== UPLOAD_ERR_NO_FILE) {
                    if (!Entitlements::can($aid, 'photos')) throw new RuntimeException('Le immagini nelle sezioni sono comprese dal piano Plus.');
                    $mid = Media::storeImage($_FILES['foto'], $aid, (int) $p['id'], (string) ($_POST['title'] ?? ''), 'section');
                    if ($s['media_id']) Media::delete((int) $s['media_id'], $aid);
                    Db::update('sections', ['media_id' => $mid], 'id = :sid', ['sid' => $s['id']]);
                }
                if (($_FILES['pdf']['error'] ?? 4) !== UPLOAD_ERR_NO_FILE) {
                    if (!Entitlements::can($aid, 'pdf')) throw new RuntimeException('I PDF nelle sezioni sono compresi dal piano Plus.');
                    $mid = Media::storePdf($_FILES['pdf'], $aid, (int) $p['id'], (string) ($_POST['title'] ?? ''));
                    if ($s['pdf_media_id']) Media::delete((int) $s['pdf_media_id'], $aid);
                    Db::update('sections', ['pdf_media_id' => $mid], 'id = :sid', ['sid' => $s['id']]);
                }
            }
            if ($vuoleJson()) Support::json(['ok' => true, 'salvato' => Support::now()]);
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

$r->post('/pannello/{id}/sezioni/{sid}/luogo', function (array $a) use ($mia, $messaggio, $tornaSezione) {
    [, $acc, $p] = $mia((int) $a['id']);
    $aid = (int) $acc['id'];
    $torna = $tornaSezione($p, (int) $a['sid'], '/pannello/' . $p['id'] . '/sezioni/' . (int) $a['sid']);
    try {
        $plid = (int) ($_POST['place_id'] ?? 0) ?: null;
        $plid = Properties::savePlace($aid, (int) $p['id'], (int) $a['sid'], $plid, $p['default_locale'], true, $_POST);
        if (($_FILES['foto']['error'] ?? 4) !== UPLOAD_ERR_NO_FILE) {
            if (!Entitlements::can($aid, 'photos')) throw new RuntimeException('Le immagini dei luoghi sono comprese dal piano Plus.');
            $mid = Media::storeImage($_FILES['foto'], $aid, (int) $p['id'], (string) ($_POST['name'] ?? ''), 'place');
            $prima = Db::val('SELECT media_id FROM places WHERE id = ?', [$plid]);
            if ($prima) Media::delete((int) $prima, $aid);
            Db::update('places', ['media_id' => $mid], 'id = :pid', ['pid' => $plid]);
        }
        Support::flash('Luogo salvato.');
    } catch (NotFound) { http_response_code(404); View::out('pub/404', []); }
    catch (\Throwable $e) { Support::flash($messaggio($e, 'salva luogo'), 'err'); }
    Support::redirect($torna);
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
                if ($pl['media_id']) Media::delete((int) $pl['media_id'], (int) $acc['id']);
                Properties::deletePlace((int) $p['id'], $sid, $plid);
            })(),
            'togli-foto' => (function () use ($pl, $acc) {
                if ($pl['media_id']) Media::delete((int) $pl['media_id'], (int) $acc['id']);
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
    View::out('host/languages', $contesto($acc, $p) + [
        'err' => $err, 'lingueAttive' => $attive, 'consentite' => Entitlements::allowedLocales((int) $acc['id']),
        'tutte' => Config::get('locales'), 'copertura' => $copertura, 'qui' => 'lingue',
    ], 'layout/cms');
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
    View::out('host/translate', $contesto($acc, $p) + [
        'loc' => $loc, 'nome' => Config::get('locales')[$loc] ?? $loc, 'sections' => $sections, 'err' => $err, 'qui' => 'lingue',
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
                      'profile' => ['profile_media_id', 'profile_image', "L'immagine profilo"]];
            if (str_starts_with($cosa, 'togli-')) {
                $quale = substr($cosa, 6);
                if (!isset($campi[$quale])) throw new RuntimeException('Azione sconosciuta.');
                $col = $campi[$quale][0];
                if ($p[$col]) Media::delete((int) $p[$col], $aid);
                Db::update('properties', [$col => null], 'id = :pid', ['pid' => $p['id']]);
            } else {
                $pal = (string) ($_POST['palette'] ?? $p['palette']);
                $tono = (string) ($_POST['text_tone'] ?? $p['text_tone']);
                if (!Palette::exists($pal)) throw new RuntimeException('Palette sconosciuta.');
                // Un tono che non passa il controllo di contrasto non si salva.
                if (!Palette::readable($pal, $tono)) throw new RuntimeException('Questa combinazione non è abbastanza leggibile: scegli l\'altra.');
                if (!Entitlements::can($aid, 'palette')) $pal = Palette::DEFAULT;
                Db::update('properties', ['palette' => $pal, 'text_tone' => $tono], 'id = :pid', ['pid' => $p['id']]);
                foreach ($campi as $input => [$col, $feature, $nome]) {
                    if (($_FILES[$input]['error'] ?? 4) === UPLOAD_ERR_NO_FILE) continue;
                    if (!Entitlements::can($aid, $feature)) throw new RuntimeException("$nome non è compreso nel tuo piano.");
                    $mid = Media::storeImage($_FILES[$input], $aid, (int) $p['id'], $p['name'], $input);
                    if ($p[$col]) Media::delete((int) $p[$col], $aid);
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
                'host_name' => mb_substr(trim((string) ($_POST['host_name'] ?? '')), 0, 120),
                'host_phone' => mb_substr(trim((string) ($_POST['host_phone'] ?? '')), 0, 40),
                'host_whatsapp' => mb_substr(trim((string) ($_POST['host_whatsapp'] ?? '')), 0, 40),
            ];
            if ($dati['name'] === '') throw new RuntimeException('Il nome non può restare vuoto.');
            Db::update('properties', $dati, 'id = :pid', ['pid' => $p['id']]);
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
    $pkg = Db::one('SELECT * FROM packages WHERE id = ?', [$pv['package_id']]);
    $quantita = Plans::quantity($pv, (int) ($acc['intended_quantity'] ?? 1)) ?? (int) $pv['min_quantity'];
    $oid = Db::insert('orders', [
        'account_id' => $aid, 'package_version_id' => $pv['id'], 'property_id' => $p['id'], 'quantity' => $quantita,
        'amount_cents' => Plans::price($pv, $quantita), 'currency' => $pv['currency'], 'status' => 'pending',
        'provider' => 'stripe', 'provider_session_id' => '', 'created_at' => Support::now(), 'updated_at' => Support::now(),
    ]);
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
    View::out('host/qr', $contesto($acc, $p) + ['qr' => $qr, 'online' => Subscriptions::propertyOnline($p), 'qui' => 'qr'], 'layout/cms');
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
$r->get('/account', function () use ($host) {
    [$u, $acc] = $host();
    $ultimo = MHW\Subscriptions::latest((int) $acc['id']);
    $gov = Subscriptions::governingVersionId((int) $acc['id']);
    View::out('host/account', [
        'user' => $u, 'acc' => $acc, 'sub' => Subscriptions::active((int) $acc['id']), 'ultimo' => $ultimo,
        'piano' => $gov ? Plans::version($gov) : null,
        'ordini' => Db::all('SELECT o.*, pk.name AS package FROM orders o JOIN package_versions pv ON pv.id = o.package_version_id
                             JOIN packages pk ON pk.id = pv.package_id WHERE o.account_id = ? ORDER BY o.id DESC LIMIT 10', [$acc['id']]),
        'portale' => Stripe::enabled() && Config::get('stripe')['customer_portal'] && $acc['stripe_customer_id'] !== '',
        'nav' => 'account',
    ], 'layout/cms');
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

$r->any('/account/strutture', function () use ($host, $portfolioAttivo) {
    [$u, $acc] = $host();
    $pa = $portfolioAttivo($acc);
    if (!$pa) { Support::flash('Il numero di strutture si cambia solo con un abbonamento Portfolio attivo.', 'err'); Support::redirect('/account'); }
    [$s, $pv] = $pa;
    $n = Plans::quantity($pv, (string) ($_POST['strutture'] ?? $_GET['strutture'] ?? ''));
    if ($n === null) {
        Support::flash('Indica un numero intero di strutture tra ' . (int) $pv['min_quantity'] . ' e ' . (int) $pv['max_quantity'] . '.', 'err');
        Support::redirect('/account');
    }
    $attuale = (int) $s['quantity'];
    if ($n === $attuale) { Support::flash('Il tuo abbonamento comprende già ' . $n . ' strutture.'); Support::redirect('/account'); }
    if ($s['provider'] !== 'stripe' || $s['provider_subscription_id'] === '' || $s['provider_extra_item_id'] === '') {
        Support::flash('Questo abbonamento non si modifica da qui: scrivici e lo aggiorniamo noi.', 'err');
        Support::redirect('/account');
    }
    $attive = Db::all('SELECT id, name, city, status FROM properties WHERE account_id = ? AND archived_at IS NULL ORDER BY id', [$acc['id']]);
    $daTogliere = max(0, count($attive) - $n);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['conferma'] ?? '') === '1') {
        $scelte = array_values(array_unique(array_map('intval', (array) ($_POST['archivia'] ?? []))));
        $ids = array_map('intval', array_column($attive, 'id'));
        if (count($scelte) !== $daTogliere || array_diff($scelte, $ids)) {
            Support::flash("Scegli esattamente $daTogliere struttur" . ($daTogliere === 1 ? 'a' : 'e') . ' da archiviare.', 'err');
            Support::redirect('/account/strutture?strutture=' . $n);
        }
        try {
            Stripe::updateExtraQuantity($s['provider_subscription_id'], $s['provider_extra_item_id'], $n - 1, $n > $attuale);
        } catch (\Throwable $e) {
            Support::flash('Non è stato possibile cambiare l\'abbonamento adesso (codice ' . Log::exception($e, 'quantita') . ').', 'err');
            Support::redirect('/account');
        }
        foreach ($scelte as $pid) Db::update('properties', ['archived_at' => Support::now()], 'id = :pid AND account_id = :aid', ['pid' => $pid, 'aid' => $acc['id']]);
        Auth::audit('subscription.quantity', (int) $u['id'], ['da' => $attuale, 'a' => $n, 'archiviate' => $scelte]);
        Support::flash($n > $attuale
            ? "Richiesta inviata: le strutture diventano $n appena Stripe conferma il pagamento del conguaglio."
            : "Abbonamento ridotto a $n strutture. La differenza ti viene accreditata sulla prossima fattura."
              . ($scelte ? ' Le strutture scelte sono archiviate: contenuti e QR restano, puoi riattivarle quando vuoi.' : ''));
        Support::redirect('/account');
    }

    View::out('host/strutture', [
        'n' => $n, 'attuale' => $attuale, 'pv' => $pv, 'attive' => $attive, 'daTogliere' => $daTogliere,
        'nuovo' => Plans::price($pv, $n), 'vecchio' => Plans::price($pv, $attuale), 'nav' => 'account',
    ], 'layout/cms');
});

$r->post('/pannello/{id}/riattiva', function (array $a) use ($mia) {
    [$u, $acc, $p] = $mia((int) $a['id']);
    $max = Entitlements::limit((int) $acc['id'], 'properties', 1);
    $attive = (int) Db::val('SELECT COUNT(*) FROM properties WHERE account_id = ? AND archived_at IS NULL', [$acc['id']], 0);
    if ($attive >= $max) {
        Support::flash("Il tuo piano comprende $max struttur" . ($max === 1 ? 'a' : 'e') . ': per riattivarla aumenta il numero di strutture da Account & Fatturazione.', 'err');
    } else {
        Db::update('properties', ['archived_at' => null], 'id = :pid', ['pid' => $p['id']]);
        Auth::audit('property.unarchive', (int) $u['id'], ['property_id' => (int) $p['id']]);
        Support::flash('Struttura riattivata. Se era pubblicata, torna online.');
    }
    Support::redirect('/pannello');
});
