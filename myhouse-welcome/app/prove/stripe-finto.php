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
if ($metodo === 'DELETE') $corpo += $_GET;   // i parametri di DELETE arrivano nell'indirizzo
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
$espandi = in_array('items.data.price.product', array_merge((array) ($_GET['expand'] ?? []), (array) ($corpo['expand'] ?? [])), true);
$abbonamento = function (string $id) use (&$stato, $ora, $dir, $espandi): array {
    $s = $stato['subs'][$id] ?? ['cancel_at_period_end' => false, 'status' => 'active', 'start' => $ora, 'end' => $ora + 365 * 86400,
                                 'extra' => is_file("$dir/extra-$id") ? (int) file_get_contents("$dir/extra-$id") : null];
    $stato['subs'][$id] = $s;
    $prodotto = fn(string $pid, string $ruolo) => $espandi ? ['id' => $pid, 'object' => 'product', 'metadata' => ['ruolo' => $ruolo]] : $pid;
    $voci = [['id' => 'si_base_' . $id, 'quantity' => 1, 'current_period_start' => $s['start'], 'current_period_end' => $s['end'],
              'price' => ['id' => $s['base_price'] ?? 'price_finto_annuale', 'product' => $prodotto('prod_finto_base', 'base')]]];
    if (($s['varianti'] ?? null) !== null) {
        $voci[] = ['id' => 'si_var_' . $id, 'quantity' => $s['varianti'], 'current_period_start' => $s['start'], 'current_period_end' => $s['end'],
                   'price' => ['id' => $s['var_price'] ?? 'price_variante', 'product' => $prodotto('prod_finto_variante', 'variante')]];
    }
    if (($s['extra'] ?? null) !== null) {
        $voci[] = ['id' => 'si_extra_' . $id, 'quantity' => $s['extra'], 'current_period_start' => $s['start'], 'current_period_end' => $s['end'],
                   'price' => ['id' => $s['extra_price'] ?? 'price_finto_extra', 'product' => $prodotto('prod_finto_extra', 'aggiuntiva')]];
    }
    // 9B: gli sconti dell'abbonamento (coupon degli inviti, del giudizio…), come li scrive il sito con discounts[].
    $sconti = [];
    foreach ((array) ($s['discounts'] ?? []) as $i => $d) $sconti[] = ['id' => 'di_' . $id . '_' . $i, 'object' => 'discount', 'coupon' => ['id' => $d, 'object' => 'coupon']];
    // 9D: la carta (ultime 4 cifre) e la fine dei mesi gratis, se la prova li ha scritti nello stato.
    $out = [
        'id' => $id, 'object' => 'subscription', 'status' => $s['status'], 'cancel_at_period_end' => $s['cancel_at_period_end'],
        'items' => ['data' => $voci], 'discounts' => $sconti,
        'default_payment_method' => ['id' => 'pm_finto_' . $id, 'object' => 'payment_method', 'card' => ['last4' => $s['last4'] ?? '4242']],
    ];
    if (!empty($s['trial_end'])) $out['trial_end'] = (int) $s['trial_end'];
    return $out;
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
// 9A: le sessioni di Checkout si tengono con gli importi calcolati come farebbe Stripe (Price salvati o price_data,
// scaglioni graduati, coupon in percento), per i controlli prima e dopo la creazione; si possono rileggere e far scadere.
$importoVoce = function (array $voce) use (&$stato): int {
    $q = (int) ($voce['quantity'] ?? 1);
    $pr = isset($voce['price']) ? ($stato['listino'][$voce['price']] ?? ['unit_amount' => 0]) : (array) ($voce['price_data'] ?? []);
    if (($pr['billing_scheme'] ?? '') === 'tiered') {
        $tot = 0; $fatte = 0;
        foreach ((array) ($pr['tiers'] ?? []) as $t) {
            $fino = ($t['up_to'] ?? 'inf') === 'inf' ? PHP_INT_MAX : (int) $t['up_to'];
            $n = max(0, min($q, $fino) - $fatte);
            $tot += $n * (int) ($t['unit_amount'] ?? 0); $fatte += $n;
            if ($fatte >= $q) break;
        }
        return $tot;
    }
    return $q * (int) ($pr['unit_amount'] ?? 0);
};
if ($metodo === 'POST' && $percorso === '/v1/checkout/sessions') {
    $sub = 0;
    foreach ((array) ($corpo['line_items'] ?? []) as $voce) $sub += $importoVoce((array) $voce);
    $sconto = 0;
    $cid = (string) ($corpo['discounts'][0]['coupon'] ?? '');
    if ($cid !== '' && isset($stato['coupons'][$cid])) {
        $c = $stato['coupons'][$cid];
        $sconto = $c['percent_off'] !== null ? (int) round($sub * (float) $c['percent_off'] / 100) : (int) $c['amount_off'];
    }
    // Con il file «importo-sbagliato» la sessione esce con un totale diverso (per la prova del secondo controllo).
    if (is_file($dir . '/importo-sbagliato')) $sconto += 100;
    // 9D: con i mesi gratis (subscription_data[trial_end]) oggi non si paga niente: come Stripe, imponibile e totale a 0.
    $trial = (int) ($corpo['subscription_data']['trial_end'] ?? 0);
    if ($trial > 0 && !is_file($dir . '/importo-sbagliato')) { $sub = 0; $sconto = 0; }
    $sessione = ['id' => 'cs_test_finto' . $n, 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.test/c/pay/cs_test_finto' . $n,
                 'mode' => $corpo['mode'] ?? '', 'status' => 'open', 'amount_subtotal' => $sub, 'amount_total' => $sub - $sconto,
                 'total_details' => ['amount_discount' => $sconto, 'amount_tax' => 0], 'metadata' => $corpo['metadata'] ?? [],
                 'trial_end' => $trial ?: null, 'payment_method_collection' => $corpo['payment_method_collection'] ?? 'if_required'];
    $stato['sessioni']['cs_test_finto' . $n] = $sessione;
    $rispondi($sessione);
}
if (preg_match('#^/v1/checkout/sessions/([A-Za-z0-9_]+)(/expire)?$#', $percorso, $m)) {
    if (!isset($stato['sessioni'][$m[1]])) $rispondi(['error' => ['message' => 'No such checkout.session: ' . $m[1]]], 404);
    if ($metodo === 'POST' && ($m[2] ?? '') === '/expire') $stato['sessioni'][$m[1]]['status'] = 'expired';
    $rispondi($stato['sessioni'][$m[1]]);
}
if (preg_match('#^/v1/subscriptions/([A-Za-z0-9_]+)$#', $percorso, $m)) {
    if ($metodo === 'POST' && isset($corpo['cancel_at_period_end'])) {
        $abbonamento($m[1]);
        $stato['subs'][$m[1]]['cancel_at_period_end'] = $corpo['cancel_at_period_end'] === 'true';
    }
    // 9B: discounts[i][coupon] aggiunge un coupon, discounts[i][discount] conferma uno sconto già presente (di_<sub>_<n>); «discounts=» li toglie tutti.
    if ($metodo === 'POST' && array_key_exists('discounts', $corpo)) {
        $abbonamento($m[1]);
        $prima = (array) ($stato['subs'][$m[1]]['discounts'] ?? []);
        $nuovi = [];
        foreach (is_array($corpo['discounts']) ? $corpo['discounts'] : [] as $d) {
            if (isset($d['coupon'])) $nuovi[] = (string) $d['coupon'];
            elseif (isset($d['discount']) && preg_match('/_(\d+)$/', (string) $d['discount'], $mm) && isset($prima[(int) $mm[1]])) $nuovi[] = $prima[(int) $mm[1]];
        }
        $stato['subs'][$m[1]]['discounts'] = array_values(array_unique($nuovi));
    }
    if ($metodo === 'POST' && isset($corpo['items'])) {
        // Le voci: la principale (prezzo nuovo) e quella delle strutture aggiuntive (quantità, prezzo, o tolta).
        $abbonamento($m[1]);
        // Varianti camera: con il file «rifiuta-variante» il pagamento non riesce e il cambio resta in sospeso.
        $prima = (array) ($corpo['items'][0] ?? []);
        $diVariante = str_starts_with((string) ($prima['price'] ?? ''), 'price_variante') || str_starts_with((string) ($prima['id'] ?? ''), 'si_var_');
        if (($corpo['payment_behavior'] ?? '') === 'pending_if_incomplete' && is_file($dir . '/rifiuta-variante') && $diVariante) {
            $rispondi($abbonamento($m[1]) + ['pending_update' => ['subscription_items' => $corpo['items']]]);
        }
        foreach ((array) $corpo['items'] as $voce) {
            $vid = (string) ($voce['id'] ?? '');
            if ($vid === 'si_var_' . $m[1] || ($vid === '' && str_starts_with((string) ($voce['price'] ?? ''), 'price_variante'))) {
                if (($voce['deleted'] ?? '') === 'true') { $stato['subs'][$m[1]]['varianti'] = null; continue; }
                if (isset($voce['quantity'])) $stato['subs'][$m[1]]['varianti'] = (int) $voce['quantity'];
                if (isset($voce['price'])) $stato['subs'][$m[1]]['var_price'] = $voce['price'];
                continue;
            }
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
// 9D: la fattura imminente di un abbonamento (importo del prossimo addebito): la prova la scrive in stato['upcoming'][sub].
if ($metodo === 'GET' && $percorso === '/v1/invoices/upcoming') {
    $sid = (string) ($_GET['subscription'] ?? '');
    if (!isset($stato['upcoming'][$sid])) $rispondi(['error' => ['message' => 'No upcoming invoices for subscription: ' . $sid]], 404);
    $rispondi(['object' => 'invoice', 'subscription' => $sid] + $stato['upcoming'][$sid]);
}
// 9E: le fatture pagate di un cliente (stato['invoices'][cus]) e i rimborsi.
if ($metodo === 'GET' && $percorso === '/v1/invoices') {
    $cus = (string) ($_GET['customer'] ?? '');
    $rispondi(['object' => 'list', 'data' => array_values((array) ($stato['invoices'][$cus] ?? []))]);
}
if ($metodo === 'POST' && $percorso === '/v1/refunds') {
    if (is_file($dir . '/rifiuta-rimborso')) $rispondi(['error' => ['message' => 'Charge has already been refunded.']], 400);
    $r = ['id' => 're_finto' . $n, 'object' => 'refund', 'payment_intent' => $corpo['payment_intent'] ?? '', 'amount' => (int) ($corpo['amount'] ?? 0),
          'charge' => 'ch_' . ($corpo['payment_intent'] ?? 'finto'), 'reason' => $corpo['reason'] ?? null, 'status' => 'pending', 'metadata' => $corpo['metadata'] ?? []];
    $stato['refunds'][] = $r;
    $rispondi($r);
}
if ($metodo === 'GET' && preg_match('#^/v1/payment_methods/([A-Za-z0-9_]+)$#', $percorso, $m)) $rispondi(['id' => $m[1], 'object' => 'payment_method', 'card' => ['last4' => '4242']]);
if ($metodo === 'GET' && preg_match('#^/v1/customers/(cus_[A-Za-z0-9_]+)$#', $percorso, $m)) {
    $rispondi(['id' => $m[1], 'object' => 'customer', 'invoice_settings' => ['default_payment_method' => ['id' => 'pm_cus_' . $m[1], 'object' => 'payment_method', 'card' => ['last4' => '4242']]]]);
}
// Price (cambio di piano, 6H): si creano con il prodotto e il suo ruolo. Quelli delle varianti
// camera hanno una lookup_key e si ritrovano con GET /v1/prices?lookup_keys[]=…
if ($metodo === 'GET' && $percorso === '/v1/prices') {
    $chiavi = (array) ($_GET['lookup_keys'] ?? []);
    $trovati = array_values(array_filter(array_map(fn($k) => $stato['prezzi'][$k] ?? null, $chiavi)));
    $rispondi(['object' => 'list', 'data' => array_map(fn($id) => ['id' => $id, 'object' => 'price'], $trovati)]);
}
// 9A: ogni Price creato resta nel listino finto, con la forma di quello vero (per Stripe::verificaPrezzi).
$salvaPrice = function (string $id, array $corpo, string $prodotto, array $metaProdotto) use (&$stato): array {
    $pr = ['id' => $id, 'object' => 'price', 'active' => true, 'currency' => $corpo['currency'] ?? 'eur',
           'unit_amount' => isset($corpo['unit_amount']) ? (int) $corpo['unit_amount'] : null, 'tax_behavior' => $corpo['tax_behavior'] ?? 'unspecified',
           'recurring' => isset($corpo['recurring']) ? ['interval' => $corpo['recurring']['interval'] ?? '', 'interval_count' => (int) ($corpo['recurring']['interval_count'] ?? 1)] : null,
           'billing_scheme' => $corpo['billing_scheme'] ?? 'per_unit', 'tiers_mode' => $corpo['tiers_mode'] ?? null, 'lookup_key' => $corpo['lookup_key'] ?? null,
           'product' => ['id' => $prodotto, 'metadata' => $metaProdotto]];
    if (isset($corpo['tiers'])) $pr['tiers'] = array_values(array_map(fn($t) => ['up_to' => ($t['up_to'] ?? 'inf') === 'inf' ? null : (int) $t['up_to'], 'unit_amount' => (int) ($t['unit_amount'] ?? 0)], (array) $corpo['tiers']));
    $stato['listino'][$id] = $pr;
    return $pr;
};
if ($metodo === 'POST' && $percorso === '/v1/prices' && (($corpo['product_data']['metadata']['ruolo'] ?? '') === 'variante')) {
    $id = 'price_variante' . $n;
    if (($corpo['lookup_key'] ?? '') !== '') $stato['prezzi'][$corpo['lookup_key']] = $id;
    $rispondi($salvaPrice($id, $corpo, 'prod_variante' . $n, ['ruolo' => 'variante']));
}
if ($metodo === 'POST' && $percorso === '/v1/prices') {
    $rispondi($salvaPrice('price_creato' . $n, $corpo, 'prod_creato' . $n, (array) ($corpo['product_data']['metadata'] ?? [])));
}
if ($metodo === 'GET' && preg_match('#^/v1/prices/([A-Za-z0-9_]+)$#', $percorso, $m)) {
    if (isset($stato['listino'][$m[1]])) $rispondi($stato['listino'][$m[1]]);
    // Un Price non creato qui (price_finto_… delle prove di prima): si risponde con quello che il listino del sito si aspetta,
    // letto dal file «price-<id>.json» se la prova l'ha scritto, altrimenti 404 come farebbe Stripe.
    if (is_file("$dir/price-{$m[1]}.json")) $rispondi(json_decode((string) file_get_contents("$dir/price-{$m[1]}.json"), true));
    $rispondi(['error' => ['message' => 'No such price: ' . $m[1]]], 404);
}
// Coupon (6E): si creano e si cancellano; la prova controlla cosa arriva.
if ($metodo === 'POST' && $percorso === '/v1/coupons') {
    $id = (string) ($corpo['id'] ?? '') !== '' ? (string) $corpo['id'] : 'coupon_finto' . $n;   // 9A: l'id lo può scegliere il sito
    if (isset($stato['coupons'][$id])) $rispondi(['error' => ['message' => 'Coupon already exists.']], 400);
    $c = ['id' => $id, 'object' => 'coupon', 'duration' => $corpo['duration'] ?? '', 'name' => $corpo['name'] ?? '',
          'percent_off' => $corpo['percent_off'] ?? null, 'amount_off' => $corpo['amount_off'] ?? null, 'valid' => true];
    $stato['coupons'][$id] = $c;
    $rispondi($c);
}
if ($metodo === 'GET' && preg_match('#^/v1/coupons/([A-Za-z0-9_-]+)$#', $percorso, $m)) {
    if (isset($stato['coupons'][$m[1]])) $rispondi($stato['coupons'][$m[1]]);
    $rispondi(['error' => ['message' => 'No such coupon: ' . $m[1]]], 404);
}
// 7B: togliere la vecchia voce delle camere da un abbonamento (tools/togli-varianti-stripe.php).
if ($metodo === 'DELETE' && preg_match('#^/v1/subscription_items/si_var_([A-Za-z0-9_]+)$#', $percorso, $m)) {
    $abbonamento($m[1]); $stato['subs'][$m[1]]['varianti'] = null;
    $rispondi(['id' => 'si_var_' . $m[1], 'object' => 'subscription_item', 'deleted' => true]);
}
if ($metodo === 'DELETE' && preg_match('#^/v1/coupons/([A-Za-z0-9_-]+)$#', $percorso, $m)) { unset($stato['coupons'][$m[1]]); $rispondi(['id' => $m[1], 'object' => 'coupon', 'deleted' => true]); }
if ($metodo === 'POST' && $percorso === '/v1/billing_portal/sessions') $rispondi(['url' => 'https://billing.stripe.test/p/session_' . $n]);
$rispondi(['error' => ['message' => 'Unrecognized request URL']], 404);
