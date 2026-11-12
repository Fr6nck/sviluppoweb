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

**È dimostrativo**: le metrature. La disponibilità è quella vera del sito:
prenotazioni dirette, Booking.com con i link iCal e blocchi manuali.

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
                          CalendarioSito       (oggi)
                          il tuo gestionale    (domani)
```

`src/Booking/CalendarioSito.php` è il calendario vero del sito. Una camera è
libera per un soggiorno se ospita quel numero di persone (`occupancy.max` e una
tariffa per quel numero) e nessuna delle sue notti è occupata. Le notti
occupate vengono tutte da `App::nottiOccupate()`, che somma tre fonti:

| Fonte | Da dove | Nel Calendario del pannello |
| --- | --- | --- |
| `sito` | le prenotazioni dirette (`Inbox::nottiOccupate`): pagate, confermate, o con la pagina di pagamento aperta da meno di 35 minuti | «Prenotazione diretta», lettera D |
| `booking` | i calendari iCal di Booking.com (`CalendariEsterni::occupate`) | «Prenotazione Booking.com», lettera B |
| `manuale` | i blocchi messi dal Calendario (`BlocchiManuali::occupate`) | «Blocco manuale», lettera M |

Una notte è quella che comincia quel giorno: «dal 7 al 10» chiude le notti del
7, dell'8 e del 9, e il 10 la camera è di nuovo libera.

Prima c'era `DemoBookingProvider`, che per far vedere il caso «non c'è posto»
dava per occupata circa una notte su cinque, inventata ma sempre la stessa. Era
il valore predefinito: per questo, con il calendario vuoto, cercando per 2
persone dal 5 al 9 dicembre uscivano una o due camere invece di quattro. Non
c'è più: `BOOKING_PROVIDER` vale `sito`, e anche un `.env` vecchio con `demo`, o
senza la voce, usa il calendario vero.

**Al cliente solo il disponibile.** La ricerca restituisce le camere libere e
basta: le altre non compaiono, nemmeno come «non disponibile», e nessuna pagina
pubblica ha un calendario con i giorni spenti. Quando non è libera nessuna, il
sito lo dice e non propone altre date: dalle proposte si leggerebbero le notti
occupate. Il filtro sta tutto sul server:
nessuna pagina, risposta o sorgente pubblica contiene le date occupate. Il link
diretto ai dati di una camera occupata torna all'elenco, senza dire perché.

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
La capienza (`occupancy.max`) sta nello stesso file ed è solo la partenza: il
super admin la cambia dal pannello, e la modifica finisce in
`storage/data/camere.json` come il resto (`occupancy` intero, standard e max).
Il sito vende fino all'ultimo numero di ospiti con una tariffa
(`ArrayRoomRepository::inVendita`); scendendo, le tariffe in più si tolgono.
Dove una voce manca, la camera non ospita quel numero di persone e il motore
non la propone: le due cose sono lo stesso dato.

Su MySQL diventano la tabella `room_rates`, una riga per camera e per
occupazione. `seasonal_rates` è pronta per le variazioni di periodo, con una
colonna `guests` per poterle fare anche per occupazione.

**Soggiorno minimo**: due notti, tutto l'anno (dal 12 novembre 2026; prima era
una notte, due con il sabato). Sta in `stay.min_nights` (`default` 2,
`saturday` 2), si cambia dal pannello in La struttura, e lo applica
`BookingService`, che per un soggiorno comprendente un sabato prende il
maggiore fra i due. `BOOKING_MIN_NIGHTS` nel `.env`, se scritto, vince su
tutto. La barra delle date lo dice prima della ricerca, e la pagina
Informazioni scrive la regola che vale. Nei periodi di
punta — Ferragosto, 4 ottobre, Capodanno — il titolare ha detto che il minimo
sale, ma non di quanto: `peak` resta `null` e non viene imposto nulla.

**Tassa di soggiorno**: 3 € a persona per notte, per le prime tre notti,
esenti i minori di 12 anni, si paga al check-in. Il preventivo la mostra a
parte, mai dentro il totale della camera. Non chiedendo l'età degli ospiti, il
sito calcola il caso peggiore (tutti paganti) e lo dice.

### Pagamento online (SumUp)

Con `PAYMENT_PROVIDER=sumup`, `SUMUP_API_KEY` e `SUMUP_MERCHANT_CODE` nel
`.env`, il passo dei dati chiede nome, e-mail e dati di fatturazione e porta a
una pagina di SumUp (Hosted Checkout): i dati della carta non passano dal sito.
I passi per accenderlo sono in `docs/DEPLOY-HOSTINGER.md`, sezione 13.

```
modulo  →  prenotazione «in attesa» (Inbox)  →  POST /v0.1/checkouts  →  pagina del sito  →  SumUp
SumUp   →  ?passo=esito  e  POST /pagamenti/sumup  →  GET /v0.1/checkouts/{id}  →  «pagato»  →  e-mail
```

- `src/Payment/SumUp.php`: le due chiamate, con la chiave; segue solo pagine
  di pagamento su un dominio di SumUp.
- `src/Payment/Pagamenti.php`: apre, verifica, riprova (fino a cinque pagine per
  prenotazione, controllando prima che una precedente non sia stata pagata).
  Lo stato si chiede sempre a SumUp: niente di quello che torna dal browser o
  dalla notifica (non firmata) decide. Il passaggio a «pagato» avviene sotto il
  lucchetto dell'archivio, così le e-mail partono una volta sola anche se ritorno
  e notifica arrivano insieme. Un importo diverso da quello chiesto diventa «da
  controllare», e all'ospite non parte la conferma.
- Il prezzo lo ricalcola il provider al momento dell'invio, dalle date e dalla
  camera: il modulo non porta cifre.
- Il passaggio a SumUp è una pagina del sito con un link, aperto dal JavaScript:
  `form-action 'self'` non lascia che un modulo porti fuori dal sito.
- Il calendario del sito (`CalendarioSito`) conta come occupate anche le notti
  con la pagina di pagamento aperta da meno di 35 minuti: due persone non
  pagano la stessa camera.
- Ogni pagina aperta tiene la camera mezz'ora, quindi dalla stessa connessione
  se ne aprono al massimo sei l'ora (`storage/data/limiti-pagamento.json`, con
  un'impronta dell'indirizzo cambiata ogni giorno e tenuta un'ora).
- Nel pannello le pagine abbandonate non contano fra le nuove, negli arrivi o
  nel valore; ogni prenotazione mostra lo stato del pagamento e, se non è
  pagata, il pulsante «Controlla con SumUp».
- I dati di fatturazione (`src/Booking/Fatturazione.php`): indirizzo, CAP,
  città, paese obbligatori; codice fiscale facoltativo; con la spunta
  «azienda», ragione sociale e partita IVA obbligatorie, codice SDI o PEC
  facoltativo. Codice fiscale e partita IVA italiani con il carattere di
  controllo. Stanno in `dati.fatturazione` della prenotazione, nel pannello e
  nell'e-mail alla struttura; a SumUp non si passano. I campi dell'azienda li
  mostra la spunta con `:has()`, senza JavaScript.
- Modalità di prova: `PAYMENT_MODE=test` usa `SUMUP_TEST_API_KEY` e
  `SUMUP_TEST_MERCHANT_CODE` (il conto sandbox di SumUp), mai le chiavi vere
  (`App::pagamentoInProva()`). Le prenotazioni nascono con `pagamento.prova`;
  il sito lo dice a chi prenota, le e-mail hanno PROVA nell'oggetto, il pannello
  l'etichetta «Prova». Tornati ai pagamenti reali, `Inbox` le esclude da
  `nottiOccupate()` e dalle nuove, e `Cruscotto` dai numeri.

### Calendari di Booking.com (iCal)

Ogni camera può avere il link iCal del suo calendario su Booking.com (campo
«Calendario di Booking.com» in Camere e tariffe, salvato come `ical_import`
con le altre modifiche della camera). `src/Booking/CalendariEsterni.php` lo
legge con cURL (tutte le camere insieme, 6 secondi al massimo, solo https) e
tiene le notti occupate in `storage/data/calendari-esterni.json`: solo date,
mai nomi. Si rilegge quando serve, alla prima ricerca di date dopo 20 minuti
(`App::nottiOccupate()` somma quelle notti a `Inbox::nottiOccupate()`), e dal
pulsante «Aggiorna adesso»; se Booking non risponde restano buone le notti
lette l'ultima volta, con l'errore nel pannello e nel preflight.
`src/Booking/Ical.php` legge e scrive il formato: solo DTSTART e DTEND, con
DTEND escluso come la partenza.

In senso inverso il sito pubblica `/ical/<camera>/<gettone>.ics`
(`IcalController`, prima della porta del sito in anteprima): le notti pagate o
confermate dal sito e i blocchi manuali, come giornate intere e senza nomi. Il gettone è uno per
tutte le camere, nasce la prima volta che serve e sta nello stesso archivio;
il link è nella scheda di ogni camera, da dare a Booking.com in «Importa
calendario». Un blocco manuale ci va con le sole date e il titolo «non
disponibile»: la nota, che può avere un nome, resta nel pannello. Le notti
lette da Booking non si ripubblicano.

Con il channel manager scollegato (sotto) `CalendariEsterni` non legge niente e
non restituisce notti: `sospendi()` mette `sospeso` nell'archivio e svuota le
notti lette, tenendo i link; `riprendi()` lo toglie.

### Il Calendario e i blocchi manuali

**Calendario** (`/admin/calendario`, tutti e due gli account) mostra un mese
per volta: una riga per camera, una casella per notte. Ogni notte ha un solo
stato, con la sua lettera, il suo colore e la scritta per chi usa un lettore di
schermo: libera, prenotazione diretta (D), prenotazione Booking.com (B), blocco
manuale (M, a righe). Sotto la tabella, per ogni camera, l'elenco dei periodi
del mese con le date; le dirette portano alla richiesta. Lo costruisce
`src/Admin/Calendario.php` da `App::nottiOccupate()`.

**Blocca una camera.** Camera, «Dal», «Al» (il giorno di partenza) e una nota
facoltativa: il blocco vale da subito, e la camera non si prenota più dal sito
per quelle notti. Si toglie dall'elenco «Blocchi manuali», con «Togli».
`src/Booking/BlocchiManuali.php` li tiene in `storage/data/blocchi-manuali.json`
(al massimo 366 notti per blocco, nota di 200 caratteri); quelli finiti si
tolgono da soli.

Il blocco va anche nel calendario pubblicato per Booking.com, con le sole date:
se Booking importa quel link, chiude le stesse notti anche lì.

Quando la stessa prenotazione arriva poi da Booking, non c'è conflitto e non
c'è doppione: per ogni notte il Calendario sceglie una fonte sola, nell'ordine
diretta, Booking, blocco (`Calendario::FONTI`), e il blocco si segna come
coperto da Booking.com. Se Booking ne copre solo una parte, lo dice. Non è
detto che Booking non ripubblichi le date che importa: in quel caso tornerebbe
indietro il blocco stesso, che il sito vedrebbe come una notte di Booking. Per
questo il messaggio non dice solo «si può togliere»: se il blocco era per una
prenotazione presa al telefono, va lasciato.

### Interruttore del channel manager (prova)

**Channel manager (prova)** (`/admin/channel-manager`, solo il super admin) è
una funzione di prova: scollega il calendario di Booking.com da tutte le camere
e lo ricollega, per vedere il sito con la sola disponibilità propria.

- *Scollega da tutte le camere* (con la spunta di conferma): l'importazione si
  sospende e le notti importate da Booking si tolgono. I link iCal restano
  salvati. Le prenotazioni dirette e i blocchi manuali non si toccano: la
  pagina li elenca, e cosa farne lo decide una persona.
- *Ricollega e sincronizza adesso*: toglie la sospensione e rilegge subito
  tutti i calendari.
- Lo stato, collegato o scollegato e da quando, è in cima alla pagina; da
  scollegato lo dicono anche la bacheca, il Calendario, la scheda di ogni camera
  e il preflight (bloccante in produzione).

Attenzione: scollegato sul sito vero, le notti già vendute su Booking.com
tornano prenotabili dal sito.

**Per toglierla** quando non serve più:

1. cancellare `src/Admin/InterruttoreChannelManager.php` e
   `views/admin/channel-manager.php`;
2. in `src/Controller/AdminController.php`, togliere la rotta
   `channel-manager`, il metodo `channelManager()`, la voce in `SOLO_SUPER` e
   l'`use` della classe;
3. in `views/admin/layout.php`, togliere la voce «Channel manager (prova)»;
4. in `views/admin/bacheca.php` e `views/admin/calendario.php`, togliere la
   striscia «scollegato» (e in `AdminController` le variabili
   `channelScollegato` che la accendono);
5. facoltativo: in `src/Booking/CalendariEsterni.php` i metodi `sospeso()`,
   `sospesoDal()`, `sospendi()`, `riprendi()` e i loro controlli, e il caso
   «sospeso» in `views/admin/camera.php`, `views/admin/calendario.php`,
   `AdminController::notaIcal()` e `tools/preflight.php`. Lasciati
   lì non fanno niente finché nessuno sospende.

## Il sito chiuso da un codice

Finché è in anteprima, ogni pagina pubblica (anche `sitemap.xml`; `robots.txt`
dice `Disallow: /`) risponde con la pagina del codice: la scheda di accesso
dell'area riservata sopra una fotografia del sito già sfocata. È una porta vera
e non un velo dipinto: senza il codice il server non manda niente del sito.

- `src/Support/Accesso.php`: acceso o spento e quale codice — prima
  `SITE_ACCESS_CODE=off` nel `.env` (spento in ogni caso), poi l'area riservata
  (`storage/data/accesso.json`), altrimenti acceso con `Blackout_2026`.
- `src/Controller/AccessController.php`: viene subito dopo l'area riservata e le
  notifiche di SumUp, che restano fuori. Il codice giusto (maiuscole e spazi non
  contano) dà un cookie firmato con HMAC, HttpOnly, trenta giorni, legato al
  codice: cambiarlo chiude di nuovo tutti. Gettone anti-CSRF; dieci errori in un
  quarto d'ora dalla stessa connessione e ci si ferma. Chi è nell'area riservata
  passa.
- `views/sipario.php` e `admin.css` (`.adm--sipario`); la fotografia la fa
  `tools/build-sipario.mjs` (Node e Playwright, sfocata prima di salvarla).

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

**Chi riceve.** Dall'area riservata, *Ricezione e-mail*: fino a cinque
indirizzi (in `storage/data/posta.json`), con un pulsante per un messaggio di
prova. Se lì non ce n'è nessuno vale `MAIL_TO_ADDRESS` del `.env`, uno o più
separati da virgola. La casella che spedisce (`MAIL_FROM_ADDRESS`,
`MAIL_SMTP_USER`, `MAIL_SMTP_PASSWORD`) sta solo nel `.env`: ha una password, e
dal pannello si vede senza. I passi sono in `docs/DEPLOY-HOSTINGER.md`, «La posta».

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
Due account, protetti da password: il gestore e il super admin (sotto). Da lì
si modifica il sito senza toccare un file e senza FTP. Il gestore vede solo
Bacheca, Richieste, Calendario, La struttura, Camere e tariffe e Account; tutte le altre
sezioni sono del super admin (`AdminController::SOLO_SUPER`), e per il gestore
rispondono 404 come Configurazione. Delle immagini il gestore apre solo le foto
delle camere (la principale e le due di galleria, i posti `camera.*` con la
chiave `camera`: `AdminController::fotoDiCamera`), dalle pagine delle camere;
logo, apertura e foto delle pagine restano del super admin. Nella sua bacheca
il riquadro del marchio non c'è. Dallo storico ripristina solo La struttura e
le camere.

| Sezione | Che cosa | Chi |
| --- | --- | --- |
| Bacheca | i numeri delle richieste (nuove, prenotazioni e messaggi negli ultimi 30 giorni, con l'andamento), il grafico a colonne di 7 giorni o 6 mesi, le ultime arrivate, i prossimi arrivi e le notti chieste nei prossimi 30 giorni, le camere, il marchio, e quello che sul sito è ancora «da confermare», CIN in testa | tutti e due |
| Richieste | le prenotazioni e i messaggi arrivati dal sito, con stato: nuova, letta, confermata, rifiutata, archiviata; si cercano per nome, e-mail, riferimento, camera | tutti e due |
| Calendario | il mese, camera per camera: notti libere, prenotazioni dirette, prenotazioni di Booking.com, blocchi manuali; il modulo per bloccare una camera e l'elenco dei blocchi da togliere | tutti e due |
| La struttura | contatti, orari, soggiorno minimo, tassa, dotazioni, obblighi di legge, recensioni, social, coordinate | tutti e due |
| Camere e tariffe | nome, metratura, piano, vista, tariffa per numero di ospiti, descrizione della fotografia, e le foto della camera (principale e galleria); «Tipologia» (il testo, in italiano e inglese) e «Ospiti al massimo» (la capienza, da 1 a 6) solo il super admin | tutti e due |
| Immagini e logo | la rosa dei venti (anche icona del browser), il logotipo chiaro e scuro, le cinque foto dell'apertura, le foto delle pagine e quella per i social, la foto principale e due di galleria per ogni camera: si carica il file, si descrive, si torna all'originale | super admin (le foto delle camere anche il gestore, da Camere e tariffe) |
| Testi del sito | tutti i testi in italiano e inglese, pagina per pagina, con l'originale accanto | super admin |
| Domande frequenti | aggiungere, modificare, riordinare, togliere | super admin |
| Accesso al sito | il codice dell'anteprima: acceso, spento, cambiato | super admin |
| Ricezione e-mail | a chi arrivano le richieste (fino a cinque indirizzi), come spedisce il sito, e un messaggio di prova | super admin |
| Account | cambiare la propria password; il super admin reimposta anche quella del gestore | tutti e due |
| Configurazione | le voci del file `.env` | super admin |
| Channel manager (prova) | scollegare e ricollegare il calendario di Booking.com da tutte le camere; funzione di prova, da togliere | super admin |

**Quale versione è sul server.** `tools/build-release.php` scrive nel
pacchetto `config/versione.php` (data e commit; nel repository non c'è, è in
`.gitignore`). Il pannello la mostra in fondo a ogni pagina e il preflight in
cima: dopo un caricamento via FTP si vede subito se i file nuovi sono arrivati.

**Dove vanno le modifiche.** In `storage/data/`, non in `content/`. I file di
`content/` restano il punto di partenza, sotto controllo di versione; quello che
si cambia dal pannello si sovrappone (`src/Storage/ContentOverrides.php`) e vince.
Così ricaricare il sito via FTP non cancella mai una modifica, e «torna al testo
originale» vuol dire togliere la modifica. Niente database: file JSON scritti in
modo atomico, con un lucchetto, e con le ultime trenta versioni di ogni archivio
nello storico — dal pannello si rimette una versione precedente con un click.

**Che cosa non si cambia da lì.** I letti e il tipo di camera che usa il motore
di prenotazione (`type` in `content/rooms.php`). Il super admin cambia la
tipologia come si legge sul sito, camera per camera e nelle due lingue
(`type_label`, `RoomPresenter::typeLabel`), e gli ospiti al massimo. Una
tipologia riscritta uguale all'etichetta del tipo non si salva: la camera torna
a seguire l'etichetta, che si cambia per tutte le camere in Testi del sito.

**Le immagini** (`src/Media/`). Il file caricato non si pubblica mai così com'è:
GD lo apre, lo raddrizza secondo l'EXIF del telefono, lo porta a 2600 px di lato
lungo, lo ritaglia ai rapporti che il posto usa (una camera: 4:3, 3:2, 16:9,
1:1) e lo riscrive in WebP e JPG, grande e piccolo, con lo stesso tetto di peso
di `tools/build-photos.php`. Loghi e rosa tengono la trasparenza (PNG e WebP;
la rosa in tutte le misure dell'icona). Riscrivere è anche la difesa: del file
arrivato resta solo l'immagine, e il nome lo decide il sito. Si rifiutano con
un motivo chiaro i file non immagine, le HEIC dell'iPhone, quelle troppo
piccole per il posto, troppo grandi per la memoria del server. I file vanno in
`public/assets/media/` (chiusa agli script da un suo `.htaccess`, fuori dal
pacchetto e da git), il posto che usano in `storage/data/immagini.json`.

**Chiaro o scuro.** Il pulsante con la luna (o il sole) nella barra in alto, e
sulla pagina di accesso, passa dal tema chiaro a quello scuro. Finché non si
sceglie, il pannello segue il tema del telefono o del computer. La scelta sta
in un cookie del browser (`adv_tema`, solo sotto `/admin`), e il server la
scrive già nella pagina: niente lampo chiaro all'apertura. Senza JavaScript il
pulsante funziona lo stesso, come modulo. Il sito pubblico resta com'è.

**La bacheca non inventa.** Tutti i numeri vengono dalle richieste arrivate dal
sito (`src/Admin/Cruscotto.php`); i grafici sono SVG disegnati sul server
(`src/Admin/Grafici.php`), senza librerie e senza stili in linea, che la
politica di sicurezza non ammette. Le prenotazioni di Booking non ci sono, e la
pagina lo dice.

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
- *Password dimenticata*: quella del gestore la reimposta il super admin da
  **Account** (con la sua password per conferma; in partenza il gestore deve
  sceglierne una sua al primo accesso, e del super admin non vede traccia).
  Senza super admin, o per la sua password, via FTP si cancella
  `storage/data/admin.json` (o `superadmin.json`) e si rifà la prima volta con
  il codice. Modifiche e richieste restano.
- Ogni password nuova chiude le sessioni aperte con la vecchia: l'account ha un
  numero di `versione` che sale a ogni cambio, e `Auth::current()` accetta solo
  la sessione con lo stesso numero. Chi cambia la propria password resta dentro;
  le sue altre sessioni no.

**Le richieste** arrivano sempre anche per e-mail. Nel pannello si cancellano da
sole dopo 24 mesi: sono dati personali, e lo stesso periodo va scritto
nell'informativa privacy.

### Super admin e Configurazione

Un secondo account, separato da quello del gestore: il **super admin**, cioè chi
ha fatto il sito. È il solo che vede **Configurazione** (`/admin/configurazione`):
tutte le voci del file `.env`, a gruppi, con un nome da persona e la spiegazione.

- Si crea una volta, su **`/admin/super`**, con il codice `SUPERADMIN_SETUP_TOKEN`
  del `.env`. Poi quell'indirizzo è il suo accesso; entra anche dal login normale.
- Per il gestore `/admin/super` e `/admin/configurazione` rispondono 404, come
  una pagina qualunque che non esiste, e nel menu non c'è niente.
- `src/Admin/Ambiente.php` conosce ogni voce e la controlla (indirizzi, e-mail,
  numeri, valori ammessi; mai un a capo, che aggiungerebbe una riga al file).
- Scrive solo le righe cambiate e lascia il resto com'è, commenti compresi;
  scrive un file accanto e lo sostituisce, poi rilegge e confronta; prima mette
  il file com'era in `storage/data/ambiente/` (le ultime dieci versioni, una si
  rimette con un click). Il registro tiene chi e quali voci, mai i valori.
- I segreti (password, chiavi, gettoni) non tornano nella pagina: si vede se ci
  sono e quanto sono lunghi. Ogni salvataggio chiede la password del super admin.
- Una voce impostata nell'ambiente del server (pannello di Hostinger) vince sul
  file: si vede, ma da qui non si cambia.
- Se il sito non può scrivere il `.env`, la pagina si legge soltanto.

---

## Contenuti da confermare

Il sito non inventa. Dove un dato manca, dal 12 ottobre 2026 la riga
semplicemente non si mostra — Informazioni, scheda della camera — invece di
dire «da confermare» a un ospite; il marcatore `[da confermare]` resta solo in
fondo alla pagina (dati di legge), nei due punti della privacy che il titolare
deve completare e nelle condizioni di cancellazione mentre non sono scritte.

**Servono per obbligo di legge**:

- CIR e REA, se la struttura ne ha (il **CIN** c'è: IT054001B403008749)
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

- **condizioni di cancellazione**: con il pagamento online sono obbligatorie.
  Si scrivono nell'area riservata (La struttura → Condizioni di
  cancellazione); finché mancano il controllo finale non lascia aprire il
  pagamento. Il pagamento è deciso: tutto online con SumUp, al momento della
  prenotazione, nessuna caparra.
- **culle e letti aggiunti** (i bambini sono benvenuti, e sotto l'età di
  esenzione non pagano la tassa di soggiorno: la riga c'è già)
- **lingue parlate** dal titolare
- di quanto sale il soggiorno minimo nei tre periodi di punta

Check-out (10:30), coordinate della casa, tempi a piedi e parcheggio (Piazza
Matteotti, a pagamento, con il link al sito del gestore invece di un prezzo
scritto a mano) sono stati confermati a ottobre 2026.

**Servono per la pagina camere**:

- **la fotografia della Camera 01**, la tripla: è la più cara del listino, è
  quella con la vista, ed è l'unica rimasta senza. Il posto è pronto e la
  scheda è completa in tutto il resto.
- la metratura di ciascuna camera: finché non è scritta nell'area riservata la
  riga non compare. L'esposizione c'è: le camere che non guardano su Piazza
  San Rufino stanno «sui vicoli di Assisi».
- i due scatti che risultano inviati ma non arrivati sul disco:
  `san-rufino-finestra` e `piazza-san-rufino`

**Da chiarire, perché due dati confermati non tornano** — vedi «Limiti noti».

**Utili**:

- il **numero e il voto delle recensioni** Booking e Google, che il titolare
  ha dato per «in verifica». Si inseriscono dall'area riservata, sezione «La
  struttura», appena verificati; fino ad allora il blocco non compare:
  meglio assente che approssimato.
- profili Instagram e Facebook, se esistono

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
- La Camera 01, la tripla, non ha fotografia: tiene il segnaposto disegnato finché
  non se ne carica una da «Immagini e logo».
- Le tre vedute di Assisi si ripetono fra le pagine.
- L'informativa privacy è impostata ma non è un documento legale finito.
- Il calendario di Booking.com si collega con i link iCal, camera per camera:
  le notti prenotate là arrivano con fino a 20 minuti di ritardo, e nel
  frattempo si chiudono con un blocco manuale.

---

## Verifiche fatte

- Tutti gli indirizzi nelle due lingue rispondono, senza errori né avvisi PHP.
- Percorso di prenotazione completo: ricerca, camere libere, scelta, dati,
  richiesta, conferma; Post/Redirect/Get, gettone CSRF, date non valide,
  soggiorno minimo e massimo, nessuna disponibilità, gruppo troppo numeroso
  per ogni camera.
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
