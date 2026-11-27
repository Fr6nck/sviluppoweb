<?php
/**
 * 034 — Invita un amico per tutti gli abbonati e giudizi dei clienti sulla piattaforma (7D).
 *
 * accounts.invite_preview: l'anteprima dell'invito (/i/CODICE e WhatsApp) mostra copertina e nome
 *   della struttura di chi invita (1, di serie) oppure è generica (0).
 * accounts.giudizio_chiuso_at / giudizio_chiusure: il riquadro «Ti sta piacendo MyHouse Welcome?»
 *   chiuso dall'host (torna una volta sola, dopo 60 giorni).
 * testimonials: le testimonianze scritte dall'amministratore restano come sono (account_id NULL);
 *   i giudizi dei clienti hanno account, struttura, voto, foto scelta, stato e consenso.
 *   city: la città della struttura al momento del consenso (il consenso la nomina).
 *
 * MySQL prima della 8.0.13 non accetta un valore predefinito su TEXT: lì consent_text è NULL.
 * Solo aggiunte, SQLite e MySQL.
 */

use MHW\Migrator;

return function (\PDO $pdo): void {
    $colonne = [
        'accounts' => [
            'invite_preview' => 'INTEGER NOT NULL DEFAULT 1',
            'giudizio_chiuso_at' => 'VARCHAR(25) NULL',
            'giudizio_chiusure' => 'INTEGER NOT NULL DEFAULT 0',
        ],
        'testimonials' => [
            'account_id' => 'INTEGER NULL',
            'property_id' => 'INTEGER NULL',
            'city' => "VARCHAR(120) NOT NULL DEFAULT ''",
            'rating' => 'INTEGER NULL',
            'photo_source' => "VARCHAR(12) NOT NULL DEFAULT 'nessuna'",
            'status' => "VARCHAR(12) NOT NULL DEFAULT ''",
            'consent_at' => 'VARCHAR(25) NULL',
            'consent_text' => 'TEXT NULL',
            'withdrawn_at' => 'VARCHAR(25) NULL',
        ],
    ];
    foreach ($colonne as $tabella => $elenco) {
        if (!Migrator::tableExists($tabella)) continue;
        foreach ($elenco as $nome => $tipo) {
            if (!Migrator::columnExists($tabella, $nome)) $pdo->exec("ALTER TABLE $tabella ADD COLUMN $nome $tipo");
        }
    }
};
