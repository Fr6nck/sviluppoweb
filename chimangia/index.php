<?php
/** ChiMangia — sito pubblico (servito dalla cache HTML generata a ogni pubblicazione). */
declare(strict_types=1);

define('CM_ROOT', __DIR__);
require __DIR__ . '/app/public.php';

serve_public_page('index', '');
