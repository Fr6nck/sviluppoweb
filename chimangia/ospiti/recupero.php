<?php
/**
 * Password dimenticata dall'ospite.
 * Con gli avvisi via email attivi e un'email nel profilo arriva un collegamento monouso (un'ora);
 * altrimenti la nuova password la dà la reception.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
pf_boot('guest', '../');
require __DIR__ . '/_comuni.php';

if (guest_current()) {
    redirect('profilo.php');
}
$venue = g_venue_pick();
if (!$venue) {
    redirect('index.php');
}
$byEmail = feature('email', $venue);
$back = 'index.php?l=' . rawurlencode((string) $venue['slug']);
$sent = false;
$error = '';

if ($byEmail && is_post()) {
    $phone = phone_normalize(post('phone'));
    if (!login_token_valid(post('_lt'), 'guest-reset')) {
        $error = 'Il modulo è scaduto. Ricarica la pagina e riprova.';
    } elseif ($phone === '') {
        $error = 'Scrivi il cellulare con cui ti sei registrato.';
    } elseif (pf_rate_count('guest-reset', 3600) >= 5) {
        $error = 'Troppe richieste. Riprova tra un\'ora oppure chiedi alla reception.';
    } else {
        pf_rate_hit('guest-reset');
        $g = q_one('SELECT * FROM ' . t('guests') . " WHERE venue_id = ? AND phone = ? AND status <> 'bloccato'", [$venue['id'], $phone]);
        if ($g && (string) $g['email'] !== '') {
            $token = pf_token_create('guest_reset', (int) $g['id'], 3600);
            pf_mail_queue(
                (int) $venue['id'],
                (string) $g['email'],
                $venue['name'] . ': nuova password',
                'Ciao ' . $g['first_name'] . ",\n\nper scegliere una nuova password apri il collegamento:\n" . site_url('ospiti/reimposta.php?t=' . $token)
                . "\n\nVale un'ora e si può usare una sola volta. Se non l'hai chiesto tu, ignora questo messaggio.\n\n" . $venue['name']
            );
        }
        $sent = true;   // stessa risposta in ogni caso: non riveliamo se il numero è registrato
    }
}

ui_guest_start('Password *dimenticata*', '', ['back' => $back, 'back_label' => (string) $venue['name']], $venue);
$reception = 'chiedi alla reception di ' . e((string) $venue['name']) . ' una nuova password'
    . ((string) $venue['phone'] !== '' ? ' (<a href="tel:' . e((string) $venue['phone']) . '">' . e(phone_show((string) $venue['phone'])) . '</a>)' : '');
if (!$byEmail) {
    echo '<div class="info-line">' . icon('key') . '<span>Per rientrare ' . $reception . '. Te la cambiano in un attimo.</span></div>'
        . '<a class="btn btn-line btn-block" href="' . e($back) . '">Torna all\'accesso</a>';
    ui_guest_end();
    exit;
}
if ($sent) {
    echo '<div class="notice notice-ok" role="status">Se il numero è registrato e nel profilo c\'è un\'email, tra poco riceverai il collegamento per scegliere una nuova password.</div>'
        . '<div class="info-line">' . icon('info') . '<span>Non arriva nulla? Allora ' . $reception . '.</span></div>'
        . '<a class="btn btn-line btn-block" href="' . e($back) . '">Torna all\'accesso</a>';
    ui_guest_end();
    exit;
}
if ($error !== '') {
    echo '<div class="notice notice-error" role="alert">' . e($error) . '</div>';
}
echo '<form method="post" action="recupero.php" class="card stack-s" novalidate>' . login_token_field('guest-reset')
    . ui_field('phone', 'Il tuo cellulare', post('phone'), ['type' => 'tel', 'required' => true, 'autocomplete' => 'tel', 'placeholder' => '333 123 4567', 'hint' => 'Ti mandiamo un collegamento all\'email del tuo profilo. Vale un\'ora.'])
    . '<button type="submit" class="btn btn-dark btn-block">Invia il collegamento' . icon('arrow', 18) . '</button></form>'
    . '<div class="info-line">' . icon('info') . '<span>Non hai indicato un\'email? Allora ' . $reception . '.</span></div>';
ui_guest_end();
