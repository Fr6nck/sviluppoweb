<?php

declare(strict_types=1);

namespace ArcoDelVento\Controller;

use ArcoDelVento\App;
use ArcoDelVento\Http\Request;
use ArcoDelVento\Http\Response;
use ArcoDelVento\I18n\Routes;

/**
 * Le pagine che si leggono e basta: home, camere, la singola camera, la
 * struttura, Assisi, informazioni, privacy, 404.
 */
final class PageController
{
    public function __construct(private readonly App $app)
    {
    }

    public function show(string $page, Request $request): Response
    {
        $locale = $this->app->locale();

        $data = match ($page) {
            'home'     => $this->home(),
            'rooms'    => $this->rooms(),
            'property' => [],
            'assisi'   => ['luoghi' => $this->places()],
            'info'     => ['domande' => $this->faq()],
            'privacy'  => [],
            default    => [],
        };

        $data += [
            'pagina'       => $page,
            'titoloSeo'    => $this->seoTitle($page),
            'descrizione'  => $this->app->translator()->get($page . '.seo_description'),
            'canonico'     => Routes::url($page, $locale),
            'alternative'  => $this->alternates($page),
        ];

        return Response::html($this->app->view()->page($page, $data));
    }

    public function room(Request $request, string $slug): Response
    {
        $locale = $this->app->locale();
        $room   = $this->app->rooms()->findBySlug($slug, $locale);

        if ($room === null) {
            return $this->notFound();
        }

        $name = (string) ($room['name'][$locale] ?? $room['ref']);

        return Response::html($this->app->view()->page('room', [
            'pagina'      => 'room',
            'camera'      => $room,
            'altreCamere' => array_values(array_filter(
                $this->app->rooms()->all(),
                static fn (array $r): bool => $r['ref'] !== $room['ref']
            )),
            'titoloSeo'   => $name . ' — ' . $this->app->translator()->get('common.brand'),
            'descrizione' => $this->app->translator()->get('room.seo_description', ['name' => $name]),
            'canonico'    => Routes::url('room', $locale, ['slug' => $slug]),
            'alternative' => $this->roomAlternates($room),
        ]));
    }

    public function notFound(): Response
    {
        return Response::html(
            $this->app->view()->page('404', [
                'pagina'      => '404',
                'titoloSeo'   => $this->app->translator()->get('not_found.seo_title'),
                'descrizione' => '',
                'canonico'    => null,
                'alternative' => [],
                'noindex'     => true,
            ]),
            404
        );
    }

    // ------------------------------------------------------------ dati

    /** @return array<string,mixed> */
    private function home(): array
    {
        return [
            'camere'  => $this->app->rooms()->all(),
            'luoghi'  => $this->places(),
            'domande' => $this->faq(),
        ];
    }

    /** @return array<string,mixed> */
    private function rooms(): array
    {
        return ['camere' => $this->app->rooms()->all()];
    }

    /** @return list<array<string,mixed>> */
    private function places(): array
    {
        return require $this->app->config('content') . '/places.php';
    }

    /** @return list<array<string,mixed>> */
    private function faq(): array
    {
        return require $this->app->config('content') . '/faq.php';
    }

    private function seoTitle(string $page): string
    {
        $key = $page . '.seo_title';

        return $this->app->translator()->has($key)
            ? $this->app->translator()->get($key)
            : $this->app->translator()->get('common.brand');
    }

    /**
     * Gli indirizzi della stessa pagina nelle altre lingue, per hreflang.
     *
     * @return array<string,string>
     */
    private function alternates(string $page): array
    {
        $out = [];
        foreach ($this->app->config('i18n.available') as $locale) {
            $out[$locale] = Routes::url($page, $locale);
        }

        return $out;
    }

    /**
     * Come sopra, ma per una camera: ogni lingua ha il suo slug.
     *
     * @param array<string,mixed> $room
     * @return array<string,string>
     */
    private function roomAlternates(array $room): array
    {
        $out = [];
        foreach ($this->app->config('i18n.available') as $locale) {
            $slug = $room['slug'][$locale] ?? null;
            if (is_string($slug) && $slug !== '') {
                $out[$locale] = Routes::url('room', $locale, ['slug' => $slug]);
            }
        }

        return $out;
    }
}
