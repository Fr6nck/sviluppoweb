<?php
/**
 * 020 — Testi coerenti: etichette delle funzioni e testi dei piani.
 *
 * Il copy del pannello e della landing è stato allineato (una parola per ogni cosa:
 * «foto» e non «immagine», «luoghi consigliati», «guida» e non «Welcome Guide»).
 * Qui si allineano i testi che stanno nel database:
 *
 * 1. Le etichette delle funzioni, che si leggono nel confronto dei piani sulla landing
 *    e in Amministrazione → Piani.
 * 2. I testi commerciali dei piani: Essential e Plus con le stesse parole del pannello;
 *    Portfolio parte da «Tutto di Plus» (prima diceva «Tutto di Essential» e poi
 *    «Tutte le funzioni di Plus»).
 *
 * Si cambia solo il testo predefinito: se l'amministratore l'ha già riscritto, resta il suo.
 * Prezzi, versioni, funzioni e Price ID di Stripe non si toccano.
 */

use MHW\Db;

return function (\PDO $pdo): void {
    // codice => [etichetta predefinita, etichetta nuova]
    $etichette = [
        'sections'      => ['Sezioni aggiuntive attive', 'Sezioni aggiuntive'],
        'photos'        => ['Immagini nelle sezioni', 'Foto nelle sezioni'],
        'profile_image' => ['Immagine profilo', 'Foto profilo'],
        'places'        => ['Consigli sul posto', 'Luoghi consigliati'],
    ];
    foreach ($etichette as $code => [$prima, $dopo]) {
        Db::run('UPDATE features SET label = ? WHERE code = ? AND label = ?', [$dopo, $code, $prima]);
    }

    $guide = ['Gestisci più Welcome Guide da un unico pannello, con contenuti, QR Code e statistiche distinti per ogni struttura.'];
    $guideDopo = 'Gestisci più guide da un unico pannello: ogni struttura ha i suoi contenuti, il suo QR Code e le sue statistiche.';
    // codice => campo => [[testi predefiniti conosciuti], testo nuovo]
    $cambi = [
        'essential' => [
            'bullets' => [["1 struttura\nCheck-in & Check-out incluso\n4 sezioni aggiuntive a scelta\nItaliano + Inglese\nLogo della struttura\nFoto di copertina\nLink e QR Code permanenti"],
                          "1 struttura\nSezione Check-in & Check-out inclusa\n4 sezioni aggiuntive a scelta\nItaliano e inglese\nLogo e foto di copertina\nLink e QR Code permanenti"],
        ],
        'plus' => [
            'bullets' => [["Tutto di Essential, e in più:\nSezioni illimitate\n5 lingue pubblicabili\nFoto esplicative nelle sezioni\nDocumenti PDF allegati\nStatistiche di lettura\nFirma MyHouse Welcome nascondibile"],
                          "Tutto di Essential, e in più:\nSezioni illimitate\n5 lingue: italiano, inglese, francese, tedesco, spagnolo\nFoto e PDF nelle sezioni\nFoto profilo dell'host\nStatistiche di lettura\nFirma MyHouse Welcome nascondibile"],
        ],
        'portfolio' => [
            'description' => [$guide, $guideDopo],
            'bullets' => [["Tutto di Essential, e in più:\nTutte le funzioni di Plus, per ogni struttura\nUna guida e un QR Code per ogni struttura\nStatistiche distinte per struttura\nUn solo account per tutte"],
                          "Tutto di Plus, per ogni struttura, e in più:\nUna guida e un QR Code per ogni struttura\nStatistiche distinte per struttura\nSezioni da copiare da una struttura all'altra\nUn solo account e un solo abbonamento"],
        ],
        // I vecchi Portfolio a strutture fisse, fuori listino: solo la descrizione.
        'portfolio2' => ['description' => [$guide, $guideDopo]],
        'portfolio3' => ['description' => [$guide, $guideDopo]],
    ];
    foreach ($cambi as $code => $campi) {
        $pk = Db::one('SELECT * FROM packages WHERE code = ?', [$code]);
        if (!$pk) continue;
        foreach ($campi as $campo => [$prima, $dopo]) {
            if (in_array(trim((string) $pk[$campo]), $prima, true)) Db::update('packages', [$campo => $dopo], 'id = :pid', ['pid' => $pk['id']]);
        }
    }
};
