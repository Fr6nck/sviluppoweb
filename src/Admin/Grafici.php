<?php

declare(strict_types=1);

namespace ArcoDelVento\Admin;

/**
 * I grafici della bacheca, disegnati sul server in SVG.
 *
 * Niente libreria e niente JavaScript: la politica di sicurezza del sito non
 * ammette script da fuori, e un grafico di quindici punti non ne ha bisogno.
 * Le misure stanno negli attributi dell'SVG, non in «style», che la stessa
 * politica non ammette.
 */
final class Grafici
{
    /**
     * La linea piccola delle schede: area sfumata, linea, un punto sugli
     * ultimi valori.
     *
     * @param list<int|float> $valori
     */
    public static function scintilla(array $valori, string $classe, string $id, int $w = 120, int $h = 48): string
    {
        if (count($valori) < 2) {
            $valori = [0, 0];
        }
        $max = max(1, max($valori));
        $n   = count($valori);
        $pad = 5;
        $pt  = [];
        foreach ($valori as $i => $v) {
            $pt[] = [round($pad + $i * ($w - 2 * $pad) / ($n - 1), 1), round($h - $pad - $v / $max * ($h - 2 * $pad), 1)];
        }
        $linea = 'M' . implode(' L', array_map(static fn (array $p): string => $p[0] . ' ' . $p[1], $pt));
        $area  = $linea . ' L' . end($pt)[0] . ' ' . $h . ' L' . $pt[0][0] . ' ' . $h . ' Z';
        $punti = '';
        foreach ([intdiv($n, 2), $n - 1] as $i) {
            $punti .= sprintf('<circle cx="%s" cy="%s" r="3.5" class="adm-grafico__punto"/>', $pt[$i][0], $pt[$i][1]);
        }

        return sprintf(
            '<svg class="adm-scintilla %1$s" viewBox="0 0 %2$d %3$d" width="%2$d" height="%3$d" aria-hidden="true" focusable="false">'
            . '<defs><linearGradient id="%4$s" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="currentColor" stop-opacity=".22"/>'
            . '<stop offset="1" stop-color="currentColor" stop-opacity="0"/></linearGradient></defs>'
            . '<path d="%5$s" fill="url(#%4$s)"/><path d="%6$s" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"/>%7$s</svg>',
            htmlspecialchars($classe, ENT_QUOTES),
            $w,
            $h,
            htmlspecialchars($id, ENT_QUOTES),
            $area,
            $linea,
            $punti
        );
    }

    /**
     * La linea larga, con la griglia tratteggiata: quante camere sono
     * occupate notte per notte.
     *
     * @param list<int> $valori
     */
    public static function linea(array $valori, int $massimo, string $id): string
    {
        $w = 300;
        $h = 120;
        $n = max(2, count($valori));
        $max = max(1, $massimo);
        $griglia = '';
        for ($i = 0; $i <= 4; $i++) {
            $y = round(8 + $i * ($h - 16) / 4, 1);
            $griglia .= sprintf('<line x1="0" x2="%d" y1="%s" y2="%s" class="adm-grafico__griglia"/>', $w, $y, $y);
        }
        $pt = [];
        foreach (array_values($valori) as $i => $v) {
            $pt[] = [round($i * $w / ($n - 1), 1), round($h - 8 - min($v, $max) / $max * ($h - 16), 1)];
        }
        // A gradini: una camera è occupata o no per tutta la notte.
        $d = 'M' . $pt[0][0] . ' ' . $pt[0][1];
        for ($i = 1, $c = count($pt); $i < $c; $i++) {
            $d .= ' L' . $pt[$i][0] . ' ' . $pt[$i - 1][1] . ' L' . $pt[$i][0] . ' ' . $pt[$i][1];
        }
        $area = $d . ' L' . end($pt)[0] . ' ' . $h . ' L0 ' . $h . ' Z';

        return sprintf(
            '<svg class="adm-linea" viewBox="0 0 %1$d %2$d" preserveAspectRatio="none" aria-hidden="true" focusable="false">'
            . '<defs><linearGradient id="%3$s" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="currentColor" stop-opacity=".18"/>'
            . '<stop offset="1" stop-color="currentColor" stop-opacity="0"/></linearGradient></defs>%4$s'
            . '<path d="%5$s" fill="url(#%3$s)"/><path d="%6$s" fill="none" stroke="currentColor" stroke-width="1.8" vector-effect="non-scaling-stroke"/></svg>',
            $w,
            $h,
            htmlspecialchars($id, ENT_QUOTES),
            $griglia,
            $area,
            $d
        );
    }

    /**
     * L'altezza di una colonna come classe, a passi del 2%: le altezze non si
     * possono scrivere in «style», e cinquanta classi pesano meno di uno script.
     */
    public static function altezza(int $valore, int $massimo): string
    {
        if ($valore <= 0 || $massimo <= 0) {
            return 'adm-h0';
        }

        return 'adm-h' . max(4, (int) (round($valore / $massimo * 50) * 2));
    }

    /** Il massimo «tondo» per l'asse: 4, 8, 10, 20, 50… */
    public static function tondo(int $valore): int
    {
        foreach ([4, 8, 10, 20, 40, 50, 100, 200, 500, 1000] as $t) {
            if ($valore <= $t) {
                return $t;
            }
        }

        return (int) (ceil($valore / 1000) * 1000);
    }
}
