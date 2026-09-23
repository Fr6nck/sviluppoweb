<?php

declare(strict_types=1);

namespace ArcoDelVento\Booking;

/**
 * L'esito di una richiesta inoltrata.
 *
 * Il riferimento non è un numero di prenotazione: è il codice con cui l'ospite
 * e Daniele parlano della stessa richiesta finché non diventa una prenotazione
 * vera. La distinzione è scritta anche nella pagina di conferma, perché è la
 * sola cosa che l'ospite deve capire di quella schermata.
 */
final class BookingConfirmation
{
    public function __construct(
        public readonly string $reference,
        public readonly BookingRequest $request,
        public readonly \DateTimeImmutable $receivedAt,
        public readonly string $status = 'requested',
    ) {
    }
}
