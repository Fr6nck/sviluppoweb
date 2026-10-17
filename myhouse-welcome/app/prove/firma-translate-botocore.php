<?php
// Firma di Amazon Translate (TranslateText): confronto con botocore. Richiede: pip install botocore
// Uso: php prove/firma-translate-botocore.php (dalla cartella app/)
foreach (['Config', 'Storage', 'S3Storage', 'Traduttore'] as $c) require __DIR__ . "/../src/$c.php";
use MHW\{Config, Traduttore};
$casi = []; $nostre = [];
$testi = ['Ciao', "Il check-in è dalle 15:00.\nLe chiavi sono nella cassetta: codice sul messaggio.", 'Àccènti, «virgolette» e 100% € — ok?'];
foreach ([['eu-west-1', 'AKIDEXAMPLE', 'segreto/1+abc', ''], ['eu-central-1', 'AKIAPROVA2', 'altro+segreto/2', 'TOKEN-di-sessione/2==']] as $j => [$reg, $key, $sec, $tok]) {
    $cfg = tempnam(sys_get_temp_dir(), 'cfg');
    file_put_contents($cfg, '<?php return ' . var_export(['translate' => ['region' => $reg, 'key' => $key, 'secret' => $sec, 'token' => $tok, 'endpoint' => ''],
                                                          'storage' => ['s3' => []]], true) . ';');
    Config::load($cfg); unlink($cfg);
    foreach ($testi as $i => $t) {
        $amz = '2026100' . ($i + 1) . 'T1' . $j . '0' . $i . '00Z';
        [$url, $corpo, $h] = Traduttore::richiesta($t, 'it', ['en', 'de', 'fr'][$i], $amz);
        $nostre[] = $h['authorization'];
        $casi[] = ['url' => $url, 'body' => $corpo, 'region' => $reg, 'key' => $key, 'secret' => $sec, 'token' => $tok, 'amz' => $amz,
                   'headers' => ['Content-Type' => $h['content-type'], 'X-Amz-Target' => $h['x-amz-target']]];
    }
}
$f = tempnam(sys_get_temp_dir(), 'casi');
file_put_contents($f, json_encode($casi));
$boto = json_decode((string) shell_exec('python3 ' . escapeshellarg(__DIR__ . '/firma_translate_botocore.py') . ' ' . escapeshellarg($f)), true);
unlink($f);
$ok = 0;
foreach ($casi as $i => $c) {
    $same = ($boto[$i] ?? '') === $nostre[$i];
    $ok += $same ? 1 : 0;
    printf("%-13s %-40s %s\n", $c['region'], mb_substr(json_decode($c['body'], true)['Text'], 0, 38), $same ? 'coincide' : "DIVERSO\n  php  {$nostre[$i]}\n  boto " . ($boto[$i] ?? '?'));
}
echo "\n$ok su " . count($casi) . " firme identiche a botocore.\n";
exit($ok === count($casi) ? 0 : 1);
