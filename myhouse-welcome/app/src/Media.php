<?php
namespace MHW;

/**
 * Immagini e PDF caricati dagli host.
 *
 * Ogni file viene controllato sul CONTENUTO, non sul nome: un .jpg che non è
 * un'immagine viene rifiutato. Le immagini vengono ridisegnate da capo (niente
 * metadati, niente contenuto nascosto sopravvive); i PDF devono essere PDF
 * veri e non contenere JavaScript né azioni automatiche. Nessun SVG: un SVG
 * caricato da un utente è codice, non un'immagine.
 */
final class Media
{
    private const IMG = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    /** Oltre questi pixel un'immagine piccola sul disco esploderebbe in memoria. */
    private const MAX_PIXELS = 40_000_000;

    private static function upload(array $file): string
    {
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) throw new \RuntimeException('Il file è troppo pesante.');
        if ($err !== UPLOAD_ERR_OK) throw new \RuntimeException('Caricamento non riuscito. Riprova.');
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) throw new \RuntimeException('Caricamento non riuscito.');
        return $tmp;
    }

    /**
     * @param string $purpose logo | cover | profile | section | place
     */
    public static function storeImage(array $file, int $accountId, ?int $propertyId, string $alt, string $purpose): int
    {
        $tmp = self::upload($file);
        $cfg = Config::get('storage');
        if (filesize($tmp) > $cfg['max_image_bytes']) {
            throw new \RuntimeException('Immagine troppo pesante: il limite è ' . round($cfg['max_image_bytes'] / 1048576) . ' MB.');
        }
        $info = @getimagesize($tmp);
        if (!$info || !isset(self::IMG[$info['mime']])) throw new \RuntimeException('Formati accettati: JPG, PNG, WebP.');
        [$w, $h] = $info;
        if ($w < 1 || $h < 1 || $w * $h > self::MAX_PIXELS) throw new \RuntimeException('Immagine troppo grande in pixel.');

        $img = match ($info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($tmp),
            'image/png'  => @imagecreatefrompng($tmp),
            'image/webp' => @imagecreatefromwebp($tmp),
        };
        if (!$img) throw new \RuntimeException('Questa immagine non si riesce a leggere.');

        $max = $purpose === 'logo' ? 800 : (int) $cfg['max_image_edge'];
        if (max($w, $h) > $max) {
            $k = $max / max($w, $h);
            $nw = max(1, (int) round($w * $k)); $nh = max(1, (int) round($h * $k));
            $r = imagecreatetruecolor($nw, $nh);
            imagealphablending($r, false); imagesavealpha($r, true);
            imagecopyresampled($r, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img); $img = $r; $w = $nw; $h = $nh;
        }

        // Un logo con la trasparenza resta PNG; tutto il resto diventa JPEG.
        $trasparente = $info['mime'] !== 'image/jpeg' && $purpose === 'logo';
        ob_start();
        if ($trasparente) { imagesavealpha($img, true); imagepng($img, null, 7); $mime = 'image/png'; $ext = 'png'; }
        else {
            // Il fondo trasparente di un PNG, in JPEG, diventerebbe nero: lo si appoggia sul bianco.
            if ($info['mime'] !== 'image/jpeg') {
                $bianco = imagecreatetruecolor($w, $h);
                imagefill($bianco, 0, 0, imagecolorallocate($bianco, 255, 255, 255));
                imagecopy($bianco, $img, 0, 0, 0, 0, $w, $h);
                imagedestroy($img); $img = $bianco;
            }
            imageinterlace($img, true);
            imagejpeg($img, null, 82); $mime = 'image/jpeg'; $ext = 'jpg';
        }
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return self::save($bytes, $mime, $ext, 'image', $accountId, $propertyId, $alt, (string) ($file['name'] ?? ''), $w, $h);
    }

    public static function storePdf(array $file, int $accountId, ?int $propertyId, string $title): int
    {
        $tmp = self::upload($file);
        $cfg = Config::get('storage');
        $size = (int) filesize($tmp);
        if ($size > $cfg['max_pdf_bytes']) throw new \RuntimeException('PDF troppo pesante: il limite è ' . round($cfg['max_pdf_bytes'] / 1048576) . ' MB.');
        if ($size < 100) throw new \RuntimeException('Questo file non è un PDF valido.');
        $bytes = (string) file_get_contents($tmp);
        $finfo = function_exists('finfo_open') ? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tmp) : 'application/pdf';
        if (!str_starts_with($bytes, '%PDF-') || $finfo !== 'application/pdf' || !str_contains(substr($bytes, -2048), '%%EOF')) {
            throw new \RuntimeException('Questo file non è un PDF valido.');
        }
        // Un PDF può contenere codice o aprire programmi: quelli non passano.
        if (preg_match('#/(JavaScript|JS|Launch|EmbeddedFile|OpenAction|AA|RichMedia|XFA)\b#', $bytes)) {
            throw new \RuntimeException('Questo PDF contiene script o azioni automatiche: salvalo di nuovo come PDF semplice e riprova.');
        }
        return self::save($bytes, 'application/pdf', 'pdf', 'pdf', $accountId, $propertyId, $title, (string) ($file['name'] ?? ''), 0, 0);
    }

    /**
     * Un file che sta già sul server (le fotografie della demo): passa dagli
     * stessi controlli sul contenuto, ma non arriva da un modulo.
     */
    public static function importImage(string $path, int $accountId, ?int $propertyId, string $alt): ?int
    {
        $info = @getimagesize($path);
        if (!$info || $info['mime'] !== 'image/jpeg') return null;
        return self::save((string) file_get_contents($path), 'image/jpeg', 'jpg', 'image', $accountId, $propertyId,
                          $alt, basename($path), (int) $info[0], (int) $info[1]);
    }

    private static function save(string $bytes, string $mime, string $ext, string $kind, int $accountId, ?int $propertyId,
                                 string $alt, string $original, int $w, int $h): int
    {
        $store = Storages::current();
        $key = Storages::newKey($accountId, $propertyId, $ext);
        $nome = preg_replace('/[^A-Za-z0-9._-]+/', '-', pathinfo($original, PATHINFO_FILENAME)) ?: 'documento';
        $store->put($key, $bytes, $mime, $kind === 'pdf' ? 'inline; filename="' . mb_substr($nome, 0, 60) . '.pdf"' : '');
        return Db::insert('media', [
            'account_id' => $accountId, 'property_id' => $propertyId,
            'filename' => $store->name() === 'local' ? LocalStorage::fileName($key) : '',
            'object_key' => $key, 'storage' => $store->name(), 'kind' => $kind, 'mime' => $mime,
            'bytes' => strlen($bytes), 'width' => $w, 'height' => $h,
            'alt' => mb_substr($alt, 0, 250), 'original_name' => mb_substr($original, 0, 190),
            'created_at' => Support::now(),
        ]);
    }

    /** Un media appartiene a questo account? Da chiedere prima di collegarlo o toglierlo. */
    public static function owned(?int $id, int $accountId): bool
    {
        return $id !== null && (bool) Db::one('SELECT id FROM media WHERE id = ? AND account_id = ?', [$id, $accountId]);
    }

    public static function url(?int $id): ?string
    {
        if (!$id) return null;
        $m = Db::one('SELECT filename, object_key, storage FROM media WHERE id = ?', [$id]);
        if (!$m) return null;
        // I file caricati prima di questa versione hanno solo il nome sul disco.
        if ($m['object_key'] === '') return Support::url(Config::get('uploads_url') . '/' . $m['filename']);
        try { return Storages::for($m['storage'])->url($m['object_key']); }
        catch (\Throwable $e) { Log::exception($e, 'Media::url'); return null; }
    }

    public static function alt(?int $id): string
    {
        if (!$id) return '';
        return (string) Db::val('SELECT alt FROM media WHERE id = ?', [$id], '');
    }

    public static function row(?int $id): ?array
    {
        return $id ? Db::one('SELECT * FROM media WHERE id = ?', [$id]) : null;
    }

    public static function delete(int $id, int $accountId): void
    {
        $m = Db::one('SELECT * FROM media WHERE id = ? AND account_id = ?', [$id, $accountId]);
        if (!$m) return;
        try {
            if ($m['object_key'] !== '') Storages::for($m['storage'])->delete($m['object_key']);
            elseif ($m['filename'] !== '') @unlink(Config::get('uploads_dir') . '/' . basename($m['filename']));
        } catch (\Throwable $e) { Log::exception($e, 'Media::delete'); }
        Db::run('DELETE FROM media WHERE id = ?', [$id]);
    }
}
