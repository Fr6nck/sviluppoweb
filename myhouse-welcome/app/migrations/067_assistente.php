<?php
/**
 * 067 — «Chiedi alla guida» (10): l'assistente che risponde agli ospiti con il testo della guida.
 *  1. La tabella assistant_questions: ogni domanda, anonima (niente nome, niente IP), con l'esito, i token e il
 *     costo stimato, per i contatori dell'host, il limite mensile e Amministrazione → Assistente AI.
 *  2. properties.assistant_enabled (acceso di serie per chi ha la funzione: l'host lo spegne da Impostazioni) e
 *     properties.assistant_contacts (quando la guida non sa rispondere, mostra i contatti dell'host).
 *  3. accounts.assistant_cap_override: un limite mensile personale, dalla scheda del cliente.
 *  4. La funzione ai_concierge (c'era già, mai venduta) prende il nome di serie «Chiedi alla guida» e si accende in
 *     tutte le versioni di Plus e Portfolio; Essential resta a 0.
 *  5. Una riga in fondo alle voci di Plus e Portfolio nel listino, se non c'è già.
 * Solo aggiunte, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */
use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    if (!Migrator::tableExists('assistant_questions')) {
        $auto = Db::isMysql() ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $pdo->exec("CREATE TABLE assistant_questions (
            id $auto,
            property_id INTEGER NOT NULL,
            account_id INTEGER NOT NULL,
            month VARCHAR(7) NOT NULL,
            locale VARCHAR(8) NOT NULL DEFAULT '',
            question TEXT NOT NULL,
            answer TEXT NULL,
            section_id INTEGER NULL,
            outcome VARCHAR(12) NOT NULL,
            tokens_in INTEGER NOT NULL DEFAULT 0,
            tokens_out INTEGER NOT NULL DEFAULT 0,
            tokens_cache_write INTEGER NOT NULL DEFAULT 0,
            tokens_cache_read INTEGER NOT NULL DEFAULT 0,
            cost_microusd INTEGER NOT NULL DEFAULT 0,
            model VARCHAR(60) NOT NULL DEFAULT '',
            error VARCHAR(255) NOT NULL DEFAULT '',
            created_at VARCHAR(25) NOT NULL
        )");
        $pdo->exec('CREATE INDEX idx_aq_prop_mese ON assistant_questions(property_id, month)');
        $pdo->exec('CREATE INDEX idx_aq_acc_mese ON assistant_questions(account_id, month)');
    }
    if (!Migrator::columnExists('properties', 'assistant_enabled')) $pdo->exec('ALTER TABLE properties ADD COLUMN assistant_enabled INTEGER NOT NULL DEFAULT 1');
    if (!Migrator::columnExists('properties', 'assistant_contacts')) $pdo->exec('ALTER TABLE properties ADD COLUMN assistant_contacts INTEGER NOT NULL DEFAULT 1');
    if (!Migrator::columnExists('accounts', 'assistant_cap_override')) $pdo->exec('ALTER TABLE accounts ADD COLUMN assistant_cap_override INTEGER NULL');

    $fid = Db::val("SELECT id FROM features WHERE code = 'ai_concierge'");
    if (!$fid) $fid = Db::insert('features', ['code' => 'ai_concierge', 'kind' => 'bool', 'default_value' => '0', 'label' => 'Chiedi alla guida (assistente AI)']);
    Db::run("UPDATE features SET label = 'Chiedi alla guida (assistente AI)' WHERE id = ? AND label = 'Concierge con intelligenza artificiale'", [$fid]);
    foreach (Db::all("SELECT v.id FROM package_versions v JOIN packages p ON p.id = v.package_id WHERE p.code IN ('plus', 'portfolio', 'portfolio2', 'portfolio3')") as $v) {
        if (Db::val('SELECT feature_id FROM package_features WHERE package_version_id = ? AND feature_id = ?', [$v['id'], $fid])) {
            Db::run("UPDATE package_features SET value = '1' WHERE package_version_id = ? AND feature_id = ?", [$v['id'], $fid]);
        } else {
            Db::insert('package_features', ['package_version_id' => $v['id'], 'feature_id' => $fid, 'value' => '1']);
        }
    }

    // La voce nel listino, in fondo all'elenco di Plus e Portfolio (anche in inglese, se l'elenco inglese c'è).
    $righe = ['plus' => ['Chiedi alla guida: l\'assistente che risponde agli ospiti con le tue informazioni, in ogni lingua', 'Ask the guide: the assistant that answers guests with your information, in any language'],
              'portfolio' => ['Chiedi alla guida in ogni struttura, con le sue informazioni', 'Ask the guide in every property, with its own information']];
    $en = Migrator::columnExists('packages', 'bullets_en');
    foreach ($righe as $code => [$it, $enRiga]) {
        $p = Db::one('SELECT * FROM packages WHERE code = ?', [$code]);
        if (!$p) continue;
        $dati = [];
        if (!str_contains((string) $p['bullets'], 'Chiedi alla guida')) $dati['bullets'] = rtrim((string) $p['bullets']) . "\n" . $it;
        if ($en && trim((string) ($p['bullets_en'] ?? '')) !== '' && !str_contains((string) $p['bullets_en'], 'Ask the guide')) $dati['bullets_en'] = rtrim((string) $p['bullets_en']) . "\n" . $enRiga;
        if ($dati) Db::update('packages', $dati, 'id = :id', ['id' => (int) $p['id']]);
    }
};
