<?php
/**
 * 074 — 9W (correzione del 27 novembre 2026) · Prezzi di prima e niente Promo dicembre nel codice. Decisione del titolare:
 * la Promo dicembre la crea lui a mano in Codici sconto; i prezzi restano quelli di prima (semmai aumenta lo sconto).
 *
 * Serve solo dove la prima versione della 073 (pacchetto 9W del 27 novembre) è già girata; altrove non trova niente.
 *  1. DICEMBRE17 creato da quella 073 (nota «Promo dicembre (9W)…»): si cancella se nessuno l'ha usato, altrimenti si spegne.
 *     Un codice creato a mano dal titolare non si tocca.
 *  2. Le versioni di Essential a 87 €, Plus a 117 € e Portfolio a 107 € la prima create da quella 073: torna in vendita la
 *     versione da cui venivano (stesse funzioni, prezzo di prima). Chi ha comprato su quelle versioni ci resta.
 *  3. L'elenco degli abbonamenti da portare ai prezzi nuovi si cancella; quelli già portati (Registro azioni «listino.fatto»
 *     e «listino.programmato») tornano alla versione di prima: storage/.listino-ripristino.json, che il giro dei richiami
 *     svuota (Listino::ripristinaInSospeso), su Stripe senza proporzioni. Una migrazione non chiama Stripe.
 * Solo dati, SQLite e MySQL; una seconda esecuzione non trova più niente da cambiare.
 */
use MHW\{Migrator, Plans};

return function (\PDO $pdo): void {
    // 1 · DICEMBRE17 della prima 073
    if (Migrator::tableExists('discount_codes')) {
        $c = $pdo->prepare("SELECT id FROM discount_codes WHERE code = 'DICEMBRE17' AND note LIKE ?");
        $c->execute(['Promo dicembre (9W)%']);
        if ($id = (int) $c->fetchColumn()) {
            $usato = Migrator::tableExists('discount_redemptions') ? (int) $pdo->query('SELECT COUNT(*) FROM discount_redemptions WHERE discount_code_id = ' . $id)->fetchColumn() : 0;
            if ($usato) $pdo->prepare("UPDATE discount_codes SET active = 0, in_home = 0 WHERE id = ?")->execute([$id]);
            else $pdo->prepare('DELETE FROM discount_codes WHERE id = ?')->execute([$id]);
        }
    }

    // 2 · Il listino di prima
    $funzioni = function (int $pv) use ($pdo): array {
        $q = $pdo->prepare('SELECT feature_id, value FROM package_features WHERE package_version_id = ? ORDER BY feature_id');
        $q->execute([$pv]);
        return $q->fetchAll(\PDO::FETCH_KEY_PAIR);
    };
    foreach (['essential' => 8700, 'plus' => 11700, 'portfolio' => 16700] as $code => $prezzo9w) {
        $q = $pdo->prepare('SELECT pv.* FROM package_versions pv JOIN packages p ON p.id = pv.package_id WHERE p.code = ? AND pv.is_current = 1');
        $q->execute([$code]);
        $cv = $q->fetch(\PDO::FETCH_ASSOC);
        if (!$cv || (string) $cv['created_at'] < '2026-11-27') continue;
        $suo = (int) $cv['per_property'] === 1 ? Plans::price($cv, max(1, (int) $cv['min_quantity'])) : (int) $cv['price_cents'];
        if ($suo !== $prezzo9w) continue;
        $p = $pdo->prepare('SELECT * FROM package_versions WHERE package_id = ? AND version = ?');
        $p->execute([(int) $cv['package_id'], (int) $cv['version'] - 1]);
        $prima = $p->fetch(\PDO::FETCH_ASSOC);
        if (!$prima || (int) $prima['price_cents'] <= (int) $cv['price_cents'] || $funzioni((int) $prima['id']) != $funzioni((int) $cv['id'])) continue;
        $pdo->prepare('UPDATE package_versions SET is_current = 0 WHERE package_id = ?')->execute([(int) $cv['package_id']]);
        $pdo->prepare('UPDATE package_versions SET is_current = 1 WHERE id = ?')->execute([(int) $prima['id']]);
    }

    // 3 · Gli abbonamenti: niente più passaggi ai prezzi nuovi; quelli già fatti tornano indietro
    @unlink(MHW_APP . '/storage/.listino-da-riallineare.json');
    if (!Migrator::tableExists('audit_log')) return;
    $indietro = [];
    foreach ($pdo->query("SELECT action, meta FROM audit_log WHERE action IN ('listino.fatto', 'listino.programmato') ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC) as $r) {
        $m = json_decode((string) $r['meta'], true) ?: [];
        if (empty($m['subscription']) || empty($m['da']['pv']) || empty($m['a']['pv'])) continue;
        $indietro[(int) $m['subscription']] = ['s' => (int) $m['subscription'], 'pv' => (int) $m['da']['pv'], 'q' => max(1, (int) ($m['da']['q'] ?? 1)),
                                               'a' => (int) $m['a']['pv'], 'tipo' => $r['action'] === 'listino.fatto' ? 'fatto' : 'programmato'];
    }
    if ($indietro) @file_put_contents(MHW_APP . '/storage/.listino-ripristino.json', json_encode(array_values($indietro)), LOCK_EX);
};
