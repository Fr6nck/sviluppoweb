<?php

declare(strict_types=1);

namespace ArcoDelVento\Booking;

use ArcoDelVento\Repository\RoomRepositoryInterface;

/**
 * Il provider dimostrativo.
 *
 * NON è un calendario reale. Serve a far funzionare tutto il percorso di
 * prenotazione — date, disponibilità, scelta, totale, richiesta — mentre il
 * gestionale vero non c'è ancora. Il sito, davanti, non sa che è finto: parla
 * con BookingProviderInterface e basta.
 *
 * La disponibilità è deterministica: la stessa camera, nella stessa notte, dà
 * sempre la stessa risposta. È un requisito, non un vezzo — una disponibilità
 * casuale renderebbe impossibile provare il percorso, perché la camera scelta
 * al passo due sparirebbe al passo tre.
 *
 * Per sostituirlo con un provider reale: una classe che implementi
 * BookingProviderInterface, e BOOKING_PROVIDER nel .env che la selezioni
 * in App::bookingProvider(). Nient'altro nel sito cambia.
 */
final class DemoBookingProvider implements BookingProviderInterface
{
    /** Quanti giorni in avanti cercare quando le date chieste sono piene. */
    private const ALTERNATIVE_WINDOW_DAYS = 60;

    public function __construct(
        private readonly RoomRepositoryInterface $rooms,
        private readonly int $minNights = 2,
        private readonly string $currency = 'EUR',
    ) {
    }

    public function search(SearchCriteria $criteria): AvailabilityResult
    {
        $offers = [];
        foreach ($this->rooms->all() as $room) {
            // Ospita quel numero di persone E ha una tariffa per quel numero:
            // le due cose vanno insieme, e la seconda è quella che dice il
            // prezzo da mettere in pagina.
            $fitsGuests = (int) ($room['occupancy']['max'] ?? 0) >= $criteria->guests
                && self::rateFor($room, $criteria->guests) !== null;
            $free       = $fitsGuests && $this->isFree((string) $room['ref'], $criteria);
            $offers[]   = $this->makeOffer($room, $criteria, $free);
        }

        $alternatives = [];
        if (!array_filter($offers, static fn (RoomOffer $o): bool => $o->available)) {
            $alternatives = $this->findAlternatives($criteria);
        }

        return new AvailabilityResult($criteria, $offers, $alternatives);
    }

    public function quote(SearchCriteria $criteria, string $roomRef): ?RoomOffer
    {
        $room = $this->rooms->findByRef($roomRef);
        if ($room === null) {
            return null;
        }

        $free = (int) ($room['occupancy']['max'] ?? 0) >= $criteria->guests
            && self::rateFor($room, $criteria->guests) !== null
            && $this->isFree($roomRef, $criteria);

        return $this->makeOffer($room, $criteria, $free);
    }

    public function request(BookingRequest $request): BookingConfirmation
    {
        // Un riferimento leggibile al telefono: ADV-<data d'arrivo>-<4 caratteri>.
        $reference = sprintf(
            'ADV-%s-%s',
            $request->criteria->arrival->format('ymd'),
            strtoupper(substr(bin2hex(random_bytes(3)), 0, 4))
        );

        return new BookingConfirmation($reference, $request, new \DateTimeImmutable('now'));
    }

    // ----------------------------------------------------------- interni

    /** @param array<string,mixed> $room */
    private function makeOffer(array $room, SearchCriteria $criteria, bool $available): RoomOffer
    {
        $nights = max(1, $criteria->nights());
        $tariffa = self::rateFor($room, $criteria->guests);

        return new RoomOffer(
            room:        $room,
            available:   $available && $tariffa !== null,
            nights:      $nights,
            nightlyRate: $tariffa ?? 0.0,
            total:       round(($tariffa ?? 0.0) * $nights, 2),
            currency:    (string) ($room['price']['currency'] ?? $this->currency),
            rateIsDemo:  !(bool) ($room['price']['confirmed'] ?? false),
        );
    }

    /**
     * La tariffa a notte per quel numero di ospiti.
     *
     * Le tariffe di questa casa non sono stagionali: dipendono da quante
     * persone dormono nella camera, ed è così che il titolare le ha date.
     * Una matrimoniale occupata da una persona sola costa meno, e il sito
     * deve dirlo.
     *
     * Restituisce null quando per quel numero di ospiti la camera non ha una
     * tariffa: vuol dire che non li ospita, e quindi non è prenotabile.
     *
     * @param array<string,mixed> $room
     */
    public static function rateFor(array $room, int $guests): ?float
    {
        $tariffe = (array) ($room['rates'] ?? []);

        return isset($tariffe[$guests]) ? (float) $tariffe[$guests] : null;
    }

    /** Una camera è libera solo se lo sono tutte le notti del soggiorno. */
    private function isFree(string $ref, SearchCriteria $criteria): bool
    {
        $night  = $criteria->arrival;
        $nights = max(1, $criteria->nights());

        for ($i = 0; $i < $nights; $i++) {
            if (!$this->isNightFree($ref, $night)) {
                return false;
            }
            $night = $night->modify('+1 day');
        }

        return true;
    }

    /**
     * Deterministico: stessa camera, stessa notte, stessa risposta.
     * Circa una notte su cinque risulta occupata — abbastanza da far vedere
     * anche il caso «non c'è posto», che è metà del lavoro di un motore di
     * prenotazione.
     */
    private function isNightFree(string $ref, \DateTimeImmutable $night): bool
    {
        $seed = crc32($ref . '|' . $night->format('Y-m-d'));

        return ($seed % 100) >= 20;
    }

    /**
     * Le date libere più vicine, cercate in avanti giorno per giorno.
     *
     * @return list<array{arrival:string,departure:string}>
     */
    private function findAlternatives(SearchCriteria $criteria, int $limit = 3): array
    {
        $nights = max($this->minNights, $criteria->nights());
        $found  = [];

        for ($offset = 1; $offset <= self::ALTERNATIVE_WINDOW_DAYS && count($found) < $limit; $offset++) {
            $arrival   = $criteria->arrival->modify("+{$offset} day");
            $departure = $arrival->modify("+{$nights} day");
            $candidate = new SearchCriteria($arrival, $departure, $criteria->guests);

            foreach ($this->rooms->forGuests($criteria->guests) as $room) {
                if ($this->isFree((string) $room['ref'], $candidate)) {
                    $found[] = [
                        'arrival'   => $candidate->arrivalIso(),
                        'departure' => $candidate->departureIso(),
                    ];
                    // Una proposta per data d'arrivo: tre righe, non trenta.
                    continue 2;
                }
            }
        }

        return $found;
    }
}
