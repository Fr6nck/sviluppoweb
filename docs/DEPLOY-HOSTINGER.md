# Pubblicare su Hostinger via FTP

Il sito è PHP senza dipendenze e senza compilazione: si carica e funziona.
Non serve Composer, non serve Node, non c'è niente da «buildare» sul server.

Prima di cominciare, dalla tua macchina:

```bash
php tools/preflight.php
```

Non passerà — lo sviluppo non è la produzione — ma ti fa vedere che cosa
controllerà una volta caricato.

---

## 1. Dove mettere i file

Ci sono due modi. **Il primo è migliore**, il secondo è più rapido.

### A. Il dominio punta su `public/` — consigliato

Le cartelle dell'applicazione restano fuori dal web: non sono raggiungibili
nemmeno se un giorno l'`.htaccess` smettesse di funzionare.

1. Carica **tutto il progetto** in una cartella accanto a `public_html`,
   per esempio `/home/uXXXXXX/arcodelvento/`
2. hPanel → **Avanzate → Cambia cartella radice del sito**
3. Imposta la radice su `/home/uXXXXXX/arcodelvento/public`

### B. Tutto dentro `public_html` — più rapido

1. Carica tutto il progetto dentro `public_html/`
2. Basta così: l'`.htaccess` nella radice manda le richieste in `public/`,
   blocca `src/`, `views/`, `content/`, `config/`, `storage/`, `database/`,
   `tools/`, `docs/`, e rimanda `/public/…` all'indirizzo pulito

In questo modo la sicurezza dipende dall'`.htaccess`. Se il server un giorno
lo ignorasse, `.env` diventerebbe leggibile. Per questo A è meglio.

### C. In una sottocartella di un altro sito — la prova

Durante la prova il sito sta in **`blackout.in/assisiapartment/`**. È il modo B
dentro una cartella:

1. In `public_html` di blackout.in crea la cartella **`assisiapartment`**
2. Carica **dentro di lei** tutto il contenuto del pacchetto
3. Usa come `.env` il file **`env-prova.txt`** (vedi sotto), rinominato

Il sito capisce da solo di stare in una cartella: la legge da `APP_URL`. Ogni
link, reindirizzamento, foglio di stile e immagine se la porta dietro, e
nessuna regola degli `.htaccess` scrive un percorso che cominci con «/» —
quindi funzionano uguali alla radice di un dominio e in una sottocartella. Il
resto di blackout.in non viene toccato: un sito WordPress nella radice
continua a funzionare, perché Apache applica alla cartella solo le regole del
suo `.htaccess`.

La prova è **fuori dai motori di ricerca** (`APP_NOINDEX=true`): ogni pagina
porta `noindex, nofollow`, nell'HTML e nell'intestazione `X-Robots-Tag`. Senza,
Google indicizzerebbe il sito su blackout.in, e il giorno della pubblicazione
avrebbe due copie dello stesso sito. E **non spedisce e-mail**
(`MAIL_TRANSPORT=log`): le richieste di prova restano in
`assisiapartment/storage/mail/`, leggibili via FTP, e non arrivano nella
casella del cliente.

Il pacchetto con il `.env` di prova si prepara così:

```bash
php tools/build-release.php --prova=https://blackout.in/assisiapartment
```

Il controllo finale, in prova, non si ferma per il CIN (lo segnala e basta) e
chiude con **«LA PROVA PUÒ ANDARE ONLINE»**. Sul dominio vero il CIN torna
bloccante.

---

## 2. Che cosa caricare

**Il pacchetto lo prepara uno script**, e conviene usarlo invece di scegliere
le cartelle a mano:

```bash
php tools/build-release.php
```

Scrive in `dist/` tre cose:

- `dist/arcodelvento/`: la cartella pronta da caricare così com'è
- `dist/arcodelvento-AAAAMMGG-HHMM.zip`: la stessa cosa in un file solo, per
  il **File Manager dell'hPanel**, che lo scompatta sul server. Con l'FTP
  lento è molto più rapido di 186 file uno per uno.
- `dist/MANIFESTO.txt`: cosa c'è dentro, cosa è rimasto fuori e l'impronta
  SHA-256 dell'archivio
- `dist/file-da-rinominare/`: copie con un nome normale dei file che il
  computer nasconde, più il `.env` di produzione già compilato — vedi sotto
- `dist/LEGGIMI-PRIMA.txt`: le stesse istruzioni, in breve

### I file che non si vedono

`.htaccess` e `public/.htaccess` **sono nello .zip**, ma il nome comincia con
un punto e il Finder del Mac li nasconde (Cmd + Maiusc + . li mostra).
FileZilla, nel pannello di sinistra, li vede comunque. Senza `.htaccess` sul
server ogni pagina tranne la home dà 404: se dopo il caricamento non ci sono,
usa le copie in `dist/file-da-rinominare/`:

| File | Dove va | Nome sul server |
| --- | --- | --- |
| `htaccess-radice.txt` | `public_html/` | `.htaccess` |
| `htaccess-public.txt` | `public_html/public/` | `.htaccess` |
| `env-produzione.txt` | `public_html/` | `.env` |

Nello .zip c'è anche **`public/.user.ini`**: chiede a PHP di accettare file
fino a 16 MB, perché le foto del telefono (3–8 MB) si possano caricare
dall'area Immagini. Se il server non lo legge, il limite resta quello del
pannello di Hostinger (*Avanzate → Configurazione PHP → upload_max_filesize*):
l'area Immagini mostra sempre il limite vero.

**Il `.env` non è nello .zip, di proposito**: deve contenere la password della
posta. `env-produzione.txt` è già compilato con produzione, debug spento,
dominio ricavato dall'e-mail della struttura, SMTP di Hostinger e un gettone
casuale per il controllo finale. Manca solo la password della casella: la
scrivi, controlli il dominio, lo carichi e lo rinomini.

Prima di scrivere il pacchetto, lo script **rende ogni pagina delle due lingue**
e si ferma se una non risponde o contiene un errore di PHP. Poi controlla che
dentro non ci siano `.env` o `.git`, che nessun valore del tuo `.env` sia
finito nei file, che la sintassi PHP sia pulita, che i due `.htaccess` ci
siano, e che ogni immagine chiesta da una pagina sia nel pacchetto.

**Lascia fuori le immagini che nessuna pagina chiede**: i ritagli non usati e
i segnaposto disegnati delle camere che adesso hanno una fotografia (circa
1 MB). Restano nel progetto, non vanno sul server. Con `--tutto` le porta
comunque.

**Che cosa resta fuori, e perché**:

| Cosa | Perché |
| --- | --- |
| `.git/` | è la storia del progetto, non serve al sito e pesa |
| `.env` | va **creato sul server**, non copiato: quello locale ha i valori di sviluppo |
| `docs/foto-originali/` | gli originali già lavorati: servono a te, non al sito |
| `storage/mail/` `storage/logs/` | il contenuto: le cartelle salgono vuote e si riempiono da sole |
| `tools/router.php` `tools/serve.sh` | servono al server di sviluppo, in produzione mai |
| `tools/build-*.php` | strumenti da tavolo: vogliono GD o si usano solo qui |

Sul server salgono solo due strumenti: `tools/preflight.php` (il controllo
finale) e `tools/export-seed.php` (serve il giorno in cui si attiva MySQL).

### Con il File Manager (consigliato)

1. hPanel → **File** → **File Manager**
2. entra nella cartella che hai scelto al punto 1 (A o B)
3. **Carica** lo `.zip`, poi tasto destro → **Estrai**
4. controlla che siano comparsi `.htaccess` e `public/.htaccess`, poi cancella
   lo `.zip` dal server

### Con l'FTP

Carica il **contenuto** di `dist/arcodelvento/` (non la cartella stessa)
nella cartella scelta al punto 1.

**Attenzione ai file che cominciano con un punto.** Molti client FTP li
nascondono, e se non carichi `.htaccess` il sito risponde 404 su ogni pagina
tranne la home. In FileZilla: *Server → Forza visualizzazione file nascosti*.

---

## 3. Le cartelle scrivibili

`storage/logs/` e `storage/mail/` devono essere scrivibili dal server:
il sito ci scrive i registri e, in modalità `log`, i messaggi dei moduli.

Lo stesso per **`public/assets/`**: la prima volta che carichi un'immagine
dall'area riservata, il sito ci crea dentro la cartella `media/`.

Da FileZilla, tasto destro sulla cartella → *Permessi file* → **755**.
Se non basta, 775. Mai 777.

---

## 4. Il file `.env`

Crealo sul server copiando `.env.example` e compilando. In produzione:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://iltuodominio.it

MAIL_TRANSPORT=smtp
MAIL_FROM_ADDRESS=no-reply@iltuodominio.it
MAIL_FROM_NAME="Arco del Vento"
MAIL_TO_ADDRESS=l-indirizzo-di-daniele@iltuodominio.it
MAIL_SMTP_HOST=smtp.hostinger.com
MAIL_SMTP_PORT=587
MAIL_SMTP_USER=no-reply@iltuodominio.it
MAIL_SMTP_PASSWORD=la-password-della-casella
MAIL_SMTP_ENCRYPTION=tls
```

Tre cose che vanno storte più spesso:

- **`APP_DEBUG=true` dimenticato.** Gli errori di PHP finiscono nella pagina
  e mostrano i percorsi del server a chiunque.
- **`APP_URL` rimasto su localhost.** Indirizzi canonici, hreflang, Open
  Graph e sitemap escono tutti sbagliati.
- **`MAIL_TO_ADDRESS` lasciato a `example.test`.** Le richieste di
  prenotazione non arrivano a nessuno, e nessuno se ne accorge.

---

## 5. La posta

Serve una casella del dominio, non una Gmail: un messaggio spedito da
`@gmail.com` ma inviato dal server di Hostinger non passa i controlli SPF e
finisce nella posta indesiderata.

1. hPanel → **E-mail → Account e-mail** → creane una, per esempio
   `no-reply@iltuodominio.it`
2. Mettine le credenziali nel `.env` come sopra
3. Prova il modulo dei contatti e controlla che il messaggio arrivi

Con `MAIL_TRANSPORT=mail` funziona anche senza credenziali, ma i messaggi non
sono autenticati e arrivano peggio. `smtp` è la scelta giusta.

### Cambiare o aggiungere l'indirizzo che riceve le richieste

**Dall'area riservata**, sezione **Ricezione e-mail**: fino a cinque
indirizzi, e ogni richiesta arriva a tutti. Accanto c'è il pulsante **Manda un
messaggio di prova**, per controllare subito che la posta arrivi. Vale dalla
richiesta successiva.

Se in quella pagina lasci tutti i campi vuoti, valgono gli indirizzi della
riga **`MAIL_TO_ADDRESS`** del file `.env` sul server: è la riserva, e resta
quella da compilare alla prima installazione. Anche lì più indirizzi si
separano con una virgola:

```ini
MAIL_TO_ADDRESS=info@arcodelvento.it, daniele@esempio.it
```

Il `.env` si apre con il File Manager di hPanel (o FileZilla) nella cartella
del sito — `public_html/`, o `public_html/assisiapartment/` per la prova. Il
nome comincia con un punto: se non lo vedi, attiva «mostra file nascosti».

**La casella che spedisce** invece si cambia solo nel `.env`, perché ha una
password e non deve passare da una pagina web: `MAIL_FROM_ADDRESS`,
`MAIL_SMTP_USER` e `MAIL_SMTP_PASSWORD` sono indirizzo, utente e password di
quella casella (su Hostinger mittente e utente devono essere lo stesso
indirizzo). Si cambiano solo se cambi la casella da cui partono i messaggi, e
allora tutte e tre insieme. La pagina Ricezione e-mail la mostra, senza la
password.

L'e-mail che compare **sul sito** per gli ospiti è un'altra cosa: si cambia
dall'area riservata, *La struttura → Contatti*.

Nella copia di prova `MAIL_TRANSPORT=log`: i messaggi non partono, restano
in `storage/mail/`. Per ricevere davvero serve `smtp` con i dati di una
casella.

---

## 6. Il certificato HTTPS

hPanel → **Sicurezza → SSL** → installa il certificato (è incluso).

L'`.htaccess` manda già tutto su `https`, quindi **installa il certificato
prima di caricare**, o il sito reindirizzerà verso un indirizzo che ancora
non risponde.

---

## 7. Il database — solo se ti serve

Il sito **non ne ha bisogno**: legge le camere da `content/rooms.php`. Per
cinque camere che cambiano due volte l'anno, un file di testo si modifica e
si ricarica. Il database serve quando vuoi disponibilità reali e uno storico
delle prenotazioni.

1. hPanel → **Database → Database MySQL** → creane uno con un utente
2. **phpMyAdmin** → Importa → `database/schema.sql`, poi `database/seed.sql`
3. Nel `.env`:

```ini
DB_DSN=mysql:host=localhost;dbname=uXXXXXX_arcodelvento;charset=utf8mb4
DB_USER=uXXXXXX_arco
DB_PASSWORD=...
```

L'applicazione passa da sola ai repository su MySQL. Nessuna vista cambia.

---

## 8. Il controllo finale

**Da riga di comando** (hPanel → Avanzate → Terminale, oppure SSH):

```bash
cd ~/arcodelvento    # o public_html
php tools/preflight.php
```

**Solo FTP?** Metti un `PREFLIGHT_TOKEN` nel `.env`, copia
`tools/preflight.php` dentro `public/` per un momento, apri
`https://iltuodominio.it/preflight.php?token=...` — **e poi cancellalo.**

Dice, in chiaro, se si può pubblicare e che cosa manca.

**Un bloccante non si toglie da riga di comando: il CIN.** Il Codice
Identificativo Nazionale va esposto nel sito e in ogni annuncio, e la sanzione
per chi non lo fa parte da 800 €. Si ottiene dalla Banca Dati Strutture
Ricettive del Ministero del Turismo e si scrive in `content/settings.php`,
sotto `legal.cin`. Finché non c'è, il piè di pagina dice `CIN [da confermare]`
— che è onesto verso l'ospite, ma non mette in regola la struttura.

### E a mano, aprendo il sito

- [ ] `https://iltuodominio.it/` porta a `/it/`
- [ ] `/it/camere` si apre — se dà 404, manca l'`.htaccess`
- [ ] `/en/rooms` si apre e la lingua cambia sulla **stessa** pagina
- [ ] `/sitemap.xml` mostra il dominio vero, non localhost
- [ ] la prenotazione arriva fino alla conferma
- [ ] il modulo contatti manda un messaggio che **ricevi davvero**
- [ ] le fotografie si vedono (se no: maiuscole e minuscole nei nomi dei file
      — il server Linux le distingue, Windows no)
- [ ] `https://iltuodominio.it/.env` deve dare **403**, mai il contenuto

---

## 9. Quando qualcosa non va

| Sintomo | Quasi sempre è |
| --- | --- |
| Pagina «Installazione incompleta — manca il file .htaccess» | l'`.htaccess` della cartella del sito non è salito: carica `htaccess-radice.txt` e rinominalo |
| «403 Forbidden» nero di Hostinger sulla cartella del sito | come sopra, con un pacchetto precedente al 24 settembre 2026 |
| In una sottocartella, `/it/` porta a una pagina del sito principale | come sopra: senza `.htaccess` la richiesta finisce al sito della radice (WordPress) |
| «Sessione scaduta» a ogni invio di un modulo, anche appena aperto | PHP non ritrova le sessioni. Dal 24 settembre 2026 il sito le tiene in `storage/sessions`: serve che `storage/` sia scrivibile. Il controllo finale lo verifica scrivendone e rileggendone una |
| Testo «installazione incompleta — manca il file .env» | `.env` non caricato o non rinominato (è ancora `env-prova.txt`) |
| 404 su tutto tranne la home | `.htaccess` non caricato, o `AllowOverride` spento |
| 500 su ogni pagina | permessi dei file, o PHP sotto la 8.1 |
| Pagina bianca | `APP_DEBUG=false` e un errore: guarda `storage/logs/` |
| Fotografie mancanti | maiuscole nei nomi: `Camera-01.webp` ≠ `camera-01.webp` |
| Ciclo di redirezioni | `.htaccess` forza https ma il certificato non è attivo |
| La posta non arriva | `MAIL_TRANSPORT` ancora su `log` |
| Caratteri sbagliati | `.woff2` caricati in modalità ASCII invece che binaria |

**Perché l'`.htaccess` resta indietro.** Se trascini nel client FTP le
cartelle, i file nascosti dentro di loro salgono — `public/.htaccess` arriva.
Se selezioni i file sciolti della radice, quelli nascosti non si vedono e non
si selezionano: l'`.htaccess` della radice resta sul tuo computer. Per questo
ogni cartella privata porta anche un suo `.htaccess` che la chiude: senza
quello della radice il sito non funziona, ma `src/`, `content/`, `storage/` e
le altre restano comunque inaccessibili dal web.

Il ciclo di redirezioni e i file caricati in ASCII sono i due che fanno
perdere più tempo. Nel client FTP metti il trasferimento su **binario** o
**automatico**, mai ASCII.

---

## 10. L'area riservata

Si apre all'indirizzo del sito seguito da **`/admin`** — in prova
`https://blackout.in/assisiapartment/admin`.

**La prima volta** chiede di creare l'account: nome utente, password (almeno
10 caratteri) e il **codice di configurazione**, che è la riga
`ADMIN_SETUP_TOKEN` del `.env` (cioè di `env-prova.txt` o
`env-produzione.txt`, prima di rinominarlo). Creato l'account, quella riga si
può svuotare: il controllo finale lo ricorda.

**Password dimenticata**: via FTP cancella `storage/data/admin.json` e rifai la
prima volta con il codice. Se il codice l'hai già tolto dal `.env`, rimettine
uno nuovo (almeno 16 caratteri a caso).

**Le modifiche fatte dal pannello stanno in `storage/data/`** sul server, non nei
file del pacchetto. Quindi:

- quando ricarichi il sito, **non cancellare e non sovrascrivere `storage/`**:
  dentro ci sono modifiche e richieste. Caricare i file nuovi sopra quelli
  vecchi va bene — il pacchetto non contiene `storage/data`;
- se qualcosa va storto dopo una modifica, ogni sezione ha le **versioni
  precedenti**, e una si rimette con un click.

**Le immagini caricate dal pannello** (logo, rosa dei venti, fotografie)
stanno in **`public/assets/media/`**, e quale posto usano sta in
`storage/data/immagini.json`. Anche questa cartella non è nel pacchetto:
quando ricarichi il sito **non cancellare `public/assets/media/`**. Dentro
c'è un `.htaccess` che impedisce a qualunque script di girare: non toglierlo.
Per tornare a un'immagine originale non serve l'FTP: nella pagina
dell'immagine, «Ripristina l'originale».

Il pannello si serve della libreria **GD** di PHP per ritagliare e alleggerire
le immagini: su Hostinger è attiva di norma, e il controllo finale lo verifica.

---

## 11. Dalla prova al dominio vero

**I file del sito sono gli stessi.** Cambia solo il `.env`.

1. Sul dominio vero carica il contenuto del pacchetto in `public_html`
   (modo B) o nella cartella su cui punta il dominio (modo A)
2. Come `.env` usa **`env-produzione.txt`**, non quello di prova: `APP_URL`
   senza cartella, `APP_NOINDEX` tolto, posta vera con la password della
   casella
3. Inserisci il CIN in `content/settings.php` — senza, il controllo finale si
   ferma, ed è giusto così
4. `php tools/preflight.php` (o dal browser, con il gettone): deve dire
   **«SI PUÒ PUBBLICARE»**
5. Solo allora, su blackout.in, **cancella la cartella `assisiapartment`**, o
   almeno lasciala con `APP_NOINDEX=true`: due copie aperte dello stesso sito
   si fanno concorrenza

**Le modifiche fatte dal pannello in prova vengono con te** se copi la cartella
`storage/data/` dalla prova al dominio vero. Dentro ci sono:

| File | Che cosa | Copiarlo? |
| --- | --- | --- |
| `impostazioni.json`, `camere.json`, `testi-it.json`, `testi-en.json`, `domande.json` | le modifiche ai contenuti | **sì** |
| `admin.json` | l'account e la sua password | sì, se vuoi tenere la stessa |
| `richieste.json` | le richieste arrivate alla prova | di solito no: sono di prova |
| `posta.json` | gli indirizzi che ricevono, scelti dal pannello | sì, se sul dominio vero devono essere gli stessi |
| `immagini.json` **insieme a `public/assets/media/`** | le immagini caricate dal pannello | **sì**, sempre in coppia |
| `accessi.json`, la cartella `storico/` | tentativi di accesso, versioni precedenti | no |

`immagini.json` senza la cartella `public/assets/media/` non fa danni — il sito
ignora le righe che puntano a file che non ci sono e mostra gli originali — ma
le immagini caricate in prova non si vedono.

Se invece di ricaricare tutto preferisci spostare i file della prova, sposta
tutto **tranne** il `.env`, e sul nuovo server metti quello di produzione.

---

## 12. Aggiornare il sito dopo

Cambiano i contenuti (`content/`), le viste (`views/`) o i fogli di stile
(`public/assets/css/`): si ricaricano quei file e basta. Non c'è cache da
svuotare — fogli e script portano nell'indirizzo la data di modifica, quindi
al cambio cambia l'indirizzo e il browser riscarica da solo.

Cambiano le fotografie o il logo: il modo più semplice è l'area riservata,
**Immagini e logo**. Scegli il posto, carica il file: il sito lo ritaglia, lo
alleggerisce e lo pubblica subito, e l'originale si rimette con un click.

Per cambiare le immagini *del pacchetto* (quelle predefinite), invece: metti
l'originale in `docs/foto-originali/`, lancia `php tools/build-photos.php`
**sulla tua macchina**, e carica i file nuovi da `public/assets/img/`.

---

## 13. Il pagamento online con SumUp

Acceso, cambia l'ultimo passo della prenotazione: l'ospite scrive **solo nome
ed e-mail**, spunta le condizioni e paga **l'intero soggiorno** su una pagina
di SumUp. I dati della carta non passano mai dal sito. Tornato sul sito, legge
l'esito; se è pagato, a lui arriva la conferma e a te la prenotazione, per
e-mail e nell'area riservata (con la scritta **Pagata** e il codice SumUp).
Spento, tutto resta com'è: la prenotazione è una richiesta.

### Che cosa serve da SumUp

- un conto SumUp con i **pagamenti online** attivi (se non lo sono, lo si
  chiede a SumUp);
- una **chiave API segreta**: si crea nel pannello di SumUp, nella parte per
  sviluppatori (API keys), e comincia con `sup_sk_`;
- il **codice esercente** (merchant code), che il pannello di SumUp mostra nel
  profilo del conto.

La chiave è come una password del conto: scrivila direttamente nel `.env` sul
server, non mandarla per e-mail o in chat. Documentazione di SumUp:
https://developer.sumup.com/online-payments/checkouts/hosted-checkout

### Il file `.env`

```ini
PAYMENT_PROVIDER=sumup
SUMUP_API_KEY=sup_sk_…
SUMUP_MERCHANT_CODE=M…
BOOKING_PROVIDER=sito
```

`APP_URL` deve essere in **https**: SumUp rimanda l'ospite e avvisa il sito a
quell'indirizzo. `SUMUP_API_URL` resta vuoto (serve solo alle prove in locale).

### Prima di aprire

1. Area riservata → **La struttura** → **Condizioni di cancellazione**, in
   italiano e in inglese: chi paga deve saperle prima. Finché mancano, sul sito
   compaiono come «da confermare» e il controllo finale si ferma.
2. `tools/preflight.php`: la sezione «Pagamento» deve essere tutta a posto.
3. **Una prenotazione vera con la tua carta**, sulla camera che costa meno;
   controlla che arrivino le due e-mail e che nell'area riservata risulti
   «Pagata». Poi rimborsala dal pannello di SumUp.

### Il calendario e i rimborsi

Con `BOOKING_PROVIDER=sito` il calendario è quello del sito: tutto libero,
tranne le notti che il sito ha già venduto (pagate, confermate, o con la pagina
di pagamento aperta da meno di mezz'ora). Il sito **non conosce Booking** né le
prenotazioni prese al telefono: quando arriva una prenotazione pagata, chiudi
quelle date su Booking; se nel frattempo erano già occupate, rimborsa
l'ospite dal pannello di SumUp e scrivigli. Con `BOOKING_PROVIDER=demo` alcune
notti risultano occupate a caso: con i pagamenti non va usato.

### Se qualcosa non torna

- SumUp avvisa il sito da solo a `…/pagamenti/sumup`: non c'è niente da
  impostare. Se una notifica si perde, il sito chiede comunque l'esito a SumUp
  quando l'ospite torna, e nell'area riservata ogni prenotazione non pagata ha
  il pulsante **Controlla con SumUp**.
- «Pagamento da controllare»: SumUp dice pagata, ma con un importo diverso.
  All'ospite non parte la conferma finché non guardi tu la transazione.
- Per spegnere i pagamenti basta svuotare `PAYMENT_PROVIDER`: si torna alle
  richieste, e le prenotazioni già pagate restano nell'area riservata.

