<?php
/**
 * 043 — 9A · Portfolio progressivo, richieste di preventivo, sconti sommati.
 *
 * 1. Una versione nuova del Portfolio, una volta sola: prima struttura 117 €, 2ª +60 €, dalla 3ª alla 5ª
 *    +50 € l'una, dalla 6ª alla 10ª +40 € l'una; da 2 a 10 strutture (oltre si fa un preventivo). I Price ID
 *    di Stripe restano vuoti: i Price nuovi li crea il sito alla prima vendita. Le funzioni si copiano dalla
 *    versione di prima. Chi ha già un Portfolio resta sulla sua versione (regola delle versioni).
 * 2. preventivi: le richieste di preventivo per più di 10 strutture (nome, email, telefono, strutture,
 *    messaggio, lingua, account se loggato, stato). Si conservano 12 mesi (Registri::pulisciDati).
 * 3. orders.sconti: la composizione degli sconti del primo anno («invito:5,giudizio:5»), per il webhook.
 *
 * Solo aggiunte, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */

use MHW\{Db, Migrator, Support};

return function (\PDO $pdo): void {
    $pk = Db::isMysql() ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';

    // 1. Il Portfolio progressivo 2026 (9A): se la versione in vendita è già questa, niente.
    $scaglioni = [['da' => 2, 'cents' => 6000], ['da' => 3, 'cents' => 5000], ['da' => 6, 'cents' => 4000]];
    $pf = Db::one("SELECT pv.* FROM package_versions pv JOIN packages p ON p.id = pv.package_id WHERE p.code = 'portfolio' AND pv.is_current = 1");
    $giaFatta = $pf && (int) $pf['price_cents'] === 11700 && (int) $pf['extra_price_cents'] === 6000 && (int) $pf['max_quantity'] === 10
                && json_decode((string) $pf['extra_tiers'], true) === $scaglioni;
    if ($pf && (int) $pf['per_property'] === 1 && !$giaFatta && Migrator::columnExists('package_versions', 'extra_tiers')) {
        $next = (int) Db::val('SELECT COALESCE(MAX(version),0)+1 FROM package_versions WHERE package_id = ?', [$pf['package_id']], 1);
        Db::run('UPDATE package_versions SET is_current = 0 WHERE package_id = ?', [$pf['package_id']]);
        $nuova = Db::insert('package_versions', [
            'package_id' => $pf['package_id'], 'version' => $next, 'price_cents' => 11700, 'currency' => $pf['currency'],
            'interval_unit' => $pf['interval_unit'], 'is_current' => 1, 'sold_count' => 0, 'created_at' => Support::now(),
            'stripe_price_id' => '', 'stripe_extra_price_id' => '',
            'per_property' => 1, 'extra_price_cents' => 6000, 'min_quantity' => 2, 'max_quantity' => 10,
            'extra_tiers' => json_encode($scaglioni),
        ]);
        foreach (Db::all('SELECT feature_id, value FROM package_features WHERE package_version_id = ?', [$pf['id']]) as $f) {
            Db::insert('package_features', ['package_version_id' => $nuova, 'feature_id' => $f['feature_id'], 'value' => $f['value']]);
        }
    }

    // 2. Le richieste di preventivo.
    if (!Migrator::tableExists('preventivi')) {
        $pdo->exec("CREATE TABLE preventivi (
            id $pk,
            account_id INTEGER NULL,
            nome VARCHAR(120) NOT NULL DEFAULT '',
            email VARCHAR(190) NOT NULL DEFAULT '',
            telefono VARCHAR(40) NOT NULL DEFAULT '',
            strutture INTEGER NOT NULL DEFAULT 0,
            messaggio TEXT NULL,
            lingua VARCHAR(5) NOT NULL DEFAULT 'it',
            stato VARCHAR(12) NOT NULL DEFAULT 'nuovo',
            created_at VARCHAR(25) NOT NULL
        )");
        $pdo->exec('CREATE INDEX idx_preventivi_data ON preventivi(created_at)');
    }

    // 3. Gli sconti del primo anno, come si sono sommati.
    if (!Migrator::columnExists('orders', 'sconti')) $pdo->exec("ALTER TABLE orders ADD COLUMN sconti VARCHAR(120) NOT NULL DEFAULT ''");
};
