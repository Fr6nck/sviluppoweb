<?php
/** Pannello del locale: parti condivise tra più pagine. */
declare(strict_types=1);

defined('CM_ROOT') || exit;

/** Testo dell'avviso WhatsApp per un pasto, dal modello nelle impostazioni. */
function wa_message(array $venue, string $day, string $meal): string
{
    return strtr((string) $venue['settings']['wa_text'], [
        '{pasto}' => $meal === 'pranzo' ? 'del pranzo' : 'della cena',
        '{giorno}' => day_it($day, 'rel'),
        '{orario}' => date('H:i', meal_cutoff($venue, $day, $meal)),
        '{link}' => guest_link($venue),
        '{locale}' => (string) $venue['name'],
    ]);
}

/** Registrazioni in attesa, con il riepilogo di notti e pasti richiesti e l'eventuale società simile. */
function pending_guests(array $venue, int $limit = 100): array
{
    $rows = q(
        'SELECT g.*, c.name AS company, c.verified AS company_verified FROM ' . t('guests') . ' g LEFT JOIN ' . t('companies') . " c ON c.id = g.company_id
         WHERE g.venue_id = ? AND g.status = 'attesa' ORDER BY g.created_at LIMIT " . max(1, $limit),
        [$venue['id']]
    )->fetchAll();
    foreach ($rows as &$r) {
        $m = q_one(
            "SELECT COALESCE(SUM(meal = 'pranzo'), 0) AS pranzi, COALESCE(SUM(meal = 'cena'), 0) AS cene FROM " . t('bookings') . " WHERE guest_id = ? AND day >= ? AND status IN ('prenotato', 'confermato')",
            [$r['id'], today()]
        );
        $r['pranzi'] = (int) $m['pranzi'];
        $r['cene'] = (int) $m['cene'];
        $r['nights'] = nights_summary((int) $r['id']);
        $r['similar'] = $r['company_id'] && !(int) $r['company_verified'] ? company_similar((int) $venue['id'], (string) $r['company'], (int) $r['company_id']) : null;
    }
    unset($r);
    return $rows;
}

/** Intervallo di date compatto: "29 nov", "29–30 nov", "30 nov – 2 dic". */
function range_short(string $from, string $to): string
{
    $f = strtotime($from);
    $t = strtotime($to);
    $m = static fn (int $ts): string => mb_substr(PF_MONTHS[(int) date('n', $ts)], 0, 3);
    if ($from === $to) {
        return date('j', $f) . ' ' . $m($f);
    }
    return date('n', $f) === date('n', $t) ? date('j', $f) . '–' . date('j', $t) . ' ' . $m($t) : date('j', $f) . ' ' . $m($f) . ' – ' . date('j', $t) . ' ' . $m($t);
}

/**
 * Tabella "Registrazioni da confermare" (cruscotto e pagina dedicata). $back: pagina a cui tornare dopo l'azione.
 * Poche colonne, perché nel cruscotto sta accanto alle richieste: società sotto il nome, notti e pasti insieme.
 */
function registrations_table(array $me, array $rows, string $back): string
{
    $v = $me['venue'];
    $hotel = feature('hotel', $v);
    $canRooms = $hotel && can('camere', $me);
    $rooms = $canRooms ? rooms_all((int) $v['id']) : [];
    $h = '<div class="table-wrap"><table class="table stack-sm reg-table"><thead><tr><th>Ospite</th><th>Chiede</th>'
        . (can('note', $me) ? '<th>Diete</th>' : '') . ($canRooms ? '<th>Camera</th>' : '') . '<th><span class="sr-only">Azioni</span></th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $id = (int) $r['id'];
        $h .= '<tr><td data-th="Ospite"><strong>' . e(guest_name($r)) . '</strong><span class="sub">' . e(phone_show((string) $r['phone'])) . '</span>';
        if (feature('societa', $v) && $r['company']) {
            $h .= '<span class="sub">' . icon('building', 14) . ' ' . e((string) $r['company']) . '</span>';
            if ($r['similar'] && can('societa', $me)) {
                $h .= '<span class="reg-similar"><span class="sub">Nuova · simile a «' . e((string) $r['similar']['name']) . '»</span>'
                    . ui_post_btn('registrazioni.php', ['azione' => 'unisci', 'da' => $r['company_id'], 'a' => $r['similar']['id'], 'back' => $back], 'Unisci') . '</span>';
            } elseif (!(int) $r['company_verified']) {
                $h .= '<span class="sub">Nuova società</span>';
            }
        }
        $h .= '</td><td data-th="Chiede">';
        $bits = [];
        if ($hotel && $r['nights']['count']) {
            $n = $r['nights'];
            $bits[] = '<div><span class="nowrap">' . e(range_short((string) $n['from'], (string) $n['to'])) . '</span><span class="sub">' . ($n['count'] === 1 ? '1 notte' : $n['count'] . ' notti') . '</span></div>';
        }
        $meals = array_filter([$r['pranzi'] ? $r['pranzi'] . ($r['pranzi'] === 1 ? ' pranzo' : ' pranzi') : '', $r['cene'] ? $r['cene'] . ($r['cene'] === 1 ? ' cena' : ' cene') : '']);
        if ($meals) {
            $bits[] = '<div class="nowrap">' . e(implode(' · ', $meals)) . '</div>';
        }
        $h .= $bits ? '<div class="reg-asks">' . implode('', $bits) . '</div>' : '<span class="muted">—</span>';
        $h .= '</td>';
        if (can('note', $me)) {
            $tags = ui_guest_tags(guest_tags($r));
            $h .= '<td data-th="Diete"><div class="tags">' . ($tags !== '' ? $tags : '<span class="muted">—</span>') . '</div></td>';
        }
        if ($canRooms) {
            $h .= '<td data-th="Camera"><label class="sr-only" for="cam' . $id . '">Camera di ' . e(guest_name($r)) . '</label><select id="cam' . $id . '" name="camera" form="conf' . $id . '"><option value="">—</option>';
            foreach ($rooms as $room) {
                $h .= '<option value="' . (int) $room['id'] . '"' . ((int) $r['nights']['room_id'] === (int) $room['id'] ? ' selected' : '') . '>' . e((string) $room['name']) . '</option>';
            }
            $h .= '</select></td>';
        }
        $h .= '<td class="actions reg-cell"><div class="reg-actions">'
            . '<form method="post" action="registrazioni.php" id="conf' . $id . '" class="inline-form">' . csrf_field()
            . '<input type="hidden" name="azione" value="conferma"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="back" value="' . e($back) . '">'
            . '<button type="submit" class="btn btn-small btn-dark">Conferma</button></form>'
            . '<a class="btn btn-small btn-line" href="ospite.php?id=' . $id . '">Modifica</a></div></td></tr>';
    }
    return $h . '</tbody></table></div>';
}

/** Scheda di una richiesta da approvare, con nota interna e pulsanti. */
function request_card(array $r, string $back): string
{
    $kind = request_kinds()[(string) $r['kind']] ?? 'Richiesta';
    return '<div class="req-item"><p class="req-kind">' . e($kind) . ($r['company'] ? ' · ' . e((string) $r['company']) : '') . '</p>'
        . '<p><strong>' . e(guest_name($r)) . '</strong> ' . e(request_text($r)) . '</p>'
        . '<form method="post" action="richieste.php" class="stack-s">' . csrf_field() . '<input type="hidden" name="id" value="' . (int) $r['id'] . '"><input type="hidden" name="back" value="' . e($back) . '">'
        . '<label class="sr-only" for="nota' . (int) $r['id'] . '">Nota interna (solo staff)</label>'
        . '<input type="text" id="nota' . (int) $r['id'] . '" name="nota" maxlength="255" placeholder="Nota interna, es. supplemento (solo staff)">'
        . '<div class="row"><button type="submit" name="esito" value="si" class="btn btn-small btn-zaff">Approva</button>'
        . '<button type="submit" name="esito" value="no" class="btn btn-small btn-line">Rifiuta</button></div></form></div>';
}

/** Pagina a cui tornare dopo un'azione: solo file del pannello. */
function back_target(string $default): string
{
    $b = post('back');
    return preg_match('#^[a-z_]+\.php(\?[A-Za-z0-9_=&%.-]*)?$#', $b) ? $b : $default;
}

/** Invia un file CSV adatto a Excel (separatore ";", BOM UTF-8). */
function csv_download(string $filename, array $header, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    $safe = static fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;   // niente formule nei fogli di calcolo
    fputcsv($out, $header, ';', '"', '');
    foreach ($rows as $row) {
        fputcsv($out, array_map($safe, $row), ';', '"', '');
    }
    fclose($out);
    exit;
}
