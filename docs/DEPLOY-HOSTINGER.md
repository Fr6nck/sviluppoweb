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

---

## 2. Che cosa caricare

Circa **11 MB**, 234 file.

**Carica**: `public/` `src/` `views/` `content/` `config/` `database/`
`tools/` `.htaccess` `README.md`

**Non caricare**:

| Cosa | Perché |
| --- | --- |
| `.git/` | è la storia del progetto, non serve al sito e pesa |
| `.env` | va **creato sul server**, non copiato: quello locale ha i valori di sviluppo |
| `docs/foto-originali/` | 1,4 MB di originali già lavorati: servono a te, non al sito |
| `storage/mail/` `storage/logs/` | contenuto: si riempiono da sole |

**Attenzione ai file che cominciano con un punto.** Molti client FTP li
nascondono, e se non carichi `.htaccess` il sito risponde 404 su ogni pagina
tranne la home. In FileZilla: *Server → Forza visualizzazione file nascosti*.

---

## 3. Le cartelle scrivibili

`storage/logs/` e `storage/mail/` devono essere scrivibili dal server:
il sito ci scrive i registri e, in modalità `log`, i messaggi dei moduli.

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
| 404 su tutto tranne la home | `.htaccess` non caricato, o `AllowOverride` spento |
| 500 su ogni pagina | permessi dei file, o PHP sotto la 8.1 |
| Pagina bianca | `APP_DEBUG=false` e un errore: guarda `storage/logs/` |
| Fotografie mancanti | maiuscole nei nomi: `Camera-01.webp` ≠ `camera-01.webp` |
| Ciclo di redirezioni | `.htaccess` forza https ma il certificato non è attivo |
| La posta non arriva | `MAIL_TRANSPORT` ancora su `log` |
| Caratteri sbagliati | `.woff2` caricati in modalità ASCII invece che binaria |

Il ciclo di redirezioni e i file caricati in ASCII sono i due che fanno
perdere più tempo. Nel client FTP metti il trasferimento su **binario** o
**automatico**, mai ASCII.

---

## 10. Aggiornare il sito dopo

Cambiano i contenuti (`content/`), le viste (`views/`) o i fogli di stile
(`public/assets/css/`): si ricaricano quei file e basta. Non c'è cache da
svuotare — fogli e script portano nell'indirizzo la data di modifica, quindi
al cambio cambia l'indirizzo e il browser riscarica da solo.

Cambiano le fotografie: metti l'originale in `docs/foto-originali/`, lancia
`php tools/build-photos.php` **sulla tua macchina**, e carica i file nuovi da
`public/assets/img/`.
