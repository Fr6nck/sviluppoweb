<?php
/**
 * 061 — 9P · L'email «Fino al 55%» 7 giorni dopo l'attivazione: accounts.email_sconti_at, quando è partita (una volta sola
 * per account; vuota = non ancora). Solo aggiunte, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */
use MHW\Migrator;

return function (\PDO $pdo): void {
    if (Migrator::tableExists('accounts') && !Migrator::columnExists('accounts', 'email_sconti_at')) {
        $pdo->exec('ALTER TABLE accounts ADD COLUMN email_sconti_at VARCHAR(25) NULL');
    }
};
