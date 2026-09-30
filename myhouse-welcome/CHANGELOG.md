# Changelog — MyHouse Welcome

## v2 · Fase 2 — Il piano una sola volta, procedura in 5 passi (30 settembre 2026)

Una migrazione: `007_passi_procedura.php` (parte da sola al primo accesso dopo l'aggiornamento).

**Piano e registrazione**
- U1 · Chi arriva da «Crea gratis con Plus» (o Portfolio con il numero di strutture) si registra e va **dritto** al nome della struttura: il piano si salva su `accounts.intended_package_version_id` e `intended_quantity` già alla registrazione. Su «Nuova struttura» compare «Piano {nome} scelto · non paghi adesso · Cambia».
- U1 · `/piano` resta per chi si registra da «Crea gratis» generico o preme «Cambia»: card intere cliccabili (il pallino è il radio vero), senza la riga che ripeteva nome e prezzo; nel Portfolio il numero di strutture sta dentro la card e scriverlo sceglie il Portfolio.
- R1 · Registrazione: chip con il piano scelto e «cambia»; «Nome e cognome»; password con «Mostra» e barra di robustezza; una sola casella obbligatoria, «Accetto i Termini e condizioni»; la privacy è una riga sotto il bottone. `Auth::recordConsent` salva ancora versione e data di entrambi i documenti. Formulazione da far verificare al consulente privacy (annotato nel codice).

**Procedura**
- U3 · Da 7 a 5 passi: Struttura e contatti (con «In che lingua scrivi la guida?»), Arrivo e partenza, Sezioni, Aspetto, Anteprima e pubblica.
- U3 · Sezioni: «Aggiungi» attiva la sezione e apre subito il suo editor sotto la card, con lo stesso modulo della pagina della sezione (nuovo `views/host/_sezione_editor.php`, condiviso); salvataggi, luoghi e azioni riportano alla procedura con la sezione aperta.
- U3 · Le lingue in più sono un riquadro facoltativo in fondo ad «Anteprima e pubblica». Le traduzioni non bloccano mai la pubblicazione.
- U3 · Tab Lingue: percentuale per lingua, campo per campo («English 60%»), e nella pagina di traduzione i campi mancanti sono evidenziati («Da tradurre»).
- U3 · Guida ospite: un campo non tradotto mostra il testo della lingua principale **campo per campo** (prima, una sezione tradotta a metà perdeva i campi non tradotti: corretto `Guide::tdata`).
- U3 · I vecchi indirizzi `/procedura/{checkin|contenuti|lingue|anteprima}` fanno un redirect 301 al passo nuovo. Migrazione 007: `checkin → arrivo`, `contenuti → sezioni`, `lingue → aspetto`, `anteprima → pubblica` (un solo `UPDATE … CASE`, valido su SQLite e MySQL).
- U2 · Durante la procedura di una guida mai pubblicata si vede una sola navigazione: i passi e «Esci, continuo dopo». Niente tab della struttura né menu dell'account; la verifica email è una riga compatta sopra i passi. Sul telefono i passi stanno in una riga.

**Prove**
- `giro-completo.php`: 25 controlli nuovi (registrazione, piano una volta, card dei piani, 5 passi, redirect 301, editor nella procedura, lingua principale, percentuali e ripiego delle traduzioni). `aggiornamento.php`: migrazione 007 da una struttura ferma a «contenuti».

## v2 · Fase 1 — Rifiniture visive e dati aziendali (30 settembre 2026)

Nessuna migrazione del database: si aggiorna copiando i file, `app/storage/` e `config.local.php` restano quelli del server.

**Moduli e controlli**
- V1 · Tutti i campi hanno lo stile del sito: i selettori includono anche i campi senza `type`, `time`, `date`, `url` e `datetime-local`, e ogni `<input>` dei modelli ha il suo `type`.
- V2 · Nuova zona di caricamento file (`views/host/_carica.php`): miniatura, nome e peso del file, «Sostituisci» / «Rimuovi», «Trascina qui o scegli un file»; trascinamento in `assets/cms.js`. L'input vero resta, nascosto alla vista ma raggiungibile da tastiera. Usata per copertina, logo, immagine profilo, immagine e PDF delle sezioni, foto dei luoghi.
- V2 · Caselle e pallini disegnati sopra gli input veri, in terracotta. Lingue, «Testo scuro / Testo chiaro» e «Colore dell'etichetta» diventano schede selezionabili (bordo terracotta e spunta).

**Procedura e pannello**
- U4 · Stessa barra azioni in tutti i passi, fissa in basso (sul telefono sotto il pollice): «← Indietro», stato «✓ Salvato» del salvataggio automatico, «Salva e continua →». «Continua dopo» è in alto accanto al nome della struttura. Struttura, Lingue e Aspetto ora salvano anche mentre si modifica.
- U5 · Sezioni e luoghi: righe tutte alte uguali, maniglia per trascinare e riordinare (usa le rotte «su/giù» esistenti), menu `⋯` con Modifica / Sposta su / Sposta giù / Disattiva (sezioni) / Elimina, utilizzabile da tastiera e telefono.
- V8 · Amministrazione pacchetti: funzioni come etichette («4 sezioni · 2 lingue · Logo · Copertina · 1 struttura»); Portfolio 2 e 3 in fondo, nel blocco chiuso «Piani nascosti».

**Marchio e sito pubblico**
- V3 · Un'icona per sezione, uguale in landing, pannello e guida: segnaposto (Come arrivare), autobus (Trasporti), auto (Parcheggio), cestino (Rifiuti), monumento (Cosa visitare), bussola (Cosa fare), documento (Regole), lavatrice (Servizi).
- V5 · Il simbolo arco + punto è il logo dell'header del sito, del pannello e del piè di pagina; nella guida senza logo dell'host prende il posto delle iniziali. Nuovi `assets/favicon.svg`, `assets/apple-touch-icon.png` (180 px) e `assets/og.jpg` (1200×630), generati con GD da `strumenti/marchio.php`; tag `og:` nelle pagine pubbliche (mai nella guida degli ospiti).
- U6 · Header del sito su telefono in una riga: logo, «Crea gratis» e menu (Come funziona, Piani, Il QR, Accedi, tema).
- K7 · Nuovi valori in `config.php → legal`: `company_vat` (`MHW_COMPANY_VAT`), `contact_phone` (`MHW_CONTACT_PHONE`), `contact_whatsapp` (`MHW_CONTACT_WHATSAPP`), `company_city` (`MHW_COMPANY_CITY`). Piè di pagina su due colonne con P.IVA, telefono (`tel:`), WhatsApp (`wa.me`), email, Termini e Privacy; un valore vuoto non compare. Termini e Privacy usano gli stessi dati al posto dei segnaposto.

**Guida degli ospiti**
- V4 · Schermata di benvenuto: due gradienti (scuro in alto, più scuro in basso) al posto del velo. Correzione: una regola della grana azzerava il velo, che quindi non c'era affatto. «Entra» usa il terracotta del marchio; leggibile anche con una foto quasi bianca.
- V7 · Riquadri in proporzione 4:3 e copertina più bassa sul telefono: a 390 px entrano quattro riquadri interi sopra la barra «Chiama / WhatsApp»; con la tastiera il riquadro a fuoco non finisce sotto la barra.

**Dati aziendali confermati** (valori predefiniti in `config.php → legal`)
- Ragione sociale `Blackout Agency`, P.IVA `02945910541`, sede `Via Ariodante Fabretti 17, Perugia`, email `info@myhousewelcome.it`, telefono e WhatsApp `+39 392 006 1600`.

**Prove**
- `app/prove/giro-completo.php`: 13 controlli nuovi per la fase (piè di pagina, favicon e og, header del telefono, icone, `type` dei campi, simbolo nella guida, etichette dei pacchetti).
