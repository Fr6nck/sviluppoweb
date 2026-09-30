<?php
/**
 * 006 — Portfolio a quantità variabile.
 *
 * Un solo pacchetto Portfolio: prima struttura al prezzo della versione,
 * ogni struttura in più al "prezzo per struttura aggiuntiva". Il numero di
 * strutture comprato sta sull'abbonamento (e, prima di pagare, sull'account).
 *
 * Non si cancella niente:
 *  - Portfolio 2 e Portfolio 3 escono dalla vendita (public = 0) ma restano:
 *    chi li ha comprati tiene la sua versione, con 2 o 3 strutture;
 *  - chi li aveva solo SCELTI (senza pagare) passa al Portfolio nuovo con la
 *    stessa quantità: stesso prezzo, 177 o 237 €.
 */

use MHW\{Db, Migrator, Support};

return function (\PDO $pdo): void {
    // ------------------------------------------------------------- colonne
    $colonne = [
        'package_versions' => ['per_property' => 'INTEGER NOT NULL DEFAULT 0', 'extra_price_cents' => 'INTEGER NOT NULL DEFAULT 0',
                               'min_quantity' => 'INTEGER NOT NULL DEFAULT 1', 'max_quantity' => 'INTEGER NOT NULL DEFAULT 1',
                               'stripe_extra_price_id' => "VARCHAR(80) NOT NULL DEFAULT ''"],
        'subscriptions' => ['quantity' => 'INTEGER NOT NULL DEFAULT 1', 'provider_extra_item_id' => "VARCHAR(80) NOT NULL DEFAULT ''"],
        'accounts' => ['intended_quantity' => 'INTEGER NOT NULL DEFAULT 1'],
        'orders' => ['quantity' => 'INTEGER NOT NULL DEFAULT 1'],
        'properties' => ['archived_at' => 'VARCHAR(25) NULL'],
    ];
    foreach ($colonne as $tabella => $cc) {
        foreach ($cc as $col => $def) {
            if (!Migrator::columnExists($tabella, $col)) $pdo->exec("ALTER TABLE $tabella ADD COLUMN $col $def");
        }
    }

    // Gli abbonamenti Portfolio già venduti: la quantità è quella della loro versione.
    foreach (Db::all("SELECT s.id, pf.value FROM subscriptions s
                      JOIN package_features pf ON pf.package_version_id = s.package_version_id
                      JOIN features f ON f.id = pf.feature_id AND f.code = 'properties'") as $s) {
        if (ctype_digit((string) $s['value']) && (int) $s['value'] > 1) {
            Db::update('subscriptions', ['quantity' => (int) $s['value']], 'id = :sid', ['sid' => $s['id']]);
        }
    }

    // -------------------------------------------------------- il pacchetto
    if (Db::one("SELECT id FROM packages WHERE code = 'portfolio'")) return;
    $modello = Db::one("SELECT * FROM packages WHERE code = 'portfolio2'");
    $pvModello = $modello ? Db::one('SELECT * FROM package_versions WHERE package_id = ? AND is_current = 1', [$modello['id']]) : null;
    $ora = Support::now();

    $pid = Db::insert('packages', [
        'code' => 'portfolio', 'name' => 'Portfolio', 'family' => 'portfolio', 'sort' => 2, 'active' => 1, 'public' => 1,
        'tagline' => '',
        'headline' => $modello['headline'] ?? 'Il Plus per più strutture.',
        'description' => $modello['description'] ?? 'Gestisci più Welcome Guide da un unico pannello, con contenuti, QR Code e statistiche distinti per ogni struttura.',
        'bullets' => $modello['bullets'] ?? "Tutte le funzionalità Plus, per ogni struttura\nUna guida e un QR Code per ogni struttura\nStatistiche distinte\nUn unico pannello di controllo",
        'badge' => '', 'cta_label' => 'Crea gratis con Portfolio',
    ]);
    $vid = Db::insert('package_versions', [
        'package_id' => $pid, 'version' => 1, 'price_cents' => 11700, 'currency' => 'EUR', 'interval_unit' => 'year',
        'is_current' => 1, 'sold_count' => 0, 'stripe_price_id' => '', 'created_at' => $ora,
        'per_property' => 1, 'extra_price_cents' => 6000, 'min_quantity' => 2, 'max_quantity' => 50, 'stripe_extra_price_id' => '',
    ]);
    // Le funzioni sono quelle di Plus per ogni struttura; "properties" qui è solo il
    // minimo: il numero vero lo decide la quantità comprata.
    $fonte = $pvModello ?: Db::one("SELECT pv.* FROM package_versions pv JOIN packages p ON p.id = pv.package_id WHERE p.code = 'plus' AND pv.is_current = 1");
    foreach (Db::all('SELECT * FROM features ORDER BY id') as $f) {
        $v = $fonte ? Db::val('SELECT value FROM package_features WHERE package_version_id = ? AND feature_id = ?', [$fonte['id'], $f['id']]) : null;
        if ($f['code'] === 'properties') $v = '2';
        Db::insert('package_features', ['package_version_id' => $vid, 'feature_id' => $f['id'], 'value' => (string) ($v ?? $f['default_value'])]);
    }

    // I vecchi Portfolio a quantità fissa escono dalla vendita, senza sparire.
    Db::run("UPDATE packages SET public = 0 WHERE code IN ('portfolio2', 'portfolio3')");

    // Chi li aveva solo scelti passa al nuovo, con la stessa quantità (e lo stesso prezzo).
    foreach (['portfolio2' => 2, 'portfolio3' => 3] as $code => $q) {
        Db::run("UPDATE accounts SET intended_package_version_id = ?, intended_quantity = ?
                 WHERE intended_package_version_id IN (SELECT pv.id FROM package_versions pv JOIN packages p ON p.id = pv.package_id WHERE p.code = ?)",
                [$vid, $q, $code]);
    }
};
