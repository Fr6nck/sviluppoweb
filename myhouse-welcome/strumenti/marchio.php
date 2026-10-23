<?php
/**
 * Genera le immagini del marchio con GD: l'icona «Aggiungi a Home» (180 px)
 * e l'anteprima per la condivisione dei link (1200×630). Si lancia una volta
 * sola, quando cambia il marchio; i file generati stanno in app/public/assets.
 *
 *   php strumenti/marchio.php /percorso/Gloock.ttf /percorso/Onest.ttf
 *
 * I caratteri sono Gloock e Onest (licenza OFL), gli stessi del sito. Senza
 * argomenti si usano i serif/sans DejaVu del sistema, se ci sono.
 */
$gloock = $argv[1] ?? '/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf';
$onest = $argv[2] ?? '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
$dest = __DIR__ . '/../app/public/assets';

const INK = [35, 27, 18]; const PAPER = [250, 245, 236]; const TERRA = [180, 69, 31]; const TERRA_NOTTE = [238, 122, 74];
const MUTED = [106, 91, 72];

/** Il simbolo (arco + punto) disegnato a pennello tondo, nel riquadro 32×32 del file SVG. */
function simbolo(GdImage $im, float $x, float $y, float $scala, array $arco, array $punto): void
{
    $c = imagecolorallocate($im, ...$arco);
    $r = 2.4 * $scala / 2;
    $timbro = function (float $px, float $py) use ($im, $c, $r, $x, $y, $scala) {
        imagefilledellipse($im, (int) round($x + $px * $scala), (int) round($y + $py * $scala), (int) round($r * 2), (int) round($r * 2), $c);
    };
    for ($t = 0; $t <= 1; $t += 0.002) { $timbro(6, 28 - 14 * $t); $timbro(26, 14 + 14 * $t); }
    for ($a = 180; $a <= 360; $a += 0.25) $timbro(16 + 10 * cos(deg2rad($a)), 14 + 10 * sin(deg2rad($a)));
    $p = imagecolorallocate($im, ...$punto);
    imagefilledellipse($im, (int) round($x + 20.5 * $scala), (int) round($y + 19 * $scala), (int) round(4 * $scala), (int) round(4 * $scala), $p);
}

/** Si disegna in grande e si riduce: GD non smussa i tratti spessi. */
function riduci(GdImage $grande, int $w, int $h): GdImage
{
    $out = imagecreatetruecolor($w, $h);
    imagecopyresampled($out, $grande, 0, 0, 0, 0, $w, $h, imagesx($grande), imagesy($grande));
    return $out;
}

// ---------------------------------------------------- apple-touch-icon 180 px
$k = 4; $n = 180 * $k;
$im = imagecreatetruecolor($n, $n);
imagefill($im, 0, 0, imagecolorallocate($im, ...INK));
simbolo($im, $n * 0.5 - 16 * 4.2 * $k, $n * 0.5 - 16 * 4.2 * $k - 2 * $k, 4.2 * $k, PAPER, TERRA_NOTTE);
imagepng(riduci($im, 180, 180), "$dest/apple-touch-icon.png", 9);

// ------------------------------------------------------- og:image 1200×630
$k = 2; $W = 1200 * $k; $H = 630 * $k;
$im = imagecreatetruecolor($W, $H);
imagefill($im, 0, 0, imagecolorallocate($im, ...PAPER));
// una grana leggera, come la carta del sito
mt_srand(7);
for ($i = 0; $i < 90000; $i++) {
    $v = mt_rand(0, 1) ? 255 : 0;
    imagesetpixel($im, mt_rand(0, $W - 1), mt_rand(0, $H - 1), imagecolorallocatealpha($im, $v, $v, $v, 118));
}
$ink = imagecolorallocate($im, ...INK); $muted = imagecolorallocate($im, ...MUTED); $terra = imagecolorallocate($im, ...TERRA);
simbolo($im, 88 * $k, 70 * $k, 3.1 * $k, INK, TERRA);
imagettftext($im, 30 * $k, 0, 200 * $k, 150 * $k, $ink, $onest, 'myhouse welcome');
imagettftext($im, 78 * $k, 0, 88 * $k, 330 * $k, $ink, $gloock, 'La casa risponde');
imagettftext($im, 78 * $k, 0, 88 * $k, 425 * $k, $ink, $gloock, 'prima che chiedano.');
imagefilledrectangle($im, 88 * $k, 480 * $k, 148 * $k, 485 * $k, $terra);
imagettftext($im, 26 * $k, 0, 88 * $k, 545 * $k, $muted, $onest, 'La guida digitale per case vacanza, B&B e agriturismi.');
imagejpeg(riduci($im, 1200, 630), "$dest/og.jpg", 86);

echo "apple-touch-icon.png e og.jpg aggiornati in $dest\n";
