<?php
namespace MHW;

/**
 * 9R · L'account staff: chi prova il sistema dall'interno. Niente pagamenti, niente fatture, niente Stripe: un abbonamento
 * con provider «staff» fino alla data scelta. Può fare tutto come un cliente (pubblicare, invitare, aderire al giudizio e
 * lasciarlo, finire in vetrina se approvato, traduzioni e campione); gli sconti che matura si registrano ma non si applicano.
 * Fuori da Cassa, Scadenze, Richiami di pagamento, commissioni partner, email «Fino al 55%» e statistiche dei clienti.
 * Diverso dal «cliente di prova» (is_test, 7F), che non entra in vetrina, e dal piano dato a mano a un cliente vero.
 */
final class Staff
{
    public static function disponibili(): bool { return Migrator::columnExists('accounts', 'is_staff'); }

    /** L'account è staff? Accetta la riga dell'account o il suo id. */
    public static function e(array|int $acc): bool
    {
        if (!self::disponibili()) return false;
        if (is_int($acc)) return (int) Db::val('SELECT is_staff FROM accounts WHERE id = ?', [$acc], 0) === 1;
        if (array_key_exists('is_staff', $acc)) return (int) $acc['is_staff'] === 1;
        return self::e((int) ($acc['id'] ?? 0));
    }

    /** Il pezzo di SQL che toglie gli account staff (per i conti e le statistiche): « AND alias.is_staff = 0». */
    public static function senza(string $alias = 'a'): string
    {
        return self::disponibili() ? " AND $alias.is_staff = 0" : '';
    }

    /**
     * «Attiva come staff»: l'abbonamento senza pagamento (provider «staff») con il piano, le strutture e la data di fine;
     * il flag sull'account. Nessun cliente né sessione su Stripe (un cliente Stripe che c'è già resta inerte).
     */
    public static function attiva(int $accountId, int $packageVersionId, int $quantita, string $fino, bool $vetrina): int
    {
        $pv = Plans::version($packageVersionId) ?? [];
        if (!$pv) throw new \RuntimeException('Piano sconosciuto.');
        $quantita = Plans::perProperty($pv) ? (Plans::quantity($pv, $quantita) ?? max(1, (int) ($pv['min_quantity'] ?? 1))) : 1;
        $fineTs = strtotime($fino . 'T23:59:59Z');
        if (!$fineTs || $fineTs <= time()) throw new \RuntimeException('La data di fine deve essere nel futuro.');
        $fineIso = gmdate('Y-m-d\TH:i:s\Z', $fineTs);
        return Db::tx(function () use ($accountId, $packageVersionId, $quantita, $fineIso, $vetrina) {
            Db::run('UPDATE subscriptions SET status = ?, updated_at = ? WHERE account_id = ? AND status IN (?, ?)', ['replaced', Support::now(), $accountId, 'active', 'trialing']);
            $id = Db::insert('subscriptions', [
                'account_id' => $accountId, 'package_version_id' => $packageVersionId, 'status' => 'active', 'provider' => 'staff',
                'current_period_start' => Support::now(), 'current_period_end' => $fineIso, 'payment_status' => 'staff',
                'created_at' => Support::now(), 'updated_at' => Support::now(), 'quantity' => $quantita,
            ]);
            Db::update('accounts', ['is_staff' => 1, 'staff_fino' => $fineIso, 'staff_no_vetrina' => $vetrina ? 0 : 1,
                                    'intended_package_version_id' => $packageVersionId, 'intended_quantity' => $quantita], 'id = :aid', ['aid' => $accountId]);
            Auth::audit('account.staff', (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$accountId], 0) ?: null,
                        ['package_version_id' => $packageVersionId, 'quantita' => $quantita, 'fino' => $fineIso, 'vetrina' => $vetrina]);
            Entitlements::forget($accountId);
            return $id;
        });
    }

    /** «Togli lo staff»: l'abbonamento staff finisce adesso, l'account torna un cliente normale. */
    public static function togli(int $accountId): void
    {
        Db::tx(function () use ($accountId) {
            Db::run("UPDATE subscriptions SET status = 'canceled', current_period_end = ?, updated_at = ? WHERE account_id = ? AND provider = 'staff' AND status = 'active'",
                    [Support::now(), Support::now(), $accountId]);
            Db::update('accounts', ['is_staff' => 0, 'staff_fino' => null, 'staff_no_vetrina' => 0], 'id = :aid', ['aid' => $accountId]);
            Auth::audit('account.staff_tolto', (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$accountId], 0) ?: null, []);
            Entitlements::forget($accountId);
        });
    }
}
