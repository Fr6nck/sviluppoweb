<?php
/**
 * 013 — La firma nella guida e il listino rivisto.
 *
 * 1. Funzione di pacchetto nuova: hide_branding («Firma MyHouse Welcome
 *    nascondibile»). Come fa l'amministratore, Plus e Portfolio ricevono una
 *    VERSIONE NUOVA, uguale a quella in vendita più la funzione: le versioni già
 *    vendute non cambiano, e chi le ha comprate resta su quelle.
 *    Chi ha scelto Plus o Portfolio senza aver ancora pagato passa alla versione
 *    nuova (non ha comprato niente: comprerà quella in vendita).
 * 2. Testi del listino: Essential senza le voci comuni a tutti i piani; Plus e
 *    Portfolio da «Tutto di Essential, e in più:», senza ripetere le voci incluse.
 *    Si cambia solo il testo predefinito: se l'amministratore l'ha già
 *    modificato, resta il suo.
 */

use MHW\{Db, Support};

return function (\PDO $pdo): void {
    $ora = Support::now();
    $fid = Db::val("SELECT id FROM features WHERE code = 'hide_branding'");
    if (!$fid) $fid = Db::insert('features', ['code' => 'hide_branding', 'kind' => 'bool', 'default_value' => '0',
                                               'label' => 'Firma «Guida creata con MyHouse Welcome» nascondibile']);

    foreach (['plus', 'portfolio'] as $code) {
        $pk = Db::one('SELECT * FROM packages WHERE code = ?', [$code]);
        $pv = $pk ? Db::one('SELECT * FROM package_versions WHERE package_id = ? AND is_current = 1', [$pk['id']]) : null;
        if (!$pv) continue;
        if (Db::val('SELECT value FROM package_features WHERE package_version_id = ? AND feature_id = ?', [$pv['id'], $fid]) === '1') continue;
        $nuova = $pv;
        unset($nuova['id']);
        $nuova['version'] = (int) Db::val('SELECT COALESCE(MAX(version),0)+1 FROM package_versions WHERE package_id = ?', [$pk['id']], 1);
        $nuova['is_current'] = 1; $nuova['sold_count'] = 0; $nuova['created_at'] = $ora;
        Db::run('UPDATE package_versions SET is_current = 0 WHERE package_id = ?', [$pk['id']]);
        $vid = Db::insert('package_versions', $nuova);
        foreach (Db::all('SELECT feature_id, value FROM package_features WHERE package_version_id = ?', [$pv['id']]) as $f) {
            if ((int) $f['feature_id'] === (int) $fid) continue;
            Db::insert('package_features', ['package_version_id' => $vid, 'feature_id' => $f['feature_id'], 'value' => $f['value']]);
        }
        Db::insert('package_features', ['package_version_id' => $vid, 'feature_id' => $fid, 'value' => '1']);
        // Chi l'aveva solo scelto (nessun abbonamento attivo) passa alla versione in vendita.
        Db::run("UPDATE accounts SET intended_package_version_id = ? WHERE intended_package_version_id = ?
                 AND id NOT IN (SELECT account_id FROM subscriptions WHERE status IN ('active', 'trialing'))", [$vid, $pv['id']]);
    }

    // codice => [testi predefiniti conosciuti], testo nuovo
    $elenchi = [
        'essential' => [["1 struttura\nCheck-in & Check-out incluso\n4 sezioni aggiuntive a scelta\nItaliano + Inglese\nLogo della struttura\nFoto di copertina\nPersonalizzazione colori\nLink e QR Code permanenti\nPannello di controllo"],
                        "1 struttura\nCheck-in & Check-out incluso\n4 sezioni aggiuntive a scelta\nItaliano + Inglese\nLogo della struttura\nFoto di copertina\nLink e QR Code permanenti"],
        'plus' => [["Tutte le funzionalità Essential\nSezioni predefinite illimitate\n5 lingue pubblicabili\nFoto esplicative nelle sezioni\nPossibilità di allegare documenti PDF\nStatistiche di lettura\nPersonalizzazione colori\nPannello di controllo"],
                   "Tutto di Essential, e in più:\nSezioni illimitate\n5 lingue pubblicabili\nFoto esplicative nelle sezioni\nDocumenti PDF allegati\nStatistiche di lettura\nFirma MyHouse Welcome nascondibile"],
        'portfolio' => [["Tutte le funzionalità Plus, per ogni struttura\nUna guida e un QR Code per ogni struttura\nStatistiche distinte\nUn unico pannello di controllo"],
                        "Tutto di Essential, e in più:\nTutte le funzioni di Plus, per ogni struttura\nUna guida e un QR Code per ogni struttura\nStatistiche distinte per struttura\nUn solo account per tutte"],
    ];
    foreach ($elenchi as $code => [$prima, $dopo]) {
        $pk = Db::one('SELECT id, bullets FROM packages WHERE code = ?', [$code]);
        if ($pk && in_array(trim((string) $pk['bullets']), $prima, true)) Db::update('packages', ['bullets' => $dopo], 'id = :pid', ['pid' => $pk['id']]);
    }
};
