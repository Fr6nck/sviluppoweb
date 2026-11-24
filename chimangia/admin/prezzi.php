<?php
/** Prezzi: pacchetti, offerta di lancio, modulo Hotel e testi della sezione. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/admin.php';

$user = require_role('editor');

$num = static fn (string $key, string $label, array $o = []) => f($key, $label, 'number', $o);
$schema = ['title' => 'Prezzi', 'groups' => [
    ['Sezione', [
        f('visible', 'Mostra la sezione prezzi nel sito', 'switch'),
        f('title', 'Titolo', 'text', ['rec' => 60]),
        f('intro', 'Introduzione', 'textarea', ['rec' => 160]),
    ]],
    ['Offerta di lancio', [
        f('offer_active', 'Offerta di lancio attiva', 'switch'),
        $num('offer_percent', 'Percentuale di sconto mostrata nella fascia', ['hint' => 'Solo il numero, es. 30.']),
        f('offer_end', 'Data di fine (facoltativa)', 'date', ['hint' => 'Dal giorno dopo il sito mostra solo i prezzi pieni, senza barrati.']),
        f('offer_band', 'Testo della fascia', 'textarea', ['rec' => 120]),
    ]],
    ['Pacchetti (passo 1)', [
        f('step1_title', 'Titolo del passo 1', 'text'),
        f('step1_text', 'Testo del passo 1', 'text', ['rec' => 100]),
        ['type' => 'repeater', 'key' => 'packages', 'label' => 'Pacchetto', 'add' => 'Aggiungi un pacchetto', 'max' => 6, 'fields' => [
            f('visible', 'Visibile nel sito', 'switch'),
            f('name', 'Nome', 'text', ['rec' => 24]),
            f('clients', 'Numero di clienti (limite del pacchetto)', 'int'),
            $num('monthly_full', 'Mensile pieno (€)', ['pair' => 'monthly_launch']),
            $num('monthly_launch', 'Mensile di lancio (€)'),
            $num('annual_full', 'Annuale pieno (€)', ['pair' => 'annual_launch']),
            $num('annual_launch', 'Annuale di lancio (€)'),
            $num('activation_full', 'Attivazione piena (€)', ['pair' => 'activation_launch']),
            $num('activation_launch', 'Attivazione di lancio (€)'),
            f('note', 'Nota sotto il prezzo (facoltativa)', 'text', ['rec' => 80]),
            f('featured', 'Scheda in evidenza', 'switch'),
            f('badge', 'Etichetta della scheda in evidenza', 'text', ['rec' => 16]),
            f('cta_text', 'Testo del pulsante', 'text', ['rec' => 24]),
            f('cta_href', 'Destinazione del pulsante', 'href'),
        ]],
    ]],
    ['Testi delle schede', [
        f('toggle_label', 'Nome del selettore (per i lettori di schermo)', 'text'),
        f('toggle_monthly', 'Selettore: voce mensile', 'text'),
        f('toggle_annual', 'Selettore: voce annuale', 'text'),
        f('per_month', 'Dicitura "al mese"', 'text'),
        f('per_year', 'Dicitura "all\'anno"', 'text'),
        f('alt_prefix', 'Inizio della riga alternativa', 'text', ['hint' => 'Es. "Oppure": precede il prezzo dell\'altro periodo.']),
        f('annual_suffix', 'Fine della riga con il prezzo annuale', 'text'),
        f('monthly_suffix', 'Fine della riga con il prezzo mensile (vista Annuale)', 'text'),
        f('activation_label', 'Etichetta della fascia attivazione', 'text'),
    ]],
    ['Attivazione (passo 2)', [
        f('step2_title', 'Titolo del passo 2', 'text'),
        f('step2_text', 'Testo del passo 2', 'text', ['rec' => 100]),
        f('activation_title', 'Titolo del riquadro', 'text'),
        f('activation_list', 'Cosa comprende', 'rich'),
        f('activation_text', 'Testo a lato', 'textarea'),
        f('activation_offer_text', 'Frase aggiunta con l\'offerta attiva', 'text', ['hint' => '{sconto} diventa la percentuale.']),
    ]],
    ['Esempio, prova gratuita e note', [
        f('example', 'Esempio di calcolo', 'rich', ['hint' => 'Se cambi i prezzi, aggiorna anche i numeri dell\'esempio.']),
        f('trial_days', 'Giorni di prova gratuita', 'int'),
        f('trial', 'Riquadro della prova gratuita', 'rich'),
        f('vat_note', 'Nota IVA', 'text'),
        f('vat_offer_note', 'Frase aggiunta alla nota con l\'offerta attiva', 'text'),
    ]],
    ['Modulo Hotel', [
        f('hotel_visible', 'Mostra la riga del modulo Hotel', 'switch'),
        f('hotel_text', 'Testo della riga', 'textarea'),
        $num('hotel_full', 'Prezzo pieno al mese (€)', ['pair' => 'f-hotel-launch']),
        $num('hotel_launch', 'Prezzo di lancio al mese (€)'),
    ]],
]];

$data = content_get('prezzi', 'draft');
$errors = [];
if (is_post()) {
    csrf_check();
    $data = editor_collect($schema, $data, $_POST, $errors);
    $pct = (float) $data['offer_percent'];
    if ($pct < 0 || $pct >= 100) {
        $errors['offer_percent'] = 'La percentuale deve essere tra 0 e 99.';
    }
    $visible = 0;
    foreach ($data['packages'] as $p) {
        $k = 'packages.' . $p['id'] . '.';
        if (trim((string) $p['name']) === '') {
            $errors[$k . 'name'] = 'Scrivi il nome del pacchetto.';
        }
        foreach (['monthly', 'annual', 'activation'] as $t) {
            if ((float) $p[$t . '_launch'] > (float) $p[$t . '_full'] && (float) $p[$t . '_full'] > 0) {
                $errors[$k . $t . '_launch'] = 'Il prezzo di lancio è più alto del prezzo pieno.';
            }
        }
        $visible += !empty($p['visible']) ? 1 : 0;
    }
    if ($visible === 0 && !empty($data['visible'])) {
        $errors['packages'] = 'Serve almeno un pacchetto visibile.';
        flash('error', 'Serve almeno un pacchetto visibile.');
    }
    if (!$errors) {
        audit('prezzi_modificati', 'Azione: ' . post('azione', 'bozza') . '; offerta ' . (!empty($data['offer_active']) ? 'attiva ' . $pct . '%' : 'spenta'));
    }
    editor_save('prezzi', $data, $errors, $user, 'prezzi.php');
}

admin_header('Prezzi', 'prezzi.php');
echo editor_status_html('prezzi');
if (!empty($data['offer_active']) && !offer_is_active($data)): ?>
  <div class="notice notice-warn" role="status">L'offerta di lancio è attiva ma la data di fine è passata: il sito mostra solo i prezzi pieni.</div>
<?php endif; ?>
<p class="hint">Tutti i prezzi sono IVA esclusa. Il pacchetto con il canone mensile più basso alimenta il segnaposto {prezzo_da}; i dati strutturati per Google si aggiornano da soli.</p>
<form method="post" action="prezzi.php" class="form" novalidate data-dirty-check>
  <?= csrf_field() ?>
  <section class="panel" aria-labelledby="calc-h">
    <h2 id="calc-h">Calcola prezzi di lancio</h2>
    <p>Applica la percentuale dell'offerta a tutti i prezzi pieni (mensile, annuale, attivazione e modulo Hotel) e arrotonda all'euro. Dopo il calcolo puoi correggere i singoli valori.</p>
    <button type="button" class="btn btn-line" id="calc-launch">Calcola prezzi di lancio</button>
    <p class="hint" id="calc-result" aria-live="polite"></p>
  </section>
  <?= editor_groups_html($schema, $data, $errors) ?>
  <?= edit_actions() ?>
</form>
<?php
admin_footer();
