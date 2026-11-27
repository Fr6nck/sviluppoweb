<?php
/**
 * 028 — Listino 2026 e «per chi è» ogni piano.
 *
 * 1. Prezzi nuovi (annui, IVA esclusa) come VERSIONI NUOVE, come fa l'amministratore:
 *      Essential 97 €, Plus 127 €, Portfolio 127 € + 70 € per ogni struttura in più, da 2 a 10.
 *    Il Portfolio torna a un prezzo unico per struttura (extra_tiers vuoto): gli scaglioni
 *    della 026 restano possibili da Amministrazione → Piani, ma non sono più in vendita.
 *    Chi è già abbonato resta sulla sua versione e rinnova al suo prezzo. Le funzioni incluse
 *    si copiano dalla versione in vendita. I Price ID di Stripe restano vuoti: Stripe usa i
 *    prezzi del database. Chi ha solo scelto un piano (senza abbonamento attivo) passa alla
 *    versione nuova, come nella 013. Se la versione in vendita ha già questi valori, niente.
 * 2. Portfolio: in coda all'elenco «Più di 10 strutture? Scrivici per un preventivo.»
 * 3. Il posizionamento: in packages.tagline, per chi è indicato ogni piano (la landing e la
 *    scelta del piano lo mostrano come «Ideale per …»). Solo se l'amministratore non l'ha scritto.
 */

use MHW\{Db, Migrator, Support};

return function (\PDO $pdo): void {
    $ora = Support::now();
    $tiers = Migrator::columnExists('package_versions', 'extra_tiers');
    $listino = [
        'essential' => ['price_cents' => 9700, 'extra_price_cents' => null, 'min_quantity' => null, 'max_quantity' => null],
        'plus'      => ['price_cents' => 12700, 'extra_price_cents' => null, 'min_quantity' => null, 'max_quantity' => null],
        'portfolio' => ['price_cents' => 12700, 'extra_price_cents' => 7000, 'min_quantity' => 2, 'max_quantity' => 10],
    ];
    foreach ($listino as $code => $nuovi) {
        Db::tx(function () use ($code, $nuovi, $ora, $tiers) {
            $pk = Db::one('SELECT * FROM packages WHERE code = ?', [$code]);
            $pv = $pk ? Db::one('SELECT * FROM package_versions WHERE package_id = ? AND is_current = 1', [$pk['id']]) : null;
            if (!$pv) return;
            $valori = array_filter($nuovi, fn($v) => $v !== null);
            $uguale = true;
            foreach ($valori as $k => $v) if ((int) $pv[$k] !== $v) $uguale = false;
            if ($tiers && (int) $pv['per_property'] === 1 && trim((string) $pv['extra_tiers']) !== '') $uguale = false;
            if ($uguale) return;
            $nuova = $pv;
            unset($nuova['id']);
            $nuova = $valori + $nuova;
            $nuova['version'] = (int) Db::val('SELECT COALESCE(MAX(version),0)+1 FROM package_versions WHERE package_id = ?', [$pk['id']], 1);
            $nuova['is_current'] = 1; $nuova['sold_count'] = 0; $nuova['created_at'] = $ora;
            $nuova['currency'] = 'EUR'; $nuova['interval_unit'] = 'year';
            $nuova['stripe_price_id'] = ''; $nuova['stripe_extra_price_id'] = '';
            if ($tiers) $nuova['extra_tiers'] = '';
            Db::run('UPDATE package_versions SET is_current = 0 WHERE package_id = ?', [$pk['id']]);
            $vid = Db::insert('package_versions', $nuova);
            foreach (Db::all('SELECT feature_id, value FROM package_features WHERE package_version_id = ?', [$pv['id']]) as $f) {
                Db::insert('package_features', ['package_version_id' => $vid, 'feature_id' => $f['feature_id'], 'value' => $f['value']]);
            }
            // Chi l'aveva solo scelto (nessun abbonamento attivo) passa alla versione in vendita.
            Db::run("UPDATE accounts SET intended_package_version_id = ? WHERE intended_package_version_id = ?
                     AND id NOT IN (SELECT account_id FROM subscriptions WHERE status IN ('active', 'trialing'))", [$vid, $pv['id']]);
        });
    }

    $riga = 'Più di 10 strutture? Scrivici per un preventivo.';
    $pf = Db::one("SELECT id, bullets FROM packages WHERE code = 'portfolio'");
    if ($pf && !str_contains((string) $pf['bullets'], $riga)) {
        Db::update('packages', ['bullets' => rtrim((string) $pf['bullets']) . "\n" . $riga], 'id = :id', ['id' => $pf['id']]);
    }

    $perChi = [
        'essential' => 'Per chi affitta una casa o un appartamento e vuole dare agli ospiti, in italiano e inglese, le informazioni che servono: arrivo, Wi-Fi, regole e partenza.',
        'plus'      => 'Per B&B, case vacanza e agriturismi con ospiti da tutto il mondo: cinque lingue, foto, consigli sul territorio e varianti per le camere.',
        'portfolio' => 'Per chi gestisce da 2 a 10 strutture, come property manager o famiglie con più case: una guida per ognuna, un solo account e un solo abbonamento.',
    ];
    foreach ($perChi as $code => $testo) {
        Db::run("UPDATE packages SET tagline = ? WHERE code = ? AND (tagline IS NULL OR tagline = '')", [$testo, $code]);
    }
};
