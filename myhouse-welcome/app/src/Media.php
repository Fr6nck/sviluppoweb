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
        if ($err !== UPLOAD_ERR_OK) throw new \RuntimeException(self::erroreCaricamento((int) $err));
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) throw new \RuntimeException('Caricamento non riuscito.');
        return $tmp;
    }

    /** 9K · Gli errori di PHP sul caricamento, in italiano chiaro: cosa è successo e cosa fare. */
    public static function erroreCaricamento(int $err): string
    {
        return match ($err) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Il file è troppo pesante per il server (al massimo ' . self::mb(self::limiteCaricamento()) . '): riducilo e riprova.',
            UPLOAD_ERR_PARTIAL => 'Il caricamento si è interrotto a metà: controlla la connessione e riprova.',
            UPLOAD_ERR_NO_FILE => 'Nessun file scelto.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'Il server non riesce a salvare il file in questo momento: riprova tra poco, e se si ripete scrivi all\'assistenza.',
            UPLOAD_ERR_EXTENSION => 'Il server ha bloccato questo file: prova a salvarlo di nuovo come JPG o PDF.',
            default => 'Caricamento non riuscito. Riprova.',
        };
    }

    /** I byte di un valore di php.ini (2M, 512K, 1G, -1 = senza limite → PHP_INT_MAX). */
    public static function byte(string $v): int
    {
        $v = trim($v);
        if ($v === '' || $v === '-1') return PHP_INT_MAX;
        $n = (int) $v;
        return match (strtolower(substr($v, -1))) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
    }

    public static function mb(int $b): string { return $b === PHP_INT_MAX ? 'senza limite' : rtrim(rtrim(number_format($b / 1048576, 1, ',', ''), '0'), ',') . ' MB'; }

    /** Il file più pesante che arriva davvero: il più basso tra upload_max_filesize e post_max_size. */
    public static function limiteCaricamento(): int
    {
        return min(self::byte((string) ini_get('upload_max_filesize')), self::byte((string) ini_get('post_max_size')));
    }

    /**
     * 9K · I limiti di PHP per le foto (Quadro e Diagnostica): [nome, valore, consigliato, basso?].
     * @return list<array{0:string,1:string,2:string,3:bool}>
     */
    public static function limitiServer(): array
    {
        $out = [];
        foreach (['upload_max_filesize' => '20M', 'post_max_size' => '20M', 'memory_limit' => '256M'] as $k => $min) {
            $v = (string) ini_get($k);
            $out[] = [$k, $v, $min, self::byte($v) < self::byte($min)];
        }
        return $out;
    }

    /**
     * @param string $purpose logo | cover | profile | section | place
     */
    public static function storeImage(array $file, int $accountId, ?int $propertyId, string $alt, string $purpose): int
    {
        [$bytes, $mime, $ext, $w, $h] = self::prepareImage($file, $purpose);
        return self::save($bytes, $mime, $ext, 'image', $accountId, $propertyId, $alt, (string) ($file['name'] ?? ''), $w, $h);
    }

    /**
     * Un'immagine che non appartiene a un cliente (la foto di una testimonianza,
     * caricata dall'amministratore): stessi controlli, salvata nello storage
     * senza riga in media. @return array{0:string,1:string} [driver, chiave]
     */
    public static function storeFreeImage(array $file, string $cartella): array
    {
        [$bytes, $mime, $ext] = self::prepareImage($file, 'section');
        $store = Storages::current();
        $key = preg_replace('/[^a-z0-9-]/', '', $cartella) . '/' . gmdate('Y') . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
        $store->put($key, $bytes, $mime);
        return [$store->name(), $key];
    }

    /** Controlla e ridisegna un'immagine caricata. @return array{0:string,1:string,2:string,3:int,4:int} */
    private static function prepareImage(array $file, string $purpose): array
    {
        $tmp = self::upload($file);
        $cfg = Config::get('storage');
        if (filesize($tmp) > $cfg['max_image_bytes']) {
            throw new \RuntimeException('Immagine troppo pesante: il limite è ' . round($cfg['max_image_bytes'] / 1048576) . ' MB.');
        }
        $info = @getimagesize($tmp);
        if (!$info || !isset(self::IMG[$info['mime']])) throw new \RuntimeException('Formati accettati: JPG, PNG, WebP.');
        [$w, $h] = $info;
        if ($w < 1 || $h < 1 || $w * $h > self::MAX_PIXELS) throw new \RuntimeException('La foto è troppo grande per essere lavorata: riducila sotto i 4000 pixel sul lato lungo.');
        // 9K · GD tiene in memoria circa 5 byte per pixel (più la copia ridotta): prima di aprire la foto si controlla
        // che ci stia; se no si prova ad alzare il limite a 256 MB, e se non basta lo si dice invece di fermarsi a metà.
        $serve = $w * $h * 5 + 8 * 1048576;
        $libera = fn() => self::byte((string) ini_get('memory_limit')) - memory_get_usage(true);
        if ($serve > $libera()) {
            @ini_set('memory_limit', '256M');
            if ($serve > $libera()) throw new \RuntimeException('La foto è troppo grande per essere lavorata: riducila sotto i 4000 pixel sul lato lungo.');
        }

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
        return [$bytes, $mime, $ext, $w, $h];
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

    /**
     * Una copia indipendente di un file già salvato, con una chiave nuova, nello
     * storage di adesso (copiando una struttura: eliminare l'una non rompe l'altra).
     * $scritti raccoglie [driver, chiave] di ogni oggetto scritto, per poterlo
     * togliere se l'operazione intera non va in porto.
     */
    public static function duplicate(int $id, int $accountId, int $propertyId, array &$scritti): ?int
    {
        $m = Db::one('SELECT * FROM media WHERE id = ? AND account_id = ?', [$id, $accountId]);
        if (!$m) return null;
        if ($m['object_key'] !== '') $bytes = Storages::for($m['storage'])->get($m['object_key']);
        else {
            $bytes = @file_get_contents(Config::get('uploads_dir') . '/' . basename((string) $m['filename']));
            if ($bytes === false) throw new \RuntimeException('Un file da copiare non si trova più.');
        }
        $ext = pathinfo((string) $m['object_key'] ?: (string) $m['filename'], PATHINFO_EXTENSION) ?: ($m['kind'] === 'pdf' ? 'pdf' : 'jpg');
        $store = Storages::current();
        $key = Storages::newKey($accountId, $propertyId, $ext);
        $nome = preg_replace('/[^A-Za-z0-9._-]+/', '-', pathinfo((string) $m['original_name'], PATHINFO_FILENAME)) ?: 'documento';
        $store->put($key, $bytes, $m['mime'], $m['kind'] === 'pdf' ? 'inline; filename="' . mb_substr($nome, 0, 60) . '.pdf"' : '');
        $scritti[] = [$store->name(), $key];
        return Db::insert('media', [
            'account_id' => $accountId, 'property_id' => $propertyId,
            'filename' => $store->name() === 'local' ? LocalStorage::fileName($key) : '',
            'object_key' => $key, 'storage' => $store->name(), 'kind' => $m['kind'], 'mime' => $m['mime'],
            'bytes' => strlen($bytes), 'width' => (int) $m['width'], 'height' => (int) $m['height'],
            'alt' => (string) $m['alt'], 'original_name' => (string) $m['original_name'], 'created_at' => Support::now(),
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

    /**
     * 9B · L'indirizzo di una versione ridotta (lato massimo $max px) di un'immagine, servita da /img/{id}/{max}
     * e tenuta in cache in storage/cache/ridotte. Senza GD, o se non è un'immagine, l'originale.
     */
    public static function ridotta(?int $id, int $max = 800): ?string
    {
        if (!$id) return null;
        if (!function_exists('imagecreatefromstring')) return self::url($id);
        $max = max(80, min(1600, $max));
        return Support::url('/img/' . $id . '/' . $max);
    }

    /** I byte della versione ridotta (JPEG o PNG per i loghi trasparenti): dalla cache, o creata adesso. @return array{0:string,1:string}|null [bytes, mime] */
    public static function ridottaBytes(int $id, int $max = 800): ?array
    {
        $max = max(80, min(1600, $max));
        $m = self::row($id);
        if (!$m) return null;
        $dir = MHW_APP . '/storage/cache/ridotte';
        $png = str_contains((string) ($m['mime'] ?? ''), 'png');
        $file = $dir . '/' . $id . '-' . $max . ($png ? '.png' : '.jpg');
        if (is_file($file)) return [(string) file_get_contents($file), $png ? 'image/png' : 'image/jpeg'];
        $bytes = self::bytes($id);
        if ($bytes === null || !function_exists('imagecreatefromstring')) return null;
        $src = @imagecreatefromstring($bytes);
        if (!$src) return [$bytes, (string) ($m['mime'] ?? 'image/jpeg')];
        $w = imagesx($src); $h = imagesy($src);
        $scala = min(1, $max / max(1, max($w, $h)));
        $nw = max(1, (int) round($w * $scala)); $nh = max(1, (int) round($h * $scala));
        $dst = imagecreatetruecolor($nw, $nh);
        if ($png) { imagealphablending($dst, false); imagesavealpha($dst, true); imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127)); }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        if ($png) imagepng($dst, null, 7); else imagejpeg($dst, null, 82);
        $out = (string) ob_get_clean();
        imagedestroy($src); imagedestroy($dst);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        @file_put_contents($file, $out);
        return [$out, $png ? 'image/png' : 'image/jpeg'];
    }

    /** I byte di un file salvato (per il logo nel PDF del QR, 7C), o null se non si trova. */
    public static function bytes(?int $id): ?string
    {
        $m = self::row($id);
        if (!$m) return null;
        try {
            if ($m['object_key'] !== '') return Storages::for($m['storage'])->get($m['object_key']);
            $b = @file_get_contents(Config::get('uploads_dir') . '/' . basename((string) $m['filename']));
            return $b === false ? null : $b;
        } catch (\Throwable $e) { Log::exception($e, 'Media::bytes'); return null; }
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

    /**
     * Un file che il pannello non usa più (foto tolta, sezione eliminata…). Se la
     * guida PUBBLICATA lo mostra ancora resta dov'è, altrimenti gli ospiti
     * vedrebbero un'immagine rotta fino alla prossima pubblicazione: lo toglie
     * pulisciOrfani() quando la guida si ripubblica.
     */
    public static function rilascia(int $id, int $accountId): void
    {
        $pid = (int) Db::val('SELECT property_id FROM media WHERE id = ? AND account_id = ?', [$id, $accountId], 0);
        if ($pid && in_array($id, Guide::mediaPubblicati($pid), true)) return;
        self::delete($id, $accountId);
    }

    /**
     * Dopo una pubblicazione: i file della struttura che non usa più nessuno —
     * né il pannello né la guida appena pubblicata. Solo quelli caricati da più
     * di un'ora, per non toccare un caricamento ancora in corso.
     */
    public static function pulisciOrfani(int $propertyId): int
    {
        $p = Db::one('SELECT * FROM properties WHERE id = ?', [$propertyId]);
        if (!$p) return 0;
        $usati = array_filter([(int) $p['cover_media_id'], (int) $p['logo_media_id'], (int) ($p['profile_media_id'] ?? 0)]);
        foreach (Db::all('SELECT id, kind, data, media_id, pdf_media_id FROM sections WHERE property_id = ?', [$propertyId]) as $s) {
            $usati = array_merge($usati, array_filter([(int) $s['media_id'], (int) $s['pdf_media_id']]),
                                 SectionCatalog::mediaIds($s['kind'], json_decode((string) $s['data'], true) ?: []));
            foreach (Db::all('SELECT media_id FROM places WHERE section_id = ? AND media_id IS NOT NULL', [$s['id']]) as $pl) $usati[] = (int) $pl['media_id'];
        }
        $usati = array_merge($usati, Guide::mediaPubblicati($propertyId));
        $n = 0;
        $prima = gmdate('Y-m-d\TH:i:s\Z', time() - 3600);
        foreach (Db::all('SELECT id FROM media WHERE property_id = ? AND created_at < ?', [$propertyId, $prima]) as $m) {
            if (in_array((int) $m['id'], $usati, true)) continue;
            self::delete((int) $m['id'], (int) $p['account_id']); $n++;
        }
        return $n;
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
