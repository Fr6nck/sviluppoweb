<?php
/**
 * 064 — Inviti: il motivo di un invito annullato (stessi dati di fatturazione, annullato dall'amministrazione), così in
 * Amministrazione → Inviti e nella scheda del cliente si vede perché un amico non ha acceso il rettangolo.
 * Solo un'aggiunta, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */
use MHW\Migrator;

return function (\PDO $pdo): void {
    if (Migrator::tableExists('referrals') && !Migrator::columnExists('referrals', 'motivo')) {
        $pdo->exec("ALTER TABLE referrals ADD COLUMN motivo VARCHAR(255) NOT NULL DEFAULT ''");
    }
};
