<?php
namespace MHW;

/**
 * Cosa può fare un account. L'ordine di risoluzione è sempre lo stesso:
 *
 *   eccezione manuale → versione di pacchetto che governa l'account → predefinito
 *
 * La versione che governa è quella dell'abbonamento valido; prima di pagare è
 * quella del piano SCELTO, così la configurazione segue già le regole del piano
 * che si comprerà. Nessuno perde una funzione già comprata: si legge la
 * VERSIONE a cui è agganciato l'abbonamento, non il pacchetto di oggi.
 *
 * Ogni limite si verifica QUI, lato server. L'interfaccia li mostra soltanto.
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

        $pv = Subscriptions::governingVersionId($accountId);
        if ($pv) {
            $sorgente = Subscriptions::active($accountId) ? 'package' : 'intended';
            foreach (Db::all(
                'SELECT f.code, pf.value FROM package_features pf
                 JOIN features f ON f.id = pf.feature_id WHERE pf.package_version_id = ?', [$pv]) as $r) {
                if (isset($out[$r['code']])) $out[$r['code']] = ['value' => $r['value'], 'kind' => $out[$r['code']]['kind'], 'source' => $sorgente];
            }
            // Portfolio a quantità: le strutture sono esattamente quelle comprate
            // (o, prima di pagare, quelle scelte).
            $versione = Db::one('SELECT * FROM package_versions WHERE id = ?', [$pv]);
            if ($versione && Plans::perProperty($versione) && isset($out['properties'])) {
                $attivo = Subscriptions::active($accountId);
                $q = $attivo ? (int) $attivo['quantity']
                             : (int) Db::val('SELECT intended_quantity FROM accounts WHERE id = ?', [$accountId], 1);
                $out['properties']['value'] = (string) (Plans::quantity($versione, $q) ?? (int) $versione['min_quantity']);
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

    public static function forget(?int $accountId = null): void
    {
        if ($accountId === null) self::$cache = [];
        else unset(self::$cache[$accountId]);
    }

    /**
     * Le lingue che questo account può davvero pubblicare, nell'ordine del
     * listino: italiano, inglese, francese, tedesco, spagnolo. Essential ne ha
     * due — italiano e inglese — gli altri piani cinque.
     */
    public static function allowedLocales(int $accountId): array
    {
        $all = array_keys(Config::get('locales'));
        $n = self::limit($accountId, 'locales', 1);
        return $n >= count($all) ? $all : array_slice($all, 0, max(1, $n));
    }

    /**
     * Quello che nella configurazione di una struttura supera il piano che la
     * governa. Non si cancella niente: si elenca, e la pubblicazione aspetta.
     *
     * @return array<int,string> frasi pronte da mostrare, vuoto se è tutto in regola
     */
    /**
     * Le strutture bloccate: esistono (col nome) ma non si modificano finché non sono pagate.
     *
     *   - Portfolio scelto e mai pagato: si configura UNA struttura, la prima; le altre,
     *     fino alla quantità scelta, aspettano il pagamento.
     *   - Abbonamento attivo: quelle oltre la quantità pagata (una struttura appena
     *     aggiunta aspetta che il webhook di Stripe confermi la quantità nuova).
     *   - Chi ha già pagato in passato e ora è scaduto non viene bloccato: le guide
     *     sono offline, ma i contenuti restano suoi da sistemare.
     *
     * Le archiviate non contano. L'ordine è quello di creazione.
     * @return int[] gli id bloccati
     */
    public static function lockedIds(int $accountId): array
    {
        $ids = array_map('intval', array_column(Db::all(
            'SELECT id FROM properties WHERE account_id = ? AND archived_at IS NULL AND is_demo < 2 ORDER BY id', [$accountId]), 'id'));   // la vetrina non si blocca mai
        if (count($ids) < 2) return [];
        if (Subscriptions::active($accountId)) return array_slice($ids, max(1, self::limit($accountId, 'properties', 1)));
        $pv = Subscriptions::governingVersionId($accountId);
        $versione = $pv ? Db::one('SELECT per_property FROM package_versions WHERE id = ?', [$pv]) : null;
        if (!$versione || !Plans::perProperty($versione)) return [];
        if (Subscriptions::latest($accountId)) return [];
        return array_slice($ids, 1);
    }

    public static function editable(int $accountId, int $propertyId): bool
    {
        return !in_array($propertyId, self::lockedIds($accountId), true);
    }

    /** Quante foto (o quanti PDF) ci sono dentro le righe delle sezioni attive. */
    private static function mediaNelleRighe(int $propertyId, string $kind): int
    {
        $ids = [];
        foreach (Db::all('SELECT kind, data FROM sections WHERE property_id = ? AND is_active = 1', [$propertyId]) as $s) {
            $ids = array_merge($ids, SectionCatalog::mediaIds($s['kind'], json_decode((string) $s['data'], true) ?: []));
        }
        if (!$ids) return 0;
        $ids = array_map('intval', array_unique($ids));
        return (int) Db::val('SELECT COUNT(*) FROM media WHERE kind = ? AND id IN (' . implode(',', $ids) . ')', [$kind], 0);
    }

    public static function violations(int $accountId, int $propertyId): array
    {
        $fuori = [];
        $maxSez = self::limit($accountId, 'sections', 4);
        $attive = (int) Db::val('SELECT COUNT(*) FROM sections WHERE property_id = ? AND is_core = 0 AND is_active = 1', [$propertyId], 0);
        if ($attive > $maxSez) {
            $fuori[] = "Hai $attive sezioni attive, il piano ne comprende $maxSez. Disattivane " . ($attive - $maxSez) . '.';
        }

        $consentite = self::allowedLocales($accountId);
        $lingue = array_column(Db::all('SELECT locale FROM property_locales WHERE property_id = ?', [$propertyId]), 'locale');
        $troppe = array_diff($lingue, $consentite);
        if ($troppe) {
            $nomi = array_map(fn($l) => Config::get('locales')[$l] ?? $l, $troppe);
            $fuori[] = 'Il piano non comprende: ' . implode(', ', $nomi) . '. Togli queste lingue da quelle della guida.';
        }

        if (!self::can($accountId, 'photos')) {
            $img = (int) Db::val('SELECT COUNT(*) FROM sections WHERE property_id = ? AND is_active = 1 AND media_id IS NOT NULL', [$propertyId], 0)
                 + (int) Db::val('SELECT COUNT(*) FROM places pl JOIN sections s ON s.id = pl.section_id
                                  WHERE s.property_id = ? AND s.is_active = 1 AND pl.media_id IS NOT NULL', [$propertyId], 0);
            $img += self::mediaNelleRighe($propertyId, 'image');
            if ($img) $fuori[] = ($img === 1 ? "C'è 1 foto nelle sezioni: il piano non la comprende. Toglila" : "Ci sono $img foto nelle sezioni: il piano non le comprende. Toglile") . ', oppure passa a Plus.';
        }
        if (!self::can($accountId, 'pdf')) {
            $pdf = (int) Db::val('SELECT COUNT(*) FROM sections WHERE property_id = ? AND is_active = 1 AND pdf_media_id IS NOT NULL', [$propertyId], 0)
                 + self::mediaNelleRighe($propertyId, 'pdf');
            if ($pdf) $fuori[] = ($pdf === 1 ? "C'è 1 PDF nelle sezioni: il piano non lo comprende. Toglilo" : "Ci sono $pdf PDF nelle sezioni: il piano non li comprende. Toglili") . ', oppure passa a Plus.';
        }
        if (!self::can($accountId, 'profile_image')) {
            if (Db::val('SELECT profile_media_id FROM properties WHERE id = ?', [$propertyId])) {
                $fuori[] = "La foto profilo non è compresa nel piano. Toglila, oppure passa a Plus.";
            }
        }
        $maxProp = self::limit($accountId, 'properties', 1);
        $strutture = (int) Db::val('SELECT COUNT(*) FROM properties WHERE account_id = ? AND archived_at IS NULL AND is_demo < 2', [$accountId], 0);
        if ($strutture > $maxProp) {
            $fuori[] = "Hai $strutture strutture, il piano ne comprende $maxProp. Scegli Portfolio, oppure eliminane una.";
        }
        return $fuori;
    }
}
