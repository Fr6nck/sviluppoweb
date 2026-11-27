<?php
/**
 * 042 — 8J · Documenti legali conformi al GDPR, titolare City Up S.r.l.
 *
 * users: dpa_version e dpa_accepted_at (l'Accordo sul trattamento dei dati, Allegato A dei Termini).
 * legal_acceptances: una riga per documento accettato (versione, data, come: registrazione o riaccettazione),
 *   con quelle già registrate in users per Termini e Privacy.
 * legal_versions: i testi delle versioni pubblicate, per «Versioni precedenti»; dentro le versioni accettate fino a oggi
 *   (Termini 2026-10-08, Privacy 2026-10), così come si leggevano.
 * orders e subscriptions: acquirente (impresa | consumatore) e recesso_rinuncia (0/1), scelti prima del pagamento.
 * seo_settings: i dati dell'azienda salvati con i vecchi valori di serie (Blackout Agency) passano al titolare.
 *
 * Solo aggiunte, SQLite e MySQL. Una seconda esecuzione non cambia niente.
 */

use MHW\{Db, Migrator};

return function (\PDO $pdo): void {
    $pk = Db::isMysql() ? 'INT NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $testo = Db::isMysql() ? 'MEDIUMTEXT' : 'TEXT';

    foreach (['users' => ['dpa_version' => 'VARCHAR(20) NULL', 'dpa_accepted_at' => 'VARCHAR(25) NULL'],
              'orders' => ['acquirente' => "VARCHAR(12) NOT NULL DEFAULT ''", 'recesso_rinuncia' => 'INTEGER NOT NULL DEFAULT 0'],
              'subscriptions' => ['acquirente' => "VARCHAR(12) NOT NULL DEFAULT ''", 'recesso_rinuncia' => 'INTEGER NOT NULL DEFAULT 0']] as $tabella => $colonne) {
        foreach ($colonne as $nome => $tipo) if (!Migrator::columnExists($tabella, $nome)) $pdo->exec("ALTER TABLE $tabella ADD COLUMN $nome $tipo");
    }

    if (!Migrator::tableExists('legal_acceptances')) {
        $pdo->exec("CREATE TABLE legal_acceptances (
            id $pk,
            user_id INTEGER NOT NULL,
            doc VARCHAR(12) NOT NULL,
            version VARCHAR(20) NOT NULL DEFAULT '',
            modo VARCHAR(20) NOT NULL DEFAULT '',
            created_at VARCHAR(25) NOT NULL
        )");
        $pdo->exec('CREATE INDEX idx_legal_acceptances_utente ON legal_acceptances(user_id)');
        // Le accettazioni di prima, da users (Termini e Privacy).
        foreach (Db::all("SELECT id, terms_version, terms_accepted_at, privacy_version, privacy_accepted_at FROM users WHERE terms_version IS NOT NULL AND terms_version <> ''") as $u) {
            Db::insert('legal_acceptances', ['user_id' => $u['id'], 'doc' => 'termini', 'version' => (string) $u['terms_version'], 'modo' => 'registrazione',
                                             'created_at' => (string) ($u['terms_accepted_at'] ?: gmdate('Y-m-d\TH:i:s\Z'))]);
            if ((string) $u['privacy_version'] !== '') Db::insert('legal_acceptances', ['user_id' => $u['id'], 'doc' => 'privacy', 'version' => (string) $u['privacy_version'],
                                             'modo' => 'registrazione', 'created_at' => (string) ($u['privacy_accepted_at'] ?: gmdate('Y-m-d\TH:i:s\Z'))]);
        }
    }

    if (!Migrator::tableExists('legal_versions')) {
        $pdo->exec("CREATE TABLE legal_versions (
            id $pk,
            doc VARCHAR(12) NOT NULL,
            locale VARCHAR(5) NOT NULL DEFAULT 'it',
            version VARCHAR(20) NOT NULL,
            html $testo NOT NULL,
            hash VARCHAR(64) NOT NULL DEFAULT '',
            created_at VARCHAR(25) NOT NULL,
            updated_at VARCHAR(25) NOT NULL
        )");
        $pdo->exec('CREATE UNIQUE INDEX idx_legal_versions_unico ON legal_versions(doc, locale, version)');
    }
    $vecchie = [['termini', '2026-10-08', <<<'TESTO'
<h1 style="margin-top:24px">Termini e condizioni</h1>
<p class="small muted" style="margin-top:8px">Versione 2026-10-08 · Fornitore: Blackout Agency · P.IVA 02945910541 · Via Ariodante Fabretti 17, Perugia · assistenza@welcomemyhouse.it · Tel. +39 392 006 1600</p>
<div class="stack" style="margin-top:24px;line-height:1.6">
<h2 style="font-size:22px">1. Il servizio</h2>
<p>MyHouse Welcome permette a chi gestisce una struttura ricettiva di creare una guida digitale per i propri ospiti,
raggiungibile da un link e da un codice QR. Gli ospiti non si registrano e non installano applicazioni.</p>
<p id="unita"><b>Una guida, un'unità ricettiva.</b> Ogni guida corrisponde a una sola unità ricettiva: un indirizzo e un CIN
(Codice Identificativo Nazionale), che è obbligatorio per pubblicare e non può essere usato da un'altra guida. Una guida non può
raccogliere più case, appartamenti o strutture con indirizzi o CIN diversi: per ciascuna serve una guida, per esempio con il piano
Portfolio. Fanno eccezione B&amp;B, affittacamere e agriturismi con più camere allo stesso indirizzo e con lo stesso CIN: sono una
struttura sola: se le camere hanno reti Wi-Fi diverse, si scrivono nella sezione Wi-Fi, una riga per zona. In ogni guida ogni sezione
compare una volta sola, e le sezioni libere sono al massimo tre. Se una guida è usata per più unità ricettive possiamo chiederti
di separarla e, se non lo fai, sospenderne la pubblicazione dopo un avviso.</p>
<h2 style="font-size:22px">2. Account</h2>
<p>Per usare il servizio crei un account con un indirizzo email che confermi. Sei responsabile della riservatezza
della password e di ciò che accade con il tuo account.</p>
<h2 style="font-size:22px">3. Prova gratuita della configurazione e pubblicazione</h2>
<p>Puoi creare e configurare la guida gratuitamente. La guida diventa visibile agli ospiti solo dopo l'attivazione
di un abbonamento a pagamento.</p>
<h2 style="font-size:22px">4. Abbonamento, prezzi e rinnovo</h2>
<p>I piani sono annuali e si rinnovano automaticamente alla scadenza. I prezzi sono indicati IVA esclusa; l'imposta
è calcolata al pagamento. Puoi disattivare il rinnovo in qualsiasi momento da Account &amp; Fatturazione: il servizio
resta attivo fino alla fine del periodo già pagato. Alla scadenza senza rinnovo la guida smette di essere visibile
agli ospiti; i tuoi contenuti restano conservati nell'account.</p>
<p>Puoi cambiare piano da Account &amp; Fatturazione. Se passi a un piano superiore o aggiungi strutture paghi subito
solo la differenza per i giorni che restano fino al rinnovo, e la data di rinnovo non cambia. Se passi a un piano
inferiore il cambio vale dal rinnovo successivo: fino ad allora resti sul piano già pagato, senza rimborsi.</p>
<p>I codici sconto valgono solo sul primo anno del primo abbonamento: dal rinnovo si paga il prezzo pieno del piano.
Non si sommano tra loro né con lo sconto di un invito: a ogni abbonamento si applica un solo codice.</p>
<p>Con «Invita un amico» chi si registra dal link di un cliente ha uno sconto del 5% sul primo anno;
le condizioni sono al punto 10.</p>
<h2 style="font-size:22px">5. Modifiche ai piani</h2>
<p>Le condizioni del piano che hai acquistato restano quelle in vigore al momento dell'acquisto per tutto il periodo
pagato. Eventuali modifiche al listino si applicano ai nuovi acquisti.</p>
<h2 style="font-size:22px">6. I tuoi contenuti</h2>
<p>I testi, le immagini e i documenti che pubblichi restano tuoi. Garantisci di avere il diritto di usarli e che non
violano diritti di terzi. Non pubblicare nella guida codici di accesso, password di allarmi o altri dati la cui
diffusione potrebbe mettere a rischio la sicurezza della struttura. Se decidi comunque di pubblicarli, la piattaforma ti chiede una conferma
e lo fai sotto la tua responsabilità.</p>
<h2 style="font-size:22px">7. Disponibilità</h2>
<p>Ci impegniamo a mantenere il servizio disponibile, senza poter garantire l'assenza di interruzioni.</p>
<h2 style="font-size:22px">8. Recesso e chiusura dell'account</h2>
<p>[Da completare con un legale: diritto di recesso, rimborsi, chiusura dell'account e cancellazione dei dati.]</p>
<h2 style="font-size:22px">9. Legge applicabile e foro competente</h2>
<p>[Da completare con un legale.]</p>
<h2 style="font-size:22px" id="inviti">10. Invita un amico</h2>
<p>Chi ha un abbonamento attivo può invitare altre persone con il proprio link personale. Chi si registra da quel link e
attiva il suo primo abbonamento ha uno sconto del 5% sul primo anno. Per ogni persona invitata che paga il
primo abbonamento, chi ha invitato ha uno sconto del 5% sul prezzo del suo prossimo rinnovo annuale, fino a un massimo
del 50% (10 inviti). Gli inviti oltre il massimo non danno altro sconto a chi invita; la persona invitata ha comunque il suo.</p>
<p>Lo sconto di chi invita si applica una sola volta, al primo rinnovo automatico successivo; dopo quel rinnovo il conto degli
inviti riparte da zero. Non è convertibile in denaro e non si applica senza un rinnovo. Lo sconto della persona invitata non
si somma ad altri codici sconto. Non valgono gli inviti a se stessi, ad account con gli stessi dati di fatturazione o a chi
ha già avuto un abbonamento. Un invito non conta più se il primo pagamento della persona invitata viene rimborsato.
Possiamo modificare o chiudere il programma: gli sconti già maturati restano validi per il rinnovo successivo.</p>
<p>[Da far rivedere a un legale e al commercialista prima di accendere gli inviti.]</p>
<h2 style="font-size:22px" id="traduzioni">11. Traduzioni suggerite</h2>
<p>Con i piani che le comprendono puoi chiedere traduzioni suggerite da un traduttore automatico (Amazon Translate).
Sono proposte da controllare: entrano nella guida solo quando le approvi, e da quel momento sono contenuti tuoi come gli altri.
Non garantiamo che siano corrette o adatte al tuo testo: per i testi importanti, falle rileggere a un madrelingua.</p>
<p>Le traduzioni suggerite sono in omaggio per dodici mesi dalla prima attivazione nel tuo account e possono avere un limite
mensile di caratteri. Finito l'omaggio non se ne possono chiedere di nuove; quelle che hai approvato restano nella guida.</p>
</div>
TESTO], ['privacy', '2026-10', <<<'TESTO'
<h1 style="margin-top:24px">Informativa sulla privacy</h1>
<p class="small muted" style="margin-top:8px">Versione 2026-10 · Titolare: Blackout Agency · P.IVA 02945910541 · Via Ariodante Fabretti 17, Perugia · assistenza@welcomemyhouse.it · Tel. +39 392 006 1600</p>
<div class="stack" style="margin-top:24px;line-height:1.6">
<h2 style="font-size:22px">Chi tratta i dati</h2>
<p>Il titolare del trattamento è Blackout Agency · P.IVA 02945910541 · Via Ariodante Fabretti 17, Perugia. Contatti: assistenza@welcomemyhouse.it · Tel. +39 392 006 1600.</p>
<h2 style="font-size:22px">Dati degli host</h2>
<p>Nome, email, password (conservata solo in forma cifrata con hash), dati di fatturazione raccolti da Stripe al
momento del pagamento, contenuti della guida, data e versione dei documenti accettati. Servono a fornire il
servizio, gestire l'abbonamento e adempiere agli obblighi fiscali.</p>
<h2 style="font-size:22px">Dati degli ospiti</h2>
<p>La guida non chiede agli ospiti di registrarsi e non imposta cookie. Registriamo solo eventi anonimi — apertura
della guida, apertura dal QR, sezione consultata, lingua — senza indirizzo IP e senza identificare il dispositivo,
per offrire all'host statistiche di lettura aggregate.</p>
<h2 style="font-size:22px">Fornitori</h2>
<p>Pagamenti: Stripe. Archiviazione di immagini e documenti: Amazon Web Services (S3). Invio delle email: il
fornitore di posta configurato. Traduzioni suggerite: Amazon Web Services (Amazon Translate), regione
eu-west-1; ricevono solo i testi della guida di cui chiedi la
traduzione, quando la chiedi. Fatturazione elettronica: Adamo (app.adamogestionale.it), che riceve da Stripe i dati di
fatturazione per emettere le fatture e inviarle allo SDI. [Completare con sedi, garanzie per il trasferimento dei dati e nomine a responsabile.]</p>
<h2 style="font-size:22px" id="cookie">Cookie</h2>
<p>Il sito usa solo cookie tecnici, che non richiedono il consenso: quello della sessione (mhw_sess), che tiene aperto l&#039;accesso al pannello e protegge i moduli, e quello che ricorda la tua scelta sui cookie (mhw_consenso, 6 mesi).</p>
<p>Le guide degli ospiti non usano cookie né statistiche di terzi: contano solo eventi anonimi per l&#039;host, come scritto sopra.</p>
<h2 style="font-size:22px">Conservazione</h2>
<p>[Da completare: tempi di conservazione dei dati dell'account, dei dati di fatturazione e delle statistiche.]</p>
<h2 style="font-size:22px">I tuoi diritti</h2>
<p>Puoi chiedere accesso, rettifica, cancellazione, limitazione, portabilità e opporti al trattamento scrivendo a
assistenza@welcomemyhouse.it. Puoi proporre reclamo al Garante per la protezione dei dati personali.</p>
</div>
TESTO]];
    foreach ($vecchie as [$doc, $versione, $html]) {
        if (Db::one('SELECT id FROM legal_versions WHERE doc = ? AND locale = ? AND version = ?', [$doc, 'it', $versione])) continue;
        Db::insert('legal_versions', ['doc' => $doc, 'locale' => 'it', 'version' => $versione, 'html' => $html, 'hash' => hash('sha256', $html),
                                      'created_at' => '2026-10-08T00:00:00Z', 'updated_at' => '2026-10-08T00:00:00Z']);
    }

    // I dati dell'azienda in SEO e GEO, solo se erano rimasti quelli di serie di prima.
    if (Migrator::tableExists('seo_settings')) {
        foreach (['az.venditore' => ['Blackout Agency', 'City Up S.r.l.'], 'az.piva' => ['02945910541', '03793880547'],
                  'az.indirizzo' => ['Via Ariodante Fabretti 17, Perugia', 'Via Ariodante Fabretti 15, 06123 Perugia (PG)']] as $k => [$prima, $dopo]) {
            Db::run('UPDATE seo_settings SET valore = ? WHERE chiave = ? AND valore = ?', [$dopo, $k, $prima]);
        }
    }
};
