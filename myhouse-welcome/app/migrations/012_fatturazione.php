<?php
/**
 * 012 — Dati di fatturazione italiani sull'account.
 *
 * Servono prima del primo pagamento: tipo (azienda o professionista / privato),
 * intestatario, partita IVA, codice fiscale, codice destinatario SDI o PEC,
 * indirizzo. Tutte colonne facoltative con un valore vuoto: gli account di
 * prima restano validi e li completano al prossimo pagamento. Le fatture non
 * le genera l'applicazione: i dati vanno a Stripe come metadati del cliente.
 */

use MHW\Migrator;

return function (\PDO $pdo): void {
    $colonne = [
        'billing_type'     => "VARCHAR(10) NOT NULL DEFAULT ''",
        'billing_name'     => "VARCHAR(160) NOT NULL DEFAULT ''",
        'vat'              => "VARCHAR(11) NOT NULL DEFAULT ''",
        'cf'               => "VARCHAR(16) NOT NULL DEFAULT ''",
        'sdi'              => "VARCHAR(7) NOT NULL DEFAULT ''",
        'pec'              => "VARCHAR(190) NOT NULL DEFAULT ''",
        'billing_address'  => "VARCHAR(255) NOT NULL DEFAULT ''",
        'billing_postal'   => "VARCHAR(5) NOT NULL DEFAULT ''",
        'billing_city'     => "VARCHAR(120) NOT NULL DEFAULT ''",
        'billing_province' => "VARCHAR(2) NOT NULL DEFAULT ''",
    ];
    foreach ($colonne as $col => $def) {
        if (!Migrator::columnExists('accounts', $col)) $pdo->exec("ALTER TABLE accounts ADD COLUMN $col $def");
    }
};
