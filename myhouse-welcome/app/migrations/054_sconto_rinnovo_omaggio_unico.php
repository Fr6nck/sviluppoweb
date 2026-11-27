<?php
/**
 * 054 — 9N · Un codice omaggio prenotato da un solo account: indice UNICO su accounts.codice_omaggio_id (i NULL restano
 * liberi, su SQLite e MySQL). Prima si tolgono i doppioni (resta all'account che l'ha prenotato per primo; agli altri si
 * toglie la prenotazione, mai un codice già usato). Lo sconto unico sul rinnovo (mhw-rinnovo-N) non tocca il database.
 * Solo aggiunte. Una seconda esecuzione non cambia niente.
 */
use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    if (!Migrator::columnExists('accounts', 'codice_omaggio_id')) return;
    foreach (Db::all('SELECT codice_omaggio_id AS c, MIN(id) AS tiene FROM accounts WHERE codice_omaggio_id IS NOT NULL GROUP BY codice_omaggio_id HAVING COUNT(*) > 1') as $r) {
        $usato = (int) Db::val('SELECT usato_da_account_id FROM codici_omaggio WHERE id = ?', [(int) $r['c']], 0);
        $tiene = $usato > 0 ? $usato : (int) $r['tiene'];
        Db::run('UPDATE accounts SET codice_omaggio_id = NULL WHERE codice_omaggio_id = ? AND id <> ?', [(int) $r['c'], $tiene]);
    }
    try { $pdo->exec('CREATE UNIQUE INDEX accounts_codice_omaggio_unico ON accounts (codice_omaggio_id)'); }
    catch (\PDOException $e) { if (!str_contains(strtolower($e->getMessage()), 'exist') && !str_contains($e->getMessage(), 'Duplicate key name')) throw $e; }
};
