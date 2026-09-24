<?php

declare(strict_types=1);

namespace ArcoDelVento\Admin;

use ArcoDelVento\Storage\JsonStore;

/**
 * Le richieste arrivate dal sito: prenotazioni e messaggi.
 *
 * Arrivano anche per e-mail, come prima. Qui restano in un posto solo, con uno
 * stato — nuova, letta, confermata, rifiutata, archiviata — così non si
 * perdono in mezzo alla posta e si vede subito che cosa aspetta una risposta.
 *
 * Sono dati personali di chi scrive: si tengono per il tempo che serve e poi
 * si cancellano da soli (CONSERVAZIONE_MESI). Lo stesso periodo va scritto
 * nell'informativa privacy.
 */
final class Inbox
{
    public const CONSERVAZIONE_MESI = 24;

    public const STATI = [
        'nuova'      => 'Nuova',
        'letta'      => 'Letta',
        'confermata' => 'Confermata',
        'rifiutata'  => 'Rifiutata',
        'archiviata' => 'Archiviata',
    ];

    public function __construct(private readonly JsonStore $store)
    {
    }

    /**
     * @param 'prenotazione'|'messaggio' $tipo
     * @param array<string,mixed> $dati
     */
    public function add(string $tipo, array $dati, string $riferimento = ''): string
    {
        $id = $riferimento !== '' ? $riferimento : strtoupper('MSG-' . date('ymd') . '-' . bin2hex(random_bytes(2)));
        $this->store->update('richieste', function (array $voci) use ($id, $tipo, $dati): array {
            $voci = $this->senzaScadute($voci);
            array_unshift($voci, [
                'id'       => $id,
                'tipo'     => $tipo,
                'ricevuta' => date('c'),
                'stato'    => 'nuova',
                'dati'     => $dati,
            ]);

            return array_values($voci);
        });

        return $id;
    }

    /** @return list<array<string,mixed>> dalla più recente */
    public function all(): array
    {
        return array_values($this->senzaScadute($this->store->read('richieste')));
    }

    /** @return array<string,mixed>|null */
    public function find(string $id): ?array
    {
        foreach ($this->all() as $voce) {
            if (($voce['id'] ?? null) === $id) {
                return $voce;
            }
        }

        return null;
    }

    public function setStatus(string $id, string $stato): bool
    {
        if (!isset(self::STATI[$stato])) {
            return false;
        }
        $trovata = false;
        $this->store->update('richieste', static function (array $voci) use ($id, $stato, &$trovata): array {
            foreach ($voci as &$voce) {
                if (($voce['id'] ?? null) === $id) {
                    $voce['stato']     = $stato;
                    $voce['aggiornata'] = date('c');
                    $trovata = true;
                }
            }
            unset($voce);

            return $voci;
        });

        return $trovata;
    }

    public function delete(string $id): bool
    {
        $prima = count($this->all());
        $this->store->update('richieste', static fn (array $voci): array => array_values(array_filter(
            $voci,
            static fn ($v): bool => ($v['id'] ?? null) !== $id
        )));

        return count($this->all()) < $prima;
    }

    public function countNew(): int
    {
        return count(array_filter($this->all(), static fn (array $v): bool => ($v['stato'] ?? '') === 'nuova'));
    }

    /**
     * @param array<mixed> $voci
     * @return array<mixed>
     */
    private function senzaScadute(array $voci): array
    {
        $limite = strtotime('-' . self::CONSERVAZIONE_MESI . ' months');

        return array_values(array_filter(
            $voci,
            static fn ($v): bool => is_array($v) && (strtotime((string) ($v['ricevuta'] ?? '')) ?: 0) >= $limite
        ));
    }
}
