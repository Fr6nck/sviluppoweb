# Changelog — MyHouse Welcome

## v2 · Home con Casa Checco (6 ottobre 2026)

Nessuna migrazione.

- **Telefono della home.** Quando la demo è la vetrina, il telefono mostra Casa Checco: nome, foto (`assets/foto/checco-telefono.jpg` e `checco-telefono-600.webp`) e orario del check-in presi dalla demo. Senza vetrina resta Casa Lucia.
- **«Inizia in pochi minuti».** Le tre schermate del pannello (`pannello-1…3.webp`) sono rifatte dal pannello di Casa Checco: Contenuti, Aspetto con l'anteprima, QR & Link.

## v2 · Amministrazione → Impostazioni: Stripe, posta e foto dal pannello (6 ottobre 2026)

Nessuna migrazione.

- **Nuova pagina Impostazioni** (menu dell'amministrazione), con tre riquadri: Stripe, Posta in uscita, Archivio delle foto.
  - **Stripe:** chiave segreta, segreto del webhook, Stripe Tax. C'è anche l'indirizzo del webhook da copiare, con l'elenco degli eventi da selezionare.
  - **Posta:** SMTP, `mail()` o «non spedire»; server, porta, cifratura, utente, password, mittente.
  - **Archivio:** disco o Amazon S3; regione, bucket, chiavi, indirizzo pubblico.
- **Dove si salvano.** I valori vanno in `app/config.local.php`, il file che l'app già leggeva. È scritto con `var_export`, quindi un testo con apici resta testo. La versione precedente resta in `config.local.bak.php`, escluso dal repository e dal pacchetto.
- **Sicurezza.**
  - Per salvare serve la password dell'amministratore (al massimo 10 tentativi ogni 15 minuti).
  - I segreti non si rimostrano: si vede solo come finiscono. Un campo lasciato vuoto li conserva, «Togli» li cancella.
  - I campi impostati da variabili d'ambiente sono bloccati.
  - Nel registro va solo quale gruppo è stato salvato, mai i valori.
- **Controlli.** Ogni campo viene controllato prima del salvataggio, insieme alle regole tra campi:
  - niente SMTP senza server e mittente;
  - niente S3 senza regione, bucket e chiavi;
  - niente chiave Stripe senza segreto del webhook.
- **«Prova la connessione».**
  - Stripe: chiede il saldo e indica se la chiave è reale o di prova.
  - Posta: manda un'email di prova all'amministratore.
  - S3: scrive, legge e cancella un piccolo file.
- **Posta: errori spiegati.** Se l'email di prova non parte, «Prova la connessione» mostra la risposta del server di posta e cosa controllare:
  - utente o password rifiutati (535);
  - server o porta irraggiungibili;
  - cifratura (meglio SSL sulla 465);
  - mittente rifiutato.
- **Posta: connessione cifrata.** STARTTLS ora chiede esplicitamente TLS 1.2 o 1.3.
- **Niente riempimento automatico.** Il browser non riempie più da solo i campi delle impostazioni con le credenziali del pannello.
- **Quadro.** Gli avvisi «Stripe non è configurato», «La posta non parte» e «I media stanno sul disco» hanno ora il pulsante «Imposta ora».

## v2 · Vetrina «Casa Checco» con i dati veri di Assisi (6 ottobre 2026)

Nessuna migrazione. La casa, il vicolo, i padroni di casa, i telefoni (075 000 …) e i locali di «Dove mangiare» e «Negozi» restano di fantasia. Tutto il resto viene dalle fonti pubbliche del 2026:

- **Eventi.** Niente più eventi di fantasia. Ci sono il mercato del sabato in Piazza Matteotti (8–13) e le feste che tornano ogni anno, ognuna con la prossima data a partire dal giorno in cui si crea la vetrina:
  - Calendimaggio, dal primo mercoledì di maggio al sabato;
  - Festa del Perdono, 1–2 agosto;
  - Santa Chiara, 11 agosto;
  - San Rufino, 11–12 agosto;
  - Festa di San Francesco, 3–4 ottobre.
- **Locandina.** Ora è quella che la casa ha preparato per il mercato del sabato.
- **Imposta di soggiorno.** Tariffe 2026 per le locazioni turistiche: 3, 4 o 6 € a notte secondo il prezzo, solo le prime 3 notti, esenti i bambini sotto i 12 anni.
- **Parcheggi.** Tutti a 2 € l'ora e 14 € al giorno: Matteotti (390 posti), Mojano (con le scale mobili per Santa Chiara) e Porta Nuova. Gratuito: San Giacomo. ZTL: solo carico e scarico, al massimo 60 minuti, con il permesso.
- **Muoversi e arrivare.** Linea C di Busitalia dalla stazione al capolinea di Piazza Matteotti (1,30 € in tabaccheria, 1,50 € a bordo). Scale mobili di Mojano. Radio Taxi Assisi.
- **Cosa visitare.** 10 luoghi veri, con orari e prezzi:
  - anfiteatro romano, Cattedrale di San Rufino, Museo Diocesano, Santa Chiara, Piazza del Comune;
  - Foro Romano (5 €), Rocca Maggiore (8 €; cumulativo 10 €);
  - Basilica di San Francesco, San Damiano, Santa Maria degli Angeli.
- **Cosa fare.** Sentiero 350 per l'Eremo delle Carceri, Bosco di San Francesco del FAI, prati del Subasio, ciclovia Assisi–Spoleto.
- **Luoghi.** 23 in tutto, con le descrizioni anche in inglese.

## v2 · Fatture con Adamo dal collegamento Stripe, vetrina «Casa Checco» ad Assisi (6 ottobre 2026)

Nessuna migrazione.

- **Adamo.** Le fatture le crea Adamo dal suo collegamento con Stripe (Impostazioni → Integrazioni → Stripe), senza token: la fase 6F non serve.
  - Il cliente Stripe porta ora i metadati che Adamo legge: `Fiscal_code` (per un'azienda senza codice fiscale, la partita IVA), `Pec` e `Fe_code` (il codice destinatario, oppure `0000000`).
  - La partita IVA di un'azienda va anche come «tax id» del cliente.
- **Vetrina «Casa Checco».** «Crea la guida vetrina» crea ora «Casa Checco», ad Assisi, in un vicolo di fantasia vicino a Piazza Matteotti. La struttura è geolocalizzata sulla piazza (43.07025, 12.61966, da OpenStreetMap), con il link di Maps e le coordinate; luoghi, parcheggi e indicazioni sono quelli di Assisi.
  - Tutte le 17 sezioni sono compilate, comprese le nuove: eventi di fantasia con una locandina disegnata, sezione libera «La storia della casa», parcheggi con prezzi e minuti a piedi, muoversi in zona, servizi extra con unità, due reti Wi-Fi, istruzioni, imposta di soggiorno, 16 luoghi.
  - Le foto sono nuove versioni di quelle della demo: specchiate, ritagliate e con un'altra tonalità (`assets/foto/checco-*.jpg`).

## v2 · Fase 6E — Codici sconto per il primo anno, con Stripe (6 ottobre 2026)

Migrazione `018`: le tabelle `discount_codes` e `discount_redemptions`, la colonna `accounts.intended_discount_code_id` e le colonne `orders.discount_code_id` e `orders.discount_cents`. Nuova classe `Sconti`.

- **Amministrazione → Codici sconto.**
  - Il modulo ha codice (con «Genera»), percentuale o importo, validità, utilizzi massimi, piani e nota, con l'anteprima del prezzo per ogni piano.
  - Ogni codice diventa un coupon Stripe «una volta», che sconta solo il primo anno.
  - L'elenco mostra stato, utilizzi e «Copia il link»; dal dettaglio si vede chi l'ha usato. Disattivazione e creazione vanno nel registro.
- **Per l'host.**
  - «Hai un codice sconto?» compare nel riquadro del piano, al passo «Pubblica» e su `/piano`. Mostra il prezzo barrato e lo sconto del primo anno, oppure il motivo per cui il codice non vale.
  - Il codice si ricontrolla alla pubblicazione. Lo sconto vero lo dice Stripe e si conta una volta sola anche se il webhook arriva due volte.
- **Link con il codice.** `/?codice=…` mostra una fascia in home e applica il codice dopo la registrazione. In «Account & Fatturazione» compare lo sconto avuto e la data del rinnovo a prezzo pieno.

## v2 · Fase 6G — Sezione «Eventi» con locandina, calendario e promemoria (5 ottobre 2026)

Nessuna migrazione. Nuovi file: `app/src/Eventi.php` (la logica delle date, già pronta) e `app/lang/eventi/` (5 lingue).

- **Nuova sezione `events`.**
  - Ogni evento ha nome, categoria, «Quando» a pillole (un giorno, più giorni, ogni settimana, altro), date, orari, luogo con i minuti, prezzo, sito e descrizione.
  - La locandina è una zona sola, per un'immagine o un PDF.
  - I campi si mostrano e si nascondono secondo «Quando» e «Prezzo». Con «Un giorno», «Dal» diventa «Giorno».
- **Pannello.**
  - Ogni evento ha il suo stato: In corso, Tra N giorni, Ricorrente, Passato: nascosto, Manca la data.
  - Per un evento passato che torna ogni anno c'è «Ripeti nel 2027»: sposta le date e toglie la locandina.
  - Una volta al mese al massimo, un'email ricorda gli eventi passati (si può disattivare).
- **Guida.**
  - Gli eventi si dividono in gruppi «In questi giorni», «Più avanti» e «Ogni settimana», con i filtri per categoria. Quelli passati spariscono da soli: le date si guardano quando la pagina si apre.
  - Ogni scheda si apre e mostra locandina, Indicazioni, «Aggiungi al calendario» (file `.ics`) e il sito.
  - In home c'è la fascia scura «Oggi» se c'è un evento oggi. La casella Eventi compare solo se c'è qualcosa nei prossimi 60 giorni, con il numero di quelli in questi giorni.
  - La demo Casa Lucia ha tre eventi di esempio.

## v2 · Fase 6D — Sezione libera, righe compresse, messaggio di benvenuto (5 ottobre 2026)

Nessuna migrazione.

- **S1 · Sezione libera (`custom`).**
  - Contiene il titolo scelto dall'host, un'icona fra 12, testo, elenco, foto e PDF.
  - Si aggiunge più volte: il catalogo la propone sempre.
  - Si copia tra strutture, con una casella sola per tutte le sezioni libere.
  - Nella home pubblica non si conta fra le sezioni pronte.
  - Senza titolo, la guida mostra «Altre informazioni».
- **X2 · Righe compresse.**
  - In tutti i ripetitori le righe salvate si mostrano su una linea di riepilogo, che non mostra mai la password del Wi-Fi. Esempio: «Lucia, per i problemi in casa · +39 0742 000000 · Dalle 8 alle 22».
  - Si aprono con un clic; la riga nuova nasce aperta.
  - Un campo non valido apre la sua riga. Senza JavaScript le righe restano aperte.
- **M2 · Messaggio di benvenuto in «QR & Link».**
  - Una versione per ogni lingua della guida, con il link che apre la guida in quella lingua.
  - Si può ritoccare prima di usarlo, con «Copia» e «Apri WhatsApp».
  - Va bene per WhatsApp, Airbnb e Booking.
- **Parole nuove da far rileggere.**
  - `kind.custom`: «Altre informazioni» / «More information» / «Autres informations» / «Weitere Informationen» / «Más información».
  - `welcome_message`: il messaggio di benvenuto nelle 5 lingue, in `app/lang/*.php`.

## v2 · Sito: chi è già registrato lo vede subito; guida vetrina (5 ottobre 2026)

Nessuna migrazione.

- **Testata del sito.**
  - Chi è dentro vede la sua iniziale e il nome. Si apre un menu con l'indirizzo email, «Le mie guide», «Account & Fatturazione» ed «Esci»; si chiude toccando fuori o con Esc.
  - Sul telefono l'iniziale resta in testata. Chi è fuori ha «Accedi» in testata, non solo nel menu.
- **Home per chi è registrato.**
  - In cima c'è la fascia «Ciao, Nome.», con l'indirizzo con cui sei dentro.
  - Mostra fino a tre guide con lo stato vero (Online, Offline, Bozza) e «Vai alle tue guide». Senza guide propone «Crea la tua prima guida».
  - I pulsanti dell'hero e della chiusura diventano «Vai alle tue guide».
- **Guida vetrina.**
  - In Amministrazione → cliente, «Crea la guida vetrina» crea «Casa dei Gerani», una demo completa nell'account di un cliente vero, con dati di fantasia diversi da Casa Lucia.
  - Si può concedere insieme Plus dimostrativo per 12 mesi.
  - La vetrina è online come demo, non occupa il posto di una struttura del piano e diventa la demo della landing.

## v2 · Fase 6C — Parcheggio, prezzi, «Muoversi in zona», home con tutte le sezioni (3 ottobre 2026)

Migrazione `017`. Converte i dati esistenti; i vecchi campi restano nel JSON. Le guide già pubblicate si leggono nel formato 6 (`Guide::FORMAT`).

- **Importi in euro (`money`).** Si scrive solo il numero, con «€» fisso a destra. Accetta virgola o punto; si salva con la virgola («1,50») ed è uguale in ogni lingua.
- **Parcheggio.**
  - Il tipo si sceglie a pillole.
  - Campi: «Nome o descrizione», indirizzo, link a Maps, «All'ora», «Al giorno», «Nota sul costo» (tradotta), «Minuti a piedi».
  - Con «Privato» o «Pubblico gratuito» i costi spariscono e si svuotano.
  - Nella guida ogni parcheggio è una scheda con le pillole «€/ora», «€/giorno», «min a piedi» (o «Gratuito») e «Apri Maps».
- **Servizi extra.** Il prezzo diventa importo e unità (a persona, a tratta, a notte…), più «Nota sul prezzo». Nella guida si legge «25 € · a tratta», con l'unità tradotta.
- **«Come arrivare» e «Muoversi in zona».**
  - Ognuna ha un riquadro introduttivo nell'editor, che rimanda all'altra.
  - Nella guida ognuna ha una riga sotto il titolo.
  - «Muoversi in zona» è a schede: tipo a pillole, nome, telefono, sito, «Dove si prende», «Orari, biglietti, costi». Ci sono le righe pronte Taxi, Autobus e Noleggio bici. Nella guida ogni scheda ha «Chiama» e «Visita il sito».
- **Conversione (017).**
  - «5 € al giorno» e «€1,50/h» diventano importi; il resto del testo va nella nota.
  - «25 € a tratta» diventa importo e unità.
  - Il vecchio elenco dei trasporti diventa schede di tipo «Altro».
  - Quello che non si riconosce resta intero nella nota, in ogni lingua.
- **Home.**
  - «Cosa trova l'ospite» mostra tutte le sezioni del catalogo in tre gruppi: «La casa», «Arrivare e muoversi», «Il territorio».
  - Il numero delle sezioni è contato dal catalogo («15 sezioni pronte»).
  - A 390 px le sezioni stanno su due colonne compatte.
- **Demo.** I prezzi degli extra sono nel formato nuovo. Casa Lucia ha «Muoversi in zona», senza nomi di aziende.

## v2 · Fase 6B — Luoghi con categorie ed etichette tradotte, «Negozi e spesa» (3 ottobre 2026)

Migrazione `016` (`places.category_key`, `places.badge_key`), che converte i dati esistenti.

- **Categorie ed etichette come chiavi, tradotte da sole.**
  - Nella scheda del luogo, la categoria si sceglie da pillole con le voci di quella sezione (in «Cosa visitare» non c'è la ristorazione), più «Altro…» per il testo libero.
  - «Etichetta» diventa «In evidenza», con «Nessuna», le voci della sezione e «Personalizzata…».
  - Nella guida, nei filtri e nelle traduzioni la voce scelta compare nella lingua dell'ospite; per tradurla non serve niente («Tradotta automaticamente»).
  - Il segnaposto di «Perché lo consigli» cambia con la sezione.
- **Conversione.** La 016 riconosce i testi già scritti (in qualunque lingua) e li trasforma in chiavi; quelli non riconosciuti restano come sono. La copia tra strutture porta le chiavi.
- **Nuova sezione «Negozi e spesa»** (`shop`): alimentari, forno, mercato, farmacia, bancomat… Ha una sua icona e conta nel limite del piano come le altre. Nella demo ci sono due negozi di fantasia.

## v2 · Fase 6A — Campi in linea, silenzio, dotazioni, tipologia (3 ottobre 2026)

Migrazione `015` (`properties.property_type_other`).

- **Righe in linea (tutti i ripetitori).**
  - In alto maniglia, nome e numero («Parcheggio 1»), poi su / giù / togli in fila.
  - Sotto, i campi su una griglia di 12 colonne, con la larghezza scritta nel catalogo; sotto i 640 px un campo per riga.
  - L'aiuto sta sotto il campo. La foto mostra miniatura, «Sostituisci» e «Togli» su una linea.
  - La password del Wi-Fi ha «Mostra»; la «Zona» compare solo con due o più reti.
  - Niente compilazione automatica dei gestori di password nelle righe.
- **Orario del silenzio con interruttore.** Acceso: «Dalle» e «Alle» sulla stessa riga, precompilati 22:00 e 08:00. Spento: gli orari si svuotano e la guida non lo mostra. Le sezioni salvate prima valgono accese se c'era un orario.
- **Dotazioni.** 35 voci in sei gruppi, dalle tassonomie tradotte nelle 5 lingue (`app/lang/tassonomie/`, `Tassonomie.php`). «Le tue dotazioni» come pillole con «×» e «+ Aggiungi una dotazione»; nella guida, spuntate e scritte a mano stanno in un solo elenco.
- **Tipologia.** In più Appartamento e Villa o casale, niente più «Non indicata». Con «Altro» compare «Che tipo di struttura è?».

## v2 · La foto di Casa Lucia (1 ottobre 2026)

- Nuova copertina della demo (`assets/foto/casa.jpg`, fornita dal cliente): il portone in legno ad arco tra la pietra e i fiori. Si vede:
  - sulla schermata di benvenuto della guida (splash);
  - in testa alla guida;
  - nel telefono dell'hero della landing (`borgo-telefono.jpg` e la sua WebP, ritagliate dalla stessa foto).
- Il testo alternativo della copertina della demo è aggiornato.
- Sul server la demo già creata tiene la foto vecchia, che è salvata nello storage. Per vederla: **Amministrazione → Quadro → «Elimina i clienti di esempio», poi «Crea i clienti di esempio»**.

## v2 · Pannello ridisegnato (1 ottobre 2026)

Nessuna migrazione. Il telaio del pannello (`views/layout/cms.php`) vale per l'area host e per l'amministrazione.

- **Barra laterale scura**, al posto delle due barre orizzontali in alto:
  - le voci dell'account («Generale» per l'host, «Amministrazione» per lo staff), con l'icona e la voce attiva segnata (`aria-current`);
  - quando si lavora su una struttura, il suo gruppo: Contenuti, Lingue, Aspetto, QR & Link, Statistiche, Impostazioni, Anteprima, con il pallino dello stato (pubblicata o bozza);
  - in fondo l'utente, il tema e «Esci».
- **Tela chiara arrotondata** su fondo sabbia:
  - in alto le briciole (Le mie guide › Casa Lucia › Aspetto) e le azioni della guida, Anteprima e Pubblica;
  - l'intestazione resta visibile scorrendo.
- **Sul telefono:**
  - la barra diventa un cassetto. È un `<details>`, quindi funziona anche senza JavaScript, e si chiude con Esc, scegliendo una voce o toccando fuori;
  - le voci della guida diventano schede che scorrono sotto l'intestazione;
  - Anteprima diventa un'icona, con l'etichetta per i lettori di schermo.
- **«Vai al contenuto»** per chi usa la tastiera.
- **Le mie guide:**
  - il saluto («Ciao, Lucia.»);
  - tre cartellini pastello con numeri veri: guide online, da finire, il piano con il rinnovo;
  - le schede delle strutture con la foto, lo stato sopra la foto e le scorciatoie Contenuti, QR e Anteprima;
  - la scheda tratteggiata «Aggiungi una struttura».
- **Contenuti:** aperture, QR e sezione più letta su cartellini. Le aperture portano alle Statistiche.
- **Statistiche:** un anello QR / link con la legenda e il testo per chi non vede il grafico, più i cartellini dei totali.
- **Quadro dell'amministrazione:**
  - otto cartellini, e quelli che hanno una pagina portano lì;
  - gli ordini hanno lo stato in pillole colorate.
- I colori pastello derivano dalla tavolozza del progetto: rosa terracotta, mare, ocra, pino. Nel tema scuro diventano toni profondi.
- La procedura di una guida mai pubblicata e le pagine senza accesso restano senza barra, per non distrarre.
- Le schermate del pannello nella landing («Come funziona») sono rifatte con il pannello nuovo.
- `giro-completo.php`: 496 controlli.

## v2 · Landing: le foto delle scene (1 ottobre 2026)

- Le tre scene sotto l'hero hanno le loro foto (fornite dal cliente):
  - `scena-qr.jpg`: l'ospite inquadra il QR all'ingresso;
  - `scena-ospite.jpg`: la guida sul telefono;
  - `scena-host.jpg`: l'host aggiorna dal portatile.
  - Ogni foto è a 1200×750, con il testo alternativo e il centro scelto per la miniatura quadrata del telefono.
- **Versioni WebP più leggere:** `scena-…-1200.webp` e `scena-…-600.webp`, generate da `tools/foto.php` (anche da Diagnostica → «Rigenera le foto WebP»).
  - Il telefono scarica quella da 600 px (circa 30 KB invece di 120).
  - Una WebP più vecchia del suo .jpg non si usa: chi carica una foto nuova la vede subito, anche prima di rigenerare.
- Se una foto manca, al suo posto torna il disegno, come prima.
- `giro-completo.php`: 490 controlli.

## v2 · Landing: movimento e interazioni (1 ottobre 2026)

Nessuna migrazione. Cambiano `home.php`, `app.css`, `landing.js` e `prezzi.js`.

- **Comparse allo scorrimento** su tutta la home: ogni blocco entra salendo e mettendosi a fuoco, gli elementi di un gruppo uno dopo l'altro. Usa una sola curva per tutto e IntersectionObserver (nessun ascoltatore di scroll per le comparse).
- **Hero:** il telefono si inclina verso il puntatore (solo con mouse); le quattro sezioni nel telefono entrano una alla volta.
- **Il tempo che non vedi:** le domande arrivano una alla volta, la guida «scrive» e poi risponde.
- **La frase sul valore:** si colora parola per parola mentre la si legge.
- **Come funziona:** i passi avanzano da soli **un giro**, solo quando la sezione è in vista, con una barra di avanzamento sotto il passo. Si fermano per sempre al primo clic, tasto, passaggio del mouse o fuoco. La schermata entra morbida.
- **Il QR:** una linea di scansione passa due volte quando compare.
- **FAQ:** si aprono e si chiudono con un'animazione morbida (restano `<details>` veri).
- **Piani:**
  - le card si sollevano sotto il puntatore;
  - Portfolio ha i pulsanti − e + accanto al numero di strutture (con etichetta per i lettori di schermo, disattivati al minimo e al massimo), anche in `/piano`;
  - il prezzo fa un piccolo scatto quando cambia.
- **Scene:** ognuna porta dove se ne parla: il QR alla sezione del QR, l'ospite alla demo, l'host a «Come funziona».
- **Header:**
  - un'ombra quando la pagina scorre;
  - una barra di lettura in alto;
  - nel menu, la voce della sezione che si sta guardando (`aria-current`).
- **Pulsanti:** si «premono» (scala 0,98) e la freccia della CTA si sposta.
- **Sicurezze:**
  - con «riduci movimento» nel sistema non si anima niente;
  - senza JavaScript non si nasconde niente: le regole che nascondono valgono solo con `html.anima`, messa da uno script prima del disegno, così non c'è lampo;
  - se `landing.js` non arriva entro 3 secondi, la pagina torna ferma e completa.
- `giro-completo.php`: 488 controlli.

## v2 · Landing ridisegnata (1 ottobre 2026)

Nessuna migrazione. Si caricano solo file.

- **Stili sempre aggiornati.** Il foglio di stile e gli script si linkano con la data del file (`app.css?v=…`, funzione `av()` in `Support.php`). Dopo un caricamento via FTP il browser e la cache del server prendono subito i file nuovi. Prima potevano restare quelli vecchi, e la landing si vedeva senza stili: scene enormi, FAQ come elenco puntato, elenchi dei piani spezzati una parola per riga.
- **Hero.**
  - Il telefono non scende più sopra le scene.
  - «Guarda la demo» e le lingue IT / EN / DE stanno in un solo gruppo, accanto alla CTA, invece di sembrare un terzo bottone.
  - Le tre garanzie hanno la spunta.
- **Scene.**
  - Senza foto, al posto del riquadro piatto con l'icona c'è un disegno: la targa col QR, il telefono con le sezioni, il pannello con «Pubblica».
  - Le foto `scena-*.jpg`, quando ci sono, hanno la precedenza come prima.
  - Sul telefono le scene diventano righe con la miniatura: la pagina è circa il 10% più corta.
- **Cosa trova l'ospite.** Righe compatte con l'icona colorata, più il link per sfogliare la demo.
- **Il tempo che non vedi.** Da cinque blocchi a due colonne:
  - a sinistra problema e soluzione;
  - a destra le domande degli ospiti e la guida che risponde;
  - sotto, i due vantaggi e il valore dell'anno con il prezzo.
  - La frase «Il valore non è soltanto nella guida. È nel tempo che puoi dedicare ad altro.» ha ora uno spazio suo.
- **La guida che lavora per te.** Fascia scura, con il commiato disegnato usando le etichette vere della guida (recensioni e codice sconto).
- **Come funziona.**
  - Tre passi a sinistra e la schermata vera grande a destra, con schede accessibili da tastiera (`assets/landing.js`).
  - Senza JavaScript i passi sono link e le schermate si vedono tutte.
  - Le schermate sono rifatte più ingrandite, per essere leggibili.
- **FAQ.** Titolo a sinistra, con i pulsanti WhatsApp ed email per chi non trova la risposta (dal `config` → `legal`); domande a destra.
- **Piani.** «Confronta tutti i piani» è un bottone centrato.
- **Chiusura.** La CTA finale e «chi c'è dietro» (Blackout Agency) nella stessa fascia, invece di una nota sospesa a metà pagina.
- Menu del sito: in più c'è «Domande».
- Verificata a 390 e 1366 px, tema chiaro e scuro, senza scorrimento orizzontale.
- `giro-completo.php`: 484 controlli.

## v2 · Revisione finale (1 ottobre 2026)

Riletto da capo tutto quello che è cambiato nelle cinque fasi: permessi e blocchi, caricamenti, richieste in uscita, webhook, copia, email, uscite non protette nelle viste, SQL composto a mano. Corretto:

- **File della guida pubblicata.** Togliere dal pannello una foto, un PDF, una sezione o un luogo cancellava subito il file, anche se la guida online lo mostrava ancora: gli ospiti vedevano un'immagine rotta fino alla ripubblicazione. Ora:
  - il file resta finché la guida pubblicata lo usa (`Media::rilascia`);
  - alla pubblicazione successiva si tolgono i file che non usa più nessuno (`Media::pulisciOrfani`), solo se caricati da più di un'ora;
  - dentro la transazione del webhook non si cancella niente.
  - Prima della v2 queste foto rimanevano per sempre nello storage.
- **Link di Google Maps.** Un indirizzo come `https://altro.sito\@www.google.com/…` sembrava di Google al controllo, ma curl poteva portare altrove. Ora si rifiutano gli indirizzi con utente, password, porta, barra rovesciata, spazi o caratteri di controllo, e curl riceve l'indirizzo ricostruito dai pezzi già controllati. In più c'è un tetto di 120 letture all'ora per account.
- **Aggiungi una struttura (Portfolio):** il nome si controlla prima di aggiornare Stripe, così un nome troppo lungo non fa pagare una struttura che poi non nasce.
- **Email «quasi pronta»:** a chi ha già un abbonamento non dice più «paghi solo quando pubblichi».
- **Registro di amministrazione:** testimonianze e foto WebP non indicano più l'amministratore come «cliente interessato».
- Nessun altro problema trovato:
  - ogni rotta di una struttura passa dal controllo di proprietà e di blocco;
  - le uscite delle viste nuove sono protette;
  - l'SQL composto a mano contiene solo numeri interi o costanti.
- `giro-completo.php`: 473 controlli.

## v2 · Fase 5 — Vendita, crescita e demo in Umbria (1 ottobre 2026)

Due migrazioni (`013`, `014`), che partono da sole al primo accesso. Prima di caricare, copia `app/storage/`.

**K1 · Listino**
- Sotto ogni prezzo annuale c'è l'equivalente mensile («circa 9,75 € al mese», prezzo / 12). Nel Portfolio segue il numero di strutture (`prezzi.js`), in landing e in `/piano`.
- Essential perde le voci comuni a tutti i piani. Plus e Portfolio partono da «Tutto di Essential, e in più:» e non ripetono le voci già incluse.
  - Una voce che finisce con i due punti si mostra come titoletto.
  - I testi restano modificabili dall'admin. La migrazione 013 cambia solo i testi predefiniti.
- «Confronta tutti i piani»: una tabella chiusa sotto le card, generata dalle funzioni delle versioni in vendita.

**K2 · La guida fa guadagnare l'host**
- Nelle Impostazioni della struttura c'è il blocco «Dopo il soggiorno», tutto facoltativo:
  - link alle recensioni (Google, Booking.com, Airbnb, altro);
  - prenotazione diretta (link al sito e codice sconto).
- Nel commiato compaiono solo i dati compilati: «Ti è piaciuto il soggiorno?» con un pulsante per piattaforma, e «La prossima volta prenota da noi» con il codice da copiare.
- I link che non sono indirizzi web si scartano, con un avviso.
- Nuova sezione **Servizi extra**: righe con titolo, descrizione, prezzo e foto.
  - Ogni riga ha «Richiedi su WhatsApp» verso il primo contatto, con il messaggio già scritto nella lingua dell'ospite.
  - Si copia anche con «Crea da una struttura esistente».
- In landing c'è il blocco «Una prenotazione diretta in più all'anno paga l'abbonamento.»

**K3 · FAQ, demo, testimonianze**
- FAQ prima del listino, con accordion su `<details>` (tastiera e lettori di schermo). Sono sei domande, con le risposte prese da `LEGGIMI.md`.
- Accanto a «Guarda la demo» si sceglie la lingua: IT / EN / DE, solo quelle che la demo ha.
- Admin → Testimonianze: nome, struttura, testo, foto, visibile sì/no, ordine. La foto sta nello storage senza riga in `media`. In landing il blocco compare solo se c'è almeno una testimonianza visibile. Nessuna è inserita d'esempio.

**K4 · Firma nella guida**
- Nel piè di pagina: «Guida creata con MyHouse Welcome · Crea la tua», con link alla landing e `?ref=guida`.
- Nuova funzione di pacchetto `hide_branding`. La migrazione 013 crea una **versione nuova** di Plus e di Portfolio, uguale a quella in vendita più la funzione, come fa l'admin.
  - Le versioni già vendute non cambiano.
  - Chi aveva scelto Plus o Portfolio senza pagare passa alla versione nuova.
- Il server nasconde la firma solo se il piano lo comprende, anche se la richiesta viene costruita a mano.
- Istantanee della guida: formato 5. Quelle pubblicate prima mostrano la firma e nessun blocco del commiato.

**K5 · Email che riportano l'host a finire**
- I richiami partono:
  - dopo 1 giorno, se l'arrivo è vuoto;
  - dopo 3 giorni, se non ci sono sezioni;
  - dopo 7 giorni, se la guida non è pubblicata;
  - 30 giorni prima del rinnovo automatico, con le aperture dell'anno.
- Le finestre non si sovrappongono (1–3, 3–7, 7–21 giorni): una struttura riceve un richiamo per volta, e chi aggiorna con bozze vecchie non riceve una raffica.
- Niente richiami a strutture bloccate o di esempio.
- I testi sono brevi, in italiano, con un solo pulsante che porta al passo giusto. Ogni email ha anche la versione HTML.
- Tabelle nuove:
  - `email_log`: ogni email una volta sola;
  - `email_optout`: «non mandarmene più», per tipo, confermato con un bottone perché i programmi che aprono i link in anteprima non disiscrivano nessuno.
- Nessun cron obbligatorio: un controllo leggero gira dopo le pagine del pannello e della landing, al massimo ogni 15 minuti, con un file di blocco `storage/richiami.lock`. In più c'è `/cron/{token}` con `MHW_CRON_TOKEN`.

**K6 · Funnel senza cookie**
- Eventi anonimi in `analytics_events`: `landing_view` (i robot non contano), `signup`, `property_created`, `published` (la prima pubblicazione di una guida vera).
- `analytics_events.property_id` diventa facoltativo. Su SQLite la tabella si ricostruisce senza perdere un evento.
- Nel Quadro dell'admin: i quattro numeri degli ultimi 30 giorni e la percentuale di passaggio da un passo all'altro.

**V6 · Landing e demo in Umbria**
- Sotto l'hero, al posto della foto grande, c'è una fascia con tre scene (`scena-qr.jpg`, `scena-ospite.jpg`, `scena-host.jpg`). Le foto non ci sono ancora: ogni card mostra un riquadro colorato con l'icona e si riempie da sola quando carichi i file.
- In «Inizia in pochi minuti» ci sono tre schermate vere del pannello (`assets/foto/pannello-1…3.webp`, 1200×750): Contenuti, Aspetto con l'anteprima, QR & Link.
- Casa Lucia si sposta a **Spello (Umbria)**:
  - locali di fantasia (Osteria dell'Arco con gli strangozzi al tartufo, Bar della Fontana, Gelateria delle Rose);
  - telefono di esempio con il prefisso 0742;
  - una sezione Servizi extra d'esempio.
  - Vale per le installazioni nuove. Le demo già installate restano finché non le ricrei dall'admin.
- `app/tools/foto.php` rigenera con GD `borgo-1200.webp`, `borgo-2000.webp` e `borgo-telefono-600.webp` dai `.jpg`. Si avvia da riga di comando o dal bottone in Diagnostica.

**Prove**
- `giro-completo.php`: 470 controlli (35 nuovi). `aggiornamento.php`: superata da `be97f4a` (la v1 della revisione), `cb6d0dc`, `d6edc08`, `8f27f4f`, `14a9c6e`, `1562635`, `74893f6`.
- Nel browser, a 390 e 1366 px: landing (listino, confronto, FAQ da tastiera, scene, piè di pagina), commiato di Casa Lucia con e senza recensioni, firma.

## v2 · Fase 4 — Portfolio: una struttura prima di pagare, poi copia (1 ottobre 2026)

Nessuna migrazione: il blocco si calcola dai dati che ci sono già (piano scelto, abbonamento, quantità, ordine di creazione).

**Una struttura completa prima del pagamento**
- Con il Portfolio scelto e mai pagato si configura una struttura sola, la prima. Le altre, fino alla quantità scelta, si creano col solo nome e compaiono come card bloccate: «Si attiva dopo il pagamento». Restano eliminabili.
- Il blocco sta sul server: `Entitlements::lockedIds()` ed `editable()`, controllati da tutte le rotte di una struttura. Questo vale anche per gli URL scritti a mano: pagine, salvataggi, sezioni, luoghi, lingue, aspetto, QR, anteprima, pubblicazione, copia.
  - Risponde con un avviso e il rimando a «Le mie guide», non con un errore.
  - Il salvataggio automatico riceve 423 con lo stesso avviso.
- Chi ha già pagato in passato e ora è scaduto non viene bloccato.

**Sblocco**
- Al pagamento (webhook firmato di Stripe, o abbonamento manuale dall'amministrazione) si sbloccano tutte le strutture fino alla quantità pagata.
- «Aggiungi una struttura» oltre la quantità pagata apre una conferma col costo:
  - 60 € + IVA l'anno;
  - circa quanto per la parte dell'anno che resta;
  - il totale dal rinnovo.
- Confermato, la voce delle strutture aggiuntive su Stripe sale di uno con `proration_behavior=create_prorations`. La struttura nasce bloccata e si sblocca quando il webhook conferma la quantità nuova.
- Se Stripe non è configurato, o se l'abbonamento è manuale, compare un messaggio chiaro e non si crea niente.
- Il cambio del numero di strutture da Account & Fatturazione resta com'era: conguaglio fatturato subito (`always_invoice`), applicato solo a pagamento riuscito.

**Crea da una struttura esistente / Copia sezioni da…** (`app/src/Copia.php`)
- Struttura nuova: scelta facoltativa della struttura di origine e di cosa copiare.
  - Già spuntati: rifiuti, dove mangiare, cosa visitare, cosa fare, trasporti, emergenze, informazioni utili, regole, servizi, aspetto (palette, logo, tono del testo), contatti.
  - Non c'è una sezione «servizi extra» nel catalogo, quindi non compare.
- Mai copiati: indirizzo, CIN, reti Wi-Fi, passaggi di arrivo (Check-in & Check-out), «Come arrivare», foto di copertina, parcheggio.
- Si copiano anche:
  - traduzioni, luoghi e loro traduzioni;
  - le lingue della guida e la lingua principale.
- Immagini e PDF sono **duplicati** nello storage (disco o S3) con nomi nuovi: le foto della sezione, quelle dei luoghi, le foto e i PDF nelle righe e il logo. Eliminare una struttura non tocca l'altra.
- Su una struttura che esiste, «Copia sezioni da un'altra struttura»: per ogni sezione che c'è già si sceglie «Saltala» o «Sostituiscila». I file della sezione sostituita si cancellano a copia riuscita.
- Tutto in una transazione. Se qualcosa fallisce, anche solo per il limite di sezioni del piano, non resta niente a metà: i file già scritti nello storage si tolgono.
- Lo storage ha un metodo nuovo, `get()`, su disco e su S3.

**Predisposizione (solo commenti, in `Copia.php`)**
- Libreria dei luoghi a livello di account.
- Sezioni «collegate», che si aggiornano in tutte le guide.

**Demo**
- Marco (`marco@esempio.it` / `dimostrazione1`) è un Portfolio per 2 strutture **non ancora pagato**:
  - B&B Le Rondini è modificabile;
  - Casa sul Mare c'è col solo nome ed è bloccata.
- Vale per le installazioni nuove. Le installazioni esistenti non cambiano.

**Prove**
- `giro-completo.php`: 435 controlli (35 nuovi):
  - Marco bloccato anche dagli URL scritti a mano;
  - sblocco dall'admin;
  - un Portfolio nuovo sbloccato dal webhook;
  - struttura in più con `create_prorations`;
  - copia completa da Casa Lucia con file duplicati e guide indipendenti;
  - «Copia sezioni da…» con saltare e sostituire;
  - copia annullata senza resti.
- `aggiornamento.php`: superata da `be97f4a`, `cb6d0dc`, `d6edc08`, `8f27f4f`, `14a9c6e` e `1562635`.
- Nel browser, a 390 e 1366 px: guide di Marco, avviso, «Crea da una struttura esistente», «Copia sezioni da…», conferma del costo e messaggio senza Stripe.

## v2 · Fase 3, blocco B — Sezioni strutturate, scheda luogo da Maps, fatturazione (1 ottobre 2026)

Due migrazioni (`011`, `012`), che partono da sole al primo accesso. Prima di caricare, copia `app/storage/`.

**Motore dei campi**
- Tipi nuovi: `checks` (più spunte da un elenco fisso), `toggles` (sì / no / non indicato, uno per voce) e `time` (un orario). Nelle righe ripetibili, anche `image` e `pdf`.
- Foto e PDF nelle righe: si caricano col bottone Salva. Il server controlla che il piano li comprenda (`Entitlements`), aggancia solo file di quella struttura e cancella quelli non più usati (riga tolta, «Togli»). Con la sezione si cancellano anche i suoi file. La pubblicazione segnala le foto e i PDF nelle righe se il piano non li comprende.
- Il bottone per scegliere il file è in italiano, non quello del browser.
- Le righe nuove ricevono subito il loro id nel browser, così i salvataggi automatici successivi le riconoscono.
- Istantanee della guida: formato 4. Le guide pubblicate prima si leggono nel formato nuovo, senza ripubblicarle.

**R5 · Sezioni strutturate** (migrazione 011)
- Emergenze: righe nome · telefono · nota, con un «Chiama» per riga. Righe pronte a un tocco, nella lingua della guida: 112, guardia medica, farmacia di turno, veterinario.
- Rifiuti: tipo, giorni della settimana, colore del bidone, dove si trova. In cima alla pagina «Oggi si butta: …», calcolato sul giorno di oggi in Italia (Europe/Rome).
- Servizi: griglia delle dotazioni con icone (lavatrice, asciugatrice, lavastoviglie, asciugacapelli, ferro, culla, seggiolone, aria condizionata, riscaldamento, TV, macchina del caffè, barbecue). Istruzioni ripetibili con titolo, passaggi, foto e PDF.
- Regole: fumo, animali, feste e visitatori (ammesso / non ammesso / non indicato), orari del silenzio, regole aggiuntive.
- Parcheggio: più possibilità (tipo, indirizzo, link Maps, costo, istruzioni, foto) e il campo ZTL.
- Come arrivare: una scheda per mezzo (auto, treno, aereo, autobus), ognuna con i suoi passaggi.
- Conversione dei dati di prima, con le vecchie chiavi lasciate nel JSON:
  - le voci «una per riga» delle emergenze diventano righe, con il telefono separato dal nome («Guardia medica: 075 123456 (notti)» diventa nome, telefono e nota);
  - le voci dei rifiuti diventano righe col testo intero nella descrizione. Il tipo si riconosce solo se è uno solo; i giorni scritti per esteso si riconoscono sempre;
  - il parcheggio unico diventa la prima riga;
  - i passaggi di «Come arrivare» diventano la prima scheda;
  - le altre lingue si allineano riga per riga a quella principale;
  - servizi e regole tengono la loro lista come «Altre dotazioni» e «Regole aggiuntive».
- 65 etichette nuove nella guida ospite, in tutte e 5 le lingue.

**R6 · Scheda luogo**
- Il primo campo è «Incolla il link di Google Maps». Il server segue i link brevi (`maps.app.goo.gl`): massimo 5 secondi, solo verso indirizzi Google, passando anche dalla pagina del consenso. Dal link prende il nome (`/place/<nome>/`) e le coordinate (`!3d…!4d…`, `@lat,lng`, `q=lat,lng`). Se non riesce, nessun errore.
- Sempre visibili: link, nome, categoria, «Perché lo consigli», etichetta. Il resto sta in «Altri dettagli», chiuso.
- Minuti a piedi stimati: distanza in linea d'aria × 1,3, a 4,5 km/h, con l'indicazione «stima, modificabile». Servono le coordinate della casa, che si prendono dal link Maps di «Come arrivare». Nessuna API a pagamento.
- Colonne `places.lat` e `places.lng` (migrazione 011).

**R7 · Fatturazione italiana** (migrazione 012)
- Account & Fatturazione ha i dati di fatturazione:
  - tipo: azienda o professionista, oppure privato;
  - intestatario;
  - partita IVA, controllata con la cifra di controllo;
  - codice fiscale: 16 caratteri col carattere di controllo, oppure 11 cifre per le società;
  - codice destinatario SDI (7 caratteri) o PEC;
  - indirizzo, CAP, città, provincia.
- Gli errori sono indicati campo per campo (`aria-invalid` e messaggio collegato al campo).
- Sono obbligatori prima del primo pagamento: «Pubblica» porta alla scheda e, salvati i dati, riporta alla pubblicazione.
- I dati vanno al cliente Stripe come intestatario e indirizzo, più i metadati `vat`, `cf`, `sdi`, `pec`. Se il cliente esiste già, viene aggiornato. L'applicazione non genera fatture.

**Prove**
- `giro-completo.php`: 400 controlli (40 nuovi).
- `aggiornamento.php`: emergenze, rifiuti, parcheggio e «Come arrivare» nel formato di prima vengono convertiti senza perdere testo, in italiano e in inglese, e si vedono nell'anteprima. Superata partendo da `be97f4a`, `cb6d0dc`, `d6edc08`, `8f27f4f` e `14a9c6e` (blocco A).
- Verifica nel browser a 390 e 1366 px:
  - righe aggiunte, spostate e tolte da tastiera;
  - righe pronte delle emergenze;
  - foto in una riga;
  - nome del luogo preso dal link;
  - errori della fatturazione;
  - guida demo in italiano e in inglese, senza scorrimento orizzontale.

## v2 · Fase 3, blocco A — Struttura e contatti, arrivo e partenza, Wi-Fi (1 ottobre 2026)

Tre migrazioni (`008`, `009`, `010`), che partono da sole al primo accesso. Prima di caricare, copia `app/storage/`.

**Motore dei campi**
- Due tipi nuovi nel catalogo: `repeater` (righe con sottocampi, che si aggiungono, si tolgono e si riordinano trascinando o con «Sposta su/giù» da tastiera) e `choice` (una scelta fissa, con etichette tradotte nei file `lang`).
- Nelle righe ripetibili i sottocampi uguali in ogni lingua (rete, password, telefono) stanno in `sections.data`, i testi in `section_translations.data`. Le due parti si uniscono con un **id di riga stabile**, quindi riordinare o togliere una riga non mescola le traduzioni.
- Aggiornati editor, pagina di traduzione (riga per riga), percentuali di traduzione e guida ospite (ripiego sulla lingua principale riga per riga).
- Nuova classe `Conversione`: un'unica conversione dal formato di prima, usata dalle migrazioni, dalle guide già pubblicate (lette al volo nel formato nuovo, senza ripubblicare né riscrivere le istantanee) e dai dati demo. È idempotente e non cancella niente: i vecchi campi restano nel JSON.
- Istantanee della guida: formato 3.

**R2 · Struttura e contatti** (migrazione 008)
- Campi nuovi, tutti facoltativi: tipologia, indirizzo, CAP, CIN, posti letto (e coordinate, per ora vuote).
- Contatti duplicabili, nella tabella nuova `property_contacts`: nome, ruolo (Host, Co-host, Pulizie e chiavi, Manutenzione, Altro), telefono, «risponde anche su WhatsApp». La migrazione copia nome, telefono e WhatsApp di prima. Se telefono e WhatsApp erano due numeri diversi, diventano due righe: nessun numero si perde. Le vecchie colonne restano e restano allineate al primo contatto.
- Guida ospite: la barra in fondo diventa «Contatta {nome}» e apre un foglio con tutti i contatti (Chiama / WhatsApp). Il CIN compare in piccolo nel piè di pagina.
- L'indirizzo della struttura precompila «Come arrivare». Se manca il link Maps, la guida ne genera uno di ricerca dall'indirizzo.

**R3 · Arrivo e partenza** (migrazione 009)
- Arrivo: modalità (self check-in, accoglienza, cassetta delle chiavi), passaggi, arrivo tardivo, documenti da mostrare, imposta di soggiorno (importo per notte, notti massime, esenzioni e pagamento).
- Partenza: le cinque caselle fisse (chiavi, rifiuti, luci, clima, finestre) diventano la lista ordinabile «Prima di partire», con suggerimenti a un tocco nella lingua della guida (Chiavi, Rifiuti, Luci, Clima, Finestre, Lavastoviglie, Asciugamani). La migrazione converte lingua per lingua, nello stesso ordine, con l'etichetta («Chiavi: …»). Aggiornato anche il congedo.

**R4 · Wi-Fi** (migrazione 010)
- Più reti: zona, nome della rete, password. La rete di prima diventa la prima riga.
- Nella guida, per ogni rete: «Copia password» e un **QR Wi-Fi** (`WIFI:T:WPA;S:…;P:…;;`, con `\ ; , : "` protetti), generato con `Qr.php` dentro la pagina, senza richieste in più.

**Correzioni trovate nelle prove d'aggiornamento**
- La migrazione 005 ripubblica la demo mentre le migrazioni stanno ancora girando. Ora `Guide::build` converte da sé i contenuti e legge i contatti dalle colonne vecchie se la tabella nuova non c'è ancora.

**Database**
- Le migrazioni nuove scelgono la sintassi giusta per SQLite e per MySQL. L'installazione da zero su MySQL resta **non supportata**: lo schema iniziale (`001`) usa sintassi solo SQLite. Si usa SQLite, come oggi. Da sistemare in un passo a parte, con un MySQL di prova.

**Prove**
- `giro-completo.php`: 360 controlli (19 nuovi per il blocco A).
- `aggiornamento.php` controlla che nessun testo, traduzione, dato comune, luogo, foto o numero di telefono si perda, e che guida, congedo e Wi-Fi della demo già pubblicata si leggano nel formato nuovo. Superata partendo da `be97f4a` (la «v1» della revisione), `cb6d0dc` (Fase 2), `d6edc08` e `8f27f4f`.

## v2 · Fase 2 — Il piano una sola volta, procedura in 5 passi (30 settembre 2026)

Una migrazione: `007_passi_procedura.php` (parte da sola al primo accesso dopo l'aggiornamento).

**Piano e registrazione**
- U1 · Chi arriva da «Crea gratis con Plus» (o Portfolio con il numero di strutture) si registra e va **dritto** al nome della struttura: il piano si salva su `accounts.intended_package_version_id` e `intended_quantity` già alla registrazione. Su «Nuova struttura» compare «Piano {nome} scelto · non paghi adesso · Cambia».
- U1 · `/piano` resta per chi si registra da «Crea gratis» generico o preme «Cambia»: card intere cliccabili (il pallino è il radio vero), senza la riga che ripeteva nome e prezzo; nel Portfolio il numero di strutture sta dentro la card e scriverlo sceglie il Portfolio.
- R1 · Registrazione: chip con il piano scelto e «cambia»; «Nome e cognome»; password con «Mostra» e barra di robustezza; una sola casella obbligatoria, «Accetto i Termini e condizioni»; la privacy è una riga sotto il bottone. `Auth::recordConsent` salva ancora versione e data di entrambi i documenti. Formulazione da far verificare al consulente privacy (annotato nel codice).

**Procedura**
- U3 · Da 7 a 5 passi: Struttura e contatti (con «In che lingua scrivi la guida?»), Arrivo e partenza, Sezioni, Aspetto, Anteprima e pubblica.
- U3 · Sezioni: «Aggiungi» attiva la sezione e apre subito il suo editor sotto la card, con lo stesso modulo della pagina della sezione (nuovo `views/host/_sezione_editor.php`, condiviso); salvataggi, luoghi e azioni riportano alla procedura con la sezione aperta.
- U3 · Le lingue in più sono un riquadro facoltativo in fondo ad «Anteprima e pubblica». Le traduzioni non bloccano mai la pubblicazione.
- U3 · Tab Lingue: percentuale per lingua, campo per campo («English 60%»), e nella pagina di traduzione i campi mancanti sono evidenziati («Da tradurre»).
- U3 · Guida ospite: un campo non tradotto mostra il testo della lingua principale **campo per campo** (prima, una sezione tradotta a metà perdeva i campi non tradotti: corretto `Guide::tdata`).
- U3 · I vecchi indirizzi `/procedura/{checkin|contenuti|lingue|anteprima}` fanno un redirect 301 al passo nuovo. Migrazione 007: `checkin → arrivo`, `contenuti → sezioni`, `lingue → aspetto`, `anteprima → pubblica` (un solo `UPDATE … CASE`, valido su SQLite e MySQL).
- U2 · Durante la procedura di una guida mai pubblicata si vede una sola navigazione: i passi e «Esci, continuo dopo». Niente tab della struttura né menu dell'account; la verifica email è una riga compatta sopra i passi. Sul telefono i passi stanno in una riga.

**Prove**
- `giro-completo.php`: 25 controlli nuovi (registrazione, piano una volta, card dei piani, 5 passi, redirect 301, editor nella procedura, lingua principale, percentuali e ripiego delle traduzioni). `aggiornamento.php`: migrazione 007 da una struttura ferma a «contenuti».

## v2 · Fase 1 — Rifiniture visive e dati aziendali (30 settembre 2026)

Nessuna migrazione del database: si aggiorna copiando i file, `app/storage/` e `config.local.php` restano quelli del server.

**Moduli e controlli**
- V1 · Tutti i campi hanno lo stile del sito: i selettori includono anche i campi senza `type`, `time`, `date`, `url` e `datetime-local`, e ogni `<input>` dei modelli ha il suo `type`.
- V2 · Nuova zona di caricamento file (`views/host/_carica.php`): miniatura, nome e peso del file, «Sostituisci» / «Rimuovi», «Trascina qui o scegli un file»; trascinamento in `assets/cms.js`. L'input vero resta, nascosto alla vista ma raggiungibile da tastiera. Usata per copertina, logo, immagine profilo, immagine e PDF delle sezioni, foto dei luoghi.
- V2 · Caselle e pallini disegnati sopra gli input veri, in terracotta. Lingue, «Testo scuro / Testo chiaro» e «Colore dell'etichetta» diventano schede selezionabili (bordo terracotta e spunta).

**Procedura e pannello**
- U4 · Stessa barra azioni in tutti i passi, fissa in basso (sul telefono sotto il pollice): «← Indietro», stato «✓ Salvato» del salvataggio automatico, «Salva e continua →». «Continua dopo» è in alto accanto al nome della struttura. Struttura, Lingue e Aspetto ora salvano anche mentre si modifica.
- U5 · Sezioni e luoghi: righe tutte alte uguali, maniglia per trascinare e riordinare (usa le rotte «su/giù» esistenti), menu `⋯` con Modifica / Sposta su / Sposta giù / Disattiva (sezioni) / Elimina, utilizzabile da tastiera e telefono.
- V8 · Amministrazione pacchetti: funzioni come etichette («4 sezioni · 2 lingue · Logo · Copertina · 1 struttura»); Portfolio 2 e 3 in fondo, nel blocco chiuso «Piani nascosti».

**Marchio e sito pubblico**
- V3 · Un'icona per sezione, uguale in landing, pannello e guida: segnaposto (Come arrivare), autobus (Trasporti), auto (Parcheggio), cestino (Rifiuti), monumento (Cosa visitare), bussola (Cosa fare), documento (Regole), lavatrice (Servizi).
- V5 · Il simbolo arco + punto è il logo dell'header del sito, del pannello e del piè di pagina; nella guida senza logo dell'host prende il posto delle iniziali. Nuovi `assets/favicon.svg`, `assets/apple-touch-icon.png` (180 px) e `assets/og.jpg` (1200×630), generati con GD da `strumenti/marchio.php`; tag `og:` nelle pagine pubbliche (mai nella guida degli ospiti).
- U6 · Header del sito su telefono in una riga: logo, «Crea gratis» e menu (Come funziona, Piani, Il QR, Accedi, tema).
- K7 · Nuovi valori in `config.php → legal`: `company_vat` (`MHW_COMPANY_VAT`), `contact_phone` (`MHW_CONTACT_PHONE`), `contact_whatsapp` (`MHW_CONTACT_WHATSAPP`), `company_city` (`MHW_COMPANY_CITY`). Piè di pagina su due colonne con P.IVA, telefono (`tel:`), WhatsApp (`wa.me`), email, Termini e Privacy; un valore vuoto non compare. Termini e Privacy usano gli stessi dati al posto dei segnaposto.

**Guida degli ospiti**
- V4 · Schermata di benvenuto: due gradienti (scuro in alto, più scuro in basso) al posto del velo. Correzione: una regola della grana azzerava il velo, che quindi non c'era affatto. «Entra» usa il terracotta del marchio; leggibile anche con una foto quasi bianca.
- V7 · Riquadri in proporzione 4:3 e copertina più bassa sul telefono: a 390 px entrano quattro riquadri interi sopra la barra «Chiama / WhatsApp»; con la tastiera il riquadro a fuoco non finisce sotto la barra.

**Dati aziendali confermati** (valori predefiniti in `config.php → legal`)
- Ragione sociale `Blackout Agency`, P.IVA `02945910541`, sede `Via Ariodante Fabretti 17, Perugia`, email `info@myhousewelcome.it`, telefono e WhatsApp `+39 392 006 1600`.

**Prove**
- `app/prove/giro-completo.php`: 13 controlli nuovi per la fase (piè di pagina, favicon e og, header del telefono, icone, `type` dei campi, simbolo nella guida, etichette dei pacchetti).
