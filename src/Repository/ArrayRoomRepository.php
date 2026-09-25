<?php

declare(strict_types=1);

namespace ArcoDelVento\Repository;

/**
 * Le camere lette da content/rooms.php.
 *
 * È l'implementazione del prototipo e resta perfettamente valida in
 * produzione per una struttura di cinque camere che cambia i dati due volte
 * l'anno: nessun database da mantenere, nessuna migrazione, un file che si
 * modifica e si carica via FTP.
 */
final class ArrayRoomRepository implements RoomRepositoryInterface
{
    /** @var list<array<string,mixed>> */
    private array $rooms;

    /** @param list<array<string,mixed>> $rooms */
    public function __construct(array $rooms)
    {
        usort($rooms, static fn (array $a, array $b): int => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));
        $this->rooms = array_values(array_map([self::class, 'inVendita'], $rooms));
    }

    /**
     * La camera come si vende: fino al numero di ospiti che ha un prezzo.
     *
     * occupancy.max nel file è quante persone la camera può ospitare, ed è
     * quanti campi di tariffa mostra il pannello. Ma se per quel numero la
     * tariffa manca, il sito non lo può vendere né scrivere: la capienza in
     * vendita scende all'ultimo numero di ospiti con un prezzo, e quella
     * dichiarata resta in occupancy.capacity. Così aggiungere la tariffa dal
     * pannello basta ad aprire la camera a una persona in più, e toglierla
     * basta a chiuderla.
     *
     * Una «singola con letto matrimoniale» venduta anche a due è, per chi
     * prenota, una matrimoniale: tipologia e disposizioni seguono.
     *
     * @param array<string,mixed> $room
     * @return array<string,mixed>
     */
    public static function inVendita(array $room): array
    {
        $dichiarata = max(1, (int) ($room['occupancy']['max'] ?? 1));
        $conPrezzo  = array_keys(array_filter(
            (array) ($room['rates'] ?? []),
            static fn ($prezzo): bool => is_numeric($prezzo) && (float) $prezzo > 0
        ));
        $venduta = $conPrezzo === [] ? $dichiarata : max(1, min($dichiarata, max(array_map('intval', $conPrezzo))));

        $room['occupancy']['capacity'] = $dichiarata;
        $room['occupancy']['max']      = $venduta;
        $room['occupancy']['standard'] = min((int) ($room['occupancy']['standard'] ?? $venduta), $venduta);

        if (($room['type'] ?? '') === 'single-double' && $venduta >= 2) {
            $room['type']    = 'double';
            $room['layouts'] = array_values(array_unique(['double', ...(array) ($room['layouts'] ?? [])]));
        }

        return $room;
    }

    public function all(): array
    {
        return $this->rooms;
    }

    public function findByRef(string $ref): ?array
    {
        foreach ($this->rooms as $room) {
            if (($room['ref'] ?? null) === $ref) {
                return $room;
            }
        }

        return null;
    }

    public function findBySlug(string $slug, string $locale): ?array
    {
        foreach ($this->rooms as $room) {
            if (($room['slug'][$locale] ?? null) === $slug) {
                return $room;
            }
        }

        return null;
    }

    public function forGuests(int $guests): array
    {
        return array_values(array_filter(
            $this->rooms,
            static fn (array $room): bool => (int) ($room['occupancy']['max'] ?? 0) >= $guests
        ));
    }

    public function count(): int
    {
        return count($this->rooms);
    }
}
