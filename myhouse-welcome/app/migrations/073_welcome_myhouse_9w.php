<?php
/**
 * 073 — 9W · Welcome MyHouse e promo per sempre (27 novembre 2026). Solo aggiunte e
 * conversioni, SQLite e MySQL; una seconda esecuzione non trova più niente da cambiare.
 *
 * 1. Il nome del progetto: «MyHouse Welcome» → «Welcome MyHouse» nei testi salvati (Testi e foto, SEO e GEO, piani,
 *    funzioni, testimonianze). Gli archivi restano come sono: le versioni dei documenti legali già accettate, le offerte e
 *    i preventivi mandati. Gli indirizzi (dominio) non si toccano.
 * 2. discount_codes.per_sempre: lo sconto di una promo «per sempre» vale anche a ogni rinnovo (Sconti::bloccato). Le promo
 *    in home (la Promo novembre) diventano per sempre, anche per chi le ha già pagate: i loro account vanno in
 *    storage/.sconti-da-riallineare.json e il giro dei richiami mette lo sconto sul rinnovo su Stripe (una migrazione non
 *    chiama Stripe).
 *
 * Correzione del 27 novembre (titolare): niente Promo dicembre nel codice (il titolare la crea a mano in Codici sconto) e
 * i prezzi restano quelli di prima. Se questa migrazione era già girata con la versione di prima (DICEMBRE17 e listino
 * nuovo), la 074 rimette le cose a posto.
 */
use MHW\Migrator;

return function (\PDO $pdo): void {
    // 1 · Il nome
    $nomi = [['MyHouse Welcome', 'Welcome MyHouse'], ['myhouse welcome', 'welcome myhouse'], ['MYHOUSE WELCOME', 'WELCOME MYHOUSE']];
    $dove = ['site_texts' => ['valore'], 'seo_settings' => ['valore'], 'features' => ['label'], 'testimonials' => ['body'],
             'packages' => ['name', 'tagline', 'headline', 'description', 'bullets', 'badge', 'cta_label', 'tagline_en', 'headline_en', 'badge_en', 'cta_label_en', 'description_en', 'bullets_en']];
    foreach ($dove as $tab => $colonne) {
        if (!Migrator::tableExists($tab)) continue;
        foreach ($colonne as $col) {
            if (!Migrator::columnExists($tab, $col)) continue;
            foreach ($nomi as [$da, $a]) $pdo->prepare("UPDATE $tab SET $col = REPLACE($col, ?, ?) WHERE $col LIKE ?")->execute([$da, $a, '%' . $da . '%']);
        }
    }

    // 2 · Le promo per sempre
    if (Migrator::tableExists('discount_codes')) {
        if (!Migrator::columnExists('discount_codes', 'per_sempre')) $pdo->exec('ALTER TABLE discount_codes ADD COLUMN per_sempre INTEGER NOT NULL DEFAULT 0');
        if (Migrator::columnExists('discount_codes', 'in_home')) $pdo->exec("UPDATE discount_codes SET per_sempre = 1 WHERE kind = 'percent' AND per_sempre = 0 AND (in_home = 1 OR nome_promo <> '')");

        // Chi ha già pagato con una promo per sempre: lo sconto sul rinnovo si mette su Stripe al giro dei richiami.
        if (Migrator::tableExists('discount_redemptions')) {
            $ids = array_map('intval', $pdo->query("SELECT DISTINCT r.account_id FROM discount_redemptions r JOIN discount_codes c ON c.id = r.discount_code_id
                                                     JOIN subscriptions s ON s.account_id = r.account_id
                                                     WHERE c.per_sempre = 1 AND s.provider = 'stripe' AND s.status IN ('active', 'trialing', 'past_due')")->fetchAll(\PDO::FETCH_COLUMN));
            if ($ids) {
                $file = MHW_APP . '/storage/.sconti-da-riallineare.json';
                $prima = is_file($file) ? (json_decode((string) @file_get_contents($file), true) ?: []) : [];
                @file_put_contents($file, json_encode(array_values(array_unique(array_merge(array_map('intval', $prima), $ids)))), LOCK_EX);
            }
        }
    }
};
