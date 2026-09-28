<?php
namespace MHW;

/**
 * Quando un abbonamento vale. Una regola sola, usata da tutti: dai diritti,
 * dalla pubblicazione e dalla guida che vedono gli ospiti.
 *
 * Vale se Stripe lo dà per attivo E il periodo pagato non è finito. La data
 * conta anche da sola: se un webhook di cancellazione si perde per strada,
 * alla scadenza la guida va offline lo stesso. Nessun cron necessario.
 */
final class Subscriptions
{
    /** Gli stati di Stripe in cui il servizio è pagato. */
    private const PAGATI = ['active', 'trialing'];

    public static function active(int $accountId): ?array
    {
        $grazia = max(0, (int) (Config::get('billing')['grace_days'] ?? 0));
        foreach (Db::all('SELECT * FROM subscriptions WHERE account_id = ? ORDER BY id DESC', [$accountId]) as $s) {
            if (self::valid($s, $grazia)) return $s;
        }
        return null;
    }

    public static function valid(array $s, int $graziaGiorni = 0): bool
    {
        $fine = (string) $s['current_period_end'];
        $inPeriodo = $fine === '' || strtotime($fine) > time();
        if (in_array($s['status'], self::PAGATI, true)) return $inPeriodo;
        // Un rinnovo non riuscito: Stripe ritenta. Si resta online solo per i
        // giorni di tolleranza configurati (zero, se nessuno li ha decisi).
        if ($s['status'] === 'past_due' && $graziaGiorni > 0) {
            $inizio = strtotime((string) ($s['current_period_start'] ?: $s['updated_at'] ?: $s['created_at']));
            return $inizio && time() < $inizio + $graziaGiorni * 86400;
        }
        return false;
    }

    /** Il più recente, valido o no: per mostrare "scaduto il…". */
    public static function latest(int $accountId): ?array
    {
        return Db::one(
            'SELECT s.*, pv.version, pv.price_cents, pv.currency, pk.name AS package_name, pk.code AS package_code
             FROM subscriptions s JOIN package_versions pv ON pv.id = s.package_version_id
             JOIN packages pk ON pk.id = pv.package_id
             WHERE s.account_id = ? ORDER BY s.id DESC', [$accountId]);
    }

    /** Una guida è visibile agli ospiti se è pubblicata e qualcuno la sta pagando. */
    public static function propertyOnline(array $property): bool
    {
        if ($property['status'] !== 'published') return false;
        if ((int) ($property['is_demo'] ?? 0) === 1) return true;
        return self::active((int) $property['account_id']) !== null;
    }

    /** La versione che governa l'account: quella pagata, altrimenti quella scelta. */
    public static function governingVersionId(int $accountId): ?int
    {
        $s = self::active($accountId);
        if ($s) return (int) $s['package_version_id'];
        $scelta = Db::val('SELECT intended_package_version_id FROM accounts WHERE id = ?', [$accountId]);
        return $scelta ? (int) $scelta : null;
    }
}
