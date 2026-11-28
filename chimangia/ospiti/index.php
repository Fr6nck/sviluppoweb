<?php
/**
 * Area ospiti: ingresso del locale (accesso con cellulare e password) e,
 * dopo l'accesso, la home dell'ospite con le cose da fare.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/platform/core.php';
pf_boot('guest', '../');
require __DIR__ . '/_comuni.php';

$asked = mb_strtolower(get_param('l'));
$me = guest_current();

// Collegamento di un altro locale mentre si è già dentro: si chiede prima di uscire
// (un semplice collegamento non deve poter far uscire nessuno).
if ($me && $asked !== '' && $asked !== $me['venue']['slug'] && ($other = venue_by_slug($asked))) {
    ui_guest_start('Un altro *locale*', '', ['eyebrow' => 'Sei dentro come ' . $me['first_name'], 'nav' => false]);
    echo '<div class="info-line">' . icon('info') . '<span>Hai aperto il collegamento di <strong>' . e((string) $other['name']) . '</strong>, ma sei collegato a <strong>' . e((string) $me['venue']['name']) . '</strong>.</span></div>'
        . '<a class="btn btn-dark btn-block" href="index.php">Resta in ' . e((string) $me['venue']['name']) . '</a>'
        . '<form method="post" action="esci.php">' . csrf_field() . '<input type="hidden" name="poi" value="' . e((string) $other['slug']) . '">'
        . '<button type="submit" class="btn btn-line btn-block">Esci e vai a ' . e((string) $other['name']) . '</button></form>';
    ui_guest_end();
    exit;
}

/* =====================================================================
 * Prima dell'accesso
 * ===================================================================== */
if (!$me) {
    $venue = g_venue_pick();

    if (!$venue) {
        unset($_SESSION['venue_slug']);
        ui_guest_start('Qual è il tuo *locale*?', '', ['eyebrow' => 'Area ospiti']);
        if ($asked !== '') {
            echo '<div class="notice notice-error" role="alert">Non troviamo un locale con questo codice. Controlla il collegamento che ti hanno dato.</div>';
        }
        echo '<form method="get" action="index.php" class="card stack-s">'
            . '<p class="small muted">Apri il collegamento che ti ha dato il locale, oppure scrivi qui il suo codice.</p>'
            . ui_field('l', 'Codice del locale', '', ['required' => true, 'placeholder' => 'es. trattoria-da-mario', 'autocomplete' => 'off', 'attrs' => 'autocapitalize="none" pattern="[a-zA-Z0-9-]{2,60}"'])
            . '<button type="submit" class="btn btn-dark btn-block">Vai' . icon('arrow', 18) . '</button></form>';
        ui_guest_end();
        exit;
    }

    if (!venue_live($venue)) {
        ui_guest_start('Prenotazioni *sospese*', '', ['eyebrow' => 'Area ospiti'], $venue);
        echo '<div class="info-line">' . icon('info') . '<span>Al momento le prenotazioni di ' . e($venue['name']) . ' non sono attive. Per pranzo e cena rivolgiti direttamente al locale.</span></div>';
        if ((string) $venue['phone'] !== '') {
            echo '<p><a class="btn btn-line btn-block" href="tel:' . e((string) $venue['phone']) . '">' . icon('phone', 18) . 'Chiama ' . e(phone_show((string) $venue['phone'])) . '</a></p>';
        }
        ui_guest_end();
        exit;
    }

    $error = '';
    $phone = '';
    if (is_post()) {
        $phone = post('phone');
        if (!login_token_valid(post('_lt'), 'guest')) {
            $error = 'Il modulo è scaduto. Ricarica la pagina e riprova.';
        } elseif ($phone === '' || post('password') === '') {
            $error = 'Scrivi cellulare e password.';
        } else {
            $error = (string) guest_login($venue, $phone, (string) ($_POST['password'] ?? ''));
            if ($error === '') {
                redirect('index.php');
            }
        }
    }

    $welcome = trim((string) ($venue['settings']['welcome'] ?? ''));
    ui_guest_start('Prenota i tuoi *pasti*', '', [
        'eyebrow' => feature('hotel', $venue) ? 'Area ospiti · Camere e ristorante' : 'Area ospiti',
        'lead' => $welcome !== '' ? nl2br(e($welcome)) : '',
    ], $venue);
    if ($error !== '') {
        echo '<div class="notice notice-error" role="alert">' . e($error) . '</div>';
    }
    $here = 'index.php?l=' . rawurlencode((string) $venue['slug']);
    echo '<form method="post" action="' . e($here) . '" class="card stack-s" novalidate>' . login_token_field('guest')
        . '<h2>Accedi</h2>'
        . ui_field('phone', 'Cellulare', $phone, ['type' => 'tel', 'required' => true, 'autocomplete' => 'tel', 'placeholder' => '333 123 4567'])
        . ui_field('password', 'Password', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password'])
        . '<button type="submit" class="btn btn-dark btn-big btn-block">Entra' . icon('arrow', 20) . '</button>'
        . '<p class="small"><a href="recupero.php">Hai dimenticato la password?</a></p></form>';
    echo '<div class="card card-cream stack-s"><h2>È la prima volta?</h2>'
        . '<p class="small muted">Ti registri in un minuto. Poi entri con cellulare e password.</p>'
        . '<a class="btn btn-zaff btn-block" href="registrati.php">Registrati' . icon('arrow', 18) . '</a></div>';
    ui_guest_end();
    exit;
}

/* =====================================================================
 * Home dell'ospite
 * ===================================================================== */
$me = guest_require();
$venue = $me['venue'];
$meals = array_values(array_intersect(array_keys(PF_MEALS), (array) $venue['settings']['meals']));
$pending = g_pending((int) $me['id']);
$tomorrow = day_add(today(), 1);
$mine = bookings_of_guest((int) $me['id'], today(), day_add(today(), (int) $venue['settings']['horizon']));

// Menù usciti a cui l'ospite deve ancora rispondere.
$todo = [];
$rows = q(
    'SELECT m.day, m.meal FROM ' . t('menus') . ' m JOIN ' . t('bookings') . " b ON b.venue_id = m.venue_id AND b.day = m.day AND b.meal = m.meal
     WHERE m.venue_id = ? AND m.status = 'pubblicato' AND m.day >= ? AND b.guest_id = ? AND b.status = 'prenotato' ORDER BY m.day, m.meal DESC",
    [$venue['id'], today(), $me['id']]
)->fetchAll();
foreach ($rows as $r) {
    $menu = menu_get((int) $venue['id'], (string) $r['day'], (string) $r['meal']);
    if ($menu && meal_open($venue, (string) $r['day'], (string) $r['meal'], $menu)) {
        $todo[] = ['day' => (string) $r['day'], 'meal' => (string) $r['meal'], 'limit' => date('H:i', meal_cutoff($venue, (string) $r['day'], (string) $r['meal'], $menu))];
    }
}

ui_guest_start('Ciao *' . $me['first_name'] . '*', 'index.php', ['eyebrow' => 'Bentornato']);

if ($me['status'] === 'attesa') {
    echo '<div class="info-line">' . icon('clock') . '<span>In attesa di conferma dalla reception. Puoi già segnare i pasti: arrivano in cucina appena la registrazione viene confermata.</span></div>';
} else {
    echo '<div class="info-line ok">' . icon('check') . '<span>Registrazione confermata dalla reception</span></div>';
}

foreach ($todo as $x) {
    echo '<a class="g-item todo" href="menu.php?g=' . e($x['day']) . '&amp;p=' . e($x['meal']) . '"><span class="g-ico">' . icon('utensils', 22) . '</span>'
        . '<span class="grow"><strong>' . e('È pronto il menù: ' . mb_strtolower(g_meal_title($x['day'], $x['meal']))) . '</strong>'
        . '<small>Scegli i piatti entro le ' . e($x['limit']) . '</small></span>' . icon('chev-right', 20) . '</a>';
}

echo '<section class="card stack-s"><div class="card-head"><h2>Oggi e domani</h2><a href="pasti.php">Tutti i giorni</a></div><div class="list-line">';
$any = false;
foreach ([today(), $tomorrow] as $day) {
    foreach ($meals as $meal) {
        if (!meal_served($venue, $day, $meal)) {
            continue;
        }
        $any = true;
        $b = $mine[$day . '|' . $meal] ?? null;
        [$label, $tone, $toChoose] = g_state($venue, $b, $day, $meal, $pending);
        $label = $toChoose ? 'Scegli i piatti' : $label;
        $menu = menu_get((int) $venue['id'], $day, $meal);
        $href = $menu && $menu['status'] !== 'bozza' ? 'menu.php?g=' . $day . '&p=' . $meal : 'pasti.php';
        $choice = $b && $b['status'] === 'confermato' ? choices_text(choices_decode((string) $b['choices'])) : '';
        echo '<a class="g-item" href="' . e($href) . '"><span class="grow"><strong>' . e(g_meal_title($day, $meal)) . '</strong>'
            . ($choice !== '' ? '<small>' . e($choice) . '</small>' : '') . '</span>' . ui_tag($label, $tone) . '</a>';
    }
}
if (!$any) {
    echo '<p class="small muted">Oggi e domani il locale è chiuso.</p>';
}
echo '</div></section>';

if (feature('notifiche', $venue)) {
    $notices = q('SELECT * FROM ' . t('notices') . ' WHERE guest_id = ? ORDER BY id DESC LIMIT 3', [$me['id']])->fetchAll();
    if ($notices) {
        echo '<section class="card card-white stack-s"><div class="card-head"><h2>Ultimi avvisi</h2><a href="notifiche.php">Vedi tutti</a></div><div class="list-line">';
        foreach ($notices as $n) {
            echo '<div' . ($n['read_at'] === null ? ' class="unread"' : '') . '><strong>' . e((string) $n['title']) . '</strong>'
                . ((string) $n['body'] !== '' ? '<p class="small muted">' . e((string) $n['body']) . '</p>' : '') . '</div>';
        }
        echo '</div></section>';
    }
}

echo '<a class="btn btn-dark btn-big btn-block" href="pasti.php">' . icon('calendar', 20) . e(feature('hotel', $venue) ? 'Segna notti e pasti' : 'Segna i tuoi pasti') . '</a>';
echo '<p class="small muted g-tip">' . icon('info', 16) . ' Aggiungi questa pagina alla schermata Home del telefono: la apri come un\'app, senza scaricare nulla.</p>';
ui_guest_end();
