<?php
/** Richieste arrivate dal modulo contatti. */
declare(strict_types=1);

defined('CM_ROOT') || exit;

function lead_statuses(): array
{
    return ['nuova' => 'Nuova', 'contattata' => 'Contattata', 'prova' => 'In prova', 'cliente' => 'Cliente', 'chiusa' => 'Chiusa'];
}

function leads_count_new(): int
{
    static $n = null;
    if ($n === null) {
        try {
            $n = (int) q_value('SELECT COUNT(*) FROM ' . t('leads') . " WHERE status = 'nuova'");
        } catch (Throwable $e) {
            $n = 0;
        }
    }
    return $n;
}

function leads_latest(int $limit = 5): array
{
    return q('SELECT id, name, business, status, created_at FROM ' . t('leads') . ' ORDER BY id DESC LIMIT ' . max(1, min(50, $limit)))->fetchAll();
}

/**
 * Filtri dell'elenco: q (testo), stato, dal, al (Y-m-d).
 * @return array{0:string,1:array}
 */
function leads_where(array $f): array
{
    $w = [];
    $p = [];
    if (($f['q'] ?? '') !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $f['q']) . '%';
        $w[] = '(name LIKE ? OR business LIKE ? OR email LIKE ? OR phone LIKE ? OR message LIKE ?)';
        array_push($p, $like, $like, $like, $like, $like);
    }
    if (isset(lead_statuses()[$f['stato'] ?? ''])) {
        $w[] = 'status = ?';
        $p[] = $f['stato'];
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['dal'] ?? '')) {
        $w[] = 'created_at >= ?';
        $p[] = $f['dal'] . ' 00:00:00';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['al'] ?? '')) {
        $w[] = 'created_at <= ?';
        $p[] = $f['al'] . ' 23:59:59';
    }
    return [$w ? ' WHERE ' . implode(' AND ', $w) : '', $p];
}

function lead_get(int $id): ?array
{
    return q_one('SELECT * FROM ' . t('leads') . ' WHERE id = ?', [$id]);
}

function lead_history(int $id): array
{
    return q(
        'SELECT h.*, u.name AS user_name FROM ' . t('lead_history') . ' h LEFT JOIN ' . t('users') . ' u ON u.id = h.user_id
         WHERE h.lead_id = ? ORDER BY h.id DESC',
        [$id]
    )->fetchAll();
}

function lead_set_status(int $id, string $status, int $userId, string $note = ''): bool
{
    $lead = lead_get($id);
    if (!$lead || !isset(lead_statuses()[$status]) || $lead['status'] === $status) {
        return false;
    }
    q('UPDATE ' . t('leads') . ' SET status = ?, status_changed_at = ?, updated_at = ? WHERE id = ?', [$status, now(), now(), $id]);
    q(
        'INSERT INTO ' . t('lead_history') . ' (lead_id, user_id, from_status, to_status, note, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$id, $userId, $lead['status'], $status, mb_substr($note, 0, 500), now()]
    );
    return true;
}

function lead_delete(int $id): void
{
    q('DELETE FROM ' . t('lead_history') . ' WHERE lead_id = ?', [$id]);
    q('DELETE FROM ' . t('leads') . ' WHERE id = ?', [$id]);
}

/**
 * Privacy: elimina le richieste chiuse più vecchie dei mesi impostati.
 * Si esegue al massimo una volta al giorno (all'apertura del pannello).
 */
function leads_retention_cleanup(bool $force = false): int
{
    $months = (int) setting('retention_months', 12);
    if ($months <= 0) {
        return 0;
    }
    $last = (string) setting('retention_last_run', '');
    if (!$force && $last === date('Y-m-d')) {
        return 0;
    }
    $limit = date('Y-m-d H:i:s', strtotime('-' . $months . ' months'));
    $ids = q('SELECT id FROM ' . t('leads') . " WHERE status = 'chiusa' AND status_changed_at < ?", [$limit])->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        lead_delete((int) $id);
    }
    setting_set('retention_last_run', date('Y-m-d'));
    if ($ids) {
        audit('richieste_eliminate_automaticamente', count($ids) . ' richieste chiuse più vecchie di ' . $months . ' mesi', null);
    }
    return count($ids);
}

/** Notifica email della nuova richiesta ai destinatari impostati. */
function lead_notify(array $lead): ?string
{
    $to = notify_recipients();
    if (!$to) {
        return 'Nessun destinatario impostato per le notifiche.';
    }
    $body = "Nuova richiesta dal sito ChiMangia\n\n"
        . 'Nome: ' . $lead['name'] . "\n"
        . 'Attività: ' . ($lead['business'] ?: '—') . "\n"
        . 'Email: ' . $lead['email'] . "\n"
        . 'Telefono: ' . ($lead['phone'] ?: '—') . "\n"
        . 'Numero indicativo di clienti: ' . ($lead['clients'] ?: '—') . "\n\n"
        . "Messaggio:\n" . ($lead['message'] ?: '—') . "\n\n"
        . 'Apri nel pannello: ' . site_url('admin/richiesta.php?id=' . $lead['id']) . "\n";
    return send_mail($to, 'Nuova richiesta: ' . $lead['name'] . ($lead['business'] ? ' (' . $lead['business'] . ')' : ''), $body, $lead['email']);
}
