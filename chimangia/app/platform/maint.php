<?php
/**
 * Piattaforma ChiMangia — manutenzione: backup del database, controlli di sistema,
 * esportazione ed eliminazione dei dati di un locale.
 * Caricato solo dove serve (super admin e lavori giornalieri).
 */
declare(strict_types=1);

defined('CM_ROOT') || exit;

/* ---------- Backup ---------- */

function backup_dir(): string
{
    $dir = storage_path('backups');
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }
    return $dir;
}

function db_prefix(): string
{
    return (string) preg_replace('/[^a-z0-9_]/i', '', (string) (cfg('db', [])['prefix'] ?? 'cm_'));
}

/** Tabelle dell'installazione (solo quelle con il prefisso configurato). */
function db_tables(): array
{
    $like = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], db_prefix()) . '%';
    return q('SHOW TABLES LIKE ' . db()->quote($like))->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Crea un backup completo del database (struttura e dati) nella cartella dati privata.
 * Le tabelle di servizio (tentativi di accesso, limiti, token) vengono salvate vuote.
 * Restituisce il nome del file.
 */
function backup_create(string $label = 'manuale'): string
{
    @set_time_limit(300);
    $gz = function_exists('gzopen');
    $label = preg_replace('/[^a-z]/', '', $label) ?: 'manuale';
    $name = 'chimangia-db-' . date('Ymd-His') . '-' . $label . '-' . bin2hex(random_bytes(3)) . '.sql' . ($gz ? '.gz' : '');
    $path = backup_dir() . '/' . $name;
    $h = $gz ? gzopen($path, 'wb6') : fopen($path, 'wb');
    if (!$h) {
        throw new RuntimeException('La cartella dei backup non è scrivibile.');
    }
    $w = static function (string $s) use ($h, $gz): void {
        $gz ? gzwrite($h, $s) : fwrite($h, $s);
    };
    $pdo = db();
    $skipData = array_map(static fn ($t) => db_prefix() . $t, ['login_attempts', 'pf_rate', 'pf_tokens', 'contact_rate', 'password_resets']);
    $w("-- ChiMangia " . CM_VERSION . " · backup del " . date('d/m/Y H:i') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
    foreach (db_tables() as $table) {
        $create = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM);
        $w("DROP TABLE IF EXISTS `$table`;\n" . $create[1] . ";\n\n");
        if (in_array($table, $skipData, true)) {
            continue;
        }
        $st = $pdo->query('SELECT * FROM `' . $table . '`');
        $batch = [];
        $flush = static function () use (&$batch, $w, $table): void {
            if ($batch) {
                $w("INSERT INTO `$table` VALUES\n" . implode(",\n", $batch) . ";\n");
                $batch = [];
            }
        };
        while ($row = $st->fetch(PDO::FETCH_NUM)) {
            $vals = array_map(static fn ($v) => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v)), $row);
            $batch[] = '(' . implode(',', $vals) . ')';
            if (count($batch) >= 200) {
                $flush();
            }
        }
        $flush();
        $w("\n");
    }
    $w("SET FOREIGN_KEY_CHECKS = 1;\n");
    $gz ? gzclose($h) : fclose($h);
    @chmod($path, 0600);
    return $name;
}

/** @return list<array{name:string,size:int,time:int,auto:bool}> dal più recente */
function backup_list(): array
{
    $out = [];
    foreach (glob(backup_dir() . '/chimangia-db-*.sql*') ?: [] as $f) {
        $out[] = ['name' => basename($f), 'size' => (int) filesize($f), 'time' => (int) filemtime($f), 'auto' => str_contains(basename($f), '-automatico-')];
    }
    usort($out, static fn ($a, $b) => $b['time'] <=> $a['time']);
    return $out;
}

function backup_path(string $name): ?string
{
    if (!preg_match('/^chimangia-db-[0-9]{8}-[0-9]{6}-[a-z]+-[a-f0-9]{6}\.sql(\.gz)?$/', $name)) {
        return null;
    }
    $p = backup_dir() . '/' . $name;
    return is_file($p) ? $p : null;
}

/** Tiene solo gli ultimi $keep backup automatici (quelli manuali non vengono toccati). */
function backup_prune(int $keep): void
{
    $auto = array_values(array_filter(backup_list(), static fn ($b) => $b['auto']));
    foreach (array_slice($auto, max(1, $keep)) as $b) {
        @unlink(backup_dir() . '/' . $b['name']);
    }
}

function bytes_it(int $b): string
{
    if ($b >= 1048576) {
        return number_format($b / 1048576, 1, ',', '.') . ' MB';
    }
    return number_format(max(1, (int) round($b / 1024)), 0, ',', '.') . ' KB';
}

/* ---------- Stato del sistema ---------- */

function db_size(): int
{
    $like = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], db_prefix()) . '%';
    return (int) q_value('SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE ?', [$like]);
}

/** @return list<array{0:string,1:string,2:string}> [voce, valore, stato: ok|warn|bad] */
function system_checks(): array
{
    $c = [];
    $c[] = ['Versione ChiMangia', CM_VERSION . ' (database ' . (string) setting('db_version', '?') . ')', (string) setting('db_version', '') === CM_VERSION ? 'ok' : 'warn'];
    $c[] = ['PHP', PHP_VERSION, version_compare(PHP_VERSION, '8.1.0', '>=') ? 'ok' : 'bad'];
    foreach (['pdo_mysql' => 'bad', 'mbstring' => 'bad', 'openssl' => 'bad', 'fileinfo' => 'warn', 'gd' => 'warn', 'zlib' => 'warn'] as $ext => $level) {
        $c[] = ['Estensione ' . $ext, extension_loaded($ext) ? 'presente' : 'assente', extension_loaded($ext) ? 'ok' : $level];
    }
    $c[] = ['Database', (string) q_value('SELECT VERSION()') . ' · ' . bytes_it(db_size()), 'ok'];
    foreach (['cache' => 'Cache delle pagine', 'logs' => 'Registro errori', 'sessions' => 'Sessioni del pannello', 'sessions-staff' => 'Sessioni dei locali', 'sessions-guest' => 'Sessioni degli ospiti', 'backups' => 'Backup'] as $dir => $label) {
        $p = storage_path($dir);
        if (!is_dir($p)) {
            @mkdir($p, 0700, true);
        }
        $c[] = [$label, is_writable($p) ? 'scrivibile' : 'non scrivibile', is_writable($p) ? 'ok' : 'bad'];
    }
    $c[] = ['Cartella delle immagini', is_writable(CM_ROOT . '/uploads') ? 'scrivibile' : 'non scrivibile', is_writable(CM_ROOT . '/uploads') ? 'ok' : 'bad'];
    $c[] = ['HTTPS', is_https() ? 'attivo' : 'non attivo', is_https() ? 'ok' : 'warn'];
    $c[] = ['Invio email', (string) setting('mail_transport', 'mail') === 'smtp' ? 'SMTP' : 'mail() del server', (string) setting('mail_transport', 'mail') === 'smtp' ? 'ok' : 'warn'];
    $pending = (int) q_value('SELECT COUNT(*) FROM ' . t('outbox') . ' WHERE sent_at IS NULL AND attempts < 3');
    $failed = (int) q_value('SELECT COUNT(*) FROM ' . t('outbox') . ' WHERE sent_at IS NULL AND attempts >= 3');
    $c[] = ['Email in coda', $pending . ' da inviare · ' . $failed . ' non riuscite', $failed > 0 ? 'warn' : 'ok'];
    $tick = (int) setting('pf_tick_at', 0);
    $c[] = ['Lavori periodici', $tick ? 'ultima esecuzione ' . date('d/m/Y H:i', $tick) : 'mai eseguiti', $tick && time() - $tick < 86400 ? 'ok' : 'warn'];
    $last = backup_list()[0] ?? null;
    $c[] = ['Ultimo backup', $last ? date('d/m/Y H:i', $last['time']) . ' · ' . bytes_it($last['size']) : 'nessuno', $last && time() - $last['time'] < 3 * 86400 ? 'ok' : 'warn'];
    $c[] = ['Cartella install/', is_file(CM_ROOT . '/install/index.php') ? 'ancora presente: va cancellata' : 'rimossa', is_file(CM_ROOT . '/install/index.php') ? 'warn' : 'ok'];
    $c[] = ['Modalità sviluppo', cfg('debug', false) ? 'attiva: gli errori sono visibili' : 'spenta', cfg('debug', false) ? 'warn' : 'ok'];
    return $c;
}

/** Ultime righe di un file di testo (per il registro degli errori). */
function log_tail(string $file, int $lines = 80): string
{
    if (!is_file($file) || !is_readable($file)) {
        return '';
    }
    $size = (int) filesize($file);
    $h = fopen($file, 'rb');
    if (!$h) {
        return '';
    }
    fseek($h, max(0, $size - 60000));
    $data = (string) stream_get_contents($h);
    fclose($h);
    return implode("\n", array_slice(explode("\n", trim($data)), -$lines));
}

function error_log_file(): string
{
    return storage_path('logs') . '/php-errors.log';
}

/* ---------- Dati di un locale ---------- */

const PF_VENUE_TABLES = ['staff', 'companies', 'guests', 'rooms', 'nights', 'menus', 'bookings', 'requests', 'notices', 'events', 'outbox'];

/** Tutti i dati del locale in un array (senza le password), per l'esportazione in JSON. */
function venue_export(int $venueId): array
{
    $venue = q_one('SELECT * FROM ' . t('venues') . ' WHERE id = ?', [$venueId]);
    if (!$venue) {
        return [];
    }
    $out = ['esportato_il' => now(), 'versione' => CM_VERSION, 'locale' => $venue];
    foreach (PF_VENUE_TABLES as $table) {
        if ($table === 'outbox') {
            continue;
        }
        $rows = q('SELECT * FROM ' . t($table) . ' WHERE venue_id = ?', [$venueId])->fetchAll();
        foreach ($rows as &$r) {
            unset($r['password_hash']);
        }
        unset($r);
        $out[$table] = $rows;
    }
    return $out;
}

/** Elimina il locale con tutti i suoi dati e le foto dei menù. Irreversibile. */
function venue_delete(int $venueId): void
{
    foreach (q('SELECT photo FROM ' . t('menus') . " WHERE venue_id = ? AND photo <> ''", [$venueId])->fetchAll(PDO::FETCH_COLUMN) as $photo) {
        pf_image_delete((string) $photo);
    }
    foreach (PF_VENUE_TABLES as $table) {
        q('DELETE FROM ' . t($table) . ' WHERE venue_id = ?', [$venueId]);
    }
    q('DELETE FROM ' . t('venues') . ' WHERE id = ?', [$venueId]);
}
