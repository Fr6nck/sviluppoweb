<?php
/**
 * 8D — Quali testi della vetrina «Casa Checco» mancano nelle altre lingue.
 *
 * Da lanciare dalla riga di comando, anche sul server (non tocca il database del sito):
 *   php app/tools/vetrina-mancanti.php            per ogni lingua (en, fr, de, es) il numero e l'elenco dei campi
 *   php app/tools/vetrina-mancanti.php --conta    solo i numeri
 *
 * Crea la vetrina da zero su un database SQLite di prova in una cartella temporanea (copia di src,
 * migrations e lang), come «Rifai la vetrina», e poi chiede a Traduttore::campi() i campi con il
 * testo nella lingua principale ma senza traduzione. Alla fine la cartella si cancella.
 * Esce con 0 se in tutte e quattro le lingue non manca niente, altrimenti con 1.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$vera = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/vetrina-mancanti-' . bin2hex(random_bytes(4));
$copia = function (string $da, string $a) use (&$copia): void {
    @mkdir($a, 0775, true);
    foreach (scandir($da) ?: [] as $f) {
        if ($f === '.' || $f === '..') continue;
        is_dir("$da/$f") ? $copia("$da/$f", "$a/$f") : copy("$da/$f", "$a/$f");
    }
};
foreach (['src', 'migrations', 'lang'] as $d) $copia("$vera/$d", "$tmp/$d");
@mkdir("$tmp/storage/uploads", 0775, true);
register_shutdown_function(function () use ($tmp) {
    $via = function (string $d) use (&$via): void { foreach (scandir($d) ?: [] as $f) { if ($f === '.' || $f === '..') continue; is_dir("$d/$f") ? $via("$d/$f") : @unlink("$d/$f"); } @rmdir($d); };
    $via($tmp);
});

define('MHW_APP', $tmp);
// Le foto della vetrina: dalla cartella pubblica vera (nel progetto app/public, sul server la radice del sito).
define('MHW_PUBLIC', is_dir("$vera/public/assets/foto") ? "$vera/public" : dirname($vera));
spl_autoload_register(function (string $c): void {
    if (str_starts_with($c, 'MHW\\') && is_file($f = MHW_APP . '/src/' . substr($c, 4) . '.php')) require $f;
});

use MHW\{Config, Db, Auth, Demo, Traduttore, Support};

Config::load("$vera/config.php");
Config::sovrascrivi('db', ['driver' => 'sqlite', 'sqlite_path' => "$tmp/storage/prova.sqlite"] + (array) Config::get('db'));
Config::sovrascrivi('storage', ['driver' => 'local', 'local_dir' => "$tmp/storage/uploads"] + (array) Config::get('storage'));
Config::sovrascrivi('uploads_dir', "$tmp/storage/uploads");
Config::sovrascrivi('mail', ['transport' => 'log'] + (array) Config::get('mail', []));

MHW\Installer::install('admin@vetrina.prova', 'VetrinaProva-' . bin2hex(random_bytes(6)));
$u = Auth::register('vetrina@vetrina.prova', 'VetrinaProva-' . bin2hex(random_bytes(6)), 'Vetrina');
Db::update('users', ['email_verified_at' => Support::now()], 'id = :uid', ['uid' => $u['user_id']]);
$acc = (int) Db::val('SELECT id FROM accounts WHERE user_id = ?', [$u['user_id']]);
$pid = Demo::creaVetrina($acc, true);
$prop = Db::one('SELECT * FROM properties WHERE id = ?', [$pid]);

$solo = in_array('--conta', $argv, true);
$tot = 0;
foreach (['en', 'fr', 'de', 'es'] as $loc) {
    $mancano = array_filter(Traduttore::campi($prop, $loc), fn($c) => $c['tradotto'] === '');
    $tot += count($mancano);
    echo strtoupper($loc), ': ', count($mancano), "\n";
    if ($solo) continue;
    foreach ($mancano as $k => $c) echo '  ', $k, '  «', mb_strimwidth(str_replace("\n", ' / ', $c['origine']), 0, 110, '…'), "»\n";
}
exit($tot === 0 ? 0 : 1);
