<?php
/**
 * 056 — 9N · Una sola formula per la traduzione automatica: «traduzioni suggerite dal traduttore automatico, le approvi
 * tu, comprese nel piano entro un limite annuale». Nei punti dei piani (packages.bullets e bullets_en) si sostituisce
 * solo la riga di serie di prima («traduciamo noi … tu controlli e confermi»): una riga scritta a mano in Amministrazione
 * → Piani resta com'è. Una seconda esecuzione non cambia niente.
 */
use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    if (!Migrator::tableExists('packages')) return;
    $righe = [
        'bullets' => ['Scrivi nella tua lingua, traduciamo noi nelle altre: tu controlli e confermi. Comprese nel piano, entro un limite annuale di caratteri.',
                      'Traduzioni suggerite dal traduttore automatico nelle altre lingue: le approvi tu. Comprese nel piano, entro un limite annuale di caratteri.'],
        'bullets_en' => ['Write in your language, we translate into the others: you check and confirm. Included in the plan, within a yearly character limit.',
                         'Translations into the other languages suggested by the automatic translator: you approve them. Included in the plan, within a yearly character limit.'],
    ];
    foreach ($righe as $col => [$prima, $dopo]) {
        if (!Migrator::columnExists('packages', $col)) continue;
        foreach (Db::all("SELECT id, $col AS v FROM packages") as $p) {
            $v = (string) $p['v'];
            if (str_contains($v, $prima)) Db::run("UPDATE packages SET $col = ? WHERE id = ?", [str_replace($prima, $dopo, $v), (int) $p['id']]);
        }
    }
};
