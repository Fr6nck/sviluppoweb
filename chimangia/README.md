# ChiMangia — sito, pannello dei contenuti e piattaforma di prenotazione

Un solo progetto con due parti:

- **il sito pubblico** di ChiMangia (prenotazione pasti per ristoranti, trattorie e mense), con un pannello da cui si modificano tutti i contenuti senza toccare il codice;
- **la piattaforma**: i locali si registrano, i loro clienti prenotano notti e pasti dal telefono, la cucina trova i conteggi pronti, il fornitore gestisce clienti, funzioni, manutenzione e backup.

- PHP 8.1+ senza framework, MySQL/MariaDB (PDO, query preparate, utf8mb4).
- Nessuna dipendenza esterna: font in locale, nessun Google Fonts, nessun cookie sul sito pubblico (quindi nessun banner).
- Funziona su hosting condiviso (cPanel, Hostinger hPanel) anche in una sottocartella.

---

## 1. Installazione su hosting condiviso

### Requisiti
- PHP 8.1 o successivo con le estensioni `pdo_mysql`, `mbstring`, `openssl`, `fileinfo` (e `gd` con WebP per il ridimensionamento delle immagini, consigliata).
- Un database MySQL o MariaDB.
- Apache o LiteSpeed con `.htaccess` attivo (è così su cPanel e Hostinger).

### Passo 1 — Crea il database
**cPanel:** *Database MySQL* → crea un database, poi un utente con password, poi *Aggiungi utente al database* con **tutti i privilegi**.
**Hostinger (hPanel):** *Database* → *Gestione MySQL* → crea database e utente insieme.

Annota nome del database, utente e password (su questi pannelli hanno un prefisso, es. `u123456789_chimangia`). Il server è quasi sempre `localhost`.

### Passo 2 — Carica i file
1. Apri il *File Manager* (o collegati via FTP) e vai in `public_html` (oppure nella sottocartella dove vuoi il sito).
2. Carica lo zip e usa **Estrai**: i file sono alla radice dello zip, quindi finiscono direttamente nella cartella giusta.
3. Cancella lo zip dal server.
4. Controlla che la versione PHP sia 8.1 o superiore (cPanel: *Seleziona versione PHP*; Hostinger: *Avanzate → Configurazione PHP*).
5. Attiva il certificato SSL (HTTPS) prima di installare.

### Passo 3 — Procedura guidata
1. Apri `https://tuodominio.it/install/`.
2. Controlla che i requisiti siano tutti verdi.
3. Compila:
   - **Database:** server (`localhost`), porta (`3306`), nome, utente, password, prefisso tabelle (lascia `cm_`).
   - **Indirizzo del sito:** completo, con `https://` e senza barra finale.
   - **Cartella dati privata:** lascia quella proposta. Se possibile è fuori da `public_html` e ospita cache HTML e log.
   - **Primo amministratore:** nome, email, password (almeno 10 caratteri, lettere e numeri).
4. Premi **Installa**. La procedura crea le tabelle, carica i contenuti iniziali, scrive `config.php` e si blocca da sola.
5. **Cancella la cartella `install/`** dal server.

Il pannello è su `https://tuodominio.it/admin/`.

---

## 2. Il pannello del sito in breve

| Voce | Cosa si fa |
| --- | --- |
| **Cruscotto** | Richieste nuove, ultime 5 richieste, data dell'ultima pubblicazione, bozze in sospeso con *Pubblica tutto*. |
| **Fotografie** | In *Apertura* scegli la disposizione (slider a tutta larghezza oppure foto a lato del testo) e fino a 6 fotografie: con più di una l'apertura diventa uno slider (con pausa e puntini). In *Il problema* e *Una giornata* scegli tra le fotografie e le illustrazioni del progetto, oppure un'immagine caricata da *Media*. |
| **Aspetto** | Colori in proporzione 60 / 30 / 10: bianco e crema, blu notte, zafferano. *Il problema* e *Contatti* hanno una fotografia di sfondo a tutta larghezza (si sceglie nelle rispettive pagine; senza foto il fondo è blu). In *Come funziona* una dimostrazione animata mostra il telefono del cliente e la scheda della cucina: i testi sono in *Apertura*. Le animazioni si spengono da sole per chi ha chiesto "meno movimento" al proprio dispositivo. |
| **Titoli** | Il testo tra `*asterischi*` esce in corsivo, es. `Come *funziona*`. |
| **Contenuti** | Una pagina per ogni sezione: testi, pulsanti (testo e destinazione), Mostra/Nascondi. Le schede si aggiungono, eliminano e riordinano trascinando la maniglia ⠿ o con le frecce. Le icone si scelgono dalla libreria del progetto. |
| **Prezzi** | Pacchetti (mensile, annuale e attivazione, pieni e di lancio), offerta di lancio (attiva, percentuale, data di fine), pulsante *Calcola prezzi di lancio*, modulo Hotel, testi di esempio, prova gratuita e nota IVA. Con l'offerta spenta o scaduta il sito mostra solo i prezzi pieni. |
| **Domande frequenti** | Aggiunta, modifica, riordino, Mostra/Nascondi per ogni domanda. |
| **Dati aziendali** | Ragione sociale, indirizzo, P. IVA, email, telefono, social. |
| **SEO** | Title, description, immagine di condivisione, dati strutturati. `sitemap.xml` e `robots.txt` si rigenerano da soli. |
| **Privacy / Cookie** | Testi delle pagine `/privacy/` e `/cookie/`. |
| **Richieste** | Ricerca, filtri per stato e data, scheda con note interne e cronologia, esportazione CSV per Excel, eliminazione. |
| **Media** | Caricamento JPG/PNG/WebP fino a 5 MB (ridimensionate a 1600 px, con versione WebP). Il testo alternativo è obbligatorio. |
| **Storico** | Ogni pubblicazione salva una versione completa. *Confronta* mostra le differenze; *Ripristina* crea una bozza (non cambia subito il sito). |
| **Registro azioni** | Accessi, pubblicazioni, modifiche a prezzi, utenti e impostazioni. |
| **Utenti / Impostazioni / Backup** | Solo Amministratore. |

### Bozza, anteprima, pubblicazione
- **Salva bozza:** le modifiche restano nel pannello, il sito non cambia.
- **Anteprima:** salva la bozza e la apre in una nuova scheda.
- **Pubblica:** la modifica va online. Viene salvata una versione nello storico e rigenerata la copia HTML del sito.

Il pubblico riceve sempre la copia HTML in cache, quindi a ogni visita il database non viene interrogato. Chi non ha accesso al pannello può vedere la bozza con il **link riservato** che trovi in *Impostazioni*.

Nei testi brevi si possono usare due segnaposto:
- `{sconto}`: la percentuale dell'offerta di lancio, es. "30%";
- `{prezzo_da}`: il canone mensile più basso, es. "29 €".

### Ruoli
- **Amministratore:** tutto, compresi utenti, impostazioni, backup e registro.
- **Redattore:** contenuti, prezzi, domande frequenti, richieste, media e storico.

---

## 3. La piattaforma

Tre aree, ognuna con il suo accesso e la sua sessione.

| Area | Indirizzo | Chi la usa |
| --- | --- | --- |
| **Registrazione del locale** | `/registrati/` | Il titolare: crea il locale e parte la prova gratuita. |
| **Pannello del locale** | `/accedi/` → `/gestione/` | Titolare, reception, cucina (con l'email). |
| **Area ospiti** | `/ospiti/?l=codice-del-locale` | I clienti del locale, dal telefono. Per registrarsi serve il collegamento (o il codice) del locale; per entrare basta cellulare e password, anche da `/accedi/` o da `/ospiti/` senza codice. |
| **Super admin** | `/superadmin/` (oppure *Clienti e piattaforma* nel pannello del sito) | Il fornitore: stesso accesso dell'Amministratore di `/admin/`. |

Sul sito, *Accedi* e *Prova gratis* nell'intestazione portano a queste pagine; i testi e le destinazioni si cambiano in *Intestazione*.

### Come funziona, in ordine
1. **Il titolare si registra** su `/registrati/`. Il modulo è protetto da un CAPTCHA fatto in casa (cinque cifre disegnate, nessun servizio esterno, nessun cookie), da un campo esca, da un tempo minimo e da un limite per connessione. Nasce il locale, in prova per 7 giorni.
2. **Condivide il collegamento** dell'area ospiti (lo trova nel cruscotto, con il pulsante per WhatsApp).
3. **Gli ospiti si registrano** con cellulare e password (anche qui CAPTCHA), indicano società, diete e intolleranze, e segnano notti, pranzi e cene. Finché la reception non li conferma, non contano in cucina.
4. **Il locale pubblica il menù** del pasto: piatti a scelta (con formule a prezzo fisso o prezzo per piatto) oppure una foto. Chi ha prenotato viene avvisato nell'area ospiti e, se attivo, per email.
5. **L'ospite risponde** entro l'orario limite: sceglie i piatti, chiede una variante o disdice. All'orario limite il menù si chiude da solo e chi non ha risposto viene disdetto (si può spegnere).
6. **Dopo l'orario** l'ospite non cambia più da solo: disdette e aggiunte diventano *Richieste* che lo staff approva o rifiuta.
7. **La cucina** vede i totali per piatto e i presenti divisi per società, con diete, note e varianti; il foglio si stampa.
8. **A fine mese** il riepilogo per società dà pasti, notti e importi, esportabile per Excel.

### Pannello del locale (`/gestione/`)
| Voce | Cosa si fa | Permesso |
| --- | --- | --- |
| **Oggi** | Numeri del giorno, registrazioni e richieste in attesa, collegamento per gli ospiti, messaggio WhatsApp pronto per chi non ha risposto. | tutti |
| **Cucina** | Piatti da preparare e presenti per pranzo e cena; foto rapida del menù; varianti da approvare. | presenti |
| **Registrazioni** | Conferma dei nuovi ospiti, camera, doppioni di società da unire. | conferma |
| **Richieste** | Disdette e aggiunte dopo l'orario, varianti, notti: approva o rifiuta con nota interna. | richieste |
| **Ospiti** | Elenco, ricerca, scheda, pasti e notti inseriti per conto dell'ospite, nuova password, esportazione CSV. | conferma |
| **Società** | Nota per la cucina, contatto, unione dei doppioni. | società |
| **Camere** | Occupazione della settimana, notti da assegnare, elenco camere (modulo Hotel). | camere |
| **Menù e pasti** | Composizione del menù, orario limite, bozza, pubblicazione, copia dell'ultimo menù. | menù |
| **Prospetti e stampe** | Foglio cucina e camere della settimana, pronti per la stampa o il PDF. | stampe |
| **Riepilogo mensile** | Pasti, notti e importi per società; CSV per Excel. | prezzi |
| **Impostazioni** | Dati del locale, pasti serviti, orari limite, giorni di chiusura, prezzi e formule, diete, messaggi. | impostazioni |
| **Staff** | Accessi di reception e cucina, ruoli, collegamento per la password. | staff |
| **Abbonamento** | Stato della prova o del piano, clienti del mese, richiesta di attivazione. | titolare |

I ruoli sono **Amministrazione**, **Reception** e **Cucina**. Il titolare ha sempre tutti i permessi; per gli altri ruoli i permessi si regolano locale per locale dal super admin. Ogni controllo è fatto lato server.

### Super admin (`/superadmin/`)
- **Clienti:** elenco dei locali con stato, piano, scadenza della prova e clienti del mese; nuovo cliente; scheda con dati, note interne, attivazione, sospensione, proroga della prova.
- **Locale demo:** in *Clienti*, un pulsante crea «Locanda Demo», un albergo con ristorante già pieno di dati di prova: 12 ospiti di 4 società, 6 camere, tre settimane di pasti con importi, i menù di oggi e di domani, due registrazioni da confermare (una con una società doppione da unire) e alcune richieste in attesa. La scheda mostra gli accessi da titolare, reception, cucina e ospite e una password casuale unica per tutti, nuova a ogni generazione. Le email degli accessi finiscono in `@demo.invalid`, quindi non parte nessun messaggio; i cellulari degli ospiti sono `300 000 0001`–`0012`. *Ricrea con le date di oggi* riparte da capo, *Elimina* toglie tutto. La demo resta fuori dai numeri d'insieme.
- **Funzioni attive:** undici interruttori per locale (modulo Hotel, menù a scelta, menù in foto, varianti, società, formule, prezzi per piatto, riepilogo mensile, avvisi nell'area ospiti, email, WhatsApp).
- **Permessi dei ruoli:** la tabella permesso × ruolo di ogni locale.
- **Entra nel pannello:** accesso di assistenza al pannello di un locale, con un collegamento monouso valido un minuto; resta scritto nel registro come «fornitore».
- **Funzioni e permessi predefiniti** per i locali nuovi, durata della prova, registrazioni aperte o chiuse, avviso a tutti i locali.
- **Sistema e manutenzione:** controlli (PHP, estensioni, cartelle, email, lavori periodici), modalità manutenzione della piattaforma, coda delle email, registro degli errori.
- **Backup:** copia completa del database (`.sql.gz`) a richiesta e ogni giorno in automatico, con il numero di copie da tenere. Per ripristinare si importa il file da phpMyAdmin.
- **Registro:** tutto quello che succede nei locali, con filtro.
- **Esportazione ed eliminazione** dei dati di un locale (JSON), per le richieste sulla privacy.

### Lavori periodici
Chiusura dei menù all'orario limite, invio delle email in coda, pulizie e backup giornaliero partono da soli a fine richiesta, quando qualcuno usa la piattaforma. Per farli girare anche senza visite si può aggiungere un cron ogni 5 minuti sull'indirizzo indicato in *Super admin → Sistema* (`cron.php?k=…`).

### Cosa non c'è (ancora)
- **Pagamenti online:** l'attivazione del piano è una richiesta al fornitore, che cambia lo stato del locale a mano.
- **Notifiche push:** gli avvisi arrivano nell'area ospiti e per email; su WhatsApp lo staff invia a mano il messaggio già pronto.

---

## 4. Configurazione delle email

Le email servono per le notifiche delle richieste e per il recupero password. Si configurano in *Impostazioni → Email e generali*.

- **mail() del server:** funziona senza configurazione su molti hosting, ma i messaggi finiscono più facilmente nello spam.
- **SMTP (consigliato):** crea una casella email del tuo dominio, poi compila così:

| | Hostinger | cPanel |
| --- | --- | --- |
| Server | `smtp.hostinger.com` | `mail.tuodominio.it` |
| Porta | `465` | `465` |
| Cifratura | SSL | SSL |
| Utente | indirizzo completo della casella | indirizzo completo della casella |
| Password | quella della casella | quella della casella |

Imposta come **indirizzo mittente** la stessa casella. Poi premi **Invia email di prova**.

La password SMTP viene salvata cifrata e non viene più mostrata. I destinatari delle notifiche si indicano uno per riga.

---

## 5. Aggiornare senza perdere i contenuti

1. Da *Backup* scarica un backup JSON. Per sicurezza copia anche la cartella `uploads/`.
2. Carica i file della nuova versione **sovrascrivendo** quelli esistenti, ma senza toccare:
   - `config.php`;
   - la cartella `uploads/`;
   - la cartella dati privata.
3. Apri il sito o un pannello: se la versione è cambiata, il database viene aggiornato da solo. Le tabelle mancanti vengono create e i contenuti esistenti non vengono toccati.

Dalla versione 5 conviene fare prima anche un backup completo del database da *Super admin → Backup*.

Lo zip prodotto da `build-zip.php` non contiene mai `config.php`, `uploads/`, cache, log, sessioni e backup, quindi si può estrarre sopra un'installazione esistente.

---

## 6. Prova in locale

```bash
# database MySQL/MariaDB locale
mysql -u root -e "CREATE DATABASE chimangia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -e "CREATE USER 'cm'@'localhost' IDENTIFIED BY 'cmpass'; GRANT ALL ON chimangia.* TO 'cm'@'localhost';"

# server di sviluppo, dalla cartella del progetto
php -S localhost:8000
```

Apri `http://localhost:8000/install/` e indica:
- **Indirizzo del sito:** `http://localhost:8000`;
- **Cartella dati:** ad esempio quella proposta o `storage`.

Il server integrato di PHP ignora i `.htaccess`: le protezioni valgono solo su Apache/LiteSpeed.

Per creare lo zip da caricare:

```bash
php build-zip.php
```

Lo zip finisce in `dist/`. Sono esclusi `config.php`, i file caricati, cache, log, la cartella `design/` e i file di sviluppo.

---

## 7. Da compilare prima della messa online

- [ ] **Email del sito:** ora è il segnaposto `[EMAIL DEL SITO]` (*Dati aziendali*).
- [ ] **Dati aziendali** (City Up Srl, indirizzo, P. IVA, telefono): verificarli.
- [ ] **Pagine Privacy e Cookie:** il testo iniziale è un segnaposto evidenziato, da far completare o verificare dal consulente.
- [ ] **Configurazione email:** SMTP e destinatari delle notifiche, poi l'email di prova.
- [ ] **Immagine di condivisione** (facoltativa): caricarla da *Media* e sceglierla in *SEO*. Senza, si usa quella predefinita con il logo.
- [ ] **Cartella `install/`:** cancellarla dopo l'installazione.

---

## 8. Sicurezza
- **Database e output:** query preparate ovunque ed escape di tutto l'output.
- **CSRF:** token su ogni modulo e azione dei pannelli e dell'area ospiti. I moduli pubblici usano un token firmato senza cookie.
- **Registrazioni:** CAPTCHA senza servizi esterni (la sfida viaggia cifrata nel modulo, vale 15 minuti e un solo tentativo), campo esca, tempo minimo, limite per connessione.
- **Locali separati:** ogni dato porta l'identificativo del locale e ogni lettura o modifica lo controlla; staff e ospiti hanno sessioni distinte da quella del pannello del sito.
- **Ruoli e permessi:** controllati lato server su ogni pagina e azione, anche nel pannello del locale.
- **Accesso:**
  - password con `password_hash`;
  - blocco di 15 minuti dopo 5 tentativi errati;
  - sessione con cookie `HttpOnly`, `Secure` (su HTTPS) e `SameSite=Lax`, ID rigenerato al login;
  - scadenza dopo 30 minuti di inattività;
  - link di recupero password monouso, valido un'ora.
- **Header:**
  - Content-Security-Policy senza script inline, sia sul sito sia sul pannello;
  - `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`;
  - il pannello è sempre `noindex`.
- **`.htaccess`:**
  - bloccano `app/`, `storage/`, `design/`, `config.php`, log e backup;
  - chiudono `install/` dopo l'installazione;
  - in `uploads/` impediscono l'esecuzione di PHP e servono solo immagini.
- **Modulo contatti:**
  - honeypot e token firmato;
  - tempo minimo di compilazione;
  - limite di invii per IP (gli IP sono salvati solo come hash).
- **Immagini caricate:**
  - tipo reale controllato;
  - nome casuale;
  - ricodificate (vengono eliminati i metadati);
  - SVG non ammessi.
- **Errori:** finiscono nel log della cartella dati e non vengono mai mostrati al pubblico.

## Struttura

```
index.php          sito pubblico (dalla cache HTML)
contatti.php       riceve il modulo e risponde in JSON
anteprima.php      anteprima della bozza (pannello o link riservato)
privacy/ cookie/   pagine legali
admin/             pannello del sito (contenuti, richieste, media)
registrati/        registrazione di un nuovo locale (con CAPTCHA)
accedi/            accesso dello staff, recupero password
gestione/          pannello del locale (titolare, reception, cucina)
ospiti/            area degli ospiti
superadmin/        pannello del fornitore
captcha.php        immagine del CAPTCHA
cron.php           lavori periodici (facoltativo, con chiave)
app/               codice condiviso (protetto da .htaccess)
app/platform/      piattaforma: avvio, regole, interfaccia, manutenzione
assets/            CSS, JS, font, immagini, illustrazioni
uploads/           immagini caricate (nessun PHP eseguibile)
install/           procedura guidata
storage/           cartella dati di riserva (cache, log), se non è fuori dalla web root
config.example.php modello di configurazione
build-zip.php      crea lo zip per il caricamento
```
