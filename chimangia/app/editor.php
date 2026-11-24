<?php
/**
 * Motore delle pagine di modifica del pannello: descrizione dei campi,
 * raccolta e pulizia dei dati inviati, schede riordinabili, Salva bozza / Anteprima / Pubblica.
 */
declare(strict_types=1);

defined('CM_ROOT') || exit;

const CM_MAX_ITEMS = 40;

/* =====================================================================
 * Schemi delle sezioni (pagina generica sezione.php)
 * ===================================================================== */

function f(string $key, string $label, string $type = 'text', array $o = []): array
{
    return ['key' => $key, 'label' => $label, 'type' => $type] + $o;
}

function section_schema(string $area): ?array
{
    $vis = f('visible', 'Mostra questa sezione nel sito', 'switch');
    $schemas = [
        'header' => ['title' => 'Intestazione', 'groups' => [
            ['Voci del menu', [
                ['type' => 'repeater', 'key' => 'nav', 'label' => 'Voce', 'add' => 'Aggiungi una voce', 'max' => 8, 'fields' => [
                    f('label', 'Testo della voce', 'text', ['rec' => 20]),
                    f('href', 'Destinazione', 'href', ['hint' => 'Un\'ancora della pagina (es. #prezzi) oppure un indirizzo https://.']),
                ]],
            ]],
            ['Pulsante', [
                f('cta_text', 'Testo del pulsante', 'text', ['rec' => 24]),
                f('cta_href', 'Destinazione del pulsante', 'href'),
            ]],
        ]],
        'hero' => ['title' => 'Apertura', 'groups' => [
            ['Sezione', [$vis]],
            ['Testi', [
                f('badge', 'Etichetta dell\'offerta', 'text', ['rec' => 60, 'hint' => 'Visibile solo quando l\'offerta di lancio è attiva. {sconto} diventa la percentuale.']),
                f('title', 'Titolo principale', 'text', ['rec' => 60]),
                f('text', 'Testo', 'textarea', ['rec' => 220]),
                f('note', 'Nota sotto i pulsanti', 'text', ['rec' => 100, 'hint' => '{prezzo_da} diventa il canone mensile più basso.']),
            ]],
            ['Pulsanti', [
                f('btn1_text', 'Primo pulsante: testo', 'text', ['rec' => 24]),
                f('btn1_href', 'Primo pulsante: destinazione', 'href'),
                f('btn2_text', 'Secondo pulsante: testo', 'text', ['rec' => 24]),
                f('btn2_href', 'Secondo pulsante: destinazione', 'href'),
            ]],
            ['Scheda d\'esempio della cucina', [
                f('card_label', 'Etichetta in alto', 'text', ['rec' => 30]),
                f('card_number', 'Numero grande', 'text', ['rec' => 4]),
                f('card_number_label', 'Testo accanto al numero', 'text', ['rec' => 24]),
                ['type' => 'repeater', 'key' => 'card_groups', 'label' => 'Gruppo di piatti', 'add' => 'Aggiungi un gruppo', 'max' => 4, 'fields' => [
                    f('title', 'Nome del gruppo', 'text', ['rec' => 16]),
                    f('items', 'Piatti', 'lines', ['hint' => 'Un piatto per riga, nella forma: Nome del piatto | quantità']),
                ]],
                f('card_tags', 'Etichette gialle', 'lines', ['hint' => 'Una per riga.']),
                f('card_caption', 'Didascalia', 'text', ['rec' => 30]),
            ]],
            ['Fotografie (slider)', [
                f('photo', 'Prima fotografia', 'photo', ['hint' => 'La scheda d\'esempio si appoggia sulla fotografia. Con più fotografie l\'apertura diventa uno slider.']),
                f('photo_alt', 'Descrizione della prima fotografia (testo alternativo)', 'text', ['rec' => 120, 'hint' => 'Vuoto: si usa la descrizione predefinita della foto.']),
                f('photo2', 'Seconda fotografia dello slider', 'photo'),
                f('photo3', 'Terza fotografia dello slider', 'photo'),
                f('photo4', 'Quarta fotografia dello slider', 'photo'),
            ]],
        ]],
        'problema' => ['title' => 'Il problema', 'groups' => [
            ['Sezione', [$vis, f('title', 'Titolo', 'text', ['rec' => 70])]],
            ['Schede', [
                ['type' => 'repeater', 'key' => 'cards', 'label' => 'Scheda', 'add' => 'Aggiungi una scheda', 'max' => 9, 'fields' => [
                    f('icon', 'Icona', 'icon'),
                    f('title', 'Titolo', 'text', ['rec' => 30]),
                    f('text', 'Descrizione', 'textarea', ['rec' => 120]),
                ]],
            ]],
            ['Chiusura', [f('closing', 'Frase finale', 'text', ['rec' => 90])]],
            ['Fotografia', [
                f('photo', 'Fotografia accanto al titolo', 'photo'),
                f('photo_alt', 'Descrizione della fotografia (testo alternativo)', 'text', ['rec' => 120, 'hint' => 'Vuoto: si usa la descrizione predefinita della foto.']),
            ]],
        ]],
        'come_funziona' => ['title' => 'Come funziona', 'groups' => [
            ['Sezione', [$vis, f('title', 'Titolo', 'text', ['rec' => 40]), f('subtitle', 'Sottotitolo', 'text', ['rec' => 60])]],
            ['Passaggi (numerati in automatico)', [
                ['type' => 'repeater', 'key' => 'cards', 'label' => 'Passaggio', 'add' => 'Aggiungi un passaggio', 'max' => 6, 'fields' => [
                    f('title', 'Titolo', 'text', ['rec' => 40]),
                    f('text', 'Descrizione', 'textarea', ['rec' => 160]),
                ]],
            ]],
        ]],
        'giornata' => ['title' => 'Una giornata con ChiMangia', 'groups' => [
            ['Sezione', [$vis, f('title', 'Titolo', 'text', ['rec' => 40]), f('intro', 'Introduzione', 'textarea', ['rec' => 240])]],
            ['Blocchi testo + illustrazione (si alternano a destra e a sinistra)', [
                ['type' => 'repeater', 'key' => 'cards', 'label' => 'Blocco', 'add' => 'Aggiungi un blocco', 'max' => 8, 'fields' => [
                    f('kicker', 'Etichetta (es. "La mattina")', 'text', ['rec' => 24]),
                    f('title', 'Titolo', 'text', ['rec' => 50]),
                    f('body', 'Testo', 'rich', ['rec' => 450]),
                    f('illustration', 'Illustrazione o fotografia', 'illustration'),
                    f('image', 'Oppure un\'immagine caricata', 'media', ['hint' => 'Se scegli un\'immagine, sostituisce l\'illustrazione.']),
                    f('alt', 'Descrizione dell\'immagine (testo alternativo)', 'text', ['rec' => 100]),
                ]],
            ]],
        ]],
        'funzioni' => ['title' => 'Funzioni', 'groups' => [
            ['Sezione', [$vis, f('title', 'Titolo', 'text', ['rec' => 50]), f('subtitle', 'Sottotitolo', 'text', ['rec' => 100])]],
            ['Schede', [
                ['type' => 'repeater', 'key' => 'cards', 'label' => 'Funzione', 'add' => 'Aggiungi una funzione', 'max' => 18, 'fields' => [
                    f('icon', 'Icona', 'icon'),
                    f('title', 'Titolo', 'text', ['rec' => 32]),
                    f('text', 'Descrizione', 'textarea', ['rec' => 120]),
                ]],
            ]],
        ]],
        'per_chi' => ['title' => 'Per chi è', 'groups' => [
            ['Sezione', [$vis, f('title', 'Titolo', 'text', ['rec' => 30])]],
            ['Schede', [
                ['type' => 'repeater', 'key' => 'cards', 'label' => 'Scheda', 'add' => 'Aggiungi una scheda', 'max' => 12, 'fields' => [
                    f('title', 'Titolo', 'text', ['rec' => 32]),
                    f('text', 'Descrizione', 'textarea', ['rec' => 90]),
                ]],
            ]],
        ]],
        'contatti' => ['title' => 'Sezione contatti', 'groups' => [
            ['Sezione', [$vis, f('title', 'Titolo', 'text', ['rec' => 50]), f('text', 'Testo', 'textarea', ['rec' => 120])]],
            ['Modulo', [
                f('label_name', 'Etichetta: nome', 'text'),
                f('label_business', 'Etichetta: attività', 'text'),
                f('label_email', 'Etichetta: email', 'text'),
                f('label_phone', 'Etichetta: telefono', 'text'),
                f('label_clients', 'Etichetta: numero di clienti', 'text'),
                f('clients_options', 'Scelte per il numero di clienti', 'lines', ['hint' => 'Una per riga.']),
                f('label_message', 'Etichetta: messaggio', 'text'),
                f('consent_text', 'Testo del consenso', 'textarea', ['hint' => '{privacy} diventa il link all\'informativa.']),
                f('consent_link_text', 'Testo del link all\'informativa', 'text'),
                f('submit_text', 'Testo del pulsante di invio', 'text', ['rec' => 24]),
                f('success_text', 'Messaggio dopo l\'invio', 'textarea', ['rec' => 140]),
            ]],
            ['Sotto il modulo', [
                f('email_label', 'Testo prima dell\'email', 'text', ['hint' => 'L\'indirizzo si modifica in Dati aziendali.']),
                f('phone_label', 'Testo prima del telefono', 'text'),
                f('offer_line', 'Riga dell\'offerta', 'text', ['rec' => 90, 'hint' => 'Visibile solo con l\'offerta di lancio attiva.']),
            ]],
        ]],
    ];
    return $schemas[$area] ?? null;
}

/* =====================================================================
 * Raccolta dei dati inviati
 * ===================================================================== */

/** Pulisce un singolo valore secondo il tipo. */
function editor_clean(array $def, mixed $raw, array &$errors, string $errKey): mixed
{
    $v = is_string($raw) ? $raw : '';
    switch ($def['type']) {
        case 'switch':
            return $raw === '1' || $raw === 1 || $raw === true;
        case 'textarea':
            return clean_text($v, 2000);
        case 'lines':
            return clean_lines($v, 4000);
        case 'rich':
            return sanitize_html($v);
        case 'href':
            $v = trim($v);
            if ($v === '') {
                return '';
            }
            if (!safe_href($v)) {
                $errors[$errKey] = 'Destinazione non valida: usa #sezione, https://…, mailto: o tel:.';
                return $v;
            }
            return mb_substr($v, 0, 500);
        case 'photo':
            return isset(photo_library()[$v]) ? $v : '';
        case 'icon':
            return isset(icon_library()[$v]) ? $v : 'spunta';
        case 'illustration':
            return isset(visual_library()[$v]) ? $v : array_key_first(visual_library());
        case 'media':
            $id = (int) $v;
            return ($id > 0 && media_get($id)) ? $id : 0;
        case 'number':
            $n = to_number($v);
            if ($v !== '' && !is_numeric(str_replace([',', '.', ' ', '€'], '', $v))) {
                $errors[$errKey] = 'Scrivi un numero.';
            }
            return $n < 0 ? 0 : $n;
        case 'int':
            return max(0, (int) $v);
        case 'date':
            $v = trim($v);
            if ($v !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                $errors[$errKey] = 'Data non valida.';
                return '';
            }
            return $v;
        case 'email':
            $v = trim($v);
            return mb_substr($v, 0, 190);
        default:
            return clean_text($v, 500);
    }
}

/** Raccoglie tutti i campi di uno schema dal POST, partendo dai dati attuali. */
function editor_collect(array $schema, array $current, array $post, array &$errors): array
{
    $data = $current;
    foreach ($schema['groups'] as [, $items]) {
        foreach ($items as $def) {
            if ($def['type'] === 'repeater') {
                $data[$def['key']] = editor_collect_items($def, $post[$def['key']] ?? [], $errors);
                continue;
            }
            $data[$def['key']] = editor_clean($def, $post[$def['key']] ?? ($def['type'] === 'switch' ? '0' : ''), $errors, $def['key']);
        }
    }
    return $data;
}

/** Elementi di una lista: l'ordine è quello in cui arrivano dal modulo (cioè quello visibile). */
function editor_collect_items(array $def, mixed $raw, array &$errors): array
{
    $out = [];
    if (!is_array($raw)) {
        return $out;
    }
    foreach ($raw as $id => $row) {
        if (!is_array($row) || count($out) >= ($def['max'] ?? CM_MAX_ITEMS)) {
            continue;
        }
        $id = preg_replace('/[^a-z0-9]/i', '', (string) $id) ?: 'x' . bin2hex(random_bytes(3));
        $item = ['id' => mb_substr($id, 0, 20)];
        $empty = true;
        foreach ($def['fields'] as $fd) {
            $val = editor_clean($fd, $row[$fd['key']] ?? ($fd['type'] === 'switch' ? '0' : ''), $errors, $def['key'] . '.' . $id . '.' . $fd['key']);
            $item[$fd['key']] = $val;
            if (in_array($fd['type'], ['text', 'textarea', 'rich', 'lines'], true) && trim(strip_tags((string) $val)) !== '') {
                $empty = false;
            }
        }
        if (!$empty) {
            $out[] = $item;
        }
    }
    return $out;
}

/* =====================================================================
 * Salvataggio comune: Salva bozza / Anteprima / Pubblica
 * ===================================================================== */

/**
 * Salva la bozza e, a seconda del pulsante premuto, apre l'anteprima o pubblica.
 * Se ci sono errori la bozza NON viene salvata e i messaggi restano vicino ai campi.
 */
function editor_save(string $area, array $data, array $errors, array $user, string $backUrl, string $previewPage = ''): void
{
    if ($errors) {
        flash('error', 'Alcuni campi non sono validi: correggili e salva di nuovo.');
        return;
    }
    content_save_draft($area, $data, (int) $user['id']);
    $label = content_areas()[$area]['label'] ?? $area;
    $action = post('azione', 'bozza');
    if ($action === 'pubblica') {
        content_publish([$area], (int) $user['id'], 'Pubblicazione di: ' . $label);
        audit('pubblicazione', $label);
        flash('ok', 'Pubblicato: le modifiche a "' . $label . '" sono visibili sul sito.');
        redirect($backUrl);
    }
    audit('bozza_salvata', $label);
    if ($action === 'anteprima') {
        redirect('../anteprima.php' . ($previewPage !== '' ? '?pagina=' . rawurlencode($previewPage) : '') . '#' . editor_anchor($area));
    }
    flash('ok', 'Bozza salvata. Il sito non cambia finché non premi Pubblica.');
    redirect($backUrl);
}

function editor_anchor(string $area): string
{
    return [
        'hero' => 'top', 'problema' => 'problema', 'come_funziona' => 'come-funziona', 'giornata' => 'una-giornata',
        'funzioni' => 'funzioni', 'per_chi' => 'per-chi', 'prezzi' => 'prezzi', 'faq' => 'domande', 'contatti' => 'contatti',
    ][$area] ?? 'top';
}

/** Riquadro di stato della bozza in cima alle pagine di modifica. */
function editor_status_html(string $area): string
{
    $row = content_row($area);
    if (!$row) {
        return '';
    }
    $pending = json_decode((string) $row['draft'], true) != json_decode((string) $row['published'], true);
    $html = '<p class="hint">Ultima pubblicazione: ' . e(data_it($row['published_at'])) . '.';
    if ($pending) {
        $html .= '</p><div class="notice notice-warn" role="status">Ci sono modifiche salvate in bozza e non ancora pubblicate (salvate il ' . e(data_it($row['draft_updated_at'])) . ').</div>';
    } else {
        $html .= ' La bozza coincide con la versione pubblicata.</p>';
    }
    return $html;
}

/* =====================================================================
 * Disegno dei campi
 * ===================================================================== */

function editor_errors_for(array $errors, string $key): string
{
    return $errors[$key] ?? '';
}

/** Disegna un campo descritto da $def. $name: nome HTML; $id: id univoco. */
function editor_field_html(array $def, string $name, string $id, mixed $value, string $error = ''): string
{
    $o = ['id' => $id, 'hint' => $def['hint'] ?? '', 'rec' => $def['rec'] ?? 0, 'error' => $error];
    switch ($def['type']) {
        case 'switch':
            return field($name, $def['label'], (bool) $value, $o + ['type' => 'switch']);
        case 'textarea':
            return field($name, $def['label'], (string) $value, $o + ['type' => 'textarea', 'rows' => 3]);
        case 'lines':
            return field($name, $def['label'], (string) $value, $o + ['type' => 'textarea', 'rows' => 4]);
        case 'rich':
            return field($name, $def['label'], (string) $value, $o + ['type' => 'richtext', 'rec' => 0]);
        case 'href':
            return field($name, $def['label'], (string) $value, $o + ['hint' => $def['hint'] ?? 'Ancora (es. #contatti) oppure indirizzo https://, mailto:, tel:.']);
        case 'number':
            if (isset($def['pair'])) {
                $def['attrs'] = trim(($def['attrs'] ?? '') . ' data-full="' . e(str_starts_with($def['pair'], 'f-') ? $def['pair'] : substr($id, 0, -strlen($def['key'])) . $def['pair']) . '"');
            }
            return field($name, $def['label'], $value === '' ? '' : rtrim(rtrim(number_format((float) $value, 2, ',', ''), '0'), ','), $o + ['attrs' => 'inputmode="decimal"' . (isset($def['attrs']) ? ' ' . $def['attrs'] : '')]);
        case 'int':
            return field($name, $def['label'], (string) (int) $value, $o + ['type' => 'number', 'attrs' => 'min="0" step="1"']);
        case 'date':
            return field($name, $def['label'], (string) $value, $o + ['type' => 'date']);
        case 'email':
            return field($name, $def['label'], (string) $value, $o + ['type' => 'email']);
        case 'photo':
            return field($name, $def['label'], (string) $value, $o + ['type' => 'select', 'options' => ['' => 'Nessuna fotografia'] + array_map(static fn ($p) => $p[0], photo_library())]);
        case 'icon':
            return icon_picker_html($name, $id, (string) $value, $def['label']);
        case 'illustration':
            return field($name, $def['label'], (string) $value, $o + ['type' => 'select', 'options' => visual_library()]);
        case 'media':
            $opts = [0 => 'Nessuna'];
            foreach (media_all() as $m) {
                $opts[(int) $m['id']] = '#' . $m['id'] . ' · ' . str_limit((string) $m['alt'], 60);
            }
            return field($name, $def['label'], (string) (int) $value, $o + ['type' => 'select', 'options' => $opts, 'hint' => ($def['hint'] ?? '') . ' Le immagini si caricano dalla pagina Media.']);
        default:
            return field($name, $def['label'], (string) $value, $o);
    }
}

function icon_picker_html(string $name, string $id, string $value, string $label): string
{
    $lib = icon_library();
    $value = isset($lib[$value]) ? $value : 'spunta';
    $html = '<details class="icon-details field"><summary>' . e($label) . ': ' . icon_svg($value) . ' <span>' . e($lib[$value][0]) . '</span> <span class="hint">(cambia)</span></summary>'
        . '<fieldset class="icon-picker"><legend class="sr-only">' . e($label) . '</legend>';
    foreach ($lib as $key => [$lab]) {
        $rid = $id . '-' . $key;
        $html .= '<div class="icon-opt"><input type="radio" name="' . e($name) . '" id="' . e($rid) . '" value="' . e($key) . '"' . ($key === $value ? ' checked' : '') . '>'
            . '<label for="' . e($rid) . '">' . icon_svg($key) . '<span>' . e($lab) . '</span></label></div>';
    }
    return $html . '</fieldset></details>';
}

/** Elemento di una lista riordinabile. */
function repeater_item_html(array $def, string $itemId, array $item, array $errors): string
{
    $base = $def['key'] . '[' . $itemId . ']';
    $idBase = 'r-' . $def['key'] . '-' . $itemId;
    $titleText = trim(strip_tags((string) ($item['title'] ?? $item['label'] ?? $item['question'] ?? '')));
    $hidden = array_key_exists('visible', $item) && !$item['visible'];
    $html = '<li class="item' . ($hidden ? ' item-hidden' : '') . '">'
        . '<div class="item-head">'
        . '<span class="drag-handle" title="Trascina per riordinare" aria-hidden="true">⠿</span>'
        . '<p class="item-title">' . e($def['label']) . ' <span data-pos>?</span>' . ($titleText !== '' ? ': ' . e(str_limit($titleText, 50)) : '') . '</p>'
        . '<button type="button" class="btn btn-line btn-small btn-icon" data-move="up" aria-label="Sposta in su">↑</button>'
        . '<button type="button" class="btn btn-line btn-small btn-icon" data-move="down" aria-label="Sposta in giù">↓</button>'
        . '<button type="button" class="btn btn-danger btn-small" data-remove="Eliminare questo elemento? L\'eliminazione diventa definitiva quando salvi.">Elimina</button>'
        . '</div>';
    foreach ($def['fields'] as $fd) {
        $html .= editor_field_html($fd, $base . '[' . $fd['key'] . ']', $idBase . '-' . $fd['key'], $item[$fd['key']] ?? '', $errors[$def['key'] . '.' . $itemId . '.' . $fd['key']] ?? '');
    }
    return $html . '</li>';
}

function repeater_html(array $def, array $items, array $errors): string
{
    $listId = 'list-' . $def['key'];
    $html = '<p class="hint">Trascina dalla maniglia ⠿ oppure usa le frecce per cambiare l\'ordine. Massimo ' . (int) ($def['max'] ?? CM_MAX_ITEMS) . ' elementi.</p>'
        . '<p class="sr-only sr-live" aria-live="polite"></p>'
        . '<ol class="repeater" data-repeater id="' . e($listId) . '">';
    foreach ($items as $item) {
        $id = preg_replace('/[^a-z0-9]/i', '', (string) ($item['id'] ?? '')) ?: 'x' . bin2hex(random_bytes(3));
        $html .= repeater_item_html($def, $id, $item, $errors);
    }
    $html .= '</ol>';
    $blank = [];
    foreach ($def['fields'] as $fd) {
        $blank[$fd['key']] = $fd['type'] === 'switch' ? true : '';
    }
    $html .= '<template id="tpl-' . e($def['key']) . '">' . repeater_item_html($def, '__N__', $blank, []) . '</template>'
        . '<button type="button" class="btn btn-line" data-add="' . e($listId) . '" data-template="tpl-' . e($def['key']) . '">+ ' . e($def['add'] ?? 'Aggiungi') . '</button>';
    return $html;
}

/** Disegna tutti i gruppi di uno schema. */
function editor_groups_html(array $schema, array $data, array $errors): string
{
    $html = '';
    foreach ($schema['groups'] as [$legend, $items]) {
        $html .= '<fieldset><legend>' . e($legend) . '</legend>';
        foreach ($items as $def) {
            if ($def['type'] === 'repeater') {
                $html .= repeater_html($def, is_array($data[$def['key']] ?? null) ? $data[$def['key']] : [], $errors);
                continue;
            }
            $html .= editor_field_html($def, $def['key'], field_id($def['key']), $data[$def['key']] ?? '', $errors[$def['key']] ?? '');
        }
        $html .= '</fieldset>';
    }
    return $html;
}

function editor_tokens_hint(): string
{
    return '<p class="hint">Nei testi brevi puoi usare <code>{sconto}</code> (percentuale dell\'offerta di lancio) e <code>{prezzo_da}</code> (canone mensile più basso).</p>';
}
