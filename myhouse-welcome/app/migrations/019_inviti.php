<?php
/**
 * 019 — Invita un amico.
 *
 * 1. accounts.referral_code: il codice personale di invito (nasce la prima volta
 *    che serve). accounts.referral_applied_percent: lo sconto inviti che in questo
 *    momento è sull'abbonamento Stripe (0 = nessuno).
 * 2. discount_codes.sistema: la riga creata da Inviti per lo sconto dell'amico.
 *    Non si vede in Amministrazione → Codici sconto e vale solo per chi è stato invitato.
 * 3. referrals: chi ha invitato chi. Un amico ha un solo invito (indice unico).
 *    Stati: registrato → valido (ha pagato) → usato (scontato a un rinnovo);
 *    oltre (ha pagato, ma il tetto era già raggiunto); annullato.
 *
 * Solo aggiunte: niente si converte, niente si cancella.
 */

use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    $pk = Db::isMysql() ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';

    if (!Migrator::columnExists('accounts', 'referral_code')) {
        $pdo->exec('ALTER TABLE accounts ADD COLUMN referral_code VARCHAR(12) NULL');
        $pdo->exec('CREATE UNIQUE INDEX idx_accounts_referral_code ON accounts(referral_code)');
    }
    if (!Migrator::columnExists('accounts', 'referral_applied_percent')) {
        $pdo->exec('ALTER TABLE accounts ADD COLUMN referral_applied_percent INTEGER NOT NULL DEFAULT 0');
    }
    if (!Migrator::columnExists('discount_codes', 'sistema')) {
        $pdo->exec('ALTER TABLE discount_codes ADD COLUMN sistema INTEGER NOT NULL DEFAULT 0');
    }
    if (!Migrator::tableExists('referrals')) {
        $pdo->exec("CREATE TABLE referrals (
            id $pk,
            referrer_account_id INTEGER NOT NULL,
            friend_account_id INTEGER NOT NULL,
            status VARCHAR(12) NOT NULL DEFAULT 'registrato',
            order_id INTEGER NULL,
            created_at VARCHAR(25) NOT NULL,
            qualified_at VARCHAR(25) NULL,
            used_at VARCHAR(25) NULL,
            used_ref VARCHAR(80) NOT NULL DEFAULT ''
        )");
        $pdo->exec('CREATE UNIQUE INDEX idx_referrals_friend ON referrals(friend_account_id)');
        $pdo->exec('CREATE INDEX idx_referrals_referrer ON referrals(referrer_account_id, status)');
    }
};
