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
        $this->rooms = array_values($rooms);
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
