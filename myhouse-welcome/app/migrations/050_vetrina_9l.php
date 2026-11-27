<?php
/**
 * 050 — 9L · Il programma «Giudizio con sconto» unico e «Le guide dei nostri host» in home.
 *
 * 1. vetrina_schede: una scheda per ogni account che ha aderito al programma. Esiste da quando la guida è pubblicata,
 *    anche prima del giudizio: struttura (property_id), scheda_home (il via libera dell'amministrazione, 0/1),
 *    scheda_da (la data di pubblicazione della guida), immagini (JSON: copertina, logo, scelte dall'host), nota_admin
 *    (appunto interno), risposta_pubblica (sotto il giudizio, facoltativa), ritirato_at, decisa_at (l'ultima decisione
 *    dell'amministrazione: senza, la scheda è «da approvare»), sospesa (il motivo per cui si è spenta da sola).
 *    Il giudizio resta in testimonials; gli stati della riga si calcolano (Vetrina::stato).
 * 2. Mai la foto dell'host: show_photo azzerato, e photo_source «profilo» dei vecchi giudizi (7D) diventa «nessuna».
 *    Le colonne restano (inerti) per non toccare i dati: nessun codice le legge più.
 * 3. Le schede di chi ha già aderito (9B): struttura, data, immagini scelte; in home se il giudizio era già in home;
 *    ritirata se il giudizio era stato ritirato. I clienti di prova, di esempio e la vetrina restano fuori.
 * 4. La copertina nuova di Casa Checco (assets/foto/checco-copertina.jpg, 9L) sulla vetrina, poi la ripubblicazione.
 *
 * Solo aggiunte, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */
use MHW\{Db, Demo, Guide, Log, Migrator, Support};

return function (\PDO $pdo): void {
    $pk = Db::isMysql() ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';

    if (!Migrator::tableExists('vetrina_schede')) {
        $pdo->exec("CREATE TABLE vetrina_schede (
            id $pk,
            account_id INTEGER NOT NULL,
            property_id INTEGER NULL,
            scheda_home INTEGER NOT NULL DEFAULT 0,
            scheda_da VARCHAR(25) NULL,
            immagini VARCHAR(255) NOT NULL DEFAULT '{\"copertina\":1,\"logo\":1}',
            nota_admin TEXT NULL,
            risposta_pubblica TEXT NULL,
            ritirato_at VARCHAR(25) NULL,
            decisa_at VARCHAR(25) NULL,
            sospesa VARCHAR(40) NOT NULL DEFAULT '',
            created_at VARCHAR(25) NOT NULL,
            updated_at VARCHAR(25) NOT NULL
        )");
        $pdo->exec('CREATE UNIQUE INDEX vetrina_schede_account ON vetrina_schede (account_id)');
        $pdo->exec('CREATE INDEX vetrina_schede_home ON vetrina_schede (scheda_home)');
    }

    // 2. Niente foto dell'host, da nessuna parte.
    if (Migrator::tableExists('testimonials')) {
        if (Migrator::columnExists('testimonials', 'show_photo')) $pdo->exec('UPDATE testimonials SET show_photo = 0 WHERE show_photo <> 0');
        if (Migrator::columnExists('testimonials', 'photo_source')) $pdo->exec("UPDATE testimonials SET photo_source = 'nessuna' WHERE photo_source = 'profilo'");
    }

    // 3. Le schede di chi ha già aderito.
    if (Migrator::columnExists('accounts', 'giudizio_adesione') && Migrator::columnExists('testimonials', 'home_visible')) {
        $ora = Support::now();
        foreach (Db::all("SELECT a.id, a.is_test, u.email FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.giudizio_adesione IS NOT NULL") as $a) {
            if (Db::one('SELECT id FROM vetrina_schede WHERE account_id = ?', [(int) $a['id']])) continue;
            if (!empty($a['is_test']) || str_ends_with((string) $a['email'], '@' . Demo::DOMINIO)) continue;
            if (Db::one('SELECT id FROM properties WHERE account_id = ? AND is_demo > 0', [(int) $a['id']])) continue;
            $p = Db::one("SELECT id, published_at FROM properties WHERE account_id = ? AND is_demo = 0 AND archived_at IS NULL AND published_at IS NOT NULL AND published_at <> '' ORDER BY published_at, id", [(int) $a['id']]);
            $t = Db::one('SELECT * FROM testimonials WHERE account_id = ? ORDER BY id DESC', [(int) $a['id']]);
            $img = $t ? ['copertina' => (int) $t['show_cover'] || !(int) $t['show_logo'] ? 1 : 0, 'logo' => (int) $t['show_logo'] || !(int) $t['show_cover'] ? 1 : 0] : ['copertina' => 1, 'logo' => 1];
            $ritirato = $t && !empty($t['withdrawn_at']);
            Db::insert('vetrina_schede', [
                'account_id' => (int) $a['id'], 'property_id' => $p ? (int) $p['id'] : null,
                'scheda_home' => $t && (int) $t['home_visible'] === 1 && !$ritirato ? 1 : 0, 'scheda_da' => $p ? (string) $p['published_at'] : null,
                'immagini' => json_encode($img), 'ritirato_at' => $ritirato ? (string) $t['withdrawn_at'] : null,
                'decisa_at' => $t && (int) $t['home_visible'] === 1 ? $ora : null, 'sospesa' => '', 'created_at' => $ora, 'updated_at' => $ora,
            ]);
        }
    }

    // 4. La copertina nuova di Casa Checco sulla vetrina pubblicata, poi la ripubblicazione (l'istantanea nuova).
    if (Migrator::tableExists('guide_versions') && Migrator::columnExists('properties', 'is_demo')) {
        foreach (Db::all("SELECT id, status FROM properties WHERE is_demo = 2 AND archived_at IS NULL") as $v) {
            try {
                if (Demo::copertinaVetrina((int) $v['id']) && $v['status'] === 'published') Guide::publish((int) $v['id']);
            } catch (\Throwable $e) {
                Log::error('050: copertina della vetrina non cambiata: ' . $e->getMessage(), ['property' => (int) $v['id']]);
            }
        }
    }
};
