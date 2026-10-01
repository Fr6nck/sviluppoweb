<?php
namespace MHW;

/**
 * Le conversioni dei contenuti dal formato di prima a quello a blocchi.
 * Una sola implementazione, usata da tre posti:
 *   - le migrazioni (009, 010, 011…), che convertono le tabelle di lavoro;
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
     * @param string $principale la lingua principale: le sue righe decidono quante
     *                           righe nascono (le altre lingue si allineano per posizione)
     * @return array{0:array,1:array<string,array>}
     */
    public static function sezione(string $kind, array $dati, array $testi, string $principale = 'it'): array
    {
        foreach ($testi as $loc => $t) if (!is_array($t)) $testi[$loc] = [];
        if ($kind === 'checkin') {
            foreach ($testi as $loc => $t) $testi[$loc] = self::partenza($t, (string) $loc);
        }
        return match ($kind) {
            'wifi' => self::wifi($dati, $testi),
            'emergency' => self::emergenze($dati, $testi, $principale),
            'waste' => self::rifiuti($dati, $testi, $principale),
            'parking' => self::parcheggio($dati, $testi),
            'arrival' => self::percorsi($dati, $testi),
            default => [$dati, $testi],
        };
    }

    /**
     * Le voci «una per riga» di un campo tradotto, con la lingua che fa da guida:
     * la principale se ha voci, altrimenti la prima che ne ha.
     * @return array{0:list<string>,1:string}|null [voci, lingua]
     */
    private static function voci(array $testi, string $campo, string $principale): ?array
    {
        $pulisci = fn(mixed $v) => array_values(array_filter(array_map(fn($x) => trim((string) $x), (array) $v), fn($x) => $x !== ''));
        $ordine = array_unique(array_merge([$principale], array_keys($testi)));
        foreach ($ordine as $loc) {
            $v = $pulisci($testi[$loc][$campo] ?? []);
            if ($v) return [$v, (string) $loc];
        }
        return null;
    }

    /** Un id di riga derivato dal contenuto: la stessa istantanea letta due volte dà le stesse righe. */
    private static function idRiga(string $kind, int $i, string $testo): string
    {
        return 'r' . substr(md5($kind . '|' . $i . '|' . $testo), 0, 8);
    }

    /**
     * Separa un numero di telefono dal resto di una riga:
     * «Guardia medica: 075 123456 (notti e festivi)» → [Guardia medica, 075 123456, notti e festivi].
     * Un numero ha almeno 6 cifre, oppure è un numero di servizio breve (112, 118, 1515).
     * @return array{0:string,1:string,2:string} [nome, telefono, nota]
     */
    public static function separaTelefono(string $riga): array
    {
        $riga = trim($riga);
        if (preg_match_all('/(?<![\w+])\+?\d[\d .\/-]*\d(?!\w)/u', $riga, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as [$num, $pos]) {
                $cifre = preg_replace('/\D/', '', $num);
                if (strlen($cifre) < 6 && !preg_match('/^1\d{2,3}$/', $cifre)) continue;
                $taglia = " \t,;:–—-";
                $prima = trim(substr($riga, 0, $pos), $taglia);
                $prima = trim((string) preg_replace('/[\s,;:–—-]*\b(tel|telefono|cell|cellulare|phone|t[ée]l[ée]phone|telefon|tel[ée]fono)\b\.?$/iu', '', $prima), $taglia);
                $dopo = trim(substr($riga, $pos + strlen($num)), $taglia);
                if (preg_match('/^\((.*)\)$/su', $dopo, $p)) $dopo = trim($p[1]);
                if ($prima === '') { $prima = $dopo; $dopo = ''; }
                return [$prima, trim($num), $dopo];
            }
        }
        return [$riga, '', ''];
    }

    /** Emergenze: le voci «una per riga» diventano righe nome · telefono · nota. */
    public static function emergenze(array $dati, array $testi, string $principale = 'it'): array
    {
        if (array_key_exists('contacts', $dati)) return [$dati, $testi];
        $guida = self::voci($testi, 'items', $principale);
        if (!$guida) return [$dati, $testi];
        [$voci, $lg] = $guida;
        $comuni = []; $ids = [];
        foreach ($voci as $i => $v) {
            $ids[$i] = self::idRiga('emergency', $i, $v);
            $comuni[] = ['id' => $ids[$i], 'phone' => self::separaTelefono($v)[1]];
        }
        $dati['contacts'] = $comuni;
        foreach ($testi as $loc => $t) {
            $sue = array_values(array_filter(array_map(fn($x) => trim((string) $x), (array) ($t['items'] ?? [])), fn($x) => $x !== ''));
            $righe = [];
            foreach ($ids as $i => $id) {
                $v = $loc === $lg ? $voci[$i] : ($sue[$i] ?? '');
                [$nome, , $nota] = $v !== '' ? self::separaTelefono($v) : ['', '', ''];
                $righe[] = ['id' => $id, 'name' => $nome, 'note' => $nota];
            }
            $t['contacts'] = $righe;
            $testi[$loc] = $t;
        }
        return [$dati, $testi];
    }

    /** I tipi di rifiuto riconosciuti in una riga, per parola chiave. */
    private const RIFIUTI = [
        'umido' => '/\b(umido|organico|food waste|organic|biom[uü]ll|bio|biod[ée]chets|org[aá]nico)/iu',
        'carta' => '/\b(carta|cartone|paper|cardboard|papier|papel)/iu',
        'plastica' => '/\b(plastica|plastic|plastique|kunststoff|pl[aá]stico|lattine|metalli)/iu',
        'vetro' => '/\b(vetro|glass|verre|glas|vidrio)/iu',
        'indifferenziato' => '/\b(indifferenziat|secco|residu|restm[uü]ll|general waste)/iu',
    ];
    /** I giorni della settimana in italiano e in inglese, per riconoscerli in una riga. */
    private const GIORNI = [
        1 => '/\b(luned[iì]|monday)/iu', 2 => '/\b(marted[iì]|tuesday)/iu', 3 => '/\b(mercoled[iì]|wednesday)/iu',
        4 => '/\b(gioved[iì]|thursday)/iu', 5 => '/\b(venerd[iì]|friday)/iu', 6 => '/\b(sabato|saturday)/iu', 7 => '/\b(domenica|sunday)/iu',
    ];

    /**
     * Rifiuti: le voci «una per riga» diventano righe col testo nella descrizione.
     * Il tipo si riconosce solo quando non c'è dubbio (un tipo solo nella riga,
     * altrimenti «Altro»); i giorni scritti per esteso (lunedì, monday…) si
     * riconoscono sempre. Il testo resta intero, nella descrizione.
     */
    public static function rifiuti(array $dati, array $testi, string $principale = 'it'): array
    {
        if (array_key_exists('bins', $dati)) return [$dati, $testi];
        $guida = self::voci($testi, 'items', $principale);
        if (!$guida) return [$dati, $testi];
        [$voci, $lg] = $guida;
        $comuni = []; $ids = [];
        foreach ($voci as $i => $v) {
            $ids[$i] = self::idRiga('waste', $i, $v);
            $tipi = array_keys(array_filter(self::RIFIUTI, fn($re) => (bool) preg_match($re, $v)));
            $giorni = array_keys(array_filter(self::GIORNI, fn($re) => (bool) preg_match($re, $v)));
            $comuni[] = ['id' => $ids[$i], 'type' => count($tipi) === 1 ? $tipi[0] : 'altro', 'days' => $giorni, 'color' => ''];
        }
        $dati['bins'] = $comuni;
        foreach ($testi as $loc => $t) {
            $sue = array_values(array_filter(array_map(fn($x) => trim((string) $x), (array) ($t['items'] ?? [])), fn($x) => $x !== ''));
            $righe = [];
            foreach ($ids as $i => $id) $righe[] = ['id' => $id, 'label' => $loc === $lg ? $voci[$i] : ($sue[$i] ?? ''), 'where' => ''];
            $t['bins'] = $righe;
            $testi[$loc] = $t;
        }
        return [$dati, $testi];
    }

    /** Parcheggio: il parcheggio unico di prima (tipo, indirizzo, link, costo, istruzioni) diventa la prima riga. */
    public static function parcheggio(array $dati, array $testi): array
    {
        if (array_key_exists('options', $dati)) return [$dati, $testi];
        $indirizzo = trim((string) ($dati['address'] ?? ''));
        $maps = trim((string) ($dati['maps_url'] ?? ''));
        $pieno = $indirizzo !== '' || $maps !== '';
        foreach ($testi as $t) foreach (['parking_type', 'cost', 'instructions'] as $k) if (trim((string) ($t[$k] ?? '')) !== '') $pieno = true;
        if (!$pieno) return [$dati, $testi];
        $id = 'r' . substr(md5('parking|' . $indirizzo . '|' . $maps), 0, 8);
        $dati['options'] = [['id' => $id, 'type' => '', 'address' => $indirizzo, 'maps_url' => $maps, 'photo' => '']];
        foreach ($testi as $loc => $t) {
            $t['options'] = [['id' => $id, 'name' => trim((string) ($t['parking_type'] ?? '')), 'cost' => trim((string) ($t['cost'] ?? '')),
                              'instructions' => trim((string) ($t['instructions'] ?? ''))]];
            $testi[$loc] = $t;
        }
        return [$dati, $testi];
    }

    /** Come arrivare: i passaggi di prima diventano la prima scheda, senza mezzo indicato. */
    public static function percorsi(array $dati, array $testi): array
    {
        if (array_key_exists('routes', $dati)) return [$dati, $testi];
        $passi = fn(array $t) => array_values(array_filter(array_map(fn($x) => trim((string) $x), (array) ($t['steps'] ?? [])), fn($x) => $x !== ''));
        $tutti = array_filter(array_map($passi, $testi));
        if (!$tutti) return [$dati, $testi];
        $id = 'r' . substr(md5('arrival|' . implode('|', reset($tutti))), 0, 8);
        $dati['routes'] = [['id' => $id, 'mode' => '']];
        foreach ($testi as $loc => $t) {
            $t['routes'] = [['id' => $id, 'steps' => implode("\n", $passi($t))]];
            $testi[$loc] = $t;
        }
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
