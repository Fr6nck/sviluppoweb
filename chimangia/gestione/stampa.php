<?php
/** Pannello del locale — Fogli stampabili (cucina, camere). Pagina senza menu, pronta per la stampa. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
require __DIR__ . '/_parts.php';
pf_boot('staff', '../');
$me = staff_require('stampe');
$v = $me['venue'];
$vid = (int) $v['id'];
$day = ui_day_param();
$tipo = get_param('tipo') === 'camere' ? 'camere' : 'cucina';
if ($tipo === 'camere' && !(feature('hotel', $v) && can('camere', $me))) {
    ui_die('Accesso non consentito', 'Il tuo ruolo non può stampare questo prospetto.', 403);
}
$showNotes = can('note', $me);

ui_head(($tipo === 'cucina' ? 'Foglio cucina' : 'Camere') . ' ' . day_it($day, 'short'), 'pf-print', ['site' => $v['name']]);
echo '<div class="print-bar"><button type="button" class="btn btn-dark" data-print>' . icon('printer', 18) . 'Stampa o salva in PDF</button><a class="btn btn-line" href="stampe.php?g=' . $day . '">Chiudi</a></div><main class="sheet" id="contenuto">';

if ($tipo === 'cucina') {
    $data = [];
    foreach ((array) $v['settings']['meals'] as $m) {
        $data[$m] = kitchen_data($v, $day, $m);
    }
    echo '<header class="sheet-head"><div><p class="sheet-brand">' . e($v['name']) . '</p><h1>Foglio cucina · ' . e(day_it($day)) . '</h1></div><p>Stampato alle ' . date('H:i') . '<br>';
    echo implode(' · ', array_map(static fn ($m) => PF_MEALS[$m] . ' ' . $data[$m]['count']['total'], array_keys($data))) . '</p></header>';
    foreach ($data as $m => $k) {
        $c = $k['count'];
        $bits = [$c['total'] . ($c['total'] === 1 ? ' coperto' : ' coperti')];
        if ($showNotes && $c['diets']) {
            $bits[] = $c['diets'] . ($c['diets'] === 1 ? ' dieta' : ' diete');
        }
        if ($c['variants']) {
            $bits[] = $c['variants'] . ($c['variants'] === 1 ? ' variante' : ' varianti');
        }
        if ($c['waiting']) {
            $bits[] = $c['waiting'] . ' senza risposta';
        }
        echo '<section><h2>' . PF_MEALS[$m] . '</h2><p class="sheet-sub">' . e(implode(' · ', $bits)) . '</p>';
        if ($k['totals']) {
            echo '<div class="sheet-totals">';
            foreach ($k['totals'] as $items) {
                foreach ($items as $name => $n) {
                    echo '<div><span>' . e((string) $name) . '</span><strong>' . (int) $n . '</strong></div>';
                }
            }
            echo '</div>';
        }
        if ($k['groups']) {
            echo '<table><thead><tr><th>Nome</th><th>Società</th><th>Piatti</th><th>' . ($showNotes ? 'Diete, note, varianti' : 'Varianti') . '</th></tr></thead><tbody>';
            foreach ($k['groups'] as $g) {
                foreach ($g['people'] as $p) {
                    $notes = [];
                    foreach ($showNotes ? $p['tags'] : [] as $t) {
                        $notes[] = $t['type'] === 'diet' ? '<strong>' . e((string) $t['label']) . '</strong>' : e((string) $t['label']);
                    }
                    if ($p['variant'] !== '' && $p['variant_status'] !== 'rifiutata') {
                        $notes[] = 'Variante' . ($p['variant_status'] === 'attesa' ? ' (da approvare)' : '') . ': ' . e($p['variant']);
                    }
                    echo '<tr><td>' . e($p['name']) . '</td><td>' . e($g['company']) . ($g['note'] !== '' ? '<br><small>' . e($g['note']) . '</small>' : '') . '</td><td>' . e($p['choice'] !== '' ? $p['choice'] : '—') . '</td><td>' . ($notes ? implode(' · ', $notes) : '—') . '</td></tr>';
                }
            }
            echo '</tbody></table>';
        } else {
            echo '<p>Nessuna prenotazione.</p>';
        }
        echo '</section>';
    }
    echo '<footer class="sheet-foot"><span>Documento interno' . ($showNotes ? ' · contiene dati su diete e intolleranze' : '') . '</span><span>ChiMangia</span></footer>';
} else {
    $to = day_add($day, 6);
    $occ = room_occupancy($vid, $day, $to);
    echo '<header class="sheet-head"><div><p class="sheet-brand">' . e($v['name']) . '</p><h1>Camere · ' . e(day_it($day, 'short') . ' – ' . day_it($to, 'short')) . '</h1></div><p>Stampato il ' . date('d/m/Y H:i') . '</p></header><table><thead><tr><th>Camera</th>';
    for ($i = 0; $i < 7; $i++) {
        echo '<th>' . e(day_it(day_add($day, $i), 'short')) . '</th>';
    }
    echo '</tr></thead><tbody>';
    $rows = rooms_all($vid);
    $rows[] = ['id' => 0, 'name' => 'Da assegnare'];
    foreach ($rows as $r) {
        if ((int) $r['id'] === 0 && empty($occ[0])) {
            continue;
        }
        echo '<tr><td><strong>' . e((string) $r['name']) . '</strong></td>';
        for ($i = 0; $i < 7; $i++) {
            $names = array_map(static fn ($n) => e(guest_name($n)), $occ[(int) $r['id']][day_add($day, $i)] ?? []);
            echo '<td>' . implode('<br>', $names) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table><footer class="sheet-foot"><span>Documento interno</span><span>ChiMangia</span></footer>';
}
echo '</main></body></html>';
