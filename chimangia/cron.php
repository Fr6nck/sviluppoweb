<?php
/**
 * Lavori periodici della piattaforma, da chiamare con un cron dell'hosting
 * (chiusura dei menù all'orario limite, email in coda, pulizie e backup giornalieri).
 *
 *   cron.php?k=CHIAVE     la chiave si legge e si rigenera in Super admin → Sistema e manutenzione
 *
 * Senza chiave giusta risponde 404. Nessuna sessione e nessun cookie.
 */
declare(strict_types=1);

define('CM_ROOT', __DIR__);
require __DIR__ . '/app/platform/core.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');

$key = is_installed() ? (string) setting('pf_cron_key', '') : '';
$given = get_param('k');
if ($key === '' || $given === '' || !hash_equals($key, $given)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

echo 'ok';
pf_tick(true);
