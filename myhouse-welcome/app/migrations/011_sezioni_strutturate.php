<?php
/**
 * 011 — Sezioni strutturate e coordinate dei luoghi.
 *
 * Emergenze, rifiuti, parcheggio e come arrivare passano dalle voci «una per
 * riga» (o dai campi singoli) alle righe con sottocampi: Conversione fa il
 * lavoro, la stessa usata per le guide già pubblicate. Le vecchie chiavi
 * restano nel JSON. Servizi e regole tengono la loro lista com'era («Altre
 * dotazioni», «Regole aggiuntive»): lì non c'è niente da convertire.
 *
 * In più: latitudine e longitudine dei luoghi, per stimare i minuti a piedi
 * dalla struttura (letti dal link di Google Maps).
 */

use MHW\{Conversione, Db, Migrator};

return function (\PDO $pdo): void {
    foreach (['lat', 'lng'] as $col) {
        if (!Migrator::columnExists('places', $col)) $pdo->exec("ALTER TABLE places ADD COLUMN $col DECIMAL(9,6) NULL");
    }

    $sezioni = Db::all("SELECT s.id, s.kind, s.data, p.default_locale FROM sections s JOIN properties p ON p.id = s.property_id
                        WHERE s.kind IN ('emergency', 'waste', 'parking', 'arrival')");
    foreach ($sezioni as $s) {
        $dati = json_decode((string) $s['data'], true) ?: [];
        $testi = [];
        foreach (Db::all('SELECT locale, data FROM section_translations WHERE section_id = ?', [$s['id']]) as $t) {
            $testi[$t['locale']] = json_decode((string) $t['data'], true) ?: [];
        }
        [$nuoviDati, $nuoviTesti] = Conversione::sezione((string) $s['kind'], $dati, $testi, (string) $s['default_locale']);
        if ($nuoviDati === $dati) continue;
        Db::update('sections', ['data' => json_encode($nuoviDati, JSON_UNESCAPED_UNICODE)], 'id = :sid', ['sid' => $s['id']]);
        foreach ($nuoviTesti as $loc => $t) {
            if (($testi[$loc] ?? null) === $t) continue;
            Db::run('UPDATE section_translations SET data = ? WHERE section_id = ? AND locale = ?',
                    [json_encode($t, JSON_UNESCAPED_UNICODE), $s['id'], $loc]);
        }
    }
};
