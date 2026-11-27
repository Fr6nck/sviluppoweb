<?php
/**
 * 073 — 9W · Welcome MyHouse, prezzi nuovi, promo per sempre, Promo dicembre (27 novembre 2026). Solo aggiunte e
 * conversioni, SQLite e MySQL; una seconda esecuzione non trova più niente da cambiare.
 *
 * 1. Il nome del progetto: «MyHouse Welcome» → «Welcome MyHouse» nei testi salvati (Testi e foto, SEO e GEO, piani,
 *    funzioni, testimonianze). Gli archivi restano come sono: le versioni dei documenti legali già accettate, le offerte e
 *    i preventivi mandati. Gli indirizzi (dominio) non si toccano.
 * 2. discount_codes.per_sempre: lo sconto di una promo «per sempre» vale anche a ogni rinnovo (Sconti::bloccato). Le promo
 *    in home (la Promo novembre) diventano per sempre, anche per chi le ha già pagate: i loro account vanno in
 *    storage/.sconti-da-riallineare.json e il giro dei richiami mette lo sconto sul rinnovo su Stripe (una migrazione non
 *    chiama Stripe).
 * 3. DICEMBRE17 · Promo dicembre: 17% per sempre, dal 1° al 31 dicembre 2026 (giorni interi Europe/Rome), tutti i piani,
 *    utilizzi illimitati, solo nuovi clienti, si somma al giudizio e non all'invito (come la Promo novembre).
 * 4. Il listino: Essential 87 €, Plus 117 €, Portfolio da 167 € (2 strutture: la prima struttura 10 € in meno, gli
 *    scaglioni come prima). Versioni nuove con le stesse funzioni; il Price di Stripe lo crea il sito alla prima vendita.
 *    Decisione del titolare: anche chi è già abbonato paga il prezzo nuovo dal prossimo rinnovo. Gli abbonamenti attivi
 *    vanno in storage/.listino-da-riallineare.json: li porta ai prezzi nuovi il giro dei richiami (Listino::inSospeso).
 */
use MHW\{Migrator, Plans};

return function (\PDO $pdo): void {
    $ora = gmdate('Y-m-d\TH:i:s\Z');

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

        // 3 · DICEMBRE17
        $c = $pdo->prepare('SELECT id FROM discount_codes WHERE code = ?');
        $c->execute(['DICEMBRE17']);
        if (!$c->fetchColumn() && Migrator::columnExists('discount_codes', 'in_home')) {
            $pdo->prepare("INSERT INTO discount_codes (code, kind, value, valid_from, valid_until, max_uses, packages, note, active, stripe_coupon_id, created_at, somma_giudizio, somma_invito, in_home, nome_promo, per_sempre)
                           VALUES ('DICEMBRE17', 'percent', 17, '2026-12-01', '2026-12-31', 0, '', 'Promo dicembre (9W): 17% per sempre, solo nuovi clienti, si somma al Giudizio con sconto', 1, '', ?, 1, 0, 1, 'Promo dicembre', 1)")
                ->execute([$ora]);
        }

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

    // 4 · Il listino
    $nuove = 0;
    foreach (['essential' => 8700, 'plus' => 11700, 'portfolio' => 16700] as $code => $obiettivo) {
        $q = $pdo->prepare('SELECT pv.* FROM package_versions pv JOIN packages p ON p.id = pv.package_id WHERE p.code = ? AND pv.is_current = 1');
        $q->execute([$code]);
        $cv = $q->fetch(\PDO::FETCH_ASSOC);
        if (!$cv) continue;
        $base = $obiettivo;
        // Portfolio: «da 167 €» è il prezzo con il minimo di strutture (2); la prima struttura scende, gli scaglioni restano.
        if ((int) $cv['per_property'] === 1) $base = $obiettivo - (Plans::price($cv, max(1, (int) $cv['min_quantity'])) - (int) $cv['price_cents']);
        if ($base <= 0 || $base === (int) $cv['price_cents']) continue;
        $riga = $cv;
        unset($riga['id']);
        $v = $pdo->prepare('SELECT COALESCE(MAX(version), 0) + 1 FROM package_versions WHERE package_id = ?');
        $v->execute([(int) $cv['package_id']]);
        $riga = ['version' => (int) $v->fetchColumn(), 'price_cents' => $base, 'is_current' => 1, 'sold_count' => 0, 'stripe_price_id' => '', 'created_at' => $ora] + $riga;
        $pdo->prepare('UPDATE package_versions SET is_current = 0 WHERE package_id = ?')->execute([(int) $cv['package_id']]);
        $campi = array_keys($riga);
        $pdo->prepare('INSERT INTO package_versions (' . implode(', ', $campi) . ') VALUES (' . implode(', ', array_fill(0, count($campi), '?')) . ')')->execute(array_values($riga));
        $nuova = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO package_features (package_version_id, feature_id, value) SELECT ?, feature_id, value FROM package_features WHERE package_version_id = ?')
            ->execute([$nuova, (int) $cv['id']]);
        $nuove++;
    }
    if ($nuove) {
        // Gli abbonamenti pagati con Stripe ancora vivi: Listino::porta decide caso per caso (offerte e staff restano).
        $subs = array_map('intval', $pdo->query("SELECT id FROM subscriptions WHERE provider = 'stripe' AND provider_subscription_id <> '' AND status IN ('active', 'trialing', 'past_due') ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN));
        if ($subs) {
            $file = MHW_APP . '/storage/.listino-da-riallineare.json';
            $prima = is_file($file) ? (json_decode((string) @file_get_contents($file), true) ?: []) : [];
            @file_put_contents($file, json_encode(array_values(array_unique(array_merge(array_map('intval', $prima), $subs)))), LOCK_EX);
        }
    }
};
