<?php
namespace MHW;

/**
 * Dove si trova la struttura (8B): i portali dove gli ospiti la trovano e prenotano, e i social.
 *
 * L'host dice solo DOVE è presente: per i cinque portali noti (Google, Booking.com, Airbnb, VRBO,
 * Expedia) il link lo prepara il sistema, perché l'host non deve cercare link di recensione:
 *   - Google apre la scheda Maps cercando nome e città (lì c'è «Scrivi una recensione»);
 *   - Booking.com, Airbnb, VRBO, Expedia aprono il sito: la recensione si lascia dall'app o
 *     dall'email che il portale manda dopo il soggiorno (la pagina delle recensioni lo dice).
 * Gli indirizzi di partenza stanno in config «portali», modificabili da Amministrazione →
 * Impostazioni → Portali. Per gli altri portali (Agoda, Tripadvisor… o «Altro» con un nome) e per
 * i social l'indirizzo lo scrive l'host: solo https://, oppure @nome per i social.
 */
final class Canali
{
    /** I portali noti: il link lo costruisce il sistema. */
    public const NOTI = ['google' => 'Google', 'booking' => 'Booking.com', 'airbnb' => 'Airbnb', 'vrbo' => 'VRBO', 'expedia' => 'Expedia'];
    /** Gli altri portali dal menu «+ Aggiungi un altro portale»: l'indirizzo della scheda lo scrive l'host. */
    public const ALTRI = ['agoda' => 'Agoda', 'hotels' => 'Hotels.com', 'tripadvisor' => 'Tripadvisor', 'holidu' => 'Holidu', 'hometogo' => 'HomeToGo', 'altro' => 'Altro…'];
    public const SOCIAL = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok'];
    /** Dove la recensione si lascia dall'app o dall'email del portale, non da un link. */
    public const DALL_APP = ['booking', 'airbnb', 'vrbo', 'expedia'];
    private const SOCIAL_BASE = ['facebook' => 'https://www.facebook.com/', 'instagram' => 'https://www.instagram.com/', 'tiktok' => 'https://www.tiktok.com/@'];
    public const MAX_ALTRI = 8;

    public static function disponibili(): bool { return Migrator::tableExists('property_channels'); }

    public static function etichetta(string $kind, string $label = ''): string
    {
        if ($kind === 'altro') return $label !== '' ? $label : 'Altro sito';
        return self::NOTI[$kind] ?? self::ALTRI[$kind] ?? self::SOCIAL[$kind] ?? $kind;
    }

    public static function social(string $kind): bool { return isset(self::SOCIAL[$kind]); }

    /** Gli indirizzi di partenza dei portali noti (Amministrazione → Impostazioni → Portali). */
    public static function basi(): array
    {
        $c = Config::get('portali') ?? [];
        return [
            'google' => (string) ($c['google'] ?? 'https://www.google.com/maps/search/?api=1&query='),
            'booking' => (string) ($c['booking'] ?? 'https://www.booking.com/'),
            'airbnb' => (string) ($c['airbnb'] ?? 'https://www.airbnb.it/'),
            'vrbo' => (string) ($c['vrbo'] ?? 'https://www.vrbo.com/'),
            'expedia' => (string) ($c['expedia'] ?? 'https://www.expedia.it/'),
        ];
    }

    /** Il link di un canale: costruito per i portali noti, quello scritto dall'host per gli altri. */
    public static function link(string $kind, array $prop, string $url = ''): string
    {
        $b = self::basi();
        if ($kind === 'google') return $b['google'] . rawurlencode(trim(($prop['name'] ?? '') . ' ' . ($prop['city'] ?? '')));
        if (isset(self::NOTI[$kind])) return $b[$kind];
        return $url;
    }

    /** Le righe salvate di una struttura, in ordine. */
    public static function righe(int $propertyId): array
    {
        return self::disponibili() ? Db::all('SELECT * FROM property_channels WHERE property_id = ? ORDER BY position, id', [$propertyId]) : [];
    }

    /**
     * Per l'istantanea della guida (formato 7): [{kind, label, url, social}] con il link già pronto.
     * @return list<array{kind:string,label:string,url:string,social:bool}>
     */
    public static function perGuida(array $prop): array
    {
        $out = [];
        foreach (self::righe((int) $prop['id']) as $r) {
            $u = self::link((string) $r['kind'], $prop, (string) $r['url']);
            if ($u === '') continue;
            $out[] = ['kind' => (string) $r['kind'], 'label' => self::etichetta((string) $r['kind'], (string) $r['label']), 'url' => $u, 'social' => self::social((string) $r['kind'])];
        }
        return $out;
    }

    /** Un indirizzo social: https://… com'è, oppure @nome → l'indirizzo del profilo. '' se non va. */
    public static function urlSocial(string $kind, string $v): string
    {
        $v = trim($v);
        if ($v === '') return '';
        if (preg_match('/^@?([A-Za-z0-9._]{1,60})$/', $v, $m) && !str_contains($v, '://')) return self::SOCIAL_BASE[$kind] . $m[1];
        return self::https($v);
    }

    /** Solo https:// valido, al massimo 500 caratteri. */
    public static function https(string $v): string
    {
        $v = mb_substr(trim($v), 0, 500);
        return preg_match('#^https://[^\s/$.?\#][^\s]*$#i', $v) && filter_var($v, FILTER_VALIDATE_URL) ? $v : '';
    }

    /**
     * Salva dal modulo di Impostazioni. $in: portali[] (i noti spuntati, nell'ordine del modulo),
     * altri[i][kind|label|url] (righe ripetibili), social[kind] (indirizzo o @nome).
     * @return string[] gli errori da mostrare (le righe non valide non si salvano)
     */
    public static function salva(int $propertyId, array $in): array
    {
        if (!self::disponibili()) return [];
        $errori = []; $righe = [];
        foreach (array_values(array_unique((array) ($in['portali'] ?? []))) as $k) {
            if (isset(self::NOTI[$k])) $righe[] = ['kind' => $k, 'label' => '', 'url' => ''];
        }
        $n = 0;
        foreach ((array) ($in['altri'] ?? []) as $a) {
            if (!is_array($a)) continue;
            $kind = (string) ($a['kind'] ?? ''); $url = trim((string) ($a['url'] ?? '')); $label = mb_substr(trim((string) ($a['label'] ?? '')), 0, 80);
            if ($kind === '' && $url === '' && $label === '') continue;
            if (!isset(self::ALTRI[$kind])) continue;
            $nome = $kind === 'altro' ? $label : self::ALTRI[$kind];
            if ($kind === 'altro' && $label === '') { $errori[] = 'Scrivi il nome del portale «Altro».'; continue; }
            $buono = self::https($url);
            if ($buono === '') { $errori[] = 'L\'indirizzo di ' . $nome . ' deve cominciare con https://.'; continue; }
            if (++$n > self::MAX_ALTRI) { $errori[] = 'Al massimo ' . self::MAX_ALTRI . ' portali in più.'; break; }
            $righe[] = ['kind' => $kind, 'label' => $kind === 'altro' ? $label : '', 'url' => $buono];
        }
        foreach (self::SOCIAL as $k => $nome) {
            $v = trim((string) (((array) ($in['social'] ?? []))[$k] ?? ''));
            if ($v === '') continue;
            $u = self::urlSocial($k, $v);
            if ($u === '') { $errori[] = $nome . ': scrivi l\'indirizzo che comincia con https:// oppure @nome.'; continue; }
            $righe[] = ['kind' => $k, 'label' => '', 'url' => $u];
        }
        Db::tx(function () use ($propertyId, $righe) {
            Db::run('DELETE FROM property_channels WHERE property_id = ?', [$propertyId]);
            foreach ($righe as $i => $r) Db::insert('property_channels', $r + ['property_id' => $propertyId, 'position' => $i, 'created_at' => Support::now()]);
        });
        return $errori;
    }

    /**
     * Le istantanee di prima (fino al formato 6) avevano i link di recensione scritti dall'host:
     * diventano canali con il loro indirizzo, così la guida già pubblicata continua a funzionare.
     */
    public static function daRecensioni(array $reviews): array
    {
        $out = [];
        foreach (['google' => 'Google', 'booking' => 'Booking.com', 'airbnb' => 'Airbnb', 'other' => 'Altro sito'] as $k => $et) {
            $u = trim((string) ($reviews[$k] ?? ''));
            if ($u === '') continue;
            $out[] = ['kind' => $k === 'other' ? 'altro' : $k, 'label' => $et, 'url' => $u, 'social' => false];
        }
        return $out;
    }
}
