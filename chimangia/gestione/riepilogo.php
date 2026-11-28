<?php
/** Pannello del locale — Riepilogo mensile per società: pasti, notti, importi. Esportabile per Excel. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
require __DIR__ . '/_parts.php';
pf_boot('staff', '../');
$me = staff_require('prezzi', 'riepilogo');
$v = $me['venue'];
$vid = (int) $v['id'];
$ym = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', get_param('m')) ? get_param('m') : date('Y-m');
$r = report_month($vid, $ym);
$hotel = feature('hotel', $v);
$money = $v['settings']['price_mode'] !== 'nessuno';

if (get_param('csv') === '1') {
    $rows = [];
    foreach ($r['companies'] as $c) {
        foreach ($c['guests'] as $g) {
            $row = [$c['name'], $g['name'], $g['pranzi'], $g['cene'], $g['pasti']];
            if ($hotel) {
                $row[] = $g['notti'];
            }
            if ($money) {
                $row[] = number_format($g['totale'], 2, ',', '');
            }
            $rows[] = $row;
        }
    }
    pf_event($vid, 'riepilogo_esportato', $ym);
    csv_download('riepilogo-' . $v['slug'] . '-' . $ym . '.csv', array_merge(['Società', 'Ospite', 'Pranzi', 'Cene', 'Pasti'], $hotel ? ['Notti'] : [], $money ? ['Importo €'] : []), $rows);
}

$prev = date('Y-m', (int) strtotime($ym . '-01 -1 month'));
$next = date('Y-m', (int) strtotime($ym . '-01 +1 month'));
$actions = '<a class="btn btn-line" href="riepilogo.php?m=' . $ym . '&amp;csv=1">' . icon('download', 18) . 'Esporta per Excel</a><button type="button" class="btn btn-line" data-print>' . icon('printer', 18) . 'Stampa</button>';
ui_staff_start('Riepilogo di *' . month_it($ym) . '*', 'riepilogo.php', ['eyebrow' => 'Per società', 'actions' => $actions]);

echo '<div class="row no-print"><a class="round" href="riepilogo.php?m=' . $prev . '" aria-label="Mese precedente">' . icon('chev-left', 18) . '</a>'
    . '<form method="get" action="riepilogo.php" class="row"><label class="sr-only" for="m">Mese</label><input type="month" id="m" name="m" value="' . $ym . '" data-autosubmit></form>'
    . ($next <= date('Y-m') ? '<a class="round" href="riepilogo.php?m=' . $next . '" aria-label="Mese successivo">' . icon('chev-right', 18) . '</a>' : '') . '</div>';

$tt = $r['total'];
echo '<div class="kpis">' . ui_kpi('Pasti serviti', $tt['pasti'], $tt['pranzi'] . ' pranzi · ' . $tt['cene'] . ' cene') . ui_kpi('Ospiti', $tt['ospiti'], count($r['companies']) . (count($r['companies']) === 1 ? ' società' : ' società'))
    . ($hotel ? ui_kpi('Notti', $tt['notti'], 'confermate') : '') . ($money ? ui_kpi('Importo', euro($tt['totale']), 'IVA come da listino', 'zaff') : '') . '</div>';

echo '<section class="card">';
if (!$r['companies']) {
    echo ui_empty('In questo mese non ci sono pasti.', 'chart');
} else {
    $cols = 5 + ($hotel ? 1 : 0) + ($money ? 1 : 0);
    echo '<div class="table-wrap"><table class="table"><thead><tr><th>Ospite</th><th class="num">Pranzi</th><th class="num">Cene</th><th class="num">Pasti</th>' . ($hotel ? '<th class="num">Notti</th>' : '') . ($money ? '<th class="num">Importo</th>' : '') . '</tr></thead><tbody>';
    foreach ($r['companies'] as $c) {
        echo '<tr class="table-group"><td>' . e((string) $c['name']) . ' <span class="muted small">· ' . count($c['guests']) . (count($c['guests']) === 1 ? ' ospite' : ' ospiti') . '</span></td><td class="num">' . $c['pranzi'] . '</td><td class="num">' . $c['cene'] . '</td><td class="num">' . $c['pasti'] . '</td>'
            . ($hotel ? '<td class="num">' . $c['notti'] . '</td>' : '') . ($money ? '<td class="num">' . e(euro($c['totale'])) . '</td>' : '') . '</tr>';
        foreach ($c['guests'] as $g) {
            echo '<tr><td><a href="ospite.php?id=' . $g['id'] . '">' . e($g['name']) . '</a></td><td class="num">' . $g['pranzi'] . '</td><td class="num">' . $g['cene'] . '</td><td class="num">' . $g['pasti'] . '</td>'
                . ($hotel ? '<td class="num">' . $g['notti'] . '</td>' : '') . ($money ? '<td class="num">' . e(euro($g['totale'])) . '</td>' : '') . '</tr>';
        }
    }
    echo '</tbody><tfoot><tr><td>Totale</td><td class="num">' . $tt['pranzi'] . '</td><td class="num">' . $tt['cene'] . '</td><td class="num">' . $tt['pasti'] . '</td>' . ($hotel ? '<td class="num">' . $tt['notti'] . '</td>' : '')
        . ($money ? '<td class="num">' . e(euro($tt['totale'])) . '</td>' : '') . '</tr></tfoot></table></div>';
}
echo '<p class="small muted">Contano i pasti confermati e quelli prenotati e non disdetti, fino a ' . e(day_it($r['to'], 'short')) . '. Gli importi vengono dalle formule o dai prezzi del menù scelto.</p></section>';
ui_shell_end();
