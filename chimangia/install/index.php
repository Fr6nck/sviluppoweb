<?php
/**
 * Procedura guidata d'installazione di ChiMangia.
 * Crea le tabelle, carica i contenuti iniziali, crea il primo amministratore,
 * scrive config.php e poi si blocca con un file di lock.
 */
declare(strict_types=1);

define('CM_ROOT', dirname(__DIR__));
require CM_ROOT . '/app/bootstrap.php';
require CM_APP . '/schema.php';
require CM_APP . '/content.php';
require CM_APP . '/auth.php';
foreach (['sanitize', 'icons', 'render'] as $cmLib) {
    if (is_file(CM_APP . '/' . $cmLib . '.php')) {
        require_once CM_APP . '/' . $cmLib . '.php';
    }
}

const INSTALL_LOCK = __DIR__ . '/install.lock';

security_headers("default-src 'self'; style-src 'self'; img-src 'self' data:; script-src 'none'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

function install_page(string $title, string $body): never
{
    echo '<!doctype html><html lang="it"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">'
        . '<title>' . e($title) . ' · Installazione ChiMangia</title>'
        . '<link rel="icon" href="../assets/img/favicon.svg" type="image/svg+xml">'
        . '<link rel="stylesheet" href="../assets/css/admin.css?v=' . CM_VERSION . '"></head>'
        . '<body class="admin auth"><main class="auth-box auth-wide" id="contenuto">'
        . '<p class="brand brand-auth">' . logo_svg(40) . '<span>ChiMangia</span></p>'
        . '<h1>' . e($title) . '</h1>' . $body . '</main></body></html>';
    exit;
}

/* ---------- Già installato: la procedura si blocca ---------- */

if (is_installed() || is_file(INSTALL_LOCK)) {
    http_response_code(403);
    install_page('Installazione già eseguita', '<div class="notice notice-warn" role="status">'
        . 'ChiMangia è già installato e questa procedura è bloccata. Per sicurezza <strong>cancella la cartella <code>install/</code></strong> dal server.</div>'
        . '<p><a class="btn btn-dark" href="../admin/">Vai al pannello</a></p>');
}

/* ---------- Requisiti ---------- */

function install_requirements(): array
{
    $checks = [
        ['PHP 8.1 o successivo (attuale ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.1.0', '>='), true],
        ['Estensione PDO MySQL', extension_loaded('pdo_mysql'), true],
        ['Estensione mbstring', extension_loaded('mbstring'), true],
        ['Estensione OpenSSL', extension_loaded('openssl'), true],
        ['Estensione fileinfo (controllo delle immagini caricate)', extension_loaded('fileinfo'), true],
        ['Estensione GD con WebP (ridimensionamento immagini)', extension_loaded('gd') && function_exists('imagewebp'), false],
        ['Cartella principale scrivibile (per creare config.php)', is_writable(CM_ROOT), true],
        ['Cartella uploads/ scrivibile', is_writable(CM_ROOT . '/uploads'), true],
        ['Cartella install/ scrivibile (file di blocco)', is_writable(__DIR__), true],
    ];
    return $checks;
}

function suggested_storage(): string
{
    $docroot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: '';
    if ($docroot !== '') {
        $outside = dirname($docroot);
        if ($outside !== $docroot && is_dir($outside) && is_writable($outside) && !str_starts_with(CM_ROOT, $outside . '/chimangia-dati')) {
            return $outside . '/chimangia-dati';
        }
    }
    return CM_ROOT . '/storage';
}

function detected_site_url(): string
{
    $scheme = is_https() ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $host = preg_replace('/[^a-z0-9.:\-\[\]]/i', '', $host);
    $path = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/install/index.php'), 2)), '/');
    return $scheme . '://' . $host . $path;
}

/* ---------- Token anti-CSRF dell'installazione (firmato, senza sessione) ---------- */

$installKey = hash('sha256', __FILE__ . filemtime(__FILE__) . php_uname());
function install_token(string $key): string
{
    $ts = (string) time();
    return $ts . '.' . hash_hmac('sha256', $ts, $key);
}
function install_token_ok(string $token, string $key): bool
{
    $p = explode('.', $token);
    return count($p) === 2 && ctype_digit($p[0]) && time() - (int) $p[0] < 7200
        && hash_equals(hash_hmac('sha256', $p[0], $key), $p[1]);
}

$v = [
    'db_host' => 'localhost', 'db_port' => '3306', 'db_name' => '', 'db_user' => '', 'db_prefix' => 'cm_',
    'site_url' => detected_site_url(), 'storage_path' => suggested_storage(),
    'admin_name' => '', 'admin_email' => '',
];
$errors = [];
$general = '';

if (is_post()) {
    foreach (array_keys($v) as $k) {
        $v[$k] = post($k);
    }
    $dbPass = (string) ($_POST['db_pass'] ?? '');
    $pass = (string) ($_POST['admin_password'] ?? '');
    $pass2 = (string) ($_POST['admin_password2'] ?? '');

    if (!install_token_ok(post('_token'), $installKey)) {
        $general = 'Il modulo è scaduto. Ricarica la pagina e riprova.';
    }
    foreach (install_requirements() as [$label, $ok, $required]) {
        if ($required && !$ok) {
            $general = 'Alcuni requisiti obbligatori non sono soddisfatti (vedi l\'elenco qui sotto).';
        }
    }
    if ($v['db_host'] === '') { $errors['db_host'] = 'Indica il server del database.'; }
    if (!ctype_digit($v['db_port'])) { $errors['db_port'] = 'La porta deve essere un numero.'; }
    if ($v['db_name'] === '') { $errors['db_name'] = 'Indica il nome del database.'; }
    if ($v['db_user'] === '') { $errors['db_user'] = 'Indica l\'utente del database.'; }
    if (!preg_match('/^[a-z][a-z0-9_]{0,15}$/', $v['db_prefix'])) {
        $errors['db_prefix'] = 'Solo lettere minuscole, numeri e trattino basso; deve iniziare con una lettera.';
    }
    $v['site_url'] = rtrim($v['site_url'], '/');
    if (!filter_var($v['site_url'], FILTER_VALIDATE_URL) || !preg_match('#^https?://#', $v['site_url'])) {
        $errors['site_url'] = 'Indica l\'indirizzo completo, ad esempio https://www.chimangia.it';
    }
    if ($v['storage_path'] === '') { $errors['storage_path'] = 'Indica la cartella dati.'; }
    if ($v['admin_name'] === '') { $errors['admin_name'] = 'Scrivi il nome dell\'amministratore.'; }
    $v['admin_email'] = mb_strtolower($v['admin_email']);
    if (!filter_var($v['admin_email'], FILTER_VALIDATE_EMAIL)) { $errors['admin_email'] = 'Scrivi un indirizzo email valido.'; }
    if ($p = password_problem($pass)) {
        $errors['admin_password'] = $p;
    } elseif ($pass !== $pass2) {
        $errors['admin_password2'] = 'Le due password non coincidono.';
    }

    // Cartella dati
    if (!isset($errors['storage_path'])) {
        $sp = rtrim($v['storage_path'], '/\\');
        foreach (['', '/cache', '/logs'] as $sub) {
            if (!is_dir($sp . $sub)) {
                @mkdir($sp . $sub, 0755, true);
            }
        }
        if (!is_dir($sp . '/cache') || !is_writable($sp . '/cache') || !is_writable($sp . '/logs')) {
            $errors['storage_path'] = 'La cartella non esiste o non è scrivibile.';
        } else {
            $v['storage_path'] = realpath($sp) ?: $sp;
            if (str_starts_with($v['storage_path'], CM_ROOT) && !is_file($v['storage_path'] . '/.htaccess')) {
                @file_put_contents($v['storage_path'] . '/.htaccess', "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
            }
        }
    }

    // Connessione
    $pdo = null;
    if (!$errors && $general === '') {
        try {
            $pdo = db_connect($v['db_host'], (int) $v['db_port'], $v['db_name'], $v['db_user'], $dbPass);
        } catch (Throwable $e) {
            $general = 'Connessione al database non riuscita. Controlla server, nome, utente e password. (Dettaglio: '
                . preg_replace('/\s+/', ' ', $e->getMessage()) . ')';
        }
    }

    if ($pdo && !$errors && $general === '') {
        try {
            $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($v['db_prefix'] . 'users'))->fetchColumn();
            if ($exists && (int) $pdo->query('SELECT COUNT(*) FROM `' . $v['db_prefix'] . 'users`')->fetchColumn() > 0) {
                $general = 'In questo database esiste già un\'installazione con il prefisso "' . $v['db_prefix']
                    . '". Usa un prefisso diverso oppure un database vuoto: i dati esistenti non vengono toccati.';
            }
        } catch (Throwable $e) {
            $general = 'Impossibile leggere il database: ' . $e->getMessage();
        }
    }

    if ($pdo && !$errors && $general === '') {
        // Configurazione temporanea per le funzioni condivise.
        $secret = bin2hex(random_bytes(32));
        $GLOBALS['cm_config'] = [
            'db' => ['host' => $v['db_host'], 'port' => (int) $v['db_port'], 'name' => $v['db_name'], 'user' => $v['db_user'], 'pass' => $dbPass, 'prefix' => $v['db_prefix']],
            'site_url' => $v['site_url'], 'app_secret' => $secret, 'storage_path' => $v['storage_path'],
            'timezone' => 'Europe/Rome', 'debug' => false,
        ];
        try {
            foreach (schema_statements($v['db_prefix']) as $sql) {
                db()->exec($sql);
            }
            $now = now();
            q(
                'INSERT INTO ' . t('users') . ' (name, email, password_hash, role, active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, ?, ?)',
                [$v['admin_name'], $v['admin_email'], password_hash($pass, PASSWORD_DEFAULT), 'admin', $now, $now]
            );
            $adminId = (int) db()->lastInsertId();
            content_seed($adminId);
            $defaults = default_settings();
            $defaults['notify_recipients'] = $v['admin_email'];
            $defaults['db_version'] = CM_VERSION;
            foreach ($defaults as $name => $value) {
                q('INSERT IGNORE INTO ' . t('settings') . ' (name, value) VALUES (?, ?)', [$name, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            }
            q('INSERT INTO ' . t('versions') . ' (user_id, note, data, created_at) VALUES (?, ?, ?, ?)', [$adminId, 'Contenuti iniziali', content_encode(content_all('published')), $now]);
            setting_set('last_publish_at', $now);
            audit('installazione', 'Versione ' . CM_VERSION, $adminId);
        } catch (Throwable $e) {
            log_message('Installazione: ' . $e->getMessage());
            install_page('Installazione non riuscita', '<div class="notice notice-error" role="alert">Errore durante la creazione delle tabelle: '
                . e($e->getMessage()) . '</div><p><a href="./">Riprova</a></p>');
        }

        // config.php dal modello
        $tpl = (string) file_get_contents(CM_ROOT . '/config.example.php');
        $repl = [
            "'__DB_HOST__'" => var_export($v['db_host'], true),
            "'__DB_PORT__'" => (string) (int) $v['db_port'],
            "'__DB_NAME__'" => var_export($v['db_name'], true),
            "'__DB_USER__'" => var_export($v['db_user'], true),
            "'__DB_PASS__'" => var_export($dbPass, true),
            "'__DB_PREFIX__'" => var_export($v['db_prefix'], true),
            "'__SITE_URL__'" => var_export($v['site_url'], true),
            "'__APP_SECRET__'" => var_export($secret, true),
            "'__STORAGE_PATH__'" => var_export($v['storage_path'], true),
        ];
        $config = strtr($tpl, $repl);
        $config = preg_replace('#/\*\*.*?\*/#s', "/** Configurazione di ChiMangia, creata dall'installazione il " . date('d/m/Y H:i') . ". Non condividere questo file. */", $config, 1);
        if (!write_file_atomic(CM_ROOT . '/config.php', $config)) {
            install_page('Installazione quasi completata', '<div class="notice notice-error" role="alert">Le tabelle sono state create, ma non è stato possibile scrivere <code>config.php</code>. '
                . 'Crea il file a mano con questo contenuto, poi cancella la cartella <code>install/</code>.</div><pre class="code">' . e($config) . '</pre>');
        }
        @chmod(CM_ROOT . '/config.php', 0640);

        // Blocco della procedura
        @file_put_contents(INSTALL_LOCK, 'Installato il ' . date('c') . "\n");
        @file_put_contents(__DIR__ . '/.htaccess', "# Installazione completata: accesso bloccato.\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");

        // Prima generazione della cache e dei file SEO
        if (function_exists('site_rebuild')) {
            try {
                site_rebuild();
            } catch (Throwable $e) {
                log_message('Prima generazione della cache: ' . $e->getMessage());
            }
        }

        install_page('Installazione completata', '<div class="notice notice-ok" role="status">ChiMangia è installato. Le tabelle e i contenuti iniziali sono pronti e l\'amministratore è stato creato.</div>'
            . '<div class="notice notice-warn" role="status"><strong>Ultimo passo:</strong> cancella la cartella <code>install/</code> dal server (via FTP o dal File Manager di cPanel). '
            . 'La procedura è già bloccata, ma è meglio non lasciarla online.</div>'
            . '<p>Prima di pubblicare il sito controlla in <em>Dati aziendali</em> l\'email (ora è un segnaposto) e completa le pagine <em>Privacy</em> e <em>Cookie</em>.</p>'
            . '<p><a class="btn btn-dark" href="../admin/login.php">Accedi al pannello<span class="arr" aria-hidden="true">→</span></a></p>');
    }
}

/* ---------- Modulo ---------- */

$req = '';
foreach (install_requirements() as [$label, $ok, $required]) {
    $req .= '<li class="' . ($ok ? 'ok' : ($required ? 'ko' : 'warn')) . '">' . ($ok ? '✓ ' : ($required ? '✗ ' : '! ')) . e($label)
        . (!$ok && !$required ? ' <small>(facoltativa: senza, le immagini vengono salvate senza ridimensionamento)</small>' : '') . '</li>';
}

function install_field(string $name, string $label, string $value, array $errors, string $type = 'text', string $hint = '', string $attrs = ''): string
{
    $id = 'i-' . $name;
    $err = $errors[$name] ?? '';
    $desc = trim(($hint ? $id . '-hint ' : '') . ($err ? $id . '-err' : ''));
    return '<div class="field"><label for="' . $id . '">' . e($label) . '</label>'
        . '<input type="' . $type . '" id="' . $id . '" name="' . $name . '" value="' . ($type === 'password' ? '' : e($value)) . '"'
        . ($desc ? ' aria-describedby="' . $desc . '"' : '') . ($err ? ' aria-invalid="true"' : '') . ' ' . $attrs . '>'
        . ($hint ? '<p class="hint" id="' . $id . '-hint">' . e($hint) . '</p>' : '')
        . ($err ? '<p class="field-error" id="' . $id . '-err">' . e($err) . '</p>' : '') . '</div>';
}

$body = '';
if ($general !== '') {
    $body .= '<div class="notice notice-error" role="alert">' . e($general) . '</div>';
} elseif ($errors) {
    $body .= '<div class="notice notice-error" role="alert">Controlla i campi evidenziati.</div>';
}
$body .= '<p>Prima di cominciare crea da cPanel un database MySQL e un utente con tutti i privilegi su quel database (vedi il README).</p>'
    . '<section class="panel"><h2>Requisiti del server</h2><ul class="checks">' . $req . '</ul></section>'
    . '<form method="post" action="./" class="form" novalidate>'
    . '<input type="hidden" name="_token" value="' . e(install_token($installKey)) . '">'
    . '<fieldset><legend>Database</legend>'
    . install_field('db_host', 'Server', $v['db_host'], $errors, 'text', 'Su cPanel di solito è "localhost".', 'required')
    . install_field('db_port', 'Porta', $v['db_port'], $errors, 'text', '', 'inputmode="numeric" required')
    . install_field('db_name', 'Nome del database', $v['db_name'], $errors, 'text', 'Su cPanel ha la forma utente_nomedb.', 'required')
    . install_field('db_user', 'Utente del database', $v['db_user'], $errors, 'text', '', 'required autocomplete="off"')
    . install_field('db_pass', 'Password del database', '', $errors, 'password', '', 'autocomplete="off"')
    . install_field('db_prefix', 'Prefisso delle tabelle', $v['db_prefix'], $errors, 'text', 'Lascia "cm_" se non hai esigenze particolari.', 'required')
    . '</fieldset>'
    . '<fieldset><legend>Sito</legend>'
    . install_field('site_url', 'Indirizzo del sito', $v['site_url'], $errors, 'url', 'Indirizzo completo senza barra finale. Funziona anche in una sottocartella.', 'required')
    . install_field('storage_path', 'Cartella dati privata', $v['storage_path'], $errors, 'text', 'Qui vanno cache e log. Se possibile, fuori dalla cartella pubblica (public_html).', 'required')
    . '</fieldset>'
    . '<fieldset><legend>Primo amministratore</legend>'
    . install_field('admin_name', 'Nome', $v['admin_name'], $errors, 'text', '', 'required autocomplete="name"')
    . install_field('admin_email', 'Email', $v['admin_email'], $errors, 'email', 'Serve per accedere e per recuperare la password.', 'required autocomplete="email"')
    . install_field('admin_password', 'Password', '', $errors, 'password', 'Almeno 10 caratteri, con lettere e numeri.', 'required autocomplete="new-password"')
    . install_field('admin_password2', 'Ripeti la password', '', $errors, 'password', '', 'required autocomplete="new-password"')
    . '</fieldset>'
    . '<button type="submit" class="btn btn-dark btn-block">Installa ChiMangia<span class="arr" aria-hidden="true">→</span></button>'
    . '</form>';

install_page('Installazione', $body);
