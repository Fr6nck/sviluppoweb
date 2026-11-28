<?php
/**
 * Menù per l'ospite.
 *   menu.php                      → i prossimi pasti con un menù già uscito
 *   menu.php?g=AAAA-MM-GG&p=pasto → formula, piatti, bevanda, extra; conferma, variante, disdetta
 * Dopo l'orario limite la pagina è di sola lettura: le modifiche passano dalla reception.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
pf_boot('guest', '../');
require __DIR__ . '/_comuni.php';

$me = guest_require();
$venue = $me['venue'];
$s = $venue['settings'];
$day = get_param('g');
$meal = get_param('p');
$pending = g_pending((int) $me['id']);

/* =====================================================================
 * Elenco dei menù usciti
 * ===================================================================== */
if ($day === '' && $meal === '') {
    $rows = q(
        'SELECT day, meal FROM ' . t('menus') . " WHERE venue_id = ? AND status IN ('pubblicato', 'chiuso') AND day >= ? ORDER BY day, meal DESC LIMIT 30",
        [$venue['id'], today()]
    )->fetchAll();
    $mine = bookings_of_guest((int) $me['id'], today(), day_add(today(), 60));
    ui_guest_start('Il *menù*', 'menu.php', ['eyebrow' => g_hello($me)]);
    if (!$rows) {
        echo '<div class="card">' . ui_empty('Nessun menù pubblicato per ora. Quando esce, lo trovi qui' . (feature('notifiche', $venue) ? ' e ti arriva un avviso.' : '.'), 'utensils') . '</div>';
    } else {
        echo '<section class="card stack-s"><div class="list-line">';
        foreach ($rows as $r) {
            [$label, $tone] = g_state($venue, $mine[$r['day'] . '|' . $r['meal']] ?? null, (string) $r['day'], (string) $r['meal'], $pending);
            echo '<a class="g-item" href="menu.php?g=' . e((string) $r['day']) . '&amp;p=' . e((string) $r['meal']) . '"><span class="g-ico">' . icon('utensils', 22) . '</span>'
                . '<span class="grow"><strong>' . e(g_meal_title((string) $r['day'], (string) $r['meal'])) . '</strong><small>' . e($label) . '</small></span>' . icon('chev-right', 20) . '</a>';
        }
        echo '</div></section>';
    }
    echo '<a class="btn btn-line btn-block" href="pasti.php">' . icon('calendar', 18) . 'Segna altri pasti</a>';
    ui_guest_end();
    exit;
}

/* =====================================================================
 * Un pasto
 * ===================================================================== */
if (!valid_day($day) || !isset(PF_MEALS[$meal])) {
    redirect('menu.php');
}
$menu = menu_get((int) $venue['id'], $day, $meal);
$here = 'menu.php?g=' . $day . '&p=' . $meal;
$word = $meal === 'pranzo' ? 'il pranzo' : 'la cena';
$Word = $meal === 'pranzo' ? 'Pranzo' : 'Cena';
$done = $meal === 'pranzo' ? 'o' : 'a';       // disdett-o / disdett-a

if (!$menu || $menu['status'] === 'bozza') {
    ui_guest_start('Menù non ancora *pronto*', 'menu.php', ['back' => 'pasti.php', 'back_label' => 'I miei pasti', 'lead' => e(day_it($day))]);
    echo '<div class="card">' . ui_empty('Il menù di questo pasto non è ancora stato pubblicato. Quando esce, lo trovi qui.', 'clock') . '</div>';
    ui_guest_end();
    exit;
}

$data = $menu['data'];
$courses = menu_courses($menu);
$uses = menu_uses_formulas($venue, $menu);
$mode = (string) $s['price_mode'];
$effMode = $mode === 'formule' && !$uses ? 'piatto' : $mode;         // stessa regola del calcolo dell'importo
$dishPrices = $effMode === 'piatto' && (int) $menu['show_prices'] === 1;
$pricesOn = $menu['mode'] === 'scelta' && $mode !== 'nessuno' && ($uses || $dishPrices);
$open = $menu['status'] === 'pubblicato' && meal_open($venue, $day, $meal, $menu);
$limit = date('H:i', meal_cutoff($venue, $day, $meal, $menu));
$canVariant = feature('varianti', $venue);
$error = '';

if (is_post()) {
    pf_csrf_check();
    $act = post('azione');
    if ($act === 'variante' && !$canVariant) {
        $act = 'conferma';
    }
    if ($act === 'conferma' || $act === 'variante') {
        $variant = $act === 'variante' ? clean_text(post('variante'), 500) : '';
        if ($act === 'variante' && $variant === '') {
            $error = 'Scrivi la variante che vorresti.';
        } else {
            $error = (string) booking_answer($venue, $me, $menu, $_POST, $variant);
            if ($error === '') {
                pf_flash('ok', $variant !== '' ? $Word . ' confermat' . $done . '. La variante va approvata: ti avvisiamo della risposta.' : $Word . ' confermat' . $done . '. Buon appetito!');
                redirect($here);
            }
        }
    } elseif ($act === 'disdici' || $act === 'aggiungi') {
        $r = booking_toggle($venue, $me, $day, $meal, $act === 'aggiungi');
        if ($r === 'ok') {
            pf_flash('ok', $act === 'disdici' ? $Word . ' disdett' . $done . '. La cucina è stata avvisata.' : $Word . ' prenotat' . $done . '.');
        } elseif ($r === 'richiesta') {
            pf_flash('info', 'Richiesta inviata alla reception: fuori orario serve la sua approvazione. Ti avvisiamo della risposta.');
        } elseif ($r === 'no') {
            pf_flash('error', 'Questa modifica non è consentita.');
        }
        redirect($r === 'ok' && $act === 'disdici' ? 'pasti.php' : $here);
    } else {
        redirect($here);
    }
}

$b = booking_get((int) $me['id'], $day, $meal);
$active = booking_active($b);
$saved = choices_decode((string) ($b['choices'] ?? ''));
[$stateLabel, $stateTone] = g_state($venue, $b, $day, $meal, $pending);
$locked = isset($pending[$day . '|' . $meal . '|disdetta']) || isset($pending[$day . '|' . $meal . '|aggiunta']);

// Selezione da mostrare: quella appena inviata (se c'è stato un errore) oppure quella salvata.
if ($error !== '') {
    $pd = is_array($_POST['dish'] ?? null) ? $_POST['dish'] : [];
    $sel = [
        'formula' => post('formula'), 'path' => (int) post('path', '0'), 'drink' => post('drink'),
        'dish' => array_map('strval', array_filter($pd, 'is_string')),
        'extras' => array_values(array_filter(is_array($_POST['extras'] ?? null) ? $_POST['extras'] : [], 'is_string')),
        'variant' => post('variante'),
    ];
} else {
    $sel = [
        'formula' => (string) ($saved['formula']['id'] ?? ''), 'path' => (int) $saved['path'], 'drink' => (string) ($saved['drink']['id'] ?? ''),
        'dish' => array_map(static fn ($x) => (string) ($x['id'] ?? ''), (array) $saved['dishes']),
        'extras' => array_map(static fn ($x) => (string) ($x['id'] ?? ''), (array) $saved['extras']),
        'variant' => $b && (string) $b['variant_status'] === 'attesa' ? (string) $b['variant'] : '',
    ];
}

$lead = e(day_it($day)) . ' · ' . ($open
    ? 'rispondi entro le <strong>' . e($limit) . '</strong>' . (!empty($s['auto_cancel']) ? ', altrimenti ' . $word . ' viene disdett' . $done : '')
    : 'risposte chiuse alle ' . e($limit));
ui_guest_start($open ? 'Cosa prendi a *' . ($meal === 'pranzo' ? 'pranzo' : 'cena') . '*?' : $Word . ' di *' . day_it($day, 'rel') . '*', 'menu.php', ['back' => 'pasti.php', 'back_label' => 'I miei pasti', 'lead' => $lead]);

if ($error !== '') {
    echo '<div class="notice notice-error" role="alert">' . e($error) . '</div>';
}
if ((string) $menu['note'] !== '') {
    echo '<div class="info-line">' . icon('info') . '<span>' . e((string) $menu['note']) . '</span></div>';
}

/** Riquadro con lo stato della prenotazione e ciò che è stato scelto. */
$summary = static function () use ($b, $active, $saved, $stateLabel, $stateTone, $pricesOn): string {
    if (!$b || (!$active && $b['status'] !== 'scaduto')) {
        return '';
    }
    $choice = choices_text($saved);
    $html = '<section class="card card-white stack-s"><div class="row">' . ui_tag($stateLabel, $stateTone) . '</div>';
    if ($b['status'] === 'confermato') {
        if (!empty($saved['formula']['name'])) {
            $html .= '<p class="small muted">Formula ' . e((string) $saved['formula']['name']) . '</p>';
        }
        if ($choice !== '') {
            $html .= '<p><strong>' . e($choice) . '</strong></p>';
        }
        if ((string) $b['variant'] !== '') {
            $html .= '<p class="small">Variante richiesta: ' . e((string) $b['variant']) . '</p>';
        }
        if ($pricesOn && $b['amount'] !== null && (float) $b['amount'] > 0) {
            $html .= '<p class="small muted">Totale: ' . e(g_price((float) $b['amount'])) . '</p>';
        }
    }
    return $html . '</section>';
};

/* ---------- Sola lettura: orario passato o menù chiuso ---------- */
if (!$open) {
    echo '<div class="info-line">' . icon('clock') . '<span>L\'orario per rispondere è passato. Per cambiare chiama la reception'
        . ((string) $venue['phone'] !== '' ? ': <a href="tel:' . e((string) $venue['phone']) . '">' . e(phone_show((string) $venue['phone'])) . '</a>' : '') . '.</span></div>';
    echo $summary();
    echo '<section class="card stack-s"><h2>Il menù</h2>';
    if ($menu['mode'] === 'foto' && (string) $menu['photo'] !== '') {
        echo '<img class="photo-preview" src="../uploads/' . e((string) $menu['photo']) . '" alt="Foto del menù: ' . e(mb_strtolower(g_meal_title($day, $meal))) . '">';
    } elseif ($courses) {
        echo '<div class="list-line">';
        foreach ($courses as $course) {
            echo '<div><p class="req-kind">' . e(PF_COURSES[$course]) . '</p><p>' . e(implode(' · ', array_map(static fn ($x) => (string) $x['name'], $data['courses'][$course]))) . '</p></div>';
        }
        echo '</div>';
    } else {
        echo '<p class="small muted">Menù del giorno, senza piatti da scegliere.</p>';
    }
    echo '</section>';
    if ($day >= today() && !$locked) {
        echo '<form method="post" action="' . e($here) . '" data-once>' . csrf_field()
            . ($active
                ? '<button type="submit" name="azione" value="disdici" class="btn btn-line btn-danger btn-block">Chiedo di disdire ' . e($word) . '</button>'
                : '<button type="submit" name="azione" value="aggiungi" class="btn btn-line btn-block">Chiedo di aggiungere ' . e($word) . '</button>')
            . '<p class="hint g-center">Fuori orario la richiesta va approvata dalla reception.</p></form>';
    }
    ui_guest_end();
    exit;
}

/* ---------- Scelta ---------- */
if ($b && $b['status'] === 'confermato') {
    echo $summary();
    echo '<p class="small muted">Puoi cambiare la scelta fino alle ' . e($limit) . '.</p>';
}

$price = static fn (float $v): string => $v > 0 ? g_price($v) : '';
$attr = static fn (float $v): string => $pricesOn ? ' data-price="' . e((string) $v) . '"' : '';
$anyPrice = false;

echo '<form method="post" action="' . e($here) . '" class="stack-s" data-choice="' . e($effMode) . '" data-once novalidate>' . csrf_field();

if ($menu['mode'] === 'foto') {
    echo '<section class="card stack-s"><h2>Il menù del giorno</h2>';
    echo (string) $menu['photo'] !== ''
        ? '<img class="photo-preview" src="../uploads/' . e((string) $menu['photo']) . '" alt="Foto del menù: ' . e(mb_strtolower(g_meal_title($day, $meal))) . '">'
        : '<p class="small muted">La foto del menù non è disponibile: chiedi in sala.</p>';
    echo '<p class="small muted">Conferma la tua presenza: i piatti li scegli in sala.</p></section>';
} else {
    if ($uses) {
        $formulas = $data['formulas'];
        if ($sel['formula'] === '' || !find_by_id($formulas, $sel['formula'])) {
            $sel['formula'] = (string) ($formulas[0]['id'] ?? '');
        }
        $types = pf_formula_types();
        $twoPaths = null;
        $selPaths = 1;
        echo '<section class="card stack-s"><h2>1. Scegli la formula</h2><div class="opts">';
        foreach ($formulas as $f) {
            $paths = $types[(string) ($f['type'] ?? 'completo')][1] ?? $types['completo'][1];
            if (count($paths) > 1) {
                $twoPaths ??= $paths;
            }
            $on = (string) $f['id'] === $sel['formula'];
            if ($on) {
                $selPaths = count($paths);
            }
            $p = (float) ($f['price'] ?? 0);
            $anyPrice = $anyPrice || $p > 0;
            echo '<label class="opt"><input type="radio" name="formula" value="' . e((string) $f['id']) . '" data-courses="' . e((string) json_encode($paths)) . '"' . $attr($p) . ($on ? ' checked' : '') . '>'
                . '<span><span class="o-main">' . e((string) $f['name']) . ((string) ($f['desc'] ?? '') !== '' ? '<small>' . e((string) $f['desc']) . '</small>' : '') . '</span>'
                . ($price($p) !== '' ? '<span class="o-price">' . e($price($p)) . '</span>' : '') . '</span></label>';
        }
        echo '</div>';
        if ($twoPaths) {
            $labels = [];
            foreach ($twoPaths as $i => $p) {
                $labels[(string) $i] = $p === ['primo'] ? 'Prendo il primo' : ucfirst(implode(' e ', array_map(static fn ($c) => mb_strtolower(PF_COURSE_ONE[$c]), $p)));
            }
            echo '<div data-paths role="group" aria-label="Primo oppure secondo"' . ($selPaths < 2 ? ' hidden' : '') . '>'
                . ui_chips('path', $labels, [(string) min($sel['path'], count($twoPaths) - 1)], 'radio') . '</div>';
        }
        echo '</section>';
    }
    foreach ($courses as $course) {
        $picked = (string) ($sel['dish'][$course] ?? '');
        echo '<section class="card stack-s" data-course="' . $course . '"><div class="card-head"><h2>' . e(PF_COURSE_ONE[$course]) . '</h2><span>Scegline uno</span></div><div class="opts">';
        foreach ($data['courses'][$course] as $dish) {
            $p = (float) ($dish['price'] ?? 0);
            $anyPrice = $anyPrice || ($dishPrices && $p > 0);
            echo '<label class="opt"><input type="radio" name="dish[' . $course . ']" value="' . e((string) $dish['id']) . '"' . ($dishPrices ? $attr($p) : '') . ((string) $dish['id'] === $picked ? ' checked' : '') . '>'
                . '<span><span class="o-main">' . e((string) $dish['name']) . '</span>' . ($dishPrices && $price($p) !== '' ? '<span class="o-price">' . e($price($p)) . '</span>' : '') . '</span></label>';
        }
        if (!$uses) {
            echo '<label class="opt"><input type="radio" name="dish[' . $course . ']" value=""><span><span class="o-main muted">Non lo prendo</span></span></label>';
        }
        echo '</div></section>';
    }
    /** Pastiglie con prezzo: bevande (una) ed extra (più di uno). */
    $chips = static function (string $name, array $items, array $selected, string $type) use ($pricesOn, $attr, &$anyPrice): string {
        $html = '<div class="chips">';
        foreach ($items as $i => $it) {
            $p = (float) ($it['price'] ?? 0);
            $anyPrice = $anyPrice || $p > 0;
            $id = 'mc-' . preg_replace('/[^a-z]/', '', $name) . '-' . $i;
            $html .= '<label class="chip" for="' . $id . '"><input type="' . $type . '" id="' . $id . '" name="' . e($name) . '" value="' . e((string) $it['id']) . '"' . $attr($p)
                . (in_array((string) $it['id'], $selected, true) ? ' checked' : '') . '><span>' . e((string) $it['label']) . ($pricesOn && $p > 0 ? ' · + ' . e(g_price($p)) : '') . '</span></label>';
        }
        return $html . '</div>';
    };
    if ($data['drinks']) {
        if ($sel['drink'] === '' && !$b && (float) ($data['drinks'][0]['price'] ?? 0) <= 0) {
            $sel['drink'] = (string) $data['drinks'][0]['id'];      // la prima bevanda senza sovrapprezzo è già scelta
        }
        echo '<section class="card stack-s"><div class="card-head"><h2>Da bere</h2><span>Facoltativo</span></div>' . $chips('drink', $data['drinks'], [$sel['drink']], 'radio') . '</section>';
    }
    if ($data['extras']) {
        echo '<section class="card stack-s"><div class="card-head"><h2>Per finire</h2><span>Facoltativo</span></div>' . $chips('extras[]', $data['extras'], $sel['extras'], 'checkbox') . '</section>';
    }
    if ($pricesOn && $anyPrice) {
        echo '<div class="total-bar"><span>Totale</span><strong data-total>' . ($b && $b['amount'] !== null ? e(g_price((float) $b['amount'])) : '—') . '</strong></div>';
    }
}

echo '<button type="submit" name="azione" value="conferma" class="btn btn-dark btn-big btn-block">' . icon('check', 20) . 'Confermo ' . e($word) . '</button>';
echo '<div class="row g-actions">';
if ($canVariant) {
    echo '<button type="button" class="btn btn-line grow" data-show="var-box" aria-controls="var-box">Chiedo una variante</button>';
}
if ($active && !$locked) {
    echo '<button type="submit" name="azione" value="disdici" class="btn btn-line btn-danger grow" formnovalidate>Disdico ' . e($word) . '</button>';
}
echo '</div>';
if ($canVariant) {
    echo '<div class="card card-cream stack-s" id="var-box"' . ($sel['variant'] === '' ? ' hidden' : '') . '>'
        . '<label class="label" for="variante">Che variante vorresti?</label>'
        . '<textarea id="variante" name="variante" rows="2" maxlength="500" placeholder="Es. penne in bianco con burro e parmigiano">' . e($sel['variant']) . '</textarea>'
        . '<p class="hint">La richiesta va approvata. Riceverai un avviso con la risposta. Se poi confermi senza variante, la richiesta viene annullata.</p>'
        . '<button type="submit" name="azione" value="variante" class="btn btn-dark btn-block">Invia la variante</button></div>';
}
echo '</form>';
ui_guest_end();
