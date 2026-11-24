<?php
/**
 * Libreria di icone SVG del progetto (tratto 24×24). Le prime vengono dal mockup,
 * le altre sono alternative. Le icone non si caricano dal pannello: si scelgono da qui.
 */
declare(strict_types=1);

defined('CM_ROOT') || exit;

function icon_library(): array
{
    return [
        // Dal mockup
        'telefono' => ['Telefono', '<path d="M22 16.9v3a2 2 0 0 1-2.2 2A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/>'],
        'cestino' => ['Cestino', '<path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/>'],
        'calcolatrice' => ['Calcolatrice', '<rect x="5" y="2" width="14" height="20" rx="2"/><path d="M9 7h6M9 12h1M14 12h1M9 16h1M14 16h1"/>'],
        'posate' => ['Posate', '<path d="M7 2v20M4 2v6a3 3 0 0 0 6 0V2M17 2c-2 2-3 5-3 8h3v12"/>'],
        'etichetta' => ['Etichetta', '<path d="M20 12 12 20 4 12V4h8Z"/><circle cx="8.5" cy="8.5" r="1.5"/>'],
        'cuore' => ['Cuore', '<path d="M12 21C6 17 3 13 3 9a4.5 4.5 0 0 1 9-1 4.5 4.5 0 0 1 9 1c0 4-3 8-9 12Z"/>'],
        'orologio' => ['Orologio', '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'],
        'edificio' => ['Edificio', '<path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/>'],
        'campanella' => ['Campanella', '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>'],
        'matita' => ['Matita', '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>'],
        'stampante' => ['Stampante', '<path d="M6 9V2h12v7"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M6 14h12v8H6z"/>'],
        'smartphone' => ['Smartphone', '<rect x="6" y="2" width="12" height="20" rx="3"/><path d="M11 18h2"/>'],
        // Alternative
        'calendario' => ['Calendario', '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>'],
        'spunta' => ['Spunta', '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>'],
        'persone' => ['Persone', '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6"/>'],
        'grafico' => ['Grafico', '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>'],
        'messaggio' => ['Messaggio', '<path d="M21 12a8 8 0 0 1-11.6 7.1L3 21l1.9-6.4A8 8 0 1 1 21 12Z"/>'],
        'email' => ['Email', '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m3 6 9 7 9-7"/>'],
        'scudo' => ['Scudo', '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>'],
        'foglia' => ['Foglia', '<path d="M11 20A7 7 0 0 1 4 13c0-6 6-10 16-10 0 10-4 16-10 16"/><path d="M4 21c3-6 7-9 11-11"/>'],
        'stella' => ['Stella', '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1Z"/>'],
        'tazza' => ['Tazza', '<path d="M3 8h14v6a6 6 0 0 1-6 6H9a6 6 0 0 1-6-6Z"/><path d="M17 10h1.5a2.5 2.5 0 0 1 0 5H17M7 2v3M11 2v3"/>'],
        'lista' => ['Elenco', '<path d="M9 6h12M9 12h12M9 18h12"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>'],
        'euro' => ['Euro', '<path d="M18 6.5A7 7 0 1 0 18 17.5"/><path d="M4 10h9M4 14h9"/>'],
        'lucchetto' => ['Lucchetto', '<rect x="4" y="11" width="16" height="11" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>'],
        'camion' => ['Consegna', '<path d="M1 4h13v12H1zM14 9h4l3 3v4h-7"/><circle cx="5.5" cy="18.5" r="2"/><circle cx="17.5" cy="18.5" r="2"/>'],
        'casa' => ['Casa', '<path d="m3 11 9-8 9 8"/><path d="M5 10v11h14V10"/><path d="M10 21v-6h4v6"/>'],
        'lampadina' => ['Idea', '<path d="M9 18h6M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7V16h8v-1.3A7 7 0 0 0 12 2Z"/>'],
    ];
}

/** SVG di un'icona. $stroke: colore del tratto. */
function icon_svg(string $name, int $size = 26, string $stroke = 'currentColor'): string
{
    $lib = icon_library();
    $paths = $lib[$name][1] ?? $lib['spunta'][1];
    return '<svg aria-hidden="true" focusable="false" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="' . e($stroke)
        . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
}

/** Illustrazioni della sezione "Una giornata" (file in assets/img/illustrazioni). */
function illustration_library(): array
{
    return [
        'menu-telefono' => 'Il menù pubblicato dal telefono',
        'tavolata' => 'Una tavolata con i piatti prenotati',
        'riepilogo' => 'Il riepilogo mensile dei pasti',
    ];
}

function illustration_svg(string $name): string
{
    if (!isset(illustration_library()[$name])) {
        return '';
    }
    $f = CM_ROOT . '/assets/img/illustrazioni/' . $name . '.svg';
    return is_file($f) ? (string) file_get_contents($f) : '';
}
