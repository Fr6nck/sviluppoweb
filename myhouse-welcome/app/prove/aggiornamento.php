<?php
/**
 * Metà della prova di aggiornamento (la lancia aggiornamento.sh).
 *   prima: installa la versione vecchia e fotografa il database
 *   dopo:  col codice nuovo, controlla che le migrazioni siano partite da
 *          sole e che niente di venduto o scritto sia andato perso
 */
[$_, $fase, $BASE, $W] = $argv;
$foto = "$W/../foto-prima.json";
$falliti = 0;
function prova(string $n, bool $ok, string $nota = ''): void { global $falliti; if (!$ok) $falliti++; echo ($ok ? '  ok  ' : ' NO   '), $n, $nota ? "   — $nota" : '', "\n"; }
function http(string $url, ?array $post = null): array {
    global $W;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => "$W/../c.txt", CURLOPT_COOKIEFILE => "$W/../c.txt"]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $raw = (string) curl_exec($ch); $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $r = ['code' => curl_getinfo($ch, CURLINFO_HTTP_CODE), 'loc' => (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL), 'body' => substr($raw, $hs)];
    curl_close($ch);
    return $r;
}
function tok(string $h): string { return preg_match('/name="_csrf" value="([a-f0-9]+)"/', $h, $m) ? $m[1] : ''; }
function pdo(): PDO { global $W; return new PDO('sqlite:' . glob("$W/app/storage/*.sqlite")[0], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]); }
function pulita(string $b): bool { foreach (['Fatal error', 'Warning:', 'Notice:', 'Uncaught', 'SQLSTATE'] as $x) if (str_contains($b, $x)) return false; return true; }

if ($fase === 'prima') {
    echo "== Versione precedente\n";
    $r = http("$BASE/installa");
    $r = http("$BASE/installa", ['_csrf' => tok($r['body']), 'email' => 'admin@prova.test', 'password' => 'AdminProva123', 'esempi' => '1']);
    prova('Installata con i clienti di esempio', $r['code'] === 302);
    $db = pdo();
    $codici = (int) $db->query("SELECT COUNT(*) FROM sections WHERE door_code <> ''")->fetchColumn();
if ($codici > 0 || !$db->query("SELECT 1 FROM schema_migrations WHERE name LIKE '003%'")->fetchColumn()) prova('Ci sono codici porta nel database vecchio (da togliere)', $codici > 0);
    // Da una versione che vendeva già Portfolio 2 e 3: un cliente che l'ha comprato
    // e uno che l'ha solo scelto, come ce ne sarebbero sul server.
    $pv2 = $db->query("SELECT pv.id FROM package_versions pv JOIN packages p ON p.id = pv.package_id WHERE p.code = 'portfolio2' AND pv.is_current = 1")->fetchColumn();
    $pv3 = $db->query("SELECT pv.id FROM package_versions pv JOIN packages p ON p.id = pv.package_id WHERE p.code = 'portfolio3' AND pv.is_current = 1")->fetchColumn();
    $copia = function (string $tabella, array $riga) use ($db): int {
        unset($riga['id']);
        $db->prepare("INSERT INTO $tabella (" . implode(', ', array_keys($riga)) . ') VALUES (' . implode(', ', array_fill(0, count($riga), '?')) . ')')->execute(array_values($riga));
        return (int) $db->lastInsertId();
    };
    $liberi = [];
    if ($pv2) foreach (['p2', 'p3'] as $k) {
        $uid = $copia('users', ['email' => "$k@vecchio.test", 'role' => 'host'] + $db->query("SELECT * FROM users WHERE role <> 'admin' ORDER BY id LIMIT 1")->fetch());
        $liberi[] = $copia('accounts', ['user_id' => $uid, 'intended_package_version_id' => null, 'stripe_customer_id' => ''] + $db->query('SELECT * FROM accounts ORDER BY id LIMIT 1')->fetch());
    }
    $modello = $db->query('SELECT * FROM subscriptions ORDER BY id LIMIT 1')->fetch();
    $p2acc = $p3acc = 0;
    if ($pv2 && $pv3 && count($liberi) >= 2 && $modello) {
        [$p2acc, $p3acc] = $liberi;
        unset($modello['id']);
        $modello = ['account_id' => $p2acc, 'package_version_id' => $pv2, 'status' => 'active', 'provider' => 'stripe', 'provider_subscription_id' => 'sub_vecchio_p2'] + $modello;
        $db->prepare('INSERT INTO subscriptions (' . implode(', ', array_keys($modello)) . ') VALUES (' . implode(', ', array_fill(0, count($modello), '?')) . ')')->execute(array_values($modello));
        $db->prepare('UPDATE accounts SET intended_package_version_id = ? WHERE id = ?')->execute([$pv3, $p3acc]);
    }
    prova('Portfolio 2 e 3 nel database vecchio: uno comprato, uno solo scelto', !$pv2 || $p2acc > 0, $pv2 ? '' : 'versione senza Portfolio 2/3');
    $f = [
        'p2acc' => (int) $p2acc, 'p3acc' => (int) $p3acc,
        'utenti' => (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'strutture' => $db->query('SELECT id, name, slug, status FROM properties ORDER BY id')->fetchAll(),
        'sezioni' => (int) $db->query('SELECT COUNT(*) FROM sections')->fetchColumn(),
        'abbonamenti' => $db->query('SELECT account_id, package_version_id, status FROM subscriptions ORDER BY id')->fetchAll(),
        'diritti' => $db->query('SELECT pf.package_version_id AS pv, f.code, pf.value FROM package_features pf JOIN features f ON f.id = pf.feature_id')->fetchAll(),
        'qr' => $db->query('SELECT property_id, token FROM qr_tokens ORDER BY id')->fetchAll(),
    ];
    file_put_contents($foto, json_encode($f));
    exit($falliti ? 1 : 0);
}

echo "== Codice nuovo sopra i dati vecchi\n";
$f = json_decode(file_get_contents($foto), true);
$r = http("$BASE/");
prova('La landing si apre (migrazioni al primo accesso)', $r['code'] === 200 && pulita($r['body']));
$db = pdo();
$mig = $db->query('SELECT name FROM schema_migrations ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
prova('Migrazioni registrate, fino alla 006', count($mig) >= 6 && in_array('006_portfolio_quantita.php', $mig, true), implode(', ', $mig));
prova('Nessun utente perso', (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() === $f['utenti']);
prova('Nessuna struttura persa', $db->query('SELECT id, name, slug, status FROM properties ORDER BY id')->fetchAll() == $f['strutture']);
prova('Nessuna sezione persa (il nucleo può aggiungersi)', (int) $db->query('SELECT COUNT(*) FROM sections')->fetchColumn() >= $f['sezioni']);
prova('I QR stampati restano validi', $db->query('SELECT property_id, token FROM qr_tokens ORDER BY id')->fetchAll() == $f['qr']);
prova('Gli abbonamenti restano sulla loro versione', array_map(fn($x) => [$x['account_id'], $x['package_version_id']], $db->query('SELECT account_id, package_version_id FROM subscriptions ORDER BY id')->fetchAll())
                                                   == array_map(fn($x) => [$x['account_id'], $x['package_version_id']], $f['abbonamenti']));
$ora = [];
foreach ($db->query('SELECT pf.package_version_id AS pv, f.code, pf.value FROM package_features pf JOIN features f ON f.id = pf.feature_id')->fetchAll() as $x) $ora[$x['pv'] . ':' . $x['code']] = $x['value'];
$cambiati = [];
foreach ($f['diritti'] as $x) if (($ora[$x['pv'] . ':' . $x['code']] ?? null) !== $x['value']) $cambiati[] = $x['pv'] . ':' . $x['code'];
prova('Le versioni vendute tengono tutti i loro diritti', !$cambiati, implode(', ', $cambiati));
prova('Nessun codice porta rimasto', (int) $db->query("SELECT COUNT(*) FROM sections WHERE door_code <> ''")->fetchColumn() === 0);
prova('Nessun codice porta nelle guide pubblicate', (int) $db->query("SELECT COUNT(*) FROM guide_versions WHERE snapshot LIKE '%door_code%' OR snapshot LIKE '%4729%'")->fetchColumn() === 0);
$prezzi = $db->query("SELECT p.code, pv.price_cents FROM package_versions pv JOIN packages p ON p.id = pv.package_id WHERE pv.is_current = 1 AND p.public = 1")->fetchAll(PDO::FETCH_KEY_PAIR);
prova('Listino nuovo in vendita', ($prezzi['essential'] ?? 0) == 8700 && ($prezzi['plus'] ?? 0) == 11700 && ($prezzi['portfolio'] ?? 0) == 11700
      && !isset($prezzi['portfolio2']) && !isset($prezzi['portfolio3']), json_encode($prezzi));
$pf = $db->query("SELECT pv.* FROM package_versions pv JOIN packages p ON p.id = pv.package_id WHERE p.code = 'portfolio' AND pv.is_current = 1")->fetch();
prova('Portfolio a quantità: 117 € + 60 € per struttura aggiuntiva, da 2', $pf && (int) $pf['per_property'] === 1 && (int) $pf['extra_price_cents'] === 6000 && (int) $pf['min_quantity'] === 2);
prova('Portfolio 2 e 3 restano nel database', (int) $db->query("SELECT COUNT(*) FROM packages WHERE code IN ('portfolio2', 'portfolio3')")->fetchColumn() === 2);
if ($f['p2acc']) {
    $s2 = $db->query("SELECT s.quantity, p.code FROM subscriptions s JOIN package_versions pv ON pv.id = s.package_version_id JOIN packages p ON p.id = pv.package_id
                      WHERE s.provider_subscription_id = 'sub_vecchio_p2'")->fetch();
    prova('Chi aveva comprato Portfolio 2 lo tiene, con 2 strutture', $s2 && $s2['code'] === 'portfolio2' && (int) $s2['quantity'] === 2, json_encode($s2));
    $a3 = $db->query('SELECT a.intended_quantity, p.code FROM accounts a JOIN package_versions pv ON pv.id = a.intended_package_version_id JOIN packages p ON p.id = pv.package_id WHERE a.id = ' . (int) $f['p3acc'])->fetch();
    prova('Chi aveva solo scelto Portfolio 3 passa al Portfolio nuovo per 3 strutture (stesso prezzo)', $a3 && $a3['code'] === 'portfolio' && (int) $a3['intended_quantity'] === 3, json_encode($a3));
}
prova('Pro non più in vendita', !isset($prezzi['pro']));
$demo = $db->query("SELECT slug FROM properties WHERE is_demo = 1 AND status = 'published' ORDER BY id")->fetchColumn();
$r = http("$BASE/g/$demo");
prova('La guida demo pubblicata si apre', $r['code'] === 200 && pulita($r['body']) && !str_contains($r['body'], '4729'));
foreach ($db->query("SELECT id FROM sections WHERE property_id = (SELECT id FROM properties WHERE slug = " . $db->quote($demo) . ")")->fetchAll(PDO::FETCH_COLUMN) as $sid) {
    $r = http("$BASE/g/$demo/$sid");
    if ($r['code'] !== 200 || !pulita($r['body']) || str_contains($r['body'], '4729')) prova("Sezione $sid della demo", false, (string) $r['code']);
}
prova('Tutte le sezioni della demo si aprono, senza codici', true);
$demoCover = $db->query("SELECT m.alt FROM properties p JOIN media m ON m.id = p.cover_media_id WHERE p.slug = " . $db->quote($demo))->fetchColumn();
prova('Copertina della demo allineata alla foto della landing', $demoCover === 'La facciata in pietra con la scalinata e i gerani', (string) $demoCover);
prova('…le altre strutture tengono la loro copertina', (int) $db->query("SELECT COUNT(*) FROM properties p JOIN media m ON m.id = p.cover_media_id WHERE p.slug <> " . $db->quote($demo) . " AND m.alt = 'La facciata in pietra con la scalinata e i gerani'")->fetchColumn() === 0);
$snap = $db->query("SELECT snapshot FROM guide_versions g JOIN properties p ON p.id = g.property_id WHERE p.slug = " . $db->quote($demo) . " ORDER BY g.version DESC LIMIT 1")->fetchColumn();
$cid = $db->query("SELECT cover_media_id FROM properties WHERE slug = " . $db->quote($demo))->fetchColumn();
prova('…e la guida demo pubblicata la mostra', str_contains((string) $snap, '"cover_id":' . (int) $cid) || str_contains((string) $snap, '"cover_id":"' . (int) $cid . '"'));
@unlink("$W/../c.txt"); // si entra da cliente, non con la sessione dell'installazione
$r = http("$BASE/accedi");
$lucia = (string) $db->query("SELECT email FROM users WHERE email LIKE 'lucia@%' ORDER BY id LIMIT 1")->fetchColumn();
$r = http("$BASE/accedi", ['_csrf' => tok($r['body']), 'email' => $lucia, 'password' => 'dimostrazione1']);
prova('Un cliente di prima entra con la sua password', $r['code'] === 302);
$pid = $db->query("SELECT p.id FROM properties p JOIN accounts a ON a.id = p.account_id JOIN users u ON u.id = a.user_id WHERE u.email = " . $db->quote($lucia))->fetchColumn();
foreach (["/pannello", "/pannello/$pid", "/pannello/$pid/lingue", "/pannello/$pid/aspetto", "/pannello/$pid/qr", "/pannello/$pid/procedura/checkin", "/account"] as $p) {
    $r = http("$BASE$p");
    prova("$p si apre", $r['code'] === 200 && pulita($r['body']));
}
$r = http("$BASE/");
prova('Una seconda richiesta non ripete le migrazioni', count($db->query('SELECT name FROM schema_migrations')->fetchAll()) === count($mig));
echo $falliti ? "$falliti prove NON superate.\n" : "Aggiornamento riuscito.\n";
exit($falliti ? 1 : 0);
