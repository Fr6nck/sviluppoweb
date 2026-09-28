<?php
namespace MHW;

/**
 * Le palette della guida. Niente selettore di colore libero: sei combinazioni
 * curate, ognuna in due varianti — testo scuro su fondo chiaro, testo chiaro
 * su fondo scuro.
 *
 * Una variante si può scegliere SOLO se tutte le coppie che contano superano
 * la soglia AA (4.5:1 per il testo). Il controllo lo fa il codice, non chi
 * disegna: se un giorno qualcuno ritocca un colore e lo rende illeggibile,
 * quella variante sparisce dalle scelte invece di finire sui telefoni degli ospiti.
 */
final class Palette
{
    public const DEFAULT = 'terracotta';

    /** tile_ink: il colore del testo sopra ciascuno dei quattro riquadri. */
    private const P = [
        'terracotta' => [
            'label' => 'Terracotta',
            'scuro'  => ['bg' => '#faf5ec', 'surface' => '#ffffff', 'sunk' => '#f1e9db', 'ink' => '#231b12', 'muted' => '#6a5b48', 'line' => '#e2d7c4', 'accent' => '#b4451f', 'on_accent' => '#fff8f2'],
            'chiaro' => ['bg' => '#17130d', 'surface' => '#2e261c', 'sunk' => '#201a13', 'ink' => '#f6f0e5', 'muted' => '#b6a891', 'line' => '#3d3327', 'accent' => '#ee7a4a', 'on_accent' => '#1c1008'],
            'tiles' => ['#b4451f', '#1c5a78', '#1f6b3f', '#b07d0c'], 'tile_ink' => ['#fff8f2', '#fff8f2', '#fff8f2', '#231b12'],
        ],
        'sabbia' => [
            'label' => 'Sabbia',
            'scuro'  => ['bg' => '#f6f0e4', 'surface' => '#fffdf8', 'sunk' => '#ece3d2', 'ink' => '#2b2419', 'muted' => '#645945', 'line' => '#e0d5c0', 'accent' => '#7a5c24', 'on_accent' => '#fffaf0'],
            'chiaro' => ['bg' => '#1a1712', 'surface' => '#2b261e', 'sunk' => '#221e18', 'ink' => '#f3ecdf', 'muted' => '#b9ad98', 'line' => '#3b3428', 'accent' => '#d8b77a', 'on_accent' => '#1a1712'],
            'tiles' => ['#7a5c24', '#56614f', '#7a5a44', '#cdb88e'], 'tile_ink' => ['#fffaf0', '#fffaf0', '#fffaf0', '#2b2419'],
        ],
        'oliva' => [
            'label' => 'Oliva',
            'scuro'  => ['bg' => '#f4f3ea', 'surface' => '#fffffa', 'sunk' => '#e9e8da', 'ink' => '#1f2418', 'muted' => '#56604a', 'line' => '#dcdcc9', 'accent' => '#4d5d33', 'on_accent' => '#fbfdf5'],
            'chiaro' => ['bg' => '#141710', 'surface' => '#242a1c', 'sunk' => '#1c2116', 'ink' => '#eef0e4', 'muted' => '#adb39c', 'line' => '#343c28', 'accent' => '#a9bf7e', 'on_accent' => '#141710'],
            'tiles' => ['#4d5d33', '#735f33', '#3a5650', '#b3b77c'], 'tile_ink' => ['#fbfdf5', '#fbfdf5', '#fbfdf5', '#1f2418'],
        ],
        'mare' => [
            'label' => 'Mare',
            'scuro'  => ['bg' => '#f1f5f6', 'surface' => '#ffffff', 'sunk' => '#e3ebee', 'ink' => '#14232b', 'muted' => '#4c5e67', 'line' => '#d3dfe3', 'accent' => '#1c5a78', 'on_accent' => '#f5fbfd'],
            'chiaro' => ['bg' => '#0f171b', 'surface' => '#1b2730', 'sunk' => '#152026', 'ink' => '#e9f1f4', 'muted' => '#9fb3bc', 'line' => '#2a3a44', 'accent' => '#74bcd9', 'on_accent' => '#0f171b'],
            'tiles' => ['#1c5a78', '#2a6b69', '#3d5a80', '#d9a35c'], 'tile_ink' => ['#f5fbfd', '#f5fbfd', '#f5fbfd', '#14232b'],
        ],
        'borgogna' => [
            'label' => 'Borgogna',
            'scuro'  => ['bg' => '#f8f1ee', 'surface' => '#fffbfa', 'sunk' => '#efe3de', 'ink' => '#2a1618', 'muted' => '#684c4e', 'line' => '#e6d6d1', 'accent' => '#7b2331', 'on_accent' => '#fff6f5'],
            'chiaro' => ['bg' => '#1a1012', 'surface' => '#2c1c1f', 'sunk' => '#221619', 'ink' => '#f5e9e8', 'muted' => '#c2a6a6', 'line' => '#3e2a2d', 'accent' => '#e08a95', 'on_accent' => '#1a1012'],
            'tiles' => ['#7b2331', '#8f4125', '#4b3a5c', '#cfa566'], 'tile_ink' => ['#fff6f5', '#fff6f5', '#fff6f5', '#2a1618'],
        ],
        'grafite' => [
            'label' => 'Grafite',
            'scuro'  => ['bg' => '#f3f3f1', 'surface' => '#ffffff', 'sunk' => '#e7e7e3', 'ink' => '#1b1c1e', 'muted' => '#55575c', 'line' => '#dadad6', 'accent' => '#2f3338', 'on_accent' => '#f6f6f4'],
            'chiaro' => ['bg' => '#121315', 'surface' => '#202226', 'sunk' => '#191a1d', 'ink' => '#eeeeec', 'muted' => '#a9abaf', 'line' => '#303338', 'accent' => '#d9d6cf', 'on_accent' => '#121315'],
            'tiles' => ['#2f3338', '#4c5560', '#6a5f55', '#bcb7ac'], 'tile_ink' => ['#f6f6f4', '#f6f6f4', '#f6f6f4', '#1b1c1e'],
        ],
    ];

    /** @return array<string,string> codice → nome */
    public static function all(): array
    {
        return array_map(fn($p) => $p['label'], self::P);
    }

    public static function exists(string $code): bool { return isset(self::P[$code]); }

    public static function get(string $code): array { return self::P[$code] ?? self::P[self::DEFAULT]; }

    // ------------------------------------------------------------- contrasto

    public static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $c = array_map(fn($i) => hexdec(substr($hex, $i, 2)) / 255, [0, 2, 4]);
        $c = array_map(fn($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $c);
        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    public static function contrast(string $a, string $b): float
    {
        $l1 = self::luminance($a); $l2 = self::luminance($b);
        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    /**
     * Le coppie che contano in una variante, con il loro rapporto.
     * @return array<string,float>
     */
    public static function pairs(string $code, string $tone): array
    {
        $p = self::get($code); $v = $p[$tone];
        $out = [
            'testo/fondo'       => self::contrast($v['ink'], $v['bg']),
            'testo/scheda'      => self::contrast($v['ink'], $v['surface']),
            'testo/incassato'   => self::contrast($v['ink'], $v['sunk']),
            'secondario/fondo'  => self::contrast($v['muted'], $v['bg']),
            'secondario/scheda' => self::contrast($v['muted'], $v['surface']),
            'accento/fondo'     => self::contrast($v['accent'], $v['bg']),
            'bottone'           => self::contrast($v['on_accent'], $v['accent']),
        ];
        foreach ($p['tiles'] as $i => $t) $out['riquadro ' . ($i + 1)] = self::contrast($p['tile_ink'][$i], $t);
        return $out;
    }

    /** Una variante è leggibile se ogni coppia arriva a 4.5:1. */
    public static function readable(string $code, string $tone): bool
    {
        if (!isset(self::P[$code]) || !in_array($tone, ['scuro', 'chiaro'], true)) return false;
        return min(self::pairs($code, $tone)) >= 4.5;
    }

    /** Le varianti scegliibili di una palette. @return string[] */
    public static function tones(string $code): array
    {
        return array_values(array_filter(['scuro', 'chiaro'], fn($t) => self::readable($code, $t)));
    }

    /**
     * Il CSS che veste la guida con questa palette. `$tone` è la scelta
     * dell'host; l'ospite può passare all'altra variante solo se anche quella
     * è leggibile. La pagina notturna del Wi-Fi usa sempre la variante chiara-su-scura.
     */
    public static function css(string $code): string
    {
        $p = self::get($code);
        $blocco = function (array $v) use ($p): string {
            $r = "--paper:{$v['bg']};--raised:{$v['surface']};--sunk:{$v['sunk']};--ink:{$v['ink']};"
               . "--muted:{$v['muted']};--line:{$v['line']};--line-strong:{$v['muted']};"
               . "--terracotta:{$v['accent']};--accent:{$v['accent']};--accent-hover:{$v['accent']};--on-dark:{$v['on_accent']};"
               . "--terracotta-soft:color-mix(in srgb,{$v['accent']} 14%,{$v['bg']});"
               . "--inverse-bg:{$v['ink']};--inverse-fg:{$v['bg']};--inverse-muted:{$v['line']};"
               . "--vetro:color-mix(in srgb,{$v['bg']} 40%,transparent);";
            foreach (['terracotta', 'sea', 'pine', 'ochre'] as $i => $n) $r .= "--tile-$n:{$p['tiles'][$i]};";
            $r .= "--tile-ink:{$p['tile_ink'][0]};--tile-ink-scuro:{$p['tile_ink'][3]};";
            return $r;
        };
        $css = ':root[data-theme="chiaro"]{' . $blocco($p['scuro']) . 'color-scheme:light}';
        $css .= ':root[data-theme="scuro"],.night{' . $blocco($p['chiaro']) . 'color-scheme:dark}';
        return $css;
    }

    /** Il tema della pagina che corrisponde alla scelta dell'host. */
    public static function themeFor(string $tone): string
    {
        // "testo scuro" vuol dire fondo chiaro, cioè il tema chiaro.
        return $tone === 'chiaro' ? 'scuro' : 'chiaro';
    }
}
