<?php
/**
 * Piattaforma ChiMangia — regole del dominio: società, ospiti, menù, prenotazioni,
 * richieste, avvisi, notti e camere, riepiloghi.
 * Ogni funzione riceve l'id del locale: i dati di un locale non si mescolano mai con quelli di un altro.
 */
declare(strict_types=1);

defined('CM_ROOT') || exit;

/* =====================================================================
 * Società
 * ===================================================================== */

function companies_all(int $venueId): array
{
    return q(
        'SELECT c.*, (SELECT COUNT(*) FROM ' . t('guests') . ' g WHERE g.company_id = c.id) AS guests
         FROM ' . t('companies') . ' c WHERE c.venue_id = ? ORDER BY c.name',
        [$venueId]
    )->fetchAll();
}

function company_get(int $venueId, int $id): ?array
{
    return q_one('SELECT * FROM ' . t('companies') . ' WHERE venue_id = ? AND id = ?', [$venueId, $id]);
}

/** Nome ridotto all'osso per riconoscere i doppioni ("Edilrossi srl" ≈ "Edil Rossi Costruzioni"). */
function company_norm(string $name): string
{
    $s = mb_strtolower($name);
    $s = strtr($s, ['à' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u']);
    $s = preg_replace('/\b(s\.?r\.?l\.?s?|s\.?p\.?a|s\.?n\.?c|s\.?a\.?s|societa|ditta|impresa|costruzioni|group|gruppo)\b\.?/u', ' ', $s) ?? $s;
    return preg_replace('/[^a-z0-9]/', '', $s) ?? '';
}

/** Una società già presente con nome molto simile (diversa da $excludeId). */
function company_similar(int $venueId, string $name, int $excludeId = 0): ?array
{
    $n = company_norm($name);
    if (strlen($n) < 3) {
        return null;
    }
    foreach (companies_all($venueId) as $c) {
        if ((int) $c['id'] === $excludeId) {
            continue;
        }
        $m = company_norm((string) $c['name']);
        if ($m === '') {
            continue;
        }
        $close = $m === $n || levenshtein($m, $n) <= 2
            || (min(strlen($m), strlen($n)) >= 5 && (str_contains($m, $n) || str_contains($n, $m)));
        if ($close) {
            return $c;
        }
    }
    return null;
}

/** Trova la società per nome (senza distinguere maiuscole) o la crea. Stringa vuota → null. */
function company_resolve(int $venueId, string $name, bool $verified): ?int
{
    $name = clean_text($name, 160);
    if (mb_strlen($name) < 2) {
        return null;
    }
    $row = q_one('SELECT id FROM ' . t('companies') . ' WHERE venue_id = ? AND LOWER(name) = LOWER(?)', [$venueId, $name]);
    if ($row) {
        return (int) $row['id'];
    }
    q('INSERT INTO ' . t('companies') . ' (venue_id, name, verified, created_at) VALUES (?, ?, ?, ?)', [$venueId, $name, $verified ? 1 : 0, now()]);
    return (int) db()->lastInsertId();
}

/** Unisce un doppione: gli ospiti passano alla società di destinazione, il doppione viene eliminato. */
function company_merge(int $venueId, int $fromId, int $intoId): bool
{
    if ($fromId === $intoId || !company_get($venueId, $fromId) || !company_get($venueId, $intoId)) {
        return false;
    }
    q('UPDATE ' . t('guests') . ' SET company_id = ? WHERE venue_id = ? AND company_id = ?', [$intoId, $venueId, $fromId]);
    q('DELETE FROM ' . t('companies') . ' WHERE venue_id = ? AND id = ?', [$venueId, $fromId]);
    return true;
}

/* =====================================================================
 * Ospiti
 * ===================================================================== */

function guest_name(array $g): string
{
    return trim(($g['first_name'] ?? '') . ' ' . ($g['last_name'] ?? ''));
}

function guest_get(int $venueId, int $id): ?array
{
    return q_one(
        'SELECT g.*, c.name AS company, c.verified AS company_verified FROM ' . t('guests') . ' g
         LEFT JOIN ' . t('companies') . ' c ON c.id = g.company_id WHERE g.venue_id = ? AND g.id = ?',
        [$venueId, $id]
    );
}

function guest_password_problem(string $p): ?string
{
    return mb_strlen($p) < 6 ? 'La password deve avere almeno 6 caratteri.' : (mb_strlen($p) > 200 ? 'La password è troppo lunga.' : null);
}

/** Diete dell'ospite come elenco di etichette. */
function guest_diets(array $g): array
{
    return array_values(array_filter(array_map('trim', explode('|', (string) ($g['diets'] ?? '')))));
}

/** Etichette da mostrare in cucina: diete (in evidenza), esigenze scritte dall'ospite e nota per la cucina. */
function guest_tags(array $g): array
{
    $tags = [];
    foreach (guest_diets($g) as $d) {
        $tags[] = ['label' => $d, 'type' => 'diet'];
    }
    if (trim((string) ($g['needs'] ?? '')) !== '') {
        $tags[] = ['label' => (string) $g['needs'], 'type' => 'diet'];
    }
    if (trim((string) ($g['kitchen_note'] ?? '')) !== '') {
        $tags[] = ['label' => (string) $g['kitchen_note'], 'type' => 'note'];
    }
    return $tags;
}

/** Tiene solo le diete previste dal locale. */
function diets_clean(array $venue, mixed $input): string
{
    $allowed = (array) ($venue['settings']['diets'] ?? []);
    $picked = array_values(array_intersect($allowed, is_array($input) ? array_map('strval', $input) : []));
    return implode('|', $picked);
}

/**
 * Crea un ospite. $d: first_name, last_name, phone, email, password, company, diets (array), needs, consent (bool).
 * $byStaff: inserito dalla reception (già confermato, società verificata).
 * Restituisce [id, null] oppure [null, messaggio d'errore].
 */
function guest_create(array $venue, array $d, bool $byStaff = false): array
{
    $first = clean_text((string) ($d['first_name'] ?? ''), 80);
    $last = clean_text((string) ($d['last_name'] ?? ''), 80);
    $phone = phone_normalize((string) ($d['phone'] ?? ''));
    $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
    $needs = clean_text((string) ($d['needs'] ?? ''), 500);
    $diets = diets_clean($venue, $d['diets'] ?? []);
    if ($first === '' || $last === '') {
        return [null, 'Scrivi nome e cognome.'];
    }
    if ($phone === '') {
        return [null, 'Scrivi un numero di cellulare valido.'];
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [null, 'L\'indirizzo email non è valido.'];
    }
    if ($problem = guest_password_problem((string) ($d['password'] ?? ''))) {
        return [null, $problem];
    }
    if (!$byStaff && ($diets !== '' || $needs !== '') && empty($d['consent'])) {
        return [null, 'Per indicare diete o intolleranze serve il tuo consenso al loro utilizzo.'];
    }
    if (q_value('SELECT 1 FROM ' . t('guests') . ' WHERE venue_id = ? AND phone = ?', [$venue['id'], $phone])) {
        return [null, $byStaff ? 'Esiste già un ospite con questo cellulare.' : 'Con questo cellulare esiste già una registrazione: accedi con la tua password.'];
    }
    $companyId = feature('societa', $venue) ? company_resolve((int) $venue['id'], (string) ($d['company'] ?? ''), $byStaff) : null;
    $status = $byStaff || !empty($venue['settings']['auto_confirm']) ? 'confermato' : 'attesa';
    q(
        'INSERT INTO ' . t('guests') . ' (venue_id, first_name, last_name, phone, email, password_hash, company_id, diets, needs, staff_note, status, consent_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $venue['id'], $first, $last, $phone, $email, password_hash((string) $d['password'], PASSWORD_DEFAULT), $companyId, $diets, $needs, '',
            $status, ($diets !== '' || $needs !== '') && !$byStaff ? now() : null, now(), now(),
        ]
    );
    return [(int) db()->lastInsertId(), null];
}

function guests_pending_count(int $venueId): int
{
    return (int) q_value('SELECT COUNT(*) FROM ' . t('guests') . " WHERE venue_id = ? AND status = 'attesa'", [$venueId]);
}

/** Conferma la registrazione: l'ospite entra nei conteggi della cucina e viene avvisato. */
function guest_confirm(array $venue, int $guestId): bool
{
    $g = guest_get((int) $venue['id'], $guestId);
    if (!$g || $g['status'] === 'confermato') {
        return false;
    }
    q('UPDATE ' . t('guests') . " SET status = 'confermato', updated_at = ? WHERE id = ?", [now(), $guestId]);
    if ($g['company_id']) {
        q('UPDATE ' . t('companies') . ' SET verified = 1 WHERE id = ? AND venue_id = ?', [$g['company_id'], $venue['id']]);
    }
    notice_add($venue, $g, 'Registrazione confermata', 'Da ora le tue prenotazioni arrivano in cucina.', 'pasti.php');
    return true;
}

/* =====================================================================
 * Menù e orari limite
 * ===================================================================== */

/** Il locale serve quel pasto in quel giorno? */
function meal_served(array $venue, string $day, string $meal): bool
{
    $s = $venue['settings'];
    return in_array($meal, (array) $s['meals'], true) && !in_array((int) date('N', (int) strtotime($day)), array_map('intval', (array) $s['closed_days']), true);
}

function menu_decode(array $row): array
{
    $d = json_decode((string) $row['data'], true);
    $d = is_array($d) ? $d : [];
    $row['data'] = [
        'formulas' => array_values((array) ($d['formulas'] ?? [])),
        'courses' => array_merge(['primo' => [], 'secondo' => [], 'contorno' => []], (array) ($d['courses'] ?? [])),
        'drinks' => array_values((array) ($d['drinks'] ?? [])),
        'extras' => array_values((array) ($d['extras'] ?? [])),
    ];
    return $row;
}

function menu_get(int $venueId, string $day, string $meal, bool $fresh = false): ?array
{
    static $cache = [];
    $k = $venueId . '|' . $day . '|' . $meal;
    if ($fresh || !array_key_exists($k, $cache)) {
        $row = q_one('SELECT * FROM ' . t('menus') . ' WHERE venue_id = ? AND day = ? AND meal = ?', [$venueId, $day, $meal]);
        $cache[$k] = $row ? menu_decode($row) : null;
    }
    return $cache[$k];
}

/** Contenuto iniziale di un menù nuovo: formule, bevande e voci finali dalle impostazioni del locale. */
function menu_blank(array $venue): array
{
    $s = $venue['settings'];
    return [
        'formulas' => feature('formule', $venue) && $s['price_mode'] === 'formule' ? array_values((array) $s['formulas']) : [],
        'courses' => ['primo' => [], 'secondo' => [], 'contorno' => []],
        'drinks' => array_values((array) $s['drinks']),
        'extras' => array_values((array) $s['extras']),
    ];
}

/** Orario limite per rispondere (timestamp). Se il menù pubblicato ne indica uno, vale quello. */
function meal_cutoff(array $venue, string $day, string $meal, ?array $menu = null): int
{
    $menu ??= menu_get((int) $venue['id'], $day, $meal);
    $time = $menu && $menu['cutoff'] && $menu['status'] !== 'bozza' ? hm((string) $menu['cutoff']) : (string) ($venue['settings']['cutoff_' . $meal] ?? '');
    if (!valid_time($time)) {
        $time = $meal === 'pranzo' ? '10:30' : '16:30';
    }
    return (int) strtotime($day . ' ' . $time . ':00');
}

function meal_open(array $venue, string $day, string $meal, ?array $menu = null): bool
{
    return time() < meal_cutoff($venue, $day, $meal, $menu);
}

/**
 * Salva il menù (bozza o già pubblicato). $f: mode, cutoff, show_prices, note, data, photo.
 * Restituisce l'id.
 */
function menu_save(array $venue, string $day, string $meal, array $f): int
{
    $json = json_encode($f['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $cur = menu_get((int) $venue['id'], $day, $meal, true);
    if ($cur) {
        q(
            'UPDATE ' . t('menus') . ' SET mode = ?, cutoff = ?, show_prices = ?, note = ?, data = ?, photo = ?, updated_at = ? WHERE id = ?',
            [$f['mode'], $f['cutoff'], $f['show_prices'] ? 1 : 0, $f['note'], $json, $f['photo'], now(), $cur['id']]
        );
        $id = (int) $cur['id'];
    } else {
        q(
            'INSERT INTO ' . t('menus') . ' (venue_id, day, meal, status, mode, cutoff, show_prices, note, data, photo, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$venue['id'], $day, $meal, 'bozza', $f['mode'], $f['cutoff'], $f['show_prices'] ? 1 : 0, $f['note'], $json, $f['photo'], now(), now()]
        );
        $id = (int) db()->lastInsertId();
    }
    menu_get((int) $venue['id'], $day, $meal, true);
    return $id;
}

/** Pubblica il menù e avvisa chi ha prenotato quel pasto. Restituisce quanti ospiti sono stati avvisati. */
function menu_publish(array $venue, string $day, string $meal): int
{
    $menu = menu_get((int) $venue['id'], $day, $meal, true);
    if (!$menu) {
        return 0;
    }
    $first = $menu['status'] === 'bozza';
    q('UPDATE ' . t('menus') . " SET status = 'pubblicato', published_at = COALESCE(published_at, ?), closed_at = NULL, updated_at = ? WHERE id = ?", [now(), now(), $menu['id']]);
    $menu = menu_get((int) $venue['id'], $day, $meal, true);
    if (!$first) {
        return 0;
    }
    $limit = date('H:i', meal_cutoff($venue, $day, $meal, $menu));
    $guests = q(
        'SELECT g.* FROM ' . t('bookings') . ' b JOIN ' . t('guests') . " g ON g.id = b.guest_id
         WHERE b.venue_id = ? AND b.day = ? AND b.meal = ? AND b.status = 'prenotato' AND g.status = 'confermato'",
        [$venue['id'], $day, $meal]
    )->fetchAll();
    foreach ($guests as $g) {
        notice_add(
            $venue,
            $g,
            'È pronto il menù ' . ($meal === 'pranzo' ? 'del pranzo' : 'della cena') . ' di ' . day_it($day, 'rel'),
            'Guardalo e rispondi entro le ' . $limit . (!empty($venue['settings']['auto_cancel']) ? ': dopo, il pasto viene disdetto.' : '.'),
            'menu.php?g=' . $day . '&p=' . $meal
        );
    }
    return count($guests);
}

/** Chiude i menù arrivati all'orario limite e, se previsto, disdice chi non ha risposto. */
function menus_close_due(?int $venueId): void
{
    $sql = 'SELECT * FROM ' . t('menus') . " WHERE status = 'pubblicato' AND day <= ?" . ($venueId !== null ? ' AND venue_id = ?' : '');
    $rows = q($sql, $venueId !== null ? [today(), $venueId] : [today()])->fetchAll();
    foreach ($rows as $m) {
        $venue = venue_get((int) $m['venue_id']);
        if (!$venue || time() < meal_cutoff($venue, (string) $m['day'], (string) $m['meal'], $m)) {
            continue;
        }
        if (q('UPDATE ' . t('menus') . " SET status = 'chiuso', closed_at = ? WHERE id = ? AND status = 'pubblicato'", [now(), $m['id']])->rowCount() !== 1) {
            continue;   // già chiuso da un'altra richiesta
        }
        menu_get((int) $venue['id'], (string) $m['day'], (string) $m['meal'], true);
        if (empty($venue['settings']['auto_cancel'])) {
            continue;
        }
        $late = q(
            'SELECT g.*, b.id AS booking_id FROM ' . t('bookings') . ' b JOIN ' . t('guests') . " g ON g.id = b.guest_id
             WHERE b.venue_id = ? AND b.day = ? AND b.meal = ? AND b.status = 'prenotato'",
            [$venue['id'], $m['day'], $m['meal']]
        )->fetchAll();
        foreach ($late as $g) {
            q('UPDATE ' . t('bookings') . " SET status = 'scaduto', updated_at = ? WHERE id = ? AND status = 'prenotato'", [now(), $g['booking_id']]);
            notice_add(
                $venue,
                $g,
                ($m['meal'] === 'pranzo' ? 'Pranzo' : 'Cena') . ' di ' . day_it((string) $m['day'], 'rel') . ' disdetto',
                'Non hai risposto entro l\'orario. Se vuoi mangiare, chiama la reception.',
                'pasti.php'
            );
        }
        if ($late) {
            pf_event((int) $venue['id'], 'disdette_automatiche', PF_MEALS[$m['meal']] . ' ' . $m['day'] . ': ' . count($late), 'sistema');
        }
    }
}

/** Allinea il locale all'ora corrente (una sola volta per richiesta). */
function venue_sync(array $venue): void
{
    static $done = [];
    if (!isset($done[$venue['id']])) {
        $done[$venue['id']] = true;
        menus_close_due((int) $venue['id']);
    }
}

/* =====================================================================
 * Prenotazioni dei pasti
 * ===================================================================== */

function booking_get(int $guestId, string $day, string $meal): ?array
{
    return q_one('SELECT * FROM ' . t('bookings') . ' WHERE guest_id = ? AND day = ? AND meal = ?', [$guestId, $day, $meal]);
}

/** Prenotazioni dell'ospite nel periodo, con chiave "giorno|pasto". */
function bookings_of_guest(int $guestId, string $from, string $to): array
{
    $out = [];
    foreach (q('SELECT * FROM ' . t('bookings') . ' WHERE guest_id = ? AND day BETWEEN ? AND ? ORDER BY day, meal DESC', [$guestId, $from, $to])->fetchAll() as $b) {
        $out[$b['day'] . '|' . $b['meal']] = $b;
    }
    return $out;
}

function booking_active(?array $b): bool
{
    return $b !== null && in_array($b['status'], ['prenotato', 'confermato'], true);
}

/**
 * Segna o toglie la presenza a un pasto.
 * Esiti: 'ok' fatto · 'same' nessun cambiamento · 'richiesta' serve l'approvazione (orario scaduto) · 'no' non consentito.
 */
function booking_toggle(array $venue, array $guest, string $day, string $meal, bool $on, bool $byStaff = false): string
{
    if (!valid_day($day) || !isset(PF_MEALS[$meal])) {
        return 'no';
    }
    $b = booking_get((int) $guest['id'], $day, $meal);
    if ($on === booking_active($b)) {
        return 'same';
    }
    $menu = menu_get((int) $venue['id'], $day, $meal);
    $open = meal_open($venue, $day, $meal, $menu);
    if (!$byStaff) {
        if ($day < today() || $day > day_add(today(), (int) $venue['settings']['horizon']) || !meal_served($venue, $day, $meal)) {
            return 'no';
        }
        if (!$open) {
            $kind = $on ? 'aggiunta' : 'disdetta';
            $pending = q_value(
                'SELECT id FROM ' . t('requests') . " WHERE guest_id = ? AND day = ? AND meal = ? AND kind = ? AND status = 'attesa'",
                [$guest['id'], $day, $meal, $kind]
            );
            if (!$pending) {
                request_add((int) $venue['id'], (int) $guest['id'], $kind, $b ? (int) $b['id'] : null, $day, $meal, '');
            }
            return 'richiesta';
        }
    }
    if ($on) {
        // La reception che aggiunge un pasto a menù già uscito lo conferma direttamente.
        $status = $byStaff && (!$open || ($menu && $menu['status'] !== 'bozza')) ? 'confermato' : 'prenotato';
        booking_write((int) $venue['id'], (int) $guest['id'], $day, $meal, $status, $byStaff ? 'staff' : 'ospite');
    } else {
        q('UPDATE ' . t('bookings') . " SET status = 'disdetto', updated_at = ? WHERE id = ?", [now(), $b['id']]);
        q('UPDATE ' . t('requests') . " SET status = 'rifiutata', staff_note = 'Pasto disdetto', handled_at = ? WHERE booking_id = ? AND status = 'attesa'", [now(), $b['id']]);
    }
    return 'ok';
}

/** Crea o riporta in vita la prenotazione (azzera scelte e varianti). */
function booking_write(int $venueId, int $guestId, string $day, string $meal, string $status, string $source): int
{
    q(
        'INSERT INTO ' . t('bookings') . " (venue_id, guest_id, day, meal, status, choices, source, created_at, updated_at) VALUES (?, ?, ?, ?, ?, '', ?, ?, ?)
         ON DUPLICATE KEY UPDATE status = VALUES(status), choices = '', amount = NULL, variant = '', variant_status = '', answered_at = NULL, source = VALUES(source), updated_at = VALUES(updated_at)",
        [$venueId, $guestId, $day, $meal, $status, $source, now(), now()]
    );
    return (int) q_value('SELECT id FROM ' . t('bookings') . ' WHERE guest_id = ? AND day = ? AND meal = ?', [$guestId, $day, $meal]);
}

/** Il locale usa le formule a prezzo fisso per questo menù? */
function menu_uses_formulas(array $venue, array $menu): bool
{
    return $menu['mode'] === 'scelta' && feature('formule', $venue) && $venue['settings']['price_mode'] === 'formule' && count($menu['data']['formulas']) > 0;
}

/** Portate del menù che hanno almeno un piatto. */
function menu_courses(array $menu): array
{
    return array_keys(array_filter($menu['data']['courses'], static fn ($list) => is_array($list) && count($list) > 0));
}

function find_by_id(array $list, string $id): ?array
{
    foreach ($list as $item) {
        if (is_array($item) && (string) ($item['id'] ?? '') === $id) {
            return $item;
        }
    }
    return null;
}

/**
 * Legge e controlla le scelte dell'ospite ($in: formula, path, dish[portata], drink, extras[]).
 * Restituisce [scelte, null] oppure [null, messaggio d'errore].
 */
function choices_read(array $venue, array $menu, array $in): array
{
    $data = $menu['data'];
    $c = ['formula' => null, 'path' => 0, 'dishes' => [], 'drink' => null, 'extras' => []];
    if ($menu['mode'] !== 'scelta') {
        return [$c, null];
    }
    $courses = menu_courses($menu);
    $need = $courses;
    $required = false;
    if (menu_uses_formulas($venue, $menu)) {
        $f = find_by_id($data['formulas'], (string) ($in['formula'] ?? ''));
        if (!$f) {
            return [null, 'Scegli la formula.'];
        }
        $paths = pf_formula_types()[(string) ($f['type'] ?? 'completo')][1] ?? [['primo', 'secondo', 'contorno']];
        $pi = count($paths) > 1 ? (int) ($in['path'] ?? -1) : 0;
        if (!isset($paths[$pi])) {
            return [null, 'Indica se prendi il primo oppure secondo e contorno.'];
        }
        $c['formula'] = ['id' => (string) $f['id'], 'name' => (string) $f['name'], 'price' => (float) ($f['price'] ?? 0)];
        $c['path'] = $pi;
        $need = array_values(array_intersect($paths[$pi], $courses));
        $required = true;
    }
    $dish = is_array($in['dish'] ?? null) ? $in['dish'] : [];
    foreach ($need as $course) {
        $d = find_by_id($data['courses'][$course], (string) ($dish[$course] ?? ''));
        if ($d) {
            $c['dishes'][$course] = ['id' => (string) $d['id'], 'name' => (string) $d['name'], 'price' => (float) ($d['price'] ?? 0)];
        } elseif ($required) {
            return [null, 'Scegli un piatto per: ' . mb_strtolower(PF_COURSE_ONE[$course]) . '.'];
        }
    }
    if (!$c['dishes'] && $courses) {
        return [null, 'Scegli almeno un piatto.'];
    }
    if ($d = find_by_id($data['drinks'], (string) ($in['drink'] ?? ''))) {
        $c['drink'] = ['id' => (string) $d['id'], 'label' => (string) $d['label'], 'price' => (float) ($d['price'] ?? 0)];
    }
    foreach ((array) ($in['extras'] ?? []) as $id) {
        if (is_string($id) && ($x = find_by_id($data['extras'], $id))) {
            $c['extras'][] = ['id' => (string) $x['id'], 'label' => (string) $x['label'], 'price' => (float) ($x['price'] ?? 0)];
        }
    }
    return [$c, null];
}

/** Importo del pasto secondo il modo di prezzo del locale (null se il locale non usa prezzi). */
function choices_amount(array $venue, array $c): ?float
{
    $mode = (string) $venue['settings']['price_mode'];
    if ($mode === 'nessuno') {
        return null;
    }
    $sum = 0.0;
    if ($mode === 'formule' && $c['formula']) {
        $sum += (float) $c['formula']['price'];
    } else {
        foreach ($c['dishes'] as $d) {
            $sum += (float) $d['price'];
        }
    }
    $sum += (float) ($c['drink']['price'] ?? 0);
    foreach ($c['extras'] as $x) {
        $sum += (float) $x['price'];
    }
    return round($sum, 2);
}

function choices_decode(?string $json): array
{
    $c = json_decode((string) $json, true);
    return is_array($c) ? $c + ['formula' => null, 'path' => 0, 'dishes' => [], 'drink' => null, 'extras' => []] : ['formula' => null, 'path' => 0, 'dishes' => [], 'drink' => null, 'extras' => []];
}

/** Riassunto leggibile delle scelte: "Penne all'arrabbiata · Patate al forno · Caffè". */
function choices_text(array $c): string
{
    $parts = [];
    foreach (array_keys(PF_COURSES) as $course) {
        if (!empty($c['dishes'][$course]['name'])) {
            $parts[] = (string) $c['dishes'][$course]['name'];
        }
    }
    if (!empty($c['drink']['label'])) {
        $parts[] = (string) $c['drink']['label'];
    }
    foreach ((array) $c['extras'] as $x) {
        $parts[] = (string) $x['label'];
    }
    return implode(' · ', $parts);
}

/**
 * L'ospite conferma il pasto con le sue scelte. Restituisce null oppure il messaggio d'errore.
 * $variant: testo della variante richiesta (facoltativo), da approvare.
 */
function booking_answer(array $venue, array $guest, array $menu, array $in, string $variant = ''): ?string
{
    if ($menu['status'] !== 'pubblicato' || !meal_open($venue, (string) $menu['day'], (string) $menu['meal'], $menu)) {
        return 'L\'orario per rispondere è passato. Per cambiare chiama la reception.';
    }
    [$c, $err] = choices_read($venue, $menu, $in);
    if ($err !== null) {
        return $err;
    }
    $variant = feature('varianti', $venue) ? clean_text($variant, 500) : '';
    $id = booking_write((int) $venue['id'], (int) $guest['id'], (string) $menu['day'], (string) $menu['meal'], 'confermato', 'ospite');
    q(
        'UPDATE ' . t('bookings') . ' SET choices = ?, amount = ?, variant = ?, variant_status = ?, answered_at = ? WHERE id = ?',
        [json_encode($c, JSON_UNESCAPED_UNICODE), choices_amount($venue, $c), $variant, $variant !== '' ? 'attesa' : '', now(), $id]
    );
    q('UPDATE ' . t('requests') . " SET status = 'rifiutata', staff_note = 'Sostituita da una nuova scelta', handled_at = ? WHERE booking_id = ? AND kind = 'variante' AND status = 'attesa'", [now(), $id]);
    if ($variant !== '') {
        request_add((int) $venue['id'], (int) $guest['id'], 'variante', $id, (string) $menu['day'], (string) $menu['meal'], $variant);
    }
    return null;
}

/**
 * Dati per la cucina: totali per piatto e presenti divisi per società.
 * Contano solo gli ospiti confermati dalla reception.
 */
function kitchen_data(array $venue, string $day, string $meal): array
{
    $rows = q(
        'SELECT b.*, g.first_name, g.last_name, g.phone, g.diets, g.needs, g.kitchen_note, c.name AS company, c.note AS company_note
         FROM ' . t('bookings') . ' b JOIN ' . t('guests') . ' g ON g.id = b.guest_id
         LEFT JOIN ' . t('companies') . " c ON c.id = g.company_id
         WHERE b.venue_id = ? AND b.day = ? AND b.meal = ? AND b.status IN ('prenotato', 'confermato') AND g.status = 'confermato'
         ORDER BY (c.name IS NULL), c.name, g.last_name, g.first_name",
        [$venue['id'], $day, $meal]
    )->fetchAll();
    $totals = [];
    $groups = [];
    $count = ['total' => 0, 'confirmed' => 0, 'waiting' => 0, 'diets' => 0, 'variants' => 0];
    $bump = static function (array &$totals, string $section, string $name): void {
        $totals[$section][$name] = ($totals[$section][$name] ?? 0) + 1;
    };
    foreach ($rows as $r) {
        $c = choices_decode((string) $r['choices']);
        $tags = guest_tags($r);
        $count['total']++;
        $count[$r['status'] === 'confermato' ? 'confirmed' : 'waiting']++;
        if (array_filter($tags, static fn ($t) => $t['type'] === 'diet')) {
            $count['diets']++;
        }
        if ($r['variant'] !== '' && $r['variant_status'] !== 'rifiutata') {
            $count['variants']++;
        }
        foreach (PF_COURSES as $course => $label) {
            if (!empty($c['dishes'][$course]['name'])) {
                $bump($totals, $label, (string) $c['dishes'][$course]['name']);
            }
        }
        if (!empty($c['drink']['label']) && (float) ($c['drink']['price'] ?? 0) > 0) {
            $bump($totals, 'Bevande', (string) $c['drink']['label']);
        }
        foreach ((array) $c['extras'] as $x) {
            $bump($totals, 'Per finire', (string) $x['label']);
        }
        $key = (string) ($r['company'] ?? '');
        $groups[$key] ??= ['company' => $key !== '' ? $key : 'Senza società', 'note' => (string) ($r['company_note'] ?? ''), 'people' => []];
        $groups[$key]['people'][] = [
            'booking_id' => (int) $r['id'], 'guest_id' => (int) $r['guest_id'], 'name' => guest_name($r), 'phone' => (string) $r['phone'],
            'status' => (string) $r['status'], 'tags' => $tags, 'choice' => choices_text($c), 'formula' => (string) ($c['formula']['name'] ?? ''),
            'variant' => (string) $r['variant'], 'variant_status' => (string) $r['variant_status'], 'wa_sent' => $r['wa_sent_at'] !== null,
        ];
    }
    $order = array_merge(array_values(PF_COURSES), ['Bevande', 'Per finire']);
    $sorted = [];
    foreach ($order as $section) {
        if (!empty($totals[$section])) {
            arsort($totals[$section]);
            $sorted[$section] = $totals[$section];
        }
    }
    return ['totals' => $sorted, 'groups' => array_values($groups), 'count' => $count];
}

/* =====================================================================
 * Richieste da approvare
 * ===================================================================== */

function request_add(int $venueId, int $guestId, string $kind, ?int $bookingId, ?string $day, string $meal, string $body): int
{
    q(
        'INSERT INTO ' . t('requests') . ' (venue_id, guest_id, kind, booking_id, day, meal, body, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$venueId, $guestId, $kind, $bookingId, $day, $meal, mb_substr($body, 0, 500), now()]
    );
    return (int) db()->lastInsertId();
}

function requests_pending_count(int $venueId): int
{
    return (int) q_value('SELECT COUNT(*) FROM ' . t('requests') . " WHERE venue_id = ? AND status = 'attesa'", [$venueId]);
}

function requests_list(int $venueId, string $status = 'attesa', int $limit = 100): array
{
    $where = $status === 'attesa' ? "r.status = 'attesa'" : "r.status <> 'attesa'";
    return q(
        'SELECT r.*, g.first_name, g.last_name, g.phone, c.name AS company, s.name AS staff_name
         FROM ' . t('requests') . ' r JOIN ' . t('guests') . ' g ON g.id = r.guest_id
         LEFT JOIN ' . t('companies') . ' c ON c.id = g.company_id LEFT JOIN ' . t('staff') . ' s ON s.id = r.handled_by
         WHERE r.venue_id = ? AND ' . $where . ' ORDER BY ' . ($status === 'attesa' ? 'r.day, r.created_at' : 'r.handled_at DESC') . ' LIMIT ' . max(1, $limit),
        [$venueId]
    )->fetchAll();
}

function request_kinds(): array
{
    return ['disdetta' => 'Disdetta', 'aggiunta' => 'Aggiunta', 'variante' => 'Variante', 'notte' => 'Notte'];
}

/** Frase che descrive la richiesta: "disdice la cena di mer 25 nov". */
function request_text(array $r): string
{
    $when = $r['day'] ? day_it((string) $r['day'], 'short') : '';
    $meal = $r['meal'] === 'pranzo' ? 'il pranzo' : ($r['meal'] === 'cena' ? 'la cena' : '');
    return match ((string) $r['kind']) {
        'disdetta' => 'disdice ' . $meal . ' di ' . $when . ' dopo l\'orario limite',
        'aggiunta' => 'chiede di aggiungere ' . $meal . ' di ' . $when . ' dopo l\'orario limite',
        'variante' => ($meal !== '' ? mb_substr($meal, 3) : 'pasto') . ' di ' . $when . ': ' . $r['body'],
        'notte' => 'disdice la notte di ' . $when,
        default => (string) $r['body'],
    };
}

/** Approva o rifiuta una richiesta, applica l'effetto e avvisa l'ospite. */
function request_handle(array $venue, int $id, bool $approve, string $note, int $staffId): bool
{
    $r = q_one('SELECT * FROM ' . t('requests') . " WHERE id = ? AND venue_id = ? AND status = 'attesa'", [$id, $venue['id']]);
    if (!$r) {
        return false;
    }
    q(
        'UPDATE ' . t('requests') . ' SET status = ?, staff_note = ?, handled_by = ?, handled_at = ? WHERE id = ?',
        [$approve ? 'approvata' : 'rifiutata', clean_text($note, 255), $staffId, now(), $id]
    );
    $guest = guest_get((int) $venue['id'], (int) $r['guest_id']);
    $day = (string) $r['day'];
    $meal = (string) $r['meal'];
    $what = ($meal === 'pranzo' ? 'pranzo' : 'cena') . ' di ' . day_it($day, 'rel');
    $title = '';
    switch ((string) $r['kind']) {
        case 'variante':
            q('UPDATE ' . t('bookings') . ' SET variant_status = ?, updated_at = ? WHERE id = ?', [$approve ? 'approvata' : 'rifiutata', now(), $r['booking_id']]);
            $title = 'Variante ' . ($approve ? 'approvata' : 'non disponibile') . ': ' . $what;
            break;
        case 'disdetta':
            if ($approve) {
                q('UPDATE ' . t('bookings') . " SET status = 'disdetto', updated_at = ? WHERE guest_id = ? AND day = ? AND meal = ?", [now(), $r['guest_id'], $day, $meal]);
            }
            $title = 'Disdetta ' . ($approve ? 'accettata' : 'non accettata') . ': ' . $what;
            break;
        case 'aggiunta':
            if ($approve) {
                booking_write((int) $venue['id'], (int) $r['guest_id'], $day, $meal, 'confermato', 'staff');
            }
            $title = ($approve ? 'Ti aspettiamo: ' : 'Non riusciamo ad aggiungere: ') . $what;
            break;
        case 'notte':
            if ($approve) {
                q('UPDATE ' . t('nights') . " SET status = 'disdetta', updated_at = ? WHERE guest_id = ? AND day = ?", [now(), $r['guest_id'], $day]);
            }
            $title = 'Disdetta della notte di ' . day_it($day, 'rel') . ($approve ? ' accettata' : ' non accettata');
            break;
    }
    if ($guest && $title !== '') {
        notice_add($venue, $guest, $title, $approve ? '' : 'Per chiarimenti chiama la reception.', 'pasti.php');
    }
    return true;
}

/* =====================================================================
 * Avvisi agli ospiti
 * ===================================================================== */

/** Avviso nell'area ospiti e, se attivo e l'ospite ha un'email, anche per posta. */
function notice_add(array $venue, array $guest, string $title, string $body = '', string $href = ''): void
{
    q(
        'INSERT INTO ' . t('notices') . ' (venue_id, guest_id, title, body, href, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$venue['id'], $guest['id'], mb_substr($title, 0, 160), mb_substr($body, 0, 500), mb_substr($href, 0, 190), now()]
    );
    if (feature('email', $venue) && (string) ($guest['email'] ?? '') !== '') {
        $text = 'Ciao ' . ($guest['first_name'] ?? '') . ",\n\n" . $title . ($body !== '' ? "\n" . $body : '')
            . "\n\nApri la tua area: " . guest_link($venue) . "\n\n" . $venue['name'];
        pf_mail_queue((int) $venue['id'], (string) $guest['email'], $venue['name'] . ': ' . $title, $text);
    }
}

function notices_unread(int $guestId): int
{
    return (int) q_value('SELECT COUNT(*) FROM ' . t('notices') . ' WHERE guest_id = ? AND read_at IS NULL', [$guestId]);
}

/* =====================================================================
 * Notti e camere (modulo Hotel)
 * ===================================================================== */

function nights_of_guest(int $guestId, string $from, string $to): array
{
    $out = [];
    foreach (q('SELECT n.*, r.name AS room FROM ' . t('nights') . ' n LEFT JOIN ' . t('rooms') . ' r ON r.id = n.room_id WHERE n.guest_id = ? AND n.day BETWEEN ? AND ?', [$guestId, $from, $to])->fetchAll() as $n) {
        $out[$n['day']] = $n;
    }
    return $out;
}

/**
 * L'ospite chiede o toglie una notte. Le notti già confermate si disdicono solo con l'approvazione della reception.
 * Esiti: 'ok' · 'same' · 'richiesta' · 'no'.
 */
function night_toggle(array $venue, array $guest, string $day, bool $on, bool $byStaff = false): string
{
    if (!valid_day($day) || (!$byStaff && ($day < today() || $day > day_add(today(), (int) $venue['settings']['horizon'])))) {
        return 'no';
    }
    $n = q_one('SELECT * FROM ' . t('nights') . ' WHERE guest_id = ? AND day = ?', [$guest['id'], $day]);
    $active = $n && $n['status'] !== 'disdetta';
    if ($on === $active) {
        return 'same';
    }
    if ($on) {
        q(
            'INSERT INTO ' . t('nights') . " (venue_id, guest_id, day, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), room_id = NULL, updated_at = VALUES(updated_at)",
            [$venue['id'], $guest['id'], $day, $byStaff ? 'confermata' : 'richiesta', now(), now()]
        );
        return 'ok';
    }
    if ($n['status'] === 'confermata' && !$byStaff) {
        $pending = q_value('SELECT id FROM ' . t('requests') . " WHERE guest_id = ? AND day = ? AND kind = 'notte' AND status = 'attesa'", [$guest['id'], $day]);
        if (!$pending) {
            request_add((int) $venue['id'], (int) $guest['id'], 'notte', null, $day, '', '');
        }
        return 'richiesta';
    }
    q('DELETE FROM ' . t('nights') . ' WHERE id = ?', [$n['id']]);
    return 'ok';
}

function rooms_all(int $venueId, bool $activeOnly = true): array
{
    $rows = q('SELECT * FROM ' . t('rooms') . ' WHERE venue_id = ?' . ($activeOnly ? ' AND active = 1' : ''), [$venueId])->fetchAll();
    usort($rows, static fn ($a, $b) => strnatcasecmp((string) $a['name'], (string) $b['name']));
    return $rows;
}

/** Conferma le notti richieste dell'ospite (da oggi in poi) e assegna la camera. */
function nights_assign(int $venueId, int $guestId, ?int $roomId): int
{
    if ($roomId !== null && !q_value('SELECT 1 FROM ' . t('rooms') . ' WHERE id = ? AND venue_id = ?', [$roomId, $venueId])) {
        $roomId = null;
    }
    return q(
        'UPDATE ' . t('nights') . " SET status = 'confermata', room_id = ?, updated_at = ? WHERE venue_id = ? AND guest_id = ? AND day >= ? AND status <> 'disdetta'",
        [$roomId, now(), $venueId, $guestId, today()]
    )->rowCount();
}

/** Riassunto delle notti di un ospite da oggi: ['from' => , 'to' => , 'count' => , 'room_id' => ]. */
function nights_summary(int $guestId): array
{
    $rows = q('SELECT day, room_id FROM ' . t('nights') . " WHERE guest_id = ? AND day >= ? AND status <> 'disdetta' ORDER BY day", [$guestId, today()])->fetchAll();
    return ['from' => $rows[0]['day'] ?? null, 'to' => $rows ? $rows[count($rows) - 1]['day'] : null, 'count' => count($rows), 'room_id' => $rows[0]['room_id'] ?? null];
}

/** Occupazione: [id camera (0 = da assegnare)][giorno] => elenco di ospiti. */
function room_occupancy(int $venueId, string $from, string $to): array
{
    $out = [];
    $rows = q(
        'SELECT n.day, n.room_id, n.status, g.id AS guest_id, g.first_name, g.last_name FROM ' . t('nights') . ' n JOIN ' . t('guests') . " g ON g.id = n.guest_id
         WHERE n.venue_id = ? AND n.day BETWEEN ? AND ? AND n.status <> 'disdetta' ORDER BY g.last_name",
        [$venueId, $from, $to]
    )->fetchAll();
    foreach ($rows as $r) {
        $out[(int) ($r['room_id'] ?? 0)][$r['day']][] = $r;
    }
    return $out;
}

/* =====================================================================
 * Numeri e riepiloghi
 * ===================================================================== */

/** Clienti del mese: ospiti con almeno un pasto prenotato e non disdetto (è la misura dei piani). */
function venue_usage(int $venueId, ?string $ym = null): int
{
    $ym ??= date('Y-m');
    return (int) q_value(
        'SELECT COUNT(DISTINCT guest_id) FROM ' . t('bookings') . " WHERE venue_id = ? AND day BETWEEN ? AND ? AND status IN ('prenotato', 'confermato')",
        [$venueId, $ym . '-01', date('Y-m-t', (int) strtotime($ym . '-01'))]
    );
}

/** Numeri della giornata per il cruscotto. */
function day_stats(array $venue, string $day): array
{
    $id = (int) $venue['id'];
    $meal = static function (string $meal) use ($id, $day): array {
        $r = q_one(
            'SELECT COALESCE(SUM(b.status = \'confermato\'), 0) AS ok, COALESCE(SUM(b.status = \'prenotato\'), 0) AS wait FROM ' . t('bookings') . ' b
             JOIN ' . t('guests') . " g ON g.id = b.guest_id WHERE b.venue_id = ? AND b.day = ? AND b.meal = ? AND g.status = 'confermato'",
            [$id, $day, $meal]
        );
        return ['ok' => (int) ($r['ok'] ?? 0), 'wait' => (int) ($r['wait'] ?? 0)];
    };
    $night = q_one(
        'SELECT COUNT(*) AS guests, COUNT(DISTINCT n.room_id) AS rooms, COUNT(DISTINCT g.company_id) AS companies FROM ' . t('nights') . ' n
         JOIN ' . t('guests') . " g ON g.id = n.guest_id WHERE n.venue_id = ? AND n.day = ? AND n.status <> 'disdetta' AND g.status = 'confermato'",
        [$id, $day]
    );
    return [
        'pranzo' => $meal('pranzo'), 'cena' => $meal('cena'),
        'nights' => ['guests' => (int) ($night['guests'] ?? 0), 'rooms' => (int) ($night['rooms'] ?? 0), 'companies' => (int) ($night['companies'] ?? 0)],
        'pending' => guests_pending_count($id), 'requests' => requests_pending_count($id),
    ];
}

/**
 * Riepilogo del mese per società e per ospite.
 * Contano i pasti confermati e quelli prenotati non disdetti, fino a oggi compreso.
 */
function report_month(int $venueId, string $ym): array
{
    $from = $ym . '-01';
    $to = min(date('Y-m-t', (int) strtotime($from)), today());
    $rows = q(
        'SELECT g.id AS guest_id, g.first_name, g.last_name, c.id AS company_id, c.name AS company,
                SUM(b.meal = \'pranzo\') AS pranzi, SUM(b.meal = \'cena\') AS cene, COUNT(*) AS pasti, COALESCE(SUM(b.amount), 0) AS totale
         FROM ' . t('bookings') . ' b JOIN ' . t('guests') . ' g ON g.id = b.guest_id LEFT JOIN ' . t('companies') . " c ON c.id = g.company_id
         WHERE b.venue_id = ? AND b.day BETWEEN ? AND ? AND b.status IN ('prenotato', 'confermato') AND g.status = 'confermato'
         GROUP BY g.id, g.first_name, g.last_name, c.id, c.name ORDER BY (c.name IS NULL), c.name, g.last_name, g.first_name",
        [$venueId, $from, $to]
    )->fetchAll();
    $nights = [];
    foreach (q('SELECT guest_id, COUNT(*) AS n FROM ' . t('nights') . " WHERE venue_id = ? AND day BETWEEN ? AND ? AND status = 'confermata' GROUP BY guest_id", [$venueId, $from, $to])->fetchAll() as $n) {
        $nights[(int) $n['guest_id']] = (int) $n['n'];
    }
    $companies = [];
    $grand = ['pranzi' => 0, 'cene' => 0, 'pasti' => 0, 'notti' => 0, 'totale' => 0.0, 'ospiti' => 0];
    foreach ($rows as $r) {
        $key = (int) ($r['company_id'] ?? 0);
        $companies[$key] ??= ['id' => $key, 'name' => $r['company'] ?? 'Senza società', 'pranzi' => 0, 'cene' => 0, 'pasti' => 0, 'notti' => 0, 'totale' => 0.0, 'guests' => []];
        $g = [
            'id' => (int) $r['guest_id'], 'name' => guest_name($r), 'pranzi' => (int) $r['pranzi'], 'cene' => (int) $r['cene'],
            'pasti' => (int) $r['pasti'], 'notti' => $nights[(int) $r['guest_id']] ?? 0, 'totale' => (float) $r['totale'],
        ];
        $companies[$key]['guests'][] = $g;
        foreach (['pranzi', 'cene', 'pasti', 'notti', 'totale'] as $k) {
            $companies[$key][$k] += $g[$k];
            $grand[$k] += $g[$k];
        }
        $grand['ospiti']++;
    }
    return ['from' => $from, 'to' => $to, 'companies' => array_values($companies), 'total' => $grand];
}

/* =====================================================================
 * Immagini caricate dallo staff (foto del menù)
 * ===================================================================== */

/**
 * Salva un'immagine in uploads/ con nome casuale. Controlla il tipo reale, la ricodifica
 * (via i metadati) e la riduce a 1600 px. Restituisce [nome file, null] oppure [null, errore].
 */
function pf_image_upload(array $file, string $tag = 'm'): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        return [null, ($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE ? 'La foto è troppo grande per questo server.' : 'Caricamento non riuscito. Riprova.'];
    }
    if ((int) $file['size'] > 8 * 1024 * 1024) {
        return [null, 'La foto supera gli 8 MB.'];
    }
    $info = @getimagesize((string) $file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]])) {
        return [null, 'Servono immagini JPG, PNG o WebP.'];
    }
    $dir = CM_ROOT . '/uploads';
    if (!is_dir($dir) || !is_writable($dir)) {
        return [null, 'La cartella delle immagini non è scrivibile.'];
    }
    $name = $tag . '-' . bin2hex(random_bytes(10));
    if (function_exists('imagecreatefromstring') && ($src = @imagecreatefromstring((string) file_get_contents((string) $file['tmp_name'])))) {
        [$w, $h] = [imagesx($src), imagesy($src)];
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $o = (int) (@exif_read_data((string) $file['tmp_name'])['Orientation'] ?? 1);
            $deg = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
            if ($deg !== 0 && ($rot = imagerotate($src, $deg, 0))) {
                $src = $rot;
                [$w, $h] = [imagesx($src), imagesy($src)];
            }
        }
        $scale = min(1, 1600 / max($w, $h));
        $dst = imagecreatetruecolor(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
        imagefill($dst, 0, 0, (int) imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
        $ok = imagejpeg($dst, $dir . '/' . $name . '.jpg', 84);
        return $ok ? [$name . '.jpg', null] : [null, 'Salvataggio della foto non riuscito.'];
    }
    $full = $name . '.' . $types[$info[2]];
    return move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $full) ? [$full, null] : [null, 'Salvataggio della foto non riuscito.'];
}

function pf_image_delete(string $name): void
{
    if (preg_match('/^[a-z0-9-]+\.(jpg|png|webp)$/', $name)) {
        @unlink(CM_ROOT . '/uploads/' . $name);
    }
}

/* =====================================================================
 * Email di servizio per lo staff
 * ===================================================================== */

/** Invia al titolare il collegamento per confermare l'email (valido 7 giorni). */
function venue_verify_send(int $venueId, string $email, string $name): void
{
    $token = pf_token_create('verifica', $venueId, 7 * 86400);
    pf_mail_queue(
        $venueId,
        $email,
        'Conferma la tua email · ChiMangia',
        "Ciao $name,\n\ngrazie per aver provato ChiMangia. Per confermare questa email apri il collegamento:\n"
        . site_url('accedi/verifica.php?t=' . $token) . "\n\nIl collegamento vale 7 giorni. Se non hai creato tu l'account, ignora questo messaggio.\n\nChiMangia"
    );
}

/** Invia allo staff il collegamento per scegliere una nuova password (valido un'ora). */
function staff_reset_send(array $staff): void
{
    $token = pf_token_create('staff_reset', (int) $staff['id'], 3600);
    pf_mail_queue(
        (int) $staff['venue_id'],
        (string) $staff['email'],
        'Nuova password · ChiMangia',
        'Ciao ' . $staff['name'] . ",\n\nper scegliere una nuova password apri il collegamento:\n" . site_url('accedi/reimposta.php?t=' . $token)
        . "\n\nVale un'ora e si può usare una sola volta. Se non l'hai chiesto tu, ignora questo messaggio.\n\nChiMangia"
    );
}
