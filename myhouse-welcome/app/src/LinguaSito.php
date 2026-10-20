<?php
namespace MHW;

/**
 * 8E — La lingua del sito pubblico: italiano (di serie) o inglese, solo per la home e le domande
 * (/en, /en/questions), (8J) i documenti legali (/en/terms, /en/privacy, /en/dpa) e il questionario dei piani (/en/plan). Pannello e registrazione restano in italiano.
 * La rotta inglese chiama imposta('en'); le viste leggono t() per i testi fissi, TestiSito per
 * quelli modificabili, Faq::tutte() per le domande, Plans::inLingua() per le schede dei piani.
 */
final class LinguaSito
{
    /** Le pagine che esistono nelle due lingue: percorso italiano => percorso inglese. */
    public const COPPIE = ['/' => '/en', '/domande' => '/en/questions', '/termini' => '/en/terms', '/privacy' => '/en/privacy', '/dpa' => '/en/dpa',
                           '/piano' => '/en/plan'];   // il questionario «Qual è il piano giusto per te?» come pagina

    private static string $loc = 'it';

    public static function imposta(string $loc): void { self::$loc = $loc === 'en' ? 'en' : 'it'; }

    public static function get(): string { return self::$loc; }

    public static function en(): bool { return self::$loc === 'en'; }

    /** Un testo fisso della pagina nelle due lingue. */
    public static function t(string $it, string $en): string { return self::$loc === 'en' ? $en : $it; }

    /** Il percorso di una pagina nella lingua di adesso (o in $loc): «/» → «/en», «/domande» → «/en/questions». */
    public static function percorso(string $it, ?string $loc = null): string
    {
        return ($loc ?? self::$loc) === 'en' ? (self::COPPIE[$it] ?? $it) : $it;
    }

    /** Il percorso italiano di una pagina inglese («/en/questions» → «/domande»), o null. */
    public static function italiano(string $en): ?string
    {
        $k = array_search($en, self::COPPIE, true);
        return $k === false ? null : $k;
    }
}
