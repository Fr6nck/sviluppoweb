<?php
/**
 * 005 — Testi dei piani per la landing e copertina della demo.
 *
 * 1. Testo commerciale dei pacchetti (titolo, descrizione, elenco, bottone).
 *    Un campo cambia soltanto se contiene ancora un testo predefinito (quello
 *    della 003 o della 004): quello che l'amministratore ha scritto resta.
 *    Prezzi, versioni, funzioni e Price ID di Stripe non si toccano.
 *
 * 2. La copertina della demo Casa Lucia: se è ancora la vecchia foto, diventa
 *    la facciata del borgo usata sulla landing. Solo strutture demo, solo quella
 *    foto; l'account non si ricrea e i media delle strutture vere non si toccano.
 *    Se l'archivio dei file non risponde, si lascia com'è e si annota nel
 *    registro: una foto non vale un sito fermo.
 */

use MHW\{Db, Guide, Log, Media};

return function (\PDO $pdo): void {
    // ------------------------------------------------- 1. testi dei pacchetti
    $portfolioElenco003 = "Funzionalità Plus per ogni struttura\nUna guida e un QR per ogni struttura\nStatistiche distinte\nTutto dallo stesso account";
    $portfolioElenco004 = "Tutte le funzioni di Plus, per ogni struttura\nPiù guide dallo stesso account\nUna guida e un QR per ogni struttura\nStatistiche distinte\nUn solo pannello di controllo";
    $portfolio = [
        'headline' => [['Più strutture. Un solo account.', 'Il Plus per più strutture.'], 'Il Plus per più strutture.'],
        'description' => [['Per proprietari e gestori che vogliono amministrare più Welcome Guide dallo stesso pannello.',
                           'Tutte le funzioni di Plus, estese a più strutture gestite dallo stesso account.'],
                          'Gestisci più Welcome Guide da un unico pannello, con contenuti, QR Code e statistiche distinti per ogni struttura.'],
        'bullets' => [[$portfolioElenco003, $portfolioElenco004],
                      "Tutte le funzionalità Plus, per ogni struttura\nUna guida e un QR Code per ogni struttura\nStatistiche distinte\nUn unico pannello di controllo"],
        'cta_label' => [['Scegli Portfolio', 'Scopri Portfolio'], 'Crea gratis con Portfolio'],
    ];

    // codice => campo => [[testi predefiniti conosciuti], testo nuovo]
    $cambi = [
        'essential' => [
            'tagline' => [['Meno domande ripetitive, più tempo per accogliere.'], ''],
            'headline' => [['Tutto ciò che serve per il soggiorno.', "L'essenziale, curato."], 'Le informazioni importanti, sempre a disposizione.'],
            'description' => [['Una guida semplice e professionale con le informazioni più importanti della tua struttura.',
                               'Check-in, quattro sezioni a scelta e due lingue, per una struttura.'],
                              'Una guida semplice e professionale per condividere ciò che conta durante il soggiorno.'],
            'bullets' => [["1 struttura\nCheck-in & Check-out incluso\n4 sezioni a scelta\nItaliano + Inglese\nLogo\nFoto copertina\nPersonalizzazione colori\nQR permanente\nLink personale\nCMS",
                           "1 struttura\nCheck-in & Check-out incluso\n4 sezioni a scelta\nItaliano e inglese\nLogo e foto di copertina\nColori personalizzati\nQR permanente e link personale\nPannello di controllo"],
                          "1 struttura\nCheck-in & Check-out incluso\n4 sezioni aggiuntive a scelta\nItaliano + Inglese\nLogo della struttura\nFoto di copertina\nPersonalizzazione colori\nLink e QR Code permanenti\nPannello di controllo"],
            'cta_label' => [['Crea gratis', 'Comincia con Essential'], 'Crea gratis'],
        ],
        'plus' => [
            'tagline' => [['Trasforma la guida in un vero concierge digitale.'], ''],
            'headline' => [['Dalla struttura al territorio.', 'Il piano completo per una struttura.'], 'Una guida completa, senza limiti di sezioni.'],
            'description' => [["Una guida completa e multilingua per accompagnare l'ospite durante tutto il soggiorno.",
                               "Tutte le sezioni, cinque lingue, foto e PDF per accompagnare l'ospite dall'arrivo alla partenza."],
                              'Per raccontare ogni dettaglio della struttura e accompagnare gli ospiti alla scoperta del territorio.'],
            'badge' => [['Più scelto', 'Più completo'], 'Più completo'],
            'bullets' => [["1 struttura\nSezioni predefinite illimitate\n5 lingue pubblicabili\nLogo\nFoto copertina\nImmagine profilo\nImmagini nelle sezioni\nPDF\nStatistiche di lettura\nPersonalizzazione colori\nQR permanente\nCMS",
                           "1 struttura\nTutte le sezioni disponibili\n5 lingue pubblicabili\nLogo e foto di copertina\nFoto esplicative nelle sezioni\nPDF utili allegati alle sezioni\nStatistiche di lettura\nColori personalizzati\nQR permanente e link personale\nPannello di controllo"],
                          "Tutte le funzionalità Essential\nSezioni predefinite illimitate\n5 lingue pubblicabili\nFoto esplicative nelle sezioni\nPossibilità di allegare documenti PDF\nStatistiche di lettura\nPersonalizzazione colori\nPannello di controllo"],
            'cta_label' => [['Crea gratis con Plus', 'Comincia con Plus'], 'Crea gratis con Plus'],
        ],
        'portfolio2' => $portfolio,
        'portfolio3' => $portfolio,
    ];

    foreach ($cambi as $code => $campi) {
        $pk = Db::one('SELECT * FROM packages WHERE code = ?', [$code]);
        if (!$pk) continue;
        $nuovi = [];
        foreach ($campi as $campo => [$conosciuti, $dopo]) {
            if (array_key_exists($campo, $pk) && in_array((string) $pk[$campo], $conosciuti, true) && (string) $pk[$campo] !== $dopo) {
                $nuovi[$campo] = $dopo;
            }
        }
        if ($nuovi) Db::update('packages', $nuovi, 'id = :pid', ['pid' => $pk['id']]);
    }

    // ------------------------------------------------ 2. copertina della demo
    $foto = (defined('MHW_PUBLIC') ? MHW_PUBLIC : dirname(__DIR__) . '/public') . '/assets/foto/casa.jpg';
    if (!is_file($foto)) return;
    $vecchie = Db::all(
        "SELECT p.id, p.account_id, p.status, p.cover_media_id FROM properties p JOIN media m ON m.id = p.cover_media_id
         WHERE p.is_demo = 1 AND m.alt = 'La casa in pietra vista dal vialetto'");
    foreach ($vecchie as $p) {
        try {
            $nuova = Media::importImage($foto, (int) $p['account_id'], (int) $p['id'], 'La facciata in pietra con la scalinata e i gerani');
            if (!$nuova) continue;
            Db::update('properties', ['cover_media_id' => $nuova], 'id = :pid', ['pid' => $p['id']]);
            Media::delete((int) $p['cover_media_id'], (int) $p['account_id']);
            // La demo pubblicata è un'istantanea: la si ripubblica perché la veda anche l'ospite.
            if ($p['status'] === 'published') Guide::publish((int) $p['id']);
        } catch (\Throwable $e) {
            Log::exception($e, 'migrazione 005: copertina della demo');
        }
    }
};
