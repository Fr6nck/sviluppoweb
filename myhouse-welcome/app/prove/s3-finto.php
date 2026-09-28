<?php
/**
 * Un bucket S3 finto per le prove, dietro `php -S`, in stile a percorso:
 * PUT e DELETE devono portare una firma SigV4 (intestazione Authorization),
 * GET deve portare una firma prefirmata in query. La firma non la ricalcola —
 * quella è verificata a parte contro i vettori di AWS e contro botocore — ma
 * controlla che ci sia e che abbia la forma giusta, e custodisce gli oggetti.
 *
 *   S3_FINTO_DIR   cartella degli oggetti e del registro
 */
$dir = getenv('S3_FINTO_DIR') ?: sys_get_temp_dir() . '/s3-finto';
@mkdir($dir, 0777, true);
$metodo = $_SERVER['REQUEST_METHOD'];
$percorso = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = $dir . '/oggetti/' . str_replace('/', '__', ltrim($percorso, '/'));
@mkdir($dir . '/oggetti', 0777, true);
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
file_put_contents($dir . '/richieste.jsonl', json_encode([
    'metodo' => $metodo, 'percorso' => $percorso, 'auth' => $auth !== '', 'query' => $_GET,
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? '', 'sha' => $_SERVER['HTTP_X_AMZ_CONTENT_SHA256'] ?? '',
]) . "\n", FILE_APPEND);

$firmata = preg_match('#^AWS4-HMAC-SHA256 Credential=[^/]+/\d{8}/[a-z0-9-]+/s3/aws4_request, SignedHeaders=[a-z0-9;-]+, Signature=[0-9a-f]{64}$#', $auth);
if (in_array($metodo, ['PUT', 'DELETE'], true)) {
    if (!$firmata) { http_response_code(403); exit('<Error><Code>AccessDenied</Code></Error>'); }
    $corpo = (string) file_get_contents('php://input');
    if ($metodo === 'PUT') {
        if (hash('sha256', $corpo) !== ($_SERVER['HTTP_X_AMZ_CONTENT_SHA256'] ?? '')) { http_response_code(400); exit('<Error><Code>XAmzContentSHA256Mismatch</Code></Error>'); }
        file_put_contents($file, $corpo);
        file_put_contents($file . '.tipo', $_SERVER['CONTENT_TYPE'] ?? 'application/octet-stream');
    } else { @unlink($file); @unlink($file . '.tipo'); }
    http_response_code($metodo === 'PUT' ? 200 : 204); exit;
}
if ($metodo === 'GET') {
    if (($_GET['X-Amz-Algorithm'] ?? '') !== 'AWS4-HMAC-SHA256' || !preg_match('/^[0-9a-f]{64}$/', $_GET['X-Amz-Signature'] ?? '')) {
        http_response_code(403); exit('<Error><Code>AccessDenied</Code></Error>');
    }
    if (!is_file($file)) { http_response_code(404); exit('<Error><Code>NoSuchKey</Code></Error>'); }
    header('Content-Type: ' . file_get_contents($file . '.tipo'));
    readfile($file); exit;
}
http_response_code(405);
