<?php
/**
 * Le migrazioni come le vede PHP prima della 8.4: lì PDO::inTransaction() su SQLite vede solo
 * le transazioni aperte con beginTransaction(), non il «BEGIN IMMEDIATE» del Migrator, e un
 * Db::tx() dentro una migrazione falliva («cannot start a transaction within a transaction»).
 * Uso: php prove/php-vecchio.php <cartella app con src, migrations, config.php e storage/> <database.sqlite>
 * Esce con 1 se una migrazione non passa.
 */
[, $app, $db] = $argv + [null, null, null];
if (!$app || !$db) { fwrite(STDERR, "uso: php php-vecchio.php <app> <db.sqlite>\n"); exit(2); }
define('MHW_APP', $app);
spl_autoload_register(function (string $c) use ($app): void { if (str_starts_with($c, 'MHW\\')) require $app . '/src/' . substr($c, 4) . '.php'; });

final class PdoPrimaDi84 extends PDO
{
    private bool $aperta = false;
    public function beginTransaction(): bool
    {
        // Come SQLite: una seconda BEGIN dentro una transazione aperta (anche a mano) fallisce.
        if ($this->aperta) throw new PDOException('There is already an active transaction');
        $r = parent::beginTransaction(); $this->aperta = true; return $r;
    }
    public function commit(): bool { $this->aperta = false; return parent::commit(); }
    public function rollBack(): bool { $this->aperta = false; return parent::rollBack(); }
    public function inTransaction(): bool { return $this->aperta; }
}

MHW\Config::load($app . '/config.php');
$pdo = new PdoPrimaDi84('sqlite:' . $db, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
(new ReflectionProperty(MHW\Db::class, 'pdo'))->setValue(null, $pdo);
@unlink($app . '/storage/migrazioni.txt');
try {
    $fatte = MHW\Migrator::run();
    echo '  ok  Migrazioni con PHP < 8.4 (simulato): ', $fatte ? implode(', ', $fatte) : 'niente da fare', "\n";
} catch (Throwable $e) {
    echo ' NO   Migrazioni con PHP < 8.4 (simulato): ', $e->getMessage(), "\n";
    exit(1);
}
