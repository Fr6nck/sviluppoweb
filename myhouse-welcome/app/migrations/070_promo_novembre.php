<?php
/**
 * 070 — 9U · Promo novembre. Quattro campi nuovi dei codici sconto (solo aggiunte, SQLite e MySQL):
 *   somma_giudizio  1 = si somma al «Giudizio con sconto» (una sola percentuale sul listino, un solo coupon Stripe)
 *   somma_invito    1 = si somma all'invito di un amico (0 = con l'invito vale il più alto)
 *   in_home         1 = in home, /piano e nelle pagine per tipologia: badge, prezzi barrati e fascia, finché vale
 *   nome_promo      il nome commerciale nella fascia e nel riepilogo («Promo novembre»)
 * e il codice NOVEMBRE17: 17% sul primo anno, dal 30 ottobre al 30 novembre 2026 (giorni interi Europe/Rome, quindi fino
 * alle 23:59 del 30), tutti i piani, utilizzi illimitati, si somma al giudizio (−22%) e non all'invito. Se il titolare
 * l'ha già creato non si duplica: si accendono solo le caselle della promo, se il nome non c'è ancora.
 * Il coupon su Stripe è quello combinato del primo anno (mhw-primo-anno-N), creato al pagamento.
 */
use MHW\Migrator;

return function (\PDO $pdo): void {
    if (!Migrator::tableExists('discount_codes')) return;
    foreach (['somma_giudizio' => 'INTEGER NOT NULL DEFAULT 0', 'somma_invito' => 'INTEGER NOT NULL DEFAULT 0', 'in_home' => 'INTEGER NOT NULL DEFAULT 0',
              'nome_promo' => "VARCHAR(80) NOT NULL DEFAULT ''"] as $col => $tipo) {
        if (!Migrator::columnExists('discount_codes', $col)) $pdo->exec("ALTER TABLE discount_codes ADD COLUMN $col $tipo");
    }
    $c = $pdo->prepare('SELECT id, nome_promo FROM discount_codes WHERE code = ?');
    $c->execute(['NOVEMBRE17']);
    $riga = $c->fetch(\PDO::FETCH_ASSOC);
    if ($riga) {
        if ((string) $riga['nome_promo'] === '') $pdo->prepare("UPDATE discount_codes SET somma_giudizio = 1, somma_invito = 0, in_home = 1, nome_promo = 'Promo novembre' WHERE id = ?")->execute([(int) $riga['id']]);
        return;
    }
    $pdo->prepare("INSERT INTO discount_codes (code, kind, value, valid_from, valid_until, max_uses, packages, note, active, stripe_coupon_id, created_at, somma_giudizio, somma_invito, in_home, nome_promo)
                   VALUES ('NOVEMBRE17', 'percent', 17, '2026-10-30', '2026-11-30', 0, '', 'Promo novembre (9U): solo nuovi clienti, si somma al Giudizio con sconto', 1, '', ?, 1, 0, 1, 'Promo novembre')")
        ->execute([gmdate('Y-m-d\TH:i:s\Z')]);
};
