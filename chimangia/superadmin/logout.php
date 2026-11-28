<?php
/** Uscita dal pannello del fornitore (chiude anche il pannello del sito: la sessione è la stessa). */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
pf_boot('super', '../');

if (is_post() && csrf_valid()) {
    logout_user();
    redirect('../admin/login.php');
}
redirect(current_user() ? 'index.php' : '../admin/login.php');
