<?php
namespace MHW;

/**
 * Le migrazioni: il database cresce per aggiunte, mai ricreandolo.
 *
 * Ogni file in migrations/ si applica una volta sola e resta scritto in
 * schema_migrations. I file .sql sono istruzioni; i file .php restituiscono
 * una funzione che riceve il PDO, per i passaggi che toccano i DATI.
 *
 * Si parte da soli all'avvio: un'installazione via FTP non ha una riga di
 * comando da cui lanciarle. Il controllo costa una lettura di file finché
 * non c'è niente di nuovo.
 */
final class Migrator
{
    public static function dir(): string { return MHW_APP . '/migrations'; }

    /** @return string[] i nomi dei file, in ordine */
    public static function files(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/*.{sql,php}', GLOB_BRACE) ?: [] as $f) $out[] = basename($f);
        sort($out, SORT_STRING);
        return $out;
    }

    private static function stamp(): string { return MHW_APP . '/storage/migrazioni.txt'; }

    /** Niente da fare? Lo si sa senza aprire il database. */
    public static function upToDate(): bool
    {
        $files = self::files();
        $last = end($files) ?: '';
        return is_file(self::stamp()) && trim((string) file_get_contents(self::stamp())) === $last;
    }

    /** @return string[] le migrazioni applicate in questa chiamata */
    public static function run(): array
    {
        if (self::upToDate()) return [];
        $pdo = Db::conn();
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            name VARCHAR(190) NOT NULL PRIMARY KEY, applied_at VARCHAR(25) NOT NULL)');

        // Un database nato prima delle migrazioni ha già lo schema di base.
        $fatte = array_column(Db::all('SELECT name FROM schema_migrations'), 'name');
        if (!$fatte && self::tableExists('users')) {
            Db::insert('schema_migrations', ['name' => '001_schema.sql', 'applied_at' => Support::now()]);
            $fatte[] = '001_schema.sql';
        }

        $applicate = [];
        foreach (self::files() as $name) {
            if (in_array($name, $fatte, true)) continue;
            self::apply($pdo, $name);
            $applicate[] = $name;
        }
        $files = self::files();
        @file_put_contents(self::stamp(), (string) end($files), LOCK_EX);
        return $applicate;
    }

    private static function apply(\PDO $pdo, string $name): void
    {
        $sqlite = !Db::isMysql();
        // Due richieste contemporanee non devono applicare la stessa migrazione
        // due volte: su SQLite BEGIN IMMEDIATE prende il lucchetto in scrittura
        // subito, e dentro si ricontrolla.
        if ($sqlite) $pdo->exec('BEGIN IMMEDIATE'); else $pdo->beginTransaction();
        try {
            if (Db::one('SELECT name FROM schema_migrations WHERE name = ?', [$name])) {
                $pdo->exec($sqlite ? 'COMMIT' : 'COMMIT');
                return;
            }
            $path = self::dir() . '/' . $name;
            if (str_ends_with($name, '.sql')) {
                foreach (self::statements((string) file_get_contents($path)) as $stmt) $pdo->exec($stmt);
            } else {
                $fn = require $path;
                if (!is_callable($fn)) throw new \RuntimeException("La migrazione $name non restituisce una funzione.");
                $fn($pdo);
            }
            Db::insert('schema_migrations', ['name' => $name, 'applied_at' => Support::now()]);
            if ($sqlite) $pdo->exec('COMMIT'); elseif ($pdo->inTransaction()) $pdo->commit();
        } catch (\Throwable $e) {
            if ($sqlite) { try { $pdo->exec('ROLLBACK'); } catch (\Throwable) {} }
            elseif ($pdo->inTransaction()) $pdo->rollBack();
            throw new \RuntimeException("Migrazione $name non applicata: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Le righe di commento si tolgono DENTRO ogni blocco: scartare il blocco
     * intero perché comincia con "--" farebbe sparire l'istruzione che segue.
     */
    public static function statements(string $sql): array
    {
        $clean = implode("\n", array_filter(
            array_map(fn(string $l) => str_starts_with(trim($l), '--') ? '' : $l, explode("\n", $sql)),
            fn(string $l) => trim($l) !== ''
        ));
        return array_values(array_filter(array_map('trim', explode(';', $clean)), fn($s) => $s !== ''));
    }

    public static function tableExists(string $table): bool
    {
        try { Db::conn()->query('SELECT 1 FROM ' . $table . ' LIMIT 1'); return true; }
        catch (\Throwable) { return false; }
    }

    public static function columnExists(string $table, string $col): bool
    {
        if (Db::isMysql()) {
            return (bool) Db::one("SHOW COLUMNS FROM $table LIKE ?", [$col]);
        }
        foreach (Db::all("PRAGMA table_info($table)") as $c) if ($c['name'] === $col) return true;
        return false;
    }
}
