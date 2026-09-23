# Arco del Vento — sito

Prototipo del sito di **Arco del Vento di Pecetta Daniele**, affittacamere in
Via Santa Maria delle Rose 1/A, ad Assisi. Cinque camere, gestite dal titolare
dal 2002.

Il disegno viene dal design system **«Arco del Vento»**: i colori sono
campionati dal file del marchio, i caratteri sono i suoi tre, e
`public/assets/css/bundle.css` è il foglio dei componenti del sistema,
copiato senza modifiche.

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
i dati strutturati, il tema notte.

**È reale**: le fotografie di quattro camere su cinque, il corridoio, e il
fatto che dalle finestre si veda San Rufino.

**È dimostrativo**: le tariffe, la disponibilità, i nomi delle camere, le
metrature e il piano. E il provider di prenotazione, che calcola tutto in
locale senza chiamare nessuno.

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

I tre caratteri — Prata, Figtree, Allura, tutti con licenza SIL Open Font —
sono serviti dalla nostra cartella in `.woff2`, sottoinsiemi `latin` e
`latin-ext`. Nessun dominio terzo vede l'indirizzo IP di chi legge, e il sito
funziona anche senza rete verso Google.

---

## Struttura

```
config/       configurazione, legge .env e l'ambiente del server
content/      i contenuti: camere, impostazioni, domande, luoghi, lingue
database/     schema.sql e seed.sql (generato)
docs/         tokens.json esportato dal design system
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
tools/        script: token, segnaposto, seed, server di sviluppo
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

Dal design system, via `bundle.css`: Bottoni, Campi, Etichette, Avvisi,
TitoloSezione, SchedaCamera, ElencoCamere, BarraPrenotazione, Indice,
Manifesto, Domande, Persone, Citazione, Ornamenti, PiediPagina, Navigazione,
Hero, HeroSilenzioso, Cornice, Numeri.

Scritti qui, in `views/`: telaio di pagina, testata, menu a tutta pagina,
cambio lingua, piè di pagina, barra appiccicata su mobile, dati strutturati,
riga camera, scheda camera, campo di modulo, riepilogo prenotazione, elenco
numerato, galleria, riga di contatto, segnaposto immagine, marcatore «da
confermare».

Primitive di impaginazione in `site.css`: contenitore, sezione, fascia
alternata, colonne asimmetriche, figura verticale, griglia camere, elenco di
fatti, tabella tariffe.

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

Italiano e inglese, completi: 408 chiavi per lingua, in parità.

Le pagine non sono duplicate. Esiste una vista per pagina; gli indirizzi per
lingua stanno in `src/I18n/Routes.php` e i testi in `content/lang/`. Una
chiave che manca ricade sull'italiano, così un sito tradotto a metà resta
usabile invece di mostrare le chiavi.

Ogni pagina dichiara `hreflang` per ogni lingua più `x-default`, e il cambio
lingua porta alla **stessa pagina** nell'altra lingua, non alla home.

**Per aggiungere lo spagnolo**: gli indirizzi (`/es/habitaciones`,
`/es/la-casa`, `/es/reservar`…) e gli slug delle camere sono già scritti.
Servono tre passi:

```bash
cp content/lang/es.php.example content/lang/es.php
# tradurre (la struttura delle chiavi è identica a it.php)
# poi nel .env:  APP_LOCALES=it,en,es
```

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

**Resta da fare al deploy**: certificato HTTPS (incluso nei piani, si attiva
dal pannello), `APP_ENV=production` e `APP_DEBUG=false`, `APP_URL` col dominio
vero, la casella di posta e le credenziali SMTP, e — se serve — il database.

Il prototipo **non è stato caricato** su Hostinger.

---

## Contenuti da confermare

Il sito non inventa: dove un dato manca lo marca `[da confermare]` e lo mostra
così all'ospite. Questi sono i dati che servono, in ordine di quanto pesano.

**Servono per primi** — sono quelli che l'ospite cerca prima di prenotare:

- orario di check-in e check-out, e cosa fare arrivando fuori orario
- parcheggio: quale, quanto dista, come si fa l'ultimo tratto con i bagagli
- scale: quante rampe dalla strada alle camere, e se c'è un ascensore
- telefono, e-mail, numero WhatsApp
- tariffe vere e stagionalità, tassa di soggiorno, soggiorno minimo
- se c'è la colazione (nel materiale non è dichiarata: il sito non la promette)

**Servono per la pagina camere**:

- i nomi definitivi delle cinque camere
- metratura, piano ed esposizione di ciascuna
- **quale fotografia corrisponde a quale camera** — la numerazione attuale è
  quella in cui le fotografie sono arrivate, non quella della casa: basta una
  riga di Daniele
- la fotografia della quinta camera, l'unica che ancora manca

**Servono per obbligo di legge**: CIN, partita IVA, CAP, e il testo completo
dell'informativa privacy (titolare, indirizzo per la privacy, tempi di
conservazione).

**Utili**: Wi-Fi, animali, fumo, bambini, riscaldamento, cambio biancheria,
lingue parlate, accessibilità, pagamento e disdetta, tempi a piedi verificati
verso i sei luoghi, coordinate della casa per la mappa.

---

## Fotografie

**Quattro camere su cinque sono fotografate**, e le fotografie sono della
casa: le ha fornite il titolare. Con loro è arrivata anche la conferma della
cosa che questo sito racconta — dalle finestre delle camere si vede il
campanile della **Cattedrale di San Rufino**, e in una si vede la facciata.

Ci sono anche il corridoio con la **rosa dei venti intarsiata nel pavimento**
— la stessa del marchio, e messa lì molto prima del sito — e tre vedute di
Assisi su licenza Unsplash (Niels Baars, Gary Walker-Jones, Alessandro
Guarino), con i crediti nel piè di pagina.

La quinta camera aspetta ancora la sua fotografia e tiene il segnaposto
disegnato. Il sito lo dice, non lo nasconde.

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
- Nessuna recensione, quindi la sezione recensioni non compare. Il componente
  c'è: comparirà quando ci saranno recensioni vere da riportare.
- Nessuna mappa: senza le coordinate della casa, un segnaposto messo a occhio
  manda l'ospite alla porta di un altro.
- La quinta camera non ha fotografia: tiene il segnaposto disegnato.
- Le tre vedute di Assisi si ripetono fra le pagine.
- L'informativa privacy è impostata ma non è un documento legale finito.
- Non c'è un pannello di amministrazione: i contenuti si modificano nei file.

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
- Bersagli da toccare sopra i 24px.
- Movimento ridotto rispettato: con `prefers-reduced-motion` il contenuto è
  subito visibile e le transizioni sono azzerate.
- Menu su schermo stretto: apre, tiene il fuoco dentro, chiude con `Esc` e
  restituisce il fuoco al pulsante che lo ha aperto.
- **Senza JavaScript**: prenotazione completa nei quattro passi, modulo
  contatti, domande, cambio lingua. La navigazione su schermo stretto ha le
  voci in chiaro al posto del pannello, e il pulsante che aprirebbe il
  pannello non compare — un comando spento è un vicolo cieco.
- **Tema notte** disegnato e misurato, non solo generato: il pulsante
  principale prende il filo d'oro perché il mattone su fondo scuro sta a
  1,6:1 contro la scheda, sotto i 3:1 che servono perché un comando si
  riconosca (WCAG 1.4.11).
- **Peso reale misurato a browser**, con le immagini in differita caricate:
  home 627 KB su mobile, 745 KB su desktop; camere 351 KB; prenota 185 KB.
  Il `srcset` sceglie la misura piccola dove la colonna è stretta.

---

## Script

```bash
php tools/build-tokens.php        # rigenera tokens.css da docs/tokens.json
php tools/build-placeholders.php  # rigenera i segnaposto delle fotografie
php tools/build-photos.php        # ritaglia le fotografie nei formati del sistema
                                  #   (il tetto di peso scala con l'area: la
                                  #    misura piccola dev'essere davvero più
                                  #    leggera, non solo più stretta)
php tools/export-seed.php         # rigenera database/seed.sql dai contenuti
sh  tools/serve.sh [porta]        # server di sviluppo
```
