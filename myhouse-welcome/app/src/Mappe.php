<?php
namespace MHW;

/**
 * Il link di Google Maps incollato dall'host: se ne ricavano il nome del posto
 * e le coordinate, senza API a pagamento. I link brevi (maps.app.goo.gl) si
 * seguono dal server, al massimo 5 secondi e solo verso indirizzi Google.
 * Se qualcosa non torna non è un errore: semplicemente non si compila niente.
 *
 * Con le coordinate della struttura (dal link in «Come arrivare») si stimano
 * i minuti a piedi: distanza in linea d'aria × 1,3, a 4,5 km/h. È una stima,
 * e nel modulo si dice: l'host la corregge se serve.
 */
final class Mappe
{
    private const SECONDI = 5;

    /**
     * Un indirizzo di Google (maps, il dominio di ogni paese, i link brevi)?
     * Niente utente, password o porta, niente barre rovesciate, spazi o caratteri
     * di controllo: sono i trucchi con cui un indirizzo sembra di Google a PHP e
     * porta altrove per curl.
     */
    public static function diGoogle(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 2000 || preg_match('/[\\\\\s\x00-\x1f\x7f]/', $url)) return false;
        $p = parse_url($url);
        if (!$p || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true)) return false;
        if (isset($p['user']) || isset($p['pass']) || isset($p['port'])) return false;
        if (str_contains(substr($url, strlen($p['scheme']) + 3, strcspn(substr($url, strlen($p['scheme']) + 3), '/?#')), '@')) return false;
        $host = strtolower($p['host'] ?? '');
        return in_array($host, ['maps.app.goo.gl', 'goo.gl'], true)
            || (bool) preg_match('/(^|\.)google\.(com|[a-z]{2}|com?\.[a-z]{2})$/', $host);
    }

    /** L'indirizzo ricostruito dai suoi pezzi: curl riceve esattamente quello che si è controllato. */
    private static function ricostruito(string $url): string
    {
        $p = parse_url(trim($url));
        return 'https://' . strtolower($p['host']) . ($p['path'] ?? '/') . (isset($p['query']) ? '?' . $p['query'] : '');
    }

    private static function breve(string $url): bool
    {
        return in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), ['maps.app.goo.gl', 'goo.gl'], true);
    }

    /**
     * Nome e coordinate da un link di Google Maps; segue i link brevi.
     * @return array{name:string,lat:?float,lng:?float}
     */
    public static function leggi(string $url): array
    {
        $vuoto = ['name' => '', 'lat' => null, 'lng' => null];
        $url = trim($url);
        if (!self::diGoogle($url)) return $vuoto;
        if (self::breve($url)) $url = self::segui($url) ?? '';
        return $url !== '' ? self::estrai($url) : $vuoto;
    }

    /**
     * Legge un link già lungo, senza rete: /place/<nome>/, poi le coordinate
     * del posto (!3d…!4d…), quelle della mappa (@lat,lng) o quelle nella ricerca (q=lat,lng).
     * @return array{name:string,lat:?float,lng:?float}
     */
    public static function estrai(string $url): array
    {
        $out = ['name' => '', 'lat' => null, 'lng' => null];
        if (preg_match('#/place/([^/@?]+)#', $url, $m)) {
            $out['name'] = mb_substr(trim(str_replace('+', ' ', rawurldecode($m[1]))), 0, 160);
        }
        $num = '(-?\d{1,3}(?:\.\d+)?)';
        if (preg_match('/!3d' . $num . '!4d' . $num . '/', $url, $m)
            || preg_match('/@' . $num . ',' . $num . '/', $url, $m)
            || preg_match('/[?&](?:q|query|ll|destination)=' . $num . '(?:,|%2C)\s*' . $num . '/i', $url, $m)) {
            $lat = (float) $m[1]; $lng = (float) $m[2];
            if (abs($lat) <= 90 && abs($lng) <= 180 && ($lat != 0.0 || $lng != 0.0)) { $out['lat'] = $lat; $out['lng'] = $lng; }
        }
        return $out;
    }

    /** Segue i rimandi di un link breve, uno alla volta, controllando che restino su Google. */
    private static function segui(string $url): ?string
    {
        if (!function_exists('curl_init')) return null;
        $fine = microtime(true) + self::SECONDI;
        for ($salti = 0; $salti < 5; $salti++) {
            $resta = $fine - microtime(true);
            if ($resta <= 0) return null;
            if (!self::diGoogle($url)) return null;
            $ch = curl_init(self::ricostruito($url));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_NOBODY => false,
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT_MS => (int) ($resta * 1000), CURLOPT_CONNECTTIMEOUT_MS => (int) ($resta * 1000),
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_USERAGENT => 'MyHouseWelcome/1.0',
                CURLOPT_RANGE => '0-2047',
            ]);
            $risposta = curl_exec($ch);
            $codice = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $dove = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            curl_close($ch);
            if ($risposta === false) return null;
            if ($codice < 300 || $codice >= 400 || $dove === '') return self::breve($url) ? null : $url;
            if (!self::diGoogle($dove)) return null;
            // In Europa Google passa a volte dalla pagina del consenso: il link vero è in «continue».
            if (str_starts_with(strtolower((string) parse_url($dove, PHP_URL_HOST)), 'consent.')) {
                parse_str((string) parse_url($dove, PHP_URL_QUERY), $q);
                $dove = (string) ($q['continue'] ?? '');
                if (!self::diGoogle($dove)) return null;
            }
            $url = $dove;
            if (!self::breve($url) && self::estrai($url)['lat'] !== null) return $url;
        }
        return self::breve($url) ? null : $url;
    }

    /** I minuti a piedi stimati, o null se manca un punto o la distanza non è da fare a piedi. */
    public static function minutiAPiedi(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2): ?int
    {
        if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) return null;
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1); $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $km = 2 * $r * asin(min(1.0, sqrt($a)));
        $minuti = (int) round($km * 1.3 / 4.5 * 60);
        return $minuti > 120 ? null : max(1, $minuti);
    }

    /** Le coordinate della struttura, se ci sono. @return array{0:?float,1:?float} */
    public static function struttura(array $p): array
    {
        return [isset($p['lat']) && $p['lat'] !== null && $p['lat'] !== '' ? (float) $p['lat'] : null,
                isset($p['lng']) && $p['lng'] !== null && $p['lng'] !== '' ? (float) $p['lng'] : null];
    }
}
