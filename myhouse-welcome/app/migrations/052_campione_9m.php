<?php
/**
 * 052 — 9M · La «scheda campione» di Portfolio e il parcheggio copiabile «da controllare».
 *
 * 1. properties.campione (0/1): la guida campione dell'account. Una sola per account: lo garantisce Campione::rendi()
 *    (prima toglie le altre, nella stessa transazione) e un controllo qui sotto che azzera i doppioni.
 * 2. sections:
 *    - versione (da 1): sale a ogni salvataggio di una sezione della campione; versione_at, la data;
 *    - origine_sezione_id, origine_property_id, origine_versione: da quale sezione (e guida) viene una copia e da quale
 *      versione; restano anche se la sezione d'origine viene eliminata (così la gemella sa di avere una sezione «in più»);
 *    - personalizzata_at: l'ultima modifica fatta a mano nella gemella dopo la copia (non si preseleziona l'aggiornamento);
 *    - da_controllare (0/1): il parcheggio copiato da un'altra guida (indirizzo diverso), finché non si salva la sezione.
 * 3. sezioni_versioni: lo storico delle copie sovrascritte o tolte nelle gemelle (e delle sezioni eliminate nella campione),
 *    con il testo intero (JSON: dati, traduzioni, luoghi); si conserva 30 giorni (Campione::pulisci, dai Richiami).
 *
 * Solo aggiunte, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */
use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    $pk = Db::isMysql() ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    if (!Migrator::columnExists('properties', 'campione')) $pdo->exec('ALTER TABLE properties ADD COLUMN campione INTEGER NOT NULL DEFAULT 0');
    foreach (['versione' => 'INTEGER NOT NULL DEFAULT 1', 'versione_at' => 'VARCHAR(25) NULL', 'origine_sezione_id' => 'INTEGER NULL',
              'origine_property_id' => 'INTEGER NULL', 'origine_versione' => 'INTEGER NULL', 'personalizzata_at' => 'VARCHAR(25) NULL',
              'da_controllare' => 'INTEGER NOT NULL DEFAULT 0'] as $c => $tipo) {
        if (!Migrator::columnExists('sections', $c)) $pdo->exec("ALTER TABLE sections ADD COLUMN $c $tipo");
    }
    if (!Migrator::tableExists('sezioni_versioni')) {
        $pdo->exec("CREATE TABLE sezioni_versioni (
            id $pk,
            section_id INTEGER NOT NULL,
            property_id INTEGER NOT NULL,
            kind VARCHAR(30) NOT NULL DEFAULT '',
            titolo VARCHAR(200) NOT NULL DEFAULT '',
            versione INTEGER NOT NULL DEFAULT 1,
            motivo VARCHAR(30) NOT NULL DEFAULT 'sovrascritta',
            testo TEXT NOT NULL,
            created_at VARCHAR(25) NOT NULL
        )");
        $pdo->exec('CREATE INDEX sezioni_versioni_sezione ON sezioni_versioni (section_id)');
        $pdo->exec('CREATE INDEX sezioni_versioni_struttura ON sezioni_versioni (property_id, motivo)');
    }
    // Una sola campione per account (se qualcuno ne avesse due, resta la prima).
    foreach (Db::all('SELECT account_id, MIN(id) AS tiene FROM properties WHERE campione = 1 GROUP BY account_id HAVING COUNT(*) > 1') as $r) {
        Db::run('UPDATE properties SET campione = 0 WHERE account_id = ? AND campione = 1 AND id <> ?', [(int) $r['account_id'], (int) $r['tiene']]);
    }
};
