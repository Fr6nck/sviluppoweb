# MyHouse Welcome v2 — da configurare

Cosa resta da fare a te (o a chi gestisce il server) prima di aprire al pubblico.
I dettagli tecnici sono in `app/LEGGIMI.md`. Le novità, fase per fase, sono in `CHANGELOG.md`.

---

## 1. Caricamento

1. Copia sul server **tutto `app/storage/`** e `config.local.php`, se c'è.
2. Carica il contenuto di `welcomebook/` sopra i file vecchi, **senza toccare `app/storage/`**.
3. Apri il sito una volta. Le migrazioni `007`–`018` partono da sole.
4. In **Amministrazione → Diagnostica** tutte le righe devono essere «OK».

Il database resta **SQLite**. Le migrazioni nuove sono scritte anche per MySQL, ma un'installazione da zero su MySQL non è supportata: lo schema iniziale (`001`) è solo per SQLite.

## 2. Variabili

Si impostano come variabili d'ambiente o in `app/config.local.php`. Il modello è `config.local.esempio.php`.

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

**Cron (facoltativo).** Le email di richiamo partono anche senza cron, perché il controllo si fa mentre qualcuno usa il sito. Se il sito è poco visitato, aggiungi in cPanel un cron ogni ora:

```
wget -q -O- https://TUODOMINIO/cron/IL_TOKEN >/dev/null
```

## 3. Stripe

- **Webhook** su `https://TUODOMINIO/webhook/stripe` (oppure `…/index.php/webhook/stripe`). Eventi da inviare:
  - `checkout.session.completed`
  - `checkout.session.expired`
  - `invoice.paid`
  - `invoice.payment_failed`
  - `customer.subscription.updated`
  - `customer.subscription.deleted`
- Il webhook non serve solo a pubblicare: sblocca anche le strutture del Portfolio, comprese quelle aggiunte dopo.
- Attiva il **portale clienti** (fatture, carta, disdetta).
- Prova tutto in modalità test (`sk_test_…`, carta `4242 4242 4242 4242`) prima di passare alle chiavi live.
- **Codici sconto (fase 6E).**
  - Si creano in **Amministrazione → Codici sconto**. Ognuno diventa un coupon Stripe «una volta», quindi sconta solo il primo anno.
  - I codici creati prima di attivare Stripe restano «da sincronizzare» e non si possono usare: premi «Riprova la sincronizzazione» dall'elenco.
  - Prova in modalità test: crea un codice del 20%, applicalo, paga con `4242 4242 4242 4242`. La prima fattura deve essere scontata e il rinnovo a prezzo pieno.

## 4. Testi da rivedere (Amministrazione)

- **Pacchetti → testo**: titoli, descrizioni ed elenchi. Nell'elenco, una riga che finisce con «:» diventa il titoletto («Tutto di Essential, e in più:»).
- **Testimonianze**: aggiungine solo di vere, con il permesso scritto della persona, anche per la foto. Finché non ce n'è una visibile, il blocco in landing non compare.
- **FAQ** in landing: le sei risposte sono in `app/views/pub/home.php`. Falle rileggere insieme ai punti 6 e 7 qui sotto, soprattutto «Ricevo fattura?».
- **Termini e Privacy** (`/termini`, `/privacy`): vanno aggiornati con le novità (email di richiamo, dati di fatturazione, funnel anonimo), insieme alla versione in `MHW_TERMS_VERSION` / `MHW_PRIVACY_VERSION`.
- **Clienti di esempio**: sono account con password nota. Toglili prima di aprire al pubblico (Quadro → «Elimina i clienti di esempio»). Se tieni la demo pubblica, ricreali: la nuova demo è a Spello.

## 4b. La guida vetrina (la demo della landing)

La demo della landing si sposta su un account vero, quello dell'agenzia:
1. Registrati sul sito con **blackout.agency@gmail.com**, oppure usa l'account se c'è già.
2. Vai su **Amministrazione → Clienti**, apri quell'account e premi **«Crea la guida vetrina»**. Lascia spuntato «Concedi Plus dimostrativo per 12 mesi»: senza un piano la guida esce senza foto, senza inglese e senza luoghi.
3. Nasce «Casa dei Gerani», a Bevagna. È sulla falsariga di Casa Lucia, ma nomi, numeri e indicazioni sono diversi e tutti di fantasia. Viene pubblicata subito, con l'etichetta «Demo».
4. Da quel momento la landing mostra la vetrina come demo. La vetrina non occupa il posto della struttura del piano, e la modifichi dal pannello di quell'account.
5. Poi togli i clienti di esempio (**Amministrazione → Clienti → «Elimina i clienti di esempio»**). Hanno una password nota. L'eliminazione tocca solo gli account `@esempio.it`, non la vetrina.

## 5. Foto da caricare

Tutte in `assets/foto/`, con **questi nomi esatti**: si caricano sopra le vecchie, senza toccare il codice.

| File | Dove | Misura consigliata | Note |
|---|---|---|---|
| `scena-qr.jpg`, `scena-ospite.jpg`, `scena-host.jpg` | landing, fascia sotto l'hero | 1200×750 (16:10), JPG, ≤ 300 KB, soggetto al centro | **già caricate** (le foto che hai fornito). Per cambiarne una, carica il .jpg nuovo con lo stesso nome, poi **Diagnostica → «Rigenera le foto WebP»**: fino ad allora si vede il .jpg. Se una manca, compare un disegno su fondo colorato. Sul telefono la foto si ritaglia quadrata, al centro. |
| `borgo.jpg` | il borgo della landing | 2000×924 (circa 2,16:1) | poi **Diagnostica → «Rigenera le foto WebP»** (oppure `php app/tools/foto.php`) |
| `borgo-telefono.jpg` | il telefono nell'hero | 900×633 | **già aggiornata** con il portone di Casa Lucia. Se la cambi, rigenera come sopra |
| `casa.jpg`, `portone.jpg`, `osteria.jpg`, `caffe.jpg`, `gelato.jpg`, `soggiorno.jpg` | foto della demo | 1600 px sul lato lungo | se le cambi con foto di Spello, ricrea i clienti di esempio |
| `casa.jpg` | copertina della demo: splash e testa della guida | 1448×1086 | **già aggiornata** (il portone ad arco). Sul server **ricrea i clienti di esempio** per vederla: Quadro → «Elimina…», poi «Crea…» |
| `pannello-1.webp`, `-2`, `-3` | «Inizia in pochi minuti» | 1200×750 | sono schermate vere fatte in locale (finestra larga 1024 px, la seconda 1200 px per mostrare l'anteprima). Rifalle se il pannello cambia molto. |
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
