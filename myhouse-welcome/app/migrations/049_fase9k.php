<?php
/**
 * 049 — 9K · Le richieste del programma Partner e la foto nuova di Casa Checco.
 *
 * 1. partner_richieste: chi compila «Diventa partner» su /property-manager (nome, società, contatti, quante strutture,
 *    zone, messaggio), con lo stato per l'amministrazione (nuovo, contattato, attivo, chiuso). Si conservano 12 mesi.
 * 2. La vetrina (Casa Checco) prende la copertina nuova (assets/foto/checco-copertina.jpg) e si ripubblica,
 *    così /g/casa-checco la mostra subito.
 * 3. «Per chi è» passa da 4 a 6 schede con chiavi per nome: i testi già cambiati in «Testi e foto» (anche in inglese)
 *    passano alla scheda giusta (1 → casa vacanza, 3 → agriturismo, 4 → più strutture). La 2 era «B&B e
 *    affittacamere», ora due schede: resta dov'era, da riscrivere a mano se serve.
 * 4. Via la PEC dai testi legali salvati a mano in «Testi e foto» (la PEC non si mostra più da nessuna parte).
 *
 * Solo aggiunte, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */
use MHW\{Db, Demo, Guide, Log, Migrator};

return function (\PDO $pdo): void {
    $pk = Db::isMysql() ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';

    if (!Migrator::tableExists('partner_richieste')) {
        $pdo->exec("CREATE TABLE partner_richieste (
            id $pk,
            nome VARCHAR(120) NOT NULL DEFAULT '',
            societa VARCHAR(160) NOT NULL DEFAULT '',
            email VARCHAR(190) NOT NULL DEFAULT '',
            telefono VARCHAR(40) NOT NULL DEFAULT '',
            strutture VARCHAR(20) NOT NULL DEFAULT '',
            zone VARCHAR(255) NOT NULL DEFAULT '',
            messaggio TEXT NULL,
            stato VARCHAR(16) NOT NULL DEFAULT 'nuovo',
            nota VARCHAR(255) NOT NULL DEFAULT '',
            created_at VARCHAR(25) NOT NULL,
            updated_at VARCHAR(25) NULL
        )");
        $pdo->exec('CREATE INDEX idx_partner_richieste_stato ON partner_richieste(stato)');
    }

    // 3. I testi di «Per chi è» già personalizzati, nelle chiavi nuove (solo se la nuova non c'è già).
    if (Migrator::tableExists('site_texts')) {
        foreach (['1' => 'casa', '3' => 'agri', '4' => 'piu'] as $vecchia => $nuova) {
            foreach (['titolo', 'testo'] as $campo) {
                foreach (['', 'en:'] as $lingua) {
                    $da = $lingua . 'perchi.' . $vecchia . '.' . $campo; $a = $lingua . 'perchi.' . $nuova . '.' . $campo;
                    $v = Db::one('SELECT valore, updated_at FROM site_texts WHERE chiave = ?', [$da]);
                    if ($v && !Db::one('SELECT chiave FROM site_texts WHERE chiave = ?', [$a])) {
                        Db::insert('site_texts', ['chiave' => $a, 'valore' => $v['valore'], 'updated_at' => $v['updated_at']]);
                    }
                }
            }
        }
    }

    // 4. Via la PEC dai testi legali salvati a mano in «Testi e foto» (Termini, Privacy, DPA, anche in inglese):
    //    le stesse frasi dei testi di serie, con l'email assistenza al posto della PEC.
    if (Migrator::tableExists('site_texts')) {
        $frasi = [
            ['(«noi»). PEC {pec} · email {email}.', '(«noi»). Email {email}.'],
            ['{sede}. PEC {pec} · email {email} · telefono', '{sede}. Email {email} · telefono'],
            ['{sede} · PEC {pec} · email {email}', '{sede} · email {email}'],
            ['scrivi a {email} (o alla PEC {pec}):', 'scrivi a {email}:'],
            ['{sede}. PEC {pec} · email {email}.', '{sede}. Email {email}.'],
            ['("we"). Certified email (PEC) {pec} · email {email}.', '("we"). Email {email}.'],
            ['Italy. Certified email (PEC) {pec} · email {email} · phone', 'Italy. Email {email} · phone'],
            ['Italy · PEC {pec} · email {email}', 'Italy · email {email}'],
            ['write to {email} (or to the PEC {pec}):', 'write to {email}:'],
            ['Italy. Certified email (PEC) {pec} · email {email}.', 'Italy. Email {email}.'],
        ];
        foreach (Db::all("SELECT chiave, valore FROM site_texts WHERE valore LIKE '%{pec}%'") as $t) {
            $nuovo = str_replace(array_column($frasi, 0), array_column($frasi, 1), (string) $t['valore']);
            $nuovo = str_replace([' (o alla PEC {pec})', ' (or to the PEC {pec})', 'PEC {pec} · ', 'Certified email (PEC) {pec} · ', '{pec}'], '', $nuovo);
            if ($nuovo !== $t['valore']) Db::update('site_texts', ['valore' => $nuovo], 'chiave = :k', ['k' => $t['chiave']]);
        }
    }

    // 2. La copertina nuova di Casa Checco sulla vetrina pubblicata, poi la ripubblicazione (l'istantanea nuova).
    if (Migrator::tableExists('guide_versions') && Migrator::columnExists('properties', 'is_demo')) {
        foreach (Db::all("SELECT id, status FROM properties WHERE is_demo = 2 AND archived_at IS NULL") as $v) {
            try {
                if (Demo::copertinaVetrina((int) $v['id']) && $v['status'] === 'published') Guide::publish((int) $v['id']);
            } catch (\Throwable $e) {
                Log::error('049: copertina della vetrina non cambiata: ' . $e->getMessage(), ['property' => (int) $v['id']]);
            }
        }
    }
};
