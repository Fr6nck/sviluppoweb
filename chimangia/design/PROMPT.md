# Prompt da incollare in Claude Code

Apri Claude Code nella cartella che contiene questo file e la cartella `design/`, poi incolla il testo qui sotto.

---

Costruisci il sito one page di **ChiMangia** (piattaforma di prenotazione pasti per ristoranti, trattorie e mense) **con un pannello di amministrazione** da cui si modificano tutti i contenuti senza toccare il codice.

Il riferimento grafico è `design/sito-onepage.dc.html`: è un mockup con stili inline e una sintassi a componenti (`<x-dc>`, `<helmet>`). Usalo come fonte di **testi iniziali, struttura, colori, misure, icone e illustrazioni SVG**, ma riscrivi il codice in modo pulito. I testi del mockup diventano i contenuti iniziali del database: riprendili alla lettera, senza riscriverli e senza aggiungere sezioni.

Lavora per fasi e fermati a mostrarmi il risultato alla fine di ciascuna:
1. struttura, database, installazione e accesso al pannello;
2. sito pubblico che legge i contenuti dal database;
3. pannello: contenuti, prezzi, FAQ, contatti, SEO;
4. richieste dal modulo contatti, media, storico, impostazioni;
5. controlli finali, README e zip.

## Tecnologia
- PHP 8.1+ senza framework, MySQL/MariaDB con PDO e query preparate, utf8mb4. Nessun Composer obbligatorio: se serve una libreria (per esempio PHPMailer per SMTP) includila nel progetto.
- Hosting condiviso cPanel, deploy via zip + FTP. Percorsi relativi, funzionante anche in una sottocartella.
- Struttura:
  - `index.php` sito pubblico; `contatti.php` riceve il modulo e risponde in JSON;
  - `admin/` pannello;
  - `app/` codice condiviso (connessione, autenticazione, CSRF, validazione, rendering, mail), protetto da `.htaccess`;
  - `assets/` CSS, JS, font, immagini del sito; `uploads/` immagini caricate dal pannello, senza esecuzione di PHP;
  - `install/` procedura guidata;
  - `config.php` creato dall'installazione partendo da `config.example.php`, mai nello zip.
- Font in locale (`assets/fonts`, woff2, `font-display: swap`), nessuna chiamata a Google Fonts o ad altri servizi esterni.

## Installazione
`install/index.php` chiede i dati del database, il primo amministratore (nome, email, password) e l'URL del sito; crea le tabelle, carica i contenuti iniziali presi dal mockup, scrive `config.php` e poi si blocca da sola (file di lock), con l'invito a cancellare la cartella.

## Sito pubblico
- Tutti i testi, le schede, i prezzi, le FAQ, i contatti e i dati SEO arrivano dal database. Ogni sezione si può nascondere dal pannello.
- Il pannello salva in **bozza**; il sito mostra solo la versione **pubblicata**. L'amministratore vede l'anteprima della bozza con un link riservato.
- Dopo ogni pubblicazione genera una copia HTML in cache del sito, così il pubblico non interroga il database a ogni visita.

### Sezioni, in ordine
1. Intestazione fissa con logo, menu ad ancore e pulsante "Prova gratis 7 giorni"; su mobile menu a scomparsa accessibile.
2. Apertura: etichetta dell'offerta, titolo, testo, due pulsanti, scheda d'esempio della cucina.
3. Il problema (fondo blu): tre schede con icona.
4. Come funziona: tre passaggi numerati.
5. Una giornata con ChiMangia: tre blocchi testo + illustrazione alternati (le tre illustrazioni SVG sono nel mockup).
6. Funzioni: nove schede con icona.
7. Per chi è: quattro schede.
8. Prezzi (fondo blu): fascia dell'offerta; passo 1 "I pacchetti" con tre schede, ognuna con prezzo pieno barrato, prezzo di lancio, annuale e la fascia "+ Attivazione, una volta sola" allineata nelle tre schede; passo 2 "L'attivazione"; esempio di calcolo; riquadro prova gratuita di 7 giorni; riga sul modulo Hotel (volutamente poco in evidenza); nota IVA. Selettore Mensile / Annuale sopra le schede.
9. Domande frequenti come accordion accessibile.
10. Contatti (fondo zafferano): modulo con nome, attività, email, telefono, numero indicativo di clienti, messaggio, consenso privacy; sotto, email e telefono cliccabili.
11. Piè di pagina con ragione sociale e partita IVA.

### Design
- Colori come variabili CSS: blu notte `#13294B`, zafferano `#F2A900`, crema `#FBF7EF`, crema scura `#F3ECDD`, bordo `#E6DDCB`, testo secondario `#33415C` e `#4A5568`, bianco `#FFFFFF`. Lo zafferano si usa solo come sfondo o su fondo blu, mai come testo su fondo chiaro.
- Font: Baloo 2 (700, 800) per titoli, logo e prezzi; Inter (400–700) per il testo.
- Logo: simbolo in `design/simbolo.svg` + scritta "ChiMangia" in Baloo 2 800, con la M maiuscola. Genera favicon e icone da quel simbolo.
- Contenitore max 1120 px; mobile-first; target touch ≥ 44 px; contrasto AA.

### Interazioni
- Pulsanti: al passaggio del mouse e al focus i colori si scambiano con transizione di 0,3 s (blu con testo chiaro → bianco con testo blu e bordo blu; bianco con bordo blu → blu con testo bianco), leggero sollevamento con ombra, freccia che scorre di 6 px, pressione al clic.
- Voci di menu con sottolineatura zafferano animata; schede che si sollevano di 4 px.
- Comparsa morbida delle sezioni allo scorrimento con `IntersectionObserver`; scorrimento fluido verso le ancore con margine per l'intestazione fissa.
- Tutto disattivato con `prefers-reduced-motion`.

## Pannello di amministrazione (`/admin`)
In italiano, mobile-friendly, con gli stessi colori e font del sito, menu laterale che su mobile diventa a scomparsa. Ogni pagina di modifica ha i pulsanti **Salva bozza**, **Anteprima** e **Pubblica**, e avvisa se si esce con modifiche non salvate.

### Accesso
- Login con email e password (`password_hash` / `password_verify`), blocco temporaneo dopo 5 tentativi errati, sessione con cookie `HttpOnly`, `Secure` e `SameSite=Lax`, rigenerazione dell'ID al login, scadenza per inattività.
- Recupero password via email con link a scadenza (1 ora, monouso).
- Due ruoli: **Amministratore** (tutto, compresi utenti e impostazioni) e **Redattore** (contenuti, prezzi, FAQ, richieste).
- Pagina profilo per cambiare nome, email e password.

### Cruscotto
Numero di richieste nuove, ultime 5 richieste, data dell'ultima pubblicazione, eventuale bozza non pubblicata, collegamento al sito.

### Contenuti
- Una pagina per ogni sezione del sito, con i campi di quella sezione: etichette, titoli, testi, testi e destinazioni dei pulsanti, interruttore Mostra/Nascondi.
- Le sezioni con schede (problema, passaggi, giornata, funzioni, per chi è) permettono di aggiungere, modificare, eliminare e riordinare le schede con trascinamento o frecce. L'icona si sceglie da una libreria di icone SVG già presenti nel progetto (quelle del mockup più qualche alternativa), non si carica a mano.
- I testi lunghi usano un editor semplice che permette solo grassetto, corsivo, link ed elenchi; l'HTML salvato passa da una whitelist di tag e attributi.
- Indicazione dei caratteri consigliati per titoli e descrizioni, senza bloccare il salvataggio.

### Prezzi
- Tabella modificabile dei pacchetti: nome, numero di clienti, prezzo mensile pieno e di lancio, annuale pieno e di lancio, attivazione piena e di lancio, ordine, visibile sì/no.
- Modulo Hotel: prezzo pieno e di lancio, testo della riga.
- Offerta lancio: attiva sì/no, percentuale mostrata nella fascia, data di fine facoltativa. Quando l'offerta è spenta o scaduta il sito mostra solo i prezzi pieni, senza barrati.
- Pulsante "Calcola prezzi di lancio" che applica la percentuale ai prezzi pieni e arrotonda all'euro, lasciando comunque modificabili i valori.
- Testo del riquadro prova gratuita, dell'esempio di calcolo e della nota IVA.
- Valori iniziali (IVA esclusa):

| Pacchetto | Mensile pieno | Mensile lancio | Annuale pieno | Annuale lancio | Attivazione piena | Attivazione lancio |
| --- | --- | --- | --- | --- | --- | --- |
| Fino a 40 clienti | 41 € | 29 € | 410 € | 290 € | 270 € | 190 € |
| Fino a 80 clienti | 70 € | 49 € | 700 € | 490 € | 410 € | 290 € |
| Oltre 80 (fino a 150) | 99 € | 69 € | 990 € | 690 € | 560 € | 390 € |
| Modulo Hotel | + 29 € | + 20 € | | | | |

  Offerta lancio attiva al 30%, prova gratuita 7 giorni.

### Domande frequenti
Elenco con aggiunta, modifica, eliminazione, riordino e Mostra/Nascondi per ogni domanda.

### Richieste dal modulo contatti
- Ogni invio viene salvato nel database e notificato via email ai destinatari impostati.
- Elenco con ricerca, filtro per stato (Nuova, Contattata, In prova, Cliente, Chiusa) e per data; scheda di dettaglio con tutti i dati, note interne e cronologia dei cambi di stato.
- Esportazione CSV (separatore `;`, UTF-8 con BOM per Excel).
- Eliminazione di una richiesta e cancellazione automatica di quelle chiuse più vecchie di un numero di mesi impostabile (privacy).
- Anti-spam sul modulo pubblico: honeypot, token CSRF, tempo minimo di compilazione, limite di invii per IP.

### Dati aziendali e contatti
Ragione sociale, indirizzo, partita IVA, email e telefono mostrati nel sito, link social facoltativi. Valori iniziali da far verificare:
- City Up Srl, Via Ariodante Fabretti 15-17, 06123 Perugia (PG)
- P.IVA 03793880547
- Telefono +39 392 006 1600
- Email `[EMAIL DEL SITO]` (lascialo come segnaposto evidente)

### SEO
Title, description, immagine Open Graph (caricata), testo per i dati strutturati `SoftwareApplication`. Le offerte nei dati strutturati si generano dai prezzi. `sitemap.xml` e `robots.txt` generati in automatico; il pannello è sempre `noindex`.

### Media
- Caricamento di immagini JPG, PNG e WebP fino a 5 MB: controllo del tipo reale del file, nome rinominato in modo casuale, ridimensionamento a 1600 px e versione WebP.
- Niente SVG caricati dall'utente (rischio di script): gli SVG restano solo quelli del progetto.
- Libreria con anteprima, testo alternativo obbligatorio, eliminazione con avviso se l'immagine è in uso.

### Pagine legali
Privacy e cookie modificabili dal pannello con lo stesso editor semplice, pubblicate come `privacy` e `cookie`. Testo iniziale segnaposto, ben evidenziato, da far completare.

### Storico e ripristino
Ogni pubblicazione salva una versione completa dei contenuti con data e autore. Elenco delle versioni con confronto essenziale e pulsante **Ripristina** (che crea una nuova bozza, non sovrascrive direttamente). Registro delle azioni: accessi, pubblicazioni, modifiche a prezzi e utenti.

### Impostazioni (solo Amministratore)
- Utenti: creazione, modifica, ruolo, disattivazione.
- Email: invio con `mail()` oppure SMTP (host, porta, cifratura, utente, password), destinatari delle notifiche, pulsante "Invia email di prova". La password SMTP non viene mai mostrata di nuovo dopo il salvataggio.
- Mesi di conservazione delle richieste chiuse.
- Modalità manutenzione: il pubblico vede una pagina d'attesa, gli amministratori collegati vedono il sito.
- Backup: scarica un file JSON con tutti i contenuti e le impostazioni (senza password) e permette di ricaricarlo.

## Sicurezza
- Query preparate ovunque, escape di tutto l'output, token CSRF su ogni form e azione del pannello, controllo del ruolo lato server su ogni pagina e azione.
- Header: `Content-Security-Policy` senza script inline nel pannello, `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`.
- `.htaccess` che blocca l'accesso diretto a `app/`, `install/` dopo l'installazione, `config.php`, file di log e backup; nessuna esecuzione di PHP in `uploads/`.
- Errori registrati in un file di log fuori dalla web root quando possibile, mai mostrati al pubblico.
- Nessuna credenziale nel codice.

## Qualità
- HTML semantico, un solo `h1`, `lang="it"`, navigazione da tastiera, focus visibile, etichette su tutti i campi del pannello, messaggi di errore vicino al campo.
- Nessun cookie di tracciamento e nessuna risorsa di terze parti nel sito pubblico, quindi nessun banner; l'unico cookie è quello tecnico della sessione del pannello.
- Non inventare testimonianze, numeri di clienti o loghi di aziende.

## Consegna
- File completi, senza TODO.
- `README.md` con: installazione su cPanel (creazione database, caricamento, procedura guidata), uso del pannello in breve, configurazione email, aggiornamento senza perdere i contenuti.
- Script `build-zip.php` che crea lo zip pronto per l'FTP escludendo `config.php`, `uploads/` con i file caricati, cache, log e file di sviluppo.
- Alla fine elenca cosa hai fatto, come provarlo in locale (`php -S localhost:8000` con un database MySQL locale) e cosa resta da compilare.
