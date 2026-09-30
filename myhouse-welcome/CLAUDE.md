# MyHouse Welcome — regole del progetto

App PHP 8.1+ per guide digitali degli ospiti (case vacanza, B&B, agriturismi). SQLite o MySQL, nessuna dipendenza, caricata via FTP su cPanel. La cartella dell'app è quella con `index.php`, `app/` e `assets/`. Il codice c'è già ed è in produzione: **si modifica, non si riscrive**.

## Regole fisse
1. Stessa struttura, stesso router (`app/src/Router.php`), stesse classi, stesso stile (commenti e nomi in italiano). Niente framework, Composer, Node o build.
2. Il database cresce solo per aggiunte: nuove migrazioni in `app/migrations/` da `007_…` in poi (`.sql` per lo schema, `.php` che restituisce `function (\PDO $pdo)` per i dati, come la `006`). Devono funzionare su SQLite **e** MySQL. I dati esistenti si convertono, non si cancellano.
3. Configurazioni nuove da variabili d'ambiente o `config.local.php`. Non inventare dati, nomi di locali reali o testimonianze.
4. Restano vere: guida ospite senza cookie e `noindex`, niente codici di porte, pagamento solo alla pubblicazione, attivazione solo da webhook Stripe firmato, limiti del piano controllati sul server (`Entitlements`).
5. Ogni nuova etichetta della guida ospite va in tutte e 5 le lingue: `app/lang/{it,en,fr,de,es}.php`.
6. Accessibilità: ogni campo con `label`; controlli personalizzati costruiti su input veri usabili da tastiera; focus visibile; contrasto AA.
7. Stile visivo: usa i token e i componenti già in `assets/app.css` (carta, terracotta, pino, ocra, mare; Gloock per i titoli, Onest per il testo). I mockup in `_brief/revisione/revisione.html` sono solo riferimento visivo.

## Dati aziendali (pubblici, da mettere come valori predefiniti in `app/config.php` → `legal`)
- Ragione sociale: `Blackout` (valore già presente in `MHW_COMPANY`; **da confermare** la forma esatta)
- P.IVA: `02945910541`
- Telefono: `+39 392 006 1600` — link `tel:+393920061600`
- WhatsApp: stesso numero — link `https://wa.me/393920061600`
- Email di contatto: lascia quella attuale in `MHW_CONTACT_EMAIL` e chiedimi conferma
- Città della sede: chiedimi, non dedurla

## Come lavori
- Un solo file di fase per sessione (`_brief/claude-code/FASE-N.md`). Leggi solo i file indicati lì; apri altri file solo se servono davvero.
- Modifiche mirate: non riscrivere interi file se basta cambiare un blocco.
- Prova in locale con `php -S localhost:8080 router.php` (router che serve i file statici e passa il resto a `index.php`). Installa da `/installa` con i dati di esempio; utente demo `lucia@esempio.it` / `dimostrazione1`. Il database locale e i file in `app/storage/` non vanno nello zip.
- A fine fase, **una** verifica completa: registrazione → procedura → anteprima → guida ospite a 390 px e 1366 px.
- Consegna a fine fase: `welcomebook-v2-faseN.zip` (stessa struttura dell'originale, senza `app/storage/*.sqlite`, log, `instance.php`, `config.local.php`), righe nuove in `CHANGELOG.md`, elenco di cosa devo configurare io. Se il progetto è su git: un commit per fase.
- Poi fermati. Se una scelta non è decisa nel file di fase, proponi due opzioni e chiedi.
