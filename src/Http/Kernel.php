<?php

declare(strict_types=1);

namespace ArcoDelVento\Http;

use ArcoDelVento\App;
use ArcoDelVento\Controller\AdminController;
use ArcoDelVento\Controller\BookingController;
use ArcoDelVento\Controller\ContactController;
use ArcoDelVento\Controller\PaymentController;
use ArcoDelVento\Controller\PageController;
use ArcoDelVento\Controller\SitemapController;
use ArcoDelVento\I18n\Routes;

/**
 * Il cuore dell'applicazione: riceve una richiesta, sceglie la lingua,
 * riconosce la pagina, la rende.
 *
 * Gli indirizzi hanno sempre la lingua davanti — /it/camere, /en/rooms — così
 * ogni pagina è indicizzabile nella sua lingua e la sua traduzione ha un
 * indirizzo proprio da dichiarare in hreflang. La radice « / » non è una
 * pagina: reindirizza alla lingua del browser, se la conosciamo, o
 * all'italiano.
 */
final class Kernel
{
    public function __construct(private readonly App $app)
    {
    }

    public function handle(Request $request): Response
    {
        $locales = $this->app->config('i18n.available');
        $default = (string) $this->app->config('i18n.default');

        // Le due rotte tecniche non hanno lingua e vengono prima di tutto:
        // sono generate perché l'indirizzo del sito e l'elenco delle camere
        // cambiano, e una mappa scritta a mano invecchia in silenzio.
        if ($request->path === '/sitemap.xml') {
            return (new SitemapController($this->app))->sitemap();
        }
        if ($request->path === '/robots.txt') {
            return (new SitemapController($this->app))->robots();
        }

        // Le notifiche di SumUp: senza lingua, senza sessione, senza gettone.
        // Non ci si fida del contenuto: dice solo quale pagamento guardare.
        if ($request->path === '/pagamenti/sumup') {
            return (new PaymentController($this->app))->notifica($request);
        }

        // L'area riservata: fuori dalle lingue del sito, sempre in italiano.
        if ($request->path === '/admin' || str_starts_with($request->path, '/admin/')) {
            return (new AdminController($this->app))->handle($request);
        }

        // La radice non ha contenuto proprio: porta alla lingua giusta.
        if ($request->path === '/') {
            return Response::redirect(Routes::url('home', $this->negotiate($request, $locales, $default)), 302);
        }

        // «/it» senza barra è la home di lingua scritta male: l'indirizzo vero
        // è «/it/», ed è quello che dichiarano canonical e hreflang. Prima
        // rispondevano tutti e due, e una pagina con due indirizzi è contenuto
        // doppio. Il reindirizzamento lo fa PHP e non .htaccess perché qui la
        // cartella del sito si conosce.
        // Il percorso di Request è già normalizzato, barre in coda comprese: la
        // barra si legge sull'indirizzo com'è arrivato.
        $solo   = trim($request->path, '/');
        $grezzo = (string) (parse_url((string) ($request->server['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
        if (in_array($solo, $locales, true) && !str_ends_with($grezzo, '/')) {
            return Response::redirect(Routes::url('home', $solo), 301);
        }

        $match = Routes::match($request->path, $locales);

        if ($match === null) {
            return $this->notFound($request, $locales, $default);
        }

        $this->app->translator()->setLocale($match['locale']);
        $this->share($match);

        return $this->dispatch($match['page'], $request, $match['params']);
    }

    /** @param array<string,string> $params */
    private function dispatch(string $page, Request $request, array $params): Response
    {
        return match ($page) {
            'book'    => (new BookingController($this->app))->handle($request),
            'contact' => (new ContactController($this->app))->handle($request),
            'room'    => (new PageController($this->app))->room($request, (string) ($params['slug'] ?? '')),
            default   => (new PageController($this->app))->show($page, $request),
        };
    }

    /** @param list<string> $locales */
    private function notFound(Request $request, array $locales, string $default): Response
    {
        // Se l'indirizzo comincia con una lingua valida, resta in quella lingua:
        // chi sbaglia una pagina inglese non deve ritrovarsi il 404 in italiano.
        $first  = explode('/', trim($request->path, '/'))[0] ?? '';
        $locale = in_array($first, $locales, true) ? $first : $this->negotiate($request, $locales, $default);

        $this->app->translator()->setLocale($locale);
        $this->share(['locale' => $locale, 'page' => '404', 'params' => []]);

        return (new PageController($this->app))->notFound();
    }

    /** @param list<string> $locales */
    private function negotiate(Request $request, array $locales, string $default): string
    {
        foreach ($request->preferredLocales() as $candidate) {
            if (in_array($candidate, $locales, true)) {
                return $candidate;
            }
        }

        return $default;
    }

    /** @param array{locale:string,page:string,params:array<string,string>} $match */
    private function share(array $match): void
    {
        $view = $this->app->view();
        $view->share('locale', $match['locale']);
        $view->share('paginaCorrente', $match['page']);
        $view->share('parametri', $match['params']);
    }
}
