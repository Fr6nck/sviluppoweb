<?php
/**
 * 066 — Via il piano di prova «Prova» (creato dalla 065 del pacchetto del 22 ottobre, poi ritirata). Se nessuno l'ha
 * comprato si cancella con la sua versione e le sue funzioni; se un ordine o un abbonamento lo usano, resta ma spento
 * (active = 0): non si vede e non si compra più. Chi ha un database senza quel piano non cambia niente.
 * Solo questo, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */
use MHW\Db;

return function (\PDO $pdo): void {
    $p = Db::one("SELECT id FROM packages WHERE code = 'prova'");
    if (!$p) return;
    $usato = (int) Db::val('SELECT COUNT(*) FROM orders WHERE package_version_id IN (SELECT id FROM package_versions WHERE package_id = ?)', [$p['id']], 0)
           + (int) Db::val('SELECT COUNT(*) FROM subscriptions WHERE package_version_id IN (SELECT id FROM package_versions WHERE package_id = ?)', [$p['id']], 0);
    if ($usato > 0) { Db::update('packages', ['active' => 0, 'public' => 0], 'id = :id', ['id' => (int) $p['id']]); return; }
    Db::run("UPDATE accounts SET intended_package_version_id = NULL WHERE intended_package_version_id IN (SELECT id FROM package_versions WHERE package_id = ?)", [$p['id']]);
    Db::run('DELETE FROM package_features WHERE package_version_id IN (SELECT id FROM package_versions WHERE package_id = ?)', [$p['id']]);
    Db::run('DELETE FROM package_versions WHERE package_id = ?', [$p['id']]);
    Db::run('DELETE FROM packages WHERE id = ?', [$p['id']]);
};
