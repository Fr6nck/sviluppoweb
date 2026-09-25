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
use ArcoDelVento\Payment\ErrorePagamento;
use ArcoDelVento\Payment\Pagamenti;
use ArcoDelVento\Support\Csrf;
use ArcoDelVento\Support\Validator;

/**
 * Il percorso di prenotazione, in quattro passi:
 *
 *     date e ospiti → camere libere → i tuoi dati → richiesta inviata
 *
 * Con il pagamento online acceso (PAYMENT_PROVIDER=sumup) l'ultimo passo
 * cambia: i dati sono solo nome ed e-mail, e dopo si paga su SumUp.
 *
 *     … → nome ed e-mail → pagina di SumUp → esito (pagato, o si riprova)
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

    /** Le prenotazioni aperte da questo browser: solo lui ne vede il riepilogo. */
    private const SESSION_PAGAMENTI = '_adv_pagamenti';

    public function __construct(private readonly App $app)
    {
    }

    public function handle(Request $request): Response
    {
        if ($request->isPost()) {
            return ($request->post['azione'] ?? '') === 'riprova' ? $this->retry($request) : $this->submit($request);
        }

        return match ($request->get('passo')) {
            'camere' => $this->stepRooms($request),
            'dati'   => $this->stepDetails($request),
            'fatto'  => $this->stepDone(),
            'paga'   => $this->stepPay($request),
            'esito'  => $this->stepResult($request),
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

        $pagamenti = $this->app->pagamenti();
        if ($pagamenti !== null) {
            return $this->submitAndPay($request, $criteria, $offer, $pagamenti);
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

    // ------------------------------------------------ pagamento online

    /**
     * Nome, e-mail, la spunta sulle condizioni: la prenotazione nasce «in
     * attesa» di pagamento e si apre la pagina di SumUp. Il prezzo è quello
     * calcolato qui, adesso, dal provider: niente di quello che arriva dal
     * modulo decide l'importo.
     */
    private function submitAndPay(Request $request, SearchCriteria $criteria, RoomOffer $offer, Pagamenti $pagamenti): Response
    {
        $v = new Validator($request->post);
        $v->required('nome')->maxLength('nome', 120)
          ->required('email')->email('email')->maxLength('email', 180)
          ->accepted('condizioni');

        if ($v->fails()) {
            return $this->stepDetails(
                $this->withCriteria($request, $criteria, $offer),
                array_map(static fn (string $key): array => [$key, []], $v->errors()),
                $request->post
            );
        }

        // Ogni pagina di pagamento tiene la camera mezz'ora: chi aprisse
        // pagine a ripetizione terrebbe occupata la casa senza pagare.
        if ($this->troppePagine($request)) {
            return $this->stepDetails(
                $this->withCriteria($request, $criteria, $offer),
                ['_form' => ['pay_limit', ['email' => (string) ($this->app->settings()['contacts']['email'] ?? '')]]],
                $request->post
            );
        }

        $booking = new BookingRequest(
            criteria:  $criteria,
            offer:     $offer,
            firstName: $v->value('nome'),
            lastName:  '',
            email:     $v->value('email'),
            locale:    $this->app->locale(),
        );
        $riferimento = $this->app->booking()->request($booking)->reference;
        $salvata = $this->archive($riferimento, $booking, $offer, [
            'fornitore' => 'sumup',
            'stato'     => Pagamenti::IN_ATTESA,
            'importo'   => round($offer->total, 2),
            'valuta'    => $offer->currency,
            'tentativi' => [],
        ]);

        try {
            if (!$salvata) {
                throw new ErrorePagamento('la prenotazione non si è potuta salvare');
            }
            $pagamenti->apri($riferimento);
        } catch (\Throwable $e) {
            error_log('Pagamento ' . $riferimento . ' non avviato: ' . $e->getMessage());
            // Una prenotazione senza pagina di pagamento non serve a nessuno.
            $this->app->inbox()->delete($riferimento);

            return $this->stepDetails(
                $this->withCriteria($request, $criteria, $offer),
                ['_form' => ['pay_error', ['email' => (string) ($this->app->settings()['contacts']['email'] ?? '')]]],
                $request->post
            );
        }

        $mie = (array) ($_SESSION[self::SESSION_PAGAMENTI] ?? []);
        $mie[] = $riferimento;
        $_SESSION[self::SESSION_PAGAMENTI] = array_slice(array_values(array_unique($mie)), -10);

        return Response::redirect(Routes::url('book', $this->app->locale(), [], ['passo' => 'paga', 'rif' => $riferimento]), 303);
    }

    /**
     * Il passaggio a SumUp. È una pagina del sito e non un rinvio diretto
     * perché la politica di sicurezza (form-action 'self') non lascia che un
     * modulo porti fuori dal sito: da qui si prosegue con un link, che il
     * JavaScript apre da solo.
     */
    private function stepPay(Request $request): Response
    {
        $riferimento = (string) ($request->get('rif') ?? '');
        $voce = $this->mia($riferimento) ? $this->app->inbox()->find($riferimento) : null;
        $p = (array) ($voce['dati']['pagamento'] ?? []);
        $tentativi = (array) ($p['tentativi'] ?? []);
        $ultimo = end($tentativi) ?: [];
        $aperta = (time() - (strtotime((string) ($ultimo['creato'] ?? '')) ?: 0)) < 29 * 60;

        if ($voce === null || ($p['stato'] ?? '') !== Pagamenti::IN_ATTESA || empty($ultimo['url']) || !$aperta) {
            return $this->redirectToResult($riferimento);
        }

        return $this->render('book', [
            'passo'        => 'pay',
            'voce'         => $voce,
            'urlPagamento' => (string) $ultimo['url'],
            'errori'  => [],
        ]);
    }

    /** Dove torna l'ospite da SumUp: si chiede a SumUp com'è andata. */
    private function stepResult(Request $request, ?string $avviso = null): Response
    {
        $riferimento = (string) ($request->get('rif') ?? $request->post['rif'] ?? '');
        $pagamenti = $this->app->pagamenti();
        $voce = $this->app->inbox()->find($riferimento);
        $stato = '';
        if ($pagamenti !== null && $voce !== null && !empty($voce['dati']['pagamento'])) {
            $stato = $pagamenti->verifica($riferimento);
            $voce  = $this->app->inbox()->find($riferimento);
        }

        return $this->render('book', [
            'passo'       => 'result',
            'riferimento' => $riferimento,
            'stato'       => $stato,
            // Il riepilogo lo vede solo il browser che ha prenotato.
            'voce'        => $this->mia($riferimento) ? $voce : null,
            'avviso'      => $avviso,
            'errori'      => [],
        ]);
    }

    /** «Riprova il pagamento»: una pagina di SumUp nuova per la stessa prenotazione. */
    private function retry(Request $request): Response
    {
        $riferimento = (string) ($request->post['rif'] ?? '');
        $pagamenti = $this->app->pagamenti();
        $voce = $this->mia($riferimento) ? $this->app->inbox()->find($riferimento) : null;
        if ($pagamenti === null || $voce === null || !Csrf::isValid($request->post['_token'] ?? null)) {
            return $this->redirectToResult($riferimento);
        }

        $d = (array) $voce['dati'];
        // Nel frattempo la camera può essere stata venduta a qualcun altro, o
        // le date possono essere passate.
        $presa = (string) ($d['arrivo'] ?? '') < (new \DateTimeImmutable('today'))->format('Y-m-d');
        foreach ($this->app->inbox()->nottiOccupate() as $o) {
            if ($o['id'] !== $riferimento && $o['ref'] === ($d['ref'] ?? '')
                && ($d['arrivo'] ?? '') < $o['partenza'] && ($d['partenza'] ?? '') > $o['arrivo']) {
                $presa = true;
            }
        }
        if ($presa) {
            return $this->stepResult($request, 'taken');
        }

        try {
            $pagina = $pagamenti->apri($riferimento);
        } catch (\Throwable $e) {
            error_log('Nuovo pagamento ' . $riferimento . ' non avviato: ' . $e->getMessage());

            return $this->stepResult($request, 'error');
        }

        return $pagina === null
            ? $this->redirectToResult($riferimento)
            : Response::redirect(Routes::url('book', $this->app->locale(), [], ['passo' => 'paga', 'rif' => $riferimento]), 303);
    }

    /**
     * Al massimo sei pagine di pagamento l'ora dalla stessa connessione. Si
     * tiene solo un'impronta dell'indirizzo, cambiata ogni giorno, e solo per
     * un'ora: non è un registro di chi visita il sito.
     */
    private function troppePagine(Request $request): bool
    {
        $chi = substr(hash('sha256', date('Y-m-d') . '|' . (string) ($request->server['REMOTE_ADDR'] ?? '')), 0, 20);
        $troppe = false;
        try {
            $this->app->store()->update('limiti-pagamento', static function (array $voci) use ($chi, &$troppe): array {
                $ora = time();
                foreach ($voci as $k => $tempi) {
                    $voci[$k] = array_values(array_filter((array) $tempi, static fn ($t): bool => $ora - (int) $t < 3600));
                    if ($voci[$k] === []) {
                        unset($voci[$k]);
                    }
                }
                if (count($voci[$chi] ?? []) >= 6) {
                    $troppe = true;

                    return $voci;
                }
                $voci[$chi][] = $ora;

                return $voci;
            }, false);
        } catch (\Throwable $e) {
            error_log('Limite dei pagamenti non controllato: ' . $e->getMessage());
        }

        return $troppe;
    }

    private function mia(string $riferimento): bool
    {
        return $riferimento !== '' && in_array($riferimento, (array) ($_SESSION[self::SESSION_PAGAMENTI] ?? []), true);
    }

    private function redirectToResult(string $riferimento): Response
    {
        return Response::redirect(Routes::url('book', $this->app->locale(), [], ['passo' => 'esito', 'rif' => $riferimento]), 303);
    }

    // --------------------------------------------------------- interni

    /**
     * La richiesta resta anche nell'area riservata, oltre che nella posta.
     * Se non si riesce a salvarla l'ospite non deve accorgersene: l'e-mail è
     * già partita, e l'errore finisce nel registro.
     */
    /** @param array<string,mixed>|null $pagamento il blocco del pagamento online, se c'è */
    private function archive(string $reference, BookingRequest $booking, RoomOffer $offer, ?array $pagamento = null): bool
    {
        try {
            $this->app->inbox()->add('prenotazione', array_filter([
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
                'pagamento' => $pagamento,
            ], static fn ($v): bool => $v !== null), $reference);

            return true;
        } catch (\Throwable $e) {
            error_log('Richiesta ' . $reference . ' non salvata nell\'area riservata: ' . $e->getMessage());

            return false;
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
            'pagamento'      => $this->app->pagamentoAttivo(),
            'calendarioDemo' => (string) $this->app->config('booking.provider') === 'demo',
            'pagina'      => 'book',
            'titoloSeo'   => $this->app->translator()->get('book.seo_title'),
            'descrizione' => $this->app->translator()->get($this->app->pagamentoAttivo() ? 'book.seo_description_pay' : 'book.seo_description'),
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
