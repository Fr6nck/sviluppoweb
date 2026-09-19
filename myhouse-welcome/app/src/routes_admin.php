<?php
/** Rotte di amministrazione. $r è il Router creato in public/index.php. */

use MHW\{Auth, Db, Entitlements, Support, View};

$r->get('/admin', function () {
    Auth::requireAdmin();
    $rows = Db::all(
        'SELECT u.id AS user_id, u.email, u.name, u.created_at, a.id AS account_id,
                (SELECT COUNT(*) FROM properties p WHERE p.account_id = a.id) AS properties
         FROM users u JOIN accounts a ON a.user_id = u.id
         WHERE u.role = ? ORDER BY u.id DESC', ['host']);
    foreach ($rows as &$row) {
        $sub = Db::one(
            'SELECT pk.name, pv.version, s.status FROM subscriptions s
             JOIN package_versions pv ON pv.id = s.package_version_id
             JOIN packages pk ON pk.id = pv.package_id
             WHERE s.account_id = ? AND s.status = ? ORDER BY s.id DESC', [$row['account_id'], 'active']);
        $row['plan'] = $sub['name'] ?? '—';
        $row['plan_version'] = $sub['version'] ?? null;
    }
    unset($row);
    View::out('admin/customers', ['rows' => $rows]);
});

$r->post('/admin/entra/{uid}', function (array $a) {
    Auth::requireAdmin();
    Auth::impersonate((int) $a['uid']);
    Support::flash('State usando l\'account di un cliente. La sua password non è mai stata mostrata.');
    Support::redirect('/pannello');
});

$r->post('/admin/esci-da-cliente', function () {
    Auth::stopImpersonating();
    Support::redirect('/admin');
});

$r->get('/admin/pacchetti', function () {
    Auth::requireAdmin();
    $packages = Db::all('SELECT * FROM packages ORDER BY sort');
    foreach ($packages as &$p) {
        $p['versions'] = Db::all('SELECT * FROM package_versions WHERE package_id = ? ORDER BY version DESC', [$p['id']]);
        foreach ($p['versions'] as &$v) {
            $v['features'] = Db::all(
                'SELECT f.code, f.label, f.kind, pf.value FROM package_features pf
                 JOIN features f ON f.id = pf.feature_id WHERE pf.package_version_id = ? ORDER BY f.id', [$v['id']]);
        }
        unset($v);
    }
    unset($p);
    View::out('admin/packages', ['packages' => $packages, 'features' => Db::all('SELECT * FROM features ORDER BY id')]);
});

/**
 * Modificare un pacchetto non tocca chi l'ha già comprato: crea una versione
 * nuova e lascia intatta quella vecchia, a cui restano agganciati gli abbonamenti.
 */
$r->post('/admin/pacchetti/{pid}/nuova-versione', function (array $a) {
    Auth::requireAdmin();
    $pkg = Db::one('SELECT * FROM packages WHERE id = ?', [(int) $a['pid']]);
    if (!$pkg) { http_response_code(404); exit('Pacchetto inesistente.'); }

    Db::tx(function () use ($pkg) {
        $next = (int) Db::val('SELECT COALESCE(MAX(version),0)+1 FROM package_versions WHERE package_id = ?', [$pkg['id']], 1);
        Db::run('UPDATE package_versions SET is_current = 0 WHERE package_id = ?', [$pkg['id']]);
        $vid = Db::insert('package_versions', [
            'package_id' => $pkg['id'], 'version' => $next,
            'price_cents' => (int) round(((float) str_replace(',', '.', (string) $_POST['prezzo'])) * 100),
            'currency' => 'EUR', 'interval_unit' => 'year', 'is_current' => 1,
            'sold_count' => 0, 'created_at' => Support::now(),
        ]);
        foreach (Db::all('SELECT * FROM features ORDER BY id') as $f) {
            $val = (string) ($_POST['f'][$f['code']] ?? '0');
            Db::insert('package_features', ['package_version_id' => $vid, 'feature_id' => $f['id'], 'value' => $val]);
        }
        Db::update('packages', [
            'name' => trim((string) $_POST['nome']),
            'tagline' => trim((string) ($_POST['sottotitolo'] ?? '')),
        ], 'id = :pid', ['pid' => $pkg['id']]);
    });

    Support::flash('Creata una versione nuova. Chi era sulla precedente ci resta, con quello che aveva comprato.');
    Support::redirect('/admin/pacchetti');
});

$r->get('/admin/cliente/{aid}', function (array $a) {
    Auth::requireAdmin();
    $acc = Db::one('SELECT a.*, u.email, u.name AS user_name, u.id AS user_id
                    FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [(int) $a['aid']]);
    if (!$acc) { http_response_code(404); exit('Cliente inesistente.'); }
    View::out('admin/customer', [
        'acc' => $acc,
        'props' => Db::all('SELECT * FROM properties WHERE account_id = ?', [$acc['id']]),
        'orders' => Db::all('SELECT o.*, pk.name AS package FROM orders o
                             JOIN package_versions pv ON pv.id = o.package_version_id
                             JOIN packages pk ON pk.id = pv.package_id
                             WHERE o.account_id = ? ORDER BY o.id DESC', [$acc['id']]),
        'ent' => Entitlements::forAccount((int) $acc['id']),
        'audit' => Db::all('SELECT * FROM audit_log WHERE target_user_id = ? ORDER BY id DESC', [$acc['user_id']]),
    ]);
});

$r->post('/admin/cliente/{aid}/override', function (array $a) {
    Auth::requireAdmin();
    $accId = (int) $a['aid'];
    $code = (string) $_POST['feature'];
    $val = trim((string) $_POST['valore']);
    $f = Db::one('SELECT * FROM features WHERE code = ?', [$code]);
    if (!$f) { http_response_code(404); exit('Funzione inesistente.'); }

    if ($val === '') {
        Db::run('DELETE FROM entitlement_overrides WHERE account_id = ? AND feature_id = ?', [$accId, $f['id']]);
        Support::flash('Eccezione rimossa: torna a valere il piano.');
    } else {
        $ex = Db::one('SELECT id FROM entitlement_overrides WHERE account_id = ? AND feature_id = ?', [$accId, $f['id']]);
        if ($ex) Db::update('entitlement_overrides', ['value' => $val, 'note' => (string) ($_POST['nota'] ?? '')], 'id = :oid', ['oid' => $ex['id']]);
        else Db::insert('entitlement_overrides', ['account_id' => $accId, 'feature_id' => $f['id'],
                                                   'value' => $val, 'note' => (string) ($_POST['nota'] ?? '')]);
        Support::flash('Eccezione salvata per questo cliente.');
    }
    Entitlements::forget($accId);
    Support::redirect('/admin/cliente/' . $accId);
});

/**
 * Diagnostica: invece di promettere che la cartella dell'applicazione è chiusa,
 * lo verifica davvero, chiedendo al proprio server i file che non dovrebbero uscire.
 */
$r->get('/admin/diagnostica', function () {
    Auth::requireAdmin();
    $base = Support::baseUrl();
    $checks = [];

    $probe = function (string $url): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6,
                                CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => false]);
        $body = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'bytes' => strlen($body)];
    };

    // La cartella dell'applicazione è sotto la radice pubblica?
    $inWebroot = str_starts_with(realpath(MHW_APP) ?: '', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: "\0");
    $checks[] = ['Applicazione fuori dalla cartella pubblica', !$inWebroot,
        $inWebroot ? "L'applicazione sta dentro la cartella pubblica: la protezione dipende da .htaccess, che non tutti i server leggono."
                   : 'Niente del codice è raggiungibile dal web.'];

    if ($inWebroot) {
        $dbFile = basename(\MHW\Db::sqlitePath(\MHW\Config::get('db')['sqlite_path']));
        $res = $probe($base . '/app/storage/' . $dbFile);
        $exposed = $res['code'] === 200 && $res['bytes'] > 0;
        $checks[] = ['Database non scaricabile dal web', !$exposed,
            $exposed ? 'ATTENZIONE: il database risponde dal web. Spostate la cartella app/ sopra la radice pubblica.'
                     : 'Il server rifiuta la richiesta.'];
        $res = $probe($base . '/app/config.php');
        $checks[] = ['config.php non leggibile', $res['bytes'] === 0,
            $res['bytes'] === 0 ? 'Non esce nulla.' : 'ATTENZIONE: config.php restituisce contenuto.'];
    }

    $up = \MHW\Config::get('uploads_dir');
    $checks[] = ['Cartella dei caricamenti scrivibile', is_dir($up) && is_writable($up),
        is_writable($up) ? $up : 'Date permessi di scrittura a ' . $up];
    $checks[] = ['Estensione GD (immagini e QR)', extension_loaded('gd'), phpversion('gd') ?: 'assente'];
    $checks[] = ['Connessione al database', (bool) \MHW\Db::val('SELECT 1'), \MHW\Config::get('db')['driver']];
    $checks[] = ['Pagamenti Stripe', \MHW\Stripe::enabled(),
        \MHW\Stripe::enabled() ? 'chiavi configurate' : 'non configurato: gli acquisti restano in modalità prova'];
    $checks[] = ['Firma dei webhook', \MHW\Config::get('stripe')['webhook_secret'] !== '',
        \MHW\Config::get('stripe')['webhook_secret'] !== '' ? 'segreto presente' : 'senza segreto ogni webhook viene rifiutato'];
    $checks[] = ['Traduzione automatica', \MHW\Translator::enabled(),
        \MHW\Translator::enabled() ? 'attiva' : 'non configurata: le lingue si compilano a mano'];
    $checks[] = ['HTTPS', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || str_starts_with($base, 'https'),
        'i cookie di sessione diventano "secure" solo su HTTPS'];

    View::out('admin/diagnostics', ['checks' => $checks, 'base' => $base]);
});
