<?php
/**
 * 029 — SEO e GEO: le impostazioni di Amministrazione → «SEO e GEO».
 *
 * seo_settings: una riga per chiave (dominio, verifiche, titolo e descrizione delle pagine
 * pubbliche, dati dell'azienda, assistenti AI ammessi, testi di llms.txt). Le chiavi che
 * mancano usano i valori di serie di Seo::predefiniti(): la tabella vuota va già bene.
 *
 * MySQL prima della 8.0.13 non accetta un valore predefinito su una colonna TEXT: lì la
 * colonna è solo NOT NULL (Seo::set scrive sempre il valore). Solo aggiunte.
 */

use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    if (Migrator::tableExists('seo_settings')) return;
    $valore = Db::isMysql() ? 'TEXT NOT NULL' : "TEXT NOT NULL DEFAULT ''";
    $pdo->exec("CREATE TABLE seo_settings (
        chiave VARCHAR(80) NOT NULL PRIMARY KEY,
        valore $valore,
        updated_at VARCHAR(25) NULL
    )");
};
