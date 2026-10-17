<?php
/**
 * 033 — Testi e foto del sito (Amministrazione → Sistema → «Testi e foto»).
 *
 * site_texts: una riga per chiave, solo per i testi cambiati dall'amministratore; le chiavi
 * che mancano usano i valori di serie di TestiSito::catalogo(). Le foto caricate stanno
 * nell'archivio (disco o S3) e qui c'è solo il riferimento «driver:chiave» (img.*).
 * La tabella vuota va già bene: la home resta com'è.
 *
 * MySQL prima della 8.0.13 non accetta un valore predefinito su TEXT: lì la colonna è
 * solo NOT NULL (TestiSito::set scrive sempre il valore). Solo aggiunte, SQLite e MySQL.
 */

use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    if (Migrator::tableExists('site_texts')) return;
    $valore = Db::isMysql() ? 'TEXT NOT NULL' : "TEXT NOT NULL DEFAULT ''";
    $pdo->exec("CREATE TABLE site_texts (
        chiave VARCHAR(80) NOT NULL PRIMARY KEY,
        valore $valore,
        updated_at VARCHAR(25) NULL
    )");
};
