<?php
/** Rotte dell'area host. $r è il Router creato in public/index.php. */

use MHW\{Auth, Config, Db, Entitlements, Guide, Media, Support, Translator, View};

/** La struttura chiesta deve appartenere all'account di chi è connesso. */
$ownProperty = function (int $id): array {
    $acc = Auth::account();
    $p = Db::one('SELECT * FROM properties WHERE id = ? AND account_id = ?', [$id, $acc['id']]);
    if (!$p) { http_response_code(404); exit('Struttura non trovata.'); }
    return $p;
};

$r->get('/pannello', function () {
    Auth::requireUser();
    $acc = Auth::account();
    $props = Db::all('SELECT * FROM properties WHERE account_id = ? ORDER BY id', [$acc['id']]);
    if (!$props) Support::redirect('/pannello/nuova');
    $sub = Db::one(
        'SELECT s.*, p.name AS package_name, pv.version FROM subscriptions s
         JOIN package_versions pv ON pv.id = s.package_version_id
         JOIN packages p ON p.id = pv.package_id
         WHERE s.account_id = ? AND s.status = ? ORDER BY s.id DESC', [$acc['id'], 'active']);
    View::out('host/properties', ['props' => $props, 'sub' => $sub, 'acc' => $acc]);
});

$r->any('/pannello/nuova', function () {
    Auth::requireUser();
    $acc = Auth::account();
    $err = null;
    $max = Entitlements::limit((int) $acc['id'], 'properties', 1);
    $have = (int) Db::val('SELECT COUNT(*) FROM properties WHERE account_id = ?', [$acc['id']], 0);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($have >= $max) {
            $err = 'Il vostro piano consente ' . $max . ' struttura/e. Passate a un piano superiore per aggiungerne altre.';
        } else {
            $name = trim((string) $_POST['name']);
            if ($name === '') $err = 'Serve un nome.';
            else {
                $pid = Db::insert('properties', [
                    'account_id' => $acc['id'], 'name' => $name, 'slug' => Support::uniqueSlug($name),
                    'city' => trim((string) ($_POST['city'] ?? '')), 'region' => '',
                    'host_name' => $acc['name'], 'default_locale' => 'it',
                    'status' => 'draft', 'created_at' => Support::now(),
                ]);
                Db::insert('property_locales', ['property_id' => $pid, 'locale' => 'it']);
                Db::insert('qr_tokens', ['property_id' => $pid, 'token' => Support::token(9), 'scans' => 0, 'created_at' => Support::now()]);
                foreach ([['checkin', 'Entrare in casa', 'Le chiavi sono nella cassetta accanto al portone.'],
                          ['wifi', 'Wi-Fi e servizi', 'La rete si chiama come la casa.'],
                          ['places', 'Dove mangiare', 'I posti che proviamo anche noi.']] as $i => [$kind, $title, $body]) {
                    $sid = Db::insert('sections', [
                        'property_id' => $pid, 'kind' => $kind, 'icon' => $kind, 'color' => ['terracotta','sea','pine'][$i],
                        'position' => $i, 'created_at' => Support::now(),
                    ]);
                    Db::insert('section_translations', [
                        'section_id' => $sid, 'locale' => 'it', 'title' => $title, 'body' => $body,
                        'state' => 'reviewed', 'updated_at' => Support::now(),
                    ]);
                }
                Support::flash('Struttura creata. Ora riempite le sezioni.');
                Support::redirect('/pannello/' . $pid);
            }
        }
    }
    View::out('host/new_property', ['err' => $err, 'have' => $have, 'max' => $max]);
});

$r->get('/pannello/{id}', function (array $a) use ($ownProperty) {
    Auth::requireUser();
    $p = $ownProperty((int) $a['id']);
    $acc = Auth::account();
    $sections = Db::all('SELECT * FROM sections WHERE property_id = ? ORDER BY position, id', [$p['id']]);
    foreach ($sections as &$s) {
        $s['tr'] = Db::one('SELECT * FROM section_translations WHERE section_id = ? AND locale = ?', [$s['id'], $p['default_locale']]) ?: ['title' => '', 'body' => ''];
        $s['locales'] = (int) Db::val('SELECT COUNT(*) FROM section_translations WHERE section_id = ?', [$s['id']], 0);
    }
    unset($s);
    $qr = Db::one('SELECT * FROM qr_tokens WHERE property_id = ?', [$p['id']]);
    $stats = [
        'aperture' => (int) Db::val('SELECT COUNT(*) FROM analytics_events WHERE property_id = ? AND kind = ?', [$p['id'], 'open'], 0),
        'scansioni' => (int) ($qr['scans'] ?? 0),
        'lingue' => (int) Db::val('SELECT COUNT(*) FROM property_locales WHERE property_id = ?', [$p['id']], 0),
    ];
    View::out('host/dashboard', [
        'p' => $p, 'sections' => $sections, 'qr' => $qr, 'stats' => $stats,
        'pending' => Guide::pendingChanges((int) $p['id']),
        'ent' => Entitlements::forAccount((int) $acc['id']),
        'acc' => $acc,
    ]);
});

$r->post('/pannello/{id}/pubblica', function (array $a) use ($ownProperty) {
    Auth::requireUser();
    $p = $ownProperty((int) $a['id']);
    $v = Guide::publish((int) $p['id']);
    Support::flash('Guida pubblicata (versione ' . $v . '). È online su /g/' . $p['slug']);
    Support::redirect('/pannello/' . $p['id']);
});

$r->any('/pannello/{id}/impostazioni', function (array $a) use ($ownProperty) {
    Auth::requireUser();
    $p = $ownProperty((int) $a['id']);
    $acc = Auth::account();
    $err = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $data = [
                'name' => trim((string) $_POST['name']),
                'city' => trim((string) $_POST['city']),
                'checkin_from' => trim((string) $_POST['checkin_from']),
                'checkout_by' => trim((string) $_POST['checkout_by']),
                'host_name' => trim((string) $_POST['host_name']),
                'host_phone' => trim((string) $_POST['host_phone']),
                'host_whatsapp' => trim((string) $_POST['host_whatsapp']),
            ];
            if ($data['name'] === '') throw new RuntimeException('Il nome non può restare vuoto.');
            if (($_FILES['cover']['error'] ?? 4) === 0) {
                if (!Entitlements::can((int) $acc['id'], 'photos')) throw new RuntimeException('Le foto sono comprese dal piano Plus in su.');
                $data['cover_media_id'] = Media::store($_FILES['cover'], (int) $acc['id'], $data['name']);
            }
            Db::update('properties', $data, 'id = :pid', ['pid' => $p['id']]);
            Support::flash('Impostazioni salvate.');
            Support::redirect('/pannello/' . $p['id'] . '/impostazioni');
        } catch (\Throwable $e) { $err = $e->getMessage(); }
    }
    View::out('host/settings', ['p' => $p, 'err' => $err, 'acc' => $acc,
                                'ent' => Entitlements::forAccount((int) $acc['id'])]);
});

$r->post('/pannello/{id}/sezioni/nuova', function (array $a) use ($ownProperty) {
    Auth::requireUser();
    $p = $ownProperty((int) $a['id']);
    $acc = Auth::account();
    $max = Entitlements::limit((int) $acc['id'], 'sections', 8);
    $have = (int) Db::val('SELECT COUNT(*) FROM sections WHERE property_id = ?', [$p['id']], 0);
    if ($have >= $max) {
        Support::flash('Il vostro piano arriva a ' . $max . ' sezioni.', 'err');
        Support::redirect('/pannello/' . $p['id']);
    }
    $sid = Db::insert('sections', [
        'property_id' => $p['id'], 'kind' => 'text', 'icon' => 'home', 'color' => 'ochre',
        'position' => $have, 'created_at' => Support::now(),
    ]);
    Db::insert('section_translations', [
        'section_id' => $sid, 'locale' => $p['default_locale'], 'title' => 'Nuova sezione',
        'body' => '', 'state' => 'reviewed', 'updated_at' => Support::now(),
    ]);
    Support::redirect('/pannello/' . $p['id'] . '/sezioni/' . $sid);
});

$r->any('/pannello/{id}/sezioni/{sid}', function (array $a) use ($ownProperty) {
    Auth::requireUser();
    $p = $ownProperty((int) $a['id']);
    $acc = Auth::account();
    $s = Db::one('SELECT * FROM sections WHERE id = ? AND property_id = ?', [(int) $a['sid'], $p['id']]);
    if (!$s) { http_response_code(404); exit('Sezione non trovata.'); }
    $err = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $act = (string) ($_POST['azione'] ?? 'salva');
            if ($act === 'elimina') {
                Db::run('DELETE FROM sections WHERE id = ?', [$s['id']]);
                Support::flash('Sezione eliminata.');
                Support::redirect('/pannello/' . $p['id']);
            }
            if ($act === 'luogo') {
                if (!Entitlements::can((int) $acc['id'], 'places')) throw new RuntimeException('I consigli sul posto sono compresi dal piano Plus in su.');
                $mid = null;
                if (($_FILES['foto']['error'] ?? 4) === 0) $mid = Media::store($_FILES['foto'], (int) $acc['id'], (string) $_POST['nome']);
                Db::insert('places', [
                    'section_id' => $s['id'], 'name' => trim((string) $_POST['nome']),
                    'category' => trim((string) $_POST['categoria']), 'distance' => trim((string) $_POST['distanza']),
                    'note' => trim((string) ($_POST['nota'] ?? '')), 'badge' => trim((string) ($_POST['badge'] ?? '')),
                    'badge_tone' => (string) ($_POST['tono'] ?? 'pine'), 'media_id' => $mid,
                    'position' => (int) Db::val('SELECT COUNT(*) FROM places WHERE section_id = ?', [$s['id']], 0),
                ]);
                Support::flash('Luogo aggiunto.');
                Support::redirect('/pannello/' . $p['id'] . '/sezioni/' . $s['id']);
            }
            if ($act === 'elimina-luogo') {
                Db::run('DELETE FROM places WHERE id = ? AND section_id = ?', [(int) $_POST['place_id'], $s['id']]);
                Support::redirect('/pannello/' . $p['id'] . '/sezioni/' . $s['id']);
            }

            $data = [
                'kind' => (string) $_POST['kind'], 'color' => (string) $_POST['color'],
                'wifi_ssid' => trim((string) ($_POST['wifi_ssid'] ?? '')),
                'wifi_pass' => trim((string) ($_POST['wifi_pass'] ?? '')),
                'door_code' => trim((string) ($_POST['door_code'] ?? '')),
            ];
            if (($_FILES['foto']['error'] ?? 4) === 0) {
                if (!Entitlements::can((int) $acc['id'], 'photos')) throw new RuntimeException('Le foto sono comprese dal piano Plus in su.');
                $data['media_id'] = Media::store($_FILES['foto'], (int) $acc['id'], (string) $_POST['title']);
            }
            Db::update('sections', $data, 'id = :sid', ['sid' => $s['id']]);

            $loc = $p['default_locale'];
            $tr = Db::one('SELECT * FROM section_translations WHERE section_id = ? AND locale = ?', [$s['id'], $loc]);
            $t = ['title' => trim((string) $_POST['title']), 'body' => (string) $_POST['body'],
                  'state' => 'reviewed', 'updated_at' => Support::now()];
            if ($tr) Db::update('section_translations', $t, 'id = :tid', ['tid' => $tr['id']]);
            else Db::insert('section_translations', $t + ['section_id' => $s['id'], 'locale' => $loc]);

            Support::flash('Sezione salvata. Ricordatevi di pubblicare.');
            Support::redirect('/pannello/' . $p['id'] . '/sezioni/' . $s['id']);
        } catch (\Throwable $e) { $err = $e->getMessage(); }
    }

    $tr = Db::one('SELECT * FROM section_translations WHERE section_id = ? AND locale = ?', [$s['id'], $p['default_locale']])
        ?: ['title' => '', 'body' => ''];
    $places = Db::all('SELECT * FROM places WHERE section_id = ? ORDER BY position, id', [$s['id']]);
    View::out('host/section', ['p' => $p, 's' => $s, 'tr' => $tr, 'places' => $places, 'err' => $err,
                               'ent' => Entitlements::forAccount((int) $acc['id']), 'acc' => $acc]);
});

$r->any('/pannello/{id}/lingue', function (array $a) use ($ownProperty) {
    Auth::requireUser();
    $p = $ownProperty((int) $a['id']);
    $acc = Auth::account();
    $allowed = Entitlements::allowedLocales((int) $acc['id']);
    $err = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $act = (string) ($_POST['azione'] ?? '');
            if ($act === 'attiva') {
                $want = array_values(array_intersect((array) ($_POST['locali'] ?? []), $allowed));
                if (!in_array($p['default_locale'], $want, true)) $want[] = $p['default_locale'];
                Db::run('DELETE FROM property_locales WHERE property_id = ?', [$p['id']]);
                foreach ($want as $l) Db::insert('property_locales', ['property_id' => $p['id'], 'locale' => $l]);
                Support::flash('Lingue aggiornate.');
            } elseif ($act === 'traduci') {
                if (!Translator::enabled()) throw new RuntimeException('Nessun servizio di traduzione configurato: compilate a mano, oppure aggiungete una chiave in config.php.');
                $locs = array_column(Db::all('SELECT locale FROM property_locales WHERE property_id = ?', [$p['id']]), 'locale');
                $res = Translator::fillProperty((int) $p['id'], $locs);
                Support::flash('Tradotte ' . $res['tradotte'] . ' voci; ' . $res['saltate_perche_riviste'] . ' lasciate stare perché già riviste da voi.');
            } elseif ($act === 'salva-traduzione') {
                $sid = (int) $_POST['section_id']; $loc = (string) $_POST['locale'];
                if (!in_array($loc, $allowed, true)) throw new RuntimeException('Lingua non compresa nel piano.');
                $own = Db::one('SELECT id FROM sections WHERE id = ? AND property_id = ?', [$sid, $p['id']]);
                if (!$own) throw new RuntimeException('Sezione non vostra.');
                $cur = Db::one('SELECT * FROM section_translations WHERE section_id = ? AND locale = ?', [$sid, $loc]);
                $t = ['title' => trim((string) $_POST['title']), 'body' => (string) $_POST['body'],
                      'state' => 'reviewed', 'updated_at' => Support::now()];
                if ($cur) Db::update('section_translations', $t, 'id = :tid', ['tid' => $cur['id']]);
                else Db::insert('section_translations', $t + ['section_id' => $sid, 'locale' => $loc]);
                Support::flash('Traduzione confermata: da ora nessuna macchina la tocca più.');
            }
            Support::redirect('/pannello/' . $p['id'] . '/lingue');
        } catch (\Throwable $e) { $err = $e->getMessage(); }
    }

    $active = array_column(Db::all('SELECT locale FROM property_locales WHERE property_id = ?', [$p['id']]), 'locale');
    $sections = Db::all('SELECT * FROM sections WHERE property_id = ? ORDER BY position, id', [$p['id']]);
    foreach ($sections as &$s) {
        $s['tr'] = [];
        foreach (Db::all('SELECT * FROM section_translations WHERE section_id = ?', [$s['id']]) as $t)
            $s['tr'][$t['locale']] = $t;
    }
    unset($s);
    View::out('host/languages', ['p' => $p, 'sections' => $sections, 'active' => $active,
        'allowed' => $allowed, 'err' => $err, 'all' => Config::get('locales'),
        'translator' => Translator::enabled(), 'acc' => $acc]);
});

$r->get('/pannello/{id}/qr', function (array $a) use ($ownProperty) {
    Auth::requireUser();
    $p = $ownProperty((int) $a['id']);
    $qr = Db::one('SELECT * FROM qr_tokens WHERE property_id = ?', [$p['id']]);
    if (!$qr) {
        Db::insert('qr_tokens', ['property_id' => $p['id'], 'token' => Support::token(9), 'scans' => 0, 'created_at' => Support::now()]);
        $qr = Db::one('SELECT * FROM qr_tokens WHERE property_id = ?', [$p['id']]);
    }
    View::out('host/qr', ['p' => $p, 'qr' => $qr, 'acc' => Auth::account()]);
});
