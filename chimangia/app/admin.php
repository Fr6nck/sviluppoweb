<?php
/**
 * Avvio e impaginazione del pannello di amministrazione.
 */
declare(strict_types=1);

define('CM_ROOT', dirname(__DIR__));
require_once __DIR__ . '/bootstrap.php';
require_once CM_APP . '/auth.php';
require_once CM_APP . '/csrf.php';
require_once CM_APP . '/content.php';
require_once CM_APP . '/mail.php';
require_once CM_APP . '/schema.php';
foreach (['sanitize', 'icons', 'media', 'render', 'leads', 'forms', 'editor'] as $cmLib) {
    if (is_file(CM_APP . '/' . $cmLib . '.php')) {
        require_once CM_APP . '/' . $cmLib . '.php';
    }
}

require_installed('../');

if ((string) setting('db_version', '') !== CM_VERSION) {
    cm_migrate();
}

security_headers(admin_csp());
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, private');
session_start_secure();

/* ---------- Messaggi flash ---------- */

function flash(string $type, string $text): void
{
    $_SESSION['flash'][] = ['type' => $type, 'text' => $text];
}

function flash_html(): string
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    $out = '';
    foreach ($items as $f) {
        $type = in_array($f['type'] ?? '', ['ok', 'error', 'info', 'warn'], true) ? $f['type'] : 'info';
        $role = $type === 'error' ? 'alert' : 'status';
        $out .= '<div class="notice notice-' . $type . '" role="' . $role . '">' . e($f['text'] ?? '') . '</div>';
    }
    return $out;
}

/* ---------- Menu ---------- */

/** Voci del menu laterale: [file, etichetta, ruolo minimo, gruppo]. */
function admin_menu(): array
{
    return [
        ['index.php', 'Cruscotto', 'editor', ''],
        ['sezione.php?s=header', 'Intestazione', 'editor', 'Contenuti'],
        ['sezione.php?s=hero', 'Apertura', 'editor', 'Contenuti'],
        ['sezione.php?s=problema', 'Il problema', 'editor', 'Contenuti'],
        ['sezione.php?s=come_funziona', 'Come funziona', 'editor', 'Contenuti'],
        ['sezione.php?s=giornata', 'Una giornata', 'editor', 'Contenuti'],
        ['sezione.php?s=funzioni', 'Funzioni', 'editor', 'Contenuti'],
        ['sezione.php?s=per_chi', 'Per chi è', 'editor', 'Contenuti'],
        ['prezzi.php', 'Prezzi', 'editor', 'Contenuti'],
        ['faq.php', 'Domande frequenti', 'editor', 'Contenuti'],
        ['sezione.php?s=contatti', 'Sezione contatti', 'editor', 'Contenuti'],
        ['azienda.php', 'Dati aziendali', 'editor', 'Contenuti'],
        ['seo.php', 'SEO', 'editor', 'Contenuti'],
        ['legali.php?p=privacy', 'Privacy', 'editor', 'Contenuti'],
        ['legali.php?p=cookie', 'Cookie', 'editor', 'Contenuti'],
        ['richieste.php', 'Richieste', 'editor', 'Gestione'],
        ['media.php', 'Media', 'editor', 'Gestione'],
        ['storico.php', 'Storico e ripristino', 'editor', 'Gestione'],
        ['registro.php', 'Registro azioni', 'admin', 'Gestione'],
        ['utenti.php', 'Utenti', 'admin', 'Impostazioni'],
        ['impostazioni.php', 'Email e generali', 'admin', 'Impostazioni'],
        ['backup.php', 'Backup', 'admin', 'Impostazioni'],
        ['profilo.php', 'Il mio profilo', 'editor', 'Account'],
    ];
}

function admin_menu_html(string $active, array $user): string
{
    $html = '';
    $group = null;
    $newLeads = function_exists('leads_count_new') ? leads_count_new() : 0;
    foreach (admin_menu() as [$href, $label, $role, $g]) {
        $file = strtok($href, '?');
        if (!is_file(CM_ROOT . '/admin/' . $file)) {
            continue; // pagina non ancora disponibile
        }
        if ($role === 'admin' && $user['role'] !== 'admin') {
            continue;
        }
        if ($g !== $group) {
            if ($group !== null) {
                $html .= '</ul>';
            }
            $html .= ($g !== '' ? '<p class="nav-group">' . e($g) . '</p>' : '') . '<ul>';
            $group = $g;
        }
        $isActive = $href === $active;
        $badge = ($href === 'richieste.php' && $newLeads > 0)
            ? ' <span class="badge" aria-label="' . $newLeads . ' nuove">' . $newLeads . '</span>' : '';
        $html .= '<li><a href="' . e($href) . '"' . ($isActive ? ' aria-current="page"' : '') . '>' . e($label) . $badge . '</a></li>';
    }
    return $html . '</ul>';
}

/* ---------- Impaginazione ---------- */

function admin_head(string $title): void
{
    echo '<!doctype html><html lang="it"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex, nofollow">'
        . '<title>' . e($title) . ' · Pannello ChiMangia</title>'
        . '<link rel="icon" href="../assets/img/favicon.svg" type="image/svg+xml">'
        . '<link rel="stylesheet" href="../assets/css/admin.css?v=' . CM_VERSION . '">'
        . '<script src="../assets/js/admin.js?v=' . CM_VERSION . '" defer></script>'
        . '</head>';
}

function admin_header(string $title, string $active = ''): void
{
    $u = current_user();
    admin_head($title);
    echo '<body class="admin">';
    echo '<a class="skip" href="#contenuto">Vai al contenuto</a>';
    echo '<header class="topbar">'
        . '<button type="button" class="menu-toggle" aria-expanded="false" aria-controls="sidebar"><span aria-hidden="true">☰</span> Menu</button>'
        . '<a class="brand" href="index.php">' . logo_svg(30) . '<span>ChiMangia</span><small>Pannello</small></a>'
        . '<a class="topbar-site" href="../" target="_blank" rel="noopener">Vedi il sito<span class="sr-only"> (si apre in una nuova scheda)</span></a>'
        . '</header>';
    echo '<div class="layout">';
    echo '<nav id="sidebar" class="sidebar" aria-label="Menu del pannello">' . admin_menu_html($active, $u ?? ['role' => 'editor'])
        . '<form method="post" action="logout.php" class="logout">' . csrf_field()
        . '<p class="who">' . e($u['name'] ?? '') . '<br><small>' . e(CM_ROLES[$u['role'] ?? 'editor'] ?? '') . '</small></p>'
        . '<button type="submit" class="btn btn-line btn-small">Esci</button></form>'
        . '</nav>';
    echo '<main id="contenuto" class="main" tabindex="-1">';
    echo '<h1>' . e($title) . '</h1>';
    echo flash_html();
}

function admin_footer(): void
{
    echo '</main></div></body></html>';
}

/** Pagina semplice per accesso, recupero password ed errori. */
function admin_auth_page_start(string $title): void
{
    admin_head($title);
    echo '<body class="admin auth"><main class="auth-box" id="contenuto">'
        . '<a class="brand brand-auth" href="../">' . logo_svg(40) . '<span>ChiMangia</span></a>'
        . '<h1>' . e($title) . '</h1>' . flash_html();
}

function admin_auth_page_end(): void
{
    echo '</main></body></html>';
}

function admin_message_page(string $title, string $text): never
{
    if (current_user()) {
        admin_header($title);
        echo '<p>' . e($text) . '</p><p><a class="btn btn-line" href="index.php">Torna al cruscotto</a></p>';
        admin_footer();
    } else {
        admin_auth_page_start($title);
        echo '<p>' . e($text) . '</p><p><a href="login.php">Vai all\'accesso</a></p>';
        admin_auth_page_end();
    }
    exit;
}

function admin_forbidden(): never
{
    admin_message_page('Accesso non consentito', 'Questa pagina è riservata agli amministratori.');
}
