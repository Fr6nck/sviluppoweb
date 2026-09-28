<?php
namespace MHW;

/**
 * Stripe, scritto con curl: nessuna libreria da installare.
 *
 * Abbonamenti annuali con rinnovo automatico (Checkout in modalità
 * subscription). I prezzi sono IVA esclusa: il prezzo Stripe ha
 * tax_behavior=exclusive e l'imposta si aggiunge al checkout.
 * Il ritorno dal browser non attiva niente: l'unica fonte di verità è il
 * webhook firmato.
 */
final class Stripe
{
    /** Senza chiave segreta E segreto del webhook non si vende: un pagamento non arriverebbe mai. */
    public static function enabled(): bool
    {
        $s = Config::get('stripe');
        return str_starts_with((string) $s['secret_key'], 'sk_') && (string) $s['webhook_secret'] !== '';
    }

    /** @throws \RuntimeException con un messaggio tecnico: va nei log, non all'utente */
    public static function call(string $method, string $path, array $params = [], string $idempotencyKey = ''): array
    {
        $s = Config::get('stripe');
        $url = rtrim((string) $s['api_base'], '/') . '/v1/' . ltrim($path, '/');
        if ($method === 'GET' && $params) { $url .= '?' . http_build_query($params); $params = []; }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => $s['secret_key'] . ':', CURLOPT_TIMEOUT => 25,
            CURLOPT_CUSTOMREQUEST => $method,
            // Con una chiave stabile, la stessa richiesta ripetuta (doppio clic, due
            // schede aperte) non crea due volte lo stesso cliente o lo stesso checkout.
            CURLOPT_HTTPHEADER => $idempotencyKey !== '' ? ['Idempotency-Key: ' . $idempotencyKey] : [],
        ]);
        if ($params) curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) throw new \RuntimeException('Stripe non raggiungibile: ' . $err);
        $json = json_decode((string) $body, true) ?: [];
        if ($code >= 400) throw new \RuntimeException('Stripe ' . $code . ': ' . ($json['error']['message'] ?? 'errore sconosciuto'));
        return $json;
    }

    /** Il cliente Stripe dell'account, creato la prima volta e poi riusato. */
    public static function ensureCustomer(array $account, array $user): string
    {
        if (($account['stripe_customer_id'] ?? '') !== '') return $account['stripe_customer_id'];
        $c = self::call('POST', 'customers', [
            'email' => $user['email'], 'name' => $user['name'],
            'metadata[account_id]' => (string) $account['id'],
            'preferred_locales[0]' => 'it',
        ], 'mhw-customer-account-' . $account['id']);
        Db::update('accounts', ['stripe_customer_id' => $c['id']], 'id = :aid', ['aid' => $account['id']]);
        return $c['id'];
    }

    /** La sessione di Checkout per un abbonamento annuale. Restituisce l'URL di Stripe. */
    public static function checkoutSubscription(array $order, array $pv, array $pkg, array $account, array $user): string
    {
        $base = Support::baseUrl();
        $cfg = Config::get('stripe');
        $meta = [
            'order_id' => (string) $order['id'], 'account_id' => (string) $account['id'],
            'package_version_id' => (string) $pv['id'], 'property_id' => (string) ($order['property_id'] ?? ''),
        ];
        $p = [
            'mode' => 'subscription',
            'customer' => self::ensureCustomer($account, $user),
            'client_reference_id' => (string) $order['id'],
            'success_url' => $base . '/pagamento/ok?order=' . $order['id'],
            'cancel_url' => $base . '/pagamento/annullato?order=' . $order['id'],
            'locale' => 'it',
            'line_items[0][quantity]' => 1,
            // I dati di fatturazione: indirizzo sempre, partita IVA se chi paga è un'azienda.
            'billing_address_collection' => 'required',
            'tax_id_collection[enabled]' => 'true',
            'customer_update[address]' => 'auto',
            'customer_update[name]' => 'auto',
        ];
        if ($pv['stripe_price_id'] !== '') {
            $p['line_items[0][price]'] = $pv['stripe_price_id'];
        } else {
            // Nessun prezzo creato a mano su Stripe: lo si descrive qui, dalla
            // versione del listino. Il prezzo resta quello del database.
            $p['line_items[0][price_data][currency]'] = strtolower($pv['currency']);
            $p['line_items[0][price_data][unit_amount]'] = (string) $pv['price_cents'];
            $p['line_items[0][price_data][tax_behavior]'] = 'exclusive';
            $p['line_items[0][price_data][recurring][interval]'] = 'year';
            $p['line_items[0][price_data][product_data][name]'] = 'MyHouse Welcome ' . $pkg['name'];
            $p['line_items[0][price_data][product_data][metadata][package]'] = $pkg['code'];
        }
        if (!empty($cfg['automatic_tax'])) $p['automatic_tax[enabled]'] = 'true';
        foreach ($meta as $k => $v) { $p["metadata[$k]"] = $v; $p["subscription_data[metadata][$k]"] = $v; }

        $res = self::call('POST', 'checkout/sessions', $p, 'mhw-checkout-order-' . $order['id']);
        Db::update('orders', ['provider_session_id' => $res['id'], 'updated_at' => Support::now()], 'id = :oid', ['oid' => $order['id']]);
        return $res['url'];
    }

    public static function retrieveSubscription(string $id): array
    {
        return self::call('GET', 'subscriptions/' . rawurlencode($id));
    }

    /** Rinnovo automatico acceso o spento. Il servizio pagato resta fino alla fine del periodo. */
    public static function setCancelAtPeriodEnd(string $subscriptionId, bool $cancel): array
    {
        return self::call('POST', 'subscriptions/' . rawurlencode($subscriptionId), ['cancel_at_period_end' => $cancel ? 'true' : 'false']);
    }

    public static function portalUrl(string $customerId): string
    {
        $r = self::call('POST', 'billing_portal/sessions', ['customer' => $customerId, 'return_url' => Support::baseUrl() . '/account']);
        return $r['url'];
    }

    /**
     * Verifica la firma del webhook. Senza questo controllo chiunque potrebbe
     * spedirci un "pagamento riuscito" e regalarsi un abbonamento.
     */
    public static function verifySignature(string $payload, string $header, string $secret, int $tolerance = 300): bool
    {
        $parts = [];
        foreach (explode(',', $header) as $piece) {
            $kv = explode('=', trim($piece), 2);
            if (count($kv) === 2) $parts[$kv[0]][] = $kv[1];
        }
        $t = $parts['t'][0] ?? null;
        $sigs = $parts['v1'] ?? [];
        if (!$t || !ctype_digit($t) || !$sigs) return false;
        if (abs(time() - (int) $t) > $tolerance) return false;
        $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
        foreach ($sigs as $s) if (hash_equals($expected, $s)) return true;
        return false;
    }
}
