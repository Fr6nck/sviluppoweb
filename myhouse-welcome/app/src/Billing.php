<?php
namespace MHW;

final class Billing
{
    /** Un ordine pagato attiva l'abbonamento sulla versione di pacchetto comprata. */
    public static function markPaid(int $orderId, string $provider = 'manual', string $customerId = ''): void
    {
        Db::tx(function () use ($orderId, $provider, $customerId) {
            $o = Db::one('SELECT * FROM orders WHERE id = ?', [$orderId]);
            if (!$o || $o['status'] === 'paid') return;

            Db::update('orders', ['status' => 'paid'], 'id = :oid', ['oid' => $orderId]);

            Db::run('UPDATE subscriptions SET status = ? WHERE account_id = ? AND status = ?',
                    ['canceled', $o['account_id'], 'active']);

            Db::insert('subscriptions', [
                'account_id' => $o['account_id'],
                'package_version_id' => $o['package_version_id'],
                'status' => 'active', 'provider' => $provider,
                'provider_customer_id' => $customerId, 'provider_subscription_id' => '',
                'current_period_end' => gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 year')),
                'created_at' => Support::now(),
            ]);

            Db::run('UPDATE package_versions SET sold_count = sold_count + 1 WHERE id = ?',
                    [$o['package_version_id']]);

            Entitlements::forget((int) $o['account_id']);
        });
    }
}
