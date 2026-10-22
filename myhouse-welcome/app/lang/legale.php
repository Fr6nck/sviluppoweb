<?php
/**
 * 8J · I testi di serie dei documenti legali: Termini e condizioni, Informativa sulla privacy, Accordo sul
 * trattamento dei dati (DPA, Allegato A dei Termini) e le novità della finestra «Abbiamo aggiornato…».
 * Si cambiano da Amministrazione → Testi e foto → Documenti legali (TestiSito); qui restano quelli di serie.
 *
 * Come si scrive (Legale::blocchi): una riga vuota separa i paragrafi; le righe che cominciano con «- » fanno un
 * elenco; quelle che cominciano con «| » una tabella a due colonne («| Dati | …»). Dentro: **grassetto**, [testo](link),
 * e i segnaposto: {titolare}, {titolare_completo}, {sede}, {email}, {email_fatture} (9H), {telefono}, {sito_titolare},
 * {link_termini}, {link_privacy}, {link_dpa}, {link_modulo}, {amico}, {passo}, {max}, {amici}, {regione_traduzioni},
 * {giorni_registri}, {posta}.
 *
 * Testi di partenza scritti sul servizio come funziona davvero: vanno fatti rivedere da un legale prima di pubblicarli.
 */
return [
'it' => [
// ================================================================ TERMINI E CONDIZIONI
'termini.intro' => <<<'T'
Questi Termini regolano l'uso di MyHouse Welcome. Leggili prima di creare l'account: creando l'account li accetti, insieme all'{link_dpa} (Allegato A). Come trattiamo i tuoi dati è scritto nell'{link_privacy}.
T,
'termini.1.titolo' => 'Chi siamo e definizioni',
'termini.1.testo' => <<<'T'
MyHouse Welcome (il «Servizio») è fornito da {titolare_completo}, con sede in {sede} («noi»). Email {email}.

In questi Termini:
- **Servizio**: la piattaforma MyHouse Welcome, con il pannello, le guide e le funzioni collegate;
- **Host** o **Cliente**: chi crea un account per gestire una o più strutture ricettive («tu»);
- **Ospite**: chi soggiorna nella struttura e legge la guida;
- **Guida**: la guida digitale di un'unità ricettiva, raggiungibile da un link e da un codice QR;
- **Piano**: l'abbonamento scelto (Essential, Plus, Portfolio…), con le sue funzioni e i suoi limiti;
- **Account**: lo spazio personale del Cliente nel Servizio.
T,
'termini.2.titolo' => 'Il servizio e la regola «una guida, un\'unità ricettiva»',
'termini.2.testo' => <<<'T'
MyHouse Welcome permette a chi gestisce una struttura ricettiva di creare una guida digitale per i propri ospiti, raggiungibile da un link e da un codice QR. Gli ospiti non si registrano e non installano applicazioni.

**Una guida, un'unità ricettiva.** Ogni guida corrisponde a una sola unità ricettiva: un indirizzo e un CIN (Codice Identificativo Nazionale), che è obbligatorio per pubblicare e non può essere usato da un'altra guida. Una guida non può raccogliere più case, appartamenti o strutture con indirizzi o CIN diversi: per ciascuna serve una guida, per esempio con il piano Portfolio. Fanno eccezione B&B, affittacamere e agriturismi con più camere allo stesso indirizzo e con lo stesso CIN: sono una struttura sola; se le camere hanno reti Wi-Fi diverse, si scrivono nella sezione Wi-Fi, una riga per zona. In ogni guida ogni sezione compare una volta sola, e la sezione libera è una sola per guida. Se una guida è usata per più unità ricettive possiamo chiederti di separarla e, se non lo fai, sospenderne la pubblicazione dopo un avviso.
T,
'termini.3.titolo' => 'Account',
'termini.3.testo' => <<<'T'
Per usare il Servizio crei un account con un indirizzo email che confermi. Il Servizio è riservato a chi ha **almeno 18 anni**.

Sei responsabile della riservatezza della password e di ciò che accade con il tuo account: se pensi che qualcuno la conosca, cambiala subito e scrivici. Ogni account è di una sola persona o azienda e non si cede né si condivide; per più strutture si usa un solo account con il piano adatto. I dati che inserisci (nome, email, dati di fatturazione) devono essere veri e aggiornati.
T,
'termini.4.titolo' => 'Prova gratuita e pubblicazione',
'termini.4.testo' => <<<'T'
Puoi creare e configurare la guida gratuitamente, senza carta di credito, e vederla in anteprima come la vedranno gli ospiti. La guida diventa visibile agli ospiti solo dopo l'attivazione di un abbonamento a pagamento, alla prima pubblicazione.
T,
'termini.5.titolo' => 'Abbonamento, prezzi e rinnovo',
'termini.5.testo' => <<<'T'
**Rinnovo automatico.** L'abbonamento è annuale e, salvo disattivazione, si rinnova automaticamente alla scadenza per un altro anno, con addebito sul metodo di pagamento registrato. Ti avvisiamo per email e nel pannello 30 e 7 giorni prima. Puoi disattivare il rinnovo in qualsiasi momento da Account & Fatturazione: il servizio resta attivo fino alla fine del periodo pagato e non ti viene addebitato altro. I prezzi sono indicati IVA esclusa; l'imposta è calcolata al pagamento.

Puoi cambiare piano da Account & Fatturazione. Se passi a un piano superiore o aggiungi strutture paghi subito solo la differenza per i giorni che restano fino al rinnovo, e la data di rinnovo non cambia. Se passi a un piano inferiore il cambio vale dal rinnovo successivo: fino ad allora resti sul piano già pagato, senza rimborsi.

**Cambio proposto da noi.** Possiamo proporti un cambio di piano con uno sconto, per email o messaggio, con un link: vale solo con la tua conferma da quel link, entro la data indicata. Senza la tua conferma non cambia niente.

I codici sconto valgono solo sul primo anno del primo abbonamento: dal rinnovo si paga il prezzo pieno del piano. Non si sommano tra loro né con lo sconto di un invito: a ogni abbonamento si applica un solo codice. Per «Invita un amico» valgono le condizioni del punto 12.

**Offerte su misura.** Oltre il numero massimo di strutture del Portfolio ti facciamo un'offerta su misura: un prezzo annuo concordato per tutte le strutture, valido fino alla data indicata nell'offerta, che vale **anche ai rinnovi**. Gli sconti del programma «Invita un amico» e «Giudizio con sconto» non si applicano in automatico alle offerte su misura: un eventuale sconto sul rinnovo lo decidiamo con te e te lo comunichiamo con l'avviso che precede il rinnovo. Per cambiare il numero di strutture ti mandiamo una nuova offerta, che vale subito (pagando la differenza per i giorni che restano) oppure dal rinnovo.
T,
'termini.6.titolo' => 'Pagamento e mancato pagamento',
'termini.6.testo' => <<<'T'
I pagamenti avvengono con Stripe (Stripe Payments Europe Ltd). I dati della carta li riceve e conserva solo Stripe: noi non li vediamo.

Se il pagamento del rinnovo non riesce, Stripe riprova fino a 3 volte in 14 giorni e ti avvisa per email. Se nessun tentativo riesce, l'abbonamento termina e la guida torna non visibile agli ospiti. I tuoi contenuti restano nell'account per 24 mesi: puoi ripubblicare la guida attivando di nuovo un abbonamento.

Per ogni pagamento emettiamo la fattura elettronica, inviata tramite il Sistema di Interscambio (SDI) con il nostro gestionale di fatturazione (Adamo), con i dati di fatturazione che inserisci nel pannello. Per le questioni di fatturazione e pagamento scrivi a {email_fatture}.
T,
'termini.7.titolo' => 'Recesso',
'termini.7.testo' => <<<'T'
Prima del pagamento ti chiediamo se acquisti per la tua attività ricettiva (impresa o professionista) oppure come privato. La scelta resta registrata con l'abbonamento.

**Imprese e professionisti** (chi acquista con partita IVA o per la propria attività ricettiva): non c'è un diritto di recesso previsto dalla legge. Puoi disattivare il rinnovo automatico in ogni momento, e il servizio resta attivo fino alla fine del periodo pagato.

**Consumatori** (privati che acquistano senza partita IVA, per scopi estranei a un'attività imprenditoriale o professionale, art. 3 del Codice del Consumo): hai 14 giorni dal pagamento per recedere senza dover dare spiegazioni, con il rimborso integrale entro 14 giorni dalla tua comunicazione, con lo stesso mezzo di pagamento. Basta una dichiarazione esplicita a {email} oppure il {link_modulo} in fondo a questa pagina.

Se al momento dell'acquisto chiedi espressamente che il servizio inizi subito (la guida viene pubblicata appena il pagamento è confermato), riconosci che, se recedi entro i 14 giorni, paghi la parte di abbonamento corrispondente ai giorni già goduti e ti rimborsiamo il resto, e che il diritto di recesso si perde con la completa esecuzione del servizio (artt. 50, 57 e 59 del Codice del Consumo).
T,
'termini.8.titolo' => 'I tuoi contenuti e i codici di accesso',
'termini.8.testo' => <<<'T'
I testi, le immagini e i documenti che pubblichi restano tuoi. Garantisci di avere il diritto di usarli (anche le foto in cui compaiono persone) e che non violano diritti di terzi.

Non pubblicare contenuti illeciti, offensivi o ingannevoli, né dati personali di altre persone senza averne il diritto. Se riceviamo una segnalazione fondata o vediamo un contenuto di questo tipo, possiamo chiederti di toglierlo e, se non lo fai, rimuoverlo o sospendere la guida dopo un avviso; senza avviso solo nei casi gravi o se ce lo chiede un'autorità.

Non pubblicare nella guida codici di accesso, password di allarmi o altri dati la cui diffusione potrebbe mettere a rischio la sicurezza della struttura. Se decidi comunque di pubblicarli, la piattaforma ti chiede una conferma e lo fai sotto la tua responsabilità.
T,
'termini.9.titolo' => 'Ruoli privacy e accordo sul trattamento dei dati',
'termini.9.testo' => <<<'T'
Per i dati personali che inserisci nella guida o che ricevi tramite il Servizio (i nomi e i contatti tuoi e dei tuoi collaboratori, l'email «per gli ospiti», i messaggi WhatsApp che gli ospiti ti mandano dalla guida, i voti anonimi delle guide) **tu sei il titolare del trattamento** e {titolare} è **responsabile del trattamento** (art. 28 GDPR). Le regole sono nell'{link_dpa}, Allegato A di questi Termini, che accetti insieme ai Termini.

Per i dati del tuo account, dei pagamenti e del sito, invece, il titolare siamo noi: vedi l'{link_privacy}.
T,
'termini.10.titolo' => 'Disponibilità e limitazione di responsabilità',
'termini.10.testo' => <<<'T'
Ci impegniamo a mantenere il Servizio disponibile e i dati al sicuro, senza poter garantire l'assenza di interruzioni. Le manutenzioni, quando possibile, le facciamo in orari di poco traffico. Ti consigliamo di tenere una copia dei testi e delle foto più importanti.

Non rispondiamo dei contenuti che pubblichi, delle interruzioni o dei cambiamenti dei servizi di terzi a cui la guida rimanda (Google, Booking, Airbnb, mappe, meteo…), né dei danni indiretti (per esempio mancati guadagni). Per il resto, il nostro risarcimento massimo è pari a quanto ci hai pagato negli ultimi 12 mesi, salvo dolo o colpa grave.

Niente in questi Termini limita i diritti che la legge riconosce ai consumatori.
T,
'termini.11.titolo' => 'Durata, sospensione e chiusura',
'termini.11.testo' => <<<'T'
Il contratto dura quanto il tuo account; l'abbonamento dura il periodo pagato e si rinnova come scritto al punto 5.

Possiamo sospendere l'account o una guida se violi questi Termini, dopo un avviso con un termine per rimediare; subito, se la violazione è grave (per esempio contenuti illeciti o un uso che mette a rischio il Servizio o altre persone).

Puoi chiedere la chiusura dell'account in ogni momento scrivendo a {email}. Alla chiusura le guide pubblicate vanno subito offline e i dati si cancellano nei tempi scritti nell'{link_privacy}, tranne quelli che la legge ci obbliga a conservare (per esempio le fatture).
T,
'termini.12.titolo' => 'Invita un amico',
'termini.12.testo' => <<<'T'
Chi ha un abbonamento attivo può invitare altre persone con il proprio link personale. Chi si registra da quel link e attiva il suo primo abbonamento ha uno sconto del {amico}% sul primo anno. Per ogni persona invitata che paga il primo abbonamento, chi ha invitato ha uno sconto del {passo}% sul prezzo del suo prossimo rinnovo annuale, fino a un massimo del {max}% ({amici} inviti). Gli inviti oltre il massimo non danno altro sconto a chi invita; la persona invitata ha comunque il suo.

Lo sconto di chi invita si applica una sola volta, al primo rinnovo automatico successivo; dopo quel rinnovo il conto degli inviti riparte da zero. Non è convertibile in denaro e non si applica senza un rinnovo. Lo sconto della persona invitata non si somma ad altri codici sconto. Non valgono gli inviti a se stessi, ad account con gli stessi dati di fatturazione o a chi ha già avuto un abbonamento. Un invito non conta più se il primo pagamento della persona invitata viene rimborsato. Se mandi l'invito per email dal pannello, dichiari di avere il consenso della persona invitata a riceverlo. Possiamo modificare o chiudere il programma: gli sconti già maturati restano validi per il rinnovo successivo.

**Programma partner.** Property manager e agenzie approvati da noi hanno un codice partner (per esempio PM-ROSSI). Se lo usi al pagamento o arrivi dal suo link, hai lo sconto del partner sul primo anno (si somma all'invito di un amico; con un codice sconto vale il più alto; con un codice omaggio non si applica) e il tuo account resta legato a quel partner, una volta sola. Al partner paghiamo una commissione sui tuoi pagamenti, anche sui rinnovi salvo diversa comunicazione, che liquidiamo su sua fattura; non cambia nulla di quello che paghi tu. Al partner non comunichiamo i tuoi dati personali oltre a nome e città della struttura, piano e date: mai email, importi pagati o dati di pagamento. Possiamo chiudere il programma con un preavviso.
T,
'termini.13.titolo' => 'Traduzioni suggerite',
'termini.13.testo' => <<<'T'
Con i piani che le comprendono puoi chiedere traduzioni suggerite da un traduttore automatico (Amazon Translate). Sono proposte da controllare: entrano nella guida solo quando le approvi, e da quel momento sono contenuti tuoi come gli altri. Non garantiamo che siano corrette o adatte al tuo testo: per i testi importanti, falle rileggere a un madrelingua.

Le traduzioni suggerite sono comprese nei piani Plus e Portfolio entro un limite annuale di caratteri indicato nel pannello; oltre, su richiesta. Quelle che hai approvato restano nella guida anche dopo il limite.
T,
'termini.14.titolo' => 'Modifiche ai Termini',
'termini.14.testo' => <<<'T'
Possiamo modificare questi Termini, per esempio per nuove funzioni o per cambiamenti di legge. Ti avvisiamo per email e con un messaggio nel pannello almeno 30 giorni prima della data in cui le modifiche valgono. Se non le accetti puoi recedere senza penali fino a quella data; dopo, continuando a usare il Servizio, le accetti.

Le condizioni economiche del periodo che hai già pagato non cambiano: eventuali modifiche al listino valgono per i nuovi acquisti e dal rinnovo successivo.
T,
'termini.15.titolo' => 'Legge applicabile e foro',
'termini.15.testo' => <<<'T'
Questi Termini sono regolati dalla legge italiana.

Se sei un consumatore, per ogni controversia è competente il giudice del luogo della tua residenza o del tuo domicilio, e restano i tuoi diritti di ricorrere agli organismi di risoluzione alternativa delle controversie (ADR) previsti dal Codice del Consumo. La piattaforma europea per la risoluzione online delle controversie (ODR) è stata chiusa dalla Commissione europea il 20 luglio 2025.

In tutti gli altri casi è competente in via esclusiva il **foro di Perugia**.
T,
'termini.17.titolo' => 'Giudizio con sconto, sconto sul rinnovo e codice omaggio',
'termini.17.testo' => <<<'T'
**Cos'è.** Un programma facoltativo: chi aderisce si impegna a lasciare, dopo la pubblicazione della guida, un voto da 1 a 5 e un commento sul servizio; in cambio riceve uno sconto del {giudizio}% sul prezzo di listino dell'abbonamento. L'adesione si fa con un'unica casella, al pagamento o dal pannello (Impostazioni → Giudizio con sconto).

**Lo sconto.** Il {giudizio}% sul prezzo di listino **si applica una sola volta**: se aderisci al pagamento (o prima del primo pagamento), sul primo anno; se aderisci dopo il pagamento, sul rinnovo successivo, a condizione che a quel rinnovo tu abbia già lasciato voto e commento (altrimenti sul primo rinnovo dopo il giudizio). Nei rinnovi seguenti non si ripete. Si somma allo sconto del programma «Porta un amico», fino a un massimo complessivo del {totale}% su quel rinnovo; non si somma ai codici sconto (vale il più alto) e non è disponibile durante un periodo omaggio. Lo sconto non dipende dal voto: vale anche con una stella.

**Cosa possiamo pubblicare.** Aderendo, l'host autorizza MyHouse Welcome a mostrare nella home di welcomemyhouse.it, a propria discrezione e senza obbligo:

- la **scheda** della struttura (nome della struttura, foto di copertina, logo, nome dell'host senza cognome, città), anche prima del giudizio e anche in assenza di giudizio; con il piano Portfolio possono comparire tutte le strutture dell'account;
- il **giudizio** (voto, commento, nome e struttura).

**Il logo della struttura, se caricato, compare nella scheda così com'è: se scegli di usare come logo una tua fotografia, acconsenti a che venga mostrata nella home del sito. Puoi cambiarlo o toglierlo in ogni momento dal pannello.**

Per la riservatezza dell'host e dei suoi ospiti, le schede e i giudizi non contengono link alla guida, non sono cercabili dai motori di ricerca, non mostrano la foto dell'host né alcun dato degli ospiti. Non pubblichiamo nulla di chi non aderisce.

**Ritiro.** L'host può ritirarsi in ogni momento dal pannello: scheda e giudizio spariscono dalla home entro 24 ore. Lo sconto già applicato resta; quello promesso sul rinnovo decade se il ritiro avviene prima del rinnovo. Scheda e giudizio spariscono anche se la guida viene archiviata, l'abbonamento scade o l'account viene chiuso. MyHouse Welcome può togliere o non pubblicare una scheda o un giudizio senza che ciò tocchi lo sconto, e può chiudere il programma ai nuovi aderenti con un preavviso di 30 giorni, mantenendo gli sconti già promessi.

**Promemoria.** Chi ha aderito e non ha ancora lasciato il giudizio riceve un promemoria dopo 30 giorni dalla pubblicazione e, se ha aderito dopo il pagamento, un secondo 30 giorni prima del rinnovo.

**Sconto sul rinnovo, una sola percentuale.** Lo sconto del giudizio e quelli degli inviti (punto 12) si sommano e si applicano **una sola volta sul prezzo di listino** del rinnovo: per esempio 10 inviti (50%) e il giudizio (5%) fanno il 55%. Non si sommano ai codici sconto, che valgono solo sul primo anno: tra codice e sconti di sistema vale il più alto.

**Codice omaggio.** Con un codice omaggio i primi mesi indicati sono gratuiti. Registri comunque un metodo di pagamento e, al termine dei mesi gratuiti, l'abbonamento parte automaticamente al prezzo pieno del piano scelto, con gli stessi avvisi per email e nel pannello 30 e 7 giorni prima dell'addebito. Disattivando il rinnovo prima della fine del periodo gratuito non ti viene addebitato nulla: la guida resta online fino a quella data, poi non è più visibile agli ospiti. Il codice vale per una sola persona, solo per il primo abbonamento, entro la data indicata. Durante i mesi gratuiti non vale lo sconto di un invito ricevuto: se l'account è arrivato da un invito e usa un codice omaggio, vale l'omaggio e l'invito non viene conteggiato. Anche nei mesi gratuiti puoi aderire al programma e la tua scheda può essere pubblicata. **Dopo il primo pagamento hai tutte le agevolazioni**: inviti ({passo}% per ogni amico, fino al {max}%) e giudizio ({giudizio}%), fino al {totale}%, a partire dal primo rinnovo. Possiamo annullare un codice non ancora usato in qualsiasi momento.
T,
'termini.16.titolo' => 'Contatti e titolare',
'termini.16.testo' => <<<'T'
{titolare_completo}, {sede}. Email {email} · telefono {telefono} · {sito_titolare}.
T,
'termini.b.titolo' => 'Allegato B · Modulo di recesso tipo',
'termini.b.testo' => <<<'T'
Allegato I, parte B, del Codice del Consumo. Compila e manda questo modulo solo se sei un consumatore e vuoi recedere dal contratto.

- Destinatario: {titolare_completo}, {sede} · email {email}
- Con la presente io/noi (*) notifico/notifichiamo (*) il recesso dal mio/nostro (*) contratto per la prestazione del seguente servizio: abbonamento MyHouse Welcome, piano …………
- Ordinato il (*) / pagato il (*): …………
- Nome del/dei consumatore/i: …………
- Indirizzo del/dei consumatore/i: …………
- Email dell'account: …………
- Firma del/dei consumatore/i (solo se il modulo è inviato su carta): …………
- Data: …………

(*) Cancella la dicitura inutile.
T,

// ================================================================ INFORMATIVA SULLA PRIVACY
'privacy.intro' => <<<'T'
Questa informativa spiega come {titolare} tratta i dati personali di chi usa MyHouse Welcome (host e visitatori del sito) e degli ospiti che leggono le guide, ai sensi degli articoli 13 e 14 del Regolamento (UE) 2016/679 (GDPR). Per ogni trattamento trovi quali dati, perché, su quale base giuridica, per quanto tempo e chi li riceve.

Per i dati che gli host inseriscono nelle guide il titolare è l'host e noi siamo responsabili del trattamento: vedi l'{link_dpa}.
T,
'privacy.t1.titolo' => 'Account e contratto',
'privacy.t1.testo' => <<<'T'
| Dati | Nome, email, password (conservata solo in forma cifrata, con hash), data di registrazione e di conferma dell'email, data e versione dei documenti accettati, le note interne dell'assistenza sul tuo account.
| Finalità | Creare e gestire il tuo account e fornirti il Servizio.
| Base giuridica | Esecuzione del contratto (art. 6.1.b GDPR); per le accettazioni, il nostro legittimo interesse a dimostrarle (art. 6.1.f).
| Conservazione | Finché l'account è attivo; poi fino a 24 mesi dall'ultimo accesso o dalla scadenza dell'ultimo abbonamento, quindi li cancelliamo. Prima della cancellazione ti avvisiamo per email con 30 giorni di anticipo.
| Destinatari | Il fornitore dell'hosting e quello della posta (vedi «Destinatari e fornitori»).
T,
'privacy.t2.titolo' => 'Pagamenti e fatturazione',
'privacy.t2.testo' => <<<'T'
| Dati | Dati di fatturazione (nome o ragione sociale, indirizzo, partita IVA o codice fiscale, codice SDI o PEC), piano, importi, date e stato dei pagamenti, la scelta «impresa» o «privato» fatta prima del pagamento, un eventuale codice omaggio. I dati della carta li tratta solo Stripe: noi riceviamo da Stripe solo le ultime 4 cifre, per scrivertele negli avvisi prima di un addebito.
| Finalità | Incassare l'abbonamento, emettere le fatture, adempiere agli obblighi fiscali e contabili.
| Base giuridica | Esecuzione del contratto (art. 6.1.b) e obbligo di legge (art. 6.1.c).
| Conservazione | 10 anni dall'ultima registrazione (art. 2220 del codice civile).
| Destinatari | Stripe (pagamenti), Adamo (fatturazione elettronica), Agenzia delle Entrate tramite il Sistema di Interscambio.
T,
'privacy.t3.titolo' => 'Contenuti della guida',
'privacy.t3.testo' => <<<'T'
| Dati | Testi, foto, PDF, foto di copertina e dell'host caricate dall'host; nomi, telefoni, WhatsApp e email «per gli ospiti» dell'host e dei suoi collaboratori; link ai portali e al sito della struttura.
| Finalità | Pubblicare la guida per gli ospiti della struttura.
| Base giuridica | Esecuzione del contratto (art. 6.1.b). Per questi dati il titolare è l'host, che garantisce di avere il diritto di pubblicarli (foto di persone comprese); noi li trattiamo come responsabili secondo l'{link_dpa}.
| Conservazione | Come l'account.
| Destinatari | Chiunque abbia il link o il QR della guida (la guida non compare nei motori di ricerca); Amazon Web Services per i file; Amazon Translate solo per i testi di cui l'host chiede la traduzione.
T,
'privacy.t4.titolo' => 'Email di servizio e promemoria',
'privacy.t4.testo' => <<<'T'
| Dati | Email, nome, il tipo e la data delle email mandate.
| Finalità | Le email necessarie al Servizio (conferma dell'email, recupero della password, avviso prima del rinnovo, avvisi sui documenti legali e sulla sicurezza) e i promemoria per completare o aggiornare la guida.
| Base giuridica | Esecuzione del contratto (art. 6.1.b); per i promemoria, il nostro legittimo interesse ad aiutarti a usare il Servizio (art. 6.1.f).
| Opposizione | Ogni promemoria ha il link «Non mandarmene più», che vale per quel tipo di promemoria. Gli avvisi di rinnovo, di sicurezza e sui documenti legali non si possono disattivare.
| Conservazione | Il registro degli invii come l'account. Non facciamo marketing a chi non è cliente.
| Destinatari | Il fornitore della posta.
T,
'privacy.t5.titolo' => 'Invita un amico',
'privacy.t5.testo' => <<<'T'
| Dati | Il link e il codice personali, chi si è registrato da un invito e se ha pagato; l'email della persona invitata, se l'host la scrive nel pannello: la usiamo solo per mandare quell'invito e la conserviamo solo in forma cifrata (hash) e abbreviata, per non mandarne due alla stessa persona.
| Finalità | Gestire gli inviti e gli sconti del programma.
| Base giuridica | Esecuzione del contratto (art. 6.1.b) e legittimo interesse (art. 6.1.f). L'host dichiara di avere il consenso della persona invitata a ricevere l'email.
| Conservazione | 24 mesi.
| Destinatari | Il fornitore della posta.
T,
'privacy.t6.titolo' => 'Giudizio con sconto e vetrina, pareri dei clienti',
'privacy.t6.testo' => <<<'T'
| Dati | Per il programma «Giudizio con sconto e vetrina»: nome della struttura, copertina e logo (se ci sono e scegli di mostrarli), il tuo nome senza cognome, la città, il voto e il commento, la data di pubblicazione della guida. **Nessuna foto della persona.** La risposta alla domanda «Cosa ti ha fermato?», se rispondi.
| Finalità | Solo se aderisci al programma (senza adesione non pubblichiamo nulla di te): mostrare, se decidiamo di pubblicarle, la tua scheda tra «Le guide dei nostri host» (da quando pubblichi la guida) e il tuo giudizio in «Cosa dicono di noi» (con lo sconto, qualunque sia il voto e anche se non li pubblichiamo); capire cosa migliorare.
| Base giuridica | Per la scheda e il giudizio, il contratto (art. 6.1.b): l'adesione al programma, con l'unica casella che accetti al pagamento o dal pannello (Termini, «Giudizio con sconto»). Puoi ritirarti quando vuoi da Impostazioni → Giudizio con sconto: scheda e giudizio spariscono subito dal sito. Per i pareri, il nostro legittimo interesse (art. 6.1.f).
| Conservazione | La scheda e il giudizio fino al ritiro o alla chiusura dell'account; i pareri come l'account.
| Destinatari | I visitatori del sito, solo per le schede e i giudizi pubblicati di chi ha aderito (home e pagina «Tutte le guide»). Nessun link alla tua guida, nessun dato dei tuoi ospiti.
T,
'privacy.t7.titolo' => 'Statistiche di lettura delle guide',
'privacy.t7.testo' => <<<'T'
| Dati | Eventi anonimi: apertura della guida, apertura dal QR, sezione consultata, lingua, voto dell'ospite. Senza indirizzo IP, senza cookie e senza identificare il dispositivo: non sono dati personali.
| Finalità | Offrire all'host statistiche di lettura aggregate.
| Base giuridica | Non serve: non sono dati personali.
| Conservazione | Senza limite, in forma aggregata.
| Destinatari | L'host della guida.
T,
'privacy.t8.titolo' => 'Percorso «Anche il tuo sito»',
'privacy.t8.testo' => <<<'T'
| Dati | Le risposte alle domande (hai già un sito, cosa vorresti migliorare), il canale scelto (sito, WhatsApp, email), la pagina da cui rispondi e, se hai fatto l'accesso, il tuo account. Se poi ci scrivi, il tuo messaggio e i tuoi contatti.
| Finalità | Rispondere alla tua richiesta di un sito per la tua struttura.
| Base giuridica | Misure precontrattuali su tua richiesta (art. 6.1.b) e legittimo interesse (art. 6.1.f).
| Conservazione | 12 mesi.
| Destinatari | **Blackout Agency** (blackout.in, Perugia), società distinta da noi che realizza i siti MyHouse: le risposte le condividiamo con lei, e ti contatta lei. Se scegli «Scopri MyHouse», le risposte arrivano a blackout.in con l'apertura della pagina. Blackout Agency le tratta come titolare autonomo, con la sua informativa.
T,
'privacy.t9.titolo' => 'Statistiche del sito',
'privacy.t9.testo' => <<<'T'
| Dati | Pagine visitate, provenienza, tipo di dispositivo e di browser, eventi come la registrazione o il pagamento. Con Matomo l'indirizzo IP viene anonimizzato (gli ultimi due byte) e non si usano cookie; con Google Analytics e Microsoft Clarity, solo se acconsenti, gli identificativi dei loro cookie.
| Finalità | Capire come si usa il sito e migliorarlo.
| Base giuridica | Matomo: nostro legittimo interesse (art. 6.1.f), senza cookie. Google Analytics e Clarity: il tuo consenso (art. 6.1.a e art. 122 del Codice privacy), che puoi revocare quando vuoi.
| Conservazione | Matomo: dati grezzi 6 mesi, poi solo in forma aggregata. Google e Microsoft: secondo le loro informative (vedi la sezione «Cookie»).
| Destinatari | Matomo è sul nostro server; Google e Microsoft solo con il consenso. Le guide degli ospiti, il pannello e l'amministrazione non hanno statistiche di terzi.
T,
'privacy.t10.titolo' => 'Registri tecnici e di sicurezza',
'privacy.t10.testo' => <<<'T'
| Dati | Errori del sito, tentativi di accesso, accessi e azioni degli amministratori, email di prova. Gli indirizzi IP, le email e i numeri di telefono finiscono nei registri abbreviati; per i limiti ai tentativi di accesso l'IP diventa un codice da cui non si risale all'indirizzo.
| Finalità | Sicurezza del Servizio, prevenzione degli abusi, correzione degli errori, controllo degli accessi degli amministratori di sistema (provvedimento del Garante del 27 novembre 2008).
| Base giuridica | Legittimo interesse (art. 6.1.f) e obbligo di legge per gli accessi degli amministratori (art. 6.1.c).
| Conservazione | Registri tecnici {giorni_registri} giorni; codici dei tentativi di accesso 24 ore; email di prova 7 giorni. Il registro degli accessi degli amministratori si copia ogni notte su Amazon Web Services (S3, Milano), dove resta **inalterabile per 6 mesi** e si cancella dopo 12 mesi.
| Destinatari | Il fornitore dell'hosting; Amazon Web Services per la copia degli accessi degli amministratori.
T,
'privacy.t11.titolo' => 'Assistenza',
'privacy.t11.testo' => <<<'T'
| Dati | Quello che ci scrivi per email ({email}), per WhatsApp o per telefono, e i tuoi contatti.
| Finalità | Rispondere alle tue richieste e aiutarti a usare il Servizio.
| Base giuridica | Esecuzione del contratto (art. 6.1.b) o legittimo interesse a risponderti (art. 6.1.f).
| Conservazione | 24 mesi dalla chiusura della richiesta.
| Destinatari | Il fornitore della posta; WhatsApp (Meta), se ci scrivi su WhatsApp, secondo la sua informativa.
T,
'privacy.t12.titolo' => 'Accesso dell\'assistenza al tuo account',
'privacy.t12.testo' => <<<'T'
| Dati | Quello che si vede nel tuo pannello, quando un amministratore ci entra per aiutarti.
| Finalità | Assistenza tecnica, su tua richiesta o per risolvere un problema.
| Base giuridica | Legittimo interesse (art. 6.1.f).
| Garanzie | Ogni accesso è registrato (data, ora, chi) e lo vedi in Account & Fatturazione. L'accesso dura al massimo un'ora.
| Conservazione | La traccia degli accessi come l'account; l'indirizzo IP abbreviato, cancellato dopo 12 mesi.
T,
'privacy.t13.titolo' => 'Richieste di preventivo',
'privacy.t13.testo' => <<<'T'
| Dati | Nome, email, telefono (se lo scrivi), numero di strutture e quello che ci scrivi nel modulo «Chiedi il preventivo» (per più di 10 strutture).
| Finalità | Prepararti una proposta su misura e ricontattarti.
| Base giuridica | Misure precontrattuali su tua richiesta (art. 6.1.b).
| Conservazione | 12 mesi dalla richiesta, poi la cancelliamo.
| Destinatari | Il fornitore dell'hosting e quello della posta.
T,
'privacy.t14.titolo' => 'Richieste di partnership',
'privacy.t14.testo' => <<<'T'
| Dati | Nome, società, email, telefono (se lo scrivi), quante strutture gestisci, le città o le zone e quello che ci scrivi nel modulo «Diventa partner» della pagina per i property manager.
| Finalità | Rispondere alla richiesta, conoscerci e valutare insieme il programma Partner.
| Base giuridica | Misure precontrattuali su tua richiesta (art. 6.1.b).
| Conservazione | 12 mesi dalla richiesta, poi la cancelliamo.
| Destinatari | Il fornitore dell'hosting e quello della posta.
T,
'privacy.t15.titolo' => 'Programma partner',
'privacy.t15.testo' => <<<'T'
| Dati | Per il partner: ragione sociale, referente, email, telefono, zona, P.IVA, IBAN, le commissioni e le liquidazioni. Per il cliente arrivato da un partner: il collegamento al partner (quale codice e quando), i suoi pagamenti per calcolare la commissione.
| Finalità | Gestire il programma: lo sconto del cliente, le commissioni e i bonifici al partner, la pagina del partner.
| Base giuridica | Esecuzione del contratto con il partner e con il cliente (art. 6.1.b); obblighi fiscali per le liquidazioni (art. 6.1.c).
| Conservazione | 10 anni per le liquidazioni e le commissioni (obblighi fiscali); il collegamento cliente–partner finché dura l'account.
| Destinatari | Il partner vede solo nome e città della struttura, piano, date e la sua commissione: mai email, importi pagati o dati di pagamento del cliente. Il fornitore dell'hosting e quello della posta.
T,
'privacy.fornitori.titolo' => 'Destinatari e fornitori',
'privacy.fornitori.testo' => <<<'T'
Ci aiutano a fornire il Servizio questi fornitori, nominati responsabili del trattamento con un contratto (art. 28 GDPR):
- **Hostinger** (Hostinger International Ltd, Cipro): hosting del sito e del database, su server nell'Unione europea;
- **Amazon Web Services EMEA SARL** (Lussemburgo): archiviazione di foto, PDF e della copia dei registri su Amazon S3 a Milano (eu-south-1); traduzioni suggerite con Amazon Translate in Irlanda ({regione_traduzioni});
- **Stripe Payments Europe Ltd** (Irlanda): pagamenti;
- **Adamo** (Italia): fatturazione elettronica;
- **posta in uscita**: {posta}.

Sono invece titolari autonomi, ciascuno con la sua informativa: **Google** e **Microsoft**, solo se acconsenti ai loro cookie; **Blackout Agency** (blackout.in), solo per il percorso «Anche il tuo sito». Possono ricevere i dati anche le autorità, quando la legge lo richiede.
T,
'privacy.trasferimenti.titolo' => 'Trasferimenti fuori dall\'Unione europea',
'privacy.trasferimenti.testo' => <<<'T'
I dati restano nell'Unione europea, tranne in questi casi: Stripe può trasferirli a Stripe Inc. (Stati Uniti), Google e Microsoft (solo con il tuo consenso ai cookie) alle loro società negli Stati Uniti. Questi trasferimenti avvengono con le Clausole Contrattuali Standard della Commissione europea e, per le società aderenti, con il Data Privacy Framework UE-USA.
T,
'privacy.minori.titolo' => 'Minori',
'privacy.minori.testo' => <<<'T'
Il Servizio è riservato ai maggiorenni. Le guide non chiedono dati agli ospiti, maggiorenni o minorenni.
T,
'privacy.diritti.titolo' => 'I tuoi diritti',
'privacy.diritti.testo' => <<<'T'
Puoi chiederci in ogni momento (artt. 15–22 GDPR):
- **accesso** ai tuoi dati e una copia;
- **rettifica** dei dati sbagliati o incompleti (molti li cambi da solo in Account & Fatturazione);
- **cancellazione** dei dati e **limitazione** del trattamento;
- **portabilità**: i tuoi dati in un formato leggibile da un computer;
- **opposizione** ai trattamenti basati sul legittimo interesse;
- **revoca del consenso**, senza effetto su quello che è stato fatto prima.

Per esercitarli scrivi a {email}: ti rispondiamo entro 30 giorni. Puoi anche proporre reclamo al Garante per la protezione dei dati personali ([www.garanteprivacy.it](https://www.garanteprivacy.it)) o ricorrere al giudice. Non prendiamo decisioni basate unicamente su trattamenti automatizzati.
T,
'privacy.modifiche.titolo' => 'Modifiche all\'informativa',
'privacy.modifiche.testo' => <<<'T'
In alto trovi la data e la versione di questa informativa. Se la cambiamo in modo importante ti avvisiamo per email e nel pannello; le versioni precedenti restano consultabili da questa pagina.
T,
'privacy.titolare.titolo' => 'Titolare del trattamento',
'privacy.titolare.testo' => <<<'T'
{titolare_completo}, {sede}. Email {email} · telefono {telefono} · {sito_titolare}.

Non è stato nominato un responsabile della protezione dei dati (non è obbligatorio, art. 37 GDPR): per ogni richiesta scrivi a {email}.
T,

// ================================================================ ACCORDO SUL TRATTAMENTO DEI DATI (DPA)
'dpa.intro' => <<<'T'
Allegato A ai {link_termini}. Questo accordo regola, ai sensi dell'art. 28 del Regolamento (UE) 2016/679 (GDPR), il trattamento dei dati personali che {titolare_completo} («Responsabile») svolge per conto dell'host («Titolare») fornendo MyHouse Welcome. Si accetta insieme ai Termini, alla registrazione, e vale finché vale il contratto.
T,
'dpa.1.titolo' => 'Oggetto e durata',
'dpa.1.testo' => <<<'T'
Il Responsabile tratta i dati personali descritti al punto 3 solo per fornire il Servizio previsto dai Termini, per tutta la durata del contratto e, dopo, per il tempo necessario a restituirli o cancellarli (punto 10).
T,
'dpa.2.titolo' => 'Natura e finalità',
'dpa.2.testo' => <<<'T'
Conservazione, organizzazione, pubblicazione nella guida, traduzione su richiesta, copia di sicurezza e cancellazione dei dati, al solo scopo di mostrare la guida agli ospiti e di permettere all'host di gestirla.
T,
'dpa.3.titolo' => 'Tipi di dati e categorie di interessati',
'dpa.3.testo' => <<<'T'
- **Interessati**: l'host, i suoi collaboratori indicati nella guida, gli ospiti che contattano l'host dalla guida, le persone che compaiono nelle foto caricate dall'host.
- **Dati**: nomi, ruoli, numeri di telefono e WhatsApp, indirizzi email «per gli ospiti», foto, testi e documenti caricati dall'host; voti anonimi lasciati dagli ospiti nella guida.
- Non sono previste categorie particolari di dati (art. 9 GDPR): il Titolare si impegna a non inserirle.
T,
'dpa.4.titolo' => 'Istruzioni documentate',
'dpa.4.testo' => <<<'T'
Il Responsabile tratta i dati solo secondo le istruzioni documentate del Titolare, che sono i Termini, questo accordo e le scelte che il Titolare fa nel pannello. Se un'istruzione gli sembra contraria alla legge, lo segnala subito al Titolare.
T,
'dpa.5.titolo' => 'Riservatezza',
'dpa.5.testo' => <<<'T'
Le persone autorizzate a trattare i dati per conto del Responsabile sono vincolate alla riservatezza e accedono solo a quello che serve per il loro compito. Gli accessi degli amministratori agli account sono registrati.
T,
'dpa.6.titolo' => 'Misure di sicurezza',
'dpa.6.testo' => <<<'T'
Il Responsabile adotta misure tecniche e organizzative adeguate (art. 32 GDPR), tra cui:
- connessioni cifrate (HTTPS) per il sito, il pannello e le guide;
- password conservate solo in forma cifrata (hash) e limiti ai tentativi di accesso;
- file su Amazon S3 nell'Unione europea, con indirizzi firmati a tempo;
- registri tecnici con i dati personali abbreviati, e registro degli accessi degli amministratori inalterabile per 6 mesi;
- ogni accesso dell'assistenza a un account registrato e visibile all'host;
- guide escluse dai motori di ricerca e senza cookie.
T,
'dpa.7.titolo' => 'Sub-responsabili',
'dpa.7.testo' => <<<'T'
Il Titolare autorizza il Responsabile a servirsi dei sub-responsabili elencati nell'{link_privacy} («Destinatari e fornitori»): Hostinger (hosting), Amazon Web Services (archiviazione e traduzioni), il fornitore della posta in uscita. Il Responsabile impone loro, con un contratto, gli stessi obblighi di questo accordo. Prima di aggiungerne o sostituirne uno avvisa il Titolare con almeno 30 giorni di anticipo: se il Titolare si oppone per motivi fondati, può recedere dal contratto senza penali.
T,
'dpa.8.titolo' => 'Assistenza al Titolare',
'dpa.8.testo' => <<<'T'
Il Responsabile aiuta il Titolare a rispondere alle richieste degli interessati (accesso, rettifica, cancellazione…), anzitutto con le funzioni del pannello, e a rispettare gli obblighi degli artt. 32–36 GDPR, per quanto gli compete. Se riceve una richiesta di un interessato rivolta al Titolare, gliela inoltra.
T,
'dpa.9.titolo' => 'Violazioni dei dati',
'dpa.9.testo' => <<<'T'
Se viene a conoscenza di una violazione dei dati personali che riguarda il Titolare, il Responsabile lo avvisa **entro 48 ore**, all'email dell'account, con le informazioni disponibili (cosa è successo, quali dati, quali misure), e lo aggiorna man mano.
T,
'dpa.10.titolo' => 'Fine del contratto',
'dpa.10.testo' => <<<'T'
Alla fine del contratto il Titolare può chiedere una copia dei suoi contenuti scrivendo a {email}; il Responsabile cancella i dati entro 30 giorni dalla chiusura dell'account, salvo quelli che la legge gli impone di conservare, e le copie di sicurezza alla loro naturale scadenza.
T,
'dpa.11.titolo' => 'Verifiche',
'dpa.11.testo' => <<<'T'
Il Responsabile mette a disposizione del Titolare, su richiesta scritta e con un preavviso ragionevole, le informazioni necessarie a dimostrare il rispetto di questo accordo (verifica documentale). Eventuali ispezioni si concordano per iscritto.
T,
'dpa.12.titolo' => 'Contatti',
'dpa.12.testo' => <<<'T'
Responsabile del trattamento: {titolare_completo}, {sede}. Email {email}.
T,

// ================================================================ AGGIORNAMENTI (la finestra nel pannello e l'email)
'legale.novita' => <<<'T'
«Giudizio con sconto»: il 5% si applica una sola volta. Se aderisci al pagamento (o prima del primo pagamento), sul primo anno; se aderisci dopo, sul rinnovo successivo al tuo giudizio (voto e commento). Il logo che carichi compare così com'è nella scheda in home.
Programma partner: con il codice di un partner hai uno sconto sul primo anno; al partner va una commissione sui tuoi pagamenti, senza i tuoi dati personali.
Cambio di piano proposto da noi con uno sconto: vale solo con la tua conferma, dal link che ti mandiamo.
Sconto sul rinnovo, una sola percentuale: inviti e giudizio si sommano e si applicano una volta sul prezzo di listino (fino al 55%).
T,
],

'en' => [
// ================================================================ TERMS AND CONDITIONS
'termini.intro' => <<<'T'
These Terms govern the use of MyHouse Welcome. Please read them before creating your account: by creating it you accept them, together with the {link_dpa} (Annex A). How we handle your data is explained in the {link_privacy}. The Italian version prevails in case of doubt.
T,
'termini.1.titolo' => 'Who we are and definitions',
'termini.1.testo' => <<<'T'
MyHouse Welcome (the "Service") is provided by {titolare_completo}, registered office {sede} ("we"). Email {email}.

In these Terms:
- **Service**: the MyHouse Welcome platform, with the dashboard, the guides and the related features;
- **Host** or **Customer**: whoever creates an account to manage one or more accommodation properties ("you");
- **Guest**: whoever stays at the property and reads the guide;
- **Guide**: the digital guide of one accommodation unit, reachable from a link and a QR code;
- **Plan**: the chosen subscription (Essential, Plus, Portfolio…), with its features and limits;
- **Account**: the Customer's personal space in the Service.
T,
'termini.2.titolo' => 'The service and the "one guide, one accommodation unit" rule',
'termini.2.testo' => <<<'T'
MyHouse Welcome lets whoever runs an accommodation business create a digital guide for their guests, reachable from a link and a QR code. Guests do not sign up and do not install apps.

**One guide, one accommodation unit.** Each guide corresponds to a single accommodation unit: one address and one CIN (the Italian National Identification Code), which is required to publish and cannot be used by another guide. A guide cannot cover several houses, flats or properties with different addresses or CINs: each one needs its own guide, for example with the Portfolio plan. B&Bs, guesthouses and farm stays with several rooms at the same address and with the same CIN are the exception: they are a single property; if rooms have different Wi-Fi networks, list them in the Wi-Fi section, one line per area. In each guide every section appears only once, and there is one free section per guide. If a guide is used for several accommodation units we may ask you to split it and, if you don't, suspend its publication after a notice.
T,
'termini.3.titolo' => 'Account',
'termini.3.testo' => <<<'T'
To use the Service you create an account with an email address that you confirm. The Service is only for people **aged 18 or over**.

You are responsible for keeping your password confidential and for what happens with your account: if you think someone knows it, change it immediately and write to us. Each account belongs to a single person or company and cannot be transferred or shared; several properties are managed from one account with the right plan. The data you enter (name, email, billing details) must be true and up to date.
T,
'termini.4.titolo' => 'Free trial and publication',
'termini.4.testo' => <<<'T'
You can create and set up the guide for free, without a credit card, and preview it as your guests will see it. The guide becomes visible to guests only after a paid subscription is activated, when you first publish.
T,
'termini.5.titolo' => 'Subscription, prices and renewal',
'termini.5.testo' => <<<'T'
**Automatic renewal.** The subscription is yearly and, unless you turn renewal off, it renews automatically at the end of the period for another year, charged to the payment method on file. We notify you by email and in the dashboard 30 and 7 days before. You can turn off renewal at any time from Account & Billing: the service stays active until the end of the period paid and nothing else is charged. Prices are shown excluding VAT; tax is calculated at checkout.

You can change plan from Account & Billing. If you move to a higher plan or add properties you pay straight away only the difference for the days left until renewal, and the renewal date does not change. If you move to a lower plan the change applies from the next renewal: until then you keep the plan already paid, without refunds.

**A change proposed by us.** We may propose a change of plan with a discount, by email or message, with a link: it applies only with your confirmation from that link, by the date indicated. Without your confirmation nothing changes.

Discount codes apply only to the first year of the first subscription: from renewal the full price of the plan applies. They cannot be combined with each other or with an invitation discount: only one code applies to each subscription. "Invite a friend" follows the conditions in section 12.

**Tailored offers.** Beyond the maximum number of properties of Portfolio we make you a tailored offer: an agreed annual price for all your properties, valid until the date stated in the offer, which also applies **at renewal**. The discounts of the «Invite a friend» and «Review with a discount» programmes do not apply automatically to tailored offers: any renewal discount is agreed with you and shown in the notice before the renewal. To change the number of properties we send you a new offer, which applies straight away (paying the difference for the days left) or from the renewal.
T,
'termini.6.titolo' => 'Payment and failed payment',
'termini.6.testo' => <<<'T'
Payments are processed by Stripe (Stripe Payments Europe Ltd). Card details are received and stored only by Stripe: we never see them.

If a renewal payment fails, Stripe retries up to 3 times within 14 days and notifies you by email. If no attempt succeeds, the subscription ends and the guide is no longer visible to guests. Your content stays in the account for 24 months: you can publish the guide again by activating a new subscription.

For every payment we issue an Italian electronic invoice, sent through the Exchange System (SDI) with our invoicing software (Adamo), using the billing details you enter in the dashboard. For billing and payment questions write to {email_fatture}.
T,
'termini.7.titolo' => 'Right of withdrawal',
'termini.7.testo' => <<<'T'
Before payment we ask whether you are buying for your accommodation business (company or professional) or as a private individual. Your choice is recorded with the subscription.

**Businesses and professionals** (buying with a VAT number or for their own accommodation business): there is no statutory right of withdrawal. You can turn off automatic renewal at any time, and the service stays active until the end of the paid period.

**Consumers** (private individuals buying without a VAT number, for purposes outside any business or profession, art. 3 of the Italian Consumer Code): you have 14 days from payment to withdraw without giving any reason, with a full refund within 14 days of your notice, using the same means of payment. An explicit statement to {email} is enough, or the {link_modulo} at the end of this page.

If at checkout you expressly ask for the service to start immediately (the guide is published as soon as payment is confirmed), you acknowledge that, if you withdraw within the 14 days, you pay for the part of the subscription corresponding to the days already used and we refund the rest, and that the right of withdrawal is lost once the service has been fully performed (arts. 50, 57 and 59 of the Italian Consumer Code).
T,
'termini.8.titolo' => 'Your content and access codes',
'termini.8.testo' => <<<'T'
The texts, images and documents you publish remain yours. You guarantee that you have the right to use them (including photos showing people) and that they do not infringe third-party rights.

Do not publish unlawful, offensive or misleading content, or other people's personal data without the right to do so. If we receive a well-founded report or see such content, we may ask you to remove it and, if you don't, remove it or suspend the guide after a notice; without notice only in serious cases or when an authority asks us to.

Do not publish door codes, alarm passwords or other data whose disclosure could put the property's security at risk. If you decide to publish them anyway, the platform asks you to confirm and you do so at your own risk.
T,
'termini.9.titolo' => 'Privacy roles and data processing agreement',
'termini.9.testo' => <<<'T'
For the personal data you enter in the guide or receive through the Service (names and contact details of you and your staff, the "for guests" email, WhatsApp messages guests send you from the guide, anonymous ratings of the guides) **you are the data controller** and {titolare} is the **data processor** (art. 28 GDPR). The rules are in the {link_dpa}, Annex A to these Terms, which you accept together with the Terms.

For the data of your account, payments and the website, on the other hand, we are the controller: see the {link_privacy}.
T,
'termini.10.titolo' => 'Availability and limitation of liability',
'termini.10.testo' => <<<'T'
We do our best to keep the Service available and the data safe, but we cannot guarantee it will be free of interruptions. Where possible, maintenance takes place at low-traffic times. We recommend keeping a copy of your most important texts and photos.

We are not liable for the content you publish, for interruptions or changes of third-party services the guide links to (Google, Booking, Airbnb, maps, weather…), or for indirect damages (for example lost profits). Otherwise, our maximum liability equals what you paid us in the last 12 months, except in cases of wilful misconduct or gross negligence.

Nothing in these Terms limits the rights granted to consumers by law.
T,
'termini.11.titolo' => 'Term, suspension and closure',
'termini.11.testo' => <<<'T'
The contract lasts as long as your account; the subscription lasts for the paid period and renews as set out in section 5.

We may suspend the account or a guide if you breach these Terms, after a notice giving you time to remedy; immediately, if the breach is serious (for example unlawful content or a use that puts the Service or other people at risk).

You can ask us to close your account at any time by writing to {email}. On closure, published guides go offline immediately and the data are deleted within the times set out in the {link_privacy}, except those we must keep by law (for example invoices).
T,
'termini.12.titolo' => 'Invite a friend',
'termini.12.testo' => <<<'T'
Anyone with an active subscription can invite other people with their personal link. Whoever signs up from that link and activates their first subscription gets {amico}% off the first year. For each invited person who pays their first subscription, the inviter gets {passo}% off the price of their next yearly renewal, up to a maximum of {max}% ({amici} invitations). Invitations beyond the maximum give no further discount to the inviter; the invited person still gets theirs.

The inviter's discount applies only once, to the next automatic renewal; after that renewal the count starts again from zero. It cannot be converted into cash and does not apply without a renewal. The invited person's discount cannot be combined with other discount codes. Invitations to yourself, to accounts with the same billing details or to people who already had a subscription do not count. An invitation no longer counts if the invited person's first payment is refunded. If you send the invitation by email from the dashboard, you declare that the invited person agreed to receive it. We may change or end the programme: discounts already earned remain valid for the next renewal.

**Partner programme.** Property managers and agencies approved by us have a partner code (for example PM-ROSSI). If you use it at checkout or arrive from their link, you get the partner's discount on the first year (it adds up with a friend's invitation; with a discount code the higher one applies; it does not apply with a gift code) and your account stays linked to that partner, once only. We pay the partner a commission on your payments, renewals included unless otherwise stated, settled against their invoice; nothing changes in what you pay. We do not share your personal data with the partner beyond the property name and town, plan and dates: never email, amounts paid or payment details. We may close the programme with notice.
T,
'termini.13.titolo' => 'Suggested translations',
'termini.13.testo' => <<<'T'
With the plans that include them you can ask for translations suggested by a machine translator (Amazon Translate). They are proposals to be checked: they enter the guide only when you approve them, and from then on they are your content like the rest. We do not guarantee they are correct or suitable for your text: for important texts, have them reviewed by a native speaker.

Suggested translations are included in the Plus and Portfolio plans within a yearly character limit shown in the dashboard; beyond it, on request. Those you approved stay in the guide even after the limit.
T,
'termini.14.titolo' => 'Changes to these Terms',
'termini.14.testo' => <<<'T'
We may change these Terms, for example for new features or changes in the law. We notify you by email and with a message in the dashboard at least 30 days before the date the changes apply. If you do not accept them you can withdraw without penalty until that date; after it, by continuing to use the Service, you accept them.

The economic conditions of the period you have already paid do not change: any price list changes apply to new purchases and from the next renewal.
T,
'termini.15.titolo' => 'Governing law and jurisdiction',
'termini.15.testo' => <<<'T'
These Terms are governed by Italian law.

If you are a consumer, the court of your place of residence or domicile has jurisdiction over any dispute, and you keep your right to use the alternative dispute resolution (ADR) bodies provided for by the Italian Consumer Code. The European online dispute resolution (ODR) platform was closed by the European Commission on 20 July 2025.

In all other cases the **courts of Perugia** (Italy) have exclusive jurisdiction.
T,
'termini.17.titolo' => 'Review with a discount, renewal discount and gift code',
'termini.17.testo' => <<<'T'
**What it is.** An optional programme: whoever joins undertakes to leave, after publishing the guide, a rating from 1 to 5 and a comment on the service; in return they get a {giudizio}% discount on the list price of the subscription. You join with a single checkbox, at checkout or from the dashboard (Settings → Review with a discount).

**The discount.** The {giudizio}% off the list price **applies only once**: if you join at checkout (or before your first payment), on the first year; if you join after paying, on the following renewal, provided that by then you have left your rating and comment (otherwise on the first renewal after the review). It is not repeated on later renewals. It adds up with the discount of the "Bring a friend" programme, up to a combined maximum of {totale}% on that renewal; it does not add up with discount codes (the higher one applies) and is not available during a gift period. The discount does not depend on the rating: it applies even with one star.

**What we may publish.** By joining, the host authorises MyHouse Welcome to show on the welcomemyhouse.it home page, at its own discretion and with no obligation:

- the property **card** (property name, cover photo, logo, the host's first name without surname, town), even before the review and even without a review; with the Portfolio plan all the account's properties may appear;
- the **review** (rating, comment, name and property).

**The property logo, if uploaded, appears on the card as it is: if you choose to use a photograph of yourself as the logo, you agree to it being shown on the site's home page. You can change or remove it at any time from the dashboard.**

To protect the privacy of the host and their guests, cards and reviews contain no link to the guide, cannot be found by search engines, and show neither the host's photo nor any guest data. We publish nothing about those who do not join.

**Leaving.** The host can leave at any time from the dashboard: the card and the review disappear from the home page within 24 hours. A discount already applied stays; the one promised on the renewal lapses if the host leaves before the renewal. The card and the review also disappear if the guide is archived, the subscription expires or the account is closed. MyHouse Welcome may remove or not publish a card or a review without affecting the discount, and may close the programme to new members with 30 days' notice, keeping the discounts already promised.

**Reminders.** Whoever has joined and has not yet left the review receives a reminder 30 days after publishing and, if they joined after paying, a second one 30 days before the renewal.

**Renewal discount, a single percentage.** The review discount and the invitation discounts (section 12) add up and are applied **once on the list price** of the renewal: for example 10 invitations (50%) and the review (5%) make 55%. They do not add up with discount codes, which apply to the first year only: between a code and the system discounts, the higher one applies.

**Gift code.** With a gift code the first months indicated are free. You still register a payment method and, at the end of the free months, the subscription starts automatically at the full price of the chosen plan, with the same notices by email and in the dashboard 30 and 7 days before the charge. If you turn off renewal before the end of the free period nothing is charged: the guide stays online until that date, then it is no longer visible to guests. The code is valid for one person only, for the first subscription only, by the date indicated. During the free months the discount of an invitation you received does not apply: if the account came from an invitation and uses a gift code, the gift code applies and the invitation is not counted. During the free months you can also join the programme and your card may be published. **After the first payment you get all the benefits**: invitations ({passo}% per friend, up to {max}%) and review ({giudizio}%), up to {totale}%, starting from the first renewal. We may cancel an unused code at any time.
T,
'termini.16.titolo' => 'Contact details and provider',
'termini.16.testo' => <<<'T'
{titolare_completo}, {sede}, Italy. Email {email} · phone {telefono} · {sito_titolare}.
T,
'termini.b.titolo' => 'Annex B · Model withdrawal form',
'termini.b.testo' => <<<'T'
Annex I, part B, of the Italian Consumer Code. Complete and send this form only if you are a consumer and wish to withdraw from the contract.

- To: {titolare_completo}, {sede}, Italy · email {email}
- I/We (*) hereby give notice that I/We (*) withdraw from my/our (*) contract for the provision of the following service: MyHouse Welcome subscription, plan …………
- Ordered on (*) / paid on (*): …………
- Name of consumer(s): …………
- Address of consumer(s): …………
- Account email: …………
- Signature of consumer(s) (only if this form is sent on paper): …………
- Date: …………

(*) Delete as appropriate.
T,

// ================================================================ PRIVACY POLICY
'privacy.intro' => <<<'T'
This policy explains how {titolare} processes the personal data of people using MyHouse Welcome (hosts and website visitors) and of guests reading the guides, under articles 13 and 14 of Regulation (EU) 2016/679 (GDPR). For each processing activity you will find which data, why, on what legal basis, for how long and who receives them.

For the data hosts enter in their guides, the host is the controller and we are the processor: see the {link_dpa}. The Italian version prevails in case of doubt.
T,
'privacy.t1.titolo' => 'Account and contract',
'privacy.t1.testo' => <<<'T'
| Data | Name, email, password (stored only in encrypted form, as a hash), sign-up and email confirmation dates, date and version of the documents accepted, support staff's internal notes on your account.
| Purpose | Creating and managing your account and providing the Service.
| Legal basis | Performance of the contract (art. 6.1.b GDPR); for acceptances, our legitimate interest in being able to prove them (art. 6.1.f).
| Retention | While the account is active; then up to 24 months from the last login or from the end of the last subscription, after which we delete them. We notify you by email 30 days before deletion.
| Recipients | The hosting provider and the email provider (see "Recipients and providers").
T,
'privacy.t2.titolo' => 'Payments and invoicing',
'privacy.t2.testo' => <<<'T'
| Data | Billing details (name or company name, address, VAT number or tax code, SDI code or PEC), plan, amounts, dates and status of payments, the "business" or "private individual" choice made before payment, any gift code. Card details are processed only by Stripe: we only receive from Stripe the last 4 digits, to show them in the notices before a charge.
| Purpose | Collecting the subscription, issuing invoices, meeting tax and accounting obligations.
| Legal basis | Performance of the contract (art. 6.1.b) and legal obligation (art. 6.1.c).
| Retention | 10 years from the last entry (art. 2220 of the Italian Civil Code).
| Recipients | Stripe (payments), Adamo (electronic invoicing), the Italian Revenue Agency through the Exchange System (SDI).
T,
'privacy.t3.titolo' => 'Guide content',
'privacy.t3.testo' => <<<'T'
| Data | Texts, photos, PDFs, cover and host photos uploaded by the host; names, phone numbers, WhatsApp and "for guests" emails of the host and their staff; links to booking portals and to the property's website.
| Purpose | Publishing the guide for the property's guests.
| Legal basis | Performance of the contract (art. 6.1.b). For these data the host is the controller and guarantees they have the right to publish them (including photos of people); we process them as processor under the {link_dpa}.
| Retention | Same as the account.
| Recipients | Anyone with the guide's link or QR code (guides are not listed in search engines); Amazon Web Services for files; Amazon Translate only for the texts the host asks to translate.
T,
'privacy.t4.titolo' => 'Service emails and reminders',
'privacy.t4.testo' => <<<'T'
| Data | Email, name, type and date of the emails sent.
| Purpose | Emails needed for the Service (email confirmation, password recovery, renewal notice, notices about legal documents and security) and reminders to complete or update the guide.
| Legal basis | Performance of the contract (art. 6.1.b); for reminders, our legitimate interest in helping you use the Service (art. 6.1.f).
| Objection | Every reminder has a "Stop sending me these" link, which applies to that type of reminder. Renewal, security and legal-document notices cannot be turned off.
| Retention | The log of emails sent, same as the account. We do not send marketing to non-customers.
| Recipients | The email provider.
T,
'privacy.t5.titolo' => 'Invite a friend',
'privacy.t5.testo' => <<<'T'
| Data | The personal link and code, who signed up from an invitation and whether they paid; the invited person's email, if the host writes it in the dashboard: we use it only to send that invitation and keep it only in hashed and shortened form, so as not to invite the same person twice.
| Purpose | Managing the programme's invitations and discounts.
| Legal basis | Performance of the contract (art. 6.1.b) and legitimate interest (art. 6.1.f). The host declares that the invited person agreed to receive the email.
| Retention | 24 months.
| Recipients | The email provider.
T,
'privacy.t6.titolo' => 'Review with a discount and showcase, customer feedback',
'privacy.t6.testo' => <<<'T'
| Data | For the "Review with a discount and showcase" programme: property name, cover and logo (if there are any and you choose to show them), your first name (no surname), the town, the rating and the comment, the date the guide was published. **No photo of the person.** The answer to "What stopped you?", if you answer.
| Purpose | Only if you join the programme (without joining we publish nothing about you): showing, if we decide to publish them, your card among "Our hosts' guides" (from the moment you publish the guide) and your review in "What they say about us" (with the discount, whatever the rating and even if we do not publish them); understanding what to improve.
| Legal basis | For the card and the review, the contract (Art. 6.1.b): joining the programme, with the single checkbox you accept at checkout or from the dashboard (Terms, "Review with a discount"). You can leave whenever you like from Settings → Review with a discount: the card and the review disappear from the site immediately. For feedback, our legitimate interest (Art. 6.1.f).
| Retention | The card and the review until you leave or close your account; feedback for as long as the account.
| Recipients | Website visitors, only for the published cards and reviews of those who joined (home page and "All guides" page). No link to your guide, no data about your guests.
T,
'privacy.t7.titolo' => 'Guide reading statistics',
'privacy.t7.testo' => <<<'T'
| Data | Anonymous events: guide opened, opened from the QR code, section viewed, language, guest rating. No IP address, no cookies and no device identification: they are not personal data.
| Purpose | Giving the host aggregated reading statistics.
| Legal basis | None needed: they are not personal data.
| Retention | Without limit, in aggregated form.
| Recipients | The guide's host.
T,
'privacy.t8.titolo' => 'The "A website for your property too" questions',
'privacy.t8.testo' => <<<'T'
| Data | Your answers (do you already have a website, what you would like to improve), the channel chosen (website, WhatsApp, email), the page you answered from and, if you are logged in, your account. If you then write to us, your message and contact details.
| Purpose | Answering your request for a website for your property.
| Legal basis | Pre-contractual steps at your request (art. 6.1.b) and legitimate interest (art. 6.1.f).
| Retention | 12 months.
| Recipients | **Blackout Agency** (blackout.in, Perugia), a separate company that builds MyHouse websites: we share your answers with them, and they contact you. If you choose "Discover MyHouse", your answers reach blackout.in when the page opens. Blackout Agency processes them as an independent controller, under its own privacy policy.
T,
'privacy.t9.titolo' => 'Website statistics',
'privacy.t9.testo' => <<<'T'
| Data | Pages visited, referrer, device and browser type, events such as sign-up or payment. With Matomo the IP address is anonymised (last two bytes) and no cookies are used; with Google Analytics and Microsoft Clarity, only if you consent, the identifiers of their cookies.
| Purpose | Understanding how the website is used and improving it.
| Legal basis | Matomo: our legitimate interest (art. 6.1.f), without cookies. Google Analytics and Clarity: your consent (art. 6.1.a and art. 122 of the Italian Privacy Code), which you can withdraw at any time.
| Retention | Matomo: raw data 6 months, then only in aggregated form. Google and Microsoft: according to their policies (see "Cookies").
| Recipients | Matomo runs on our server; Google and Microsoft only with consent. Guest guides, the dashboard and the administration area have no third-party statistics.
T,
'privacy.t10.titolo' => 'Technical and security logs',
'privacy.t10.testo' => <<<'T'
| Data | Website errors, login attempts, administrators' logins and actions, test emails. IP addresses, emails and phone numbers are shortened in the logs; for login attempt limits the IP becomes a code that cannot be traced back to the address.
| Purpose | Security of the Service, abuse prevention, error fixing, monitoring of system administrators' access (Italian Data Protection Authority decision of 27 November 2008).
| Legal basis | Legitimate interest (art. 6.1.f) and legal obligation for administrators' access (art. 6.1.c).
| Retention | Technical logs {giorni_registri} days; login attempt codes 24 hours; test emails 7 days. The administrators' access log is copied every night to Amazon Web Services (S3, Milan), where it **cannot be altered for 6 months** and is deleted after 12 months.
| Recipients | The hosting provider; Amazon Web Services for the copy of the administrators' access log.
T,
'privacy.t11.titolo' => 'Support',
'privacy.t11.testo' => <<<'T'
| Data | What you write to us by email ({email}), WhatsApp or phone, and your contact details.
| Purpose | Answering your requests and helping you use the Service.
| Legal basis | Performance of the contract (art. 6.1.b) or legitimate interest in answering you (art. 6.1.f).
| Retention | 24 months from the closing of the request.
| Recipients | The email provider; WhatsApp (Meta), if you write to us on WhatsApp, under its own policy.
T,
'privacy.t12.titolo' => 'Support staff access to your account',
'privacy.t12.testo' => <<<'T'
| Data | What is shown in your dashboard, when an administrator enters it to help you.
| Purpose | Technical support, at your request or to fix a problem.
| Legal basis | Legitimate interest (art. 6.1.f).
| Safeguards | Every access is logged (date, time, who) and you can see it in Account & Billing. Access lasts one hour at most.
| Retention | The access log, same as the account; the shortened IP address, deleted after 12 months.
T,
'privacy.t13.titolo' => 'Quote requests',
'privacy.t13.testo' => <<<'T'
| Data | Name, email, phone (if you give it), number of properties and what you write in the "Ask for a quote" form (for more than 10 properties).
| Purpose | Preparing a tailored offer and getting back to you.
| Legal basis | Pre-contractual steps at your request (art. 6.1.b).
| Retention | 12 months from the request, then we delete it.
| Recipients | The hosting provider and the email provider.
T,
'privacy.t14.titolo' => 'Partnership requests',
'privacy.t14.testo' => <<<'T'
| Data | Name, company, email, phone (if you give it), how many properties you manage, the cities or areas and what you write in the "Become a partner" form on the page for property managers.
| Purpose | Answering the request, getting to know each other and assessing the Partner programme together.
| Legal basis | Pre-contractual steps at your request (art. 6.1.b).
| Retention | 12 months from the request, then we delete it.
| Recipients | The hosting provider and the email provider.
T,
'privacy.t15.titolo' => 'Partner programme',
'privacy.t15.testo' => <<<'T'
| Data | For the partner: company name, contact person, email, phone, area, VAT number, IBAN, commissions and settlements. For a client who came from a partner: the link to the partner (which code and when), their payments to calculate the commission.
| Purpose | Running the programme: the client's discount, the commissions and the bank transfers to the partner, the partner page.
| Legal basis | Performance of the contract with the partner and with the client (art. 6.1.b); tax obligations for the settlements (art. 6.1.c).
| Retention | 10 years for settlements and commissions (tax obligations); the client–partner link as long as the account exists.
| Recipients | The partner only sees the property name and town, plan, dates and their commission: never the client's email, amounts paid or payment details. The hosting provider and the email provider.
T,
'privacy.fornitori.titolo' => 'Recipients and providers',
'privacy.fornitori.testo' => <<<'T'
These providers help us deliver the Service and are appointed as processors by contract (art. 28 GDPR):
- **Hostinger** (Hostinger International Ltd, Cyprus): hosting of the website and database, on servers in the European Union;
- **Amazon Web Services EMEA SARL** (Luxembourg): storage of photos, PDFs and the copy of the logs on Amazon S3 in Milan (eu-south-1); suggested translations with Amazon Translate in Ireland ({regione_traduzioni});
- **Stripe Payments Europe Ltd** (Ireland): payments;
- **Adamo** (Italy): electronic invoicing;
- **outgoing email**: {posta}.

The following are instead independent controllers, each with its own policy: **Google** and **Microsoft**, only if you consent to their cookies; **Blackout Agency** (blackout.in), only for the "A website for your property too" questions. Authorities may also receive data, when required by law.
T,
'privacy.trasferimenti.titolo' => 'Transfers outside the European Union',
'privacy.trasferimenti.testo' => <<<'T'
Data stay in the European Union, except in these cases: Stripe may transfer them to Stripe Inc. (United States), Google and Microsoft (only with your consent to cookies) to their companies in the United States. These transfers rely on the European Commission's Standard Contractual Clauses and, for companies that have joined it, on the EU-US Data Privacy Framework.
T,
'privacy.minori.titolo' => 'Minors',
'privacy.minori.testo' => <<<'T'
The Service is only for adults. The guides do not ask guests, adult or minor, for any data.
T,
'privacy.diritti.titolo' => 'Your rights',
'privacy.diritti.testo' => <<<'T'
You can ask us at any time (arts. 15–22 GDPR) for:
- **access** to your data and a copy;
- **rectification** of wrong or incomplete data (you can change many of them yourself in Account & Billing);
- **erasure** of your data and **restriction** of processing;
- **portability**: your data in a machine-readable format;
- **objection** to processing based on legitimate interest;
- **withdrawal of consent**, without affecting what was done before.

To exercise them write to {email}: we reply within 30 days. You can also lodge a complaint with the Italian Data Protection Authority ([www.garanteprivacy.it](https://www.garanteprivacy.it)) or go to court. We do not take decisions based solely on automated processing.
T,
'privacy.modifiche.titolo' => 'Changes to this policy',
'privacy.modifiche.testo' => <<<'T'
The date and version of this policy are shown at the top. If we change it significantly we notify you by email and in the dashboard; previous versions remain available from this page.
T,
'privacy.titolare.titolo' => 'Data controller',
'privacy.titolare.testo' => <<<'T'
{titolare_completo}, {sede}, Italy. Email {email} · phone {telefono} · {sito_titolare}.

No data protection officer has been appointed (it is not mandatory, art. 37 GDPR): for any request write to {email}.
T,

// ---- the «Cookies» paragraph of the privacy policy (the Italian ones are in TestiSito, section «cookie»)
'cookie.tecnici' => 'The website only uses technical cookies, which do not require consent: the session cookie (mhw_sess), which keeps you logged in to the dashboard and protects forms, the one that remembers your cookie choice (mhw_consenso, 6 months) and, in the dashboard, the one that hides the renewal notice for 24 hours when you press «Close» (mhw_fascia_…).',
'cookie.matomo' => 'To count visits we use Matomo, installed on our own server: it uses no cookies, the IP address is anonymised (last two bytes) and the data stay with us, without being passed to third parties.',
'cookie.ga4' => '**Google Analytics 4** (Google Ireland Ltd), only with your consent to «Statistics»: it measures visits in aggregated form, without advertising signals or ad personalisation. Cookies _ga and _ga_*, lasting up to 2 years. [Google privacy policy](https://policies.google.com/privacy).',
'cookie.clarity' => '**Microsoft Clarity** (Microsoft Ireland Operations Ltd), only with your consent to «Usage analysis»: heatmaps and session recordings to understand where the website is unclear; form fields are masked. Cookies _clck (1 year) and _clsk (1 day). [Microsoft privacy statement](https://privacy.microsoft.com/privacystatement).',
'cookie.revoca' => 'You can change your mind at any time from «Cookie preferences», at the bottom of the website pages: if you withdraw consent, the Google Analytics and Clarity cookies are deleted.',
'cookie.guide' => 'Guest guides use no cookies and no third-party statistics: they only count anonymous events for the host, as described above.',

// ================================================================ DATA PROCESSING AGREEMENT
'dpa.intro' => <<<'T'
Annex A to the {link_termini}. Under art. 28 of Regulation (EU) 2016/679 (GDPR), this agreement governs the processing of personal data that {titolare_completo} ("Processor") carries out on behalf of the host ("Controller") when providing MyHouse Welcome. It is accepted together with the Terms, at sign-up, and applies for as long as the contract does. The Italian version prevails in case of doubt.
T,
'dpa.1.titolo' => 'Subject matter and duration',
'dpa.1.testo' => <<<'T'
The Processor processes the personal data described in section 3 only to provide the Service under the Terms, for the whole duration of the contract and, afterwards, for the time needed to return or delete them (section 10).
T,
'dpa.2.titolo' => 'Nature and purpose',
'dpa.2.testo' => <<<'T'
Storage, organisation, publication in the guide, translation on request, backup and deletion of data, for the sole purpose of showing the guide to guests and letting the host manage it.
T,
'dpa.3.titolo' => 'Types of data and categories of data subjects',
'dpa.3.testo' => <<<'T'
- **Data subjects**: the host, their staff named in the guide, guests contacting the host from the guide, people shown in photos uploaded by the host.
- **Data**: names, roles, phone and WhatsApp numbers, "for guests" email addresses, photos, texts and documents uploaded by the host; anonymous ratings left by guests in the guide.
- No special categories of data (art. 9 GDPR) are expected: the Controller undertakes not to enter them.
T,
'dpa.4.titolo' => 'Documented instructions',
'dpa.4.testo' => <<<'T'
The Processor processes the data only on the Controller's documented instructions, which are the Terms, this agreement and the choices the Controller makes in the dashboard. If an instruction appears to infringe the law, the Processor immediately informs the Controller.
T,
'dpa.5.titolo' => 'Confidentiality',
'dpa.5.testo' => <<<'T'
People authorised to process the data on the Processor's behalf are bound by confidentiality and access only what they need for their task. Administrators' access to accounts is logged.
T,
'dpa.6.titolo' => 'Security measures',
'dpa.6.testo' => <<<'T'
The Processor implements appropriate technical and organisational measures (art. 32 GDPR), including:
- encrypted connections (HTTPS) for the website, the dashboard and the guides;
- passwords stored only in encrypted form (hash) and limits on login attempts;
- files on Amazon S3 in the European Union, served through time-limited signed links;
- technical logs with shortened personal data, and an administrators' access log that cannot be altered for 6 months;
- every support access to an account logged and visible to the host;
- guides kept out of search engines and without cookies.
T,
'dpa.7.titolo' => 'Sub-processors',
'dpa.7.testo' => <<<'T'
The Controller authorises the Processor to use the sub-processors listed in the {link_privacy} ("Recipients and providers"): Hostinger (hosting), Amazon Web Services (storage and translations), the outgoing email provider. The Processor imposes on them, by contract, the same obligations as this agreement. Before adding or replacing one, it notifies the Controller at least 30 days in advance: if the Controller objects on reasonable grounds, they may terminate the contract without penalty.
T,
'dpa.8.titolo' => 'Assistance to the Controller',
'dpa.8.testo' => <<<'T'
The Processor helps the Controller respond to data subjects' requests (access, rectification, erasure…), first of all through the dashboard features, and comply with the obligations of arts. 32–36 GDPR, as far as it is concerned. If it receives a data subject's request addressed to the Controller, it forwards it.
T,
'dpa.9.titolo' => 'Personal data breaches',
'dpa.9.testo' => <<<'T'
If it becomes aware of a personal data breach affecting the Controller, the Processor notifies them **within 48 hours**, at the account email, with the information available (what happened, which data, which measures), and keeps them updated.
T,
'dpa.10.titolo' => 'End of the contract',
'dpa.10.testo' => <<<'T'
At the end of the contract the Controller may ask for a copy of their content by writing to {email}; the Processor deletes the data within 30 days of the account being closed, except those it must keep by law, and backups at their natural expiry.
T,
'dpa.11.titolo' => 'Audits',
'dpa.11.testo' => <<<'T'
On written request and with reasonable notice, the Processor makes available to the Controller the information needed to demonstrate compliance with this agreement (documentary audit). Any inspection is agreed in writing.
T,
'dpa.12.titolo' => 'Contact details',
'dpa.12.testo' => <<<'T'
Processor: {titolare_completo}, {sede}, Italy. Email {email}.
T,

// ================================================================ UPDATES (the dashboard window and the email)
'legale.novita' => <<<'T'
“Review with a discount”: the 5% applies only once. If you join at checkout (or before your first payment), on the first year; if you join later, on the renewal following your review (rating and comment). The logo you upload appears as it is on the home-page card.
Partner programme: with a partner's code you get a discount on the first year; the partner receives a commission on your payments, without your personal data.
A change of plan proposed by us with a discount: it applies only with your confirmation, from the link we send you.
Renewal discount, a single percentage: invitations and review add up and are applied once on the list price (up to 55%).
T,
],
];
