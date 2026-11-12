<?php

declare(strict_types=1);

namespace ArcoDelVento\Booking;

use ArcoDelVento\App;
use ArcoDelVento\Storage\JsonStore;

/**
 * Il soggiorno di un ospite, da mettere nel suo calendario.
 *
 * Tre strade, tutte nell'e-mail di conferma:
 *   - un link a Google Calendar, con l'evento già scritto;
 *   - un link al file .ics sul sito (/soggiorno/<riferimento>/<codice>.ics):
 *     aperto dal telefono, iPhone e Mac lo aggiungono al Calendario, e lo
 *     stesso fa Outlook;
 *   - lo stesso file .ics allegato all'e-mail.
 *
 * L'evento va dall'orario di check-in del giorno di arrivo a quello di
 * check-out del giorno di partenza, in ora italiana (scritto in UTC, così
 * ogni calendario lo mette all'ora giusta). Se gli orari non si conoscono,
 * è un evento di giornate intere. Dentro ci sono camera, riferimento,
 * indirizzo e telefono della casa: mai il nome dell'ospite.
 *
 * Il codice nel link è un'impronta del riferimento con un segreto del
 * server (storage/data/calendario-ospiti.json): chi non ha l'e-mail non lo
 * indovina, e il file si legge solo per prenotazioni pagate o confermate.
 */
final class CalendarioOspite
{
    private const ARCHIVIO = 'calendario-ospiti';
    private const FUSO = 'Europe/Rome';

    public function __construct(private readonly App $app, private readonly JsonStore $store)
    {
    }

    /** Il codice del link per un riferimento. */
    public function codice(string $riferimento): string
    {
        return substr(hash_hmac('sha256', $riferimento, $this->segreto()), 0, 32);
    }

    public function codiceGiusto(string $riferimento, string $codice): bool
    {
        return hash_equals($this->codice($riferimento), $codice);
    }

    /** L'indirizzo completo del file .ics di una prenotazione. */
    public function link(string $riferimento): string
    {
        return $this->app->indirizzoCompleto(\ArcoDelVento\I18n\Routes::base() . '/soggiorno/' . rawurlencode($riferimento) . '/' . $this->codice($riferimento) . '.ics');
    }

    /** Se il calendario di questa prenotazione si può dare: pagata, o confermata da Daniele. */
    public static function daDare(array $voce): bool
    {
        if (($voce['tipo'] ?? '') !== 'prenotazione' || in_array($voce['stato'] ?? '', ['rifiutata', 'archiviata'], true)) {
            return false;
        }

        return ($voce['stato'] ?? '') === 'confermata' || (($voce['dati']['pagamento']['stato'] ?? '') === 'pagato');
    }

    /**
     * L'evento, nella lingua dell'ospite.
     *
     * @param array<string,mixed> $voce la prenotazione, com'è nell'archivio delle richieste
     * @return array{uid: string, titolo: string, inizio: \DateTimeImmutable, fine: \DateTimeImmutable, giornate: bool, luogo: string, descrizione: string}
     */
    public function evento(array $voce, string $lingua): array
    {
        $d = (array) $voce['dati'];
        $impostazioni = $this->app->settings();
        $stay = (array) ($impostazioni['stay'] ?? []);
        $t = fn (string $k, array $s = []): string => $this->app->translator()->get('book.mail.' . $k, $s, $lingua);
        $fuso = new \DateTimeZone(self::FUSO);

        $daOra = static function (string $giorno, ?string $ora) use ($fuso): ?\DateTimeImmutable {
            if ($ora === null || preg_match('/^(\d{1,2})[:.](\d{2})$/', trim($ora), $m) !== 1) {
                return null;
            }
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $giorno . ' ' . sprintf('%02d:%02d', (int) $m[1], (int) $m[2]), $fuso);

            return $d === false ? null : $d;
        };
        $inizio = $daOra((string) $d['arrivo'], isset($stay['check_in_from']) ? (string) $stay['check_in_from'] : null);
        $fine   = $daOra((string) $d['partenza'], isset($stay['check_out_by']) ? (string) $stay['check_out_by'] : null);
        $giornate = $inizio === null || $fine === null || $fine <= $inizio;
        if ($giornate) {
            $inizio = new \DateTimeImmutable((string) $d['arrivo'], $fuso);
            $fine   = new \DateTimeImmutable((string) $d['partenza'], $fuso);
        }

        $camera = (string) ($this->app->rooms()->findByRef((string) ($d['ref'] ?? ''))['name'][$lingua] ?? $d['camera'] ?? '');
        $indirizzo = (array) ($impostazioni['address'] ?? []);
        $luogo = implode(', ', array_filter([
            'Arco del Vento',
            (string) ($indirizzo['street'] ?? ''),
            trim(((string) ($indirizzo['postal_code'] ?? '')) . ' ' . (string) ($indirizzo['city'] ?? '')),
        ], static fn (string $s): bool => trim($s) !== ''));

        $righe = [$t('reference') . ': ' . $voce['id']];
        if (!empty($stay['check_in_from']) && !empty($stay['check_in_to'])) {
            $righe[] = ucfirst($t('check_in', ['from' => $stay['check_in_from'], 'to' => $stay['check_in_to']]));
        }
        if (!empty($stay['check_out_by'])) {
            $righe[] = ucfirst($t('check_out', ['time' => $stay['check_out_by']]));
        }
        if (!empty($impostazioni['contacts']['phone'])) {
            $righe[] = $t('event_phone', ['phone' => (string) $impostazioni['contacts']['phone']]);
        }
        $righe[] = $this->app->indirizzoCompleto(\ArcoDelVento\I18n\Routes::base() . '/' . $lingua . '/');

        return [
            'uid'         => preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $voce['id']) . '@' . $this->dominio(),
            'titolo'      => $t('event_title', ['room' => $camera]),
            'inizio'      => $inizio,
            'fine'        => $fine,
            'giornate'    => $giornate,
            'luogo'       => $luogo,
            'descrizione' => implode("\n", $righe),
        ];
    }

    /** Il file .ics di una prenotazione. */
    public function ics(array $voce, string $lingua): string
    {
        $e = $this->evento($voce, $lingua);
        $utc = new \DateTimeZone('UTC');
        $quando = $e['giornate']
            ? ['DTSTART;VALUE=DATE:' . $e['inizio']->format('Ymd'), 'DTEND;VALUE=DATE:' . $e['fine']->format('Ymd')]
            : ['DTSTART:' . $e['inizio']->setTimezone($utc)->format('Ymd\THis\Z'), 'DTEND:' . $e['fine']->setTimezone($utc)->format('Ymd\THis\Z')];
        $righe = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Arco del Vento//Prenotazione//' . strtoupper($lingua),
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $e['uid'],
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            ...$quando,
            'SUMMARY:' . self::testo($e['titolo']),
            'LOCATION:' . self::testo($e['luogo']),
            'DESCRIPTION:' . self::testo($e['descrizione']),
            'STATUS:CONFIRMED',
            'TRANSP:OPAQUE',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map(self::piega(...), $righe)) . "\r\n";
    }

    /** Il link che apre Google Calendar con l'evento già scritto. */
    public function google(array $voce, string $lingua): string
    {
        $e = $this->evento($voce, $lingua);
        $utc = new \DateTimeZone('UTC');
        $date = $e['giornate']
            ? $e['inizio']->format('Ymd') . '/' . $e['fine']->format('Ymd')
            : $e['inizio']->setTimezone($utc)->format('Ymd\THis\Z') . '/' . $e['fine']->setTimezone($utc)->format('Ymd\THis\Z');

        // La barra fra le due date resta com'è: è così che Google le legge.
        return 'https://calendar.google.com/calendar/render?' . str_replace('%2F', '/', http_build_query([
            'action'   => 'TEMPLATE',
            'text'     => $e['titolo'],
            'dates'    => $date,
        ], '', '&', PHP_QUERY_RFC3986)) . '&' . http_build_query([
            'details'  => $e['descrizione'],
            'location' => $e['luogo'],
            'ctz'      => self::FUSO,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function dominio(): string
    {
        return (string) (parse_url($this->app->indirizzoCompleto('/'), PHP_URL_HOST) ?: 'arcodelvento.local');
    }

    private function segreto(): string
    {
        $s = (string) ($this->store->read(self::ARCHIVIO)['segreto'] ?? '');
        if (preg_match('/^[a-f0-9]{64}$/', $s) !== 1) {
            $nuovo = bin2hex(random_bytes(32));
            $s = (string) $this->store->update(self::ARCHIVIO, static function (array $tutto) use ($nuovo): array {
                if (preg_match('/^[a-f0-9]{64}$/', (string) ($tutto['segreto'] ?? '')) !== 1) {
                    $tutto['segreto'] = $nuovo;
                }

                return $tutto;
            })['segreto'];
        }

        return $s;
    }

    /** Un testo in una riga iCal: barra rovescia, virgole, punti e virgola e a capo vanno protetti. */
    private static function testo(string $s): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], $s);
    }

    /** RFC 5545: righe di al massimo 75 ottetti, le altre continuano con uno spazio. */
    private static function piega(string $riga): string
    {
        if (strlen($riga) <= 75) {
            return $riga;
        }
        $out = [];
        $pezzo = '';
        foreach (mb_str_split($riga) as $carattere) {
            if (strlen($pezzo . $carattere) > ($out === [] ? 75 : 74)) {
                $out[] = $pezzo;
                $pezzo = '';
            }
            $pezzo .= $carattere;
        }
        $out[] = $pezzo;

        return implode("\r\n ", $out);
    }
}
