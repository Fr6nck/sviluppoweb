<?php
/**
 * Uno Stripe finto per le prove, da mettere dietro `php -S`: risponde alle
 * sole chiamate che l'applicazione fa, con la forma delle risposte vere, e
 * annota ogni richiesta in un file perché la prova possa controllarla.
 *
 *   STRIPE_FINTO_DIR   cartella dove tenere richieste e stato
 *
 * NON è un simulatore completo: serve a verificare cosa l'applicazione MANDA
 * (modalità abbonamento, rinnovo annuale, IVA esclusa, chiavi di idempotenza)
 * e come reagisce alle risposte. La prova vera va fatta anche in modalità test
 * di Stripe, con le chiavi del cliente.
 */
$dir = getenv('STRIPE_FINTO_DIR') ?: sys_get_temp_dir() . '/stripe-finto';
@mkdir($dir, 0777, true);
$statoFile = $dir . '/stato.json';
$stato = is_file($statoFile) ? json_decode((string) file_get_contents($statoFile), true) : ['n' => 0, 'idem' => [], 'subs' => []];

$metodo = $_SERVER['REQUEST_METHOD'];
$percorso = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
parse_str((string) file_get_contents('php://input'), $corpo);
$chiave = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '';
$auth = $_SERVER['PHP_AUTH_USER'] ?? '';

file_put_contents($dir . '/richieste.jsonl', json_encode([
    'metodo' => $metodo, 'percorso' => $percorso, 'corpo' => $corpo, 'idem' => $chiave, 'auth' => $auth,
]) . "\n", FILE_APPEND);

header('Content-Type: application/json');
$rispondi = function (array $r, int $code = 200) use (&$stato, $statoFile, $chiave) {
    if ($chiave !== '') $stato['idem'][$chiave] = $r;
    file_put_contents($statoFile, json_encode($stato));
    http_response_code($code);
    echo json_encode($r);
    exit;
};
if (!str_starts_with($auth, 'sk_test_')) $rispondi(['error' => ['message' => 'Invalid API Key provided']], 401);
if ($chiave !== '' && isset($stato['idem'][$chiave])) { echo json_encode($stato['idem'][$chiave]); exit; }
if (getenv('STRIPE_FINTO_GUASTO') === '1' || is_file($dir . '/guasto')) $rispondi(['error' => ['message' => 'Simulated outage']], 500);

$stato['n']++;
$n = $stato['n'];
$ora = time();
// Portfolio: la prova scrive "extra-<sub>" con le strutture aggiuntive pagate al checkout;
// l'abbonamento avrà allora una seconda voce, come quello vero.
$espandi = in_array('items.data.price.product', (array) ($_GET['expand'] ?? []), true);
$abbonamento = function (string $id) use (&$stato, $ora, $dir, $espandi): array {
    $s = $stato['subs'][$id] ?? ['cancel_at_period_end' => false, 'status' => 'active', 'start' => $ora, 'end' => $ora + 365 * 86400,
                                 'extra' => is_file("$dir/extra-$id") ? (int) file_get_contents("$dir/extra-$id") : null];
    $stato['subs'][$id] = $s;
    $prodotto = fn(string $pid, string $ruolo) => $espandi ? ['id' => $pid, 'object' => 'product', 'metadata' => ['ruolo' => $ruolo]] : $pid;
    $voci = [['id' => 'si_base_' . $id, 'quantity' => 1, 'current_period_start' => $s['start'], 'current_period_end' => $s['end'],
              'price' => ['id' => $s['base_price'] ?? 'price_finto_annuale', 'product' => $prodotto('prod_finto_base', 'base')]]];
    if (($s['extra'] ?? null) !== null) {
        $voci[] = ['id' => 'si_extra_' . $id, 'quantity' => $s['extra'], 'current_period_start' => $s['start'], 'current_period_end' => $s['end'],
                   'price' => ['id' => $s['extra_price'] ?? 'price_finto_extra', 'product' => $prodotto('prod_finto_extra', 'aggiuntiva')]];
    }
    return [
        'id' => $id, 'object' => 'subscription', 'status' => $s['status'], 'cancel_at_period_end' => $s['cancel_at_period_end'],
        'items' => ['data' => $voci],
    ];
};

if ($metodo === 'GET' && $percorso === '/v1/balance') $rispondi(['object' => 'balance', 'available' => [], 'pending' => []]);
if ($metodo === 'POST' && $percorso === '/v1/customers') $rispondi(['id' => 'cus_finto' . $n, 'object' => 'customer', 'metadata' => $corpo['metadata'] ?? []]);
if ($metodo === 'POST' && preg_match('#^/v1/customers/(cus_[A-Za-z0-9_]+)$#', $percorso, $m)) {
    $rispondi(['id' => $m[1], 'object' => 'customer', 'metadata' => $corpo['metadata'] ?? []]);
}
// Le partite IVA del cliente (tax id): tenute in un file, per vedere aggiunte e cancellazioni.
if (preg_match('#^/v1/customers/(cus_[A-Za-z0-9_]+)/tax_ids(?:/([A-Za-z0-9_]+))?$#', $percorso, $m)) {
    $f = $dir . '/tax_ids.json'; $tutti = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
    $suoi = $tutti[$m[1]] ?? [];
    if ($metodo === 'POST') { $nuovo = ['id' => 'txi_finto' . $n, 'object' => 'tax_id', 'type' => $corpo['type'] ?? '', 'value' => $corpo['value'] ?? '']; $suoi[] = $nuovo; }
    if ($metodo === 'DELETE') $suoi = array_values(array_filter($suoi, fn($t) => $t['id'] !== ($m[2] ?? '')));
    $tutti[$m[1]] = $suoi; file_put_contents($f, json_encode($tutti));
    $rispondi($metodo === 'GET' ? ['object' => 'list', 'data' => $suoi] : ($metodo === 'POST' ? $nuovo : ['id' => $m[2] ?? '', 'deleted' => true]));
}
if ($metodo === 'POST' && $percorso === '/v1/checkout/sessions') {
    $rispondi(['id' => 'cs_test_finto' . $n, 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.test/c/pay/cs_test_finto' . $n,
               'mode' => $corpo['mode'] ?? '']);
}
if (preg_match('#^/v1/subscriptions/([A-Za-z0-9_]+)$#', $percorso, $m)) {
    if ($metodo === 'POST' && isset($corpo['cancel_at_period_end'])) {
        $abbonamento($m[1]);
        $stato['subs'][$m[1]]['cancel_at_period_end'] = $corpo['cancel_at_period_end'] === 'true';
    }
    if ($metodo === 'POST' && isset($corpo['items'])) {
        // Le voci: la principale (prezzo nuovo) e quella delle strutture aggiuntive (quantità, prezzo, o tolta).
        $abbonamento($m[1]);
        foreach ((array) $corpo['items'] as $voce) {
            $vid = (string) ($voce['id'] ?? '');
            if ($vid === 'si_base_' . $m[1]) {
                if (isset($voce['price'])) $stato['subs'][$m[1]]['base_price'] = $voce['price'];
            } elseif ($vid === 'si_extra_' . $m[1] || ($vid === '' && isset($voce['price']))) {
                if (($voce['deleted'] ?? '') === 'true') { $stato['subs'][$m[1]]['extra'] = null; continue; }
                if (isset($voce['quantity'])) $stato['subs'][$m[1]]['extra'] = (int) $voce['quantity'];
                if (isset($voce['price'])) $stato['subs'][$m[1]]['extra_price'] = $voce['price'];
            } else {
                $rispondi(['error' => ['message' => 'No such subscription item']], 400);
            }
        }
    }
    $rispondi($abbonamento($m[1]));
}
// Price (cambio di piano, 6H): si creano con il prodotto e il suo ruolo.
if ($metodo === 'POST' && $percorso === '/v1/prices') {
    $rispondi(['id' => 'price_creato' . $n, 'object' => 'price', 'unit_amount' => (int) ($corpo['unit_amount'] ?? 0),
               'product' => ['id' => 'prod_creato' . $n, 'metadata' => $corpo['product_data']['metadata'] ?? []]]);
}
// Coupon (6E): si creano e si cancellano; la prova controlla cosa arriva.
if ($metodo === 'POST' && $percorso === '/v1/coupons') {
    $rispondi(['id' => 'coupon_finto' . $n, 'object' => 'coupon', 'duration' => $corpo['duration'] ?? '', 'name' => $corpo['name'] ?? '',
               'percent_off' => $corpo['percent_off'] ?? null, 'amount_off' => $corpo['amount_off'] ?? null]);
}
if ($metodo === 'DELETE' && preg_match('#^/v1/coupons/([A-Za-z0-9_]+)$#', $percorso, $m)) $rispondi(['id' => $m[1], 'object' => 'coupon', 'deleted' => true]);
if ($metodo === 'POST' && $percorso === '/v1/billing_portal/sessions') $rispondi(['url' => 'https://billing.stripe.test/p/session_' . $n]);
$rispondi(['error' => ['message' => 'Unrecognized request URL']], 404);
