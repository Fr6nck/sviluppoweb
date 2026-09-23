<?php

declare(strict_types=1);

namespace ArcoDelVento\Booking;

use ArcoDelVento\Repository\RoomRepositoryInterface;

/**
 * Il livello che sta fra l'interfaccia e il provider.
 *
 * Qui stanno le regole che valgono comunque, chiunque tenga il calendario:
 * una partenza dopo l'arrivo, un soggiorno minimo, un tetto ragionevole alla
 * durata. Il provider risponde della disponibilità; questo servizio risponde
 * del fatto che gli si chiedano solo cose sensate.
 */
final class BookingService
{
    /** Oltre questa soglia si passa da una richiesta a una trattativa. */
    private const MAX_NIGHTS = 30;

    public function __construct(
        private readonly BookingProviderInterface $provider,
        private readonly RoomRepositoryInterface $rooms,
        private readonly int $minNights = 1,
        /**
         * Il sabato notte non si vende da solo: un soggiorno che lo comprende
         * dura almeno due notti. Vale tutto l'anno, ed è una regola della
         * casa, non un'invenzione del sito — quindi sta qui e non sepolta in
         * un provider che un giorno verrà sostituito.
         */
        private readonly int $saturdayMinNights = 1,
    ) {
    }

    public function saturdayMinNights(): int
    {
        return $this->saturdayMinNights;
    }

    public function minNights(): int
    {
        return $this->minNights;
    }

    public function maxNights(): int
    {
        return self::MAX_NIGHTS;
    }

    public function rooms(): RoomRepositoryInterface
    {
        return $this->rooms;
    }

    /**
     * Controlla le date prima di chiedere qualunque cosa al provider.
     *
     * @return array<string,array{0:string,1:array<string,string|int>}> campo => [chiave, sostituzioni]
     */
    public function validateCriteria(SearchCriteria $criteria, ?\DateTimeImmutable $today = null): array
    {
        $today  = ($today ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        $errors = [];

        if ($criteria->arrival < $today) {
            $errors['arrival'] = ['past', []];
        }

        if ($criteria->departure <= $criteria->arrival) {
            $errors['departure'] = ['order', []];

            return $errors;   // senza un ordine valido le altre regole non si possono applicare
        }

        $nights = $criteria->nights();

        // La regola del sabato è più stretta di quella generale, quindi vince.
        $minimo = $criteria->includesSaturdayNight()
            ? max($this->minNights, $this->saturdayMinNights)
            : $this->minNights;

        if ($nights < $minimo) {
            $errors['departure'] = $criteria->includesSaturdayNight() && $minimo > $this->minNights
                ? ['saturday_min', ['nights' => $minimo]]
                : ['min_nights', ['nights' => $minimo]];
        } elseif ($nights > self::MAX_NIGHTS) {
            $errors['departure'] = ['max_stay', ['nights' => self::MAX_NIGHTS]];
        }

        return $errors;
    }

    public function search(SearchCriteria $criteria): AvailabilityResult
    {
        return $this->provider->search($criteria);
    }

    public function quote(SearchCriteria $criteria, string $roomRef): ?RoomOffer
    {
        return $this->provider->quote($criteria, $roomRef);
    }

    public function request(BookingRequest $request): BookingConfirmation
    {
        return $this->provider->request($request);
    }
}
