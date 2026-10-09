<?php
namespace MHW;

/**
 * Codici di accesso nelle guide (6M): porte, portoni, cancelli, garage, cassette delle chiavi,
 * casseforti, allarmi. Sono SCONSIGLIATI, non vietati: la piattaforma li riconosce, avvisa
 * l'host e alla pubblicazione gli chiede di confermare che lo fa a suo rischio.
 *
 * codiceAccesso() è pura (niente database): vero se nello stesso tratto di 60 caratteri ci sono
 * una parola di accesso, una parola di luogo e un gruppo di 3–8 cifre (anche separate da spazi,
 * punti o trattini). Senza distinguere maiuscole e accenti. La stessa regola, in JavaScript,
 * sta in assets/codici.js: se cambi una lista, cambiala anche lì.
 */
final class Sicurezza
{
    public const FINESTRA = 60;

    public const ACCESSO = ['codice', 'code', 'codigo', 'pin', 'combinazione', 'combination', 'combinaison', 'combinacion',
                            'digicode', 'zahlencode', 'kombination'];

    public const LUOGO = ['porta', 'portone', 'cancello', 'ingresso', 'garage', 'box auto', 'cassetta', 'cassetta di sicurezza',
                          'keybox', 'key box', 'lockbox', 'cassaforte', 'safe', 'coffre', 'coffre-fort', 'caja fuerte', 'tresor',
                          'allarme', 'alarm', 'alarme', 'alarma', 'alarmanlage', 'lucchetto', 'serratura', 'door', 'gate', 'porte',
                          'portail', 'puerta', 'tur', 'tor'];

    /** I campi che non si controllano: Wi-Fi, CIN, telefoni, CAP, prezzi, indirizzi web, codice sconto per chi torna. */
    private const ESCLUSI = ['ssid', 'password', 'phone', 'whatsapp', 'cin', 'postal_code', 'cap', 'price', 'prezzo', 'cost', 'costo',
                             'amount', 'tax_amount', 'direct_code', 'direct_discount_code', 'maps_url', 'website', 'booking_url', 'url'];

    /** Minuscole, senza accenti (Tür → tur, código → codigo). */
    public static function normalizza(string $t): string
    {
        // Senza iconv né intl: iconv con TRANSLIT dipende dalla locale del server e può dare «?».
        return strtr(mb_strtolower($t, 'UTF-8'), [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n', 'ß' => 'ss', '’' => "'",
        ]);
    }

    /** Le posizioni in cui compare una delle parole (intere, non dentro altre parole). */
    private static function posizioni(string $t, array $parole): array
    {
        $pos = [];
        foreach ($parole as $p) {
            $re = '/(?<![a-z0-9])' . str_replace(' ', '[\s\-]+', preg_quote($p, '/')) . '(?![a-z0-9])/';
            if (preg_match_all($re, $t, $m, PREG_OFFSET_CAPTURE)) foreach ($m[0] as $x) $pos[] = $x[1];
        }
        return $pos;
    }

    public static function codiceAccesso(string $testo): bool
    {
        $t = self::normalizza($testo);
        if (!preg_match('/\d/', $t)) return false;
        $accesso = self::posizioni($t, self::ACCESSO);
        if (!$accesso) return false;
        $luogo = self::posizioni($t, self::LUOGO);
        if (!$luogo) return false;
        $cifre = [];
        if (preg_match_all('/(?<![\d])\d(?:[ .\-]?\d){2,7}(?![\d])/', $t, $m, PREG_OFFSET_CAPTURE)) foreach ($m[0] as $x) $cifre[] = $x[1];
        foreach ($cifre as $c) foreach ($accesso as $a) foreach ($luogo as $l) {
            if (max($a, $l, $c) - min($a, $l, $c) <= self::FINESTRA) return true;
        }
        return false;
    }

    /** Il campo si controlla? Le chiavi escluse, e quelle che finiscono con _url, _phone, _price, _cost. */
    public static function campoControllato(string $chiave): bool
    {
        $k = strtolower($chiave);
        return !in_array($k, self::ESCLUSI, true) && !preg_match('/_(url|phone|price|cost|prezzo|costo)$/', $k);
    }

    /** I testi di un valore (anche annidato), con il percorso del campo. @return array<int,array{0:string,1:string}> */
    private static function testi(mixed $v, string $percorso = ''): array
    {
        $out = [];
        if (is_string($v)) { if ($v !== '') $out[] = [$percorso, $v]; return $out; }
        if (!is_array($v)) return $out;
        foreach ($v as $k => $x) {
            if (is_string($k) && !self::campoControllato($k)) continue;
            $out = array_merge($out, self::testi($x, $percorso === '' ? (string) $k : $percorso . '.' . $k));
        }
        return $out;
    }

    /** L'etichetta del campo per l'host: dal catalogo delle sezioni, altrimenti «Testo». */
    private static function etichetta(string $kind, string $percorso): string
    {
        $parti = explode('.', $percorso);
        $def = SectionCatalog::field($kind, $parti[0]);
        if (!$def) return in_array($parti[0], ['title', 'titolo'], true) ? 'Titolo' : 'Testo';
        foreach (array_slice($parti, 1) as $p) {
            if (!ctype_digit($p) && isset($def['sub'][$p])) return $def[1] . ' → ' . $def['sub'][$p][1];
        }
        return (string) $def[1];
    }

    /**
     * I campi di un'istantanea (Guide::build/normalize) che contengono un codice: sezioni e luoghi,
     * in tutte le lingue. Ogni voce: id della sezione, titolo, campo, lingua. Mai il codice.
     * @return array<int,array{sid:int,sezione:string,campo:string,lingua:string}>
     */
    public static function campiConCodice(array $snap): array
    {
        $out = []; $visti = [];
        $base = (string) ($snap['property']['default_locale'] ?? 'it');
        foreach ($snap['sections'] ?? [] as $s) {
            $kind = (string) ($s['kind'] ?? '');
            $titolo = trim((string) ($s['tr'][$base]['title'] ?? '')) ?: SectionCatalog::title($kind, 'it');
            $fonti = [[$base, $s['data'] ?? []]];
            foreach ($s['tr'] ?? [] as $loc => $t) $fonti[] = [(string) $loc, ['title' => $t['title'] ?? ''] + (array) ($t['data'] ?? [])];
            foreach ($s['places'] ?? [] as $pl) {
                $fonti[] = [$base, ['luogo' => (string) ($pl['name'] ?? '')]];
                foreach ($pl['tr'] ?? [] as $loc => $t) $fonti[] = [(string) $loc, ['luogo_descrizione' => (string) ($t['description'] ?? ''), 'luogo_nota' => (string) ($t['note'] ?? '')]];
            }
            foreach ($fonti as [$loc, $dati]) {
                foreach (self::testi($dati) as [$percorso, $testo]) {
                    if (!self::codiceAccesso($testo)) continue;
                    $campo = str_starts_with($percorso, 'luogo') ? 'Luoghi' : self::etichetta($kind, $percorso);
                    $k = $s['id'] . '|' . $campo . '|' . $loc;
                    if (isset($visti[$k])) continue;
                    $visti[$k] = true;
                    $out[] = ['sid' => (int) $s['id'], 'sezione' => $titolo, 'campo' => $campo, 'lingua' => $loc];
                }
            }
        }
        return $out;
    }

    /** I campi di una variante camera che contengono un codice. @return string[] */
    public static function campiVariante(array $dati): array
    {
        $out = [];
        foreach (self::testi($dati) as [$percorso, $testo]) if (self::codiceAccesso($testo)) $out[] = $percorso;
        return array_values(array_unique($out));
    }
}
