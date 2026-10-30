# MyHouse Welcome — regole del progetto

App PHP 8.1+ per guide digitali degli ospiti (case vacanza, B&B, agriturismi). SQLite o MySQL, nessuna dipendenza, caricata via FTP su cPanel. La cartella dell'app è quella con `index.php`, `app/` e `assets/`. Il codice c'è già ed è in produzione: **si modifica, non si riscrive**.

## Regole fisse
1. Stessa struttura, stesso router (`app/src/Router.php`), stesse classi, stesso stile (commenti e nomi in italiano). Niente framework, Composer, Node o build.
2. Il database cresce solo per aggiunte: nuove migrazioni in `app/migrations/` da `007_…` in poi (`.sql` per lo schema, `.php` che restituisce `function (\PDO $pdo)` per i dati, come la `006`). Devono funzionare su SQLite **e** MySQL. I dati esistenti si convertono, non si cancellano.
3. Configurazioni nuove da variabili d'ambiente o `config.local.php`. Non inventare dati, nomi di locali reali o testimonianze.
4. Restano vere: guida ospite senza cookie e `noindex`, niente codici di porte, pagamento solo alla pubblicazione, attivazione solo da eventi verificati con Stripe (webhook firmato, o evento letto da Stripe con la chiave segreta quando il webhook non arriva: `RecuperoPagamenti`), mai dal ritorno del browser, limiti del piano controllati sul server (`Entitlements`).
5. Ogni nuova etichetta della guida ospite va in tutte e 5 le lingue: `app/lang/{it,en,fr,de,es}.php`.
6. Accessibilità: ogni campo con `label`; controlli personalizzati costruiti su input veri usabili da tastiera; focus visibile; contrasto AA.
7. Stile visivo: usa i token e i componenti già in `assets/app.css` (carta, terracotta, pino, ocra, mare; Gloock per i titoli, Onest per il testo). I mockup in `_brief/revisione/revisione.html` sono solo riferimento visivo.

## Dati aziendali (pubblici, da mettere come valori predefiniti in `app/config.php` → `legal`)
- Titolare e fornitore del servizio (8J): `City Up S.r.l.` (`MHW_COMPANY`) — «City Up S.r.l. — P.IVA e C.F. 03793880547 — REA PG-352001» (`MHW_COMPANY_LEGAL`)
- P.IVA: `03793880547` · REA `PG-352001` (sempre visibile nel piè di pagina) · sito `https://www.cityup.eu`
- PEC: dalla 9K non si mostra più sul sito né nelle email (resta solo come promemoria interno in Impostazioni); dove serve un contatto, l'email assistenza.
- Blackout Agency (blackout.in) realizza i siti MyHouse («Anche il tuo sito»), destinatario delle risposte di quel percorso; dalla 9K compare con City Up anche in «Chi c'è dietro» della home (con il link «Vuoi vedere altri lavori?»).
- Telefono: `+39 392 006 1600` — link `tel:+393920061600`
- WhatsApp: stesso numero — link `https://wa.me/393920061600`
- Email di contatto: `assistenza@welcomemyhouse.it` (`MHW_CONTACT_EMAIL`, confermata: è quella di tutti i `mailto:`)
- Sede: `Via Ariodante Fabretti 15, 06123 Perugia (PG)` (`MHW_COMPANY_CITY`)

## Come lavori
- Un solo file di fase per sessione (`_brief/claude-code/FASE-N.md`). Leggi solo i file indicati lì; apri altri file solo se servono davvero.
- Modifiche mirate: non riscrivere interi file se basta cambiare un blocco.
- Prova in locale con `php -S localhost:8080 router.php` (router che serve i file statici e passa il resto a `index.php`). Installa da `/installa` con i dati di esempio; utente demo `lucia@esempio.it` / `dimostrazione1`. Il database locale e i file in `app/storage/` non vanno nello zip.
- A fine fase, **una** verifica completa: registrazione → procedura → anteprima → guida ospite a 390 px e 1366 px.
- Consegna a fine fase: `welcomebook-v2-faseN.zip` (stessa struttura dell'originale, senza `app/storage/*.sqlite`, log, `instance.php`, `config.local.php`), righe nuove in `CHANGELOG.md`, elenco di cosa devo configurare io. Se il progetto è su git: un commit per fase.
- Poi fermati. Se una scelta non è decisa nel file di fase, proponi due opzioni e chiedi.
