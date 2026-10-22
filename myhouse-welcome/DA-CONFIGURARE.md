# MyHouse Welcome v2 — da configurare

Cosa resta da fare a te (o a chi gestisce il server) prima di aprire al pubblico.
I dettagli tecnici sono in `app/LEGGIMI.md`. Le novità, fase per fase, sono in `CHANGELOG.md`.

---

## 1. Caricamento

1. Copia sul server **tutto `app/storage/`** e `config.local.php`, se c'è.
2. Carica il contenuto di `welcomebook/` sopra i file vecchi, **senza toccare `app/storage/`**.
3. Apri il sito una volta. Le migrazioni `007`–`042` partono da sole.
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
| `MHW_TERMS_VERSION`, `MHW_PRIVACY_VERSION`, `MHW_DPA_VERSION` | quando cambi i testi | date AAAA-MM-GG (8J); meglio da Impostazioni → Titolare e documenti legali |
| `MHW_COMPANY`, `MHW_COMPANY_LEGAL`, `MHW_COMPANY_VAT`, `MHW_COMPANY_CITY`, `MHW_COMPANY_REA`, `MHW_COMPANY_CAPITALE`, `MHW_COMPANY_UNIPERSONALE`, `MHW_COMPANY_SITE`, `MHW_CONTACT_EMAIL`, `MHW_CONTACT_PHONE`, `MHW_CONTACT_WHATSAPP` | no | 8J: di serie il titolare City Up S.r.l.; si cambiano da Impostazioni → Titolare e documenti legali |

**Se la posta non parte con «535 authentication failed».** Il server rifiuta utente o password. L'errore ora dice quale utente è stato usato, quanti caratteri ha la password e con quali metodi si è provato (LOGIN e PLAIN).
- L'utente è l'indirizzo completo della casella. La password è quella della casella, la stessa della webmail, non quella del pannello.
- Scrivila e premi «Prova con questi dati»: prova senza salvare. Prima la prova usava solo la password già salvata.
- Se la webmail entra e qui no, chiedi al fornitore della posta se l'invio SMTP è attivo per quella casella o se serve una «password per le app».

**Cron (facoltativo).** Le email di richiamo partono anche senza cron, perché il controllo si fa mentre qualcuno usa il sito. Se il sito è poco visitato, aggiungi in cPanel un cron ogni ora:

```
wget -q -O- https://TUODOMINIO/cron/IL_TOKEN >/dev/null
```

## 3. Stripe

- **Chiave segreta**: vanno bene la chiave segreta standard (`sk_live_…`, in Stripe → Sviluppatori → Chiavi API → «Chiavi standard») **o una chiave con restrizioni** (`rk_live_…`). Con la chiave con restrizioni dai questi permessi, altrimenti Stripe rifiuta alcune operazioni:
  - **Scrittura**: Customers (con i tax ID), Checkout Sessions, Subscriptions, Products, Prices, Coupons, Invoices, Refunds, Customer portal.
  - **Lettura**: Events, Webhook Endpoints, Charges, PaymentIntents, PaymentMethods, Payment method configurations, Tax settings e Tax registrations.
  - Il sito accetta anche le chiavi `mk_live_…` (`mk_test_…` per le prove). Stripe non le documenta: dopo averla salvata premi «Prova la connessione»; se risponde «Invalid API Key», usa una `sk_live_` o una `rk_live_`.
  - «Prova la connessione» e Diagnostica → Stripe dicono se qualcosa manca (un errore «does not have the required permissions» indica il permesso da aggiungere).

- **Webhook** su `https://TUODOMINIO/webhook/stripe` (oppure `…/index.php/webhook/stripe`). Eventi da inviare:
  - `checkout.session.completed`
  - `checkout.session.expired`
  - `checkout.session.async_payment_succeeded` e `checkout.session.async_payment_failed` (l'addebito SEPA confermato o rifiutato dalla banca: abbonamenti nuovi e cambi di piano)
  - `invoice.paid`
  - `invoice.payment_failed`
  - `customer.subscription.updated`
  - `customer.subscription.deleted`
  - `customer.subscription.trial_will_end` (solo registro: gli avvisi della prova li mandiamo noi)
  - `charge.refunded` (9O: ogni rimborso, fatto dal sito, da Stripe o da Adamo)
  - `charge.refund.updated` (consigliato: un rimborso confermato o fallito dopo)
- Il webhook non serve solo a pubblicare: sblocca anche le strutture del Portfolio, comprese quelle aggiunte dopo.
- **Se dopo il pagamento la guida resta «Stiamo attivando la tua guida» (19 ottobre).** Vuol dire che il webhook non arriva o viene rifiutato. Da questa versione la guida si attiva lo stesso dopo circa 20 secondi (il sito chiede a Stripe gli eventi della sessione, con la chiave segreta), ma il webhook va sistemato perché serve anche per rinnovi, disdette e rimborsi. Cosa guardare:
  1. **Amministrazione → Impostazioni → Stripe → «Prova la connessione»**: dice se su Stripe, nella modalità della chiave (prova o reale), c'è l'endpoint verso `…/webhook/stripe`, se è attivo e quali eventi mancano.
  2. **Quadro e Diagnostica** («Webhook di Stripe»): l'ultimo webhook ricevuto, l'ultimo rifiutato con il motivo, l'ultimo pagamento confermato dal recupero.
  3. Su **Stripe → Sviluppatori → Webhook → il tuo endpoint**, l'esito degli ultimi invii: **400** = il «Segreto del webhook» in Impostazioni non è il «Segreto di firma» (`whsec_…`) di quell'endpoint (attenzione: prova e reale hanno endpoint e segreti diversi; se l'hai ruotato, incolla quello nuovo); **404** = indirizzo sbagliato; **301/302** = l'indirizzo reindirizza (usa quello finale, con `https://` e il `www` giusto); **403** o **406** = un firewall dell'hosting (ModSecurity) blocca Stripe: chiedi all'hosting di escludere `/webhook/stripe`; **nessun invio** = endpoint creato nell'altra modalità o evento `checkout.session.completed` non selezionato.
  4. Sistemato, dal dettaglio dell'evento in Stripe puoi premere **«Invia di nuovo»** per gli eventi rimasti in errore: arrivano come «già elaborati» se il recupero li ha già presi, senza doppioni.
- **Pagamento con la carta nel pannello (19 ottobre).** Il primo acquisto si può pagare con la carta di credito o di debito direttamente nel passo «Pubblica», nel modulo di Stripe incorporato nella pagina; «Preferisci pagare sulla pagina di Stripe?» resta a un clic. Per accenderlo:
  1. In Stripe → Sviluppatori → Chiavi API copia la **chiave pubblicabile** (`pk_test_…` per le prove, `pk_live_…` in produzione). Non è segreta: sta nella pagina del cliente.
  2. Incollala in **Amministrazione → Impostazioni → Stripe → «Chiave pubblicabile»** (oppure `STRIPE_PUBLISHABLE_KEY`). Deve essere della stessa modalità della chiave segreta: `pk_test_` con `sk_test_`/`rk_test_`, `pk_live_` con `sk_live_`/`rk_live_`. Se non lo è, il sito la ignora e il cliente paga sulla pagina di Stripe; «Prova la connessione» te lo dice.
  3. Senza chiave pubblicabile non cambia niente: «Conferma e paga» porta sulla pagina di Stripe, come prima.
  4. Apple Pay e Google Pay nel modulo incorporato compaiono solo se li accendi in Stripe → Impostazioni → Metodi di pagamento e, per Apple Pay, se **registri il dominio** del sito in Stripe → Impostazioni → Domini dei metodi di pagamento.
  5. Prova in modalità test: «Conferma e paga» → «Paga e pubblica.» con il modulo; carta `4242 4242 4242 4242` → «Stiamo attivando la tua guida» → online. Poi rifai con «Paga sulla pagina di Stripe» e con «Annulla e torna indietro» (nessun addebito, si torna a Pubblica). Con la carta `4000 0025 0000 3155` la verifica 3D Secure si apre dentro il modulo.
  6. Il sito in produzione deve essere in **https**: Stripe non mostra il modulo incorporato su http (in locale sì, solo in modalità test).
  - Gli eventi del webhook restano gli stessi. Il cambio di piano da Account resta sulla pagina di Stripe.
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

## 4i. Fase 9 (9A, 9B, 9C, 9D, 9E)

**9A · Portfolio progressivo e controlli Stripe**
- La migrazione **043** crea la versione nuova del Portfolio (117 € + 60/50/40 a scaglioni, da 2 a 10) con i Price ID vuoti: il sito crea i Price nuovi su Stripe alla prima vendita (il Price graduato della struttura aggiuntiva ha `up_to` 1, 4, inf). Chi ha già un Portfolio resta sulla sua versione.
- **Prima di andare online** (e dopo ogni cambio di listino), con le chiavi di **prova** (`sk_test_`) in `config.local.php` o nelle variabili: `php app/tools/verifica-stripe.php`. Crea una sessione di Checkout per Essential, Plus e Portfolio da 2 a 10, con e senza il 5%, fa i due controlli e la fa scadere; tutte le righe devono dire «ok». Con la chiave viva si rifiuta.
- Se nel Quadro compare «Prezzi su Stripe diversi dal listino», il pagamento di quella versione è fermo: correggi i Price su Stripe oppure svuota i Price ID della versione in Vendite → Piani (nuova versione con gli stessi importi: i Price ID si tengono solo se importo e scaglioni sono identici).
- Le richieste di **preventivo** (più di 10 strutture) arrivano all'indirizzo di contatto (`MHW_CONTACT_EMAIL`) e in Amministrazione → Vendite → Preventivi: la promessa è una risposta entro un giorno lavorativo. Si cancellano dopo 12 mesi.

**9B · Giudizio con sconto**
- La migrazione **044** aggiunge gli stati dell'adesione e delle immagini scelte. Di serie: sconto **acceso**, **5%**, sezione «Cosa dicono di noi» accesa (Impostazioni → Giudizi con sconto; variabili `MHW_GIUDIZI_SCONTO`, `MHW_GIUDIZI_PERCENTO`, `MHW_GIUDIZI_HOME`). Spento, la casella al pagamento e «Partecipo» spariscono per chi non ha ancora aderito.
- I giudizi vanno in home solo quando li accendi in **Clienti → Giudizi** («In home»). I giudizi già pubblicati con il consenso (7D) sono rimasti in home.
- **Termini e Privacy sono cambiati** (punto 17 «Giudizio con sconto»; trattamento «Giudizi e pareri dei clienti» su base contratto): versioni di serie **2026-10-16** (`MHW_TERMS_VERSION`, `MHW_PRIVACY_VERSION`); il DPA resta 2026-10-15. I clienti con un abbonamento ricevono l'avviso e ri-accettano al primo accesso. Se hai cambiato i testi legali da Testi e foto, rileggi il punto 17 e il trattamento 6 (e in inglese). Da far rivedere a un legale: il programma è una recensione incentivata (direttiva Omnibus): lo sconto non dipende dal voto ed è scritto ovunque.
- Il coupon Stripe `mhw-giudizio-5` (una volta) e `mhw-primo-anno-N` (invito + giudizio) li crea il sito; non vanno creati a mano.

**9C · Email, telefono, vetrina**
- Il marchio ora viaggia **dentro** le email (`cid:marchio`): si vede anche con le immagini esterne bloccate. Imposta comunque `base_url` (Impostazioni → Sito): i link e le icone social delle email mandate dal cron (senza un browser) lo usano; se è vuoto il Quadro lo segnala.
- Per Gmail e Outlook: **SPF, DKIM e DMARC** sul dominio del mittente (`MAIL_FROM`): sono quelli che tengono le email fuori dallo spam e fanno mostrare le immagini esterne (le icone social). Senza, il marchio incorporato si vede lo stesso.
- La vetrina «Casa Checco» ha sempre l'indirizzo `/g/casa-checco` (con «Indirizzi puliti» spenti, `/index.php/g/casa-checco`); i vecchi `/g/casa-checco-2`… rispondono con un 301. L'indirizzo attuale, con «Apri» e «Copia», è in Amministrazione → scheda del cliente della vetrina. Se `/g/casa-checco` fosse di un cliente vero, la vetrina tiene il numero e la scheda lo dice.

**9B-bis · Quando si applica lo sconto del giudizio**
- Amministrazione → Giudizi (riquadro in alto, con la tua password) o Impostazioni → Giudizi con sconto → «Quando si applica lo sconto»: di serie **all'adesione** (chi spunta la casella al pagamento ha il 5% sul primo addebito, anche se il giudizio arriva dopo). Se preferisci fidarti meno, **solo dopo il giudizio**: lo sconto parte quando il giudizio è stato inviato (sul primo addebito se arriva prima, altrimenti sul rinnovo). Variabile d'ambiente: `MHW_GIUDIZI_QUANDO=adesione|giudizio`.

**9D · Codici omaggio e avvisi prima dell'addebito**
- La migrazione **045** parte da sola al primo accesso (tabelle `codici_omaggio` e `rimborsi`, colonne nuove). Niente da fare a mano.
- **In Stripe** (Dashboard → Impostazioni → Abbonamenti ed email, o Billing → Settings): **spegni** le email automatiche di Stripe «Prova in scadenza» (*trial ending*) e «Rinnovo imminente» (*upcoming renewal*): gli avvisi a 30 e 7 giorni li manda il sito, con il nostro testo e il nostro marchio. **Accendi** Smart Retries (Billing → Settings → Manage failed payments) per i rinnovi non riusciti.
- **Webhook**: aggiungi all'endpoint di Stripe gli eventi `customer.subscription.trial_will_end` (solo registro) e `charge.refunded` (9E: conferma dei rimborsi). Gli altri sono gli stessi di prima. Controlla in Stripe → Sviluppatori → Webhook che ci siano tutti e dieci (l'elenco è anche in Impostazioni → Stripe e al punto 3).
- **Informativa, «Cookie tecnici»** (19 ottobre): il testo di serie ora cita anche `mhw_fascia_…`. Se in Amministrazione → Testi del sito hai salvato a mano «Cookie tecnici», aggiungi tu la frase (in italiano e in inglese); altrimenti non c'è niente da fare. Non serve cambiare la versione dei documenti legali.
- **Codici omaggio**: Amministrazione → Vendite → Codici omaggio → «Nuovo codice» (mesi 1–12, scadenza di serie 14 giorni, nota interna). Il codice appena creato ha i bottoni **WhatsApp**, **Email** e **Copia il link** (`/omaggio/XXXXX`). Il testo del messaggio si cambia in Testi e foto → «Codici omaggio». Un codice non ancora usato si annulla dalla stessa pagina.
- Il cliente in omaggio inserisce la carta al Checkout (Stripe chiede il metodo di pagamento anche a 0 €): su Stripe l'abbonamento è «in prova» (*trialing*) fino alla data del primo addebito. Chi disattiva il rinnovo prima non paga nulla.
- **Prova con le chiavi di test**: `php app/tools/verifica-stripe.php` ora prova anche i casi «omaggio 1, 6 e 12 mesi» con e senza lo sconto del giudizio: tutte le righe devono dire «ok» (sessione a 0 € oggi). Poi, sempre in test: un codice da 3 mesi → `/omaggio/CODICE` → registrazione → Checkout 0 € con carta `4242…` → la guida è online, in Clienti il cliente ha «Omaggio fino al …». Con un *test clock* di Stripe a fine omaggio: la fattura parte con l'eventuale sconto del giudizio e Adamo la riceve come oggi.
- Gli avvisi a **7 giorni** partono anche a chi ha chiesto di non ricevere gli avvisi di rinnovo (sono dovuti prima di un addebito): lo dice il piè dell'email.
- **Termini** (punti 5 «Rinnovo automatico», 17, nuovo 18 «Codice omaggio») e **Privacy** (t2: ultime 4 cifre della carta) sono cambiati: le versioni di serie restano 2026-10-16 (le stesse della 9B, pubblicate insieme). Da far rivedere da un legale, come il resto dei documenti.

**9E · Azioni sul cliente (scheda cliente)**
- Niente da configurare. Ogni azione chiede il motivo (resta nel registro) e manda un'email al cliente. Per i **rimborsi** serve Stripe configurato: il rimborso parte da Stripe (`refunds`), il cliente lo vede sulla carta in 5–10 giorni; la **nota di credito la fai tu in Adamo** (il sito te lo ricorda nel messaggio di esito). Il rimborso non ferma l'abbonamento: se serve, «Ferma il rinnovo».
- **Password provvisoria**: la vedi una sola volta nella scheda del cliente (12 caratteri, due parole e quattro cifre), vale 48 ore; comunicala al telefono, mai per email. Al primo accesso il cliente deve sceglierne una nuova. Scaduta, usa «Manda il link per reimpostarla».

## 4j. Fase 9 ter (9F, 9G, 9H, 9I, 9J)

**9H · Email: quattro indirizzi, ognuno al suo posto**

| Indirizzo | Tipo | A cosa serve |
|---|---|---|
| assistenza@welcomemyhouse.it | casella vera | Clienti, privacy, avvisi interni. Mittente di tutte le email del sito. Unico indirizzo nel piè di pagina. |
| commerciale@welcomemyhouse.it | alias → assistenza@ | MyHouse siti («Anche il tuo sito»), preventivi, convenzioni, codici omaggio. |
| news@welcomemyhouse.it | alias → assistenza@ | Mittente di newsletter e campagne (Brevo). Il sito non manda email da qui. |
| amministrazione@welcomemyhouse.it | alias → assistenza@ | Fatture, ricevute, pagamenti, rinnovi, rimborsi. |

Su **Hostinger** (è la nota «Email per il tecnico» in `_brief/claude-code/email-per-il-tecnico.md`):
1. Casella **assistenza@welcomemyhouse.it** con password forte. I dati SMTP (server, porta, cifratura, utente, password) li inserisci tu in **Amministrazione → Impostazioni → Posta**, poi premi **«Prova»**. La password non va scritta in chat né nel repository.
2. Alias (solo inoltro) verso assistenza@: **commerciale@**, **news@**, **amministrazione@**. Manda un'email di prova a ciascuno.
3. DNS: **SPF** di Hostinger, **DKIM** attivo, **DMARC** `_dmarc.welcomemyhouse.it` → `v=DMARC1; p=none` (dopo un mese senza problemi `p=quarantine`).
4. **Brevo**, quando parte la newsletter: mittente news@welcomemyhouse.it, dominio verificato con il record DKIM di Brevo.
5. Nella casella assistenza@: una regola o etichetta per destinatario (commerciale, news, amministrazione) per separare la posta.

Sul sito, **Amministrazione → Impostazioni → Indirizzi email**: i quattro campi hanno già questi valori. Commerciale, newsletter e amministrazione vuoti = tutto va all'assistenza.
- **Mittente salvato vecchio**: al primo caricamento dopo l'aggiornamento, se in Impostazioni → Posta il mittente era `noreply@myhouse.it` o di un altro dominio, diventa da solo assistenza@ (una volta sola). La prima volta che apri **Gestione** lo leggi in alto; resta nel registro e la versione di prima delle impostazioni è in `app/config.local.bak.php`. Un mittente da variabile d'ambiente (`MAIL_FROM`) non si tocca.
- **Dove arriva cosa**: preventivi e risposte di «Anche il tuo sito» a commerciale@ (la conferma al cliente parte da assistenza@ con `Reply-To` commerciale@); le email di rinnovo, scadenza, cambio piano e rimborso partono da assistenza@ con `Reply-To` amministrazione@ e la riga «Per domande su fatture e pagamenti: amministrazione@…»; gli avvisi a noi su pagamenti falliti e rimborsi confermati vanno ad amministrazione@; tutto il resto all'assistenza.
- **Stripe** → Impostazioni → Dettagli pubblici dell'attività: **email di assistenza = amministrazione@welcomemyhouse.it** (compare su ricevute e fatture Stripe). Stripe → Email ai clienti: **ricevute accese**, «rinnovo imminente» e «fine prova» spente (le mandiamo noi, 9D).
- **Adamo**: imposta amministrazione@welcomemyhouse.it come email del mittente e di contatto sulle fatture.
- **Codici omaggio**: «Manda per email» apre il tuo programma di posta (non parte dal sito): scrivi da commerciale@. Il messaggio ha già la firma «MyHouse Welcome · commerciale@…».
- **Vetrina**: `vetrina_email` di serie è **vetrina@welcomemyhouse.it** (non riceve email: i Richiami lo saltano). La vetrina che c'è già resta dov'è.

**9F · Rifiniture**
- **Vetrina**: la migrazione 046 aggiunge i portali e «PG · Umbria» alla vetrina pubblicata e la ripubblica. Controlla il saluto finale di Casa Checco (le tre stelle con Google, Booking e Airbnb) e, se hai cambiato la vetrina a mano, dal pannello di quell'account **Lingue → Regione/Provincia** e **Pubblica** di nuovo. In alto nella vetrina c'è «Questa è una guida di esempio · Torna al sito · Crea la tua guida».
- **Testi e foto → «Pannello: una lingua in più»** (`lingue.extra.*`) e **«Inviti» → Messaggio di partenza** (`inviti.messaggio_partenza`, `{amico}` = la percentuale): rileggili; le versioni in inglese stanno nelle chiavi `en:` (di serie da `lang/sito-en.php`).
- **Provincia e Regione**: le strutture esistenti hanno la regione di prima in «Regione» e la provincia vuota: i clienti la mettono dal modulo della struttura (sigla con i suggerimenti; all'estero testo libero). Per la vetrina lo fa la migrazione. Nella guida si vede «Città · Provincia · Regione», tradotti dove esiste una forma (Florence, Tuscany…).
- **Sezione libera**: una per guida per tutti i piani (la migrazione mette «Sezione libera (una per guida)» nel confronto dei piani). Chi ne ha già due o tre le tiene.
- **Pannello in inglese**: parte da solo quando la lingua principale non è l'italiano; il cliente lo cambia in Account & Fatturazione. Restano in italiano il passo Pubblica, Impostazioni, Aspetto, QR & Link, Statistiche e i messaggi di esito: se serve, è la fase dopo. Le richieste «La tua lingua non c'è?» arrivano all'assistenza con oggetto «Lingua mancante: …».

**9G · Traduzioni suggerite**
- **Impostazioni → Traduzioni**: il tetto per account è ora **all'anno** (`translate.cap_account`, di serie 500.000 caratteri; il vecchio 150.000 al mese viene letto come 500.000). L'anno parte dall'attivazione dell'abbonamento e si azzera al rinnovo. Il tetto mensile del sito resta com'era.
- **Chi finisce i caratteri** vede «Hai usato le traduzioni suggerite di quest'anno… scrivici» e tu ricevi un'email all'80% e al 100%: in **Amministrazione → Clienti → scheda** premi **«Aggiungi 250.000»** con una nota (resta nel registro), oppure scrivi un tetto personale. Nessuna configurazione su AWS o Stripe: il tetto è solo nostro.
- **Vendite → Traduzioni**: la pagina nuova con gli esiti per mese e la memoria delle frasi. La memoria si pulisce da sola con il cron (righe mai riusate da 12 mesi).
- Niente da fare per il «primo anno in omaggio»: non esiste più, le traduzioni sono comprese in Plus e Portfolio entro il tetto.

**9I · Codici cliente, struttura e pagamento; «Cerca»**
- Niente da configurare sul sito: alla prima apertura dopo l'aggiornamento la migrazione **048** dà un codice a tutti i clienti (`MW-7K4P2`), a tutte le strutture (`MW-7K4P2-01`) e agli ordini pagati (`MW-7K4P2-2026-1`). I codici non cambiano e non si riusano.
- **Stripe, una volta sola**: dalla cartella del sito, `php app/tools/stripe-codici.php` (prova a vuoto: elenca), poi con le chiavi di **test** `php app/tools/stripe-codici.php --scrivi`, e infine con quelle vive `php app/tools/stripe-codici.php --scrivi --live`. Scrive `codice_cliente` sui clienti Stripe che ci sono già; quelli nuovi lo ricevono da soli.
- **Adamo**: la causale della fattura la prende da Stripe. Gli abbonamenti nuovi hanno la descrizione «Codice cliente MW-… · Pagamento MW-…», i conguagli la hanno nella descrizione della fattura, i rinnovi nei metadati (`codice_cliente`, `codice_pagamento`). In Adamo → Integrazioni → Stripe controlla che descrizione o metadati finiscano nella causale; se Adamo non legge i metadati, il codice del rinnovo lo trovi nella scheda del cliente.
- **Cerca** (in alto in ogni pagina di Amministrazione, tasto **/**): codice cliente, struttura o pagamento anche scritti male, email, nome, telefono senza +39, struttura, città, CIN, indirizzo della guida, partita IVA, codice fiscale, ID di Stripe (`cus_`, `sub_`, `in_`, `pi_`, `ch_`, `cs_`), codice sconto, omaggio o d'invito. Il numero della fattura Adamo non si trova: il sito non lo salva.

**9J · Palette «Bianco e nero»**
- Niente da configurare: in **Aspetto** c'è la settima palette, chiara e scura, con la casella «Foto in bianco e nero». La migrazione **047** aggiunge la colonna della casella. Le altre sei palette restano identiche.

## 4l. 9L (19 ottobre): finestra del questionario, foto, 55%, «Giudizio con sconto»

1. **Termini e Informativa nuovi (2026-10-19).** Al primo accesso ogni cliente vede «Abbiamo aggiornato i Termini e l'Informativa» e li riaccetta (8J). Se in «Testi e foto» avevi riscritto a mano il punto «Giudizio con sconto» o il trattamento 6, rileggili: il testo nuovo vale solo dove non c'è il tuo.
2. **Le schede in home.** In Amministrazione → Vendite → **Giudizi e vetrina** accendi le schede «da approvare» (al massimo 6) e approva i giudizi arrivati. Una scheda senza copertina non va in home: chiedi all'host di caricarla.
3. **Parole da controllare.** In Impostazioni → Giudizi con sconto c'è l'elenco di serie (truffa, vergogna, schifo, …): cambialo come preferisci. I «Segnalati» servono solo ad attirare l'attenzione: lo sconto resta.
4. **Il testo della casella** è uno solo, in «Testi e foto» → «Le guide dei nostri host e Giudizio con sconto» → «La casella del programma». Se cambi le regole, cambia anche i Termini.
5. **Email di benvenuto dopo la pubblicazione:** fatta il 20 ottobre (vedi «4m»).
6. **Safari.** Prova la finestra «Rispondi a 3 domande» su un iPhone: si apre dal basso, la pagina sotto non scorre, si chiude con la ×.

## 4l bis. 9L rivista (20 ottobre): scheda e giudizio separati

1. **Vendite → Giudizi e vetrina**: le schede si approvano dalla scheda «Vetrina», i giudizi dalla scheda «Giudizi» (filtro «5 stelle» di serie). Di norma si approvano tutte le schede; i giudizi solo quelli che vuoi in home. L'ordine si cambia con ▲▼ o trascinando le righe.
2. **Impostazioni → Sito**: «Schede in home» (di serie 4) e «Giudizi in home» (di serie 6).
3. **Testi e foto** → «Le guide dei nostri host, Cosa dicono di noi…»: le chiavi `vetrina.*` (titolo «Case vere, già online.») e `dicono.*`, anche nella linguetta EN.
4. **Termini e Informativa** sono alla versione 2026-10-20: al primo accesso i clienti li riaccettano.
5. Il segnaposto «Welcome My House» è generato dal marchio; se preferisci un'altra immagine, sostituisci `assets/vetrina-segnaposto.jpg` (1200 × 675) e `assets/vetrina-logo-segnaposto.png` (240 × 240) con lo stesso nome.

## 4m. Email di benvenuto (20 ottobre)

1. **Niente da attivare:** parte da sola quando la prima guida di un account va online, una volta sola, in italiano o in inglese (lingua del pannello).
2. **La riga sul programma** si cambia in Amministrazione → Testi e foto → «Le guide dei nostri host e “Giudizio con sconto”»: «Email di benvenuto · la riga per chi ha aderito» e «… per chi può ancora aderire» (linguette IT ed EN). `{giudizio}` è la percentuale, `{link}` la pagina del programma nel pannello.
3. **Per provarla:** con un cliente di prova non parte (come gli altri richiami). Registrati con un indirizzo tuo, pubblica una guida e guarda la posta; in Amministrazione → scheda del cliente, nelle email mandate compare «benvenuto».
4. Chi risponde all'email scrive all'assistenza (Reply-To di serie).

## 4n. Questionario in inglese (20 ottobre)

1. **Testi:** Amministrazione → Testi e foto → «Pagine per tipologia: il questionario», linguetta **EN**: le domande, le risposte e i perché in inglese (di serie già scritti). La linguetta IT resta quella delle pagine italiane.
2. **SEO e GEO:** la pagina nuova «Quale piano scegliere in inglese» (/en/plan) è accesa di serie; titolo e descrizione si cambiano lì.

## 4o. 9M (20 ottobre): scheda campione e parcheggio copiabile

1. **Niente da attivare**: la scheda campione compare da sola ai clienti Portfolio dopo il pagamento («Le mie guide» → «Rendi campione»). Prima del pagamento vedono la voce spenta con «Disponibile dopo l'attivazione».
2. **Testi e foto** → «Scheda campione e attivazione (Portfolio)»: lo spiegone (titolo, testo, «Si copiano», «Non si copiano mai», «Come funziona l'avviso») e il riquadro «Disponibile dopo l'attivazione», anche in inglese. Sono le stesse chiavi del pannello, delle FAQ e di /piu-strutture.
3. **Attenzione, cambio di comportamento**: la traduzione automatica ora parte solo dopo il pagamento (o con un codice omaggio, o un piano dato a mano). Chi sta preparando la guida senza aver pagato vede il bottone spento.
4. Lo storico delle sezioni sovrascritte o tolte nelle gemelle si cancella da solo dopo 30 giorni.

## 4p. Cache e SEO/GEO (20 ottobre)

1. **Dopo ogni caricamento via FTP**: Amministrazione → Sistema → **Cache** → «Svuota tutto». Se una modifica non si vede ancora, è quasi sempre OPcache o il browser: questo li sistema tutti e due.
2. Se la riga «Codice PHP (OPcache)» dice che l'hosting non permette di svuotarla (`opcache.restrict_api`), chiedi all'hosting di riavviare PHP, o aspetta qualche minuto.
3. **Memoria delle traduzioni**: non svuotarla tutta senza motivo, ogni frase tolta si paga di nuovo ad Amazon. «Togli le frasi mai riusate» invece è innocuo.
4. **SEO e GEO, da fare una volta sul dominio vero**: imposta il **dominio** con https (senza, canonical, sitemap e IndexNow non usano gli indirizzi giusti); in **Bing Webmaster Tools** verifica il sito e invia la sitemap (Bing alimenta anche ChatGPT e Copilot); in **Google Search Console** invia la sitemap. Poi premi **«Avvisa i motori (IndexNow)»**, e di nuovo ogni volta che cambi prezzi, testi o titoli.
5. Se avevi già cambiato a mano la descrizione della home o l'introduzione di llms.txt, restano le tue: rileggile in SEO e GEO (la home deve stare tra 70 e 155 caratteri; il controllo lo dice).
6. **Profili social**: in SEO e GEO → Dati dell'azienda → «Profili social» metti gli indirizzi veri (Instagram, Facebook, LinkedIn…). Sono il segnale più forte, per Google e per gli assistenti AI, che il marchio è un'azienda vera.
7. **Google Business Profile** (fuori dal sito): crea la scheda di City Up / MyHouse Welcome con lo stesso indirizzo, telefono ed email del sito, scritti uguali. Aiuta le ricerche locali e il pannello del marchio.

## 4q. 9N (20 ottobre): sconto unico, solo aderenti, inglese, omaggio, legale, demo, Cassa

1. **Carica e apri una pagina qualsiasi**: le migrazioni 054–057 girano da sole. La **055 rigenera la vetrina Casa Checco** (stesso indirizzo /g/casa-checco): se in Quadro → «Da configurare» compare «rifai la vetrina», usa **«Rifai la vetrina»** dalla scheda del cliente della vetrina. La **056** cambia la formula delle traduzioni solo nei testi rimasti uguali a quelli di serie; un testo scritto da te resta: rileggilo in Testi e foto (questionario, piani, attivazione).
2. **Le due sezioni pubbliche sono spente di serie.** Amministrazione → Giudizi e vetrina, in cima: accendi «Mostra la sezione “Le guide dei nostri host” sul sito» e/o «… “Cosa dicono di noi” …» quando hai almeno una scheda (o un giudizio) approvata. Finché sono spente, o accese senza contenuti, non si vede niente e /guide-dei-nostri-host risponde 404. Dopo il primo caricamento erano accese (9L): **ora sono spente finché non le riaccendi**.
3. **Versione dei Termini.** Il punto 17 (giudizio, rinnovo, omaggio) e la privacy sono cambiati ma la versione di serie resta **2026-10-20**. Se sul server c'era già la 9L con la stessa data, i clienti non vedranno la finestra di riaccettazione: se vuoi che riaccettino, imposta `MHW_TERMS_VERSION=2026-10-21` e `MHW_PRIVACY_VERSION=2026-10-21` (o da Impostazioni → Titolare e documenti legali). Fai rileggere i testi al legale.
4. **Password**: minimo 8 caratteri per i nuovi account e i cambi di password (scelta tua; il brief diceva 10). Chi ha già una password più corta entra lo stesso.
5. **Capitale sociale**: di serie «10.000,00 € i.v.» (`MHW_COMPANY_CAPITALE`), nel piè di pagina e nei documenti. Controlla il numero.
6. **GA4 e Clarity**: se li usi, inserisci gli ID (SEO e GEO → «Statistiche delle visite»); la Diagnostica avvisa se mancano. Il banner dei cookie compare solo con almeno un ID.
7. **Bonifici**: dalla scheda del cliente → «Attiva un abbonamento manuale» → «Bonifico ricevuto», con importo **IVA esclusa**, IVA, data e riferimento (CRO o causale). Nasce un pagamento con codice MW che entra in **Cassa**, Prospetti e Scadenze. Il rinnovo di un cliente a bonifico è a mano (Scadenze lo segna «Pagato con bonifico: rinnovo a mano»). La fattura la fai in Adamo come sempre.
8. **Cassa**: l'IVA dei pagamenti Stripe è quella calcolata da Stripe (IVA automatica accesa); se un mese ha l'asterisco, Stripe non l'ha detta per quei pagamenti: controllali nella dashboard di Stripe. Il CSV («pagamenti dell'anno») è quello da mandare al commercialista.
9. **Codici sconto**: «Valido fino al» è ora già compilato con il 31 dicembre; cambialo se serve.
10. Restano in italiano (per ora): l'email d'invito all'amico e le email scritte dall'amministrazione al cliente (rinnovo fermato, cambio piano, rimborso, password provvisoria).

## 4u. Pagamento fermo «Il pagamento non è disponibile in questo momento» (22 ottobre)

Il messaggio compare quando il controllo dei prezzi prima del Checkout non va a buon fine. La causa più comune: un Price ID salvato nel piano (di solito la «struttura aggiuntiva» del Portfolio, che il sito crea da solo) era nato con le chiavi di **test** e con le chiavi **live** Stripe non lo trova.

1. Carica questa versione: il Price ID dell'altra modalità si toglie da solo al primo pagamento e il sito lo ricrea con le chiavi live (nel Registro azioni: «stripe.price_tolto»). Non devi fare niente.
2. Se non puoi aggiornare subito: **Amministrazione → Piani → Portfolio** (versione in vendita) → svuota «Price ID» e «Price ID della struttura aggiuntiva» → salva. Fai lo stesso per gli altri piani se avevano Price ID creati in test.
3. Se il pagamento resta fermo, il motivo esatto è nel **Quadro** («Prezzi su Stripe diversi dal listino» o «Il Checkout di Stripe chiede importi diversi dal riepilogo») e in **Diagnostica → Stripe**. Casi tipici: un Price ID messo a mano con «IVA inclusa» (va «IVA esclusa») o importo diverso; la chiave con restrizioni senza il permesso di scrittura su **Prices**; **Adaptive Pricing** acceso in Stripe → Impostazioni → Pagamenti (va spento: il cliente pagherebbe in un'altra valuta).
4. **«Stripe 400: Keys for idempotent requests can only be used with the same parameters…»** (per esempio in Cambia piano): corretto in questa versione, non devi fare niente su Stripe. Dopo l'aggiornamento premi di nuovo «Vai al pagamento»: si apre una pagina di Stripe nuova e quella di prima scade.
5. Prova: con un cliente di prova (o «Entra come cliente», dove l'errore mostra anche il motivo tecnico) arriva al passo Pubblica con Portfolio · 2 strutture e premi «Conferma e paga»: deve aprirsi il pagamento a 177 € + IVA.

## 4t. 9R pacchetto 16 (22 ottobre): il 5% del giudizio una volta sola

1. Carica e apri una pagina qualsiasi: parte la migrazione **063**. Poi Amministrazione → Sistema → Cache → «Svuota tutto».
2. Termini e privacy passano alla **2026-10-22**: i clienti li riaccettano al primo accesso. Se hai scritto una versione a mano in Impostazioni → Titolare e documenti legali, aggiornala.
3. In Testi e foto rileggi «Giudizio con sconto · riga sotto la casella» e le email «Fino al 55%» (oggetto, prima riga e conti per chi ha aderito al pagamento; riquadro del giudizio; le due chiavi nuove «Riquadro del giudizio per chi ha aderito dopo il pagamento» e «I conti per chi ha aderito dopo il pagamento»). Se le avevi cambiate a mano, riscrivile con la regola «una volta».
4. Chi con il pacchetto di ieri aveva avuto il 5% sul primo anno e, lasciato il giudizio, era tornato «Sul prossimo rinnovo»: la migrazione **063** lo riporta da sola ad «Applicato al primo anno» e al primo giro dei richiami lo sconto del rinnovo su Stripe si ricalcola. Non devi fare niente.

## 4s. 9R (22 ottobre): giudizio, pagamenti di prova, staff, cambio proposto, Portfolio

1. **Carica e apri una pagina qualsiasi**: la migrazione 062 gira da sola. Poi Amministrazione → Sistema → Cache → «Svuota tutto».
2. **Termini e privacy** passano alla versione 2026-10-21: al primo accesso i clienti vedono la finestra per riaccettarli (8J). Se in Impostazioni → Titolare e documenti legali hai scritto a mano una versione, aggiornala tu.
3. **Testi rivisti**: in Testi e foto controlla «Giudizio con sconto · casella», «Sconto massimo · inviti e giudizio», il titolo di «Porta un amico» e le email «Fino al 55%». I testi salvati identici a quelli di serie vecchi si sono aggiornati da soli; quelli cambiati a mano restano: riscrivili con la formula dei due programmi.
4. **Pagamenti di prova**: da ora i pagamenti fatti con le chiavi di test di Stripe non entrano nei conti (lo dice il webhook). Quelli di prima, se ci sono, escludili a mano da Cassa → mese → «Escludi dai conti» con il motivo.
5. **Account di prova da togliere**: scheda cliente → «Elimina account di prova» (solo senza pagamenti live), scrivendo «ELIMINA» e il codice cliente.
6. **Staff**: per chi prova il sistema da dentro, scheda cliente → «Attiva come staff» (piano, strutture, fino al). Non usare più i piani dati a mano per i tester.
7. **Portfolio**: se hai creato a mano su Stripe il Price della prima struttura (`stripe_price_id`), il nome della voce in fattura è quello del prodotto su Stripe: chiamalo «MyHouse Welcome Portfolio — prima struttura». Poi, una volta, `php app/tools/verifica-stripe.php --portfolio` (va bene anche con la chiave live: solo letture) o Anomalie → «Ricontrolla con Stripe le quantità dei Portfolio».
8. **Foto di Francesco (demo)**: manca. Metti un ritratto generato con l'IA (nessuna persona reale), quadrato, almeno 800×800, in `_brief/claude-code/pronti/checco/francesco.jpg` e chiedi di collegarlo alla demo; fino ad allora resta la porta rossa.

## 4r. 9N 10 bis, 9O, 9P, 9Q (21 ottobre): offerta su misura, rimborsi, SEPA, IVA per i privati, partner, email «Fino al 55%» e bozze

1. **Carica e apri una pagina qualsiasi**: le migrazioni 058–061 girano da sole. Poi **Amministrazione → Diagnostica → Stripe → «Ricontrolla»**: ogni voce deve essere «OK». Le due voci «Da controllare a mano» (Adaptive Pricing spento, email di Stripe ai clienti spente): controllale su Stripe e premi **«Confermo»**.
2. **Webhook**: aggiungi all'endpoint `charge.refunded` e (consigliato) `charge.refund.updated`; controlla che ci siano anche `checkout.session.async_payment_succeeded` e `checkout.session.async_payment_failed` (SEPA). L'elenco completo è al punto 3 qui sopra e nella Diagnostica.
3. **Rimborsi e Adamo**: fai un rimborso di prova da Stripe e uno dal sito; controlla che Adamo emetta da solo la nota di credito. Se non lo fa, fai la nota in Adamo e spunta **«Segna come fatta»** nella scheda del cliente (finché non la spunti resta in «Da fare oggi»).
4. **SEPA**: prova con l'IBAN di test `DE89370400440532013000` (riesce) e `DE62370400440532013001` (fallisce). Il periodo dell'abbonamento è quello di Stripe: decorre dal pagamento, non dalla conferma della banca (3–7 giorni).
5. **IVA per i privati**: Impostazioni → Sito → «Aliquota IVA per i prezzi IVA inclusa» (vuota = 22). Serve solo a mostrare i prezzi: l'IVA vera la calcola Stripe Tax.
6. **Partner**: Amministrazione → Vendite → Partner → «Approva e crea il partner» (codice, commissione, sconto, rinnovi sì/no, IBAN, P.IVA, periodicità). Il partner entra da `/partner` con un link via email (48 ore). Le commissioni si pagano con un bonifico su fattura del partner: Cassa → **Commissioni partner** → «Segna come pagata». Le email ai partner partono da commerciale@.
7. **Termini e privacy**: il punto 12 (Programma partner) e il trattamento 15 sono nuovi; la versione di serie resta 2026-10-20. Per far riaccettare i clienti imposta in Impostazioni → Titolare e documenti legali una versione nuova (per esempio a 30 giorni da oggi, come chiede l'aiuto del campo) e fai rileggere i testi al legale.
8. **Email «Fino al 55%» (9P)**: parte da sola 7 giorni dopo l'attivazione, solo con gli inviti accesi (`MHW_INVITI=1`): spenti, in Amministrazione → Richiami vedi «Email sconti in attesa: N» e partono quando li accendi. Rileggi i testi in Testi e foto → «Email ai clienti: Fino al 55%».
9. **Email sulle bozze (9Q)**: al giorno 7 e al giorno 21 da sole. Per la strada «Quindici minuti insieme» metti in Impostazioni → Sito il **«Link per fissare una chiamata»** (Calendly, Google Calendar…): vuoto, il bottone è «Chiamaci» con il telefono dell'assistenza. Le risposte «la finiamo noi» arrivano all'assistenza: compila la guida da «Entra come cliente» e segna «compilata da noi» nella scheda. Il vecchio richiamo «pubblica» non parte più.
10. **Matomo** (se lo usi): crea gli obiettivi `bozza7_click`, `bozza21_click`, `email_sconti_click` (eventi, categoria «obiettivo»).
11. **Offerte su misura (10 bis)**: da Vendite → Preventivi → «Prepara l'offerta»; il cliente riceve il link `/offerta/…`. Lo sconto sui rinnovi è quello che scrivi nell'offerta e vale a ogni rinnovo finché non lo cambi.

## 4k. 9K (19 ottobre): foto, pagine nuove, partner, PEC

1. **Limiti di PHP per le foto.** Se nel Quadro compare «Il server limita il caricamento delle foto», chiedi all'hosting (o in cPanel: «Seleziona versione PHP» → Opzioni, oppure «MultiPHP INI Editor») `upload_max_filesize = 20M`, `post_max_size = 20M` (meglio 25M) e `memory_limit = 256M`. Controlla poi in Diagnostica la riga «Limiti per il caricamento delle foto». Le foto prese dal browser arrivano già ridotte; i limiti servono a chi carica senza JavaScript.
2. **Safari.** Apri la home su un iPhone e su Safari del Mac: il telefono a destra deve avere il contorno scuro tutto intorno, anche in basso. Qui l'ho provato solo con Chromium.
3. **Casa Checco.** La foto nuova è 921 × 1125 px: va bene per la guida e per il telefono, ma se ne hai una più grande (almeno 2000 px) mettila in `assets/foto/checco-copertina.jpg` e dalla scheda della vetrina usa «Rifai la vetrina».
4. **Partner.** In Amministrazione → Impostazioni → Sito, quando le decidi, «Commissione partner (%)» e «Sconto per i clienti del partner (%)»: la pagina /property-manager le scrive da sola. Le richieste arrivano a commerciale@ e in Amministrazione → Vendite → Partner. **Fase successiva** (non fatta): i codici partner, lo sconto applicato con il codice e il conteggio delle commissioni.
5. **Titolare.** In Impostazioni → Titolare e documenti legali compila «Capitale sociale» (per esempio «€ 10.000 i.v.») e, se vale, «Società unipersonale»: finché sono vuoti nel piè di pagina c'è solo il REA. La PEC non compare più da nessuna parte; se in `config.local.php` c'è ancora `company_pec`, puoi lasciarla (è solo un promemoria).
6. **Testi legali.** Tolta la PEC e aggiunto il trattamento 14 (richieste di partnership): l'ho trattata come correzione, senza cambiare la versione dei documenti. Se il consulente preferisce farla riaccettare, cambia la data di versione dell'Informativa in Impostazioni → Titolare.
7. **«Per chi è» e questionario.** Rileggi i testi in «Testi e foto» → «Per chi è» e «Pagine per tipologia: …». Se avevi cambiato la scheda «B&B e affittacamere», ora sono due schede: riscrivile. Le foto delle sei schede si cambiano in fondo alla stessa pagina.
8. **SEO delle pagine nuove.** /piu-strutture e /property-manager sono in sitemap e in Amministrazione → SEO con titolo e descrizione già scritti: se vuoi, ritoccali lì. Poi, in Search Console, invia di nuovo la sitemap.

## 4b. La guida vetrina (la demo della landing)

La demo della landing si sposta su un account vero, quello dell'agenzia.

**Dopo l'aggiornamento della fase 8 (8D): premi «Rifai la vetrina».** La vetrina online è stata creata prima delle sezioni nuove (Riscaldamento e Aria condizionata separate, piattaforme, messaggi, email per gli ospiti): in **Amministrazione → Clienti → account della vetrina** premi **«Rifai la vetrina»**. La nuova ha tutte le sezioni del catalogo compilate e tradotte in inglese, francese, tedesco e spagnolo, e il telefono della home mostra tutte le sue sezioni. Se invece vuoi tenere le modifiche fatte a mano, usa **«Completa le traduzioni della vetrina»**: scrive solo i campi ancora vuoti nelle altre lingue e ripubblica. Per controllare, dalla riga di comando: `php app/tools/vetrina-mancanti.php` (deve dare 0 in tutte e quattro le lingue).

**Da questa versione la vetrina si crea da sola.** Alla prima apertura del sito (o del Quadro) dopo il caricamento, Casa Checco nasce nell'account **vetrina@welcomemyhouse.it** (9H; prima era blackout.agency@gmail.com), con Plus dimostrativo per 12 mesi se l'account non ha un piano.
- Succede solo se l'account esiste e non ha già una vetrina. L'esito è scritto in `app/storage/vetrina-automatica.txt`: cancellando quel file si riprova.
- Per un altro account imposta `MHW_VETRINA_EMAIL`; per spegnere la creazione automatica, lascia la variabile vuota. La vetrina già creata nell'account di prima non si sposta.
- Se c'era già una vetrina vecchia, in **Amministrazione → Clienti → quell'account** premi **«Rifai la vetrina»**. Toglie la vecchia e ne crea una con i dati aggiornati; le modifiche fatte a mano sulla vetrina si perdono.

A mano, come prima:
1. Registrati sul sito con **vetrina@welcomemyhouse.it** (o con l'account che ha già la vetrina), oppure usa l'account se c'è già.
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
4. Tetti: 500.000 caratteri **all'anno** per account (9G: dall'attivazione dell'abbonamento, si azzera al rinnovo) e 1.900.000 al mese per tutto il sito (sotto i 2 milioni del piano gratuito). Cambiali se vuoi; dalla scheda del cliente si aggiungono 250.000 caratteri a chi li finisce.
5. La tua password, **«Prova con questi dati»**, poi **Salva**: deve rispondere «Benvenuti» → «Welcome» (9 caratteri, nel registro).

**La prova vera, da cliente:**
1. Con un account Plus (o la vetrina): **Lingue → Accendi le traduzioni suggerite**. Compaiono la spiegazione e il contatore dell'anno («0% dell'anno usato · si azzera il …»).
2. Apri una lingua, per esempio English: **«Suggerisci le traduzioni mancanti (N)»**. Sotto ogni campo vuoto compare «Suggerita: da controllare».
3. Apri la guida in inglese: le suggerite **non** ci sono. Approvane una e ricarica la guida: ora c'è.
4. In **Amministrazione → Vendite → Traduzioni** controlla caratteri, esiti (ok, dalla memoria, fermate, errori) e costo stimato. Sul conto AWS i caratteri compaiono il giorno dopo.

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
4. **Registrazione** (8J): una casella sola, non spuntata, «Accetto i Termini e l'Accordo sul trattamento dei dati e ho letto l'Informativa privacy».
5. **Dati di fatturazione**: P.IVA e codice fiscale sono salvati nell'account e su Stripe. Servono un tempo di conservazione e una voce nell'informativa.
6. **Registro email** (`email_log`): conserva tipo e data di ogni richiamo. Va deciso il tempo di conservazione.
7. **Traduzioni suggerite**: i testi della guida di cui il cliente chiede la traduzione vanno ad Amazon Web Services (Amazon Translate, regione impostata in Amministrazione). Nell'informativa c'è una riga tra i fornitori; vanno confermati la nomina di AWS come responsabile e la policy di rinuncia ai servizi di IA (punto 4c). I testi non contengono dati degli ospiti, ma possono contenere nomi e telefoni dell'host. Termini e informativa sono passati alla versione 2026-10.
8. **Adamo** è ora tra i fornitori dell'informativa (riceve da Stripe i dati di fatturazione): va confermata la nomina a responsabile.

## 7b. Termini, Privacy, DPA e titolare (8J)

I testi sono **di partenza, scritti sul servizio come funziona davvero**: prima di pubblicarli falli rivedere da un legale (Termini e recesso) e dal consulente privacy (informativa, DPA, registro dei trattamenti).

1. **Titolare.** Amministrazione → Impostazioni → **Titolare e documenti legali**: ragione sociale, riga completa (P.IVA, C.F., REA), sede, REA, capitale sociale, sito (9K: la PEC non compare più da nessuna parte). Di serie City Up S.r.l. Se `config.local.php` sul server ha ancora un blocco `'legal'` con Blackout Agency, salva una volta questo gruppo (o togli quel blocco): vale quello che c'è lì. Anche Amministrazione → SEO e GEO → «L'azienda»: la migrazione 042 aggiorna i campi solo se avevano i vecchi valori di serie.
2. **Testi.** Testi e foto → linguetta **Documenti legali** (italiano e inglese): Termini (16 punti e modulo di recesso), Informativa (12 trattamenti in tabella, fornitori, trasferimenti, diritti, cookie), DPA, le tre novità della finestra. La versione inglese dice che in caso di dubbio prevale l'italiano.
3. **Versioni.** Dopo una modifica sostanziale cambia la data in Impostazioni → Titolare e documenti legali. **Mettila almeno 30 giorni avanti**: i clienti con un abbonamento attivo ricevono subito un'email (una volta) e una riga nel pannello; a quella data compare la finestra «Accetto» e, finché non accettano, il pannello è in sola lettura. Il testo di prima resta in «Versioni precedenti». Una data già arrivata fa comparire subito la finestra (come in questo rilascio: le versioni sono al 15 ottobre 2026, quindi **tutti i clienti attuali** al prossimo accesso accettano i nuovi documenti e ricevono l'email).
4. **Stripe**, per quello che scrivono i Termini: in Impostazioni → Fatturazione → Recupero dei ricavi, **3 tentativi in 14 giorni**, poi **annulla l'abbonamento**; accendi le email ai clienti per i pagamenti non riusciti.
5. **Fornitori.** Firma (o accetta online) i DPA di Hostinger, AWS, Stripe e Adamo e conservali; l'elenco con i link è in `_brief/REGISTRO-TRATTAMENTI.md` (documento interno, non va sul sito: completalo e tienilo aggiornato).
6. **Non ancora nel codice** (fase 6L): download dei dati e chiusura dell'account dal pannello, cancellazione automatica degli account inattivi a 24 mesi con avviso a 30 giorni, backup cifrati. I testi oggi dicono di scrivere all'assistenza: fino alla 6L queste richieste si fanno a mano, entro 30 giorni.
7. **ODR.** La piattaforma europea ODR è stata chiusa il 20 luglio 2025: i Termini lo dicono e rimandano agli organismi ADR. Se vuoi indicare un organismo ADR preciso, aggiungilo al punto 15.
8. **Email dei diritti.** I testi usano l'indirizzo dell'assistenza (Impostazioni → Sito), oggi assistenza@welcomemyhouse.it.

## 8. Dieci prove da fare sul server

1. Apri la landing a 390 px e da computer: listino col prezzo mensile, «Confronta tutti i piani», FAQ apribili da tastiera, fascia delle scene, piè di pagina con i dati di City Up S.r.l. (sede, P.IVA, REA) e i link Termini · Privacy · DPA.
2. Registrati con un'email vera, conferma il link arrivato **via SMTP**, crea una struttura e completa la procedura fino all'anteprima.
3. Prova «Pubblica» senza dati di fatturazione (deve portarti alla scheda), poi compilali e paga in **modalità test di Stripe**. La guida va online quando Stripe conferma (webhook, o il recupero dopo 20 secondi): in Diagnostica «Webhook di Stripe» deve dire «ultimo ricevuto», non «confermato dal recupero».
4. Inquadra il QR stampato (PDF) con due telefoni diversi, Android e iPhone: si apre la guida. Inquadra anche il QR del Wi-Fi.
5. Incolla in un luogo un link breve vero di Google Maps (`maps.app.goo.gl/…`): il nome si deve compilare da solo.
6. Carica una foto e un PDF in una sezione e in una riga (Servizi → Istruzioni), poi controlla che si vedano dalla guida. Con S3: i file stanno nel bucket.
7. Portfolio in modalità test: scegli 3 strutture, controlla che solo la prima si modifichi, paga, controlla che si sblocchino. Poi «Aggiungi una struttura» dal pannello e guarda il conguaglio nella fattura di prova di Stripe.
8. Crea una struttura «da una struttura esistente», elimina quella di origine e controlla che le foto della copia restino.
9. Compila recensioni e prenotazione diretta nelle Impostazioni, ripubblica e apri il commiato dal telefono. Con Plus, nascondi la firma.
10. Imposta `MHW_CRON_TOKEN`, apri `/cron/IL_TOKEN` (deve rispondere `{"ok":true,…}`). Il giorno dopo la prova 2, controlla che sia arrivata l'email «Come si entra…» se l'arrivo era vuoto, e che «Non mandarmene più» funzioni.
