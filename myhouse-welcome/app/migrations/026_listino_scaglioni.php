<?php
/**
 * 026 — Portfolio a scaglioni, etichetta «Consigliato», QR che non cambia.
 *
 * 1. package_versions.extra_tiers: gli scaglioni del Portfolio, in JSON
 *    ([{"da":3,"cents":5000}, …]: dalla struttura 3 in poi, 50 € l'una). Vuoto = un solo
 *    prezzo per struttura aggiuntiva, come le versioni vendute finora.
 * 2. Una versione nuova del Portfolio con il listino progressivo: la 1ª struttura al prezzo
 *    di Plus, la 2ª 60 €, dalla 3ª alla 5ª 50 €, dalla 6ª alla 10ª 40 €, dall'11ª alla 20ª
 *    30 €, oltre la 20ª 25 €. Chi ha già un Portfolio resta sulla sua versione (regola delle
 *    versioni). Il Price ID della struttura aggiuntiva resta vuoto: quello a scaglioni lo crea
 *    il sito su Stripe al primo pagamento.
 * 3. «Più scelto» su Plus diventa «Consigliato», finché non è vero.
 * 4. Nei testi dei piani «QR Code permanenti» diventa «il QR non cambia mai, anche se
 *    modifichi la guida»: «permanente» stonava con la guida che va offline senza rinnovo.
 *
 * Solo aggiunte e testi; nessun abbonamento cambia prezzo.
 */

use MHW\{Db, Migrator, Support};

return function (\PDO $pdo): void {
    if (!Migrator::columnExists('package_versions', 'extra_tiers')) {
        $pdo->exec("ALTER TABLE package_versions ADD COLUMN extra_tiers VARCHAR(500) NOT NULL DEFAULT ''");
    }

    // 2. Il Portfolio progressivo, una volta sola (se la versione in vendita ha già scaglioni, niente).
    $pf = Db::one("SELECT pv.* FROM package_versions pv JOIN packages p ON p.id = pv.package_id
                   WHERE p.code = 'portfolio' AND pv.is_current = 1");
    if ($pf && (int) $pf['per_property'] === 1 && trim((string) $pf['extra_tiers']) === '') {
        $plus = (int) Db::val("SELECT pv.price_cents FROM package_versions pv JOIN packages p ON p.id = pv.package_id
                               WHERE p.code = 'plus' AND pv.is_current = 1", [], 0);
        $base = $plus > 0 ? $plus : (int) $pf['price_cents'];
        $scaglioni = [['da' => 2, 'cents' => 6000], ['da' => 3, 'cents' => 5000], ['da' => 6, 'cents' => 4000],
                      ['da' => 11, 'cents' => 3000], ['da' => 21, 'cents' => 2500]];
        $next = (int) Db::val('SELECT COALESCE(MAX(version),0)+1 FROM package_versions WHERE package_id = ?', [$pf['package_id']], 1);
        Db::run('UPDATE package_versions SET is_current = 0 WHERE package_id = ?', [$pf['package_id']]);
        $nuova = Db::insert('package_versions', [
            'package_id' => $pf['package_id'], 'version' => $next, 'price_cents' => $base, 'currency' => $pf['currency'],
            'interval_unit' => $pf['interval_unit'], 'is_current' => 1, 'sold_count' => 0, 'created_at' => Support::now(),
            // Il Price base si riusa solo se l'importo è lo stesso.
            'stripe_price_id' => $base === (int) $pf['price_cents'] ? (string) $pf['stripe_price_id'] : '',
            'per_property' => 1, 'extra_price_cents' => 6000, 'min_quantity' => (int) $pf['min_quantity'],
            'max_quantity' => max(50, (int) $pf['max_quantity']), 'stripe_extra_price_id' => '',
            'extra_tiers' => json_encode($scaglioni),
        ]);
        foreach (Db::all('SELECT feature_id, value FROM package_features WHERE package_version_id = ?', [$pf['id']]) as $f) {
            Db::insert('package_features', ['package_version_id' => $nuova, 'feature_id' => $f['feature_id'], 'value' => $f['value']]);
        }
    }

    // 3. L'etichetta di Plus: «Consigliato» al posto di «(Il) più scelto».
    foreach (Db::all("SELECT id, badge FROM packages WHERE code = 'plus'") as $p) {
        if (preg_match('/^\s*(il\s+)?più\s+scelto\s*$/iu', (string) $p['badge'])) Db::update('packages', ['badge' => 'Consigliato'], 'id = :id', ['id' => $p['id']]);
    }

    // 4. Il QR: una riga dell'elenco dei piani, ovunque compaia (solo le righe scritte così).
    $righe = ['Link e QR Code permanenti' => 'Link e QR Code che non cambiano mai, anche se modifichi la guida',
              'QR permanente e link personale' => 'Link e QR Code che non cambiano mai, anche se modifichi la guida',
              'QR permanente' => 'QR Code che non cambia mai, anche se modifichi la guida'];
    foreach (Db::all('SELECT id, bullets FROM packages') as $p) {
        $voci = preg_split('/\R/', (string) $p['bullets']) ?: [];
        $nuove = array_map(fn($v) => $righe[trim($v)] ?? $v, $voci);
        if ($nuove !== $voci) Db::update('packages', ['bullets' => implode("\n", $nuove)], 'id = :id', ['id' => $p['id']]);
    }
};
