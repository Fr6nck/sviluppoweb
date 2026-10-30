<?php
/**
 * 058 — 9N · 10 bis · Offerte su misura oltre il massimo del Portfolio (oltre 10 strutture).
 *
 * 1. offerte: le offerte preparate in Amministrazione dal preventivo (o dalla scheda del cliente per più strutture): strutture,
 *    prezzo annuo concordato, scadenza, link con il token, stato (inviata, accettata, scaduta, annullata), lo sconto sul
 *    prossimo rinnovo deciso a mano (gli sconti del programma non valgono da soli), la nota per il cliente e, per un cliente
 *    che ha già un'offerta attiva, il modo del passaggio (subito con conguaglio o dal rinnovo).
 * 2. orders.offerta_id; subscriptions.offerta_id (l'offerta che l'abbonamento paga) e next_offerta_id (quella che vale dal rinnovo).
 *
 * Solo aggiunte, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */
use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    $pk = Db::isMysql() ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    if (!Migrator::tableExists('offerte')) {
        $pdo->exec("CREATE TABLE offerte (
            id $pk,
            preventivo_id INTEGER NULL,
            account_id INTEGER NULL,
            email VARCHAR(190) NOT NULL DEFAULT '',
            nome VARCHAR(120) NOT NULL DEFAULT '',
            lingua VARCHAR(5) NOT NULL DEFAULT 'it',
            strutture INTEGER NOT NULL DEFAULT 0,
            prezzo_cents INTEGER NOT NULL DEFAULT 0,
            valida_fino VARCHAR(10) NOT NULL,
            token VARCHAR(64) NOT NULL,
            stato VARCHAR(12) NOT NULL DEFAULT 'inviata',
            sconto_rinnovo_pct INTEGER NOT NULL DEFAULT 0,
            note TEXT NULL,
            modo VARCHAR(10) NOT NULL DEFAULT 'nuovo',
            creata_da INTEGER NULL,
            created_at VARCHAR(25) NOT NULL,
            accettata_at VARCHAR(25) NULL,
            sconto_at VARCHAR(25) NULL
        )");
        $pdo->exec('CREATE UNIQUE INDEX idx_offerte_token ON offerte(token)');
        $pdo->exec('CREATE INDEX idx_offerte_preventivo ON offerte(preventivo_id)');
    }
    $colonne = [
        'orders' => ['offerta_id' => 'INTEGER NULL'],
        'subscriptions' => ['offerta_id' => 'INTEGER NULL', 'next_offerta_id' => 'INTEGER NULL'],
    ];
    foreach ($colonne as $tabella => $nuove) {
        foreach ($nuove as $col => $tipo) if (!Migrator::columnExists($tabella, $col)) $pdo->exec("ALTER TABLE $tabella ADD COLUMN $col $tipo");
    }
};
