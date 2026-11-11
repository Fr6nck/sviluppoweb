<?php

declare(strict_types=1);

namespace ArcoDelVento\Controller;

use ArcoDelVento\App;
use ArcoDelVento\Booking\Ical;
use ArcoDelVento\Http\Response;

/**
 * Il calendario di una camera, pubblicato per Booking.com e le altre
 * piattaforme: /ical/<camera>/<gettone>.ics.
 *
 * Dentro ci sono solo le notti vendute o confermate dal sito, come giornate
 * intere e senza nomi: a chi importa il calendario basta sapere che la
 * camera è presa. Il gettone sta nell'indirizzo perché Booking.com non sa
 * fare altro che scaricare un link; chi non lo conosce trova una pagina che
 * non esiste. Passa prima della porta del sito in anteprima: Booking non ha
 * il codice d'accesso.
 */
final class IcalController
{
    public function __construct(private readonly App $app)
    {
    }

    public function esporta(string $ref, string $gettone): Response
    {
        $camera = $this->app->rooms()->findByRef($ref);
        if ($camera === null || !hash_equals($this->app->calendari()->gettone(), $gettone)) {
            return Response::html('Non trovato', 404);
        }

        // Le pagine di pagamento aperte e non ancora pagate non si pubblicano:
        // durano mezz'ora, Booking rileggerebbe il calendario molto dopo.
        $eventi = [];
        foreach ($this->app->inbox()->nottiOccupate(0) as $n) {
            if ($n['ref'] === $ref) {
                $eventi[] = ['uid' => $n['id'], 'arrivo' => $n['arrivo'], 'partenza' => $n['partenza'], 'titolo' => 'Arco del Vento: prenotata dal sito'];
            }
        }
        $dominio = (string) (parse_url($this->app->indirizzoCompleto('/'), PHP_URL_HOST) ?: 'arcodelvento.local');
        $nome    = 'Arco del Vento — ' . (string) ($camera['name']['it'] ?? $ref);

        return Response::text(Ical::calendario($nome, $eventi, $dominio), 'text/calendar; charset=UTF-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-LiteSpeed-Cache-Control', 'no-cache')
            ->withHeader('X-Robots-Tag', 'noindex');
    }
}
