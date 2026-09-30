# Changelog — MyHouse Welcome

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
