<?php
/**
 * 045 — 9D/9E · Codici omaggio, avvisi prima dell'addebito, rimborsi, password provvisoria.
 *
 * 1. codici_omaggio: i codici generati dall'amministrazione (OMAGGIO-XXXXX), con i mesi gratis, la scadenza,
 *    la nota, chi li ha creati, chi li ha usati e quando, l'annullamento.
 * 2. accounts.codice_omaggio_id: il codice prenotato all'account (si brucia al pagamento, dal webhook).
 *    orders.codice_omaggio_id: l'ordine nato con l'omaggio (trial su Stripe).
 * 3. subscriptions.omaggio_fino: la fine dei mesi gratis (il primo addebito); subscriptions.card_last4: le ultime
 *    4 cifre della carta, lette da Stripe per gli avvisi a 30 e 7 giorni.
 * 4. rimborsi (9E): i rimborsi fatti dall'amministrazione da Stripe, con la fattura, l'importo e il motivo.
 * 5. users.password_temp_until, users.must_change_password (9E): la password provvisoria impostata
 *    dall'amministrazione, valida 48 ore e da cambiare al primo accesso.
 *
 * Solo aggiunte, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */
use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    $pk = Db::isMysql() ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';

    if (!Migrator::tableExists('codici_omaggio')) {
        $pdo->exec("CREATE TABLE codici_omaggio (
            id $pk,
            codice VARCHAR(20) NOT NULL,
            mesi INTEGER NOT NULL DEFAULT 3,
            scade_il VARCHAR(10) NOT NULL,
            nota VARCHAR(255) NOT NULL DEFAULT '',
            creato_da INTEGER NULL,
            created_at VARCHAR(25) NOT NULL,
            usato_da_account_id INTEGER NULL,
            usato_at VARCHAR(25) NULL,
            annullato_at VARCHAR(25) NULL
        )");
        $pdo->exec('CREATE UNIQUE INDEX idx_codici_omaggio_codice ON codici_omaggio(codice)');
    }

    if (!Migrator::tableExists('rimborsi')) {
        $pdo->exec("CREATE TABLE rimborsi (
            id $pk,
            account_id INTEGER NOT NULL,
            invoice_id VARCHAR(64) NOT NULL DEFAULT '',
            charge_id VARCHAR(64) NOT NULL DEFAULT '',
            refund_id VARCHAR(64) NOT NULL DEFAULT '',
            amount_cents INTEGER NOT NULL DEFAULT 0,
            motivo VARCHAR(40) NOT NULL DEFAULT '',
            testo VARCHAR(255) NOT NULL DEFAULT '',
            stato VARCHAR(16) NOT NULL DEFAULT 'richiesto',
            creato_da INTEGER NULL,
            created_at VARCHAR(25) NOT NULL,
            confermato_at VARCHAR(25) NULL
        )");
        $pdo->exec('CREATE INDEX idx_rimborsi_account ON rimborsi(account_id)');
    }

    $colonne = [
        'accounts' => ['codice_omaggio_id' => 'INTEGER NULL'],
        'orders' => ['codice_omaggio_id' => 'INTEGER NULL'],
        'subscriptions' => ['omaggio_fino' => 'VARCHAR(25) NULL', 'card_last4' => "VARCHAR(4) NOT NULL DEFAULT ''"],
        'users' => ['password_temp_until' => 'VARCHAR(25) NULL', 'must_change_password' => 'INTEGER NOT NULL DEFAULT 0'],
    ];
    foreach ($colonne as $tabella => $elenco) {
        if (!Migrator::tableExists($tabella)) continue;
        foreach ($elenco as $nome => $tipo) {
            if (!Migrator::columnExists($tabella, $nome)) $pdo->exec("ALTER TABLE $tabella ADD COLUMN $nome $tipo");
        }
    }
};
