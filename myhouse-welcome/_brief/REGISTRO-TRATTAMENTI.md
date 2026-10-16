# Registro delle attività di trattamento — MyHouse Welcome

Art. 30 del Regolamento (UE) 2016/679 (GDPR). **Documento interno: non va sul sito.** Va tenuto aggiornato e mostrato al Garante se lo chiede.
Bozza scritta sul servizio come funziona nel codice al 15 ottobre 2026 (fase 8J): da completare e far rivedere a un consulente privacy.

## Titolare

- **City Up S.r.l.** — P.IVA e C.F. 03793880547 — REA PG-352001
- Sede: Via Ariodante Fabretti 15, 06123 Perugia (PG) · PEC cityup@pec.it · sito https://www.cityup.eu
- Contatto privacy: assistenza@welcomemyhouse.it (lo stesso indirizzo dell'assistenza, Impostazioni → Sito)
- Responsabile della protezione dei dati (DPO): non nominato (non obbligatorio, art. 37 GDPR)
- Rappresentante legale: _da completare_

## Trattamenti come titolare

| # | Trattamento | Finalità | Interessati e categorie di dati | Destinatari | Trasferimenti extra-UE | Conservazione | Misure di sicurezza |
|---|---|---|---|---|---|---|---|
| 1 | Account e contratto | Creare e gestire l'account, fornire il servizio; dimostrare le accettazioni | Host: nome, email, password (hash), date di registrazione e conferma, versioni e date dei documenti accettati (`users`, `legal_acceptances`) | Hostinger (hosting), fornitore della posta | No | Account attivo + 24 mesi dall'ultimo accesso o dalla scadenza; avviso email 30 giorni prima (**oggi a mano**: la cancellazione automatica arriva con la 6L) | HTTPS, password con hash, limiti ai tentativi, sessione con cookie sicuro |
| 2 | Pagamenti e fatturazione | Incassi, fatture, obblighi fiscali | Host: dati di fatturazione (nome/ragione sociale, indirizzo, P.IVA/CF, SDI/PEC), piano, importi, stato pagamenti, scelta impresa/consumatore (`accounts`, `orders`, `subscriptions`, `webhook_events`) | Stripe, Adamo, Agenzia delle Entrate (SDI) | Stripe Inc. (USA): Clausole Contrattuali Standard + Data Privacy Framework | 10 anni (art. 2220 c.c.) | Carta solo su Stripe; webhook firmati; chiavi in `config.local.php` |
| 3 | Contenuti della guida (dati dell'host) | Pubblicare la guida | Host e collaboratori: nomi, ruoli, telefoni, WhatsApp, email «per gli ospiti», foto (`properties`, `property_contacts`, `media`, `sections`) | AWS S3 Milano (file), Amazon Translate Irlanda (solo testi da tradurre), chi ha il link/QR | No | Come l'account | Guide `noindex`, senza cookie, URL S3 firmati a tempo |
| 4 | Email di servizio e promemoria | Conferme, recupero password, rinnovo, avvisi legali e di sicurezza, promemoria | Host: email, nome, tipo e data degli invii (`email_log`, `email_optout`) | Fornitore della posta | No | Come l'account (registro invii) | Link «Non mandarmene più» per i promemoria |
| 5 | Invita un amico | Gestire inviti e sconti | Host, persone invitate: link/codice, chi si è registrato e ha pagato; email dell'invitato solo hash + abbreviata (`referrals`, `referral_invites`) | Fornitore della posta | No | 24 mesi (`referral_invites` cancellati in automatico) | Email dell'invitato mai in chiaro |
| 6 | Giudizi e pareri dei clienti | Pubblicare i giudizi; migliorare il servizio | Host: voto, testo, nome, struttura, città, foto, consenso con testo e data (`testimonials`); risposte «Cosa ti ha fermato?» (`feedback`) | Visitatori del sito (solo giudizi pubblicati) | No | Giudizi fino alla revoca; pareri come l'account | Pubblicazione solo dall'amministrazione, revoca dal pannello |
| 7 | Statistiche di lettura delle guide | Statistiche aggregate per l'host | Nessun dato personale: eventi anonimi senza IP né cookie (`analytics_events`) | Host | No | Senza limite | — |
| 8 | «Anche il tuo sito» | Rispondere alla richiesta di un sito | Visitatori e host: risposte, canale, pagina, account se loggato (`interessi_sito`); messaggi se scrivono | **Blackout Agency** (blackout.in), titolare autonomo | No | 12 mesi (cancellazione automatica) | Nessun IP salvato |
| 9 | Statistiche del sito | Capire l'uso del sito | Visitatori: pagine, provenienza, dispositivo; Matomo con IP anonimizzato (2 byte) e senza cookie; GA4 e Clarity solo con consenso | Matomo sul nostro server; Google, Microsoft (titolari autonomi) solo con consenso | Google LLC, Microsoft Corp. (USA) con consenso: SCC + DPF | Matomo dati grezzi 6 mesi; Google/Microsoft secondo le loro policy | Banner del consenso (8H), nessuno script di terzi prima della scelta |
| 10 | Registri tecnici e di sicurezza | Sicurezza, abusi, errori; controllo degli amministratori di sistema (Garante 27/11/2008) | Host, visitatori, amministratori: IP abbreviati, email e telefoni mascherati, tentativi di accesso (IP come HMAC), azioni degli amministratori (`storage/logs`, `rate_limits`, `audit_log`) | Hostinger; AWS S3 Milano (copia accessi admin) | No | Tecnici 90 giorni (30–180); tentativi 24 ore; posta di prova 7 giorni; accessi admin inalterabili 6 mesi, cancellati a 12; IP nell'audit tolto a 12 mesi | Mascheramento, Object Lock Compliance, chiavi IAM solo scrittura |
| 11 | Assistenza | Rispondere alle richieste | Chi scrive: contenuto e contatti | Fornitore della posta; WhatsApp (Meta) se usato | Meta (USA) se si usa WhatsApp | 24 mesi dalla chiusura della richiesta (**a mano**) | Casella con password robusta |
| 12 | Accesso dell'assistenza all'account | Assistenza tecnica | Host: quello che si vede nel pannello; traccia degli accessi (`audit_log` `impersonate.*`) | — | No | Traccia come l'account; IP abbreviato, tolto a 12 mesi | Accesso massimo 1 ora, registrato, visibile all'host in Account |

## Trattamenti come responsabile (per conto degli host)

Dati che gli host inseriscono nelle guide o ricevono tramite il servizio (nomi e contatti propri e dei collaboratori, email «per gli ospiti», messaggi WhatsApp degli ospiti, voti anonimi). Accordo art. 28: `/dpa` (Allegato A dei Termini), accettato alla registrazione (`users.dpa_version`).
Categorie di interessati: host, collaboratori, ospiti che contattano l'host, persone nelle foto. Sub-responsabili: Hostinger, AWS, fornitore della posta. Violazioni: avviso all'host entro 48 ore. Fine contratto: cancellazione entro 30 giorni dalla chiusura.

## Fornitori (responsabili ex art. 28) e i loro accordi sul trattamento

| Fornitore | Servizio | Sede / dati | DPA |
|---|---|---|---|
| Hostinger International Ltd | Hosting, database, posta (se si usa la loro) | Cipro; server UE | https://www.hostinger.com/legal/dpa (indirizzo da verificare) |
| Amazon Web Services EMEA SARL | S3 (eu-south-1 Milano), Translate (eu-west-1 Irlanda) | Lussemburgo; dati UE | https://d1.awsstatic.com/legal/aws-dpa/aws-dpa.pdf |
| Stripe Payments Europe Ltd | Pagamenti | Irlanda; trasferimenti a Stripe Inc. con SCC + DPF | https://stripe.com/legal/dpa |
| Adamo (app.adamogestionale.it) | Fatturazione elettronica | Italia | _da chiedere al fornitore e archiviare_ |
| Fornitore della posta in uscita | Invio email | Quello in Impostazioni → Posta | _secondo il fornitore scelto_ |

Titolari autonomi (nessun DPA, informativa propria): Google (GA4), Microsoft (Clarity), Blackout Agency (percorso «Anche il tuo sito»), Meta (WhatsApp).

## Da fare (non ancora nel codice)

- Cancellazione automatica degli account inattivi a 24 mesi con avviso a 30 giorni (6L), download dei dati dal pannello e chiusura dell'account dal pannello (6L): oggi su richiesta a assistenza@welcomemyhouse.it.
- Backup cifrati (6L).
- Conservazione di 24 mesi della casella dell'assistenza: da fare a mano.
