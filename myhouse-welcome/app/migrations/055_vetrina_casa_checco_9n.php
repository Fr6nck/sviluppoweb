<?php
/**
 * 055 — 9N · Rigenera la guida vetrina «Casa Checco» con i contenuti nuovi (Demo::creaVetrina): imposta di soggiorno
 * «4 € a persona» in tutte le lingue (la guida aggiunge «a notte»), due reti Wi-Fi con due password, commiato con solo
 * Google e il sito della casa (casachecco.it, rel=nofollow), «Marta» con il ruolo «Pulizie e chiavi» tradotto, eventi
 * «ogni anno», farmacia e pronto soccorso con «Apri Maps». Tocca solo le guide vetrina (is_demo = 2), mai quelle dei
 * clienti; l'indirizzo /g/casa-checco resta lo stesso. Se non riesce resta la vetrina di prima: si rifà da
 * Amministrazione → cliente → «Rifai la vetrina». Il database non cambia struttura.
 */
use MHW\{Db, Demo, Log, Migrator};

return function (\PDO $pdo): void {
    if (!Migrator::tableExists('properties') || !Migrator::columnExists('properties', 'is_demo')) return;
    foreach (Db::all('SELECT DISTINCT account_id FROM properties WHERE is_demo = ?', [Demo::VETRINA]) as $r) {
        try {
            Demo::eliminaVetrina((int) $r['account_id']);
            Demo::creaVetrina((int) $r['account_id'], false);
        } catch (\Throwable $e) {
            Log::error('055 vetrina: ' . $e->getMessage(), ['account' => (int) $r['account_id']]);
        }
    }
};
