<?php
/** Rotte di amministrazione. $r è il Router creato in public/index.php. */

use MHW\{Auth, Billing, Config, Db, Demo, Entitlements, Log, Media, Plans, Stats, Storages, Stripe, Subscriptions, Support, View};

/**
 * Il quadro: quanti clienti, quanto hanno pagato davvero, quanto viene letto
 * quello che scrivono. Numeri interrogati adesso. Gli abbonamenti di prova,
 * manuali e dimostrativi NON entrano nell'incasso.
 */
$r->get('/admin', function () {
    Auth::requireAdmin();
    $numeri = [
        'clienti'    => (int) Db::val("SELECT COUNT(*) FROM users WHERE role = 'host' AND email NOT LIKE ?", ['%@' . Demo::DOMINIO], 0),
        'abbonati'   => (int) Db::val("SELECT COUNT(DISTINCT account_id) FROM subscriptions WHERE status IN ('active','trialing')
                                        AND provider = 'stripe' AND current_period_end > ?", [Support::now()], 0),
        'guide'      => (int) Db::val('SELECT COUNT(*) FROM properties WHERE is_demo = 0', [], 0),
        'pubblicate' => (int) Db::val("SELECT COUNT(*) FROM properties WHERE status = 'published' AND is_demo = 0", [], 0),
        'aperture'   => Stats::totalViews(30),
        'incassato'  => (int) Db::val("SELECT COALESCE(SUM(amount_cents),0) FROM orders WHERE status = 'paid' AND provider = 'stripe'", [], 0),
        'in_attesa'  => (int) Db::val("SELECT COUNT(*) FROM orders WHERE status IN ('pending','awaiting')", [], 0),
        'rinnovo_off'=> (int) Db::val("SELECT COUNT(*) FROM subscriptions WHERE status = 'active' AND cancel_at_period_end = 1", [], 0),
        'falliti'    => (int) Db::val("SELECT COUNT(*) FROM subscriptions WHERE status = 'past_due'", [], 0),
    ];
    $piani = Db::all(
        "SELECT pk.name, pk.public, pk.active, pv.version, pv.price_cents, pv.currency, pv.is_current, pv.stripe_price_id,
                (SELECT COUNT(*) FROM subscriptions s WHERE s.package_version_id = pv.id AND s.status IN ('active','trialing')) AS clienti
         FROM package_versions pv JOIN packages pk ON pk.id = pv.package_id ORDER BY pk.sort, pv.version DESC");
    $ordini = Db::all(
        'SELECT o.*, pk.name AS package, u.email, u.name AS cliente, a.id AS account_id FROM orders o
         JOIN package_versions pv ON pv.id = o.package_version_id JOIN packages pk ON pk.id = pv.package_id
         JOIN accounts a ON a.id = o.account_id JOIN users u ON u.id = a.user_id ORDER BY o.id DESC LIMIT 8');

    $avvisi = [];
    if (!Stripe::enabled()) $avvisi[] = ['Stripe non è configurato', 'Senza chiave segreta e segreto del webhook nessuno può pubblicare: i clienti possono preparare la guida ma non pagarla.'];
    if (Config::get('mail')['transport'] === 'log') $avvisi[] = ['La posta non parte', 'Le email di verifica e di recupero password finiscono in storage/logs/mail.log. Configura MAIL_TRANSPORT=smtp.'];
    if (Config::get('storage')['driver'] !== 's3') $avvisi[] = ['I media stanno sul disco', 'In produzione imposta MHW_STORAGE=s3 con bucket e credenziali AWS.'];
    if (Demo::presente()) $avvisi[] = ['Ci sono ancora i clienti di esempio', 'Sono account veri con una password nota. Toglili prima di aprire al pubblico.'];

    View::out('admin/dashboard', ['numeri' => $numeri, 'piani' => $piani, 'ordini' => $ordini, 'avvisi' => $avvisi, 'funnel' => Stats::funnel(30),
                                  'esempi' => Demo::presente(), 'nav' => 'admin'], 'layout/cms');
});

// ------------------------------------------------------------------- clienti
$r->get('/admin/clienti', function () {
    Auth::requireAdmin();
    $cerca = trim((string) ($_GET['q'] ?? ''));
    $sql = 'SELECT u.id AS user_id, u.email, u.name, u.created_at, u.email_verified_at, a.id AS account_id
            FROM users u JOIN accounts a ON a.user_id = u.id WHERE u.role = ?';
    $args = ['host'];
    if ($cerca !== '') {
        $sql .= ' AND (u.name LIKE ? OR u.email LIKE ? OR EXISTS (SELECT 1 FROM properties p WHERE p.account_id = a.id AND p.name LIKE ?))';
        array_push($args, "%$cerca%", "%$cerca%", "%$cerca%");
    }
    $rows = Db::all($sql . ' ORDER BY u.id DESC LIMIT 300', $args);
    foreach ($rows as &$row) {
        $aid = (int) $row['account_id'];
        $sub = Subscriptions::active($aid);
        $gov = Subscriptions::governingVersionId($aid);
        $v = $gov ? Plans::version($gov) : null;
        $row['plan'] = $v['name'] ?? '—';
        $row['plan_version'] = $v['version'] ?? null;
        $props = Db::all('SELECT name, city, status FROM properties WHERE account_id = ? ORDER BY id', [$aid]);
        $row['struttura'] = $props[0]['name'] ?? '';
        $row['citta'] = $props[0]['city'] ?? '';
        $row['strutture'] = count($props);
        $pubbl = count(array_filter($props, fn($p) => $p['status'] === 'published'));
        $row['stato'] = match (true) {
            (bool) $sub && $sub['status'] === 'past_due' => ['Pagamento non riuscito', 'alert'],
            (bool) $sub && $pubbl > 0 => ['Attivo', 'pine'],
            (bool) $sub => ['Pagato, non pubblicato', 'ochre'],
            !$props => ['Nessuna struttura', 'ochre'],
            $pubbl > 0 => ['Scaduto (offline)', 'alert'],
            default => ['In bozza, senza piano pagato', 'ochre'],
        };
    }
    unset($row);
    View::out('admin/customers', ['rows' => $rows, 'cerca' => $cerca, 'esempi' => Demo::presente(), 'nav' => 'clienti'], 'layout/cms');
});

$r->post('/admin/entra/{uid}', function (array $a) {
    Auth::requireAdmin();
    Auth::impersonate((int) $a['uid']);
    Support::flash('Stai usando l\'account di un cliente. La sua password non è mai stata mostrata; la sessione finisce da sola dopo un\'ora.');
    Support::redirect('/pannello');
});

$r->post('/admin/esci-da-cliente', function () {
    Auth::stopImpersonating();
    Support::redirect('/admin/clienti');
});

$r->get('/admin/cliente/{aid}', function (array $a) {
    Auth::requireAdmin();
    $acc = Db::one('SELECT a.*, u.email, u.name AS user_name, u.id AS user_id, u.email_verified_at, u.terms_version,
                           u.terms_accepted_at, u.privacy_version, u.privacy_accepted_at, u.created_at AS registrato
                    FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [(int) $a['aid']]);
    if (!$acc) { http_response_code(404); View::out('pub/404', []); }
    $props = Db::all('SELECT * FROM properties WHERE account_id = ? ORDER BY id', [$acc['id']]);
    foreach ($props as &$p) {
        $p['lingue'] = implode(', ', array_column(Db::all('SELECT locale FROM property_locales WHERE property_id = ?', [$p['id']]), 'locale'));
        $p['online'] = Subscriptions::propertyOnline($p);
        $p['qr'] = Db::one('SELECT token, scans FROM qr_tokens WHERE property_id = ?', [$p['id']]);
    }
    unset($p);
    View::out('admin/customer', [
        'acc' => $acc, 'props' => $props,
        'subs' => Db::all('SELECT s.*, pk.name AS package, pv.version FROM subscriptions s JOIN package_versions pv ON pv.id = s.package_version_id
                           JOIN packages pk ON pk.id = pv.package_id WHERE s.account_id = ? ORDER BY s.id DESC', [$acc['id']]),
        'orders' => Db::all('SELECT o.*, pk.name AS package FROM orders o JOIN package_versions pv ON pv.id = o.package_version_id
                             JOIN packages pk ON pk.id = pv.package_id WHERE o.account_id = ? ORDER BY o.id DESC', [$acc['id']]),
        'ent' => Entitlements::forAccount((int) $acc['id']),
        'versioni' => Db::all('SELECT pv.id, pv.version, pk.name FROM package_versions pv JOIN packages pk ON pk.id = pv.package_id
                               WHERE pv.is_current = 1 ORDER BY pk.sort'),
        'audit' => Db::all('SELECT * FROM audit_log WHERE target_user_id = ? ORDER BY id DESC LIMIT 50', [$acc['user_id']]),
        'nav' => 'clienti',
    ], 'layout/cms');
});

$r->post('/admin/cliente/{aid}/override', function (array $a) {
    $admin = Auth::requireAdmin();
    $accId = (int) $a['aid'];
    $f = Db::one('SELECT * FROM features WHERE code = ?', [(string) ($_POST['feature'] ?? '')]);
    if (!$f) { http_response_code(404); View::out('pub/404', []); }
    $val = trim((string) ($_POST['valore'] ?? ''));
    if ($val !== '' && !preg_match('/^(\d{1,4}|unlimited)$/', $val)) {
        Support::flash('Valore non valido: un numero, 0/1 per sì e no, oppure unlimited.', 'err');
        Support::redirect('/admin/cliente/' . $accId);
    }
    $uid = (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$accId]);
    if ($val === '') {
        Db::run('DELETE FROM entitlement_overrides WHERE account_id = ? AND feature_id = ?', [$accId, $f['id']]);
        Support::flash('Eccezione rimossa: torna a valere il piano.');
    } else {
        $ex = Db::one('SELECT id FROM entitlement_overrides WHERE account_id = ? AND feature_id = ?', [$accId, $f['id']]);
        if ($ex) Db::update('entitlement_overrides', ['value' => $val, 'note' => (string) ($_POST['nota'] ?? '')], 'id = :oid', ['oid' => $ex['id']]);
        else Db::insert('entitlement_overrides', ['account_id' => $accId, 'feature_id' => $f['id'], 'value' => $val, 'note' => (string) ($_POST['nota'] ?? '')]);
        Support::flash('Eccezione salvata per questo cliente.');
    }
    Auth::audit('entitlement.override', $uid, ['feature' => $f['code'], 'value' => $val]);
    Entitlements::forget($accId);
    Support::redirect('/admin/cliente/' . $accId);
});

/** Un abbonamento concesso a mano: omaggi, demo, pagamenti arrivati per altre vie. */
$r->post('/admin/cliente/{aid}/abbonamento', function (array $a) {
    Auth::requireAdmin();
    $accId = (int) $a['aid'];
    if (!Db::one('SELECT id FROM accounts WHERE id = ?', [$accId])) { http_response_code(404); View::out('pub/404', []); }
    $pv = Db::one('SELECT id FROM package_versions WHERE id = ?', [(int) ($_POST['pv'] ?? 0)]);
    $mesi = max(1, min(36, (int) ($_POST['mesi'] ?? 12)));
    $nota = trim((string) ($_POST['nota'] ?? ''));
    if (!$pv || $nota === '') {
        Support::flash('Scegli un piano e scrivi il motivo: resta nel registro.', 'err');
        Support::redirect('/admin/cliente/' . $accId);
    }
    Billing::grantManual($accId, (int) $pv['id'], $mesi, $nota, max(1, (int) ($_POST['strutture'] ?? 1)));
    Support::flash("Abbonamento manuale attivo per $mesi mesi. Non compare nell'incasso.");
    Support::redirect('/admin/cliente/' . $accId);
});

/*
 * La guida vetrina (Demo::vetrina) dentro l'account di un cliente vero: una sola per account.
 * Foto, lingue e luoghi seguono il piano: se l'account non ha un abbonamento attivo, si può
 * concedere Plus dimostrativo per 12 mesi (lo stesso abbonamento manuale qui sopra, nel registro).
 */
$r->post('/admin/cliente/{aid}/vetrina', function (array $a) {
    Auth::requireAdmin();
    $accId = (int) $a['aid'];
    $uid = (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$accId]);
    if (!$uid) { http_response_code(404); View::out('pub/404', []); }
    if (Db::val('SELECT id FROM properties WHERE account_id = ? AND is_demo = ?', [$accId, Demo::VETRINA])) {
        Support::flash('Questo account ha già una guida vetrina: per rifarla, eliminala dal pannello del cliente.', 'err');
        Support::redirect('/admin/cliente/' . $accId);
    }
    if (!Subscriptions::active($accId) && ($_POST['plus'] ?? '') === '1') {
        $pv = (int) Db::val("SELECT pv.id FROM package_versions pv JOIN packages p ON p.id = pv.package_id WHERE p.code = 'plus' AND pv.is_current = 1", [], 0);
        if ($pv) Billing::grantManual($accId, $pv, 12, 'Guida vetrina: Plus dimostrativo');
    }
    Entitlements::forget($accId);
    try {
        $pid = Demo::vetrina($accId);
    } catch (\Throwable $e) {
        Log::error('vetrina: ' . $e->getMessage(), ['account' => $accId]);
        Support::flash('La guida vetrina non è stata creata: ' . $e->getMessage(), 'err');
        Support::redirect('/admin/cliente/' . $accId);
    }
    Auth::audit('demo.vetrina', $uid, ['property_id' => $pid]);
    Support::flash('Guida vetrina «Casa dei Gerani» creata e pubblicata. È la demo della landing.');
    Support::redirect('/admin/cliente/' . $accId);
});

$r->post('/admin/dati-esempio', function () {
    Auth::requireAdmin();
    if (($_POST['cosa'] ?? '') === 'elimina') {
        $n = Demo::rimuovi();
        Auth::audit('demo.remove', null, ['accounts' => $n]);
        Support::flash($n . ' clienti di esempio eliminati, con le loro guide e le loro foto.');
    } elseif (Demo::presente()) {
        Support::flash('Ci sono già: toglili prima di ricrearli.', 'err');
    } else {
        $creati = Demo::popola();
        Auth::audit('demo.create', null, ['accounts' => count($creati)]);
        Support::flash('Creati ' . count($creati) . ' clienti di esempio. Password: ' . Demo::PASSWORD . '.');
    }
    Support::redirect('/admin/clienti');
});

// -------------------------------------------------------------- abbonamenti
$r->get('/admin/abbonamenti', function () {
    Auth::requireAdmin();
    $subs = Db::all(
        'SELECT s.*, pk.name AS package, pv.version, pv.price_cents, pv.currency, u.email, u.name AS cliente, a.id AS account_id
         FROM subscriptions s JOIN package_versions pv ON pv.id = s.package_version_id JOIN packages pk ON pk.id = pv.package_id
         JOIN accounts a ON a.id = s.account_id JOIN users u ON u.id = a.user_id ORDER BY s.id DESC LIMIT 300');
    foreach ($subs as &$s) $s['valido'] = Subscriptions::valid($s, (int) (Config::get('billing')['grace_days'] ?? 0));
    unset($s);
    View::out('admin/subscriptions', ['subs' => $subs, 'nav' => 'abbonamenti'], 'layout/cms');
});

// -------------------------------------------------------------------- guide
$r->get('/admin/guide', function () {
    Auth::requireAdmin();
    $guide = Db::all(
        'SELECT p.*, u.email, u.name AS cliente, a.id AS account_id,
                (SELECT token FROM qr_tokens q WHERE q.property_id = p.id) AS qr_token,
                (SELECT scans FROM qr_tokens q WHERE q.property_id = p.id) AS qr_scans,
                (SELECT MAX(version) FROM guide_versions g WHERE g.property_id = p.id) AS versione
         FROM properties p JOIN accounts a ON a.id = p.account_id JOIN users u ON u.id = a.user_id ORDER BY p.id DESC LIMIT 300');
    foreach ($guide as &$g) {
        $g['online'] = Subscriptions::propertyOnline($g);
        $g['lingue'] = implode(', ', array_column(Db::all('SELECT locale FROM property_locales WHERE property_id = ?', [$g['id']]), 'locale'));
    }
    unset($g);
    View::out('admin/guides', ['guide' => $guide, 'nav' => 'guide'], 'layout/cms');
});

$r->get('/admin/registro', function () {
    Auth::requireAdmin();
    $righe = Db::all(
        'SELECT l.*, ua.email AS attore, ut.email AS bersaglio FROM audit_log l
         LEFT JOIN users ua ON ua.id = l.actor_user_id LEFT JOIN users ut ON ut.id = l.target_user_id
         ORDER BY l.id DESC LIMIT 300');
    View::out('admin/audit', ['righe' => $righe, 'nav' => 'registro'], 'layout/cms');
});

// ----------------------------------------------------------------- pacchetti
$r->get('/admin/pacchetti', function () {
    Auth::requireAdmin();
    $packages = Db::all('SELECT * FROM packages ORDER BY sort, id');
    foreach ($packages as &$p) {
        $p['versions'] = Db::all('SELECT * FROM package_versions WHERE package_id = ? ORDER BY version DESC', [$p['id']]);
        foreach ($p['versions'] as &$v) {
            $v['features'] = array_column(Db::all(
                'SELECT f.code, pf.value FROM package_features pf JOIN features f ON f.id = pf.feature_id
                 WHERE pf.package_version_id = ?', [$v['id']]), 'value', 'code');
            $v['clienti'] = (int) Db::val("SELECT COUNT(*) FROM subscriptions WHERE package_version_id = ? AND status IN ('active','trialing')", [$v['id']], 0);
        }
        unset($v);
    }
    unset($p);
    View::out('admin/packages', ['packages' => $packages, 'features' => Db::all('SELECT * FROM features ORDER BY id'), 'nav' => 'pacchetti'], 'layout/cms');
});

/**
 * Cambiare prezzo o contenuto crea una versione NUOVA: quelle già vendute non
 * si toccano, e gli abbonamenti restano agganciati alla loro. Il testo
 * commerciale (titoli, elenco puntato) invece si aggiorna sul pacchetto.
 */
$r->post('/admin/pacchetti/{pid}/nuova-versione', function (array $a) {
    Auth::requireAdmin();
    $pkg = Db::one('SELECT * FROM packages WHERE id = ?', [(int) $a['pid']]);
    if (!$pkg) { http_response_code(404); View::out('pub/404', []); }
    $prezzo = (int) round(((float) str_replace(',', '.', (string) ($_POST['prezzo'] ?? '0'))) * 100);
    if ($prezzo < 0 || $prezzo > 10_000_000) { Support::flash('Prezzo non valido.', 'err'); Support::redirect('/admin/pacchetti'); }
    $priceId = trim((string) ($_POST['stripe_price_id'] ?? ''));
    if ($priceId !== '' && !preg_match('/^price_[A-Za-z0-9]+$/', $priceId)) {
        Support::flash('Il Price ID di Stripe comincia con price_.', 'err'); Support::redirect('/admin/pacchetti');
    }
    // Piani a struttura (Portfolio): prezzo per struttura aggiuntiva, minimo, massimo.
    $corrente = Db::one('SELECT * FROM package_versions WHERE package_id = ? AND is_current = 1', [$pkg['id']]) ?: [];
    $aStruttura = ['per_property' => 0, 'extra_price_cents' => 0, 'min_quantity' => 1, 'max_quantity' => 1, 'stripe_extra_price_id' => ''];
    if (Plans::perProperty($corrente)) {
        $extra = (int) round(((float) str_replace(',', '.', (string) ($_POST['prezzo_extra'] ?? '0'))) * 100);
        $min = (int) ($_POST['min_quantita'] ?? 2); $max = (int) ($_POST['max_quantita'] ?? 50);
        $extraId = trim((string) ($_POST['stripe_extra_price_id'] ?? ''));
        if ($extra < 0 || $extra > 10_000_000 || $min < 1 || $max < $min || $max > 500 || ($extraId !== '' && !preg_match('/^price_[A-Za-z0-9]+$/', $extraId))) {
            Support::flash('Controlla prezzo per struttura aggiuntiva, minimo, massimo e Price ID.', 'err'); Support::redirect('/admin/pacchetti');
        }
        $aStruttura = ['per_property' => 1, 'extra_price_cents' => $extra, 'min_quantity' => $min, 'max_quantity' => $max, 'stripe_extra_price_id' => $extraId];
    }
    $vid = Db::tx(function () use ($pkg, $prezzo, $priceId, $aStruttura) {
        $next = (int) Db::val('SELECT COALESCE(MAX(version),0)+1 FROM package_versions WHERE package_id = ?', [$pkg['id']], 1);
        Db::run('UPDATE package_versions SET is_current = 0 WHERE package_id = ?', [$pkg['id']]);
        $vid = Db::insert('package_versions', [
            'package_id' => $pkg['id'], 'version' => $next, 'price_cents' => $prezzo, 'currency' => 'EUR',
            'interval_unit' => 'year', 'is_current' => 1, 'sold_count' => 0, 'stripe_price_id' => $priceId, 'created_at' => Support::now(),
        ] + $aStruttura);
        foreach (Db::all('SELECT * FROM features ORDER BY id') as $f) {
            $val = trim((string) ($_POST['f'][$f['code']] ?? '0'));
            if (!preg_match('/^(\d{1,4}|unlimited)$/', $val)) $val = '0';
            Db::insert('package_features', ['package_version_id' => $vid, 'feature_id' => $f['id'], 'value' => $val]);
        }
        Db::update('packages', [
            'name' => trim((string) $_POST['nome']) ?: $pkg['name'],
            'tagline' => trim((string) ($_POST['tagline'] ?? '')), 'headline' => trim((string) ($_POST['headline'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')), 'bullets' => trim((string) ($_POST['bullets'] ?? '')),
            'badge' => trim((string) ($_POST['badge'] ?? '')), 'cta_label' => trim((string) ($_POST['cta_label'] ?? '')),
            'public' => !empty($_POST['public']) ? 1 : 0, 'active' => !empty($_POST['active']) ? 1 : 0,
        ], 'id = :pid', ['pid' => $pkg['id']]);
        return $vid;
    });
    Auth::audit('package.new_version', null, ['package' => $pkg['code'], 'version_id' => $vid, 'price_cents' => $prezzo]);
    Entitlements::forget();
    Support::flash('Creata una versione nuova di ' . $pkg['name'] . '. Chi era sulla precedente ci resta, con quello che aveva comprato.');
    Support::redirect('/admin/pacchetti');
});

/** Solo il testo commerciale: nessuna versione nuova, i diritti non cambiano. */
$r->post('/admin/pacchetti/{pid}/testo', function (array $a) {
    Auth::requireAdmin();
    $pkg = Db::one('SELECT * FROM packages WHERE id = ?', [(int) $a['pid']]);
    if (!$pkg) { http_response_code(404); View::out('pub/404', []); }
    Db::update('packages', [
        'tagline' => trim((string) ($_POST['tagline'] ?? '')), 'headline' => trim((string) ($_POST['headline'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')), 'bullets' => trim((string) ($_POST['bullets'] ?? '')),
        'badge' => trim((string) ($_POST['badge'] ?? '')), 'cta_label' => trim((string) ($_POST['cta_label'] ?? '')),
        'public' => !empty($_POST['public']) ? 1 : 0, 'active' => !empty($_POST['active']) ? 1 : 0,
    ], 'id = :pid', ['pid' => $pkg['id']]);
    Auth::audit('package.copy', null, ['package' => $pkg['code']]);
    Support::flash('Testo di ' . $pkg['name'] . ' aggiornato.');
    Support::redirect('/admin/pacchetti');
});

// --------------------------------------------------------------- diagnostica
/**
 * Queste voci non sono promesse: il server interroga davvero sé stesso,
 * e prova perfino a scaricare il proprio database.
 */
$r->get('/admin/diagnostica', function () {
    Auth::requireAdmin();
    $base = Support::baseUrl();
    // Le sonde cercano i file veri: senza riscrittura degli indirizzi la base
    // finisce con /index.php, che qui va tolto.
    $cartella = preg_replace('#/index\.php$#', '', $base);
    $checks = [];
    $probe = function (string $url): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6, CURLOPT_FOLLOWLOCATION => false]);
        $body = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'bytes' => strlen($body)];
    };

    $inWebroot = str_starts_with(realpath(MHW_APP) ?: '', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: "\0");
    $checks[] = ['Applicazione fuori dalla cartella pubblica', !$inWebroot,
        $inWebroot ? "L'applicazione sta dentro la cartella pubblica: la protezione dipende da .htaccess, che non tutti i server leggono."
                   : 'Niente del codice è raggiungibile dal web.'];
    if ($inWebroot) {
        $dbFile = basename(\MHW\Db::sqlitePath(Config::get('db')['sqlite_path']));
        $res = $probe($cartella . '/app/storage/' . $dbFile);
        $esposto = $res['code'] === 200 && $res['bytes'] > 0;
        $checks[] = ['Database non scaricabile dal web', !$esposto,
            $esposto ? 'ATTENZIONE: il database risponde dal web. Sposta la cartella app/ sopra la radice pubblica.' : 'Il server rifiuta la richiesta.'];
        $res = $probe($cartella . '/app/config.php');
        $checks[] = ['config.php non leggibile', $res['bytes'] === 0, $res['bytes'] === 0 ? 'Non esce nulla.' : 'ATTENZIONE: config.php restituisce contenuto.'];
        foreach (['mail.log' => 'Posta di prova non leggibile dal web (contiene link di accesso)'] as $f => $titolo) {
            @touch(MHW_APP . '/storage/logs/' . $f);
            $res = $probe($cartella . '/app/storage/logs/' . $f);
            $checks[] = [$titolo, !($res['code'] === 200), $res['code'] === 200 ? 'ATTENZIONE: storage/logs è raggiungibile dal web.' : 'Il server rifiuta la richiesta.'];
        }
        $res = $probe($cartella . '/app/storage/logs/app.log');
        $checks[] = ['Registro tecnico non leggibile dal web', !($res['code'] === 200 && $res['bytes'] > 0),
            $res['code'] === 200 && $res['bytes'] > 0 ? 'ATTENZIONE: storage/logs è raggiungibile dal web.' : 'Il server rifiuta la richiesta.'];
    }

    $checks[] = ['Estensione GD (immagini e QR)', extension_loaded('gd'), phpversion('gd') ?: 'assente'];
    $checks[] = ['Estensione fileinfo (controllo dei PDF)', extension_loaded('fileinfo'), extension_loaded('fileinfo') ? 'presente' : 'assente'];
    $checks[] = ['Connessione al database', (bool) \MHW\Db::val('SELECT 1'), Config::get('db')['driver']];
    $checks[] = ['Pagamenti Stripe', Stripe::enabled(),
        Stripe::enabled() ? 'chiave segreta e segreto del webhook presenti' : 'mancano STRIPE_SECRET_KEY o STRIPE_WEBHOOK_SECRET: nessuno può pubblicare'];
    $checks[] = ['Indirizzo del webhook', true, 'In Stripe punta il webhook a ' . $base . '/webhook/stripe'];
    $mail = Config::get('mail');
    $checks[] = ['Posta in uscita', $mail['transport'] !== 'log',
        $mail['transport'] === 'log' ? 'le email finiscono in storage/logs/mail.log: imposta MAIL_TRANSPORT=smtp' : 'trasporto ' . $mail['transport']];
    $st = Config::get('storage');
    $s3ok = false; $s3det = 'MHW_STORAGE non è s3: i file restano sul disco del server';
    if ($st['driver'] === 's3') {
        try { Storages::current(); $s3ok = true; $s3det = 'bucket ' . $st['s3']['bucket'] . ' (' . $st['s3']['region'] . ')'; }
        catch (\Throwable $e) { $s3det = $e->getMessage(); }
    }
    $checks[] = ['Media su Amazon S3', $s3ok, $s3det];
    $checks[] = ['HTTPS', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || str_starts_with($base, 'https'),
        'i cookie di sessione diventano "secure" solo su HTTPS'];
    $checks[] = ['Indirizzo pubblico impostato', Config::get('base_url') !== '',
        Config::get('base_url') !== '' ? Config::get('base_url') : 'MHW_BASE_URL vuoto: QR ed email usano l\'indirizzo della richiesta'];
    $checks[] = ['Traduzione automatica', true, 'Non attiva, per scelta: le lingue le scrive l\'host.'];

    View::out('admin/diagnostics', ['checks' => $checks, 'base' => $base, 'nav' => 'diagnostica'], 'layout/cms');
});

/* Rigenera le foto WebP del borgo dai .jpg (app/tools/foto.php). */
$r->post('/admin/diagnostica/foto', function () {
    $admin = Auth::requireAdmin();
    require_once MHW_APP . '/tools/foto.php';
    $esiti = mhw_rigenera_foto(MHW_PUBLIC . '/assets/foto');
    Auth::audit('tools.foto', null, ['esiti' => $esiti]);
    $falliti = array_filter($esiti, fn($e) => !$e[1]);
    Support::flash($falliti ? 'Non tutte: ' . implode('; ', array_map(fn($e) => $e[0] . ' — ' . $e[2], $falliti))
                            : 'Foto WebP rigenerate: ' . implode(', ', array_column($esiti, 0)) . '.', $falliti ? 'err' : 'ok');
    Support::redirect('/admin/diagnostica');
});

// ------------------------------------------------------------- testimonianze
/**
 * Le testimonianze della landing: le scrive l'amministratore, una per una, con
 * il consenso di chi parla. Nessuna d'esempio. In landing il blocco compare
 * solo se almeno una è visibile.
 */
$r->get('/admin/testimonianze', function () {
    Auth::requireAdmin();
    View::out('admin/testimonials', ['righe' => MHW\Testimonianze::tutte(), 'nav' => 'testimonianze'], 'layout/cms');
});

$r->post('/admin/testimonianze', function () {
    $admin = Auth::requireAdmin();
    try {
        $id = MHW\Testimonianze::salva($_POST, $_FILES['foto'] ?? null);
        Auth::audit('testimonial.save', null, ['id' => $id]);
        Support::flash('Testimonianza salvata.' . (empty($_POST['visible']) ? ' Non è visibile: spunta «Visibile in landing» quando vuoi mostrarla.' : ''));
    } catch (\Throwable $e) {
        Support::flash($e instanceof \RuntimeException ? $e->getMessage() : 'Non salvata (codice ' . Log::exception($e, 'testimonianze') . ').', 'err');
    }
    Support::redirect('/admin/testimonianze');
});

$r->post('/admin/testimonianze/{tid}/elimina', function (array $a) {
    $admin = Auth::requireAdmin();
    MHW\Testimonianze::elimina((int) $a['tid']);
    Auth::audit('testimonial.delete', null, ['id' => (int) $a['tid']]);
    Support::flash('Testimonianza eliminata.');
    Support::redirect('/admin/testimonianze');
});
