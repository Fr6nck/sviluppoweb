<?php
/**
 * 017 — Parcheggio, prezzi e «Muoversi in zona» (fase 6C).
 *
 * Il costo del parcheggio scritto a mano diventa «all'ora», «al giorno» e una
 * nota; il prezzo dei servizi extra diventa importo e unità; l'elenco «uno per
 * riga» di «Muoversi in zona» diventa una scheda per voce. Fa tutto Conversione,
 * la stessa usata per le guide già pubblicate (Guide::daFormato5). Quello che
 * non si riconosce resta intero nella nota; le vecchie chiavi restano nel JSON.
 */

use MHW\{Conversione, Db};

return function (\PDO $pdo): void {
    $sezioni = Db::all("SELECT s.id, s.kind, s.data, p.default_locale FROM sections s JOIN properties p ON p.id = s.property_id
                        WHERE s.kind IN ('parking', 'extras', 'transport')");
    foreach ($sezioni as $s) {
        $dati = json_decode((string) $s['data'], true) ?: [];
        $testi = [];
        foreach (Db::all('SELECT locale, data FROM section_translations WHERE section_id = ?', [$s['id']]) as $t) {
            $testi[$t['locale']] = json_decode((string) $t['data'], true) ?: [];
        }
        [$nuoviDati, $nuoviTesti] = Conversione::sezione((string) $s['kind'], $dati, $testi, (string) $s['default_locale']);
        if ($nuoviDati !== $dati) {
            Db::update('sections', ['data' => json_encode($nuoviDati, JSON_UNESCAPED_UNICODE)], 'id = :sid', ['sid' => $s['id']]);
        }
        foreach ($nuoviTesti as $loc => $t) {
            if (($testi[$loc] ?? null) === $t) continue;
            Db::run('UPDATE section_translations SET data = ? WHERE section_id = ? AND locale = ?',
                    [json_encode($t, JSON_UNESCAPED_UNICODE), $s['id'], $loc]);
        }
    }
};
