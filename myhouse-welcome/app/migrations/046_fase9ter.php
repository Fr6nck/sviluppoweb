<?php
/**
 * 046 — 9F/9G · Regione e provincia, vetrina ripubblicata con i portali, memoria delle traduzioni, tetto personale,
 *       lingua del pannello.
 *
 * 1. properties.province: la sigla della provincia (PG, FI…) o, all'estero, un testo libero. «Zona o regione» resta
 *    in properties.region e diventa «Regione».
 * 2. Le vetrine pubblicate (is_demo = 2) senza canali (property_channels) ricevono Google, Booking.com e Airbnb e si
 *    ripubblicano: così l'istantanea ha i canali e con 5 stelle compaiono i bottoni dei portali (9F·3).
 * 3. translation_cache (9G): le frasi già tradotte, per riusarle senza chiamare Amazon (hash di testo + lingue).
 * 4. accounts.translate_cap_override (9G): il tetto annuale personale di un account, se diverso da quello di serie.
 * 5. users.panel_lang (9F·11): la lingua del pannello scelta dall'host ('' = quella della lingua principale della guida).
 *
 * Solo aggiunte, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */
use MHW\{Db, Migrator, Canali, Guide, Log};

return function (\PDO $pdo): void {
    $pk = Db::isMysql() ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';

    $colonne = [
        'properties' => ['province' => "VARCHAR(60) NOT NULL DEFAULT ''"],
        'accounts' => ['translate_cap_override' => 'INTEGER NULL'],
        'users' => ['panel_lang' => "VARCHAR(5) NOT NULL DEFAULT ''"],
    ];
    foreach ($colonne as $tabella => $elenco) {
        if (!Migrator::tableExists($tabella)) continue;
        foreach ($elenco as $nome => $tipo) {
            if (!Migrator::columnExists($tabella, $nome)) $pdo->exec("ALTER TABLE $tabella ADD COLUMN $nome $tipo");
        }
    }

    if (!Migrator::tableExists('translation_cache')) {
        $pdo->exec("CREATE TABLE translation_cache (
            id $pk,
            hash VARCHAR(64) NOT NULL,
            da VARCHAR(8) NOT NULL DEFAULT '',
            a VARCHAR(8) NOT NULL DEFAULT '',
            chars INTEGER NOT NULL DEFAULT 0,
            testo_tradotto TEXT NOT NULL,
            hits INTEGER NOT NULL DEFAULT 0,
            created_at VARCHAR(25) NOT NULL,
            used_at VARCHAR(25) NULL
        )");
        $pdo->exec('CREATE UNIQUE INDEX idx_translation_cache_hash ON translation_cache(hash)');
    }

    // 9F·12 · La sezione libera (una per guida) è in tutti i piani: la funzione «Sezioni personalizzate» si accende nelle
    // versioni in vendita, così «Confronta i piani» la mostra. Il numero di sezioni di Essential (6) non cambia.
    if (Migrator::tableExists('features') && Migrator::tableExists('package_features')) {
        $fid = (int) Db::val("SELECT id FROM features WHERE code = 'custom_sections'", [], 0);
        if ($fid) {
            Db::run("UPDATE features SET label = 'Sezione libera (una per guida)' WHERE id = ? AND label = 'Sezioni personalizzate'", [$fid]);
            foreach (Db::all('SELECT id FROM package_versions WHERE is_current = 1') as $v) {
                $c = Db::one('SELECT value FROM package_features WHERE package_version_id = ? AND feature_id = ?', [$v['id'], $fid]);
                if ($c === null) Db::insert('package_features', ['package_version_id' => (int) $v['id'], 'feature_id' => $fid, 'value' => '1']);
                elseif ((string) $c['value'] !== '1') Db::run('UPDATE package_features SET value = ? WHERE package_version_id = ? AND feature_id = ?', ['1', $v['id'], $fid]);
            }
        }
    }

    // 9G · Le schede dei piani: «Il primo anno è in omaggio» → «comprese nel piano, entro un limite annuale di caratteri»
    // (solo se l'amministratore non ha già cambiato la riga: si sostituisce la frase esatta, in italiano e in inglese).
    if (Migrator::tableExists('packages')) {
        foreach (Db::all('SELECT id, bullets' . (Migrator::columnExists('packages', 'bullets_en') ? ', bullets_en' : '') . ' FROM packages') as $pk) {
            $b = str_replace('tu controlli e confermi. Il primo anno è in omaggio.', 'tu controlli e confermi. Comprese nel piano, entro un limite annuale di caratteri.', (string) $pk['bullets']);
            $dati = [];
            if ($b !== (string) $pk['bullets']) $dati['bullets'] = $b;
            if (isset($pk['bullets_en'])) {
                $be = str_replace('you check and confirm. The first year is free.', 'you check and confirm. Included in the plan, within a yearly character limit.', (string) $pk['bullets_en']);
                if ($be !== (string) $pk['bullets_en']) $dati['bullets_en'] = $be;
            }
            if ($dati) Db::update('packages', $dati, 'id = :id', ['id' => (int) $pk['id']]);
        }
    }

    // 2. Le vetrine: i canali, poi la ripubblicazione (l'istantanea nuova ha channels, provincia e regione).
    if (Migrator::tableExists('guide_versions') && Migrator::columnExists('properties', 'is_demo')) {
        foreach (Db::all("SELECT id FROM properties WHERE is_demo = 2 AND status = 'published' AND archived_at IS NULL") as $v) {
            $pid = (int) $v['id'];
            try {
                if (Canali::disponibili() && !Canali::righe($pid)) {
                    Canali::salva($pid, ['portali' => ['google', 'booking', 'airbnb'], 'social' => ['instagram' => '@casacheccoassisi']]);
                }
                if (Migrator::columnExists('properties', 'province') && (string) Db::val('SELECT province FROM properties WHERE id = ?', [$pid], '') === '') {
                    Db::update('properties', ['province' => 'PG', 'region' => 'Umbria'], 'id = :pid', ['pid' => $pid]);
                }
                Guide::publish($pid);
            } catch (\Throwable $e) {
                Log::error('046: vetrina non ripubblicata: ' . $e->getMessage(), ['property' => $pid]);
            }
        }
    }
};
