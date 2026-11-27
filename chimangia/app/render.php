<?php
/**
 * Generazione dell'HTML del sito pubblico a partire dai contenuti.
 * Il risultato viene salvato in cache a ogni pubblicazione (site_rebuild),
 * così le visite non interrogano il database.
 */
declare(strict_types=1);

defined('CM_ROOT') || exit;

require_once CM_APP . '/icons.php';
require_once CM_APP . '/sanitize.php';
require_once CM_APP . '/media.php';

const CM_FORM_TOKEN_PLACEHOLDER = '{{CM_FORM_TOKEN}}';
const CM_FORM_STATUS_PLACEHOLDER = '<!--CM_FORM_STATUS-->';

/** Contesto di rendering condiviso dalle funzioni di sezione. */
final class RenderCtx
{
    public array $c;
    public array $tokens;
    public bool $offer;
    public string $prefix;
    public bool $preview;
    /** Scheda d'esempio della cucina: l'apertura la prepara, "Il problema" la mostra. */
    public string $heroCard = '';
    /** Telefono d'esempio (lato cliente), mostrato accanto alla scheda della cucina. */
    public string $heroPhone = '';

    public function __construct(array $c, string $prefix, bool $preview)
    {
        $this->c = $c;
        $this->tokens = content_tokens($c);
        $this->offer = offer_is_active($c['prezzi'] ?? []);
        $this->prefix = $prefix;
        $this->preview = $preview;
    }

    /** Testo breve con segnaposto, già con escape. */
    public function t(string $s): string
    {
        return e(apply_tokens($s, $this->tokens));
    }

    /** Titolo: come t(), ma il testo tra *asterischi* diventa corsivo. */
    public function ti(string $s): string
    {
        return preg_replace('/\\*([^*<>]+)\\*/u', '<em>$1</em>', $this->t($s)) ?? $this->t($s);
    }

    /** HTML dell'editor (già filtrato in salvataggio, rifiltrato per sicurezza). */
    public function h(string $html): string
    {
        return sanitize_html(apply_tokens($html, $this->tokens));
    }

    /** Destinazione di un link, adattata alla pagina (le ancore puntano alla home dalle pagine interne). */
    public function href(string $h): string
    {
        $h = trim($h);
        if (!safe_href($h)) {
            return '#';
        }
        if ($this->prefix !== '' && str_starts_with($h, '#')) {
            return $this->prefix . $h;
        }
        if ($this->prefix !== '' && !preg_match('#^([a-z]+:|/|\#|\.)#i', $h)) {
            return $this->prefix . $h;
        }
        return $h;
    }
}

function btn_arrow(): string
{
    return '<span class="arr" aria-hidden="true">→</span>';
}

/* =====================================================================
 * Pagina completa
 * ===================================================================== */

function render_site(array $c, array $opts = []): string
{
    $x = new RenderCtx($c, (string) ($opts['prefix'] ?? ''), !empty($opts['preview']));
    $seo = $c['seo'];

    $body = render_header($x)
        . '<main id="contenuto">'
        . render_hero($x)
        . render_marquee($x)
        . render_problema($x)
        . render_come_funziona($x)
        . render_giornata($x)
        . render_funzioni($x)
        . render_per_chi($x)
        . render_prezzi($x)
        . render_faq($x)
        . render_contatti($x)
        . '</main>'
        . render_footer($x);

    return render_document($x, (string) $seo['title'], (string) $seo['description'], site_url('/'), $body, structured_data($x));
}

function render_legal(array $c, string $area, array $opts = []): string
{
    $x = new RenderCtx($c, (string) ($opts['prefix'] ?? '../'), !empty($opts['preview']));
    $page = $c[$area] ?? ['title' => '', 'body' => ''];
    $body = render_header($x)
        . '<main id="contenuto" class="legal"><div class="wrap wrap-narrow">'
        . '<p class="back"><a href="' . e($x->prefix !== '' ? $x->prefix : './') . '">← Torna alla home</a></p>'
        . '<h1>' . e($page['title']) . '</h1>'
        . '<div class="prose">' . $x->h((string) $page['body']) . '</div>'
        . '</div></main>'
        . render_footer($x);
    $title = $page['title'] . ' · ' . ($c['seo']['app_name'] ?: 'ChiMangia');
    return render_document($x, $title, (string) $page['title'], site_url($area . '/'), $body, '');
}

function render_maintenance(string $message, string $prefix = ''): string
{
    return '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>Sito in aggiornamento · ChiMangia</title>'
        . '<link rel="icon" href="' . e($prefix) . 'assets/img/favicon.svg" type="image/svg+xml">'
        . '<link rel="stylesheet" href="' . e($prefix) . 'assets/css/site.css?v=' . CM_VERSION . '"></head>'
        . '<body><main class="maintenance" id="contenuto"><div class="maintenance-box">'
        . '<p class="logo">' . logo_svg(48) . '<span>ChiMangia</span></p>'
        . '<h1>Torniamo subito</h1><p>' . e($message) . '</p></div></main></body></html>';
}

function render_document(RenderCtx $x, string $title, string $description, string $canonical, string $body, string $jsonLd): string
{
    $p = e($x->prefix);
    $seo = $x->c['seo'];
    $og = media_url((int) ($seo['og_image'] ?? 0), true);
    $ogUrl = $og !== '' ? $og : site_url('assets/img/og-default.png');
    $ogAlt = $og !== '' ? media_alt((int) $seo['og_image']) : 'ChiMangia: prenotazione pasti';

    $head = '<!doctype html><html lang="it"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($title) . '</title>'
        . '<meta name="description" content="' . e($description) . '">'
        . ($x->preview ? '<meta name="robots" content="noindex, nofollow">' : '<link rel="canonical" href="' . e($canonical) . '">')
        . '<meta name="theme-color" content="#13294B">'
        . '<meta property="og:type" content="website"><meta property="og:locale" content="it_IT">'
        . '<meta property="og:site_name" content="' . e($seo['app_name'] ?: 'ChiMangia') . '">'
        . '<meta property="og:title" content="' . e($title) . '">'
        . '<meta property="og:description" content="' . e($description) . '">'
        . '<meta property="og:url" content="' . e($canonical) . '">'
        . '<meta property="og:image" content="' . e($ogUrl) . '"><meta property="og:image:alt" content="' . e($ogAlt) . '">'
        . '<meta name="twitter:card" content="summary_large_image">'
        . '<link rel="icon" href="' . $p . 'assets/img/favicon.ico" sizes="32x32">'
        . '<link rel="icon" href="' . $p . 'assets/img/favicon.svg" type="image/svg+xml">'
        . '<link rel="apple-touch-icon" href="' . $p . 'assets/img/apple-touch-icon.png">'
        . '<link rel="preload" href="' . $p . 'assets/fonts/instrument-serif-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>'
        . '<link rel="preload" href="' . $p . 'assets/fonts/inter-latin-300-normal.woff2" as="font" type="font/woff2" crossorigin>'
        . '<link rel="stylesheet" href="' . $p . 'assets/css/site.css?v=' . CM_VERSION . '">'
        . '<script src="' . $p . 'assets/js/site.js?v=' . CM_VERSION . '"></script>'
        . ($jsonLd !== '' ? '<script type="application/ld+json">' . $jsonLd . '</script>' : '')
        . '</head><body>';

    $banner = $x->preview
        ? '<div class="preview-banner" role="status"><strong>Anteprima della bozza.</strong> Questa versione non è ancora pubblicata.</div>'
        : '';

    return $head . '<a class="skip" href="#contenuto">Vai al contenuto</a>' . $banner . $body . '</body></html>';
}

/* =====================================================================
 * Sezioni
 * ===================================================================== */

function render_header(RenderCtx $x): string
{
    $h = $x->c['header'];
    $links = '';
    foreach ($h['nav'] as $n) {
        if (trim((string) ($n['label'] ?? '')) === '') {
            continue;
        }
        $links .= '<li><a class="nav-link" href="' . e($x->href((string) $n['href'])) . '">' . e($n['label']) . '</a></li>';
    }
    return '<header class="site-header"><div class="edge header-inner">'
        . '<a class="logo" href="' . e($x->prefix === '' ? '#top' : $x->prefix) . '" aria-label="ChiMangia, torna all\'inizio">' . logo_svg(40) . '<span>ChiMangia</span></a>'
        . '<button type="button" class="nav-toggle" aria-expanded="false" aria-controls="site-nav">'
        . '<span class="nav-toggle-bars" aria-hidden="true"></span><span class="nav-toggle-label">Menu</span></button>'
        . '<nav id="site-nav" class="site-nav" aria-label="Sezioni"><ul>' . $links . '</ul>'
        . ($h['cta_text'] !== '' ? '<a class="btn btn-dark btn-pill" href="' . e($x->href((string) $h['cta_href'])) . '">' . $x->t($h['cta_text']) . btn_arrow() . '</a>' : '')
        . '</nav></div><div class="scroll-progress" aria-hidden="true"><span></span></div></header>';
}

function render_hero(RenderCtx $x): string
{
    $s = $x->c['hero'];
    if (empty($s['visible'])) {
        return '';
    }
    // Gruppi di piatti: servono sia alla scheda della cucina sia al telefono d'esempio.
    $groupsData = [];
    foreach ($s['card_groups'] as $g) {
        $items = [];
        foreach (preg_split('/\n/', (string) $g['items']) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 2));
            $items[] = [$parts[0], $parts[1] ?? ''];
        }
        if ($items) {
            $groupsData[] = [(string) $g['title'], $items];
        }
    }
    // Il piatto che il cliente "sceglie" nell'animazione: il secondo del primo gruppo, il primo del secondo.
    $pick = static fn (int $gi, int $count): int => $gi === 0 ? min(1, $count - 1) : 0;

    $groups = '';
    $phoneGroups = '';
    foreach ($groupsData as $gi => [$title, $items]) {
        $groups .= '<p class="kc-group">' . e($title) . '</p><ul class="kc-list">';
        if ($gi < 2) {
            $phoneGroups .= '<p class="ph-group">' . e($title) . '</p><ul class="ph-list">';
        }
        foreach ($items as $ii => [$name, $qty]) {
            $picked = $gi < 2 && $ii === $pick($gi, count($items));
            $num = ctype_digit($qty) ? ' data-count="' . e($qty) . '"' . ($picked ? ' data-tick' : '') : '';
            $groups .= '<li><span>' . e($name) . '</span><strong' . $num . '>' . e($qty) . '</strong></li>';
            if ($gi < 2 && $ii < 3) {
                $phoneGroups .= '<li class="ph-opt"' . ($picked ? ' data-pick' : '') . '><span class="ph-radio"></span><span>' . e($name) . '</span></li>';
            }
        }
        $groups .= '</ul>';
        if ($gi < 2) {
            $phoneGroups .= '</ul>';
        }
    }
    $tags = '';
    foreach (preg_split('/\n/', (string) $s['card_tags']) ?: [] as $tag) {
        if (trim($tag) !== '') {
            $tags .= '<li>' . e(trim($tag)) . '</li>';
        }
    }
    $buttons = '';
    if ($s['btn1_text'] !== '') {
        $buttons .= '<a class="btn btn-dark" href="' . e($x->href($s['btn1_href'])) . '">' . $x->t($s['btn1_text']) . btn_arrow() . '</a>';
    }
    if ($s['btn2_text'] !== '') {
        $buttons .= '<a class="btn btn-line" href="' . e($x->href($s['btn2_href'])) . '">' . $x->t($s['btn2_text']) . '</a>';
    }
    $full = ($s['layout'] ?? 'full') === 'full';
    $photo = hero_slider_html($x, $s, $full);
    $text = '<div class="hero-text">'
        . ($x->offer && $s['badge'] !== '' ? '<p class="pill">' . $x->t($s['badge']) . '</p>' : '')
        . '<h1 id="hero-title" data-split>' . $x->ti($s['title']) . '</h1>'
        . '<p class="hero-lead">' . $x->t($s['text']) . '</p>'
        . ($buttons !== '' ? '<div class="btn-row">' . $buttons . '</div>' : '')
        . ($s['note'] !== '' ? '<p class="hero-note">' . $x->t($s['note']) . '</p>' : '')
        . '</div>';
    $number = (string) $s['card_number'];
    $card = '<figure class="kitchen-card">'
        . '<p class="kc-label"><span class="kc-live" aria-hidden="true"></span>' . e($s['card_label']) . '</p>'
        . '<p class="kc-count"><span class="kc-num"' . (ctype_digit($number) ? ' data-count="' . e($number) . '" data-tick' : '') . '>' . e($number) . '</span> <span>' . e($s['card_number_label']) . '</span></p>'
        . '<div class="kc-body">' . $groups . '</div>'
        . ($tags !== '' ? '<ul class="kc-tags">' . $tags . '</ul>' : '')
        . '<figcaption class="kc-caption">' . e($s['card_caption']) . '</figcaption>'
        . '</figure>';
    $phone = '';
    if ($phoneGroups !== '') {
        $phoneTitle = (string) ($s['phone_title'] ?? '');
        $phone = '<div class="phone" role="img" aria-label="' . e(trim($phoneTitle . '. ' . $s['card_caption'], '. ')) . '"><div class="phone-screen" aria-hidden="true">'
            . ($phoneTitle !== '' ? '<p class="ph-title">' . e($phoneTitle) . '</p>' : '')
            . $phoneGroups
            . (($s['phone_button'] ?? '') !== '' ? '<span class="ph-btn"><span>' . e($s['phone_button']) . '</span></span>' : '')
            . '</div></div>';
    }
    if ($full && $photo !== '') {
        // Fotografia grande con il titolo al centro. Telefono e scheda d'esempio passano a "Come funziona".
        $x->heroCard = $card;
        $x->heroPhone = $phone;
        $chip = (ctype_digit($number) && $s['card_number_label'] !== '')
            ? '<p class="hero-chip"><span class="kc-live" aria-hidden="true"></span><b>' . e($number) . '</b> ' . e($s['card_number_label']) . '</p>' : '';
        $after = empty($x->c['come_funziona']['visible']) ? '<div class="edge hero-card-row">' . $card . '</div>' : '';
        return '<section id="top" class="hero-full" aria-labelledby="hero-title">' . $photo
            . '<div class="hero-shade" aria-hidden="true"></div>'
            . '<div class="hero-inner">' . $text . '</div>' . $chip . '</section>' . $after;
    }
    return '<section id="top" class="hero-side edge" aria-labelledby="hero-title">' . $text
        . '<div class="hero-visual' . ($photo !== '' ? ' has-photo' : '') . '">' . $photo . $card . '</div></section>';
}

/** Nastro zafferano con i titoli delle funzioni: decorativo, scorre insieme alla pagina. */
function render_marquee(RenderCtx $x): string
{
    $f = $x->c['funzioni'];
    if (empty($f['visible'])) {
        return '';
    }
    $items = '';
    $n = 0;
    foreach ($f['cards'] as $card) {
        $title = trim((string) ($card['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $items .= '<span class="mq-item' . ($n % 2 === 1 ? ' mq-it' : '') . '">' . e($title) . '</span>';
        $n++;
    }
    if ($n < 3) {
        return '';
    }
    return '<div class="marquee" aria-hidden="true"><div class="marquee-track">' . str_repeat('<div class="mq-run">' . $items . '</div>', 3) . '</div></div>';
}

/** Fotografie dell'apertura: una sola è un'immagine fissa, più di una diventano uno slider. */
function hero_slider_html(RenderCtx $x, array $s, bool $full = false): string
{
    $keys = [];
    foreach (['photo', 'photo2', 'photo3', 'photo4', 'photo5', 'photo6'] as $f) {
        $k = (string) ($s[$f] ?? '');
        if ($k !== '' && isset(photo_library()[$k]) && !in_array($k, $keys, true)) {
            $keys[] = $k;
        }
    }
    if (!$keys) {
        return '';
    }
    $sizes = $full ? '100vw' : '(min-width: 1000px) 44vw, 92vw';
    $box = $full ? 'hero-bg' : 'hero-photo';
    if (count($keys) === 1) {
        return '<div class="' . $box . '">' . photo_picture($keys[0], (string) ($s['photo_alt'] ?? ''), $x->prefix, $sizes, true) . '</div>';
    }
    $n = count($keys);
    $slides = '';
    $dots = '';
    foreach ($keys as $i => $k) {
        $alt = ($i === 0 && ($s['photo'] ?? '') === $k) ? (string) ($s['photo_alt'] ?? '') : '';
        $slides .= '<div class="hero-slide' . ($i === 0 ? ' is-active' : '') . '" role="group" aria-roledescription="slide" aria-label="Fotografia ' . ($i + 1) . ' di ' . $n . '"'
            . ($i === 0 ? '' : ' aria-hidden="true"') . '>' . photo_picture($k, $alt, $x->prefix, $sizes, $i === 0) . '</div>';
        $dots .= '<button type="button" class="slider-dot" data-slide="' . $i . '" aria-label="Mostra la fotografia ' . ($i + 1) . ' di ' . $n . '"' . ($i === 0 ? ' aria-current="true"' : '') . '></button>';
    }
    return '<div class="' . $box . ' hero-slider" data-slider role="region" aria-roledescription="carousel" aria-label="Fotografie di ChiMangia al lavoro">'
        . $slides
        . '<div class="slider-ui" hidden><button type="button" class="slider-pause" aria-label="Metti in pausa lo scorrimento delle fotografie" aria-pressed="false"><span aria-hidden="true"></span></button>'
        . '<div class="slider-dots">' . $dots . '</div></div></div>';
}

function render_problema(RenderCtx $x): string
{
    $s = $x->c['problema'];
    if (empty($s['visible'])) {
        return '';
    }
    $items = '';
    foreach ($s['cards'] as $card) {
        $items .= '<li class="line-item"><span class="line-icon" aria-hidden="true">' . icon_svg((string) ($card['icon'] ?? ''), 24) . '</span>'
            . '<h3 class="title-medium">' . e($card['title']) . '</h3><p>' . $x->t($card['text']) . '</p></li>';
    }
    $photo = photo_picture((string) ($s['photo'] ?? ''), (string) ($s['photo_alt'] ?? ''), $x->prefix, '100vw');
    return '<section id="problema" class="panel ' . ($photo !== '' ? 'panel-photo' : 'panel-navy') . ' reveal" aria-labelledby="problema-title"' . ($photo !== '' ? ' data-parallax' : '') . '>'
        . ($photo !== '' ? '<div class="panel-bg">' . $photo . '</div><div class="panel-shade" aria-hidden="true"></div>' : '')
        . '<div class="panel-inner problem">'
        . '<div class="problem-top"><h2 id="problema-title" class="title" data-split>' . $x->ti($s['title']) . '</h2>'
        . ($s['closing'] !== '' ? '<p class="problem-closing">' . $x->t($s['closing']) . '</p>' : '') . '</div>'
        . '<ul class="line-grid on-dark reveal" data-stagger>' . $items . '</ul>'
        . '</div></section>';
}

function render_come_funziona(RenderCtx $x): string
{
    $s = $x->c['come_funziona'];
    if (empty($s['visible'])) {
        return '';
    }
    $items = '';
    foreach (array_values($s['cards']) as $i => $card) {
        $items .= '<li class="how-step" data-step="' . $i . '"><span class="how-num" aria-hidden="true">' . ($i + 1) . '</span>'
            . '<div class="how-copy"><h3 class="title-medium"><span class="sr-only">Passaggio ' . ($i + 1) . ': </span><span class="how-title">' . e($card['title']) . '</span></h3>'
            . '<p>' . $x->t($card['text']) . '</p></div></li>';
    }
    $stage = '';
    if ($x->heroCard !== '') {
        $stage = '<div class="how-stage' . ($x->heroPhone === '' ? ' how-solo' : '') . '" data-how-stage><span class="how-disc" aria-hidden="true"></span>'
            . $x->heroPhone . $x->heroCard . '</div>';
    }
    return '<section id="come-funziona" class="edge section" aria-labelledby="cf-title">'
        . '<div class="section-head reveal"><h2 id="cf-title" class="title" data-split>' . $x->ti($s['title']) . '</h2>'
        . ($s['subtitle'] !== '' ? '<p class="sub">' . $x->t($s['subtitle']) . '</p>' : '') . '</div>'
        . '<div class="how' . ($stage === '' ? ' how-nostage' : '') . ' reveal"><ol class="how-steps">' . $items . '</ol>' . $stage . '</div></section>';
}

function render_giornata(RenderCtx $x): string
{
    $s = $x->c['giornata'];
    if (empty($s['visible'])) {
        return '';
    }
    $sizes = '(min-width: 1000px) 30vw, 92vw';
    // Riquadro di un'immagine: fotografia del progetto, illustrazione oppure immagine caricata.
    $frame = static function (string $key, int $media, string $alt) use ($x, $sizes): string {
        if ($media > 0 && ($pic = media_picture($media, $alt, '', $x->prefix)) !== '') {
            return '<div class="photo-frame" data-parallax>' . $pic . '</div>';
        }
        if (str_starts_with($key, 'foto-')) {
            $pic = photo_picture(substr($key, 5), $alt, $x->prefix, $sizes);
            return $pic !== '' ? '<div class="photo-frame" data-parallax>' . $pic . '</div>' : '';
        }
        $svg = illustration_svg($key);
        return $svg !== '' ? '<div class="photo-frame is-illustration" role="img" aria-label="' . e($alt) . '">' . $svg . '</div>' : '';
    };
    $rows = '';
    foreach (array_values($s['cards']) as $i => $card) {
        $first = $frame((string) $card['illustration'], (int) ($card['image'] ?? 0), (string) $card['alt']);
        $second = $frame((string) ($card['illustration2'] ?? ''), 0, '');
        $media = $first . $second;
        $rows .= '<article class="duo' . ($i % 2 === 1 ? ' duo-alt' : '') . ($second === '' || $first === '' ? ' duo-single' : '') . ' reveal">'
            . ($media !== '' ? '<div class="duo-media">' . $media . '</div>' : '')
            . '<div class="duo-text"><div class="duo-head">' . ($card['kicker'] !== '' ? '<p class="label">' . e($card['kicker']) . '</p>' : '')
            . '<h3 class="heading" data-split>' . $x->ti((string) $card['title']) . '</h3></div>'
            . '<div class="duo-body">' . $x->h((string) $card['body']) . '</div></div></article>';
    }
    return '<section id="una-giornata" class="edge section" aria-labelledby="day-title">'
        . '<div class="section-head reveal"><h2 id="day-title" class="title" data-split>' . $x->ti($s['title']) . '</h2>'
        . ($s['intro'] !== '' ? '<p class="sub">' . $x->t($s['intro']) . '</p>' : '') . '</div>'
        . $rows . '</section>';
}

function render_funzioni(RenderCtx $x): string
{
    $s = $x->c['funzioni'];
    if (empty($s['visible'])) {
        return '';
    }
    $items = '';
    foreach ($s['cards'] as $card) {
        $items .= '<li class="feat-card"><span class="feat-icon" aria-hidden="true">' . icon_svg((string) ($card['icon'] ?? ''), 22) . '</span>'
            . '<h3 class="title-medium">' . e($card['title']) . '</h3><p>' . $x->t($card['text']) . '</p></li>';
    }
    return '<section id="funzioni" class="panel panel-cream" aria-labelledby="fn-title"><div class="panel-inner feat">'
        . '<div class="feat-head reveal"><h2 id="fn-title" class="heading" data-split>' . $x->ti($s['title']) . '</h2>'
        . ($s['subtitle'] !== '' ? '<p class="sub">' . $x->t($s['subtitle']) . '</p>' : '') . '</div>'
        . '<ul class="feat-grid reveal" data-stagger>' . $items . '</ul></div></section>';
}

function render_per_chi(RenderCtx $x): string
{
    $s = $x->c['per_chi'];
    if (empty($s['visible'])) {
        return '';
    }
    $items = '';
    foreach (array_values($s['cards']) as $i => $card) {
        $items .= '<li class="tile"><span class="tile-label" aria-hidden="true">' . sprintf('%02d', $i + 1) . '</span>'
            . '<h3 class="title-medium">' . e($card['title']) . '</h3><p>' . $x->t($card['text']) . '</p></li>';
    }
    return '<section id="per-chi" class="edge section" aria-labelledby="pc-title">'
        . '<div class="section-head reveal"><h2 id="pc-title" class="title" data-split>' . $x->ti($s['title']) . '</h2></div>'
        . '<ul class="tile-grid reveal" data-stagger>' . $items . '</ul></section>';
}

/** Riga "Oppure … : due mesi li offriamo noi." */
function price_alt_line(string $prefix, string $priceText, string $struck, string $suffix): string
{
    $suffix = trim($suffix);
    $joiner = ($suffix !== '' && preg_match('/^[:;,.!?]/u', $suffix)) ? '' : ' ';
    return e($prefix) . ' <strong>' . e($priceText) . '</strong>'
        . ($struck !== '' ? ' <s><span class="sr-only">invece di </span>' . e($struck) . '</s>' : '')
        . ($suffix !== '' ? $joiner . e($suffix) : '');
}

function render_prezzi(RenderCtx $x): string
{
    $s = $x->c['prezzi'];
    if (empty($s['visible'])) {
        return '';
    }
    $offer = $x->offer;
    $pm = (string) $s['per_month'];
    $py = (string) $s['per_year'];

    $cards = '';
    foreach (visible_packages($s) as $p) {
        $mCur = (float) ($offer ? $p['monthly_launch'] : $p['monthly_full']);
        $yCur = (float) ($offer ? $p['annual_launch'] : $p['annual_full']);
        $aCur = (float) ($offer ? $p['activation_launch'] : $p['activation_full']);
        $mFull = (float) $p['monthly_full'];
        $yFull = (float) $p['annual_full'];
        $aFull = (float) $p['activation_full'];
        $showM = $offer && $mFull > $mCur;
        $showY = $offer && $yFull > $yCur;
        $showA = $offer && $aFull > $aCur;
        $featured = !empty($p['featured']);

        $monthly = '<div class="pk-price pk-monthly">'
            . ($showM ? '<p class="pk-full"><s><span class="sr-only">Prezzo pieno: </span>' . e(euro($mFull) . ' ' . $pm) . '</s></p>' : '')
            . '<p class="pk-main"><span class="pk-amount">' . e(euro($mCur)) . '</span> <span>' . e($pm) . '</span></p>'
            . '<p class="pk-alt">' . price_alt_line((string) $s['alt_prefix'], euro($yCur) . ' ' . $py, $showY ? euro($yFull) : '', (string) $s['annual_suffix']) . '</p>'
            . '</div>';
        $annual = '<div class="pk-price pk-annual">'
            . ($showY ? '<p class="pk-full"><s><span class="sr-only">Prezzo pieno: </span>' . e(euro($yFull) . ' ' . $py) . '</s></p>' : '')
            . '<p class="pk-main"><span class="pk-amount">' . e(euro($yCur)) . '</span> <span>' . e($py) . '</span></p>'
            . '<p class="pk-alt">' . price_alt_line((string) $s['alt_prefix'], euro($mCur) . ' ' . $pm, $showM ? euro($mFull) : '', (string) $s['monthly_suffix']) . '</p>'
            . '</div>';

        $cards .= '<li class="pk-card' . ($featured ? ' pk-featured' : '') . '">'
            . '<div class="pk-head"><h3>' . e($p['name']) . '</h3>'
            . ($featured && $p['badge'] !== '' ? '<span class="pk-badge">' . e($p['badge']) . '</span>' : '') . '</div>'
            . $monthly . $annual
            . ($p['note'] !== '' ? '<p class="pk-note">' . $x->t($p['note']) . '</p>' : '')
            . '<div class="pk-activation"><p class="pk-act-label">' . e($s['activation_label']) . '</p>'
            . '<p class="pk-act-price"><span>' . e(euro($aCur)) . '</span>'
            . ($showA ? ' <s><span class="sr-only">invece di </span>' . e(euro($aFull)) . '</s>' : '') . '</p></div>'
            . '<a class="btn ' . ($featured ? 'btn-dark' : 'btn-line') . ' btn-block" href="' . e($x->href((string) $p['cta_href'])) . '">'
            . e($p['cta_text']) . '<span class="sr-only"> — ' . e($p['name']) . '</span>' . btn_arrow() . '</a>'
            . '</li>';
    }

    $band = '';
    if ($offer) {
        $band = '<div class="offer-band reveal"><p class="offer-pct">−' . e($x->tokens['{sconto}']) . '</p><p class="offer-text">' . $x->t($s['offer_band']) . '</p></div>';
    }

    $toggle = '<div class="billing-toggle" role="group" aria-label="' . e($s['toggle_label']) . '" hidden>'
        . '<button type="button" class="bt-opt" data-mode="monthly" aria-pressed="true">' . e($s['toggle_monthly']) . '</button>'
        . '<button type="button" class="bt-opt" data-mode="annual" aria-pressed="false">' . e($s['toggle_annual']) . '</button></div>';

    $hotel = '';
    if (!empty($s['hotel_visible'])) {
        $hCur = (float) ($offer ? $s['hotel_launch'] : $s['hotel_full']);
        $hotel = '<p class="hotel-line">' . $x->t($s['hotel_text']) . ' + ' . e(euro($hCur) . ' ' . $pm)
            . ($offer && (float) $s['hotel_full'] > $hCur ? ' <s><span class="sr-only">invece di </span>' . e(euro((float) $s['hotel_full'])) . '</s>' : '') . '.</p>';
    }

    $vat = trim($x->t($s['vat_note']) . ($offer && $s['vat_offer_note'] !== '' ? ' ' . $x->t($s['vat_offer_note']) : ''));

    return '<section id="prezzi" class="panel panel-navy" aria-labelledby="prezzi-title"><div class="panel-inner prices" data-mode="monthly">'
        . '<div class="section-head reveal"><h2 id="prezzi-title" class="title" data-split>' . $x->ti($s['title']) . '</h2>'
        . ($s['intro'] !== '' ? '<p class="sub">' . $x->t($s['intro']) . '</p>' : '') . '</div>'
        . $band
        . '<div class="step-head"><span class="step-num" aria-hidden="true">1</span><div><h3>' . e($s['step1_title']) . '</h3><p>' . $x->t($s['step1_text']) . '</p></div></div>'
        . $toggle
        . '<ul class="pk-grid reveal" data-stagger>' . $cards . '</ul>'
        . '<div class="step-head"><span class="step-num" aria-hidden="true">2</span><div><h3>' . e($s['step2_title']) . '</h3><p>' . $x->t($s['step2_text']) . '</p></div></div>'
        . '<div class="activation-box"><div class="ab-main"><p class="ab-title">' . e($s['activation_title']) . '</p>' . $x->h((string) $s['activation_list']) . '</div>'
        . '<p class="ab-side">' . $x->t($s['activation_text']) . ($offer && $s['activation_offer_text'] !== '' ? ' ' . $x->t($s['activation_offer_text']) : '') . '</p></div>'
        . '<div class="notes">'
        . (trim((string) $s['example']) !== '' ? '<div class="example">' . $x->h((string) $s['example']) . '</div>' : '')
        . (trim((string) $s['trial']) !== '' ? '<div class="trial-box">' . $x->h((string) $s['trial']) . '</div>' : '')
        . '</div>'
        . $hotel
        . ($vat !== '' ? '<p class="vat-note">' . $vat . '</p>' : '')
        . '</div></section>';
}

function render_faq(RenderCtx $x): string
{
    $s = $x->c['faq'];
    if (empty($s['visible'])) {
        return '';
    }
    $items = '';
    $n = 0;
    foreach ($s['items'] as $q) {
        if (empty($q['visible']) || trim((string) $q['question']) === '') {
            continue;
        }
        $n++;
        $items .= '<div class="faq-item"><h3><button type="button" class="faq-q" aria-expanded="true" aria-controls="faq-a' . $n . '" id="faq-q' . $n . '">'
            . '<span>' . e($q['question']) . '</span><span class="faq-icon" aria-hidden="true"></span></button></h3>'
            . '<div class="faq-a" id="faq-a' . $n . '" role="region" aria-labelledby="faq-q' . $n . '">' . $x->h((string) $q['answer']) . '</div></div>';
    }
    if ($items === '') {
        return '';
    }
    $photo = photo_picture((string) ($s['photo'] ?? ''), (string) ($s['photo_alt'] ?? ''), $x->prefix, '(min-width: 1000px) 34vw, 92vw');
    return '<section id="domande" class="edge section faq-section' . ($photo === '' ? ' faq-nophoto' : '') . ' reveal" aria-labelledby="faq-title">'
        . '<div class="faq-col"><h2 id="faq-title" class="heading" data-split>' . $x->ti($s['title']) . '</h2><div class="faq">' . $items . '</div></div>'
        . ($photo !== '' ? '<div class="faq-media photo-frame">' . $photo . '</div>' : '')
        . '</section>';
}

function render_contatti(RenderCtx $x): string
{
    $s = $x->c['contatti'];
    if (empty($s['visible'])) {
        return '';
    }
    $a = $x->c['azienda'];
    $options = '<option value="">Seleziona</option>';
    foreach (preg_split('/\n/', (string) $s['clients_options']) ?: [] as $o) {
        if (trim($o) !== '') {
            $options .= '<option>' . e(trim($o)) . '</option>';
        }
    }
    $privacyLink = '<a href="' . e($x->prefix) . 'privacy/">' . e($s['consent_link_text']) . '</a>';
    $consent = str_replace(e('{privacy}'), $privacyLink, e((string) $s['consent_text']));
    if (!str_contains((string) $s['consent_text'], '{privacy}')) {
        $consent .= ' (' . $privacyLink . ')';
    }

    $field = static function (string $id, string $label, string $input, bool $req): string {
        return '<div class="f-field"><label for="' . $id . '">' . e($label) . ($req ? ' <span aria-hidden="true">*</span><span class="sr-only"> (obbligatorio)</span>' : '') . '</label>'
            . $input . '<p class="f-error" id="' . $id . '-err" hidden></p></div>';
    };

    $form = '<form class="contact-form" method="post" action="' . e($x->prefix) . 'contatti.php" novalidate data-contact-form data-success="' . e($s['success_text']) . '">'
        . '<input type="hidden" name="token" value="' . CM_FORM_TOKEN_PLACEHOLDER . '">'
        . '<div class="hp" aria-hidden="true"><label for="cf-sito">Lascia vuoto questo campo</label><input type="text" id="cf-sito" name="sito" tabindex="-1" autocomplete="off"></div>'
        . '<div class="f-grid">'
        . $field('cf-nome', (string) $s['label_name'], '<input type="text" id="cf-nome" name="nome" required maxlength="120" autocomplete="name" aria-describedby="cf-nome-err">', true)
        . $field('cf-attivita', (string) $s['label_business'], '<input type="text" id="cf-attivita" name="attivita" maxlength="160" autocomplete="organization" aria-describedby="cf-attivita-err">', false)
        . $field('cf-email', (string) $s['label_email'], '<input type="email" id="cf-email" name="email" required maxlength="190" autocomplete="email" aria-describedby="cf-email-err">', true)
        . $field('cf-telefono', (string) $s['label_phone'], '<input type="tel" id="cf-telefono" name="telefono" maxlength="40" autocomplete="tel" aria-describedby="cf-telefono-err">', false)
        . $field('cf-clienti', (string) $s['label_clients'], '<select id="cf-clienti" name="clienti" aria-describedby="cf-clienti-err">' . $options . '</select>', false)
        . '</div>'
        . $field('cf-messaggio', (string) $s['label_message'], '<textarea id="cf-messaggio" name="messaggio" rows="4" maxlength="3000" aria-describedby="cf-messaggio-err"></textarea>', false)
        . '<div class="f-field f-check"><input type="checkbox" id="cf-privacy" name="privacy" value="1" required aria-describedby="cf-privacy-err">'
        . '<label for="cf-privacy">' . $consent . ' <span aria-hidden="true">*</span></label><p class="f-error" id="cf-privacy-err" hidden></p></div>'
        . '<p class="f-required">I campi con * sono obbligatori.</p>'
        . '<button type="submit" class="btn btn-dark">' . e($s['submit_text']) . btn_arrow() . '</button>'
        . '<div class="f-status" role="status" aria-live="polite" tabindex="-1">' . CM_FORM_STATUS_PLACEHOLDER . '</div>'
        . '</form>';

    $links = '';
    if (trim((string) $a['email']) !== '') {
        $links .= '<a class="btn btn-line" href="mailto:' . e($a['email']) . '">' . e($s['email_label']) . ' ' . e($a['email']) . '</a>';
    }
    if (trim((string) $a['phone']) !== '') {
        $tel = preg_replace('/[^0-9+]/', '', (string) $a['phone']);
        $links .= '<a class="btn btn-line" href="tel:' . e($tel) . '">' . e($s['phone_label']) . ' ' . e($a['phone']) . '</a>';
    }

    $cphoto = photo_picture((string) ($s['photo'] ?? ''), (string) ($s['photo_alt'] ?? ''), $x->prefix, '100vw');
    return '<section id="contatti" class="panel ' . ($cphoto !== '' ? 'panel-photo' : 'panel-navy') . ' closing" aria-labelledby="ct-title"' . ($cphoto !== '' ? ' data-parallax' : '') . '>'
        . ($cphoto !== '' ? '<div class="panel-bg">' . $cphoto . '</div><div class="panel-shade" aria-hidden="true"></div>' : '')
        . '<div class="panel-inner ask">'
        . '<div class="ask-head reveal"><h2 id="ct-title" class="title" data-split>' . $x->ti($s['title']) . '</h2>'
        . ($s['text'] !== '' ? '<p class="ask-lead">' . $x->t($s['text']) . '</p>' : '')
        . ($links !== '' ? '<div class="btn-row">' . $links . '</div>' : '')
        . ($x->offer && $s['offer_line'] !== '' ? '<p class="offer-line">' . $x->t($s['offer_line']) . '</p>' : '')
        . '</div><div class="ask-form reveal">' . $form . '</div></div></section>';
}

function render_footer(RenderCtx $x): string
{
    $a = $x->c['azienda'];
    $social = '';
    foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube'] as $k => $label) {
        $url = trim((string) ($a[$k] ?? ''));
        if ($url !== '' && preg_match('#^https://#i', $url)) {
            $social .= '<li><a href="' . e($url) . '" rel="noopener">' . $label . '</a></li>';
        }
    }
    $legal = trim((string) $a['company_name']) . ($a['vat_number'] !== '' ? ' · P. IVA ' . $a['vat_number'] : '');
    return '<footer class="site-footer reveal"><div class="footer-inner">'
        . '<p class="footer-legal">' . e($legal) . ($a['address'] !== '' ? '<br><span>' . e($a['address']) . '</span>' : '') . '</p>'
        . '<ul class="footer-links">' . $social
        . '<li><a href="' . e($x->prefix) . 'privacy/">' . e($a['privacy_label']) . '</a></li>'
        . '<li><a href="' . e($x->prefix) . 'cookie/">' . e($a['cookie_label']) . '</a></li></ul>'
        . '</div><p class="footer-word" aria-hidden="true">ChiMangia</p></footer>';
}

/** Dati strutturati SoftwareApplication; le offerte vengono dai prezzi. */
function structured_data(RenderCtx $x): string
{
    $seo = $x->c['seo'];
    $s = $x->c['prezzi'];
    $offers = [];
    foreach (visible_packages($s) as $p) {
        $price = (float) ($x->offer ? $p['monthly_launch'] : $p['monthly_full']);
        $offer = [
            '@type' => 'Offer',
            'name' => (string) $p['name'],
            'price' => number_format($price, 2, '.', ''),
            'priceCurrency' => 'EUR',
            'priceSpecification' => [
                '@type' => 'UnitPriceSpecification',
                'price' => number_format($price, 2, '.', ''),
                'priceCurrency' => 'EUR',
                'unitCode' => 'MON',
                'valueAddedTaxIncluded' => false,
            ],
            'url' => site_url('/') . '#prezzi',
        ];
        if ($x->offer && trim((string) $s['offer_end']) !== '') {
            $offer['priceValidUntil'] = (string) $s['offer_end'];
        }
        $offers[] = $offer;
    }
    $a = $x->c['azienda'];
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => (string) ($seo['app_name'] ?: 'ChiMangia'),
        'description' => (string) $seo['app_description'],
        'applicationCategory' => (string) ($seo['app_category'] ?: 'BusinessApplication'),
        'operatingSystem' => 'Web',
        'inLanguage' => 'it',
        'url' => site_url('/'),
        'offers' => $offers,
    ];
    if (trim((string) $a['company_name']) !== '') {
        $data['publisher'] = ['@type' => 'Organization', 'name' => (string) $a['company_name']];
        if ($a['vat_number'] !== '') {
            $data['publisher']['vatID'] = 'IT' . preg_replace('/\D/', '', (string) $a['vat_number']);
        }
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    return $json === false ? '' : $json;
}

/* =====================================================================
 * Cache, sitemap, robots
 * ===================================================================== */

/** Il nome contiene la versione: dopo un aggiornamento dei file la pagina vecchia non viene più servita. */
function cache_file(string $name): string
{
    return storage_path('cache/' . $name . '-' . CM_VERSION . '.html');
}

/** Rigenera le pagine in cache e i file SEO dalla versione pubblicata. */
function site_rebuild(): void
{
    $c = content_all('published');
    // Elimina le pagine in cache delle versioni precedenti.
    foreach (glob(storage_path('cache') . '/*.html') ?: [] as $old) {
        if (!str_ends_with($old, '-' . CM_VERSION . '.html')) {
            @unlink($old);
        }
    }
    write_file_atomic(cache_file('index'), render_site($c, ['prefix' => '']));
    foreach (['privacy', 'cookie'] as $area) {
        write_file_atomic(cache_file($area), render_legal($c, $area, ['prefix' => '../']));
    }
    $now = date('Y-m-d');
    $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ([['/', '1.0'], ['privacy/', '0.2'], ['cookie/', '0.2']] as [$path, $prio]) {
        $sitemap .= '  <url><loc>' . e(site_url($path)) . '</loc><lastmod>' . $now . '</lastmod><priority>' . $prio . '</priority></url>' . "\n";
    }
    $sitemap .= '</urlset>' . "\n";
    @file_put_contents(CM_ROOT . '/sitemap.xml', $sitemap, LOCK_EX);

    $base = site_base_path();
    $robots = "User-agent: *\nDisallow: {$base}admin/\nDisallow: {$base}install/\nDisallow: {$base}anteprima.php\nDisallow: {$base}contatti.php\n\nSitemap: " . site_url('sitemap.xml') . "\n";
    @file_put_contents(CM_ROOT . '/robots.txt', $robots, LOCK_EX);
}

/** Serve una pagina dalla cache, generandola se manca. */
function serve_cached(string $name, string $prefix): string
{
    $file = cache_file($name);
    if (!is_file($file)) {
        // Prima visita dopo un aggiornamento dei file: allinea anche il database, poi rigenera.
        if ((string) setting('db_version', '') !== CM_VERSION) {
            require_once CM_APP . '/schema.php';
            cm_migrate();
        }
        if (!is_file($file)) {
            site_rebuild();
        }
    }
    $html = is_file($file) ? (string) file_get_contents($file) : '';
    if ($html === '') {
        $c = content_all('published');
        $html = $name === 'index' ? render_site($c, ['prefix' => $prefix]) : render_legal($c, $name, ['prefix' => $prefix]);
    }
    return $html;
}

/** Inserisce nell'HTML il token del modulo e l'eventuale esito dell'invio senza JavaScript. */
function finalize_public_html(string $html): string
{
    require_once CM_APP . '/csrf.php';
    $status = '';
    $esito = get_param('inviato');
    if ($esito === '1') {
        $ok = preg_match('/data-success="([^"]*)"/', $html, $m) ? $m[1] : 'Richiesta inviata. Grazie!';
        $status = '<p class="f-ok">' . $ok . '</p>';
    } elseif ($esito === '0') {
        $status = '<p class="f-ko">Invio non riuscito: controlla i campi obbligatori e riprova.</p>';
    }
    return str_replace([CM_FORM_TOKEN_PLACEHOLDER, CM_FORM_STATUS_PLACEHOLDER], [e(form_token()), $status], $html);
}

/** File che indica la modalità manutenzione (letto senza interrogare il database). */
function maintenance_flag(): string
{
    return storage_path('maintenance.flag');
}
