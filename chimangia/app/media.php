<?php
/**
 * Immagini caricate dal pannello: solo JPG, PNG e WebP fino a 5 MB.
 * Tipo reale verificato, nome casuale, ridimensionamento a 1600 px e versione WebP.
 * Gli SVG non si caricano (potrebbero contenere script).
 */
declare(strict_types=1);

defined('CM_ROOT') || exit;

const CM_MEDIA_MAX_BYTES = 5 * 1024 * 1024;
const CM_MEDIA_MAX_SIDE = 1600;
const CM_MEDIA_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

function media_get(int $id): ?array
{
    static $cache = [];
    if ($id <= 0) {
        return null;
    }
    if (!array_key_exists($id, $cache)) {
        try {
            $cache[$id] = q_one('SELECT * FROM ' . t('media') . ' WHERE id = ?', [$id]);
        } catch (Throwable $e) {
            $cache[$id] = null;
        }
    }
    return $cache[$id];
}

function media_all(): array
{
    return q('SELECT * FROM ' . t('media') . ' ORDER BY id DESC')->fetchAll();
}

/** URL dell'immagine (relativo alla radice del sito oppure assoluto). */
function media_url(int $id, bool $absolute = false, bool $preferWebp = false): string
{
    $m = media_get($id);
    if (!$m) {
        return '';
    }
    $file = ($preferWebp && $m['webp'] !== '') ? $m['webp'] : $m['file'];
    return $absolute ? site_url('uploads/' . $file) : 'uploads/' . $file;
}

function media_alt(int $id): string
{
    $m = media_get($id);
    return $m ? (string) $m['alt'] : '';
}

/** <picture> con versione WebP e formato originale. */
function media_picture(int $id, string $alt = '', string $class = '', string $prefix = ''): string
{
    $m = media_get($id);
    if (!$m) {
        return '';
    }
    $alt = $alt !== '' ? $alt : (string) $m['alt'];
    $src = e($prefix . 'uploads/' . $m['file']);
    $html = '<picture>';
    if ($m['webp'] !== '' && $m['webp'] !== $m['file']) {
        $html .= '<source srcset="' . e($prefix . 'uploads/' . $m['webp']) . '" type="image/webp">';
    }
    return $html . '<img src="' . $src . '" alt="' . e($alt) . '" width="' . (int) $m['width'] . '" height="' . (int) $m['height'] . '" loading="lazy" decoding="async"'
        . ($class !== '' ? ' class="' . e($class) . '"' : '') . '></picture>';
}

/**
 * Elabora un file caricato. Restituisce [id, null] oppure [0, messaggio d'errore].
 */
function media_upload(array $file, string $alt, int $userId): array
{
    $alt = clean_text($alt, 255);
    if ($alt === '') {
        return [0, 'Il testo alternativo è obbligatorio: descrivi in poche parole cosa mostra l\'immagine.'];
    }
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        return [0, 'Il file supera la dimensione massima consentita (5 MB).'];
    }
    if ($err !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
        return [0, 'Scegli un file da caricare.'];
    }
    $tmp = (string) $file['tmp_name'];
    $size = (int) filesize($tmp);
    if ($size <= 0 || $size > CM_MEDIA_MAX_BYTES) {
        return [0, 'Il file supera la dimensione massima consentita (5 MB).'];
    }
    // Tipo reale dal contenuto, non dall'estensione.
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if (!isset(CM_MEDIA_TYPES[$mime])) {
        return [0, 'Formato non ammesso: carica un\'immagine JPG, PNG o WebP.'];
    }
    $info = @getimagesize($tmp);
    if (!$info || ($info['mime'] ?? '') !== $mime || $info[0] < 1 || $info[1] < 1) {
        return [0, 'Il file non è un\'immagine valida.'];
    }
    if ($info[0] * $info[1] > 40_000_000) {
        return [0, 'L\'immagine ha troppi pixel: riducila prima di caricarla.'];
    }

    $dir = CM_ROOT . '/uploads';
    $base = bin2hex(random_bytes(12));
    $ext = CM_MEDIA_TYPES[$mime];
    $fileName = $base . '.' . $ext;
    $webpName = '';
    [$w, $h] = [$info[0], $info[1]];

    $gdOk = extension_loaded('gd') && function_exists('imagecreatetruecolor');
    if ($gdOk) {
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmp),
            'image/png' => @imagecreatefrompng($tmp),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
        };
        if (!$src) {
            return [0, 'Impossibile leggere l\'immagine.'];
        }
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $src = media_fix_orientation($src, $tmp, $w, $h);
        }
        $scale = min(1, CM_MEDIA_MAX_SIDE / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        if ($mime !== 'image/jpeg') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        // Ricodifica: elimina metadati ed eventuale contenuto estraneo.
        $saved = match ($mime) {
            'image/jpeg' => imagejpeg($dst, $dir . '/' . $fileName, 85),
            'image/png' => imagepng($dst, $dir . '/' . $fileName, 6),
            'image/webp' => imagewebp($dst, $dir . '/' . $fileName, 82),
        };
        if (!$saved) {
            imagedestroy($dst);
            return [0, 'Impossibile salvare l\'immagine: controlla che la cartella uploads/ sia scrivibile.'];
        }
        if ($mime === 'image/webp') {
            $webpName = $fileName;
        } elseif (function_exists('imagewebp') && imagewebp($dst, $dir . '/' . $base . '.webp', 82)) {
            $webpName = $base . '.webp';
        }
        imagedestroy($dst);
        [$w, $h] = [$nw, $nh];
    } else {
        if (!move_uploaded_file($tmp, $dir . '/' . $fileName)) {
            return [0, 'Impossibile salvare l\'immagine: controlla che la cartella uploads/ sia scrivibile.'];
        }
        if ($mime === 'image/webp') {
            $webpName = $fileName;
        }
    }
    @chmod($dir . '/' . $fileName, 0644);
    if ($webpName !== '') {
        @chmod($dir . '/' . $webpName, 0644);
    }

    q(
        'INSERT INTO ' . t('media') . ' (file, webp, original_name, mime, width, height, size, alt, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$fileName, $webpName, clean_text((string) ($file['name'] ?? ''), 190), $mime, $w, $h, (int) filesize($dir . '/' . $fileName), $alt, $userId, now()]
    );
    return [(int) db()->lastInsertId(), null];
}

function media_fix_orientation(GdImage $img, string $path, int &$w, int &$h): GdImage
{
    $exif = @exif_read_data($path);
    $o = (int) ($exif['Orientation'] ?? 1);
    $angle = match ($o) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
    if ($angle !== 0) {
        $rot = imagerotate($img, $angle, 0);
        if ($rot) {
            imagedestroy($img);
            $img = $rot;
            $w = imagesx($img);
            $h = imagesy($img);
        }
    }
    return $img;
}

/** Dove è usata un'immagine (bozza o versione pubblicata). */
function media_usages(int $id): array
{
    $uses = [];
    foreach (['draft' => 'bozza', 'published' => 'pubblicata'] as $which => $label) {
        $c = content_all($which);
        if ((int) ($c['seo']['og_image'] ?? 0) === $id) {
            $uses[] = 'Immagine Open Graph (SEO, ' . $label . ')';
        }
        foreach ($c['giornata']['cards'] ?? [] as $card) {
            if ((int) ($card['image'] ?? 0) === $id) {
                $uses[] = 'Una giornata: "' . ($card['title'] ?? '') . '" (' . $label . ')';
            }
        }
    }
    return array_values(array_unique($uses));
}

function media_delete(int $id): bool
{
    $m = media_get($id);
    if (!$m) {
        return false;
    }
    foreach (array_unique(array_filter([$m['file'], $m['webp']])) as $f) {
        $path = CM_ROOT . '/uploads/' . basename((string) $f);
        if (is_file($path)) {
            @unlink($path);
        }
    }
    q('DELETE FROM ' . t('media') . ' WHERE id = ?', [$id]);
    return true;
}
