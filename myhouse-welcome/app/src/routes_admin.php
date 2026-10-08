<?php
/** Rotte di amministrazione. $r è il Router creato in public/index.php. */

use MHW\{Auth, Billing, Config, Db, Demo, Entitlements, Gestione, Impostazioni, Inviti, Log, Media, Plans, RateLimit, Richiami, Stats, Storages, Stripe, Subscriptions, Support, Traduttore, View};

/**
 * Il quadro: quanti clienti, quanto hanno pagato davvero, quanto viene letto
 * quello che scrivono. Numeri interrogati adesso. Gli abbonamenti di prova,
 * manuali e dimostrativi NON entrano nell'incasso.
 */
$r->get('/admin', function () {
    Auth::requireAdmin();
    Demo::vetrinaAutomatica();
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
    if (!Stripe::enabled()) $avvisi[] = ['Stripe non è configurato', 'Senza chiave segreta e segreto del webhook nessuno può pubblicare: i clienti possono preparare la guida ma non pagarla. Inseriscili nelle Impostazioni.', '/admin/impostazioni#stripe'];
    if (Config::get('mail')['transport'] === 'log') $avvisi[] = ['La posta non parte', 'Le email di conferma e di recupero della password finiscono in storage/logs/mail.log. Imposta il server SMTP nelle Impostazioni.', '/admin/impostazioni#posta'];
    if (Config::get('storage')['driver'] !== 's3') $avvisi[] = ['Foto e PDF stanno sul disco del server', 'In produzione usa Amazon S3: bucket e credenziali si inseriscono nelle Impostazioni.', '/admin/impostazioni#archivio'];
    $usati = Traduttore::usati(); $tetto = Traduttore::tetti()['sito'];
    if ($tetto > 0 && $usati > $tetto * 0.8) $avvisi[] = ['Traduzioni suggerite oltre l\'80% del tetto del mese', 'Usati ' . number_format($usati, 0, ',', '.') . ' caratteri su ' . number_format($tetto, 0, ',', '.') . ': al tetto le richieste si fermano fino al primo del mese.', '/admin/traduzioni'];
    if (Demo::presente()) $avvisi[] = ['Ci sono ancora i clienti di esempio', 'Sono account veri con una password nota. Toglili prima di aprire al pubblico.'];
    $anomalie = Gestione::contaAnomalie();
    if ($anomalie['alta'] > 0) $avvisi[] = ['Ci sono anomalie da guardare subito', $anomalie['alta'] . ($anomalie['alta'] === 1 ? ' controllo segnala' : ' controlli segnalano') . ' pagamenti o rinnovi che non tornano.', '/admin/anomalie', 'Guarda le anomalie'];
    $adesso = Gestione::adesso();
    $numeri['arr'] = $adesso['arr'];
    $numeri['rinnovi30'] = $adesso['rinnovi']['30'];
    $numeri['scadono30'] = count(array_filter(Gestione::scadenze(30), fn($x) => !$x['automatico'] && !$x['finito']));
    $numeri['anomalie'] = $anomalie;

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
            (bool) $sub && $sub['status'] === 'past_due' => ['Pagamento non riuscito', 'alert', 'fallito'],
            (bool) $sub && $pubbl > 0 => ['Attivo', 'pine', 'attivo'],
            (bool) $sub => ['Pagato, non pubblicato', 'ochre', 'pagato'],
            !$props => ['Nessuna struttura', 'ochre', 'vuoto'],
            $pubbl > 0 => ['Scaduto (offline)', 'alert', 'scaduto'],
            default => ['In bozza, senza piano pagato', 'ochre', 'bozza'],
        };
        $row['scadenza'] = $sub['current_period_end'] ?? '';
        $row['rinnovo'] = $sub ? ($sub['provider'] !== 'stripe' ? 'staff' : ((int) $sub['cancel_at_period_end'] ? 'disattivato' : 'automatico')) : '';
    }
    unset($row);
    // Filtri: stato e piano. Si applicano dopo il calcolo, perché lo stato non è una colonna.
    $filtroStato = (string) ($_GET['stato'] ?? ''); $filtroPiano = (string) ($_GET['piano'] ?? '');
    $piani = array_values(array_unique(array_filter(array_column($rows, 'plan'), fn($p) => $p !== '—')));
    $rows = array_values(array_filter($rows, fn($x) => ($filtroStato === '' || $x['stato'][2] === $filtroStato) && ($filtroPiano === '' || $x['plan'] === $filtroPiano)));
    if (($_GET['formato'] ?? '') === 'csv') {
        Gestione::csv('clienti', ['Nome', 'Email', 'Email confermata', 'Struttura', 'Città', 'Strutture', 'Piano', 'Stato', 'Scadenza', 'Rinnovo', 'Registrato'],
            array_map(fn($x) => [$x['name'], $x['email'], $x['email_verified_at'] ? 'sì' : 'no', $x['struttura'], $x['citta'], $x['strutture'], $x['plan'],
                                 $x['stato'][0], $x['scadenza'] ? substr($x['scadenza'], 0, 10) : '', $x['rinnovo'], substr((string) $x['created_at'], 0, 10)], $rows));
    }
    View::out('admin/customers', ['rows' => $rows, 'cerca' => $cerca, 'filtroStato' => $filtroStato, 'filtroPiano' => $filtroPiano, 'piani' => $piani,
                                  'esempi' => Demo::presente(), 'nav' => 'clienti'], 'layout/cms');
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
        'email' => Richiami::disponibili() ? Db::all('SELECT kind, ref, sent_at FROM email_log WHERE account_id = ? ORDER BY sent_at DESC, id DESC LIMIT 20', [$acc['id']]) : [],
        'optout' => Richiami::disponibili() ? array_column(Db::all('SELECT kind FROM email_optout WHERE account_id = ?', [$acc['id']]), 'kind') : [],
        'inviti' => Inviti::disponibili() ? ['stato' => Inviti::stato($acc), 'amici' => Inviti::amici((int) $acc['id']), 'invitato' => Inviti::invitato((int) $acc['id'])] : null,
        'traduzioni' => \MHW\Migrator::tableExists('translation_usage') ? ['mese' => Traduttore::usati((int) $acc['id']), 'omaggio' => Traduttore::omaggioFino((int) $acc['id'])] : null,
        'nav' => 'clienti',
    ], 'layout/cms');
});

/** La nota interna sul cliente: solo per l'amministrazione, mai mostrata al cliente. */
$r->post('/admin/cliente/{aid}/nota', function (array $a) {
    Auth::requireAdmin();
    $accId = (int) $a['aid'];
    $uid = (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$accId]);
    if (!$uid) { http_response_code(404); View::out('pub/404', []); }
    $nota = mb_substr(trim((string) ($_POST['nota'] ?? '')), 0, 4000);
    Db::update('accounts', ['admin_note' => $nota, 'admin_note_at' => Support::now()], 'id = :aid', ['aid' => $accId]);
    Auth::audit('account.note', $uid, ['chars' => mb_strlen($nota)]);
    Support::flash($nota === '' ? 'Nota tolta.' : 'Nota salvata.');
    Support::redirect('/admin/cliente/' . $accId . '#nota');
});

$r->post('/admin/cliente/{aid}/override', function (array $a) {
    $admin = Auth::requireAdmin();
    $accId = (int) $a['aid'];
    $f = Db::one('SELECT * FROM features WHERE code = ?', [(string) ($_POST['feature'] ?? '')]);
    if (!$f) { http_response_code(404); View::out('pub/404', []); }
    $val = trim((string) ($_POST['valore'] ?? ''));
    if ($val !== '' && !preg_match('/^(\d{1,4}|unlimited)$/', $val)) {
        Support::flash('Valore non valido: scrivi un numero, 1 per sì e 0 per no, oppure unlimited.', 'err');
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
    // «Rifai»: la vetrina vecchia si toglie e se ne crea una con i dati aggiornati.
    $rifai = ($_POST['rifai'] ?? '') === '1';
    if (Db::val('SELECT id FROM properties WHERE account_id = ? AND is_demo = ?', [$accId, Demo::VETRINA])) {
        if (!$rifai) {
            Support::flash('Questo account ha già una guida vetrina: usa «Rifai la vetrina» per sostituirla.', 'err');
            Support::redirect('/admin/cliente/' . $accId);
        }
        Demo::eliminaVetrina($accId);
    }
    try {
        $pid = Demo::creaVetrina($accId, ($_POST['plus'] ?? '') === '1');
    } catch (\Throwable $e) {
        Log::error('vetrina: ' . $e->getMessage(), ['account' => $accId]);
        Support::flash('La guida vetrina non è stata creata: ' . $e->getMessage(), 'err');
        Support::redirect('/admin/cliente/' . $accId);
    }
    Auth::audit('demo.vetrina', $uid, ['property_id' => $pid]);
    Support::flash($rifai ? 'Guida vetrina rifatta con i dati aggiornati e pubblicata.' : 'Guida vetrina «Casa Checco» creata e pubblicata. È la demo della landing.');
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
    Support::flash('Testi di ' . $pkg['name'] . ' aggiornati.');
    Support::redirect('/admin/pacchetti');
});

// ---------------------------------------------------------------- traduzioni
/**
 * Le traduzioni suggerite (Traduttore): consumi e costi stimati del mese, gli
 * ultimi 12 mesi, chi traduce di più, quanto costerebbe tradurre tutto quello che
 * manca, e gli anni in omaggio (con la possibilità di allungarli). I costi sono stime.
 */
$r->get('/admin/traduzioni', function () {
    Auth::requireAdmin();
    $mese = Traduttore::mese();
    $usati = Traduttore::usati();
    $gratis = Traduttore::gratuitoAttivo();
    $mesi = Db::all("SELECT month, SUM(CASE WHEN outcome = 'ok' THEN chars ELSE 0 END) AS chars, SUM(CASE WHEN outcome = 'ok' THEN 1 ELSE 0 END) AS chiamate,
                            SUM(CASE WHEN outcome = 'errore' THEN 1 ELSE 0 END) AS errori, SUM(CASE WHEN outcome = 'limite' THEN 1 ELSE 0 END) AS limiti
                     FROM translation_usage GROUP BY month ORDER BY month DESC LIMIT 12");
    $primi = Db::all("SELECT t.account_id, SUM(t.chars) AS chars, u.email, u.name FROM translation_usage t
                      LEFT JOIN accounts a ON a.id = t.account_id LEFT JOIN users u ON u.id = a.user_id
                      WHERE t.month = ? AND t.outcome = 'ok' GROUP BY t.account_id, u.email, u.name ORDER BY chars DESC LIMIT 10", [$mese]);
    // Previsione: i clienti con le traduzioni suggerite nel piano, e i caratteri ancora da tradurre nelle lingue accese.
    $clienti = 0; $daTradurre = 0;
    foreach (Db::all('SELECT DISTINCT account_id FROM properties WHERE is_demo = 0 AND archived_at IS NULL') as $x) {
        if (!Traduttore::nelPiano((int) $x['account_id'])) continue;
        $clienti++;
        foreach (Db::all('SELECT * FROM properties WHERE account_id = ? AND is_demo = 0 AND archived_at IS NULL', [$x['account_id']]) as $p) {
            foreach (Db::all('SELECT locale FROM property_locales WHERE property_id = ? AND locale <> ?', [$p['id'], $p['default_locale']]) as $l) {
                foreach (Traduttore::campi($p, $l['locale']) as $c) if ($c['tradotto'] === '') $daTradurre += mb_strlen($c['origine']);
            }
        }
    }
    $omaggi = Db::all("SELECT a.id, a.translation_trial_until AS fino, a.translation_trial_by_admin AS admin, u.email, u.name FROM accounts a JOIN users u ON u.id = a.user_id
                       WHERE a.translation_trial_until IS NOT NULL ORDER BY a.translation_trial_until");
    $errori = Db::all("SELECT t.*, u.email FROM translation_usage t LEFT JOIN accounts a ON a.id = t.account_id LEFT JOIN users u ON u.id = a.user_id
                       WHERE t.outcome <> 'ok' ORDER BY t.id DESC LIMIT 8");
    View::out('admin/traduzioni', ['mese' => $mese, 'usati' => $usati, 'tetti' => Traduttore::tetti(), 'gratis' => $gratis, 'mesi' => $mesi, 'primi' => $primi,
        'clienti' => $clienti, 'daTradurre' => $daTradurre, 'omaggi' => $omaggi, 'errori' => $errori, 'configurato' => Traduttore::configurato(),
        'config' => Traduttore::config(), 'nav' => 'traduzioni'], 'layout/cms');
});

/** Allungare (o accorciare) l'anno in omaggio di un account: si sceglie la data di fine. */
$r->post('/admin/traduzioni/omaggio', function () {
    $admin = Auth::requireAdmin();
    $acc = (int) ($_POST['account'] ?? 0);
    $fino = (string) ($_POST['fino'] ?? '');
    if (!Db::val('SELECT id FROM accounts WHERE id = ?', [$acc]) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fino) || !checkdate((int) substr($fino, 5, 2), (int) substr($fino, 8, 2), (int) substr($fino, 0, 4))) {
        Support::flash('Scegli un cliente e una data valida.', 'err');
        Support::redirect('/admin/traduzioni#omaggi');
    }
    Db::update('accounts', ['translation_trial_until' => $fino . 'T23:59:59Z', 'translation_trial_by_admin' => 1], 'id = :aid', ['aid' => $acc]);
    Auth::audit('translation.trial', (int) $admin['id'], ['account' => $acc, 'fino' => $fino]);
    Support::flash('Omaggio delle traduzioni suggerite fino al ' . Support::date($fino . 'T12:00:00Z') . '.');
    Support::redirect('/admin/traduzioni#omaggi');
});

// ---------------------------------------------------------------- scadenze
/* Chi scade o si rinnova a breve, con l'ultimo avviso mandato e il prossimo automatico.
   «Manda il promemoria» parte subito, anche per più clienti insieme. */
$r->get('/admin/scadenze', function () {
    Auth::requireAdmin();
    $giorni = in_array((int) ($_GET['giorni'] ?? 60), [30, 60, 90, 180], true) ? (int) ($_GET['giorni'] ?? 60) : 60;
    $righe = Gestione::scadenze($giorni);
    if (($_GET['formato'] ?? '') === 'csv') {
        Gestione::csv('scadenze', ['Cliente', 'Email', 'Piano', 'Strutture', 'Scadenza', 'Giorni', 'Stato', 'Rinnovo (IVA esclusa)', 'Sconto inviti %', 'Ultimo avviso', 'Avviso a mano'],
            array_map(fn($x) => [$x['cliente'], $x['email'], $x['piano'], (int) ($x['quantity'] ?? 1), substr((string) $x['current_period_end'], 0, 10), $x['giorni'], $x['stato'][0],
                                 number_format($x['importo'] / 100, 2, ',', ''), $x['sconto'], $x['avviso'] ? substr((string) $x['avviso']['sent_at'], 0, 10) : '',
                                 $x['avviso'] && $x['avviso']['manuale'] ? 'sì' : ''], $righe));
    }
    $lock = MHW_APP . '/storage/richiami.lock';
    View::out('admin/scadenze', ['righe' => $righe, 'giorni' => $giorni, 'giro' => is_file($lock) ? gmdate('Y-m-d\TH:i:s\Z', (int) filemtime($lock)) : '',
                                 'cron' => (string) (Config::get('cron_token') ?? '') !== '', 'nav' => 'scadenze'], 'layout/cms');
});

$r->post('/admin/scadenze/promemoria', function () {
    Auth::requireAdmin();
    // «Manda il promemoria» su una riga manda solo quella; il bottone in fondo, le righe scelte.
    $ids = isset($_POST['solo']) ? [(int) $_POST['solo']] : array_values(array_unique(array_map('intval', (array) ($_POST['sub'] ?? []))));
    $torna = preg_match('#^/admin/(scadenze(\?giorni=\d+)?|cliente/\d+)$#', (string) ($_POST['torna'] ?? '')) ? (string) $_POST['torna'] : '/admin/scadenze';
    if (!$ids) { Support::flash('Scegli almeno un cliente.', 'err'); Support::redirect($torna); }
    $ok = 0; $no = [];
    foreach (array_slice($ids, 0, 100) as $id) {
        [$partito, $msg] = Richiami::promemoriaManuale($id);
        if ($partito) $ok++; else $no[] = $msg;
        Auth::audit('reminder.manual', (int) Db::val('SELECT a.user_id FROM subscriptions s JOIN accounts a ON a.id = s.account_id WHERE s.id = ?', [$id], 0) ?: null,
                    ['subscription' => $id, 'sent' => $partito]);
    }
    if (!$no) Support::flash($ok === 1 ? 'Promemoria mandato.' : "Promemoria mandati: $ok.");
    else Support::flash(($ok ? "Promemoria mandati: $ok. " : '') . 'Non partiti: ' . count($no) . '. ' . implode(' ', array_slice($no, 0, 5)), 'err');
    Support::redirect($torna);
});

// ---------------------------------------------------------------- anomalie
$r->get('/admin/anomalie', function () {
    Auth::requireAdmin();
    View::out('admin/anomalie', Gestione::anomalie() + ['nav' => 'anomalie'], 'layout/cms');
});

// --------------------------------------------------------------- prospetti
$r->get('/admin/prospetti', function () {
    Auth::requireAdmin();
    $mesi = Gestione::mesi();
    if (($_GET['formato'] ?? '') === 'csv') {
        $eur = fn(int $c) => number_format($c / 100, 2, ',', '');
        Gestione::csv('prospetti', ['Mese', 'Registrati', 'Nuovi abbonati', 'Incasso nuovi', 'Incasso cambi di piano', 'Rinnovi', 'Incasso rinnovi', 'Incasso totale', 'Persi'],
            array_map(fn($m) => [$m['mese'], $m['registrati'], $m['nuovi'], $eur($m['incasso_nuovi']), $eur($m['incasso_cambi']), $m['rinnovi'], $eur($m['incasso_rinnovi']),
                                 $eur($m['incasso']), $m['persi']], $mesi));
    }
    View::out('admin/prospetti', ['mesi' => $mesi, 'adesso' => Gestione::adesso(), 'nav' => 'prospetti'], 'layout/cms');
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
        $mail['transport'] === 'log' ? 'le email finiscono in storage/logs/mail.log: imposta il server SMTP nelle Impostazioni' : 'trasporto ' . $mail['transport']];
    $st = Config::get('storage');
    $s3ok = false; $s3det = 'MHW_STORAGE non è s3: i file restano sul disco del server';
    if ($st['driver'] === 's3') {
        try { Storages::current(); $s3ok = true; $s3det = 'bucket ' . $st['s3']['bucket'] . ' (' . $st['s3']['region'] . ')'; }
        catch (\Throwable $e) { $s3det = $e->getMessage(); }
    }
    $checks[] = ['Foto e PDF su Amazon S3', $s3ok, $s3det];
    $checks[] = ['HTTPS', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || str_starts_with($base, 'https'),
        'i cookie di sessione diventano "secure" solo su HTTPS'];
    $checks[] = ['Indirizzo pubblico impostato', Config::get('base_url') !== '',
        Config::get('base_url') !== '' ? Config::get('base_url') : 'MHW_BASE_URL vuoto: QR ed email usano l\'indirizzo della richiesta'];
    $daCompletare = MHW\CambioAbbonamento::inSospeso();
    $checks[] = ['Cambi di piano pagati da completare: ' . $daCompletare, $daCompletare === 0, $daCompletare === 0
        ? 'Nessuno: ogni differenza pagata è già applicata, su Stripe e nel sito.'
        : 'Pagati ma non ancora applicati su Stripe: il sito riprova da solo ogni 15 minuti (o col cron). Se restano, controlla le chiavi di Stripe.'];
    $checks[] = ['Traduzioni suggerite (Amazon Translate)', MHW\Traduttore::configurato(), MHW\Traduttore::configurato()
        ? 'Collegate: un traduttore automatico propone, il cliente approva. Consumi in Amministrazione → Traduzioni.'
        : 'Non collegate: senza chiavi i clienti Plus e Portfolio non ricevono suggerite. Le lingue le scrivono loro.'];

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

// -------------------------------------------------------------- impostazioni
/*
 * Stripe, posta e archivio delle foto dal pannello (Impostazioni scrive in
 * app/config.local.php). Ogni salvataggio chiede la password dell'amministratore.
 */
$impostazioni = function (array $admin, string $gruppo = '', array $errori = [], array $valori = [], int $codice = 200) {
    http_response_code($codice);
    View::out('admin/settings', ['locale' => Impostazioni::locale(), 'gruppo' => $gruppo, 'errori' => $errori, 'valori' => $valori,
                                 'webhook' => Support::baseUrl() . '/webhook/stripe',
                                 'scrivibile' => is_writable(MHW_APP) || is_writable(Impostazioni::file()),
                                 'emailAdmin' => (string) $admin['email'], 'nav' => 'impostazioni'], 'layout/cms');
};

$r->get('/admin/impostazioni', function () use ($impostazioni) {
    $impostazioni(Auth::requireAdmin());
});

$r->post('/admin/impostazioni/{gruppo}', function (array $a) use ($impostazioni) {
    $admin = Auth::requireAdmin();
    $gruppo = (string) $a['gruppo'];
    if (!isset(Impostazioni::GRUPPI[$gruppo])) { http_response_code(404); exit('Non trovato.'); }
    // Quello che l'amministratore ha scritto, segreti esclusi, per non farglielo riscrivere.
    $valori = [];
    foreach (Impostazioni::GRUPPI[$gruppo] as $percorso => $c) {
        if ($c[2] !== 'secret') $valori[Impostazioni::nome($percorso)] = (string) ($_POST[Impostazioni::nome($percorso)] ?? '');
    }
    if (!RateLimit::hit('impostazioni:' . (int) $admin['id'], 10, 900)) {
        $impostazioni($admin, $gruppo, ['_' => 'Troppi tentativi: riprova tra un quarto d\'ora.'], $valori, 429);
        return;
    }
    $hash = (string) Db::val('SELECT password_hash FROM users WHERE id = ?', [(int) $admin['id']], '');
    if (!password_verify((string) ($_POST['password'] ?? ''), $hash)) {
        $impostazioni($admin, $gruppo, ['password' => 'La password non è giusta: le impostazioni non sono state salvate.'], $valori, 422);
        return;
    }
    try {
        $errori = Impostazioni::salva($gruppo, $_POST);
    } catch (\RuntimeException $e) {
        Log::exception($e, 'impostazioni');
        $impostazioni($admin, $gruppo, ['_' => $e->getMessage()], $valori, 500);
        return;
    }
    if ($errori) { $impostazioni($admin, $gruppo, $errori, $valori, 422); return; }
    // Nel registro si scrive cosa è cambiato, mai i valori.
    Auth::audit('impostazioni.' . $gruppo, null, ['campi' => array_keys(Impostazioni::GRUPPI[$gruppo])]);
    Support::flash(Impostazioni::TITOLI[$gruppo] . ': impostazioni salvate. Ora prova la connessione.');
    Support::redirect('/admin/impostazioni#' . $gruppo);
});

$r->post('/admin/impostazioni/{gruppo}/prova', function (array $a) use ($impostazioni) {
    $admin = Auth::requireAdmin();
    $gruppo = (string) $a['gruppo'];
    if (!isset(Impostazioni::GRUPPI[$gruppo])) { http_response_code(404); exit('Non trovato.'); }
    if (!RateLimit::hit('impostazioni-prova:' . (int) $admin['id'], 10, 900)) {
        Support::flash('Troppe prove di fila: riprova tra un quarto d\'ora.', 'err');
        Support::redirect('/admin/impostazioni#' . $gruppo);
    }
    if (!empty($_POST['con_dati'])) {
        // «Prova con questi dati»: i valori del modulo, senza salvarli. Serve la password dell'amministratore,
        // come per salvare: altrimenti un segreto salvato si potrebbe mandare a un server scelto da altri.
        $valori = [];
        foreach (Impostazioni::GRUPPI[$gruppo] as $percorso => $c) {
            if ($c[2] !== 'secret') $valori[Impostazioni::nome($percorso)] = (string) ($_POST[Impostazioni::nome($percorso)] ?? '');
        }
        $hash = (string) Db::val('SELECT password_hash FROM users WHERE id = ?', [(int) $admin['id']], '');
        if (!password_verify((string) ($_POST['password'] ?? ''), $hash)) {
            $impostazioni($admin, $gruppo, ['password' => 'La password non è giusta: la prova non è partita.'], $valori, 422);
            return;
        }
        [$ok, $msg, $errori] = Impostazioni::provaCon($gruppo, $_POST, (string) $admin['email']);
        if ($errori) { $impostazioni($admin, $gruppo, $errori, $valori, 422); return; }
        Support::flash(Impostazioni::TITOLI[$gruppo] . ': ' . $msg . ($ok ? ' Questi dati non sono ancora salvati: premi «Salva».' : ''), $ok ? 'ok' : 'err');
        Support::redirect('/admin/impostazioni#' . $gruppo);
    }
    [$ok, $msg] = Impostazioni::prova($gruppo, (string) $admin['email']);
    Support::flash(Impostazioni::TITOLI[$gruppo] . ': ' . $msg, $ok ? 'ok' : 'err');
    Support::redirect('/admin/impostazioni#' . $gruppo);
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
        Support::flash('Testimonianza salvata.' . (empty($_POST['visible']) ? ' Non è visibile: spunta «Visibile sulla landing» quando vuoi mostrarla.' : ''));
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


// ---------------------------------------------------------- codici sconto (6E)
/*
 * I codici del primo anno: si creano qui e diventano coupon Stripe (duration=once).
 * Dopo la creazione si cambiano solo nota, piani e stato; per valore o date si
 * disattiva e se ne crea un altro. Creazioni e disattivazioni vanno nel registro.
 */
$r->get('/admin/sconti', function () {
    Auth::requireAdmin();
    $righe = MHW\Sconti::disponibili() ? Db::all('SELECT * FROM discount_codes ORDER BY id DESC') : [];
    // La riga di sistema di Invita un amico non è un codice da gestire a mano: sta in Amministrazione → Inviti.
    $righe = array_values(array_filter($righe, fn($c) => empty($c['sistema'])));
    foreach ($righe as &$c) { $c['stato'] = MHW\Sconti::stato($c); $c['usi'] = MHW\Sconti::utilizzi((int) $c['id']); }
    unset($c);
    View::out('admin/sconti', [
        'righe' => $righe, 'piani' => Plans::public(), 'proposto' => MHW\Sconti::disponibili() ? MHW\Sconti::genera() : '',
        'cifre' => ['attivi' => count(array_filter($righe, fn($c) => $c['stato'] === 'attivo')),
                    'usi' => MHW\Sconti::disponibili() ? (int) Db::val('SELECT COUNT(*) FROM discount_redemptions', [], 0) : 0,
                    'euro' => MHW\Sconti::disponibili() ? (int) Db::val('SELECT COALESCE(SUM(discount_cents), 0) FROM discount_redemptions', [], 0) : 0],
        'stripe' => Stripe::enabled(), 'vecchi' => $_SESSION['sconto_modulo'] ?? [], 'nav' => 'sconti',
    ], 'layout/cms');
    unset($_SESSION['sconto_modulo']);
});

$r->post('/admin/sconti', function () {
    Auth::requireAdmin();
    try {
        [$id, $sync] = MHW\Sconti::crea($_POST);
        Auth::audit('discount.create', null, ['id' => $id, 'code' => MHW\Sconti::normalizza((string) $_POST['code'])]);
        Support::flash($sync ? 'Codice creato e attivo anche su Stripe.' : 'Codice salvato, ma non ancora su Stripe: non si può usare finché non lo sincronizzi.', $sync ? 'ok' : 'err');
    } catch (\RuntimeException $e) {
        $_SESSION['sconto_modulo'] = array_intersect_key($_POST, array_flip(['code', 'kind', 'value', 'valid_from', 'valid_until', 'max_uses', 'piani', 'packages', 'note']));
        Support::flash($e->getMessage(), 'err');
    }
    Support::redirect('/admin/sconti');
});

$r->get('/admin/sconti/{id}', function (array $a) {
    Auth::requireAdmin();
    $c = MHW\Sconti::riga((int) $a['id']);
    if (!$c) { http_response_code(404); View::out('pub/404', []); }
    $c['stato'] = MHW\Sconti::stato($c);
    View::out('admin/sconto', [
        'c' => $c, 'piani' => Plans::public(),
        'usi' => Db::all('SELECT r.*, u.email, pk.name AS piano FROM discount_redemptions r JOIN accounts a ON a.id = r.account_id JOIN users u ON u.id = a.user_id
                          JOIN orders o ON o.id = r.order_id JOIN package_versions pv ON pv.id = o.package_version_id JOIN packages pk ON pk.id = pv.package_id
                          WHERE r.discount_code_id = ? ORDER BY r.id DESC', [$c['id']]),
        'nav' => 'sconti',
    ], 'layout/cms');
});

$r->post('/admin/sconti/{id}/{fai}', function (array $a) {
    Auth::requireAdmin();
    $c = MHW\Sconti::riga((int) $a['id']);
    if (!$c) { http_response_code(404); View::out('pub/404', []); }
    $torna = ($_POST['torna'] ?? '') === 'dettaglio' ? '/admin/sconti/' . $c['id'] : '/admin/sconti';
    if (!empty($c['sistema'])) { Support::flash('Questo codice è gestito da Invita un amico: non si modifica da qui.', 'err'); Support::redirect('/admin/sconti'); }
    switch ($a['fai']) {
        case 'disattiva':
            MHW\Sconti::disattiva((int) $c['id']);
            Auth::audit('discount.disable', null, ['id' => (int) $c['id'], 'code' => $c['code']]);
            Support::flash('Codice ' . $c['code'] . ' disattivato. Chi l\'ha già usato conserva il suo sconto.');
            break;
        case 'sincronizza':
            $ok = MHW\Sconti::sincronizza($c);
            Support::flash($ok ? 'Codice attivo su Stripe.' : 'Stripe non ha risposto, o non è configurato: riprova più tardi.', $ok ? 'ok' : 'err');
            break;
        case 'modifica':
            MHW\Sconti::aggiorna((int) $c['id'], $_POST);
            Support::flash('Nota e piani aggiornati.');
            break;
        default: http_response_code(404); View::out('pub/404', []);
    }
    Support::redirect($torna);
});

// ------------------------------------------------------------ Invita un amico
/* Chi ha invitato chi e in che stato è ogni invito. «Annulla» toglie un invito dal
   conto (un rimborso, un abuso) e riallinea lo sconto di chi invita su Stripe. */
$r->get('/admin/inviti', function () {
    Auth::requireAdmin();
    $righe = MHW\Migrator::tableExists('referrals') ? Db::all(
        'SELECT r.*, ur.name AS chi, ur.email AS chi_email, ar.id AS chi_account, uf.name AS amico, uf.email AS amico_email, af.id AS amico_account
         FROM referrals r JOIN accounts ar ON ar.id = r.referrer_account_id JOIN users ur ON ur.id = ar.user_id
         JOIN accounts af ON af.id = r.friend_account_id JOIN users uf ON uf.id = af.user_id ORDER BY r.id DESC LIMIT 300') : [];
    View::out('admin/inviti', ['righe' => $righe, 'attivi' => MHW\Inviti::disponibili(), 'nav' => 'inviti'], 'layout/cms');
});

$r->post('/admin/inviti/{id}/annulla', function (array $a) {
    Auth::requireAdmin();
    MHW\Inviti::annulla((int) $a['id']);
    Auth::audit('referral.cancel', null, ['id' => (int) $a['id']]);
    Support::flash('Invito annullato: non conta più per lo sconto.');
    Support::redirect('/admin/inviti');
});
