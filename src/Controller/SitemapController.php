<?php

declare(strict_types=1);

namespace ArcoDelVento\Controller;

use ArcoDelVento\App;
use ArcoDelVento\Http\Response;
use ArcoDelVento\I18n\Routes;

/**
 * sitemap.xml e robots.txt, generati.
 *
 * Generati e non scritti a mano per due ragioni: l'indirizzo del sito arriva
 * da APP_URL e cambia fra sviluppo, collaudo e produzione, e le camere
 * cambiano — una camera aggiunta a content/rooms.php entra nella mappa da
 * sola, senza che qualcuno si ricordi di aggiornare un file.
 *
 * Ogni pagina dichiara le proprie traduzioni con xhtml:link, che è il modo in
 * cui una sitemap dice a Google che /it/camere e /en/rooms sono la stessa
 * pagina in due lingue.
 */
final class SitemapController
{
    public function __construct(private readonly App $app)
    {
    }

    public function sitemap(): Response
    {
        $base    = rtrim((string) $this->app->config('app.url'), '/');
        $locales = $this->app->config('i18n.available');

        // Le pagine che vanno indicizzate. «privacy» c'è ma vale poco;
        // i passi della prenotazione non ci sono, perché non sono pagine.
        $pagine = ['home', 'rooms', 'property', 'assisi', 'info', 'book', 'contact', 'privacy'];

        $voci = [];

        foreach ($pagine as $pagina) {
            $alternative = [];
            foreach ($locales as $locale) {
                $alternative[$locale] = $base . Routes::url($pagina, $locale);
            }
            foreach ($locales as $locale) {
                $voci[] = [
                    'loc'         => $base . Routes::url($pagina, $locale),
                    'priority'    => $pagina === 'home' ? '1.0' : ($pagina === 'privacy' ? '0.3' : '0.8'),
                    'alternative' => $alternative,
                ];
            }
        }

        foreach ($this->app->rooms()->all() as $camera) {
            $alternative = [];
            foreach ($locales as $locale) {
                $slug = $camera['slug'][$locale] ?? null;
                if (is_string($slug) && $slug !== '') {
                    $alternative[$locale] = $base . Routes::url('room', $locale, ['slug' => $slug]);
                }
            }
            foreach ($alternative as $locale => $loc) {
                $voci[] = ['loc' => $loc, 'priority' => '0.7', 'alternative' => $alternative];
            }
        }

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '
              . 'xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach ($voci as $voce) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($voce['loc'], ENT_XML1) . "</loc>\n";
            foreach ($voce['alternative'] as $locale => $href) {
                $xml .= sprintf(
                    '    <xhtml:link rel="alternate" hreflang="%s" href="%s"/>' . "\n",
                    htmlspecialchars($locale, ENT_XML1),
                    htmlspecialchars($href, ENT_XML1)
                );
            }
            $xml .= '    <priority>' . $voce['priority'] . "</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>' . "\n";

        return Response::text($xml, 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $base = rtrim((string) $this->app->config('app.url'), '/');

        $righe = [
            '# Arco del Vento — affittacamere, Assisi',
            '',
            'User-agent: *',
            'Allow: /',
            '',
            '# I passi intermedi della prenotazione sono stati di un modulo, non pagine:',
            '# indicizzarli manderebbe gli ospiti a metà di un percorso, con date scadute.',
        ];

        foreach ($this->app->config('i18n.available') as $locale) {
            $righe[] = 'Disallow: ' . Routes::url('book', $locale) . '?';
        }

        $righe[] = '';
        $righe[] = 'Sitemap: ' . $base . '/sitemap.xml';
        $righe[] = '';

        return Response::text(implode("\n", $righe), 'text/plain; charset=UTF-8');
    }
}
