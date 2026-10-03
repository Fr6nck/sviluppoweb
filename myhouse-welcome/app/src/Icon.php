<?php
namespace MHW;

/**
 * Le icone del progetto, disegnate a tratto come sulle tavole: un solo
 * spessore, estremità tonde, sempre del colore del testo che accompagnano.
 * Stanno qui e non in un file esterno per non aggiungere una richiesta di rete
 * a una guida che spesso si apre con una tacca di segnale.
 */
final class Icon
{
    private const PATHS = [
        'home'      => '<path d="M3 21V8l9-5 9 5v13"/><path d="M9 21v-7h6v7"/>',
        // Il pannello: la barra laterale.
        'grid'      => '<rect x="3.5" y="3.5" width="7" height="7" rx="2"/><rect x="13.5" y="3.5" width="7" height="7" rx="2"/><rect x="3.5" y="13.5" width="7" height="7" rx="2"/><rect x="13.5" y="13.5" width="7" height="7" rx="2"/>',
        'card'      => '<rect x="3" y="5.5" width="18" height="13" rx="2.5"/><path d="M3 10h18M7 15h3"/>',
        'palette'   => '<path d="M12 3.5a8.5 8.5 0 1 0 0 17c1.2 0 1.8-.8 1.8-1.7 0-1.2-1-1.5-1-2.6 0-1 .8-1.7 1.8-1.7h2.2a3.7 3.7 0 0 0 3.7-3.7C20.5 6.6 16.7 3.5 12 3.5Z"/><circle cx="7.8" cy="11" r="1.1"/><circle cx="10.5" cy="7.3" r="1.1"/><circle cx="15" cy="7.5" r="1.1"/>',
        'sliders'   => '<path d="M4 7h10M18 7h2M4 17h4M12 17h8"/><circle cx="16" cy="7" r="2"/><circle cx="10" cy="17" r="2"/>',
        'logout'    => '<path d="M14 4.5h3.5a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H14"/><path d="M10 8l-4 4 4 4M6 12h9"/>',
        'layers'    => '<path d="M12 3.5 21 8l-9 4.5L3 8z"/><path d="m3 12 9 4.5 9-4.5"/><path d="m3 16 9 4.5 9-4.5"/>',
        'star'      => '<path d="m12 3.8 2.5 5.2 5.7.8-4.1 4 1 5.6-5.1-2.7-5.1 2.7 1-5.6-4.1-4 5.7-.8z"/>',
        'list'      => '<path d="M9 6.5h11M9 12h11M9 17.5h11"/><circle cx="4.8" cy="6.5" r="1"/><circle cx="4.8" cy="12" r="1"/><circle cx="4.8" cy="17.5" r="1"/>',
        'pulse'     => '<path d="M3 12h4l2.5-6 5 12 2.5-6H21"/>',
        'external'  => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4"/>',
        'close'     => '<path d="M6 6l12 12M18 6 6 18"/>',
        'bag'       => '<path d="M5 8h14l-1.2 12H6.2L5 8Z"/><path d="M9 10.5V6.5a3 3 0 0 1 6 0v4"/>',
        'wifi'      => '<path d="M2.5 9a15 15 0 0 1 19 0"/><path d="M6 12.5a10 10 0 0 1 12 0"/><path d="M9.5 16a5 5 0 0 1 5 0"/><circle cx="12" cy="19.5" r="1" fill="currentColor" stroke="none"/>',
        'fork'      => '<path d="M6 3v8a2.5 2.5 0 0 0 5 0V3"/><path d="M8.5 11v10"/><path d="M17 3c-1.7 1.5-2 4-2 6.5 0 1.4.7 2.5 2 2.5v9"/>',
        'pin'       => '<path d="M12 21s-7-4.7-7-10a7 7 0 0 1 14 0c0 5.3-7 10-7 10Z"/><circle cx="12" cy="11" r="2.5"/>',
        'arrow'     => '<path d="M5 12h13M12 5l7 7-7 7"/>',
        'back'      => '<path d="M15 5l-7 7 7 7"/>',
        'chevron'   => '<path d="M9 5l7 7-7 7"/>',
        'copy'      => '<rect x="9" y="9" width="11" height="11" rx="2.5"/><path d="M15 5.5A2.5 2.5 0 0 0 12.5 3h-7A2.5 2.5 0 0 0 3 5.5v7A2.5 2.5 0 0 0 5.5 15"/>',
        'globe'     => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 2.5 15.3 0 18M12 3c-2.5 2.7-2.5 15.3 0 18"/>',
        'search'    => '<circle cx="11" cy="11" r="7"/><path d="M16.5 16.5L21 21"/>',
        'eye'       => '<path d="M2 12s3.8-6.5 10-6.5S22 12 22 12s-3.8 6.5-10 6.5S2 12 2 12Z"/><circle cx="12" cy="12" r="2.6"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'check'     => '<path d="M4.5 12.5l5 5 10-11"/>',
        'info'      => '<circle cx="12" cy="12" r="9"/><path d="M12 8h.01M12 11.5v5"/>',
        'warning'   => '<path d="M12 3l9 16H3l9-16Z"/><path d="M12 10v4M12 16.5h.01"/>',
        'phone'     => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a1.5 1.5 0 0 1-1.7 1.5A16 16 0 0 1 3.5 5.7 1.5 1.5 0 0 1 5 4Z"/>',
        'whatsapp'  => '<path d="M21 11.5a8.5 8.5 0 0 1-12.6 7.4L3 20.5l1.7-5.2A8.5 8.5 0 1 1 21 11.5Z"/>',
        'moon'      => '<path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5Z"/>',
        'sun'       => '<circle cx="12" cy="12" r="4.2"/><path d="M12 2.5v2.2M12 19.3v2.2M4.9 4.9l1.6 1.6M17.5 17.5l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.9 19.1l1.6-1.6M17.5 6.5l1.6-1.6"/>',
        'doc'       => '<path d="M4 20V6a2 2 0 0 1 2-2h8l6 6v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z"/><path d="M8 13h8M8 17h5"/>',
        'drop'      => '<path d="M12 3s6 6.4 6 10.4A6 6 0 0 1 6 13.4C6 9.4 12 3 12 3Z"/>',
        'washer'    => '<rect x="3.5" y="6" width="17" height="13" rx="2.5"/><path d="M8 6V4h8v2M8 19v1.5M16 19v1.5"/>',
        'menu'      => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'grip'      => '<path d="M8 7h.01M8 12h.01M8 17h.01M16 7h.01M16 12h.01M16 17h.01"/>',
        'key'       => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17.5 5.5l2 2M15 8l2 2"/>',
        'qr'        => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><path d="M14 14h2.5M20.5 14v2.5M14 17.5v3M17.5 20.5h3"/>',
        'people'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 5.2a3.5 3.5 0 0 1 0 5.6M18 20a6.6 6.6 0 0 0-2-4.7"/>',
        'euro'      => '<path d="M17.5 6.5A6.5 6.5 0 0 0 7 11.7 6.5 6.5 0 0 0 17.5 17.5"/><path d="M4.5 10.5h8M4.5 13.5h8"/>',
        'book'      => '<path d="M4 4.5A1.5 1.5 0 0 1 5.5 3H19v18H5.5A1.5 1.5 0 0 1 4 19.5Z"/><path d="M4 17.5h15"/>',
        'chart'     => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'bin'       => '<path d="M4.5 7h15M9.5 7V4.5h5V7M6.5 7l1 13h9l1-13"/><path d="M10.5 11v5.5M13.5 11v5.5"/>',
        'clock'     => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'message'   => '<path d="M20 12a7.5 7.5 0 0 1-11 6.6L4 20l1.4-4.6A7.5 7.5 0 1 1 20 12Z"/>',
        'bus'       => '<rect x="4.5" y="3.5" width="15" height="14" rx="3"/><path d="M4.5 11h15M8 17.5v2.5M16 17.5v2.5"/><path d="M8 14.3h.01M16 14.3h.01"/><path d="M8.5 6.5h7"/>',
        'monument'  => '<path d="M3.5 20.5h17M5 17.5h14"/><path d="M6.5 17.5v-7M10 17.5v-7M14 17.5v-7M17.5 17.5v-7"/><path d="M4 10.5h16L12 4.5 4 10.5Z"/>',
        'compass'   => '<circle cx="12" cy="12" r="9"/><path d="M15.5 8.5l-2 5-5 2 2-5 5-2Z"/>',
        // Dotazioni, regole e mezzi (dalla fase 3): stesso tratto delle altre.
        'dryer'     => '<rect x="3.5" y="3.5" width="17" height="17" rx="2.5"/><circle cx="12" cy="13" r="4.5"/><path d="M7 6.5h.01M10 6.5h.01"/><path d="M10 12c1 1 3-1 4 0"/>',
        'dishwasher'=> '<rect x="4" y="3" width="16" height="18" rx="2.5"/><path d="M4 8h16"/><path d="M7.5 5.5h.01M10.5 5.5h.01"/><path d="M8 13v4M12 12v5M16 13v4"/>',
        'hairdryer' => '<path d="M14 6.5a5 5 0 1 0 0 10H9"/><path d="M14 6.5h6.5v6H14"/><path d="M9 16.5l-1.5 4.5h3L12 16.5"/>',
        'iron'      => '<path d="M3 17.5h17V15a6 6 0 0 0-6-6H8"/><path d="M8 9V6.5h9"/><path d="M7 13.5h.01M10.5 13.5h.01"/>',
        'crib'      => '<path d="M4 4v16M20 4v16M4 9h16M4 17h16"/><path d="M8 9v8M12 9v8M16 9v8"/>',
        'highchair' => '<path d="M7 3v8h10V3"/><path d="M5.5 11h13"/><path d="M8 11l-2 10M16 11l2 10M7 16.5h10"/>',
        'snow'      => '<path d="M12 2.5v19M3.8 7.2l16.4 9.6M20.2 7.2L3.8 16.8"/><path d="M9.5 4l2.5 2.5L14.5 4M9.5 20l2.5-2.5 2.5 2.5"/>',
        'flame'     => '<path d="M12 21a6 6 0 0 0 6-6c0-4-3-6-4-10-2 2-3 4-3 6-1-1-1.5-2-1.5-3C7.5 10 6 12.5 6 15a6 6 0 0 0 6 6Z"/>',
        'tv'        => '<rect x="3" y="5.5" width="18" height="12" rx="2"/><path d="M8.5 21h7M9 2.5l3 3 3-3"/>',
        'coffee'    => '<path d="M4.5 9h12v5.5a5 5 0 0 1-5 5h-2a5 5 0 0 1-5-5Z"/><path d="M16.5 10.5h1.5a2.5 2.5 0 0 1 0 5h-1.8"/><path d="M8.5 3c-1 1.2 1 2.3 0 3.5M12.5 3c-1 1.2 1 2.3 0 3.5"/>',
        'grill'     => '<path d="M4 10h16a8 8 0 0 1-16 0Z"/><path d="M8 17.5L6 21.5M16 17.5l2 4M12 18v3.5"/><path d="M9 3c-.8 1.2.8 2.3 0 3.5M15 3c-.8 1.2.8 2.3 0 3.5"/>',
        'smoke'     => '<rect x="2.5" y="13.5" width="15" height="4" rx="1"/><path d="M14 13.5v4M20 13.5v4M20 10c0-2-2-2-2-4M17 10.5c0-1.5-1.5-1.5-1.5-3"/>',
        'paw'       => '<circle cx="7" cy="9" r="1.8"/><circle cx="11" cy="5.8" r="1.8"/><circle cx="15.5" cy="6.5" r="1.8"/><circle cx="18" cy="11" r="1.8"/><path d="M8 17.5c0-3 2.2-5.5 4.5-5.5s4 2 4 4.3c0 2.4-1.8 3.2-4 3.2s-4.5.6-4.5-2Z"/>',
        'music'     => '<path d="M9 18V5.5l11-2.5v12.5"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="17.5" cy="15.5" r="2.5"/>',
        'train'     => '<rect x="5" y="3" width="14" height="13" rx="3"/><path d="M5 10h14M9 16l-2.5 5M15 16l2.5 5M8.5 19h7"/><path d="M8.5 13h.01M15.5 13h.01"/>',
        'plane'     => '<path d="M10.5 13.5l-6 2v-2L10.5 9V4.5a1.5 1.5 0 0 1 3 0V9l6 4.5v2l-6-2v4l2 1.5v1.5L12 19.5 8.5 20.5V19l2-1.5Z"/>',
        'lock'      => '<rect x="5" y="10.5" width="14" height="10" rx="2.5"/><path d="M8.5 10.5V7.5a3.5 3.5 0 0 1 7 0v3"/><path d="M12 14.5v2"/>',
        'ban'       => '<circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/>',
        'car'       => '<path d="M5 16.5V12l1.8-4.6A2 2 0 0 1 8.7 6h6.6a2 2 0 0 1 1.9 1.4L19 12v4.5"/><path d="M4 16.5h16M5 12h14"/><circle cx="8" cy="16.5" r="1.6"/><circle cx="16" cy="16.5" r="1.6"/>',
    ];

    /**
     * Il simbolo del marchio: l'arco di una porta e il punto della maniglia.
     * L'arco prende il colore del testo, il punto il terracotta del tema.
     */
    public static function brand(int $size = 26, string $class = ''): string
    {
        return '<svg class="simbolo' . ($class !== '' ? ' ' . $class : '') . '" width="' . $size . '" height="' . $size . '"'
             . ' viewBox="0 0 32 32" fill="none" aria-hidden="true">'
             . '<path d="M6 28V14a10 10 0 0 1 20 0v14" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>'
             . '<circle class="simbolo__punto" cx="20.5" cy="19" r="2" fill="#b4451f"/></svg>';
    }

    /** L'icona di una dotazione (Servizi). */
    public static function amenita(string $chiave): string
    {
        return ['washer' => 'washer', 'dryer' => 'dryer', 'dishwasher' => 'dishwasher', 'hairdryer' => 'hairdryer', 'iron' => 'iron',
                'crib' => 'crib', 'highchair' => 'highchair', 'ac' => 'snow', 'heating' => 'flame', 'tv' => 'tv',
                'coffee' => 'coffee', 'bbq' => 'grill'][$chiave] ?? 'check';
    }

    public static function svg(string $name, int $size = 20, float $stroke = 1.8, string $class = ''): string
    {
        $d = self::PATHS[$name] ?? self::PATHS['info'];
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none"'
             . ' stroke="currentColor" stroke-width="' . $stroke . '" stroke-linecap="round"'
             . ' stroke-linejoin="round" aria-hidden="true"'
             . ($class !== '' ? ' class="' . $class . '"' : '') . '>' . $d . '</svg>';
    }
}
