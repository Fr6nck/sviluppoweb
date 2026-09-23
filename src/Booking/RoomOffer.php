<?php

declare(strict_types=1);

namespace ArcoDelVento\Booking;

/**
 * Una camera con il suo esito per le date chieste: libera o no, e a quanto.
 */
final class RoomOffer
{
    /** @param array<string,mixed> $room */
    public function __construct(
        public readonly array $room,
        public readonly bool $available,
        public readonly int $nights,
        public readonly float $nightlyRate,
        public readonly float $total,
        public readonly string $currency,
        /** Vero quando la tariffa è dimostrativa e non una tariffa del cliente. */
        public readonly bool $rateIsDemo = true,
    ) {
    }

    public function ref(): string
    {
        return (string) $this->room['ref'];
    }
}
