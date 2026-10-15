# MyHouse Welcome v2 — da configurare

Cosa resta da fare a te (o a chi gestisce il server) prima di aprire al pubblico.
I dettagli tecnici sono in `app/LEGGIMI.md`. Le novità, fase per fase, sono in `CHANGELOG.md`.

---

## 1. Caricamento

1. Copia sul server **tutto `app/storage/`** e `config.local.php`, se c'è.
2. Carica il contenuto di `welcomebook/` sopra i file vecchi, **senza toccare `app/storage/`**.
3. Apri il sito una volta. Le migrazioni `007`–`041` partono da sole.
4. In **Amministrazione → Diagnostica** tutte le righe devono essere «OK».

Il database resta **SQLite**. Le migrazioni nuove sono scritte anche per MySQL, ma un'installazione da zero su MySQL non è supportata: lo schema iniziale (`001`) è solo per SQLite.

## 1b. L'applicazione fuori dalla cartella pubblica (consigliato)

Diagnostica dice «NO · Applicazione fuori dalla cartella pubblica» quando la cartella `app/` sta dentro la cartella del sito (per esempio `public_html/app/`). Se le righe sotto (database, config.php, posta, registro) sono «OK», i file sono già protetti da `.htaccess`. Spostarla fuori li toglie del tutto dal web, anche se un giorno `.htaccess` smettesse di funzionare. `index.php` trova l'applicazione da solo in tutte e due le disposizioni.

Prima: copia di sicurezza di tutta la cartella del sito, con `app/storage/` e `app/config.local.php`.

**Se nel pannello dell'hosting puoi scegliere la cartella del dominio** (cPanel → Domini → la «radice del documento»):
1. Crea una cartella fuori da `public_html`, per esempio `/home/TUOUTENTE/welcomemyhouse/`.
2. Sposta lì dentro **il contenuto** di `public_html/app/`: `config.php`, `config.local.php`, `src/`, `views/`, `lang/`, `migrations/`, `tools/`, `storage/` (database e foto compresi).
3. Crea `/home/TUOUTENTE/welcomemyhouse/public/` e sposta lì `index.php`, `assets/`, `.htaccess`, `web.config` e, se ci sono, `robots.txt`, `sitemap.xml`, `llms.txt`.
4. Nel pannello dell'hosting imposta la radice del documento di welcomemyhouse.it su `/home/TUOUTENTE/welcomemyhouse/public`.
5. Apri il sito e poi Diagnostica: «Applicazione fuori dalla cartella pubblica» diventa «OK».

**Se la cartella del dominio è fissa** (`public_html`): sposta il contenuto di `public_html/app/` direttamente nella cartella sopra, `/home/TUOUTENTE/`, accanto a `public_html`, e lascia in `public_html` solo `index.php`, `assets/`, `.htaccess`, `web.config` e i tre file per i motori. Funziona allo stesso modo, ma mescola i file dell'applicazione con quelli della tua cartella principale: la prima strada è più ordinata.

Per i prossimi aggiornamenti, con questa disposizione: il contenuto di `welcomebook/app/` dello zip va nella cartella dell'applicazione, il resto di `welcomebook/` nella cartella pubblica. Mai sovrascrivere `storage/` e `config.local.php`.

## 2. Variabili

Si impostano come variabili d'ambiente o in `app/config.local.php`. Il modello è `config.local.esempio.php`.

**Il modo più semplice per Stripe, posta e foto: Amministrazione → Impostazioni.** Inserisci i valori nei tre riquadri, conferma con la tua password e premi «Prova con questi dati»: prova senza salvare. Se va, premi «Salva». «Prova i dati salvati» prova quelli già in uso.
- I valori si salvano solo sul server, in `app/config.local.php`. La versione precedente resta in `config.local.bak.php`.
- I segreti non si rivedono più: si vede solo come finiscono.
- Serve che la cartella `app/` (o il file) sia scrivibile dal sito. Se non lo è, la pagina te lo dice.
- I campi già impostati come variabili d'ambiente si vedono ma non si cambiano da lì.
- Se scrivi `config.local.php` a mano, i commenti si perdono al primo salvataggio dal pannello.

| Variabile | Obbligatoria | Note |
|---|---|---|
| `MHW_BASE_URL` | **sì** | indirizzo pubblico senza barra finale (es. `https://myhousewelcome.it` o `https://blackout.in/welcomebook`). Finisce nei QR, nelle email e nei link «non mandarmene più». |
| `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET` | **sì**, per vendere | senza, i clienti preparano la guida ma non possono pagarla né aggiungere strutture |
| `STRIPE_AUTOMATIC_TAX` | da decidere col commercialista | `1` = calcolo dell'IVA con Stripe Tax (va attivato anche su Stripe) |
| `MAIL_TRANSPORT=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS`, `MAIL_ENCRYPTION`, `MAIL_FROM`, `MAIL_FROM_NAME` | **sì** | senza, verifica dell'email, password e richiami finiscono in `storage/logs/mail.log` |
| `MHW_STORAGE=s3`, `AWS_REGION`, `AWS_S3_BUCKET`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` | consigliata | l'utente IAM deve avere `s3:PutObject`, `s3:GetObject` e `s3:DeleteObject`. `GetObject` serve anche a copiare le foto tra strutture. |
| `AWS_S3_PUBLIC_URL` | no | un CDN davanti al bucket |
| `MHW_CRON_TOKEN` | no | una stringa lunga a caso. Attiva `/cron/IL_TOKEN`. |
| `MHW_GRACE_DAYS` | no | giorni online dopo un rinnovo non riuscito (predefinito 0) |
| `MHW_TERMS_VERSION`, `MHW_PRIVACY_VERSION` | quando cambi i testi | i consensi nuovi registrano la versione |
| `MHW_COMPANY`, `MHW_COMPANY_VAT`, `MHW_COMPANY_CITY`, `MHW_CONTACT_EMAIL`, `MHW_CONTACT_PHONE`, `MHW_CONTACT_WHATSAPP` | no | i valori predefiniti sono già quelli confermati di Blackout Agency |

**Se la posta non parte con «535 authentication failed».** Il server rifiuta utente o password. L'errore ora dice quale utente è stato usato, quanti caratteri ha la password e con quali metodi si è provato (LOGIN e PLAIN).
- L'utente è l'indirizzo completo della casella. La password è quella della casella, la stessa della webmail, non quella del pannello.
- Scrivila e premi «Prova con questi dati»: prova senza salvare. Prima la prova usava solo la password già salvata.
- Se la webmail entra e qui no, chiedi al fornitore della posta se l'invio SMTP è attivo per quella casella o se serve una «password per le app».

**Cron (facoltativo).** Le email di richiamo partono anche senza cron, perché il controllo si fa mentre qualcuno usa il sito. Se il sito è poco visitato, aggiungi in cPanel un cron ogni ora:

```
wget -q -O- https://TUODOMINIO/cron/IL_TOKEN >/dev/null
```

## 3. Stripe

- **Webhook** su `https://TUODOMINIO/webhook/stripe` (oppure `…/index.php/webhook/stripe`). Eventi da inviare:
  - `checkout.session.completed`
  - `checkout.session.expired`
  - `checkout.session.async_payment_succeeded` e `checkout.session.async_payment_failed` (nuovi: servono ai cambi di piano pagati con bonifico SEPA o altri metodi non immediati)
  - `invoice.paid`
  - `invoice.payment_failed`
  - `customer.subscription.updated`
  - `customer.subscription.deleted`
- Il webhook non serve solo a pubblicare: sblocca anche le strutture del Portfolio, comprese quelle aggiunte dopo.
- Attiva il **portale clienti** (fatture, carta, disdetta). Nel portale **spegni «Cambio di piano» e «Modifica quantità»**: il cambio si fa solo da Account → «Cambia piano», che chiede le scelte e calcola il conguaglio. Se lo lasci acceso, il cliente cambierebbe piano su Stripe senza passare da qui.
- **Cambio di piano (fase 6H).** Prima di aprirlo ai clienti, in modalità test:
  1. Un Plus passa a Portfolio con 2 strutture: la pagina di Stripe chiede solo la differenza per i giorni che restano. Dopo il pagamento, in Account il piano è Portfolio e la fattura del conguaglio è tra le fatture del cliente.
  2. Un Portfolio da 3 scende a 2: oggi non paga niente, sceglie la struttura da archiviare, in Account compare «Dal … passi a …» con «Annulla il cambio».
  3. Con un orologio di prova di Stripe (test clock) fai arrivare il rinnovo: la fattura è al prezzo nuovo e la struttura scelta è archiviata.
  - I prezzi Stripe delle versioni nuove dei piani nascono da soli al primo cambio: non serve crearli a mano.
  - Se un pagamento arriva ma il cambio su Stripe non riesce, in **Diagnostica** compare «Cambi di piano pagati da completare»: si ritenta da solo a ogni giro del cron.
- Prova tutto in modalità test (`sk_test_…`, carta `4242 4242 4242 4242`) prima di passare alle chiavi live.
- **Codici sconto (fase 6E).**
  - Si creano in **Amministrazione → Codici sconto**. Ognuno diventa un coupon Stripe «una volta», quindi sconta solo il primo anno.
  - I codici creati prima di attivare Stripe restano «da sincronizzare» e non si possono usare: premi «Riprova la sincronizzazione» dall'elenco.
  - Prova in modalità test: crea un codice del 20%, applicalo, paga con `4242 4242 4242 4242`. La prima fattura deve essere scontata e il rinnovo a prezzo pieno.
- **Invita un amico.**
  - È spento. Si accende da **Amministrazione → Impostazioni → Invita un amico** (una casella, con la tua password), oppure con `MHW_INVITI=1`.
  - I clienti trovano «Invita un amico» nel menu: link, codice da copiare e **invito per email** mandato dal sito (fino a 10 indirizzi per volta, 20 al giorno, mai due volte allo stesso indirizzo in 30 giorni). Gli indirizzi degli amici non si conservano: solo un'impronta e la forma mascherata.
  - Fai rileggere al consulente privacy la frase sull'invito per email in Privacy e Termini (§ inviti): il sito scrive a un indirizzo dato dal cliente, una volta sola.
  - Su Stripe non c'è niente da creare a mano: i coupon (`mhw-invito-5` … `mhw-invito-50` e quello dell'amico) nascono da soli. Gli eventi del webhook restano quelli di prima.
  - Prima di accenderlo in produzione fai queste prove in modalità test:
    1. Con un cliente che ha già pagato apri «Invita un amico» e copia il link.
    2. In una finestra anonima apri il link, registrati e paga con `4242 4242 4242 4242`. La fattura dell'amico deve avere il 5% di sconto.
    3. Su Stripe apri l'abbonamento di chi ha invitato: deve avere il coupon `mhw-invito-5`, e l'anteprima della prossima fattura deve essere scontata.
    4. Con un orologio di prova di Stripe (test clock) fai arrivare il rinnovo. La fattura deve essere scontata, in «I tuoi inviti» l'amico diventa «Già scontato» e la barra torna a zero.
    5. Con un Portfolio aggiungi una struttura da Account → «Cambia piano» (conguaglio subito). Dopo il pagamento il coupon deve essere ancora sull'abbonamento.
  - Per i Portfolio grandi lo sconto del 50% può superare quello che portano dieci amici: valuta un tetto in euro con `Inviti::TETTO_CENTS`.

**Listino 2026 (migrazione 028), CIN (027), scaglioni (026).**
  - **Prezzi in vendita** (annui, IVA esclusa): Essential 97 €, Plus 127 €, Portfolio 127 € + 70 € per ogni struttura in più, **da 2 a 10 strutture** (oltre, il bottone «Chiedi un preventivo», 7B). Sono versioni nuove: **chi è già abbonato resta sulla sua versione e rinnova al suo prezzo**. I Price ID di Stripe delle versioni nuove sono vuoti: Stripe usa i prezzi del database, non c'è niente da creare.
  - Sotto il nome di ogni piano, sulla landing e nella scelta del piano, c'è **«Ideale per …»** (il posizionamento). Si cambia in **Amministrazione → Piani → Testi sulla landing**, campo «Ideale per».
  - **Scaglioni del Portfolio** (prezzo che scende con le strutture): oggi non sono in vendita, ma restano possibili in **Amministrazione → Piani → Portfolio → Nuova versione**, campo «Scaglioni» (una riga per scaglione, come `3: 50`). In quel caso Stripe riceve da solo un Price «graduato»; lascia vuoto il «Price ID della struttura aggiuntiva».
  - **Varianti camera: tolte (7B).** Una struttura, una guida; le reti Wi-Fi diverse si scrivono nella sezione Wi-Fi. `MHW_VARIANTE_PREZZO` non serve più: puoi toglierlo. Vedi «4f» per gli abbonamenti che pagano ancora la voce delle camere.
  - **CIN obbligatorio per pubblicare e unico** su tutta la piattaforma (le vetrine demo no). Le guide già online senza CIN restano online, ma per **ripubblicarle** serve il CIN: avvisa i clienti. In **Anomalie** trovi le guide online senza CIN e i CIN ripetuti.
  - Prove in modalità test di Stripe:
    1. Un Portfolio nuovo da 6 strutture: il totale deve essere 127 + 70 × 5 = 477 € + IVA, e su Stripe la voce delle strutture aggiuntive deve avere quantità 5 a 70 €.

**Vendite per piano.** In **Amministrazione → Vendite per piano** vedi che piani si comprano, mese per mese (3, 6 o 12 mesi): colonne per piano, abbonamenti attivi oggi, incasso per piano, ultimi acquisti, con tabella ed esportazione CSV. Contano gli ordini pagati su Stripe; i clienti di esempio sono esclusi e i rinnovi sono in «Prospetti».

## 4. Testi da rivedere (Amministrazione)

- **Piani → «Testi sulla landing»** (la voce che prima si chiamava «Pacchetti»): titoli, descrizioni ed elenchi. Nell'elenco, una riga che finisce con «:» diventa il titoletto («Tutto di Essential, e in più:»).
- **Testimonianze**: aggiungine solo di vere, con il permesso scritto della persona, anche per la foto. Finché non ce n'è una visibile, il blocco in landing non compare.
- **FAQ**: tutte le domande sono in `app/src/Faq.php` (landing, `/domande`, dati strutturati e llms.txt le leggono da lì). Le otto in vista nella landing sono in `Faq::IN_VISTA`. Falle rileggere insieme ai punti 6 e 7 qui sotto, soprattutto «Ricevo fattura?».
- **Termini § 4** (cambio di piano, codici sconto, inviti; il capoverso sulle varianti camera è stato tolto nella 7B) e **Privacy → Fornitori** (Adamo): testi nuovi, versioni dei documenti **non** cambiate. Dopo la revisione del legale aggiorna `MHW_TERMS_VERSION` / `MHW_PRIVACY_VERSION`.
- **Termini** (§ 1, «Una guida, un'unità ricettiva»): una guida = un indirizzo e un CIN; eccezione per B&B, affittacamere e agriturismi con più camere allo stesso indirizzo (reti Wi-Fi diverse nella sezione Wi-Fi, 7B); sospensione dopo avviso se una guida serve più unità. Falla rileggere al consulente. Versione dei Termini: `2026-10-08`.
- **Termini e Privacy** (`/termini`, `/privacy`): vanno aggiornati con le novità (email di richiamo, dati di fatturazione, funnel anonimo), insieme alla versione in `MHW_TERMS_VERSION` / `MHW_PRIVACY_VERSION`.
- **Clienti di esempio**: sono account con password nota. Toglili prima di aprire al pubblico (Quadro → «Elimina i clienti di esempio»). Se tieni la demo pubblica, ricreali: la nuova demo è a Spello.

## 4a. Fatture con Adamo (collegamento Stripe)

1. In Adamo vai in **Impostazioni → Integrazioni → Stripe** e collega l'account Stripe del sito.
2. Su Stripe deve esserci l'IVA, e il sito la applica **solo con Stripe Tax**: attivalo nel pannello di Stripe e poi imposta `STRIPE_AUTOMATIC_TAX=1` (o la casella in Amministrazione → Impostazioni → Stripe). Un'aliquota fissa creata a mano su Stripe il sito non la usa. Senza Stripe Tax, Adamo fa fatture senza IVA.
3. Il sito manda già al cliente Stripe i dati che Adamo legge: `Fiscal_code`, `Pec` e `Fe_code` (`0000000` se manca il codice destinatario), e la partita IVA come «tax id». Quando il cliente cambia i dati nel sito, si aggiornano anche su Stripe: un dato tolto (la PEC, la partita IVA passando a persona fisica) sparisce anche lì.
4. Fai un pagamento di prova e controlla la fattura in Adamo: codice fiscale, PEC o codice destinatario, importi (anche con un codice sconto).
5. Ai privati Adamo fa fattura elettronica con il codice fiscale, non una ricevuta: **fallo confermare al commercialista**.
6. L'invio allo SDI e l'email al cliente si attivano da Adamo.
7. Non serve nessun token di Adamo nel sito.

## 4f. Listino 7B: dati di fatturazione, varianti camera, Essential

**Gli abbonamenti che pagano ancora le varianti camera.** Una volta sola, dalla cartella sopra `app/` (via SSH, o dal «Terminale» di cPanel):

    php app/tools/togli-varianti-stripe.php --prova     # elenca soltanto: cliente, quante camere, importo
    php app/tools/togli-varianti-stripe.php             # chiede conferma (scrivi SI) e toglie

Per ogni abbonamento attivo con la voce delle camere, la toglie con il credito della parte non goduta sulla prossima fattura, azzera la colonna nel database e scrive una riga nel Registro. **Dopo**, su Stripe → Catalogo prodotti, **archivia** il prodotto «MyHouse Welcome — variante camera». Se `--prova` non trova niente, non c'è niente da fare. I QR delle camere già stampati aprono la guida della struttura.

**Essential**: 6 sezioni aggiuntive e foto profilo, anche per chi è già abbonato (migrazione 031). I testi delle schede sono stati aggiornati solo dove erano quelli di serie: controllali in **Amministrazione → Piani → Testi sulla landing** (in Plus, `{sezioni}` diventa il numero di sezioni del catalogo).

**Dati di fatturazione → Adamo.** Il sito li chiede nel passo «Pubblica» prima di «Conferma e paga» (o in Account & Fatturazione) e li manda al cliente Stripe; Adamo li legge da lì:

| Campo del sito | Sul cliente Stripe | Campo di Adamo |
|---|---|---|
| Persona fisica / Azienda (`billing_type`) | `metadata[billing_type]` | — (decide quali campi servono) |
| Nome e cognome / Ragione sociale (`billing_name`) | `name` | Denominazione / Nome |
| Indirizzo, CAP, Città, Provincia | `address[line1]`, `address[postal_code]`, `address[city]`, `address[state]`, `address[country]=IT` | Indirizzo, CAP, Comune, Provincia, Nazione |
| Partita IVA (`vat`, solo azienda) | tax id `eu_vat` `IT…` e `metadata[vat]` | Partita IVA |
| Codice fiscale (`cf`; per un'azienda senza, la P.IVA) | `metadata[Fiscal_code]` e `metadata[cf]` | Codice fiscale |
| Codice destinatario SDI (`sdi`) | `metadata[Fe_code]` (`0000000` se manca) e `metadata[sdi]` | Codice destinatario |
| PEC (`pec`) | `metadata[Pec]` e `metadata[pec]` | PEC |

Da provare con un pagamento di prova: (1) una persona fisica con solo codice fiscale → fattura con `0000000`; (2) un'azienda con P.IVA e SDI; (3) un'azienda con P.IVA e PEC, senza SDI; (4) un cambio di dati dopo il primo pagamento (Account) → in Adamo, alla fattura successiva, i dati nuovi; (5) un codice sconto → importi giusti in fattura. I nomi esatti dei campi di Adamo vanno confermati nella loro integrazione Stripe (sono quelli che Adamo ci ha indicato: `Fiscal_code`, `Pec`, `Fe_code`).

## 4g. Novità della 7A e della 7C

**Indirizzi puliti (7A).** Carica anche il file nascosto `.htaccess` nella cartella principale. Poi in **Amministrazione → Impostazioni → Sito** scrivi l'indirizzo pubblico (`https://…`, senza `/` in fondo) e premi «Prova»: se risponde, accendi «Indirizzi puliti». Se il server non legge `.htaccess` (mod_rewrite spento), gli indirizzi restano con `index.php` e funzionano lo stesso.

**Email (7A).** Perché le email non finiscano in Spam servono **SPF, DKIM e DMARC** sul dominio da cui partono: chiedili all'hosting o configurali nel DNS. Mandati una «Prova della posta» da Impostazioni e controlla anche Spam e Promozioni. Gli HTML di ogni email sono in `app/prove/email/`. L'indirizzo dell'assistenza è `info@welcomemyhouse.it` (Impostazioni → Sito): cambialo lì, non nei testi.

**Conferma dell'email (7A).** Chi si è registrato e non ha mai confermato l'email, al prossimo accesso trova «Controlla la tua email» invece del pannello. Chi entra dall'amministrazione («Entra come cliente») no.

**Traduzioni (7C).** In **Impostazioni → Traduzioni** premi «Prova»: traduce «Buongiorno» e, se non va, mostra il messaggio esatto di Amazon (chiave sconosciuta, firma, permessi, regione) e cosa fare. All'utente IAM serve la policy **TranslateReadOnly** (o `translate:TranslateText`). Sul database del sito che mi hai mandato non c'è nessuna chiamata registrata e l'interruttore era spento su tutte le strutture: dalla 7C il cliente traduce con «Traduci» senza accenderlo.

**Foto e PDF (controllo del 13 ottobre).** Nel sito online caricare logo e copertina dà «Archiviazione dei file non disponibile al momento»: non è il piano, è l'archivio S3 che non risponde (chiavi, bucket, regione o connessioni in uscita dell'hosting). Vai in **Amministrazione → Impostazioni → Archivio** e premi «Prova»: ora dice la risposta esatta di Amazon e cosa sistemare. In **Diagnostica** la riga «Foto e PDF su Amazon S3» mostra l'ultimo errore registrato. Finché non va, puoi mettere l'archivio su «disco del server» (le foto stanno in `app/storage/uploads`) e passare a S3 dopo.

**Testi e foto della home.** In **Amministrazione → Sistema → Testi e foto** cambi ogni testo della home e le sue foto; niente da configurare. Dopo l'aggiornamento la tabella è vuota e la home è identica a prima.

**Novità (7C).** Le novità per chi ha già la guida sono in `app/lang/novita.php` (una per voce, la prima dell'elenco si vede per prima). Per una novità nuova aggiungi una voce con un `id` mai usato.

## 4h. Fase 8 (7D, 8A, 8B, 8C, 8D, 8E, 7F)

**Anteprima del link della guida (8B).** Su WhatsApp, Telegram e Messenger il link della guida mostra la copertina (`/g/…/anteprima.jpg`, 1200×630, con il marchio). WhatsApp tiene in memoria l'anteprima: dopo aver cambiato la copertina, il primo link mandato può mostrare ancora quella vecchia. Basta aspettare qualche ora, o mandare il link con un `?l=it` in fondo.

**Portali (8B).** Gli host spuntano solo dove sono presenti; i link li prepara il sito. Gli indirizzi di partenza (Google Maps, Booking.com, Airbnb, VRBO, Expedia) si cambiano da Amministrazione → Impostazioni → Portali. I link di recensione scritti prima dagli host restano nelle guide già pubblicate finché non ripubblicano.

**Recensioni e lingue (8C).** Niente da configurare: la migrazione `037` parte da sola. Da sapere:
- **Riscaldamento e aria condizionata** ora sono due sezioni. Le guide già pubblicate restano come sono finché l'host non ripubblica. Chi era al limite di sezioni del piano trova «Aria condizionata» disattivata, con la novità che glielo spiega.
- **Lingua in più** (Plus e Portfolio): la caratteristica «Lingua aggiuntiva» si cambia in Amministrazione → Piani. Le traduzioni suggerite verso quella lingua si spengono da Impostazioni → Traduzioni → «Traduci anche la lingua aggiuntiva» (di serie accesa). Amazon Translate copre tutte e nove le lingue.
- **Email per gli ospiti:** campo facoltativo in Impostazioni di ogni guida; senza, da 1 a 4 stelle l'ospite vede solo WhatsApp.

**La home in inglese (8E).** Niente da configurare: la migrazione `038` parte da sola. Da rileggere:
- **Amministrazione → Sistema → Testi e foto → English:** i testi inglesi di serie sono scritti da noi; cambiane quanti vuoi. «Ripristina il testo di serie» vale per la lingua scelta.
- **Amministrazione → Piani → «English»** sotto ogni pacchetto: i testi inglesi delle schede. Se lasci vuoto un campo, /en mostra quello italiano.
- **Amministrazione → SEO e GEO:** titolo e descrizione di «Home in inglese» e «Domande in inglese».
- **Email per l'aiuto in inglese:** nella registrazione con `?lang=en` compare l'email di contatto delle Impostazioni (di serie `assistenza@welcomemyhouse.it`).
- Termini e Privacy restano in italiano: il piede inglese lo dice.

**Clienti di prova, Marketing, SEO (7F).** La migrazione `039` parte da sola. Da fare:
- **Segna i tuoi account di collaudo** come «Cliente di prova» (Amministrazione → Clienti → scheda → in alto): così non sporcano Quadro, Vendite, Prospetti, Scadenze e Marketing e non ricevono richiami.
- **La cartella principale del sito deve essere scrivibile** dal sito (accanto a `index.php`), perché robots.txt, sitemap.xml e llms.txt si scrivono anche come file. Dopo il caricamento apri **SEO e GEO** e premi **«Salva»**: la riga «file veri» deve diventare verde. Non caricare mai quei tre file a mano: li riscrive il sito.
- **Imposta il dominio** in SEO e GEO (o l'indirizzo pubblico in Impostazioni): senza, il rinnovo giornaliero dei tre file non parte, per sicurezza.
- **Rileggi le quattro pagine per tipologia** (`/case-vacanza`, `/bed-and-breakfast`, `/affittacamere`, `/agriturismi`): i testi sono in `app/lang/tipologie.php`, titoli e descrizioni per Google in SEO e GEO.

**Portoghese del Portogallo e del Brasile.** Niente da configurare: la migrazione `040` parte da sola. Le guide già in portoghese restano «Português (Portugal)»: da adesso le traduzioni suggerite arrivano in portoghese europeo. Chi ha ospiti brasiliani può aggiungere «Português (Brasil)». I testi della guida in brasiliano sono scritti da noi: falli rileggere a un madrelingua, se puoi.

**Rifiniture 8F: rifai la vetrina.** Dopo il caricamento apri **Amministrazione → Clienti → l'account della vetrina → «Rifai la vetrina»**: la vetrina online è quella vecchia. Quella nuova ha i portali Google, Booking.com e Airbnb, la prenotazione diretta con il codice `CHECCO10` (−10%) e Instagram: chi prova la demo e dà 5 stelle vede i bottoni dei portali e, sotto, «Tornerai da queste parti?» con il codice. «Prenota sul nostro sito» porta alla pagina di MyHouse (`https://blackout.in/myhouse/?da=vetrina`), perché Casa Checco non esiste.

**Statistiche delle visite (8H).** Tutto da Amministrazione → SEO e GEO → «Statistiche delle visite»; finché i campi sono vuoti non parte niente e non c'è nessun banner.
1. **Matomo** (consigliato, senza cookie e senza banner): in cPanel → Softaculous installa Matomo su un sottodominio, per esempio `https://statistiche.welcomemyhouse.it/` (con il certificato https). In Matomo:
   - Amministrazione → Privacy → **Anonimizza i dati di tracciamento**: anonimizzazione dell'IP di **2 byte**;
   - Amministrazione → Privacy → **disabilita i cookie** di tracciamento (il sito li disabilita già, così vale anche per altri siti);
   - aggiungi il sito (l'indirizzo pubblico) e copia l'**ID del sito** (di solito 1) e l'indirizzo di Matomo in SEO e GEO;
   - **Obiettivi** → aggiungi un obiettivo per ognuno di questi nomi, con «Evento» → «Azione dell'evento» → «è esattamente»: `registrazione`, `prova`, `pagamento`, `contatto`, `sito-myhouse`, `vedi-esempio`. La categoria è sempre `obiettivo`; per `pagamento` il nome dell'evento è il piano, per `contatto` il canale (whatsapp, email, telefono).
   - L'obiettivo `prova` (guida creata) parte dal server verso Matomo: se il server non raggiunge l'indirizzo di Matomo non si conta, il resto funziona lo stesso.
2. **Google Analytics 4 e Microsoft Clarity** (facoltativi): incolla l'ID `G-…` e/o l'ID del progetto Clarity. Compare il banner del consenso; partono solo per chi accetta. In GA4 crea gli stessi eventi come «eventi chiave». Rileggi i testi del banner in Testi e foto → «Cookie e consenso» e il paragrafo «Cookie» della privacy (Testi e foto → «Informativa: il paragrafo Cookie»): fallo controllare dal consulente privacy.
3. **Search Console:** aggiungi il sito in Google Search Console (la verifica è in SEO e GEO → «Verifica Google») e invia la sitemap `https://…/sitemap.xml`.

**Registri (log) conformi al GDPR (8I).** Niente da fare perché funzioni: al primo giro dei richiami (o del `/cron`) i vecchi `app.log` e `mail.log` si dividono per giorno e parte la pulizia. Da configurare:
1. **Amministrazione → Impostazioni → Registri:** quanti giorni tenere i registri tecnici (di serie 90, da 30 a 180). Lì vedi spazio, file e il più vecchio, e puoi «Scaricare gli ultimi 7 giorni» o «Svuotare» (scrivendo SVUOTA). Il registro degli accessi degli amministratori non si svuota.
2. **Copia notturna su AWS S3 (Milano)**, con gli accessi degli amministratori inalterabili. In console AWS:
   - S3 → **Crea bucket** `welcomemyhouse-registri` (o un nome tuo), regione **Europa (Milano) eu-south-1**; **Blocca tutto l'accesso pubblico** acceso; **Crittografia** SSE-S3 (di serie); in «Impostazioni avanzate» **Object Lock: Abilita** (si può solo alla creazione; accende anche il versioning).
   - Bucket → Proprietà → **Object Lock**: lascia **senza** conservazione predefinita. S3 non permette una regola per cartella: la conservazione **Compliance di 180 giorni** la chiede il sito file per file, solo per quelli in `accessi-admin/` (nessuno, nemmeno l'account principale AWS, li può modificare o cancellare prima).
   - Bucket → Gestione → **Regole del ciclo di vita**: una regola per il prefisso `tecnici/` e una per `posta/` → «Scadenza delle versioni correnti» dopo **90 giorni** e «Eliminazione definitiva delle versioni non correnti» dopo 1 giorno; una per `accessi-admin/` → dopo **365 giorni** (e versioni non correnti dopo 1 giorno).
   - IAM → Utenti → **Crea utente** `welcomemyhouse-registri`, senza accesso alla console; Autorizzazioni → «Crea policy in linea» (JSON):
     `{"Version":"2012-10-17","Statement":[{"Effect":"Allow","Action":["s3:PutObject","s3:PutObjectRetention"],"Resource":"arn:aws:s3:::welcomemyhouse-registri/*"}]}`
     (`PutObjectRetention` serve solo a mettere i 180 giorni sugli accessi: in modalità Compliance si può allungare, mai accorciare. Niente `DeleteObject` né `GetObject`.)
   - Utente → Credenziali di sicurezza → **Crea chiave di accesso** («Applicazione eseguita al di fuori di AWS»): copia Access key ID e Secret in **Impostazioni → Registri** con bucket e regione, salva e premi **«Prova i dati salvati»**: deve dire che il bucket accetta i file e **rifiuta la cancellazione**.
   - Costo: centesimi al mese (i primi 12 mesi rientrano nel piano gratuito di S3).
3. **cPanel → Raw Access:** togli «Archivia i registri» (o tieni solo il mese in corso): i registri di Apache con l'IP completo li conserva l'hosting.
4. **cPanel → Errors / `error_log` di PHP:** controlla che il file `error_log` non finisca nella cartella pubblica (se c'è, impostalo in `app/storage/logs` o chiedi all'hosting).
5. **Matomo** (se usato, 8H) → Amministrazione → Privacy: «Anonimizza i dati di tracciamento» a 2 byte e **«Elimina i vecchi dati grezzi» dopo 180 giorni** (i report aggregati restano).
6. **Registro dei trattamenti:** aggiungi «Registri tecnici e di sicurezza» con queste durate: registri tecnici 90 giorni sul server (o quelli scelti), posta di prova 7 giorni, limiti ai tentativi 24 ore (IP come codice), accessi degli amministratori 6 mesi inalterabili e 12 mesi in tutto su S3 Milano.

## 4b. La guida vetrina (la demo della landing)

La demo della landing si sposta su un account vero, quello dell'agenzia.

**Dopo l'aggiornamento della fase 8 (8D): premi «Rifai la vetrina».** La vetrina online è stata creata prima delle sezioni nuove (Riscaldamento e Aria condizionata separate, piattaforme, messaggi, email per gli ospiti): in **Amministrazione → Clienti → account della vetrina** premi **«Rifai la vetrina»**. La nuova ha tutte le sezioni del catalogo compilate e tradotte in inglese, francese, tedesco e spagnolo, e il telefono della home mostra tutte le sue sezioni. Se invece vuoi tenere le modifiche fatte a mano, usa **«Completa le traduzioni della vetrina»**: scrive solo i campi ancora vuoti nelle altre lingue e ripubblica. Per controllare, dalla riga di comando: `php app/tools/vetrina-mancanti.php` (deve dare 0 in tutte e quattro le lingue).

**Da questa versione la vetrina si crea da sola.** Alla prima apertura del sito (o del Quadro) dopo il caricamento, Casa Checco nasce nell'account **blackout.agency@gmail.com**, con Plus dimostrativo per 12 mesi se l'account non ha un piano.
- Succede solo se l'account esiste e non ha già una vetrina. L'esito è scritto in `app/storage/vetrina-automatica.txt`: cancellando quel file si riprova.
- Per un altro account imposta `MHW_VETRINA_EMAIL`; per spegnere la creazione automatica, lascia la variabile vuota.
- Se c'era già una vetrina vecchia, in **Amministrazione → Clienti → quell'account** premi **«Rifai la vetrina»**. Toglie la vecchia e ne crea una con i dati aggiornati; le modifiche fatte a mano sulla vetrina si perdono.

A mano, come prima:
1. Registrati sul sito con **blackout.agency@gmail.com**, oppure usa l'account se c'è già.
2. Vai su **Amministrazione → Clienti**, apri quell'account e premi **«Crea la guida vetrina»**. Lascia spuntato «Concedi Plus dimostrativo per 12 mesi»: senza un piano la guida esce senza foto, senza inglese e senza luoghi.
3. Nasce «Casa Checco», ad Assisi, in un vicolo di fantasia vicino a Piazza Matteotti. La posizione (link di Maps e coordinate) è quella della piazza, non di un portone vero. Ha tutte le sezioni compilate, comprese quelle nuove (eventi con una locandina, sezione libera, muoversi in zona, parcheggi con i prezzi, servizi extra). Sono di fantasia la casa, il vicolo, i padroni di casa, i telefoni e i locali di «Dove mangiare» e «Negozi»; le foto sono ricavate da quelle della demo. Sono veri, presi dalle fonti pubbliche del 2026: parcheggi e tariffe, linea C, imposta di soggiorno, monumenti e musei con orari e prezzi, sentieri, mercato del sabato e feste dell'anno. Orari e prezzi cambiano: ricontrollali una volta l'anno. Viene pubblicata subito, con l'etichetta «Demo». Le date degli eventi partono dal giorno in cui la crei.
4. Da quel momento la landing mostra la vetrina come demo. La vetrina non occupa il posto della struttura del piano, e la modifichi dal pannello di quell'account.
5. Poi togli i clienti di esempio (**Amministrazione → Clienti → «Elimina i clienti di esempio»**). Hanno una password nota. L'eliminazione tocca solo gli account `@esempio.it`, non la vetrina.

## 4c. Traduzioni suggerite (Amazon Translate)

Dalla migrazione `022` Plus e Portfolio hanno le traduzioni suggerite, anche chi è già abbonato. Finché non inserisci le chiavi, i clienti possono accenderle ma non ne ricevono: la pagina lo dice.

**Una volta, su AWS:**
1. **IAM → Utenti → Crea utente**, per esempio `myhousewelcome-translate`, senza accesso alla console.
2. Aggiungi una policy in linea con il solo permesso necessario:
   ```json
   {"Version": "2012-10-17", "Statement": [{"Effect": "Allow", "Action": "translate:TranslateText", "Resource": "*"}]}
   ```
3. **Credenziali di sicurezza → Crea chiave di accesso** («Applicazione eseguita fuori da AWS»). Copia l'access key ID e la secret access key: la seconda AWS non la rimostra.
4. **Consigliato per la privacy:** in AWS Organizations, **Policy → Policy di rinuncia ai servizi di IA** (AI services opt-out), crea una policy di rinuncia per Amazon Translate e collegala all'account. Senza, AWS può conservare i testi tradotti per migliorare i suoi servizi. Verificalo con il consulente privacy (punto 7).
5. **Billing → Budget**: un avviso a pochi dollari al mese, per dormire tranquillo. I tetti del sito fermano comunque le richieste.
6. **Billing → Piano gratuito**: se l'account AWS è nel primo anno di Amazon Translate, segnati la data di fine (2 milioni di caratteri al mese gratis per 12 mesi dalla prima traduzione).

**Sul sito, in Amministrazione → Impostazioni → Traduzioni:**
1. Regione `eu-west-1` (Irlanda), oppure un'altra regione europea con Amazon Translate.
2. Access key ID e secret access key dell'utente del punto 1. Se le lasci vuote si usano quelle dell'archivio S3, ma allora quell'utente deve avere anche `translate:TranslateText`: meglio un utente a parte.
3. Prezzo (15 dollari per milione di caratteri), cambio dollaro-euro e fine del piano gratuito: servono solo alle stime.
4. Tetti: 150.000 caratteri al mese per account e 1.900.000 per tutto il sito (sotto i 2 milioni del piano gratuito). Cambiali se vuoi.
5. La tua password, **«Prova con questi dati»**, poi **Salva**: deve rispondere «Benvenuti» → «Welcome» (9 caratteri, nel registro).

**La prova vera, da cliente:**
1. Con un account Plus (o la vetrina): **Lingue → Accendi le traduzioni suggerite**. Compaiono la spiegazione e «In omaggio fino al…».
2. Apri una lingua, per esempio English: **«Suggerisci le traduzioni mancanti (N)»**. Sotto ogni campo vuoto compare «Suggerita: da controllare».
3. Apri la guida in inglese: le suggerite **non** ci sono. Approvane una e ricarica la guida: ora c'è.
4. In **Amministrazione → Traduzioni** controlla caratteri, costo stimato e omaggio. Sul conto AWS i caratteri compaiono il giorno dopo.

Dal tuo computer, senza toccare il sito: `php prove/firma-translate-botocore.php` (dalla cartella `app/`, con `pip install botocore`) confronta la firma con quella della libreria ufficiale di AWS.

## 4d. SEO e GEO (Amministrazione → Sistema → SEO e GEO)

1. **Dominio**: scrivi `https://myhousewelcome.it` (senza barra finale) quando il sito è sul suo dominio. Senza dominio non c'è il canonical e sitemap e llms.txt usano l'indirizzo di adesso.
2. **Il sito deve stare nella radice del dominio**: in una sottocartella (come `/welcomebook`) robots.txt e llms.txt non vengono letti. La pagina lo dice in rosso.
3. **Search Console e Bing**: crea la proprietà, scegli la verifica con il «tag HTML» e incolla il codice (va bene anche tutto il tag). Poi invia `https://…/sitemap.xml`.
4. **Immagine per la condivisione**: JPG o PNG 1200 × 630. Finché non la carichi si usa `assets/og.jpg`. Con S3 senza indirizzo pubblico l'indirizzo dell'immagine scade: meglio impostare l'indirizzo pubblico del bucket.
5. **Assistenti AI**: di serie sono ammessi tutti tranne CCBot (Common Crawl). Decidi tu.
6. **llms.txt**: rileggi introduzione e fatti chiave; prezzi e FAQ si aggiornano da soli.
7. Un CDN o una cache del server davanti al sito può tenere per un po' le versioni vecchie di robots.txt, sitemap e llms.txt.

## 4e. Guide fuori dai motori

Le guide (`/g/`, `/q/`, `/qr/`, `/media/`) hanno su ogni risposta `X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex` e lo stesso meta nella pagina. In robots.txt i motori (`User-agent: *`) **possono** leggerle, perché devono vedere il `noindex`; gli assistenti AI (GPTBot, ClaudeBot, PerplexityBot…) no. Le guide nuove hanno un indirizzo con 4 caratteri casuali (`casa-lucia-7k3f`); quelle già esistenti restano come sono, e il cliente può cambiarlo da Impostazioni → «Cambia il link della guida» (il QR stampato continua a funzionare).

Dopo la messa online:
1. Cerca su Google `site:tuodominio.it/g/` (e `site:tuodominio.it/q/`): non deve uscire niente.
2. Se compare qualcosa, in **Search Console → Rimozioni** chiedi la rimozione temporanea dell'indirizzo, e fai lo stesso per le guide della versione v1 su `blackout.in/welcomebookv1` (`site:blackout.in/welcomebookv1`).
3. In Amministrazione → Impostazioni → archivio lascia **vuoto** «Indirizzo pubblico (CDN)»: con un indirizzo pubblico foto e PDF non passano dal sito e perdono il blocco per i motori (lo segnalano anche le Anomalie).
4. Le guide con un codice di accesso confermato «a proprio rischio» sono in Amministrazione → Anomalie, solo per informazione.

## 5. Foto da caricare

Tutte in `assets/foto/`, con **questi nomi esatti**: si caricano sopra le vecchie, senza toccare il codice.

| File | Dove | Misura consigliata | Note |
|---|---|---|---|
| `scena-qr.jpg`, `scena-ospite.jpg`, `scena-host.jpg` | landing, fascia sotto l'hero | 1200×750 (16:10), JPG, ≤ 300 KB, soggetto al centro | **già caricate** (le foto che hai fornito). Per cambiarne una, carica il .jpg nuovo con lo stesso nome, poi **Diagnostica → «Rigenera le foto WebP»**: fino ad allora si vede il .jpg. Se una manca, compare un disegno su fondo colorato. Sul telefono la foto si ritaglia quadrata, al centro. |
| `borgo.jpg` | il borgo della landing | 2000×924 (circa 2,16:1) | poi **Diagnostica → «Rigenera le foto WebP»** (oppure `php app/tools/foto.php`) |
| `borgo-telefono.jpg` | il telefono nell'hero | 900×633 | **già aggiornata** con il portone di Casa Lucia. Se la cambi, rigenera come sopra |
| `casa.jpg`, `portone.jpg`, `osteria.jpg`, `caffe.jpg`, `gelato.jpg`, `soggiorno.jpg` | foto della demo | 1600 px sul lato lungo | se le cambi con foto di Spello, ricrea i clienti di esempio |
| `casa.jpg` | copertina della demo: splash e testa della guida | 1448×1086 | **già aggiornata** (il portone ad arco). Sul server **ricrea i clienti di esempio** per vederla: Quadro → «Elimina…», poi «Crea…» |
| `pannello-1.webp`, `-2`, `-3` | «Inizia in pochi minuti» | 1200×750 | sono schermate vere del pannello di Casa Checco, fatte in locale (finestra larga 1024 px, la seconda 1200 px per mostrare l'anteprima). Rifalle se il pannello cambia molto. |
| `og.jpg` | anteprima dei link condivisi | 1200×630 | generata da `strumenti/marchio.php` |

Usa solo foto di cui hai i diritti, e nessun locale reale riconoscibile nella demo.

## 6. Da verificare con il commercialista

1. **Fattura elettronica.** Stripe emette ricevute e fatture proprie, ma **non** invia la fattura elettronica al Sistema di Interscambio. I dati raccolti (P.IVA o codice fiscale, SDI o PEC, indirizzo) arrivano sul cliente Stripe come metadati, pronti per un gestionale di fatturazione elettronica collegato a Stripe o per l'emissione a mano. L'applicazione non genera fatture. Bisogna decidere come emetterle.
2. **IVA.** I prezzi sono IVA esclusa. Bisogna decidere se attivare Stripe Tax (`STRIPE_AUTOMATIC_TAX`) e come trattare i clienti esteri: la scheda di fatturazione oggi è solo italiana.
3. La frase della FAQ «Ricevo fattura? Sì. …» va confermata.
4. Il conguaglio quando si aggiunge una struttura: dal pannello va sulla prossima fattura (`create_prorations`); da Account & Fatturazione si paga subito. Le due fatture devono essere coerenti.
5. I codici sconto per la prenotazione diretta li gestisce l'host sul **suo** sito: MyHouse Welcome li mostra soltanto.

## 7. Da verificare con il consulente privacy

1. **Email di richiamo**: vanno a chi si è registrato, anche se non ha ancora confermato l'email. Ognuna ha il link «non mandarmene più» per quel tipo. Bisogna confermare la base giuridica (legittimo interesse / esecuzione del servizio) e il testo dell'informativa. Se serve, si limitano ai soli indirizzi confermati: è una riga di codice.
2. **Funnel e statistiche**: eventi anonimi, senza cookie, IP o identificativi. Va confermato che non serve un banner.
3. **Testimonianze**: consenso scritto per nome, testo e foto. La foto si toglie dall'admin in qualsiasi momento.
4. **Registrazione**: la formulazione «Creando l'account dichiari di aver letto l'informativa privacy» (annotata nel codice).
5. **Dati di fatturazione**: P.IVA e codice fiscale sono salvati nell'account e su Stripe. Servono un tempo di conservazione e una voce nell'informativa.
6. **Registro email** (`email_log`): conserva tipo e data di ogni richiamo. Va deciso il tempo di conservazione.
7. **Traduzioni suggerite**: i testi della guida di cui il cliente chiede la traduzione vanno ad Amazon Web Services (Amazon Translate, regione impostata in Amministrazione). Nell'informativa c'è una riga tra i fornitori; vanno confermati la nomina di AWS come responsabile e la policy di rinuncia ai servizi di IA (punto 4c). I testi non contengono dati degli ospiti, ma possono contenere nomi e telefoni dell'host. Termini e informativa sono passati alla versione 2026-10.
8. **Adamo** è ora tra i fornitori dell'informativa (riceve da Stripe i dati di fatturazione): va confermata la nomina a responsabile.

## 8. Dieci prove da fare sul server

1. Apri la landing a 390 px e da computer: listino col prezzo mensile, «Confronta tutti i piani», FAQ apribili da tastiera, fascia delle scene, piè di pagina con i dati di Blackout Agency.
2. Registrati con un'email vera, conferma il link arrivato **via SMTP**, crea una struttura e completa la procedura fino all'anteprima.
3. Prova «Pubblica» senza dati di fatturazione (deve portarti alla scheda), poi compilali e paga in **modalità test di Stripe**. La guida va online solo quando arriva il webhook.
4. Inquadra il QR stampato (PDF) con due telefoni diversi, Android e iPhone: si apre la guida. Inquadra anche il QR del Wi-Fi.
5. Incolla in un luogo un link breve vero di Google Maps (`maps.app.goo.gl/…`): il nome si deve compilare da solo.
6. Carica una foto e un PDF in una sezione e in una riga (Servizi → Istruzioni), poi controlla che si vedano dalla guida. Con S3: i file stanno nel bucket.
7. Portfolio in modalità test: scegli 3 strutture, controlla che solo la prima si modifichi, paga, controlla che si sblocchino. Poi «Aggiungi una struttura» dal pannello e guarda il conguaglio nella fattura di prova di Stripe.
8. Crea una struttura «da una struttura esistente», elimina quella di origine e controlla che le foto della copia restino.
9. Compila recensioni e prenotazione diretta nelle Impostazioni, ripubblica e apri il commiato dal telefono. Con Plus, nascondi la firma.
10. Imposta `MHW_CRON_TOKEN`, apri `/cron/IL_TOKEN` (deve rispondere `{"ok":true,…}`). Il giorno dopo la prova 2, controlla che sia arrivata l'email «Come si entra…» se l'arrivo era vuoto, e che «Non mandarmene più» funzioni.
