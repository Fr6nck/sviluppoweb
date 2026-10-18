<?php
namespace MHW;

/**
 * L'immagine d'anteprima 1200×630 di una struttura, per i link condivisi (WhatsApp, Telegram,
 * Messenger). Due varianti:
 *   invito — la copertina e basta, ritagliata al centro: l'anteprima di /i/CODICE (7D);
 *   guida  — la copertina con una sfumatura scura in basso e il marchio piccolo in bianco in basso
 *            a destra: l'anteprima del link della guida (8B).
 * Senza copertina: il fondo della palette (e il marchio, nella variante guida).
 *
 * Niente testo disegnato: GD senza un font TTF non scrive gli accenti, e il nome è già nel titolo
 * dell'anteprima. Mai indirizzo, Wi-Fi o contatti: l'immagine viene solo dalla copertina.
 *
 * Il file si salva in storage/anteprime/ con un'impronta di copertina, nome e palette: se
 * cambiano, il nome del file cambia e il vecchio si cancella.
 */
final class Anteprima
{
    public const L = 1200;
    public const A = 630;
    private const VERSIONE = 1;

    public static function cartella(): string { return MHW_APP . '/storage/anteprime'; }

    /** Il percorso del JPEG, creato la prima volta; null senza GD o se il disco non è scrivibile. */
    public static function file(array $p, string $tipo = 'invito'): ?string
    {
        if (!function_exists('imagecreatetruecolor') || !in_array($tipo, ['invito', 'guida'], true)) return null;
        $pid = (int) ($p['id'] ?? 0);
        if ($pid <= 0) return null;
        $firma = substr(sha1(implode('|', [(int) ($p['cover_media_id'] ?? 0), (string) ($p['name'] ?? ''), (string) ($p['palette'] ?? ''), (int) ($p['photos_bw'] ?? 0), $tipo, self::VERSIONE])), 0, 12);
        $dir = self::cartella();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return null;
        $f = "$dir/$pid-$tipo-$firma.jpg";
        if (is_file($f)) return $f;
        foreach (glob("$dir/$pid-$tipo-*.jpg") ?: [] as $vecchio) @unlink($vecchio);
        try { $img = self::disegna($p, $tipo); }
        catch (\Throwable $e) { Log::exception($e, 'Anteprima'); return null; }
        $tmp = $f . '.' . bin2hex(random_bytes(4));
        $ok = imagejpeg($img, $tmp, 82);
        imagedestroy($img);
        if (!$ok || !@rename($tmp, $f)) { @unlink($tmp); return null; }
        return $f;
    }

    /** Manda l'immagine al browser, con l'intestazione delle guide (fuori dai motori). */
    public static function manda(string $file): never
    {
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . (string) filesize($file));
        header('Cache-Control: public, max-age=3600');
        header('X-Robots-Tag: ' . Support::ROBOTS_GUIDE);
        readfile($file);
        exit;
    }

    private static function disegna(array $p, string $tipo): \GdImage
    {
        $img = imagecreatetruecolor(self::L, self::A);
        $pal = Palette::get(Palette::exists((string) ($p['palette'] ?? '')) ? (string) $p['palette'] : Palette::DEFAULT);
        [$r, $g, $b] = self::rgb((string) (($pal['scuro']['accent'] ?? null) ?: '#b4451f'));
        imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b));

        $bytes = !empty($p['cover_media_id']) ? Media::bytes((int) $p['cover_media_id']) : null;
        $foto = $bytes ? @imagecreatefromstring($bytes) : false;
        if ($foto) {
            // Ritaglio al centro: la foto copre tutto il riquadro, senza deformarsi.
            $w = imagesx($foto); $h = imagesy($foto);
            $scala = max(self::L / $w, self::A / $h);
            $cw = (int) round(self::L / $scala); $ch = (int) round(self::A / $scala);
            imagecopyresampled($img, $foto, 0, 0, (int) (($w - $cw) / 2), (int) (($h - $ch) / 2), self::L, self::A, $cw, $ch);
            imagedestroy($foto);
            // 9J · «Bianco e nero» con «Foto in bianco e nero»: anche l'anteprima del link in scala di grigi.
            if (($p['palette'] ?? '') === 'monocromo' && !empty($p['photos_bw'])) imagefilter($img, IMG_FILTER_GRAYSCALE);
        }
        if ($tipo === 'guida') {
            // Sfumatura scura in basso (ultimo 45%), poi il marchio.
            imagealphablending($img, true);
            $da = (int) (self::A * 0.55);
            for ($y = $da; $y < self::A; $y++) {
                $alfa = 127 - (int) round(80 * (($y - $da) / (self::A - $da)));
                imageline($img, 0, $y, self::L - 1, $y, imagecolorallocatealpha($img, 12, 10, 8, max(0, $alfa)));
            }
            self::marchio($img);
        }
        return $img;
    }

    /** Il marchio «myhouse welcome» in bianco, in basso a destra (assets/email/marchio-email.png). */
    private static function marchio(\GdImage $img): void
    {
        $f = MHW_APP . '/public/assets/email/marchio-email.png';
        if (!is_file($f)) $f = dirname(MHW_APP) . '/assets/email/marchio-email.png';   // nel pacchetto gli asset stanno accanto ad app/
        $m = is_file($f) ? @imagecreatefrompng($f) : false;
        if (!$m) return;
        imagealphablending($m, false); imagesavealpha($m, true);
        imagefilter($m, IMG_FILTER_BRIGHTNESS, 255);   // ricolorato in bianco, la trasparenza resta
        $w = imagesx($m); $h = imagesy($m);
        $nw = 282; $nh = (int) round($h * $nw / max(1, $w));
        imagealphablending($img, true);
        imagecopyresampled($img, $m, self::L - $nw - 44, self::A - $nh - 40, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($m);
    }

    private static function rgb(string $hex): array
    {
        $h = ltrim($hex, '#');
        if (strlen($h) === 3) $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    }
}
