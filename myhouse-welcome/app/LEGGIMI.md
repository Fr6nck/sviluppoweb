# MyHouse Welcome — applicazione funzionante

PHP 8 + SQLite (o MySQL). **Nessuna dipendenza da installare**: niente Composer,
niente Node, niente librerie esterne. Si carica via FTP e funziona.

---

## Cosa funziona davvero

| | |
|---|---|
| Registrazione, accesso, sessioni | password con `password_hash`, sessione rigenerata a ogni accesso |
| Acquisto di un piano | Stripe Checkout se configurate le chiavi, altrimenti modalità prova dichiarata |
| Webhook Stripe | firma verificata, finestra temporale, riconsegne ignorate |
| Diritti per piano | override → pacchetto comprato → predefinito |
| Versioni di pacchetto congelate | cambiare un piano **non** toglie niente a chi l'ha già comprato |
| CMS della guida | sezioni, testi, Wi-Fi, codici, luoghi, foto |
| Caricamento immagini | riconvertite in JPEG e ridimensionate; i file finti vengono rifiutati |
| Multilingua | quattro lingue; una traduzione confermata non viene mai sovrascritta |
| Pubblicazione a istantanee | gli ospiti leggono una copia congelata, non la bozza |
| QR permanente | generato in PHP; l'indirizzo non cambia mai, nemmeno rinominando la casa |
| Amministrazione | clienti, impersonazione senza password, pacchetti, eccezioni |
| Amministrazione, il quadro | clienti, incassi, aperture, versioni di piano e chi ci sta sopra |
| Tema chiaro e scuro | segue il sistema finché non scegliete; poi la scelta resta |
| Diagnostica | il server verifica sé stesso invece di promettere |
| Dati di esempio | tre host finti con guide vere, foto e statistiche, creabili e cancellabili |

## Cosa serve configurare per andare in produzione

Tutto il resto funziona da subito. Queste tre cose richiedono chiavi vostre:

1. **Pagamenti reali** — in `app/config.php` (o come variabili d'ambiente):
   `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, `STRIPE_WEBHOOK_SECRET`.
   Senza, gli acquisti restano in modalità prova **e lo dichiarano a schermo**.
   Nel pannello Stripe puntate il webhook all'indirizzo completo, sottocartella
   compresa: `https://vostrodominio/welcomebook/webhook/stripe`.
2. **Traduzione automatica** — `MHW_TRANSLATE_PROVIDER` (`deepl` o `libre`) e
   `MHW_TRANSLATE_KEY`. Senza, le lingue si compilano a mano: tutto il resto funziona.
3. **HTTPS** — i cookie di sessione diventano `secure` solo sotto HTTPS.

---

## Installazione

### Disposizione consigliata (la più sicura)

Caricate la cartella **intera** fuori dalla radice pubblica e puntate il dominio
su `public/`, che sta dentro:

```
/home/vostroutente/
  myhouse-welcome/
    config.php  src/  views/  migrations/  storage/
    public/      <- il dominio punta QUI
      index.php  .htaccess  assets/
```

Così niente del codice né il database è raggiungibile dal web, qualunque server
usiate: sopra `public/` il browser non può salire.

Non spezzate la cartella: `public/` deve restare **dentro** `myhouse-welcome/`,
perché l'applicazione si cerca nella cartella che contiene `public/`.

### Installazione in una sottocartella (es. `dominio.it/welcomebook/`)

L'applicazione si accorge da sola di stare in una sottocartella: tutti gli
indirizzi, **il QR compreso**, prendono il prefisso giusto. Caricate il
contenuto del pacchetto dentro la sottocartella:

```
public_html/
  welcomebook/
    index.php  .htaccess  web.config  assets/
    app/       <- src, views, migrations, config.php, storage
```

> **Leggete questo.** In questa disposizione la protezione della cartella `app/`
> dipende da `.htaccess`, che **nginx non legge affatto** e che Apache ignora se
> `AllowOverride` è disattivato. Per questo il file del database prende un nome
> casuale, deciso all'installazione e custodito in un file `.php` che il server
> esegue invece di servirlo. È una difesa in più, non una garanzia.
>
> Dopo l'installazione aprite **Diagnostica** nel menu di amministrazione: il
> server prova a scaricare il proprio database e vi dice se ci riesce.

**Se gli indirizzi danno 404** (per esempio `/welcomebook/installa` non si apre),
il server non sta riscrivendo gli indirizzi: manca `mod_rewrite`, oppure
`AllowOverride` è su `None`. Due strade:

- chiedete all'assistenza di abilitare `mod_rewrite` e `AllowOverride All`
  per quella cartella (è la soluzione giusta);
- oppure usate la via di riserva, che funziona senza riscrittura:
  `dominio.it/welcomebook/index.php/installa`, e da lì in poi tutto prosegue.

### Poi

1. Aprite il dominio: vi porta a `/installa`.
2. Scegliete email e password dell'amministratore. Il database si crea da solo,
   con le funzioni e i tre piani già dentro.
3. Lasciate spuntato **«Riempi con tre clienti di esempio»** se volete vedere il
   prodotto pieno invece che vuoto (vedi sotto). Si tolgono in un clic.
4. Date permessi di scrittura a `app/storage/` e `app/storage/uploads/` (di solito 755 o 775).

### I tre clienti di esempio

Servono a vedere com'è l'applicazione quando è abitata: guide pubblicate, foto,
luoghi, traduzioni in tre lingue, trenta giorni di statistiche, ordini pagati.

| Chi | Entra con | Piano | Cosa mostra |
|---|---|---|---|
| Lucia Ferrante | `lucia@esempio.it` | Plus | Casa Lucia, Montepulciano — pubblicata, it/en/de, tre luoghi con foto |
| Marco Bevilacqua | `marco@esempio.it` | Pro | B&B Le Rondini, Lecce — pubblicata, it/en |
| Agnese Ruta | `agnese@esempio.it` | Essential | Il Cortile, Ortigia — **ferma in bozza**, di proposito |

Entrano tutti con la password `dimostrazione1`.

> **Toglieteli prima di aprire il sito al pubblico.** Sono account veri con una
> password che sta scritta qui. Si eliminano — con le loro guide e le loro foto —
> dal riquadro in fondo a **Amministrazione → Clienti**, e da lì si ricreano.

### MySQL invece di SQLite

In `config.php` mettete `'driver' => 'mysql'` e le credenziali. Lo schema è lo stesso.

---

## Indirizzi

| Indirizzo | Cosa |
|---|---|
| `/` | sito pubblico con i piani |
| `/registrati`, `/accedi` | account |
| `/pannello` | area host: guide, sezioni, lingue, QR |
| `/g/{slug}/benvenuto` | la soglia: la schermata che si apre inquadrando il QR |
| `/g/{slug}` | la guida che vedono gli ospiti |
| `/g/{slug}/{id}` | una sezione: arrivo, Wi-Fi (in tema notte), luoghi, testo |
| `/g/{slug}/commiato` | il congedo, con le poche cose da fare prima di partire |
| `/q/{token}` | l'indirizzo dietro il QR — **non cambia mai** |
| `/qr/{token}.png` | l'immagine del QR |
| `/admin` | il quadro: clienti, incassi, aperture, piani |
| `/admin/clienti` | elenco, ricerca, impersonazione, dati di esempio |
| `/admin/pacchetti` | il listino, versione per versione |
| `/admin/diagnostica` | i controlli che il server fa su sé stesso |
| `/webhook/stripe` | solo per Stripe, verificato per firma |

---

## Come è fatto

```
app/
  config.php            unica configurazione
  migrations/           lo schema (21 tabelle)
  src/                  Db, Auth, Csrf, Router, View, Entitlements,
                        Guide, Media, Qr, Stripe, Billing, Translator, Installer
  views/                le pagine
  storage/              database e immagini caricate
public/                 index.php, .htaccess, web.config, assets/
```

Il generatore di QR è scritto da zero seguendo ISO/IEC 18004 (modalità byte,
correzione M, versioni 1-10) proprio per non dipendere da librerie esterne.

## Scelte che vale la pena conoscere

- **Il webhook è l'unica fonte di verità sui pagamenti.** Il ritorno dal browser
  non attiva niente: chiunque potrebbe visitare quell'indirizzo.
- **Una versione di pacchetto venduta non si modifica.** L'amministratore che
  cambia un piano ne crea una nuova; gli abbonamenti già venduti restano sulla loro.
- **Le traduzioni confermate sono intoccabili.** Lo stato passa da `missing` a
  `machine` a `reviewed`, e da `reviewed` nessuna macchina torna indietro.
- **Gli ospiti leggono un'istantanea.** Si modifica la guida senza che nessuno
  veda mezze frasi, e ogni versione pubblicata resta conservata.
- **L'impersonazione non passa mai dalla password del cliente** e lascia traccia
  nel registro, in entrata e in uscita.
- **Una riga vuota separa i passaggi.** L'host scrive normalmente; sulla guida
  ogni capoverso diventa un riquadro numerato. L'unica convenzione da imparare è
  che un capoverso che comincia con `Nota:` non è un passaggio ma l'avviso in
  fondo alla pagina.

---

## Provarla

```
app/prove/esegui.sh
```

Schiera una copia dell'applicazione come finisce sull'hosting, le mette davanti
un server, e le passa sopra 91 controlli veri via HTTP: installazione, dati di
esempio, i numeri del quadro, l'impersonazione, tutte le schermate dell'ospite
in tre lingue, il tema notte, il QR che rimanda alla soglia e la sua immagine,
la cancellazione e la ricreazione dei clienti di esempio, e infine la sabbia, il
vetro delle barre e l'interruttore dei temi. Alla fine pulisce.

---

## Com'è fatta da vedere

Il disegno segue la direzione **Fauna** della tavola di progetto: fotografie a
tutta larghezza, riquadri di colore pieno al posto delle icone, **Gloock** sui
titoli e **Onest** su tutto il resto, angoli 8 / 18 / 26 e tondo pieno su ogni
cosa che si preme. Tutto quello che si tocca è alto almeno 44 px.

La tavolozza è quella delle Fondamenta: carta `#faf5ec`, inchiostro `#231b12`,
terracotta `#b4451f`, mare `#1c5a78`, pino `#1f6b3f`, ocra `#b07d0c`,
allarme `#9c2b20`, notte `#17130d`. Sull'ocra il testo è scuro, non chiaro:
in chiaro si ferma a 3.5:1 e non passa. È l'unica eccezione della tavolozza.

Su tutte le superfici — carta, schede, riquadri, bottoni, barre — corre una
**grana di sabbia** finissima (`assets/grana.png`, 17 kB, una sola richiesta).
Sta nello sfondo, sotto il contenuto: non tocca le fotografie, né il testo, né
le icone. La stessa immagine ha granelli chiari e scuri, così funziona nei due
temi senza cambiarla.

Le **due barre di navigazione**, quella in alto e quella in fondo alla guida,
sono vetro: 40% di colore e il resto è la pagina che ci scorre sotto, sfocata.
Il vetro non si limita a sfocare, tira anche quello che c'è sotto verso il
colore del tema: senza, il marchio sulla barra scendeva a 3.66:1 sopra una
fotografia chiara — misurato, non supposto; ora sta a 4.70:1. Dove il browser
non sa sfocare, le barre tornano opache, perché un 40% senza sfocatura renderebbe
illeggibile quello che c'è sopra.

### Chiaro e scuro

C'è un interruttore in ogni intestazione, e nella guida accanto alla lingua.
Finché nessuno lo tocca, il tema segue il sistema — e continua a seguirlo anche
se il sistema cambia mentre la pagina è aperta. Appena qualcuno sceglie, la
scelta vince e resta, anche dopo aver chiuso il browser.

I riquadri di colore pieno **non cambiano col tema**: sono il marchio, non una
superficie. Cambiano le superfici, le righe, il testo e l'accento, che di notte
si schiarisce (`#ee7a4a`) per restare leggibile. Il contrasto di ogni testo è
stato misurato in tutti e due i temi, su tutte le pagine: nessuno sta sotto la
soglia AA.

La sezione Wi-Fi resta in **tema notte** comunque: è quella che si cerca al buio,
in una casa che non si conosce ancora. Lì l'interruttore non compare, perché non
cambierebbe niente sotto gli occhi di chi guarda.

Tutto sta in un unico foglio di stile (`public/assets/app.css`) e in un unico
insieme di icone disegnate a tratto (`src/Icon.php`), scritte in PHP invece che
caricate da fuori: una guida si apre spesso con una tacca di segnale.
