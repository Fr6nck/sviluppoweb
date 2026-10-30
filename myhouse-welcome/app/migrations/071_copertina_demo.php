<?php
/**
 * 071 — 9U · Una sola fonte per la foto della demo: se in Testi e foto c'è già «La schermata nel telefono», diventa adesso
 * anche la copertina della guida vetrina (Casa Checco), che si ripubblica (Demo::copertinaDaSito). Da qui in poi lo fa il
 * salvataggio di Testi e foto. Senza foto caricata o senza vetrina non cambia niente; una seconda esecuzione nemmeno.
 */
use MHW\{Db, Demo, Media, Migrator, TestiSito};

return function (\PDO $pdo): void {
    if (!Migrator::tableExists('site_texts') || !Migrator::columnExists('properties', 'is_demo')) return;
    $rif = TestiSito::riferimentoImmagine('telefono');
    if ($rif === '') return;
    foreach (Demo::vetrineOnline() as $v) {
        $attuale = Media::row((int) Db::val('SELECT cover_media_id FROM properties WHERE id = ?', [(int) $v['id']], 0));
        if (str_starts_with((string) ($attuale['original_name'] ?? ''), Demo::FOTO_SITO)) continue;   // già fatto
        Demo::copertinaDaSito($rif, (int) $v['id']);
    }
};
