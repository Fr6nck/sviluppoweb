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
    // Solo da una versione che non ha ancora la 006: dopo, Portfolio 2 e 3 sono già fuori listino.
    try { if ($db->query("SELECT 1 FROM schema_migrations WHERE name LIKE '006%'")->fetchColumn()) $pv2 = $pv3 = false; }
    catch (PDOException) { /* versione senza registro delle migrazioni: niente 006 */ }
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
    prova('Portfolio 2 e 3 nel database vecchio: uno comprato, uno solo scelto', !$pv2 || $p2acc > 0, $pv2 ? '' : 'non serve per questa versione');
    // Una struttura ferma a «contenuti» della procedura a sette passi (migrazione 007).
    $fermaA = false;
    try {
        $db->exec("UPDATE properties SET wizard_step = 'contenuti' WHERE id = (SELECT p.id FROM properties p JOIN accounts a ON a.id = p.account_id
                   JOIN users u ON u.id = a.user_id WHERE u.email LIKE 'lucia@%' ORDER BY p.id LIMIT 1)");
        $fermaA = true;
    } catch (PDOException) { /* versione senza procedura guidata */ }
    // Fase 3B: sezioni nel formato di prima (voci «una per riga», parcheggio singolo,
    // passaggi di arrivo), da convertire con la 011. Solo dove le sezioni hanno già i dati a campi.
    $strutturate = [];
    try {
        $sid0 = $db->query("SELECT s.* FROM sections s JOIN properties p ON p.id = s.property_id JOIN accounts a ON a.id = p.account_id
                            JOIN users u ON u.id = a.user_id WHERE u.email LIKE 'lucia@%' ORDER BY s.id LIMIT 1")->fetch();
        $tr0 = $sid0 ? $db->query('SELECT * FROM section_translations WHERE section_id = ' . (int) $sid0['id'] . ' LIMIT 1')->fetch() : null;
        // Da una versione che ha già la 011 il formato di prima non c'è più: niente da convertire.
        $con011 = (function () use ($db) { try { return (bool) $db->query("SELECT 1 FROM schema_migrations WHERE name LIKE '011%'")->fetchColumn(); } catch (PDOException) { return false; } })();
        if (!$con011 && $sid0 && $tr0 && array_key_exists('data', $sid0) && array_key_exists('data', $tr0)) {
            $vecchie = [
                'emergency' => [['emergency_number' => '112'], ['it' => ['items' => ['Guardia medica: 075 123456 (notti e festivi)', 'Farmacia di turno: il turno è sulla porta']],
                                                            'en' => ['items' => ['Out-of-hours doctor: 075 123456', 'Duty pharmacy: rota on the door']]]],
                'waste' => [[], ['it' => ['items' => ['Umido martedì e venerdì', 'Vetro nella campana in piazza'], 'note' => 'Bidoni in cortile.'],
                                 'en' => ['items' => ['Food waste on Tuesday and Friday']]]],
                'arrival' => [['address' => 'Via Vecchia 1, Montepulciano', 'maps_url' => ''], ['it' => ['steps' => ['Esci a Chiusi.', 'Segui per Montepulciano.']],
                                                                                              'en' => ['steps' => ['Exit at Chiusi.']]]],
                'parking' => [['address' => 'Piazza Grande', 'maps_url' => 'https://maps.google.com/?q=Piazza+Grande'],
                              ['it' => ['parking_type' => 'Parcheggio pubblico', 'instructions' => 'Strisce bianche gratis.', 'cost' => 'Gratis'],
                               'en' => ['parking_type' => 'Public car park']]],
            ];
            foreach ($vecchie as $kind => [$dati, $testi]) {
                $sid = $copia('sections', ['kind' => $kind, 'data' => json_encode($dati, JSON_UNESCAPED_UNICODE), 'is_core' => 0, 'is_active' => 1,
                                           'media_id' => null, 'pdf_media_id' => null, 'position' => 90] + $sid0);
                foreach ($testi as $loc => $t) $copia('section_translations', ['section_id' => $sid, 'locale' => $loc, 'title' => '', 'data' => json_encode($t, JSON_UNESCAPED_UNICODE)] + $tr0);
                $strutturate[$kind] = $sid;
            }
        }
    } catch (PDOException $e) { echo '  (sezioni del formato di prima non inserite: ', $e->getMessage(), ")\n"; }
    prova('Sezioni del formato di prima (emergenze, rifiuti, come arrivare, parcheggio)', count($strutturate) === 4 || !$strutturate, $strutturate ? '' : 'versione senza sezioni a campi');
    $con007 = (function () use ($db) { try { return (bool) $db->query("SELECT 1 FROM schema_migrations WHERE name LIKE '007%'")->fetchColumn(); } catch (PDOException) { return false; } })();
    $f = [
        'p2acc' => (int) $p2acc, 'p3acc' => (int) $p3acc, 'contenuti' => $fermaA, 'con007' => $con007, 'strutturate' => $strutturate,
        'utenti' => (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'strutture' => $db->query('SELECT id, name, slug, status FROM properties ORDER BY id')->fetchAll(),
        'sezioni' => (int) $db->query('SELECT COUNT(*) FROM sections')->fetchColumn(),
        'abbonamenti' => $db->query('SELECT account_id, package_version_id, status FROM subscriptions ORDER BY id')->fetchAll(),
        'diritti' => $db->query('SELECT pf.package_version_id AS pv, f.code, pf.value FROM package_features pf JOIN features f ON f.id = pf.feature_id')->fetchAll(),
        'qr' => $db->query('SELECT property_id, token FROM qr_tokens ORDER BY id')->fetchAll(),
        // Fase 3: tutto quello che le conversioni toccano, per controllare che niente si perda.
        'testi' => (function () use ($db) { try { return $db->query('SELECT id, section_id, locale, title, data FROM section_translations ORDER BY id')->fetchAll(); } catch (PDOException) { return []; } })(),
        'datiSezioni' => (function () use ($db) { try { return $db->query('SELECT id, kind, data FROM sections ORDER BY id')->fetchAll(); } catch (PDOException) { return []; } })(),
        'luoghi' => (function () use ($db) { try { return $db->query('SELECT * FROM places ORDER BY id')->fetchAll(); } catch (PDOException) { return []; } })(),
        'testiLuoghi' => (function () use ($db) { try { return $db->query('SELECT * FROM place_translations ORDER BY id')->fetchAll(); } catch (PDOException) { return []; } })(),
        // Le statistiche delle guide vere (la 003 azzera di proposito quelle della demo).
        // Qualche lettura vera prima dell'aggiornamento: la 014 ricostruisce la tabella e non deve perderne.
        'eventiSemina' => (function () use ($db) { try {
            $pid = (int) $db->query('SELECT id FROM properties ORDER BY id LIMIT 1')->fetchColumn();
            $st = $db->prepare("INSERT INTO analytics_events (property_id, kind, locale, day, created_at) VALUES (?, 'guide_view', 'it', ?, ?)");
            for ($i = 0; $i < 7; $i++) $st->execute([$pid, gmdate('Y-m-d'), gmdate('Y-m-d\\TH:i:s\\Z')]);
            return 7; } catch (PDOException) { return 0; } })(),
        // Tutti gli eventi, se la 003 (che azzera di proposito quelli della demo) è già passata; altrimenti -1.
        'eventi' => (function () use ($db) { try { return $db->query("SELECT 1 FROM schema_migrations WHERE name LIKE '003%'")->fetchColumn()
                       ? (int) $db->query('SELECT COUNT(*) FROM analytics_events')->fetchColumn() : -1; } catch (PDOException) { return -1; } })(),
        'media' => (function () use ($db) { try { return (int) $db->query('SELECT COUNT(*) FROM media')->fetchColumn(); } catch (PDOException) { return 0; } })(),
        'host' => (function () use ($db) { try { return $db->query('SELECT id, host_name, host_phone, host_whatsapp FROM properties ORDER BY id')->fetchAll(); } catch (PDOException) { return []; } })(),
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
prova('Migrazioni registrate, fino alla 007', count($mig) >= 7 && in_array('007_passi_procedura.php', $mig, true), implode(', ', $mig));
prova('Nessun utente perso', (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() === $f['utenti']);
prova('Nessuna struttura persa', $db->query('SELECT id, name, slug, status FROM properties ORDER BY id')->fetchAll() == $f['strutture']);
prova('Nessuna sezione persa (il nucleo può aggiungersi)', (int) $db->query('SELECT COUNT(*) FROM sections')->fetchColumn() >= $f['sezioni']);
prova('I QR stampati restano validi', $db->query('SELECT property_id, token FROM qr_tokens ORDER BY id')->fetchAll() == $f['qr']);
prova('Gli abbonamenti restano sulla loro versione', array_map(fn($x) => [$x['account_id'], $x['package_version_id']], $db->query('SELECT account_id, package_version_id FROM subscriptions ORDER BY id')->fetchAll())
                                                   == array_map(fn($x) => [$x['account_id'], $x['package_version_id']], $f['abbonamenti']));
$ora = [];
foreach ($db->query('SELECT pf.package_version_id AS pv, f.code, pf.value FROM package_features pf JOIN features f ON f.id = pf.feature_id')->fetchAll() as $x) $ora[$x['pv'] . ':' . $x['code']] = $x['value'];
$cambiati = [];
// L'unico cambio voluto (022): le traduzioni suggerite si accendono per Plus e Portfolio, anche già venduti.
$pvTrad = array_map('intval', $db->query("SELECT v.id FROM package_versions v JOIN packages p ON p.id = v.package_id WHERE p.code IN ('plus', 'portfolio', 'portfolio2', 'portfolio3')")->fetchAll(PDO::FETCH_COLUMN));
$accese = [];
foreach ($f['diritti'] as $x) {
    $adesso = $ora[$x['pv'] . ':' . $x['code']] ?? null;
    if ($adesso === $x['value']) continue;
    if ($x['code'] === 'auto_translation' && $adesso === '1' && in_array((int) $x['pv'], $pvTrad, true)) { $accese[] = $x['pv']; continue; }
    $cambiati[] = $x['pv'] . ':' . $x['code'];
}
prova('Le versioni vendute tengono tutti i loro diritti (in più: traduzioni suggerite in Plus e Portfolio)', !$cambiati, implode(', ', $cambiati) . ' · accese in ' . implode(',', $accese));
$essTrad = $db->query("SELECT pf.value FROM package_features pf JOIN features f ON f.id = pf.feature_id JOIN package_versions v ON v.id = pf.package_version_id
                       JOIN packages p ON p.id = v.package_id WHERE f.code = 'auto_translation' AND p.code = 'essential'")->fetchAll(PDO::FETCH_COLUMN);
prova('022 · traduzioni suggerite: Essential resta senza, Plus e Portfolio le hanno', !in_array('1', $essTrad, true) && count($accese) > 0
      && (bool) $db->query("SELECT 1 FROM features WHERE code = 'auto_translation' AND label = 'Traduzioni suggerite'")->fetchColumn());
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
    if (in_array((int) $sid, array_map('intval', $f['strutturate'] ?? []), true)) continue;   // aggiunte dalla prova, non ancora pubblicate
    $r = http("$BASE/g/$demo/$sid");
    if ($r['code'] !== 200 || !pulita($r['body']) || str_contains($r['body'], '4729')) prova("Sezione $sid della demo", false, (string) $r['code']);
}
prova('Tutte le sezioni della demo si aprono, senza codici', true);
$demoCover = $db->query("SELECT m.alt FROM properties p JOIN media m ON m.id = p.cover_media_id WHERE p.slug = " . $db->quote($demo))->fetchColumn();
// La 005 allinea le demo di prima; una demo installata dopo la nuova copertina (b34de83) ha già il portone.
prova('Copertina della demo allineata alla foto della landing', in_array($demoCover, ['La facciata in pietra con la scalinata e i gerani', 'Il portone in legno ad arco, tra la pietra, i fiori rampicanti e i vasi di terracotta'], true), (string) $demoCover);
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
if (($f['contenuti'] ?? false) && !($f['con007'] ?? false)) prova('Migrazione 007: «contenuti» diventa «sezioni»', $db->query("SELECT wizard_step FROM properties WHERE id = " . (int) $pid)->fetchColumn() === 'sezioni');
if (!($f['con007'] ?? false)) prova('…e nessun passo vecchio rimasto', (int) $db->query("SELECT COUNT(*) FROM properties WHERE wizard_step IN ('checkin', 'contenuti', 'lingue', 'anteprima')")->fetchColumn() === 0);
$r = http("$BASE/pannello/$pid/procedura/contenuti");
prova('Il vecchio indirizzo «contenuti» porta a «sezioni» (301)', $r['code'] === 301 && str_ends_with($r['loc'], "/pannello/$pid/procedura/sezioni"), $r['code'] . ' ' . $r['loc']);
foreach (["/pannello", "/pannello/$pid", "/pannello/$pid/lingue", "/pannello/$pid/aspetto", "/pannello/$pid/qr", "/pannello/$pid/procedura/sezioni", "/pannello/$pid/procedura/pubblica", "/account"] as $p) {
    $r = http("$BASE$p");
    prova("$p si apre", $r['code'] === 200 && pulita($r['body']));
}
// ----------------------------------------------- Fase 3: niente si perde
$ora = [];
foreach ($db->query('SELECT id, title, data FROM section_translations')->fetchAll() as $t) $ora[$t['id']] = $t;
// «Contiene»: ogni chiave di prima c'è ancora col suo valore; le conversioni possono solo aggiungere (dalla 017 le righe hanno campi nuovi).
$contiene = function (mixed $prima, mixed $dopo) use (&$contiene): bool {
    if (!is_array($prima) || !is_array($dopo)) return $prima === $dopo;
    foreach ($prima as $k => $v) if (!array_key_exists($k, $dopo) || !$contiene($v, $dopo[$k])) return false;
    return true;
};
$persi = [];
foreach ($f['testi'] ?? [] as $t) {
    $nuovo = $ora[$t['id']] ?? null;
    if (!$nuovo || $nuovo['title'] !== $t['title']) { $persi[] = "titolo {$t['id']}"; continue; }
    $vecchi = json_decode((string) $t['data'], true) ?: []; $nuovi = json_decode((string) $nuovo['data'], true) ?: [];
    foreach ($vecchi as $k => $v) if (!$contiene($v, $nuovi[$k] ?? null)) $persi[] = "{$t['id']}:$k";
}
prova('Fase 3 · nessun testo né traduzione perso (i vecchi campi restano)', !$persi, implode(', ', array_slice($persi, 0, 6)));
$oraS = [];
foreach ($db->query('SELECT id, data FROM sections')->fetchAll() as $x) $oraS[$x['id']] = json_decode((string) $x['data'], true) ?: [];
$persiS = [];
foreach ($f['datiSezioni'] ?? [] as $x) foreach ((json_decode((string) $x['data'], true) ?: []) as $k => $v) if (!$contiene($v, $oraS[$x['id']][$k] ?? null)) $persiS[] = "{$x['id']}:$k";
prova('Fase 3 · nessun dato comune perso (reti, indirizzi, link)', !$persiS, implode(', ', $persiS));
// Le colonne di prima con gli stessi valori (le migrazioni vecchie possono averne aggiunte).
$uguali = function (array $prima, array $dopo): bool {
    $perId = []; foreach ($dopo as $x) $perId[$x['id']] = $x;
    foreach ($prima as $x) foreach ($x as $k => $v) if (!isset($perId[$x['id']]) || (string) ($perId[$x['id']][$k] ?? '') !== (string) $v) return false;
    return true;
};
// Dalla 016 (fase 6B) categoria ed etichetta riconosciute diventano chiavi e il loro testo si svuota:
// per quei luoghi il testo vuoto è giusto, per tutti gli altri deve restare com'era.
$chiaviLuoghi = [];
foreach ($db->query("SELECT * FROM places")->fetchAll() as $x) $chiaviLuoghi[$x['id']] = ['category' => (string) ($x['category_key'] ?? ''), 'badge' => (string) ($x['badge_key'] ?? '')];
$testiOra = $db->query('SELECT * FROM place_translations')->fetchAll();
foreach ($testiOra as &$x) foreach (['category', 'badge'] as $c) {
    if (($chiaviLuoghi[$x['place_id']][$c] ?? '') !== '' && (string) $x[$c] === '') {
        foreach ($f['testiLuoghi'] ?? [] as $v) if ($v['id'] === $x['id']) $x[$c] = $v[$c];
    }
}
unset($x);
prova('Fase 3 · luoghi e loro testi invariati (in 6B le categorie riconosciute diventano chiavi)', $uguali($f['luoghi'] ?? [], $db->query('SELECT * FROM places')->fetchAll())
      && $uguali($f['testiLuoghi'] ?? [], $testiOra));
prova('Fase 3 · nessuna foto persa', (int) $db->query('SELECT COUNT(*) FROM media')->fetchColumn() >= (int) ($f['media'] ?? 0));
$senza = [];
foreach ($f['host'] ?? [] as $h) {
    if (trim($h['host_name'] . $h['host_phone'] . $h['host_whatsapp']) === '') continue;
    $cc = $db->query('SELECT phone FROM property_contacts WHERE property_id = ' . (int) $h['id'])->fetchAll(PDO::FETCH_COLUMN);
    $numeri = array_filter([trim($h['host_phone']), trim($h['host_whatsapp'])]);
    if (!$cc || array_diff($numeri, $cc)) $senza[] = $h['id'];
}
prova('Fase 3 · contatti copiati da nome, telefono e WhatsApp di prima, nessun numero perso', !$senza, implode(', ', $senza));
$conv = [];
foreach ($f['testi'] ?? [] as $t) {
    $vecchi = json_decode((string) $t['data'], true) ?: [];
    if (trim((string) ($vecchi['checkout_keys'] ?? '')) === '') continue;
    $nuovi = json_decode((string) $ora[$t['id']]['data'], true) ?: [];
    if (!in_array(true, array_map(fn($v) => str_ends_with((string) $v, ': ' . trim($vecchi['checkout_keys'])), (array) ($nuovi['checkout_steps'] ?? [])), true)) $conv[] = $t['id'];
}
prova('Fase 3 · partenza: le vecchie caselle sono voci della lista, lingua per lingua', !$conv, implode(', ', $conv));
$reti = [];
foreach ($f['datiSezioni'] ?? [] as $x) {
    $v = json_decode((string) $x['data'], true) ?: [];
    if ($x['kind'] !== 'wifi' || trim((string) ($v['network'] ?? '')) === '') continue;
    if (($oraS[$x['id']]['networks'][0]['ssid'] ?? '') !== $v['network'] || ($oraS[$x['id']]['networks'][0]['password'] ?? '') !== (string) ($v['password'] ?? '')) $reti[] = $x['id'];
}
prova('Fase 3 · Wi-Fi: la rete di prima è la prima riga', !$reti, implode(', ', $reti));
// La guida demo pubblicata con il codice vecchio: si legge nel formato nuovo, senza ripubblicare.
foreach (['it', 'en'] as $l) {
    $r = http("$BASE/g/$demo?l=$l");
    prova("Fase 3 · guida demo pubblicata ($l): «Contatta …» dai contatti di prima", $r['code'] === 200 && pulita($r['body']) && preg_match('/(Contatta|Contact) \S+/', $r['body']) === 1);
    $r = http("$BASE/g/$demo/commiato?l=$l");
    if (array_filter($f['testi'] ?? [], fn($t) => str_contains((string) $t['data'], 'checkout_keys')))
    prova("Fase 3 · congedo ($l): la lista di partenza dalle vecchie caselle", $r['code'] === 200 && pulita($r['body']) && preg_match('/(Chiavi|Keys): /', $r['body']) === 1);
}
$wifiDemo = $db->query("SELECT s.id FROM sections s JOIN properties p ON p.id = s.property_id WHERE p.slug = " . $db->quote($demo) . " AND s.kind = 'wifi'")->fetchColumn();
if ($wifiDemo) {
    $r = http("$BASE/g/$demo/$wifiDemo");
    prova('Fase 3 · Wi-Fi della demo pubblicata: rete, password e QR', $r['code'] === 200 && pulita($r['body']) && str_contains($r['body'], 'data:image/png;base64,'));
}

// ------------------------------- Fase 3B: sezioni strutturate (011) e fatturazione (012)
$st = $f['strutturate'] ?? [];
$sezione = function (int $sid) use ($db): array {
    $d = json_decode((string) $db->query('SELECT data FROM sections WHERE id = ' . $sid)->fetchColumn(), true) ?: [];
    $t = [];
    foreach ($db->query('SELECT locale, data FROM section_translations WHERE section_id = ' . $sid)->fetchAll() as $x) $t[$x['locale']] = json_decode((string) $x['data'], true) ?: [];
    return [$d, $t];
};
if ($st) {
    [$d, $t] = $sezione((int) $st['emergency']);
    $nomi = array_column($t['it']['contacts'] ?? [], 'name', 'id');
    prova('Fase 3B · emergenze: le voci sono righe nome · telefono · nota, in ogni lingua', count($d['contacts'] ?? []) === 2 && $d['contacts'][0]['phone'] === '075 123456'
          && $d['contacts'][1]['phone'] === '' && ($nomi[$d['contacts'][0]['id']] ?? '') === 'Guardia medica' && ($t['it']['contacts'][0]['note'] ?? '') === 'notti e festivi'
          && ($t['en']['contacts'][0]['name'] ?? '') === 'Out-of-hours doctor' && ($t['en']['contacts'][1]['id'] ?? '') === $d['contacts'][1]['id']
          && count($t['it']['items'] ?? []) === 2);
    [$d, $t] = $sezione((int) $st['waste']);
    prova('Fase 3B · rifiuti: una riga per voce, col testo intero; umido e giorni riconosciuti', count($d['bins'] ?? []) === 2 && $d['bins'][0]['type'] === 'umido'
          && $d['bins'][0]['days'] === [2, 5] && $d['bins'][1]['type'] === 'vetro' && ($t['it']['bins'][1]['label'] ?? '') === 'Vetro nella campana in piazza'
          && ($t['en']['bins'][0]['label'] ?? '') === 'Food waste on Tuesday and Friday' && ($t['it']['note'] ?? '') === 'Bidoni in cortile.');
    [$d, $t] = $sezione((int) $st['arrival']);
    prova('Fase 3B · come arrivare: i passaggi di prima sono la prima scheda', count($d['routes'] ?? []) === 1 && $d['routes'][0]['mode'] === ''
          && ($t['it']['routes'][0]['steps'] ?? '') === "Esci a Chiusi.\nSegui per Montepulciano." && ($t['en']['routes'][0]['steps'] ?? '') === 'Exit at Chiusi.'
          && $d['address'] === 'Via Vecchia 1, Montepulciano');
    [$d, $t] = $sezione((int) $st['parking']);
    prova('Fase 3B · parcheggio: quello di prima è la prima riga (tipo, indirizzo, link, costo, istruzioni)', count($d['options'] ?? []) === 1
          && $d['options'][0]['address'] === 'Piazza Grande' && $d['options'][0]['maps_url'] === 'https://maps.google.com/?q=Piazza+Grande'
          && ($t['it']['options'][0]['name'] ?? '') === 'Parcheggio pubblico' && ($t['it']['options'][0]['cost'] ?? '') === 'Gratis'
          && ($t['it']['options'][0]['instructions'] ?? '') === 'Strisce bianche gratis.' && ($t['en']['options'][0]['name'] ?? '') === 'Public car park');
    prova('Fase 6C · parcheggio: «Gratis» non è un importo, resta intero in «Nota sul costo»', ($d['options'][0]['cost_hour'] ?? 'x') === '' && ($d['options'][0]['cost_day'] ?? 'x') === ''
          && ($t['it']['options'][0]['cost_note'] ?? '') === 'Gratis');
}
// Le stesse sezioni nell'anteprima di Lucia (entrata più sopra con la sua password), in italiano e in inglese.
if ($st) {
    $pidL = (int) $db->query('SELECT property_id FROM sections WHERE id = ' . (int) $st['emergency'])->fetchColumn();
    $vedi = fn(string $k, string $l) => http("$BASE/pannello/$pidL/anteprima/{$st[$k]}?l=$l")['body'];
    $it = [$vedi('emergency', 'it'), $vedi('waste', 'it'), $vedi('arrival', 'it'), $vedi('parking', 'it')];
    prova('Fase 3B · anteprima (it): «Chiama» guardia medica, umido con i giorni, scheda di arrivo, parcheggio con Maps',
          str_contains($it[0], 'href="tel:075123456"') && str_contains($it[0], 'aria-label="Chiama Guardia medica"')
          && str_contains($it[1], 'Umido') && str_contains($it[1], 'martedì, venerdì') && str_contains($it[1], 'Vetro nella campana in piazza')
          && str_contains($it[2], 'Esci a Chiusi.') && str_contains($it[2], 'Indicazioni')
          && str_contains($it[3], 'Parcheggio pubblico') && str_contains($it[3], 'Strisce bianche gratis.') && str_contains($it[3], 'maps.google.com/?q=Piazza+Grande'));
    $en = [$vedi('emergency', 'en'), $vedi('waste', 'en'), $vedi('arrival', 'en')];
    prova('Fase 3B · anteprima (en): testi e etichette in inglese, riga per riga',
          str_contains($en[0], 'Out-of-hours doctor') && str_contains($en[1], 'Food waste on Tuesday and Friday') && str_contains($en[1], 'Tuesday, Friday')
          && str_contains($en[2], 'Exit at Chiusi.') && str_contains($en[2], 'Directions'));
}
// Le guide pubblicate prima (parcheggio di Marco nella demo, se c'è) si leggono nel formato nuovo.
$park = $db->query("SELECT s.id, p.slug FROM sections s JOIN properties p ON p.id = s.property_id
                    WHERE s.kind = 'parking' AND p.status = 'published' AND s.data LIKE '%Lo Re%' ORDER BY s.id LIMIT 1")->fetch();
if ($park && str_contains((string) $db->query("SELECT snapshot FROM guide_versions gv JOIN properties p ON p.id = gv.property_id WHERE p.slug = " . $db->quote($park['slug']) . ' ORDER BY gv.version DESC LIMIT 1')->fetchColumn(), 'parking_type')) {
    $r = http("$BASE/g/{$park['slug']}/{$park['id']}");
    prova('Fase 3B · parcheggio di una guida pubblicata prima: si legge come riga, con Maps', $r['code'] === 200 && pulita($r['body'])
          && str_contains($r['body'], 'Parcheggio pubblico gratuito') && str_contains($r['body'], 'Viale Lo Re'));
}
$colF = array_filter(['billing_type', 'vat', 'cf', 'sdi', 'pec', 'billing_province'], fn($c) => $db->query("SELECT COUNT(*) FROM pragma_table_info('accounts') WHERE name = '$c'")->fetchColumn());
prova('Fase 3B · colonne di fatturazione aggiunte, vuote per gli account di prima', count($colF) === 6 && (int) $db->query("SELECT COUNT(*) FROM accounts WHERE billing_type <> ''")->fetchColumn() === 0);
prova('Fase 3B · coordinate dei luoghi', (int) $db->query("SELECT COUNT(*) FROM pragma_table_info('places') WHERE name IN ('lat', 'lng')")->fetchColumn() === 2);

// ------------------------- Fase 5: firma (013), crescita (014)
$eventiOra = (int) $db->query('SELECT COUNT(*) FROM analytics_events WHERE property_id IS NOT NULL')->fetchColumn();
if (($f['eventi'] ?? -1) >= 0) prova('Fase 5 · nessun evento delle statistiche perso nella ricostruzione della tabella', $eventiOra >= (int) $f['eventi'], $eventiOra . ' / ' . $f['eventi']);
$nn = $db->query("SELECT \"notnull\" FROM pragma_table_info('analytics_events') WHERE name = 'property_id'")->fetchColumn();
prova('Fase 5 · eventi del funnel senza struttura ammessi', (string) $nn === '0');
$hb = (int) $db->query("SELECT id FROM features WHERE code = 'hide_branding'")->fetchColumn();
$plusOra = $db->query("SELECT pv.id FROM package_versions pv JOIN packages p ON p.id = pv.package_id WHERE p.code = 'plus' AND pv.is_current = 1")->fetchColumn();
prova('Fase 5 · Plus in vendita con la firma nascondibile, in una versione nuova', $hb > 0
      && $db->query("SELECT value FROM package_features WHERE package_version_id = " . (int) $plusOra . " AND feature_id = $hb")->fetchColumn() === '1');
$r = http("$BASE/g/$demo");
prova('Fase 5 · la guida demo pubblicata prima mostra la firma', $r['code'] === 200 && str_contains($r['body'], 'Guida creata con MyHouse Welcome'));
$r = http("$BASE/g/$demo/commiato");
prova('Fase 5 · commiato senza recensioni (non compilate)', $r['code'] === 200 && pulita($r['body']) && !str_contains($r['body'], 'Ti è piaciuto il soggiorno?'));
$r = http("$BASE/");
prova('Fase 5 · landing con FAQ, confronto e scene', $r['code'] === 200 && str_contains($r['body'], 'Confronta tutti i piani') && str_contains($r['body'], 'class="faq__voce"'));
prova('Fase 5 · nessuna email di richiamo partita per le bozze di prima', !is_file("$W/app/storage/logs/mail.log") || !str_contains((string) file_get_contents("$W/app/storage/logs/mail.log"), 'procedura/'));

// Fase 6
prova('Fase 6A · tipologia «Altro» scritta a mano', (int) $db->query("SELECT COUNT(*) FROM pragma_table_info('properties') WHERE name = 'property_type_other'")->fetchColumn() === 1);
$conChiave = (int) $db->query("SELECT COUNT(*) FROM places WHERE category_key <> ''")->fetchColumn();
$testiNoti = 0;
foreach ($f['testiLuoghi'] ?? [] as $v) if (in_array(mb_strtolower(trim((string) $v['category'])), ['trattoria', 'ristorante', 'colazione', 'spiaggia', 'museo', 'bar', 'pizzeria', 'gelateria', 'borgo'], true)) $testiNoti++;
prova('Fase 6B · categorie riconosciute convertite in chiavi (016), le altre restano testo', $testiNoti === 0 || $conChiave > 0, "$conChiave luoghi con chiave, $testiNoti testi noti prima");
// Fase 6C (017): nessun prezzo o costo scritto a mano si perde. O è diventato importo, o è intero nella nota; il vecchio campo resta nel JSON.
$kindDi = []; foreach ($f['datiSezioni'] ?? [] as $v) $kindDi[(int) $v['id']] = $v['kind'];
$datiOra = []; foreach ($db->query("SELECT id, data FROM sections WHERE kind IN ('extras', 'parking')")->fetchAll() as $v) $datiOra[(int) $v['id']] = json_decode((string) $v['data'], true) ?: [];
$persi = []; $controllati = 0;
foreach ($f['testi'] ?? [] as $v) {
    $k = $kindDi[(int) $v['section_id']] ?? ''; if (!in_array($k, ['extras', 'parking'], true)) continue;
    [$campo, $vecchio, $nota, $importi] = $k === 'extras' ? ['items', 'price', 'price_note', ['amount']] : ['options', 'cost', 'cost_note', ['cost_hour', 'cost_day']];
    $ora = json_decode((string) $db->query('SELECT data FROM section_translations WHERE id = ' . (int) $v['id'])->fetchColumn(), true) ?: [];
    foreach ((array) ((json_decode((string) $v['data'], true) ?: [])[$campo] ?? []) as $riga) {
        $testo = trim((string) ($riga[$vecchio] ?? '')); if ($testo === '' || !isset($riga['id'])) continue;
        $controllati++;
        $dopo = []; foreach ((array) ($ora[$campo] ?? []) as $x) if (($x['id'] ?? null) === $riga['id']) $dopo = $x;
        $comune = []; foreach ((array) ($datiOra[(int) $v['section_id']][$campo] ?? []) as $x) if (($x['id'] ?? null) === $riga['id']) $comune = $x;
        $importo = array_filter(array_map(fn($c) => (string) ($comune[$c] ?? ''), $importi));
        if (($dopo[$vecchio] ?? '') !== ($riga[$vecchio] ?? '') || (!$importo && ($dopo[$nota] ?? '') !== $testo)) $persi[] = $v['locale'] . ': ' . $testo;
    }
}
prova('Fase 6C · prezzi degli extra e costi dei parcheggi: niente testo perso (017)', !$persi, $controllati . ' controllati' . ($persi ? '; persi: ' . implode(' | ', $persi) : ''));
prova('Fase 6C · migrazione 017 applicata', (bool) $db->query("SELECT 1 FROM schema_migrations WHERE name LIKE '017%'")->fetchColumn());
$r = http("$BASE/g/$demo");
prova('Fase 6 · la guida demo pubblicata prima si apre ancora', $r['code'] === 200 && pulita($r['body']));

$r = http("$BASE/");
prova('Una seconda richiesta non ripete le migrazioni', count($db->query('SELECT name FROM schema_migrations')->fetchAll()) === count($mig));
echo $falliti ? "$falliti prove NON superate.\n" : "Aggiornamento riuscito.\n";
exit($falliti ? 1 : 0);
