# MyHouse Welcome — applicazione

PHP 8.1+ e SQLite (o MySQL). **Nessuna dipendenza da installare**: niente
Composer, niente Node, niente SDK. Si carica via FTP, anche in una
sottocartella del dominio, e funziona.

Il percorso del cliente è uno solo:

```
Landing → Crea gratis → Registrazione → Scegli il piano (senza pagare)
→ Configura la guida → Anteprima → Pubblica → Stripe → webhook firmato
→ guida online → QR
```

Configurare è gratis. Si paga solo per pubblicare, con un abbonamento annuale.
La guida va online **solo** quando arriva il webhook firmato di Stripe: il
ritorno dal browser non attiva niente.

---

## Cosa fa

| | |
|---|---|
| Account | registrazione con accettazione dei Termini e presa visione della Privacy (versione e data salvate), verifica email obbligatoria prima di pagare, recupero password, limiti ai tentativi |
| Piani | Essential, Plus, Portfolio 2 e 3 letti dal database; prezzi e testi della landing modificabili dall'amministrazione; ogni modifica di prezzo o funzioni crea una **versione nuova**, chi ha già comprato resta sulla sua |
| Limiti del piano | verificati **sul server**: sezioni aggiuntive, lingue, immagini, PDF, immagine profilo, strutture |
| Procedura guidata | La tua struttura → Check-in & Check-out → Scegli le sezioni → Compila i contenuti → Lingue → Aspetto → Anteprima; salva a ogni passo, si riprende quando si vuole |
| Editor | campi veri per ogni tipo di sezione (passaggi numerati, elenchi, indirizzi, link), salvataggio mentre si scrive, anteprima nel telefono accanto |
| Sezioni | Check-in & Check-out sempre incluso e fuori dal conto; catalogo di 12 sezioni; ordinamento con Sposta su / giù; disattivare libera un posto |
| Consigli sul posto | nome, categoria, descrizione, indirizzo, minuti a piedi e in auto, Maps, telefono, sito, prenotazione, consiglio dell'host, etichetta editoriale, foto (Plus) |
| Lingue | italiano, inglese, francese, tedesco, spagnolo; traduzione **manuale**, affiancata all'originale; tutta l'interfaccia della guida è tradotta |
| Aspetto | sei palette curate, testo chiaro o scuro solo se il contrasto supera AA, logo, copertina, immagine profilo (Plus) |
| Media | su **Amazon S3** (firma SigV4 scritta a mano, niente SDK) o sul disco; tipo vero controllato dal contenuto, SVG rifiutati, PDF con JavaScript rifiutati, nomi non prevedibili per account e struttura |
| Pagamenti | Stripe Checkout in modalità abbonamento, rinnovo annuale, prezzi IVA esclusa, indirizzo di fatturazione e partita IVA, portale clienti, rinnovo automatico attivabile e disattivabile |
| Scadenza | a fine periodo pagato la guida va offline da sola (nessun cron): i dati restano, il QR resta valido e torna a funzionare al rinnovo |
| QR | permanente; PNG, SVG e PDF da stampare; link da copiare |
| Statistiche (Plus) | aperture, aperture dal QR, sezioni più lette, lingue; eventi anonimi, niente cookie |
| Amministrazione | quadro, clienti, abbonamenti con gli ID Stripe, guide, pacchetti e versioni, eccezioni per cliente, abbonamenti manuali, accesso come cliente tracciato (scade da solo dopo un'ora), registro, diagnostica |
| Guida ospite | non indicizzabile (`noindex`), nessun cookie, nessun codice di porte o cassette, commiato con le sole istruzioni scritte dall'host |

---

## Configurazione

**Nessun segreto va scritto nel codice o nel repository.** Tutto si legge
dalle variabili d'ambiente. Se l'hosting non permette di impostarle, copia
`config.local.esempio.php` in `config.local.php` (escluso dal repository),
accanto a `config.php`, e riempi solo le voci che servono.

| Variabile | A cosa serve |
|---|---|
| `MHW_BASE_URL` | indirizzo pubblico senza barra finale, es. `https://blackout.in/welcomebook` — finisce nei QR e nelle email, **impostalo** |
| `STRIPE_SECRET_KEY` | chiave segreta (`sk_live_…` o `sk_test_…`) |
| `STRIPE_PUBLISHABLE_KEY` | chiave pubblicabile (facoltativa: il checkout è ospitato da Stripe) |
| `STRIPE_WEBHOOK_SECRET` | segreto del webhook (`whsec_…`) |
| `STRIPE_AUTOMATIC_TAX` | `1` per far calcolare l'IVA a Stripe Tax (va attivato anche su Stripe) |
| `MHW_GRACE_DAYS` | giorni di tolleranza dopo un rinnovo non riuscito (predefinito 0) |
| `MAIL_TRANSPORT` | `smtp`, `mail` o `log` (predefinito: le email finiscono in `storage/logs/mail.log`) |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS`, `MAIL_ENCRYPTION` | server SMTP (`tls` = STARTTLS sulla 587, `ssl` sulla 465) |
| `MAIL_FROM`, `MAIL_FROM_NAME` | mittente |
| `MHW_STORAGE` | `s3` oppure `local` (predefinito) |
| `AWS_REGION`, `AWS_S3_BUCKET` | regione e bucket |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` | credenziali di un utente IAM con i soli permessi `s3:PutObject`, `s3:GetObject`, `s3:DeleteObject` su quel bucket |
| `AWS_S3_PUBLIC_URL` | facoltativo: indirizzo di un CDN (CloudFront) davanti al bucket; senza, le immagini usano URL prefirmati |
| `MHW_TERMS_VERSION`, `MHW_PRIVACY_VERSION` | versione dei testi legali: cambiandola, i nuovi consensi la registrano |
| `MHW_DEBUG` | `1` solo in sviluppo: mostra i dettagli degli errori |

### Stripe, passo per passo

1. In Stripe, **Sviluppatori → Webhook → Aggiungi endpoint** con l'indirizzo
   completo, sottocartella compresa: `https://tuodominio/welcomebook/webhook/stripe`
   (se il server non riscrive gli indirizzi: `…/welcomebook/index.php/webhook/stripe`).
2. Eventi da inviare: `checkout.session.completed`, `checkout.session.expired`,
   `invoice.paid`, `invoice.payment_failed`, `customer.subscription.updated`,
   `customer.subscription.deleted`.
3. Copia il segreto del webhook in `STRIPE_WEBHOOK_SECRET` e la chiave segreta in
   `STRIPE_SECRET_KEY`. Senza tutte e due nessuno può pubblicare: i clienti
   preparano la guida, e il pannello dice che i pagamenti non sono ancora attivi.
4. Per il **portale clienti** (fatture, carta, disdetta) attivalo in
   **Impostazioni → Fatturazione → Portale clienti**.
5. I prezzi: di base l'applicazione descrive a Stripe il prezzo della versione
   del listino (annuale, IVA esclusa). Se preferisci prezzi creati su Stripe,
   crea un prezzo **ricorrente annuale** con lo stesso importo e incolla il suo
   `price_…` nella nuova versione del pacchetto, da **Amministrazione → Pacchetti**.
6. Prova tutto in modalità test (`sk_test_…`, carta `4242 4242 4242 4242`) prima
   di passare alle chiavi live.

### Amazon S3

Crea un bucket **privato** (Blocco dell'accesso pubblico attivo) nella regione
che preferisci, un utente IAM con la policy qui sotto, e imposta le variabili.
Le immagini si vedono con URL prefirmati che scadono dopo un'ora; se metti
CloudFront davanti, imposta `AWS_S3_PUBLIC_URL`.

```json
{ "Version": "2012-10-17", "Statement": [{ "Effect": "Allow",
  "Action": ["s3:PutObject", "s3:GetObject", "s3:DeleteObject"],
  "Resource": "arn:aws:s3:::NOME-BUCKET/*" }] }
```

Le immagini già caricate sul disco prima del passaggio a S3 continuano a
funzionare: ogni file ricorda dove è stato salvato.

---

## Installazione

### In una sottocartella (es. `dominio.it/welcomebook/`)

```
./costruisci-pacchetto.sh welcomebook      → dist/myhouse-welcome-welcomebook.zip
```

Carica il contenuto dello zip nella sottocartella:

```
public_html/
  welcomebook/
    index.php  .htaccess  web.config  assets/  controllo.php
    app/       <- src, views, lang, migrations, config.php, storage
```

L'applicazione si accorge da sola della sottocartella: tutti gli indirizzi,
il QR e il webhook prendono il prefisso giusto. Per un dominio dedicato in
futuro (`welcome.myhouse.it`) basta cambiare `MHW_BASE_URL`; i QR già stampati
puntano a `/q/{token}`, quindi conviene lasciare un reindirizzamento dal vecchio
indirizzo al nuovo.

> In questa disposizione la protezione della cartella `app/` dipende da
> `.htaccess`, che nginx non legge e che Apache ignora con `AllowOverride None`.
> Dopo l'installazione apri **Amministrazione → Diagnostica**: il server prova a
> scaricare il proprio database e ti dice se ci riesce. La disposizione più
> sicura resta quella con il dominio che punta su `public/` e il resto fuori.

Se gli indirizzi danno 404, il server non riscrive gli URL: usa
`dominio.it/welcomebook/index.php/installa` e tutto prosegue così.

### Poi

1. Apri l'indirizzo: ti porta a `/installa`.
2. Scegli email e password dell'amministratore. Il database si crea da solo.
3. Se vuoi, spunta **«Crea anche la demo e tre clienti di esempio»**.
4. Permessi di scrittura a `app/storage/` e `app/storage/uploads/` (755 o 775).
5. Cancella `controllo.php` dal server quando tutto funziona.

### Aggiornare un'installazione esistente

Carica i file nuovi sopra i vecchi **senza toccare `app/storage/`**. Alla prima
richiesta le migrazioni partono da sole, una volta sola, dentro una transazione:
niente di venduto o scritto si perde, i QR stampati restano validi, le versioni
di pacchetto già vendute tengono le loro funzioni. I vecchi codici di porte e
cassette vengono **cancellati**, anche dalle guide già pubblicate.

### I clienti di esempio

| Chi | Entra con | Piano | Cosa mostra |
|---|---|---|---|
| Lucia Ferrante | `lucia@esempio.it` | Plus | Casa Lucia, Montepulciano — pubblicata, è la **demo** della landing |
| Marco Bevilacqua | `marco@esempio.it` | Portfolio 2 | B&B Le Rondini pubblicata, Casa sul Mare in bozza |
| Agnese Ruta | `agnese@esempio.it` | Essential | Il Cortile, in bozza |

Password: `dimostrazione1`. Hanno abbonamenti «dimostrativi» che non entrano
nell'incasso. **Toglili prima di aprire al pubblico**: sono account veri con una
password scritta qui. Si eliminano da **Amministrazione → Quadro**.

---

## Indirizzi

| Indirizzo | Cosa |
|---|---|
| `/` | landing con i piani |
| `/registrati`, `/accedi`, `/password/dimenticata` | account |
| `/piano` | scelta del piano, senza pagare |
| `/pannello` | le guide; `/pannello/{id}` contenuti, `/procedura/{passo}` la procedura guidata |
| `/pannello/{id}/anteprima` | l'anteprima, visibile solo all'host |
| `/account` | piano, rinnovo, pagamenti, portale Stripe |
| `/g/{slug}/benvenuto`, `/g/{slug}`, `/g/{slug}/{id}`, `/g/{slug}/commiato` | la guida degli ospiti |
| `/q/{token}` | l'indirizzo dietro il QR — **non cambia mai** |
| `/admin` … | quadro, clienti, abbonamenti, guide, pacchetti, registro, diagnostica |
| `/webhook/stripe` | solo per Stripe: niente sessione né CSRF, firma verificata |

---

## Come è fatto

```
app/
  config.php               configurazione, tutta da variabili d'ambiente
  config.local.esempio.php modello per gli hosting senza variabili d'ambiente
  migrations/              001 schema, 002 MVP, 003 listino e sezioni (PHP)
  lang/                    dizionari della guida: it, en, fr, de, es
  src/                     il codice: una classe per file, caricate da sole
  views/                   le pagine
  prove/                   le prove automatiche (non vanno sul server)
  storage/                 database, file caricati, registri
public/                    index.php, .htaccess, web.config, assets/
```

## Scelte da conoscere

- **Il webhook è l'unica fonte di verità.** L'evento si segna come elaborato
  nella stessa transazione che lo applica: se qualcosa fallisce, torna tutto
  indietro e Stripe lo rimanda. Lo stesso evento due volte non fa niente.
- **Un abbonamento vale finché è pagato e il periodo non è finito.** La data
  conta anche da sola: se un webhook si perde, alla scadenza la guida va offline
  comunque.
- **Una versione di pacchetto venduta non si modifica.** Prezzo o funzioni nuove
  = versione nuova.
- **Prima di pagare valgono le regole del piano scelto**, così la configurazione
  è già quella che si comprerà.
- **Gli ospiti leggono un'istantanea.** Le modifiche si vedono quando l'host
  ripubblica; con l'abbonamento attivo ripubblicare non costa niente.
- **Niente traduzione automatica.** Le lingue le scrive l'host; dove manca una
  traduzione l'ospite legge la lingua principale.
- **Niente codici di accesso nella guida.** Si mandano all'ospite in privato.

---

## Provarla

```
app/prove/esegui.sh                      # 227 prove via HTTP, con Stripe e S3 finti
MHW_STORAGE=local app/prove/esegui.sh    # le stesse, con i file sul disco
app/prove/aggiornamento.sh               # aggiorna un'installazione della versione precedente
php app/prove/firma-s3.php               # firma S3 contro i vettori ufficiali di AWS
```

`esegui.sh` schiera l'applicazione come sull'hosting, avvia uno Stripe finto e
un bucket S3 finto, e prova: installazione, landing e listino, registrazione e
consensi, verifica email, procedura guidata, limiti di Essential, Plus e
Portfolio lato server, caricamenti rifiutati (SVG travestiti, PDF con
JavaScript), pubblicazione con Stripe, webhook firmati, sbagliati, vecchi e
ripetuti, rinnovo, scadenza, pagamento fallito, chiusura, QR in tre formati,
accesso ai dati di altri clienti, CSRF, recupero password, amministrazione,
demo in più lingue.

**Cosa le prove non coprono**: Stripe e S3 veri. I servizi finti controllano
cosa l'applicazione manda e come reagisce, non il comportamento reale di Stripe
o AWS. Prima di aprire al pubblico fai un giro completo in modalità test di
Stripe e con un bucket vero.

---

## Com'è fatta da vedere

La direzione è **Fauna**: fotografie a tutta larghezza, riquadri di colore
pieno, **Gloock** sui titoli e **Onest** sul resto, angoli morbidi, tutto quello
che si tocca alto almeno 44 px. Una grana di sabbia leggera corre sotto le
superfici del sito e della guida, mai sotto bottoni, fotografie, testo o icone;
nel pannello di lavoro è tolta dai componenti operativi, per leggere meglio.
Le barre di navigazione sono vetro al 40% con sfocatura. C'è un interruttore
chiaro/scuro; nella guida il tema di partenza lo sceglie l'host con la palette.
