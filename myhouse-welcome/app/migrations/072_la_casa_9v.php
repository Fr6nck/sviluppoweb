<?php
/**
 * 072 — 9V · «La casa»: la galleria di foto e la descrizione che si aprono dalla copertina della guida.
 *  1. La tabella property_gallery: le foto della galleria (al massimo 10 per struttura: il limite si controlla sul
 *     server, in Casa). Nel database c'è solo il riferimento al file (media_id): i file stanno nello storage.
 *  2. Su properties: casa_titolo (60 battute), casa_descrizione (600), casa_invito (la scritta sulla copertina, 24;
 *     vuota = «Scopri la casa» nella lingua dell'ospite), casa_attiva (l'interruttore, acceso di serie: la galleria
 *     compare solo se c'è almeno una foto o la descrizione) e casa_tr (le altre lingue della guida, in JSON).
 *  3. La funzione «gallery» nel catalogo dei piani, inclusa in tutte le versioni (Essential, Plus, Portfolio): è la
 *     riga «Galleria della casa (fino a 10 foto) e descrizione» di «Confronta tutti i piani». Nessun prezzo nuovo.
 *  4. La guida vetrina che c'è già (Casa Checco) riceve la galleria e la descrizione di serie, se non ne ha: foto e
 *     testi già messi dal titolare non si toccano. Si ripubblica solo se non aveva modifiche in sospeso.
 * Solo aggiunte, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */
use MHW\{Db, Demo, Guide, Log, Migrator};

return function (\PDO $pdo): void {
    if (!Migrator::tableExists('properties')) return;
    if (!Migrator::tableExists('property_gallery')) {
        $id = Db::isMysql() ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $pdo->exec("CREATE TABLE property_gallery (
            id          $id,
            property_id INTEGER NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
            media_id    INTEGER NOT NULL,
            position    INTEGER NOT NULL DEFAULT 0,
            alt         VARCHAR(250) NOT NULL DEFAULT '',
            created_at  VARCHAR(25) NOT NULL DEFAULT ''
        )");
        $pdo->exec('CREATE INDEX idx_gallery_property ON property_gallery(property_id, position)');
    }
    $colonne = [
        'casa_titolo'      => "VARCHAR(60) NOT NULL DEFAULT ''",
        'casa_descrizione' => 'TEXT NULL',
        'casa_invito'      => "VARCHAR(24) NOT NULL DEFAULT ''",
        'casa_attiva'      => 'INTEGER NOT NULL DEFAULT 1',
        'casa_tr'          => 'TEXT NULL',
    ];
    foreach ($colonne as $col => $def) {
        if (!Migrator::columnExists('properties', $col)) $pdo->exec("ALTER TABLE properties ADD COLUMN $col $def");
    }

    // La funzione nel catalogo dei piani: inclusa in tutte le versioni di tutti i pacchetti.
    if (Migrator::tableExists('features') && Migrator::tableExists('package_features')) {
        $fid = Db::val("SELECT id FROM features WHERE code = 'gallery'");
        if (!$fid) $fid = Db::insert('features', ['code' => 'gallery', 'kind' => 'bool', 'default_value' => '1', 'label' => 'Galleria della casa (fino a 10 foto) e descrizione']);
        foreach (Db::all('SELECT id FROM package_versions') as $v) {
            if (!Db::val('SELECT feature_id FROM package_features WHERE package_version_id = ? AND feature_id = ?', [$v['id'], $fid])) {
                Db::insert('package_features', ['package_version_id' => $v['id'], 'feature_id' => $fid, 'value' => '1']);
            }
        }
    }

    // La vetrina che c'è già: galleria e descrizione di serie, solo dove mancano.
    if (Migrator::columnExists('properties', 'is_demo')) {
        foreach (Db::all('SELECT id, status FROM properties WHERE is_demo = ? AND archived_at IS NULL', [Demo::VETRINA]) as $v) {
            try {
                $pid = (int) $v['id'];
                $inSospeso = $v['status'] === 'published' && Guide::pendingChanges($pid) > 0;
                if (Demo::casaVetrina($pid) && $v['status'] === 'published' && !$inSospeso) Guide::publish($pid);
            } catch (\Throwable $e) { Log::exception($e, '072: la galleria della vetrina'); }
        }
    }
};
