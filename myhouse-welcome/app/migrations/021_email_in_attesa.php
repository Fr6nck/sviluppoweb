<?php
/**
 * 021 — Cambio dell'email dall'account.
 *
 * users.pending_email: l'indirizzo nuovo, finché non si apre il link di conferma
 * che gli abbiamo mandato. Fino ad allora vale quello vecchio.
 * Solo un'aggiunta: niente si converte, niente si cancella.
 */

use MHW\Migrator;

return function (\PDO $pdo): void {
    if (!Migrator::columnExists('users', 'pending_email')) $pdo->exec('ALTER TABLE users ADD COLUMN pending_email VARCHAR(190) NULL');
};
