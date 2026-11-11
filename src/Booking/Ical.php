<?php

declare(strict_types=1);

namespace ArcoDelVento\Booking;

/**
 * Il formato iCalendar (RFC 5545), per quel poco che serve a un calendario
 * di camere: leggere i periodi occupati di un calendario altrui (Booking.com,
 * Airbnb) e scrivere quelli del sito.
 *
 * Di un evento contano solo le date: il giorno d'arrivo e quello di partenza,
 * che in iCal è escluso (DTEND) come lo è qui. Nomi e note non si leggono e
 * non si tengono: sono dati degli ospiti di un'altra piattaforma.
 */
final class Ical
{
    /**
     * I periodi occupati di un calendario, dal più vicino. Gli eventi annullati
     * e quelli già finiti prima di $daGiorno non contano.
     *
     * @return list<array{arrivo: string, partenza: string}>
     */
    public static function periodi(string $testo, ?string $daGiorno = null): array
    {
        $testo = str_replace(["\r\n", "\r"], "\n", $testo);
        // Le righe lunghe sono spezzate con un a capo e uno spazio: si riuniscono.
        $testo = preg_replace("/\n[ \t]/", '', $testo) ?? $testo;

        $out = [];
        foreach (explode('BEGIN:VEVENT', $testo) as $i => $blocco) {
            if ($i === 0) {
                continue;
            }
            $blocco = explode('END:VEVENT', $blocco, 2)[0];
            if (preg_match('/^STATUS:CANCELLED/mi', $blocco) === 1) {
                continue;
            }
            $arrivo = self::data($blocco, 'DTSTART');
            if ($arrivo === null) {
                continue;
            }
            $partenza = self::data($blocco, 'DTEND');
            if ($partenza === null || $partenza <= $arrivo) {
                // Senza fine, o con la fine il giorno stesso: una notte.
                $partenza = date('Y-m-d', strtotime($arrivo . ' +1 day') ?: 0);
            }
            if ($daGiorno !== null && $partenza <= $daGiorno) {
                continue;
            }
            $out[] = ['arrivo' => $arrivo, 'partenza' => $partenza];
        }
        usort($out, static fn (array $a, array $b): int => [$a['arrivo'], $a['partenza']] <=> [$b['arrivo'], $b['partenza']]);

        return $out;
    }

    /** È un calendario iCal, a guardarlo? */
    public static function sembraCalendario(string $testo): bool
    {
        return stripos($testo, 'BEGIN:VCALENDAR') !== false;
    }

    /**
     * Un calendario con i periodi dati, tutti di giornate intere.
     *
     * @param list<array{uid: string, arrivo: string, partenza: string, titolo: string}> $eventi
     */
    public static function calendario(string $nome, array $eventi, string $dominio): string
    {
        $righe = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Arco del Vento//Sito//IT',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::testo($nome),
        ];
        $adesso = gmdate('Ymd\THis\Z');
        foreach ($eventi as $e) {
            $righe[] = 'BEGIN:VEVENT';
            $righe[] = 'UID:' . preg_replace('/[^A-Za-z0-9._-]/', '-', $e['uid']) . '@' . $dominio;
            $righe[] = 'DTSTAMP:' . $adesso;
            $righe[] = 'DTSTART;VALUE=DATE:' . str_replace('-', '', $e['arrivo']);
            $righe[] = 'DTEND;VALUE=DATE:' . str_replace('-', '', $e['partenza']);
            $righe[] = 'SUMMARY:' . self::testo($e['titolo']);
            $righe[] = 'END:VEVENT';
        }
        $righe[] = 'END:VCALENDAR';

        return implode("\r\n", $righe) . "\r\n";
    }

    /** La data di una proprietà (DTSTART, DTEND), con o senza orario e fuso. */
    private static function data(string $blocco, string $nome): ?string
    {
        if (preg_match('/^' . $nome . '(?:;[^:\n]*)?:(\d{4})(\d{2})(\d{2})/mi', $blocco, $m) !== 1) {
            return null;
        }
        if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return $m[1] . '-' . $m[2] . '-' . $m[3];
    }

    /** Un testo in una riga iCal: virgole, punti e virgola e a capo vanno protetti. */
    private static function testo(string $s): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], $s);
    }
}
