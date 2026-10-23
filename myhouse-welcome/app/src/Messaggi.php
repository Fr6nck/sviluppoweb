<?php
namespace MHW;

/**
 * I due messaggi da mandare all'ospite su WhatsApp (o nella chat di Booking e Airbnb), in QR & Link (8B):
 *   benvenuto — prima dell'arrivo, con il link della guida;
 *   saluto    — a fine soggiorno, con il link della pagina delle recensioni (/g/{slug}/recensione).
 *
 * Si salvano per lingua in properties.messages come MODELLI con i segnaposto {link} e {host}, riempiti
 * quando si mostrano: se l'host cambia il link della guida (6M) i messaggi restano giusti. Un messaggio
 * vuoto o uguale a quello di serie non si salva: vale il testo di serie della lingua.
 */
final class Messaggi
{
    public const TIPI = ['benvenuto' => 'Messaggio di benvenuto', 'saluto' => 'Messaggio di fine soggiorno'];
    public const MAX = 1000;

    public static function disponibili(): bool { return Migrator::columnExists('properties', 'messages'); }

    /** @return array<string,array<string,string>> tipo => lingua => modello salvato */
    public static function salvati(array $p): array
    {
        $m = json_decode((string) ($p['messages'] ?? ''), true);
        return is_array($m) ? $m : [];
    }

    /** Il testo di serie, come modello ({link}, {host}). */
    public static function serie(array $p, string $tipo, string $loc): string
    {
        return $tipo === 'saluto' ? I18n::t($loc, 'farewell_message') : I18n::t($loc, 'welcome_message', (string) $p['name'], '{link}', '{host}');
    }

    public static function modello(array $p, string $tipo, string $loc): string
    {
        $s = trim((string) (self::salvati($p)[$tipo][$loc] ?? ''));
        return $s !== '' ? $s : self::serie($p, $tipo, $loc);
    }

    public static function cambiato(array $p, string $tipo, string $loc): bool { return trim((string) (self::salvati($p)[$tipo][$loc] ?? '')) !== ''; }

    /** Il link del messaggio: la guida (benvenuto) o la pagina del voto (saluto), nella lingua giusta. */
    public static function link(array $p, string $tipo, string $loc): string
    {
        $base = Support::baseUrl() . '/g/' . $p['slug'] . ($tipo === 'saluto' ? '/recensione' : '');
        return $base . ($loc === (string) $p['default_locale'] ? '' : '?l=' . $loc);
    }

    /** Chi firma: il nome dell'host, altrimenti il nome della struttura (come il messaggio di benvenuto di prima). */
    public static function firma(array $p): string
    {
        $h = trim((string) ($p['host_name'] ?? ''));
        return $h !== '' ? $h : (string) $p['name'];
    }

    /** Il messaggio pronto da copiare. */
    public static function testo(array $p, string $tipo, string $loc): string
    {
        return strtr(self::modello($p, $tipo, $loc), ['{link}' => self::link($p, $tipo, $loc), '{host}' => self::firma($p)]);
    }

    /** Salva il modello di una lingua; '' (o uguale a quello di serie) torna al testo di serie. */
    public static function salva(array $p, string $tipo, string $loc, string $testo): void
    {
        if (!isset(self::TIPI[$tipo]) || !self::disponibili()) return;
        $testo = mb_substr(trim(str_replace("\r\n", "\n", $testo)), 0, self::MAX);
        $m = self::salvati($p);
        if ($testo === '' || $testo === self::serie($p, $tipo, $loc)) unset($m[$tipo][$loc]);
        else $m[$tipo][$loc] = $testo;
        if (isset($m[$tipo]) && !$m[$tipo]) unset($m[$tipo]);
        Db::update('properties', ['messages' => $m ? json_encode($m, JSON_UNESCAPED_UNICODE) : null], 'id = :pid', ['pid' => (int) $p['id']]);
    }
}
