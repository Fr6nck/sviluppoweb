<?php
/**
 * 060 — 9O · Il programma partner con le commissioni (dopo le richieste «Diventa partner» della 049).
 *
 * 1. partner: il partner approvato, con il codice (PM-…), la sua commissione, lo sconto per i suoi clienti sul primo anno,
 *    le commissioni sui rinnovi sì/no, IBAN, P.IVA, periodicità della liquidazione e lo stato (attivo, sospeso, chiuso).
 * 2. partner_commissioni: una riga per pagamento di un cliente del partner (primo anno o rinnovo) sull'imponibile incassato;
 *    maturata → liquidata, o stornata dopo un rimborso (una riga negativa se era già liquidata). payment_id è unico:
 *    «ordine:N», «fattura:in_…», «storno:re_…»: lo stesso pagamento non conta due volte.
 * 3. partner_liquidazioni: i bonifici fatti al partner su sua fattura (periodo, importo, data, riferimento, numero fattura).
 * 4. partner_accessi: i link senza password per la pagina /partner (48 ore, una volta sola; si salva solo l'hash).
 * 5. accounts.partner_id e accounts.partner_codice_usato_at: il partner del cliente, assegnato una volta sola.
 *
 * Solo aggiunte, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */
use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    $pk = Db::isMysql() ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    if (!Migrator::tableExists('partner')) {
        $pdo->exec("CREATE TABLE partner (
            id $pk,
            richiesta_id INTEGER NULL,
            ragione_sociale VARCHAR(160) NOT NULL DEFAULT '',
            referente VARCHAR(120) NOT NULL DEFAULT '',
            email VARCHAR(190) NOT NULL,
            telefono VARCHAR(40) NOT NULL DEFAULT '',
            zona VARCHAR(255) NOT NULL DEFAULT '',
            codice VARCHAR(24) NOT NULL,
            commissione_pct INTEGER NOT NULL DEFAULT 0,
            sconto_clienti_pct INTEGER NOT NULL DEFAULT 0,
            rinnovi INTEGER NOT NULL DEFAULT 1,
            iban VARCHAR(40) NOT NULL DEFAULT '',
            piva VARCHAR(20) NOT NULL DEFAULT '',
            periodicita VARCHAR(12) NOT NULL DEFAULT 'trimestrale',
            stato VARCHAR(10) NOT NULL DEFAULT 'attivo',
            lingua VARCHAR(5) NOT NULL DEFAULT 'it',
            note TEXT NULL,
            created_at VARCHAR(25) NOT NULL,
            updated_at VARCHAR(25) NULL
        )");
        $pdo->exec('CREATE UNIQUE INDEX idx_partner_codice ON partner(codice)');
        $pdo->exec('CREATE INDEX idx_partner_email ON partner(email)');
    }
    if (!Migrator::tableExists('partner_commissioni')) {
        $pdo->exec("CREATE TABLE partner_commissioni (
            id $pk,
            partner_id INTEGER NOT NULL,
            account_id INTEGER NOT NULL,
            payment_id VARCHAR(80) NOT NULL,
            codice_pagamento VARCHAR(24) NOT NULL DEFAULT '',
            tipo VARCHAR(12) NOT NULL DEFAULT 'primo_anno',
            imponibile_cents INTEGER NOT NULL DEFAULT 0,
            pct INTEGER NOT NULL DEFAULT 0,
            importo_cents INTEGER NOT NULL DEFAULT 0,
            stato VARCHAR(10) NOT NULL DEFAULT 'maturata',
            liquidazione_id INTEGER NULL,
            stornata_at VARCHAR(25) NULL,
            created_at VARCHAR(25) NOT NULL
        )");
        $pdo->exec('CREATE UNIQUE INDEX idx_partner_comm_pagamento ON partner_commissioni(payment_id)');
        $pdo->exec('CREATE INDEX idx_partner_comm_partner ON partner_commissioni(partner_id)');
        $pdo->exec('CREATE INDEX idx_partner_comm_account ON partner_commissioni(account_id)');
    }
    if (!Migrator::tableExists('partner_liquidazioni')) {
        $pdo->exec("CREATE TABLE partner_liquidazioni (
            id $pk,
            partner_id INTEGER NOT NULL,
            periodo_da VARCHAR(10) NOT NULL,
            periodo_a VARCHAR(10) NOT NULL,
            importo_cents INTEGER NOT NULL DEFAULT 0,
            data_bonifico VARCHAR(10) NOT NULL,
            riferimento VARCHAR(120) NOT NULL DEFAULT '',
            fattura_partner VARCHAR(60) NOT NULL DEFAULT '',
            creata_da INTEGER NULL,
            created_at VARCHAR(25) NOT NULL
        )");
        $pdo->exec('CREATE INDEX idx_partner_liq_partner ON partner_liquidazioni(partner_id)');
    }
    if (!Migrator::tableExists('partner_accessi')) {
        $pdo->exec("CREATE TABLE partner_accessi (
            id $pk,
            partner_id INTEGER NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            scade_at VARCHAR(25) NOT NULL,
            usato_at VARCHAR(25) NULL,
            created_at VARCHAR(25) NOT NULL
        )");
        $pdo->exec('CREATE UNIQUE INDEX idx_partner_accessi_token ON partner_accessi(token_hash)');
    }
    foreach (['partner_id' => 'INTEGER NULL', 'partner_codice_usato_at' => 'VARCHAR(25) NULL'] as $col => $tipo) {
        if (!Migrator::columnExists('accounts', $col)) $pdo->exec("ALTER TABLE accounts ADD COLUMN $col $tipo");
    }
};
