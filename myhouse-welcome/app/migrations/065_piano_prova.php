<?php
/**
 * 065 — Il piano di prova «Prova»: 1 € a settimana, le funzioni di Plus, nascosto (packages.public = 0: non compare nel
 * sito né nella scelta del piano). Serve a chi amministra per provare con soldi veri, a costo quasi zero, pagamento,
 * rinnovo e sconti: lo si assegna a un cliente dalla sua scheda in Amministrazione («Assegna il piano di prova»).
 * La versione usa package_versions.interval_unit = 'week' (la colonna c'era dalla 001, finora sempre 'year').
 * Solo aggiunte, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */
use MHW\{Db, Migrator, Support};

return function (\PDO $pdo): void {
    if (Db::one("SELECT id FROM packages WHERE code = 'prova'")) return;
    $plus = Db::one("SELECT * FROM packages WHERE code = 'plus'");
    $pvPlus = $plus ? Db::one('SELECT * FROM package_versions WHERE package_id = ? AND is_current = 1', [$plus['id']]) : null;
    $ora = Support::now();
    $pacchetto = ['code' => 'prova', 'name' => 'Prova', 'tagline' => 'Solo per le prove: 1 € a settimana, le funzioni di Plus.', 'sort' => 99, 'active' => 1];
    foreach (['headline' => 'Piano di prova', 'description' => 'Un piano riservato alle prove di chi amministra: costa 1 € a settimana e dà tutto quello che dà Plus.',
              'bullets' => (string) ($plus['bullets'] ?? ''), 'badge' => '', 'cta_label' => '', 'family' => ''] as $c => $v) {
        if (Migrator::columnExists('packages', $c)) $pacchetto[$c] = $v;
    }
    if (Migrator::columnExists('packages', 'public')) $pacchetto['public'] = 0;
    $pid = Db::insert('packages', $pacchetto);
    $versione = ['package_id' => $pid, 'version' => 1, 'price_cents' => 100, 'currency' => 'EUR', 'interval_unit' => 'week', 'is_current' => 1, 'sold_count' => 0, 'created_at' => $ora];
    foreach (['stripe_price_id' => '', 'stripe_extra_price_id' => '', 'per_property' => 0, 'extra_price_cents' => 0, 'min_quantity' => 1, 'max_quantity' => 1, 'extra_tiers' => ''] as $c => $v) {
        if (Migrator::columnExists('package_versions', $c)) $versione[$c] = $v;
    }
    $vid = Db::insert('package_versions', $versione);
    if ($pvPlus) {
        foreach (Db::all('SELECT feature_id, value FROM package_features WHERE package_version_id = ?', [$pvPlus['id']]) as $f) {
            Db::insert('package_features', ['package_version_id' => $vid, 'feature_id' => $f['feature_id'], 'value' => $f['value']]);
        }
    }
};
