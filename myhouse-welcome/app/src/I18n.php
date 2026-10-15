<?php
namespace MHW;

/**
 * Le parole dell'interfaccia della guida, nella lingua dell'ospite.
 *
 * Non i contenuti — quelli li scrive l'host — ma tutto il resto: "Rete",
 * "Copia", "Chiama", "Prima di andare", i nomi delle sezioni di catalogo.
 * Ogni stringa sta in lang/{it,en,fr,de,es}.php; se in una lingua manca,
 * si ripiega sull'inglese e poi sull'italiano, mai su una chiave nuda.
 *
 * 8C: alle cinque lingue di base (BASE) si aggiungono le aggiuntive (EXTRA). L'interfaccia è
 * tradotta per nl, pt e pl (lang/{nl,pt,pl}.php); per le altre vale l'inglese, mentre i testi
 * dell'host restano nella loro lingua.
 */
final class I18n
{
    /** Le cinque lingue di base, nell'ordine del listino. */
    public const BASE = ['it', 'en', 'fr', 'de', 'es'];
    /** Le lingue aggiuntive (una a scelta con Plus e Portfolio, o la seconda di Essential). */
    public const EXTRA = ['nl', 'pt', 'pt-BR', 'pl', 'ru', 'zh', 'ja', 'sv', 'da', 'cs'];
    public const LOCALES = [...self::BASE, ...self::EXTRA];

    /**
     * Il codice per Amazon Translate, quando non è lo stesso: «pt» per Amazon è il portoghese del Brasile,
     * quello del Portogallo è «pt-PT». Da noi «pt» è il Portogallo e «pt-BR» il Brasile.
     */
    private const AWS = ['pt' => 'pt-PT', 'pt-BR' => 'pt'];

    public static function aws(string $loc): string { return self::AWS[$loc] ?? $loc; }

    /**
     * La lingua del telefono tra quelle $disponibili, da una voce di Accept-Language («pt-BR;q=0.9»):
     * prima la variante esatta (pt-BR), poi la lingua (pt), poi un'altra variante della stessa lingua.
     */
    public static function dalTelefono(string $parte, array $disponibili): ?string
    {
        $tag = strtolower(trim(explode(';', $parte)[0]));
        if ($tag === '') return null;
        $base = substr($tag, 0, 2);
        $trova = function (string $cerca) use ($disponibili): ?string {
            foreach ($disponibili as $l) if (strtolower((string) $l) === $cerca) return (string) $l;
            return null;
        };
        if (($l = $trova($tag)) !== null) return $l;
        if (($l = $trova($base)) !== null) return $l;
        foreach ($disponibili as $l) if (str_starts_with(strtolower((string) $l), $base . '-')) return (string) $l;
        return null;
    }

    public static function base(string $loc): bool { return in_array($loc, self::BASE, true); }
    private static array $dict = [];

    private static function load(string $loc): array
    {
        if (!isset(self::$dict[$loc])) {
            $f = MHW_APP . '/lang/' . $loc . '.php';
            self::$dict[$loc] = in_array($loc, self::LOCALES, true) && is_file($f) ? (require $f) : [];
            // Fase 6G: le parole degli eventi (categorie, date, «Oggi»…).
            $x = MHW_APP . '/lang/eventi/' . $loc . '.php';
            if (self::$dict[$loc] && is_file($x)) self::$dict[$loc] = array_merge(self::$dict[$loc], require $x);
            // Riscaldamento e aria condizionata (sezione «clima»).
            $x = MHW_APP . '/lang/clima/' . $loc . '.php';
            if (self::$dict[$loc] && is_file($x)) self::$dict[$loc] = array_merge(self::$dict[$loc], require $x);
            // Fase 6: categorie, etichette, dotazioni, modi di muoversi e unità, nelle 5 lingue.
            $x = MHW_APP . '/lang/tassonomie/' . $loc . '.php';
            if (self::$dict[$loc] && is_file($x)) self::$dict[$loc] = array_merge(self::$dict[$loc], require $x);
        }
        return self::$dict[$loc];
    }

    public static function t(string $loc, string $key, mixed ...$args): string
    {
        foreach ([$loc, 'en', 'it'] as $l) {
            $d = self::load($l);
            if (isset($d[$key])) return $args ? vsprintf($d[$key], $args) : $d[$key];
        }
        return $key;
    }

    public static function has(string $loc, string $key): bool
    {
        return isset(self::load($loc)[$key]);
    }

    /** @return string[] le chiavi del dizionario di riferimento (italiano) */
    public static function keys(): array { return array_keys(self::load('it')); }
}
