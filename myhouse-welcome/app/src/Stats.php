<?php
namespace MHW;

/**
 * Statistiche di lettura, di base. Contano eventi anonimi: nessun IP, nessun
 * cookie, nessuna impronta del dispositivo. Gli eventi scritti dalle versioni
 * precedenti (open, qr, section) valgono come quelli nuovi.
 */
final class Stats
{
    private const VIEW = ['guide_view', 'open'];
    private const QR = ['qr_open', 'qr'];
    private const SECTION = ['section_view', 'section'];

    private static function in(array $kinds): string { return "'" . implode("','", $kinds) . "'"; }

    public static function forProperty(int $propertyId, int $days = 30): array
    {
        $da = gmdate('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $conta = fn(array $k) => (int) Db::val(
            'SELECT COUNT(*) FROM analytics_events WHERE property_id = ? AND day >= ? AND kind IN (' . self::in($k) . ')',
            [$propertyId, $da], 0);

        $serie = [];
        for ($i = $days - 1; $i >= 0; $i--) $serie[gmdate('Y-m-d', strtotime("-$i days"))] = 0;
        foreach (Db::all('SELECT day, COUNT(*) AS n FROM analytics_events WHERE property_id = ? AND day >= ? AND kind IN ('
                         . self::in(self::VIEW) . ') GROUP BY day', [$propertyId, $da]) as $r) {
            if (isset($serie[$r['day']])) $serie[$r['day']] = (int) $r['n'];
        }

        $sezioni = Db::all(
            'SELECT e.section_id, COUNT(*) AS n, s.kind,
                    (SELECT t.title FROM section_translations t JOIN properties p ON p.id = s.property_id
                      WHERE t.section_id = s.id AND t.locale = p.default_locale) AS title
             FROM analytics_events e JOIN sections s ON s.id = e.section_id
             WHERE e.property_id = ? AND e.day >= ? AND e.kind IN (' . self::in(self::SECTION) . ')
             GROUP BY e.section_id ORDER BY n DESC LIMIT 8', [$propertyId, $da]);

        $lingue = Db::all(
            'SELECT locale, COUNT(*) AS n FROM analytics_events
             WHERE property_id = ? AND day >= ? AND kind IN (' . self::in(self::VIEW) . ") AND locale <> ''
             GROUP BY locale ORDER BY n DESC", [$propertyId, $da]);

        return [
            'days' => $days, 'views' => $conta(self::VIEW), 'qr' => $conta(self::QR),
            'sections' => $sezioni, 'languages' => $lingue, 'series' => $serie,
        ];
    }

    /** Il totale delle letture recenti di tutte le guide pubblicate (per il quadro admin). */
    public static function totalViews(int $days = 30): int
    {
        return (int) Db::val('SELECT COUNT(*) FROM analytics_events e JOIN properties p ON p.id = e.property_id
                              WHERE p.is_demo = 0 AND e.day >= ? AND e.kind IN (' . self::in(self::VIEW) . ')',
                             [gmdate('Y-m-d', strtotime('-' . ($days - 1) . ' days'))], 0);
    }
}
