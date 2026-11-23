<?php
/**
 * Campi dei moduli del pannello: etichetta sempre presente, suggerimento,
 * conteggio dei caratteri consigliati (non bloccante) ed errore vicino al campo.
 */
declare(strict_types=1);

defined('CM_ROOT') || exit;

function field_id(string $name): string
{
    return 'f-' . trim((string) preg_replace('/[^a-z0-9]+/i', '-', $name), '-');
}

/**
 * Opzioni: type, hint, error, rec (caratteri consigliati), required, attrs (stringa già sicura),
 * rows (textarea), options (select: valore => etichetta), class.
 */
function field(string $name, string $label, mixed $value = '', array $o = []): string
{
    $type = $o['type'] ?? 'text';
    $id = $o['id'] ?? field_id($name);
    $hint = $o['hint'] ?? '';
    $error = $o['error'] ?? '';
    $rec = (int) ($o['rec'] ?? 0);
    $describedBy = [];
    if ($hint !== '') {
        $describedBy[] = $id . '-hint';
    }
    if ($rec > 0) {
        $describedBy[] = $id . '-count';
    }
    if ($error !== '') {
        $describedBy[] = $id . '-err';
    }
    $common = ' id="' . e($id) . '" name="' . e($name) . '"'
        . ($describedBy ? ' aria-describedby="' . e(implode(' ', $describedBy)) . '"' : '')
        . ($error !== '' ? ' aria-invalid="true"' : '')
        . (!empty($o['required']) ? ' required' : '')
        . ($rec > 0 ? ' data-rec="' . $rec . '"' : '')
        . (isset($o['attrs']) ? ' ' . $o['attrs'] : '');

    $html = '<div class="field' . (isset($o['class']) ? ' ' . e($o['class']) : '') . '">';

    if ($type === 'checkbox' || $type === 'switch') {
        $html .= '<label class="check' . ($type === 'switch' ? ' switch' : '') . '" for="' . e($id) . '">'
            . '<input type="hidden" name="' . e($name) . '" value="0">'
            . '<input type="checkbox" value="1"' . $common . ($value ? ' checked' : '') . '>'
            . '<span>' . e($label) . '</span></label>';
    } else {
        $html .= '<label id="' . e($id) . '-lab" for="' . e($id) . '">' . e($label) . (!empty($o['required']) ? ' <span class="req" aria-hidden="true">*</span>' : '') . '</label>';
        if ($type === 'textarea') {
            $html .= '<textarea rows="' . (int) ($o['rows'] ?? 3) . '"' . $common . '>' . e($value) . '</textarea>';
        } elseif ($type === 'select') {
            $html .= '<select' . $common . '>';
            foreach ($o['options'] ?? [] as $k => $lab) {
                $html .= '<option value="' . e($k) . '"' . ((string) $k === (string) $value ? ' selected' : '') . '>' . e($lab) . '</option>';
            }
            $html .= '</select>';
        } elseif ($type === 'richtext') {
            $html .= rich_editor($name, (string) $value, $id, $common);
        } else {
            $html .= '<input type="' . e($type) . '" value="' . e($value) . '"' . $common . '>';
        }
    }
    if ($hint !== '') {
        $html .= '<p class="hint" id="' . e($id) . '-hint">' . e($hint) . '</p>';
    }
    if ($rec > 0) {
        $html .= '<p class="count" id="' . e($id) . '-count" aria-live="polite" data-for="' . e($id) . '">Consigliati fino a ' . $rec . ' caratteri.</p>';
    }
    if ($error !== '') {
        $html .= '<p class="field-error" id="' . e($id) . '-err">' . e($error) . '</p>';
    }
    return $html . '</div>';
}

/**
 * Editor semplice: grassetto, corsivo, link ed elenchi.
 * Il contenuto modificabile è sincronizzato con una textarea nascosta;
 * senza JavaScript resta visibile la textarea con l'HTML.
 */
function rich_editor(string $name, string $value, string $id, string $common): string
{
    $tools = [
        ['bold', 'Grassetto', '<b>G</b>'],
        ['italic', 'Corsivo', '<i>C</i>'],
        ['link', 'Inserisci link', 'Link'],
        ['unlink', 'Togli link', 'Togli link'],
        ['ul', 'Elenco puntato', '• Elenco'],
        ['ol', 'Elenco numerato', '1. Elenco'],
    ];
    $bar = '<div class="rte-bar" role="toolbar" aria-label="Formattazione" hidden>';
    foreach ($tools as [$cmd, $label, $txt]) {
        $bar .= '<button type="button" class="rte-btn" data-cmd="' . $cmd . '" aria-label="' . e($label) . '" title="' . e($label) . '">' . $txt . '</button>';
    }
    $bar .= '</div>';
    return '<div class="rte" data-rte>' . $bar
        . '<div class="rte-area" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="' . e($id) . '-lab" hidden></div>'
        . '<textarea class="rte-source" rows="5"' . $common . '>' . e($value) . '</textarea></div>';
}

/** Pulsanti standard delle pagine di modifica. */
function edit_actions(string $previewHref = '../anteprima.php'): string
{
    return '<div class="actions-bar">'
        . '<button type="submit" name="azione" value="bozza" class="btn btn-line">Salva bozza</button>'
        . '<button type="submit" name="azione" value="anteprima" class="btn btn-line" formtarget="_blank">Anteprima<span class="sr-only"> (si apre in una nuova scheda)</span></button>'
        . '<button type="submit" name="azione" value="pubblica" class="btn btn-dark">Pubblica<span class="arr" aria-hidden="true">→</span></button>'
        . '</div>';
}
