<?php

declare(strict_types=1);

namespace ArcoDelVento\Database;

/**
 * Apertura della connessione a MySQL, quando c'è.
 *
 * Il prototipo gira con DB_DSN vuoto e legge i contenuti dai file: questa
 * classe restituisce allora `null`, e App sceglie i repository su file.
 * Compilato il DSN — su Hostinger host, nome e utente arrivano dall'hPanel —
 * gli stessi repository passano alle tabelle di database/schema.sql senza che
 * una sola vista debba cambiare.
 */
final class Connection
{
    public static function open(string $dsn, string $user, string $password): ?\PDO
    {
        if (trim($dsn) === '') {
            return null;
        }

        return new \PDO($dsn, $user, $password, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            // Query preparate davvero, non emulate: è ciò che rende le
            // parametrizzazioni una difesa e non una formalità.
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
}
