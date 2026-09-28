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
$abbonamento = function (string $id) use (&$stato, $ora): array {
    $s = $stato['subs'][$id] ?? ['cancel_at_period_end' => false, 'status' => 'active', 'start' => $ora, 'end' => $ora + 365 * 86400];
    $stato['subs'][$id] = $s;
    return [
        'id' => $id, 'object' => 'subscription', 'status' => $s['status'], 'cancel_at_period_end' => $s['cancel_at_period_end'],
        'items' => ['data' => [['current_period_start' => $s['start'], 'current_period_end' => $s['end'], 'price' => ['id' => 'price_finto_annuale']]]],
    ];
};

if ($metodo === 'POST' && $percorso === '/v1/customers') $rispondi(['id' => 'cus_finto' . $n, 'object' => 'customer']);
if ($metodo === 'POST' && $percorso === '/v1/checkout/sessions') {
    $rispondi(['id' => 'cs_test_finto' . $n, 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.test/c/pay/cs_test_finto' . $n,
               'mode' => $corpo['mode'] ?? '']);
}
if (preg_match('#^/v1/subscriptions/([A-Za-z0-9_]+)$#', $percorso, $m)) {
    if ($metodo === 'POST' && isset($corpo['cancel_at_period_end'])) {
        $abbonamento($m[1]);
        $stato['subs'][$m[1]]['cancel_at_period_end'] = $corpo['cancel_at_period_end'] === 'true';
    }
    $rispondi($abbonamento($m[1]));
}
if ($metodo === 'POST' && $percorso === '/v1/billing_portal/sessions') $rispondi(['url' => 'https://billing.stripe.test/p/session_' . $n]);
$rispondi(['error' => ['message' => 'Unrecognized request URL']], 404);
