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
        'doc'       => '<path d="M4 20V6a2 2 0 0 1 2-2h8l6 6v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z"/><path d="M8 13h8M8 17h5"/>',
        'drop'      => '<path d="M12 3s6 6.4 6 10.4A6 6 0 0 1 6 13.4C6 9.4 12 3 12 3Z"/>',
        'washer'    => '<rect x="3.5" y="6" width="17" height="13" rx="2.5"/><path d="M8 6V4h8v2M8 19v1.5M16 19v1.5"/>',
        'grip'      => '<path d="M8 7h.01M8 12h.01M8 17h.01M16 7h.01M16 12h.01M16 17h.01"/>',
        'key'       => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17.5 5.5l2 2M15 8l2 2"/>',
        'qr'        => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><path d="M14 14h2.5M20.5 14v2.5M14 17.5v3M17.5 20.5h3"/>',
        'people'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 5.2a3.5 3.5 0 0 1 0 5.6M18 20a6.6 6.6 0 0 0-2-4.7"/>',
        'euro'      => '<path d="M17.5 6.5A6.5 6.5 0 0 0 7 11.7 6.5 6.5 0 0 0 17.5 17.5"/><path d="M4.5 10.5h8M4.5 13.5h8"/>',
        'book'      => '<path d="M4 4.5A1.5 1.5 0 0 1 5.5 3H19v18H5.5A1.5 1.5 0 0 1 4 19.5Z"/><path d="M4 17.5h15"/>',
        'chart'     => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    ];

    public static function svg(string $name, int $size = 20, float $stroke = 1.8, string $class = ''): string
    {
        $d = self::PATHS[$name] ?? self::PATHS['info'];
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none"'
             . ' stroke="currentColor" stroke-width="' . $stroke . '" stroke-linecap="round"'
             . ' stroke-linejoin="round" aria-hidden="true"'
             . ($class !== '' ? ' class="' . $class . '"' : '') . '>' . $d . '</svg>';
    }
}
