<?php

declare(strict_types=1);

namespace ArcoDelVento\Admin;

/**
 * Il calendario delle camere, per l'area riservata: un mese, una riga per
 * camera, una casella per notte.
 *
 * Le notti occupate arrivano da App::nottiOccupate(), ognuna con la sua
 * fonte: prenotazione diretta (sito), Booking.com (booking), blocco manuale
 * (manuale). Ogni notte ha una voce sola: se due fonti la occupano insieme —
 * un blocco messo a mano e poi la stessa prenotazione arrivata da Booking —
 * vince la prima nell'ordine di FONTI, e la notte non risulta né doppia né in
 * conflitto.
 *
 * Una notte è quella che comincia quel giorno: una prenotazione dal 7 al 10
 * occupa le notti del 7, 8 e 9; il 10 è il giorno di partenza ed è libero.
 *
 * Questo calendario vive solo nell'area riservata. Al sito pubblico le date
 * occupate non arrivano mai: lì si vede soltanto quali camere sono libere per
 * le date chieste.
 */
final class Calendario
{
    /** Le fonti, dalla più importante: etichetta, nome breve e lettera della casella. */
    public const FONTI = [
        'sito'    => ['etichetta' => 'Prenotazione diretta', 'breve' => 'Diretta', 'lettera' => 'D'],
        'booking' => ['etichetta' => 'Prenotazione Booking.com', 'breve' => 'Booking.com', 'lettera' => 'B'],
        'manuale' => ['etichetta' => 'Blocco manuale', 'breve' => 'Blocco manuale', 'lettera' => 'M'],
    ];

    public const MESI = ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto',
                         'settembre', 'ottobre', 'novembre', 'dicembre'];
    public const GIORNI = ['domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato'];

    private readonly \DateTimeImmutable $primo;

    /** @var array<string, array<string, array<string,mixed>>> ref => giorno (Y-m-d) => occupazione */
    private array $notti = [];

    /**
     * @param string $mese «2026-12»; vuoto o sbagliato: il mese di oggi
     * @param list<array<string,mixed>> $occupazioni come App::nottiOccupate()
     */
    public function __construct(string $mese, array $occupazioni)
    {
        $this->primo = self::primoDelMese($mese);
        $inizioMese  = $this->primo->format('Y-m-d');
        $fineMese    = $this->primo->modify('+1 month')->format('Y-m-d');

        foreach ($occupazioni as $o) {
            $fonte = (string) ($o['fonte'] ?? '');
            if (!isset(self::FONTI[$fonte])) {
                continue;
            }
            $da = max((string) $o['arrivo'], $inizioMese);
            $a  = min((string) $o['partenza'], $fineMese);
            for ($g = $da; $g < $a; $g = date('Y-m-d', strtotime($g . ' +1 day') ?: 0)) {
                $prima = $this->notti[(string) $o['ref']][$g] ?? null;
                if ($prima === null || self::rango($fonte) < self::rango((string) $prima['fonte'])) {
                    $this->notti[(string) $o['ref']][$g] = $o;
                }
            }
        }
    }

    /** Il primo giorno del mese chiesto («2026-12»), o di quello di oggi. */
    public static function primoDelMese(string $mese): \DateTimeImmutable
    {
        if (preg_match('/^(20\d\d)-(0[1-9]|1[0-2])$/', $mese, $m) === 1) {
            return new \DateTimeImmutable($m[1] . '-' . $m[2] . '-01');
        }

        return new \DateTimeImmutable(date('Y-m-01'));
    }

    public function primo(): \DateTimeImmutable
    {
        return $this->primo;
    }

    /** «dicembre 2026». */
    public function nome(?\DateTimeImmutable $mese = null): string
    {
        $mese ??= $this->primo;

        return self::MESI[(int) $mese->format('n') - 1] . ' ' . $mese->format('Y');
    }

    /** @return list<\DateTimeImmutable> */
    public function giorni(): array
    {
        $out = [];
        $fine = $this->primo->modify('+1 month');
        for ($g = $this->primo; $g < $fine; $g = $g->modify('+1 day')) {
            $out[] = $g;
        }

        return $out;
    }

    /** Chi occupa la notte di quel giorno, o null se è libera. @return array<string,mixed>|null */
    public function notte(string $ref, string $giorno): ?array
    {
        return $this->notti[$ref][$giorno] ?? null;
    }

    /** Quante notti occupate ha una camera nel mese. */
    public function occupate(string $ref): int
    {
        return count($this->notti[$ref] ?? []);
    }

    /**
     * I periodi occupati di una camera nel mese: notti di fila con la stessa
     * occupazione, una riga ciascuno. Un periodo che continua nel mese prima o
     * dopo porta le sue date vere.
     *
     * @return list<array<string,mixed>> l'occupazione, con «arrivo» e «partenza» del periodo
     */
    public function periodi(string $ref): array
    {
        $notti = $this->notti[$ref] ?? [];
        ksort($notti);
        $inizioMese = $this->primo->format('Y-m-d');
        $fineMese   = $this->primo->modify('+1 month')->format('Y-m-d');
        $out = [];
        $corrente = null;
        foreach ($notti as $giorno => $o) {
            $giorno = (string) $giorno;
            $chiave = $o['fonte'] . '|' . $o['id'];
            if ($corrente !== null && $corrente['chiave'] === $chiave && $corrente['fine'] === $giorno) {
                $corrente['fine'] = date('Y-m-d', strtotime($giorno . ' +1 day') ?: 0);
                continue;
            }
            if ($corrente !== null) {
                $out[] = $corrente;
            }
            $corrente = ['chiave' => $chiave, 'inizio' => $giorno, 'fine' => date('Y-m-d', strtotime($giorno . ' +1 day') ?: 0), 'o' => $o];
        }
        if ($corrente !== null) {
            $out[] = $corrente;
        }

        return array_map(static function (array $p) use ($inizioMese, $fineMese): array {
            $o = $p['o'];
            $o['arrivo']   = $p['inizio'] === $inizioMese && (string) $o['arrivo'] < $inizioMese ? (string) $o['arrivo'] : $p['inizio'];
            $o['partenza'] = $p['fine'] === $fineMese && (string) $o['partenza'] > $fineMese ? (string) $o['partenza'] : $p['fine'];

            return $o;
        }, $out);
    }

    private static function rango(string $fonte): int
    {
        $i = array_search($fonte, array_keys(self::FONTI), true);

        return $i === false ? PHP_INT_MAX : (int) $i;
    }
}
