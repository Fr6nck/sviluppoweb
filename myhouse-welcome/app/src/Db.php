<?php
namespace MHW;

use PDO;

final class Db
{
    private static ?PDO $pdo = null;

    public static function conn(): PDO
    {
        if (self::$pdo) return self::$pdo;
        $cfg = Config::get('db');
        if ($cfg['driver'] === 'mysql') {
            $m = $cfg['mysql'];
            $dsn = "mysql:host={$m['host']};dbname={$m['name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $m['user'], $m['pass']);
        } else {
            $path = self::sqlitePath($cfg['sqlite_path']);
            $pdo = new PDO('sqlite:' . $path);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA journal_mode = WAL');
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return self::$pdo = $pdo;
    }

    /**
     * Il file SQLite prende un suffisso casuale, deciso una volta sola e
     * conservato in un file .php (che il server esegue, non serve come testo).
     *
     * Serve perché su parecchi hosting .htaccess non viene letto — nginx non lo
     * legge affatto — e senza questo il database sarebbe scaricabile da chiunque
     * ne indovinasse il nome. Il nome, così, non è indovinabile.
     */
    public static function sqlitePath(string $base): string
    {
        $dir = dirname($base);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $stamp = $dir . '/instance.php';
        if (is_file($stamp)) {
            $suffix = (string) (require $stamp);
        } else {
            $suffix = bin2hex(random_bytes(16));
            file_put_contents($stamp, "<?php return '" . $suffix . "';\n", LOCK_EX);
            @chmod($stamp, 0640);
        }
        $name = basename($base, '.sqlite');
        return $dir . '/' . $name . '-' . $suffix . '.sqlite';
    }

    public static function isMysql(): bool { return Config::get('db')['driver'] === 'mysql'; }

    /** @return array<int,array<string,mixed>> */
    public static function all(string $sql, array $args = []): array
    {
        $st = self::conn()->prepare($sql); $st->execute($args); return $st->fetchAll();
    }

    public static function one(string $sql, array $args = []): ?array
    {
        $st = self::conn()->prepare($sql); $st->execute($args);
        $r = $st->fetch(); return $r === false ? null : $r;
    }

    public static function val(string $sql, array $args = [], mixed $default = null): mixed
    {
        $st = self::conn()->prepare($sql); $st->execute($args);
        $r = $st->fetchColumn(); return $r === false ? $default : $r;
    }

    public static function run(string $sql, array $args = []): int
    {
        $st = self::conn()->prepare($sql); $st->execute($args); return $st->rowCount();
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (:' . implode(',:', $cols) . ')';
        self::conn()->prepare($sql)->execute($data);
        return (int) self::conn()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $args = []): int
    {
        $set = implode(',', array_map(fn($c) => "$c = :$c", array_keys($data)));
        return self::run("UPDATE $table SET $set WHERE $where", $data + $args);
    }

    public static function tx(callable $fn): mixed
    {
        $pdo = self::conn();
        $pdo->beginTransaction();
        try { $r = $fn($pdo); $pdo->commit(); return $r; }
        catch (\Throwable $e) { $pdo->rollBack(); throw $e; }
    }
}
