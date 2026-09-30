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
                    pv.per_property, pv.extra_price_cents, pv.min_quantity, pv.max_quantity
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

    /** Il prezzo annuale per una quantità: prima struttura + le altre al prezzo aggiuntivo. */
    public static function price(array $pv, int $quantity = 1): int
    {
        $base = (int) $pv['price_cents'];
        if (!self::perProperty($pv)) return $base;
        return $base + max(0, $quantity - 1) * (int) $pv['extra_price_cents'];
    }

    public static function priceLabel(int $cents, string $currency = 'EUR'): string
    {
        return Support::money($cents, $currency) . ' + IVA / anno';
    }
}
