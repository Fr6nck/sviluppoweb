<?php
/**
 * 010 — Wi-Fi con più reti.
 *
 * La rete di prima (network, password in sections.data) diventa la prima riga
 * di «networks»; ogni lingua riceve la riga corrispondente (zona vuota). Le
 * vecchie chiavi restano nel JSON.
 */

use MHW\{Conversione, Db};

return function (\PDO $pdo): void {
    foreach (Db::all("SELECT id, data FROM sections WHERE kind = 'wifi'") as $s) {
        $dati = json_decode((string) $s['data'], true) ?: [];
        $testi = [];
        foreach (Db::all('SELECT id, locale, data FROM section_translations WHERE section_id = ?', [$s['id']]) as $t) {
            $testi[$t['locale']] = json_decode((string) $t['data'], true) ?: [];
        }
        [$nuoviDati, $nuoviTesti] = Conversione::wifi($dati, $testi);
        if ($nuoviDati === $dati) continue;
        Db::update('sections', ['data' => json_encode($nuoviDati, JSON_UNESCAPED_UNICODE)], 'id = :sid', ['sid' => $s['id']]);
        foreach ($nuoviTesti as $loc => $t) {
            Db::run('UPDATE section_translations SET data = ? WHERE section_id = ? AND locale = ?',
                    [json_encode($t, JSON_UNESCAPED_UNICODE), $s['id'], $loc]);
        }
    }
};
