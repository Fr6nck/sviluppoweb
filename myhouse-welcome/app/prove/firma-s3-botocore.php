<?php
// Firma S3: confronto con botocore (riferimento indipendente). Richiede: pip install botocore
// Uso: php prove/firma-s3-botocore.php (dalla cartella app/)
require __DIR__ . '/../src/Storage.php'; require __DIR__ . '/../src/S3Storage.php';
use MHW\S3Storage;
$casi = [];
$chiavi = ['a12/p5/2026/9f3cabc.jpg', 'a1/p0/2026/file con spazi.pdf', 'a7/p3/2026/àccènto+più=uguale.png', 'a2/p2/2026/~tilde_-.webp'];
foreach ($chiavi as $i => $k) {
  $url = 'https://mhw-media.s3.eu-south-1.amazonaws.com/' . S3Storage::encodeKey($k);
  $body = "contenuto $i " . str_repeat('x', $i * 37);
  $casi[] = ['kind' => 'headers', 'method' => 'PUT', 'url' => $url, 'body' => $body, 'region' => 'eu-south-1',
    'key' => 'AKIDEXAMPLE' . $i, 'secret' => 'segreto/' . $i . '+abc', 'token' => $i % 2 ? 'TOKEN-di-sessione/' . $i . '==' : '',
    'amz' => '2026092' . $i . 'T10' . $i . '000Z',
    'headers' => ['content-type' => $i === 1 ? 'application/pdf' : 'image/jpeg', 'cache-control' => 'public, max-age=31536000, immutable']
                 + ($i === 1 ? ['content-disposition' => 'inline; filename="menu.pdf"'] : [])];
  $casi[] = ['kind' => 'query', 'method' => 'GET', 'url' => $url, 'region' => 'eu-south-1', 'key' => 'AKIDEXAMPLE' . $i,
    'secret' => 'segreto/' . $i, 'token' => $i % 2 ? 'TOKEN/' . $i : '', 'amz' => '2026092' . $i . 'T08' . $i . '500Z', 'expires' => 900 + $i];
}
file_put_contents('/tmp/casi.json', json_encode($casi));
$boto = json_decode(shell_exec('python3 ' . __DIR__ . '/firma_s3_botocore.py /tmp/casi.json'), true);
$ok = 0;
foreach ($casi as $i => $c) {
  if ($c['kind'] === 'headers') {
    $h = S3Storage::signHeaders($c['method'], $c['url'], $c['headers'], hash('sha256', $c['body']), $c['region'], $c['key'], $c['secret'], $c['amz'], $c['token']);
    preg_match('/Signature=(\w+)/', $h['authorization'], $a); preg_match('/Signature=(\w+)/', $boto[$i], $b);
    preg_match('/SignedHeaders=([^,]+)/', $h['authorization'], $sa); preg_match('/SignedHeaders=([^,]+)/', $boto[$i], $sb);
    $same = $a[1] === $b[1];
    printf("PUT   %-44s %s%s\n", basename(rawurldecode(parse_url($c['url'], PHP_URL_PATH))), $same ? 'coincide' : 'DIVERSO', $same ? '' : "\n  php  {$sa[1]}\n  boto {$sb[1]}");
  } else {
    $u = S3Storage::presign($c['method'], $c['url'], $c['region'], $c['key'], $c['secret'], $c['expires'], $c['amz'], $c['token']);
    preg_match('/X-Amz-Signature=(\w+)/', $u, $a); preg_match('/X-Amz-Signature=(\w+)/', $boto[$i], $b);
    $same = $a[1] === $b[1];
    printf("GET?  %-44s %s%s\n", basename(rawurldecode(parse_url($c['url'], PHP_URL_PATH))), $same ? 'coincide' : 'DIVERSO', $same ? '' : "\n  php  $u\n  boto {$boto[$i]}");
  }
  $ok += $same;
}
echo "\n$ok su " . count($casi) . " firme identiche a botocore.\n";
