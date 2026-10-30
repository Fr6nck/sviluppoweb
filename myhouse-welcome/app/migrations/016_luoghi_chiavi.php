<?php
/**
 * 016 — Categorie ed etichette dei luoghi come chiavi, tradotte da sole (Fase 6B).
 *
 * places.category_key, places.badge_key: una chiave delle tassonomie della
 * sezione (Tassonomie::categorie / etichette). Con la chiave, nella guida si
 * mostra I18n::t($loc, 'cat.' . $chiave) in ogni lingua e il testo scritto a
 * mano si svuota in tutte le traduzioni; senza chiave resta il testo, come prima.
 *
 * Conversione dei luoghi esistenti: si cerca la chiave dal testo della lingua
 * principale (in qualunque lingua sia scritto). Se non si trova, niente cambia:
 * nessun testo si perde.
 */

use MHW\{Db, Migrator, Tassonomie};

return function (\PDO $pdo): void {
    foreach (['category_key', 'badge_key'] as $col) {
        if (!Migrator::columnExists('places', $col)) $pdo->exec("ALTER TABLE places ADD COLUMN $col VARCHAR(40) NOT NULL DEFAULT ''");
    }
    if (!Migrator::tableExists('place_translations')) return;
    $righe = Db::all("SELECT pl.id, s.kind, p.default_locale FROM places pl
                      JOIN sections s ON s.id = pl.section_id JOIN properties p ON p.id = s.property_id
                      WHERE pl.category_key = '' AND pl.badge_key = ''");
    foreach ($righe as $r) {
        $tr = Db::one('SELECT category, badge FROM place_translations WHERE place_id = ? AND locale = ?', [$r['id'], $r['default_locale'] ?: 'it']);
        if (!$tr) continue;
        $ck = Tassonomie::chiaveDaTesto('cat.', Tassonomie::categorie((string) $r['kind']), (string) $tr['category']);
        $bk = Tassonomie::chiaveDaTesto('badge.', Tassonomie::etichette((string) $r['kind']), (string) $tr['badge']);
        if ($ck !== '') {
            Db::run('UPDATE places SET category_key = ? WHERE id = ?', [$ck, $r['id']]);
            Db::run("UPDATE place_translations SET category = '' WHERE place_id = ?", [$r['id']]);
        }
        if ($bk !== '') {
            Db::run('UPDATE places SET badge_key = ? WHERE id = ?', [$bk, $r['id']]);
            Db::run("UPDATE place_translations SET badge = '' WHERE place_id = ?", [$r['id']]);
        }
    }
};
