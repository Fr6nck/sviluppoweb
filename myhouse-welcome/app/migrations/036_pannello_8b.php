<?php
/**
 * 036 — Pannello 8B: dove si trova la struttura, domanda sul sito, messaggi da mandare.
 *
 * property_channels: i portali dove la struttura è presente (Google, Booking.com, Airbnb, VRBO,
 *   Expedia: senza indirizzo, il link lo prepara Canali::link) e quelli scritti dall'host (con
 *   l'indirizzo della scheda), più i social (Facebook, Instagram, TikTok). Una riga per canale.
 *   I portali che oggi hanno un link di recensione nascono spuntati; review_other diventa una riga
 *   «altro» con l'etichetta «Altro sito» e il suo indirizzo. Le colonne review_* restano (si
 *   leggono ancora nelle istantanee vecchie) ma non si scrivono più.
 * properties.has_site: «Hai un sito dove gli ospiti possono prenotare direttamente?» 1 / 0 / NULL
 *   (non risposto); con un direct_url già scritto vale 1.
 * properties.messages: i messaggi di benvenuto e di fine soggiorno salvati dall'host, per lingua,
 *   come modelli con {link} e {host} (JSON). Vuoto = il testo di serie.
 *
 * MySQL prima della 8.0.13 non accetta un valore predefinito su TEXT: messages è NULL.
 * Solo aggiunte, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */

use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    $pk = Db::isMysql() ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    if (!Migrator::tableExists('property_channels')) {
        $pdo->exec("CREATE TABLE property_channels (
            id $pk,
            property_id INTEGER NOT NULL,
            kind VARCHAR(20) NOT NULL,
            label VARCHAR(80) NOT NULL DEFAULT '',
            url VARCHAR(500) NOT NULL DEFAULT '',
            position INTEGER NOT NULL DEFAULT 0,
            created_at VARCHAR(25) NOT NULL
        )");
        $pdo->exec('CREATE INDEX idx_property_channels ON property_channels(property_id, position)');

        // I portali con un link di recensione oggi: spuntati. Solo alla creazione della tabella.
        if (Migrator::columnExists('properties', 'review_google')) {
            $ora = gmdate('Y-m-d\TH:i:s\Z');
            foreach (Db::all("SELECT id, review_google, review_booking, review_airbnb, review_other FROM properties") as $p) {
                $pos = 0;
                foreach (['google' => 'review_google', 'booking' => 'review_booking', 'airbnb' => 'review_airbnb'] as $kind => $col) {
                    if (trim((string) $p[$col]) === '') continue;
                    Db::insert('property_channels', ['property_id' => (int) $p['id'], 'kind' => $kind, 'label' => '', 'url' => '', 'position' => $pos++, 'created_at' => $ora]);
                }
                if (trim((string) $p['review_other']) !== '') {
                    Db::insert('property_channels', ['property_id' => (int) $p['id'], 'kind' => 'altro', 'label' => 'Altro sito',
                                                     'url' => mb_substr(trim((string) $p['review_other']), 0, 500), 'position' => $pos, 'created_at' => $ora]);
                }
            }
        }
    }
    if (!Migrator::columnExists('properties', 'has_site')) {
        $pdo->exec('ALTER TABLE properties ADD COLUMN has_site INTEGER NULL');
        if (Migrator::columnExists('properties', 'direct_url')) Db::run("UPDATE properties SET has_site = 1 WHERE direct_url <> '' AND direct_url IS NOT NULL");
    }
    if (!Migrator::columnExists('properties', 'messages')) $pdo->exec('ALTER TABLE properties ADD COLUMN messages TEXT NULL');
};
