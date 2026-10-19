<?php
namespace MHW;

final class Support
{
    public static function now(): string { return gmdate('Y-m-d\TH:i:s\Z'); }

    /** L'indirizzo unico per assistenza, suggerimenti e preventivi (config contatti.email). */
    public static function contatto(): string { return (string) ((Config::get('contatti') ?? [])['email'] ?? ''); }

    /** 9H · Uno degli indirizzi del sito (contatti.*): vuoto o non valido = l'email assistenza. */
    private static function indirizzo(string $chiave): string
    {
        $c = trim((string) ((Config::get('contatti') ?? [])[$chiave] ?? ''));
        return filter_var($c, FILTER_VALIDATE_EMAIL) ? $c : self::contatto();
    }

    /** 9H · MyHouse siti, preventivi, convenzioni e codici omaggio. */
    public static function commerciale(): string { return self::indirizzo('commerciale'); }

    /** 9H · Il mittente di newsletter e campagne (Brevo): il sito non manda email da qui. */
    public static function newsletter(): string { return self::indirizzo('newsletter'); }

    /** 9H · Fatture, ricevute, pagamenti, rinnovi e rimborsi. */
    public static function amministrazione(): string { return self::indirizzo('amministrazione'); }

    /** 9H · La frase delle email di pagamento: dove scrivere per fatture e pagamenti. */
    public static function fraseFatture(bool $en = false): string
    {
        return ($en ? 'Questions about invoices and payments: ' : 'Per domande su fatture e pagamenti: ') . self::amministrazione() . '.';
    }

    /** 9H · Un link mailto: all'email commerciale con oggetto e corpo già scritti. */
    public static function mailtoCommerciale(string $oggetto, string $corpo = ''): string
    {
        return 'mailto:' . self::commerciale() . '?subject=' . rawurlencode($oggetto) . ($corpo !== '' ? '&body=' . rawurlencode($corpo) : '');
    }

    /** Un link mailto: all'assistenza con oggetto e corpo già scritti. */
    public static function mailto(string $oggetto, string $corpo = ''): string
    {
        // 9I · «[MW-7K4P2] …»: chi risponde riconosce subito il cliente.
        $cod = self::codiceCollegato();
        if ($cod !== '' && !str_contains($oggetto, $cod)) $oggetto = '[' . $cod . '] ' . $oggetto;
        return 'mailto:' . self::contatto() . '?subject=' . rawurlencode($oggetto) . ($corpo !== '' ? '&body=' . rawurlencode($corpo) : '');
    }

    /** 9I · Il codice del cliente collegato (anche se è l'amministrazione che guarda con il suo account); '' altrimenti. */
    public static function codiceCollegato(): string
    {
        try {
            if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['uid']) || !Codici::disponibili()) return '';
            return (string) Db::val("SELECT a.code FROM accounts a JOIN users u ON u.id = a.user_id WHERE u.id = ? AND u.role = 'host'", [(int) $_SESSION['uid']], '');
        } catch (\Throwable) { return ''; }
    }
    /** «Chiedi un preventivo» per più di 10 strutture (7B): oggetto e domande già scritti. */
    public static function mailtoPreventivo(): string
    {
        return self::mailtoCommerciale('Preventivo Portfolio oltre 10 strutture', "Ciao, gestisco più di 10 strutture e vorrei una proposta per MyHouse Welcome.\n\n"
            . "Quante strutture:\nTipologie (case vacanza, B&B, affittacamere…):\nZona:\nLingue che mi servono:\nCosa mi serve in particolare:\n\nNome e telefono:");
    }
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

    /**
     * L'indirizzo di una guida nuova (6M): nome + «-» + 4 caratteri casuali senza lettere ambigue,
     * così non si indovina partendo dal nome della struttura (casa-lucia-7k3f).
     */
    public static function slugGuida(string $nome, ?int $ignoreId = null): string
    {
        $base = self::slug($nome);
        $alfabeto = 'abcdefghjkmnpqrstuvwxyz23456789';
        while (true) {
            $coda = '';
            for ($i = 0; $i < 4; $i++) $coda .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            $prova = $base . '-' . $coda;
            $row = Db::one('SELECT id FROM properties WHERE slug = ?', [$prova]);
            if (!$row || ($ignoreId !== null && (int) $row['id'] === $ignoreId)) return $prova;
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

    /** Le guide (e foto, PDF e QR che contengono) restano fuori da motori e assistenti: intestazione e meta (6M). */
    public const ROBOTS_GUIDE = 'noindex, nofollow, noarchive, nosnippet, noimageindex';

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
        // 9K · Un modulo con una foto mandato da foto-leggere.js (con la barra di caricamento): l'indirizzo
        // in JSON, e ci va la pagina, così il messaggio si legge una volta sola dove deve.
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_SERVER['HTTP_X_MHW_CARICA'])) {
            header('Content-Type: application/json; charset=utf-8'); echo json_encode(['vai' => $to], JSON_UNESCAPED_SLASHES); exit;
        }
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
        // Con l'indirizzo impostato si tiene index.php solo se il sito lo usa (indirizzi puliti spenti e non riconosciuti).
        if ($cfg) return rtrim($cfg, '/') . (str_ends_with(self::base(), '/index.php') ? '/index.php' : '');
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return ($https ? 'https://' : 'http://') . $host . self::base();
    }

    /**
     * L'indirizzo assoluto della cartella dei file (8A): per le immagini delle email e delle
     * anteprime. Mai con /index.php, anche quando gli indirizzi puliti sono spenti: lì ci sono i
     * file veri (assets/), non le rotte.
     */
    public static function radice(): string
    {
        $cfg = (string) (Config::get('base_url') ?? '');
        if ($cfg !== '') return rtrim(preg_replace('#/index\.php/?$#', '', rtrim($cfg, '/')) ?? '', '/');
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . self::baseDir();
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
        // Anche i numeri salvati prima, senza prefisso: dall'estero si chiamano solo col +39.
        return preg_replace('/(?!^\+)[^0-9]/', '', Telefono::normalizza($n)) ?? '';
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

/** 9F·11 · Un testo del pannello nella lingua del pannello (italiano, o inglese per chi scrive la guida in un'altra lingua). */
function t(string $it, ?string $en = null): string { return Pannello::t($it, $en); }

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
