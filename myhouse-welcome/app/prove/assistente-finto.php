<?php
/**
 * Un'API di Anthropic finta per le prove (Messages), dietro `php -S`. Controlla la chiave (x-api-key) e la versione,
 * legge il prompt di sistema con la guida e l'ultima domanda, e risponde come farebbe il modello con le regole di
 * Assistente::istruzioni: «[[S:id]] …» con la sezione il cui titolo contiene una parola della domanda (altrimenti la
 * prima), oppure «[[NO]] …» se la domanda contiene «NIENTE-FINTO». Con «ERRORE-FINTO» risponde 529 (sovraccarico).
 * Registra ogni richiesta in richieste.jsonl.
 *
 *   ASSISTENTE_FINTO_DIR   cartella del registro
 *   ASSISTENTE_FINTO_KEY   la chiave attesa
 *   ASSISTENTE_FINTO_ADMIN_KEY   la Admin key attesa dal rapporto dei costi (9T)
 */
$dir = getenv('ASSISTENTE_FINTO_DIR') ?: sys_get_temp_dir() . '/assistente-finto';
@mkdir($dir, 0777, true);
$corpo = (string) file_get_contents('php://input');
$h = [];
foreach (getallheaders() as $k => $v) $h[strtolower($k)] = trim((string) $v);
$j = json_decode($corpo, true);
$rispondi = function (int $code, array $dati) use ($dir, $corpo, $h, $j) {
    $sistema = is_array($j) ? implode("\n", array_map(fn($b) => is_array($b) ? (string) ($b['text'] ?? '') : (string) $b, (array) ($j['system'] ?? []))) : '';
    $ultimo = is_array($j) && !empty($j['messages']) ? (string) (end($j['messages'])['content'] ?? '') : '';
    file_put_contents($dir . '/richieste.jsonl', json_encode(['code' => $code, 'model' => $j['model'] ?? null, 'byte_sistema' => strlen($sistema), 'cache' => str_contains($corpo, 'cache_control'),
        'messaggi' => is_array($j) ? count((array) ($j['messages'] ?? [])) : 0, 'domanda' => $ultimo, 'effort' => $j['output_config']['effort'] ?? null, 'versione' => $h['anthropic-version'] ?? ''], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    http_response_code($code);
    header('Content-Type: application/json');
    exit(json_encode($dati, JSON_UNESCAPED_UNICODE));
};
$errore = fn(int $code, string $tipo, string $msg) => $rispondi($code, ['type' => 'error', 'error' => ['type' => $tipo, 'message' => $msg]]);

// 9T · Usage & Cost API (GET /v1/organizations/cost_report) con la Admin key (ASSISTENTE_FINTO_ADMIN_KEY): ogni giorno
// 125,5 centesimi nel workspace wrkspc_finto01 e 40 nel workspace di serie (workspace_id null); pagine da 10 giorni.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/v1/organizations/cost_report') {
    if (($h['x-api-key'] ?? '') === '' || ($h['x-api-key'] ?? '') !== (getenv('ASSISTENTE_FINTO_ADMIN_KEY') ?: '')) $errore(401, 'authentication_error', 'invalid x-api-key');
    if (($h['anthropic-version'] ?? '') === '') $errore(400, 'invalid_request_error', 'anthropic-version header is required');
    parse_str((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $q);
    $da = strtotime((string) ($q['starting_at'] ?? '')); $a = strtotime((string) ($q['ending_at'] ?? ''));
    if (!$da || !$a || $a <= $da) $errore(400, 'invalid_request_error', 'starting_at and ending_at are required');
    $perWs = in_array('workspace_id', (array) ($q['group_by'] ?? []), true);
    $giorni = [];
    for ($t = $da; $t < $a; $t += 86400) {
        $giorni[] = ['starting_at' => gmdate('Y-m-d\TH:i:s\Z', $t), 'ending_at' => gmdate('Y-m-d\TH:i:s\Z', $t + 86400),
                     'results' => $perWs ? [['amount' => '125.5', 'currency' => 'USD', 'workspace_id' => 'wrkspc_finto01', 'description' => null],
                                            ['amount' => '40', 'currency' => 'USD', 'workspace_id' => null, 'description' => null]]
                                         : [['amount' => '165.5', 'currency' => 'USD', 'workspace_id' => null, 'description' => null]]];
    }
    $da0 = (int) ($q['page'] ?? 0);
    file_put_contents($dir . '/richieste.jsonl', json_encode(['code' => 200, 'percorso' => 'cost_report', 'pagina' => $da0, 'gruppi' => $perWs, 'versione' => $h['anthropic-version'] ?? '']) . "\n", FILE_APPEND);
    header('Content-Type: application/json');
    exit(json_encode(['data' => array_slice($giorni, $da0, 10), 'has_more' => $da0 + 10 < count($giorni), 'next_page' => $da0 + 10 < count($giorni) ? (string) ($da0 + 10) : null]));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) !== '/v1/messages') $errore(404, 'not_found_error', 'Not found');
if (($h['x-api-key'] ?? '') !== (getenv('ASSISTENTE_FINTO_KEY') ?: '')) $errore(401, 'authentication_error', 'invalid x-api-key');
if (($h['anthropic-version'] ?? '') === '') $errore(400, 'invalid_request_error', 'anthropic-version header is required');
if (!is_array($j) || empty($j['model']) || empty($j['messages']) || !isset($j['max_tokens'])) $errore(400, 'invalid_request_error', 'model, max_tokens and messages are required');
if (!preg_match('/^claude-[a-z0-9.-]+$/', (string) $j['model'])) $errore(404, 'not_found_error', 'model: ' . $j['model']);
if (($j['messages'][0]['role'] ?? '') !== 'user' || (end($j['messages'])['role'] ?? '') !== 'user') $errore(400, 'invalid_request_error', 'messages must start and end with a user turn');

$domanda = (string) (end($j['messages'])['content'] ?? '');
if (str_contains($domanda, 'ERRORE-FINTO')) $errore(529, 'overloaded_error', 'Overloaded');
$sistema = implode("\n", array_map(fn($b) => is_array($b) ? (string) ($b['text'] ?? '') : (string) $b, (array) ($j['system'] ?? [])));
$cache = str_contains($corpo, 'cache_control');
$byteSistema = strlen($sistema);
// La sezione: quella il cui titolo contiene una parola (≥ 4 lettere) della domanda, altrimenti la prima.
preg_match_all('/^## \[#(\d+)\] (.+)$/mu', $sistema, $mm, PREG_SET_ORDER);
$sezione = $mm[0][1] ?? null;
$q = mb_strtolower(preg_replace('/^.*Domanda:\s*/su', '', $domanda));
foreach ($mm as $s) {
    foreach (preg_split('/[^\p{L}\p{N}-]+/u', $q) as $parola) {
        if (mb_strlen($parola) >= 4 && str_contains(mb_strtolower($s[2]), $parola)) { $sezione = $s[1]; break 2; }
    }
}
if (str_contains($domanda, 'NIENTE-FINTO')) $testo = '[[NO]] La guida non lo dice: chiedi a chi ti ospita.';
elseif ($sezione === null) $testo = '[[NO]] La guida non lo dice.';
else $testo = '[[S:' . $sezione . ']] Risposta finta alla domanda «' . trim(preg_replace('/^.*Domanda:\s*/su', '', $domanda)) . '», dalla sezione ' . $sezione . '.';
// I token: una stima dai byte; con la cache il sistema è «scritto» la prima volta e «letto» dopo (per struttura e lingua).
$impronta = $dir . '/cache-' . md5($sistema) . '.flag';
$tokSis = (int) ceil($byteSistema / 4); $tokMsg = (int) ceil(strlen(json_encode($j['messages'])) / 4);
$uso = ['input_tokens' => $tokMsg, 'output_tokens' => (int) ceil(strlen($testo) / 4), 'cache_creation_input_tokens' => 0, 'cache_read_input_tokens' => 0];
if ($cache && is_file($impronta)) $uso['cache_read_input_tokens'] = $tokSis;
elseif ($cache) { $uso['cache_creation_input_tokens'] = $tokSis; touch($impronta); }
else $uso['input_tokens'] += $tokSis;
$rispondi(200, ['id' => 'msg_finto_' . bin2hex(random_bytes(4)), 'type' => 'message', 'role' => 'assistant', 'model' => $j['model'],
    'content' => [['type' => 'text', 'text' => $testo]], 'stop_reason' => 'end_turn', 'stop_sequence' => null, 'usage' => $uso]);
