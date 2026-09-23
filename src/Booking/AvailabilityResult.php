<?php

declare(strict_types=1);

namespace ArcoDelVento\Booking;

/**
 * Il risultato di una ricerca: le camere con il loro esito e, se non ce n'è
 * nessuna libera, le date libere più vicine.
 *
 * Il design system è esplicito su questo: «Quando non c'è disponibilità, la
 * risposta non è un vuoto: mostra le date libere più vicine.»
 */
final class AvailabilityResult
{
    /**
     * @param list<RoomOffer> $offers
     * @param list<array{arrival:string,departure:string}> $alternatives
     */
    public function __construct(
        public readonly SearchCriteria $criteria,
        public readonly array $offers,
        public readonly array $alternatives = [],
    ) {
    }

    /** @return list<RoomOffer> */
    public function available(): array
    {
        return array_values(array_filter($this->offers, static fn (RoomOffer $o): bool => $o->available));
    }

    public function hasAvailability(): bool
    {
        return $this->available() !== [];
    }

    public function find(string $ref): ?RoomOffer
    {
        foreach ($this->offers as $offer) {
            if ($offer->ref() === $ref) {
                return $offer;
            }
        }

        return null;
    }
}
