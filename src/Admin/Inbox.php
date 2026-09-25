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

    /**
     * Cambia una richiesta sotto il lucchetto dell'archivio: due processi che
     * la toccano insieme (la pagina di ritorno da SumUp e la notifica di
     * SumUp, per esempio) non si pestano i piedi.
     *
     * @param callable(array<string,mixed>): array<string,mixed> $cambia
     * @return array<string,mixed>|null la richiesta com'è dopo, o null se non c'è
     */
    public function aggiorna(string $id, callable $cambia): ?array
    {
        $dopo = null;
        $this->store->update('richieste', static function (array $voci) use ($id, $cambia, &$dopo): array {
            foreach ($voci as &$voce) {
                if (is_array($voce) && ($voce['id'] ?? null) === $id) {
                    $voce = $cambia($voce);
                    $voce['aggiornata'] = date('c');
                    $dopo = $voce;
                    break;
                }
            }
            unset($voce);

            return $voci;
        });

        return $dopo;
    }

    /** La prenotazione a cui appartiene un pagamento SumUp. */
    public function perPagamento(string $checkoutId): ?array
    {
        foreach ($this->all() as $voce) {
            foreach ((array) ($voce['dati']['pagamento']['tentativi'] ?? []) as $t) {
                if (is_array($t) && ($t['id'] ?? null) === $checkoutId) {
                    return $voce;
                }
            }
        }

        return null;
    }

    /**
     * Le notti che il sito ha già venduto o sta vendendo, camera per camera.
     *
     * Contano le prenotazioni confermate nell'area riservata, quelle pagate, e
     * quelle con la pagina di pagamento ancora aperta (per mezz'ora, quanto
     * vale la pagina di SumUp): due ospiti non devono pagare la stessa camera
     * per le stesse notti. Rifiutate e archiviate non contano.
     *
     * @return list<array{id: string, ref: string, arrivo: string, partenza: string}>
     */
    public function nottiOccupate(int $minutiInAttesa = 35): array
    {
        $out = [];
        foreach ($this->all() as $v) {
            if (($v['tipo'] ?? '') !== 'prenotazione' || in_array($v['stato'] ?? '', ['rifiutata', 'archiviata'], true)) {
                continue;
            }
            $p = (array) ($v['dati']['pagamento'] ?? []);
            // Il tempo si conta dall'apertura dell'ultima pagina di pagamento,
            // non dall'ultima verifica: controllare non allunga la tenuta.
            $tentativi = (array) ($p['tentativi'] ?? []);
            $aperta    = (string) (end($tentativi)['creato'] ?? '');
            $inAttesa  = ($p['stato'] ?? '') === 'in attesa' && $aperta !== ''
                && (time() - (strtotime($aperta) ?: 0)) < $minutiInAttesa * 60;
            if (($v['stato'] ?? '') === 'confermata' || in_array($p['stato'] ?? '', ['pagato', 'da controllare'], true) || $inAttesa) {
                $out[] = [
                    'id'       => (string) ($v['id'] ?? ''),
                    'ref'      => (string) ($v['dati']['ref'] ?? ''),
                    'arrivo'   => (string) ($v['dati']['arrivo'] ?? ''),
                    'partenza' => (string) ($v['dati']['partenza'] ?? ''),
                ];
            }
        }

        return $out;
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
        // Un pagamento abbandonato non è una richiesta da leggere.
        return count(array_filter($this->all(), static fn (array $v): bool => ($v['stato'] ?? '') === 'nuova'
            && \ArcoDelVento\Payment\Pagamenti::conta((array) ($v['dati'] ?? []))));
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
