<?php
/**
 * Router per il server di sviluppo di PHP.
 *
 *     php -S localhost:8080 -t public tools/router.php
 *
 * Il server interno di PHP non legge .htaccess: questo file fa la stessa cosa
 * che fa Apache in produzione — serve i file che esistono davvero e manda
 * tutto il resto a public/index.php. In produzione non viene mai caricato.
 */

declare(strict_types=1);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$file = __DIR__ . '/../public' . $path;

// Come Apache e LiteSpeed: i file che cominciano con un punto (.htaccess,
// .user.ini) non si servono mai.
if (preg_match('#/\.[^/]+$#', $path) === 1) {
    http_response_code(403);
    return true;
}

if ($path !== '/' && is_file($file)) {
    return false;   // lo serve il server interno
}

require __DIR__ . '/../public/index.php';
