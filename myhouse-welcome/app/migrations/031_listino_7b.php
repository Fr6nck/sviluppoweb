<?php
/**
 * 031 — Listino 7B: più cose in Essential, niente varianti camera, testi delle schede.
 *
 * 1. Essential: sezioni aggiuntive da 4 a 6 e foto profilo dell'host compresa, in TUTTE le
 *    versioni di Essential (è un regalo: chi è già abbonato lo riceve subito).
 * 2. Varianti camera: la funzione non c'è più in nessun piano (room_variants = 0). La tabella
 *    room_variants resta com'è e non si legge più.
 * 3. Testi delle schede (packages.bullets e tagline), sostituendo solo le frasi di serie: se
 *    l'amministratore le ha già cambiate, restano le sue. «{sezioni}» lo sostituisce la vista
 *    con il numero di sezioni del catalogo.
 *
 * Solo aggiornamenti di valori, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */

use MHW\{Db, Entitlements};

return function (\PDO $pdo): void {
    $feature = fn(string $code) => (int) Db::val('SELECT id FROM features WHERE code = ?', [$code], 0);
    $metti = function (int $pvId, int $fid, string $valore): void {
        if (!$fid) return;
        $c = Db::one('SELECT value FROM package_features WHERE package_version_id = ? AND feature_id = ?', [$pvId, $fid]);
        if ($c === null) Db::insert('package_features', ['package_version_id' => $pvId, 'feature_id' => $fid, 'value' => $valore]);
        elseif ((string) $c['value'] !== $valore) Db::run('UPDATE package_features SET value = ? WHERE package_version_id = ? AND feature_id = ?', [$valore, $pvId, $fid]);
    };

    // 1. Essential, tutte le versioni.
    foreach (Db::all("SELECT pv.id FROM package_versions pv JOIN packages p ON p.id = pv.package_id WHERE p.code = 'essential'") as $v) {
        $sez = (string) Db::val('SELECT pf.value FROM package_features pf WHERE pf.package_version_id = ? AND pf.feature_id = ?', [$v['id'], $feature('sections')], '');
        if ($sez !== 'unlimited' && (int) $sez < 6) $metti((int) $v['id'], $feature('sections'), '6');
        $metti((int) $v['id'], $feature('profile_image'), '1');
    }
    // 2. Niente varianti camera, in nessun piano.
    if ($fv = $feature('room_variants')) Db::run("UPDATE package_features SET value = '0' WHERE feature_id = ? AND value <> '0'", [$fv]);

    // 3. Testi delle schede.
    $sostituisci = [
        "4 sezioni aggiuntive a scelta" => "6 sezioni aggiuntive a scelta",
        "Sezioni illimitate" => "Tutte le {sezioni} sezioni",
        "Foto e PDF nelle sezioni" => "Foto e PDF dentro le sezioni: la foto del cancello, le istruzioni della lavatrice",
        "Traduzioni suggerite: in omaggio per un anno" => "Scrivi in italiano, traduciamo noi in inglese, francese, tedesco e spagnolo: tu controlli e confermi. Il primo anno è in omaggio.",
    ];
    foreach (Db::all('SELECT id, code, bullets, tagline FROM packages') as $p) {
        $righe = preg_split('/\r\n|\n/', (string) $p['bullets']);
        $nuove = [];
        foreach ($righe as $r) {
            $t = trim($r);
            if ($t === 'Più di 10 strutture? Scrivici per un preventivo.') continue;   // ora è il bottone «Chiedi un preventivo»
            if ($p['code'] !== 'essential' && $t === "Foto profilo dell'host") continue;  // ora è già in Essential («Tutto di Essential»)
            $nuove[] = $sostituisci[$t] ?? $r;
        }
        if ($p['code'] === 'essential' && !in_array("Foto profilo dell'host", array_map('trim', $nuove), true)) {
            $dopo = array_search('Logo e foto di copertina', array_map('trim', $nuove), true);
            if ($dopo === false) $nuove[] = "Foto profilo dell'host"; else array_splice($nuove, $dopo + 1, 0, ["Foto profilo dell'host"]);
        }
        $bullets = implode("\n", $nuove);
        $tagline = (string) $p['tagline'];
        if ($p['code'] === 'plus' && str_contains($tagline, 'varianti')) {
            $tagline = 'Per B&B, case vacanza e agriturismi con ospiti da tutto il mondo: cinque lingue, foto e consigli sul territorio.';
        }
        if ($bullets !== (string) $p['bullets'] || $tagline !== (string) $p['tagline']) {
            Db::run('UPDATE packages SET bullets = ?, tagline = ? WHERE id = ?', [$bullets, $tagline, $p['id']]);
        }
    }
    Entitlements::forget();
};
