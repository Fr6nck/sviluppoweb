<?php
/**
 * 014 — La guida che fa guadagnare l'host, le email di richiamo, le
 * testimonianze, il funnel senza cookie.
 *
 * 1. properties: link alle recensioni (Google, Booking, Airbnb, altro), link per
 *    la prenotazione diretta con il codice sconto, firma nascosta (sì/no).
 * 2. email_log: ogni email di richiamo una volta sola (account, tipo, riferimento).
 *    email_optout: i tipi che un account ha chiesto di non ricevere più.
 * 3. testimonials: le testimonianze, scritte dall'amministratore. Nessuna d'esempio.
 *    La foto sta nello storage (driver e chiave), senza riga in media: non è di un cliente.
 * 4. analytics_events.property_id diventa facoltativo: gli eventi del funnel
 *    (landing_view, signup…) non appartengono a una struttura. Su SQLite la
 *    tabella si ricostruisce con gli stessi dati; su MySQL basta MODIFY.
 *
 * Lo schema si scrive qui, non in un .sql, per la chiave autoincrementale che
 * si scrive diversa su SQLite e su MySQL.
 */

use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    $mysql = Db::isMysql();
    $pk = $mysql ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';

    $colonne = [
        'review_google'  => "VARCHAR(500) NOT NULL DEFAULT ''",
        'review_booking' => "VARCHAR(500) NOT NULL DEFAULT ''",
        'review_airbnb'  => "VARCHAR(500) NOT NULL DEFAULT ''",
        'review_other'   => "VARCHAR(500) NOT NULL DEFAULT ''",
        'direct_url'     => "VARCHAR(500) NOT NULL DEFAULT ''",
        'direct_code'    => "VARCHAR(60) NOT NULL DEFAULT ''",
        'hide_branding'  => 'INTEGER NOT NULL DEFAULT 0',
    ];
    foreach ($colonne as $col => $def) {
        if (!Migrator::columnExists('properties', $col)) $pdo->exec("ALTER TABLE properties ADD COLUMN $col $def");
    }

    if (!Migrator::tableExists('email_log')) {
        $pdo->exec("CREATE TABLE email_log (
            id $pk,
            account_id INTEGER NOT NULL,
            kind VARCHAR(40) NOT NULL,
            ref VARCHAR(60) NOT NULL DEFAULT '',
            token VARCHAR(64) NOT NULL DEFAULT '',
            sent_at VARCHAR(25) NOT NULL
        )");
        $pdo->exec('CREATE UNIQUE INDEX idx_email_log_unico ON email_log(account_id, kind, ref)');
        $pdo->exec('CREATE INDEX idx_email_log_token ON email_log(token)');
    }
    if (!Migrator::tableExists('email_optout')) {
        $pdo->exec("CREATE TABLE email_optout (
            id $pk,
            account_id INTEGER NOT NULL,
            kind VARCHAR(40) NOT NULL,
            created_at VARCHAR(25) NOT NULL
        )");
        $pdo->exec('CREATE UNIQUE INDEX idx_email_optout_unico ON email_optout(account_id, kind)');
    }
    if (!Migrator::tableExists('testimonials')) {
        $pdo->exec("CREATE TABLE testimonials (
            id $pk,
            name VARCHAR(120) NOT NULL,
            property_name VARCHAR(160) NOT NULL DEFAULT '',
            body TEXT NOT NULL,
            photo_storage VARCHAR(10) NOT NULL DEFAULT '',
            photo_key VARCHAR(255) NOT NULL DEFAULT '',
            visible INTEGER NOT NULL DEFAULT 0,
            position INTEGER NOT NULL DEFAULT 0,
            created_at VARCHAR(25) NOT NULL
        )");
    }

    // analytics_events.property_id facoltativo, senza perdere un evento.
    if ($mysql) {
        $pdo->exec('ALTER TABLE analytics_events MODIFY property_id INT NULL');
    } else {
        $sql = (string) Db::val("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'analytics_events'");
        if (preg_match('/property_id\s+INTEGER\s+NOT\s+NULL/i', $sql)) {
            $indici = array_column(Db::all("SELECT sql FROM sqlite_master WHERE type = 'index' AND tbl_name = 'analytics_events' AND sql IS NOT NULL"), 'sql');
            $nuova = preg_replace('/^CREATE TABLE\s+"?analytics_events"?/i', 'CREATE TABLE analytics_events_nuova', $sql);
            $nuova = preg_replace('/property_id\s+INTEGER\s+NOT\s+NULL/i', 'property_id INTEGER', $nuova);
            $pdo->exec($nuova);
            $pdo->exec('INSERT INTO analytics_events_nuova SELECT * FROM analytics_events');
            $pdo->exec('DROP TABLE analytics_events');
            $pdo->exec('ALTER TABLE analytics_events_nuova RENAME TO analytics_events');
            foreach ($indici as $i) $pdo->exec($i);
        }
    }
    $pdo->exec('CREATE INDEX ' . ($mysql ? '' : 'IF NOT EXISTS ') . 'idx_an_kind_day ON analytics_events(kind, day)');
};
