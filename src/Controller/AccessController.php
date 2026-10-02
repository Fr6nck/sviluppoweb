<?php

declare(strict_types=1);

namespace ArcoDelVento\Controller;

use ArcoDelVento\App;
use ArcoDelVento\Http\Request;
use ArcoDelVento\Http\Response;
use ArcoDelVento\I18n\Routes;
use ArcoDelVento\Support\Accesso;
use ArcoDelVento\Support\Csrf;

/**
 * La porta d'ingresso del sito durante l'anteprima.
 *
 * Viene prima di ogni pagina pubblica. Se l'accesso è libero, o il browser ha
 * già dato il codice, o è entrato nell'area riservata, non fa niente. Se no,
 * qualunque indirizzo risponde con la pagina del codice; dato il codice
 * giusto, si torna allo stesso indirizzo, con il sito aperto.
 *
 * Restano fuori l'area riservata (ha il suo accesso, e da lì si spegne il
 * codice) e le notifiche di SumUp, che arrivano dal server di SumUp.
 */
final class AccessController
{
    /** Codici sbagliati, al massimo, per connessione ogni quarto d'ora. */
    private const TENTATIVI = 10;

    public function __construct(private readonly App $app)
    {
    }

    /** La risposta della porta, o null se si passa. */
    public function handle(Request $request): ?Response
    {
        $accesso = $this->app->accesso();
        if (!$accesso->attivo()) {
            return null;
        }

        // Con il sito chiuso, i motori di ricerca non entrano da nessuna parte.
        if ($request->path === '/robots.txt') {
            return Response::text("# Arco del Vento — sito in anteprima, chiuso da un codice\n\nUser-agent: *\nDisallow: /\n");
        }

        if ($accesso->sbloccato($_COOKIE[Accesso::COOKIE] ?? null) || $this->app->auth()->current() !== null) {
            return null;
        }

        $this->app->translator()->setLocale($this->lingua($request));

        if ($request->isPost() && isset($request->post['_accesso'])) {
            return $this->prova($request, $accesso);
        }

        return $this->pagina($request);
    }

    private function prova(Request $request, Accesso $accesso): Response
    {
        if (!Csrf::isValid($request->post['_token'] ?? null)) {
            return $this->pagina($request, 'gate.expired', 403);
        }
        if ($this->troppiErrori($request, false)) {
            return $this->pagina($request, 'gate.too_many', 429);
        }
        $inserito = is_string($request->post['codice'] ?? null) ? (string) $request->post['codice'] : '';
        if (trim($inserito) === '') {
            // Un campo vuoto non è un tentativo: non conta fra gli sbagliati.
            return $this->pagina($request, 'gate.empty', 422);
        }
        if (!$accesso->giusto($inserito)) {
            $this->troppiErrori($request, true);

            return $this->pagina($request, 'gate.wrong', 401);
        }

        $https = (($request->server['HTTPS'] ?? '') !== '' && ($request->server['HTTPS'] ?? '') !== 'off')
            || (($request->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        setcookie(Accesso::COOKIE, $accesso->gettone(), [
            'expires'  => time() + Accesso::GIORNI * 86400,
            'path'     => (Routes::base() !== '' ? Routes::base() : '') . '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // Si torna allo stesso indirizzo, ricostruito qui: mai quello che
        // arriva grezzo dalla richiesta, che potrebbe portare fuori dal sito.
        $query = (string) ($request->server['QUERY_STRING'] ?? '');

        return Response::redirect(Routes::base() . $request->path . ($query !== '' ? '?' . $query : ''), 303)
            ->withHeader('Cache-Control', 'no-store');
    }

    private function pagina(Request $request, ?string $errore = null, int $codice = 200): Response
    {
        $query = (string) ($request->server['QUERY_STRING'] ?? '');
        $lingua = $this->app->translator()->locale();

        $html = $this->app->view()->render('sipario', [
            'azione' => Routes::base() . $request->path . ($query !== '' ? '?' . $query : ''),
            'errore' => $errore,
            'lingua' => $lingua,
            'altra'  => $lingua === 'it' ? 'en' : 'it',
        ]);

        return Response::html($html, $codice)
            ->withHeader('Cache-Control', 'no-store, private')
            ->withHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    /** La lingua della pagina: quella dell'indirizzo, o quella del browser. */
    private function lingua(Request $request): string
    {
        $disponibili = (array) $this->app->config('i18n.available');
        $prima = explode('/', trim($request->path, '/'))[0] ?? '';
        if (in_array($prima, $disponibili, true)) {
            return $prima;
        }
        foreach ($request->preferredLocales() as $l) {
            if (in_array($l, $disponibili, true)) {
                return $l;
            }
        }

        return (string) $this->app->config('i18n.default');
    }

    /**
     * Troppi codici sbagliati dalla stessa connessione? Con $conta, ne
     * aggiunge uno. Si tiene un'impronta dell'indirizzo, solo per un quarto d'ora.
     */
    private function troppiErrori(Request $request, bool $conta): bool
    {
        $chi = substr(hash('sha256', date('Y-m-d') . '|' . (string) ($request->server['REMOTE_ADDR'] ?? '')), 0, 20);
        $troppi = false;
        try {
            $this->app->store()->update('accesso-tentativi', static function (array $voci) use ($chi, $conta, &$troppi): array {
                $ora = time();
                foreach ($voci as $k => $tempi) {
                    $voci[$k] = array_values(array_filter((array) $tempi, static fn ($t): bool => $ora - (int) $t < 900));
                    if ($voci[$k] === []) {
                        unset($voci[$k]);
                    }
                }
                $troppi = count($voci[$chi] ?? []) >= self::TENTATIVI;
                if ($conta && !$troppi) {
                    $voci[$chi][] = $ora;
                }

                return $voci;
            }, false);
        } catch (\Throwable $e) {
            error_log('Tentativi d\'accesso non contati: ' . $e->getMessage());
        }

        return $troppi;
    }
}
