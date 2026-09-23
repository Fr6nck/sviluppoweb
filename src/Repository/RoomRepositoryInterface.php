<?php

declare(strict_types=1);

namespace ArcoDelVento\Repository;

/**
 * Il contratto delle camere.
 *
 * Esiste perché il resto del sito non sappia mai da dove arrivino: oggi da
 * content/rooms.php, domani da MySQL. Le viste e il motore di prenotazione
 * parlano solo con questa interfaccia.
 *
 * Una camera è un array con questa forma (vedi content/rooms.php):
 *   id, ref, slug[lingua], name[lingua], name_confirmed, confirmed, position,
 *   type, occupancy{standard,max}, beds{tipo:quantità}, layouts[],
 *   size_sqm, floor, view, bathroom{}, amenities[], images{}, price{},
 *   view_san_rufino
 */
interface RoomRepositoryInterface
{
    /** @return list<array<string,mixed>> tutte le camere, in ordine di posizione */
    public function all(): array;

    /** @return array<string,mixed>|null */
    public function findByRef(string $ref): ?array;

    /** Trova una camera dallo slug di una lingua: /it/camere/camera-01 */
    public function findBySlug(string $slug, string $locale): ?array;

    /** @return list<array<string,mixed>> le camere che ospitano almeno :guests persone */
    public function forGuests(int $guests): array;

    public function count(): int;
}
