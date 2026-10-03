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

    /**
     * Il percorso della rotta, tolti la sottocartella e l'eventuale index.php:
     * /welcomebook/index.php/webhook/stripe e /webhook/stripe diventano la
     * stessa cosa. Lo usano il router E l'esenzione CSRF del webhook: se i due
     * calcoli fossero diversi, in una sottocartella Stripe riceverebbe un 419.
     */
    public static function routePath(?string $uri = null): string
    {
        $path = (string) (parse_url($uri ?? (string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
        $dir = self::baseDir();
        if ($dir !== '' && str_starts_with($path, $dir)) $path = substr($path, strlen($dir));
        if (str_starts_with($path, '/index.php')) $path = substr($path, strlen('/index.php'));
        return '/' . trim($path, '/');
    }

    /** Le pagine degli ospiti: niente sessione, niente cookie, niente indicizzazione. */
    public static function isGuestPath(string $route): bool
    {
        return (bool) preg_match('#^/(g|q|qr|media)(/|$)#', $route);
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

    /** 87 €, 117,50 € — il simbolo dopo, come si scrive in italiano. */
    public static function money(int $cents, string $currency = 'EUR'): string
    {
        $sym = ['EUR' => '€', 'USD' => '$', 'GBP' => '£'][$currency] ?? $currency;
        return number_format($cents / 100, ($cents % 100 === 0) ? 0 : 2, ',', '.') . "\u{00A0}" . $sym;
    }

    /** Una data leggibile: 26 settembre 2026. */
    public static function date(?string $iso): string
    {
        if (!$iso) return '—';
        $t = strtotime($iso); if (!$t) return '—';
        $mesi = ['gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre'];
        return (int) gmdate('j', $t) . ' ' . $mesi[(int) gmdate('n', $t) - 1] . ' ' . gmdate('Y', $t);
    }

    /** Un indirizzo web accettabile per un link: solo http e https. */
    public static function safeUrl(string $u): string
    {
        $u = trim($u);
        if ($u === '') return '';
        if (!preg_match('#^https?://#i', $u)) $u = 'https://' . ltrim($u, '/');
        return filter_var($u, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $u) ? $u : '';
    }

    /** Un numero di telefono per tel: solo cifre e il più iniziale. */
    public static function telHref(string $n): string
    {
        return preg_replace('/(?!^\+)[^0-9]/', '', trim($n)) ?? '';
    }

    public static function json_attr(mixed $v): string
    {
        return htmlspecialchars((string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
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

/**
 * Come a(), ma per il foglio di stile e gli script: aggiunge ?v= con la data
 * del file. Dopo un aggiornamento via FTP il browser (e la cache del server)
 * prende subito il file nuovo, invece di tenersi quello vecchio.
 */
function av(string $path): string
{
    if ($path[0] !== '/') $path = '/' . $path;
    $file = (defined('MHW_PUBLIC') ? MHW_PUBLIC : dirname(__DIR__) . '/public') . $path;
    $data = is_file($file) ? @filemtime($file) : false;
    return a($path) . ($data ? '?v=' . $data : '');
}
