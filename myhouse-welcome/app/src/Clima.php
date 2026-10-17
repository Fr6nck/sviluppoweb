<?php
namespace MHW;

/**
 * Riscaldamento e aria condizionata (sezione «clima»): le fasce orarie di un impianto
 * e se adesso è acceso. L'ora è quella italiana, non quella del telefono dell'ospite.
 *
 * Una fascia è [from, to, days]: days vuoto vale tutti i giorni (1 = lunedì … 7 = domenica).
 * Una fascia che passa la mezzanotte (22:00 → 6:00) vale fino alla mattina dopo.
 */
final class Clima
{
    /** Le fasce compilate per bene, in ordine di orario. */
    public static function fasce(array $righe): array
    {
        $ok = fn($t) => is_string($t) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) === 1;
        $f = array_values(array_filter(array_map(fn($r) => [
            'from' => (string) ($r['from'] ?? ''), 'to' => (string) ($r['to'] ?? ''),
            'days' => array_values(array_filter(array_map('intval', (array) ($r['days'] ?? [])), fn($d) => $d >= 1 && $d <= 7)),
        ], $righe), fn($r) => $ok($r['from']) && $ok($r['to']) && $r['from'] !== $r['to']));
        usort($f, fn($a, $b) => [$a['days'] ? min($a['days']) : 0, $a['from']] <=> [$b['days'] ? min($b['days']) : 0, $b['from']]);
        return $f;
    }

    /** Vale in quel giorno (1–7)? */
    private static function inGiorno(array $f, int $g): bool { return !$f['days'] || in_array($g, $f['days'], true); }

    /**
     * Adesso è acceso? E fino a quando, o quando si riaccende: oggi più tardi o domani.
     * @return array{acceso:bool,fino:?string,prossima:?string,domani:bool}
     */
    public static function stato(array $fasce, ?\DateTimeImmutable $ora = null): array
    {
        $ora ??= new \DateTimeImmutable('now', new \DateTimeZone('Europe/Rome'));
        $oggi = (int) $ora->format('N'); $ieri = $oggi === 1 ? 7 : $oggi - 1; $hm = $ora->format('H:i');
        foreach ($fasce as $f) {
            $notte = $f['to'] < $f['from'];
            $dentro = $notte
                ? (self::inGiorno($f, $oggi) && $hm >= $f['from']) || (self::inGiorno($f, $ieri) && $hm < $f['to'])
                : self::inGiorno($f, $oggi) && $hm >= $f['from'] && $hm < $f['to'];
            if ($dentro) return ['acceso' => true, 'fino' => $f['to'], 'prossima' => null, 'domani' => false];
        }
        // La prossima accensione: oggi più tardi, altrimenti domani (più in là non si dice).
        $domani = $oggi === 7 ? 1 : $oggi + 1;
        $oggiDopo = array_filter($fasce, fn($f) => self::inGiorno($f, $oggi) && $f['from'] > $hm);
        if ($oggiDopo) return ['acceso' => false, 'fino' => null, 'prossima' => min(array_column($oggiDopo, 'from')), 'domani' => false];
        $diDomani = array_filter($fasce, fn($f) => self::inGiorno($f, $domani));
        return ['acceso' => false, 'fino' => null, 'prossima' => $diDomani ? min(array_column($diDomani, 'from')) : null, 'domani' => (bool) $diDomani];
    }

    /** «tutti i giorni», «lun–ven», «sab, dom»: nella lingua dell'ospite. */
    public static function giorni(array $days, string $loc): string
    {
        sort($days);
        if (!$days || count($days) === 7) return I18n::t($loc, 'clima.every_day');
        $breve = fn(int $g) => mb_substr(I18n::t($loc, 'day_' . $g), 0, 3);
        $consecutivi = count($days) >= 3 && $days === range($days[0], end($days));
        return $consecutivi ? $breve($days[0]) . "\u{2013}" . $breve(end($days)) : implode(', ', array_map($breve, $days));
    }

    /** 6:30 invece di 06:30: si legge meglio. */
    public static function ora(string $hm): string { return ltrim(substr($hm, 0, 2), '0') === '' ? '0' . substr($hm, 2) : ltrim(substr($hm, 0, 2), '0') . substr($hm, 2); }
}
