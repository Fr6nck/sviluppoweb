<?php
/**
 * Piattaforma ChiMangia — impaginazione e componenti dell'interfaccia.
 * Stesso linguaggio della home: Instrument Serif per i titoli, Inter per i testi,
 * pannelli arrotondati, blu notte, crema e zafferano.
 */
declare(strict_types=1);

defined('CM_ROOT') || exit;

/* =====================================================================
 * Icone (tratto 1.8, 24 × 24)
 * ===================================================================== */

function icon(string $name, int $size = 20): string
{
    static $lib = [
        'home' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v10h14V10"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.4 3.2-5.5 6.5-5.5s5.9 2.1 6.5 5.5"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18.5 14.8c1.7.8 2.7 2.5 3 5.2"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c.8-4 4-6.5 8-6.5s7.2 2.5 8 6.5"/>',
        'user-plus' => '<circle cx="10" cy="8" r="4"/><path d="M2 21c.8-4 4-6.5 8-6.5 1.6 0 3 .4 4.300 1"/><path d="M19 13v6M16 16h6"/>',
        'building' => '<path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/>',
        'bed' => '<path d="M3 18V6M3 14h18v4M21 18v-5a3 3 0 0 0-3-3h-7v4"/><circle cx="7" cy="10.5" r="1.8"/>',
        'utensils' => '<path d="M7 3v8M4.500 3v5a2.500 2.500 0 0 0 5 0V3M7 11v10"/><path d="M17 3c-2 1.500-3 4-3 7h3v11"/>',
        'pot' => '<path d="M4 11h16v5a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4z"/><path d="M2 11h20M9 7c0-1.500 1-2 1-3.500M14 7c0-1.500 1-2 1-3.500"/>',
        'inbox' => '<path d="M3 13l3-8h12l3 8v6H3z"/><path d="M3 13h5l1.500 3h5L16 13h5"/>',
        'printer' => '<path d="M7 9V4h10v5"/><rect x="4" y="9" width="16" height="8" rx="2"/><path d="M7 14h10v6H7z"/>',
        'chart' => '<path d="M4 20V4M4 20h16"/><path d="M8 16v-5M12 16V8M16 16v-3"/>',
        'settings' => '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
        'key' => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l3 3M14 9l2 2"/>',
        'shield' => '<path d="M12 3l8 3v6c0 4.500-3.200 7.800-8 9-4.800-1.200-8-4.500-8-9V6z"/>',
        'card' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/>',
        'bell' => '<path d="M6 16v-5a6 6 0 0 1 12 0v5l2 2H4z"/><path d="M10 21h4"/>',
        'check' => '<path d="M5 12.500l4.500 4.500L19 7.500"/>',
        'x' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'back' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'chev-left' => '<path d="M15 5l-7 7 7 7"/>',
        'chev-right' => '<path d="M9 5l7 7-7 7"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'phone' => '<path d="M5 4h4l2 5-2.500 1.500a11 11 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1 1A16 16 0 0 1 4 5a1 1 0 0 1 1-1z"/>',
        'message' => '<path d="M4 20l1.500-4A8 8 0 1 1 9 19.500z"/>',
        'camera' => '<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.500"/>',
        'copy' => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>',
        'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M13.500 6.500l4 4"/>',
        'trash' => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6"/>',
        'search' => '<circle cx="11" cy="11" r="6.500"/><path d="M16 16l4.500 4.500"/>',
        'logout' => '<path d="M10 4H5v16h5M14 8l4 4-4 4M18 12H9"/>',
        'refresh' => '<path d="M20 11a8 8 0 0 0-14.500-4M4 4v4h4M4 13a8 8 0 0 0 14.500 4M20 20v-4h-4"/>',
        'alert' => '<path d="M12 4l9 16H3z"/><path d="M12 10v4M12 17h.01"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.500h.01"/>',
        'database' => '<ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v6c0 1.700 3.100 3 7 3s7-1.300 7-3V6M5 12v6c0 1.700 3.100 3 7 3s7-1.300 7-3v-6"/>',
        'wrench' => '<path d="M15 6a4 4 0 0 0 4.500 5.500L11 20a2.100 2.100 0 0 1-3-3l8.500-8.500A4 4 0 0 1 15 6z"/>',
        'toggle' => '<rect x="2" y="7" width="20" height="10" rx="5"/><circle cx="16" cy="12" r="3"/>',
        'external' => '<path d="M14 5h5v5M19 5l-8 8M11 6H5v13h13v-6"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.500 1.500M17.500 17.500L19 19M5 19l1.500-1.500M17.500 6.500L19 5"/>',
        'moon' => '<path d="M20 14.500A8 8 0 0 1 9.500 4 8 8 0 1 0 20 14.500z"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'link' => '<path d="M10 14a4 4 0 0 0 5.700 0l3-3a4 4 0 0 0-5.700-5.700l-1 1M14 10a4 4 0 0 0-5.700 0l-3 3a4 4 0 0 0 5.700 5.700l1-1"/>',
        'history' => '<path d="M4 12a8 8 0 1 0 2.500-5.800M4 5v4h4"/><path d="M12 8v4l3 2"/>',
        'download' => '<path d="M12 4v11M7 11l5 5 5-5M5 20h14"/>',
        'eye' => '<path d="M2 12s3.500-7 10-7 10 7 10 7-3.500 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.500 2.700 3.800 5.700 3.800 9s-1.300 6.300-3.800 9c-2.500-2.700-3.800-5.700-3.800-9S9.500 5.700 12 3z"/>',
        'list' => '<path d="M9 6h11M9 12h11M9 18h11M4.500 6h.01M4.500 12h.01M4.500 18h.01"/>',
    ];
    $d = $lib[$name] ?? $lib['info'];
    return '<svg class="ic" aria-hidden="true" focusable="false" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $d . '</svg>';
}

/** Simbolo ChiMangia; $light: versione chiara per i fondi blu. */
function pf_logo(int $width = 30, bool $light = false): string
{
    $svg = logo_svg($width);
    return $light ? str_replace(['stroke="#13294B"', 'stroke="#FBF7EF"'], ['stroke="#FFFFFF"', 'stroke="#13294B"'], $svg) : $svg;
}

/** Titolo: il testo tra *asterischi* diventa corsivo, come nella home. */
function ui_title(string $s): string
{
    return preg_replace('/\*([^*<>]+)\*/u', '<em>$1</em>', e($s)) ?? e($s);
}

/* =====================================================================
 * Pagina: intestazione HTML
 * ===================================================================== */

function ui_head(string $title, string $bodyClass, array $o = []): void
{
    $p = pf_prefix();
    $v = CM_VERSION;
    echo '<!doctype html><html lang="it"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
        . '<meta name="theme-color" content="#13294B">'
        . (empty($o['indexable']) ? '<meta name="robots" content="noindex, nofollow">' : '<meta name="description" content="' . e((string) ($o['description'] ?? '')) . '">')
        . '<title>' . e($title) . ' · ' . e((string) ($o['site'] ?? 'ChiMangia')) . '</title>'
        . '<link rel="icon" href="' . $p . 'assets/img/favicon.svg" type="image/svg+xml">'
        . '<link rel="apple-touch-icon" href="' . $p . 'assets/img/apple-touch-icon.png">'
        . (!empty($o['manifest']) ? '<link rel="manifest" href="' . e((string) $o['manifest']) . '">' : '')
        . '<link rel="preload" href="' . $p . 'assets/fonts/instrument-serif-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>'
        . '<link rel="stylesheet" href="' . $p . 'assets/css/app.css?v=' . $v . '">'
        . '<script src="' . $p . 'assets/js/app.js?v=' . $v . '" defer></script>'
        . '</head><body class="pf ' . e($bodyClass) . '">'
        . '<a class="skip" href="#contenuto">Vai al contenuto</a>';
}

/* =====================================================================
 * Pannello con menu laterale (gestione del locale e super admin)
 * ===================================================================== */

/**
 * Voci del menu del locale secondo permessi e funzioni attive.
 * @return list<array{0:string,1:string,2:string,3:int,4:string}> [file, etichetta, icona, contatore, gruppo]
 */
function staff_nav(array $me): array
{
    $v = $me['venue'];
    $items = [['index.php', 'Oggi', 'sun', 0, '']];
    $add = static function (bool $ok, string $href, string $label, string $icon, int $count = 0, string $group = '') use (&$items): void {
        if ($ok) {
            $items[] = [$href, $label, $icon, $count, $group];
        }
    };
    $add(can('presenti', $me), 'cucina.php', 'Cucina', 'pot');
    $add(can('conferma', $me), 'registrazioni.php', 'Registrazioni', 'user-plus', guests_pending_count((int) $v['id']));
    $add(can('richieste', $me), 'richieste.php', 'Richieste', 'inbox', requests_pending_count((int) $v['id']));
    $add(can('conferma', $me), 'ospiti.php', 'Ospiti', 'users');
    $add(can('societa', $me) && feature('societa', $v), 'societa.php', 'Società', 'building');
    $add(can('camere', $me) && feature('hotel', $v), 'camere.php', 'Camere', 'bed');
    $add(can('menu', $me), 'menu.php', 'Menù e pasti', 'utensils');
    $add(can('stampe', $me), 'stampe.php', 'Prospetti e stampe', 'printer');
    $add(can('prezzi', $me) && feature('riepilogo', $v), 'riepilogo.php', 'Riepilogo mensile', 'chart');
    $add(can('impostazioni', $me), 'impostazioni.php', 'Impostazioni', 'settings', 0, 'Il locale');
    $add(can('staff', $me), 'staff.php', 'Staff', 'key', 0, 'Il locale');
    $add((int) $me['is_owner'] === 1 || $me['role'] === 'admin', 'abbonamento.php', 'Abbonamento', 'card', 0, 'Il locale');
    $add(true, 'profilo.php', 'Il mio profilo', 'user', 0, 'Il locale');
    return $items;
}

function super_nav(): array
{
    return [
        ['index.php', 'Clienti', 'building', 0, ''],
        ['funzioni.php', 'Funzioni e permessi', 'toggle', 0, ''],
        ['sistema.php', 'Sistema', 'wrench', 0, ''],
        ['backup.php', 'Backup', 'database', 0, ''],
        ['registro.php', 'Registro', 'history', 0, ''],
        ['../admin/', 'Sito web', 'globe', 0, 'Altro'],
    ];
}

function ui_nav_html(array $items, string $active): string
{
    $html = '';
    $group = null;
    foreach ($items as [$href, $label, $ic, $count, $g]) {
        if ($g !== $group) {
            $html .= ($group !== null ? '</ul>' : '') . ($g !== '' ? '<p class="side-group">' . e($g) . '</p>' : '') . '<ul>';
            $group = $g;
        }
        $html .= '<li><a href="' . e($href) . '"' . ($href === $active ? ' aria-current="page"' : '') . '>' . icon($ic) . '<span>' . e($label) . '</span>'
            . ($count > 0 ? '<span class="count" aria-label="' . $count . ' da gestire">' . $count . '</span>' : '') . '</a></li>';
    }
    return $html . ($group !== null ? '</ul>' : '');
}

/**
 * Apre una pagina del pannello. $o: eyebrow, actions (HTML), wide (bool).
 * Il titolo accetta il corsivo tra *asterischi*.
 */
function ui_staff_start(string $title, string $active = '', array $o = []): void
{
    $me = staff_current();
    $v = $me['venue'];
    $banners = '';
    if ($me['impersonated']) {
        $banners .= '<div class="banner banner-zaff">' . icon('eye') . '<span>Stai vedendo il pannello di <strong>' . e($v['name']) . '</strong> come fornitore.</span>'
            . '<form method="post" action="logout.php">' . csrf_field() . '<button class="btn btn-small btn-dark" type="submit">Esci dal locale</button></form></div>';
    }
    $days = venue_trial_days($v);
    if ($days !== null && pf_script() !== 'abbonamento.php') {
        $banners .= '<div class="banner">' . icon('clock') . '<span>' . ($days > 0
            ? 'Prova gratuita: ' . ($days === 1 ? 'manca 1 giorno' : 'mancano ' . $days . ' giorni') . '.'
            : 'La prova gratuita è terminata.') . '</span><a class="btn btn-small btn-line" href="abbonamento.php">Attiva il piano</a></div>';
    }
    if ($v['email_verified_at'] === null && (int) $me['is_owner'] === 1 && !$me['impersonated']) {
        $banners .= '<div class="banner">' . icon('info') . '<span>Conferma la tua email: ti abbiamo scritto a <strong>' . e($me['email']) . '</strong>.</span>'
            . '<form method="post" action="profilo.php">' . csrf_field() . '<input type="hidden" name="azione" value="verifica"><button class="btn btn-small btn-line" type="submit">Invia di nuovo</button></form></div>';
    }
    $notice = setting('pf_announcement', []);
    if (!empty($notice['text']) && (empty($notice['until']) || $notice['until'] >= today())) {
        $banners .= '<div class="banner">' . icon('bell') . '<span>' . e((string) $notice['text']) . '</span></div>';
    }
    ui_shell([
        'class' => 'pf-staff', 'title' => $title, 'site' => $v['name'], 'active' => $active, 'nav' => staff_nav($me),
        'brand_sub' => $v['name'], 'brand_note' => feature('hotel', $v) ? 'Ospiti, camere e pasti' : 'Ospiti e pasti',
        'who' => $me['name'], 'role' => (int) $me['is_owner'] === 1 ? 'Titolare' : (pf_roles()[$me['role']] ?? ''),
        'banners' => $banners,
    ] + $o);
}

function ui_super_start(string $title, string $active = '', array $o = []): void
{
    $u = current_user();
    $m = pf_maintenance();
    ui_shell([
        'class' => 'pf-staff pf-super', 'title' => $title, 'site' => 'Super admin', 'active' => $active, 'nav' => super_nav(),
        'brand_sub' => 'Super admin', 'brand_note' => 'Accesso riservato al fornitore',
        'who' => $u['name'] ?? '', 'role' => 'Fornitore',
        'banners' => $m['on'] ? '<div class="banner banner-zaff">' . icon('wrench') . '<span>La piattaforma è in manutenzione: locali e ospiti vedono un avviso.</span><a class="btn btn-small btn-dark" href="sistema.php">Gestisci</a></div>' : '',
    ] + $o);
}

function ui_shell(array $o): void
{
    ui_head((string) $o['title'] === '' ? 'Pannello' : strip_tags(str_replace('*', '', (string) $o['title'])), (string) $o['class'], ['site' => $o['site'] ?? 'ChiMangia']);
    echo '<div class="shell"><aside class="side" id="side"><div class="side-panel">'
        . '<a class="side-brand" href="index.php">' . pf_logo(28, true) . '<span>ChiMangia</span></a>'
        . '<p class="side-venue"><em>' . e((string) $o['brand_sub']) . '</em><small>' . e((string) $o['brand_note']) . '</small></p>'
        . '<nav class="side-nav" aria-label="Sezioni">' . ui_nav_html($o['nav'], (string) $o['active']) . '</nav>'
        . '<div class="side-foot"><p class="who">' . e((string) $o['who']) . '<small>' . e((string) $o['role']) . '</small></p>'
        . '<form method="post" action="logout.php">' . csrf_field() . '<button type="submit" class="btn btn-small btn-ghost">' . icon('logout', 16) . 'Esci</button></form></div>'
        . '</div></aside>'
        . '<div class="main-col"><header class="topbar"><a class="side-brand" href="index.php">' . pf_logo(26) . '<span>ChiMangia</span></a>'
        . '<button type="button" class="nav-toggle" aria-expanded="false" aria-controls="side">' . icon('menu') . '<span>Menu</span></button></header>'
        . (string) ($o['banners'] ?? '')
        . '<main id="contenuto" class="main' . (!empty($o['narrow']) ? ' main-narrow' : '') . '" tabindex="-1">'
        . '<div class="page-head"><div>' . (!empty($o['eyebrow']) ? '<p class="eyebrow">' . e((string) $o['eyebrow']) . '</p>' : '')
        . '<h1>' . ui_title((string) $o['title']) . '</h1>' . (!empty($o['lead']) ? '<p class="lead">' . e((string) $o['lead']) . '</p>' : '') . '</div>'
        . (!empty($o['actions']) ? '<div class="page-actions">' . $o['actions'] . '</div>' : '') . '</div>'
        . pf_flash_html();
}

function ui_shell_end(): void
{
    echo '</main></div></div></body></html>';
}

/* =====================================================================
 * Area ospiti (pensata per il telefono)
 * ===================================================================== */

/** $o: eyebrow, back (href), nav (bool, default true). $venue serve quando l'ospite non è ancora entrato. */
function ui_guest_start(string $title, string $active = '', array $o = [], ?array $venue = null): void
{
    $me = guest_current();
    $venue ??= $me['venue'] ?? null;
    $name = (string) ($venue['name'] ?? 'ChiMangia');
    ui_head(strip_tags(str_replace('*', '', $title)), 'pf-guest', ['site' => $name, 'manifest' => $venue ? 'manifest.php?l=' . rawurlencode((string) $venue['slug']) : '']);
    $bell = '';
    if ($me && feature('notifiche', $venue)) {
        $n = notices_unread((int) $me['id']);
        $bell = '<a class="g-bell" href="notifiche.php" aria-label="Avvisi' . ($n > 0 ? ' (' . $n . ' nuovi)' : '') . '">' . icon('bell', 22) . ($n > 0 ? '<span class="dot">' . $n . '</span>' : '') . '</a>';
    }
    echo '<div class="g-shell"><header class="g-head">'
        . '<div class="g-top">' . (!empty($o['back']) ? '<a class="g-back" href="' . e((string) $o['back']) . '">' . icon('back', 18) . e((string) ($o['back_label'] ?? 'Indietro')) . '</a>' : '<span class="g-venue">' . e($name) . '</span>') . $bell . '</div>'
        . (!empty($o['eyebrow']) ? '<p class="g-hello">' . e((string) $o['eyebrow']) . '</p>' : '')
        . '<h1>' . ui_title($title) . '</h1>'
        . (!empty($o['lead']) ? '<p class="g-lead">' . $o['lead'] . '</p>' : '')
        . '</header><main id="contenuto" class="g-main" tabindex="-1">' . pf_flash_html();
    $GLOBALS['pf_guest_nav'] = $me && ($o['nav'] ?? true) ? $active : null;
}

function ui_guest_end(): void
{
    echo '</main>';
    $active = $GLOBALS['pf_guest_nav'] ?? null;
    if ($active !== null) {
        $me = guest_current();
        $items = [['index.php', 'Home', 'home'], ['pasti.php', feature('hotel', $me['venue']) ? 'Notti e pasti' : 'I miei pasti', 'calendar'], ['menu.php', 'Menù', 'utensils'], ['profilo.php', 'Profilo', 'user']];
        echo '<nav class="g-nav" aria-label="Menu principale">';
        foreach ($items as [$href, $label, $ic]) {
            echo '<a href="' . $href . '"' . ($href === $active ? ' aria-current="page"' : '') . '>' . icon($ic, 22) . '<span>' . e($label) . '</span></a>';
        }
        echo '</nav>';
    }
    echo '<p class="g-foot">Prenotazioni con <a href="' . e(pf_prefix()) . '">ChiMangia</a></p></div></body></html>';
}

/* =====================================================================
 * Pagine di accesso e registrazione del locale (foto a tutta altezza + modulo)
 * ===================================================================== */

/** $o: eyebrow, lead, photo (chiave della libreria), quote, indexable, description. */
function ui_auth_start(string $title, array $o = []): void
{
    $p = pf_prefix();
    ui_head(strip_tags(str_replace('*', '', $title)), 'pf-auth', $o);
    echo '<div class="auth"><aside class="auth-side">' . photo_picture((string) ($o['photo'] ?? 'brigata'), '', $p, '(max-width: 960px) 100vw, 50vw', true)
        . '<a class="auth-logo" href="' . e($p) . '" aria-label="ChiMangia, torna al sito">' . pf_logo(34, true) . '<span>ChiMangia</span></a>'
        . '<p class="auth-quote">' . ui_title((string) ($o['quote'] ?? 'Sai chi mangia, prima di *accendere i fornelli.*')) . '</p></aside>'
        . '<main class="auth-main" id="contenuto" tabindex="-1"><div class="auth-box">'
        . '<a class="auth-logo-m" href="' . e($p) . '">' . pf_logo(30) . '<span>ChiMangia</span></a>'
        . (!empty($o['eyebrow']) ? '<p class="eyebrow">' . e((string) $o['eyebrow']) . '</p>' : '')
        . '<h1>' . ui_title($title) . '</h1>' . (!empty($o['lead']) ? '<p class="lead">' . e((string) $o['lead']) . '</p>' : '')
        . pf_flash_html();
}

function ui_auth_end(): void
{
    echo '</div></main></div></body></html>';
}

/** Pagina essenziale per messaggi ed errori. */
function ui_plain_page(string $title, string $html): void
{
    ui_head($title, 'pf-plain');
    echo '<main class="plain" id="contenuto"><a class="auth-logo-m" href="' . e(pf_prefix()) . '">' . pf_logo(30) . '<span>ChiMangia</span></a>'
        . '<h1>' . ui_title($title) . '</h1>' . $html . '</main></body></html>';
}

function ui_die(string $title, string $text, int $code = 200): never
{
    if ($code !== 200) {
        http_response_code($code);
    }
    ui_plain_page($title, '<p class="lead">' . e($text) . '</p><p><a class="btn btn-line" href="index.php">' . icon('back', 16) . 'Torna indietro</a></p>');
    exit;
}

/* =====================================================================
 * Componenti
 * ===================================================================== */

/**
 * Campo di un modulo. $o: type (text, email, tel, password, number, time, date, textarea, select),
 * required, hint, placeholder, autocomplete, options (select), attrs (HTML già pronto), id, rows, error.
 */
function ui_field(string $name, string $label, string $value = '', array $o = []): string
{
    $type = (string) ($o['type'] ?? 'text');
    $id = (string) ($o['id'] ?? 'f-' . preg_replace('/[^a-z0-9]+/i', '-', $name));
    $req = !empty($o['required']);
    $hintId = !empty($o['hint']) ? $id . '-h' : '';
    $common = ' id="' . e($id) . '" name="' . e($name) . '"' . ($req ? ' required' : '')
        . (isset($o['placeholder']) ? ' placeholder="' . e((string) $o['placeholder']) . '"' : '')
        . (isset($o['autocomplete']) ? ' autocomplete="' . e((string) $o['autocomplete']) . '"' : '')
        . ($hintId !== '' ? ' aria-describedby="' . e($hintId) . '"' : '') . (isset($o['attrs']) ? ' ' . $o['attrs'] : '');
    if ($type === 'textarea') {
        $control = '<textarea rows="' . (int) ($o['rows'] ?? 3) . '"' . $common . '>' . e($value) . '</textarea>';
    } elseif ($type === 'select') {
        $control = '<select' . $common . '>';
        foreach ((array) ($o['options'] ?? []) as $k => $lab) {
            $control .= '<option value="' . e((string) $k) . '"' . ((string) $k === $value ? ' selected' : '') . '>' . e((string) $lab) . '</option>';
        }
        $control .= '</select>';
    } else {
        $control = '<input type="' . e($type) . '" value="' . e($value) . '"' . $common . '>';
    }
    return '<div class="field' . (!empty($o['class']) ? ' ' . e((string) $o['class']) : '') . '"><label for="' . e($id) . '">' . e($label)
        . ($req ? ' <span class="req" aria-hidden="true">*</span>' : '') . '</label>' . $control
        . ($hintId !== '' ? '<p class="hint" id="' . e($hintId) . '">' . e((string) $o['hint']) . '</p>' : '') . '</div>';
}

/** Casella di spunta con etichetta (HTML ammesso nell'etichetta: passare testo già con escape). */
function ui_check(string $name, string $labelHtml, bool $checked = false, bool $required = false, string $value = '1'): string
{
    $id = 'c-' . preg_replace('/[^a-z0-9]+/i', '-', $name . '-' . $value);
    return '<label class="check" for="' . e($id) . '"><input type="checkbox" id="' . e($id) . '" name="' . e($name) . '" value="' . e($value) . '"'
        . ($checked ? ' checked' : '') . ($required ? ' required' : '') . '><span>' . $labelHtml . '</span></label>';
}

/** Interruttore (una casella di spunta con aspetto da interruttore). */
function ui_switch(string $name, string $label, bool $on, string $desc = '', string $value = '1'): string
{
    $id = 's-' . preg_replace('/[^a-z0-9]+/i', '-', $name . '-' . $value);
    return '<label class="switch" for="' . e($id) . '"><span class="switch-text"><strong>' . e($label) . '</strong>' . ($desc !== '' ? '<small>' . e($desc) . '</small>' : '') . '</span>'
        . '<input type="checkbox" id="' . e($id) . '" name="' . e($name) . '" value="' . e($value) . '"' . ($on ? ' checked' : '') . '><span class="switch-track" aria-hidden="true"></span></label>';
}

/**
 * Gruppo di pastiglie selezionabili. $options: valore => etichetta. $type: checkbox (più scelte) o radio (una).
 */
function ui_chips(string $name, array $options, array $selected, string $type = 'checkbox', string $class = ''): string
{
    $html = '<div class="chips ' . e($class) . '">';
    $i = 0;
    foreach ($options as $val => $label) {
        $id = 'ch-' . preg_replace('/[^a-z0-9]+/i', '-', $name) . '-' . (++$i);
        $html .= '<label class="chip" for="' . $id . '"><input type="' . $type . '" id="' . $id . '" name="' . e($name) . '" value="' . e((string) $val) . '"'
            . (in_array((string) $val, array_map('strval', $selected), true) ? ' checked' : '') . '><span>' . e((string) $label) . '</span></label>';
    }
    return $html . '</div>';
}

/** Etichetta piccola. $tone: '' (linea), diet (blu pieno), zaff, ok, warn, err, soft. */
function ui_tag(string $label, string $tone = ''): string
{
    return '<span class="tag' . ($tone !== '' ? ' tag-' . e($tone) : '') . '">' . e($label) . '</span>';
}

function ui_kpi(string $label, string|int $value, string $sub = '', string $tone = ''): string
{
    return '<div class="kpi' . ($tone !== '' ? ' kpi-' . e($tone) : '') . '"><p class="kpi-label">' . e($label) . '</p><p class="kpi-num">' . e((string) $value) . '</p>'
        . ($sub !== '' ? '<p class="kpi-sub">' . e($sub) . '</p>' : '') . '</div>';
}

function ui_empty(string $text, string $ic = 'check'): string
{
    return '<div class="empty">' . icon($ic, 26) . '<p>' . e($text) . '</p></div>';
}

/** Schede a segmenti (collegamenti). $items: [href, etichetta, attiva, contatore opzionale]. */
function ui_seg(array $items, string $label = 'Viste'): string
{
    $html = '<nav class="seg" aria-label="' . e($label) . '">';
    foreach ($items as $it) {
        $html .= '<a href="' . e((string) $it[0]) . '"' . (!empty($it[2]) ? ' aria-current="true"' : '') . '>' . e((string) $it[1])
            . (isset($it[3]) ? ' <span class="seg-n">' . (int) $it[3] . '</span>' : '') . '</a>';
    }
    return $html . '</nav>';
}

/** Navigazione tra i giorni: ← data →, con il collegamento a oggi. $base: es. "cucina.php?p=cena&g=". */
function ui_day_nav(string $day, string $base): string
{
    return '<div class="day-nav"><a class="round" href="' . e($base . day_add($day, -1)) . '" aria-label="Giorno precedente">' . icon('chev-left', 18) . '</a>'
        . '<span class="day-nav-label">' . e(day_it($day, 'full')) . '</span>'
        . '<a class="round" href="' . e($base . day_add($day, 1)) . '" aria-label="Giorno successivo">' . icon('chev-right', 18) . '</a>'
        . ($day !== today() ? '<a class="btn btn-small btn-line" href="' . e($base . today()) . '">Oggi</a>' : '') . '</div>';
}

/** Pulsante dentro un piccolo modulo POST (azioni rapide nelle tabelle). */
function ui_post_btn(string $action, array $fields, string $label, string $class = 'btn btn-small btn-line', string $confirm = ''): string
{
    $html = '<form method="post" action="' . e($action) . '" class="inline-form"' . ($confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field();
    foreach ($fields as $k => $v) {
        $html .= '<input type="hidden" name="' . e((string) $k) . '" value="' . e((string) $v) . '">';
    }
    return $html . '<button type="submit" class="' . e($class) . '">' . $label . '</button></form>';
}

/** Etichette di diete e note di un ospite. */
function ui_guest_tags(array $tags): string
{
    $html = '';
    foreach ($tags as $t) {
        $html .= ui_tag((string) $t['label'], $t['type'] === 'diet' ? 'diet' : 'soft');
    }
    return $html;
}

/** Giorno valido dalla query (?g=AAAA-MM-GG), altrimenti oggi. */
function ui_day_param(string $key = 'g'): string
{
    $d = get_param($key);
    return valid_day($d) ? $d : today();
}

function ui_meal_param(?array $venue = null, string $key = 'p'): string
{
    $m = get_param($key);
    if (isset(PF_MEALS[$m])) {
        return $m;
    }
    // Di mattina si lavora sul pranzo, dal primo pomeriggio sulla cena.
    $meals = (array) ($venue['settings']['meals'] ?? ['pranzo', 'cena']);
    $guess = (int) date('G') < 14 ? 'pranzo' : 'cena';
    return in_array($guess, $meals, true) ? $guess : (string) ($meals[0] ?? 'pranzo');
}
