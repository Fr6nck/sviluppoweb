<?php
/**
 * Una fotografia servita bene.
 *
 * Preferisce il .webp e tiene il .jpg di ripiego, serve due misure con
 * srcset, e scrive sempre width e height perché la pagina non salti durante
 * il caricamento. Tutto tranne la prima immagine della pagina arriva in
 * differita.
 *
 * Le misure NON si passano a mano: le legge dal file. Un width dichiarato a
 * occhio e diverso da quello vero è esattamente il salto di impaginazione
 * che l'attributo dovrebbe impedire, e non se ne accorge nessuno finché non
 * si guarda la pagina caricare su una rete lenta.
 *
 * I segnaposto disegnati sono SVG: lì non c'è nulla da negoziare.
 *
 * @var string      $src    percorso sotto /assets/, senza estensione per le foto
 * @var string      $alt
 * @var bool        $eager  true per l'immagine che apre la pagina
 * @var string|null $sizes  quanto spazio occupa in pagina, per il srcset
 * @var string      $classe
 * @var bool        $differita  true per le fotografie che il JavaScript carica
 *                              quando servono: gli indirizzi stanno in data-*
 * @var int         $width  usati solo se il file non si trova
 * @var int         $height
 */

$eager  = $eager ?? false;
$differita = $differita ?? false;
$classe = $classe ?? '';
$sizes  = $sizes ?? '(max-width: 900px) 100vw, 50vw';

$radice = \ArcoDelVento\App::instance()->config('root') . '/public/assets/';

/** Le misure vere di un file, lette una volta sola per richiesta. */
$misure = static function (string $relativo) use ($radice): ?array {
    static $cache = [];
    if (array_key_exists($relativo, $cache)) {
        return $cache[$relativo];
    }
    $file = $radice . ltrim($relativo, '/');
    $info = is_file($file) ? @getimagesize($file) : false;

    return $cache[$relativo] = $info ? [(int) $info[0], (int) $info[1]] : null;
};

// ------------------------------------------------------------- segnaposto SVG
if (str_ends_with($src, '.svg')) {
    $dim = $misure($src) ?? [(int) ($width ?? 800), (int) ($height ?? 600)];
    printf(
        '<img src="%s"%s>',
        e(asset($src)),
        attrs([
            'class'    => $classe !== '' ? $classe : null,
            'width'    => (string) $dim[0],
            'height'   => (string) $dim[1],
            'alt'      => $alt,
            'loading'  => $eager ? 'eager' : 'lazy',
            'decoding' => $eager ? 'sync' : 'async',
            'fetchpriority' => $eager ? 'high' : null,
        ])
    );
    return;
}

// --------------------------------------------------------------- fotografia
$grande  = $misure($src . '.webp');
$piccola = $misure($src . '-sm.webp');

$dim = $grande ?? $misure($src . '.jpg') ?? [(int) ($width ?? 1200), (int) ($height ?? 900)];

/** Costruisce il srcset solo quando la seconda misura esiste davvero. */
$srcset = static function (string $estensione) use ($src, $grande, $piccola): ?string {
    if ($grande === null || $piccola === null) {
        return null;
    }

    return sprintf(
        '%s %dw, %s %dw',
        asset($src . '-sm' . $estensione), $piccola[0],
        asset($src . $estensione), $grande[0]
    );
};

/* Una fotografia «differita» non ha src: gli indirizzi stanno in data-src e
   data-srcset, e li copia al loro posto site.js appena la fotografia sta per
   comparire. Serve alle fotografie che si alternano in apertura: sono cinque
   a tutta larghezza, e scaricarle tutte subito per mostrarne una costava
   seicento kilobyte. Senza JavaScript se ne vede solo la prima, quindi non
   si perde niente. */
$pre = $differita ? 'data-' : '';

$comuni = attrs([
    'class'    => $classe !== '' ? $classe : null,
    'width'    => (string) $dim[0],
    'height'   => (string) $dim[1],
    'alt'      => $alt,
    'loading'  => $eager ? 'eager' : 'lazy',
    'decoding' => $eager ? 'sync' : 'async',
    'fetchpriority' => $eager ? 'high' : null,
    $pre . 'srcset' => $srcset('.jpg'),
    'sizes'    => $srcset('.jpg') !== null ? $sizes : null,
]);
?>
<picture>
  <?php /* La WebP si offre solo se c'è: un server senza WebP scrive solo JPG,
           e una <source> che punta a un file mancante lascia il riquadro
           vuoto invece di ripiegare sulla JPG. */ ?>
  <?php if ($grande !== null): ?>
    <source type="image/webp"<?= attrs([
        $pre . 'srcset' => $srcset('.webp') ?? asset($src . '.webp'),
        'sizes'  => $srcset('.webp') !== null ? $sizes : null,
    ]) ?>>
  <?php endif; ?>
  <img <?= $pre ?>src="<?= e(asset($src . '.jpg')) ?>"<?= $comuni ?>>
</picture>
