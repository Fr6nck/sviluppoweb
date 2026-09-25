<?php
/**
 * Arco del Vento — ricava dalla rosa dei venti le misure che servono in pagina.
 *
 *     php tools/build-icone.php
 *
 * La rosa sta nella testata accanto al logotipo, a 60-70 pixel: servono una
 * misura normale e una doppia per gli schermi fitti. Le PNG che ci sono già
 * restano per l'icona del browser; qui si scrivono WebP con la trasparenza,
 * che a parità di nitidezza pesano un quinto.
 */

declare(strict_types=1);

$dir      = dirname(__DIR__) . '/public/assets/img/logo';
$sorgente = imagecreatefrompng($dir . '/icona-512.png');
if ($sorgente === false) {
    fwrite(STDERR, "icona-512.png non leggibile\n");
    exit(1);
}

foreach ([72, 144] as $lato) {
    $im = imagecreatetruecolor($lato, $lato);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagecopyresampled($im, $sorgente, 0, 0, 0, 0, $lato, $lato, 512, 512);
    imagewebp($im, "{$dir}/icona-{$lato}.webp", 88);
    printf("icona-%d.webp  %5.1f KB\n", $lato, filesize("{$dir}/icona-{$lato}.webp") / 1024);
}
