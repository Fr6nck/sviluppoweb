<?php
namespace MHW;

/**
 * Le parole dell'interfaccia della guida, nella lingua dell'ospite.
 *
 * Non i contenuti — quelli li scrive l'host — ma tutto il resto: "Rete",
 * "Copia", "Chiama", "Prima di andare", i nomi delle sezioni di catalogo.
 * Ogni stringa sta in lang/{it,en,fr,de,es}.php; se in una lingua manca,
 * si ripiega sull'inglese e poi sull'italiano, mai su una chiave nuda.
 */
final class I18n
{
    public const LOCALES = ['it', 'en', 'fr', 'de', 'es'];
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
