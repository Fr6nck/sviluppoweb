<?php
/**
 * 038 — 8E: le schede dei piani in inglese per /en.
 *
 * packages.tagline_en, headline_en, description_en, bullets_en, badge_en, cta_label_en: i testi inglesi,
 * modificabili in Amministrazione → Piani (linguetta English). Vuoti = si mostra l'italiano. Qui i valori
 * di serie per essential, plus e portfolio (anche le vecchie portfolio2/portfolio3), solo se ancora vuoti.
 * packages.tagline di Essential: «in italiano e inglese» → «nella tua lingua e in una seconda a scelta» (8C),
 * solo dove c'è ancora il testo di serie.
 *
 * Solo aggiunte, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */

use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    foreach (['tagline_en' => 'VARCHAR(255)', 'headline_en' => 'VARCHAR(255)', 'badge_en' => 'VARCHAR(80)', 'cta_label_en' => 'VARCHAR(120)'] as $c => $tipo) {
        if (!Migrator::columnExists('packages', $c)) $pdo->exec("ALTER TABLE packages ADD COLUMN $c $tipo NOT NULL DEFAULT ''");
    }
    foreach (['description_en', 'bullets_en'] as $c) {
        if (!Migrator::columnExists('packages', $c)) $pdo->exec("ALTER TABLE packages ADD COLUMN $c TEXT NULL");
    }

    $portfolio = [
        'tagline_en' => 'For anyone managing 2 to 10 properties, such as property managers or families with several houses: a guide for each, one account and one subscription.',
        'headline_en' => 'Plus, for several properties.',
        'description_en' => 'Manage several guides from a single dashboard: each property has its own content, QR code and statistics.',
        'bullets_en' => "Everything in Plus, for each property, and also:\nA guide and a QR code for each property\nSeparate statistics for each property\nSections you can copy from one property to another\nOne account and one subscription\nWrite in your language, we translate into the others: you check and confirm. The first year is free.",
        'badge_en' => '', 'cta_label_en' => 'Start free with Portfolio',
    ];
    $serie = [
        'essential' => [
            'tagline_en' => 'For anyone renting out a house or apartment who wants to give guests the essentials, in two languages: arrival, Wi-Fi, house rules and departure.',
            'headline_en' => 'The important information, always at hand.',
            'description_en' => 'A simple, professional guide to share what matters during the stay.',
            'bullets_en' => "1 property\nCheck-in & Check-out section included\n6 extra sections of your choice\n2 languages: yours and one of your choice\nLogo and cover photo\nHost profile photo\nA link and QR code that never change, even when you edit the guide",
            'badge_en' => '', 'cta_label_en' => 'Start free',
        ],
        'plus' => [
            'tagline_en' => 'For B&Bs, holiday homes and farm stays with guests from all over the world: six languages, photos and local tips.',
            'headline_en' => 'A complete guide, with no limit on sections.',
            'description_en' => 'To describe every detail of your property and help guests discover the area.',
            'bullets_en' => "Everything in Essential, and also:\nAll {sezioni} sections\n6 languages: yours, the others among Italian, English, French, German and Spanish, and one of your choice out of 9\nPhotos and PDFs next to your texts, to show better what you explain\nReading statistics\nThe MyHouse Welcome signature can be hidden\nWrite in your language, we translate into the others: you check and confirm. The first year is free.",
            'badge_en' => 'Most complete', 'cta_label_en' => 'Start free with Plus',
        ],
        'portfolio' => $portfolio, 'portfolio2' => $portfolio, 'portfolio3' => $portfolio,
    ];
    foreach (Db::all('SELECT * FROM packages') as $p) {
        $v = $serie[$p['code']] ?? null;
        if (!$v) continue;
        $cambi = [];
        foreach ($v as $c => $testo) if (trim((string) ($p[$c] ?? '')) === '' && $testo !== '') $cambi[$c] = $testo;
        if ($cambi) Db::update('packages', $cambi, 'id = :id', ['id' => $p['id']]);
    }

    $vecchia = 'Per chi affitta una casa o un appartamento e vuole dare agli ospiti, in italiano e inglese, le informazioni che servono: arrivo, Wi-Fi, regole e partenza.';
    $nuova = 'Per chi affitta una casa o un appartamento e vuole dare agli ospiti, nella sua lingua e in una seconda a scelta, le informazioni che servono: arrivo, Wi-Fi, regole e partenza.';
    Db::run('UPDATE packages SET tagline = ? WHERE tagline = ?', [$nuova, $vecchia]);
    $vecchiaP = 'Per B&B, case vacanza e agriturismi con ospiti da tutto il mondo: cinque lingue, foto e consigli sul territorio.';
    Db::run('UPDATE packages SET tagline = ? WHERE tagline = ?', ['Per B&B, case vacanza e agriturismi con ospiti da tutto il mondo: sei lingue, foto e consigli sul territorio.', $vecchiaP]);
};
