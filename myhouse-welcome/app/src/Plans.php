<?php
namespace MHW;

/**
 * Il listino come lo vede chi compra: pacchetti in vendita, versione
 * corrente, testo commerciale. Tutto dal database — prezzi e testi li cambia
 * l'amministratore, non il codice. I pacchetti della stessa famiglia
 * (Portfolio 2 e 3) si presentano come un'offerta sola con più opzioni.
 */
final class Plans
{
    /** @return array<int,array> pacchetti pubblici, ciascuno con la versione corrente */
    public static function public(): array
    {
        $rows = Db::all(
            'SELECT p.*, pv.id AS pv_id, pv.version, pv.price_cents, pv.currency, pv.stripe_price_id,
                    pv.per_property, pv.extra_price_cents, pv.min_quantity, pv.max_quantity'
            . (Migrator::columnExists('package_versions', 'extra_tiers') ? ', pv.extra_tiers' : '') . '
             FROM packages p JOIN package_versions pv ON pv.package_id = p.id AND pv.is_current = 1
             WHERE p.active = 1 AND p.public = 1 ORDER BY p.sort, p.id');
        foreach ($rows as &$r) {
            $r['bullet_list'] = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $r['bullets']) ?: [])));
            $r['features'] = array_column(Db::all(
                'SELECT f.code, pf.value FROM package_features pf JOIN features f ON f.id = pf.feature_id
                 WHERE pf.package_version_id = ?', [$r['pv_id']]), 'value', 'code');
        }
        return $rows;
    }

    /**
     * Raggruppati per offerta: [['main' => pacchetto, 'options' => [pacchetti]], …]
     * Un pacchetto senza famiglia è un'offerta da solo.
     */
    public static function offers(): array
    {
        $out = [];
        foreach (self::public() as $p) {
            $k = $p['family'] !== '' ? 'f:' . $p['family'] : 'p:' . $p['id'];
            if (!isset($out[$k])) $out[$k] = ['main' => $p, 'options' => []];
            $out[$k]['options'][] = $p;
        }
        return array_values($out);
    }

    /** La versione in vendita di un pacchetto pubblico, dato l'id di una sua versione qualsiasi. */
    public static function currentVersion(int $pvId): ?array
    {
        return Db::one(
            'SELECT pv.*, p.name, p.code, p.family FROM package_versions pv JOIN packages p ON p.id = pv.package_id
             WHERE pv.package_id = (SELECT package_id FROM package_versions WHERE id = ?)
               AND pv.is_current = 1 AND p.active = 1 AND p.public = 1', [$pvId]);
    }

    public static function version(int $pvId): ?array
    {
        return Db::one('SELECT pv.*, p.name, p.code, p.family, p.tagline FROM package_versions pv
                        JOIN packages p ON p.id = pv.package_id WHERE pv.id = ?', [$pvId]);
    }

    /** Il piano si paga a struttura (Portfolio)? */
    public static function perProperty(array $pv): bool { return (int) ($pv['per_property'] ?? 0) === 1; }

    /**
     * La quantità valida per una versione, o null se quella chiesta non va bene.
     * I piani a struttura singola valgono sempre 1.
     */
    public static function quantity(array $pv, mixed $chiesta): ?int
    {
        if (!self::perProperty($pv)) return 1;
        $min = max(1, (int) $pv['min_quantity']); $max = max($min, (int) $pv['max_quantity']);
        if ($chiesta === null || $chiesta === '') return $min;
        if (!is_int($chiesta) && !(is_string($chiesta) && ctype_digit(trim($chiesta)))) return null;
        $q = (int) $chiesta;
        return $q >= $min && $q <= $max ? $q : null;
    }

    /**
     * Gli scaglioni del Portfolio: dalla struttura N in poi, ognuna costa tot.
     * Senza scaglioni (versioni vendute prima) c'è un solo prezzo per struttura aggiuntiva.
     * @return array<int,array{da:int,a:?int,cents:int}> in ordine; 'a' null = senza limite
     */
    public static function tiers(array $pv): array
    {
        if (!self::perProperty($pv)) return [];
        $grezzi = json_decode((string) ($pv['extra_tiers'] ?? ''), true);
        $t = [];
        foreach (is_array($grezzi) ? $grezzi : [] as $x) {
            if (is_array($x) && (int) ($x['da'] ?? 0) >= 2 && (int) ($x['cents'] ?? -1) >= 0) $t[(int) $x['da']] = (int) $x['cents'];
        }
        if (!isset($t[2])) $t[2] = (int) $pv['extra_price_cents'];
        ksort($t);
        $da = array_keys($t); $out = [];
        foreach ($da as $i => $n) $out[] = ['da' => $n, 'a' => isset($da[$i + 1]) ? $da[$i + 1] - 1 : null, 'cents' => $t[$n]];
        return $out;
    }

    /** Quanto costa all'anno la struttura numero $n (la prima è il prezzo base). */
    public static function unitPrice(array $pv, int $n): int
    {
        if ($n <= 1 || !self::perProperty($pv)) return (int) $pv['price_cents'];
        $c = 0;
        foreach (self::tiers($pv) as $t) if ($n >= $t['da']) $c = $t['cents'];
        return $c;
    }

    /** Il prezzo annuale per una quantità: prima struttura + le altre, ognuna al prezzo del suo scaglione. */
    public static function price(array $pv, int $quantity = 1): int
    {
        $base = (int) $pv['price_cents'];
        if (!self::perProperty($pv)) return $base;
        $tot = $base;
        for ($n = 2; $n <= $quantity; $n++) $tot += self::unitPrice($pv, $n);
        return $tot;
    }

    /** Gli scaglioni per il JavaScript del listino: [[da, centesimi], …]. */
    public static function tiersJson(array $pv): string
    {
        return json_encode(array_map(fn($t) => [$t['da'], $t['cents']], self::tiers($pv)));
    }

    /** «dalla 3ª alla 5ª», «dall'11ª alla 20ª», «oltre la 20ª»: per il listino. */
    public static function tierLabel(array $t): string
    {
        $o = fn(int $n) => $n . 'ª';
        $dal = fn(int $n) => in_array($n, [8, 11], true) || ($n >= 80 && $n < 90) ? "dall'" . $o($n) : 'dalla ' . $o($n);
        if ($t['a'] === null) return $t['da'] === 2 ? 'dalla 2ª in poi' : 'oltre la ' . $o($t['da'] - 1);
        if ($t['a'] === $t['da']) return $o($t['da']);
        return $dal($t['da']) . ' alla ' . $o($t['a']);
    }

    /** L'equivalente mensile di un prezzo annuale: «circa 9,75 € al mese». */
    public static function monthly(int $centsAnno): int { return (int) round($centsAnno / 12); }

    /**
     * La tabella di confronto, generata dalle funzioni delle versioni in vendita:
     * una riga per funzione compresa in almeno un piano pubblico.
     * @return array{piani:array,righe:array<int,array{label:string,valori:string[]}>}
     */
    public static function comparison(): array
    {
        $piani = [];
        foreach (self::offers() as $of) {
            $p = $of['options'][0];
            $p['nome'] = count($of['options']) > 1 ? preg_replace('/\s*\d+$/', '', $of['main']['name']) : $p['name'];
            $piani[] = $p;
        }
        $righe = [];
        foreach (Db::all('SELECT * FROM features ORDER BY id') as $f) {
            $valori = []; $qualcuno = false;
            foreach ($piani as $p) {
                $v = (string) ($p['features'][$f['code']] ?? $f['default_value']);
                if ($v !== '0' && $v !== '') $qualcuno = true;
                if ($f['code'] === 'properties' && self::perProperty($p)) $v = 'da ' . (int) $p['min_quantity'] . ' a ' . (int) $p['max_quantity'];
                elseif ($v === 'unlimited') $v = 'Illimitate';
                elseif ($f['kind'] === 'bool') $v = $v !== '0' && $v !== '' ? 'si' : 'no';
                $valori[] = $v;
            }
            if ($qualcuno) $righe[] = ['label' => $f['label'], 'code' => $f['code'], 'valori' => $valori];
        }
        return ['piani' => $piani, 'righe' => $righe];
    }

    public static function priceLabel(int $cents, string $currency = 'EUR'): string
    {
        return Support::money($cents, $currency) . ' + IVA / anno';
    }
}
