<?php
/**
 * Autoloader PSR-4 minimo per lo spazio dei nomi ArcoDelVento\.
 *
 * Il sito non ha dipendenze esterne e quindi non ha bisogno di Composer:
 * su un hosting condiviso si carica via FTP e funziona. Se un giorno
 * servisse una libreria, basta aggiungere composer.json e sostituire
 * questo file con vendor/autoload.php — nient'altro cambia.
 */

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'ArcoDelVento\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file     = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
