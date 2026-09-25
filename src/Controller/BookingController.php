<?php

declare(strict_types=1);

namespace ArcoDelVento\Controller;

use ArcoDelVento\App;
use ArcoDelVento\Booking\BookingRequest;
use ArcoDelVento\Booking\RoomOffer;
use ArcoDelVento\Booking\SearchCriteria;
use ArcoDelVento\Http\Request;
use ArcoDelVento\Http\Response;
use ArcoDelVento\I18n\Routes;
use ArcoDelVento\Mail\MailMessage;
use ArcoDelVento\Support\Csrf;
use ArcoDelVento\Support\Validator;

/**
 * Il percorso di prenotazione, in quattro passi:
 *
 *     date e ospiti → camere libere → i tuoi dati → richiesta inviata
 *
 * I primi tre passi vivono negli indirizzi, non nella sessione: /it/prenota
 * ?arrivo=…&partenza=…&ospiti=…&camera=… è un indirizzo che si può salvare,
 * rimandare a qualcuno e ricaricare senza perdere niente. Il tasto «indietro»
 * del browser funziona come si aspetta chiunque. Solo la conferma finale passa
 * dalla sessione, perché quella non deve essere ricaricabile.
 *
 * La disponibilità la dà il provider (vedi BookingProviderInterface): qui non
 * si sa, e non si deve sapere, se dietro ci sia il provider dimostrativo o un
 * gestionale vero.
 */
final class BookingController
{
    private const SESSION_DONE = '_adv_booking_done';

    public function __construct(private readonly App $app)
    {
    }

    public function handle(Request $request): Response
    {
        if ($request->isPost()) {
            return $this->submit($request);
        }

        return match ($request->get('passo')) {
            'camere' => $this->stepRooms($request),
            'dati'   => $this->stepDetails($request),
            'fatto'  => $this->stepDone(),
            default  => $this->stepDates($request),
        };
    }

    // ------------------------------------------------- 1. date e ospiti

    private function stepDates(Request $request, array $errors = []): Response
    {
        return $this->render('book', [
            'passo'    => 'dates',
            'criteri'  => $this->criteriaFromRequest($request),
            'errori'   => $errors,
            'ospiti'   => (int) ($request->get('ospiti') ?? 2),
        ]);
    }

    // ---------------------------------------------- 2. le camere libere

    private function stepRooms(Request $request): Response
    {
        $criteria = $this->criteriaFromRequest($request);

        if ($criteria === null) {
            return $this->stepDates($request);
        }

        $errors = $this->app->booking()->validateCriteria($criteria);
        if ($errors !== []) {
            return $this->stepDates($request, $errors);
        }

        return $this->render('book', [
            'passo'         => 'rooms',
            'criteri'       => $criteria,
            'disponibilita' => $this->app->booking()->search($criteria),
            'errori'        => [],
        ]);
    }

    // --------------------------------------------------- 3. i tuoi dati

    private function stepDetails(Request $request, array $errors = [], array $values = []): Response
    {
        $criteria = $this->criteriaFromRequest($request);
        if ($criteria === null) {
            return $this->stepDates($request);
        }

        $offer = $this->offerFor($criteria, (string) ($request->get('camera') ?? ''));
        if ($offer === null) {
            // La camera non esiste o non è più libera: si torna all'elenco,
            // che è l'unico posto in cui la scelta ha senso.
            return $this->redirectToStep('camere', $criteria);
        }

        return $this->render('book', [
            'passo'    => 'details',
            'criteri'  => $criteria,
            'offerta'  => $offer,
            'errori'   => $errors,
            'valori'   => $values,
        ]);
    }

    // ------------------------------------------------ 4. richiesta inviata

    private function stepDone(): Response
    {
        $done = $_SESSION[self::SESSION_DONE] ?? null;
        unset($_SESSION[self::SESSION_DONE]);   // una volta sola: ricaricare non rispedisce

        if (!is_array($done)) {
            return Response::redirect(Routes::url('book', $this->app->locale()));
        }

        return $this->render('book', ['passo' => 'done', 'conferma' => $done, 'errori' => []]);
    }

    // ------------------------------------------------------ invio dati

    private function submit(Request $request): Response
    {
        // I criteri arrivano dai campi nascosti del modulo, non dall'indirizzo:
        // questa è una POST e la sua stringa di ricerca è vuota.
        $criteria = SearchCriteria::fromArray([
            'arrival'   => (string) ($request->post['arrivo'] ?? ''),
            'departure' => (string) ($request->post['partenza'] ?? ''),
            'guests'    => (int) ($request->post['ospiti'] ?? 1),
        ]);

        if ($criteria === null || $this->app->booking()->validateCriteria($criteria) !== []) {
            return $this->stepDates($request, ['_form' => ['no_session', []]]);
        }

        $offer = $this->offerFor($criteria, (string) ($request->post['camera'] ?? ''));
        if ($offer === null) {
            return $this->redirectToStep('camere', $criteria);
        }

        // Il gettone si controlla dopo aver ricostruito date e camera, così
        // una sessione scaduta rimanda al modulo compilato con il messaggio
        // che spiega cosa fare — e non a una pagina vuota che perde tutto.
        if (!Csrf::isValid($request->post['_token'] ?? null)) {
            return $this->stepDetails(
                $this->withCriteria($request, $criteria, $offer),
                ['_form' => ['token', []]],
                $request->post
            );
        }

        $v = new Validator($request->post);
        $v->required('nome')->maxLength('nome', 80)
          ->required('cognome')->maxLength('cognome', 80)
          ->required('email')->email('email')->maxLength('email', 180)
          ->maxLength('telefono', 40)
          ->maxLength('paese', 80)
          ->maxLength('note', 2000)
          ->accepted('privacy');

        if ($v->fails()) {
            return $this->stepDetails(
                $this->withCriteria($request, $criteria, $offer),
                array_map(static fn (string $key): array => [$key, []], $v->errors()),
                $request->post
            );
        }

        $booking = new BookingRequest(
            criteria:  $criteria,
            offer:     $offer,
            firstName: $v->value('nome'),
            lastName:  $v->value('cognome'),
            email:     $v->value('email'),
            phone:     $v->value('telefono'),
            country:   $v->value('paese'),
            notes:     $v->value('note'),
            locale:    $this->app->locale(),
        );

        $confirmation = $this->app->booking()->request($booking);
        $this->notify($confirmation->reference, $booking, $offer);
        $this->archive($confirmation->reference, $booking, $offer);

        $locale = $this->app->locale();
        $_SESSION[self::SESSION_DONE] = [
            'reference' => $confirmation->reference,
            'room'      => (string) ($offer->room['name'][$locale] ?? $offer->ref()),
            'roomRef'   => $offer->ref(),
            'arrival'   => $criteria->arrivalIso(),
            'departure' => $criteria->departureIso(),
            'nights'    => $offer->nights,
            'guests'    => $criteria->guests,
            'rate'      => $offer->nightlyRate,
            'total'     => $offer->total,
            'rateIsDemo' => $offer->rateIsDemo,
            'guestName' => $booking->guestName(),
            'email'     => $booking->email,
            'pretend'   => $this->app->mailer()->isPretend(),
        ];

        // Post/Redirect/Get: ricaricare la conferma non rispedisce la richiesta.
        return Response::redirect(Routes::url('book', $locale, [], ['passo' => 'fatto']), 303);
    }

    // --------------------------------------------------------- interni

    /**
     * La richiesta resta anche nell'area riservata, oltre che nella posta.
     * Se non si riesce a salvarla l'ospite non deve accorgersene: l'e-mail è
     * già partita, e l'errore finisce nel registro.
     */
    private function archive(string $reference, BookingRequest $booking, RoomOffer $offer): void
    {
        try {
            $this->app->inbox()->add('prenotazione', [
                'camera'    => (string) ($offer->room['name']['it'] ?? $offer->ref()),
                'ref'       => $offer->ref(),
                'arrivo'    => $booking->criteria->arrivalIso(),
                'partenza'  => $booking->criteria->departureIso(),
                'notti'     => $offer->nights,
                'ospiti'    => $booking->criteria->guests,
                'totale'    => $offer->total,
                'valuta'    => $offer->currency,
                'nome'      => $booking->guestName(),
                'email'     => $booking->email,
                'telefono'  => $booking->phone,
                'paese'     => $booking->country,
                'note'      => $booking->notes,
                'lingua'    => $booking->locale,
            ], $reference);
        } catch (\Throwable $e) {
            error_log('Richiesta ' . $reference . ' non salvata nell\'area riservata: ' . $e->getMessage());
        }
    }

    private function criteriaFromRequest(Request $request): ?SearchCriteria
    {
        $arrival   = (string) ($request->get('arrivo') ?? '');
        $departure = (string) ($request->get('partenza') ?? '');

        if ($arrival === '' || $departure === '') {
            return null;
        }

        return SearchCriteria::fromArray([
            'arrival'   => $arrival,
            'departure' => $departure,
            'guests'    => (int) ($request->get('ospiti') ?? 2),
        ]);
    }

    /** L'offerta di una camera, solo se è davvero libera in quelle date. */
    private function offerFor(SearchCriteria $criteria, string $ref): ?RoomOffer
    {
        if ($ref === '') {
            return null;
        }
        $offer = $this->app->booking()->quote($criteria, $ref);

        return ($offer !== null && $offer->available) ? $offer : null;
    }

    /**
     * Ricostruisce una richiesta GET con i criteri già noti: serve a
     * ridisegnare il passo dei dati dopo un errore di convalida, senza
     * perdere date e camera.
     */
    private function withCriteria(Request $request, SearchCriteria $criteria, RoomOffer $offer): Request
    {
        $_GET = [
            'passo'    => 'dati',
            'arrivo'   => $criteria->arrivalIso(),
            'partenza' => $criteria->departureIso(),
            'ospiti'   => (string) $criteria->guests,
            'camera'   => $offer->ref(),
        ];

        return Request::fromGlobals();
    }

    private function redirectToStep(string $step, SearchCriteria $criteria): Response
    {
        return Response::redirect(Routes::url('book', $this->app->locale(), [], [
            'passo'    => $step,
            'arrivo'   => $criteria->arrivalIso(),
            'partenza' => $criteria->departureIso(),
            'ospiti'   => (string) $criteria->guests,
        ]), 303);
    }

    /** @param array<string,mixed> $data */
    private function render(string $page, array $data): Response
    {
        $locale = $this->app->locale();

        return Response::html($this->app->view()->page($page, $data + [
            'pagina'      => 'book',
            'titoloSeo'   => $this->app->translator()->get('book.seo_title'),
            'descrizione' => $this->app->translator()->get('book.seo_description'),
            'canonico'    => Routes::url('book', $locale),
            'alternative' => array_combine(
                $this->app->config('i18n.available'),
                array_map(
                    static fn (string $l): string => Routes::url('book', $l),
                    $this->app->config('i18n.available')
                )
            ),
            // I passi intermedi non vanno indicizzati: sono stati di un modulo.
            'noindex'     => ($data['passo'] ?? 'dates') !== 'dates',
        ]));
    }

    /** Avvisa la struttura. Chi spedisce davvero lo decide MAIL_TRANSPORT. */
    private function notify(string $reference, BookingRequest $booking, RoomOffer $offer): void
    {
        $locale = $booking->locale;
        $room   = (string) ($offer->room['name'][$locale] ?? $offer->ref());

        $body = implode("\n", [
            'Nuova richiesta di prenotazione dal sito.',
            '',
            'Riferimento: ' . $reference,
            'Camera:      ' . $room . ' (' . $offer->ref() . ')',
            'Arrivo:      ' . $booking->criteria->arrivalIso(),
            'Partenza:    ' . $booking->criteria->departureIso(),
            'Notti:       ' . $offer->nights,
            'Ospiti:      ' . $booking->criteria->guests,
            'Totale:      ' . number_format($offer->total, 2, ',', '.') . ' ' . $offer->currency
                            . ($offer->rateIsDemo ? '  (tariffa dimostrativa)' : ''),
            '',
            'Ospite:      ' . $booking->guestName(),
            'E-mail:      ' . $booking->email,
            'Telefono:    ' . ($booking->phone !== '' ? $booking->phone : '—'),
            'Paese:       ' . ($booking->country !== '' ? $booking->country : '—'),
            'Lingua:      ' . $locale,
            '',
            'Note:',
            $booking->notes !== '' ? $booking->notes : '—',
        ]);

        $this->app->mailer()->send(new MailMessage(
            to:          $this->app->destinatari(),
            subject:     sprintf('[Arco del Vento] Richiesta %s — %s', $reference, $room),
            body:        $body,
            fromAddress: (string) $this->app->config('mail.from.address'),
            fromName:    (string) $this->app->config('mail.from.name'),
            replyTo:     $booking->email,
        ));
    }
}
