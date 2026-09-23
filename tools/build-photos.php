<?php
/**
 * Arco del Vento — prepara le fotografie per il sito.
 *
 *     php tools/build-photos.php
 *
 * Prende gli originali da docs/foto-originali/ e ne ricava i tagli che il
 * design system chiede — 4:3 per la scheda, 3:2 per l'elenco, 16:9 per
 * un'apertura larga, 1:1 per la galleria, 3:4 per una figura verticale —
 * in .webp con il ripiego .jpg, due misure ciascuno per il srcset.
 *
 * Due scelte da conoscere.
 *
 * NESSUNA CORREZIONE DI COLORE. Il manuale dice fotografie calde e in luce
 * naturale, niente filtri: queste lo sono già. Si taglia e si ridimensiona,
 * nient'altro.
 *
 * NESSUN INGRANDIMENTO. Se il taglio esce più stretto della misura chiesta,
 * si serve la misura vera: un'immagine ingrandita oltre i suoi pixel è più
 * pesante e più brutta dell'originale.
 *
 * Il punto di messa a fuoco di ogni foto dice che cosa NON perdere quando il
 * taglio stringe. Nelle camere è la finestra, a destra e in basso; nel
 * corridoio è la rosa dei venti intarsiata nel pavimento.
 */

declare(strict_types=1);

$root = dirname(__DIR__);

/** Le misure del manuale: rapporto => [larghezze servite, tetto di peso del .webp in KB]. */
const FORMATI = [
    '4x3'  => [[1200, 800],  140],
    '3x2'  => [[1400, 900],  160],
    '16x9' => [[1400, 900],  240],
    '1x1'  => [[1000, 700],  120],
    '3x4'  => [[1400, 900],  200],
];

/**
 * Che cosa produrre da ogni originale.
 *
 * `fuoco` è il punto da tenere quando il taglio stringe, in frazione di
 * larghezza e altezza. Nelle camere sta a destra e sotto il centro: a destra
 * c'è la finestra, sotto ci sono i letti, e sopra c'è solo soffitto.
 */
$sorgenti = [
    // Il nome del file è il numero della camera: l'accoppiamento è confermato
    // dal titolare. La Camera 01 — la tripla — non ha ancora una fotografia.
    'camera-02'       => ['dir' => 'camere', 'fuoco' => [0.60, 0.58], 'formati' => ['4x3', '3x2', '16x9', '1x1']],
    'camera-03'       => ['dir' => 'camere', 'fuoco' => [0.60, 0.58], 'formati' => ['4x3', '3x2', '16x9', '1x1']],
    'camera-04'       => ['dir' => 'camere', 'fuoco' => [0.60, 0.58], 'formati' => ['4x3', '3x2', '16x9', '1x1']],
    'camera-05'       => ['dir' => 'camere', 'fuoco' => [0.55, 0.58], 'formati' => ['4x3', '3x2', '16x9', '1x1']],
    // Il corridoio: la rosa dei venti è intarsiata in basso, e tagliando
    // dall'alto si perde. Il fuoco scende.
    'casa-corridoio'  => ['dir' => 'casa',   'fuoco' => [0.50, 0.66], 'formati' => ['3x4', '4x3', '1x1']],
    // Le due di San Rufino, quando arrivano:
    'san-rufino-finestra' => ['dir' => 'casa', 'fuoco' => [0.50, 0.45], 'formati' => ['3x4', '4x3', '1x1']],
    'piazza-san-rufino'   => ['dir' => 'casa', 'fuoco' => [0.50, 0.50], 'formati' => ['16x9', '3x2', '4x3']],
    'camera-01'           => ['dir' => 'camere', 'fuoco' => [0.60, 0.58], 'formati' => ['4x3', '3x2', '16x9', '1x1']],

    // Le tre vedute di Assisi su licenza Unsplash. Arrivavano dal design
    // system già tagliate, ma servite in una misura sola: su un telefono si
    // scaricavano 1400px per riempirne 350. Passano di qui come le altre.
    'vicolo-campanile'  => ['dir' => 'foto', 'fuoco' => [0.50, 0.45], 'formati' => ['3x4', '4x3']],
    'basilica-tramonto' => ['dir' => 'foto', 'fuoco' => [0.50, 0.50], 'formati' => ['3x4']],
    'valle-panorama'    => ['dir' => 'foto', 'fuoco' => [0.50, 0.50], 'formati' => ['16x9']],
];

/** Apre un originale, qualunque sia il suo formato. */
function apri(string $file): \GdImage
{
    $dati = file_get_contents($file);
    $im   = @imagecreatefromstring((string) $dati);
    if (!$im) {
        throw new \RuntimeException("Non riesco ad aprire {$file}");
    }

    return $im;
}

/**
 * Ritaglia al rapporto chiesto tenendo il punto di messa a fuoco dentro
 * l'inquadratura, e senza mai uscire dai bordi dell'originale.
 */
function ritaglia(\GdImage $src, float $rapporto, array $fuoco): \GdImage
{
    $w = imagesx($src);
    $h = imagesy($src);

    if ($w / $h > $rapporto) {
        // L'originale è più largo del taglio: si toglie ai lati.
        $nh = $h;
        $nw = (int) round($h * $rapporto);
    } else {
        // Più alto: si toglie sopra e sotto.
        $nw = $w;
        $nh = (int) round($w / $rapporto);
    }

    // Il punto di fuoco va al centro del ritaglio, poi si rientra nei bordi.
    $x = (int) round($fuoco[0] * $w - $nw / 2);
    $y = (int) round($fuoco[1] * $h - $nh / 2);
    $x = max(0, min($x, $w - $nw));
    $y = max(0, min($y, $h - $nh));

    $out = imagecreatetruecolor($nw, $nh);
    imagecopy($out, $src, 0, 0, $x, $y, $nw, $nh);

    return $out;
}

function ridimensiona(\GdImage $src, int $larghezza): \GdImage
{
    $w = imagesx($src);
    $h = imagesy($src);
    if ($larghezza >= $w) {
        return $src;   // mai ingrandire
    }
    $altezza = (int) round($h * $larghezza / $w);
    $out     = imagecreatetruecolor($larghezza, $altezza);
    // Ricampionamento bicubico: su una foto di pietra e tessuto la differenza
    // con imagecopyresized si vede a occhio nudo.
    imagecopyresampled($out, $src, 0, 0, 0, 0, $larghezza, $altezza, $w, $h);

    return $out;
}

/** Scrive .webp e .jpg scendendo di qualità finché il .webp sta nel tetto. */
function scrivi(\GdImage $im, string $base, int $tettoKB): array
{
    $qualita = 84;
    do {
        imagewebp($im, $base . '.webp', $qualita);
        $peso = (int) round(filesize($base . '.webp') / 1024);
        if ($peso <= $tettoKB || $qualita <= 56) {
            break;
        }
        $qualita -= 6;
    } while (true);

    // Il ripiego .jpg pesa dal 15 al 25 per cento in più: è normale.
    imagejpeg($im, $base . '.jpg', min(88, $qualita + 4));

    return [$peso, (int) round(filesize($base . '.jpg') / 1024), $qualita];
}

$rapporti = ['4x3' => 4/3, '3x2' => 3/2, '16x9' => 16/9, '1x1' => 1.0, '3x4' => 3/4];
$fatti = 0;
$saltati = [];

printf("%-34s %8s %8s %8s  %s\n", 'file', 'webp', 'jpg', 'qualità', 'esito');
printf("%s\n", str_repeat('-', 78));

foreach ($sorgenti as $nome => $spec) {
    $originale = $root . '/docs/foto-originali/' . $nome . '.webp';
    if (!is_file($originale)) {
        $originale = $root . '/docs/foto-originali/' . $nome . '.jpg';
    }
    if (!is_file($originale)) {
        $saltati[] = $nome;
        continue;
    }

    $src    = apri($originale);
    $cartella = $root . '/public/assets/img/' . $spec['dir'];
    @mkdir($cartella, 0775, true);

    foreach ($spec['formati'] as $formato) {
        [$larghezze, $tetto] = FORMATI[$formato];
        $tagliata = ritaglia($src, $rapporti[$formato], $spec['fuoco']);

        $larghezzaMax = imagesx($tagliata);

        foreach ($larghezze as $i => $larghezza) {
            $im   = ridimensiona($tagliata, $larghezza);
            $vera = imagesx($im);

            // Il tetto scala con l'area: la misura piccola ha senso solo se è
            // davvero più leggera, e un tetto uguale per tutte le lascerebbe
            // pesare quanto la grande. Un'immagine larga la metà ha un quarto
            // dei pixel, quindi il tetto scende con il quadrato del rapporto.
            $rapporto   = $vera / min($larghezzaMax, $larghezze[0]);
            $tettoQui   = (int) max(28, round($tetto * $rapporto ** 2));

            // La misura piccola tiene il suffisso: è la seconda voce del srcset.
            $base = sprintf('%s/%s-%s%s', $cartella, $nome, $formato, $i === 0 ? '' : '-sm');
            [$w, $j, $q] = scrivi($im, $base, $tettoQui);
            $esito = $w <= $tettoQui ? 'ok' : sprintf('OLTRE IL TETTO (%d KB)', $tettoQui);
            printf("%-34s %7dK %7dK %8d  %s (%dpx)\n", basename($base), $w, $j, $q, $esito, $vera);
            $fatti++;
        }
    }
}

printf("\n%d file scritti.\n", $fatti);
if ($saltati !== []) {
    printf("Originali non ancora presenti in docs/foto-originali/: %s\n", implode(', ', $saltati));
}
