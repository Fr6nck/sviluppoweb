<?php
namespace MHW;

/**
 * Gli eventi di una guida: le righe del ripetitore `events` della sezione `events`.
 * Tutta la logica delle date sta qui, così le viste restano semplici.
 *
 * Una riga (già unita alla traduzione):
 *   name, when_text, description          — da tradurre
 *   cat                                   — una chiave di CATEGORIE
 *   when                                  — day | range | weekly | other
 *   date_from, date_to                    — 'YYYY-MM-DD' (per weekly e other: la stagione, facoltativa)
 *   time_from, time_to                    — 'HH:MM'
 *   days                                  — [1..7], 1 = lunedì (solo weekly)
 *   yearly, recommended                   — '1' oppure ''
 *   place, dist_min, dist_mode (walk|car), price_kind (''|free|paid), price, url
 *   poster (id immagine), poster_pdf (id PDF)
 *
 * «Oggi» è sempre il giorno in Italia. La guida dell'ospite è un'istantanea
 * fatta alla pubblicazione: questi filtri vanno applicati quando la pagina si
 * mostra, non quando si pubblica.
 */
final class Eventi
{
    public const CATEGORIE = ['sagra', 'market', 'music', 'festival', 'history', 'exhibition', 'theatre', 'sport', 'kids', 'nature', 'religious', 'other'];
    public const QUANDO = ['day' => 'Un giorno', 'range' => 'Più giorni', 'weekly' => 'Ogni settimana', 'other' => 'Altro'];
    /** Fin qui un evento è «in questi giorni». */
    public const VICINO = 7;
    /** Oltre questi giorni un evento non basta a far comparire la casella in home. */
    public const ORIZZONTE = 60;

    public static function oggi(?int $t = null): string
    {
        return (new \DateTimeImmutable('@' . ($t ?? time())))->setTimezone(new \DateTimeZone('Europe/Rome'))->format('Y-m-d');
    }

    private static function data(mixed $v): string
    {
        $v = trim((string) $v);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) return '';
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $v : '';
    }

    private static function piu(string $giorno, int $n): string
    {
        return (new \DateTimeImmutable($giorno))->modify(($n >= 0 ? '+' : '') . $n . ' day')->format('Y-m-d');
    }

    public static function ricorrente(array $r): bool { return in_array($r['when'] ?? '', ['weekly', 'other'], true); }

    /** Il primo giorno. Per gli eventi ricorrenti è l'inizio della stagione, e può mancare. */
    public static function inizio(array $r): string { return self::data($r['date_from'] ?? ''); }

    /** L'ultimo giorno. Per gli eventi ricorrenti è la fine della stagione, e può mancare. */
    public static function fine(array $r): string
    {
        $a = self::data($r['date_to'] ?? '');
        if (self::ricorrente($r)) return $a;
        $da = self::inizio($r);
        return ($r['when'] ?? '') === 'range' && $a !== '' && $a >= $da ? $a : $da;
    }

    public static function passato(array $r, string $oggi): bool
    {
        $f = self::fine($r);
        return $f !== '' && $f < $oggi;
    }

    /** Si mostra all'ospite? Serve un nome; una data se non è ricorrente; e non dev'essere finito. */
    public static function visibile(array $r, string $oggi): bool
    {
        if (trim((string) ($r['name'] ?? '')) === '') return false;
        if (self::ricorrente($r)) {
            $da = self::inizio($r);
            return !self::passato($r, $oggi) && ($da === '' || $da <= self::piu($oggi, self::ORIZZONTE));
        }
        return self::inizio($r) !== '' && !self::passato($r, $oggi);
    }

    /**
     * Le righe da mostrare, già ordinate e divise in tre gruppi.
     * @return array{giorni:array,avanti:array,ricorrenti:array}
     */
    public static function gruppi(array $righe, string $oggi): array
    {
        $g = ['giorni' => [], 'avanti' => [], 'ricorrenti' => []];
        $limite = self::piu($oggi, self::VICINO);
        foreach ($righe as $r) {
            if (!self::visibile($r, $oggi)) continue;
            if (self::ricorrente($r)) $g['ricorrenti'][] = $r;
            elseif (self::inizio($r) <= $limite) $g['giorni'][] = $r;
            else $g['avanti'][] = $r;
        }
        $perData = fn(array $a, array $b) => [self::inizio($a), (string) ($a['time_from'] ?? '')] <=> [self::inizio($b), (string) ($b['time_from'] ?? '')];
        usort($g['giorni'], $perData);
        usort($g['avanti'], $perData);
        return $g;
    }

    /** Gli eventi che ci sono oggi: per la fascia in cima alla home della guida. */
    public static function diOggi(array $righe, string $oggi): array
    {
        $giornoSettimana = (int) (new \DateTimeImmutable($oggi))->format('N');
        $out = [];
        foreach ($righe as $r) {
            if (!self::visibile($r, $oggi)) continue;
            $da = self::inizio($r);
            if (($r['when'] ?? '') === 'weekly') {
                if (($da === '' || $da <= $oggi) && in_array($giornoSettimana, array_map('intval', (array) ($r['days'] ?? [])), true)) $out[] = $r;
            } elseif (!self::ricorrente($r) && $da <= $oggi) {
                $out[] = $r;
            }
        }
        return $out;
    }

    /** Quanti eventi «in questi giorni»: il numero sulla casella in home. */
    public static function conta(array $righe, string $oggi): int { return count(self::gruppi($righe, $oggi)['giorni']); }

    /** La casella «Eventi» si vede in home? No, se non c'è niente nei prossimi 60 giorni. */
    public static function daMostrare(array $righe, string $oggi): bool
    {
        $limite = self::piu($oggi, self::ORIZZONTE);
        foreach ($righe as $r) {
            if (self::visibile($r, $oggi) && (self::ricorrente($r) || self::inizio($r) <= $limite)) return true;
        }
        return false;
    }

    /** Gli eventi finiti: per l'elenco dell'host e per il promemoria. */
    public static function passati(array $righe, string $oggi): array
    {
        return array_values(array_filter($righe, fn(array $r) => trim((string) ($r['name'] ?? '')) !== '' && self::passato($r, $oggi)));
    }

    /**
     * Lo stato per la pillola nell'elenco dell'host.
     * @return array{0:string,1:int} [senza_data | passato | in_corso | tra | ricorrente, giorni che mancano]
     */
    public static function stato(array $r, string $oggi): array
    {
        if (self::passato($r, $oggi)) return ['passato', 0];
        if (self::ricorrente($r)) return ['ricorrente', 0];
        $da = self::inizio($r);
        if ($da === '') return ['senza_data', 0];
        if ($da <= $oggi) return ['in_corso', 0];
        return ['tra', (int) (new \DateTimeImmutable($oggi))->diff(new \DateTimeImmutable($da))->days];
    }

    /**
     * «Ripeti l'anno prossimo»: stesse informazioni, date spostate di un anno
     * (o più, finché l'evento non è nel futuro) e locandina tolta. Le date
     * restano da controllare: l'host le vede nel modulo prima di pubblicare.
     */
    public static function ripeti(array $r, string $oggi): array
    {
        for ($i = 0; $i < 20 && self::passato($r, $oggi); $i++) {
            foreach (['date_from', 'date_to'] as $k) {
                $d = self::data($r[$k] ?? '');
                if ($d !== '') $r[$k] = (new \DateTimeImmutable($d))->modify('+1 year')->format('Y-m-d');
            }
        }
        $r['poster'] = '';
        $r['poster_pdf'] = '';
        return $r;
    }

    /** L'anno dopo quello dell'evento: per l'etichetta «Ripeti nel 2027». */
    public static function annoDopo(array $r): int
    {
        $f = self::fine($r);
        return (int) substr($f !== '' ? $f : self::oggi(), 0, 4) + 1;
    }

    /** «3–4 ottobre», «giovedì 8 ottobre», «31 ottobre – 1 novembre», «ogni sabato». */
    public static function periodo(array $r, string $loc, ?string $oggi = null): string
    {
        $quando = (string) ($r['when'] ?? '');
        if ($quando === 'other') return trim((string) ($r['when_text'] ?? ''));
        if ($quando === 'weekly') {
            $gg = array_map(fn($g) => I18n::t($loc, 'day_' . (int) $g), (array) ($r['days'] ?? []));
            return $gg ? I18n::t($loc, 'ev.every', implode(', ', $gg)) : I18n::t($loc, 'ev.recurring');
        }
        $da = self::inizio($r);
        if ($da === '') return '';
        $a = self::fine($r);
        [$y1, $m1, $d1] = array_map('intval', explode('-', $da));
        [$y2, $m2, $d2] = array_map('intval', explode('-', $a));
        $anno = $y2 !== (int) substr($oggi ?? self::oggi(), 0, 4) ? ' ' . $y2 : '';
        $mese = fn(int $m) => I18n::t($loc, 'ev.month_' . $m);
        if ($a === $da) {
            return I18n::t($loc, 'ev.date_wdm', I18n::t($loc, 'day_' . (new \DateTimeImmutable($da))->format('N')), $d1, $mese($m1)) . $anno;
        }
        if ($m1 === $m2 && $y1 === $y2) return I18n::t($loc, 'ev.date_range', $d1, $d2, $mese($m1)) . $anno;
        return I18n::t($loc, 'ev.date_dm', $d1, $mese($m1)) . ' – ' . I18n::t($loc, 'ev.date_dm', $d2, $mese($m2)) . $anno;
    }

    /** «dalle 11:00», «21:00–23:00», oppure niente. */
    public static function orario(array $r, string $loc): string
    {
        $da = trim((string) ($r['time_from'] ?? ''));
        $a = trim((string) ($r['time_to'] ?? ''));
        if ($da === '') return '';
        if ($a !== '') return $da . '–' . $a;
        return in_array($r['when'] ?? '', ['range', 'weekly'], true) ? I18n::t($loc, 'ev.from_time', $da) : $da;
    }

    /**
     * Il riquadro con la data a sinistra della scheda: [riga grande, riga piccola].
     * «8 / gio», «3–4 / ott», «31 / ott», «sab / 8:00–13:00». Vuoto per «Altro».
     */
    public static function riquadro(array $r, string $loc): array
    {
        $quando = (string) ($r['when'] ?? '');
        if ($quando === 'weekly') {
            $gg = array_map('intval', (array) ($r['days'] ?? []));
            $da = trim((string) ($r['time_from'] ?? ''));
            $alle = trim((string) ($r['time_to'] ?? ''));
            return $gg ? [I18n::t($loc, 'ev.dow_' . $gg[0]) . (count($gg) > 1 ? '…' : ''), $da . ($da !== '' && $alle !== '' ? '–' . $alle : '')] : ['', ''];
        }
        if ($quando === 'other') return ['', ''];
        $da = self::inizio($r);
        if ($da === '') return ['', ''];
        $a = self::fine($r);
        [, $m1, $d1] = array_map('intval', explode('-', $da));
        [, $m2, $d2] = array_map('intval', explode('-', $a));
        if ($a === $da) return [(string) $d1, I18n::t($loc, 'ev.dow_' . (new \DateTimeImmutable($da))->format('N'))];
        return [$m1 === $m2 ? $d1 . '–' . $d2 : (string) $d1, I18n::t($loc, 'ev.mon_' . $m1)];
    }

    /** «5 min a piedi», «15 min in auto», oppure niente. */
    public static function distanza(array $r, string $loc): string
    {
        $min = (int) ($r['dist_min'] ?? 0);
        return $min > 0 ? I18n::t($loc, ($r['dist_mode'] ?? '') === 'car' ? 'ev.car' : 'ev.walk', $min) : '';
    }

    /** «Gratis», «Ingresso 5 €», «A pagamento», oppure niente. */
    public static function prezzo(array $r, string $loc): string
    {
        $tipo = (string) ($r['price_kind'] ?? '');
        if ($tipo === 'free') return I18n::t($loc, 'ev.free');
        if ($tipo !== 'paid') return '';
        $p = trim((string) ($r['price'] ?? ''));
        return $p !== '' ? I18n::t($loc, 'ev.paid', $p) : I18n::t($loc, 'ev.paid_plain');
    }

    /**
     * Il file .ics per «Aggiungi al calendario». Vuoto per gli eventi ricorrenti
     * e per quelli senza data: lì il pulsante non si mostra.
     */
    public static function ics(array $r, string $uid, string $indirizzoGuida = '', ?int $adesso = null): string
    {
        if (self::ricorrente($r) || self::inizio($r) === '') return '';
        $da = self::inizio($r);
        $a = self::fine($r);
        $ora = trim((string) ($r['time_from'] ?? ''));
        $fuso = new \DateTimeZone('Europe/Rome');
        $utc = new \DateTimeZone('UTC');
        $righe = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//MyHouse Welcome//Eventi//IT', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'BEGIN:VEVENT',
                  'UID:' . preg_replace('/[^A-Za-z0-9@._-]/', '', $uid),
                  'DTSTAMP:' . gmdate('Ymd\THis\Z', $adesso ?? time())];
        if ($a === $da && preg_match('/^\d{2}:\d{2}$/', $ora)) {
            $inizio = new \DateTimeImmutable($da . ' ' . $ora, $fuso);
            $alle = trim((string) ($r['time_to'] ?? ''));
            $fine = preg_match('/^\d{2}:\d{2}$/', $alle) && $alle > $ora ? new \DateTimeImmutable($da . ' ' . $alle, $fuso) : $inizio->modify('+2 hours');
            $righe[] = 'DTSTART:' . $inizio->setTimezone($utc)->format('Ymd\THis\Z');
            $righe[] = 'DTEND:' . $fine->setTimezone($utc)->format('Ymd\THis\Z');
        } else {
            $righe[] = 'DTSTART;VALUE=DATE:' . str_replace('-', '', $da);
            $righe[] = 'DTEND;VALUE=DATE:' . str_replace('-', '', self::piu($a, 1));   // la fine è esclusa
        }
        $testo = fn(string $s) => str_replace(["\\", ';', ',', "\r\n", "\n", "\r"], ["\\\\", '\;', '\,', '\n', '\n', ''], trim($s));
        $descrizione = trim((string) ($r['description'] ?? ''));
        if ($ora !== '' && $a !== $da) $descrizione = trim($ora . "\n" . $descrizione);
        $righe[] = 'SUMMARY:' . $testo((string) ($r['name'] ?? ''));
        if (trim((string) ($r['place'] ?? '')) !== '') $righe[] = 'LOCATION:' . $testo((string) $r['place']);
        if ($descrizione !== '') $righe[] = 'DESCRIPTION:' . $testo($descrizione);
        $link = trim((string) ($r['url'] ?? '')) ?: $indirizzoGuida;
        if ($link !== '') $righe[] = 'URL:' . $link;
        $righe[] = 'END:VEVENT';
        $righe[] = 'END:VCALENDAR';
        // Le righe lunghe si spezzano a 75 byte, senza tagliare un carattere a metà.
        $out = [];
        foreach ($righe as $riga) {
            while (strlen($riga) > 75) {
                $pezzo = mb_strcut($riga, 0, 75, 'UTF-8');
                $out[] = $pezzo;
                $riga = ' ' . substr($riga, strlen($pezzo));
            }
            $out[] = $riga;
        }
        return implode("\r\n", $out) . "\r\n";
    }
}
