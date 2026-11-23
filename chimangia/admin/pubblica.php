<?php
/** Pubblica tutte le bozze in sospeso (dal cruscotto). */
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

$user = require_login();
if (!is_post()) {
    redirect('index.php');
}
csrf_check();

$pending = content_unpublished_areas();
if (!$pending) {
    flash('info', 'Non c\'erano bozze da pubblicare.');
    redirect('index.php');
}
$labels = array_map(static fn ($a) => content_areas()[$a]['label'] ?? $a, $pending);
content_publish(null, (int) $user['id'], 'Pubblicazione di: ' . implode(', ', $labels));
audit('pubblicazione', implode(', ', $labels));
flash('ok', 'Pubblicato. Il sito mostra ora la nuova versione.');
redirect('index.php');
