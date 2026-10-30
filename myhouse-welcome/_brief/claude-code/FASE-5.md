# Fase 5 di 5 — Vendita, crescita e demo in Umbria
Modello consigliato: Sonnet 5.5 · Migrazioni per le nuove funzioni di pacchetto e per il registro delle email.

Leggi `CLAUDE.md`. Le fasi 1–4 sono fatte. Riferimento visivo: K1, K2, K3, K4, V6 in `_brief/revisione/revisione.html`.

File da leggere: `app/views/pub/home.php`, `app/views/admin/_testi_pacchetto.php`, `app/src/Plans.php`, `app/src/Entitlements.php`, `app/src/SectionCatalog.php`, `app/views/guest/farewell.php`, `app/views/guest/guide.php`, `app/views/host/settings.php`, `app/src/Mailer.php`, `app/src/Stats.php`, `app/views/admin/dashboard.php`, `app/src/Demo.php`, `assets/prezzi.js`.

## Da fare

1. **K1 · Listino.**
   - Sotto ogni prezzo annuale, l'equivalente mensile: «circa 9,75 € al mese», calcolato `prezzo / 12` in formato italiano; per il Portfolio si aggiorna con la quantità in `prezzi.js`.
   - Plus e Portfolio partono da «Tutto di Essential, e in più» e **non ripetono** le voci già incluse.
   - In Essential togli le voci presenti in tutti i piani («Pannello di controllo», «Personalizzazione colori»).
   - Sotto le card, una tabella di confronto completa generata dalle funzioni dei pacchetti, apribile con «Confronta tutti i piani».
   - I testi restano modificabili dall'admin come oggi.

2. **K2 · La guida fa guadagnare l'host.**
   - Nelle Impostazioni della struttura, due blocchi facoltativi:
     - **recensioni**: link Google, Booking, Airbnb, altro;
     - **prenotazione diretta**: link al sito e codice sconto.
   - Nel commiato (`farewell.php`) compaiono solo se compilati: «Ti è piaciuto il soggiorno?» con i pulsanti delle piattaforme, e «La prossima volta prenota da noi» con il codice sconto.
   - Nuova sezione di catalogo **Servizi extra**: repeater con titolo · descrizione · prezzo · foto. Ogni riga ha «Richiedi su WhatsApp», che apre `wa.me` del primo contatto con un messaggio precompilato nella lingua dell'ospite.
   - In landing, un blocco che lo spiega: «Una prenotazione diretta in più all'anno paga l'abbonamento.»

3. **K3 · FAQ e demo.**
   - Sezione FAQ prima del listino, con accordion accessibile (`<details>` o pulsanti con `aria-expanded`). Domande, con le risposte che si trovano già in `app/LEGGIMI.md`:
     - Serve un'app?
     - Cosa succede se non rinnovo?
     - Posso cambiare i testi dopo aver stampato il QR?
     - Ricevo fattura?
     - Posso disdire?
     - Gli ospiti vengono tracciati?
   - Accanto a «Guarda la demo», un selettore IT / EN / DE che apre la demo in quella lingua.
   - Nell'admin, una sezione «Testimonianze» (nome, struttura, testo, foto, visibile sì/no): in landing il blocco compare **solo** se c'è almeno una testimonianza visibile. Non inserirne di esempio.

4. **K4 · Firma nella guida.**
   - Nel piè di pagina della guida ospite: «Guida creata con MyHouse Welcome · Crea la tua», con link alla landing e `?ref=guida`.
   - Nuova funzione di pacchetto `hide_branding`, attiva in Plus e Portfolio: con quella, la firma si può nascondere dalle Impostazioni.
   - La funzione si aggiunge con una migrazione, che crea una **nuova versione** dei pacchetti come fa l'admin, senza cambiare le versioni già vendute.

5. **K5 · Email che riportano l'host a finire.**
   - Sequenza:
     - dopo 1 giorno, se l'arrivo è vuoto;
     - dopo 3 giorni, se non ci sono sezioni;
     - dopo 7 giorni, se la guida non è pubblicata;
     - 30 giorni prima del rinnovo, con le statistiche dell'anno.
   - Testi brevi, in italiano, con un solo pulsante che riporta al passo giusto.
   - Tabella `email_log` per non inviare due volte la stessa email; link per non ricevere altre email di questo tipo.
   - Avvio senza cron obbligatorio: controllo leggero a ogni richiesta del pannello o della landing, al massimo una volta ogni 15 minuti, con un file di blocco in `storage/`.
   - In più, un URL `/cron/{token}` con token da `MHW_CRON_TOKEN`, da collegare a un cron di cPanel.

6. **K6 · Funnel senza cookie.**
   - Eventi anonimi in `analytics_events`: `landing_view`, `signup`, `property_created`, `published`.
   - Nel Quadro dell'admin, i numeri degli ultimi 30 giorni e le percentuali di passaggio.

7. **V6 · Landing e demo in Umbria.**
   - Sotto l'hero, al posto della foto grande ripetuta, una fascia con tre scene: `assets/foto/scena-qr.jpg`, `scena-ospite.jpg`, `scena-host.jpg`. Se un file manca, la card mostra un riquadro colorato con icona, senza errori.
   - In «Inizia in pochi minuti», tre schermate reali del pannello al posto dei numeri 01·02·03: fotografale dall'app in locale e salvale in `assets/foto/pannello-1.webp`…`-3.webp`.
   - Casa Lucia si sposta da Montepulciano a **Spello (Umbria)**. Aggiorna città, regione e testi in `Demo.php` in modo coerente, per esempio strangozzi al tartufo al posto dei pici. Nomi dei locali chiaramente di fantasia.
   - **Foto:** i nomi di file restano quelli attuali, così le nuove si caricano sopra. Crea `app/tools/foto.php`, avviabile da riga di comando o da un pulsante in Diagnostica, che con GD rigenera `borgo-1200.webp`, `borgo-2000.webp` e `borgo-telefono-600.webp` dai rispettivi `.jpg`.

## Verifica prima di consegnare
- Landing a 390 px e 1366 px: listino, FAQ, fascia scene, footer.
- Commiato di Casa Lucia con recensioni e prenotazione diretta compilate, e senza.
- Una email di prova di ogni tipo finisce in `storage/logs/mail.log` (trasporto `log`).
- Una installazione **v1 con dati**, aggiornata con il codice finale, funziona senza interventi manuali.

Consegna finale:
- `welcomebook-v2.zip` completo;
- `CHANGELOG.md` con tutte le fasi;
- `DA-CONFIGURARE.md` con variabili, testi, foto con misure, e cosa verificare con commercialista e consulente privacy;
- al massimo dieci test manuali da fare sul server.
