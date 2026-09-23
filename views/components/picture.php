<?php
/**
 * Una fotografia servita bene.
 *
 * Preferisce il .webp e tiene il .jpg di ripiego, scrive sempre width e
 * height perché la pagina non salti durante il caricamento, e carica in
 * differita tutto tranne la prima immagine della pagina.
 *
 * I segnaposto disegnati sono SVG: lì non c'è nulla da negoziare, si serve
 * il file e basta.
 *
 * @var string $src     percorso sotto /assets/, senza estensione per le foto
 * @var string $alt
 * @var int    $width
 * @var int    $height
 * @var bool   $eager   true per l'immagine che apre la pagina
 * @var string $classe
 */

$eager  = $eager ?? false;
$classe = $classe ?? '';
$attr   = attrs([
    'class'    => $classe !== '' ? $classe : null,
    'width'    => (string) $width,
    'height'   => (string) $height,
    'alt'      => $alt,
    'loading'  => $eager ? 'eager' : 'lazy',
    'decoding' => $eager ? 'sync' : 'async',
    'fetchpriority' => $eager ? 'high' : null,
]);

if (str_ends_with($src, '.svg')) {
    printf('<img src="%s"%s>', e(asset($src)), $attr);
    return;
}
?>
<picture>
  <source srcset="<?= e(asset($src . '.webp')) ?>" type="image/webp">
  <img src="<?= e(asset($src . '.jpg')) ?>"<?= $attr ?>>
</picture>
