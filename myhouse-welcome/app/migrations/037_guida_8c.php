<?php
/**
 * 037 — La guida 8C: email per gli ospiti, lingua in più, riscaldamento e aria condizionata separati.
 *
 * properties.guest_email: «Email per gli ospiti» (Impostazioni → Chi risponde agli ospiti), facoltativa:
 *   il bottone «Mandaci un'email» della pagina delle recensioni, da 1 a 4 stelle.
 * features.lingua_extra: le lingue aggiuntive (nl, pt, pl, ru, zh, ja, sv, da, cs) oltre a quelle di base,
 *   1 per Plus e Portfolio, 0 per gli altri; si cambia in Amministrazione → Piani. «locales» resta com'è.
 * sections «clima» → «riscaldamento» (stesso id, stessi testi e traduzioni); se l'aria condizionata c'è
 *   (ac_mode non vuoto né «no») nasce subito dopo una sezione «aria» con i campi ac_* e le loro traduzioni,
 *   attiva come la prima: disattivata se la struttura è già al limite di sezioni del piano (la novità lo dice).
 *   I campi ac_* passano alla sezione nuova; niente testo si perde. La vecchia «clima» resta nel catalogo,
 *   nascosta, per le istantanee già pubblicate.
 * packages.bullets: «Italiano e inglese» → «2 lingue: la tua e una a scelta»; «5 lingue: italiano, …»
 *   → «6 lingue: …», solo dove c'è ancora il testo di serie.
 *
 * Solo aggiunte e conversioni, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */

use MHW\{Db, Migrator, Entitlements, Support, I18n};

return function (\PDO $pdo): void {
    if (!Migrator::columnExists('properties', 'guest_email')) {
        $pdo->exec("ALTER TABLE properties ADD COLUMN guest_email VARCHAR(190) NOT NULL DEFAULT ''");
    }

    // ------------------------------------------------------------ la lingua in più
    if (!Db::val("SELECT id FROM features WHERE code = 'lingua_extra'")) {
        Db::insert('features', ['code' => 'lingua_extra', 'label' => 'Lingua aggiuntiva', 'kind' => 'int', 'default_value' => '0']);
    }
    $fid = (int) Db::val("SELECT id FROM features WHERE code = 'lingua_extra'");
    foreach (Db::all('SELECT pv.id, p.code FROM package_versions pv JOIN packages p ON p.id = pv.package_id') as $v) {
        if (Db::val('SELECT 1 FROM package_features WHERE package_version_id = ? AND feature_id = ?', [$v['id'], $fid])) continue;
        $si = $v['code'] === 'plus' || str_starts_with((string) $v['code'], 'portfolio');
        Db::insert('package_features', ['package_version_id' => $v['id'], 'feature_id' => $fid, 'value' => $si ? '1' : '0']);
    }
    Entitlements::forget();

    // ------------------------------------------- riscaldamento e aria condizionata
    $caldo = ['heating_mode', 'heating_times', 'heating_temp', 'heating_period', 'heating_how'];
    $fresco = ['ac_mode', 'ac_times', 'ac_temp', 'ac_how'];
    // I titoli di serie della vecchia sezione: se l'host non l'ha rinominata, prende il titolo nuovo.
    $titoliVecchi = [];
    foreach (I18n::BASE as $l) {
        $f = MHW_APP . '/lang/clima/' . $l . '.php';
        if (is_file($f)) $titoliVecchi[] = mb_strtolower(trim((string) ((require $f)['kind.clima'] ?? '')));
    }
    foreach (Db::all("SELECT s.*, p.account_id FROM sections s JOIN properties p ON p.id = s.property_id WHERE s.kind = 'clima' ORDER BY s.id") as $s) {
        Db::tx(function () use ($s, $caldo, $fresco, $titoliVecchi) {
            $dati = json_decode((string) $s['data'], true) ?: [];
            $modoAc = (string) ($dati['ac_mode'] ?? '');
            $traduzioni = Db::all('SELECT * FROM section_translations WHERE section_id = ?', [$s['id']]);
            $conAria = $modoAc !== '' && $modoAc !== 'no';
            if ($conAria) {
                // La sezione nuova, subito dopo: le altre scalano di un posto.
                Db::run('UPDATE sections SET position = position + 1 WHERE property_id = ? AND position > ?', [$s['property_id'], (int) $s['position']]);
                $datiAria = array_intersect_key($dati, array_flip($fresco));
                if (array_key_exists('risparmio', $dati)) $datiAria['risparmio'] = $dati['risparmio'];
                $attiva = (int) $s['is_active'];
                if ($attiva) {
                    $max = Entitlements::limit((int) $s['account_id'], 'sections', 4);
                    $attive = (int) Db::val('SELECT COUNT(*) FROM sections WHERE property_id = ? AND is_core = 0 AND is_active = 1', [$s['property_id']], 0);
                    if ($attive + 1 > $max) $attiva = 0;   // al limite del piano: nasce spenta, la novità lo dice
                }
                $aid = Db::insert('sections', [
                    'property_id' => $s['property_id'], 'kind' => 'aria', 'icon' => 'aria', 'color' => (string) ($s['color'] ?? 'terracotta'),
                    'position' => (int) $s['position'] + 1, 'is_core' => 0, 'is_active' => $attiva,
                    'data' => json_encode($datiAria ?: new \stdClass(), JSON_UNESCAPED_UNICODE), 'created_at' => Support::now(),
                ]);
                foreach ($traduzioni as $t) {
                    $td = json_decode((string) $t['data'], true) ?: [];
                    $tAria = array_intersect_key($td, array_flip($fresco));
                    Db::insert('section_translations', [
                        'section_id' => $aid, 'locale' => $t['locale'], 'title' => I18n::t((string) $t['locale'], 'kind.aria'), 'body' => '',
                        'data' => json_encode($tAria ?: new \stdClass(), JSON_UNESCAPED_UNICODE),
                        'state' => (string) ($t['state'] ?? 'reviewed'), 'updated_at' => Support::now(),
                    ]);
                    // I campi ac_* sono passati alla sezione nuova: escono da quella del riscaldamento.
                    if ($tAria) Db::run('UPDATE section_translations SET data = ? WHERE id = ?',
                        [json_encode(array_diff_key($td, $tAria) ?: new \stdClass(), JSON_UNESCAPED_UNICODE), $t['id']]);
                }
                // Le impronte e le suggerite dei campi ac_* (7C) seguono la sezione nuova.
                foreach (['translation_hashes', 'translation_suggestions'] as $tab) {
                    if (!Migrator::tableExists($tab)) continue;
                    foreach ($fresco as $campo) {
                        Db::run("UPDATE $tab SET target_id = ? WHERE target_type = 'section' AND target_id = ? AND (field_path = ? OR field_path LIKE ?)",
                                [$aid, $s['id'], $campo, $campo . '.%']);
                    }
                }
                $dati = array_diff_key($dati, $datiAria + array_flip($fresco));
                if (array_key_exists('risparmio', $datiAria)) $dati['risparmio'] = $datiAria['risparmio'];
            }
            Db::update('sections', ['kind' => 'riscaldamento', 'icon' => 'riscaldamento',
                                    'data' => json_encode($dati ?: new \stdClass(), JSON_UNESCAPED_UNICODE)], 'id = :sid', ['sid' => $s['id']]);
            // Il titolo di serie («Riscaldamento e aria condizionata») lascia il posto a quello nuovo («Riscaldamento»).
            foreach ($traduzioni as $t) {
                if (in_array(mb_strtolower(trim((string) $t['title'])), $titoliVecchi, true)) {
                    Db::run('UPDATE section_translations SET title = ? WHERE id = ?', [I18n::t((string) $t['locale'], 'kind.riscaldamento'), $t['id']]);
                }
            }
        });
    }

    // -------------------------------------------------- i testi dei piani (di serie)
    $cambi = ['Italiano e inglese' => '2 lingue: la tua e una a scelta',
              '5 lingue: italiano, inglese, francese, tedesco, spagnolo' => '6 lingue: la tua, le altre tra italiano, inglese, francese, tedesco e spagnolo, e una a scelta tra 9'];
    foreach (Db::all('SELECT id, bullets FROM packages') as $p) {
        $righe = preg_split('/\r\n|\n/', (string) $p['bullets']);
        $nuove = array_map(fn($r) => $cambi[trim($r)] ?? $r, $righe);
        if ($nuove !== $righe) Db::run('UPDATE packages SET bullets = ? WHERE id = ?', [implode("\n", $nuove), $p['id']]);
    }
};
