<?php
/**
 * Manifest dell'applicazione web del locale: permette di aggiungere l'area ospiti
 * alla schermata Home del telefono con il nome del locale. Nessuna sessione, nessun cookie.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';

header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex');
$venue = is_installed() ? venue_by_slug(mb_strtolower(get_param('l'))) : null;
if (!$venue) {
    http_response_code(404);
    exit;
}
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo json_encode([
    'name' => (string) $venue['name'],
    'short_name' => mb_substr((string) $venue['name'], 0, 12),
    'description' => 'Prenotazione dei pasti · ' . $venue['name'],
    'lang' => 'it',
    'start_url' => 'index.php?l=' . $venue['slug'],
    'scope' => './',
    'display' => 'standalone',
    'theme_color' => '#13294B',
    'background_color' => '#FBF7EF',
    'icons' => [
        ['src' => '../assets/img/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => '../assets/img/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
