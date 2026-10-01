<?php
namespace MHW;

/**
 * Le conversioni dei contenuti dal formato di prima a quello a blocchi.
 * Una sola implementazione, usata da tre posti:
 *   - le migrazioni (009, 010, …), che convertono le tabelle di lavoro;
 *   - Guide::normalize(), che converte al volo le guide già pubblicate
 *     (le istantanee non si riscrivono: si leggono nel formato nuovo);
 *   - Demo, che scrive i suoi esempi nel formato nuovo.
 *
 * Regole: non si cancella niente (i vecchi campi restano nel JSON, solo non
 * si leggono più), e una conversione già fatta non si rifà (è idempotente).
 */
final class Conversione
{
    /** Le cinque caselle della partenza di prima, nell'ordine in cui si mostravano. */
    public const PARTENZA_VECCHIA = ['checkout_keys', 'checkout_waste', 'checkout_lights', 'checkout_climate', 'checkout_windows'];

    /**
     * Converte una sezione: dati comuni e testi per lingua.
     * @param array<string,array> $testi lingua => data
     * @return array{0:array,1:array<string,array>}
     */
    public static function sezione(string $kind, array $dati, array $testi): array
    {
        if ($kind === 'checkin') {
            foreach ($testi as $loc => $t) $testi[$loc] = self::partenza(is_array($t) ? $t : [], (string) $loc);
        }
        if ($kind === 'wifi') [$dati, $testi] = self::wifi($dati, $testi);
        return [$dati, $testi];
    }

    /**
     * Partenza: i campi non vuoti diventano voci della lista «Prima di partire»,
     * nello stesso ordine, con la loro etichetta («Chiavi: …») nella lingua del testo.
     */
    public static function partenza(array $t, string $loc): array
    {
        if (array_key_exists('checkout_steps', $t)) return $t;
        $voci = [];
        foreach (self::PARTENZA_VECCHIA as $k) {
            $v = trim((string) ($t[$k] ?? ''));
            if ($v !== '') $voci[] = I18n::t($loc, $k) . ': ' . $v;
        }
        if ($voci) $t['checkout_steps'] = $voci;
        return $t;
    }

    /** Wi-Fi: la rete singola (network, password) diventa la prima riga di «networks». */
    public static function wifi(array $dati, array $testi): array
    {
        if (array_key_exists('networks', $dati)) return [$dati, $testi];
        $ssid = trim((string) ($dati['network'] ?? ''));
        $pw = trim((string) ($dati['password'] ?? ''));
        if ($ssid === '' && $pw === '') return [$dati, $testi];
        // Un id derivato dai dati, non casuale: la stessa istantanea letta due volte
        // dà la stessa riga (serve al QR della guida pubblicata).
        $id = 'r' . substr(md5('wifi|' . $ssid . '|' . $pw), 0, 8);
        $dati['networks'] = [['id' => $id, 'ssid' => $ssid, 'password' => $pw]];
        foreach ($testi as $loc => $t) {
            if (!is_array($t)) $t = [];
            $t['networks'] = [['id' => $id, 'zone' => '']];
            $testi[$loc] = $t;
        }
        return [$dati, $testi];
    }

    /**
     * Contatti: da host_name / host_phone / host_whatsapp alla lista dei contatti.
     * Se telefono e WhatsApp sono due numeri diversi, diventano due righe: nessun
     * numero si perde.
     * @return list<array{name:string,role:string,phone:string,whatsapp:int}>
     */
    public static function contatti(string $nome, string $telefono, string $whatsapp): array
    {
        $nome = trim($nome); $tel = trim($telefono); $wa = trim($whatsapp);
        if ($nome === '' && $tel === '' && $wa === '') return [];
        $cifre = fn(string $n) => preg_replace('/\D/', '', $n);
        if ($tel === '' || $wa === '' || $cifre($tel) === $cifre($wa)) {
            return [['name' => $nome, 'role' => 'host', 'phone' => $tel !== '' ? $tel : $wa, 'whatsapp' => $wa !== '' ? 1 : 0]];
        }
        return [['name' => $nome, 'role' => 'host', 'phone' => $tel, 'whatsapp' => 0],
                ['name' => $nome, 'role' => 'host', 'phone' => $wa, 'whatsapp' => 1]];
    }

    /**
     * La stringa di un QR Wi-Fi: WIFI:T:WPA;S:<rete>;P:<password>;;
     * con \ ; , : " preceduti da una barra rovesciata. Senza password, rete aperta.
     */
    public static function wifiQr(string $ssid, string $password): string
    {
        $esc = fn(string $s) => preg_replace('/([\\\\;,:"])/', '\\\\$1', $s);
        return $password === ''
            ? 'WIFI:T:nopass;S:' . $esc($ssid) . ';;'
            : 'WIFI:T:WPA;S:' . $esc($ssid) . ';P:' . $esc($password) . ';;';
    }
}
