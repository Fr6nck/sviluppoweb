<?php
/**
 * 032 — La guida e l'editor (7C).
 *
 * properties.host_welcome: «Due parole di benvenuto» dell'host, una per lingua (JSON {it: …, en: …}),
 *   mostrate nel foglio che si apre dal tondo con la foto dell'host. Vuoto = il testo di serie.
 * properties.direct_discount: lo sconto per chi torna a prenotare direttamente («10%», «15 €»).
 * translation_hashes: l'impronta (sha1) del testo originale quando una traduzione è stata approvata
 *   o salvata: se l'originale cambia, il campo si segna «Il testo italiano è cambiato».
 * novita_viste: le novità che un account ha chiuso (la fascia nel pannello) o visto («Ho visto»).
 *
 * MySQL prima della 8.0.13 non accetta un valore predefinito su TEXT: lì la colonna è NULL.
 * Solo aggiunte, SQLite e MySQL.
 */

use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    $pk = Db::isMysql() ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    if (!Migrator::columnExists('properties', 'host_welcome')) $pdo->exec('ALTER TABLE properties ADD COLUMN host_welcome TEXT NULL');
    if (!Migrator::columnExists('properties', 'direct_discount')) $pdo->exec("ALTER TABLE properties ADD COLUMN direct_discount VARCHAR(40) NOT NULL DEFAULT ''");
    if (!Migrator::tableExists('translation_hashes')) {
        $pdo->exec("CREATE TABLE translation_hashes (
            id $pk,
            target_type VARCHAR(10) NOT NULL,
            target_id INTEGER NOT NULL,
            locale VARCHAR(5) NOT NULL,
            field_path VARCHAR(190) NOT NULL,
            source_hash VARCHAR(40) NOT NULL,
            created_at VARCHAR(25) NOT NULL
        )");
        $pdo->exec('CREATE UNIQUE INDEX idx_thash_campo ON translation_hashes(target_type, target_id, locale, field_path)');
    }
    if (!Migrator::tableExists('novita_viste')) {
        $pdo->exec("CREATE TABLE novita_viste (
            id $pk,
            account_id INTEGER NOT NULL,
            novita_id VARCHAR(60) NOT NULL,
            created_at VARCHAR(25) NOT NULL
        )");
        $pdo->exec('CREATE UNIQUE INDEX idx_novita_viste ON novita_viste(account_id, novita_id)');
    }
};
