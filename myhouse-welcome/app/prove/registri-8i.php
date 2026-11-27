<?php
/**
 * 8I · Le prove dei registri dentro l'applicazione schierata (senza passare dal web):
 * mascheramento, pulizia per età, copia sul bucket dei registri (S3 finto), limiti senza IP in chiaro.
 * Uso: php registri-8i.php <cartella-schierata> <endpoint-s3-finto>   → stampa un JSON con gli esiti.
 */
$W = rtrim($argv[1] ?? '', '/');
define('MHW_APP', $W . '/app'); define('MHW_PUBLIC', $W);
$_SERVER['HTTP_HOST'] = '127.0.0.1'; $_SERVER['SCRIPT_NAME'] = '/index.php'; $_SERVER['REMOTE_ADDR'] = '93.41.12.77';
spl_autoload_register(function ($c) { if (str_starts_with($c, 'MHW\\')) require MHW_APP . '/src/' . substr($c, 4) . '.php'; });
MHW\Config::load(MHW_APP . '/config.php');
use MHW\{Log, Registri, Config, RateLimit};
$esiti = [];

// 1. Mascheramento nel file del giorno.
$ref = Log::error('Accesso non riuscito per mario.rossi@esempio.it da 93.41.12.77, tel. +39 333 123 4567, chiave sk_test_51AbCdEfGhIjKlMn',
                  ['email' => 'anna.verdi@prova.it', 'ip' => '2001:db8:85a3:1:2:3:4:5', 'password' => 'Segreta123', 'nota' => 'token=abcdef1234567890']);
$riga = ''; foreach (file(Log::file('app')) ?: [] as $l) if (str_contains($l, $ref)) $riga = $l;
$esiti['maschera'] = ['riga' => $riga,
    'ok' => $riga !== '' && str_contains($riga, 'm***@esempio.it') && str_contains($riga, '93.41.12.0') && str_contains($riga, '***567')
            && str_contains($riga, 'a***@prova.it') && str_contains($riga, '2001:db8:85a3::') && !str_contains($riga, 'Segreta123') && !str_contains($riga, 'sk_test_51')
            && !str_contains($riga, 'abcdef1234567890') && !str_contains($riga, 'mario.rossi') && !str_contains($riga, '93.41.12.77')];

// 2. Pulizia: tecnici dopo 90 giorni, posta dopo 7.
$d = Log::dir(); $g = fn(int $n) => gmdate('Y-m-d', time() - $n * 86400);
$finti = ["app-{$g(100)}.log", "app-{$g(10)}.log", "mail-{$g(8)}.log", "mail-{$g(3)}.log", "accessi-admin-{$g(100)}.log"];
foreach ($finti as $f) file_put_contents("$d/$f", "{\"t\":\"finto\"}\n");
@mkdir("$d/email"); file_put_contents("$d/email/vecchia.html", 'x'); touch("$d/email/vecchia.html", time() - 9 * 86400);
Registri::pulisci();
$esiti['pulizia'] = ['app100' => is_file("$d/{$finti[0]}"), 'app10' => is_file("$d/{$finti[1]}"), 'mail8' => is_file("$d/{$finti[2]}"),
                     'mail3' => is_file("$d/{$finti[3]}"), 'accessi100' => is_file("$d/{$finti[4]}"), 'email9' => is_file("$d/email/vecchia.html")];
$esiti['pulizia']['ok'] = !$esiti['pulizia']['app100'] && $esiti['pulizia']['app10'] && !$esiti['pulizia']['mail8'] && $esiti['pulizia']['mail3']
                          && !$esiti['pulizia']['accessi100'] && !$esiti['pulizia']['email9'];
@unlink("$d/{$finti[1]}"); @unlink("$d/{$finti[3]}");

// 3. Accessi degli amministratori e copia sul bucket dei registri (S3 finto).
Log::accesso('login', 1, 'admin@prova.it');
$acc = (string) @file_get_contents(Log::file('accessi-admin'));
$esiti['accessi'] = ['ok' => str_contains($acc, '"azione":"login"') && str_contains($acc, '"ip":"93.41.12.0"') && str_contains($acc, 'a***@prova.it') && !str_contains($acc, 'admin@prova.it')];
Config::sovrascrivi('registri', ['giorni' => 90, 'bucket' => 'mhw-registri', 'regione' => 'eu-south-1', 'key' => 'AKIAREGISTRIPROVA01', 'secret' => 'segreto-registri',
                                 'endpoint' => $argv[2] ?? '']);
$esiti['s3'] = Registri::caricaS3(gmdate('Y-m-d'));
$esiti['s3']['stato'] = Registri::stato();

// 3b. Il registro delle azioni: nessuna riga cancellata, gli IP abbreviati.
$prima = (int) MHW\Db::val('SELECT COUNT(*) FROM audit_log');
Registri::pulisciAudit();
$esiti['audit'] = ['prima' => $prima, 'dopo' => (int) MHW\Db::val('SELECT COUNT(*) FROM audit_log'),
                   'ipChiari' => (int) MHW\Db::val("SELECT COUNT(*) FROM audit_log WHERE meta LIKE '%127.0.0.1%' OR meta LIKE '%93.41.12.77%'")];
$esiti['audit']['ok'] = $esiti['audit']['prima'] === $esiti['audit']['dopo'] && $esiti['audit']['ipChiari'] === 0;

// 4. Limiti ai tentativi: il nome del limite non contiene l'IP.
$esiti['ipkey'] = ['chiave' => RateLimit::ipKey(), 'ok' => preg_match('/^[0-9a-f]{16}$/', RateLimit::ipKey()) === 1 && RateLimit::ipKey() === RateLimit::ipKey()
                   && is_file(MHW_APP . '/storage/.chiave-registri')];
echo json_encode($esiti, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
