<?php

declare(strict_types=1);

namespace ArcoDelVento\Booking;

/**
 * La richiesta di prenotazione, pronta da consegnare al provider.
 */
final class BookingRequest
{
    public function __construct(
        public readonly SearchCriteria $criteria,
        public readonly RoomOffer $offer,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly string $phone = '',
        public readonly string $country = '',
        public readonly string $notes = '',
        public readonly string $locale = 'it',
    ) {
    }

    public function guestName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }
}
