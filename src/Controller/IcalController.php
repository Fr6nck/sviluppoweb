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
 * Dentro ci sono le notti vendute o confermate dal sito e quelle chiuse con
 * un blocco manuale dal Calendario del pannello, come giornate intere e senza
 * nomi: a chi importa il calendario basta sapere che la camera è presa. La
 * nota del blocco resta nel pannello. Le notti lette da Booking.com non si
 * ripubblicano: Booking le conosce già. Il gettone sta nell'indirizzo perché Booking.com non sa
 * fare altro che scaricare un link; chi non lo conosce trova una pagina che
 * non esiste. Passa prima della porta del sito in anteprima: Booking non ha
 * il codice d'accesso.
 */
final class IcalController
{
    public function __construct(private readonly App $app)
    {
    }

    /**
     * Il soggiorno di un ospite, da aggiungere al suo calendario: il link è
     * nell'e-mail di conferma. Solo per una prenotazione pagata o confermata,
     * e con il codice giusto; altrimenti una pagina che non esiste.
     */
    public function ospite(string $riferimento, string $codice): Response
    {
        $calendario = $this->app->calendarioOspite();
        $voce = $calendario->codiceGiusto($riferimento, $codice) ? $this->app->inbox()->find($riferimento) : null;
        if ($voce === null || !\ArcoDelVento\Booking\CalendarioOspite::daDare($voce)) {
            return Response::html('Non trovato', 404);
        }
        $lingua = in_array($voce['dati']['lingua'] ?? '', (array) $this->app->config('i18n.available'), true) ? (string) $voce['dati']['lingua'] : 'it';

        return Response::text($calendario->ics($voce, $lingua), 'text/calendar; charset=UTF-8')
            ->withHeader('Content-Disposition', 'inline; filename="arco-del-vento-' . strtolower($riferimento) . '.ics"')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-LiteSpeed-Cache-Control', 'no-cache')
            ->withHeader('X-Robots-Tag', 'noindex');
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
        // I blocchi manuali: una prenotazione presa al telefono si chiude anche
        // su Booking. Solo le date: la nota può avere un nome.
        foreach ($this->app->blocchi()->occupate() as $b) {
            if ($b['ref'] === $ref) {
                $eventi[] = ['uid' => $b['id'], 'arrivo' => $b['arrivo'], 'partenza' => $b['partenza'], 'titolo' => 'Arco del Vento: non disponibile'];
            }
        }
        usort($eventi, static fn (array $a, array $b): int => [$a['arrivo'], $a['uid']] <=> [$b['arrivo'], $b['uid']]);
        $dominio = (string) (parse_url($this->app->indirizzoCompleto('/'), PHP_URL_HOST) ?: 'arcodelvento.local');
        $nome    = 'Arco del Vento — ' . (string) ($camera['name']['it'] ?? $ref);

        return Response::text(Ical::calendario($nome, $eventi, $dominio), 'text/calendar; charset=UTF-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-LiteSpeed-Cache-Control', 'no-cache')
            ->withHeader('X-Robots-Tag', 'noindex');
    }
}
