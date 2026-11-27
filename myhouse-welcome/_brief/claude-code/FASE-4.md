# Fase 4 di 5 — Portfolio: una struttura prima di pagare, poi copia
Modello consigliato: Opus 5.5 · Migrazioni se servono (numerazione successiva all'ultima esistente).

Leggi `CLAUDE.md`. Le fasi 1–3 sono fatte. **Prima di scrivere codice** proponimi un piano breve (controlli sul server, cambi a Stripe, cosa si copia) e aspetta il mio ok.

File da leggere: `app/src/Entitlements.php`, `app/src/Plans.php`, `app/src/Subscriptions.php`, `app/src/Billing.php`, `app/src/Stripe.php`, `app/src/Properties.php`, `app/src/Media.php`, `app/src/routes_host.php` (pannello, `/pannello/nuova`, `/account/strutture`), `app/src/routes_public.php` (webhook), `app/views/host/properties.php`, `app/views/host/strutture.php`, `app/views/host/new_property.php`, `app/migrations/006_portfolio_quantita.php`.

## Da fare

1. **Una struttura completa prima del pagamento.**
   - Con il Portfolio scelto e non ancora pagato, l'host configura **una sola struttura**.
   - Le altre, fino alla quantità scelta, si creano con il solo nome e compaiono come card bloccate: «Si attiva dopo il pagamento».
   - Il blocco va controllato **sul server** (`Entitlements` e rotte in `routes_host.php`): ogni rotta di modifica di una struttura bloccata risponde con un avviso, non con un errore.

2. **Sblocco dopo il pagamento.**
   - Quando arriva il webhook di pagamento, tutte le strutture fino alla quantità pagata si sbloccano.
   - «Aggiungi una struttura» oltre la quantità pagata aggiorna la quantità dell'abbonamento Stripe con `proration_behavior=create_prorations`, dopo una conferma che mostra il costo.
   - Se Stripe non è configurato, messaggio chiaro, niente errori.

3. **«Crea da una struttura esistente».**
   - Quando crei una nuova struttura: scelta facoltativa della struttura di origine e lista di cosa copiare.
     - **Già spuntate:** rifiuti, dove mangiare, cosa visitare, cosa fare, trasporti, emergenze, informazioni utili, regole, servizi, servizi extra (se esiste), aspetto (palette, logo, tono del testo), contatti.
     - **Mai copiate:** indirizzo, CIN, reti Wi-Fi, passaggi di arrivo, foto di copertina, parcheggio.
   - La copia include traduzioni, luoghi e loro traduzioni. Le immagini e i PDF vanno **duplicati** nello storage (locale o S3) con nuovi nomi, non condivisi, così eliminare una struttura non rompe l'altra.
   - Lo stesso strumento, su una struttura esistente, diventa «Copia sezioni da…»: se la sezione esiste già, chiedi se sostituirla o saltarla.
   - Scrivi l'operazione in una classe (`app/src/Copia.php` o simile) dentro una transazione: se qualcosa fallisce non resta nessuna copia a metà.

4. **Predisposizione, non implementazione.** Lascia un commento nel codice dove andrebbero la libreria dei luoghi a livello di account e le sezioni «collegate», che si aggiornano in tutte le guide. Non implementarle ora.

## Verifica prima di consegnare
- Account demo Portfolio (`marco@esempio.it` / `dimostrazione1`): prima del pagamento solo una struttura è modificabile, anche chiamando a mano gli URL delle altre.
- Pagamento simulato (webhook di prova o abbonamento manuale dall'admin): le strutture si sbloccano.
- Copia completa da Casa Lucia a una nuova struttura: le due guide sono indipendenti; modificare o eliminare l'una non tocca l'altra.

Consegna `welcomebook-v2-fase4.zip` come da `CLAUDE.md` e fermati.
