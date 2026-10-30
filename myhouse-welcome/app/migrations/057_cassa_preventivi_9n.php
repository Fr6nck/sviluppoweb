<?php
/**
 * 057 — 9N · Cassa e preventivi.
 *
 * 1. orders.iva_cents: l'IVA di un pagamento segnato a mano (bonifico), scritta dall'amministrazione; per i pagamenti
 *    Stripe resta NULL e l'IVA si legge dall'evento di Stripe. orders.riferimento: il riferimento del bonifico (CRO, causale).
 * 2. preventivi: l'importo proposto, una nota interna e la data in cui ricontattare. Lo storico sta nel registro azioni.
 *
 * Solo aggiunte, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */
use MHW\Migrator;

return function (\PDO $pdo): void {
    $colonne = [
        'orders'     => ['iva_cents' => 'INTEGER NULL', 'riferimento' => "VARCHAR(120) NOT NULL DEFAULT ''"],
        'preventivi' => ['importo_cents' => 'INTEGER NULL', 'nota' => 'TEXT NULL', 'ricontatta_il' => 'VARCHAR(10) NULL', 'aggiornato_at' => 'VARCHAR(25) NULL'],
    ];
    foreach ($colonne as $tabella => $nuove) {
        if (!Migrator::tableExists($tabella)) continue;
        foreach ($nuove as $col => $tipo) {
            if (!Migrator::columnExists($tabella, $col)) $pdo->exec("ALTER TABLE $tabella ADD COLUMN $col $tipo");
        }
    }
};
