<?php

declare(strict_types=1);

namespace ArcoDelVento\Repository;

/**
 * Le camere lette da MySQL.
 *
 * Si attiva da sola quando DB_DSN è compilato. Restituisce esattamente la
 * stessa forma di array di ArrayRoomRepository — è il punto di tutto: le
 * viste non cambiano, il motore di prenotazione non cambia, cambia solo da
 * dove arrivano i dati.
 *
 * Le tabelle sono quelle di database/schema.sql: `rooms`, `room_translations`,
 * `room_amenities`, `room_images`, `room_rates`.
 */
final class PdoRoomRepository implements RoomRepositoryInterface
{
    /** @var list<array<string,mixed>>|null cache per richiesta */
    private ?array $cache = null;

    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $rooms = $this->pdo
            ->query('SELECT * FROM rooms WHERE published = 1 ORDER BY position ASC')
            ->fetchAll();

        $byId = [];
        foreach ($rooms as $row) {
            $byId[(int) $row['id']] = $this->hydrate($row);
        }

        if ($byId !== []) {
            $ids = implode(',', array_map('intval', array_keys($byId)));

            foreach ($this->pdo->query("SELECT * FROM room_translations WHERE room_id IN ({$ids})") as $t) {
                $id     = (int) $t['room_id'];
                $locale = (string) $t['locale'];
                $byId[$id]['name'][$locale]        = (string) $t['name'];
                $byId[$id]['slug'][$locale]        = (string) $t['slug'];
                $byId[$id]['description'][$locale] = (string) ($t['description'] ?? '');
            }

            foreach ($this->pdo->query("SELECT room_id, guests, nightly_rate FROM room_rates WHERE room_id IN ({$ids}) ORDER BY guests ASC") as $r) {
                $byId[(int) $r['room_id']]['rates'][(int) $r['guests']] = (float) $r['nightly_rate'];
            }

            foreach ($this->pdo->query("SELECT room_id, amenity_key FROM room_amenities WHERE room_id IN ({$ids}) ORDER BY position ASC") as $a) {
                $byId[(int) $a['room_id']]['amenities'][] = (string) $a['amenity_key'];
            }

            foreach ($this->pdo->query("SELECT * FROM room_images WHERE room_id IN ({$ids}) ORDER BY position ASC") as $img) {
                $id   = (int) $img['room_id'];
                $role = (string) $img['role'];
                $item = ['src' => (string) $img['path'], 'ratio' => (string) $img['ratio'], 'alt' => (string) ($img['alt'] ?? '')];
                if ($role === 'gallery') {
                    $byId[$id]['images']['gallery'][] = $item;
                } else {
                    $byId[$id]['images'][$role] = $item;
                }
            }
        }

        return $this->cache = array_values($byId);
    }

    public function findByRef(string $ref): ?array
    {
        foreach ($this->all() as $room) {
            if ($room['ref'] === $ref) {
                return $room;
            }
        }

        return null;
    }

    public function findBySlug(string $slug, string $locale): ?array
    {
        foreach ($this->all() as $room) {
            if (($room['slug'][$locale] ?? null) === $slug) {
                return $room;
            }
        }

        return null;
    }

    public function forGuests(int $guests): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (array $room): bool => (int) ($room['occupancy']['max'] ?? 0) >= $guests
        ));
    }

    public function count(): int
    {
        return count($this->all());
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function hydrate(array $row): array
    {
        return [
            'id'             => (int) $row['id'],
            'ref'            => (string) $row['ref'],
            'position'       => (int) $row['position'],
            'confirmed'      => (bool) $row['confirmed'],
            'name_confirmed' => (bool) $row['name_confirmed'],
            'type'           => (string) $row['type_key'],
            'occupancy'      => [
                'standard' => (int) $row['occupancy_standard'],
                'max'      => (int) $row['occupancy_max'],
            ],
            'beds'     => json_decode((string) ($row['beds_json'] ?? '{}'), true) ?: [],
            'layouts'  => json_decode((string) ($row['layouts_json'] ?? '[]'), true) ?: [],
            'size_sqm' => $row['size_sqm'] !== null ? (int) $row['size_sqm'] : null,
            'floor'    => $row['floor'] !== null ? (string) $row['floor'] : null,
            'view'     => (string) ($row['view_key'] ?? 'demo'),
            'bathroom' => json_decode((string) ($row['bathroom_json'] ?? '{}'), true) ?: [],
            'price'    => [
                'confirmed' => (bool) $row['price_confirmed'],
                'currency'  => (string) ($row['currency'] ?? 'EUR'),
            ],
            // Riempite da room_rates qui sotto: una riga per numero di ospiti.
            'rates'    => [],
            'currency' => (string) ($row['currency'] ?? 'EUR'),
            'view_san_rufino' => $row['view_san_rufino'] !== null ? (bool) $row['view_san_rufino'] : null,
            'name'            => [],
            'slug'            => [],
            'description'     => [],
            'amenities'       => [],
            'images'          => ['gallery' => []],
        ];
    }
}
