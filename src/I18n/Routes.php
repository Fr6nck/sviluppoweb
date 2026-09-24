<?php

declare(strict_types=1);

namespace ArcoDelVento\I18n;

/**
 * La mappa degli indirizzi, una riga per pagina e una colonna per lingua.
 *
 * Le pagine non sono duplicate per lingua: esiste una vista sola, e questa
 * tabella dice come si chiama il suo indirizzo in italiano, in inglese e —
 * quando le traduzioni saranno pronte — in spagnolo. Aggiungere lo spagnolo
 * vuol dire aggiungere una colonna qui e un file in content/lang/.
 */
final class Routes
{
    /** @var array<string, array<string,string>> chiave di pagina => lingua => segmento */
    private const MAP = [
        'home'     => ['it' => '',              'en' => '',            'es' => ''],
        'rooms'    => ['it' => 'camere',        'en' => 'rooms',       'es' => 'habitaciones'],
        'property' => ['it' => 'la-struttura',  'en' => 'the-house',   'es' => 'la-casa'],
        'assisi'   => ['it' => 'assisi',        'en' => 'assisi',      'es' => 'asis'],
        'info'     => ['it' => 'informazioni',  'en' => 'information', 'es' => 'informacion'],
        'book'     => ['it' => 'prenota',       'en' => 'book',        'es' => 'reservar'],
        'contact'  => ['it' => 'contatti',      'en' => 'contact',     'es' => 'contacto'],
        'privacy'  => ['it' => 'privacy',       'en' => 'privacy',     'es' => 'privacidad'],
    ];

    /** La pagina di una camera vive sotto quella dell'elenco: /it/camere/camera-01 */
    public const ROOM_PARENT = 'rooms';

    /**
     * La cartella del sito sul server, senza barra finale: «» alla radice del
     * dominio, «/assisiapartment» in una sottocartella. Si imposta una volta,
     * all'avvio, da APP_URL.
     */
    private static string $base = '';

    public static function setBase(string $base): void
    {
        $base = '/' . trim($base, '/');
        self::$base = $base === '/' ? '' : $base;
    }

    public static function base(): string
    {
        return self::$base;
    }

    /**
     * Toglie la cartella dal percorso di una richiesta, perché le rotte si
     * riconoscono sempre come se il sito stesse alla radice.
     */
    public static function stripBase(string $path): string
    {
        if (self::$base === '') {
            return $path;
        }
        if ($path === self::$base) {
            return '/';
        }
        if (str_starts_with($path, self::$base . '/')) {
            return substr($path, strlen(self::$base));
        }

        return $path;
    }

    /** @return list<string> */
    public static function pages(): array
    {
        return array_keys(self::MAP);
    }

    public static function exists(string $page): bool
    {
        return isset(self::MAP[$page]);
    }

    public static function segment(string $page, string $locale): string
    {
        return self::MAP[$page][$locale] ?? self::MAP[$page]['it'] ?? '';
    }

    /**
     * Indirizzo assoluto (relativo al dominio) di una pagina.
     *
     * @param array<string,string> $params  ad esempio ['slug' => 'camera-01']
     * @param array<string,string> $query
     */
    public static function url(string $page, string $locale, array $params = [], array $query = []): string
    {
        $segments = [$locale];

        if ($page === 'room') {
            $segments[] = self::segment(self::ROOM_PARENT, $locale);
            $segments[] = $params['slug'] ?? '';
        } else {
            $segment = self::segment($page, $locale);
            if ($segment !== '') {
                $segments[] = $segment;
            }
        }

        $path = '/' . implode('/', array_filter($segments, static fn (string $s): bool => $s !== ''));
        // La home di una lingua tiene la barra finale: /it/ e non /it
        if ($page === 'home') {
            $path = rtrim($path, '/') . '/';
        }
        $path = self::$base . $path;

        return $query === [] ? $path : $path . '?' . http_build_query($query);
    }

    /**
     * Riconosce il percorso di una richiesta.
     *
     * @return array{locale:string,page:string,params:array<string,string>}|null
     */
    public static function match(string $path, array $locales): ?array
    {
        $parts  = array_values(array_filter(explode('/', trim($path, '/')), static fn (string $s): bool => $s !== ''));
        $locale = $parts[0] ?? null;

        if ($locale === null || !in_array($locale, $locales, true)) {
            return null;
        }

        $rest = array_slice($parts, 1);

        if ($rest === []) {
            return ['locale' => $locale, 'page' => 'home', 'params' => []];
        }

        // /{lingua}/{camere}/{slug} — la pagina di una singola camera.
        if (count($rest) === 2 && $rest[0] === self::segment(self::ROOM_PARENT, $locale)) {
            return ['locale' => $locale, 'page' => 'room', 'params' => ['slug' => $rest[1]]];
        }

        if (count($rest) === 1) {
            foreach (self::MAP as $page => $byLocale) {
                if (($byLocale[$locale] ?? null) === $rest[0] && $rest[0] !== '') {
                    return ['locale' => $locale, 'page' => $page, 'params' => []];
                }
            }
        }

        return null;
    }
}
