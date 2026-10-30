<?php
/**
 * 063 — 9R (pacchetto 16) · Il 5% di «Giudizio con sconto» si usa una volta sola.
 *
 * Con il pacchetto 9R del 22 ottobre chi aveva aderito al pagamento e aveva già avuto il 5% sul primo anno, lasciando il
 * giudizio, tornava «sul prossimo rinnovo» (da_applicare_rinnovo). Con la regola definitiva quel 5% è già usato: lo stato
 * torna «applicato_primo_anno». Si riconoscono dalla data dello sconto (giudizio_sconto_at), che c'è solo se il 5% è stato
 * applicato. Gli account toccati finiscono in storage/.sconti-da-riallineare.json: al giro dei richiami lo sconto del
 * rinnovo su Stripe si ricalcola (Richiami → Sconti::sincronizzaRinnovo), perché una migrazione non chiama Stripe.
 *
 * Solo dati, SQLite e MySQL. Una seconda esecuzione non trova più niente da cambiare.
 */
use MHW\Migrator;

return function (\PDO $pdo): void {
    if (!Migrator::tableExists('accounts') || !Migrator::columnExists('accounts', 'giudizio_sconto_stato') || !Migrator::columnExists('accounts', 'giudizio_sconto_at')) return;
    $st = $pdo->query("SELECT id FROM accounts WHERE giudizio_adesione = 'pagamento' AND giudizio_sconto_stato = 'da_applicare_rinnovo'
                       AND giudizio_sconto_at IS NOT NULL AND giudizio_sconto_at <> ''");
    $ids = array_map('intval', $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []);
    if (!$ids) return;
    $up = $pdo->prepare("UPDATE accounts SET giudizio_sconto_stato = 'applicato_primo_anno' WHERE id = ?");
    foreach ($ids as $id) $up->execute([$id]);
    $file = MHW_APP . '/storage/.sconti-da-riallineare.json';
    $prima = is_file($file) ? (json_decode((string) @file_get_contents($file), true) ?: []) : [];
    @file_put_contents($file, json_encode(array_values(array_unique(array_merge($prima, $ids)))), LOCK_EX);
};
