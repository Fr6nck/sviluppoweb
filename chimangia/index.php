<?php
declare(strict_types=1);

define('CM_ROOT', __DIR__);
require __DIR__ . '/app/bootstrap.php';
require_installed();

// Il sito pubblico arriva nella fase 2.
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="it"><meta charset="utf-8"><title>ChiMangia</title><p>Installazione completata. <a href="admin/">Pannello</a></p></html>';
