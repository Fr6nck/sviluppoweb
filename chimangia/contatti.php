<?php
/**
 * Riceve il modulo contatti e risponde in JSON.
 * Anti-spam: campo nascosto (honeypot), token firmato, tempo minimo di compilazione,
 * limite di invii per IP. Senza JavaScript rimanda alla pagina con l'esito.
 */
declare(strict_types=1);

define('CM_ROOT', __DIR__);
require __DIR__ . '/app/bootstrap.php';
require CM_APP . '/csrf.php';
require CM_APP . '/content.php';
require CM_APP . '/sanitize.php';
require CM_APP . '/mail.php';
require CM_APP . '/leads.php';

require_installed();
security_headers("default-src 'none'; frame-ancestors 'none'");
header('X-Robots-Tag: noindex');

$wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

function contact_reply(bool $wantsJson, bool $ok, string $message, array $errors = [], int $code = 200): never
{
    if ($wantsJson) {
        json_response(['ok' => $ok, 'message' => $message, 'errors' => (object) $errors], $code);
    }
    redirect('./?inviato=' . ($ok ? '1' : '0') . '#contatti');
}

if (!is_post()) {
    header('Allow: POST');
    contact_reply($wantsJson, false, 'Metodo non consentito.', [], 405);
}

$contatti = content_get('contatti', 'published');
$success = (string) $contatti['success_text'];

// 1. Honeypot: i robot compilano tutti i campi. Rispondiamo "ok" senza salvare.
if (post('sito') !== '') {
    contact_reply($wantsJson, true, $success);
}

// 2. Token firmato e tempo minimo di compilazione.
$age = form_token_age(post('token'));
if ($age < 0) {
    contact_reply($wantsJson, false, 'Il modulo è scaduto: ricarica la pagina e invia di nuovo.', [], 400);
}
if ($age < (int) setting('contact_min_seconds', 4)) {
    contact_reply($wantsJson, false, 'Invio troppo rapido: attendi qualche secondo e riprova.', [], 429);
}

// 3. Limite di invii per IP nell'ultima ora.
$ipHash = ip_hash();
$max = max(1, (int) setting('contact_max_per_hour', 5));
$recent = (int) q_value('SELECT COUNT(*) FROM ' . t('contact_rate') . ' WHERE ip_hash = ? AND created_at > ?', [$ipHash, date('Y-m-d H:i:s', time() - 3600)]);
if ($recent >= $max) {
    contact_reply($wantsJson, false, 'Hai già inviato diverse richieste: riprova più tardi oppure scrivici o chiamaci.', [], 429);
}

// 4. Validazione.
$data = [
    'name' => clean_text(post('nome'), 120),
    'business' => clean_text(post('attivita'), 160),
    'email' => mb_strtolower(clean_text(post('email'), 190)),
    'phone' => clean_text(post('telefono'), 40),
    'clients' => clean_text(post('clienti'), 40),
    'message' => clean_lines(post('messaggio'), 3000),
];
$errors = [];
if ($data['name'] === '') {
    $errors['nome'] = 'Scrivi il tuo nome.';
}
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'L\'indirizzo email non sembra corretto.';
}
if ($data['phone'] !== '' && !preg_match('/^[0-9+().\s\/-]{5,40}$/', $data['phone'])) {
    $errors['telefono'] = 'Il numero di telefono non sembra corretto.';
}
if (post('privacy') !== '1') {
    $errors['privacy'] = 'Per inviare la richiesta serve il consenso.';
}
if ($errors) {
    contact_reply($wantsJson, false, 'Controlla i campi evidenziati.', $errors, 422);
}

// 5. Salvataggio e notifica.
$now = now();
q('INSERT INTO ' . t('contact_rate') . ' (ip_hash, created_at) VALUES (?, ?)', [$ipHash, $now]);
q(
    'INSERT INTO ' . t('leads') . ' (name, business, email, phone, clients, message, consent_at, status, notes, ip_hash, user_agent, created_at, updated_at, status_changed_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [$data['name'], $data['business'], $data['email'], $data['phone'], $data['clients'], $data['message'], $now, 'nuova', '', $ipHash,
        mb_substr(clean_text((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 255), 0, 255), $now, $now, $now]
);
$data['id'] = (int) db()->lastInsertId();
q('INSERT INTO ' . t('lead_history') . ' (lead_id, user_id, from_status, to_status, note, created_at) VALUES (?, NULL, ?, ?, ?, ?)', [$data['id'], '', 'nuova', 'Richiesta ricevuta dal sito', $now]);

$mailError = lead_notify($data);
if ($mailError !== null) {
    log_message('Notifica richiesta #' . $data['id'] . ' non inviata: ' . $mailError);
}
// Pulizia del registro degli invii.
if (random_int(1, 20) === 1) {
    q('DELETE FROM ' . t('contact_rate') . ' WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
}

contact_reply($wantsJson, true, $success);
