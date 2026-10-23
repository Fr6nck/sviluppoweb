<?php
/**
 * 008 — Struttura e contatti.
 *
 * 1. Su properties, colonne nuove tutte facoltative: tipologia, indirizzo, CAP,
 *    CIN (codice identificativo nazionale degli affitti brevi), posti letto,
 *    coordinate (per ora vuote).
 * 2. Contatti duplicabili: tabella property_contacts. La prima riga si copia da
 *    host_name / host_phone / host_whatsapp, che restano dove sono (le leggono
 *    ancora le guide pubblicate prima).
 *
 * Lo schema si scrive qui e non in un .sql perché la chiave autoincrementale si
 * scrive diversa su SQLite e su MySQL; il resto è sintassi comune.
 */

use MHW\{Conversione, Db, Migrator};

return function (\PDO $pdo): void {
    $colonne = [
        'property_type' => "VARCHAR(20) NOT NULL DEFAULT ''",
        'address'       => "VARCHAR(255) NOT NULL DEFAULT ''",
        'postal_code'   => "VARCHAR(10) NOT NULL DEFAULT ''",
        'cin'           => "VARCHAR(40) NOT NULL DEFAULT ''",
        'beds'          => 'INTEGER NOT NULL DEFAULT 0',
        'lat'           => 'DECIMAL(9,6) NULL',
        'lng'           => 'DECIMAL(9,6) NULL',
    ];
    foreach ($colonne as $col => $def) {
        if (!Migrator::columnExists('properties', $col)) $pdo->exec("ALTER TABLE properties ADD COLUMN $col $def");
    }

    if (!Migrator::tableExists('property_contacts')) {
        $id = Db::isMysql() ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $pdo->exec("CREATE TABLE property_contacts (
            id          $id,
            property_id INTEGER NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
            name        VARCHAR(120) NOT NULL DEFAULT '',
            role        VARCHAR(20) NOT NULL DEFAULT 'host',
            phone       VARCHAR(40) NOT NULL DEFAULT '',
            whatsapp    INTEGER NOT NULL DEFAULT 0,
            position    INTEGER NOT NULL DEFAULT 0
        )");
        $pdo->exec('CREATE INDEX idx_contacts_property ON property_contacts(property_id)');
    }

    foreach (Db::all('SELECT id, host_name, host_phone, host_whatsapp FROM properties') as $p) {
        if (Db::val('SELECT COUNT(*) FROM property_contacts WHERE property_id = ?', [$p['id']], 0)) continue;
        foreach (Conversione::contatti((string) $p['host_name'], (string) $p['host_phone'], (string) $p['host_whatsapp']) as $i => $c) {
            Db::insert('property_contacts', $c + ['property_id' => $p['id'], 'position' => $i]);
        }
    }
};
