<?php

declare(strict_types=1);

namespace ArcoDelVento\Booking;

/**
 * Il risultato di una ricerca: le camere con il loro esito. Le date libere
 * vicine non si propongono: al cliente si dice solo quali camere sono libere
 * nel periodo che ha chiesto.
 */
final class AvailabilityResult
{
    /**
     * @param list<RoomOffer> $offers
     */
    public function __construct(
        public readonly SearchCriteria $criteria,
        public readonly array $offers,
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
