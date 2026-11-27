<?php
/**
 * 035 — Home 8A: il percorso «Anche il tuo sito» e la voce su foto e PDF dei piani.
 *
 * interessi_sito: chi completa il percorso a domande e sceglie come sentirci (Scopri MyHouse,
 *   WhatsApp, Email). Una riga per clic: risposte, canale e da dove (home, pannello, impostazioni);
 *   l'account solo se chi clicca è dentro. Niente indirizzo IP né altri dati personali.
 *   Non è «feedback», che ha una riga sola per account.
 * packages.bullets: «Foto e PDF dentro le sezioni: la foto del cancello…» diventa «Possibilità di
 *   inserire foto e PDF accanto ai testi…», solo dove c'è ancora il testo di serie.
 *
 * Solo aggiunte, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */

use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    $pk = Db::isMysql() ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    if (!Migrator::tableExists('interessi_sito')) {
        $pdo->exec("CREATE TABLE interessi_sito (
            id $pk,
            account_id INTEGER NULL,
            ha_sito VARCHAR(3) NOT NULL DEFAULT '',
            bisogni VARCHAR(120) NOT NULL DEFAULT '',
            altro VARCHAR(200) NOT NULL DEFAULT '',
            canale VARCHAR(12) NOT NULL DEFAULT '',
            da VARCHAR(16) NOT NULL DEFAULT '',
            created_at VARCHAR(25) NOT NULL
        )");
        $pdo->exec('CREATE INDEX idx_interessi_sito_data ON interessi_sito(created_at)');
    }

    $vecchio = 'Foto e PDF dentro le sezioni: la foto del cancello, le istruzioni della lavatrice';
    $nuovo = 'Possibilità di inserire foto e PDF accanto ai testi, per far vedere meglio quello che spieghi';
    foreach (Db::all('SELECT id, bullets FROM packages') as $p) {
        $righe = preg_split('/\r\n|\n/', (string) $p['bullets']);
        $cambiate = array_map(fn($r) => trim($r) === $vecchio ? $nuovo : $r, $righe);
        if ($cambiate !== $righe) Db::run('UPDATE packages SET bullets = ? WHERE id = ?', [implode("\n", $cambiate), $p['id']]);
    }
};
