<?php
/**
 * 044 — 9B · «Ti piace MyHouse Welcome?»: il giudizio con lo sconto.
 *
 * accounts: giudizio_adesione (null | pagamento | pannello), giudizio_adesione_at, giudizio_sconto_stato
 *   (promesso | applicato_primo_anno | da_applicare_rinnovo | applicato_rinnovo | nessuno), giudizio_sconto_at.
 * testimonials: show_cover, show_photo, show_logo (le immagini scelte dal cliente; photo_source resta per i vecchi),
 *   home_visible (la nostra decisione: in home sì/no), published_at.
 * I giudizi già pubblicati con il consenso (7D) restano in home: home_visible = 1, published_at = created_at,
 * e la foto scelta allora diventa show_photo / show_cover.
 *
 * Solo aggiunte, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */
use MHW\Migrator;

return function (\PDO $pdo): void {
    $colonne = [
        'accounts' => [
            'giudizio_adesione' => 'VARCHAR(12) NULL',
            'giudizio_adesione_at' => 'VARCHAR(25) NULL',
            'giudizio_sconto_stato' => "VARCHAR(24) NOT NULL DEFAULT 'nessuno'",
            'giudizio_sconto_at' => 'VARCHAR(25) NULL',
        ],
        'testimonials' => [
            'show_cover' => 'INTEGER NOT NULL DEFAULT 0',
            'show_photo' => 'INTEGER NOT NULL DEFAULT 0',
            'show_logo' => 'INTEGER NOT NULL DEFAULT 0',
            'home_visible' => 'INTEGER NOT NULL DEFAULT 0',
            'published_at' => 'VARCHAR(25) NULL',
        ],
    ];
    $nuovaHome = Migrator::tableExists('testimonials') && !Migrator::columnExists('testimonials', 'home_visible');
    foreach ($colonne as $tabella => $elenco) {
        if (!Migrator::tableExists($tabella)) continue;
        foreach ($elenco as $nome => $tipo) {
            if (!Migrator::columnExists($tabella, $nome)) $pdo->exec("ALTER TABLE $tabella ADD COLUMN $nome $tipo");
        }
    }
    if ($nuovaHome && Migrator::columnExists('testimonials', 'account_id')) {
        $pdo->exec("UPDATE testimonials SET home_visible = 1, published_at = created_at WHERE account_id IS NOT NULL AND status = 'pubblicata' AND visible = 1
                    AND consent_at IS NOT NULL AND withdrawn_at IS NULL");
        $pdo->exec("UPDATE testimonials SET show_photo = 1 WHERE account_id IS NOT NULL AND photo_source = 'profilo'");
        $pdo->exec("UPDATE testimonials SET show_cover = 1 WHERE account_id IS NOT NULL AND photo_source = 'copertina'");
    }
};
