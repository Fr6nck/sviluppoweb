<?php
/** Pannello del locale — Società: note per la cucina, contatti, doppioni da unire. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
require __DIR__ . '/_parts.php';
pf_boot('staff', '../');
$me = staff_require('societa', 'societa');
$v = $me['venue'];
$vid = (int) $v['id'];

if (is_post()) {
    pf_csrf_check();
    $id = (int) post('id');
    switch (post('azione')) {
        case 'nuova':
            $name = clean_text(post('name'), 160);
            if (mb_strlen($name) < 2) {
                pf_flash('error', 'Scrivi il nome della società.');
            } elseif (q_value('SELECT 1 FROM ' . t('companies') . ' WHERE venue_id = ? AND LOWER(name) = LOWER(?)', [$vid, $name])) {
                pf_flash('error', 'Questa società c\'è già.');
            } else {
                $new = company_resolve($vid, $name, true);
                q('UPDATE ' . t('companies') . ' SET note = ?, contact = ? WHERE id = ?', [clean_text(post('note'), 255), clean_text(post('contact'), 190), $new]);
                pf_event($vid, 'societa_creata', $name);
                pf_flash('ok', 'Società aggiunta.');
            }
            break;
        case 'salva':
            $name = clean_text(post('name'), 160);
            if (company_get($vid, $id) && mb_strlen($name) >= 2) {
                q('UPDATE ' . t('companies') . ' SET name = ?, note = ?, contact = ?, verified = 1 WHERE id = ? AND venue_id = ?', [$name, clean_text(post('note'), 255), clean_text(post('contact'), 190), $id, $vid]);
                pf_flash('ok', 'Società salvata.');
            }
            break;
        case 'unisci':
            if (company_merge($vid, $id, (int) post('in'))) {
                pf_event($vid, 'societa_unite', '');
                pf_flash('ok', 'Società unite: gli ospiti sono passati a quella scelta.');
            } else {
                pf_flash('error', 'Scegli la società in cui unirla.');
            }
            break;
        case 'elimina':
            if (company_get($vid, $id)) {
                q('UPDATE ' . t('guests') . ' SET company_id = NULL WHERE venue_id = ? AND company_id = ?', [$vid, $id]);
                q('DELETE FROM ' . t('companies') . ' WHERE id = ? AND venue_id = ?', [$id, $vid]);
                pf_flash('info', 'Società eliminata. I suoi ospiti restano, senza società.');
            }
            break;
    }
    redirect('societa.php');
}

$rows = companies_all($vid);
ui_staff_start('Le *società*', 'societa.php', ['lead' => 'Le aziende per cui lavorano i tuoi ospiti. Servono per raggruppare i presenti in cucina e per il riepilogo di fine mese.']);

echo '<details class="card card-cream"><summary class="btn btn-dark">' . icon('plus', 18) . 'Nuova società</summary><form method="post" action="societa.php" class="form">' . csrf_field()
    . '<input type="hidden" name="azione" value="nuova"><div class="form-grid">' . ui_field('name', 'Nome', '', ['required' => true, 'attrs' => 'maxlength="160"'])
    . ui_field('contact', 'Contatto per i conti', '', ['placeholder' => 'Email o telefono dell\'amministrazione'])
    . ui_field('note', 'Nota per la cucina', '', ['placeholder' => 'Es. squadra fuori dalle 7:00', 'class' => 'full']) . '</div><div><button type="submit" class="btn btn-dark">Aggiungi</button></div></form></details>';

echo '<section class="card">';
if (!$rows) {
    echo ui_empty('Ancora nessuna società. Compaiono qui quando gli ospiti le indicano registrandosi, oppure aggiungile tu.', 'building');
} else {
    echo '<div class="list-line">';
    foreach ($rows as $c) {
        $cid = (int) $c['id'];
        $similar = company_similar($vid, (string) $c['name'], $cid);
        echo '<details><summary class="row-between"><span><strong>' . e((string) $c['name']) . '</strong> ' . (!(int) $c['verified'] ? ui_tag('Nuova, da verificare', 'zaff') : '')
            . ($similar ? ' ' . ui_tag('Simile a «' . $similar['name'] . '»', 'soft') : '') . ($c['note'] !== '' ? '<span class="sub small muted">' . e((string) $c['note']) . '</span>' : '') . '</span>'
            . '<span class="muted small nowrap">' . (int) $c['guests'] . ((int) $c['guests'] === 1 ? ' ospite' : ' ospiti') . ' · Modifica</span></summary>'
            . '<div class="stack-s"><form method="post" action="societa.php" class="form">' . csrf_field() . '<input type="hidden" name="azione" value="salva"><input type="hidden" name="id" value="' . $cid . '"><div class="form-grid">'
            . ui_field('name', 'Nome', (string) $c['name'], ['required' => true, 'id' => 'n' . $cid]) . ui_field('contact', 'Contatto per i conti', (string) $c['contact'], ['id' => 'c' . $cid])
            . ui_field('note', 'Nota per la cucina', (string) $c['note'], ['id' => 'k' . $cid, 'class' => 'full']) . '</div><div><button type="submit" class="btn btn-small btn-dark">Salva</button></div></form>';
        if (count($rows) > 1) {
            echo '<form method="post" action="societa.php" class="row" data-confirm="Unire questa società a quella scelta? Gli ospiti vengono spostati e questa viene eliminata.">' . csrf_field()
                . '<input type="hidden" name="azione" value="unisci"><input type="hidden" name="id" value="' . $cid . '"><label for="in' . $cid . '" class="small">È un doppione? Uniscila a</label><select class="grow" id="in' . $cid . '" name="in"><option value="">Scegli…</option>';
            foreach ($rows as $o) {
                if ((int) $o['id'] !== $cid) {
                    echo '<option value="' . (int) $o['id'] . '"' . ($similar && (int) $similar['id'] === (int) $o['id'] ? ' selected' : '') . '>' . e((string) $o['name']) . '</option>';
                }
            }
            echo '</select><button type="submit" class="btn btn-small btn-line">Unisci</button></form>';
        }
        echo '<div class="row"><a class="btn btn-small btn-line" href="ospiti.php?s=' . $cid . '">Vedi gli ospiti</a>'
            . ui_post_btn('societa.php', ['azione' => 'elimina', 'id' => $cid], 'Elimina', 'btn btn-small btn-danger', 'Eliminare la società? Gli ospiti restano, senza società.') . '</div></div></details>';
    }
    echo '</div>';
}
echo '</section>';
ui_shell_end();
