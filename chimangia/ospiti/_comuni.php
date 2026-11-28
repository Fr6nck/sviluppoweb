<?php
/**
 * Area ospiti — funzioni comuni alle pagine.
 * Il file non produce nulla se aperto direttamente.
 */
declare(strict_types=1);

defined('CM_ROOT') || exit;

/**
 * Il locale della visita quando l'ospite non è ancora entrato:
 * dal collegamento (?l=codice) oppure dall'ultima visita. Lo ricorda nella sessione.
 */
function g_venue_pick(): ?array
{
    $slug = mb_strtolower(get_param('l'));
    if ($slug === '') {
        $slug = (string) ($_SESSION['venue_slug'] ?? '');
    }
    $venue = venue_by_slug($slug);
    if ($venue) {
        $_SESSION['venue_slug'] = (string) $venue['slug'];
        $GLOBALS['pf_guest_venue'] = $venue;
    }
    return $venue;
}

/** Richieste dell'ospite ancora da approvare, con chiave "giorno|pasto|tipo". */
function g_pending(int $guestId): array
{
    $out = [];
    foreach (q('SELECT day, meal, kind FROM ' . t('requests') . " WHERE guest_id = ? AND status = 'attesa'", [$guestId])->fetchAll() as $r) {
        $out[$r['day'] . '|' . $r['meal'] . '|' . $r['kind']] = true;
    }
    return $out;
}

/**
 * Stato di un pasto visto dall'ospite.
 * Restituisce [etichetta, tono dell'etichetta, c'è da scegliere i piatti?].
 */
function g_state(array $venue, ?array $b, string $day, string $meal, array $pending): array
{
    if (isset($pending[$day . '|' . $meal . '|disdetta'])) {
        return ['Disdetta in attesa di approvazione', 'zaff', false];
    }
    if (isset($pending[$day . '|' . $meal . '|aggiunta'])) {
        return ['Aggiunta in attesa di approvazione', 'zaff', false];
    }
    $status = (string) ($b['status'] ?? '');
    if ($status === 'scaduto') {
        return ['Disdetto: nessuna risposta entro l\'orario', 'err', false];
    }
    if ($status === 'confermato') {
        $v = (string) ($b['variant_status'] ?? '');
        $extra = $v === 'attesa' ? ' · variante in attesa' : ($v === 'approvata' ? ' · variante approvata' : ($v === 'rifiutata' ? ' · variante non disponibile' : ''));
        return ['Confermato' . $extra, 'ok', false];
    }
    if ($status === 'prenotato') {
        $menu = menu_get((int) $venue['id'], $day, $meal);
        if ($menu && $menu['status'] === 'pubblicato' && meal_open($venue, $day, $meal, $menu)) {
            return ['Menù pronto: rispondi entro le ' . date('H:i', meal_cutoff($venue, $day, $meal, $menu)), 'zaff', true];
        }
        return ['Prenotato', 'soft', false];
    }
    return ['Non prenotato', '', false];
}

/** "Pranzo di oggi", "Cena di martedì 24 novembre". */
function g_meal_title(string $day, string $meal): string
{
    return PF_MEALS[$meal] . ' di ' . day_it($day, 'rel');
}

/** Riga di saluto: "Ciao Marco · Edil Rossi Costruzioni". */
function g_hello(array $me): string
{
    $company = $me['company_id'] ? (string) q_value('SELECT name FROM ' . t('companies') . ' WHERE id = ? AND venue_id = ?', [$me['company_id'], $me['venue_id']]) : '';
    return 'Ciao ' . $me['first_name'] . ($company !== '' ? ' · ' . $company : '');
}

/** Collegamento interno sicuro per gli avvisi (solo pagine di quest'area). */
function g_href(string $href): string
{
    return preg_match('/^[a-z]+\.php(\?[A-Za-z0-9_=&.-]*)?$/', $href) ? $href : '';
}

/** Campo "Società" con i suggerimenti mentre si scrive (solo società già verificate dal locale). */
function g_company_field(array $venue, string $value): string
{
    $names = q('SELECT name FROM ' . t('companies') . ' WHERE venue_id = ? AND verified = 1 ORDER BY name', [$venue['id']])->fetchAll(PDO::FETCH_COLUMN);
    $json = (string) json_encode(array_values(array_map('strval', $names)), JSON_UNESCAPED_UNICODE);
    return '<div class="field"><label for="f-company">Società per cui lavori</label>'
        . '<input type="text" id="f-company" name="company" value="' . e($value) . '" maxlength="160" autocomplete="off" placeholder="Scrivi le prime 3 lettere"'
        . ' data-suggest="' . e($json) . '" data-suggest-box="soc-box" data-suggest-note="soc-note" data-suggest-new aria-describedby="soc-hint">'
        . '<div class="suggest" id="soc-box" hidden></div>'
        . '<p class="hint" id="soc-note" hidden></p>'
        . '<p class="hint" id="soc-hint">Se non c\'è nell\'elenco scrivila per intero: la reception la verificherà.</p></div>';
}

/** Diete scelte nel modulo (solo testo). */
function g_post_diets(): array
{
    $in = $_POST['diets'] ?? [];
    return is_array($in) ? array_values(array_filter($in, 'is_string')) : [];
}

/** Prezzo per l'ospite: "12,50 €". */
function g_price(float $v): string
{
    return number_format($v, 2, ',', '.') . ' €';
}
