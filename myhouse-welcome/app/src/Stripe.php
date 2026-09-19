<?php
namespace MHW;

/**
 * Integrazione Stripe Checkout, scritta con curl: nessuna libreria da installare.
 *
 * Senza chiavi configurate l'applicazione resta usabile in "modalità prova":
 * l'ordine viene creato e confermato localmente, e ogni schermata lo dichiara.
 * Con le chiavi vere il flusso passa da Stripe e l'unica fonte di verità
 * diventa il webhook firmato, mai il ritorno dal browser.
 */
final class Stripe
{
    public static function enabled(): bool { return Config::stripeReady(); }

    private static function call(string $method, string $path, array $params = []): array
    {
        $key = Config::get('stripe')['secret_key'];
        $ch = curl_init('https://api.stripe.com/v1/' . ltrim($path, '/'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $key . ':',
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);
        if ($params) curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($body === false) throw new \RuntimeException('Stripe non raggiungibile: ' . $err);
        $json = json_decode((string) $body, true) ?: [];
        if ($code >= 400) throw new \RuntimeException('Stripe: ' . ($json['error']['message'] ?? 'errore sconosciuto'));
        return $json;
    }

    /** Crea la sessione di pagamento e restituisce l'URL a cui mandare il cliente. */
    public static function checkout(array $order, array $pv, array $pkg, string $email): string
    {
        $base = Support::baseUrl();
        $res = self::call('POST', 'checkout/sessions', [
            'mode' => 'payment',
            'customer_email' => $email,
            'client_reference_id' => (string) $order['id'],
            'success_url' => $base . '/pagamento/ok?order=' . $order['id'],
            'cancel_url'  => $base . '/pagamento/annullato?order=' . $order['id'],
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => strtolower($order['currency']),
            'line_items[0][price_data][unit_amount]' => (string) $order['amount_cents'],
            'line_items[0][price_data][product_data][name]' => 'MyHouse Welcome — ' . $pkg['name'],
            'metadata[order_id]' => (string) $order['id'],
            'metadata[package_version_id]' => (string) $pv['id'],
        ]);
        Db::update('orders', ['provider_session_id' => $res['id']], 'id = :oid', ['oid' => $order['id']]);
        return $res['url'];
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
        if (!$t || !$sigs) return false;
        if (abs(time() - (int) $t) > $tolerance) return false;
        $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
        foreach ($sigs as $s) if (hash_equals($expected, $s)) return true;
        return false;
    }

    /** Applica l'evento una volta sola: il secondo arrivo non fa niente. */
    public static function handleEvent(array $event): string
    {
        $id = (string) ($event['id'] ?? '');
        $kind = (string) ($event['type'] ?? '');
        if ($id === '') return 'evento senza identificativo';

        if (Db::one('SELECT id FROM webhook_events WHERE provider = ? AND provider_event_id = ?', ['stripe', $id])) {
            return 'gia-elaborato';
        }
        Db::insert('webhook_events', [
            'provider' => 'stripe', 'provider_event_id' => $id, 'kind' => $kind,
            'payload' => json_encode($event, JSON_UNESCAPED_UNICODE), 'processed_at' => Support::now(),
        ]);

        if ($kind === 'checkout.session.completed') {
            $obj = $event['data']['object'] ?? [];
            $orderId = (int) ($obj['metadata']['order_id'] ?? $obj['client_reference_id'] ?? 0);
            if ($orderId) { Billing::markPaid($orderId, 'stripe', (string) ($obj['customer'] ?? '')); return 'ordine-pagato'; }
        }
        return 'ignorato';
    }
}
