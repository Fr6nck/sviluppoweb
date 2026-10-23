<?php
/**
 * 022 — Traduzioni suggerite (Amazon Translate).
 *
 * 1. translation_suggestions: le traduzioni proposte dal traduttore automatico,
 *    una per campo e lingua, finché il cliente non le approva o le scarta. Stanno
 *    qui e non nelle traduzioni: la guida non le legge mai. Approvata, il testo
 *    passa nella traduzione vera e la riga sparisce.
 * 2. translation_usage: ogni chiamata al servizio (caratteri, esito), per i tetti
 *    mensili e per Amministrazione → Traduzioni.
 * 3. properties.translation_suggest: l'interruttore della struttura, in Lingue.
 *    accounts.translation_trial_until: la fine dell'anno in omaggio (nasce alla
 *    prima accensione); translation_trial_by_admin: 1 se l'ha cambiata l'amministratore.
 * 4. La funzione auto_translation si accende per tutte le versioni di Plus e
 *    Portfolio, anche quelle già vendute: chi ha già un abbonamento la riceve.
 *    L'etichetta diventa «Traduzioni suggerite».
 * 5. Testi dei piani: Plus e Portfolio dicono l'omaggio, solo se il testo è
 *    ancora quello predefinito (se l'amministratore l'ha riscritto, resta il suo).
 *
 * Solo aggiunte: niente si converte, niente si cancella. Prezzi e Price ID non si toccano.
 */

use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    $pk = Db::isMysql() ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';

    if (!Migrator::tableExists('translation_suggestions')) {
        $pdo->exec("CREATE TABLE translation_suggestions (
            id $pk,
            property_id INTEGER NOT NULL,
            locale VARCHAR(5) NOT NULL,
            target_type VARCHAR(10) NOT NULL,
            target_id INTEGER NOT NULL,
            field_path VARCHAR(190) NOT NULL,
            source_text TEXT NOT NULL,
            text TEXT NOT NULL,
            chars INTEGER NOT NULL DEFAULT 0,
            created_at VARCHAR(25) NOT NULL
        )");
        $pdo->exec('CREATE UNIQUE INDEX idx_tsugg_campo ON translation_suggestions(target_type, target_id, locale, field_path)');
        $pdo->exec('CREATE INDEX idx_tsugg_struttura ON translation_suggestions(property_id, locale)');
    }
    if (!Migrator::tableExists('translation_usage')) {
        $pdo->exec("CREATE TABLE translation_usage (
            id $pk,
            account_id INTEGER NOT NULL,
            property_id INTEGER NULL,
            month VARCHAR(7) NOT NULL,
            locale VARCHAR(5) NOT NULL DEFAULT '',
            chars INTEGER NOT NULL DEFAULT 0,
            outcome VARCHAR(12) NOT NULL DEFAULT 'ok',
            error VARCHAR(255) NOT NULL DEFAULT '',
            created_at VARCHAR(25) NOT NULL
        )");
        $pdo->exec('CREATE INDEX idx_tusage_mese ON translation_usage(month, account_id)');
    }
    if (!Migrator::columnExists('properties', 'translation_suggest')) {
        $pdo->exec('ALTER TABLE properties ADD COLUMN translation_suggest INTEGER NOT NULL DEFAULT 0');
    }
    if (!Migrator::columnExists('accounts', 'translation_trial_until')) {
        $pdo->exec('ALTER TABLE accounts ADD COLUMN translation_trial_until VARCHAR(25) NULL');
    }
    if (!Migrator::columnExists('accounts', 'translation_trial_by_admin')) {
        $pdo->exec('ALTER TABLE accounts ADD COLUMN translation_trial_by_admin INTEGER NOT NULL DEFAULT 0');
    }

    $fid = Db::val("SELECT id FROM features WHERE code = 'auto_translation'");
    if (!$fid) $fid = Db::insert('features', ['code' => 'auto_translation', 'kind' => 'bool', 'default_value' => '0', 'label' => 'Traduzioni suggerite']);
    Db::run("UPDATE features SET label = 'Traduzioni suggerite' WHERE id = ? AND label = 'Traduzione automatica'", [$fid]);
    foreach (Db::all("SELECT v.id FROM package_versions v JOIN packages p ON p.id = v.package_id
                      WHERE p.code IN ('plus', 'portfolio', 'portfolio2', 'portfolio3')") as $v) {
        if (Db::val('SELECT feature_id FROM package_features WHERE package_version_id = ? AND feature_id = ?', [$v['id'], $fid])) {
            Db::run("UPDATE package_features SET value = '1' WHERE package_version_id = ? AND feature_id = ?", [$v['id'], $fid]);
        } else {
            Db::insert('package_features', ['package_version_id' => $v['id'], 'feature_id' => $fid, 'value' => '1']);
        }
    }

    // Testi dei piani: l'omaggio in fondo all'elenco, solo sui testi predefiniti (quelli della 020).
    $riga = "\nTraduzioni suggerite: in omaggio per un anno";
    $predefiniti = [
        'plus' => "Tutto di Essential, e in più:\nSezioni illimitate\n5 lingue: italiano, inglese, francese, tedesco, spagnolo\nFoto e PDF nelle sezioni\nFoto profilo dell'host\nStatistiche di lettura\nFirma MyHouse Welcome nascondibile",
        'portfolio' => "Tutto di Plus, per ogni struttura, e in più:\nUna guida e un QR Code per ogni struttura\nStatistiche distinte per struttura\nSezioni da copiare da una struttura all'altra\nUn solo account e un solo abbonamento",
    ];
    foreach ($predefiniti as $code => $testo) {
        Db::run('UPDATE packages SET bullets = ? WHERE code = ? AND bullets = ?', [$testo . $riga, $code, $testo]);
    }
};
