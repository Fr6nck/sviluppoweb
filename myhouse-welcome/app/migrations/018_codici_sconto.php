<?php
/**
 * 018 — Codici sconto per il primo anno (fase 6E).
 *
 * 1. discount_codes: i codici creati dall'amministrazione, ognuno col suo coupon
 *    Stripe (duration=once: sconta solo la prima fattura, cioè il primo anno).
 * 2. discount_redemptions: un utilizzo per ordine pagato (indice unico su order_id:
 *    un webhook riconsegnato non conta due volte). Gli utilizzi si contano da qui.
 * 3. accounts.intended_discount_code_id: il codice applicato e non ancora pagato.
 *    orders.discount_code_id e orders.discount_cents: il codice dell'ordine e lo
 *    sconto davvero concesso (amount_cents resta il prezzo pieno).
 *
 * Solo aggiunte: niente si converte, niente si cancella.
 */

use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    $pk = Db::isMysql() ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';

    if (!Migrator::tableExists('discount_codes')) {
        $pdo->exec("CREATE TABLE discount_codes (
            id $pk,
            code VARCHAR(24) NOT NULL,
            kind VARCHAR(10) NOT NULL DEFAULT 'percent',
            value INTEGER NOT NULL DEFAULT 0,
            valid_from VARCHAR(10) NOT NULL,
            valid_until VARCHAR(10) NOT NULL,
            max_uses INTEGER NOT NULL DEFAULT 0,
            packages VARCHAR(255) NOT NULL DEFAULT '',
            note VARCHAR(255) NOT NULL DEFAULT '',
            active INTEGER NOT NULL DEFAULT 1,
            stripe_coupon_id VARCHAR(80) NOT NULL DEFAULT '',
            stripe_synced_at VARCHAR(25) NULL,
            created_at VARCHAR(25) NOT NULL
        )");
        $pdo->exec('CREATE UNIQUE INDEX idx_discount_codes_code ON discount_codes(code)');
    }
    if (!Migrator::tableExists('discount_redemptions')) {
        $pdo->exec("CREATE TABLE discount_redemptions (
            id $pk,
            discount_code_id INTEGER NOT NULL,
            account_id INTEGER NOT NULL,
            order_id INTEGER NOT NULL,
            discount_cents INTEGER NOT NULL DEFAULT 0,
            created_at VARCHAR(25) NOT NULL
        )");
        $pdo->exec('CREATE UNIQUE INDEX idx_discount_redemptions_order ON discount_redemptions(order_id)');
        $pdo->exec('CREATE INDEX idx_discount_redemptions_code ON discount_redemptions(discount_code_id)');
    }
    if (!Migrator::columnExists('accounts', 'intended_discount_code_id')) $pdo->exec('ALTER TABLE accounts ADD COLUMN intended_discount_code_id INTEGER NULL');
    if (!Migrator::columnExists('orders', 'discount_code_id')) $pdo->exec('ALTER TABLE orders ADD COLUMN discount_code_id INTEGER NULL');
    if (!Migrator::columnExists('orders', 'discount_cents')) $pdo->exec('ALTER TABLE orders ADD COLUMN discount_cents INTEGER NOT NULL DEFAULT 0');
};
