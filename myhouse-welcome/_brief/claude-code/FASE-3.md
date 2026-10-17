# Fase 3 di 5 — Dati dell'host: blocchi duplicabili e dati italiani
Modello consigliato: Opus 5.5, oppure Opus per il piano e la revisione delle migrazioni e Sonnet per scrivere il codice. Migrazioni dalla `008` in poi.

Leggi `CLAUDE.md`. Le fasi 1 e 2 sono fatte. È la fase più delicata, perché converte i dati delle guide esistenti.

**Prima di scrivere codice**, proponimi un piano breve con le migrazioni, i nuovi tipi di campo e i file toccati, e aspetta il mio ok. Poi lavora in due blocchi, **A** e **B**: alla fine del blocco A consegna uno zip intermedio e fermati.

File da leggere: `app/src/SectionCatalog.php`, `app/src/Guide.php`, `app/src/Properties.php`, `app/src/Qr.php`, `app/src/Media.php`, `app/src/Entitlements.php`, `app/src/Billing.php`, `app/src/Stripe.php`, `app/src/routes_host.php` (sezioni, luoghi, lingue, account), `app/views/host/_campi.php`, `app/views/host/section.php`, `app/views/host/translate.php`, `app/views/host/_struttura_form.php`, `app/views/host/account.php`, `app/views/guest/*.php`, `app/migrations/001_schema.sql`, `002_mvp.sql`, `006_portfolio_quantita.php`.

## Principio tecnico
- Nel catalogo aggiungi due tipi di campo.
  - `repeater`: righe con sottocampi; si aggiungono, eliminano e riordinano.
  - `toggles`: interruttori sì/no con etichette già tradotte nei file `lang`.
- I sottocampi seguono la regola che c'è già:
  - `plain/url/secret` stanno in `sections.data`;
  - i testi stanno in `section_translations.data`;
  - le due parti si uniscono per indice di riga, e un nuovo `id` di riga stabile rende sicuro il riordino.
- Aggiorna tutto ciò che legge questi dati:
  - `fromInput()`;
  - `isEmpty()`;
  - `_campi.php` (editor);
  - `translate.php` (traduzioni);
  - `Guide::build/normalize`;
  - le viste ospite.

## Blocco A — struttura, contatti, arrivo e partenza, Wi-Fi

1. **R2 · Struttura e contatti.**
   - Nuove colonne su `properties`, tutte facoltative:
     - `property_type` (`casa_vacanza|bnb|affittacamere|agriturismo|altro`);
     - `address`;
     - `postal_code`;
     - `cin`;
     - `beds`;
     - `lat` e `lng`, per ora vuote.
   - Il CIN, se presente, compare in piccolo nel piè di pagina della guida ospite: «CIN IT…».
   - **Contatti duplicabili**: nuova tabella `property_contacts` (id, property_id, name, role, phone, whatsapp 0/1, position).
     - Ruoli: Host, Co-host, Pulizie e chiavi, Manutenzione, Altro.
     - La migrazione copia `host_name/host_phone/host_whatsapp` nella prima riga; le vecchie colonne restano.
     - Nella guida ospite la barra diventa «Contatta {primo nome}» e apre un foglio con tutti i contatti (Chiama / WhatsApp).
   - L'indirizzo inserito qui precompila «Come arrivare». Se l'host non mette un link Maps, se ne genera uno di ricerca dall'indirizzo.

2. **R3 · Arrivo e partenza.**
   - **Arrivo:**
     - modalità (`self` | `accoglienza` | `cassetta`);
     - passaggi (esistono già);
     - nota per l'arrivo tardivo;
     - **imposta di soggiorno**: importo per notte, notti massime, testo per esenzioni e modalità di pagamento;
     - documenti da mostrare (testo).
   - **Partenza:** i campi `checkout_keys/waste/lights/climate/windows` diventano una lista ordinabile `checkout_steps`, con suggerimenti a un tocco: Chiavi, Rifiuti, Luci, Clima, Finestre, Lavastoviglie, Asciugamani.
     - Migrazione: per ogni lingua, i campi non vuoti diventano voci nello stesso ordine.
     - I vecchi campi restano nel JSON ma non si leggono più.
     - Aggiorna `guest/farewell.php`.

3. **R4 · Wi-Fi.**
   - Più reti, con un `repeater`: nome della zona, rete, password.
   - Per ogni rete la guida mostra «Copia password» e un **QR Wi-Fi** generato con `Qr.php`, stringa `WIFI:T:WPA;S:<ssid>;P:<password>;;` con i caratteri `\ ; , : "` preceduti da backslash.
   - La migrazione porta la rete attuale nella prima riga.

→ Consegna `welcomebook-v2-fase3a.zip` e fermati.

## Blocco B — sezioni strutturate, luoghi, fatturazione

4. **R5 · Sezioni strutturate** (in ogni migrazione, le vecchie liste «una per riga» diventano righe del repeater con il testo nel campo principale):
   - **Emergenze:** righe nome · telefono · nota. Preset: 112, guardia medica, farmacia di turno, veterinario. Nella guida, un pulsante «Chiama» per riga.
   - **Rifiuti:** righe tipo (umido, carta, plastica, vetro, indifferenziato, altro) · giorni della settimana (scelta multipla) · colore del bidone · dove si trova. Nella guida, in evidenza «Oggi si butta: …» calcolato sul giorno corrente, fuso orario Europe/Rome.
   - **Servizi:** griglia di dotazioni con icone da spuntare (lavatrice, asciugatrice, lavastoviglie, asciugacapelli, ferro, culla, seggiolone, aria condizionata, riscaldamento, TV, macchina caffè, barbecue) + **manuali** duplicabili (titolo · foto · passaggi · PDF), con i limiti del piano su foto e PDF.
   - **Regole:** `toggles` (fumo, animali, feste, visitatori esterni) + orario del silenzio (dalle/alle) + regole aggiuntive in lista.
   - **Parcheggio:** più opzioni (tipo, indirizzo, link Maps, costo, istruzioni, foto) + campo **ZTL** (orari e varchi, testo).
   - **Come arrivare:** schede per mezzo (auto, treno, aereo, autobus), ognuna con i suoi passaggi.

5. **R6 · Scheda luogo.**
   - Primo campo «Incolla il link di Google Maps». Lato server: se il link è breve (`maps.app.goo.gl`) segui il redirect con timeout di 5 secondi, poi ricava il nome da `/place/<nome>/` e le coordinate da `@lat,lng`. Se non riesce, nessun errore: l'host compila a mano.
   - Sempre visibili solo: link, nome, categoria, «Perché lo consigli», etichetta.
   - Il resto (telefono, sito, prenotazione, foto, minuti a piedi e in auto) va in «Altri dettagli», chiuso.
   - Se struttura e luogo hanno coordinate, precompila i minuti a piedi stimati (distanza in linea d'aria × 1,3, a 4,5 km/h) con la dicitura «stima, modificabile».
   - Niente API a pagamento.

6. **R7 · Fatturazione italiana.** In «Account & Fatturazione», obbligatorio prima del primo pagamento:
   - tipo (azienda/libero professionista | privato);
   - ragione sociale o nome;
   - P.IVA (11 cifre con cifra di controllo);
   - codice fiscale (16 caratteri, oppure 11 cifre per le aziende);
   - **codice destinatario SDI** (7 caratteri) **oppure PEC**;
   - indirizzo, CAP, città, provincia.

   Salva tutto in nuove colonne su `accounts` e passalo al cliente Stripe come metadati (`vat`, `cf`, `sdi`, `pec`). Non generare fatture.

## Verifica prima di consegnare
- Installa la **v1 originale** con i dati demo, poi copia sopra il codice nuovo: le migrazioni devono convertire Casa Lucia e le altre demo senza perdere testi, traduzioni, luoghi, foto.
- Le tre guide demo appaiono corrette nella guida ospite in italiano e in inglese.
- Nuova struttura da zero: tutti i repeater si aggiungono, riordinano ed eliminano, anche da tastiera.
- Verifica che le migrazioni siano scritte in SQL valido anche per MySQL (tipi e `ALTER TABLE` compatibili).

Consegna `welcomebook-v2-fase3.zip` come da `CLAUDE.md` e fermati.
