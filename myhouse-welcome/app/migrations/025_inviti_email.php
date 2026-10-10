<?php
/**
 * 025 — Invita un amico per email, dal pannello del cliente.
 *
 * referral_invites: gli inviti mandati dal sito a un indirizzo scritto dal cliente.
 * L'indirizzo non si conserva: solo l'impronta (sha256 dell'indirizzo in minuscolo),
 * per non mandarlo due volte e per riconoscere l'amico quando si registra, e una
 * forma mascherata da mostrare al cliente (m•••@gmail.com).
 *
 * Solo aggiunte, SQLite e MySQL.
 */

use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    $pk = Db::isMysql() ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    if (!Migrator::tableExists('referral_invites')) {
        $pdo->exec("CREATE TABLE referral_invites (
            id $pk,
            account_id INTEGER NOT NULL,
            email_hash VARCHAR(64) NOT NULL,
            email_mask VARCHAR(190) NOT NULL DEFAULT '',
            sent_at VARCHAR(25) NOT NULL
        )");
        $pdo->exec('CREATE INDEX idx_referral_invites_account ON referral_invites(account_id, email_hash)');
    }
};
