<?php

declare(strict_types=1);

namespace ArcoDelVento\Booking;

use ArcoDelVento\Storage\JsonStore;

/**
 * I blocchi manuali: notti che si chiudono a mano dal Calendario dell'area
 * riservata — per una prenotazione arrivata su Booking.com che il calendario
 * iCal non mostra ancora (arriva con 15-30 minuti di ritardo), o per una presa
 * al telefono.
 *
 * Un blocco vale da subito: le sue notti entrano in App::nottiOccupate() e la
 * camera non si prenota più dal sito. Si toglie dal Calendario. Quando la
 * stessa prenotazione arriva da Booking, il calendario conta quelle notti una
 * volta sola (Admin\Calendario dà la precedenza a Booking) e il blocco si
 * segna come «coperto»: non c'è conflitto e non c'è doppione.
 *
 * Stanno in storage/data/blocchi-manuali.json, come le altre modifiche del
 * pannello. Una notte è quella che comincia quel giorno: il blocco dal 7 al
 * 10 chiude le notti del 7, 8 e 9. I blocchi finiti si tolgono da soli.
 */
final class BlocchiManuali
{
    private const ARCHIVIO = 'blocchi-manuali';

    /** Il blocco più lungo, in notti. */
    public const NOTTI_MAX = 366;

    public const NOTA_MAX = 200;

    public function __construct(private readonly JsonStore $store)
    {
    }

    /**
     * I blocchi da oggi in poi, dal più vicino.
     *
     * @return list<array{id: string, ref: string, arrivo: string, partenza: string, nota: string, creato: string, utente: string}>
     */
    public function tutti(): array
    {
        $oggi = date('Y-m-d');
        $out  = array_values(array_filter(
            (array) ($this->store->read(self::ARCHIVIO)['blocchi'] ?? []),
            static fn ($b): bool => is_array($b) && (string) ($b['partenza'] ?? '') > $oggi
        ));
        usort($out, static fn (array $a, array $b): int => [$a['arrivo'], $a['ref'], $a['creato']] <=> [$b['arrivo'], $b['ref'], $b['creato']]);

        return $out;
    }

    /**
     * Le notti bloccate, nel formato di App::nottiOccupate().
     *
     * @return list<array{id: string, ref: string, arrivo: string, partenza: string, fonte: string, nota: string}>
     */
    public function occupate(): array
    {
        return array_map(static fn (array $b): array => [
            'id' => (string) $b['id'], 'ref' => (string) $b['ref'], 'arrivo' => (string) $b['arrivo'],
            'partenza' => (string) $b['partenza'], 'fonte' => 'manuale', 'nota' => (string) ($b['nota'] ?? ''),
        ], $this->tutti());
    }

    /**
     * Che cosa non va in un blocco, detto a chi lo scrive. Vuoto se va bene.
     *
     * @param list<string> $camere i riferimenti delle camere che esistono
     * @return list<string>
     */
    public static function problemi(string $ref, string $dal, string $al, string $nota, array $camere): array
    {
        $errori = [];
        if (!in_array($ref, $camere, true)) {
            $errori[] = 'Scegli la camera da bloccare.';
        }
        $da = self::data($dal);
        $a  = self::data($al);
        if ($da === null) {
            $errori[] = 'Scrivi la data «Dal», la prima notte da bloccare.';
        } elseif ($da < date('Y-m-d')) {
            $errori[] = 'La data «Dal» è già passata.';
        }
        if ($a === null) {
            $errori[] = 'Scrivi la data «Al», il giorno di partenza.';
        }
        if ($da !== null && $a !== null) {
            $notti = (int) (new \DateTimeImmutable($da))->diff(new \DateTimeImmutable($a))->format('%r%a');
            if ($notti < 1) {
                $errori[] = '«Al» è il giorno di partenza e deve venire dopo «Dal»: per bloccare solo la notte del 7, dal 7 all\'8.';
            } elseif ($notti > self::NOTTI_MAX) {
                $errori[] = 'Al massimo ' . self::NOTTI_MAX . ' notti per volta.';
            }
        }
        if (mb_strlen($nota) > self::NOTA_MAX) {
            $errori[] = 'La nota: al massimo ' . self::NOTA_MAX . ' caratteri.';
        }

        return $errori;
    }

    /** Aggiunge un blocco già controllato con problemi(). Restituisce il suo identificativo. */
    public function aggiungi(string $ref, string $dal, string $al, string $nota, string $utente): string
    {
        $id = 'BL-' . strtoupper(bin2hex(random_bytes(4)));
        $blocco = [
            'id' => $id, 'ref' => $ref, 'arrivo' => $dal, 'partenza' => $al,
            'nota' => self::pulita($nota), 'creato' => date('c'), 'utente' => $utente,
        ];
        $this->store->update(self::ARCHIVIO, static function (array $tutto) use ($blocco): array {
            $oggi = date('Y-m-d');
            $tutto['blocchi'] = array_values(array_filter(
                (array) ($tutto['blocchi'] ?? []),
                static fn ($b): bool => is_array($b) && (string) ($b['partenza'] ?? '') > $oggi
            ));
            $tutto['blocchi'][] = $blocco;

            return $tutto;
        });

        return $id;
    }

    /** Toglie un blocco. Restituisce quello tolto, o null se non c'era. @return array<string,mixed>|null */
    public function togli(string $id): ?array
    {
        $tolto = null;
        $this->store->update(self::ARCHIVIO, static function (array $tutto) use ($id, &$tolto): array {
            $tutto['blocchi'] = array_values(array_filter((array) ($tutto['blocchi'] ?? []), static function ($b) use ($id, &$tolto): bool {
                if (is_array($b) && ($b['id'] ?? null) === $id) {
                    $tolto = $b;

                    return false;
                }

                return is_array($b);
            }));

            return $tutto;
        });

        return $tolto;
    }

    /**
     * Quanto di un blocco è già occupato da altro — Booking.com o una
     * prenotazione diretta: «tutto», «parte» o «niente».
     *
     * @param array<string,mixed> $blocco
     * @param list<array<string,mixed>> $occupazioni come App::nottiOccupate()
     */
    public static function copertura(array $blocco, array $occupazioni): string
    {
        $coperte = 0;
        $notti = 0;
        for ($g = (string) $blocco['arrivo']; $g < (string) $blocco['partenza']; $g = date('Y-m-d', strtotime($g . ' +1 day') ?: 0)) {
            $notti++;
            foreach ($occupazioni as $o) {
                if (($o['fonte'] ?? '') !== 'manuale' && $o['ref'] === $blocco['ref'] && $o['arrivo'] <= $g && $g < $o['partenza']) {
                    $coperte++;
                    break;
                }
            }
        }

        return $coperte === 0 ? 'niente' : ($coperte === $notti ? 'tutto' : 'parte');
    }

    /** Una nota su una riga, senza caratteri di controllo. */
    public static function pulita(string $nota): string
    {
        return trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $nota) ?? '');
    }

    /** Una data «2026-12-07» che esiste davvero, o null. */
    private static function data(string $s): ?string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $s);

        return $d !== false && $d->format('Y-m-d') === $s ? $s : null;
    }
}
