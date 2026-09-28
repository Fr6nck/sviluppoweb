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
            'SELECT p.*, pv.id AS pv_id, pv.version, pv.price_cents, pv.currency, pv.stripe_price_id
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

    public static function priceLabel(int $cents, string $currency = 'EUR'): string
    {
        return Support::money($cents, $currency) . ' + IVA / anno';
    }
}
