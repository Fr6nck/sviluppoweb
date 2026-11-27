<?php
/**
 * 041 — Le schede dei piani non dicono più «Scrivi in italiano»: la lingua principale si sceglie (8C).
 * Plus e Portfolio: «Scrivi in italiano, traduciamo noi in inglese, francese, tedesco e spagnolo…» diventa
 * «Scrivi nella tua lingua, traduciamo noi nelle altre…» (in inglese era già così, 038).
 * Solo dove c'è ancora il testo di serie; i testi cambiati dall'amministratore non si toccano.
 * SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */

use MHW\Db;

return function (\PDO $pdo): void {
    $vecchia = 'Scrivi in italiano, traduciamo noi in inglese, francese, tedesco e spagnolo: tu controlli e confermi. Il primo anno è in omaggio.';
    $nuova = 'Scrivi nella tua lingua, traduciamo noi nelle altre: tu controlli e confermi. Il primo anno è in omaggio.';
    foreach (Db::all('SELECT id, bullets FROM packages WHERE bullets LIKE ?', ['%' . $vecchia . '%']) as $p) {
        Db::update('packages', ['bullets' => str_replace($vecchia, $nuova, (string) $p['bullets'])], 'id = :id', ['id' => $p['id']]);
    }
};
