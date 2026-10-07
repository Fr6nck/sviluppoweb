<?php
/**
 * Un Amazon Translate finto per le prove, dietro `php -S`. Risponde solo a
 * TranslateText e VERIFICA la firma SigV4, ricalcolata qui da capo (non con il
 * codice dell'applicazione) con la chiave segreta finta delle prove: una firma
 * sbagliata riceve 403, come da AWS. Traduce mettendo davanti «[en] ».
 * Un testo con «ERRORE-FINTO» riceve 503, per provare gli errori.
 *
 *   TRANSLATE_FINTO_DIR      cartella del registro delle richieste
 *   TRANSLATE_FINTO_KEY / TRANSLATE_FINTO_SECRET   le chiavi finte attese
 */
$dir = getenv('TRANSLATE_FINTO_DIR') ?: sys_get_temp_dir() . '/translate-finto';
@mkdir($dir, 0777, true);
$corpo = (string) file_get_contents('php://input');
$h = [];
foreach (getallheaders() as $k => $v) $h[strtolower($k)] = trim((string) $v);
$rispondi = function (int $code, array $dati) use ($dir, $corpo, $h) {
    file_put_contents($dir . '/richieste.jsonl', json_encode(['code' => $code, 'byte' => strlen($corpo), 'target' => $h['x-amz-target'] ?? '',
        'testo' => json_decode($corpo, true)['Text'] ?? null]) . "\n", FILE_APPEND);
    http_response_code($code);
    header('Content-Type: application/x-amz-json-1.1');
    exit(json_encode($dati, JSON_UNESCAPED_UNICODE));
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($h['x-amz-target'] ?? '') !== 'AWSShineFrontendService_20170701.TranslateText') {
    $rispondi(400, ['__type' => 'UnknownOperationException', 'message' => 'Solo TranslateText']);
}
// La firma, da capo.
if (!preg_match('#^AWS4-HMAC-SHA256 Credential=([^/]+)/(\d{8})/([a-z0-9-]+)/translate/aws4_request, SignedHeaders=([a-z0-9;-]+), Signature=([0-9a-f]{64})$#', $h['authorization'] ?? '', $m)) {
    $rispondi(403, ['__type' => 'IncompleteSignatureException', 'message' => 'Firma mancante o malformata']);
}
[, $chiave, $giorno, $regione, $firmate, $firma] = $m;
if ($chiave !== (getenv('TRANSLATE_FINTO_KEY') ?: '')) $rispondi(403, ['__type' => 'UnrecognizedClientException', 'message' => 'Chiave sconosciuta']);
$quando = $h['x-amz-date'] ?? '';
if (substr($quando, 0, 8) !== $giorno || abs(strtotime($quando) - time()) > 900) $rispondi(403, ['__type' => 'InvalidSignatureException', 'message' => 'Data fuori tempo']);
$canoniche = '';
foreach (explode(';', $firmate) as $n) $canoniche .= $n . ':' . preg_replace('/\s+/', ' ', $h[$n] ?? '') . "\n";
foreach (['host', 'x-amz-date', 'x-amz-target', 'content-type'] as $n) if (!in_array($n, explode(';', $firmate), true)) $rispondi(403, ['__type' => 'InvalidSignatureException', 'message' => "$n non firmata"]);
$richiesta = "POST\n/\n\n$canoniche\n$firmate\n" . hash('sha256', $corpo);
$ambito = "$giorno/$regione/translate/aws4_request";
$k = hash_hmac('sha256', 'aws4_request', hash_hmac('sha256', 'translate', hash_hmac('sha256', $regione, hash_hmac('sha256', $giorno, 'AWS4' . getenv('TRANSLATE_FINTO_SECRET'), true), true), true), true);
$attesa = hash_hmac('sha256', "AWS4-HMAC-SHA256\n$quando\n$ambito\n" . hash('sha256', $richiesta), $k);
if (!hash_equals($attesa, $firma)) $rispondi(403, ['__type' => 'InvalidSignatureException', 'message' => 'The request signature we calculated does not match the signature you provided.']);

$j = json_decode($corpo, true);
if (!is_array($j) || !isset($j['Text'], $j['SourceLanguageCode'], $j['TargetLanguageCode'])) $rispondi(400, ['__type' => 'ValidationException', 'message' => 'Corpo non valido']);
if (strlen($j['Text']) > 10000) $rispondi(400, ['__type' => 'TextSizeLimitExceededException', 'message' => 'Oltre 10.000 byte']);
if (str_contains($j['Text'], 'ERRORE-FINTO')) $rispondi(503, ['__type' => 'ServiceUnavailableException', 'message' => 'Servizio finto non disponibile']);
$rispondi(200, ['TranslatedText' => '[' . $j['TargetLanguageCode'] . '] ' . $j['Text'], 'SourceLanguageCode' => $j['SourceLanguageCode'], 'TargetLanguageCode' => $j['TargetLanguageCode']]);
