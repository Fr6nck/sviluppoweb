<?php
/**
 * 7B — Toglie la vecchia voce «camere» dagli abbonamenti Stripe (le varianti camera non ci sono più).
 *
 * Da lanciare UNA volta, a mano, dalla cartella dell'applicazione:
 *   php app/tools/togli-varianti-stripe.php --prova   elenca soltanto: cliente, quante camere, importo; non tocca niente
 *   php app/tools/togli-varianti-stripe.php           elenca, chiede conferma (scrivi SI) e toglie
 *
 * Per ogni abbonamento Stripe attivo con la voce delle camere (il prodotto con ruolo=variante, o la
 * voce salvata in subscriptions.provider_variant_item_id): DELETE della voce con
 * proration_behavior=create_prorations, così il cliente ha il credito della parte non goduta sulla
 * prossima fattura; poi provider_variant_item_id si azzera e il registro (audit_log) ne tiene traccia.
 * Dopo: su Stripe archivia il prodotto «MyHouse Welcome — variante camera» (vedi DA-CONFIGURARE.md).
 */

declare(strict_types=1);

use MHW\{Config, Db, Migrator, Support, Stripe};

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('MHW_APP', dirname(__DIR__));
spl_autoload_register(function (string $c): void {
    if (str_starts_with($c, 'MHW\\') && is_file($f = MHW_APP . '/src/' . substr($c, 4) . '.php')) require $f;
});
Config::load(MHW_APP . '/config.php');
$prova = in_array('--prova', $argv, true);

if (!Stripe::enabled()) { fwrite(STDERR, "Stripe non è configurato (chiave segreta): niente da fare.\n"); exit(1); }
$colonna = Migrator::columnExists('subscriptions', 'provider_variant_item_id');

$trovati = [];
foreach (Db::all("SELECT s.*, u.email FROM subscriptions s JOIN accounts a ON a.id = s.account_id JOIN users u ON u.id = a.user_id
                  WHERE s.provider = 'stripe' AND s.provider_subscription_id <> '' AND s.status IN ('active', 'trialing', 'past_due')
                  ORDER BY s.id") as $s) {
    try {
        $sub = Stripe::call('GET', 'subscriptions/' . rawurlencode((string) $s['provider_subscription_id']), ['expand' => ['items.data.price.product']]);
    } catch (\Throwable $e) { echo "  ! {$s['email']}: Stripe non risponde per {$s['provider_subscription_id']} ({$e->getMessage()})\n"; continue; }
    foreach ((array) ($sub['items']['data'] ?? []) as $v) {
        $prodotto = $v['price']['product'] ?? null;
        $ruolo = is_array($prodotto) ? (string) ($prodotto['metadata']['ruolo'] ?? '') : '';
        if ($ruolo !== 'variante' && !($colonna && (string) $s['provider_variant_item_id'] !== '' && $v['id'] === $s['provider_variant_item_id'])) continue;
        $q = (int) ($v['quantity'] ?? 0);
        $trovati[] = ['s' => $s, 'voce' => (string) $v['id'], 'quante' => $q, 'importo' => $q * (int) ($v['price']['unit_amount'] ?? 0)];
    }
}

if (!$trovati) { echo "Nessun abbonamento attivo ha la voce delle camere: niente da togliere.\n"; exit(0); }
echo count($trovati) . " abbonamenti con la voce delle camere:\n";
foreach ($trovati as $t) {
    printf("  - %s · abbonamento %s · %d camere · %s + IVA l'anno\n", $t['s']['email'], $t['s']['provider_subscription_id'], $t['quante'], Support::money($t['importo']));
}
if ($prova) { echo "\n--prova: non ho toccato niente.\n"; exit(0); }

echo "\nTolgo queste voci? Il cliente avrà il credito della parte non goduta sulla prossima fattura. Scrivi SI per continuare: ";
if (strtoupper(trim((string) fgets(STDIN))) !== 'SI') { echo "Fermato: non ho toccato niente.\n"; exit(0); }

$fatti = 0;
foreach ($trovati as $t) {
    try {
        Stripe::call('DELETE', 'subscription_items/' . rawurlencode($t['voce']), ['proration_behavior' => 'create_prorations'],
                     'mhw-togli-varianti-' . $t['voce']);
        if ($colonna) Db::run("UPDATE subscriptions SET provider_variant_item_id = '' WHERE id = ?", [$t['s']['id']]);
        Db::insert('audit_log', ['actor_user_id' => null, 'target_user_id' => (int) Db::val('SELECT user_id FROM accounts WHERE id = ?', [$t['s']['account_id']]),
                                 'action' => 'stripe.voce_camere_tolta', 'created_at' => Support::now(),
                                 'meta' => json_encode(['abbonamento' => $t['s']['provider_subscription_id'], 'voce' => $t['voce'], 'camere' => $t['quante'], 'importo_cents' => $t['importo']])]);
        echo "  ✓ {$t['s']['email']}: voce tolta\n"; $fatti++;
    } catch (\Throwable $e) { echo "  ! {$t['s']['email']}: non riuscito ({$e->getMessage()})\n"; }
}
echo "\nFatto: $fatti su " . count($trovati) . ". Ora archivia su Stripe il prodotto «MyHouse Welcome — variante camera».\n";
