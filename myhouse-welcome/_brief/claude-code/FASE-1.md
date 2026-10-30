# Fase 1 di 5 — Rifiniture visive e dati aziendali
Modello consigliato: Sonnet 5.5 · Nessuna migrazione del database.

Leggi `CLAUDE.md`. Lavora sul codice esistente. Riferimento visivo: in `_brief/revisione/revisione.html` i punti V1, V2, V3, V4, V5, V7, V8, U4, U5, U6, K7.

File da leggere per questa fase: `assets/app.css`, `assets/cms.js`, `app/src/Icon.php`, `app/src/SectionCatalog.php` (solo le icone), `app/config.php`, `app/views/layout/*.php`, `app/views/host/_struttura_form.php`, `app/views/host/section.php`, `app/views/host/wizard.php`, `app/views/host/_aspetto_form.php`, `app/views/host/_lingue_form.php`, `app/views/host/_sezioni.php`, `app/views/host/new_property.php`, `app/views/guest/splash.php`, `app/views/guest/guide.php`, `app/views/pub/home.php`, `app/views/admin/packages.php`.

## Da fare

1. **V1 · Campi senza stile.** In `app.css` (selettori intorno alle righe 230 e 467) lo stile si applica solo a `input[type=text|email|password|tel|search|number]`. Aggiungi `input:not([type])`, `input[type=time]`, `input[type=date]`, `input[type=url]`, `input[type=datetime-local]`. Metti anche un `type` esplicito su ogni `<input>` dei template. Alla fine non deve restare nessun campo con il bordo nero del browser: struttura, orari, form dei luoghi, admin.

2. **V2 · Controlli nativi.**
   - `input[type=file]` → zona di caricamento con miniatura, nome del file, «Sostituisci» / «Rimuovi», testo «Trascina qui o scegli un file», trascinamento gestito in `assets/cms.js`. L'input vero resta, nascosto visivamente ma raggiungibile da tastiera.
   - Caselle delle lingue, radio «Testo scuro / Testo chiaro», radio «Colore dell'etichetta» → card selezionabili (bordo terracotta e spunta quando attive).

3. **V4 · Splash ospite.** In `guest/splash.php` e `.full__scrim`: al posto del velo uniforme, gradiente scuro in alto (0→55%) e in basso (fino a ~85%). Il pulsante «Entra» usa il terracotta del marchio. Deve restare leggibile anche con una foto molto chiara.

4. **U4 · Barra azioni della procedura.** Stessa barra in tutti i passi, fissa in basso su telefono: `← Indietro` a sinistra, stato «✓ Salvato» (collegato al salvataggio automatico che esiste già) e `Salva e continua →` a destra. «Continua dopo» va in alto, accanto al nome della struttura.

5. **U5 · Azioni delle righe.** Nelle liste di sezioni e luoghi: maniglia per trascinare e cambiare l'ordine, più un menu `⋯` con Modifica / Sposta su / Sposta giù / Disattiva / Elimina (per tastiera e telefono). Tutte le righe della stessa altezza. Usa le rotte di ordinamento che esistono già.

6. **U6 · Header su telefono.** Una riga sola: logo, «Crea gratis», pulsante menu che apre Come funziona, Piani, Il QR, Accedi e il cambio tema.

7. **V3 · Icone.** Un'icona diversa per ogni sezione, uguale in landing, pannello e guida:
   - Come arrivare: segnaposto
   - Trasporti: autobus
   - Parcheggio: auto
   - Rifiuti: cestino
   - Cosa visitare: monumento
   - Cosa fare: bussola
   - Regole: documento
   - Servizi: lavatrice

   Nuove icone in `Icon.php`, aggiorna `SectionCatalog`.

8. **V5 · Marchio.**
   - Il simbolo arco+punto (oggi solo nello splash) diventa il logo nell'header del sito, del pannello e nel footer.
   - Aggiungi le favicon: `assets/favicon.svg` e `assets/apple-touch-icon.png` a 180 px, generata con GD.
   - Nei layout pubblici metti `og:title`, `og:description` e `og:image`: un'immagine 1200×630 in `assets/og.jpg`, composta con GD dal simbolo e dal titolo su fondo carta.
   - Nella guida ospite senza logo dell'host, mostra il simbolo al posto delle iniziali nel cerchio nero.

9. **V7 · Riquadri della guida ospite.** Proporzione circa 4:3, così nella prima schermata a 390 px ne entrano quattro interi. Sotto la barra «Chiama / WhatsApp» lascia uno spazio pari alla sua altezza, così non copre i riquadri.

10. **K7 · Footer e dati aziendali.**
    - In `app/config.php`, nella sezione `legal`, aggiungi:
      - `company_vat` = `MHW_COMPANY_VAT`, predefinito `02945910541`;
      - `contact_phone` = `MHW_CONTACT_PHONE`, predefinito `+39 392 006 1600`;
      - `contact_whatsapp` = `MHW_CONTACT_WHATSAPP`, predefinito `393920061600`;
      - `company_city` = `MHW_COMPANY_CITY`, vuoto.
    - Footer della landing e delle pagine pubbliche su due colonne:
      - sinistra: «MyHouse Welcome · un progetto {company}», «P.IVA 02945910541», città se impostata;
      - destra: email, «Tel. +39 392 006 1600» (link `tel:`), «WhatsApp» (link `https://wa.me/393920061600`), Termini · Privacy.
    - Se un valore è vuoto, la riga non compare.
    - Nelle pagine Termini e Privacy usa gli stessi valori al posto di eventuali segnaposto.

11. **V8 · Amministrazione pacchetti.** Mostra le funzioni come etichette leggibili, per esempio «4 sezioni · 2 lingue · Logo · Copertina · 1 struttura», al posto di `sections=4 · locales=2`. Raggruppa i pacchetti nascosti (Portfolio 2 e 3) in fondo, in un blocco chiuso.

## Verifica prima di consegnare
- Nessun campo o controllo con l'aspetto nativo del browser nel pannello (procedura, sezioni, luogo, aspetto, lingue, admin).
- Splash leggibile con `assets/foto/casa.jpg` e con una foto chiarissima.
- Header su telefono su una riga; footer con P.IVA e numero; il link WhatsApp apre `wa.me/393920061600`.
- Tema chiaro e scuro entrambi corretti.

Consegna come da `CLAUDE.md` (zip `welcomebook-v2-fase1.zip`, CHANGELOG, cosa devo configurare) e fermati.
