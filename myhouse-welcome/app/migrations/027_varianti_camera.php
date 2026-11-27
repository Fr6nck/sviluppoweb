<?php
/**
 * 027 — Varianti camera.
 *
 * Una guida è un'unità ricettiva (un indirizzo, un CIN). Un B&B o un affittacamere con
 * più camere ha una guida sola; le camere che hanno un Wi-Fi o istruzioni di accesso
 * diverse diventano «varianti camera»: stesso contenuto, il loro Wi-Fi, le loro istruzioni,
 * un QR e un link propri. Ogni variante è una voce dell'abbonamento (prezzo in config,
 * `varianti.prezzo_cents`), con i piani che le permettono (Plus e Portfolio).
 *
 * room_variants: le varianti. access e note sono JSON per lingua ({"it": "…", "en": "…"}).
 *   token è quello del QR e del link (/q/{token}, /g/{slug}/c/{token}); non cambia mai.
 *   removed_at: tolta (non si cancella: i QR stampati portano alla guida senza variante).
 * subscriptions.provider_variant_item_id: la voce Stripe delle varianti.
 * features.room_variants: chi può aggiungerle (1 per Plus e Portfolio, anche nelle versioni
 *   già vendute: è un acquisto in più, non cambia quello che il piano comprende).
 *
 * Solo aggiunte, SQLite e MySQL.
 */

use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    $pk = Db::isMysql() ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    if (!Migrator::tableExists('room_variants')) {
        $pdo->exec("CREATE TABLE room_variants (
            id $pk,
            property_id INTEGER NOT NULL,
            name VARCHAR(80) NOT NULL DEFAULT '',
            token VARCHAR(24) NOT NULL,
            wifi_ssid VARCHAR(80) NOT NULL DEFAULT '',
            wifi_password VARCHAR(120) NOT NULL DEFAULT '',
            access TEXT NULL,
            note TEXT NULL,
            position INTEGER NOT NULL DEFAULT 0,
            created_at VARCHAR(25) NOT NULL,
            removed_at VARCHAR(25) NULL
        )");
        $pdo->exec('CREATE INDEX idx_room_variants_property ON room_variants(property_id)');
        $pdo->exec('CREATE UNIQUE INDEX idx_room_variants_token ON room_variants(token)');
    }
    if (!Migrator::columnExists('subscriptions', 'provider_variant_item_id')) {
        $pdo->exec("ALTER TABLE subscriptions ADD COLUMN provider_variant_item_id VARCHAR(80) NOT NULL DEFAULT ''");
    }
    if (!Db::val("SELECT id FROM features WHERE code = 'room_variants'")) {
        Db::insert('features', ['code' => 'room_variants', 'label' => 'Varianti camera (a parte)', 'kind' => 'bool', 'default_value' => '0']);
    }
    $fid = (int) Db::val("SELECT id FROM features WHERE code = 'room_variants'");
    foreach (Db::all('SELECT pv.id, p.code FROM package_versions pv JOIN packages p ON p.id = pv.package_id') as $v) {
        if (Db::val('SELECT 1 FROM package_features WHERE package_version_id = ? AND feature_id = ?', [$v['id'], $fid])) continue;
        $si = $v['code'] === 'plus' || str_starts_with((string) $v['code'], 'portfolio');
        Db::insert('package_features', ['package_version_id' => $v['id'], 'feature_id' => $fid, 'value' => $si ? '1' : '0']);
    }
};
