<?php
/** Pannello del locale — Impostazioni: dati, pasti e orari, prezzi, diete, messaggi. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
require __DIR__ . '/_parts.php';
pf_boot('staff', '../');
$me = staff_require('impostazioni');
$v = $me['venue'];
$vid = (int) $v['id'];
$s = $v['settings'];
$modes = ['formule' => 'Formule a prezzo fisso', 'piatto' => 'Prezzo per piatto', 'nessuno' => 'Nessun prezzo'];
if (!feature('formule', $v)) {
    unset($modes['formule']);
}

/** Righe "etichetta + prezzo" (bevande, voci finali): tiene l'id se c'è, altrimenti lo crea. */
function priced_rows(mixed $src, string $labelKey = 'label'): array
{
    $out = [];
    if (!is_array($src) || !is_array($src['label'] ?? null)) {
        return $out;
    }
    foreach ($src['label'] as $i => $label) {
        $label = clean_text(is_string($label) ? $label : '', 60);
        if ($label === '') {
            continue;
        }
        $id = is_string($src['id'][$i] ?? null) && preg_match('/^[a-z0-9]{3,12}$/', $src['id'][$i]) ? $src['id'][$i] : substr(bin2hex(random_bytes(5)), 0, 8);
        $out[] = ['id' => $id, $labelKey => $label, 'price' => max(0, to_number(is_string($src['price'][$i] ?? null) ? $src['price'][$i] : ''))];
    }
    return array_slice($out, 0, 20);
}

if (is_post()) {
    pf_csrf_check();
    $name = clean_text(post('name'), 160);
    $email = mb_strtolower(post('email'));
    if (mb_strlen($name) < 2) {
        pf_flash('error', 'Scrivi il nome del locale.');
        redirect('impostazioni.php');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        pf_flash('error', 'L\'email di contatto non è valida.');
        redirect('impostazioni.php');
    }
    q('UPDATE ' . t('venues') . ' SET name = ?, phone = ?, address = ?, email = ?, vat = ?, updated_at = ? WHERE id = ?',
        [$name, clean_text(post('phone'), 40), clean_text(post('address'), 255), $email !== '' ? $email : $v['email'], clean_text(post('vat'), 40), now(), $vid]);

    $meals = array_values(array_intersect(array_keys(PF_MEALS), (array) ($_POST['meals'] ?? [])));
    $formulas = [];
    $fsrc = $_POST['formula'] ?? null;
    if (is_array($fsrc) && is_array($fsrc['name'] ?? null)) {
        foreach ($fsrc['name'] as $i => $fname) {
            $fname = clean_text(is_string($fname) ? $fname : '', 60);
            $type = is_string($fsrc['type'][$i] ?? null) ? $fsrc['type'][$i] : '';
            if ($fname === '' || !isset(pf_formula_types()[$type])) {
                continue;
            }
            $fid = is_string($fsrc['id'][$i] ?? null) && preg_match('/^[a-z0-9]{3,12}$/', $fsrc['id'][$i]) ? $fsrc['id'][$i] : substr(bin2hex(random_bytes(5)), 0, 8);
            $formulas[] = ['id' => $fid, 'name' => $fname, 'desc' => pf_formula_types()[$type][0], 'price' => max(0, to_number(is_string($fsrc['price'][$i] ?? null) ? $fsrc['price'][$i] : '')), 'type' => $type];
        }
    }
    $diets = array_slice(array_values(array_unique(array_filter(array_map(static fn ($l) => str_replace('|', ' ', clean_text($l, 40)), explode("\n", clean_lines(post('diets'), 1000)))))), 0, 20);
    venue_patch($vid, 'settings', [
        'meals' => $meals ?: ['pranzo', 'cena'],
        'cutoff_pranzo' => valid_time(post('cutoff_pranzo')) ? post('cutoff_pranzo') : $s['cutoff_pranzo'],
        'cutoff_cena' => valid_time(post('cutoff_cena')) ? post('cutoff_cena') : $s['cutoff_cena'],
        'closed_days' => array_values(array_intersect(range(1, 7), array_map('intval', (array) ($_POST['closed_days'] ?? [])))),
        'horizon' => max(1, min(60, (int) post('horizon'))),
        'auto_cancel' => post('auto_cancel') === '1',
        'auto_confirm' => post('auto_confirm') === '1',
        'price_mode' => isset($modes[post('price_mode')]) ? post('price_mode') : $s['price_mode'],
        'formulas' => $formulas,
        'drinks' => priced_rows($_POST['drink'] ?? null),
        'extras' => priced_rows($_POST['extra'] ?? null),
        'diets' => $diets,
        'welcome' => clean_lines(post('welcome'), 600),
        'wa_text' => clean_lines(post('wa_text'), 600) ?: pf_default_settings()['wa_text'],
    ]);
    pf_event($vid, 'impostazioni_modificate', '');
    pf_flash('ok', 'Impostazioni salvate.');
    redirect('impostazioni.php');
}

$pricedRow = static function (string $key, array $it = []): string {
    return '<div class="opt-row" data-row><input type="hidden" name="' . $key . '[id][]" value="' . e((string) ($it['id'] ?? '')) . '">'
        . '<input type="text" name="' . $key . '[label][]" value="' . e((string) ($it['label'] ?? '')) . '" maxlength="60" aria-label="Nome" placeholder="Nome">'
        . '<input type="text" inputmode="decimal" name="' . $key . '[price][]" value="' . ((float) ($it['price'] ?? 0) > 0 ? e(number_format((float) $it['price'], 2, ',', '')) : '') . '" aria-label="Prezzo in euro" placeholder="Prezzo €">'
        . '<button type="button" class="round" data-remove-row aria-label="Togli">' . icon('trash', 18) . '</button></div>';
};
$formulaRow = static function (array $f = []): string {
    $h = '<div class="formula-row" data-row><input type="hidden" name="formula[id][]" value="' . e((string) ($f['id'] ?? '')) . '">'
        . '<input type="text" name="formula[name][]" value="' . e((string) ($f['name'] ?? '')) . '" maxlength="60" aria-label="Nome della formula" placeholder="Nome, es. Base">'
        . '<select name="formula[type][]" class="f-desc" aria-label="Cosa comprende">';
    foreach (pf_formula_types() as $k => [$lab]) {
        $h .= '<option value="' . $k . '"' . (($f['type'] ?? 'completo') === $k ? ' selected' : '') . '>' . e($lab) . '</option>';
    }
    return $h . '</select><input type="text" inputmode="decimal" name="formula[price][]" value="' . ((float) ($f['price'] ?? 0) > 0 ? e(number_format((float) $f['price'], 2, ',', '')) : '') . '" aria-label="Prezzo in euro" placeholder="Prezzo €">'
        . '<button type="button" class="round" data-remove-row aria-label="Togli">' . icon('trash', 18) . '</button></div>';
};

ui_staff_start('Le *impostazioni*', 'impostazioni.php', ['lead' => 'Valgono per tutti i menù nuovi. Orario limite e prezzi si possono cambiare anche pasto per pasto.']);
echo '<form method="post" action="impostazioni.php" class="stack" novalidate>' . csrf_field();

echo '<div class="grid-even"><section class="card"><div class="card-head"><h2>Il locale</h2></div><div class="form">'
    . ui_field('name', 'Nome', (string) $v['name'], ['required' => true, 'attrs' => 'maxlength="160"'])
    . ui_field('address', 'Indirizzo', (string) $v['address'], ['attrs' => 'maxlength="255"'])
    . '<div class="form-grid">' . ui_field('phone', 'Telefono per gli ospiti', (string) $v['phone'], ['type' => 'tel', 'hint' => 'È il numero da chiamare dopo l\'orario limite.'])
    . ui_field('email', 'Email di contatto', (string) $v['email'], ['type' => 'email']) . '</div>'
    . ui_field('vat', 'Partita IVA', (string) $v['vat'], ['attrs' => 'maxlength="40"'])
    . '</div></section>';

echo '<section class="card"><div class="card-head"><h2>Pasti e orari</h2></div><div class="form">'
    . '<div class="field"><span class="label">Pasti che servi</span>' . ui_chips('meals[]', PF_MEALS, (array) $s['meals']) . '</div>'
    . '<div class="form-grid">' . ui_field('cutoff_pranzo', 'Pranzo: risposte entro le', (string) $s['cutoff_pranzo'], ['type' => 'time']) . ui_field('cutoff_cena', 'Cena: risposte entro le', (string) $s['cutoff_cena'], ['type' => 'time']) . '</div>'
    . '<div class="field"><span class="label">Giorni di chiusura</span>' . ui_chips('closed_days[]', array_map('ucfirst', PF_DAYS), array_map('strval', (array) $s['closed_days'])) . '</div>'
    . ui_field('horizon', 'Giorni prenotabili in anticipo', (string) $s['horizon'], ['type' => 'number', 'attrs' => 'min="1" max="60"'])
    . ui_switch('auto_cancel', 'Disdetta automatica', !empty($s['auto_cancel']), 'Chi non risponde al menù entro l\'orario viene disdetto.')
    . ui_switch('auto_confirm', 'Conferma automatica dei nuovi ospiti', !empty($s['auto_confirm']), 'Spenta: ogni registrazione passa dalla reception prima di contare in cucina.')
    . '</div></section></div>';

echo '<section class="card"><div class="card-head"><h2>Prezzi</h2><p>Come calcoli il conto dei pasti</p></div><div class="form">'
    . ui_chips('price_mode', $modes, [(string) $s['price_mode']], 'radio');
if (isset($modes['formule'])) {
    echo '<div class="stack-s" data-toggle-for="price_mode" data-toggle-value="formule"><span class="label">Formule proposte nei menù nuovi</span><div class="dish-rows" id="righe-formule">';
    foreach ((array) $s['formulas'] ?: [[]] as $f) {
        echo $formulaRow($f);
    }
    echo '</div><template id="tpl-formula">' . $formulaRow() . '</template><div><button type="button" class="btn btn-small btn-line" data-add-row="tpl-formula" data-into="righe-formule">' . icon('plus', 16) . 'Aggiungi una formula</button></div></div>';
}
echo '<p class="hint" data-toggle-for="price_mode" data-toggle-value="piatto">Il prezzo di ogni piatto si scrive componendo il menù.' . (feature('prezzi', $v) ? ' Puoi decidere pasto per pasto se mostrarlo agli ospiti.' : '') . '</p>'
    . '<div class="grid-even"><div class="stack-s"><span class="label">Da bere</span><div class="dish-rows" id="righe-bere">';
foreach ((array) $s['drinks'] as $it) {
    echo $pricedRow('drink', $it);
}
echo '</div><template id="tpl-bere">' . $pricedRow('drink') . '</template><div><button type="button" class="btn btn-small btn-line" data-add-row="tpl-bere" data-into="righe-bere">' . icon('plus', 16) . 'Aggiungi</button></div><p class="hint">Senza prezzo = compresa.</p></div>'
    . '<div class="stack-s"><span class="label">Per finire</span><div class="dish-rows" id="righe-fine">';
foreach ((array) $s['extras'] as $it) {
    echo $pricedRow('extra', $it);
}
echo '</div><template id="tpl-fine">' . $pricedRow('extra') . '</template><div><button type="button" class="btn btn-small btn-line" data-add-row="tpl-fine" data-into="righe-fine">' . icon('plus', 16) . 'Aggiungi</button></div></div></div></div></section>';

echo '<div class="grid-even"><section class="card"><div class="card-head"><h2>Diete e intolleranze</h2><p>Quelle che l\'ospite può indicare</p></div>'
    . ui_field('diets', 'Una per riga', implode("\n", (array) $s['diets']), ['type' => 'textarea', 'rows' => 6, 'hint' => 'L\'ospite può sempre aggiungere altre esigenze a parole sue.']) . '</section>';
echo '<section class="card"><div class="card-head"><h2>Messaggi</h2></div><div class="form">'
    . ui_field('welcome', 'Benvenuto nell\'area ospiti', (string) $s['welcome'], ['type' => 'textarea', 'rows' => 3, 'placeholder' => 'Es. Benvenuto! Qui prenoti pranzo e cena.'])
    . (feature('whatsapp', $v) ? ui_field('wa_text', 'Avviso menù su WhatsApp', (string) $s['wa_text'], ['type' => 'textarea', 'rows' => 4, 'hint' => 'Si riempiono da soli: {pasto} {giorno} {orario} {link} {locale}.']) : '')
    . '</div></section></div>';

echo '<div class="row"><button type="submit" class="btn btn-dark btn-big">Salva le impostazioni</button></div></form>';
ui_shell_end();
