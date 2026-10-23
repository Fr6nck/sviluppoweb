<?php
/**
 * 039 — 7F: i clienti di prova.
 *
 * accounts.is_test: 1 = «Cliente di prova» (scheda cliente in Amministrazione). Fuori da Quadro, Vendite,
 * Prospetti, Scadenze e Marketing; nessun richiamo automatico né richiesta di giudizio.
 *
 * Solo aggiunte, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */

use MHW\Migrator;

return function (\PDO $pdo): void {
    if (!Migrator::columnExists('accounts', 'is_test')) {
        $pdo->exec('ALTER TABLE accounts ADD COLUMN is_test INTEGER NOT NULL DEFAULT 0');
    }
};
