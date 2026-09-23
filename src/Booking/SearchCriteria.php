<?php

declare(strict_types=1);

namespace ArcoDelVento\Booking;

/**
 * Le tre cose che si chiedono a chi prenota: da quando, a quando, in quanti.
 */
final class SearchCriteria
{
    public function __construct(
        public readonly \DateTimeImmutable $arrival,
        public readonly \DateTimeImmutable $departure,
        public readonly int $guests,
    ) {
    }

    public function nights(): int
    {
        return max(0, (int) $this->arrival->diff($this->departure)->days);
    }

    /**
     * Le notti del soggiorno, una per una. Una «notte» è il giorno in cui si
     * dorme: un arrivo venerdì con partenza domenica sono le notti di venerdì
     * e di sabato, non quella di domenica.
     *
     * @return list<\DateTimeImmutable>
     */
    public function nightsList(): array
    {
        $notti = [];
        $n = $this->arrival;
        for ($i = 0; $i < $this->nights(); $i++) {
            $notti[] = $n;
            $n = $n->modify('+1 day');
        }

        return $notti;
    }

    /** Vero se fra le notti del soggiorno c'è un sabato. */
    public function includesSaturdayNight(): bool
    {
        foreach ($this->nightsList() as $notte) {
            if ((int) $notte->format('N') === 6) {
                return true;
            }
        }

        return false;
    }

    public function arrivalIso(): string
    {
        return $this->arrival->format('Y-m-d');
    }

    public function departureIso(): string
    {
        return $this->departure->format('Y-m-d');
    }

    /** @return array{arrival:string,departure:string,guests:int} per la sessione */
    public function toArray(): array
    {
        return [
            'arrival'   => $this->arrivalIso(),
            'departure' => $this->departureIso(),
            'guests'    => $this->guests,
        ];
    }

    /** @param array{arrival?:string,departure?:string,guests?:int|string} $data */
    public static function fromArray(array $data): ?self
    {
        $arrival   = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($data['arrival'] ?? ''));
        $departure = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($data['departure'] ?? ''));

        if (!$arrival || !$departure) {
            return null;
        }

        return new self($arrival, $departure, max(1, (int) ($data['guests'] ?? 1)));
    }
}
