<?php
/**
 * 062 — 9R · Correzioni del 22 ottobre.
 *
 * 1. accounts.is_staff, staff_fino, staff_no_vetrina: l'account staff (tester interni, niente pagamenti né Stripe), con la
 *    data di fine e la scelta «non in vetrina».
 * 2. orders.livemode: il pagamento è arrivato con le chiavi live (1) o di test (0) di Stripe, dall'evento del webhook;
 *    NULL = non si sa (ordini di prima). I pagamenti di test non entrano nei conti.
 * 3. pagamenti_esclusi: un pagamento escluso a mano dai conti (ordine «ordine:N» o fattura «fattura:in_…»), con il motivo,
 *    chi e quando. Resta nell'elenco della Cassa, barrato; l'abbonamento del cliente non cambia.
 * 3 bis. vetrina_schede.logo_nascosto: «Logo: non mostrare» deciso in admin (al posto del logo, il quadratino con l'arco).
 * 4. proposte_cambio: il cambio di piano proposto dall'amministrazione con uno sconto, che vale solo con la conferma del
 *    cliente dal suo link (token, solo l'hash), entro la data di validità; rinnovo_del = la fine del periodo sul cui rinnovo
 *    vale lo sconto (scelta «dal rinnovo»).
 *
 * 5. La casella di «Giudizio con sconto» e gli altri testi di serie cambiati dalla 9R (titolo di «Porta un amico», riga del
 *    55%, email 9P), salvati in «Testi e foto» identici a quelli di serie di prima (IT ed EN): si tolgono, così vale il testo nuovo. Un testo cambiato a mano resta.
 *
 * Solo aggiunte, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */
use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    $pk = Db::isMysql() ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $colonne = [
        'accounts' => ['is_staff' => 'INTEGER NOT NULL DEFAULT 0', 'staff_fino' => 'VARCHAR(25) NULL', 'staff_no_vetrina' => 'INTEGER NOT NULL DEFAULT 0'],
        'orders'   => ['livemode' => 'INTEGER NULL'],
        'vetrina_schede' => ['logo_nascosto' => 'INTEGER NOT NULL DEFAULT 0'],
        'proposte_cambio' => ['rinnovo_del' => 'VARCHAR(25) NULL'],   // anche su una tabella creata da una prova precedente della 062
    ];
    foreach ($colonne as $tabella => $nuove) {
        if (!Migrator::tableExists($tabella)) continue;
        foreach ($nuove as $col => $tipo) {
            if (!Migrator::columnExists($tabella, $col)) $pdo->exec("ALTER TABLE $tabella ADD COLUMN $col $tipo");
        }
    }
    if (!Migrator::tableExists('pagamenti_esclusi')) {
        $pdo->exec("CREATE TABLE pagamenti_esclusi (
            id $pk,
            rif VARCHAR(80) NOT NULL,
            account_id INTEGER NOT NULL DEFAULT 0,
            motivo VARCHAR(255) NOT NULL DEFAULT '',
            escluso_da INTEGER NULL,
            created_at VARCHAR(25) NOT NULL
        )");
        $pdo->exec('CREATE UNIQUE INDEX pagamenti_esclusi_rif ON pagamenti_esclusi (rif)');
    }
    if (!Migrator::tableExists('proposte_cambio')) {
        $pdo->exec("CREATE TABLE proposte_cambio (
            id $pk,
            account_id INTEGER NOT NULL,
            package_version_id INTEGER NOT NULL,
            quantita INTEGER NOT NULL DEFAULT 1,
            quando VARCHAR(10) NOT NULL DEFAULT 'subito',
            sconto_pct INTEGER NOT NULL DEFAULT 0,
            valida_fino VARCHAR(25) NOT NULL,
            messaggio TEXT NULL,
            token_hash VARCHAR(64) NOT NULL,
            stato VARCHAR(12) NOT NULL DEFAULT 'inviata',
            order_id INTEGER NULL,
            creata_da INTEGER NULL,
            created_at VARCHAR(25) NOT NULL,
            accettata_at VARCHAR(25) NULL,
            rinnovo_del VARCHAR(25) NULL
        )");
        $pdo->exec('CREATE UNIQUE INDEX proposte_cambio_token ON proposte_cambio (token_hash)');
        $pdo->exec('CREATE INDEX proposte_cambio_account ON proposte_cambio (account_id)');
    }
    if (Migrator::tableExists('site_texts')) {
        $vecchie = ['giudizio.casella' => '**Aderisco al programma «Giudizio con sconto» e ho il {giudizio} di sconto.** Il programma comprende due cose: (1) la mia scheda tra «Le guide dei nostri host» su welcomemyhouse.it **da quando pubblico la guida**, con nome della struttura, copertina, logo, il mio nome (senza cognome) e la città, anche senza foto; (2) dopo 30 giorni, un voto da 1 a 5 con un commento, che può comparire in «Cosa dicono di noi». MyHouse decide se e quando pubblicare scheda e giudizio. Nessun link alla guida, nessuna mia foto. Lo sconto non dipende dal voto né dalla pubblicazione. Posso ritirarmi quando voglio da Impostazioni → Giudizio con sconto.',
                    'en:giudizio.casella' => '**I join the “Review with a discount” programme and get {giudizio} off.** The programme includes two things: (1) my card among “Our hosts\' guides” on welcomemyhouse.it **from when I publish the guide**, with the property name, cover, logo, my name (without surname) and the town, even without photos; (2) after 30 days, a rating from 1 to 5 with a comment, which may appear in “What they say about us”. MyHouse decides whether and when to publish the card and the review. No link to the guide, no photo of me. The discount does not depend on the rating or on publication. I can leave whenever I like from Settings → Review with a discount.'];
        // 9R · Gli altri testi di serie cambiati per la regola nuova (inviti fino al 50%, giudizio un altro 5%, sconto solo con il giudizio).
        $vecchie += [
            'amico.titolo' => "Più amici porti, meno paghi.\nIl prossimo anno fino al {totale} in meno.",
            'en:amico.titolo' => "The more friends you bring, the less you pay.\nUp to {totale} off next year.",
            'risparmio.testo' => '**Fino al {totale} di sconto a partire dal prossimo anno.** Porta un amico: {passo} a testa sul rinnovo, fino al {max} con {amici} amici. Aderisci al programma “Giudizio con sconto”, con la tua guida nella nostra home: un altro {giudizio}.',
            'en:risparmio.testo' => '**Up to {totale} off from next year.** Bring a friend: {passo} each off your renewal, up to {max} with {amici} friends. Join the “Review with a discount” programme, with your guide on our home page: another {giudizio}.',
            'email.sconti.giudizio' => 'Giudizio con sconto: un altro {giudizio}. Aderisci con una casella: la tua scheda entra tra «Le guide dei nostri host» e, dopo 30 giorni, ci lasci un voto con un commento. Il {giudizio} lo hai qualunque voto tu dia.',
            'en:email.sconti.giudizio' => 'Review with a discount: another {giudizio}. Join with one tick: your card appears among “Our hosts\' guides” and after 30 days you leave us a rating with a comment. You get the {giudizio} whatever rating you give.',
            'email.sconti.intro_aderito' => 'con il programma «Giudizio con sconto» hai già il {giudizio}. Ogni amico che porti aggiunge un altro {passo} sul prossimo rinnovo, fino al {max}: in tutto fino al {totale}.',
            'en:email.sconti.intro_aderito' => 'with the “Review with a discount” programme you already have {giudizio}. Every friend you bring adds another {passo} off your next renewal, up to {max}: up to {totale} in all.',
        ];
        foreach ($vecchie as $chiave => $testo) {
            $st = $pdo->prepare('DELETE FROM site_texts WHERE chiave = ? AND valore = ?');
            $st->execute([$chiave, $testo]);
        }
    }
};
