<?php
/**
 * 059 — 9O · Rimborsi da qualunque parte, addebito SEPA «in verifica», email «La tua guida è online».
 *
 * 1. rimborsi.origine: da dove è partito il rimborso (admin, stripe, adamo); rimborsi.nota_credito_at: quando l'amministrazione
 *    ha spuntato «nota di credito fatta in Adamo» (per i rimborsi partiti da Adamo la fa Adamo, e si segna subito).
 * 2. orders.in_verifica_at: quando un pagamento con addebito SEPA è entrato «in verifica» (Stripe aspetta la banca);
 *    orders.online_email_at: quando è partita l'email «La tua guida è online» per quel pagamento (una volta sola).
 *
 * Solo aggiunte, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */
use MHW\Migrator;

return function (\PDO $pdo): void {
    $colonne = [
        'rimborsi' => ['origine' => "VARCHAR(10) NOT NULL DEFAULT 'admin'", 'nota_credito_at' => 'VARCHAR(25) NULL'],
        'orders'   => ['in_verifica_at' => 'VARCHAR(25) NULL', 'online_email_at' => 'VARCHAR(25) NULL'],
    ];
    foreach ($colonne as $tabella => $nuove) {
        if (!Migrator::tableExists($tabella)) continue;
        foreach ($nuove as $col => $tipo) {
            if (!Migrator::columnExists($tabella, $col)) $pdo->exec("ALTER TABLE $tabella ADD COLUMN $col $tipo");
        }
    }
};
