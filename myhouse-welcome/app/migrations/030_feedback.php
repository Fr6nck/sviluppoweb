<?php
/**
 * 030 — «Cosa ti ha fermato?» (7A): la risposta di chi si è registrato e non ha mai pubblicato.
 *
 * feedback: una riga per account (la risposta più recente vince), con il motivo scelto con un
 * clic dall'email (prezzo, tempo, funzione, altro) e il testo facoltativo di «Raccontaci».
 * MySQL prima della 8.0.13 non accetta un valore predefinito su TEXT: lì la colonna è NULL.
 *
 * Solo aggiunte, SQLite e MySQL.
 */

use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    if (Migrator::tableExists('feedback')) return;
    $pk = Db::isMysql() ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $pdo->exec("CREATE TABLE feedback (
        id $pk,
        account_id INTEGER NOT NULL,
        motivo VARCHAR(20) NOT NULL,
        testo TEXT NULL,
        created_at VARCHAR(25) NOT NULL,
        updated_at VARCHAR(25) NULL
    )");
    $pdo->exec('CREATE UNIQUE INDEX idx_feedback_account ON feedback(account_id)');
};
