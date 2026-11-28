<?php
/** Pannello del locale — Cucina: piatti da preparare e presenti, pasto per pasto. Pensata per telefono e tablet. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
require __DIR__ . '/_parts.php';
pf_boot('staff', '../');
$me = staff_require('presenti');
$v = $me['venue'];
$vid = (int) $v['id'];
$day = ui_day_param();
$meal = ui_meal_param($v);
$menu = menu_get($vid, $day, $meal);
$k = kitchen_data($v, $day, $meal);
$c = $k['count'];
$stats = day_stats($v, $day);
$showNotes = can('note', $me);

$actions = can('stampe', $me) ? '<a class="btn btn-line" href="stampa.php?tipo=cucina&amp;g=' . $day . '" target="_blank" rel="noopener">' . icon('printer', 18) . 'Stampa il foglio</a>' : '';
ui_staff_start('In cucina, *' . day_it($day, 'rel') . '*', 'cucina.php', ['eyebrow' => 'Cucina', 'actions' => $actions, 'narrow' => true]);

echo '<div class="row-between">' . ui_day_nav($day, 'cucina.php?p=' . $meal . '&g=');
$tabs = [];
foreach ((array) $v['settings']['meals'] as $m) {
    $tabs[] = ['cucina.php?g=' . $day . '&p=' . $m, PF_MEALS[$m], $m === $meal, $stats[$m]['ok'] + $stats[$m]['wait']];
}
echo ui_seg($tabs, 'Pasto') . '</div>';

$mealWord = $meal === 'pranzo' ? 'del pranzo' : 'della cena';
$status = (string) ($menu['status'] ?? 'nuovo');
echo '<p class="status-line' . ($status === 'pubblicato' ? ' live' : '') . '">' . match ($status) {
    'pubblicato' => icon('check', 18) . '<span>Menù pubblicato: le risposte arrivano fino alle <strong>' . date('H:i', meal_cutoff($v, $day, $meal, $menu)) . '</strong>.</span>',
    'chiuso' => icon('clock', 18) . '<span>Risposte chiuse: questi sono i numeri definitivi.</span>',
    default => icon('info', 18) . '<span>Menù ' . $mealWord . ' non ancora pubblicato: per ora vedi chi ha prenotato.</span>',
} . '</p>';

if (can('menu', $me) && feature('foto', $v) && in_array($status, ['nuovo', 'bozza'], true) && $day >= today()) {
    echo '<form method="post" action="menu.php" enctype="multipart/form-data" class="card card-cream stack-s">' . csrf_field()
        . '<input type="hidden" name="g" value="' . $day . '"><input type="hidden" name="p" value="' . $meal . '"><input type="hidden" name="azione" value="foto_rapida">'
        . '<label class="btn btn-dark btn-big btn-block" for="foto-rapida">' . icon('camera', 22) . 'Fotografa il menù ' . $mealWord . '</label>'
        . '<input class="sr-only" type="file" id="foto-rapida" name="foto" accept="image/jpeg,image/png,image/webp" capture="environment" data-autosubmit>'
        . '<p class="small muted">Scatti la foto e il menù è pubblicato. Per i piatti a scelta usa <a href="menu.php?g=' . $day . '&amp;p=' . $meal . '">Menù e pasti</a>.</p></form>';
}

echo '<div class="kpis kpis-3">' . ui_kpi($status === 'nuovo' || $status === 'bozza' ? 'Prenotati' : 'Confermati', $status === 'nuovo' || $status === 'bozza' ? $c['total'] : $c['confirmed'],
    $c['waiting'] && $status === 'pubblicato' ? $c['waiting'] . ' senza risposta' : '', 'navy')
    . ui_kpi('Con diete', $showNotes ? $c['diets'] : '—', '') . ui_kpi('Varianti', $c['variants'], '', $c['variants'] ? 'zaff' : '') . '</div>';

if ($k['totals']) {
    echo '<section class="card"><div class="card-head"><h2>Piatti da preparare</h2></div><div class="totals">';
    foreach ($k['totals'] as $section => $items) {
        echo '<div><h3>' . e($section) . '</h3>';
        foreach ($items as $name => $n) {
            echo '<div class="total-row"><span>' . e((string) $name) . '</span><strong>' . (int) $n . '</strong></div>';
        }
        echo '</div>';
    }
    echo '</div></section>';
}

if (!$k['groups']) {
    echo '<section class="card">' . ui_empty('Nessuno ha prenotato questo pasto.', 'utensils') . '</section>';
}
$reqByBooking = [];
foreach (q('SELECT id, booking_id FROM ' . t('requests') . " WHERE venue_id = ? AND kind = 'variante' AND status = 'attesa' AND day = ? AND meal = ?", [$vid, $day, $meal])->fetchAll() as $r) {
    $reqByBooking[(int) $r['booking_id']] = (int) $r['id'];
}
foreach ($k['groups'] as $g) {
    $n = count($g['people']);
    echo '<section class="card"><div class="card-head"><h2>' . e($g['company']) . '</h2><p>' . $n . ($n === 1 ? ' persona' : ' persone') . '</p></div>';
    if ($g['note'] !== '') {
        echo '<p class="co-note">' . icon('info', 16) . 'Nota società: ' . e($g['note']) . '</p>';
    }
    echo '<div class="list-line">';
    foreach ($g['people'] as $p) {
        echo '<div class="person"><div class="person-top"><span class="person-name">' . e($p['name']) . '</span>' . ($p['formula'] !== '' ? ui_tag($p['formula']) : '')
            . ($p['status'] === 'prenotato' && $status === 'pubblicato' ? ui_tag('Non ha ancora risposto', 'zaff') : '') . '</div>';
        if ($showNotes && $p['tags']) {
            echo '<div class="tags">' . ui_guest_tags($p['tags']) . '</div>';
        }
        if ($p['choice'] !== '') {
            echo '<p class="small muted">' . e($p['choice']) . '</p>';
        }
        if ($p['variant'] !== '') {
            echo '<div class="variant"><div><strong>Variante' . ($p['variant_status'] === 'attesa' ? ' richiesta' : ($p['variant_status'] === 'approvata' ? ' approvata' : ' rifiutata')) . ':</strong> ' . e($p['variant']) . '</div>';
            if ($p['variant_status'] === 'attesa' && can('richieste', $me) && isset($reqByBooking[$p['booking_id']])) {
                $back = 'cucina.php?g=' . $day . '&p=' . $meal;
                echo '<form method="post" action="richieste.php" class="row">' . csrf_field() . '<input type="hidden" name="id" value="' . $reqByBooking[$p['booking_id']] . '"><input type="hidden" name="back" value="' . e($back) . '">'
                    . '<button type="submit" name="esito" value="si" class="btn btn-small btn-dark">Approva</button><button type="submit" name="esito" value="no" class="btn btn-small btn-line">Rifiuta</button></form>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</div></section>';
}
ui_shell_end();
