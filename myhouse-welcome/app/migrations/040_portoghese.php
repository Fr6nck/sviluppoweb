<?php
/**
 * 040 — Il portoghese in due varianti: «pt» è il Portogallo (i testi della guida erano già europei;
 * Amazon Translate riceve pt-PT), «pt-BR» il Brasile (nuovo). Le lingue in più diventano dieci.
 *
 * Nessuna colonna nuova (i codici stanno nei VARCHAR(5) che ci sono). Si aggiornano solo i testi di serie
 * che contano le lingue in più: le schede dei piani («una a scelta tra 9» → 10, «out of 9» → 10) e il
 * fatto chiave di llms.txt, se è ancora quello di serie. I testi scritti dall'amministratore non si toccano.
 *
 * SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */

use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    $sost = ['bullets' => ['una a scelta tra 9', 'una a scelta tra 10'], 'bullets_en' => ['one of your choice out of 9', 'one of your choice out of 10']];
    foreach ($sost as $col => [$da, $a]) {
        if (!Migrator::columnExists('packages', $col)) continue;
        foreach (Db::all("SELECT id, $col AS t FROM packages WHERE $col LIKE ?", ['%' . $da . '%']) as $p) {
            // «tra 9» seguito da una cifra sarebbe un altro numero (90…): si cambia solo «tra 9» a fine parola.
            $nuovo = preg_replace('/' . preg_quote($da, '/') . '(?!\d)/u', $a, (string) $p['t']);
            if ($nuovo !== null && $nuovo !== $p['t']) Db::update('packages', [$col => $nuovo], 'id = :id', ['id' => $p['id']]);
        }
    }
    if (Migrator::tableExists('seo_settings')) {
        $vecchia = 'spagnolo e una a scelta tra nove).';
        $nuova = 'spagnolo e una a scelta tra dieci, tra cui il portoghese del Portogallo e quello del Brasile).';
        foreach (Db::all("SELECT chiave, valore FROM seo_settings WHERE chiave = 'llms.fatti' AND valore LIKE ?", ['%' . $vecchia . '%']) as $r) {
            Db::run('UPDATE seo_settings SET valore = ? WHERE chiave = ?', [str_replace($vecchia, $nuova, (string) $r['valore']), $r['chiave']]);
        }
    }
};
