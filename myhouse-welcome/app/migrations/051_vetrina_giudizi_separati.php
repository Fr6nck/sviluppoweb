<?php
/**
 * 051 — 9L (revisione del 20 ottobre) · Le due pubblicazioni del programma «Giudizio con sconto», approvate separatamente:
 * la scheda in «Le guide dei nostri host» e il giudizio in «Cosa dicono di noi».
 *
 * 1. vetrina_schede, due gruppi di colonne:
 *    scheda   → scheda_ordine (posizione decisa in admin), scheda_approvata_at, scheda_ritirata_at e scheda_motivo (ritiro
 *               dell'amministrazione, motivo interno). scheda_home (050) resta: 1 = approvata e mostrata (in home o in «Tutte le guide»).
 *    giudizio → giudizio_home (0/1), giudizio_ordine, giudizio_approvato_at, giudizio_ritirato_at e giudizio_motivo.
 *    risposta_pubblica, immagini, nota_admin, ritirato_at (ritiro dell'host, spegne tutte e due) restano quelle della 050.
 * 2. I dati di prima: le schede accese prendono la data di approvazione (decisa_at) e un ordine (le più recenti prima, come
 *    le mostrava la home); i giudizi già approvati (testimonials «pubblicata») diventano giudizi accesi, nello stesso ordine.
 *    La sospensione «manca la copertina» non esiste più (la scheda senza copertina mostra il segnaposto): si azzera.
 *
 * Solo aggiunte, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */
use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    if (!Migrator::tableExists('vetrina_schede')) return;
    $colonne = [
        'scheda_ordine' => 'INTEGER NOT NULL DEFAULT 0', 'scheda_approvata_at' => 'VARCHAR(25) NULL', 'scheda_ritirata_at' => 'VARCHAR(25) NULL',
        'scheda_motivo' => 'VARCHAR(255) NULL',
        'giudizio_home' => 'INTEGER NOT NULL DEFAULT 0', 'giudizio_ordine' => 'INTEGER NOT NULL DEFAULT 0', 'giudizio_approvato_at' => 'VARCHAR(25) NULL',
        'giudizio_ritirato_at' => 'VARCHAR(25) NULL', 'giudizio_motivo' => 'VARCHAR(255) NULL',
    ];
    $nuove = false;
    foreach ($colonne as $c => $tipo) {
        if (Migrator::columnExists('vetrina_schede', $c)) continue;
        $pdo->exec("ALTER TABLE vetrina_schede ADD COLUMN $c $tipo");
        $nuove = true;
    }
    if (!$nuove) return;   // già fatta

    // 2. Le schede accese: approvate quando erano state decise, nell'ordine in cui le mostrava la home.
    $n = 0;
    foreach (Db::all("SELECT id, decisa_at, updated_at FROM vetrina_schede WHERE scheda_home = 1 ORDER BY scheda_da DESC, id DESC") as $s) {
        Db::update('vetrina_schede', ['scheda_ordine' => ++$n, 'scheda_approvata_at' => $s['decisa_at'] ?: $s['updated_at']], 'id = :id', ['id' => (int) $s['id']]);
    }
    $pdo->exec("UPDATE vetrina_schede SET sospesa = '' WHERE sospesa = 'copertina'");
    // I giudizi già approvati sulla scheda: accesi in «Cosa dicono di noi».
    if (Migrator::tableExists('testimonials')) {
        $g = 0;
        foreach (Db::all("SELECT v.id, t.published_at FROM vetrina_schede v JOIN testimonials t ON t.account_id = v.account_id
                          WHERE t.status = 'pubblicata' AND t.withdrawn_at IS NULL AND v.ritirato_at IS NULL ORDER BY t.published_at DESC, v.id DESC") as $r) {
            Db::update('vetrina_schede', ['giudizio_home' => 1, 'giudizio_ordine' => ++$g, 'giudizio_approvato_at' => $r['published_at'] ?: null], 'id = :id', ['id' => (int) $r['id']]);
        }
    }
};
