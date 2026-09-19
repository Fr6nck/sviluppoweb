<?php
namespace MHW;

final class Media
{
    private const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const MAX_BYTES = 6_000_000;
    private const MAX_EDGE = 1600;

    /** Accetta solo immagini vere: controlla il contenuto, non il nome del file. */
    public static function store(array $file, int $accountId, string $alt = ''): int
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)
            throw new \RuntimeException('Caricamento non riuscito.');
        if ($file['size'] > self::MAX_BYTES)
            throw new \RuntimeException('Immagine troppo pesante: il limite è 6 MB.');

        $info = @getimagesize($file['tmp_name']);
        if (!$info) throw new \RuntimeException('Questo file non è un\'immagine.');
        $mime = $info['mime'];
        if (!isset(self::ALLOWED[$mime])) throw new \RuntimeException('Formati accettati: JPG, PNG, WebP.');

        $img = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
            'image/png'  => imagecreatefrompng($file['tmp_name']),
            'image/webp' => imagecreatefromwebp($file['tmp_name']),
        };
        if (!$img) throw new \RuntimeException('Immagine illeggibile.');

        [$w, $h] = [imagesx($img), imagesy($img)];
        if (max($w, $h) > self::MAX_EDGE) {
            $scale = self::MAX_EDGE / max($w, $h);
            $nw = (int) round($w * $scale); $nh = (int) round($h * $scale);
            $resized = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img); $img = $resized; $w = $nw; $h = $nh;
        }

        $dir = Config::get('uploads_dir');
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        // Riscritta come JPEG: qualunque cosa fosse dentro il file originale non sopravvive.
        $name = date('Ym') . '-' . Support::token(8) . '.jpg';
        imagejpeg($img, $dir . '/' . $name, 82);
        imagedestroy($img);

        return Db::insert('media', [
            'account_id' => $accountId, 'filename' => $name, 'mime' => 'image/jpeg',
            'bytes' => filesize($dir . '/' . $name) ?: 0, 'width' => $w, 'height' => $h,
            'alt' => mb_substr($alt, 0, 250), 'created_at' => Support::now(),
        ]);
    }

    public static function url(?int $id): ?string
    {
        if (!$id) return null;
        $m = Db::one('SELECT filename FROM media WHERE id = ?', [$id]);
        return $m ? Config::get('uploads_url') . '/' . $m['filename'] : null;
    }

    public static function alt(?int $id): string
    {
        if (!$id) return '';
        return (string) Db::val('SELECT alt FROM media WHERE id = ?', [$id], '');
    }
}
