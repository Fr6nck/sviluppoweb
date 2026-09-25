<?php

declare(strict_types=1);

namespace ArcoDelVento\Admin;

/**
 * I numeri della bacheca, contati sulle richieste vere.
 *
 * Niente è stimato e niente è inventato: se non è arrivato niente, i grafici
 * sono piatti e i numeri sono zero. Le richieste rifiutate e archiviate non
 * contano negli arrivi e nelle notti; contano invece in «quante ne sono
 * arrivate», perché sono arrivate.
 */
final class Cruscotto
{
    private const MESI = ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];
    private const GIORNI = ['dom', 'lun', 'mar', 'mer', 'gio', 'ven', 'sab'];

    private readonly \DateTimeImmutable $oggi;

    /** @param list<array<string,mixed>> $richieste dalla più recente */
    public function __construct(private readonly array $richieste, ?\DateTimeImmutable $oggi = null)
    {
        $this->oggi = ($oggi ?? new \DateTimeImmutable('today'))->setTime(0, 0);
    }

    /**
     * Quante ne sono arrivate, giorno per giorno, dal più vecchio a oggi.
     *
     * @param ?string $tipo prenotazione | messaggio | null per tutte
     * @return list<int>
     */
    public function perGiorno(?string $tipo, int $giorni): array
    {
        $conta = array_fill(0, $giorni, 0);
        foreach ($this->richieste as $r) {
            if ($tipo !== null && ($r['tipo'] ?? '') !== $tipo) {
                continue;
            }
            $giorno = $this->giorno((string) ($r['ricevuta'] ?? ''));
            if ($giorno === null) {
                continue;
            }
            $fa = (int) $giorno->diff($this->oggi)->format('%r%a');
            if ($fa >= 0 && $fa < $giorni) {
                $conta[$giorni - 1 - $fa]++;
            }
        }

        return $conta;
    }

    /** Quante in un periodo; con $scarto, il periodo di pari lunghezza prima. */
    public function totale(?string $tipo, int $giorni, int $scarto = 0): int
    {
        return array_sum(array_slice($this->perGiorno($tipo, $giorni * ($scarto + 1)), 0, $giorni));
    }

    /**
     * Com'è cambiato il periodo rispetto a quello prima, in percento.
     * Null se prima era zero: «+∞%» non vuol dire niente.
     */
    public function variazione(?string $tipo, int $giorni): ?float
    {
        $ora   = $this->totale($tipo, $giorni);
        $prima = $this->totale($tipo, $giorni, 1);
        if ($prima === 0) {
            return null;
        }

        return ($ora - $prima) / $prima * 100;
    }

    /** Quanti giorni copre il grafico: 7, o dal primo del mese di cinque mesi fa. */
    public function giorni(string $periodo): int
    {
        if ($periodo !== 'mesi') {
            return 7;
        }

        return (int) $this->oggi->modify('first day of this month')->modify('-5 months')->diff($this->oggi)->days + 1;
    }

    /** Il valore delle prenotazioni richieste nel periodo, come l'ha calcolato il sito. */
    public function valore(int $giorni): float
    {
        $totale = 0.0;
        foreach ($this->richieste as $r) {
            if (($r['tipo'] ?? '') !== 'prenotazione' || in_array($r['stato'] ?? '', ['rifiutata'], true)) {
                continue;
            }
            $giorno = $this->giorno((string) ($r['ricevuta'] ?? ''));
            if ($giorno !== null && (int) $giorno->diff($this->oggi)->format('%r%a') < $giorni) {
                $totale += (float) ($r['dati']['totale'] ?? 0);
            }
        }

        return $totale;
    }

    /**
     * Le colonne del grafico: gli ultimi 7 giorni, o gli ultimi 6 mesi.
     *
     * @return list<array{etichetta: string, prenotazioni: int, messaggi: int, oggi: bool}>
     */
    public function colonne(string $periodo): array
    {
        $out = [];
        if ($periodo === 'mesi') {
            $inizio = $this->oggi->modify('first day of this month');
            for ($i = 5; $i >= 0; $i--) {
                $mese = $inizio->modify("-{$i} months");
                $out[$mese->format('Y-m')] = ['etichetta' => self::MESI[(int) $mese->format('n') - 1], 'prenotazioni' => 0, 'messaggi' => 0, 'oggi' => $i === 0];
            }
            $chiave = static fn (\DateTimeImmutable $g): string => $g->format('Y-m');
        } else {
            for ($i = 6; $i >= 0; $i--) {
                $g = $this->oggi->modify("-{$i} days");
                $out[$g->format('Y-m-d')] = ['etichetta' => $i === 0 ? 'oggi' : self::GIORNI[(int) $g->format('w')], 'prenotazioni' => 0, 'messaggi' => 0, 'oggi' => $i === 0];
            }
            $chiave = static fn (\DateTimeImmutable $g): string => $g->format('Y-m-d');
        }
        foreach ($this->richieste as $r) {
            $g = $this->giorno((string) ($r['ricevuta'] ?? ''));
            $tipo = ($r['tipo'] ?? '') === 'prenotazione' ? 'prenotazioni' : 'messaggi';
            if ($g !== null && isset($out[$chiave($g)])) {
                $out[$chiave($g)][$tipo]++;
            }
        }

        return array_values($out);
    }

    /**
     * Gli arrivi da oggi in poi, delle prenotazioni ancora vive.
     *
     * @return list<array<string,mixed>>
     */
    public function prossimiArrivi(int $quanti): array
    {
        $vivi = array_filter($this->prenotazioniVive(), fn (array $r): bool => ($this->giorno((string) ($r['dati']['arrivo'] ?? '')) ?? $this->oggi->modify('-1 day')) >= $this->oggi);
        usort($vivi, static fn (array $a, array $b): int => strcmp((string) $a['dati']['arrivo'], (string) $b['dati']['arrivo']));

        return array_slice(array_values($vivi), 0, $quanti);
    }

    /**
     * Quante camere sono chieste o confermate, notte per notte, da stanotte.
     *
     * @return list<int>
     */
    public function camerePerNotte(int $notti): array
    {
        $conta = array_fill(0, $notti, 0);
        foreach ($this->prenotazioniVive() as $r) {
            $arrivo   = $this->giorno((string) ($r['dati']['arrivo'] ?? ''));
            $partenza = $this->giorno((string) ($r['dati']['partenza'] ?? ''));
            if ($arrivo === null || $partenza === null) {
                continue;
            }
            for ($i = 0; $i < $notti; $i++) {
                $notte = $this->oggi->modify("+{$i} days");
                if ($notte >= $arrivo && $notte < $partenza) {
                    $conta[$i]++;
                }
            }
        }

        return $conta;
    }

    /** «12 dic» */
    public static function breve(string $iso): string
    {
        $t = strtotime($iso);

        return $t ? date('j', $t) . ' ' . self::MESI[(int) date('n', $t) - 1] : '';
    }

    /** @return list<array<string,mixed>> */
    private function prenotazioniVive(): array
    {
        return array_values(array_filter(
            $this->richieste,
            static fn (array $r): bool => ($r['tipo'] ?? '') === 'prenotazione'
                && in_array($r['stato'] ?? '', ['nuova', 'letta', 'confermata'], true)
        ));
    }

    private function giorno(string $iso): ?\DateTimeImmutable
    {
        $t = strtotime($iso);

        return $t ? (new \DateTimeImmutable('@' . $t))->setTimezone(new \DateTimeZone(date_default_timezone_get()))->setTime(0, 0) : null;
    }
}
