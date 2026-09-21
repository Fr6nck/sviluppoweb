<?php
namespace MHW;

final class Support
{
    public static function now(): string { return gmdate('Y-m-d\TH:i:s\Z'); }
    public static function today(): string { return gmdate('Y-m-d'); }

    public static function e(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function slug(string $s): string
    {
        $s = \iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        $s = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $s) ?? '');
        return trim($s, '-') ?: 'casa';
    }

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = self::slug($base); $try = $slug; $n = 1;
        while (true) {
            $row = Db::one('SELECT id FROM properties WHERE slug = ?', [$try]);
            if (!$row || ($ignoreId !== null && (int) $row['id'] === $ignoreId)) return $try;
            $try = $slug . '-' . (++$n);
        }
    }

    public static function token(int $bytes = 9): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', 'ab'), '=');
    }

    /**
     * La cartella in cui vive l'applicazione: '' sulla radice del dominio,
     * '/welcomebook' in una sottocartella. Serve per CAPIRE le richieste.
     */
    public static function baseDir(): string
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $forced = Config::get('base_path');
        if (is_string($forced) && trim($forced, '/') !== '') return $cache = '/' . trim($forced, '/');
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        return $cache = ($dir === '' || $dir === '.') ? '' : $dir;
    }

    /**
     * Il prefisso da mettere davanti agli indirizzi che GENERIAMO.
     *
     * Non tutti i server riscrivono gli indirizzi: dove .htaccess viene
     * ignorato, /welcomebook/accedi non esiste e solo
     * /welcomebook/index.php/accedi funziona. Qui ce ne accorgiamo da soli.
     *
     * Nel dubbio si sceglie la forma con index.php, perche' quella funziona
     * su ENTRAMBE le configurazioni: e' il router a toglierla.
     */
    public static function base(): string
    {
        static $cache = null;
        if ($cache !== null) return $cache;

        $dir = self::baseDir();
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');

        $scelta = Config::get('pretty_urls');            // true | false | null = da solo
        if ($scelta === true)  return $cache = $dir;
        if ($scelta === false) return $cache = $script;

        $req = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
        // La richiesta e' arrivata riscritta: il server sa farlo, usiamo gli indirizzi puliti.
        if ($req !== $dir && $req !== $dir . '/' && !str_starts_with($req, $script)) {
            return $cache = $dir;
        }
        return $cache = $script;
    }

    /** Un indirizzo interno, sempre corretto anche in sottocartella. */
    public static function url(string $path = '/'): string
    {
        if ($path === '' || $path[0] !== '/') $path = '/' . $path;
        return self::base() . $path;
    }

    public static function redirect(string $to): never
    {
        // Gli indirizzi esterni (Stripe) passano intatti.
        if ($to !== '' && $to[0] === '/') $to = self::url($to);
        header('Location: ' . $to); exit;
    }

    public static function json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit;
    }

    public static function baseUrl(): string
    {
        $cfg = Config::get('base_url');
        if ($cfg) return rtrim($cfg, '/');
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return ($https ? 'https://' : 'http://') . $host . self::base();
    }

    public static function money(int $cents, string $currency = 'EUR'): string
    {
        $sym = ['EUR' => '€', 'USD' => '$', 'GBP' => '£'][$currency] ?? $currency . ' ';
        return $sym . number_format($cents / 100, ($cents % 100 === 0) ? 0 : 2, ',', '.');
    }

    public static function flash(?string $msg = null, string $kind = 'ok'): ?array
    {
        if ($msg !== null) { $_SESSION['flash'] = ['msg' => $msg, 'kind' => $kind]; return null; }
        $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
    }
}

/** Scorciatoia per le viste: u('/pannello') tiene conto della sottocartella. */
function u(string $path = '/'): string { return Support::url($path); }

/** Solo il prefisso della sottocartella: '' oppure '/welcomebook'. */
function b(): string { return Support::base(); }

/**
 * Per i FILE veri (il foglio di stile, le immagini fisse): mai index.php
 * davanti. Senza argomenti restituisce solo il prefisso, come b(), cosi'
 * nel markup si scrive <?= a() ?>/assets/... senza doppie barre.
 */
function a(string $path = ''): string
{
    if ($path === '') return Support::baseDir();
    if ($path[0] !== '/') $path = '/' . $path;
    return Support::baseDir() . $path;
}
