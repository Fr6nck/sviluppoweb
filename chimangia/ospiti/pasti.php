<?php
/**
 * Notti e pasti dell'ospite: una settimana alla volta, entro i giorni prenotabili.
 * Prima dell'orario limite le modifiche valgono subito; dopo, diventano richieste per la reception.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
pf_boot('guest', '../');
require __DIR__ . '/_comuni.php';

$me = guest_require();
$venue = $me['venue'];
$s = $venue['settings'];
$hotel = feature('hotel', $venue);
$meals = array_values(array_intersect(array_keys(PF_MEALS), (array) $s['meals']));

// Settimane: da oggi fino all'ultimo giorno prenotabile, sette giorni per pagina.
$horizon = max(1, (int) $s['horizon']);
$pages = (int) ceil(($horizon + 1) / 7);
$w = max(0, min($pages - 1, (int) (is_post() ? post('w') : get_param('w'))));
$lastDay = day_add(today(), $horizon);
$days = [];
for ($i = 0; $i < 7; $i++) {
    $day = day_add(today(), $w * 7 + $i);
    if ($day <= $lastDay) {
        $days[] = $day;
    }
}
$pending = g_pending((int) $me['id']);
$mealLocked = static fn (string $day, string $meal): bool => isset($pending[$day . '|' . $meal . '|disdetta']) || isset($pending[$day . '|' . $meal . '|aggiunta']);

if (is_post()) {
    pf_csrf_check();
    $res = ['ok' => 0, 'richiesta' => 0, 'no' => 0];
    $inMeal = is_array($_POST['meal'] ?? null) ? $_POST['meal'] : [];
    $inNight = is_array($_POST['night'] ?? null) ? $_POST['night'] : [];
    foreach ($days as $day) {
        foreach ($meals as $meal) {
            if (!meal_served($venue, $day, $meal) || $mealLocked($day, $meal)) {
                continue;   // caselle non modificabili: non fanno parte dell'invio
            }
            $want = is_array($inMeal[$day] ?? null) && !empty($inMeal[$day][$meal]);
            $r = booking_toggle($venue, $me, $day, $meal, $want);
            if ($r !== 'same') {
                $res[$r]++;
            }
        }
        if ($hotel && !isset($pending[$day . '||notte'])) {
            $r = night_toggle($venue, $me, $day, !empty($inNight[$day]));
            if ($r !== 'same') {
                $res[$r]++;
            }
        }
    }
    $n = static fn (int $k, string $one, string $many): string => $k === 1 ? $one : $k . ' ' . $many;
    if ($res['ok'] > 0) {
        pf_flash('ok', 'Fatto: ' . $n($res['ok'], 'una modifica salvata', 'modifiche salvate') . '.' . ($me['status'] === 'attesa' ? ' Arrivano in cucina appena la reception conferma la tua registrazione.' : ''));
    }
    if ($res['richiesta'] > 0) {
        pf_flash('info', $n($res['richiesta'], 'Una modifica è', 'modifiche sono') . ' fuori orario: l\'abbiamo passata alla reception, che deve approvarla. Ti avvisiamo della risposta.');
    }
    if ($res['no'] > 0) {
        pf_flash('error', $n($res['no'], 'Una modifica non è consentita', 'modifiche non sono consentite') . ' (giorno passato o non prenotabile).');
    }
    if (array_sum($res) === 0) {
        pf_flash('info', 'Nessuna modifica da salvare.');
    }
    redirect('pasti.php?w=' . $w);
}

$from = $days[0];
$to = $days[count($days) - 1];
$mine = bookings_of_guest((int) $me['id'], $from, $to);
$nights = $hotel ? nights_of_guest((int) $me['id'], $from, $to) : [];
$welcome = get_param('benvenuto') === '1';

$cut = [];
foreach ($meals as $meal) {
    $cut[] = ($meal === 'pranzo' ? 'il pranzo' : 'la cena') . ' entro le <strong>' . e(hm((string) $s['cutoff_' . $meal])) . '</strong>';
}

ui_guest_start($hotel ? 'Notti e *pasti*' : 'I miei *pasti*', 'pasti.php', ['eyebrow' => $welcome ? 'Passo 2 di 2' : g_hello($me)]);

if ($welcome) {
    echo '<div class="notice notice-ok" role="status">Registrazione fatta. Ora segna ' . ($hotel ? 'le notti e i pasti' : 'i pasti') . ' che ti servono e premi «Invia richiesta».</div>';
}
if ($me['status'] === 'attesa') {
    echo '<div class="info-line">' . icon('clock') . '<span>In attesa di conferma dalla reception: le tue prenotazioni arrivano in cucina dopo la conferma.</span></div>';
} else {
    echo '<div class="info-line ok">' . icon('check') . '<span>Registrazione confermata dalla reception</span></div>';
}
if ($cut) {
    echo '<div class="info-line">' . icon('clock') . '<span>Puoi cambiare ' . implode(' e ', $cut) . '. Dopo, la modifica va approvata dalla reception.</span></div>';
}

// Navigazione tra le settimane.
$label = day_it($from, 'short') . ' – ' . day_it($to, 'short');
echo '<div class="row-between g-pager">'
    . ($w > 0 ? '<a class="round" href="pasti.php?w=' . ($w - 1) . '" aria-label="Settimana precedente">' . icon('chev-left', 18) . '</a>' : '<span class="round-gap"></span>')
    . '<span class="day-nav-label">' . e($label) . '</span>'
    . ($w < $pages - 1 ? '<a class="round" href="pasti.php?w=' . ($w + 1) . '" aria-label="Settimana successiva">' . icon('chev-right', 18) . '</a>' : '<span class="round-gap"></span>')
    . '</div>';

echo '<form method="post" action="pasti.php" class="stack-s" data-meals data-once>' . csrf_field() . '<input type="hidden" name="w" value="' . $w . '">';

/* ---------- Notti (modulo Hotel) ---------- */
if ($hotel) {
    $count = 0;
    $rooms = [];
    $waiting = false;
    echo '<section class="card stack-s"><div class="card-head"><h2>Le tue notti</h2><span>' . e(month_it(substr($from, 0, 7))) . '</span></div><div class="week">';
    foreach ($days as $day) {
        $n = $nights[$day] ?? null;
        $on = $n !== null && $n['status'] !== 'disdetta';
        $locked = isset($pending[$day . '||notte']);
        if ($on) {
            $count++;
            if ((string) ($n['room'] ?? '') !== '') {
                $rooms[(string) $n['room']] = true;
            }
        }
        $waiting = $waiting || $locked;
        $ts = (int) strtotime($day);
        echo '<label class="daybox' . ($on && $n['status'] === 'confermata' ? ' confirmed' : '') . '"><input type="checkbox" name="night[' . e($day) . ']" value="1"' . ($on ? ' checked' : '') . ($locked ? ' disabled' : '') . '>'
            . '<span>' . e(PF_DAYS_SHORT[(int) date('N', $ts)]) . '<b>' . (int) date('j', $ts) . '</b><span class="sr-only"> ' . e(PF_MONTHS[(int) date('n', $ts)]) . ($on && $n['status'] === 'confermata' ? ', confermata' : '') . '</span></span></label>';
    }
    echo '</div><p class="small muted">' . ($count === 0
        ? 'Tocca i giorni in cui dormi qui. La camera la assegna la reception.'
        : e($count === 1 ? '1 notte' : $count . ' notti') . ' in questi giorni · ' . ($rooms ? 'Camera ' . e(implode(', ', array_keys($rooms))) : 'camera assegnata dalla reception')
            . '. Le notti con il bordo giallo sono già confermate: per disdirle serve l\'approvazione della reception.')
        . ($waiting ? ' Hai una disdetta in attesa di risposta.' : '') . '</p></section>';
}

/* ---------- Pranzi e cene ---------- */
$checked = 0;
echo '<section class="card stack-s"><div class="card-head"><h2>' . e(count($meals) > 1 ? 'Pranzi e cene' : (($meals[0] ?? 'pranzo') === 'pranzo' ? 'Pranzi' : 'Cene')) . '</h2><span>Tocca per scegliere</span></div>';
echo '<div class="row">';
foreach ($meals as $meal) {
    echo '<button type="button" class="btn btn-small btn-line" data-check="' . $meal . '">' . ($meal === 'pranzo' ? 'Tutti i pranzi' : 'Tutte le cene') . '</button>';
}
echo '<button type="button" class="btn btn-small btn-line" data-check="none">Nessuno</button></div><div class="meal-list">';
foreach ($days as $day) {
    $ts = (int) strtotime($day);
    $head = $day === today() ? 'oggi' : ($day === day_add(today(), 1) ? 'domani' : PF_DAYS[(int) date('N', $ts)]);
    echo '<div class="meal-day' . (count($meals) === 1 ? ' one' : '') . '"><div class="meal-date"><b>' . e($head) . '</b>' . e(day_it($day, 'short')) . '</div>';
    $info = '';
    foreach ($meals as $meal) {
        $name = PF_MEALS[$meal];
        if (!meal_served($venue, $day, $meal)) {
            echo '<label class="mealbox off"><input type="checkbox" disabled><span>' . e($name) . '<small>chiuso</small></span></label>';
            continue;
        }
        $b = $mine[$day . '|' . $meal] ?? null;
        $on = booking_active($b);
        $locked = $mealLocked($day, $meal);
        $menu = menu_get((int) $venue['id'], $day, $meal);
        $open = meal_open($venue, $day, $meal, $menu);
        [$label, , $todo] = g_state($venue, $b, $day, $meal, $pending);
        $small = $locked ? 'in attesa' : (!$open ? 'fuori orario' : ($on ? ($b['status'] === 'confermato' ? 'confermato' : 'prenotato') : ''));
        if ($on) {
            $checked++;
        }
        echo '<label class="mealbox' . ($on && $b['status'] === 'confermato' ? ' answered' : '') . (!$open ? ' closed' : '') . '">'
            . '<input type="checkbox" name="meal[' . e($day) . '][' . $meal . ']" value="1" data-meal="' . $meal . '"' . ($on ? ' checked' : '') . ($locked ? ' disabled' : '') . '>'
            . '<span>' . e($name) . ($small !== '' ? '<small>' . e($small) . '</small>' : '') . '</span></label>';
        // Riga di dettaglio sotto il giorno: scelta fatta, menù da guardare, richieste in attesa.
        $link = $menu && $menu['status'] !== 'bozza' ? 'menu.php?g=' . $day . '&amp;p=' . $meal : '';
        if ($locked) {
            $info .= '<span>' . e($name . ': ' . mb_strtolower($label)) . '</span>';
        } elseif ($on && $b['status'] === 'confermato') {
            $choice = choices_text(choices_decode((string) $b['choices']));
            $info .= '<span>' . e($name . ': ' . ($choice !== '' ? $choice : 'confermato'))
                . ((string) $b['variant'] !== '' ? ' · ' . e(mb_strtolower(explode(' · ', $label)[1] ?? 'variante')) : '')
                . ($link !== '' && $open ? ' · <a href="' . $link . '">Cambia</a>' : '') . '</span>';
        } elseif ($todo) {
            $info .= '<span><strong>' . e($name . ': menù pronto, rispondi entro le ' . date('H:i', meal_cutoff($venue, $day, $meal, $menu))) . '</strong> · <a href="' . $link . '">Scegli i piatti</a></span>';
        } elseif ((string) ($b['status'] ?? '') === 'scaduto') {
            $info .= '<span>' . e($name . ': ' . mb_strtolower($label)) . '</span>';
        } elseif ($link !== '') {
            $info .= '<span>' . e($name) . ': <a href="' . $link . '">guarda il menù</a></span>';
        }
    }
    echo ($info !== '' ? '<div class="meal-info">' . $info . '</div>' : '') . '</div>';
}
echo '</div></section>';

echo '<button type="submit" class="btn btn-dark btn-big btn-block"><span>Invia richiesta (<span data-meal-count>' . $checked . '</span>&nbsp;pasti)</span></button></form>';
ui_guest_end();
