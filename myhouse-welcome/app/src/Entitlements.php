<?php
namespace MHW;

/**
 * Cosa può fare un account. L'ordine di risoluzione è sempre lo stesso:
 * override manuale -> pacchetto acquistato -> valore predefinito della feature.
 * Nessuno perde una funzione che ha già comprato: si legge la VERSIONE del
 * pacchetto a cui è agganciato l'abbonamento, non il pacchetto di oggi.
 */
final class Entitlements
{
    private static array $cache = [];

    public static function forAccount(int $accountId): array
    {
        if (isset(self::$cache[$accountId])) return self::$cache[$accountId];

        $out = [];
        foreach (Db::all('SELECT code, kind, default_value FROM features') as $f) {
            $out[$f['code']] = ['value' => $f['default_value'], 'kind' => $f['kind'], 'source' => 'default'];
        }

        $sub = Db::one(
            'SELECT s.*, pv.id AS pv_id FROM subscriptions s
             JOIN package_versions pv ON pv.id = s.package_version_id
             WHERE s.account_id = ? AND s.status = ? ORDER BY s.id DESC',
            [$accountId, 'active']
        );
        if ($sub) {
            $rows = Db::all(
                'SELECT f.code, pf.value FROM package_features pf
                 JOIN features f ON f.id = pf.feature_id WHERE pf.package_version_id = ?',
                [$sub['pv_id']]
            );
            foreach ($rows as $r) {
                if (isset($out[$r['code']])) { $out[$r['code']]['value'] = $r['value']; $out[$r['code']]['source'] = 'package'; }
            }
        }

        foreach (Db::all(
            'SELECT f.code, o.value FROM entitlement_overrides o
             JOIN features f ON f.id = o.feature_id WHERE o.account_id = ?', [$accountId]) as $r) {
            if (isset($out[$r['code']])) { $out[$r['code']]['value'] = $r['value']; $out[$r['code']]['source'] = 'override'; }
        }

        return self::$cache[$accountId] = $out;
    }

    public static function can(int $accountId, string $code): bool
    {
        $e = self::forAccount($accountId)[$code] ?? null;
        return $e !== null && $e['value'] !== '0' && $e['value'] !== '';
    }

    public static function limit(int $accountId, string $code, int $default = 0): int
    {
        $e = self::forAccount($accountId)[$code] ?? null;
        if (!$e) return $default;
        return $e['value'] === 'unlimited' ? PHP_INT_MAX : (int) $e['value'];
    }

    public static function forget(int $accountId): void { unset(self::$cache[$accountId]); }

    /** Le lingue che questo account può davvero pubblicare. */
    public static function allowedLocales(int $accountId): array
    {
        $all = array_keys(Config::get('locales'));
        $n = self::limit($accountId, 'locales', 1);
        return $n >= count($all) ? $all : array_slice($all, 0, max(1, $n));
    }
}
