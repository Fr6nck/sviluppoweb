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
posta e la chiave di SumUp. `env-produzione.txt` è già compilato con
produzione, debug spento, dominio ricavato dall'e-mail della struttura, SMTP di
Hostinger, calendario del sito (`BOOKING_PROVIDER=sito`), pagamento SumUp
(`PAYMENT_PROVIDER=sumup`) e un gettone casuale per il controllo finale.
Mancano la password della casella e i due dati di SumUp (vedi il punto 13): li
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

### Se le e-mail non partono

Area riservata → **Ricezione e-mail** → **Manda un messaggio di prova**. Se
non parte, l'avviso dice a che punto si ferma e che cosa risponde il server di
posta; la stessa riga va nel registro degli errori di PHP, anche per le
richieste e le prenotazioni. La password non compare mai. I casi più comuni:

| L'avviso dice | Che cosa fare |
| --- | --- |
| non accetta l'accesso con l'utente…, risposta «535…» | in hPanel → E-mail, controlla che la casella esista e reimpostane la password; scrivi la stessa in `MAIL_SMTP_PASSWORD`, da Configurazione |
| non accetta … come mittente senza accesso, risposta «530 Authentication required» | in Configurazione mancano «Utente SMTP» e «Password della casella»: scrivi l'indirizzo della casella e la sua password (con l'utente vuoto il sito usa il mittente, ma la password serve) |
| non accetta … come mittente | `MAIL_FROM_ADDRESS` e `MAIL_SMTP_USER` devono essere lo stesso indirizzo |
| il server di posta … non risponde | `MAIL_SMTP_HOST=smtp.hostinger.com`, e porta 465 con `ssl` oppure 587 con `tls` |
| scritto in storage/mail, non spedito | `MAIL_TRANSPORT` è ancora `log`, il modo della copia di prova: mettilo a `smtp` |

La casella deve essere su Hostinger: se la posta del dominio sta da un altro
provider, servono host, porta e credenziali di quel provider. Quello che si
vede già storto prima di provare (mittente diverso dalla casella, password
vuota, porta e cifratura che non vanno insieme) la pagina lo segnala da sola.

Con il pagamento online acceso, le e-mail della prenotazione partono solo
quando SumUp conferma il pagamento, sia alla struttura sia all'ospite. Senza
pagamento online l'ospite non riceve e-mail: arriva solo la richiesta alla
struttura.

### Cambiare o aggiungere l'indirizzo che riceve le richieste

**Dall'area riservata**, sezione **Ricezione e-mail**: fino a cinque
indirizzi, e ogni richiesta arriva a tutti. Accanto c'è il pulsante **Manda un
messaggio di prova**, per controllare subito che la posta arrivi. Vale dalla
richiesta successiva.

Se in quella pagina lasci tutti i campi vuoti, valgono gli indirizzi della
riga **`MAIL_TO_ADDRESS`** del file `.env` sul server: è la riserva. Nei file
`env-produzione.txt` ed `env-prova.txt` del pacchetto è già
`dpecetta@gmail.com` (da `contacts.requests_email` in `content/settings.php`);
la casella che spedisce resta `info@arcodelvento.it`, perché Hostinger non
spedisce a nome di Gmail. Anche lì più indirizzi si separano con una virgola:

```ini
MAIL_TO_ADDRESS=dpecetta@gmail.com, info@arcodelvento.it
```

Su un sito già installato il `.env` non si ricarica: l'indirizzo si cambia da
**Configurazione** (voce `MAIL_TO_ADDRESS`) o da **Ricezione e-mail**.

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
| Ho caricato il pacchetto ma non cambia niente | Guarda in fondo a una pagina qualsiasi dell'area riservata: **«Versione del sito: pacchetto del …»** deve dire la data che sta nel nome dello .zip (`arcodelvento-20261110-1544` = 10/11/2026 alle 15:44). Se dice una data vecchia, i file nuovi non sono arrivati: cartella sbagliata (`public_html/` e non `public_html/assisiapartment/`, o viceversa), FileZilla che ha chiesto «sovrascrivere?» e ha saltato, o la cache di Hostinger (hPanel → svuota la cache del sito). Se la data è giusta e manca una cosa che è solo del super admin, guarda in alto a destra: sotto il nome c'è scritto **Amministratore** o **Super admin** |
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

**Password dimenticata**: se c'è il super admin (punto 15), gliela reimposta lui:
area riservata → **Account** → «La password del gestore». Scrive la password
nuova due volte e la sua per conferma; le sessioni aperte del gestore si
chiudono, e con la spunta (attiva in partenza) al primo accesso il gestore deve
sceglierne una sua. La password nuova si comunica a voce o al telefono: il sito
non la manda a nessuno.

Senza super admin: via FTP cancella `storage/data/admin.json` e rifai la prima
volta con il codice. Se il codice l'hai già tolto dal `.env`, rimettine uno
nuovo (almeno 16 caratteri a caso).

Ogni cambio di password, anche dalla pagina Account, chiude le altre sessioni
aperte con la vecchia password.

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

**La decisione è presa: si paga tutto online, con SumUp, al momento della
prenotazione. Non c'è caparra.** La tassa di soggiorno resta esclusa e si paga
all'arrivo.

Acceso, cambia l'ultimo passo della prenotazione: l'ospite scrive nome,
e-mail, telefono se vuole, e i **dati di fatturazione** (indirizzo, CAP, città,
paese; il codice fiscale se risiede in Italia; ragione sociale, partita IVA e
codice SDI o PEC se vuole la fattura intestata a un'azienda), spunta le
condizioni e paga **l'intero soggiorno** su una pagina sicura di SumUp. I dati
di fatturazione restano sul sito: si leggono nell'area riservata e nell'e-mail
della prenotazione, e a SumUp non si passano. Il codice fiscale e la partita
IVA italiani si controllano già nel modulo (lunghezza e carattere di
controllo). I dati della carta non passano mai dal sito. Tornato sul sito,
legge l'esito; se è pagato, gli arriva la conferma e a te la prenotazione, per
e-mail e nell'area riservata (con la scritta **Pagata** e il codice SumUp).
In Informazioni compare la riga **Pagamento** («Online, con carta, al momento
della prenotazione… Nessuna caparra»), e nella privacy il paragrafo su SumUp.
Spento — `PAYMENT_PROVIDER` vuoto, o le due righe SumUp vuote — la
prenotazione è una richiesta e quelle due parti non si vedono.

### Che cosa serve da SumUp

- un conto SumUp con i **pagamenti online** attivi (se non lo sono, lo si
  chiede a SumUp);
- una **chiave API segreta**: su me.sumup.com, menu del profilo →
  **Impostazioni** → **Per sviluppatori** → **Toolkit** → **Chiavi API** →
  **Crea**. Dagli un nome, poi copiala subito: SumUp non la conserva, e se si
  perde se ne crea un'altra. Non è la «SumUp Public Key» che la pagina mostra
  già: quella non serve;
- il **codice esercente** (merchant code, di solito una M seguita da lettere e
  cifre): nelle impostazioni del conto, fra i dati dell'attività.

Per provare senza soldi veri c'è la **modalità di prova** (vedi sotto).

La chiave è come una password del conto: scrivila direttamente nel `.env` sul
server, non mandarla per e-mail o in chat. Documentazione di SumUp:
https://developer.sumup.com/online-payments/checkouts/hosted-checkout

### Il file `.env`

```ini
PAYMENT_PROVIDER=sumup
SUMUP_API_KEY=…la chiave segreta…
SUMUP_MERCHANT_CODE=M…
BOOKING_PROVIDER=sito
MAIL_TRANSPORT=smtp
```

`env-produzione.txt` e `env-prova.txt` escono dal pacchetto già con
`PAYMENT_PROVIDER=sumup` e `BOOKING_PROVIDER=sito`: restano da scrivere la
chiave e il codice esercente. `BOOKING_PROVIDER=sito` è il calendario vero:
con quello dimostrativo si incasserebbe su date che nessuno ha controllato.
`MAIL_TRANSPORT=smtp`, con i dati della casella (punto 5), perché le conferme
di pagamento partono per e-mail.

`APP_URL` deve essere in **https**: SumUp rimanda l'ospite e avvisa il sito a
quell'indirizzo. `SUMUP_API_URL` resta vuoto (serve solo alle prove in locale).

In prova, con le due righe SumUp vuote, il pagamento resta spento e il
controllo finale lo segnala soltanto. Sul dominio vero, invece, si ferma.

### La modalità di prova

Con `PAYMENT_MODE=test` (in **Configurazione** → Pagamento online → Modalità:
«Prova») il sito paga sul **conto di prova di SumUp** e nessuno paga davvero.
Il conto di prova si crea su me.sumup.com → **Impostazioni** → **Per
sviluppatori** → scheda **Sandboxes**; da quel conto si crea la sua chiave in
**Chiavi API**. Chiave e codice esercente del conto di prova vanno in
`SUMUP_TEST_API_KEY` e `SUMUP_TEST_MERCHANT_CODE`: le chiavi del conto vero
restano dove sono e in prova non si usano mai.

```ini
PAYMENT_PROVIDER=sumup
PAYMENT_MODE=test
SUMUP_TEST_API_KEY=…la chiave del conto di prova…
SUMUP_TEST_MERCHANT_CODE=M…
```

In prova:

- chi prenota vede «Pagamento di prova» nel modulo, nella pagina di passaggio a
  SumUp e nell'esito;
- alla pagina di SumUp si paga con la carta **4200 0000 0000 0091**, una
  scadenza futura e un CVV qualsiasi (le carte di prova di SumUp:
  https://developer.sumup.com/online-payments/testing/). Un importo di 11,00 €
  dà un pagamento rifiutato;
- le e-mail hanno **PROVA** nell'oggetto e una riga che lo dice;
- nell'area riservata la prenotazione ha l'etichetta **Prova**, e la bacheca
  ricorda che il pagamento è in prova.

Quando torni a «Reale» (`PAYMENT_MODE` vuoto), le prenotazioni di prova restano
nell'area riservata con la loro etichetta, ma **non occupano più le date** e non
contano nei numeri della bacheca: si possono eliminare. Sul dominio vero il
controllo finale si ferma se la modalità è ancora «Prova».

### Prima di aprire

1. Area riservata → **La struttura** → **Condizioni di cancellazione**, in
   italiano e in inglese: chi paga deve saperle prima. Compaiono prima del
   pagamento e nella riga **Disdetta** di Informazioni. Finché mancano la riga
   Disdetta non si vede, nella prenotazione restano «da confermare» e il
   controllo finale si ferma.
2. `tools/preflight.php`: la sezione «Pagamento» deve essere tutta a posto.
3. Una prenotazione in **modalità di prova**, per vedere tutto il giro senza
   soldi veri.
4. Rimessa la modalità su «Reale», **una prenotazione vera con la tua carta**,
   sulla camera che costa meno; controlla che arrivino le due e-mail e che
   nell'area riservata risulti «Pagata». Poi rimborsala dal pannello di SumUp.

### Il calendario e i rimborsi

Il calendario è quello del sito: tutto libero, tranne le notti che il sito ha
già venduto (pagate, confermate, o con la pagina di pagamento aperta da meno di
mezz'ora), quelle di Booking.com con il calendario iCal collegato (punto 16) e
i blocchi messi a mano dal **Calendario** (punto 16). Il telefono il sito non lo
conosce: una prenotazione presa a voce si blocca dal Calendario. Quando arriva
una prenotazione pagata, chiudi quelle date su Booking; se nel frattempo erano
già occupate, rimborsa l'ospite dal pannello di SumUp e scrivigli.

Il vecchio calendario dimostrativo (`BOOKING_PROVIDER=demo`), che dava per
occupate notti inventate, non c'è più: anche un `.env` che porta ancora `demo`,
o non ha la voce, usa il calendario vero.

### Se qualcosa non torna

- SumUp avvisa il sito da solo a `…/pagamenti/sumup`: non c'è niente da
  impostare. Se una notifica si perde, il sito chiede comunque l'esito a SumUp
  quando l'ospite torna, e nell'area riservata ogni prenotazione non pagata ha
  il pulsante **Controlla con SumUp**.
- «Pagamento da controllare»: SumUp dice pagata, ma con un importo diverso.
  All'ospite non parte la conferma finché non guardi tu la transazione.
- Per spegnere i pagamenti basta svuotare `PAYMENT_PROVIDER`: si torna alle
  richieste, e le prenotazioni già pagate restano nell'area riservata.

---

## 14. Il sito chiuso da un codice (anteprima)

Finché il sito è in anteprima, **chi lo apre vede solo una pagina con il campo
del codice**, con il sito sfocato dietro. Con il codice giusto — all'inizio
**`Blackout_2026`**, maiuscole e minuscole non contano — si apre la pagina che
aveva chiesto, e su quel browser il sito resta aperto trenta giorni.

- È acceso appena carichi il pacchetto: non c'è niente da impostare.
- **Si spegne, si riaccende e si cambia il codice** dall'area riservata:
  **Accesso al sito**. Il giorno dell'apertura basta togliere la spunta.
- Cambiando il codice, chi era già entrato deve scrivere quello nuovo.
- L'area riservata (`/admin`) non è mai chiusa, e chi ci è entrato vede il sito
  aperto. Per vedere che cosa vede un visitatore usa una finestra anonima.
- Dieci codici sbagliati dalla stessa connessione: un quarto d'ora di pausa.
- Mentre il sito è chiuso, `robots.txt` dice ai motori di ricerca di stare
  fuori, e ogni pagina porta `noindex`.
- **Via d'uscita via FTP**: `SITE_ACCESS_CODE=off` nel `.env` spegne il codice in
  ogni caso, qualunque cosa dica l'area riservata.

Dietro la scheda c'è una **fotografia già sfocata** del sito
(`public/assets/img/sipario/`), non la pagina vera: nel sorgente o con la
modalità lettura del browser non si legge niente. Se il sito cambia molto e la
vuoi rifare: `node tools/build-sipario.mjs http://indirizzo-del-sito-di-prova`
sulla tua macchina (vuole Node e Playwright, e il sito con `SITE_ACCESS_CODE=off`),
poi carica i due JPG.

---

## 15. Il super admin e il pannello Configurazione

Oltre all'account del gestore c'è il **super admin**: il tuo. È il solo che
vede **Configurazione**, cioè tutte le voci del file `.env` dal browser, senza
FTP: indirizzo del sito, posta, pagamento SumUp, calendario, codici.

**Crearlo.** Nel `.env` c'è la riga `SUPERADMIN_SETUP_TOKEN` (i file
`env-produzione.txt` ed `env-prova.txt` del pacchetto la portano già, con un
codice a caso). Apri l'indirizzo del sito seguito da **`/admin/super`**, scrivi
quel codice, un nome utente diverso da quello del gestore e una password. Si
entra dritti in Configurazione. Da lì svuota `SUPERADMIN_SETUP_TOKEN`: non serve
più.

**Entrare dopo.** Da `/admin/super` (porta dritto a Configurazione) o dal login
normale di `/admin`: il nome utente dice chi entra.

**Il gestore non lo vede.** Nel suo menu non c'è Configurazione, e
`/admin/configurazione` e `/admin/super` per lui sono pagine che non esistono.

**Che cosa resta al gestore.** Bacheca, Richieste, Calendario, La struttura,
Camere e tariffe e Account. Immagini e logo, Testi del sito, Domande frequenti, Accesso
al sito e Ricezione e-mail sono solo del super admin: il gestore non le vede nel
menu, e se ne apre l'indirizzo trova una pagina che non esiste. Le tariffe e
le foto delle camere (principale e galleria) le cambia anche lui, dalla pagina
di ogni camera; logo, foto della home e delle pagine, e i testi, li cambi tu.

**La tipologia di una camera.** Nella scheda di ogni camera tu hai anche il
campo **Tipologia**, in italiano e in inglese: è la scritta che si legge sul
sito, per esempio «Singola con letto matrimoniale». Cambiala lì e salva: vale
per quella camera sola, nella sua pagina, negli elenchi e nella prenotazione.
Gli ospiti e le tariffe non cambiano. Riscritta com'era all'inizio, la camera
torna alla scritta di partenza. Per cambiare la scritta a tutte le camere dello
stesso tipo c'è Testi del sito → Tipologie, letti e viste.

**Quanti ospiti in una camera.** Nella scheda di ogni camera tu hai in più il
campo **Ospiti al massimo** (da 1 a 6). Salvato, compare un campo di tariffa
per ogni ospite: la camera si prenota fino all'ultimo numero di ospiti che ha
una tariffa, quindi dopo aver alzato il numero scrivi anche la tariffa nuova.
Abbassandolo, le tariffe in più si tolgono da sole. La tipologia e i letti
non cambiano da soli.

**Come funziona il pannello.**

- Le voci sono divise in gruppi (sito, pagamento, prenotazioni, posta, codici,
  titolare, database), con un nome comprensibile e la spiegazione.
- **Le password e le chiavi non si rivedono**: si vede se ci sono e quanto sono
  lunghe. Per cambiarne una scrivi quella nuova; lasciata vuota, resta com'è.
  «Svuota» la cancella.
- **Ogni salvataggio chiede la tua password**, e cambia solo le righe dei valori
  cambiati: commenti e righe sconosciute restano come sono.
- **Prima di scrivere fa una copia** del file com'era (in
  `storage/data/ambiente/`, le ultime dieci). In fondo alla pagina si rimette
  una versione precedente con un click.
- Cambiare **l'indirizzo del sito** chiede una spunta in più: se è sbagliato,
  link, stili e pagamento smettono di funzionare.
- In cima, **«Da guardare»** segnala le combinazioni che non vanno: debug
  acceso in produzione, SumUp senza chiave, posta su `log`, codici rimasti.
- Una voce impostata nel **pannello di Hostinger** (variabili d'ambiente) vince
  sul file: si vede, ma da qui non si cambia.
- Se il sito non può scrivere il `.env`, la pagina si legge soltanto: dai al file
  i permessi 644 via FTP.

**Channel manager (prova).** Nel tuo menu, sotto Configurazione, c'è una
funzione di prova: **Scollega da tutte le camere** sospende la lettura dei
calendari di Booking.com e toglie le notti lette, così il sito si vede con la
sola disponibilità propria; **Ricollega e sincronizza adesso** li rilegge
subito. I link iCal restano salvati. Le prenotazioni dirette e i blocchi
manuali non si toccano: la pagina li elenca. Finché è scollegato, la bacheca e
il Calendario lo dicono, e il controllo finale si ferma in produzione:
**scollegato sul sito vero, le notti vendute su Booking tornano prenotabili
qui**. Per togliere la funzione, il README elenca i file, sotto «Interruttore
del channel manager».

**Password del gestore dimenticata.** Da **Account** → «La password del
gestore»: vedi il punto 10.

**Password del super admin dimenticata.** Nessuno la può reimpostare dal sito.
Via FTP cancella
`storage/data/superadmin.json`, rimetti nel `.env` una riga
`SUPERADMIN_SETUP_TOKEN=` con un codice nuovo (almeno 16 caratteri a caso) e
rifai la creazione da `/admin/super`. Il `.env` e le sue copie non si toccano.

---

## 16. Il calendario di Booking.com (iCal)

Booking.com e il sito possono leggersi il calendario a vicenda, camera per
camera, con il formato iCal: un link da copiare da una parte all'altra. Niente
da installare.

### Da Booking.com al sito

1. Nell'extranet di Booking.com: **Tariffe e disponibilità → Sincronizza
   calendari → Esporta calendario**: per ogni camera c'è un link che finisce
   per `.ics`. Copialo.
2. Area riservata → **Camere e tariffe** → la camera → campo **Calendario di
   Booking.com (link iCal)**: incolla il link e salva. Lo può fare anche il
   gestore.
3. Il sito lo legge subito e dice quante notti risultano occupate; da lì in
   poi lo rilegge da solo ogni 20 minuti, quando qualcuno cerca una data. Nella
   scheda della camera, il riquadro **Calendario di Booking.com** dice quando
   l'ha letto l'ultima volta e quali periodi ha trovato; **Aggiorna adesso** lo
   rilegge al momento.

Le notti prenotate su Booking risultano occupate anche sul sito: la camera non
compare fra quelle libere e non si può pagare. Vale anche per il link iCal di
Airbnb. Del calendario di Booking il sito tiene solo le date, mai i nomi.

**I limiti, da sapere.** Fra la prenotazione su Booking e la lettura passano
fino a 20 minuti: in quei minuti la camera resta prenotabile qui. Se Booking
non risponde, restano buone le notti lette l'ultima volta e il pannello mostra
l'errore. Il controllo finale (`tools/preflight.php`) rilegge i calendari e
segnala quelli che non si leggono.

### Dal sito a Booking.com

Nella stessa scheda c'è il link del calendario che il sito pubblica per quella
camera (`…/ical/camera-01/<codice>.ics`): le notti pagate o confermate qui e
quelle bloccate a mano dal Calendario, senza nomi. In Booking.com, **Sincronizza calendari → Importa calendario**,
incolla il link e dagli un nome («Sito»). Booking lo rilegge con i suoi tempi,
di solito qualche ora. Il link contiene un codice segreto: non pubblicarlo.
Funziona anche con il sito chiuso dal codice d'accesso.

### Il Calendario e i blocchi manuali

Area riservata → **Calendario** (lo vedono tutti e due gli account): un mese
per volta, una riga per camera e una casella per notte.

| Casella | Che cosa |
| --- | --- |
| vuota | libera |
| **D**, rosso scuro | prenotazione diretta, fatta sul sito |
| **B**, blu | prenotazione arrivata da Booking.com |
| **M**, a righe | blocco manuale |

Sotto, per ogni camera, i periodi del mese con le date: «Booking.com dal
07/12/2026 al 10/12/2026». Il giorno di partenza è libero: «dal 7 al 10» vuol
dire le notti del 7, 8 e 9.

**Bloccare una camera.** Nel riquadro **Blocca una camera**: la camera, «Dal»,
«Al» (il giorno di partenza) e, se serve, una nota («telefono, sig. Rossi»).
**Blocca le date**: da quel momento la camera non si prenota dal sito per quelle
notti. Il blocco compare nel calendario come «Blocco manuale» e nell'elenco
**Blocchi manuali**, dove si toglie con **Togli**. Serve per le prenotazioni
prese al telefono, o per una di Booking che il calendario iCal non ha ancora
letto (arriva con fino a 20 minuti di ritardo).

**Il blocco va anche a Booking.com**, con il calendario che il sito pubblica
(«Dal sito a Booking.com», sopra): se Booking lo importa, chiude le stesse notti
anche lì, di solito entro qualche ora. Va solo la data, con la scritta «non
disponibile»: la nota resta nel pannello. Togliendo il blocco, al giro dopo
Booking riapre quelle notti.

Quando la prenotazione arriva poi da Booking, non c'è doppione: le notti
compaiono una volta sola, come Booking, e il blocco dice che Booking.com segna
occupate anche quelle notti. Se il blocco era per quella prenotazione di
Booking, si può togliere. Se era per una prenotazione presa al telefono,
lascialo: Booking potrebbe mostrare soltanto il blocco che il sito gli manda.
