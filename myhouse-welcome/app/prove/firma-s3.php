<?php
// Firma S3 (SigV4): i quattro esempi pubblicati nella documentazione AWS.
// Uso: php prove/firma-s3.php (dalla cartella app/)
require __DIR__ . '/../src/Storage.php'; require __DIR__ . '/../src/S3Storage.php';
use MHW\S3Storage;
$K = 'AKIAIOSFODNN7EXAMPLE'; $S = 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY';
$ok = 0; $tot = 0;
function atteso($nome, $vero, $calcolato) { global $ok, $tot; $tot++; $b = $vero === $calcolato; $ok += $b;
  printf("%-44s %s\n", $nome, $b ? 'coincide' : "DIVERSO\n   atteso    $vero\n   calcolato $calcolato"); }

// 1. Documentazione S3, "Example: GET Object" (header Range)
$h = S3Storage::signHeaders('GET', 'https://examplebucket.s3.amazonaws.com/test.txt', ['range' => 'bytes=0-9'],
     'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', 'us-east-1', $K, $S, '20130524T000000Z');
preg_match('/Signature=(\w+)/', $h['authorization'], $m);
atteso('GET Object con Range', 'f0e8bdb87c964420e857bd35b5d6ed310bd44f0170aba48dd91039c6036bdb41', $m[1]);

// 2. Documentazione S3, "Example: PUT Object"
$body = 'Welcome to Amazon S3.';
$h = S3Storage::signHeaders('PUT', 'https://examplebucket.s3.amazonaws.com/test%24file.text',
     ['date' => 'Fri, 24 May 2013 00:00:00 GMT', 'x-amz-storage-class' => 'REDUCED_REDUNDANCY'],
     hash('sha256', $body), 'us-east-1', $K, $S, '20130524T000000Z');
preg_match('/Signature=(\w+)/', $h['authorization'], $m);
atteso('PUT Object con storage class', '98ad721746da40c64f1a55b78f14c238d841ea1380cd77a1b5971af0ece108bd', $m[1]);

// 3. Documentazione S3, "Example: GET Bucket Lifecycle" (query senza valore)
$h = S3Storage::signHeaders('GET', 'https://examplebucket.s3.amazonaws.com/?lifecycle', [],
     'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', 'us-east-1', $K, $S, '20130524T000000Z');
preg_match('/Signature=(\w+)/', $h['authorization'], $m);
atteso('GET Bucket Lifecycle', 'fea454ca298b7da1c68078a5d1bdbfbbe0d65c699e0f91ac7a200a0136783543', $m[1]);

// 4. Documentazione S3, URL prefirmato di esempio
$u = S3Storage::presign('GET', 'https://examplebucket.s3.amazonaws.com/test.txt', 'us-east-1', $K, $S, 86400, '20130524T000000Z');
preg_match('/X-Amz-Signature=(\w+)/', $u, $m);
atteso('URL prefirmato (24 ore)', 'aeeed9bbccd4d02ee5c0109b86d86835f995330da4c265957d157751f604d404', $m[1]);
echo "\n$ok su $tot vettori ufficiali.\n";
