<?php
/** Pannello del locale — Prospetti e stampe. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
require __DIR__ . '/_parts.php';
pf_boot('staff', '../');
$me = staff_require('stampe');
$v = $me['venue'];
$day = ui_day_param();

ui_staff_start('Prospetti *e stampe*', 'stampe.php', ['lead' => 'Fogli pronti da stampare o da salvare in PDF dal browser.']);
echo '<form method="get" action="stampe.php" class="row">' . '<label for="g">Giorno</label><input class="grow" type="date" id="g" name="g" value="' . $day . '" data-autosubmit><button type="submit" class="btn btn-line">Cambia giorno</button></form>';
echo '<div class="grid-3">';
$card = static function (string $ic, string $title, string $text, string $href, string $cta, bool $blank = true): string {
    return '<section class="card stack-s">' . icon($ic, 28) . '<h2>' . e($title) . '</h2><p class="muted small grow">' . e($text) . '</p><p><a class="btn btn-dark" href="' . e($href) . '"' . ($blank ? ' target="_blank" rel="noopener"' : '') . '>' . e($cta) . '</a></p></section>';
};
echo $card('pot', 'Foglio cucina', 'Pranzo e cena di ' . day_it($day, 'rel') . ': totali per piatto e presenti con diete, note e varianti.', 'stampa.php?tipo=cucina&g=' . $day, 'Apri il foglio');
if (feature('hotel', $v) && can('camere', $me)) {
    echo $card('bed', 'Camere della settimana', 'Chi dorme in quale camera, nei sette giorni da ' . day_it($day, 'short') . '.', 'stampa.php?tipo=camere&g=' . $day, 'Apri il prospetto');
}
if (feature('riepilogo', $v) && can('prezzi', $me)) {
    echo $card('chart', 'Riepilogo mensile', 'Pasti e importi del mese, società per società. Si esporta anche per Excel.', 'riepilogo.php?m=' . substr($day, 0, 7), 'Apri il riepilogo', false);
}
echo '</div>';
ui_shell_end();
