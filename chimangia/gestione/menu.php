<?php
/** Pannello del locale — Menù e pasti: composizione del menù di un pasto, orario limite, pubblicazione. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
require __DIR__ . '/_parts.php';
pf_boot('staff', '../');
$me = staff_require('menu');
$v = $me['venue'];
$vid = (int) $v['id'];
$s = $v['settings'];
$canScelta = feature('scelta', $v);
$canFoto = feature('foto', $v);
$useFormulas = feature('formule', $v) && $s['price_mode'] === 'formule';
$dishPrices = $s['price_mode'] === 'piatto';

$day = is_post() ? post('g') : get_param('g');
$day = valid_day($day) ? $day : today();
$meal = is_post() && isset(PF_MEALS[post('p')]) ? post('p') : ui_meal_param($v);
$self = 'menu.php?g=' . $day . '&p=' . $meal;
$menu = menu_get($vid, $day, $meal);

/** Legge le righe ripetibili di un modulo: $src = ['id' => [...], 'name' => [...], ...]. */
function rows_from_post(mixed $src, array $fields): array
{
    $out = [];
    if (!is_array($src) || !is_array($src[$fields[0]] ?? null)) {
        return $out;
    }
    foreach ($src[$fields[0]] as $i => $_) {
        $row = [];
        foreach ($fields as $f) {
            $row[$f] = is_string($src[$f][$i] ?? null) ? trim($src[$f][$i]) : '';
        }
        $out[] = $row;
    }
    return $out;
}

function row_id(string $given): string
{
    return preg_match('/^[a-z0-9]{3,12}$/', $given) ? $given : substr(bin2hex(random_bytes(5)), 0, 8);
}

if (is_post()) {
    pf_csrf_check();
    $azione = post('azione');
    $photo = (string) ($menu['photo'] ?? '');

    if ($azione === 'copia') {
        $src = q_one('SELECT * FROM ' . t('menus') . " WHERE venue_id = ? AND meal = ? AND day < ? AND mode = 'scelta' ORDER BY day DESC LIMIT 1", [$vid, $meal, $day]);
        if ($src) {
            $srcD = menu_decode($src);
            menu_save($v, $day, $meal, ['mode' => 'scelta', 'cutoff' => $menu['cutoff'] ?? null, 'show_prices' => (bool) $src['show_prices'], 'note' => '', 'data' => $srcD['data'], 'photo' => $photo]);
            pf_flash('ok', 'Copiato il menù di ' . day_it((string) $src['day'], 'rel') . '. Controlla i piatti e pubblica.');
        } else {
            pf_flash('info', 'Non c\'è ancora un menù precedente da copiare.');
        }
        redirect($self);
    }
    if ($azione === 'ritira' && $menu) {
        q('UPDATE ' . t('menus') . " SET status = 'bozza', closed_at = NULL, updated_at = ? WHERE id = ?", [now(), $menu['id']]);
        pf_event($vid, 'menu_ritirato', PF_MEALS[$meal] . ' ' . $day);
        pf_flash('info', 'Menù ritirato: gli ospiti non lo vedono più. Le risposte già date restano.');
        redirect($self);
    }

    // Foto: nuova, tolta, oppure quella di prima.
    if (!empty($_FILES['foto']['name'])) {
        [$file, $err] = pf_image_upload($_FILES['foto']);
        if ($file === null) {
            pf_flash('error', (string) $err);
            redirect($self);
        }
        pf_image_delete($photo);
        $photo = $file;
    } elseif (post('togli_foto') === '1') {
        pf_image_delete($photo);
        $photo = '';
    }

    if ($azione === 'foto_rapida') {
        // Dalla cucina: una foto e il menù è pubblicato.
        if ($photo === '' || !$canFoto) {
            pf_flash('error', 'Scegli la foto del menù.');
            redirect('cucina.php?g=' . $day . '&p=' . $meal);
        }
        menu_save($v, $day, $meal, ['mode' => 'foto', 'cutoff' => null, 'show_prices' => false, 'note' => '', 'data' => $menu['data'] ?? menu_blank($v), 'photo' => $photo]);
        if (meal_open($v, $day, $meal, menu_get($vid, $day, $meal, true))) {
            $n = menu_publish($v, $day, $meal);
            pf_event($vid, 'menu_pubblicato', PF_MEALS[$meal] . ' ' . $day . ' (foto)');
            pf_flash('ok', 'Foto pubblicata' . ($n ? ': ' . $n . ($n === 1 ? ' ospite avvisato.' : ' ospiti avvisati.') : '.'));
        } else {
            pf_flash('warn', 'Foto salvata come bozza: l\'orario limite è già passato. Apri "Menù e pasti" per spostarlo.');
        }
        redirect('cucina.php?g=' . $day . '&p=' . $meal);
    }

    $mode = post('mode') === 'foto' ? 'foto' : 'scelta';
    if (!$canFoto) {
        $mode = 'scelta';
    } elseif (!$canScelta) {
        $mode = 'foto';
    }
    $data = ['formulas' => [], 'courses' => ['primo' => [], 'secondo' => [], 'contorno' => []], 'drinks' => [], 'extras' => []];
    if ($useFormulas) {
        foreach (rows_from_post($_POST['formula'] ?? null, ['name', 'id', 'type', 'price']) as $r) {
            if ($r['name'] !== '' && isset(pf_formula_types()[$r['type']])) {
                $data['formulas'][] = ['id' => row_id($r['id']), 'name' => clean_text($r['name'], 60), 'desc' => pf_formula_types()[$r['type']][0], 'price' => max(0, to_number($r['price'])), 'type' => $r['type']];
            }
        }
    }
    foreach (array_keys(PF_COURSES) as $course) {
        foreach (rows_from_post($_POST['dish'][$course] ?? null, ['name', 'id', 'price']) as $r) {
            if ($r['name'] !== '') {
                $data['courses'][$course][] = ['id' => row_id($r['id']), 'name' => clean_text($r['name'], 120), 'price' => $dishPrices ? max(0, to_number($r['price'])) : 0];
            }
        }
    }
    foreach (['drinks', 'extras'] as $k) {
        $picked = array_map('strval', (array) ($_POST[$k] ?? []));
        foreach ((array) $s[$k] as $item) {
            if (in_array((string) $item['id'], $picked, true)) {
                $data[$k][] = $item;
            }
        }
    }
    $cutoff = valid_time(post('cutoff')) ? post('cutoff') . ':00' : null;
    menu_save($v, $day, $meal, [
        'mode' => $mode, 'cutoff' => $cutoff, 'show_prices' => $dishPrices && feature('prezzi', $v) && post('show_prices') === '1',
        'note' => clean_text(post('note'), 255), 'data' => $data, 'photo' => $photo,
    ]);
    $menu = menu_get($vid, $day, $meal, true);

    if ($azione === 'pubblica') {
        $dishes = array_sum(array_map('count', $data['courses']));
        $limit = strtotime($day . ' ' . ($cutoff ?? (string) $s['cutoff_' . $meal] . ':00'));
        if ($day < today()) {
            pf_flash('error', 'Non si può pubblicare il menù di un giorno passato.');
        } elseif ($mode === 'scelta' && $dishes === 0) {
            pf_flash('error', 'Aggiungi almeno un piatto prima di pubblicare.');
        } elseif ($mode === 'foto' && $photo === '') {
            pf_flash('error', 'Carica la foto del menù prima di pubblicare.');
        } elseif ($mode === 'scelta' && $useFormulas && !$data['formulas']) {
            pf_flash('error', 'Aggiungi almeno una formula, oppure cambia il modo dei prezzi nelle Impostazioni.');
        } elseif ($limit === false || $limit <= time()) {
            pf_flash('error', 'L\'orario limite per rispondere è già passato: spostalo più avanti e pubblica di nuovo.');
        } else {
            $n = menu_publish($v, $day, $meal);
            pf_event($vid, 'menu_pubblicato', PF_MEALS[$meal] . ' ' . $day);
            pf_flash('ok', 'Menù pubblicato' . ($n ? ': ' . $n . ($n === 1 ? ' ospite avvisato.' : ' ospiti avvisati.') : '. Chi prenota questo pasto lo trova nella sua area.'));
            redirect($self);
        }
        pf_flash('info', 'Le modifiche sono salvate, ma il menù non è stato pubblicato.');
    } else {
        pf_flash('ok', $menu['status'] === 'bozza' ? 'Bozza salvata.' : 'Modifiche salvate.');
    }
    redirect($self);
}

$status = (string) ($menu['status'] ?? 'nuovo');
$data = $menu['data'] ?? menu_blank($v);
$mode = (string) ($menu['mode'] ?? ($canScelta ? 'scelta' : 'foto'));
$cutoffTs = meal_cutoff($v, $day, $meal, $menu);
$waiting = (int) q_value(
    'SELECT COUNT(*) FROM ' . t('bookings') . ' b JOIN ' . t('guests') . " g ON g.id = b.guest_id WHERE b.venue_id = ? AND b.day = ? AND b.meal = ? AND b.status = 'prenotato' AND g.status = 'confermato'",
    [$vid, $day, $meal]
);

$actions = can('presenti', $me) ? '<a class="btn btn-line" href="cucina.php?g=' . $day . '&amp;p=' . $meal . '">' . icon('pot', 18) . 'Vedi in cucina</a>' : '';
ui_staff_start('Componi *il menù*', 'menu.php', ['eyebrow' => 'Menù e pasti', 'actions' => $actions]);
echo '<div class="row-between">' . ui_day_nav($day, 'menu.php?p=' . $meal . '&g=');
$tabs = [];
foreach ((array) $s['meals'] as $m) {
    $tabs[] = ['menu.php?g=' . $day . '&p=' . $m, PF_MEALS[$m], $m === $meal];
}
echo ui_seg($tabs, 'Pasto') . '</div>';

echo '<p class="status-line' . ($status === 'pubblicato' ? ' live' : '') . '">' . match ($status) {
    'pubblicato' => icon('check', 18) . '<span>Pubblicato: gli ospiti rispondono entro le <strong>' . date('H:i', $cutoffTs) . '</strong>.</span>',
    'chiuso' => icon('clock', 18) . '<span>Risposte chiuse alle ' . date('H:i', $cutoffTs) . '. Per riaprirle sposta l\'orario più avanti e pubblica di nuovo.</span>',
    'bozza' => icon('edit', 18) . '<span>Bozza: gli ospiti non la vedono finché non pubblichi.</span>',
    default => icon('info', 18) . '<span>Per questo pasto non c\'è ancora un menù.</span>',
} . '</p>';

$dishRow = static function (string $course, array $d = []) use ($dishPrices): string {
    $label = PF_COURSE_ONE[$course];
    return '<div class="dish-row' . ($dishPrices ? '' : ' no-price') . '" data-row><input type="hidden" name="dish[' . $course . '][id][]" value="' . e((string) ($d['id'] ?? '')) . '">'
        . '<input type="text" name="dish[' . $course . '][name][]" value="' . e((string) ($d['name'] ?? '')) . '" maxlength="120" aria-label="' . $label . ': nome del piatto" placeholder="Nome del piatto">'
        . ($dishPrices ? '<input type="text" inputmode="decimal" name="dish[' . $course . '][price][]" value="' . ((float) ($d['price'] ?? 0) > 0 ? e(number_format((float) $d['price'], 2, ',', '')) : '') . '" aria-label="' . $label . ': prezzo in euro" placeholder="Prezzo €">' : '')
        . '<button type="button" class="round" data-remove-row aria-label="Togli questo piatto">' . icon('trash', 18) . '</button></div>';
};
$formulaRow = static function (array $f = []): string {
    $h = '<div class="formula-row" data-row><input type="hidden" name="formula[id][]" value="' . e((string) ($f['id'] ?? '')) . '">'
        . '<input type="text" name="formula[name][]" value="' . e((string) ($f['name'] ?? '')) . '" maxlength="60" aria-label="Nome della formula" placeholder="Nome, es. Base">'
        . '<select name="formula[type][]" class="f-desc" aria-label="Cosa comprende la formula">';
    foreach (pf_formula_types() as $k => [$lab]) {
        $h .= '<option value="' . $k . '"' . (($f['type'] ?? 'completo') === $k ? ' selected' : '') . '>' . e($lab) . '</option>';
    }
    return $h . '</select><input type="text" inputmode="decimal" name="formula[price][]" value="' . ((float) ($f['price'] ?? 0) > 0 ? e(number_format((float) $f['price'], 2, ',', '')) : '') . '" aria-label="Prezzo della formula in euro" placeholder="Prezzo €">'
        . '<button type="button" class="round" data-remove-row aria-label="Togli questa formula">' . icon('trash', 18) . '</button></div>';
};

echo '<form method="post" action="menu.php" enctype="multipart/form-data" class="grid-2">' . csrf_field()
    . '<input type="hidden" name="g" value="' . $day . '"><input type="hidden" name="p" value="' . $meal . '"><div class="stack">';

if ($canScelta && $canFoto) {
    echo '<section class="card"><div class="card-head"><h2>Come lo pubblichi</h2></div>'
        . ui_chips('mode', ['scelta' => 'Menù a scelta: gli ospiti scelgono i piatti', 'foto' => 'Foto del menù: gli ospiti confermano la presenza'], [$mode], 'radio') . '</section>';
} else {
    echo '<input type="hidden" name="mode" value="' . $mode . '">';
}

if ($canFoto) {
    echo '<section class="card" data-toggle-for="mode" data-toggle-value="foto"><div class="card-head"><h2>Foto del menù</h2><p>Fotografa il foglio o la lavagna</p></div><div class="stack-s">'
        . '<img id="foto-anteprima" class="photo-preview" alt="Foto del menù" ' . ($menu && $menu['photo'] !== '' ? 'src="../uploads/' . e((string) $menu['photo']) . '"' : 'hidden') . '>'
        . '<div class="field"><label for="foto">' . ($menu && $menu['photo'] !== '' ? 'Sostituisci la foto' : 'Scegli o scatta la foto') . '</label>'
        . '<input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp" capture="environment" data-preview="foto-anteprima"><p class="hint">JPG, PNG o WebP fino a 8 MB. Viene ridotta e ripulita dai dati della fotocamera.</p></div>'
        . ($menu && $menu['photo'] !== '' ? ui_check('togli_foto', 'Togli la foto attuale') : '') . '</div></section>';
}

if ($canScelta) {
    echo '<div class="stack" data-toggle-for="mode" data-toggle-value="scelta">';
    if ($useFormulas) {
        echo '<section class="card"><div class="card-head"><h2>Formule a prezzo fisso</h2><p>L\'ospite sceglie prima la formula, poi i piatti</p></div><div class="dish-rows" id="righe-formule">';
        foreach ($data['formulas'] ?: [[]] as $f) {
            echo $formulaRow($f);
        }
        echo '</div><template id="tpl-formula">' . $formulaRow() . '</template><p><button type="button" class="btn btn-small btn-line" data-add-row="tpl-formula" data-into="righe-formule">' . icon('plus', 16) . 'Aggiungi una formula</button></p></section>';
    }
    foreach (PF_COURSES as $course => $label) {
        $list = $data['courses'][$course] ?? [];
        echo '<section class="card"><div class="card-head"><h2>' . $label . '</h2><p>L\'ospite ne sceglie uno</p></div><div class="dish-rows" id="righe-' . $course . '">';
        foreach ($list ?: [[], []] as $d) {
            echo $dishRow($course, $d);
        }
        echo '</div><template id="tpl-' . $course . '">' . $dishRow($course) . '</template><p><button type="button" class="btn btn-small btn-line" data-add-row="tpl-' . $course . '" data-into="righe-' . $course . '">' . icon('plus', 16) . 'Aggiungi un piatto</button></p></section>';
    }
    $pick = static function (string $k, string $title, string $hint) use ($s, $data, $menu): string {
        $all = (array) $s[$k];
        if (!$all) {
            return '';
        }
        $opts = [];
        foreach ($all as $it) {
            $opts[(string) $it['id']] = (string) $it['label'] . ((float) $it['price'] > 0 ? ' · + ' . euro((float) $it['price']) : '');
        }
        $sel = $menu ? array_column($data[$k], 'id') : array_keys($opts);
        return '<section class="card"><div class="card-head"><h2>' . $title . '</h2><p>' . $hint . '</p></div>' . ui_chips($k . '[]', $opts, array_map('strval', $sel)) . '</section>';
    };
    echo $pick('extras', 'Per finire', 'Voci facoltative per l\'ospite') . $pick('drinks', 'Da bere', 'Una a scelta') . '</div>';
}

echo '</div><aside class="stack"><section class="card card-cream"><div class="card-head"><h2>Impostazioni di questo pasto</h2></div><div class="form">'
    . ui_field('cutoff', 'Gli ospiti rispondono entro le', $menu && $menu['cutoff'] ? hm((string) $menu['cutoff']) : (string) $s['cutoff_' . $meal], ['type' => 'time', 'required' => true,
        'hint' => !empty($s['auto_cancel']) ? 'Chi non risponde viene disdetto in automatico.' : 'Dopo quest\'ora le modifiche vanno approvate da te.'])
    . ($dishPrices && feature('prezzi', $v) ? ui_switch('show_prices', 'Mostra i prezzi agli ospiti', (bool) ($menu['show_prices'] ?? false)) : '')
    . ui_field('note', 'Nota per gli ospiti', (string) ($menu['note'] ?? ''), ['attrs' => 'maxlength="255"', 'placeholder' => 'Es. oggi pesce fresco'])
    . '<button type="submit" name="azione" value="pubblica" class="btn btn-dark btn-block">' . ($status === 'pubblicato' ? 'Salva le modifiche' : 'Pubblica' . ($waiting ? ' e avvisa ' . $waiting . ($waiting === 1 ? ' ospite' : ' ospiti') : '')) . '</button>'
    . ($status !== 'pubblicato' ? '<button type="submit" name="azione" value="salva" class="btn btn-line btn-block">Salva come bozza</button>' : '')
    . ($canScelta ? '<button type="submit" name="azione" value="copia" class="btn btn-line btn-block" formnovalidate>' . icon('copy', 18) . 'Copia l\'ultimo menù</button>' : '')
    . ($status === 'pubblicato' ? '<button type="submit" name="azione" value="ritira" class="btn btn-danger btn-block" formnovalidate>Ritira il menù</button>' : '')
    . '</div></section>';

if ($menu && $status !== 'bozza') {
    $k = kitchen_data($v, $day, $meal)['count'];
    $tot = max(1, $k['total']);
    $step = static fn (int $n): int => (int) (round($n / $tot * 10) * 10);
    echo '<section class="card"><div class="card-head"><h2>Risposte ricevute</h2></div><div class="stack-s">'
        . '<div class="bar" role="img" aria-label="' . $k['confirmed'] . ' confermati, ' . $k['variants'] . ' varianti, ' . $k['waiting'] . ' senza risposta"><i class="b-ok bar-w' . $step($k['confirmed'] - $k['variants']) . '"></i><i class="b-var bar-w' . $step($k['variants']) . '"></i></div>'
        . '<div class="list-line small"><div class="row-between"><span>Confermati</span><strong>' . $k['confirmed'] . '</strong></div><div class="row-between"><span>Con variante</span><strong>' . $k['variants'] . '</strong></div>'
        . '<div class="row-between"><span>Senza risposta</span><strong>' . $k['waiting'] . '</strong></div></div></div></section>';
}
echo '</aside></form>';
ui_shell_end();
