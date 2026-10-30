<?php
/**
 * 009 — Arrivo e partenza.
 *
 * Le cinque caselle fisse della partenza (chiavi, rifiuti, luci, clima,
 * finestre) diventano, lingua per lingua, voci della lista «Prima di partire»,
 * nello stesso ordine e con la loro etichetta. I vecchi campi restano nel JSON:
 * non si leggono più, ma non si perde niente. I campi nuovi dell'arrivo
 * (modalità, arrivo tardivo, imposta di soggiorno, documenti) partono vuoti.
 */

use MHW\{Conversione, Db};

return function (\PDO $pdo): void {
    $righe = Db::all("SELECT t.id, t.locale, t.data FROM section_translations t JOIN sections s ON s.id = t.section_id WHERE s.kind = 'checkin'");
    foreach ($righe as $t) {
        $d = json_decode((string) $t['data'], true);
        if (!is_array($d)) continue;
        $nuovo = Conversione::partenza($d, (string) $t['locale']);
        if ($nuovo !== $d) Db::update('section_translations', ['data' => json_encode($nuovo, JSON_UNESCAPED_UNICODE)], 'id = :tid', ['tid' => $t['id']]);
    }
};
