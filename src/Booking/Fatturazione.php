<?php

declare(strict_types=1);

namespace ArcoDelVento\Booking;

use ArcoDelVento\Support\Validator;

/**
 * I dati di fatturazione di chi paga online: l'indirizzo, il codice fiscale
 * se c'è, e i dati dell'azienda se la fattura va intestata a un'azienda.
 *
 * Servono a chi tiene la contabilità per emettere la ricevuta o la fattura.
 * Restano sul sito con la prenotazione: a SumUp non si passano.
 *
 * Il codice fiscale e la partita IVA italiani hanno un carattere di
 * controllo: si verifica qui, così un errore di battitura si vede subito e
 * non quando la fattura torna indietro.
 */
final class Fatturazione
{
    /** I campi del modulo. */
    public const CAMPI = [
        'indirizzo', 'cap', 'citta', 'provincia', 'nazione', 'codice_fiscale',
        'azienda', 'ragione_sociale', 'partita_iva', 'sdi_pec',
    ];

    /**
     * Controlla i campi del modulo, aggiunge gli errori al validatore e
     * restituisce i dati puliti. I dati dell'azienda contano solo con la
     * spunta: senza, quello che fosse rimasto scritto si ignora.
     *
     * @return array<string,string|bool>
     */
    public static function controlla(Validator $v): array
    {
        $v->required('indirizzo')->maxLength('indirizzo', 160)
          ->required('cap')->maxLength('cap', 12)
          ->required('citta')->maxLength('citta', 80)
          ->maxLength('provincia', 40)
          ->required('nazione')->maxLength('nazione', 80)
          ->maxLength('codice_fiscale', 20);

        $italia = self::italia($v->value('nazione'));
        $cap = strtoupper(preg_replace('/\s+/', ' ', $v->value('cap')) ?? '');
        if ($cap !== '' && preg_match($italia ? '/^\d{5}$/' : '/^[A-Z0-9][A-Z0-9 -]{1,11}$/', $cap) !== 1) {
            $v->fail('cap', 'cap');
        }
        $provincia = strtoupper($v->value('provincia'));
        if ($italia && $provincia !== '' && preg_match('/^[A-Z]{2}$/', $provincia) !== 1) {
            $v->fail('provincia', 'provincia');
        }
        $cf = self::compatto($v->value('codice_fiscale'));
        if ($cf !== '' && !self::codiceFiscale($cf)) {
            $v->fail('codice_fiscale', 'codice_fiscale');
        }

        $dati = [
            'indirizzo'      => $v->value('indirizzo'),
            'cap'            => $cap,
            'citta'          => $v->value('citta'),
            'provincia'      => $provincia,
            'nazione'        => $v->value('nazione'),
            'codice_fiscale' => $cf,
        ];

        if ($v->value('azienda') !== '') {
            $v->required('ragione_sociale')->maxLength('ragione_sociale', 160)
              ->required('partita_iva')->maxLength('partita_iva', 24)
              ->maxLength('sdi_pec', 180);
            $piva = self::compatto($v->value('partita_iva'));
            if ($piva !== '' && !self::partitaIva($piva, $italia)) {
                $v->fail('partita_iva', 'partita_iva');
            }
            $sdiPec = $v->value('sdi_pec');
            if ($sdiPec !== '' && !str_contains($sdiPec, '@')) {
                $sdiPec = strtoupper($sdiPec);
            }
            if ($sdiPec !== '' && preg_match('/^[A-Z0-9]{7}$/', $sdiPec) !== 1 && !filter_var($sdiPec, FILTER_VALIDATE_EMAIL)) {
                $v->fail('sdi_pec', 'sdi_pec');
            }
            $dati += [
                'azienda'         => true,
                'ragione_sociale' => $v->value('ragione_sociale'),
                'partita_iva'     => $piva,
                'sdi_pec'         => $sdiPec,
            ];
        }

        return array_filter($dati, static fn ($x): bool => $x !== '' && $x !== false);
    }

    /**
     * Le righe da mostrare, in italiano, per il pannello e per la struttura.
     *
     * @param array<string,mixed> $f
     * @return array<string,string> etichetta => valore
     */
    public static function righe(array $f): array
    {
        $righe = [
            'Intestata a'      => !empty($f['azienda']) ? 'Azienda' : 'Persona',
            'Ragione sociale'  => (string) ($f['ragione_sociale'] ?? ''),
            'Partita IVA'      => (string) ($f['partita_iva'] ?? ''),
            'Codice SDI o PEC' => (string) ($f['sdi_pec'] ?? ''),
            'Indirizzo'        => self::indirizzo($f),
            'Codice fiscale'   => (string) ($f['codice_fiscale'] ?? ''),
        ];

        return array_filter($righe, static fn (string $x): bool => $x !== '');
    }

    /**
     * L'indirizzo su una riga: «Via Roma 1, 06081 Assisi (PG), Italia».
     *
     * @param array<string,mixed> $f
     */
    public static function indirizzo(array $f): string
    {
        $luogo = trim(($f['cap'] ?? '') . ' ' . ($f['citta'] ?? ''));
        if (($f['provincia'] ?? '') !== '') {
            $luogo .= ' (' . $f['provincia'] . ')';
        }

        return implode(', ', array_filter([
            trim((string) ($f['indirizzo'] ?? '')), $luogo, trim((string) ($f['nazione'] ?? '')),
        ], static fn (string $x): bool => $x !== ''));
    }

    /** Il paese scritto è l'Italia? */
    public static function italia(string $paese): bool
    {
        return in_array(mb_strtolower(trim($paese)), ['italia', 'italy', 'it', 'ita', 'italie', 'italien'], true);
    }

    /**
     * Il codice fiscale: 16 caratteri con il carattere di controllo giusto
     * (anche con le lettere di omocodia), oppure 11 cifre, come quello di
     * un'azienda o di un'associazione.
     */
    public static function codiceFiscale(string $cf): bool
    {
        $cf = self::compatto($cf);
        if (preg_match('/^\d{11}$/', $cf) === 1) {
            return self::partitaIva($cf, true);
        }
        $n = '[0-9LMNPQRSTUV]';
        if (preg_match("/^[A-Z]{6}{$n}{2}[A-EHLMPR-T]{$n}{2}[A-Z]{$n}{3}[A-Z]$/", $cf) !== 1) {
            return false;
        }
        // Il carattere di controllo: i caratteri in posizione dispari e
        // pari valgono in due modi diversi, e la somma modulo 26 è la lettera.
        $dispari = [
            '0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9, '5' => 13, '6' => 15, '7' => 17, '8' => 19, '9' => 21,
            'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7, 'E' => 9, 'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21,
            'K' => 2, 'L' => 4, 'M' => 18, 'N' => 20, 'O' => 11, 'P' => 3, 'Q' => 6, 'R' => 8, 'S' => 12, 'T' => 14,
            'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23,
        ];
        $somma = 0;
        for ($i = 0; $i < 15; $i++) {
            $c = $cf[$i];
            $somma += $i % 2 === 0
                ? $dispari[$c]
                : (ctype_digit($c) ? (int) $c : ord($c) - ord('A'));
        }

        return chr(ord('A') + $somma % 26) === $cf[15];
    }

    /**
     * La partita IVA. Italiana: 11 cifre (con o senza «IT» davanti) e la
     * cifra di controllo giusta. Di un altro paese: il prefisso del paese e
     * da 2 a 13 lettere o cifre, oppure solo lettere e cifre — fuori
     * dall'Europa i formati sono troppi per controllarli tutti.
     */
    public static function partitaIva(string $piva, bool $italia): bool
    {
        $piva = self::compatto($piva);
        if (str_starts_with($piva, 'IT') || ($italia && preg_match('/^\d+$/', $piva) === 1) || preg_match('/^\d{11}$/', $piva) === 1) {
            $cifre = str_starts_with($piva, 'IT') ? substr($piva, 2) : $piva;
            if (preg_match('/^\d{11}$/', $cifre) !== 1) {
                return false;
            }
            $somma = 0;
            for ($i = 0; $i < 11; $i++) {
                $d = (int) $cifre[$i];
                if ($i % 2 === 1) {
                    $d *= 2;
                    $d = $d > 9 ? $d - 9 : $d;
                }
                $somma += $d;
            }

            return $somma % 10 === 0;
        }

        return preg_match('/^[A-Z0-9]{4,20}$/', $piva) === 1;
    }

    /** Maiuscolo, senza spazi, punti e trattini. */
    private static function compatto(string $s): string
    {
        return strtoupper(preg_replace('/[\s.\-]+/', '', $s) ?? '');
    }
}
