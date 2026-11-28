<?php
/** Pannello del locale — Abbonamento: stato della prova o del piano, clienti del mese, richiesta di attivazione. */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
require __DIR__ . '/_parts.php';
pf_boot('staff', '../');
$me = staff_require();
$v = $me['venue'];
$vid = (int) $v['id'];
if ((int) $me['is_owner'] !== 1 && $me['role'] !== 'admin') {
    ui_die('Accesso non consentito', 'L\'abbonamento lo gestisce il titolare.', 403);
}
$plans = pf_plans();

if (is_post()) {
    pf_csrf_check();
    if (post('azione') === 'attiva') {
        $plan = isset($plans[post('plan')]) ? post('plan') : (string) $v['plan'];
        $billing = post('billing') === 'annuale' ? 'annuale' : 'mensile';
        if (pf_rate_count('attiva-' . $vid, 3600) >= 3) {
            pf_flash('info', 'Abbiamo già ricevuto la tua richiesta: ti rispondiamo al più presto.');
        } else {
            pf_rate_hit('attiva-' . $vid);
            $body = "Locale: {$v['name']} ({$v['slug']})\nRichiesta da: {$me['name']} <{$me['email']}>\nPiano: {$plans[$plan][0]} · pagamento $billing\nModulo Hotel: " . (feature('hotel', $v) ? 'sì' : 'no')
                . "\nMessaggio: " . clean_lines(post('message'), 1000) . "\n\nScheda: " . site_url('superadmin/cliente.php?id=' . $vid);
            foreach (notify_recipients() as $to) {
                pf_mail_queue(null, $to, 'Richiesta di attivazione: ' . $v['name'], $body);
            }
            pf_event($vid, 'attivazione_richiesta', $plans[$plan][0] . ' · ' . $billing);
            pf_flash('ok', 'Richiesta inviata. Ti scriviamo a ' . $me['email'] . ' per completare l\'attivazione.');
        }
    }
    redirect('abbonamento.php');
}

$days = venue_trial_days($v);
$live = venue_live($v);
$used = venue_usage($vid);
$limit = $plans[$v['plan']][1] ?? 40;
$statusText = match (true) {
    $v['status'] === 'attivo' => 'Piano attivo',
    $v['status'] === 'prova' && $live => 'Prova gratuita: ' . ($days === 1 ? 'manca 1 giorno' : 'mancano ' . $days . ' giorni'),
    $v['status'] === 'prova' => 'La prova gratuita è terminata',
    $v['status'] === 'sospeso' => 'Servizio sospeso',
    default => 'Servizio chiuso',
};
ui_staff_start($live ? 'Il tuo *abbonamento*' : 'Riattiva *ChiMangia*', 'abbonamento.php', ['eyebrow' => $statusText,
    'lead' => $live ? '' : 'I tuoi dati sono al sicuro: ospiti, menù e prenotazioni restano dove li hai lasciati. Per ripartire invia la richiesta qui sotto.']);

echo '<div class="kpis">' . ui_kpi('Stato', pf_statuses()[$v['status']] ?? '', $v['status'] === 'prova' && $v['trial_ends_at'] ? 'fino al ' . data_it((string) $v['trial_ends_at'], false) : '', $live ? '' : 'zaff')
    . ui_kpi('Piano', (string) $limit, $plans[$v['plan']][0] ?? '') . ui_kpi('Clienti di ' . PF_MONTHS[(int) date('n')], $used, $used > $limit ? 'oltre il piano: parliamone' : 'con almeno un pasto prenotato', $used > $limit ? 'zaff' : '')
    . ui_kpi('Modulo Hotel', feature('hotel', $v) ? 'Sì' : 'No', feature('hotel', $v) ? 'notti e camere' : 'solo pasti') . '</div>';

echo '<div class="grid-2"><section class="card"><div class="card-head"><h2>' . ($v['status'] === 'attivo' ? 'Cambia piano' : 'Attiva il piano') . '</h2><p>Paghi solo per i clienti che prenotano almeno un pasto nel mese</p></div>'
    . '<form method="post" action="abbonamento.php" class="form">' . csrf_field() . '<input type="hidden" name="azione" value="attiva">'
    . '<div class="field"><span class="label">Quanti clienti servi</span>' . ui_chips('plan', array_map(static fn ($p) => $p[0], $plans), [(string) $v['plan']], 'radio') . '</div>'
    . '<div class="field"><span class="label">Pagamento</span>' . ui_chips('billing', ['mensile' => 'Mensile', 'annuale' => 'Annuale'], [(string) $v['billing']], 'radio') . '</div>'
    . ui_field('message', 'Vuoi dirci qualcosa?', '', ['type' => 'textarea', 'placeholder' => 'Domande, dati per la fattura, moduli da aggiungere…'])
    . '<div><button type="submit" class="btn btn-dark btn-big">Invia la richiesta' . icon('arrow', 18) . '</button></div>'
    . '<p class="hint">Ti ricontattiamo noi per fattura e pagamento. I prezzi sono quelli del <a href="../#prezzi" target="_blank" rel="noopener">listino sul sito</a>.</p></form></section>';

echo '<section class="card card-cream"><div class="card-head"><h2>Funzioni attive</h2></div><ul class="list-line small">';
foreach (pf_features() as $k => [$lab, $desc]) {
    echo '<li class="row-between"><span>' . e($lab) . '<span class="sub">' . e($desc) . '</span></span>' . (feature($k, $v) ? ui_tag('Attiva', 'ok') : ui_tag('Non attiva', 'soft')) . '</li>';
}
echo '</ul></section></div>';
ui_shell_end();
