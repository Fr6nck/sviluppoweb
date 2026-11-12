<?php

declare(strict_types=1);

namespace ArcoDelVento\Mail;

use ArcoDelVento\App;
use ArcoDelVento\Booking\BookingRequest;
use ArcoDelVento\Booking\Fatturazione;
use ArcoDelVento\Booking\RoomOffer;

/**
 * Tutte le e-mail del sito, in un posto solo, con la grafica di Contenuto.
 *
 * Alla struttura, in italiano:
 *   - richiestaAllaStruttura(): una richiesta di prenotazione (pagamento spento);
 *   - pagataAllaStruttura(): una prenotazione pagata con SumUp;
 *   - contattoAllaStruttura(): un messaggio del modulo contatti;
 *   - prova(): il messaggio di prova di Ricezione e-mail.
 *
 * All'ospite, nella lingua in cui ha prenotato:
 *   - ricevutaAllOspite(): la richiesta è arrivata, non è ancora confermata;
 *   - confermataAllOspite(): pagata, o confermata da Daniele nel pannello,
 *     con il soggiorno da aggiungere al calendario (Google, Apple, Outlook)
 *     e il file .ics allegato.
 *
 * Tutto quello che c'è scritto viene dalla prenotazione e dalle impostazioni
 * della casa: un orario o una regola che non si conoscono non si scrivono.
 * Chi decide quando spedire resta dov'era (BookingController,
 * AvvisiPagamento, AdminController): qui si scrive soltanto.
 */
final class Messaggi
{
    public function __construct(private readonly App $app)
    {
    }

    // ============================================================ alla struttura

    public function richiestaAllaStruttura(string $riferimento, BookingRequest $booking, RoomOffer $offer): MailMessage
    {
        $camera = (string) ($offer->room['name']['it'] ?? $offer->ref());
        $c = (new Contenuto('it'))
            ->intestazione('Richiesta ' . $riferimento, $booking->guestName() . ' · ' . $this->giorno($booking->criteria->arrivalIso())
                . ' → ' . $this->giorno($booking->criteria->departureIso()) . ' · ' . $camera, 'Nuova richiesta di prenotazione')
            ->paragrafo('È arrivata una richiesta di prenotazione dal sito. Prima di confermare, guarda il Calendario dell\'area riservata: '
                . 'le prenotazioni prese al telefono, e quelle di Booking senza il calendario iCal collegato, il sito non le conosce.')
            ->dati('Il soggiorno', [
                ['Riferimento', $riferimento],
                ['Camera', $camera . ' (' . $offer->ref() . ')'],
                ['Arrivo', $this->giorno($booking->criteria->arrivalIso())],
                ['Partenza', $this->giorno($booking->criteria->departureIso())],
                ['Notti', (string) $offer->nights],
                ['Ospiti', (string) $booking->criteria->guests],
                ['Totale', $this->euro($offer->total) . ($offer->rateIsDemo ? ' (tariffa dimostrativa)' : '')],
            ])
            ->dati('L\'ospite', [
                ['Nome', $booking->guestName()],
                ['E-mail', $booking->email],
                ['Telefono', $booking->phone !== '' ? $booking->phone : '—'],
                ['Paese', $booking->country !== '' ? $booking->country : '—'],
                ['Lingua', $booking->locale],
            ])
            ->dati('Note', [['Note', $booking->notes !== '' ? $booking->notes : '—']])
            ->pulsanti([
                ['Apri nell\'area riservata', $this->admin('richieste/' . rawurlencode($riferimento)), true],
                ['Rispondi a ' . $booking->firstName, 'mailto:' . $booking->email],
            ])
            ->piede($this->piedeStruttura());

        return $this->messaggio(
            $this->app->destinatari(),
            sprintf('[Arco del Vento] Richiesta %s — %s', $riferimento, $camera),
            $c,
            $booking->email,
        );
    }

    /** @param array<string,mixed> $voce la prenotazione pagata, com'è nell'archivio delle richieste */
    public function pagataAllaStruttura(array $voce): MailMessage
    {
        $d = (array) $voce['dati'];
        $p = (array) ($d['pagamento'] ?? []);
        $daControllare = ($p['stato'] ?? '') === 'da controllare';
        $prova = !empty($p['prova']);

        $c = (new Contenuto('it'))->intestazione(
            (string) $voce['id'] . ' · ' . ($d['camera'] ?? ''),
            ($d['nome'] ?? '') . ' · ' . $this->giorno((string) ($d['arrivo'] ?? '')) . ' → ' . $this->giorno((string) ($d['partenza'] ?? '')),
            $daControllare ? 'Pagamento da controllare' : 'Prenotazione pagata',
        );
        if ($prova) {
            $c->avviso('PROVA: pagamento sul conto di prova di SumUp. Nessun addebito vero, la camera non è prenotata.', 'prova');
        }
        if ($daControllare) {
            $c->avviso('SumUp dice che la prenotazione è pagata, ma l\'importo non coincide. Controlla nel pannello di SumUp: all\'ospite la conferma non è partita.');
        }
        $c->paragrafo($daControllare ? '' : 'La prenotazione è pagata dal sito, e all\'ospite è partita l\'e-mail di conferma con il soggiorno da aggiungere al calendario.')
            ->dati('Il soggiorno', [
                ['Riferimento', (string) $voce['id']],
                ['Camera', ($d['camera'] ?? '') . ' (' . ($d['ref'] ?? '') . ')'],
                ['Arrivo', $this->giorno((string) ($d['arrivo'] ?? ''))],
                ['Partenza', $this->giorno((string) ($d['partenza'] ?? ''))],
                ['Notti', (string) ($d['notti'] ?? '')],
                ['Ospiti', (string) ($d['ospiti'] ?? '')],
            ])
            ->dati('Il pagamento', [
                ['Da pagare', $this->euro((float) ($p['importo'] ?? 0))],
                ['Pagato', $this->euro((float) ($p['pagato'] ?? 0)) . ' su SumUp'],
                ['Codice transazione', (string) ($p['codice'] ?? '')],
            ])
            ->dati('L\'ospite', [
                ['Nome', (string) ($d['nome'] ?? '')],
                ['E-mail', (string) ($d['email'] ?? '')],
                ['Telefono', ($d['telefono'] ?? '') !== '' ? (string) $d['telefono'] : '—'],
                ['Lingua', (string) ($d['lingua'] ?? '')],
            ]);
        $fatturazione = Fatturazione::righe((array) ($d['fatturazione'] ?? []));
        if (count($fatturazione) > 1) {
            $c->dati('Dati di fatturazione', array_map(static fn ($k, $v): array => [(string) $k, (string) $v], array_keys($fatturazione), $fatturazione));
        }
        if (in_array((string) $this->app->config('booking.provider'), ['demo', 'sito', ''], true)) {
            $c->nota('Il calendario del sito non conosce le prenotazioni prese al telefono, e quelle di Booking solo se il calendario iCal è collegato: '
                . 'controlla che le date siano libere. Se non lo sono, rimborsa dal pannello di SumUp e scrivi all\'ospite.');
        }
        $c->pulsanti([['Apri nell\'area riservata', $this->admin('richieste/' . rawurlencode((string) $voce['id'])), true]])
            ->piede($this->piedeStruttura());

        return $this->messaggio(
            $this->app->destinatari(),
            sprintf('[Arco del Vento] %s%s %s — %s, %s', $prova ? 'PROVA · ' : '', $daControllare ? 'DA CONTROLLARE' : 'PAGATA',
                $voce['id'], (string) ($d['camera'] ?? ''), $this->giorno((string) ($d['arrivo'] ?? ''))),
            $c,
            (string) ($d['email'] ?? ''),
        );
    }

    /** @param array{oggetto: string, nome: string, email: string, telefono: string, messaggio: string, lingua: string} $m */
    public function contattoAllaStruttura(array $m, ?string $id = null): MailMessage
    {
        $nome = trim(strtok($m['nome'], ' ') ?: $m['nome']);
        $c = (new Contenuto('it'))
            ->intestazione($m['oggetto'], $m['nome'] . ': ' . mb_substr(preg_replace('/\s+/', ' ', $m['messaggio']) ?? '', 0, 90), 'Nuovo messaggio dal sito')
            ->dati('Chi scrive', [
                ['Nome', $m['nome']],
                ['E-mail', $m['email']],
                ['Telefono', $m['telefono'] !== '' ? $m['telefono'] : '—'],
                ['Lingua', $m['lingua']],
            ])
            ->dati('Il messaggio', [['Messaggio', $m['messaggio']]])
            ->pulsanti([
                ['Rispondi a ' . $nome, 'mailto:' . $m['email'], true],
                ['Apri nell\'area riservata', $this->admin($id !== null ? 'richieste/' . rawurlencode($id) : 'richieste')],
            ])
            ->piede($this->piedeStruttura());

        return $this->messaggio($this->app->destinatari(), '[Arco del Vento] ' . $m['oggetto'] . ' — ' . $m['nome'], $c, $m['email']);
    }

    public function prova(string $destinatari): MailMessage
    {
        $c = (new Contenuto('it'))
            ->intestazione('La posta del sito funziona', 'Messaggio di prova dall\'area riservata', 'Messaggio di prova')
            ->paragrafo('Questo è un messaggio di prova mandato dall\'area riservata del sito.')
            ->paragrafo('Se lo stai leggendo, le richieste di prenotazione e i messaggi del modulo contatti arrivano a questo indirizzo.')
            ->nota('Mandato il ' . date('d/m/Y \a\l\l\e H:i') . '.')
            ->piede($this->piedeStruttura());

        return $this->messaggio($destinatari, '[Arco del Vento] Messaggio di prova', $c);
    }

    // ================================================================= all'ospite

    /** La richiesta è arrivata: non è ancora una prenotazione. Pagamento online spento. */
    public function ricevutaAllOspite(string $riferimento, BookingRequest $booking, RoomOffer $offer): MailMessage
    {
        $lingua = $this->lingua($booking->locale);
        $t = $this->traduttore($lingua);
        $camera = (string) ($offer->room['name'][$lingua] ?? $offer->room['name']['it'] ?? $offer->ref());

        $c = (new Contenuto($lingua))
            ->intestazione($t('mail.received_title'), $camera . ' · ' . $this->giorno($booking->criteria->arrivalIso())
                . ' → ' . $this->giorno($booking->criteria->departureIso()), $t('mail.received_eyebrow'))
            ->saluto($t('mail.hello', ['name' => $booking->firstName]))
            ->paragrafo($t('mail.received_intro'))
            ->dati($t('mail.details'), [
                [$t('mail.reference'), $riferimento],
                [$t('mail.room'), $camera],
                [$t('mail.arrival'), $this->giorno($booking->criteria->arrivalIso())],
                [$t('mail.departure'), $this->giorno($booking->criteria->departureIso())],
                [$t('mail.nights'), (string) $offer->nights],
                [$t('mail.guests'), (string) $booking->criteria->guests],
                [$t('summary.total'), $this->euro($offer->total)],
            ])
            ->nota($t('summary.total_note'))
            ->paragrafo($t('mail.received_next'));
        $this->chiusa($c, $t);

        return $this->messaggio($booking->email, $t('mail.received_subject', ['ref' => $riferimento]), $c, $this->rispondiA());
    }

    /**
     * La prenotazione è confermata: pagata con SumUp, oppure confermata da
     * Daniele. Con il soggiorno da aggiungere al calendario.
     *
     * @param array<string,mixed> $voce
     */
    public function confermataAllOspite(array $voce): MailMessage
    {
        $d = (array) $voce['dati'];
        $p = (array) ($d['pagamento'] ?? []);
        $pagata = ($p['stato'] ?? '') === 'pagato';
        $prova = $pagata && !empty($p['prova']);
        $lingua = $this->lingua((string) ($d['lingua'] ?? ''));
        $t = $this->traduttore($lingua);
        $impostazioni = $this->app->settings();
        $stay = (array) ($impostazioni['stay'] ?? []);
        $camera = (string) ($this->app->rooms()->findByRef((string) ($d['ref'] ?? ''))['name'][$lingua] ?? $d['camera'] ?? '');

        $arrivo = $this->giorno((string) $d['arrivo']);
        if (!empty($stay['check_in_from']) && !empty($stay['check_in_to'])) {
            $arrivo .= "\n" . ucfirst($t('mail.check_in', ['from' => $stay['check_in_from'], 'to' => $stay['check_in_to']]));
        }
        $partenza = $this->giorno((string) $d['partenza']);
        if (!empty($stay['check_out_by'])) {
            $partenza .= "\n" . ucfirst($t('mail.check_out', ['time' => $stay['check_out_by']]));
        }

        $c = (new Contenuto($lingua))->intestazione(
            $t('mail.confirmed_title'),
            $camera . ' · ' . $this->giorno((string) $d['arrivo']) . ' → ' . $this->giorno((string) $d['partenza']) . ' · ' . $voce['id'],
            $t('mail.confirmed_eyebrow'),
        );
        if ($prova) {
            $c->avviso($t('mail.test_note'), 'prova');
        }
        $c->saluto($t('mail.hello', ['name' => strtok((string) ($d['nome'] ?? ''), ' ') ?: (string) ($d['nome'] ?? '')]))
            ->paragrafo($pagata ? $t('mail.intro') : $t('mail.intro_confirmed'))
            ->dati($t('mail.details'), [
                [$t('mail.reference'), (string) $voce['id']],
                [$t('mail.room'), $camera],
                [$t('mail.arrival'), $arrivo],
                [$t('mail.departure'), $partenza],
                [$t('mail.nights'), (string) ($d['notti'] ?? '')],
                [$t('mail.guests'), (string) ($d['ospiti'] ?? '')],
                $pagata
                    ? [$t('mail.paid'), $this->euro((float) ($p['pagato'] ?? $p['importo'] ?? 0)) . ' (SumUp' . (!empty($p['codice']) ? ', ' . $p['codice'] : '') . ')']
                    : [$t('summary.total'), $this->euro((float) ($d['totale'] ?? 0))],
            ]);
        if (!$pagata) {
            $c->nota($t('summary.total_note'));
        }

        // Il calendario: Google con un link, Apple e Outlook con il file .ics (link e allegato).
        $calendario = $this->app->calendarioOspite();
        $c->pulsanti([
            [$t('mail.calendar_google'), $calendario->google($voce, $lingua), true],
            [$t('mail.calendar_apple'), $calendario->link((string) $voce['id'])],
        ], $t('mail.calendar_title'))
            ->nota($t('mail.calendar_note'));

        // I dati di fatturazione come li ha scritti, così li può controllare.
        $f = (array) ($d['fatturazione'] ?? []);
        if (Fatturazione::indirizzo($f) !== '') {
            $c->dati(rtrim($t('mail.billing'), ':'), array_values(array_filter([
                !empty($f['azienda']) ? [(string) $f['ragione_sociale'], $t('pay.billing.vat') . ' ' . $f['partita_iva']] : null,
                [$t('mail.address'), Fatturazione::indirizzo($f)],
                !empty($f['codice_fiscale']) ? [$t('pay.billing.tax_code'), (string) $f['codice_fiscale']] : null,
            ])));
        }
        $tassa = (array) ($stay['city_tax'] ?? []);
        if (!empty($tassa['amount'])) {
            $c->nota($t('pay.city_tax', [
                'amount' => number_format((float) $tassa['amount'], 2, ',', '.'),
                'nights' => (string) ($tassa['max_nights'] ?? ''),
                'age'    => (string) ($tassa['exempt_under'] ?? ''),
            ]));
        }
        $cancellazione = $stay['cancellation'][$lingua] ?? null;
        if (is_string($cancellazione) && trim($cancellazione) !== '') {
            $c->nota($t('pay.cancellation') . ': ' . trim($cancellazione));
        }
        $this->chiusa($c, $t);

        return $this->messaggio(
            (string) ($d['email'] ?? ''),
            ($prova ? $t('mail.test_subject') . ' ' : '') . $t('mail.subject', ['ref' => (string) $voce['id']]),
            $c,
            $this->rispondiA(),
            [['nome' => 'arco-del-vento-' . strtolower((string) $voce['id']) . '.ics', 'tipo' => 'text/calendar; charset=UTF-8; method=PUBLISH',
              'contenuto' => $calendario->ics($voce, $lingua)]],
        );
    }

    // ===================================================================== interni

    /** Dove si trova la casa e come si raggiunge, poi la firma e il piè di pagina. */
    private function chiusa(Contenuto $c, callable $t): void
    {
        $impostazioni = $this->app->settings();
        $indirizzo = (array) ($impostazioni['address'] ?? []);
        $via = trim(((string) ($indirizzo['street'] ?? '')) . ', ' . trim(((string) ($indirizzo['postal_code'] ?? '')) . ' ' . (string) ($indirizzo['city'] ?? '')), ', ');
        $telefono = (string) ($impostazioni['contacts']['phone'] ?? '');
        $c->dati($t('mail.where'), [[$t('mail.address'), $via], [$t('mail.phone'), $telefono]]);
        if ($telefono !== '') {
            $c->paragrafo($t('mail.contact', ['phone' => $telefono]));
        }
        $c->firma($t('mail.sign'))->piede(['Arco del Vento · ' . $via, $telefono]);
    }

    /** @return list<string> */
    private function piedeStruttura(): array
    {
        return ['Mandata dal sito di Arco del Vento il ' . date('d/m/Y \a\l\l\e H:i') . '.'];
    }

    /**
     * @param list<array{nome: string, tipo: string, contenuto: string}> $allegati
     */
    private function messaggio(string $a, string $oggetto, Contenuto $c, string $rispondiA = '', array $allegati = []): MailMessage
    {
        return new MailMessage(
            to:          $a,
            subject:     $oggetto,
            body:        $c->testo(),
            fromAddress: (string) $this->app->config('mail.from.address'),
            fromName:    (string) $this->app->config('mail.from.name'),
            replyTo:     $rispondiA,
            html:        $c->html($this->app),
            allegati:    $allegati,
        );
    }

    /** Rispondere a un'e-mail per l'ospite scrive alla struttura, non al mittente automatico. */
    private function rispondiA(): string
    {
        return MailMessage::indirizzi($this->app->destinatari())[0] ?? (string) ($this->app->settings()['contacts']['email'] ?? '');
    }

    private function lingua(string $lingua): string
    {
        return in_array($lingua, (array) $this->app->config('i18n.available'), true) ? $lingua : 'it';
    }

    /** @return callable(string, array<string,mixed>=): string */
    private function traduttore(string $lingua): callable
    {
        return fn (string $chiave, array $sost = []): string => $this->app->translator()->get('book.' . $chiave, $sost, $lingua);
    }

    private function admin(string $sotto): string
    {
        return $this->app->indirizzoCompleto(\ArcoDelVento\I18n\Routes::base() . '/admin/' . ltrim($sotto, '/'));
    }

    private function giorno(string $iso): string
    {
        $t = strtotime($iso);

        return $t ? date('d/m/Y', $t) : $iso;
    }

    private function euro(float $importo): string
    {
        return '€ ' . number_format($importo, 2, ',', '.');
    }
}
