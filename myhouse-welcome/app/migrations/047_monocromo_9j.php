<?php
/**
 * 047 — 9J · «Bianco e nero»: la casella «Foto in bianco e nero» della guida (properties.photos_bw, 0/1).
 * La palette sta nel codice (Palette::P), qui solo la scelta dell'host. Solo aggiunte, SQLite e MySQL.
 */
use MHW\Migrator;

return function (\PDO $pdo): void {
    if (Migrator::tableExists('properties') && !Migrator::columnExists('properties', 'photos_bw')) {
        $pdo->exec('ALTER TABLE properties ADD COLUMN photos_bw INTEGER NOT NULL DEFAULT 0');
    }
};
