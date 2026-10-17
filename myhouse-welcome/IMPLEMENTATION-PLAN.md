# MyHouse Welcome — dall'applicazione funzionante all'MVP vendibile

Base di lavoro: il codice del ramo `claude/tender-mendel-6uq9eq` al commit `8f27f4f`
(lo stesso contenuto dello ZIP `dist/myhouse-welcome-welcomebook.zip`).
Nessuno ZIP diverso è stato allegato alla richiesta: il codice del repository è la base.

## 1. Audit del codice reale

**Stack.** PHP 8.1+ senza dipendenze, SQLite (MySQL opzionale), front controller unico
(`public/index.php`), router a espressioni regolari, viste PHP, un foglio di stile.
Installabile via FTP in sottocartella, con o senza riscrittura degli indirizzi.

**Da conservare così com'è** (funziona ed è già verificato):
versioni di pacchetto congelate; risoluzione dei diritti override → pacchetto → predefinito;
istantanee di pubblicazione immutabili; QR permanente con token indiretto e generatore
proprio; impersonazione senza password con registro; CSRF su ogni POST; `password_hash`;
query preparate ovunque; escaping con `Support::e`; rilevamento sottocartella e fallback
senza riscrittura; sessioni nella cartella dell'app; diagnostica che si autoverifica;
identità visiva Fauna (Gloock/Onest, tavolozza calda, foto grandi, riquadri pieni).

**Difetti e scostamenti rispetto al brief** (verificati sul codice, non supposti):

| # | Dove | Problema |
|---|---|---|
| 1 | `index.php:92` | L'esenzione CSRF del webhook confronta il percorso grezzo con `/webhook/stripe`: in `/welcomebook/` Stripe riceve **419** e nessun pagamento si attiva. |
| 2 | `Stripe.php` | Checkout `mode=payment`: pagamento una tantum, non abbonamento. |
| 3 | `Stripe::handleEvent` | L'evento viene registrato come elaborato **prima** di applicarlo, fuori transazione: un errore a metà lo perde per sempre. Gestito solo `checkout.session.completed`. |
| 4 | `index.php:204` | Senza chiavi Stripe l'ordine si conferma da solo ("modalità prova"): **pubblicazione gratuita** possibile. |
| 5 | pubblicazione | `/pannello/{id}/pubblica` pubblica sempre, anche senza abbonamento. |
| 6 | scadenza | Nessun controllo: una guida resta online per sempre. |
| 7 | codice cassetta | `door_code` in CMS, guida, congedo, demo ("4729") e dentro le istantanee JSON già pubblicate. |
| 8 | editor | Mini-sintassi (riga vuota = passo, `Nota:` = avviso) al posto di campi strutturati. |
| 9 | luoghi | La `nota` dell'host viene salvata ma **mai mostrata**; badge "Aperto fino alle 23" presentati come dati vivi; nessun indirizzo/Maps/telefono/sito/prenotazione; non tradotti. |
| 10 | i18n ospite | Etichette d'interfaccia in italiano in ogni lingua ("Benvenuti", "Rete", "Chiama"…). Spagnolo assente. |
| 11 | traduzione | Chiamate DeepL/Libre e messaggi tecnici "aggiungete provider e chiave in config.php" mostrati al cliente. |
| 12 | landing | Metrica reale ma usata come riprova sociale ("103 aperture questo mese"); "Guardane una vera" su una demo; "Rispondete a sei domande"; tono "voi". |
| 13 | demo | Statistiche e scansioni QR generate artificialmente; incasso del quadro admin gonfiato dagli ordini finti. |
| 14 | registrazione | Niente termini/privacy, verifica email, recupero password, limitazione tentativi. |
| 15 | piani | Utente già autenticato che sceglie un piano viene rimandato a `/registrati`. |
| 16 | media | Solo disco locale; cover vincolata al piano Plus; niente logo, profilo, PDF. |
| 17 | CSS | `:focus-visible` impone `border-radius:999px` anche a input e textarea. |
| 18 | affordance | Maniglia `⋮⋮` di trascinamento senza trascinamento. |
| 19 | errori | Il messaggio dell'eccezione arriva all'utente finale. |
| 20 | congedo | Istruzioni inventate dal sistema ("Finestre accostate, luci e gas spenti"). |

## 2. Piano

### Migrazioni (incrementali, mai distruttive)
- `Migrator` + tabella `schema_migrations`; eseguito all'avvio, in transazione, idempotente.
  Un database esistente registra `001` come già applicata.
- `002_mvp.sql`: colonne aggiunte a `users` (verifica, termini, privacy), `accounts`
  (piano scelto, cliente Stripe), `packages` (copy commerciale, famiglia, visibilità),
  `package_versions` (price ID Stripe), `subscriptions` (price ID, periodo, rinnovo,
  stato pagamento), `properties` (palette, tono, logo, profilo, demo, passo del wizard),
  `sections` (core, attiva, dati strutturati, PDF), `section_translations` (dati tradotti),
  `places` (indirizzo, Maps, telefono, sito, prenotazione, minuti), `media` (tipo,
  storage, chiave), `analytics_events` (sorgente). Nuove tabelle: `place_translations`,
  `email_tokens`, `rate_limits`.
- `003_listino_e_sezioni.php`: feature nuove e future (a 0); Essential v2 87 €, Plus v2
  117 €, Portfolio 2 177 €, Portfolio 3 237 €; Pro nascosto (le sue versioni restano per
  chi l'ha comprato); **backfill** delle versioni già vendute perché nessuno perda nulla;
  conversione delle sezioni esistenti nei tipi del catalogo; `door_code` svuotato e tolto
  dalle istantanee già pubblicate.

### Servizi nuovi
`Migrator`, `Mailer` (smtp/mail/log), `RateLimit`, `Tokens`, `SectionCatalog`,
`Palette` (con verifica del contrasto WCAG), `I18n` + `lang/{it,en,fr,de,es}.php`,
`Storage` (`LocalStorage`, `S3Storage` con firma SigV4), `Subscriptions`, `QrExport`
(SVG e PDF scritti a mano), `Log`.

### Rotte nuove o cambiate
`/verifica/{token}`, `/password/dimenticata`, `/password/nuova/{token}`, `/termini`,
`/privacy`, `/piano`, `/pannello/{id}/procedura/{passo}`, `/pannello/{id}/anteprima`,
`/pannello/{id}/sezioni/{sid}/azione` (sposta su/giù, attiva, disattiva, elimina), `/pannello/{id}/aspetto`,
`/pannello/{id}/statistiche`, `/pannello/{id}/qr.{png,svg,pdf}`, `/account`,
`/account/rinnovo`, `/account/portale`, `/pagamento/stato`, `/admin/abbonamenti`,
`/admin/registro`, `/admin/cliente/{aid}/abbonamento`. Il webhook resta
`/webhook/stripe` e funziona sotto qualunque percorso di base.

### Rischi e compatibilità
- **Semantica di `sections`**: diventa "sezioni aggiuntive" (il check-in non conta).
  Per le versioni già vendute il numero non scende: è un guadagno, non una perdita.
- **Istantanee già pubblicate** contengono URL di immagini; le nuove contengono l'id del
  media (serve per S3 con URL firmati). Il rendering legge entrambi i formati.
- **Senza chiavi Stripe non si pubblica**: è voluto. Per demo e omaggi c'è l'attivazione
  manuale dall'admin, registrata.
- **Stripe reale non è raggiungibile da qui** (niente chiavi): i test usano un server
  Stripe finto che registra le richieste e webhook firmati localmente. Lo dirò.
- **S3 reale non è raggiungibile** (niente credenziali): la firma è verificata con i
  vettori ufficiali AWS e confrontata con botocore; il giro completo usa un S3 finto.

## Stato alla consegna

Implementato tutto il piano qui sopra. Verificato con `app/prove/esegui.sh`
(227 prove, con S3 finto e con archiviazione locale), `app/prove/aggiornamento.sh`
(aggiornamento dalla versione 8f27f4f con dati di esempio) e i vettori di firma S3.

Emersi durante le prove e corretti:
- un salvataggio parziale di una sezione (per esempio l'invio di una foto rifiutata
  dal piano) azzerava i campi non inviati, password del Wi-Fi compresa: ora i
  campi assenti dal modulo restano come sono;
- nell'installazione in sottocartella `app/storage/logs/` era leggibile dal web
  (e senza SMTP lì finiscono i link di verifica e recupero password): ora
  `app/.htaccess` e `app/web.config` chiudono la cartella, `.htaccess` principale
  la chiude una seconda volta e la Diagnostica lo controlla davvero.

Rimandato di proposito (vedi il riepilogo di consegna): rivenditori e gestori di
immobili, configurazione assistita, video, traduzione automatica, AI, serrature
e codici, trascinamento, piano Pro pubblico, sezioni personalizzate, statistiche
avanzate.
