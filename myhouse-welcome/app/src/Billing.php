<?php
namespace MHW;

/**
 * Gli abbonamenti e gli eventi di Stripe.
 *
 * Idempotenza: l'evento si segna come elaborato DENTRO la stessa transazione
 * che lo applica. Se qualcosa fallisce a metà, la transazione torna indietro
 * — segno compreso — e Stripe lo rimanda. Se arriva due volte, l'indice unico
 * su (provider, provider_event_id) ferma il secondo, anche se i due arrivi
 * sono contemporanei.
 */
final class Billing
{
    private static function iso(?int $ts): string { return $ts ? gmdate('Y-m-d\TH:i:s\Z', $ts) : ''; }

    /** Le date del periodo: nelle API recenti stanno sulle voci, in quelle vecchie sull'abbonamento. */
    private static function period(array $sub): array
    {
        $item = $sub['items']['data'][0] ?? [];
        return [
            self::iso($sub['current_period_start'] ?? $item['current_period_start'] ?? null),
            self::iso($sub['current_period_end'] ?? $item['current_period_end'] ?? null),
        ];
    }

    /**
     * Portfolio: quante strutture paga l'abbonamento, e quale voce le conta.
     * La voce "struttura aggiuntiva" si riconosce dall'id già noto, dal Price
     * ID configurato o dal prodotto (metadato ruolo=aggiuntiva).
     *
     * @return array{0:?int,1:string} [quantità o null se non ricavabile, id della voce]
     */
    private static function quantityFrom(array $sub, array $pv, string $itemNoto = ''): array
    {
        $voci = $sub['items']['data'] ?? null;
        if (!is_array($voci) || !$voci) return [null, $itemNoto];
        foreach ($voci as $v) {
            $prezzo = $v['price'] ?? [];
            $prodotto = is_array($prezzo['product'] ?? null) ? $prezzo['product'] : [];
            $aggiuntiva = ($itemNoto !== '' && ($v['id'] ?? '') === $itemNoto)
                || (($pv['stripe_extra_price_id'] ?? '') !== '' && ($prezzo['id'] ?? '') === $pv['stripe_extra_price_id'])
                || (($prodotto['metadata']['ruolo'] ?? '') === 'aggiuntiva');
            if ($aggiuntiva) return [1 + max(0, (int) ($v['quantity'] ?? 0)), (string) ($v['id'] ?? '')];
        }
        // Nessuna voce riconosciuta: meglio la quantità dell'ordine (o quella già
        // registrata) che toglierne qualcuna per un dato che non si sa leggere.
        return [null, $itemNoto];
    }

    /** @return string un esito leggibile, per il registro */
    public static function handleEvent(array $event): string
    {
        $id = (string) ($event['id'] ?? '');
        $type = (string) ($event['type'] ?? '');
        if ($id === '' || $type === '') return 'evento-incompleto';
        if (Db::one('SELECT id FROM webhook_events WHERE provider = ? AND provider_event_id = ?', ['stripe', $id])) return 'gia-elaborato';

        $obj = $event['data']['object'] ?? [];

        // Le chiamate di rete si fanno PRIMA della transazione: non si tiene
        // bloccato il database mentre si aspetta Stripe.
        $sub = null;
        if ($type === 'checkout.session.completed' && !empty($obj['subscription']) && is_string($obj['subscription'])) {
            $sub = Stripe::retrieveSubscription($obj['subscription']);
        }

        try {
            return Db::tx(function () use ($id, $type, $obj, $event, $sub) {
                Db::insert('webhook_events', [
                    'provider' => 'stripe', 'provider_event_id' => $id, 'kind' => $type,
                    'payload' => json_encode($event, JSON_UNESCAPED_UNICODE), 'processed_at' => Support::now(),
                ]);
                return match ($type) {
                    'checkout.session.completed' => self::onCheckoutCompleted($obj, $sub),
                    'checkout.session.expired' => self::onCheckoutExpired($obj),
                    'invoice.paid', 'invoice.payment_succeeded' => self::onInvoicePaid($obj),
                    'invoice.payment_failed' => self::onInvoiceFailed($obj),
                    'customer.subscription.created', 'customer.subscription.updated' => self::onSubscriptionChanged($obj),
                    'customer.subscription.deleted' => self::onSubscriptionDeleted($obj),
                    default => 'ignorato',
                };
            });
        } catch (\PDOException $e) {
            // Lo stesso evento arrivato in parallelo: l'indice unico ha fermato il secondo.
            if (Db::one('SELECT id FROM webhook_events WHERE provider = ? AND provider_event_id = ?', ['stripe', $id])) return 'gia-elaborato';
            throw $e;
        }
    }

    private static function onCheckoutCompleted(array $s, ?array $sub): string
    {
        if (($s['mode'] ?? '') !== 'subscription') return 'non-abbonamento';
        $orderId = (int) ($s['metadata']['order_id'] ?? $s['client_reference_id'] ?? 0);
        $o = Db::one('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if (!$o) { Log::error('Webhook: ordine inesistente', ['order' => $orderId]); return 'ordine-sconosciuto'; }
        if ((string) ($s['metadata']['account_id'] ?? $o['account_id']) !== (string) $o['account_id']) {
            Log::error('Webhook: account non coerente con l\'ordine', ['order' => $orderId]);
            return 'incoerente';
        }
        // Pagamenti differiti (bonifico): si aspetta invoice.paid.
        if (!in_array($s['payment_status'] ?? '', ['paid', 'no_payment_required'], true)) {
            Db::update('orders', ['status' => 'awaiting', 'updated_at' => Support::now()], 'id = :oid', ['oid' => $orderId]);
            return 'in-attesa-del-pagamento';
        }
        self::activate($o, $sub ?? ['id' => $s['subscription'] ?? '', 'status' => 'active'], (string) ($s['customer'] ?? ''));
        return 'abbonamento-attivato';
    }

    /** Ordine pagato → abbonamento attivo → guida pubblicata. Tutto o niente. */
    public static function activate(array $order, array $sub, string $customerId): void
    {
        if ($order['status'] === 'paid') return;
        $acc = (int) $order['account_id'];
        [$inizio, $fine] = self::period($sub);
        $price = (string) ($sub['items']['data'][0]['price']['id'] ?? '');

        Db::update('orders', ['status' => 'paid', 'updated_at' => Support::now()], 'id = :oid', ['oid' => $order['id']]);
        // Un piano nuovo sostituisce il precedente.
        Db::run('UPDATE subscriptions SET status = ?, updated_at = ? WHERE account_id = ? AND status IN (?, ?)',
                ['replaced', Support::now(), $acc, 'active', 'trialing']);

        $pv = Db::one('SELECT * FROM package_versions WHERE id = ?', [$order['package_version_id']]) ?: [];
        $quantita = 1; $voceExtra = '';
        if (Plans::perProperty($pv)) {
            [$daStripe, $voceExtra] = self::quantityFrom($sub, $pv);
            // Vale quello che Stripe dice di aver fatto pagare; l'ordine è la riserva.
            $quantita = $daStripe ?? max(1, (int) ($order['quantity'] ?? 1));
        }

        $esistente = ($sub['id'] ?? '') !== '' ? Db::one('SELECT id FROM subscriptions WHERE provider_subscription_id = ?', [$sub['id']]) : null;
        $riga = [
            'account_id' => $acc, 'package_version_id' => $order['package_version_id'],
            'status' => in_array($sub['status'] ?? 'active', ['active', 'trialing'], true) ? $sub['status'] : 'active',
            'provider' => 'stripe', 'provider_customer_id' => $customerId,
            'provider_subscription_id' => (string) ($sub['id'] ?? ''), 'provider_price_id' => $price,
            'current_period_start' => $inizio ?: Support::now(),
            'current_period_end' => $fine ?: gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 year')),
            'cancel_at_period_end' => !empty($sub['cancel_at_period_end']) ? 1 : 0,
            'payment_status' => 'paid', 'updated_at' => Support::now(),
            'quantity' => $quantita, 'provider_extra_item_id' => $voceExtra,
        ];
        if ($esistente) Db::update('subscriptions', $riga, 'id = :sid', ['sid' => $esistente['id']]);
        else Db::insert('subscriptions', $riga + ['created_at' => Support::now()]);

        Db::update('accounts', ['intended_package_version_id' => $order['package_version_id'], 'intended_quantity' => $quantita]
                   + ($customerId !== '' ? ['stripe_customer_id' => $customerId] : []), 'id = :aid', ['aid' => $acc]);
        Db::run('UPDATE package_versions SET sold_count = sold_count + 1 WHERE id = ?', [$order['package_version_id']]);
        Entitlements::forget($acc);

        // La guida che aspettava il pagamento va online adesso.
        if (!empty($order['property_id'])) {
            $p = Db::one('SELECT id FROM properties WHERE id = ? AND account_id = ?', [$order['property_id'], $acc]);
            if ($p) Guide::publish((int) $p['id']);
        }
    }

    private static function onCheckoutExpired(array $s): string
    {
        $orderId = (int) ($s['metadata']['order_id'] ?? $s['client_reference_id'] ?? 0);
        Db::run("UPDATE orders SET status = 'expired', updated_at = ? WHERE id = ? AND status = 'pending'", [Support::now(), $orderId]);
        return 'checkout-scaduto';
    }

    private static function subscriptionId(array $invoice): string
    {
        // Le API recenti spostano il riferimento dentro parent.subscription_details.
        return (string) ($invoice['subscription'] ?? $invoice['parent']['subscription_details']['subscription'] ?? '');
    }

    private static function onInvoicePaid(array $inv): string
    {
        $sid = self::subscriptionId($inv);
        $row = $sid !== '' ? Db::one('SELECT * FROM subscriptions WHERE provider_subscription_id = ?', [$sid]) : null;
        if (!$row) {
            // La fattura può arrivare prima del checkout completato: sarà lui a creare l'abbonamento.
            // Un ordine rimasto "in attesa" (pagamento differito) si attiva qui.
            $meta = $inv['parent']['subscription_details']['metadata'] ?? $inv['subscription_details']['metadata'] ?? [];
            $o = !empty($meta['order_id']) ? Db::one("SELECT * FROM orders WHERE id = ? AND status = 'awaiting'", [(int) $meta['order_id']]) : null;
            if ($o) {
                $linea = $inv['lines']['data'][0] ?? [];
                self::activate($o, ['id' => $sid, 'status' => 'active', 'items' => ['data' => [[
                    'current_period_start' => $linea['period']['start'] ?? null, 'current_period_end' => $linea['period']['end'] ?? null,
                    'price' => ['id' => $linea['price']['id'] ?? ($linea['pricing']['price_details']['price'] ?? '')]]]]],
                    (string) ($inv['customer'] ?? ''));
                return 'abbonamento-attivato-da-fattura';
            }
            return 'abbonamento-non-ancora-noto';
        }
        $linea = $inv['lines']['data'][0] ?? [];
        Db::update('subscriptions', [
            'status' => 'active', 'payment_status' => 'paid',
            'current_period_start' => self::iso($linea['period']['start'] ?? null) ?: $row['current_period_start'],
            'current_period_end' => self::iso($linea['period']['end'] ?? null) ?: $row['current_period_end'],
            'updated_at' => Support::now(),
        ], 'id = :sid', ['sid' => $row['id']]);
        Entitlements::forget((int) $row['account_id']);
        return 'rinnovo-pagato';
    }

    private static function onInvoiceFailed(array $inv): string
    {
        $sid = self::subscriptionId($inv);
        $row = $sid !== '' ? Db::one('SELECT * FROM subscriptions WHERE provider_subscription_id = ?', [$sid]) : null;
        if (!$row) return 'abbonamento-non-ancora-noto';
        Db::update('subscriptions', ['payment_status' => 'failed', 'status' => 'past_due', 'updated_at' => Support::now()],
                   'id = :sid', ['sid' => $row['id']]);
        Entitlements::forget((int) $row['account_id']);
        return 'pagamento-fallito';
    }

    private static function onSubscriptionChanged(array $sub): string
    {
        $row = Db::one('SELECT * FROM subscriptions WHERE provider_subscription_id = ?', [(string) ($sub['id'] ?? '')]);
        if (!$row) return 'abbonamento-non-ancora-noto';
        [$inizio, $fine] = self::period($sub);
        $pv = Db::one('SELECT * FROM package_versions WHERE id = ?', [$row['package_version_id']]) ?: [];
        $quantita = (int) $row['quantity']; $voce = (string) $row['provider_extra_item_id'];
        if (Plans::perProperty($pv)) {
            [$daStripe, $voce] = self::quantityFrom($sub, $pv, $voce);
            if ($daStripe !== null) $quantita = $daStripe;
        }
        Db::update('subscriptions', [
            'quantity' => $quantita, 'provider_extra_item_id' => $voce,
            'status' => (string) ($sub['status'] ?? $row['status']),
            'cancel_at_period_end' => !empty($sub['cancel_at_period_end']) ? 1 : 0,
            'current_period_start' => $inizio ?: $row['current_period_start'],
            'current_period_end' => $fine ?: $row['current_period_end'],
            'provider_price_id' => (string) ($sub['items']['data'][0]['price']['id'] ?? $row['provider_price_id']),
            'updated_at' => Support::now(),
        ], 'id = :sid', ['sid' => $row['id']]);
        Entitlements::forget((int) $row['account_id']);
        return 'abbonamento-aggiornato';
    }

    private static function onSubscriptionDeleted(array $sub): string
    {
        $row = Db::one('SELECT * FROM subscriptions WHERE provider_subscription_id = ?', [(string) ($sub['id'] ?? '')]);
        if (!$row) return 'abbonamento-non-ancora-noto';
        // La guida non si cancella e i dati restano: va solo offline, perché
        // Subscriptions::valid non la considera più pagata.
        Db::update('subscriptions', ['status' => 'canceled', 'updated_at' => Support::now()], 'id = :sid', ['sid' => $row['id']]);
        Entitlements::forget((int) $row['account_id']);
        return 'abbonamento-chiuso';
    }

    /**
     * Un abbonamento concesso a mano dall'amministratore: per demo, omaggi,
     * pagamenti arrivati per altre vie. Resta scritto nel registro.
     */
    public static function grantManual(int $accountId, int $packageVersionId, int $months, string $note, int $quantity = 1): int
    {
        $pv = Db::one('SELECT * FROM package_versions WHERE id = ?', [$packageVersionId]) ?: [];
        $quantity = Plans::quantity($pv, $quantity) ?? (int) ($pv['min_quantity'] ?? 1);
        return Db::tx(function () use ($accountId, $packageVersionId, $months, $note, $quantity) {
            Db::run('UPDATE subscriptions SET status = ?, updated_at = ? WHERE account_id = ? AND status IN (?, ?)',
                    ['replaced', Support::now(), $accountId, 'active', 'trialing']);
            $id = Db::insert('subscriptions', [
                'account_id' => $accountId, 'package_version_id' => $packageVersionId, 'status' => 'active',
                'provider' => 'manuale', 'current_period_start' => Support::now(),
                'current_period_end' => gmdate('Y-m-d\TH:i:s\Z', strtotime('+' . max(1, $months) . ' months')),
                'payment_status' => 'manuale', 'created_at' => Support::now(), 'updated_at' => Support::now(),
                'quantity' => $quantity,
            ]);
            Db::update('accounts', ['intended_package_version_id' => $packageVersionId, 'intended_quantity' => $quantity], 'id = :aid', ['aid' => $accountId]);
            $uid = (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$accountId]);
            Auth::audit('subscription.manual', $uid, ['package_version_id' => $packageVersionId, 'months' => $months, 'note' => $note, 'quantity' => $quantity]);
            Entitlements::forget($accountId);
            return $id;
        });
    }
}
