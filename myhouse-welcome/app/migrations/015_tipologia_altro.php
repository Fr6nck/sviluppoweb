<?php
/**
 * 015 — La tipologia «Altro» scritta a mano (Fase 6A).
 *
 * properties.property_type_other: con la tipologia «Altro», che tipo di
 * struttura è (area camper, glamping, ostello…). Dove si mostra la tipologia,
 * con «Altro» si mostra questo testo.
 */

use MHW\Migrator;

return function (\PDO $pdo): void {
    if (!Migrator::columnExists('properties', 'property_type_other')) {
        $pdo->exec("ALTER TABLE properties ADD COLUMN property_type_other VARCHAR(60) NOT NULL DEFAULT ''");
    }
};
