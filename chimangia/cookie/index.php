<?php
/** Pagina cookie: testo modificabile dal pannello (Contenuti › Cookie). */
declare(strict_types=1);

define('CM_ROOT', dirname(__DIR__));
require dirname(__DIR__) . '/app/public.php';

serve_public_page('cookie', '../');
