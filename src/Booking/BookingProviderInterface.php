<?php

declare(strict_types=1);

namespace ArcoDelVento\Booking;

/**
 * Il confine fra il sito e chiunque tenga il calendario.
 *
 * L'interfaccia utente della prenotazione non sa — e non deve sapere — se
 * dietro ci sia il provider dimostrativo incluso, un channel manager, un
 * booking engine o un gestionale. Sostituire il provider vuol dire scrivere
 * una classe che implementi questi tre metodi e cambiare BOOKING_PROVIDER
 * nel file .env: nessuna vista, nessun modulo e nessun percorso cambiano.
 */
interface BookingProviderInterface
{
    /** Le camere e il loro esito per il periodo chiesto. */
    public function search(SearchCriteria $criteria): AvailabilityResult;

    /** Il preventivo di una singola camera, ricalcolato al momento della scelta. */
    public function quote(SearchCriteria $criteria, string $roomRef): ?RoomOffer;

    /** Inoltra la richiesta e restituisce il riferimento con cui seguirla. */
    public function request(BookingRequest $request): BookingConfirmation;
}
