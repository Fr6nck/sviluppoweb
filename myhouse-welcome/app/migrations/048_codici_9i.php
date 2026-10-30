<?php
/**
 * 048 — 9I · Codice cliente, codice struttura e codice pagamento.
 *
 * 1. Colonne: accounts.code, properties.code, orders.code (uniche), subscriptions.code e rimborsi.code
 *    (il codice dell'ordine che ha creato l'abbonamento; il codice del pagamento rimborsato).
 * 2. codici_usati: il registro di ogni codice generato (indice unico su codice), che resta anche quando l'account
 *    o la struttura si cancellano: così un codice non si riassegna mai. Con i legami (account, struttura, ordine,
 *    fattura Stripe) per i pagamenti che non hanno un ordine (rinnovi, conguagli).
 * 3. I codici per tutto quello che c'è già: tutti gli account, tutte le strutture (anche archiviate e la vetrina),
 *    gli ordini pagati nell'ordine di data, gli abbonamenti e i rimborsi.
 *
 * Solo aggiunte, SQLite e MySQL. Una seconda esecuzione non cambia niente (i codici già dati restano).
 */
use MHW\{Db, Migrator, Codici};

return function (\PDO $pdo): void {
    $pk = Db::isMysql() ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $colonne = ['accounts' => 'VARCHAR(12)', 'properties' => 'VARCHAR(16)', 'orders' => 'VARCHAR(24)', 'subscriptions' => 'VARCHAR(24)', 'rimborsi' => 'VARCHAR(24)'];
    foreach ($colonne as $t => $tipo) {
        if (Migrator::tableExists($t) && !Migrator::columnExists($t, 'code')) $pdo->exec("ALTER TABLE $t ADD COLUMN code $tipo NULL");
    }
    if (!Migrator::tableExists('codici_usati')) {
        $pdo->exec("CREATE TABLE codici_usati (
            id $pk,
            codice VARCHAR(24) NOT NULL,
            livello VARCHAR(12) NOT NULL,
            account_id INTEGER NULL,
            property_id INTEGER NULL,
            order_id INTEGER NULL,
            invoice_id VARCHAR(64) NULL,
            created_at VARCHAR(25) NOT NULL
        )");
    }
    // Gli indici unici (i NULL non contano, su SQLite e su MySQL): se ci sono già, si va avanti.
    foreach (['idx_codici_usati_codice' => 'codici_usati(codice)', 'idx_accounts_code' => 'accounts(code)', 'idx_properties_code' => 'properties(code)', 'idx_orders_code' => 'orders(code)'] as $nome => $dove) {
        try { $pdo->exec("CREATE UNIQUE INDEX $nome ON $dove"); } catch (\Throwable) { }
    }
    try { $pdo->exec('CREATE INDEX idx_codici_usati_fattura ON codici_usati(invoice_id)'); } catch (\Throwable) { }

    // I codici per i dati che ci sono già, nell'ordine di creazione.
    foreach (Db::all('SELECT id FROM accounts ORDER BY id') as $a) Codici::cliente((int) $a['id']);
    foreach (Db::all('SELECT id FROM properties ORDER BY account_id, id') as $p) Codici::struttura((int) $p['id']);
    foreach (Db::all("SELECT id FROM orders WHERE status = 'paid' ORDER BY created_at, id") as $o) Codici::pagamento((int) $o['id']);
    // L'abbonamento ha il codice dell'ultimo ordine pagato dello stesso account nato prima di lui.
    foreach (Db::all("SELECT id, account_id, created_at FROM subscriptions WHERE code IS NULL OR code = ''") as $s) {
        $c = (string) Db::val("SELECT code FROM orders WHERE account_id = ? AND status = 'paid' AND code IS NOT NULL AND created_at <= ? ORDER BY created_at DESC, id DESC LIMIT 1",
                              [(int) $s['account_id'], (string) $s['created_at']], '');
        if ($c !== '') Db::run('UPDATE subscriptions SET code = ? WHERE id = ?', [$c, (int) $s['id']]);
    }
    // Il rimborso porta il codice del pagamento rimborsato (la fattura Stripe), se si ritrova.
    if (Migrator::tableExists('rimborsi')) {
        foreach (Db::all("SELECT id, account_id, invoice_id, created_at FROM rimborsi WHERE (code IS NULL OR code = '') AND invoice_id <> ''") as $r) {
            Db::run('UPDATE rimborsi SET code = ? WHERE id = ?', [Codici::pagamentoFattura((int) $r['account_id'], (string) $r['invoice_id'], (string) $r['created_at']), (int) $r['id']]);
        }
    }
};
