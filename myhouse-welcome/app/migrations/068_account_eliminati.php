<?php
/**
 * 068 — Elimina account da Amministrazione: la data in cui un account con pagamenti veri è stato chiuso. L'account resta
 * (anonimo) solo per i dati di fatturazione e i pagamenti, che la legge chiede di conservare 10 anni; tutto il resto si
 * cancella (EliminaAccount). Solo un'aggiunta, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */
use MHW\Migrator;

return function (\PDO $pdo): void {
    if (!Migrator::columnExists('accounts', 'eliminato_at')) $pdo->exec('ALTER TABLE accounts ADD COLUMN eliminato_at VARCHAR(25) NULL');
};
