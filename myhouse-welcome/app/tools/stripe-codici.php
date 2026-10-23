<?php
/**
 * 9I — Una volta sola: scrive il codice cliente (metadata[codice_cliente]) sui clienti Stripe che esistono già.
 *
 *   php app/tools/stripe-codici.php            prova a vuoto: dice cosa farebbe, non tocca Stripe
 *   php app/tools/stripe-codici.php --scrivi   lo fa davvero (prima con la chiave di prova sk_test_)
 *   php app/tools/stripe-codici.php --scrivi --live   con la chiave viva (sk_live_), dopo la prova
 *
 * I clienti nuovi il codice lo ricevono da soli (Stripe::ensureCustomer e syncCustomer). Ripeterlo non fa danni:
 * Stripe riceve lo stesso valore.
 */

declare(strict_types=1);

use MHW\{Config, Db, Codici, Stripe};

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('MHW_APP', dirname(__DIR__));
spl_autoload_register(function (string $c): void {
    if (str_starts_with($c, 'MHW\\') && is_file($f = MHW_APP . '/src/' . substr($c, 4) . '.php')) require $f;
});
Config::load(MHW_APP . '/config.php');

$scrivi = in_array('--scrivi', $argv, true);
$chiave = (string) ((Config::get('stripe') ?? [])['secret_key'] ?? '');
if ($scrivi && $chiave === '') { fwrite(STDERR, "Stripe non è configurato (chiave segreta).\n"); exit(1); }
if ($scrivi && \MHW\Stripe::modoChiave($chiave) === 'live' && !in_array('--live', $argv, true)) {
    fwrite(STDERR, "Chiave viva (sk_live_): prima prova con quella di test, poi rilancia con --scrivi --live.\n"); exit(1);
}
if (!Codici::disponibili()) { fwrite(STDERR, "Manca la migrazione 048: apri il sito una volta per aggiornare il database.\n"); exit(1); }

$fatti = 0; $errori = 0;
foreach (Db::all("SELECT id, stripe_customer_id FROM accounts WHERE stripe_customer_id IS NOT NULL AND stripe_customer_id <> '' ORDER BY id") as $a) {
    $codice = Codici::cliente((int) $a['id']);
    if (!$scrivi) { echo $a['stripe_customer_id'], ' → ', $codice, "\n"; $fatti++; continue; }
    try {
        Stripe::call('POST', 'customers/' . rawurlencode((string) $a['stripe_customer_id']), ['metadata[codice_cliente]' => $codice], 'mhw-customer-codice-' . $a['id'] . '-' . $codice);
        echo 'ok  ', $a['stripe_customer_id'], ' → ', $codice, "\n"; $fatti++;
    } catch (\Throwable $e) {
        echo 'NO  ', $a['stripe_customer_id'], ': ', $e->getMessage(), "\n"; $errori++;
    }
}
echo $scrivi ? "Aggiornati $fatti clienti Stripe" . ($errori ? ", $errori non riusciti" : '') . ".\n" : "Prova a vuoto: $fatti clienti Stripe da aggiornare. Rilancia con --scrivi.\n";
exit($errori ? 1 : 0);
