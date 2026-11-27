<?php
namespace MHW;

final class Installer
{
    public static function installed(): bool
    {
        try { Db::val('SELECT COUNT(*) FROM users'); return true; }
        catch (\Throwable) { return false; }
    }

    /**
     * Crea il database applicando TUTTE le migrazioni in ordine — le stesse che
     * aggiornano un database già esistente — e poi il primo amministratore.
     * Un solo percorso: un'installazione nuova e una aggiornata finiscono
     * identiche, perché passano dalle stesse istruzioni.
     */
    public static function install(string $adminEmail, string $adminPass): array
    {
        Migrator::run();
        $u = Auth::register($adminEmail, $adminPass, 'Amministrazione');
        Db::update('users', [
            'role' => 'admin',
            // L'amministratore ha appena dimostrato di avere accesso al server.
            'email_verified_at' => Support::now(),
        ], 'id = :uid', ['uid' => $u['user_id']]);
        return $u;
    }
}
