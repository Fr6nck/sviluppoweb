<?php

declare(strict_types=1);

namespace ArcoDelVento\Media;

/**
 * Prepara le immagini caricate dall'area riservata.
 *
 * Il file che arriva non si pubblica mai così com'è: si apre con GD, si
 * raddrizza secondo l'EXIF del telefono, si ritaglia ai rapporti che il sito
 * usa e si riscrive da capo. Riscrivere è anche una misura di sicurezza: di
 * un file caricato resta solo l'immagine, non quello che qualcuno ci avesse
 * nascosto dentro, e il nome lo decide il sito, non chi carica.
 *
 * Tre trattamenti:
 *   - foto:  ritagli ai rapporti del sito, .webp e .jpg, grande e piccola;
 *   - logo:  proporzioni libere, trasparenza tenuta, .webp e .png;
 *   - icona: quadrata, trasparenza tenuta, le misure dell'icona del browser.
 *
 * Mai ingrandire una foto: se il ritaglio esce più stretto della misura
 * chiesta, si serve quella vera.
 */
final class Elaboratore
{
    public const MAX_BYTE   = 15 * 1024 * 1024;
    public const MAX_PIXEL  = 60_000_000;

    /** rapporto => [larghezza, larghezza piccola, tetto del .webp in KB] — come tools/build-photos.php */
    public const FORMATI = [
        '4x3'  => [1200, 800, 140],
        '3x2'  => [1400, 900, 160],
        '16x9' => [1600, 900, 260],
        '1x1'  => [1000, 700, 120],
        '3x4'  => [1400, 900, 200],
    ];

    /** Il lato lungo a cui si porta subito un'immagine grande: basta per ogni ritaglio. */
    public const LATO_DI_LAVORO = 2600;

    /** Le misure della rosa: le WebP per la testata, le PNG per il browser. */
    public const ICONA_WEBP = [72, 144];
    public const ICONA_PNG  = [48, 96, 192, 512];

    public static function disponibile(): bool
    {
        return function_exists('imagecreatefromjpeg') && function_exists('imagecreatetruecolor');
    }

    public static function scriveWebp(): bool
    {
        return function_exists('imagewebp');
    }

    /**
     * Apre un file caricato e controlla che sia davvero un'immagine usabile.
     *
     * @return array{0: \GdImage, 1: bool} l'immagine e se ha trasparenza
     * @throws ErroreImmagine con un messaggio da mostrare a chi carica
     */
    public static function apri(string $percorso, int $latoMinimo): array
    {
        if (!self::disponibile()) {
            throw new ErroreImmagine('Il server non ha la libreria per le immagini (GD): chiedi all\'assistenza di Hostinger di attivarla.');
        }
        if (!is_file($percorso) || filesize($percorso) === 0) {
            throw new ErroreImmagine('Il file non è arrivato. Riprova.');
        }
        if (filesize($percorso) > self::MAX_BYTE) {
            throw new ErroreImmagine('Il file è troppo grande: al massimo 15 MB.');
        }

        $info = @getimagesize($percorso);
        if ($info === false) {
            $inizio = (string) file_get_contents($percorso, false, null, 0, 64);
            if (str_contains($inizio, 'ftyphei') || str_contains($inizio, 'ftypmif')) {
                throw new ErroreImmagine('È una foto HEIC dell\'iPhone. Nelle Impostazioni → Fotocamera → Formati scegli «Più compatibile», oppure esportala in JPG, e riprova.');
            }
            throw new ErroreImmagine('Questo file non è un\'immagine: carica una JPG, PNG o WebP.');
        }
        [$w, $h, $tipo] = $info;
        if (!in_array($tipo, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new ErroreImmagine('Formato non accettato: carica una JPG, PNG o WebP.');
        }
        if ($w * $h > self::MAX_PIXEL) {
            throw new ErroreImmagine('L\'immagine ha troppi pixel: riducila sotto i 60 milioni (per esempio 9000 × 6000).');
        }
        if (min($w, $h) < $latoMinimo) {
            throw new ErroreImmagine(sprintf('L\'immagine è troppo piccola (%d × %d px): il lato corto deve essere almeno %d px, altrimenti sul sito si vede sgranata.', $w, $h, $latoMinimo));
        }
        if ($tipo === IMAGETYPE_WEBP && !function_exists('imagecreatefromwebp')) {
            throw new ErroreImmagine('Il server non sa leggere le WebP: carica una JPG o una PNG.');
        }

        // GD tiene in memoria ogni pixel: una foto da 48 megapixel sono circa
        // 200 MB. Meglio dirlo prima che finire la memoria a metà.
        if (!self::memoriaPer((int) ($w * $h * 4.4) + 64 * 1048576)) {
            throw new ErroreImmagine(sprintf(
                'L\'immagine è troppo grande per la memoria del server (%d × %d px). Riducila a circa 4000 px di lato e riprova.',
                $w, $h
            ));
        }

        $im = match ($tipo) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($percorso),
            IMAGETYPE_PNG  => @imagecreatefrompng($percorso),
            IMAGETYPE_WEBP => @imagecreatefromwebp($percorso),
        };
        if (!$im instanceof \GdImage) {
            throw new ErroreImmagine('L\'immagine è rovinata o incompleta: riesportala e riprova.');
        }
        if (!imageistruecolor($im)) {
            imagepalettetotruecolor($im);
        }

        $trasparente = $tipo !== IMAGETYPE_JPEG && self::haTrasparenza($im);
        // Subito alla misura di lavoro: i ritagli e la rotazione costano
        // memoria in proporzione, e nessun formato del sito chiede di più.
        if (max($w, $h) > self::LATO_DI_LAVORO) {
            $scala = self::LATO_DI_LAVORO / max($w, $h);
            $nw = max(1, (int) round($w * $scala));
            $nh = max(1, (int) round($h * $scala));
            $piccola = $trasparente ? self::tela($nw, $nh) : imagecreatetruecolor($nw, $nh);
            imagecopyresampled($piccola, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
            unset($im);
            $im = $piccola;
        }
        if ($tipo === IMAGETYPE_JPEG) {
            $im = self::raddrizza($im, $percorso);
        }

        return [$im, $trasparente];
    }

    /**
     * Una foto: un ritaglio per ogni rapporto, due misure ciascuno.
     *
     * @param list<string>        $rapporti
     * @param array{0:float,1:float} $fuoco  il punto da tenere quando il taglio stringe
     * @return list<string> i file scritti, relativi alla cartella
     */
    public static function foto(\GdImage $src, string $cartella, string $nome, array $rapporti, array $fuoco = [0.5, 0.5]): array
    {
        $scritti = [];
        foreach ($rapporti as $rapporto) {
            [$grande, $piccola, $tetto] = self::FORMATI[$rapporto];
            [$a, $b] = array_map('intval', explode('x', $rapporto));
            $taglio  = self::ritaglia($src, $a / $b, $fuoco);

            foreach (['' => $grande, '-sm' => $piccola] as $suffisso => $larghezza) {
                $im   = self::ridimensiona($taglio, $larghezza);
                $base = "{$nome}-{$rapporto}{$suffisso}";
                foreach (self::scriviFoto($im, $cartella . '/' . $base, $tetto) as $file) {
                    $scritti[] = $file;
                }
            }
        }

        return $scritti;
    }

    /**
     * Un logo: niente ritaglio, trasparenza tenuta, alto il doppio di quanto
     * serve in pagina perché resti nitido sugli schermi fitti.
     *
     * @return array{file: list<string>, w: int, h: int}
     */
    public static function logo(\GdImage $src, string $cartella, string $nome, int $altezza): array
    {
        $src = self::rifila($src);
        $w   = imagesx($src);
        $h   = imagesy($src);
        $nh  = min($h, $altezza);
        $nw  = (int) max(1, round($w * $nh / $h));
        $im  = self::tela($nw, $nh);
        imagecopyresampled($im, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        $file = [];
        imagepng($im, "{$cartella}/{$nome}.png", 9);
        $file[] = "{$nome}.png";
        if (self::scriveWebp()) {
            imagewebp($im, "{$cartella}/{$nome}.webp", 90);
            $file[] = "{$nome}.webp";
        }

        return ['file' => $file, 'w' => $nw, 'h' => $nh];
    }

    /**
     * L'icona: il quadrato centrale, alle misure che servono.
     *
     * @return list<string>
     */
    public static function icona(\GdImage $src, string $cartella, string $nome): array
    {
        $quadrato = self::ritaglia($src, 1.0, [0.5, 0.5], true);
        $lato     = imagesx($quadrato);
        $file     = [];
        foreach ([...self::ICONA_WEBP, ...self::ICONA_PNG] as $i => $misura) {
            $im = self::tela($misura, $misura);
            imagecopyresampled($im, $quadrato, 0, 0, 0, 0, $misura, $misura, $lato, $lato);
            if ($i < count(self::ICONA_WEBP)) {
                if (self::scriveWebp()) {
                    imagewebp($im, "{$cartella}/{$nome}-{$misura}.webp", 88);
                    $file[] = "{$nome}-{$misura}.webp";
                }
            } else {
                imagepng($im, "{$cartella}/{$nome}-{$misura}.png", 9);
                $file[] = "{$nome}-{$misura}.png";
            }
        }

        return $file;
    }

    // ------------------------------------------------------------ interni

    /** C'è memoria per $byte in più? Se il limite è stretto, prova ad alzarlo. */
    private static function memoriaPer(int $byte): bool
    {
        $inByte = static function (string $v): int {
            $n = (int) $v;
            return match (strtolower(substr(trim($v), -1))) {
                'g' => $n * 1024 ** 3, 'm' => $n * 1024 ** 2, 'k' => $n * 1024, default => $n,
            };
        };
        $limite = $inByte((string) ini_get('memory_limit'));
        if ($limite <= 0 || $limite - memory_get_usage() >= $byte) {
            return true;
        }
        @ini_set('memory_limit', (int) ceil((memory_get_usage() + $byte) / 1048576) + 16 . 'M');
        $limite = $inByte((string) ini_get('memory_limit'));

        return $limite <= 0 || $limite - memory_get_usage() >= $byte;
    }

    /** Una tela trasparente. */
    private static function tela(int $w, int $h): \GdImage
    {
        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));

        return $im;
    }

    /** @param array{0:float,1:float} $fuoco */
    private static function ritaglia(\GdImage $src, float $rapporto, array $fuoco, bool $alfa = false): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        if ($w / $h > $rapporto) {
            $nh = $h;
            $nw = (int) round($h * $rapporto);
        } else {
            $nw = $w;
            $nh = (int) round($w / $rapporto);
        }
        $x = max(0, min((int) round($fuoco[0] * $w - $nw / 2), $w - $nw));
        $y = max(0, min((int) round($fuoco[1] * $h - $nh / 2), $h - $nh));

        $out = $alfa ? self::tela($nw, $nh) : imagecreatetruecolor($nw, $nh);
        imagecopy($out, $src, 0, 0, $x, $y, $nw, $nh);

        return $out;
    }

    private static function ridimensiona(\GdImage $src, int $larghezza): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        if ($larghezza >= $w) {
            return $src;
        }
        $altezza = (int) round($h * $larghezza / $w);
        $out     = imagecreatetruecolor($larghezza, $altezza);
        imagecopyresampled($out, $src, 0, 0, 0, 0, $larghezza, $altezza, $w, $h);

        return $out;
    }

    /**
     * .webp e .jpg; il .webp scende di qualità finché sta nel tetto.
     *
     * @return list<string> i nomi scritti, relativi alla cartella
     */
    private static function scriviFoto(\GdImage $im, string $base, int $tettoKB): array
    {
        $scritti = [];
        if (self::scriveWebp()) {
            $qualita = 84;
            do {
                imagewebp($im, $base . '.webp', $qualita);
                clearstatcache(true, $base . '.webp');
                $peso = (int) round(filesize($base . '.webp') / 1024);
                if ($peso <= $tettoKB || $qualita <= 56) {
                    break;
                }
                $qualita -= 6;
            } while (true);
            $scritti[] = basename($base) . '.webp';
        }
        imagejpeg($im, $base . '.jpg', 82);
        $scritti[] = basename($base) . '.jpg';

        return $scritti;
    }

    /** Toglie i bordi del tutto trasparenti attorno a un logo. */
    private static function rifila(\GdImage $src): \GdImage
    {
        if (!function_exists('imagecropauto')) {
            return $src;
        }
        imagesavealpha($src, true);
        $rifilata = @imagecropauto($src, IMG_CROP_TRANSPARENT);

        return $rifilata instanceof \GdImage ? $rifilata : $src;
    }

    private static function haTrasparenza(\GdImage $im): bool
    {
        $w = imagesx($im);
        $h = imagesy($im);
        $passo = max(1, (int) floor(min($w, $h) / 40));
        for ($x = 0; $x < $w; $x += $passo) {
            foreach ([0, $h - 1, (int) ($h / 2)] as $y) {
                if (((imagecolorat($im, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }
        for ($y = 0; $y < $h; $y += $passo) {
            foreach ([0, $w - 1] as $x) {
                if (((imagecolorat($im, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Le foto del telefono arrivano coricate, con l'orientamento scritto nell'EXIF. */
    private static function raddrizza(\GdImage $im, string $percorso): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $im;
        }
        $exif = @exif_read_data($percorso);
        $o    = (int) ($exif['Orientation'] ?? 1);

        return match ($o) {
            2 => self::specchia($im, IMG_FLIP_HORIZONTAL),
            3 => imagerotate($im, 180, 0) ?: $im,
            4 => self::specchia($im, IMG_FLIP_VERTICAL),
            5 => self::specchia(imagerotate($im, -90, 0) ?: $im, IMG_FLIP_HORIZONTAL),
            6 => imagerotate($im, -90, 0) ?: $im,
            7 => self::specchia(imagerotate($im, 90, 0) ?: $im, IMG_FLIP_HORIZONTAL),
            8 => imagerotate($im, 90, 0) ?: $im,
            default => $im,
        };
    }

    private static function specchia(\GdImage $im, int $modo): \GdImage
    {
        imageflip($im, $modo);

        return $im;
    }
}
