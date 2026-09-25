# Arco del Vento — sito

Prototipo del sito di **Arco del Vento di Pecetta Daniele**, affittacamere in
Via Santa Maria delle Rose 1/A, ad Assisi. Cinque camere, gestite dal titolare
dal 2002.

L'impianto viene dal progetto grafico **«Assisi · Anfiteatro Romano»** —
schede avorio con il filo sabbia, pulsanti a pillola, testata in vetro che
resta in alto — vestito con il logotipo di Arco del Vento: il logotipo al
centro della testata, il bruno della rosa dei venti per il testo, il mattone
di «arco del vento» per pulsanti, filetti e piè di pagina; Prata per i titoli
e Allura, la mano di «Assisi», per l'ultima parola di ogni titolo; Sora per il
testo. Del progetto si è preso il disegno, non i contenuti: nomi, dati e
fotografie sono quelli di Arco del Vento.

---

## Come avviarlo

Serve PHP 8.1 o superiore. Nient'altro: niente Composer, niente Node, niente
passo di compilazione.

```bash
sh tools/serve.sh          # http://localhost:8080
```

Il file `.env` non è obbligatorio per far girare il prototipo. Se lo vuoi:

```bash
cp .env.example .env
```

---

## Che cosa c'è e che cosa no

**Funziona davvero**: navigazione, otto pagine per lingua, italiano e inglese,
le cinque pagine camera, il percorso di prenotazione completo in quattro passi
con convalida e riepilogo, il modulo dei contatti con convalida e trappola per
i robot, il menu su schermo stretto, `sitemap.xml` e `robots.txt` generati,
i dati strutturati, le fotografie che si alternano in apertura (con il
pulsante per fermarle), il carosello delle camere.

**È reale**: tutto quello che il titolare ha dichiarato nell'intervista —
tariffe, tipologie, letti e occupazione delle cinque camere; telefono,
WhatsApp ed e-mail; partita IVA; orari di check-in e di reperibilità; il
piano, le scale e l'assenza di ascensore; la tassa di soggiorno; la regola
del sabato; colazione e pasti (non ci sono); Wi-Fi, bollitore, ventilatori,
minibar su richiesta, animali, divieto di fumo; l'apertura tutto l'anno. Le
fotografie di quattro camere su cinque e del corridoio. E il fatto che dalle
finestre si veda San Rufino.

**È dimostrativo**: la disponibilità — cioè quali date risultino libere — e
le metrature. Il provider di prenotazione calcola i totali con i prezzi veri
e applica le regole vere di soggiorno minimo, ma il calendario se lo inventa.

**È una scelta del cliente**: i prezzi non compaiono fuori dal percorso di
prenotazione (`show_prices_publicly => false`). Si vedono al passo «camere»,
dopo le date. Ha un costo — chi confronta strutture a colpo d'occhio se ne va
prima — ed è ribaltabile con una riga in `content/settings.php`.

**Non c'è**: nessuna credenziale, nessun pagamento, nessun invio di posta
vero, nessun collegamento a un gestionale.

---

## Tecnologia

PHP 8.1+ senza framework, viste in PHP puro, CSS e JavaScript scritti a mano.
Nessuna dipendenza esterna, nessun `node_modules`, nessuna compilazione: la
cartella si carica via FTP e il sito è in piedi.

La scelta è dettata da dove finirà: hosting condiviso Hostinger, cioè Apache
o LiteSpeed con PHP e MySQL. Un sito di otto pagine che cambia i contenuti due
volte l'anno non ha bisogno di più di così, e ogni dipendenza in meno è una
cosa in meno da aggiornare fra tre anni.

I tre caratteri — Prata e Allura, quelli del logotipo, e Sora per il testo,
tutti con licenza SIL Open Font — sono serviti dalla nostra cartella in
`.woff2`, sottoinsiemi `latin` e `latin-ext` (Prata esiste solo in `latin`,
che copre già le accentate dell'italiano). Nessun dominio terzo vede l'indirizzo IP di chi legge, e il sito
funziona anche senza rete verso Google.

---

## Struttura

```
config/       configurazione, legge .env e l'ambiente del server
content/      i contenuti: camere, impostazioni, domande, luoghi, lingue
database/     schema.sql e seed.sql (generato)
docs/         guida alla pubblicazione, originali delle fotografie
public/       LA SOLA CARTELLA DA ESPORRE AL WEB
  index.php   punto d'ingresso unico
  .htaccess   indirizzi puliti, compressione, cache, sicurezza
  assets/     css, js, caratteri, immagini
src/          l'applicazione
  App.php     il contenitore: sceglie repository, provider e trasporto posta
  Booking/    il confine verso chi tiene il calendario
  Controller/ pagine, prenotazione, contatti, sitemap
  Http/       richiesta, risposta, router
  I18n/       mappa degli indirizzi e traduzioni
  Mail/       il confine verso la posta
  Repository/ le camere, da file o da MySQL
  Support/    escape, convalida, gettone CSRF, presentazione delle camere
  View/       il motore di viste
storage/      registri e posta scritta su file (non versionati)
tools/        script: segnaposto, fotografie, seed, pacchetto, server di sviluppo
views/        telaio, frammenti, componenti, pagine
```

---

## Pagine

| Pagina | Italiano | Inglese |
| --- | --- | --- |
| Home | `/it/` | `/en/` |
| Camere | `/it/camere` | `/en/rooms` |
| Camera | `/it/camere/camera-01` … `-05` | `/en/rooms/room-01` … `-05` |
| La struttura | `/it/la-struttura` | `/en/the-house` |
| Assisi a piedi | `/it/assisi` | `/en/assisi` |
| Informazioni | `/it/informazioni` | `/en/information` |
| Prenota | `/it/prenota` | `/en/book` |
| Contatti | `/it/contatti` | `/en/contact` |
| Privacy | `/it/privacy` | `/en/privacy` |

`/` reindirizza alla lingua del browser, o all'italiano. `sitemap.xml` e
`robots.txt` sono generati. Il 404 resta nella lingua dell'indirizzo sbagliato.

---

## Componenti

Tre fogli, tutti in `public/assets/css/`: `fonts.css` (i caratteri),
`tokens.css` (colori, raggi, ombre, spazi — scritto a mano, con in fondo i
vecchi nomi che usa l'area riservata) e `site.css` (tutto il resto).

In `views/`: telaio di pagina, riga bruna in cima, testata a pillola con il
marchio al centro (rosa dei venti e logotipo, `components/logo.php`), menu a
tutta pagina, cambio lingua, fotografie che si alternano, barra di
prenotazione, scheda camera (la stessa per il carosello, l'elenco e le camere
libere), elenco numerato, pannello mappa, domande, chiusura in terracotta con
il piè di pagina, pillola in fondo allo schermo su mobile, campo di modulo,
riepilogo prenotazione, riga di contatto, immagine, marcatore «da confermare».

Aiuti per le viste in `src/Support/helpers.php`: `titolo()` (l'ultima parola
in Allura, color mattone; «|» nel testo va a capo), `occhiello()`, `righe()`,
`icona()` (tracciati Lucide, in linea), `mappa()` (link a Google Maps).

Movimento: entrate e parallasse sono CSS legato allo scorrimento
(`animation-timeline: view()`), solo traslazioni — mai da opacità zero — e
spente per chi chiede meno movimento. Il JavaScript (`site.js`) aggiunge e non
regge: senza, la prima fotografia resta ferma, le camere scorrono con il dito
e il menu diventa una riga di voci in chiaro.

---

## Prenotazione

```
interfaccia  →  BookingService  →  BookingProviderInterface
                                        ↑
                          DemoBookingProvider (oggi)
                          il tuo gestionale    (domani)
```

`DemoBookingProvider` non è un calendario: è deterministico — la stessa camera,
nella stessa notte, dà sempre la stessa risposta — perché con una
disponibilità casuale il percorso non si potrebbe provare. Circa una notte su
cinque risulta occupata, così si vede anche il caso «non c'è posto», che è
metà del lavoro di un motore di prenotazione.

**Per collegare un provider vero**: scrivi una classe che implementi
`BookingProviderInterface` (tre metodi: `search`, `quote`, `request`),
aggiungila al `match` in `App::bookingProvider()` e cambia `BOOKING_PROVIDER`
nel `.env`. Nessuna vista, nessun modulo e nessun indirizzo cambiano.

I primi tre passi vivono negli indirizzi e non nella sessione: un risultato di
ricerca si può salvare, rimandare a qualcuno e ricaricare, e il tasto
«indietro» fa quello che promette.

---

## Tariffe

Non sono stagionali: **dipendono da quante persone dormono in camera**. È così
che il titolare le ha date, ed è anche come funzionano davvero — una
matrimoniale occupata da una persona sola costa meno.

| Camera | Tipologia | 1 ospite | 2 ospiti | 3 ospiti |
| --- | --- | ---: | ---: | ---: |
| 01 | Tripla | € 100 | € 110 | € 130 |
| 02 | Doppia, letti separabili | € 80 | € 90 | — |
| 03 | Matrimoniale | € 70 | € 80 | — |
| 04 | Matrimoniale | € 70 | € 80 | — |
| 05 | Singola con letto matrimoniale | € 70 | — | — |

Stanno in `content/rooms.php` sotto `rates`, una voce per numero di ospiti.
Dove una voce manca, la camera non ospita quel numero di persone e il motore
non la propone: le due cose sono lo stesso dato.

Su MySQL diventano la tabella `room_rates`, una riga per camera e per
occupazione. `seasonal_rates` è pronta per le variazioni di periodo, con una
colonna `guests` per poterle fare anche per occupazione.

**Soggiorno minimo**: una notte, tranne il sabato, che non si prenota da solo
— chi arriva di sabato resta almeno due notti. Sta in `stay.min_nights`
(`default` 1, `saturday` 2) e lo applica `BookingService`, che per un
soggiorno comprendente un sabato prende il maggiore fra i due. Nei periodi di
punta — Ferragosto, 4 ottobre, Capodanno — il titolare ha detto che il minimo
sale, ma non di quanto: `peak` resta `null` e non viene imposto nulla.

**Tassa di soggiorno**: 3 € a persona per notte, per le prime tre notti,
esenti i minori di 12 anni, si paga al check-in. Il preventivo la mostra a
parte, mai dentro il totale della camera. Non chiedendo l'età degli ospiti, il
sito calcola il caso peggiore (tutti paganti) e lo dice.

## E-mail

```
modulo  →  controller  →  MailerInterface  →  LogMailer | NativeMailer | SmtpMailer
```

Si sceglie con `MAIL_TRANSPORT` nel `.env`:

- `log` — scrive un `.eml` in `storage/mail/` e non spedisce niente. È il
  modo del prototipo: nessuna credenziale, nessun messaggio che parte per
  sbaglio verso un indirizzo di prova.
- `mail` — la funzione `mail()` di PHP. Funziona su Hostinger senza
  configurare nulla, ma i messaggi finiscono più spesso nella posta
  indesiderata perché non sono autenticati dal dominio.
- `smtp` — SMTP autenticato, **consigliato in produzione**: il messaggio parte
  dalla casella del dominio e passa i controlli SPF e DKIM. Su Hostinger
  `smtp.hostinger.com`, porta 587 con STARTTLS.

Quando il trasporto non spedisce davvero, il sito lo dice all'utente nella
pagina di conferma. Non finge.

---

## Database

Oggi il sito legge i contenuti da `content/*.php` e **non ha bisogno di un
database**: per cinque camere che cambiano due volte l'anno, un file di testo
si modifica e si carica via FTP.

Lo schema c'è per il giorno in cui servono disponibilità reali, tariffe
stagionali e uno storico. Tredici tabelle in `database/schema.sql`:
`site_settings`, `pages`, `translations`, `rooms`, `room_translations`,
`amenities`, `room_amenities`, `room_images`, `prices`, `seasonal_rates`,
`availability`, `guests`, `bookings`, `reviews`, `contact_requests`.

**Per attivarlo**: crea database e utente dall'hPanel, importa `schema.sql` e
poi `seed.sql`, compila `DB_DSN`, `DB_USER`, `DB_PASSWORD` nel `.env`.
`App` passa da solo a `PdoRoomRepository`, che restituisce esattamente la
stessa forma di dati: nessuna vista cambia.

`seed.sql` si rigenera dai file con `php tools/export-seed.php`.

---

## Lingue

Italiano e inglese, completi: 460 chiavi per lingua, in parità.

Le pagine non sono duplicate. Esiste una vista per pagina; gli indirizzi per
lingua stanno in `src/I18n/Routes.php` e i testi in `content/lang/`. Una
chiave che manca ricade sull'italiano, così un sito tradotto a metà resta
usabile invece di mostrare le chiavi.

Ogni pagina dichiara `hreflang` per ogni lingua più `x-default`, e il cambio
lingua porta alla **stessa pagina** nell'altra lingua, non alla home.

**I contenuti sono due cose diverse, e si scrivono in due posti.** I *dati*
stanno in `content/settings.php` e non hanno lingua: un orario, un numero, un
booleano, un prezzo. La *prosa* ha lingua, anche quando descrive un dato — com'è
fatta la scala, dove si parcheggia, come stanno gli animali. Quella si scrive
per lingua, dentro lo stesso file:

```php
'stairs' => [
    'it' => 'due rampe, circa 10 scalini e poi 3',
    'en' => 'two flights: about 10 steps, then 3',
],
```

e le viste la leggono con `testoLocale()`. Se la lingua corrente manca, la
funzione restituisce `null` e la riga diventa `[da confermare]`: **non ricade
sull'italiano**, perché una riga assente è onesta e una riga nella lingua
sbagliata sembra un guasto del sito. Una stringa semplice, senza chiavi di
lingua, vale per tutte: si usa solo per ciò che non si traduce — «60 Mbps», il
nome di una piazza.

**Per aggiungere lo spagnolo**: gli indirizzi (`/es/habitaciones`,
`/es/la-casa`, `/es/reservar`…) e gli slug delle camere sono già scritti.
Servono tre passi:

```bash
cp content/lang/es.php.example content/lang/es.php
# tradurre (la struttura delle chiavi è identica a it.php)
# poi nel .env:  APP_LOCALES=it,en,es
```

E poi la quarta: aggiungere `'es' => …` alle voci di prosa in
`content/settings.php` — parcheggio, scale, animali, minibar, accessibilità,
mesi tranquilli, date di punta, prezzo del parcheggio. Sono otto.

---

## Hostinger

**Già pronto**: PHP senza dipendenze né compilazione, MySQL previsto,
`.htaccess` con indirizzi puliti, compressione, cache e regole di sicurezza,
variabili d'ambiente da `.env` o dal pannello, caratteri e immagini serviti
in locale, caricamento via FTP.

**Due modi di caricarlo**, in ordine di preferenza:

1. Punta il dominio su `public/` dall'hPanel (Avanzate → Cambia cartella
   radice del sito). Le cartelle dell'applicazione restano fuori dal web.
2. Carica tutto dentro `public_html/`. L'`.htaccess` nella radice manda le
   richieste in `public/` e blocca `src`, `views`, `content`, `config`,
   `storage`, `database`, `tools`, `docs`.

**Funziona anche in una sottocartella**, ed è così che gira in prova:
`blackout.in/assisiapartment/`. La cartella il sito la legge da `APP_URL`
(`https://blackout.in/assisiapartment`) e la mette davanti a ogni link,
reindirizzamento e file statico; gli `.htaccess` non scrivono mai un percorso
che cominci con «/». Per passare al dominio vero si cambia il `.env`, non i
file. La copia di prova sta fuori dai motori di ricerca (`APP_NOINDEX=true`)
e non spedisce posta (`MAIL_TRANSPORT=log`).

```bash
php tools/build-release.php --prova=https://blackout.in/assisiapartment
```

prepara il pacchetto e, accanto, un `env-prova.txt` già pronto. Il pacchetto
**si ferma se una pagina, resa come in una sottocartella, chiede anche un solo
file fuori dalla sua cartella**: è l'errore più facile da reintrodurre, e con
il server di sviluppo alla radice non si vede.

**La procedura completa è in [`docs/DEPLOY-HOSTINGER.md`](docs/DEPLOY-HOSTINGER.md)**:
dove mettere i file, che cosa non caricare, il `.env` di produzione, la posta,
il certificato, e i sintomi dei guai più comuni.

Prima di pubblicare, sul server:

```bash
php tools/preflight.php
```

Controlla versione di PHP ed estensioni, `.env` e ambiente, cartelle
scrivibili, cartelle esposte al web, riscritture, database, posta, materiali e
sessione. Dice che cosa manca e come rimediare, ed esce con codice 1 se c'è un
problema bloccante.

**Provato con Apache vero** (2.4, mod_rewrite, mod_php), non solo con il
server di sviluppo di PHP — che gli `.htaccess` non li legge: alla radice di un
dominio e in `/assisiapartment/`, con un sito diverso nella radice. Tutte le
pagine, i reindirizzamenti, i file protetti (`.env`, `src/`, `content/` danno
403), la prenotazione fino alla conferma, il modulo contatti, il menu, il cambio
lingua e la pagina d'errore. Quella prova ha trovato due errori che il server
di sviluppo nascondeva: `/it/` veniva rimandato a `/it`, diverso dall'indirizzo
dichiarato in canonical, e una barra finale con una query dava 403.

Il prototipo **non è stato caricato** su Hostinger da qui: il caricamento lo
fai tu, via FTP.

---

## Area riservata

**`/admin`** — sul sito di prova `https://blackout.in/assisiapartment/admin`.
Un solo account, protetto da password. Da lì si modifica il sito senza toccare
un file e senza FTP:

| Sezione | Che cosa |
| --- | --- |
| Bacheca | richieste nuove e l'elenco di quello che sul sito è ancora «da confermare», CIN in testa |
| Richieste | le prenotazioni e i messaggi arrivati dal sito, con stato: nuova, letta, confermata, rifiutata, archiviata |
| La struttura | contatti, orari, soggiorno minimo, tassa, dotazioni, obblighi di legge, recensioni, social, coordinate |
| Camere e tariffe | nome, metratura, piano, vista, tariffa per numero di ospiti, descrizione della fotografia |
| Testi del sito | tutti i testi in italiano e inglese, pagina per pagina, con l'originale accanto |
| Domande frequenti | aggiungere, modificare, riordinare, togliere |
| Account | cambiare la password |

**Dove vanno le modifiche.** In `storage/data/`, non in `content/`. I file di
`content/` restano il punto di partenza, sotto controllo di versione; quello che
si cambia dal pannello si sovrappone (`src/Storage/ContentOverrides.php`) e vince.
Così ricaricare il sito via FTP non cancella mai una modifica, e «torna al testo
originale» vuol dire togliere la modifica. Niente database: file JSON scritti in
modo atomico, con un lucchetto, e con le ultime trenta versioni di ogni archivio
nello storico — dal pannello si rimette una versione precedente con un click.

**Che cosa non si cambia da lì.** Tipologia, letti e occupazione massima delle
camere (da quelli dipende il motore di prenotazione) e le fotografie.

**Sicurezza.**
- *La prima volta* `/admin` chiede di creare l'account, ma solo con il codice
  `ADMIN_SETUP_TOKEN` del `.env`: chi lo conosce ha già i file del sito. Senza
  codice la creazione è chiusa — nessuno arriva per primo su un sito appena
  caricato e si prende il pannello. Creato l'account, il codice si toglie.
- La password sta solo come hash (`password_hash`). Cinque tentativi sbagliati
  dallo stesso indirizzo in un quarto d'ora chiudono l'accesso a
  quell'indirizzo per un quarto d'ora; gli indirizzi si salvano come impronta.
  Il tempo di risposta non rivela se il nome utente esiste.
- Ogni modulo porta il gettone anti-CSRF; la sessione si rinnova all'ingresso,
  scade dopo due ore di inattività e comunque dopo dodici.
- Ogni pagina del pannello è `no-store`, `noindex`, non incorniciabile.
- `storage/` ha il suo `.htaccess` che la chiude anche se mancasse quello
  della radice.
- Le sessioni stanno in `storage/sessions`, non nella cartella di PHP: su
  blackout.in quella non conservava i dati tra una pagina e l'altra, e ogni
  modulo del sito — area riservata, prenotazione, contatti — rispondeva
  «sessione scaduta».
- *Password dimenticata*: via FTP si cancella `storage/data/admin.json` e si
  rifà la prima volta con il codice. Modifiche e richieste restano.

**Le richieste** arrivano sempre anche per e-mail. Nel pannello si cancellano da
sole dopo 24 mesi: sono dati personali, e lo stesso periodo va scritto
nell'informativa privacy.

---

## Contenuti da confermare

Il sito non inventa: dove un dato manca lo marca `[da confermare]` e lo mostra
così all'ospite. Dopo l'intervista al titolare la lista si è accorciata molto
— restano diciannove campi vuoti su settantotto.

**Servono per obbligo di legge**:

- **CIN** — il Codice Identificativo Nazionale. Va esposto nel sito e negli
  annunci: senza, la sanzione parte da 800 €. È il buco più grave. Compare in
  fondo a ogni pagina come `CIN [da confermare]` accanto alla partita IVA, che
  invece c'è (03323290548).
- CIR e REA, se la struttura ne ha
- il CAP di via Santa Maria delle Rose
- il testo completo dell'informativa privacy: tempi di conservazione,
  responsabile del trattamento, destinatari dei dati

Il codice fiscale del titolare **non è nel repository**: sta in `.env`, sotto
`OWNER_TAX_CODE`, che non è versionato. `content/settings.php` lo legge da lì
con `tax_code_public => false`, e nessuna vista lo stampa in nessuna lingua.
Per una ditta individuale la partita IVA basta, il codice fiscale di una
persona è un dato personale, e da una cronologia git non si cancella più
niente.

**Servono per primi** — l'ospite li cerca prima di prenotare:

- **orario di check-out**: il check-in è confermato (13:00–20:00, di persona),
  il check-out no
- **pagamento, caparra e disdetta**: come si paga, se serve un anticipo, entro
  quando si annulla senza penale
- **politica sui bambini**: culle, letti aggiunti, sconti per età
- **pulizia e cambio biancheria** durante il soggiorno
- **lingue parlate** dal titolare
- di quanto sale il soggiorno minimo nei tre periodi di punta

**Servono per la pagina camere**:

- **la fotografia della Camera 01**, la tripla: è la più cara del listino, è
  quella con la vista, ed è l'unica rimasta senza. Il posto è pronto e la
  scheda è completa in tutto il resto.
- metratura ed esposizione di ciascuna camera (il piano è confermato: secondo)
- i due scatti che risultano inviati ma non arrivati sul disco:
  `san-rufino-finestra` e `piazza-san-rufino`

**Da chiarire, perché due dati confermati non tornano** — vedi «Limiti noti».

**Utili**:

- coordinate della casa per la mappa (`geo.latitude`, `geo.longitude`)
- i **tempi a piedi verificati** verso i sei luoghi. Assisi è in salita: una
  stima piatta presa da una mappa sottostima il percorso, quindi restano vuoti
  finché qualcuno non li cammina.
- il **numero e il voto delle recensioni** Booking e Google, che il titolare
  ha dato per «in verifica». Si inseriscono dall'area riservata, sezione «La
  struttura», appena verificati; fino ad allora il blocco non compare:
  meglio assente che approssimato.
- profili Instagram e Facebook, se esistono
- prezzo e distanza del parcheggio di via dell'Eremo (quello di piazza
  Matteotti è confermato: circa 18 € per 24 ore, 200 m)

---

## Fotografie

**Quattro camere su cinque sono fotografate**, e le fotografie sono della
casa: le ha fornite il titolare, che ha anche confermato quale fotografia
appartiene a quale camera. Con loro è arrivata la conferma della cosa che
questo sito racconta — dalle finestre delle camere si vede il campanile della
**Cattedrale di San Rufino**, e da una si vede la facciata.

Ci sono anche il corridoio con la **rosa dei venti intarsiata nel pavimento**
— la stessa del marchio, e messa lì molto prima del sito — e tre vedute di
Assisi su licenza Unsplash (Niels Baars, Gary Walker-Jones, Alessandro
Guarino), con i crediti nel piè di pagina.

L'accoppiamento fotografia-camera è confermato dal titolare: il nome del file
è il numero della camera. La **Camera 01** — la tripla — è l'unica ancora
senza fotografia e tiene il segnaposto disegnato. Il sito lo dice, non lo
nasconde.

### Come si lavorano

Gli originali stanno in `docs/foto-originali/`, fuori dalla cartella
pubblica. Da lì si ricavano tutti i tagli del design system:

```bash
php tools/build-photos.php
```

Lo script taglia a 4:3, 3:2, 16:9, 1:1 e 3:4 tenendo il punto che conta —
nelle camere la finestra, nel corridoio la rosa dei venti — e scrive `.webp`
con il ripiego `.jpg`, due misure ciascuno per il `srcset`, restando dentro i
tetti di peso del manuale. Non corregge il colore e non ingrandisce mai oltre
i pixel dell'originale.

Per sostituire o aggiungere una fotografia: il file in `docs/foto-originali/`
con il nome giusto, poi si rilancia lo script. Nel sito non cambia
nient'altro. `docs/foto-originali/README.md` ha la tabella dei nomi attesi.

## Limiti noti

- Il motore di prenotazione è finto e va sostituito prima di aprire al
  pubblico. Finché è quello, il sito lo dichiara in ogni passo.
- Nessuna e-mail parte davvero: `MAIL_TRANSPORT=log`.
- Nessuna recensione riportabile: il titolare ha dato numero e voto per «in
  verifica». Il blocco recensioni (in home, sotto Daniele: non in apertura)
  compare quando dall'area riservata si inseriscono punteggio e numero di una
  piattaforma; fino ad allora non c'è.
- **Due dati confermati non tornano, e li deciderà il titolare.**
  *La vista*: dalle fasce di prezzo dell'intervista risulta che solo la 01 e
  la 02 guardano piazza San Rufino, e così sta nei file. Ma le fotografie
  della 03 e della 04 mostrano il campanile dalla finestra. O è una vista
  minore — il campanile di scorcio, non la piazza — oppure l'accoppiamento
  fotografia-camera va rivisto.
  *La tripla a tre ospiti*: il primo listino diceva 120 €, l'intervista 130 €.
  Vale 130, perché l'intervista è più recente ed esplicita; basta una riga per
  tornare indietro.
- Nessuna mappa: senza le coordinate della casa, un segnaposto messo a occhio
  manda l'ospite alla porta di un altro.
- Manca il CIN, ed è un obbligo di legge: va messo prima di pubblicare.
- I prezzi non si vedono fuori dal percorso di prenotazione, per scelta del
  cliente. È un freno reale alla conversione, non un dettaglio di stile.
- La Camera 01, la tripla, non ha fotografia: tiene il segnaposto disegnato.
- Le tre vedute di Assisi si ripetono fra le pagine.
- L'informativa privacy è impostata ma non è un documento legale finito.
- Il calendario non è ancora collegato a Booking: l'area riservata mostra le
  richieste, ma le date libere del sito restano dimostrative.
- Le fotografie non si caricano dall'area riservata: si aggiungono via FTP con
  `tools/build-photos.php`.

---

## Verifiche fatte

- Tutti gli indirizzi nelle due lingue rispondono, senza errori né avvisi PHP.
- Percorso di prenotazione completo: ricerca, camere libere, scelta, dati,
  richiesta, conferma; Post/Redirect/Get, gettone CSRF, date non valide,
  soggiorno minimo e massimo, nessuna disponibilità con proposta delle date
  libere più vicine, gruppo troppo numeroso per ogni camera.
- Modulo contatti: convalida, trappola per i robot, gettone, e-mail scritta
  nel registro con `Reply-To` all'ospite.
- Gerarchia dei titoli senza salti in tutte le pagine, un solo `h1`, nessun
  `id` duplicato, ogni campo con la sua `label`, `canonical` e `hreflang`
  ovunque.
- Contrasto misurato su ogni coppia testo/fondo usata, nei due temi: tutte
  sopra 4,5:1 (3:1 per bordi e anelli di fuoco).
- Nessun trabocco orizzontale a 1440, 1280, 1024, 768 e 390 px.
- Bersagli da toccare sopra i 24px, con due sole eccezioni, entrambe previste
  dalla norma: la trappola per i robot, che nessuno può raggiungere, e il link
  all'informativa dentro la frase del consenso.
- **Regola del sabato** provata sui quattro casi: venerdì→sabato di una notte
  passa, sabato→domenica di una notte viene rifiutato con la spiegazione,
  venerdì→domenica di due notti passa, martedì→mercoledì di una notte passa.
- **Prezzi nascosti** verificati a browser: nessun importo su `/it/camere`,
  sulla home, su `/en/rooms` né sulle schede camera; presenti al passo
  «camere» della prenotazione.
- **Tassa di soggiorno** calcolata e mostrata a parte: due ospiti per tre
  notti fanno 18 €, e la quarta notte non la aumenta.
- **Area riservata**, 44 prove a browser: prima configurazione con codice
  giusto e sbagliato, password corta, ogni sezione senza errori, salvataggio
  che arriva sul sito (CIN, orari, testi inglesi, tariffe nel percorso di
  prenotazione, metratura, domande anche nei dati strutturati), convalide che
  non salvano niente, segnaposto dei testi protetti, ripristino di una versione
  precedente, richieste vere dal sito nel pannello, stati, eliminazione con
  conferma, accesso negato senza login, POST senza gettone CSRF rifiutato,
  blocco dopo cinque tentativi, intestazioni `noindex`/`no-store`, uscita.
- **Il codice fiscale del titolare non compare in nessuna pagina** né nel
  repository: controllato su tutti gli indirizzi generati e su tutto il diff.
- **Nessuna frase italiana nelle pagine inglesi**: le nove pagine `/en/`
  passate al setaccio parola per parola. La prosa dei contenuti — parcheggio,
  scale, animali, minibar, accessibilità — è scritta per lingua e passa da
  `testoLocale()`.
- **Le cifre scritte come le scrive la lingua**: «€ 1.200» in italiano, «€1,200»
  in inglese.
- Movimento ridotto rispettato: con `prefers-reduced-motion` il contenuto è
  subito visibile e le transizioni sono azzerate.
- Menu su schermo stretto: apre, tiene il fuoco dentro, chiude con `Esc` e
  restituisce il fuoco al pulsante che lo ha aperto.
- **Senza JavaScript**: prenotazione completa nei quattro passi, modulo
  contatti, domande, cambio lingua. La navigazione su schermo stretto ha le
  voci in chiaro al posto del pannello, e il pulsante che aprirebbe il
  pannello non compare — un comando spento è un vicolo cieco.
- **Contrasto misurato** su ogni coppia di colori usata per il testo: bruno su
  crema 11,1:1, mattone su crema 7,7:1, avorio su mattone 8,6:1, mattone su
  sabbia 7,0:1, muto su crema 5,3:1; l'oro della parola in corsivo sul
  mattone del piè di pagina 5,7:1.
- **Fotografie in apertura**: autoplay ogni sei secondi, fermo per chi chiede
  meno movimento, pulsante pausa, la didascalia si annuncia solo quando la
  foto la cambia chi legge. Dopo la prima, ogni fotografia si scarica appena
  prima di comparire.
- **Peso reale misurato a browser**, al primo caricamento: home 681 KB su
  mobile, 874 KB su desktop; camere 497 KB; prenota 251 KB.
  Il `srcset` sceglie la misura piccola dove la colonna è stretta.

---

## Script

```bash
php tools/build-placeholders.php  # rigenera i segnaposto delle fotografie
php tools/build-icone.php         # rosa dei venti in WebP (72 e 144 px) per la testata
php tools/build-photos.php        # ritaglia le fotografie nei formati del sistema
                                  #   (il tetto di peso scala con l'area: la
                                  #    misura piccola dev'essere davvero più
                                  #    leggera, non solo più stretta)
php tools/export-seed.php         # rigenera database/seed.sql dai contenuti
php tools/build-release.php [--prova=URL]
                                  # prepara il pacchetto per il server in dist/:
                                  #   rende tutte le pagine, si ferma se una
                                  #   non risponde, lascia fuori .env e le
                                  #   immagini che nessuna pagina chiede
php tools/preflight.php           # sul server: dice se si può pubblicare
sh  tools/serve.sh [porta]        # server di sviluppo
```
