<?php
/** Pannello del locale — Oggi: la giornata a colpo d'occhio. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
require __DIR__ . '/_parts.php';
pf_boot('staff', '../');
$me = staff_require();
$v = $me['venue'];
$vid = (int) $v['id'];

// Chi vede solo la cucina parte dalla cucina.
if (!can('conferma', $me) && !can('richieste', $me) && !can('menu', $me) && can('presenti', $me)) {
    redirect('cucina.php');
}

if (is_post()) {
    pf_csrf_check();
    if (post('azione') === 'guida_fatta' && (int) $me['is_owner'] === 1) {
        venue_patch($vid, 'settings', ['guide_done' => true]);
    }
    redirect('index.php');
}

$today = today();
$s = day_stats($v, $today);
$hotel = feature('hotel', $v);
$hour = (int) date('G');
$hello = $hour < 13 ? 'Buongiorno' : ($hour < 18 ? 'Buon pomeriggio' : 'Buonasera');

$actions = can('stampe', $me) ? '<a class="btn btn-line" href="stampa.php?tipo=cucina&amp;g=' . $today . '" target="_blank" rel="noopener">' . icon('printer', 18) . 'Stampa foglio cucina</a>' : '';
ui_staff_start($hello . ', ecco *la giornata*', 'index.php', ['eyebrow' => day_it($today, 'full'), 'actions' => $actions]);

/* ---------- Primi passi (solo finché servono) ---------- */
$guests = (int) q_value('SELECT COUNT(*) FROM ' . t('guests') . ' WHERE venue_id = ?', [$vid]);
$menus = (int) q_value('SELECT COUNT(*) FROM ' . t('menus') . " WHERE venue_id = ? AND status <> 'bozza'", [$vid]);
$roomsN = (int) q_value('SELECT COUNT(*) FROM ' . t('rooms') . ' WHERE venue_id = ?', [$vid]);
$staffN = (int) q_value('SELECT COUNT(*) FROM ' . t('staff') . ' WHERE venue_id = ?', [$vid]);
$steps = [
    [$guests > 0, 'Invita i clienti', 'Manda il collegamento ai tuoi clienti: si registrano dal telefono.', '#invito', 'Copia il collegamento'],
    [$menus > 0, 'Pubblica il primo menù', 'Scrivi i piatti o fotografa il foglio: i clienti scelgono entro l\'orario.', 'menu.php', 'Componi il menù'],
];
if ($hotel) {
    $steps[] = [$roomsN > 0, 'Aggiungi le camere', 'Così la reception assegna le notti con un tocco.', 'camere.php', 'Vai alle camere'];
}
$steps[] = [$staffN > 1, 'Fai entrare reception e cucina', 'Ognuno con il suo accesso e solo quello che gli serve.', 'staff.php', 'Aggiungi lo staff'];
$allDone = !array_filter($steps, static fn ($st) => !$st[0]);
if ((int) $me['is_owner'] === 1 && !$allDone && empty($v['settings']['guide_done'])) {
    echo '<section class="card card-cream" aria-labelledby="guida"><div class="card-head"><h2 id="guida">Per partire</h2>'
        . ui_post_btn('index.php', ['azione' => 'guida_fatta'], 'Nascondi', 'link-btn small') . '</div><div class="steps">';
    foreach ($steps as $i => [$done, $title, $text, $href, $cta]) {
        echo '<div class="step' . ($done ? ' done' : '') . '"><span class="step-n">' . ($done ? icon('check', 18) : $i + 1) . '</span><h3>' . e($title) . '</h3><p>' . e($text) . '</p>'
            . ($done ? '<span class="tag tag-ok">Fatto</span>' : '<a class="btn btn-small btn-line" href="' . e($href) . '">' . e($cta) . '</a>') . '</div>';
    }
    echo '</div></section>';
}

/* ---------- Numeri del giorno ---------- */
$mealSub = static function (array $m, ?array $menu): string {
    if ($m['ok'] + $m['wait'] === 0) {
        return 'nessuna prenotazione';
    }
    if ($m['wait'] > 0 && (!$menu || $menu['status'] === 'bozza')) {
        return $m['wait'] === 1 ? 'prenotato · menù da pubblicare' : 'prenotati · menù da pubblicare';
    }
    return $m['wait'] === 0 ? 'tutti confermati' : ($m['wait'] === 1 ? '1 non ha ancora risposto' : $m['wait'] . ' non hanno ancora risposto');
};
echo '<div class="kpis">';
if ($hotel) {
    $n = $s['nights'];
    echo ui_kpi('Ospiti stanotte', $n['guests'], $n['guests'] ? $n['companies'] . ' società · ' . $n['rooms'] . ($n['rooms'] === 1 ? ' camera' : ' camere') : 'nessun pernotto');
}
if (in_array('pranzo', (array) $v['settings']['meals'], true)) {
    echo ui_kpi('Pranzo oggi', $s['pranzo']['ok'] + $s['pranzo']['wait'], $mealSub($s['pranzo'], menu_get($vid, $today, 'pranzo')));
}
if (in_array('cena', (array) $v['settings']['meals'], true)) {
    echo ui_kpi('Cena oggi', $s['cena']['ok'] + $s['cena']['wait'], $mealSub($s['cena'], menu_get($vid, $today, 'cena')));
}
$todo = $s['pending'] + $s['requests'];
echo ui_kpi('Da gestire', $todo, $todo ? $s['pending'] . ' registrazioni · ' . $s['requests'] . ' richieste' : 'tutto in ordine', $todo ? 'zaff' : '');
if (!$hotel) {
    echo ui_kpi('Clienti del mese', venue_usage($vid), 'con almeno un pasto prenotato');
}
echo '</div>';

echo '<div class="grid-2 dash-grid"><div class="stack">';

/* ---------- Registrazioni da confermare ---------- */
if (can('conferma', $me)) {
    $pending = pending_guests($v, 6);
    echo '<section class="card" aria-labelledby="reg"><div class="card-head"><h2 id="reg">Registrazioni da confermare</h2><p>'
        . ($hotel ? 'Controlla, correggi, assegna la camera' : 'Controlla e conferma: da quel momento contano in cucina') . '</p></div>';
    echo $pending ? registrations_table($me, $pending, 'index.php') : ui_empty('Nessuna registrazione in attesa.');
    if ($s['pending'] > count($pending)) {
        echo '<p class="small"><a href="registrazioni.php">Vedi tutte le ' . $s['pending'] . ' registrazioni</a></p>';
    }
    echo '</section>';
}

/* ---------- Collegamento per i clienti ---------- */
echo '<section class="card card-cream" id="invito" aria-labelledby="inv"><div class="card-head"><h2 id="inv">Il collegamento per i tuoi clienti</h2><p>Si registrano e prenotano da qui</p></div>'
    . '<div class="link-box"><code id="guest-link">' . e(guest_link($v)) . '</code><button type="button" class="btn btn-small btn-dark" data-copy="#guest-link">Copia</button>'
    . (feature('whatsapp', $v) ? '<a class="btn btn-small btn-line" target="_blank" rel="noopener" href="https://wa.me/?text=' . rawurlencode('Prenota i pasti da ' . $v['name'] . ' dal telefono: ' . guest_link($v)) . '">' . icon('message', 16) . 'Invia su WhatsApp</a>' : '')
    . '</div></section>';

echo '</div><div class="stack">';

/* ---------- Richieste da approvare ---------- */
if (can('richieste', $me)) {
    $reqs = requests_list($vid, 'attesa', 3);
    echo '<section class="card" aria-labelledby="rich"><div class="card-head"><h2 id="rich">Richieste da approvare</h2></div>';
    if ($reqs) {
        echo '<div class="list-line">';
        foreach ($reqs as $r) {
            echo request_card($r, 'index.php');
        }
        echo '</div>';
        if ($s['requests'] > count($reqs)) {
            echo '<p class="small"><a href="richieste.php">Vedi tutte le ' . $s['requests'] . ' richieste</a></p>';
        }
    } else {
        echo ui_empty('Nessuna richiesta in sospeso.');
    }
    echo '</section>';
}

/* ---------- Avviso menù su WhatsApp ---------- */
if (feature('whatsapp', $v) && can('menu', $me)) {
    $open = null;
    foreach ([[$today, 'pranzo'], [$today, 'cena'], [day_add($today, 1), 'pranzo'], [day_add($today, 1), 'cena']] as [$d, $m]) {
        $menu = menu_get($vid, $d, $m);
        if ($menu && $menu['status'] === 'pubblicato' && meal_open($v, $d, $m, $menu)) {
            $open = [$d, $m];
            break;
        }
    }
    echo '<section class="card card-navy" aria-labelledby="wa"><div class="card-head"><h2 id="wa">Avviso menù su WhatsApp</h2></div>';
    if ($open) {
        [$d, $m] = $open;
        $text = wa_message($v, $d, $m);
        $people = q(
            'SELECT b.id, b.wa_sent_at, g.first_name, g.last_name, g.phone FROM ' . t('bookings') . ' b JOIN ' . t('guests') . " g ON g.id = b.guest_id
             WHERE b.venue_id = ? AND b.day = ? AND b.meal = ? AND b.status = 'prenotato' AND g.status = 'confermato' ORDER BY g.last_name LIMIT 12",
            [$vid, $d, $m]
        )->fetchAll();
        echo '<div class="stack-s"><p class="wa-text" id="wa-text">' . e($text) . '</p>'
            . '<div><button type="button" class="btn btn-small btn-ghost" data-copy="#wa-text">Copia testo</button></div>';
        if ($people) {
            echo '<p class="small muted">Non hanno ancora risposto (' . PF_MEALS[$m] . ' di ' . e(day_it($d, 'rel')) . '):</p><div>';
            foreach ($people as $p) {
                echo '<div class="wa-row"><span>' . e(guest_name($p)) . '</span>' . ($p['wa_sent_at']
                    ? '<span class="small">Inviato ✓</span>'
                    : '<a class="btn btn-small btn-zaff" target="_blank" rel="noopener" href="wa.php?b=' . (int) $p['id'] . '&amp;k=' . e(csrf_token()) . '">Apri WhatsApp</a>') . '</div>';
            }
            echo '</div>';
        } else {
            echo '<p class="small muted">Hanno già risposto tutti.</p>';
        }
        echo '</div>';
    } else {
        echo '<p class="muted small">Quando pubblichi un menù, qui trovi il messaggio pronto da mandare a chi non ha ancora risposto.</p>'
            . '<p><a class="btn btn-small btn-ghost" href="menu.php">Componi il menù</a></p>';
    }
    echo '</section>';
}

echo '</div></div>';
ui_shell_end();
