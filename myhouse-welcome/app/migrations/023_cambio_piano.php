<?php
/**
 * 023 — Cambio di piano per chi ha già un abbonamento (fase 6H).
 *
 * 1. subscriptions.next_*: il passaggio a un piano più economico, programmato per
 *    il prossimo rinnovo (versione, strutture, scelte di cosa tenere, quando è stato chiesto).
 * 2. orders.kind: 'new' (un abbonamento nuovo, come finora) o 'change' (la differenza
 *    pagata per salire di piano); from_* dice da dove si partiva, applied_at quando il
 *    cambio è stato applicato davvero (anche su Stripe).
 *
 * Solo aggiunte: niente si converte, niente si cancella.
 */

use MHW\Migrator;

return function (\PDO $pdo): void {
    $colonne = [
        'subscriptions' => ['next_package_version_id' => 'INTEGER NULL', 'next_quantity' => 'INTEGER NULL',
                            'next_choices' => 'TEXT NULL', 'next_requested_at' => 'VARCHAR(25) NULL'],
        'orders' => ['kind' => "VARCHAR(10) NOT NULL DEFAULT 'new'", 'from_package_version_id' => 'INTEGER NULL',
                     'from_quantity' => 'INTEGER NULL', 'applied_at' => 'VARCHAR(25) NULL'],
    ];
    foreach ($colonne as $tabella => $cc) {
        foreach ($cc as $nome => $tipo) {
            if (!Migrator::columnExists($tabella, $nome)) $pdo->exec("ALTER TABLE $tabella ADD COLUMN $nome $tipo");
        }
    }
};
