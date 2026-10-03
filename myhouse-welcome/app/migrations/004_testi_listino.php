<?php
/**
 * 004 — I testi del listino sulla landing, riscritti.
 *
 * Tocca SOLO il testo commerciale dei pacchetti (titolo, elenco, etichetta,
 * bottone, frase finale). Prezzi, versioni e funzioni restano come sono.
 *
 * Ogni campo si aggiorna soltanto se contiene ancora il testo originale della
 * 003: se l'amministratore lo ha già cambiato da Amministrazione → Pacchetti,
 * la sua versione vince e qui non si tocca niente.
 */

use MHW\Db;

return function (\PDO $pdo): void {
    $portfolioPrima = [
        'headline' => 'Più strutture. Un solo account.',
        'description' => 'Per proprietari e gestori che vogliono amministrare più Welcome Guide dallo stesso pannello.',
        'bullets' => "Funzionalità Plus per ogni struttura\nUna guida e un QR per ogni struttura\nStatistiche distinte\nTutto dallo stesso account",
        'cta_label' => 'Scegli Portfolio',
    ];
    $portfolioDopo = [
        'headline' => 'Il Plus per più strutture.',
        'description' => 'Tutte le funzioni di Plus, estese a più strutture gestite dallo stesso account.',
        'bullets' => "Tutte le funzioni di Plus, per ogni struttura\nPiù guide dallo stesso account\nUna guida e un QR per ogni struttura\nStatistiche distinte\nUn solo pannello di controllo",
        'cta_label' => 'Scopri Portfolio',
    ];

    // codice => [campo => [testo della 003, testo nuovo]]
    $cambi = [
        'essential' => [
            'tagline' => ['Meno domande ripetitive, più tempo per accogliere.', ''],
            'headline' => ['Tutto ciò che serve per il soggiorno.', "L'essenziale, curato."],
            'description' => ['Una guida semplice e professionale con le informazioni più importanti della tua struttura.',
                              'Check-in, quattro sezioni a scelta e due lingue, per una struttura.'],
            'bullets' => ["1 struttura\nCheck-in & Check-out incluso\n4 sezioni a scelta\nItaliano + Inglese\nLogo\nFoto copertina\nPersonalizzazione colori\nQR permanente\nLink personale\nCMS",
                          "1 struttura\nCheck-in & Check-out incluso\n4 sezioni a scelta\nItaliano e inglese\nLogo e foto di copertina\nColori personalizzati\nQR permanente e link personale\nPannello di controllo"],
            'cta_label' => ['Crea gratis', 'Comincia con Essential'],
        ],
        'plus' => [
            'tagline' => ['Trasforma la guida in un vero concierge digitale.', ''],
            'headline' => ['Dalla struttura al territorio.', 'Il piano completo per una struttura.'],
            'description' => ["Una guida completa e multilingua per accompagnare l'ospite durante tutto il soggiorno.",
                              "Tutte le sezioni, cinque lingue, foto e PDF per accompagnare l'ospite dall'arrivo alla partenza."],
            'badge' => ['Più scelto', 'Più completo'],
            'bullets' => ["1 struttura\nSezioni predefinite illimitate\n5 lingue pubblicabili\nLogo\nFoto copertina\nImmagine profilo\nImmagini nelle sezioni\nPDF\nStatistiche di lettura\nPersonalizzazione colori\nQR permanente\nCMS",
                          "1 struttura\nTutte le sezioni disponibili\n5 lingue pubblicabili\nLogo e foto di copertina\nFoto esplicative nelle sezioni\nPDF utili allegati alle sezioni\nStatistiche di lettura\nColori personalizzati\nQR permanente e link personale\nPannello di controllo"],
            'cta_label' => ['Crea gratis con Plus', 'Comincia con Plus'],
        ],
    ];
    foreach (['portfolio2', 'portfolio3'] as $code) {
        foreach ($portfolioPrima as $campo => $prima) $cambi[$code][$campo] = [$prima, $portfolioDopo[$campo]];
    }

    foreach ($cambi as $code => $campi) {
        $pk = Db::one('SELECT * FROM packages WHERE code = ?', [$code]);
        if (!$pk) continue;
        $nuovi = [];
        foreach ($campi as $campo => [$prima, $dopo]) {
            if (array_key_exists($campo, $pk) && (string) $pk[$campo] === $prima) $nuovi[$campo] = $dopo;
        }
        if ($nuovi) Db::update('packages', $nuovi, 'id = :pid', ['pid' => $pk['id']]);
    }
};
