<?php
/**
 * 024 — Strumenti di gestione per l'amministrazione.
 *
 * accounts.admin_note / admin_note_at: una nota interna sul cliente (telefonate,
 * accordi, solleciti), visibile solo in amministrazione.
 *
 * Solo aggiunte: niente si converte, niente si cancella.
 */

use MHW\Migrator;

return function (\PDO $pdo): void {
    foreach (['admin_note' => 'TEXT NULL', 'admin_note_at' => 'VARCHAR(25) NULL'] as $nome => $tipo) {
        if (!Migrator::columnExists('accounts', $nome)) $pdo->exec("ALTER TABLE accounts ADD COLUMN $nome $tipo");
    }
};
