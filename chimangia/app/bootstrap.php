<?php
/**
 * Avvio comune a sito, pannello e procedura d'installazione.
 * Carica la configurazione, imposta errori, fuso orario e funzioni condivise.
 */
declare(strict_types=1);

if (!defined('CM_ROOT')) {
    define('CM_ROOT', dirname(__DIR__));
}
define('CM_APP', __DIR__);
define('CM_VERSION', '1.3.1');

mb_internal_encoding('UTF-8');

require_once CM_APP . '/helpers.php';
require_once CM_APP . '/db.php';

$GLOBALS['cm_config'] = null;
if (is_file(CM_ROOT . '/config.php')) {
    $cfg = require CM_ROOT . '/config.php';
    if (is_array($cfg)) {
        $GLOBALS['cm_config'] = $cfg;
    }
}

date_default_timezone_set((string) cfg('timezone', 'Europe/Rome'));

// Gli errori non vengono mai mostrati al pubblico: finiscono nel file di log.
error_reporting(E_ALL);
ini_set('display_errors', cfg('debug', false) ? '1' : '0');
ini_set('log_errors', '1');
$cmLogDir = storage_path('logs');
if (is_dir($cmLogDir) && is_writable($cmLogDir)) {
    ini_set('error_log', $cmLogDir . '/php-errors.log');
}

set_exception_handler(static function (Throwable $e): void {
    error_log('[ChiMangia] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    if (cfg('debug', false)) {
        echo '<pre>' . e((string) $e) . '</pre>';
        return;
    }
    echo '<!doctype html><html lang="it"><meta charset="utf-8"><title>Errore</title>'
        . '<body>'
        . '<h1>Si è verificato un errore</h1><p>Riprova tra qualche minuto.</p></body></html>';
});

function is_installed(): bool
{
    return is_array($GLOBALS['cm_config'] ?? null);
}

/**
 * Da usare in ogni punto d'ingresso che richiede l'installazione già fatta.
 */
function require_installed(string $prefix = ''): void
{
    if (!is_installed()) {
        header('Location: ' . $prefix . 'install/');
        exit;
    }
}
