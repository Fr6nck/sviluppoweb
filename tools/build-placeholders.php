<?php
/**
 * Arco del Vento — genera i segnaposto delle fotografie mancanti.
 *
 *     php tools/build-placeholders.php
 *
 * Perché esistono: nel materiale consegnato non c'è nessuna fotografia delle
 * camere di Arco del Vento con una provenienza dichiarata. Mettere sul sito
 * l'interno di casa d'altri sotto il nome di una camera è una bugia che
 * l'ospite scopre aprendo la porta — e, prima ancora, è pratica commerciale
 * ingannevole. Quindi: nessuna finta fotografia, e un segnaposto che dice
 * quello che è.
 *
 * Sono disegnati con i colori del marchio e tagliati ai rapporti del design
 * system. La fotografia vera prende lo stesso nome e lo stesso rapporto, e
 * nel sito non cambia nient'altro.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$dir  = $root . '/public/assets/img/demo';
@mkdir($dir, 0775, true);

/** I formati richiesti dal design system, con le larghezze servite. */
$formats = [
    '4x3'  => [800, 600],
    '3x2'  => [900, 600],
    '16x9' => [1400, 788],
    '1x1'  => [700, 700],
];

$sabbia = '#eacb89';   // surface-sunken
$siena  = '#a05938';   // decoro
$noce   = '#65412a';   // ink-muted
$mattone = '#813131';  // ink-brand

/** Un arco: due montanti e una volta a tutto sesto. È la forma del marchio. */
$arco = static function (float $cx, float $cy, float $w, float $h, string $colore, float $spessore): string {
    $r    = $w / 2;
    $left = $cx - $r;
    $base = $cy + $h / 2;
    $spring = $cy + $h / 2 - ($h - $r);   // quota d'imposta della volta

    return sprintf(
        '<path d="M %1$.1f %2$.1f L %1$.1f %3$.1f A %4$.1f %4$.1f 0 0 1 %5$.1f %3$.1f L %5$.1f %2$.1f" '
        . 'fill="none" stroke="%6$s" stroke-width="%7$.1f" stroke-linecap="round" opacity="0.5"/>',
        $left, $base, $spring, $r, $left + $w, $colore, $spessore
    );
};

/** @return string il documento SVG */
$svg = static function (int $w, int $h, string $ordinale, string $etichetta) use ($sabbia, $siena, $noce, $mattone, $arco): string {
    $lato   = min($w, $h);
    $archW  = $lato * 0.46;
    $archH  = $lato * 0.62;
    $cx     = $w / 2;
    $cy     = $h / 2 - $lato * 0.04;

    $ordinaleSize  = $lato * 0.19;
    $etichettaSize = max(11.0, $lato * 0.032);
    $etichettaY    = $h - $lato * 0.10;          // la riga sta staccata dal bordo basso
    $etichettaGap  = $etichettaSize * 0.10;      // maiuscoletto: le lettere vanno spaziate

    return <<<SVG
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$w} {$h}" width="{$w}" height="{$h}" role="img">
      <rect width="{$w}" height="{$h}" fill="{$sabbia}"/>
      {$arco($cx, $cy, $archW, $archH, $siena, max(1.5, $lato * 0.004))}
      <text x="{$cx}" y="{$cy}" fill="{$mattone}" opacity="0.62"
            font-family="Prata, 'Playfair Display', Georgia, serif" font-size="{$ordinaleSize}"
            text-anchor="middle" dominant-baseline="central">{$ordinale}</text>
      <text x="{$cx}" y="{$etichettaY}" fill="{$noce}"
            font-family="Figtree, 'Segoe UI', system-ui, sans-serif" font-size="{$etichettaSize}"
            font-weight="600" letter-spacing="{$etichettaGap}" text-anchor="middle">{$etichetta}</text>
    </svg>
    SVG;
};

$scritti = 0;

// Le cinque camere: scheda, elenco, apertura e due di galleria.
foreach (range(1, 5) as $n) {
    $due       = str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    $etichetta = 'FOTOGRAFIA DA FORNIRE';

    foreach (['4x3', '3x2', '16x9'] as $formato) {
        [$w, $h] = $formats[$formato];
        file_put_contents("{$dir}/camera-{$due}-{$formato}.svg", $svg($w, $h, $due, $etichetta));
        $scritti++;
    }
    foreach (['a', 'b'] as $i => $lettera) {
        [$w, $h] = $formats['1x1'];
        file_put_contents("{$dir}/camera-{$due}-1x1-{$lettera}.svg", $svg($w, $h, $due, $etichetta));
        $scritti++;
    }
}

// L'arco della casa e il ritratto: stessa regola, stesso segnaposto.
[$w, $h] = $formats['16x9'];
file_put_contents("{$dir}/struttura-16x9.svg", $svg($w, $h, '—', 'FOTOGRAFIA DELLA CASA DA FORNIRE'));
$scritti++;
[$w, $h] = $formats['4x3'];
file_put_contents("{$dir}/struttura-4x3.svg", $svg($w, $h, '—', 'FOTOGRAFIA DELLA CASA DA FORNIRE'));
$scritti++;

printf("%d segnaposto scritti in %s\n", $scritti, $dir);
