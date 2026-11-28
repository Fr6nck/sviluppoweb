<?php
/** Avvisi del locale per l'ospite. Aprendo la pagina, quelli nuovi risultano letti. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
pf_boot('guest', '../');
require __DIR__ . '/_comuni.php';

$me = guest_require();
$rows = q('SELECT * FROM ' . t('notices') . ' WHERE guest_id = ? AND venue_id = ? ORDER BY id DESC LIMIT 50', [$me['id'], $me['venue_id']])->fetchAll();
// Si segnano come letti prima di disegnare la pagina: la campanella torna pulita.
q('UPDATE ' . t('notices') . ' SET read_at = ? WHERE guest_id = ? AND read_at IS NULL', [now(), $me['id']]);

ui_guest_start('I tuoi *avvisi*', '', ['back' => 'index.php', 'back_label' => 'Home']);
if (!$rows) {
    echo '<div class="card">' . ui_empty('Nessun avviso per ora. Qui trovi le novità del locale: menù pronti, conferme, risposte alle tue richieste.', 'bell') . '</div>';
} else {
    echo '<section class="card card-white"><div class="list-line">';
    foreach ($rows as $n) {
        $href = g_href((string) $n['href']);
        $when = date('Y-m-d', (int) strtotime((string) $n['created_at']));
        echo '<div' . ($n['read_at'] === null ? ' class="unread"' : '') . '>'
            . '<p><strong>' . e((string) $n['title']) . '</strong>' . ($n['read_at'] === null ? '<span class="sr-only"> (nuovo)</span>' : '') . '</p>'
            . ((string) $n['body'] !== '' ? '<p class="small">' . e((string) $n['body']) . '</p>' : '')
            . '<p class="small muted">' . e(day_it($when, 'rel') . ', ' . date('H:i', (int) strtotime((string) $n['created_at'])))
            . ($href !== '' ? ' · <a href="' . e($href) . '">Apri</a>' : '') . '</p></div>';
    }
    echo '</div></section>';
}
ui_guest_end();
