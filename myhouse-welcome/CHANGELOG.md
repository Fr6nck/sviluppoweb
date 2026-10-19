# Changelog — MyHouse Welcome

## v2 · Cookie, webhook e ritorno dal pagamento (19 ottobre 2026)

Nessuna migrazione.

- **Informativa, «Cookie tecnici»** (italiano e inglese): ora nomina anche `mhw_fascia_…`, il cookie tecnico del pannello che nasconde per 24 ore l'avviso del rinnovo quando l'host preme «Chiudi» (9D). Correzione di completezza, non un cambio sostanziale: la versione dei documenti legali resta la stessa e nessuno deve riaccettare. Se il testo «Cookie tecnici» è stato modificato a mano in Testi del sito, resta quello salvato: va aggiornato lì.
- **Ritorno dal pagamento** (`/pagamento/ok`): nella pagina «Stiamo attivando la tua guida» i bottoni «Apri la guida» e «Scarica il QR» si vedevano già prima della conferma di Stripe, perché lo stile di `.actions` vinceva sull'attributo `hidden` (e la guida, aperta in quel momento, dava 404). Ora restano nascosti finché Stripe non conferma.
- **Eventi del webhook di Stripe**: l'elenco in Impostazioni → Stripe, nel LEGGIMI e in DA-CONFIGURARE comprende anche `customer.subscription.trial_will_end` e `charge.refunded` (già gestiti dal 9D e dal 9E); nel LEGGIMI anche i due `checkout.session.async_payment_*`.

## v2 · 9I Codice cliente, codice struttura e codice pagamento; «Cerca» in Amministrazione (18 ottobre 2026)

Migrazione **048**: `accounts.code`, `properties.code`, `orders.code` (uniche), `subscriptions.code`, `rimborsi.code`, registro `codici_usati`; i codici per tutti gli account, tutte le strutture (anche archiviate e la vetrina) e gli ordini pagati. Classe `Codici`. Da configurare: DA-CONFIGURARE, «4j» (comando una tantum per Stripe, Adamo).

- **I tre codici**: cliente `MW-7K4P2` (5 caratteri senza 0, O, 1, I), struttura `MW-7K4P2-01` (in ordine di creazione), pagamento `MW-7K4P2-2026-1` (anno e progressivo dell'anno per quel cliente). Mai cambiano, mai si riusano: ogni codice resta nel registro `codici_usati`, anche quando il record si cancella. Si generano alla registrazione, alla struttura nuova, all'ordine (anche il conguaglio); l'abbonamento porta il codice dell'ordine che l'ha creato; i rinnovi e i conguagli senza ordine hanno il loro codice nel registro con l'id della fattura Stripe; il rimborso porta il codice del pagamento rimborsato.
- **Pannello**: Account & Fatturazione → «Il tuo codice cliente MW-…» con «Copia»; il codice di ogni pagamento; il codice della struttura in piccolo accanto a «La struttura»; i mailto dell'assistenza con «[MW-…] …» nell'oggetto; «Domande su fatture e pagamenti?» con «[MW-…] Fatturazione».
- **Email**: «Codice cliente MW-…» nel piede delle email a un cliente (non a chi non è cliente, come gli amici invitati, né alla vetrina).
- **Amministrazione**: colonna «Codice» in Clienti; nella scheda cliente i tre livelli con «Copia» (strutture e pagamenti, con quello cercato in evidenza); il codice cliccabile accanto al nome in Preventivi, Giudizi, Codici omaggio («usato da MW-…»), Traduzioni; il codice del rinnovo accanto alla fattura; la barra «Stai guardando con l'account di un cliente» mostra il codice.
- **«Cerca»** in alto in ogni pagina di Amministrazione (tasto **/**), `GET /admin/cerca`: codici scritti male (`Codici::normalizza`: «mw 7k4p2», «MW7K4P2»), email (anche in attesa e PEC), nome e intestatario, telefono (solo cifre, anche senza +39), nome della struttura, città, CIN, indirizzo della guida o URL intero, partita IVA e codice fiscale, ID di Stripe (`cus_`, `sub_`, `cs_`, `in_`, `pi_`, `ch_`, `re_`), codice sconto, omaggio o d'invito usato. Un risultato apre la scheda (con la struttura o il pagamento in evidenza), più risultati un elenco con «perché», nessuno «Nessun cliente con “…”». Query con parametri, uguaglianza su codici e ID, al massimo 50; nel registro `admin.search` senza il testo se contiene un'email. Clienti usa la stessa ricerca.
- **Stripe**: `metadata[codice_cliente]` sul cliente (alla creazione e a ogni aggiornamento dei dati di fatturazione); `codice_cliente`, `codice_pagamento`, `codice_struttura` nei metadati di Checkout, abbonamento e pagamento; la descrizione dell'abbonamento e della fattura del conguaglio «Codice cliente … · Pagamento …» (la causale che legge Adamo); sulle fatture di rinnovo i metadati con il codice nuovo (`invoice.paid`). `tools/stripe-codici.php`: scrive il codice sui clienti Stripe che esistono già (a vuoto, poi `--scrivi`, con le chiavi vive anche `--live`).
- **Registri**: ogni riga di `app-*.log` con un account in gioco (nel contesto o il cliente collegato) porta `codice_cliente`, non mascherato.
- Non fatto, perché nel codice non c'è: il riepilogo di chiusura dell'account (oggi si chiede per email) e la ricerca per numero di fattura Adamo (il sito non lo salva).
- Screenshot in `prove/schermate/9i-*.png`.

## v2 · 9J Palette «Bianco e nero» (18 ottobre 2026)

Migrazione **047**: `properties.photos_bw`.

- **Settima palette** «Bianco e nero» (`monocromo`, prima di Grafite), chiara e scura, tutte le coppie ≥ 5,7:1. In Aspetto con la riga «Elegante e sobria: ideale se le foto della casa parlano da sole.», disponibile come le altre palette.
- **Davvero monocromatica**: i colori della guida che non venivano dalla palette (bollino DEMO, pillole ocra e mare come «Ti accogliamo di persona», bordo dei bottoni della lingua, lente sulle foto, fondo e testi delle pagine a tutto schermo) ora sono variabili con lo stesso valore di prima; solo «Bianco e nero» le ridefinisce in grigio (`Palette::P[…]['extra']`). Restano a colori solo il verde e il rosso di significato. Controllato con Playwright su tutte le pagine della vetrina, chiara e scura.
- I riquadri con un bordo di 1 px della palette (`--tile-bordo`, solo per questa palette); la pillola del check-in con il testo della palette e il pallino verde.
- **«Foto in bianco e nero»** (casella in Aspetto, solo con questa palette, di serie spenta): copertina, foto delle sezioni e dei luoghi, foto profilo in scala di grigi nella guida (non il logo né il QR); entra nell'istantanea; anche l'anteprima del link (OG). L'`<html>` della guida ha `palette-{codice}` e `foto-bn`.
- Le altre sei palette identiche a prima: confronto pixel per pixel con la versione precedente (Terracotta chiara e scura, Mare). Screenshot in `prove/schermate/9j-*.png`.

## v2 · 9H ter Email: quattro indirizzi (18 ottobre 2026)

Nessuna migrazione. Da configurare: DA-CONFIGURARE, «4j», blocco 9H (Hostinger, Stripe, Adamo, Brevo).

- **Amministrazione → Impostazioni → Indirizzi email**: assistenza, commerciale, newsletter (news@, il sito non la usa per inviare), amministrazione; gli ultimi tre vuoti valgono l'assistenza. `Support::newsletter()`, `Support::amministrazione()`, `Support::fraseFatture()`.
- **amministrazione@**: le email di rinnovo, scadenza, pagamento in sospeso, cambio piano, conguaglio e rimborso hanno `Reply-To` amministrazione@ (`Mailer::diPagamento`) e la riga «Per domande su fatture e pagamenti: amministrazione@…»; gli avvisi a noi su pagamenti falliti (`invoice.payment_failed`) e rimborsi confermati (`charge.refunded`) vanno ad amministrazione@ (`Mailer::avvisaAmministrazione`); Account & Fatturazione «Domande su fatture e pagamenti? Scrivi a amministrazione@…»; Termini, punto 6, «Per le questioni di fatturazione e pagamento scrivi a …» (segnaposto `{email_fatture}`, italiano e inglese).
- **commerciale@**: in più la notifica a noi delle risposte di «Anche il tuo sito» (con le risposte, senza dati del visitatore), l'oggetto del mailto «MyHouse siti: informazioni», la firma dei messaggi dei codici omaggio.
- La password provvisoria non è un'email di pagamento: `Reply-To` assistenza.

## v2 · 9H bis Posta: assistenza@ e commerciale@ (18 ottobre 2026)

Nessuna migrazione: sono impostazioni. Da configurare: DA-CONFIGURARE, «4j», blocco 9H.

- **Due indirizzi** (Impostazioni → Sito): «Email assistenza» (`contatti.email`) e **«Email commerciale (preventivi e marketing)»** (`contatti.commerciale`, `MHW_COMMERCIAL_EMAIL`, di serie commerciale@welcomemyhouse.it; vuota = l'assistenza). `Support::commerciale()` e `Support::mailtoCommerciale()` accanto a `Support::contatto()`.
- **A commerciale@**: le richieste di preventivo oltre il Portfolio (a noi con `Reply-To` chi l'ha chiesto; la conferma al cliente da assistenza@ con `Reply-To` commerciale@, anche in inglese), il mailto «Chiedi un preventivo» (`mailtoPreventivo`), le risposte di «Anche il tuo sito» e «Oppure scrivici subito», le risposte agli inviti degli amici (`Reply-To`). Tutto il resto resta sull'assistenza.
- **Mailer::conRispostaA**: un `Reply-To` diverso per un invio, poi torna quello di serie (l'assistenza).
- **Mittente salvato corretto una volta** (`Impostazioni::correggiMittente`, dopo le migrazioni): se in `config.local.php` il mittente è `noreply@myhouse.it` o sta su un altro dominio, diventa l'email assistenza; copia in `config.local.bak.php`, riga `settings.mail_from` nel registro, segno in `storage/mittente-9h.txt`, messaggio in Gestione alla prima apertura. Un mittente da variabile d'ambiente non si tocca; con `base_url` su un IP o localhost si corregge solo `noreply@myhouse.it`.
- Aiuto del campo «Mittente»: «Deve essere una casella vera del dominio del sito, con l'SMTP qui sotto: di serie l'email assistenza». Gli aiuti di Impostazioni non scrivono più indirizzi.
- `vetrina_email` di serie **vetrina@welcomemyhouse.it** (prima blackout.agency@gmail.com), esclusa dai Richiami (`Richiami::manda`). La vetrina già creata non si sposta.
- Nessun indirizzo email scritto a mano in `src/`, `views/` e `lang/` fuori dai valori di serie di `config.php` e dagli esempi (lo controlla la prova 9H).
- La tabella dei codici omaggio del mockup aggiornato (schede sotto 700 px, «Usato il …» con il nome e il primo addebito sotto) era già così dalla 9D: nessuna modifica.

## v2 · 9F Rifiniture della guida e del pannello, Regione e Provincia, pannello in inglese (18 ottobre 2026)

Migrazione **046** (con la 9G): `properties.province`, `accounts.translate_cap_override`, `users.panel_lang`, tabella `translation_cache`; le vetrine pubblicate ricevono i portali (Google, Booking, Airbnb, Instagram) e «PG · Umbria» se mancano, e vengono ripubblicate. Da configurare: DA-CONFIGURARE, «Fase 9 ter».

- **9F·1 Finestra della lingua** (`scegli-lingua`): i bottoni delle lingue usano i colori della scelta (`--scelta-fondo`/`--scelta-ink`) e non più quelli della tavolozza: leggibili in tutte e sei le tavolozze, chiare e scure. Screenshot `prove/schermate/9f-lingua-*-390.png`.
- **9F·2 Vetrina**: il marchio in alto porta a `/` e sotto compare «Questa è una guida di esempio · Torna al sito · Crea la tua guida» (cinque lingue, chiavi `demo_*`); nelle guide vere non c'è.
- **9F·3 Portali a 5 stelle nella vetrina**: la causa era la **fotografia pubblicata** della vetrina, nata prima dei portali (nessuna riga in `property_channels` nel JSON): `Demo::vetrina` li salvava già e il saluto finale era già quello dell'8C. La migrazione aggiunge i portali alle vetrine che non li hanno e le ripubblica.
- **9F·4 Prenotazione diretta** (Impostazioni → Dopo il soggiorno): due colonne da 900 px (`.diretta`), «Codice sconto» e «Sconto» sulla stessa riga 3/2 (`.diretta__codice`), l'aiuto del codice sotto tutti e due.
- **9F·5 Inviti**: il messaggio di «Invita per email» e di WhatsApp parte già scritto con lo sconto (`Inviti::testoPartenza`, Testi e foto → `inviti.messaggio_partenza`, segnaposto `{amico}`), poi il link e il codice.
- **9F·6 Avvisi senza X**: il riquadro «Hai già un sito?» resta sempre (dopo «Sì»/«No» diventa compatto: «Hai già risposto: vuoi cambiare?»); `/pannello/sito/nascondi` non nasconde più niente. La fascia **Novità** mostra solo l'ultima, senza ✕: «Mostrami dove» (`POST /pannello/novita/vai`) la segna come vista e porta lì; le passate stanno nella pagina **Novità** (`/pannello/novita`, nel menù).
- **9F·7 «Una lingua in più, oltre alle cinque di base»** (`_lingua_come.php`, dal mockup): in Lingue sopra la scelta, con i due esempi e la riga sui bottoni tradotti; nella struttura (primo passo e Impostazioni) la versione breve. Con Essential: «2 lingue: la tua e una a scelta». Testi in Testi e foto, sezione «Pannello: una lingua in più» (`lingue.extra.*`, anche in inglese).
- **9F·8 Traduzione in corso** (`cms.js`): «Traduci tutti i campi mancanti» chiama `POST …/lingue/{loc}/suggerite.json` un gruppo di campi per volta, il bottone si disattiva con la rotellina, la barra dice «Inglese · 12 di 30 testi», i campi compaiono con una dissolvenza di 400 ms (`.traduci-campo--nuovo`), «Fatto» verde per 3 secondi, errore giallo con «Riprova» (gli altri campi restano). Nell'editor della sezione «Traduci questa sezione in N lingue», una lingua per volta. Senza JavaScript resta il modulo di prima; `prefers-reduced-motion` ferma le animazioni. Screenshot `9f-traduzione-*.png`.
- **9F·9 «Guida di esempio»**: dovunque si parla della guida di esempio il link è la vetrina (Casa Checco, `Demo::esempio`); se la vetrina manca, Gestione lo dice in rosso.
- **9F·10 Regione e Provincia** (`Territorio`, `lang/regioni-province.php`): due campi al posto di «Zona o regione», la provincia come sigla (PG, FI, RM…) con i suggerimenti delle 107 sigle e la regione che si completa da sola (`cms.js`); all'estero testo libero. La guida mostra «Città · Provincia · Regione» (`Territorio::dove`), tradotti dove esiste una forma (Firenze → Florence/Florenz, Toscana → Tuscany/Toskana) in it/en/fr/de/es/nl/pt/pl. Marketing ricava la regione dalla provincia per le statistiche per zona. Screenshot `9f-regione-provincia-*.png`.
- **9F·11 Pannello in inglese** (`Pannello`, `lang/pannello-en.php`, `lang/catalogo-en.php`): quando la lingua principale della guida non è l'italiano il pannello passa in inglese (`<html lang="en">`): la lingua in cui scrivi è la prima cosa del primo passo, con «With Essential» / «With Plus and Portfolio» e «Your language isn't listed? Write to us» (mailto «Lingua mancante: …»); procedura, modulo della struttura, catalogo delle sezioni con etichette e aiuti, editor, Lingue, Account e menù sono tradotti; in Account l'interruttore «Dashboard language: Default / Italiano / English» (`users.panel_lang`, nel registro `panel.language`). Restano in italiano: il passo Pubblica (testi legali e pagamento), Impostazioni, Aspetto, QR & Link, Statistiche e i messaggi di esito. L'amministrazione è sempre in italiano. Screenshot `9f-pannello-inglese-*.png`.
- **9F·12 Sezione libera**: una per guida in tutti i piani (`SectionCatalog::LIBERE_MAX = 1`), anche con Essential (conta come una delle 6); chi ne ha già 2 o 3 le tiene. FAQ, Termini (punto 2) e «Confronta i piani» («Sezione libera (una per guida)») aggiornati.

## v2 · 9G Traduzioni suggerite: sempre comprese, tetto annuale, memoria delle frasi (18 ottobre 2026)

- **Sempre per Plus e Portfolio**: via «il primo anno è in omaggio» (`translation_trial_until` non conta più; `Traduttore::omaggioFinito` è sempre falso). Schede dei piani: «Comprese nel piano, entro un limite annuale di caratteri» (la migrazione corregge i `bullets`); Confronta i piani, FAQ e Termini (punto 13) aggiornati, in italiano e in inglese.
- **Tetto annuale per account** (`translate.cap_account`, di serie **500.000** caratteri): l'anno parte dall'attivazione dell'abbonamento e si azzera al rinnovo (senza abbonamento: l'anno solare). Il tetto mensile del sito resta. Raggiunto: «Hai usato le traduzioni suggerite di quest'anno (N caratteri). Puoi scrivere le traduzioni a mano, oppure scrivici: le riattiviamo noi» con il mailto; registrato come `limite`; **email a noi** la prima volta all'80% e al 100% (`avviso80`/`avviso100` in `translation_usage`).
- **Scheda del cliente**: «Traduzioni: 123.000 / 500.000 caratteri quest'anno», **«Aggiungi 250.000»** (nota obbligatoria, nel registro `translate.cap_added`) e il tetto personale (`accounts.translate_cap_override`, `translate.cap_override`).
- **Solo i campi cambiati**: salvare una sezione senza cambiare niente non chiama Amazon; cambia un campo già tradotto e la suggerita nuova è «Aggiornata: da ricontrollare» e sostituisce la traduzione vecchia quando la approvi.
- **Memoria delle frasi** (`translation_cache`: sha256 di testo+da+a, esito `cache`, `hits`): la stessa frase in un'altra guida o in un'altra struttura non si ripaga; il cron toglie le righe mai riusate da 12 mesi (`Traduttore::pulisciCache`).
- **Meno caratteri**: spazi doppi e righe vuote via; `{segnaposto}`, link, email, telefoni, orari, temperature, prezzi e percentuali sostituiti da `⟦n⟧` prima dell'invio e rimessi dopo (`proteggi`/`ripristina`); i campi che non si traducono mai (nomi, codici, indirizzi, SSID, link) per nome (`Traduttore::NON_TRADURRE`).
- **Contatori**: in Account & Fatturazione e in Lingue (e nella pagina della lingua) «Traduzioni suggerite: 24% dell'anno usato · si azzera il …» con la barra; arancione sotto il 10% che resta; al 100% il messaggio con l'assistenza. Amministrazione → **Vendite → Traduzioni**: per mese con gli esiti (ok, dalla memoria, fermate dal tetto, errori), chi traduce di più nel mese e negli ultimi 12 mesi con il tetto annuale, la memoria; «Anni in omaggio» non c'è più.

## v2 · 9H Posta: mittente assistenza@welcomemyhouse.it, Reply-To all'assistenza (18 ottobre 2026)

- `mail.from` di serie **assistenza@welcomemyhouse.it** (`MAIL_FROM`); Gestione avvisa se il mittente in Impostazioni non è sul dominio del sito (`Mailer::mittenteFuoriDominio`; con un IP o localhost non giudica). Ogni email ha `Reply-To` all'indirizzo di contatto (`Support::contatto()`); tutte le email «a noi» (giudizi, preventivi, «Anche il tuo sito», tetti delle traduzioni, rimborsi) passano da lì: nessun indirizzo scritto a mano in `src/` e `views/`. DA-CONFIGURARE: casella su Hostinger, SPF/DKIM/DMARC.

## v2 · 9E Amministrazione: fermare il rinnovo, cambiare il pacchetto, rimborsare, password provvisoria (17 ottobre 2026)

Migrazione **045** (con la 9D). Ogni azione: solo amministratori, CSRF, motivo nel registro (`audit_log`), email al cliente che dice cosa è cambiato. Classe `AzioniCliente`.

- **Ferma il rinnovo / Riattiva il rinnovo** (scheda cliente → Abbonamento): lo stesso effetto di quando lo fa il cliente (`cancel_at_period_end` su Stripe e nel database). Resta attivo fino alla scadenza (o alla fine dell'omaggio), poi la guida va offline senza addebito.
- **Cambia il pacchetto** (scheda cliente): piano e strutture, «Anteprima» con il conto, poi due modi. **Subito, con conguaglio**: riusa `CambioAbbonamento` (6H): l'ordine di cambio nasce dall'amministrazione, il cliente riceve l'email con il link di pagamento della differenza (`checkoutChange`) e il cambio parte quando paga; senza differenza vale subito. **Dal rinnovo**: `subscriptions.next_*` (lo stesso meccanismo della discesa 6H) con il piano e le strutture nuove; al rinnovo paga il prezzo del pacchetto scelto meno gli sconti maturati (inviti, giudizio); l'avviso a 30 e 7 giorni dice già piano e importo nuovi. Scendere si fa solo dal rinnovo. Niente «subito gratis».
- **Fatture e rimborsi** (scheda cliente): le ultime 12 fatture pagate da Stripe, con «Rimborsa» totale o parziale, motivo obbligatorio (pagamento doppio, chiesto dal cliente, altro + testo). `POST refunds` con il `payment_intent` della fattura, idempotenza `mhw-refund-{invoice}-{cents}`; tabella `rimborsi`; il webhook `charge.refunded` conferma la riga e scrive nel registro. Il rimborso non tocca l'abbonamento (lo dice il riquadro); nota di credito da fare in Adamo (avviso nel messaggio di esito). Email al cliente con importo e tempi (5–10 giorni).
- **Accesso** (scheda cliente): «Manda il link per reimpostare la password» (lo stesso flusso di «Password dimenticata», token di un'ora) e «Imposta una password provvisoria»: 12 caratteri leggibili (`parola-parola-1234`), mostrata una sola volta, valida 48 ore (`users.password_temp_until`), `must_change_password = 1`: al primo accesso il cliente può solo scegliere la password nuova (`/password/cambia`) e poi entra; scaduta, l'accesso la rifiuta e vale solo il link. Mai in chiaro nel database; nel registro solo «password provvisoria impostata». Email al cliente.
- Screenshot 390/1280 in `prove/schermate/9e-*.png`.

## v2 · 9D Codici omaggio (mesi gratis con carta), avvisi a 30 e 7 giorni per tutti, rinnovo automatico nei Termini (17 ottobre 2026)

Migrazione **045**: `codici_omaggio`, `accounts.codice_omaggio_id`, `orders.codice_omaggio_id`, `subscriptions.omaggio_fino` e `card_last4`, `rimborsi` (9E), `users.password_temp_until` e `must_change_password` (9E). Da configurare: DA-CONFIGURARE, «Fase 9» (in Stripe spegnere le email «trial ending» e «upcoming renewal», accendere Smart Retries).

- **Codici omaggio** (`Omaggi`): Amministrazione → Vendite → Codici omaggio: «Nuovo codice» (mesi 1–12, scadenza di serie 14 giorni, nota), il codice appena creato (`OMAGGIO-XXXXX`, senza 0/O/1/I) con **WhatsApp**, **Email** e **Copia il link**, l'elenco con lo stato (valido / usato da … il … / scaduto / annullato) e «Annulla». Testo del messaggio in Testi e foto (`omaggio.messaggio`, segnaposto {mesi} {link} {codice} {scadenza}). Una persona sola, solo nuovi clienti (mai pagato), si **prenota** all'account quando viene applicato e si **brucia** al Checkout riuscito (webhook); resta prenotato fino alla scadenza; annullato, non vale più nemmeno se prenotato.
- **Dove si inserisce**: link **`/omaggio/XXXXX`** (in sessione, toglie un eventuale invito, registrazione con il banner «Hai N mesi gratis. Il codice è già inserito. Vale fino al …» e il campo in sola lettura) oppure **scritto a mano** nello stesso campo del codice di invito («Hai un codice di invito o omaggio?»), in registrazione (con «Applica», controllo prima di creare l'account) e in Account & Fatturazione / passo Pubblica finché non si è pagato. Si riconosce dal prefisso. Errori: «scaduto il …», «già usato», «vale solo per i nuovi clienti», «Hai già un codice omaggio: non si somma a un invito».
- **Omaggio e invito si escludono**: con un codice omaggio l'invito in corso si chiude come `omaggio` (non conta per chi ha invitato) e lo sconto dell'amico decade; un codice di invito scritto dopo dà errore. Lo sconto del **giudizio** (9B) resta e va sul primo addebito. Niente codici sconto scritti a mano con l'omaggio.
- **Checkout** (`Stripe::checkoutSubscription`): `subscription_data[trial_end]` = mezzanotte UTC di oggi + N mesi (`DateTimeImmutable::modify('+N months')`), `payment_method_collection = always`, `trial_settings[end_behavior][missing_payment_method] = cancel`, metadati `omaggio_codice` e `omaggio_mesi`; il solo coupon ammesso è quello del giudizio (`duration=once`, Stripe lo applica alla prima fattura, cioè a fine omaggio). Controllo 2 della 9A: con l'omaggio `amount_total` deve essere 0. `tools/verifica-stripe.php` prova anche 1, 6 e 12 mesi con e senza giudizio (sessione a 0 €).
- **Passo Pubblica** con l'omaggio: «Oggi paghi 0 €. I tuoi primi N mesi sono gratis: il primo addebito di X € + IVA sarà il …» (avvisi 30 e 7 giorni, disattivazione), riepilogo con «Codice omaggio · N mesi gratis», «Oggi 0 €», «Il {data} X € + IVA»; bottone «**Inserisci la carta e pubblica · 0 € oggi, X € + IVA il …**» (art. 65 Codice del Consumo).
- **Webhook** (`Billing::activate`): l'abbonamento nasce `trialing`, `current_period_end` e `omaggio_fino` = fine omaggio, la guida si pubblica subito, il codice si brucia, `card_last4` da Stripe (`default_payment_method` espanso, o il metodo predefinito del cliente). `customer.subscription.trial_will_end`: solo registro. Lo sconto del giudizio promesso al pagamento si segna «applicato al primo anno» alla prima fattura (`invoice.paid`), non al Checkout. Account & Fatturazione: «In omaggio · Omaggio fino al … · poi X € + IVA l'anno», «Disattiva il rinnovo» con «fino al … la guida resta online e non paghi nulla».
- **Avvisi a 30 e 7 giorni per tutti** (`Richiami`): rinnovo automatico (Stripe, `active` o `trialing`): email a 30 e a 7 giorni (`ref` «-30» e «-7») con piano, importo, «sulla carta che termina con ####» (o «sul tuo metodo di pagamento»), «Non devi fare nulla. Se non vuoi rinnovare, disattiva il rinnovo entro il …»; con sconti «Hai uno sconto del N%: pagherai …»; in omaggio «La tua prova gratuita finisce il …: da quel giorno parte …». L'importo si legge da Stripe (`GET invoices/upcoming`), altrimenti dal listino con gli sconti noti (inviti + giudizio, piano programmato). L'avviso a **7 giorni parte sempre** (è dovuto prima di un addebito): ignora `email_optout` e lo dice nel piè. Senza rinnovo: `scadenza` a 30 e 7 e il giorno dopo, come prima. Niente per clienti di prova e demo. Il promemoria a mano sceglie il testo a 7 o a 30 giorni. L'avviso a 30 giorni di chi rinnova tiene anche le statistiche dell'anno (aperture, QR) e la spinta a invitare un amico, come dalla fase K5.
- **Nel pannello**: dagli stessi 30 giorni una fascia in cima a «Le mie guide» e ad Account & Fatturazione: «Rinnovo il … · piano · importo · carta» con «Disattiva il rinnovo», oppure «La tua prova gratuita finisce il …» / «L'abbonamento scade il … e non si rinnova»; a 7 giorni in evidenza (colore d'accento). «Chiudi» la nasconde per 24 ore (cookie), poi ricompare.
- **Termini** (IT/EN): punto 5 «Rinnovo automatico» riscritto (30 e 7 giorni, disattivazione senza altri addebiti); punto 17 «lo sconto del giudizio si ha una sola volta»; punto nuovo **18 «Codice omaggio»** (dopo il 17). **Privacy** t2: le ultime 4 cifre della carta ricevute da Stripe per gli avvisi, l'eventuale codice omaggio. Le versioni di serie restano **2026-10-16** (le stesse della 9B, pubblicate insieme).
- **Amministrazione**: Clienti con la colonna «Omaggio fino al» e il filtro «In omaggio»; Quadro «Clienti in omaggio · primo addebito entro 30 giorni»; Marketing: tabella «Codici omaggio» (creati / usati / diventati paganti); Scadenze: il prossimo avviso automatico anche a 7 giorni. Stripe finto delle prove: sessioni con `trial_end` a 0 €, `invoices/upcoming`, `invoices`, `refunds`, carta.
- Screenshot 390/1280 in `prove/schermate/9d-*.png`: registrazione dal link, riepilogo con «0 € oggi», fascia dei 7 giorni, pagina dei codici omaggio.

## v2 · 9B-bis «Quando si applica lo sconto» del giudizio (17 ottobre 2026)

- Impostazione **`giudizi.sconto_quando`** (`MHW_GIUDIZI_QUANDO`): **all'adesione** (di serie: chi spunta la casella al pagamento ha il 5% sul primo addebito, anche se il giudizio arriva dopo) oppure **solo dopo il giudizio** (lo sconto parte quando il giudizio è stato inviato: sul primo addebito se arriva prima, per esempio durante i mesi di un codice omaggio, altrimenti sul rinnovo, con il coupon «una volta» sull'abbonamento). Interruttori in Amministrazione → Giudizi (riquadro «programma»: acceso, percentuale, quando, sezione in home, con la password) e in Impostazioni → Giudizi con sconto. Sotto la casella al pagamento, con «solo dopo il giudizio», la riga che lo spiega.

## v2 · 9C Bottoni su telefono, marchio dentro le email, indirizzo fisso della vetrina (16 ottobre 2026)

Nessuna migrazione. Da configurare: DA-CONFIGURARE, «Fase 9».

- **Bottoni e pillole su telefono**: `.btn` e `.badge` non vanno più a capo (`white-space: nowrap`); nelle tabelle e nelle righe i bottoni non si restringono (`flex: none`): se lo spazio manca scorre il solo contenitore della tabella (`.tablewrap`), mai la pagina. In Amministrazione → Clienti e nella scheda del cliente «Entra come cliente» diventa «Entra» sotto i 640 px, con `aria-label` completo. Controllate con Playwright a 360 e 390 px tutte le pagine dell'amministrazione (Quadro, Abbonamenti, Anomalie, Clienti e scheda cliente, Diagnostica, Giudizi, Guide, Impostazioni, Inviti, Marketing, Piani, Preventivi, Prospetti, Registro, Scadenze, Codici sconto, SEO e GEO, Testi e foto, Testimonianze, Traduzioni, Vendite) e del pannello (Le mie guide, Nuova struttura, Account, Cambia piano, Giudizio, Inviti, la struttura, i cinque passi della procedura, QR, Statistiche, Pubblicata, Scegli il piano). Corretti: la testata del pannello sotto i 400 px (i bottoni «Continua dopo»/«Pubblica» facevano scorrere la pagina a 360 px), i testi lunghi delle Anomalie, i campi file di SEO e GEO e Testimonianze (5 px oltre il bordo), le pillole di stato nelle tabelle (andavano a capo dentro la pillola). Screenshot prima/dopo di Clienti in `prove/schermate/9c-clienti-*.png`.
- **Marchio dentro le email**: `Mailer` manda `multipart/related` con il PNG del marchio in linea (`Content-ID: <marchio>`, `<img src="cid:marchio">`, ~12 KB): si vede anche con le immagini esterne bloccate (Outlook, Gmail). Le icone social restano esterne, con l'indirizzo da `base_url`. In modalità prova `storage/logs/email/` tiene l'HTML con `cid:marchio` e il messaggio intero `.eml`. Il piè delle email è quello dell'8J (City Up S.r.l.). Nuovo avviso nel Quadro se «Indirizzo pubblico del sito» è vuoto (i link e le icone nelle email dal cron puntano a localhost).
- **Indirizzo della vetrina**: `Demo::slugVetrina` dà a «Casa Checco» sempre `/g/casa-checco`; se lo slug è di una struttura di esempio o archiviata, quella cambia slug (`casa-checco-prima…`); se è di un cliente vero la vetrina tiene il numero e «Rifai la vetrina» lo dice. I vecchi `/g/casa-checco-N` (e le loro sottopagine) rispondono **301** verso la vetrina attuale. In Amministrazione → cliente della vetrina: l'indirizzo attuale con «Apri» e «Copia». Tutti i link alla guida di esempio passano già da `Demo::esempio()` (nessun indirizzo scritto nei testi o in `site_texts`).

## v2 · 9B «Ti piace MyHouse Welcome?»: giudizio con sconto, «Cosa dicono di noi», Amministrazione → Giudizi (16 ottobre 2026)

Migrazione **044**. Da configurare: DA-CONFIGURARE, «Fase 9». Termini (punto 17) e Privacy (t6) cambiano: versioni di serie **2026-10-16** (il DPA resta 2026-10-15): i clienti con un abbonamento ricevono l'avviso e ri-accettano.

- **Regole** (Termini, «Giudizio con sconto»): una sola casella, al pagamento o dal pannello; chi aderisce si impegna a lasciare un voto 1–5 con due righe dopo la pubblicazione e accetta che possa comparire in home (nome senza cognome, struttura, città, immagini scelte, nessun link); in cambio il 5% (Impostazioni, 1–20). Al pagamento: sul primo anno, come sconto di sistema (9A), sommato all'invito, non a un codice. Dopo, dal pannello: sul rinnovo successivo, quando ha lasciato il giudizio. Lo sconto non dipende dal voto. Pubblicazione a nostra discrezione; ritiro quando si vuole (lo sconto già avuto resta, quello promesso decade). Esclusi clienti di prova, account di esempio e la vetrina. Nuova classe `Giudizi`.
- **Dati**: `accounts.giudizio_adesione` (pagamento | pannello), `giudizio_adesione_at`, `giudizio_sconto_stato` (promesso | applicato_primo_anno | da_applicare_rinnovo | applicato_rinnovo | nessuno), `giudizio_sconto_at`; `testimonials.show_cover/show_photo/show_logo`, `home_visible`, `published_at`. `consent_at`/`consent_text` tengono la data dell'adesione e il testo della casella. I giudizi già pubblicati (7D) restano in home.
- **Pagamento** (passo «Pubblica»): la casella «Sì, voglio il 5% in più: lascerò un giudizio…» (mai pre-spuntata, solo con l'interruttore acceso) con lo spiegone e il link ai Termini; spuntata si salva subito (`POST /giudizio/adesione`, anche senza JavaScript) e il riepilogo aggiunge «Giudizio · −5%»; tolta prima di pagare, l'adesione si annulla. Al Checkout un solo coupon: `mhw-giudizio-N` da solo, `mhw-primo-anno-N` con l'invito; `orders.sconti = …,giudizio:5`. Al webhook: `applicato_primo_anno`.
- **Pannello**: il riquadro «Ti piace MyHouse Welcome?» c'è dal primo accesso (stati: aderito, invito con la casella «Partecipo» + «Conferma», senza sconto «Raccontacelo» con l'interruttore spento); chiudibile, torna dopo 60 giorni; resta sempre in **Account → Il tuo giudizio** (stato dello sconto, Modifica finché non è in home, Ritira). Modulo del giudizio senza casella di consenso: 5 stelle, 20–400 caratteri con il conto, chip Copertina / La mia foto / Logo (solo quelle che esistono, pre-selezionate), «Invia il giudizio»; chi non ha aderito trova la casella «Partecipo» nel modulo. Il giudizio si lascia dopo la prima pubblicazione; dopo l'invio «Grazie! Giudizio ricevuto».
- **Sconto sul rinnovo**: con un abbonamento Stripe attivo, al giudizio il coupon `mhw-giudizio-N` (una volta) si aggiunge agli sconti dell'abbonamento **insieme** a quello degli inviti; `Inviti::sincronizza` lo conserva quando gli inviti cambiano. Il webhook della fattura di rinnovo segna `applicato_rinnovo`. Senza abbonamento attivo resta `da_applicare_rinnovo` e vale al primo Checkout come sconto di sistema. Ritiro prima del rinnovo: coupon tolto, stato «nessuno».
- **Amministrazione → Clienti → Giudizi**: interruttore e percentuale (Impostazioni → Giudizi con sconto, con «Sezione in home»), tabella (cliente e struttura, voto, testo intero al clic, adesione con data, immagini scelte, stato dello sconto, **In home** sì/no, elimina), filtri tutti / da valutare / in home / ritirati, anteprima della scheda. Testimonianze tiene solo quelle scritte da noi.
- **Home**: sezione `#cosa-dicono` «Cosa dicono di noi» tra «Più sanno, meglio stanno» e «Per chi è», solo con almeno un giudizio in home: fino a 6 schede (copertina, logo nel quadrato bianco, stelle ocra, testo tra «», foto tonda o iniziale, nome, «Struttura · Città»), **nessun link** né area toccabile, scorrimento orizzontale a scatto su telefono; testi `dicono.*` in Testi e foto (anche in inglese), nota in fondo obbligatoria (vuota torna quella di serie). Immagini ridotte da `/img/{id}/{max}` (cache in `storage/cache/ridotte`). La media da 5 giudizi in su resta; niente dati strutturati `Review`.
- **Termini**: punto 17 «Giudizio con sconto» (dopo «Invita un amico», IT/EN). **Privacy**: trattamento «Giudizi e pareri dei clienti» su base contratto (art. 6.1.b), conservazione fino al ritiro o alla chiusura dell'account.
- Screenshot 390/1280 in `prove/schermate/9b-*.png`: riquadro nei tre stati, casella e riepilogo al pagamento, modulo, Account, Amministrazione → Giudizi, sezione in home.

## v2 · 9A Portfolio progressivo, due controlli Stripe, preventivo oltre 10, codice di invito, prezzi barrati, sconti sommati (16 ottobre 2026)

Migrazione **043**. Da configurare: DA-CONFIGURARE, «Fase 9» (lanciare `tools/verifica-stripe.php` con le chiavi di prova).

- **Portfolio progressivo**: versione nuova (una volta sola): 117 € la prima struttura, 2ª +60 €, dalla 3ª alla 5ª +50 €, dalla 6ª alla 10ª +40 €; da 2 a 10 (2 = 177 €, 3 = 227, 5 = 327, 6 = 367, 10 = 527); Price ID vuoti (il sito crea il Price graduato alla prima vendita). Chi ha già un Portfolio resta sulla sua versione. Tabella degli scaglioni a 4 righe («dalla 6ª alla 10ª»: l'ultimo scaglione arriva al massimo del listino) in home, /en, /piano, registrazione, llms.txt; via «come Plus» dalle FAQ (IT/EN).
- **Stripe, due controlli**: Amministrazione → Piani, nuova versione: un Price ID si tiene solo se importo e scaglioni sono identici alla versione di prima, altrimenti si svuota («Prezzo cambiato: il Price di Stripe sarà creato nuovo»). Prima del Checkout `Stripe::verificaPrezzi` rilegge i Price (`unit_amount`, valuta, anno, `tax_behavior`, `billing_scheme` graduato e ogni scaglione) con esito in cache un'ora (`storage/.prezzi-stripe.json`); dopo, `Stripe::controllaSessione` confronta imponibile e totale senza IVA con il listino e con lo sconto atteso, altrimenti fa scadere la sessione. In entrambi i casi niente pagamento, ordine fallito, «Il pagamento non è disponibile in questo momento, ti avvisiamo noi», errore nei registri e avviso nel Quadro. `tools/verifica-stripe.php` (solo `sk_test_`): Essential, Plus e Portfolio 2–10, con e senza sconto, tabella atteso/Stripe.
- **Preventivo oltre 10**: nel selettore del Portfolio si sale fino a 999 (`data-max-prezzo`): oltre il massimo sparisce il prezzo, il bottone del piano si spegne e si apre il riquadro «N strutture? Ti facciamo una proposta su misura» (nome, email, telefono, messaggio, numero già compilato) in home, /en, /piano e registrazione; `POST /preventivo` (CSRF, 5 l'ora per IP) salva in **`preventivi`**, manda i dati all'assistenza e la conferma a chi ha scritto (anche in inglese); senza JavaScript funziona lo stesso; `Plans::quantity` continua a rifiutare oltre il massimo. **Amministrazione → Vendite → Preventivi** (stato nuovo/contattato/chiuso, mailto, telefono) e avviso nel Quadro. Conservazione 12 mesi (pulizia dei registri); Privacy: trattamento 13 «Richieste di preventivo».
- **Codice di invito in registrazione**: dal link `/i/CODICE` campo già compilato in sola lettura con «✓ Codice di {nome}: 5% sul primo anno»; altrimenti «Hai un codice di invito?» con «Applica» (`POST /invito/verifica`) e controllo di nuovo all'invio (codice falso: «Questo codice non esiste o non è più attivo. Controlla con chi te l'ha dato.», account non creato). Lo stesso campo in Account & Fatturazione finché non si è pagato.
- **Prezzi barrati** dove vale uno sconto: schede in registrazione, /piano (anche con lo script, al cambio di strutture), passo «Pubblica» (`<s>127 €</s> 120,65 € + riga verde −5% primo anno) e **riepilogo** prima del pagamento: prezzo pieno, una riga per sconto, totale primo anno, «Dal secondo anno 127 € + IVA». Arrotondamento ai centesimi sul totale, come Stripe.
- **Sconti sommati**: `Sconti::sistema` (invito dai referral, giudizio 9B) si sommano; un codice scritto a mano non si somma: vale il più alto e il riepilogo lo dice. Al Checkout un solo coupon (quello dell'invito, `mhw-giudizio-N`, o `mhw-primo-anno-N` creato una volta); `orders.sconti` tiene la composizione (`invito:5,giudizio:5`) e il webhook registra l'uso dell'invito dai metadati (la sua parte dello sconto), non dall'id del coupon. `PrezziNonCoerenti` per i due controlli.
- Stripe finto delle prove: listino dei Price, coupon con id scelto, sessioni di Checkout con gli importi (scaglioni graduati e coupon), `expire`, sconti dell'abbonamento. Screenshot 390/1280 in `prove/schermate/9a-*.png`.

## v2 · 8J Termini, Privacy e DPA conformi al GDPR, titolare City Up S.r.l. (15 ottobre 2026)

Migrazione **042**. Da configurare: DA-CONFIGURARE, «Termini, Privacy, DPA e titolare (8J)». Testi di partenza: da far rivedere da un legale.

- **Titolare: City Up S.r.l.** (P.IVA e C.F. 03793880547, REA PG-352001, Via Ariodante Fabretti 15, 06123 Perugia, PEC cityup@pec.it) al posto di Blackout Agency in `config.php → legal`, piè di pagina del sito e delle email («City Up S.r.l. · P.IVA … · PEC …»), dati strutturati (parentOrganization con www.cityup.eu), SEO e GEO (la 042 aggiorna i vecchi valori di serie), documenti. Nuovo gruppo **Impostazioni → Titolare e documenti legali** (anche le versioni). Blackout Agency resta solo come chi realizza i siti MyHouse: nel percorso «Anche il tuo sito» una riga dice che le risposte vanno a loro.
- **Termini e condizioni** riscritti: 16 punti con indice (definizioni, una guida per unità, 18 anni, prova, rinnovo con avviso, mancato pagamento, **recesso impresa/consumatore**, contenuti, ruoli privacy, responsabilità, sospensione e chiusura, Invita un amico, traduzioni, modifiche con 30 giorni di preavviso, legge e foro di Perugia, contatti), Allegato A (DPA) e **Allegato B, modulo di recesso** (con «Compila e manda per email»).
- **Informativa privacy** (artt. 13–14): 12 trattamenti, ognuno in una tabella (dati, finalità, base giuridica, conservazione, destinatari), fornitori con sede, trasferimenti extra-UE, minori, diritti, cookie (8H), modifiche, titolare, nessun DPO. Il fornitore della posta viene dall'impostazione.
- **Accordo sul trattamento dei dati** (art. 28) in `/dpa`: istruzioni, riservatezza, misure di sicurezza, sub-responsabili con 30 giorni di preavviso, violazioni entro 48 ore, cancellazione, verifiche.
- **In inglese**: `/en/terms`, `/en/privacy`, `/en/dpa`, con canonical e hreflang, nella sitemap; piè di pagina con Termini · Privacy · DPA nella lingua della pagina.
- I testi si cambiano da **Testi e foto → Documenti legali** (italiano e inglese), con i segnaposto dei dati del titolare; `lang/legale.php` tiene quelli di serie. Ogni testo in vigore si archivia da solo (`legal_versions`): «Versioni precedenti» in fondo alle pagine (`?versione=…`, noindex) e in Testi e foto. I Termini del 2026-10-08 e la Privacy 2026-10 sono già nell'archivio.
- **Versioni e ri-accettazione**: le versioni sono date (di serie 2026-10-15). Alla registrazione si accettano Termini, Privacy e DPA (`users.dpa_version`, `legal_acceptances`, una riga per documento). Versione cambiata: nel pannello la finestra «Abbiamo aggiornato i Termini e l'Informativa» con le novità in tre righe e «Accetto»; finché non accetta il pannello è in sola lettura (niente moduli, tranne «Accetto» ed «Esci»); mai durante l'accesso dell'assistenza. Versione con data futura: una riga nel pannello e la finestra a quella data. Email di avviso ai clienti con un abbonamento attivo, **una sola volta** per combinazione di versioni, dal giro dei richiami e da `/cron`.
- **Registrazione**: una sola casella, non spuntata, «Accetto i Termini e l'Accordo sul trattamento dei dati e ho letto l'Informativa privacy», con i tre link.
- **Checkout**: «Chi acquista?» impresa/professionista o privato; per il privato la casella obbligatoria «Chiedo che il servizio inizi subito…». Salvata nell'ordine e nell'abbonamento (`acquirente`, `recesso_rinuncia`; nel cambio di piano resta quella di prima) e visibile in Amministrazione → cliente (anche il DPA accettato e «Da accettare»).
- Sotto l'email per gli ospiti e i contatti della guida: «Questo contatto compare nella guida e nella pagina delle recensioni: assicurati di poterlo pubblicare».
- **Account**: «Documenti accettati e accessi dell'assistenza» (data, ora e chi è entrato nell'account).
- Tempi dell'informativa applicati dal sito: risposte di «Anche il tuo sito» cancellate dopo 12 mesi, email cifrate degli inviti dopo 24 (giro giornaliero dei registri).
- `_brief/REGISTRO-TRATTAMENTI.md`: il registro dei trattamenti (art. 30) e l'elenco dei fornitori con i loro DPA. Documento interno.
- Non fatto (manca la 6L): download dei dati e chiusura dell'account dal pannello, cancellazione automatica degli account inattivi, backup cifrati. I testi dicono di scrivere all'assistenza.

## v2 · 8I registri (log) conformi al GDPR (15 ottobre 2026)

Nessuna migrazione. Da configurare: DA-CONFIGURARE, «Registri (log) conformi al GDPR».

- **Un file al giorno**, in `app/storage/logs/` (fuori dal web): `app-AAAA-MM-GG.log` (registro tecnico, stesso formato JSON), `mail-AAAA-MM-GG.log` (posta di prova), `accessi-admin-AAAA-MM-GG.log` (nuovo). `Log::file($tipo)` dà il file del giorno; Anomalie, Diagnostica (prova «non leggibile dal web» sul file di oggi, ultimo errore S3 dagli ultimi 7 giorni) e i testi che nominavano `app.log`/`mail.log` sono aggiornati. Al primo giro i vecchi `app.log` e `mail.log` si dividono per giorno (il registro tecnico vecchio viene anche mascherato).
- **Pulizia automatica** una volta al giorno (giro dei richiami e `/cron`): registri tecnici e accessi dopo **90 giorni** (Impostazioni → Registri, da 30 a 180), posta di prova ed `email/` dopo **7**.
- **Meno dati personali:** prima di scrivere si mascherano email (`m***@dominio.it`), IP (IPv4 con l'ultimo blocco a 0, IPv6 con i primi 3 gruppi), telefoni (ultime 3 cifre), password, token e chiavi (`[nascosto]`). La posta di prova resta com'è (contiene apposta i link), ma 7 giorni.
- **Limiti ai tentativi** (`login-ip:`, `register:`, `reset-ip:`, `sito-interesse:`): l'IP diventa un codice (`RateLimit::ipKey()`, HMAC con una chiave generata una volta e tenuta in `app/storage/.chiave-registri`). I limiti funzionano come prima.
- **Registro delle azioni:** nessuna riga cancellata (alcune servono al funzionamento). Le entrate negli account dei clienti salvano l'IP abbreviato; dopo 12 mesi l'IP si toglie, e quelli già scritti si abbreviano.
- **Accessi degli amministratori:** accesso riuscito o fallito, uscita, entrata e uscita dagli account dei clienti, cambi di impostazioni e le altre azioni dello staff, con IP ed email abbreviati.
- **Copia notturna su AWS S3 Milano** dei file del giorno prima (`tecnici/`, `posta/`, `accessi-admin/`), con chiavi a parte che possono solo scrivere; gli accessi degli amministratori con Object Lock Compliance per 180 giorni, messo dal sito file per file. La cancellazione la fa il ciclo di vita del bucket. Esito in Impostazioni → Registri; «Prova» controlla anche che la cancellazione sia rifiutata.
- **Impostazioni → Registri:** spazio, numero di file, il più vecchio, l'ultima copia su S3; «Scarica gli ultimi 7 giorni» (zip) e «Svuota i registri» (scrivendo SVUOTA; il registro degli accessi degli amministratori resta).
- **Privacy:** paragrafo «Registri tecnici e sicurezza» (Testi e foto), con base giuridica, IP abbreviati, durate e luogo. `analytics_events` e `interessi_sito` non avevano IP né user agent.

## v2 · Banner del consenso più leggero (15 ottobre 2026)

Nessuna migrazione.

- **Testo corto, con le finalità e senza i nomi:** «Usiamo cookie tecnici e, solo con il tuo consenso, cookie di statistica e di analisi dell'uso per migliorare il sito. Cookie e privacy» (in inglese «…statistics and usage analysis cookies…»). Con un solo strumento configurato dice solo la sua finalità. I nomi dei fornitori (Google Analytics, Microsoft Clarity) restano in «Scegli», accanto a ogni interruttore, e nel paragrafo «Cookie» della privacy: è l'informativa a due livelli delle linee guida del Garante (2021). In Testi e foto il segnaposto è `{finalita}`; chi vuole i nomi anche nel banner può usare `{strumenti}`.
- **Su computer un riquadro compatto in basso a sinistra** (420 px) al posto della barra larga; sul telefono resta la barra in basso. «Accetta» e «Rifiuta» restano uguali, la X vale come rifiuto, niente script prima della scelta.
- Le FAQ non cambiano: parlano delle guide degli ospiti, che restano senza cookie.

## v2 · 8H statistiche delle visite e consenso ai cookie (15 ottobre 2026)

Nessuna migrazione (le impostazioni nuove stanno nella tabella di SEO e GEO). Da configurare: DA-CONFIGURARE, sezione «Statistiche delle visite».

- **Caratteri sul nostro server:** Gloock e Onest (variabile 300–800, latino e cirillico) in `assets/fonts/`, con `@font-face` e `font-display: swap` in cima ad `app.css` e il `preload` del solo Onest. Tolti `preconnect` e il foglio di `fonts.googleapis.com` dai cinque layout (le email non li usavano): nessuna pagina, guide comprese, chiede più niente a Google. Il sito è identico. `.htaccess` e `web.config` conoscono il tipo `font/woff2`.
- **Amministrazione → SEO e GEO → «Statistiche delle visite»:** Matomo (indirizzo e ID del sito), Google Analytics 4 (`G-…`), Microsoft Clarity (ID del progetto). Vuoti = spento; i valori sbagliati non si salvano. La verifica di Google e Bing resta dov'era.
- **Solo sul sito pubblico:** home e `/en`, domande, prezzi, pagine per tipologia, registrazione, accesso, «controlla la tua email», password dimenticata, pagina dopo il pagamento, Termini e Privacy. Mai nelle guide (`/g/…`), negli inviti (`/i/…`), nel pannello, in Amministrazione e nelle pagine con un codice nell'indirizzo (reimposta password, conferma email, link delle email). Agli strumenti va l'indirizzo della pagina senza la parte dopo «?».
- **Matomo senza cookie** (`disableCookies`, nessun Do Not Track, nessun utente): sempre attivo se configurato, nessun banner.
- **Obiettivi** (evento con categoria «obiettivo» e azione = nome): `registrazione`, `prova` (guida creata: dal server a Matomo, perché succede nel pannello), `pagamento` (dopo Stripe, una volta per ordine, con il nome del piano), `contatto` (WhatsApp, email, telefono), `sito-myhouse` («Sì» in «Anche il tuo sito»), `vedi-esempio` (apertura della guida di esempio). Vanno anche a GA4, solo con il consenso.
- **Banner del consenso** (solo con GA4 o Clarity): barra fissa in basso, «Accetta» e «Rifiuta» con lo stesso aspetto, «Scegli» con due interruttori spenti (Statistiche → GA4, Analisi dell'uso → Clarity), la X vale come rifiuto, scorrere non è consenso. Prima della scelta nessuno script di Google o Microsoft. La scelta, anche il rifiuto, sta 6 mesi nel cookie tecnico `mhw_consenso` (SameSite=Lax, Secure in https). «Preferenze cookie» nel piede (in inglese «Cookie preferences») riapre il banner; revocando si cancellano `_ga*`, `_clck` e `_clsk` e la pagina si ricarica. GA4 con `allow_google_signals` e `allow_ad_personalization_signals` spenti. Testi in Testi e foto → «Cookie e consenso», anche in inglese.
- **Privacy, paragrafo «Cookie»** (`/privacy#cookie`): cookie tecnici, Matomo, GA4 e Clarity (ognuno solo se configurato, con fornitore, cookie, durata e informativa), come revocare, le guide senza cookie. Testi in Testi e foto → «Informativa: il paragrafo Cookie».
- `/domande` non ha una ricerca: niente `trackSiteSearch`.
- Screenshot del banner in `app/prove/schermate/8h-consenso-*.png`.

## v2 · 8G «Più sanno, meglio stanno» nella home (15 ottobre 2026)

Nessuna migrazione, niente da configurare.

- **Sezione nuova** (`id="recensioni-positive"`) tra le tre scene e «Cosa trova l'ospite.»: lo stesso riquadro di «Un QR. Tutta la struttura.» (fondo sabbia, titolo grande, tre punti con la spunta, scheda bianca a destra; sul telefono la scheda va sotto i punti).
- **La scheda:** quattro passi disegnati in HTML, con un'icona tonda e una freccia sottile tra l'uno e l'altro: «Informazioni chiare» → «Ospite sereno» → cinque stelle ocra e «Recensione a 5 stelle» → «Nuove prenotazioni» (nel colore d'accento). Sotto: «Il circolo che riempie il calendario.». Nessun numero e nessuna recensione inventata.
- **Testi:** tutti in Amministrazione → Testi e foto, gruppo «Più sanno, meglio stanno» (chiavi `soddisfatti.*`), anche in inglese per `/en`.
- Screenshot in `app/prove/schermate/8g-soddisfatti-390.png` e `-1280.png`.

## v2 · 8F rifiniture dopo il controllo del 15 ottobre (15 ottobre 2026)

Nessuna migrazione. Dopo il caricamento: **Amministrazione → Clienti → account della vetrina → «Rifai la vetrina»** (DA-CONFIGURARE).

- **Pagina di saluto:** il blocco della prenotazione diretta diventa «Tornerai da queste parti?» con «La prossima volta prenota direttamente sul nostro sito per ottenere un codice sconto.», nelle nove lingue della guida (it, en, fr, de, es, nl, pt, pt-BR, pl). Codice, «Copia il codice» e «Prenota sul nostro sito» restano. Stesso testo nell'anteprima di Impostazioni → Prenotazione diretta e nella scheda disegnata della home. L'ordine dopo 5 stelle era già quello giusto (grazie → favore → portali → riga sulle app → «Tornerai…» con il codice → Seguici → WhatsApp → Torna alla guida) e ora c'è una prova; con 1–4 stelle il blocco del codice resta.
- **Vetrina Casa Checco:** portali Google, Booking.com e Airbnb (via VRBO), codice `CHECCO10` con −10%, Instagram e «Prenota sul nostro sito» che porta alla pagina di MyHouse (`blackout.in/myhouse/?da=vetrina`), perché la casa non esiste. Il resto (riscaldamento e aria separati, messaggi, «Due parole di benvenuto») c'era già dalla 8D.
- **«Vedi un esempio»** nell'editor di ogni sezione (anche nella procedura): 16 px, peso 500, icona 18 px, centrato sulla riga del titolo, area cliccabile di 40 px; sul telefono sotto il titolo, a sinistra.
- **Temperatura** (riscaldamento e aria condizionata, nella guida e nell'anteprima): la pillola sta al centro della scheda, icona e testo sulla stessa riga; tra il numero e «°C» uno spazio indivisibile, anche nei consigli sull'energia.
- **Home, «Anche il tuo sito»:** fondo del colore di MyHouse con la grana leggera, senza foto; logo tutto bianco (`assets/myhouse/myhouse-logo-bianco-su-rosso.png`, ricavato dal logo nuovo); kicker e seconda riga del titolo in crema `#ffe3c2`, titolo e testo in bianco, il riquadro «servizio a parte» su `rgba(0,0,0,.18)`. Vale anche per `/en`. In Amministrazione → Testi e foto non c'è più la foto di sfondo di questa sezione.
- **Colore:** il brief indicava il rosso `#c9240c` (il rosso della porta del logo vecchio); con il logo nuovo la porta è magenta, quindi il fondo è `#b8256a`, il colore del logo nuovo. Contrasto: bianco 5,98:1, crema 4,85:1. Per tornare al rosso basta cambiare `--myhouse-fondo` in `.sito2` (`assets/app.css`): con `#c9240c` il crema resta a 4,55:1.
- Screenshot in `app/prove/schermate/8f-*.png`.

## v2 · Il nuovo logo MyHouse (15 ottobre 2026)

Nessuna migrazione.

- **Logo MyHouse** (il servizio dei siti, non la guida) sostituito con quello nuovo: casa con porta magenta. Nella home («Anche il tuo sito», sfondo scuro) c'è la versione bianca con «Il tuo sito, le tue prenotazioni»; nel pannello («Hai già un sito per la tua struttura?») e in Impostazioni della struttura (le strisce «Lo miglioriamo noi» e «Crealo con noi») la versione senza frase, che a quell'altezza resta leggibile.
- I file sono in `assets/myhouse/` (`myhouse-logo-bianco.png`, `myhouse-logo-nero.png`, `myhouse-logo-bianco-breve.png`, `myhouse-logo-nero-breve.png`) e ora hanno `?v=` nell'indirizzo, così il browser non tiene in memoria il logo vecchio.

## v2 · I bottoni «Email» funzionano anche senza programma di posta (15 ottobre 2026)

Nessuna migrazione.

- **Indirizzo:** l'email di serie per assistenza, suggerimenti e preventivi è `assistenza@welcomemyhouse.it` (prima `info@welcomemyhouse.it`). Vale per tutti i link `mailto:` del sito e del pannello, il piede del sito e delle email, /domande, llms.txt. Se in Amministrazione → Impostazioni → Sito (o in `MHW_CONTACT_EMAIL`) c'è un altro indirizzo, vince quello: sul sito pubblicato c'è già `assistenza@welcomemyhouse.it`.
- **Perché sembravano rotti:** i link erano giusti e niente li bloccava, ma su un computer senza un programma di posta impostato (per esempio Chrome su Windows con Gmail nel browser) il clic su un `mailto:` non apre niente. Ora il link resta `mailto:assistenza@welcomemyhouse.it` (sul telefono e con Outlook o Mail si apre come prima) e in più, al clic, compare un riquadro in basso con «Scrivi con Gmail», «Scrivi con Outlook» e l'indirizzo, già copiato negli appunti; oggetto e messaggio, dove ci sono (preventivo, «Anche il tuo sito»), passano anche a Gmail e Outlook. Si chiude con × o Esc, da solo dopo 15 secondi. In italiano e, su /en, in inglese. Vale anche nel pannello.

## v2 · Diagnostica: config.php e l'applicazione fuori dalla cartella pubblica (15 ottobre 2026)

Nessuna migrazione.

- **«config.php non leggibile»:** il controllo contava i byte della risposta senza guardare il codice, così la pagina d'errore del server (403 o 404, cioè file protetto) risultava «contenuto». Ora è «NO» solo con una risposta 200 che contiene testo, e dice il codice ricevuto.
- **config.php** aperto direttamente dal web risponde 404, vuoto, anche dove `.htaccess` non è letto.
- **«Applicazione fuori dalla cartella pubblica»:** il messaggio rimanda ai passi in DA-CONFIGURARE (sezione 1b). Verificato che la disposizione con l'applicazione sopra la cartella pubblica si installa e funziona.

## v2 · La lingua principale spiegata ovunque (15 ottobre 2026)

Migrazione `041`: nelle schede di Plus e Portfolio «Scrivi in italiano, traduciamo noi in inglese, francese, tedesco e spagnolo…» diventa «Scrivi nella tua lingua, traduciamo noi nelle altre…» (solo dove c'è ancora il testo di serie).

- **Pannello:** il riquadro «Scrivi la guida in … : ecco come funziona» nella pagina Lingue, per chi non scrive in italiano, e accanto a «In che lingua scrivi la guida?» (chiuso, «Scrivi in un'altra lingua? Ecco come funziona», per chi scrive in italiano). Dice, secondo la lingua e il piano: dove restano i testi, quali altre lingue si hanno (Essential: la sua e una seconda; Plus e Portfolio: lingua di base o lingua in più), da dove partono le traduzioni suggerite o che si scrivono a mano, se i bottoni della guida sono tradotti o in inglese, che il pannello resta in italiano.
- **Home:** «nella lingua dell'ospite», «scegli la lingua in cui scrivi (non per forza l'italiano)» e «con Plus le traduzioni te le suggeriamo noi, partendo dalla tua lingua»; lo stesso in /en.
- **Domande:** «Posso scrivere la guida in un'altra lingua?» spiega tutto questo, in italiano e in inglese.

## v2 · Il portoghese del Portogallo e del Brasile (15 ottobre 2026)

Migrazione `040` (nessuna colonna nuova): le schede dei piani di serie passano da «una a scelta tra 9» a «tra 10».

- **Due varianti:** «Português (Portugal)» (codice `pt`, quello che c'era: i testi della guida erano già del Portogallo) e «Português (Brasil)» (`pt-BR`, nuovo, con i bottoni, le categorie, il riscaldamento e gli eventi scritti alla brasiliana). Le lingue in più sono dieci.
- **Amazon Translate:** per il Portogallo ora riceve `pt-PT` (prima riceveva `pt`, cioè il brasiliano: i bottoni erano europei e le traduzioni suggerite brasiliane); per il Brasile `pt`.
- **La lingua del telefono:** si legge la variante intera (`pt-BR`, `pt-PT`), poi la lingua (`pt`), poi l'altra variante della stessa lingua; vale per la lingua con cui si apre la guida e per quella in cima alla finestra «Scegli la lingua».
- **Testi:** domande frequenti (italiano e inglese), schede e confronto dei piani, finestra di Plus, llms.txt, novità, Marketing.
- **Prove:** ognuna delle dieci lingue opzionali come lingua principale, con Essential (nessuna chiamata ad Amazon) e con Plus (suggerite dalla lingua principale), più le due varianti del portoghese nella guida e al telefono.

## v2 · 7F clienti di prova, Marketing, SEO e GEO, pagine per tipologia (15 ottobre 2026)

Migrazione `039` (solo aggiunte): `accounts.is_test`.

- **Cliente di prova:** interruttore nella scheda cliente. Chi è «di prova» non entra in Quadro, Vendite per piano, Prospetti, Scadenze e Marketing, non riceve richiami automatici né la richiesta di giudizio (il promemoria mandato a mano dall'amministrazione parte lo stesso); etichetta «Prova» in Clienti, Guide e Abbonamenti.
- **Abbonamento manuale:** scelte rapide «Gratis 12 mesi», «Gratis per sempre» (100 anni, motivo obbligatorio come prima) o un numero di mesi; il messaggio dice il nome del piano. In Abbonamenti il filtro «Gratuiti» (manuali e dimostrativi) e «per sempre» al posto della data.
- **Vendite → Marketing:** solo lettura, periodo 30 / 90 / 365 giorni / tutto, clienti di esempio e di prova esclusi. Guide per tipologia, piano, regione (dal CAP se non è scritta) e le 10 città; il percorso registrati → email confermata → guida iniziata → pubblicata → pagato con le percentuali, e «Cosa ti ha fermato?»; sezioni più usate e lingue attive delle guide pubblicate; le 20 guide più visitate (aperture e QR) con il link alla scheda cliente; inviti mandati, amici registrati e amici che hanno pagato. Numeri, percentuali e una barra in CSS; «Scarica CSV» per ogni tabella.
- **File veri per motori e assistenti:** robots.txt, sitemap.xml e llms.txt si scrivono anche nella cartella principale del sito (accanto a index.php) a ogni salvataggio di SEO e GEO, a ogni cambio dei piani, delle testimonianze e una volta al giorno dal giro dei richiami (questo solo con il dominio o l'indirizzo pubblico impostati). Se la cartella non è scrivibile lo dicono SEO e GEO e la Diagnostica. La prova degli indirizzi puliti ora chiede `/indirizzi-puliti` (robots.txt può essere un file vero e risponderebbe sempre).
- **Mai index.php** in canonical, og:url, sitemap, robots.txt, llms.txt e dati strutturati con gli indirizzi puliti accesi, anche se il dominio salvato lo contiene.
- **Quattro pagine nuove** (solo in italiano, indicizzabili, in sitemap e in llms.txt): `/case-vacanza`, `/bed-and-breakfast`, `/affittacamere`, `/agriturismi`. Ognuna con il suo titolo, tre problemi tipici con la risposta della guida, le sezioni più utili, il piano consigliato con il prezzo del listino, quattro domande (anche come FAQPage), «Crea gratis la tua guida» e «Guarda la demo». Titoli e descrizioni in SEO e GEO; nel piè di pagina «Per chi è». Testi in `app/lang/tipologie.php`.
- **Dati strutturati:** Organization con `sameAs` anche https://blackout.in e `parentOrganization` Blackout Agency; offerte del listino nuovo; nessun aggregateRating. llms.txt con la sezione «Per chi è» e una riga sui giudizi solo quando ce ne sono di pubblicati.
- **Immagini della home:** testo alternativo descrittivo per tutte (telefono, foto delle testimonianze, la foto di «Anche il tuo sito» in fondo), larghezza e altezza, e le schermate del pannello caricate solo quando servono.

## v2 · 8E la home e le domande in inglese (15 ottobre 2026)

Migrazione `038` (solo aggiunte): testi inglesi dei piani (`packages.tagline_en`, `headline_en`, `description_en`, `bullets_en`, `badge_en`, `cta_label_en`) con i valori di serie; tagline di Essential e Plus allineate alle lingue della 8C, solo dove c'era il testo di serie.

- **`/en` e `/en/questions`:** stessa impaginazione e stesse sezioni della home e di /domande, in inglese. Prezzi dal listino con «+ VAT / year» e «about … a month»; Termini e Privacy restano in italiano («Terms (in Italian) · Privacy (in Italian)» nel piede).
- **Interruttore IT / EN** nella barra in alto e nel menu del telefono, solo sulle pagine che esistono nelle due lingue; porta alla stessa pagina nell'altra lingua. `<html lang>`, canonical per lingua, `hreflang` reciproci (`it`, `en`, `x-default` = italiano), `og:locale` `en_GB`; le due pagine inglesi in sitemap e in llms.txt (sezione «English»); dati strutturati con `inLanguage`.
- **Registrazione:** i bottoni inglesi portano a `/registrati?lang=en`, che avvisa che il pannello per ora è in italiano e dà l'email per l'aiuto in inglese.
- **Testi e foto:** linguette Italiano / English; i testi inglesi cambiati si salvano con la chiave `en:` + chiave italiana, i valori di serie stanno in `app/lang/sito-en.php`; l'anteprima dal vivo mostra la lingua scelta; le foto sono le stesse.
- **Domande:** le FAQ inglesi in `app/lang/faq-en.php`, con gli stessi id (le ancore valgono nelle due lingue) e le parti calcolate (prezzi del Portfolio, sconti di «Bring a friend») dal listino.
- **Piani:** in Amministrazione → Piani, sotto ogni pacchetto, «English» con titolo, frase, descrizione, punti, etichetta e bottone; vuoti, /en mostra l'italiano.
- **SEO e GEO:** titolo e descrizione per `/en` e `/en/questions` come le altre pagine.

## v2 · 8D la vetrina Casa Checco completa, in tutte le lingue (15 ottobre 2026)

Nessuna migrazione. Sostituisce la 7E.

- **Tutto compilato:** ogni sezione del catalogo, Riscaldamento e Aria condizionata comprese, con ogni campo (tranne gli orari dell'aria condizionata, che con «la accende l'ospite» non valgono); due parole di benvenuto in 5 lingue, sconto per chi torna (10% con CHECCO10), email per gli ospiti di fantasia, messaggi di benvenuto e di fine soggiorno, portali e Instagram.
- **Tutte le lingue:** `app/tools/vetrina-mancanti.php` crea la vetrina su un database di prova e conta i campi senza traduzione: 0 in inglese, francese, tedesco e spagnolo.
- **Il telefono della home** mostra tutte le sezioni della vetrina pubblicata (titolo, icona, colore), in una griglia che scorre dentro lo schermo con una sfumatura in basso; senza vetrina restano i quattro riquadri.
- **Amministrazione → Clienti:** accanto a «Rifai la vetrina», «Completa le traduzioni della vetrina» scrive solo i campi vuoti nelle altre lingue e ripubblica.

## v2 · 8C la guida dell'ospite: recensioni, lingua, «Stiamo per partire», palette, riscaldamento e aria, lingue (15 ottobre 2026)

Migrazione `037` (solo aggiunte e conversioni): `properties.guest_email`, caratteristica `lingua_extra` (Plus e Portfolio 1), sezioni `clima` divise in `riscaldamento` e `aria`, testi di serie dei piani sulle lingue. Istantanee in formato 8.

- **Pagina delle recensioni** `/g/{slug}/recensione` (il link del messaggio di fine soggiorno): copertina piccola, nome, «Com'è andato il soggiorno?» e 5 stelle, noindex, nelle lingue della guida, anteprima anche nel pannello. **5 stelle:** il grazie, il favore e i bottoni dei portali spuntati dall'host, con la riga sull'app di Booking.com, Airbnb, VRBO ed Expedia. **Da 1 a 4:** nessun link ai portali; «Grazie della sincerità», «Scrivici su WhatsApp» con il messaggio pronto e «Mandaci un'email» se c'è l'«Email per gli ospiti» (campo nuovo in Impostazioni → Chi risponde agli ospiti). Lo stesso blocco nella pagina di saluto, dove compare anche «Seguici» con i social. Il voto resta un conteggio anonimo; tolti «Ti va di raccontarlo…» e «Vuoi dire qualcosa a…».
- **«Stiamo per partire»:** un riquadro largo del colore d'accento, con la valigia, la riga «Cosa fare prima di uscire, e un saluto» e la freccia (5 lingue).
- **Riquadri:** icone da 30 px; i titoli non spezzano le parole col trattino («Check-in & Check-out»), al massimo tre righe, e se sono lunghi scendono fino a 15 px. Verificato senza sovrapposizioni con i titoli tedeschi (`prove/schermate/8c-riquadri-de-390.png`).
- **Palette:** quattro colori ben distinti in ognuna, come da brief; testo chiaro ≥ 4,5:1 sui primi tre, scuro ≥ 5:1 sul quarto (prova automatica che calcola i contrasti).
- **Riscaldamento e Aria condizionata:** due sezioni, ognuna con titolo, introduzione, editor e pagina sua (stato «acceso adesso», orari, consiglio per il risparmio). La migrazione trasforma ogni `clima` in `riscaldamento` (stesso id e testi) e, se l'aria condizionata c'è, crea `aria` subito dopo con i suoi campi e traduzioni; se la struttura è al limite di sezioni del piano nasce disattivata e la novità lo dice. La vecchia `clima` resta nascosta per le istantanee già pubblicate. Vetrina, Copia e catalogo aggiornati.
- **Lingue:** la lingua principale si sceglie tra tutte (di serie italiano). Essential: la principale e una seconda a scelta tra tutte. Plus e Portfolio: la principale, le altre lingue di base e una lingua in più tra Nederlands, Português, Polski, Русский, 中文, 日本語, Svenska, Dansk, Čeština (una principale aggiuntiva occupa quel posto). Interfaccia della guida tradotta in olandese, portoghese e polacco (con categorie, riscaldamento ed eventi); per le altre in inglese. Le regole sono in `Entitlements::regolaLingue` e valgono anche al cambio di piano. Traduzioni suggerite anche verso la lingua in più, con l'interruttore «Traduci anche la lingua aggiuntiva» in Impostazioni → Traduzioni.
- **«Scegli la lingua»:** alla prima apertura di una guida con più lingue, senza `?l=`, una finestra (a tutto schermo sul telefono) con le lingue nel nome nativo e quella del telefono in cima; la scelta si ricorda in `localStorage` per quella guida (niente cookie) e non si richiede più.
- **Testi:** schede dei piani «2 lingue: la tua e una a scelta» e «6 lingue: …» (solo dove c'era il testo di serie), una riga sola per le lingue in «Confronta tutti i piani», FAQ «In quali lingue…» aggiornata e nuova «Posso scrivere la guida in un'altra lingua?», tre novità nel pannello.

## v2 · 8B il pannello: piattaforme, sito, messaggi, anteprima del link, finestra del piano (15 ottobre 2026)

Migrazione `036`: tabella `property_channels` (i link di recensione scritti dagli host diventano canali), `properties.has_site`, `properties.messages`. Istantanee in formato 7.

- **Su quali piattaforme si trova la tua struttura?** Portali da spuntare (Google, Booking.com, Airbnb, VRBO, Expedia: i link li prepara il sito), altri siti con nome e indirizzo, social (Instagram, Facebook, TikTok). Indirizzi di partenza in Amministrazione → Impostazioni → Portali.
- **Hai un sito per prenotare?** Sì/No in Impostazioni e nel riquadro di «Le mie guide», con il percorso a domande della home; con «No» la prenotazione diretta non compare nella guida.
- **QR & Link:** messaggio di benvenuto e di fine soggiorno per lingua, modificabili, con l'anteprima stile WhatsApp; il secondo porta alla pagina delle recensioni.
- **Anteprima del link della guida:** og:title, og:description e `/g/{slug}/anteprima.jpg` (1200×630 dalla copertina).
- **«… è compresa in Plus / Portfolio»:** una finestra al posto degli errori quando si tocca una funzione del piano superiore (foto, sezioni, lingue, firma, strutture); i limiti restano controllati sul server.

## v2 · 8A la home (14 ottobre 2026)

Migrazione `035`: tabella `interessi_sito`; voce su foto e PDF nelle schede dei piani.

- «Il tempo» con le ore e gli euro in evidenza; «Anche il tuo sito» come percorso a domande (Sì / No, cosa ti serve, come sentirci), riusato nel pannello; «Interessati a MyHouse» in Amministrazione → Prospetti; scheda con le stelle nella home; email con il marchio al centro e i social nel piè di pagina.

## v2 · 7D Invita un amico per tutti, anteprima del link, giudizi dei clienti (14 ottobre 2026)

Migrazione `034`.

- «Invita un amico» per ogni abbonamento attivo; pagina `/i/{codice}` con l'immagine di anteprima; giudizi dei clienti con consenso, da approvare in Amministrazione, ritirabili dall'account.

## v2 · Amministrazione → Testi e foto del sito (13 ottobre 2026)

Migrazione `033` (solo aggiunte): tabella `site_texts`.

- **Sistema → Testi e foto:** ogni titolo, paragrafo, bottone e scheda della home (118 testi) e l'intestazione di /domande, raggruppati per blocco nell'ordine della pagina. Ogni campo parte dal testo di serie; «Ripristina il testo di serie» e il badge «cambiato» su quelli modificati; un campo lasciato vuoto torna di serie. Si possono usare `**grassetto**` e `[testo](link)`: tutto il resto viene neutralizzato (niente HTML, niente `javascript:`). I segnaposto ({sezioni}, {demo}, {amico}, {passo}, {max}, {tot}) restano calcolati dal programma.
- **Anteprima dal vivo:** a destra del modulo (da 1180 px di larghezza) c'è la home in un riquadro che si aggiorna mentre scrivi, prima di salvare: testi, grassetto e link, elenchi, foto appena scelte; il blocco che stai modificando scorre in vista e si evidenzia. Due larghezze: Telefono (390 px) e Computer (1280 px, rimpicciolito). I link dentro l'anteprima non portano da nessuna parte. L'anteprima è la home aperta con `?anteprima=testi`, che solo l'amministratore vede con i segnaposto (`data-testo`, `data-foto`); per chiunque altro quell'indirizzo è la home normale.
- **Foto della home:** la schermata nel telefono, le tre scene, le tre schermate di «Come funziona» e lo sfondo di «Anche il tuo sito» si caricano (JPG, PNG, WebP, ridisegnate a 1800 px) e si tolgono («Torna alla foto di serie»); stanno nell'archivio come le foto delle testimonianze. L'immagine di condivisione resta in SEO e GEO.
- **/media:** serve anche le immagini caricate dall'amministrazione (testimonianze, condivisione, foto del sito) con l'archivio su disco: prima rispondevano 404 perché non hanno una riga in `media`.
- Domande e risposte, piani, testimonianze e testi legali restano nelle loro pagine.

## v2 · Correzioni dal controllo del sito (13 ottobre 2026)

Nessuna migrazione.

- **Home:** sottotitolo più immediato («…tutto accessibile tramite QR Code. Senza app da scaricare.»); nelle schede «Per chi è», «sempre aggiornato» sta accanto al QR Code e «Più strutture» comincia con «Gestisci più strutture?»; «Il tempo delle risposte» si intitola «L'accoglienza resta tua. Rispondere sempre alle stesse domande, no.» e il cursore delle comunicazioni necessarie è bianco come la barra in alto (l'arancione è il tempo che la guida ti restituisce); sotto «Una prenotazione diretta in più all'anno…» i due inviti sono detti in una frase sola.
- **Porta un amico:** «Come funziona» apre la domanda «Come funziona «Porta un amico»?» (anche se sta sotto «Altre domande»), non le FAQ in generale.
- **Email di contatto:** accanto ai bottoni «Email» e «Scrivici un'email» l'indirizzo è scritto per esteso: su un computer senza programma di posta il bottone non apre niente, e così si copia.
- **Editor, piano Essential:** nelle Istruzioni (e in ogni elenco di righe con foto) l'aiuto non promette più foto e PDF, e c'è «Vuoi aggiungere foto o PDF? Scopri il piano Plus».
- **Archivio S3:** Impostazioni → Archivio → «Prova» mostra la risposta esatta di Amazon (codice e messaggio) e cosa fare (chiave sconosciuta, segreto, regione, permessi, connessioni in uscita, certificati); la Diagnostica mostra l'ultimo errore S3 registrato, cioè quello che un cliente ha visto come «Archiviazione dei file non disponibile al momento». Ai clienti resta il messaggio generico.
- **Traduzioni:** il messaggio «Il traduttore automatico non ha risposto» dice anche di scrivere da «Aiuto» se si ripete (nel 99% dei casi è una configurazione da sistemare in Impostazioni → Traduzioni → «Prova»).
- Già fatti nella 7C, se il controllo li ha visti sul sito vecchio: il QR da stampare con il logo e l'invito alle recensioni con le stelle.

## v2 · Schede dei piani: prezzo in fondo (12 ottobre 2026)

- Home (Prezzi) e pagina «Scegli il piano»: prezzo e «circa … al mese» in fondo alla scheda, sopra il bottone, separati da una riga, alla stessa altezza nelle tre schede (CSS subgrid; su telefono le schede sono una sotto l'altra). Nel Portfolio il costo per struttura e «Quante strutture vuoi gestire?» stanno subito sopra il prezzo. Nessuna migrazione.

## v2 · 7C la guida e l'editor (11 ottobre 2026)

Migrazione `032` (solo aggiunte): `properties.host_welcome`, `properties.direct_discount`, tabelle `translation_hashes` e `novita_viste`.

- **Chi ti accoglie:** la foto profilo compare in un tondo di 64 px a cavallo dell'angolo della copertina (anche con il logo), con il pallino verde; al tocco un `<dialog>` con la foto intera, «Ciao, sono {nome}», le due parole di benvenuto (campo nuovo in Aspetto, 160 caratteri, una per lingua; vuoto = testo di serie in 5 lingue) e «Scrivi a {nome}» (WhatsApp o telefono). Aiuto in Aspetto: «Una tua foto…». Casa Checco usa già `checco-profilo.jpg`.
- **Recensioni:** in Impostazioni una scheda per piattaforma con «Prova il link» e «Dove trovo il link?». Nel commiato «Com'è andato il soggiorno?» con 5 stelle; dopo il tocco gli stessi bottoni a ogni voto, e da 1 a 3 anche «Vuoi dire qualcosa a {host}? Scrivigli». Se non ci sono link per le recensioni, dopo il voto solo «Grazie del tuo voto!». Il voto è un conteggio anonimo (`rating_N`), in Statistiche «Voti dei tuoi ospiti». Senza JavaScript le stelle ricaricano la pagina.
- **Sconto per chi torna:** aiuto sotto il codice, campo «Sconto», avviso se manca il link, anteprima della scheda; nel commiato «−10% con il codice …» con «Copia il codice» e «Prenota sul nostro sito», in 5 lingue.
- **Foto ingrandibili:** le foto delle sezioni e delle righe si aprono in un `<dialog>` a tutto schermo (dita, rotella, doppio clic; ✕, Esc, tocco fuori), con la lente nell'angolo; copertina, logo, foto profilo e miniature no. PDF: bottone grande «Apri il PDF». JavaScript in `assets/guida.js`.
- **QR da stampare:** PDF e SVG centrati con logo, nome, QR, «Inquadra il QR Code / Scan the QR code» e «La guida della casa… / The house guide…» della stessa grandezza, marchio in basso. Il PNG resta il solo codice. Esempio in `prove/qr-esempio.pdf`.
- **Guida di esempio:** nella barra laterale e «Vedi un esempio» nell'editor di ogni sezione (la vetrina; non compare nella guida che fa essa stessa da esempio).
- **Traduzioni:** «Prova» in Impostazioni traduce «Buongiorno» e mostra il messaggio esatto di AWS con cosa fare; Diagnostica mostra l'esito dell'ultima chiamata. Senza chiavi: «Le traduzioni non sono disponibili in questo momento». «Traduci» campo per campo e «Traduci tutti i campi mancanti (N)» senza dover prima accendere l'interruttore (il primo clic lo accende e fa partire l'anno in omaggio); proposte da approvare come prima. Impronta del testo originale: «Il testo italiano è cambiato» con «Traduci di nuovo». «… da tradurre» sulle sezioni in Contenuti, «Manca in: …» nell'editor, lingue da completare nel passo Pubblica.
- **Novità:** `app/lang/novita.php` e una fascia nel pannello, una per volta, finché non si chiude (per account); i campi nuovi ancora vuoti hanno «Nuovo» in Contenuti e il bordo ocra nell'editor, finché non si compilano o si preme «Ho visto».

## v2 · 7B listino, via le varianti camera, piano prima del pagamento (11 ottobre 2026)

Migrazione `031` (solo valori): Essential con 6 sezioni e foto profilo in tutte le versioni; varianti camera spente ovunque; testi di serie delle schede aggiornati.

- Varianti camera eliminate dal codice; le tabelle restano. I vecchi indirizzi del pannello e i QR delle camere aprono la guida. `app/tools/togli-varianti-stripe.php` toglie la voce dagli abbonamenti Stripe (con `--prova`, conferma, credito, registro).
- Schede: «6 sezioni aggiuntive», «Foto profilo dell'host», «Tutte le {sezioni} sezioni», testi nuovi per foto/PDF e traduzioni; Portfolio oltre 10 «Chiedi un preventivo»; pannello «Chiedi informazioni».
- Home: il riquadro del tempo senza confronto col prezzo, «Crea gratis la tua guida» dopo il blocco scuro, bottoni principali pieni (48 px).
- Registrazione senza piano: «Scegli il tuo piano». Passo Pubblica: «Hai scelto …», «Conferma e paga», «Passa a …» con l'elenco di cosa si perde, dati di fatturazione nella pagina. Account: «Passa a {piano}» o «Chiedi il cambio di piano».
- «MyHouse Pro · da blackout.in» nel pannello; aiuto sotto il Price ID in Amministrazione → Piani.

## v2 · 7A indirizzi, email, registrazione, assistenza (11 ottobre 2026)

Migrazione `030`: tabella `feedback`.

- Impostazioni → Sito (indirizzo pubblico, indirizzi puliti con «Prova», email di contatto `info@welcomemyhouse.it`), riga in Diagnostica.
- Tutte le email con lo stesso aspetto (`Mailer::layout`), un HTML per ognuna in `prove/email/`.
- Registrazione: email ripetuta, password di almeno 10 caratteri e fuori dalle 1.000 più comuni, «Mostra», conferma dell'email prima della guida («Controlla la tua email» con Rimanda e Ho sbagliato indirizzo), aiuto sotto gli errori.
- «Aiuto» nella barra laterale e «Problema / Suggerimento» sotto ogni sezione.
- Richiami nuovi: conferma (24 e 72 ore), pagamento (annullato da 2 ore), parere (14 giorni, «Cosa ti ha fermato?» con risposta a un clic, in scheda cliente e in Prospetti).

## v2 · Correzione: il sito non si apriva dopo l'aggiornamento (10 ottobre 2026)

- **Causa:** la migrazione `028` (listino 2026) apre una transazione dentro quella che il Migrator apre già con `BEGIN IMMEDIATE`. Con PHP 8.4 PDO se ne accorge; con PHP 8.1–8.3 (quello di molti hosting) no, e SQLite rifiuta la seconda: la migrazione falliva a ogni richiesta e ogni pagina mostrava «Qualcosa non ha funzionato». Il database non era rovinato: resta fermo alla 027, con tutti i dati.
- **Correzione:** il Migrator segnala a `Db::tx()` la sua transazione, e `Db::tx()` non ne apre un'altra (`src/Db.php`, `src/Migrator.php`). Nessuna migrazione nuova.
- **Verifica:** sul database del sito (copia) le migrazioni 028 e 029 passano anche simulando PHP prima della 8.4, e i dati restano gli stessi (utenti, strutture, sezioni, foto, abbonamenti). La prova di aggiornamento ora fa girare le migrazioni anche così (`prove/php-vecchio.php`). Prove: 831 superate.

## v2 · 6N correzione: conferma dei codici nella modifica di una variante (10 ottobre 2026)

- Nel modulo «Modifica nome, Wi-Fi e istruzioni» di una variante camera il riquadro «Nella variante ci sono codici di accesso» è aperto, con la casella obbligatoria, quando la variante salvata contiene già un codice: anche senza JavaScript l'host può confermare e salvare. Prima il riquadro partiva sempre chiuso e, senza JavaScript, il server chiedeva una conferma che non si poteva dare.
- **Prove:** 831 in tutto, tutte superate (3 nuove per la 6N).

## v2 · 6M guide fuori dai motori e codici di accesso (9 ottobre 2026)

Nessuna migrazione.

**Le guide non si trovano (cinque livelli)**
- Guide, QR e file di `/media/`: `X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex`, e lo stesso meta nei layout della guida, nell'anteprima e nel pannello.
- robots.txt: per i motori (`*`) le guide **non** sono più in `Disallow` (devono leggere il noindex); per 13 assistenti AI (GPTBot, ClaudeBot, PerplexityBot, CCBot…) sì. Un assistente spento in «SEO e GEO» resta `Disallow: /`.
- Mai in sitemap, llms.txt o dati strutturati (come già era).
- Guide nuove con indirizzo `nome-xxxx` (4 caratteri casuali); vetrine e clienti di esempio come prima. Impostazioni → «Cambia il link della guida», con conferma e riga nel registro: il QR stampato continua a funzionare.
- Indirizzo pubblico del bucket S3 compilato: avviso accanto al campo e in Anomalie.

**Codici di accesso: sconsigliati, non vietati**
- `Sicurezza::codiceAccesso()` (parola di accesso + luogo + 3–8 cifre in 60 caratteri, senza accenti né maiuscole), e la stessa regola in `assets/codici.js`: avviso arancione sotto il campo mentre si scrive, in tutti gli editor e nelle traduzioni. Si salva comunque.
- Alla pubblicazione, se ci sono codici: riquadro nella pagina con i campi e «Correggi», «Ho capito, pubblico a mio rischio», «Torna a correggere» e «Pubblica lo stesso» (attivo solo con la casella). La conferma va nel registro (`guida.codici_confermati`: campi, mai i codici) e vale per quella pubblicazione.
- Varianti camera: stesso riquadro al salvataggio; senza la casella non si salva. Traduzioni suggerite: l'avviso sotto la proposta.
- Amministrazione → Anomalie: «Guide pubblicate con un codice di accesso», con la data della conferma (solo informativo).

**Avvisi, FAQ e Termini**
- Riga fissa in ogni editor di sezione; Check-in & Check-out e Servizi con i testi nuovi; «Chi vede la tua guida» al passo 1, una volta per account; nota accanto a «Pubblica» e in registrazione.
- FAQ: nuova «La mia guida si trova su Google?» e risposta nuova sul codice della porta (sotto «Altre domande» e in `/domande`). Termini § 6: la conferma sotto la propria responsabilità (versione invariata).
- **Prove:** 828 in tutto, tutte superate. La prova di aggiornamento da `f048ec5` passa.

## v2 · 6J rifiniture e 6K «SEO e GEO» (9 ottobre 2026)

Migrazione `029` (tabella `seo_settings`, solo aggiunte; su MySQL la colonna `valore` è `TEXT NOT NULL` senza valore predefinito, che MySQL prima della 8.0.13 non accetta).

**6J · Landing**
- Demo in IT / EN / FR (compaiono solo le lingue che la vetrina ha davvero).
- Tolto il blocco doppione «Il valore non è soltanto nella guida…» e il suo CSS.
- Prezzi prima delle domande (con «Porta un amico», che sta dopo i piani). Menu: Come funziona · Il QR · Prezzi · Domande. «Il sito per la tua struttura» passa nel piè di pagina.
- FAQ: otto in vista, nell'ordine dato; le altre sotto «Altre domande (N)»; sotto, «Tutte le domande». Testi delle risposte invariati.
- Schede dei piani senza titoletto e descrizione. Nei piani con le varianti camera: «Varianti camera per B&B e affittacamere: 15 € + IVA l'anno l'una» (da `Varianti::prezzo()`).
- Fonti della sezione tempo aggiornate. La fascia del codice sconto o dell'invito compare anche in registrazione (stessa vista `_fascia_sconto.php`, dati dalla rotta).

**6J · Pannello**
- L'invito al sito della struttura resta solo in «Le mie guide», con «Non mostrare più» (POST con CSRF, cookie `mhw_no_sito` per 180 giorni).
- La scheda «Varianti camera» compare se la struttura ne ha già, o se sono permesse e la tipologia è B&B, affittacamere, agriturismo o altro.
- «Codice sconto per chi torna (facoltativo)», con l'aiuto «Lo sconto che dai tu ai tuoi ospiti sul tuo sito.». Editor eventi: un solo aiuto, «Facoltativo: il periodo in cui vale.». Demo Casa Lucia: sezione «Wi-Fi».

**6J · Amministrazione e testi legali**
- Menu in tre gruppi: Vendite, Clienti, Sistema.
- Quadro → Listino: solo i piani in vendita, Portfolio «127 € + 70 € dalla 2ª», link «Versioni precedenti e piani nascosti».
- Codici sconto → Anteprima: il Portfolio al prezzo della quantità minima («Portfolio (2 strutture): 197 €»).
- Privacy → Fornitori: Adamo per la fatturazione elettronica. Termini § 4: cambio di piano, codici sconto (solo il primo anno, un solo sconto per abbonamento, mai sommato a quello di un invito), «Invita un amico» (se attivo), varianti camera. Versioni dei documenti invariate.

**6K · SEO e GEO**
- Le FAQ stanno in un posto solo (`src/Faq.php`): landing, nuova pagina `/domande` (tutte aperte, ognuna con il suo `id`), dati strutturati e llms.txt.
- Testa delle pagine (`Seo::head`) nei layout `app` e `bare`: home, domande, termini e privacy con title, description, robots, canonical (solo con il dominio), Open Graph, verifiche Google e Bing (solo home), JSON-LD (Organization, WebSite, SoftwareApplication con un'offerta per piano, FAQPage). Tutte le altre pagine pubbliche hanno `noindex`. I layout `cms`, `full` e `guest` restano com'erano.
- `/robots.txt`, `/sitemap.xml`, `/llms.txt`, generati a ogni richiesta.
- Amministrazione → Sistema → «SEO e GEO»: tre riquadri, controllo ricalcolato a ogni apertura, pagine con contatori e anteprima stile Google, immagine per la condivisione, dati dell'azienda e JSON-LD, dominio, verifiche e sei assistenti AI con l'anteprima di robots.txt, llms.txt. Il salvataggio va nel registro (`seo.save`).
- Corretto: in una tabella che scorre, l'etichetta nascosta «Azioni» allargava la pagina a 390 px (Codici sconto).
- **Prove:** 801 in tutto, tutte superate. La prova di aggiornamento da `f048ec5` passa.

## v2 · Listino 2026, «Ideale per» e Vendite per piano (9 ottobre 2026)

Migrazione `028` (solo aggiunte: versioni nuove dei piani e testi).

- **Listino 2026**, come nel file 6I (nuove versioni, con la stessa logica di «Nuova versione» dell'amministrazione):
  - Essential 97 €, Plus 127 €, Portfolio 127 € + 70 € per ogni struttura in più, da 2 a 10 strutture;
  - le funzioni incluse si copiano dalla versione in vendita;
  - i Price ID di Stripe sono vuoti;
  - chi è già abbonato resta sulla sua versione e rinnova al suo prezzo;
  - chi aveva solo scelto un piano passa alla versione nuova;
  - la migrazione è idempotente: se la versione in vendita ha già questi valori non fa niente;
  - al Portfolio si aggiunge «Più di 10 strutture? Scrivici per un preventivo.».
- **Gli scaglioni del Portfolio escono dal listino** (prezzo unico 70 €, come da file). Restano disponibili da Amministrazione → Piani. Con un solo prezzo, il listino scrive «Dalla 2ª in poi +70 € l'una» e la FAQ «ogni struttura in più costa 70 €, fino a 10 strutture».
- **«Ideale per»** sotto il nome di ogni piano, nel listino della landing e nella scelta del piano:
  - Essential: chi affitta una casa o un appartamento e vuole dare le informazioni essenziali in italiano e inglese;
  - Plus: B&B, case vacanza e agriturismi con ospiti da tutto il mondo;
  - Portfolio: chi gestisce da 2 a 10 strutture.
  - Il testo è nel campo «Ideale per» del pacchetto (prima «Sottotitolo breve», che non compariva).
- **Amministrazione → Vendite per piano** (nuova voce di menu). Per il periodo scelto (3, 6 o 12 mesi):
  - nuovi abbonamenti e il piano più scelto, incasso del periodo, abbonamenti attivi oggi per piano, varianti camera attive;
  - colonne impilate per mese e per piano, con legenda e dettaglio al passaggio o al focus;
  - barre degli abbonamenti attivi (con le strutture del Portfolio) e dell'incasso per piano;
  - ultimi 15 acquisti; tabella dei dati ed esportazione CSV.
  - Colori validati per daltonismo e contrasto, in chiaro e in scuro.
- **Prove:** 775 in tutto. La prova di aggiornamento da `f048ec5` passa con il listino nuovo.

## v2 · Una guida = un'unità ricettiva, varianti camera, Portfolio a scaglioni (9 ottobre 2026)

Migrazioni `026` (scaglioni del Portfolio, etichetta e testi del QR) e `027` (varianti camera). Solo aggiunte, SQLite e MySQL.

- **Foto profilo nella vetrina.** Casa Checco ha come foto profilo un ritaglio della sua porta rossa: Francesco è di fantasia, quindi niente volto. La foto profilo (Plus) ora si vede anche sul bottone «Contatta …» e accanto al primo contatto nel foglio dei contatti. Prima compariva solo, piccolissima, in testata e solo senza logo.
- **Portfolio a scaglioni** (versione nuova, chi ha già un Portfolio resta sulla sua):
  - la 1ª struttura costa come Plus (117 €), la 2ª 60 €;
  - dalla 3ª alla 5ª 50 €, dalla 6ª alla 10ª 40 €;
  - dall'11ª alla 20ª 30 €, oltre la 20ª 25 €.
  - Su Stripe la voce delle strutture aggiuntive è un Price graduato, creato dal sito. In amministrazione gli scaglioni si scrivono una riga per scaglione (`3: 50`).
  - L'aggiunta di una struttura mostra il prezzo del suo scaglione («La 4ª struttura: 50 €»).
- **Quanto costa ogni struttura:**
  - nel listino (home e /piano), la tabella degli scaglioni e «Con N strutture: in media X € a struttura», che segue il numero scelto;
  - in Account, per il cliente Portfolio, il conto struttura per struttura (con il nome di ognuna), la media e quanto costerebbe la prossima;
  - nella FAQ, gli scaglioni in una frase.
- **Una guida = un'unità ricettiva:**
  - **Termini**, § 1: un indirizzo e un CIN per guida. Eccezione per B&B, affittacamere e agriturismi con più camere allo stesso indirizzo. Sospensione dopo avviso se una guida serve più unità. Versione dei Termini `2026-10-08`.
  - **Sezioni**: ogni tipo una volta sola per guida, controllato anche dal server, non solo dal catalogo; sezioni libere al massimo 3, anche copiando da un'altra struttura.
  - **CIN obbligatorio per pubblicare e unico** su tutta la piattaforma (vetrine demo escluse):
    - si salva pulito (maiuscolo, senza spazi né «CIN:»);
    - un CIN già usato da un'altra guida non si salva, e il messaggio spiega le varianti camera e come contattarvi;
    - un CIN incompleto non si pubblica.
    - In Anomalie, le guide online senza CIN e i CIN ripetuti.
  - **FAQ**: «Ho un B&B, un affittacamere o un agriturismo con più camere» (una guida sola, con le varianti) e «Posso mettere più case in una guida sola?» (no: Portfolio).
- **Varianti camera** (Plus e Portfolio, 15 € + IVA l'anno l'una, `MHW_VARIANTE_PREZZO`):
  - Nella guida, «Varianti camera» nel menu. Ogni variante ha:
    - nome, Wi-Fi e password;
    - «come si entra in camera» e una nota, nelle lingue della guida;
    - un QR proprio (PDF, PNG, SVG) e un link `/g/…/c/…`.
  - L'ospite che inquadra il QR della camera vede la stessa guida con:
    - «La tua camera» in cima;
    - il Wi-Fi della camera prima di quelli della casa;
    - nel check-in, «Come entrare in camera».
  - Le varianti si leggono dal vivo: modificarle non richiede di ripubblicare.
  - **Pagamento**: voce «variante camera» nell'abbonamento Stripe. La parte dell'anno che resta si paga subito, e la variante nasce solo se il pagamento riesce. Togliendola, credito sulla prossima fattura, e il suo QR riapre la guida normale.
  - Con un abbonamento dello staff si aggiunge senza pagare.
  - Non si passa a Essential finché ci sono varianti.
  - Il rinnovo previsto in amministrazione le conta.
- **«Consigliato» al posto di «(Il) più scelto»** sull'etichetta di Plus, se c'era.
- **Il QR «non cambia mai, anche se modifichi la guida»** al posto di «QR permanente», nei testi dei piani e nella FAQ, che ricorda anche che resta valido con l'abbonamento attivo.
- **Prove:** 770 in tutto. In più:
  - scaglioni e Price graduato;
  - conto per struttura;
  - sezioni uniche e libere al massimo 3;
  - CIN mancante, ripetuto o incompleto, e la sua anomalia;
  - varianti: aggiunta, QR, guida della camera, seconda variante, pagamento rifiutato, rimozione, blocco del passaggio a Essential;
  - foto profilo della vetrina, Termini e FAQ.
- La prova di aggiornamento da `f048ec5` passa.

## v2 · Home: «Il tempo delle risposte» e vetrina Casa Checco completa (8 ottobre 2026)

Nessuna migrazione.

- **Home, sezione nuova «Il tempo delle risposte»** (`views/pub/_tempo.php`, dal file fornito, copiato così com'è). Prende il posto del blocco «Il tempo che non vedi», nello stesso punto: tra il prodotto e «Come funziona». In `home.php` cambia solo l'include; CSS e JS non si toccano.
  - Racconta i minuti di messaggi di una prenotazione tipo e li separa: quelli che restano dell'host e quelli evitabili con una guida.
  - Il calcolatore lavora su prenotazioni al mese e valore di un'ora. Mostra il tempo risparmiato in un anno, in euro, contro il prezzo del piano di partenza (oggi Essential, 87 € + IVA, letto dal listino).
  - I numeri di mercato citano fonti di terzi (Enso Connect, Guestar/StayReply, Verto AI, Touch Stay). Le cifre sono quelle del file fornito: **non le ho verificate io**.
- **Vetrina «Casa Checco» completata** (`Demo::vetrina`):
  - **Scheda:** CIN di esempio (`IT054001C2DEMO0001`: dice «DEMO», non è un codice vero) e 4 posti letto.
  - **Recensioni e prenotazione diretta:** i link per le recensioni portano alle pagine di Assisi di Google Maps, Booking e Airbnb, perché la casa non esiste. Il codice sconto per la prossima volta è `CHECCO10`. Nessun link a un sito per prenotare: sarebbe il sito di qualcun altro.
  - **Riscaldamento e aria condizionata:**
    - riscaldamento automatico dalle 6:30 alle 9:30 e dalle 17 alle 22:30, a 20 °C, da metà ottobre a metà aprile;
    - condizionatore libero, consigliato a 26 °C;
    - il consiglio per risparmiare energia, e una nota sulle mura in pietra.
  - **Servizi:** al posto delle istruzioni del condizionatore ci sono quelle della macchina del caffè. Una nota rimanda alla sezione nuova.
  - **Tutte le lingue:** tedesco, francese e spagnolo erano quasi vuoti (148–152 campi su 152 da tradurre). Ora tutti i 160 campi sono tradotti in inglese, tedesco, francese e spagnolo: sezioni, righe, luoghi, eventi. Le traduzioni stanno in `lang/vetrina/{en,de,fr,es}.php`, cercate per testo italiano. Se un testo cambia e lì non c'è, quel campo resta «da tradurre», come per ogni cliente. Tedesco e francese danno del Lei, spagnolo del tu, come il resto della guida.
- **Correzione nelle traduzioni suggerite:** approvando la traduzione di un luogo in una lingua che per quel luogo non aveva ancora niente, il testo approvato non veniva salvato. Ora sì (`Traduttore::scrivi`, che diventa pubblico per la vetrina).
- **Prove:** 742 in tutto:
  - la sezione nuova della home;
  - la vetrina con scheda, congedo in tedesco, clima anche in francese, macchina del caffè, luoghi in spagnolo e tedesco, e una traduzione per ogni sezione.

## v2 · Sezione «Riscaldamento e aria condizionata» (8 ottobre 2026)

Nessuna migrazione: è una sezione nuova del catalogo (`clima`), nel gruppo «La casa».

- **Per l'host, due blocchi semplici.** Per ogni impianto si sceglie com'è; poi compaiono solo i campi che servono (con JavaScript; senza, si vedono tutti).
  - **Riscaldamento:** «Si accende da solo, a orari», «Lo accende l'ospite» o «Non c'è». A orari: una riga per ogni fascia (si accende alle / si spegne alle), con i giorni facoltativi; vuoto vale tutti i giorni. Poi la temperatura, il periodo dell'anno e come si usa il termostato.
  - **Aria condizionata:** «L'ospite la accende quando vuole» (con un tasto o il telecomando), «Funziona solo in certi orari» o «Non c'è». Poi la temperatura consigliata e come si accende.
  - **«Mostra il consiglio per risparmiare energia»:** acceso di serie.
- **Per l'ospite, una scheda per impianto:**
  - se va a orari, una pastiglia dice subito «Acceso adesso · fino alle 23:00» oppure «Spento adesso · si accende alle 17:00 / domani alle 6:30», con l'ora italiana;
  - le fasce raggruppate per giorni («tutti i giorni», «lun–ven»), la temperatura («Impostato a 20 °C» o «Temperatura consigliata: 26 °C»), il periodo, i passaggi per usarlo;
  - un riquadro gentile, «Energia: ogni grado conta». Dice che in Italia l'energia costa molto e chiede tre cose: porte e finestre chiuse con il clima acceso, le temperature dell'host (o 20 °C d'inverno e 26 °C d'estate) e di spegnere quando si esce.
- Le frasi della guida sono nelle 5 lingue (`lang/clima/`). La sezione si copia tra strutture del Portfolio come le altre.
- Nell'editor, un campo di sezione può comparire solo con certi valori di una scelta (`solo_con`, come già nelle righe), e un interruttore può nascere acceso (`'acceso' => true`).
- **Prove:** 737 in tutto, con lo stato «acceso adesso» anche per le fasce che passano la mezzanotte.

## v2 · «Invita un amico» nel pannello del cliente (8 ottobre 2026)

Migrazione `025`: tabella `referral_invites` (inviti mandati per email: impronta dell'indirizzo, forma mascherata, data). Solo aggiunte.

- **Si accende da Amministrazione → Impostazioni → «Invita un amico»**: una casella, senza toccare `config.local.php` a mano. «Prova i dati salvati» dice se è acceso e quanti clienti possono invitare.
- **Pagina «Invita un amico» del cliente**, come i programmi di affiliazione:
  - il link e il **codice** ben visibile, con «Copia il link» e «Copia il codice»; WhatsApp ed email dal proprio programma restano;
  - **«Invita per email»**: il cliente scrive fino a 10 indirizzi e un messaggio facoltativo, e il sito manda l'invito a nome suo, con link, codice e il 5% per l'amico. Al massimo 20 al giorno, mai due volte allo stesso indirizzo in 30 giorni; chi è già cliente, il proprio indirizzo e quelli non validi si saltano senza dire quali (per non rivelare chi è cliente);
  - l'elenco degli **inviti mandati per email**, con l'indirizzo mascherato (m•••@gmail.com) e lo stato: invitato, registrato, ha pubblicato. Gli indirizzi non si conservano: solo l'impronta.
- La voce «Invita un amico» c'è per tutti i clienti quando gli inviti sono accesi: chi non può ancora invitare (senza abbonamento, o con un abbonamento dello staff) vede la pagina e il motivo.
- Amministrazione → Inviti: il conto degli inviti per email degli ultimi 30 giorni.
- Impostazioni → Stripe: l'elenco degli eventi del webhook aggiornato (con i due eventi «async» del cambio di piano).
- **Prove:** 730 in tutto.

## v2 · Posta in uscita: accesso SMTP più robusto (8 ottobre 2026)

Nessuna migrazione.

- **«Prova con questi dati»** in Amministrazione → Impostazioni: prova la connessione con i dati scritti nel modulo, **senza salvarli** (serve la tua password, come per salvare). Prima la prova usava solo i dati già salvati: chi scriveva la password e premeva «Prova» senza salvare provava quella vecchia, e riceveva «535 authentication failed». Il pulsante sotto ora si chiama «Prova i dati salvati».
- **Accesso SMTP con LOGIN e PLAIN:** il sito legge i metodi che il server offre; se il primo è rifiutato prova l'altro, una volta. Alcuni server ne accettano uno solo anche se li elencano tutti e due.
- **Errore più chiaro:** dice l'utente usato, quanti caratteri ha la password e i metodi provati (mai la password). Con la password vuota lo dice subito, senza tentare.
- **Prove:** 719 in tutto, con un server SMTP finto (`prove/smtp-finto.php`).

## v2 · Dati di fatturazione allineati su Stripe per Adamo (7 ottobre 2026)

Nessuna migrazione.

- Quando il cliente cambia i dati di fatturazione, il cliente Stripe si aggiorna del tutto: i dati tolti (PEC, codice destinatario, partita IVA passando a persona fisica) si cancellano anche su Stripe. Prima restavano quelli vecchi, e Adamo li avrebbe letti.
- La partita IVA come «tax id» si aggiunge anche se l'azienda la inserisce dopo il primo pagamento, e quella vecchia si toglie se cambia.
- `DA-CONFIGURARE` §4a corretto: l'IVA il sito la applica solo con Stripe Tax; un'aliquota fissa creata a mano su Stripe non viene usata.
- **Prove:** 713 in tutto.

## v2 · Amministrazione: pannello di controllo (7 ottobre 2026)

Migrazione `024`: `accounts.admin_note` e `admin_note_at` (nota interna sul cliente). Solo aggiunte.

- **Quadro:** quattro numeri nuovi (ricavo annuo ricorrente, rinnovi entro 30 giorni con l'incasso atteso, abbonamenti che scadono senza rinnovo, anomalie) e un avviso in cima quando c'è un'anomalia grave.
- **Prospetti** (`/admin/prospetti`): ricavo annuo ricorrente, ricavo a rischio (rinnovo disattivato), rinnovi attesi a 30 e 90 giorni (con sconti inviti e cambi di piano programmati). Gli ultimi 12 mesi in un grafico e in tabella: registrati, nuovi abbonati, incasso da nuovi, da cambi di piano e da rinnovi (letti dalle fatture del webhook), abbonamenti persi. Poi i piani (clienti, strutture, ricavo), la conversione da registrato ad abbonato, «Porta un amico» (invitati, paganti, sconti dati, chi invita di più), i codici sconto usati e il costo stimato delle traduzioni. Esporta in CSV.
- **Anomalie** (`/admin/anomalie`): sedici controlli, dal più grave. Tra questi: pagamenti senza abbonamento, cambi di piano pagati e non applicati, rinnovi falliti, rinnovi senza notizie da Stripe, webhook silenzioso, guide pubblicate ma offline, paganti senza dati di fatturazione, errori del sito negli ultimi 7 giorni, codici sconto e sconti inviti non allineati su Stripe, promemoria automatici fermi, abbonamenti dello staff in scadenza, pagamenti abbandonati, email non confermate. Ogni voce dice cosa fare; in fondo i controlli andati bene.
- **Scadenze** (`/admin/scadenze`): chi si rinnova, chi scade e chi è scaduto da poco (30, 60, 90 o 180 giorni). Per ognuno: importo del rinnovo, ultimo avviso mandato (automatico o a mano) e prossimo automatico. «Manda il promemoria» su una riga o sui clienti scelti; il testo si sceglie da solo (rinnovo, scadenza o «la tua guida è offline»). Esporta in CSV.
- **Promemoria di scadenza automatici** (nuovo tipo `scadenza` nei Richiami): per gli abbonamenti che non si rinnovano da soli (rinnovo disattivato o attivati dallo staff), 30 e 7 giorni prima e il giorno dopo la fine se la guida è offline. Una volta sola ciascuno, con il link «non mandarmene più». Chi l'ha chiesto non riceve nemmeno quelli a mano: l'amministratore lo legge.
- **Clienti:** filtri per stato e per piano, colonna della scadenza, esportazione CSV.
- **Scheda cliente:** nota interna, dati di fatturazione (completi o no), avvisi e promemoria (con «Manda il promemoria adesso» e lo storico delle email), «Porta un amico» (amici, sconto, chi l'ha invitato), traduzioni del mese e omaggio.
- Menu dell'amministrazione riordinato: Quadro, Prospetti, Anomalie, Clienti, Abbonamenti, Scadenze, Guide, Piani, Codici sconto, Inviti, Traduzioni, Testimonianze, Impostazioni, Registro, Diagnostica.
- **Prove:** 711 in tutto. L'aggiornamento da `f048ec5` passa con la `024`.

## v2 · FAQ e «Porta un amico» in landing (7 ottobre 2026)

Nessuna migrazione.

- **FAQ nuove:** «Posso cambiare piano dopo?» (salire: differenza fino al rinnovo; scendere: dal rinnovo, oggi niente) e «Se scendo di piano perdo qualcosa?» (niente si cancella, scegli cosa tenere, annullabile). «Ricevo fattura?» ora distingue azienda e persona fisica.
- **Landing: «Porta un amico».** Dopo i piani, un riquadro ocra: «Più amici porti, meno paghi. Il prossimo anno fino al 50% in meno.», tre passi e una barra di dieci tacche (una per amico, 5% ciascuna). Chi è già dentro va a «Invita un amico», gli altri a creare la guida.
  - Due FAQ: «Come funziona «Porta un amico»?» e «Quando conta un amico, e cosa succede dopo il rinnovo?» (amico che paga, dati di fatturazione diversi, conteggio che riparte dopo il rinnovo, tetto, niente rimborsi).
  - Le percentuali vengono da `Inviti` (5%, 5% per amico, tetto 50%): nessun numero scritto nella pagina.
  - **Compare solo con gli inviti accesi** (`MHW_INVITI=1`): da spenti la landing non promette uno sconto che non c'è.
- **Prove:** 697 in tutto.

## v2 · Cambio di piano e fatturazione per tipo (7 ottobre 2026)

Migrazione `023`: colonne `subscriptions.next_package_version_id`, `next_quantity`, `next_choices`, `next_requested_at` e `orders.kind` (predefinito `new`), `from_package_version_id`, `from_quantity`, `applied_at`. Solo aggiunte, SQLite e MySQL.

- **Account → «Cambia piano»** (`/account/piano`, fase 6H). Una scheda per piano, quella attuale col bordo verde. Su ogni scheda c'è già scritto cosa succede.
  - **Salire** (Essential → Plus, Plus → Portfolio, più strutture): si paga oggi solo la differenza per i giorni che restano fino al rinnovo, minimo 1 €. La conferma mostra il conto (differenza, IVA se calcolata da Stripe, totale) e il prezzo dal rinnovo, poi «Vai al pagamento» porta alla pagina di Stripe. Il piano cambia solo quando il webhook conferma il pagamento; la data di rinnovo non si sposta. Se mancano i dati di fatturazione si passa prima da lì e poi si torna alla conferma.
  - **Scendere** (Plus → Essential, meno strutture): oggi non si paga niente e il cambio vale dal rinnovo. Nella conferma si scelgono le sezioni da tenere e le strutture da archiviare, con l'elenco di quello che non si vedrà più (lingue, foto, foto profilo, statistiche, firma, traduzioni suggerite). Niente si cancella. In Account compare «Dal … passi a …» con «Annulla il cambio».
  - Al rinnovo pagato: strutture archiviate, sezioni oltre il limite spente, lingue non comprese tolte, guide pubblicate ripubblicate, email «Da oggi sei su …».
  - Il − / + delle strutture del Portfolio funziona anche senza JavaScript.
  - Non si può cambiare con un rinnovo non pagato («Prima sistema il pagamento…») o con un abbonamento attivato dallo staff («scrivici»).
- **«Aggiungi una struttura»** del Portfolio usa lo stesso giro: conguaglio sui giorni restanti e pagina di Stripe. Il vecchio modulo «quantità» in Account è sostituito; `/account/strutture` porta alla conferma.
- I link «Scopri Plus» e «Passa a Portfolio» del pannello vanno a `/piano?passa=…`: chi ha già un abbonamento finisce sulla conferma del cambio, gli altri sulla pagina dei piani.
- **Stripe.** I prezzi delle versioni nuove dei piani si creano da soli (`ensurePrices`). Il conguaglio è un Checkout `mode=payment` con fattura; l'abbonamento si aggiorna senza proration di Stripe. Eventi webhook nuovi: `checkout.session.async_payment_succeeded` e `checkout.session.async_payment_failed`. Se il pagamento arriva ma l'aggiornamento su Stripe non riesce, il cambio resta «da completare», si ritenta a ogni giro dei richiami e si vede in Diagnostica.
- **Amministrazione → cliente:** il cambio programmato e l'ultimo cambio fatto. Ogni cambio finisce nel registro (`subscription.change`, `subscription.change_scheduled`).
- **Dati di fatturazione: persona fisica o azienda.** Si sceglie per primo «A chi intestiamo la fattura».
  - Persona fisica: nome e cognome, codice fiscale, indirizzo. Partita IVA, codice SDI e PEC non si vedono e non si salvano; una nota spiega che la fattura arriva nel cassetto fiscale.
  - Azienda o professionista: ragione sociale, partita IVA, codice fiscale facoltativo, SDI o PEC, indirizzo.
  - Il cambio è immediato con JavaScript; senza, la pagina mostra i campi del tipo salvato.
- **Non fatto della 6H:** le foto, i PDF e la foto profilo oltre il nuovo piano restano bloccanti come prima (la 6H proponeva di farli diventare avvisi).
- **Prove:** 695 in tutto (giro Plus ↔ Essential ↔ Portfolio con pagamento, webhook doppio, checkout scaduto, rinnovo non pagato, abbonamento dello staff, fatturazione per tipo). L'aggiornamento da `f048ec5` passa con la `023`.

## v2 · Modulo della struttura in linea (7 ottobre 2026)

- **Impostazioni e configurazione → «La struttura».** CAP, Città, Zona, CIN e Posti letto stanno in una griglia sola a tre colonne. Il CIN occupa le prime due colonne, così i bordi dei campi sono in colonna con quelli sopra.
  - I campi sono allineati in basso anche quando l'aiuto sopra va a capo: prima il campo del CIN stava più in basso di quello dei posti letto.
  - A due colonne (tablet) «Posti letto» va accanto a «Zona» e il CIN sotto, a tutta riga; al telefono uno sotto l'altro.
  - Classi nuove `.campi-allineati` e `.campo-largo`.

## v2 · Copy coerente, patch 2 (7 ottobre 2026)

Nessuna migrazione. Della `copy-coerente-patch-2` era già tutto integrato tranne una cosa:
- **Landing:** accanto a «Guarda la demo» le lingue tornano **IT · EN · DE** (erano IT · EN · FR). Il francese e lo spagnolo restano nel selettore della guida demo.

## v2 · Il sito della struttura (7 ottobre 2026)

Nessuna migrazione.

- **Landing: «Anche il tuo sito».** In fondo alla pagina, dopo «La tua struttura ha tanto da raccontare», a tutta larghezza: la foto di un soggiorno (`assets/foto/sito-800.webp`, `sito-1600.webp`, `sito.jpg`) sotto un velo nero al 60%. La voce «Il tuo sito» è l'ultima del menu. Il titolo è «Meno commissioni ai portali. Più incasso per te. Più ospiti diretti.», seguito da tre riquadri brevi, uno per punto.
  - «Chiedici il tuo sito» porta a https://myhouse.blackout.in (in una scheda nuova); c'è anche «Scrivici un'email», con l'indirizzo di `legal` in `config.php`.
  - Nessun prezzo: si risponde con una proposta.
  - `body` ha `overflow-x: clip`: la fascia a tutta larghezza non fa scorrere la pagina di lato.
- **Pannello: un invito breve.** In «Le mie guide», sotto i numeri, e nella pagina della guida, sotto le sezioni. Riporta il messaggio diretto, una riga di spiegazione e «Scopri come», che porta alla sezione della landing.
  - «Non ora» lo nasconde per 30 giorni, solo in quel browser.
- Icona nuova `percent`. **Prove:** 3 in più, 673 in tutto.

## v2 · Traduzioni suggerite (7 ottobre 2026)

Migrazione `022`: tabelle `translation_suggestions` e `translation_usage`, colonne `properties.translation_suggest`, `accounts.translation_trial_until` e `accounts.translation_trial_by_admin`. Accende la funzione `auto_translation` in tutte le versioni di Plus e Portfolio, anche quelle già vendute; l'etichetta diventa «Traduzioni suggerite»; i testi di Plus e Portfolio dicono l'omaggio, se sono ancora quelli predefiniti. Solo aggiunte.

- **Il traduttore propone, il cliente approva.** Con Plus e Portfolio, nella pagina di ogni lingua c'è «Suggerisci le traduzioni mancanti (N)». Amazon Translate traduce un campo per chiamata; sotto ogni campo vuoto compare «Suggerita: da controllare» con **Approva**, **Modifica** e **Scarta**, e in alto **Approva tutte**.
  - Le suggerite stanno in una tabella a parte: la guida non le vede finché non sono approvate.
  - Si suggerisce solo dove la traduzione manca. L'approvazione non scrive se nel frattempo il campo è stato tradotto a mano.
  - Se il testo originale cambia, la suggerita è «Da rifare», con **Rifai**.
  - «Modifica» porta il testo nel campo, da correggere: il salvataggio automatico lo registra come traduzione del cliente.
  - La nota «Per i testi importanti, falli rileggere a un madrelingua» sta nella pagina della lingua e nella spiegazione.
- **Interruttore per struttura, in Lingue.** La prima accensione nell'account fa partire l'anno in omaggio e mostra la spiegazione in due righe. Finito l'anno non se ne chiedono di nuove; quelle approvate restano. Essential vede la funzione spenta, «con il piano Plus». Nessun prezzo, nessuna promessa oltre l'anno.
- **Tetti e registro.** Ogni chiamata va in `translation_usage`. Tetti al mese: 150.000 caratteri per account e 1.900.000 per il sito. Al tetto le richieste si fermano e il cliente legge perché.
- **Amministrazione → Impostazioni → Traduzioni**: regione (predefinita eu-west-1, controllata), chiavi (vuote = quelle di S3), prezzo, cambio, fine del piano gratuito AWS, tetti, «Prova la connessione».
- **Amministrazione → Traduzioni** (voce nuova): caratteri e costo stimato del mese, ultimi 12 mesi, chi traduce di più, previsione (clienti × caratteri ancora da tradurre, con e senza piano gratuito), anni in omaggio con la data da cambiare, ultime chiamate non riuscite. I costi sono stime. Nel Quadro, un avviso oltre l'80% del tetto del sito. Diagnostica dice se le traduzioni sono collegate.
- **Firma.** SigV4 di `S3Storage::signHeaders`, con il servizio come parametro (`'s3'` resta il predefinito). Testata contro botocore: `prove/firma-translate-botocore.php`, 6 firme su 6 identiche; quelle S3 restano 8 su 8. Un testo oltre i 10.000 byte si spezza ai paragrafi.
- **Testi.** Landing: «Parla la lingua di chi arriva» parla delle suggerite, due FAQ nuove; il confronto dei piani dice «in omaggio per un anno». Lingue non dice più «nessuna traduzione automatica». Termini (paragrafo «Traduzioni suggerite») e informativa (Amazon Translate tra i fornitori), versione 2026-10. `LEGGIMI.md` e `DA-CONFIGURARE.md` (§4c: utente IAM, policy, prova reale).
- **Prove.** Amazon Translate finto (`prove/translate-finto.php`): ricalcola la firma da capo e risponde 403 se è sbagliata. 37 prove nuove (firma, tetti, errori, omaggio, approvazione, guida senza suggerite, Essential, amministrazione): 670 in tutto. La prova di aggiornamento ammette un solo cambio di diritti nelle versioni vendute, `auto_translation` in Plus e Portfolio.

## v2 · «Già in <struttura>» (7 ottobre 2026)

Nessuna migrazione: si legge dalle sezioni che ci sono già.

- **Riusare quello che hai già scritto.** Chi ha almeno due strutture (non archiviate) trova, nell'editor di una sezione, i luoghi e le righe scritti nelle altre: «Già in Casa Checco: + Enoteca Tre Calici · Enoteca». Un tocco e sono qui.
  - Si portano testi, traduzioni (nelle lingue di questa guida), categoria, etichetta e colore. La foto, e il PDF di una riga, si duplicano: le due guide restano indipendenti. Senza foto nel piano, la foto non si porta.
  - I minuti a piedi si ricalcolano dalle coordinate del luogo e della struttura; se mancano restano vuoti. I minuti in auto e le distanze delle righe restano vuoti: dipendono dalla struttura.
  - Mai in Check-in & Check-out, Wi-Fi, Come arrivare e Parcheggio, che sono solo di quella struttura.
  - Non si propone quello che c'è già con lo stesso nome. Con più di 12 luoghi compare un campo per cercarli.
  - Il luogo copiato si apre nel modulo, da controllare; la riga copiata va in fondo all'elenco. Se si stava scrivendo qualcosa, prima si salva.
  - Funziona nella pagina della sezione e nella configurazione. `Suggerimenti.php`, rotta `POST /pannello/{id}/sezioni/{sid}/da-altra`.
- **Prove.** 13 in più (anche: un luogo di un altro account non si copia), 633 in tutto.

## v2 · Form e pannello al telefono (7 ottobre 2026)

Migrazione `021`: la colonna `users.pending_email`, per cambiare l'email dall'account. Solo un'aggiunta.

- **Pannello al telefono e al tablet.**
  - Nelle sezioni con le righe la pagina non è più larga dello schermo. Prima, a 390 px, arrivava fino a 1.266 px (Servizi extra, Muoversi, Come arrivare, Parcheggio, Eventi, Emergenze, Rifiuti, Wi-Fi, Servizi, Impostazioni) e il telefono rimpiccioliva tutto. La causa era il `fieldset` che contiene le righe.
  - Le schede del pannello sono alte 44 px e hanno una sfumatura a destra che dice che si scorrono; quella aperta resta in vista.
  - Bersagli da almeno 44 px: «Sposta su / giù / Togli» delle righe, nomi delle sezioni, «Tutte le sezioni», pillole, passi della configurazione.
  - Una riga aperta non ha più la fascia vuota in cima: la freccia per chiuderla sta accanto ai bottoni.
  - La foto di una riga ha la sua zona tratteggiata, come la foto della sezione.
  - Con la tastiera aperta la barra in basso si toglie di mezzo e non copre il campo.
  - I testi di benvenuto della pagina QR sono a 16 px: iPhone non ingrandisce più la pagina.
- **Scheda del luogo.**
  - Se il salvataggio non riesce, il modulo torna con tutto quello che era scritto e l'errore sta sotto il campo. Prima si perdeva.
  - «Nome» spiega che serve sempre.
  - Un luogo già salvato si salva anche mentre si scrive.
  - I minuti a piedi e in auto si scrivono col tastierino, come nel parcheggio.
- **Campi.**
  - Il numero di emergenza ha il tastierino del telefono.
  - «Per quante notti al massimo» accetta solo cifre.
  - I campi semplici accettano 200 caratteri, come il server: prima la pagina ne lasciava scrivere 300 e il resto si perdeva.
  - Nome, indirizzo e città della struttura non si riempiono più con i dati del cliente.
  - Codice fiscale, SDI, provincia e CIN aprono la tastiera in maiuscolo.
- **Account.**
  - Si cambiano il nome, la password (con quella attuale) e l'email.
  - L'email nuova vale dopo il link di conferma; quella vecchia riceve un avviso.
  - Se l'email di conferma non parte, il pannello lo dice.
- **Fatturazione.** I due testi che dicevano «Le fatture le emette Stripe» ora parlano della fattura elettronica e della ricevuta di Stripe.
- **Password.** Al massimo 72 caratteri, in registrazione, recupero e cambio: oltre, bcrypt le tagliava senza dirlo.
- **Eliminare una struttura.** Il nome scritto per confermare si confronta senza badare a maiuscole e spazi doppi: il telefono mette la maiuscola da solo.
- **Righe.** Nel riassunto di una riga chiusa le date sono scritte per esteso («3 ottobre 2027»).
- **Telefoni col prefisso.**
  - Toccando un campo telefono vuoto nel pannello compare «+39 »; se resta solo il prefisso, il campo torna vuoto.
  - Al salvataggio un numero italiano senza prefisso (cellulare con 3, fisso con 0) prende +39, e 0039 diventa +39.
  - I numeri brevi e speciali restano come sono: 112, 113, 1522, 800….
  - Nella guida i link «Chiama» e WhatsApp aggiungono il prefisso anche ai numeri salvati prima.
- **Prove.** Le prove automatiche sono riallineate al copy nuovo e ce ne sono 18 in più: 620 in tutto.

## v2 · Copy coerente e landing ampliata (6 ottobre 2026)

Migrazione `020`, solo testi: etichette delle funzioni e testi dei piani, cambiati solo dove erano ancora quelli predefiniti. Nessuna tabella nuova, nessuna impostazione nuova. Il dettaglio, con il glossario e le tabelle «prima → dopo», è in `RAPPORTO-COPY.md`.

- **Una parola per ogni cosa.** «Struttura» (non «casa») nel pannello; «piano» (non «pacchetto») anche in amministrazione, dove la voce «Pacchetti» diventa **Piani**; «foto» (non «immagine») per copertina, sezioni, luoghi e profilo; «QR» nel pannello e «QR Code» sulla landing; «Togli» per levare qualcosa, «Elimina» per cancellarlo; «conferma» dell'email (non «verifica»); «Apri l'anteprima»; «Mandami di nuovo l'email».
- **Check-in & Check-out** è il nome della sezione anche nella procedura (prima il passo si chiamava «Arrivo e partenza»). L'ultimo passo si chiama **Pubblica**; la procedura, dappertutto, «configurazione».
- **Sezioni.** Ogni sezione ha una spiegazione sotto il titolo, scritta sempre allo stesso modo (a cosa serve all'ospite, poi come compilarla o cosa va altrove), e una riga di presentazione nel catalogo «Aggiungi una sezione». `SectionCatalog`: chiavi `breve` e `intro`, funzioni `breve()` e `facoltativo()`.
- **Campi.** «(facoltativo)» sta accanto all'etichetta e l'aiuto resta per l'esempio (prima era la prima parola dell'aiuto); «Una riga per…», «Una voce per riga» dappertutto. Le regole della casa si scelgono con le frasi che l'ospite leggerà («Si può fumare» / «Vietato fumare»). In Aspetto, «Testo scuro / chiaro» diventa **Tema chiaro / scuro**. Nei luoghi, «In evidenza» diventa **Etichetta**.
- **Errori corretti.** «Il cifra lo cambi…» e «Cambia il cifra di strutture»; «Non vuoi più ricevere il avviso…» nelle email; «compresa nel piano Plus» detto del logo; chi ha Portfolio non vede più «Scopri Portfolio» quando ha finito le strutture; «Tradotta automaticamente» accanto a «nessuna traduzione automatica».
- **Amministrazione.** Stati di ordini e abbonamenti in italiano (`_stati.php`); date dei codici sconto scritte per esteso; «Archivio di foto e PDF».
- **Landing.** Blocco nuovo **Per chi è** (casa vacanza, B&B e affittacamere, agriturismo, più strutture); terzo vantaggio sulle lingue e fascia del prezzo a tutta larghezza, con l'equivalente al mese; undici domande invece di sei (provarla prima di pagare, tempo, lingue, più strutture, codici della porta); «senza carta di credito» nella chiusura; «Prezzi» nel menu; descrizione per i motori di ricerca riscritta. Nessun numero e nessuna testimonianza inventati.
- **Immagini.** `scena-host.jpg` (e le due WebP): tolto il codice della cassetta delle chiavi che si leggeva sullo schermo. `pannello-1…3.webp` rifatte con i caratteri veri del sito e i testi nuovi; il QR della terza porta alla demo.

## v2 · Invita un amico (6 ottobre 2026)

Migrazione `019`: la tabella `referrals`, le colonne `accounts.referral_code` e `accounts.referral_applied_percent`, la colonna `discount_codes.sistema`. Nuova classe `Inviti`. **Spento finché non lo accendi** con `MHW_INVITI=1`.

- **La regola.** Chi ha un abbonamento Stripe attivo ha un link personale (`/i/CODICE`). Chi si registra da lì ha il 5% sul primo anno. Chi ha invitato ha il 5% in meno sul prossimo rinnovo per ogni amico che paga, fino al 50% (10 amici). Dall'undicesimo l'amico ha comunque il suo 5%. Le tre percentuali sono costanti in `Inviti`.
- **Per chi invita.**
  - Il riquadro con la barra a dieci tacche, il prezzo del prossimo rinnovo e il link con «Copia», «WhatsApp» ed «Email»: in «Le mie guide», dopo «La tua guida è online» e nella pagina nuova **Invita un amico** (voce nel menu).
  - In «Account & Fatturazione» compare lo sconto inviti sul prossimo rinnovo.
  - Un'email per ogni amico che pubblica, e una riga in più nell'avviso dei 30 giorni prima del rinnovo.
- **Per l'amico.** La landing aperta dal link dice chi lo ha invitato. Dopo la registrazione lo sconto è già applicato («Invito di Lucia»). Il codice di invito si può anche scrivere dove si mette il codice sconto. Non si somma a un altro codice.
- **Stripe.**
  - L'amico: una riga «di sistema» in `discount_codes`, quindi lo stesso percorso dei codici sconto.
  - Chi invita: un coupon «una volta» (`mhw-invito-5` … `mhw-invito-50`) sull'abbonamento, sostituito a ogni amico in più. Sconta il rinnovo e Stripe lo toglie da solo.
  - Il rinnovo scontato chiude gli inviti e il conto riparte da zero. Se una fattura di conguaglio consuma il coupon, torna sull'abbonamento.
  - Se Stripe non risponde il webhook passa lo stesso e il giro dei richiami riallinea.
  - Nessun evento nuovo da aggiungere al webhook.
- **Amministrazione → Inviti.** Chi ha invitato chi, lo stato di ogni invito e «Annulla». La riga di sistema non compare tra i codici sconto.
- **Regolamento.** Un testo di partenza al punto 10 dei Termini, visibile solo con gli inviti accesi: va fatto rivedere.

## v2 · Casa Checco si crea da sola (6 ottobre 2026)

Nessuna migrazione.

- **Vetrina automatica.** Alla prima apertura della landing o del Quadro dopo l'aggiornamento, Casa Checco si crea da sola nell'account `blackout.agency@gmail.com`:
  - l'account si cambia con `MHW_VETRINA_EMAIL`; vuoto = mai;
  - con Plus dimostrativo per 12 mesi se l'account non ha un piano;
  - una volta sola: l'esito resta in `app/storage/vetrina-automatica.txt`;
  - una vetrina che c'è già non si tocca;
  - se la creazione si interrompe, quello che è stato creato a metà si toglie.
- **«Rifai la vetrina».** Nuovo pulsante in Amministrazione → cliente: toglie la vetrina vecchia (con le sue foto) e ne crea una nuova con i dati aggiornati. Le altre strutture dell'account non si toccano.

## v2 · Home con Casa Checco (6 ottobre 2026)

Nessuna migrazione.

- **Telefono della home.** Quando la demo è la vetrina, il telefono mostra Casa Checco: nome, foto (`assets/foto/checco-telefono.jpg` e `checco-telefono-600.webp`) e orario del check-in presi dalla demo. Senza vetrina resta Casa Lucia.
- **«Inizia in pochi minuti».** Le tre schermate del pannello (`pannello-1…3.webp`) sono rifatte dal pannello di Casa Checco: Contenuti, Aspetto con l'anteprima, QR & Link.

## v2 · Amministrazione → Impostazioni: Stripe, posta e foto dal pannello (6 ottobre 2026)

Nessuna migrazione.

- **Nuova pagina Impostazioni** (menu dell'amministrazione), con tre riquadri: Stripe, Posta in uscita, Archivio delle foto.
  - **Stripe:** chiave segreta, segreto del webhook, Stripe Tax. C'è anche l'indirizzo del webhook da copiare, con l'elenco degli eventi da selezionare.
  - **Posta:** SMTP, `mail()` o «non spedire»; server, porta, cifratura, utente, password, mittente.
  - **Archivio:** disco o Amazon S3; regione, bucket, chiavi, indirizzo pubblico.
- **Dove si salvano.** I valori vanno in `app/config.local.php`, il file che l'app già leggeva. È scritto con `var_export`, quindi un testo con apici resta testo. La versione precedente resta in `config.local.bak.php`, escluso dal repository e dal pacchetto.
- **Sicurezza.**
  - Per salvare serve la password dell'amministratore (al massimo 10 tentativi ogni 15 minuti).
  - I segreti non si rimostrano: si vede solo come finiscono. Un campo lasciato vuoto li conserva, «Togli» li cancella.
  - I campi impostati da variabili d'ambiente sono bloccati.
  - Nel registro va solo quale gruppo è stato salvato, mai i valori.
- **Controlli.** Ogni campo viene controllato prima del salvataggio, insieme alle regole tra campi:
  - niente SMTP senza server e mittente;
  - niente S3 senza regione, bucket e chiavi;
  - niente chiave Stripe senza segreto del webhook.
- **«Prova la connessione».**
  - Stripe: chiede il saldo e indica se la chiave è reale o di prova.
  - Posta: manda un'email di prova all'amministratore.
  - S3: scrive, legge e cancella un piccolo file.
- **Posta: errori spiegati.** Se l'email di prova non parte, «Prova la connessione» mostra la risposta del server di posta e cosa controllare:
  - utente o password rifiutati (535);
  - server o porta irraggiungibili;
  - cifratura (meglio SSL sulla 465);
  - mittente rifiutato.
- **Posta: connessione cifrata.** STARTTLS ora chiede esplicitamente TLS 1.2 o 1.3.
- **Niente riempimento automatico.** Il browser non riempie più da solo i campi delle impostazioni con le credenziali del pannello.
- **Quadro.** Gli avvisi «Stripe non è configurato», «La posta non parte» e «I media stanno sul disco» hanno ora il pulsante «Imposta ora».

## v2 · Vetrina «Casa Checco» con i dati veri di Assisi (6 ottobre 2026)

Nessuna migrazione. La casa, il vicolo, i padroni di casa, i telefoni (075 000 …) e i locali di «Dove mangiare» e «Negozi» restano di fantasia. Tutto il resto viene dalle fonti pubbliche del 2026:

- **Eventi.** Niente più eventi di fantasia. Ci sono il mercato del sabato in Piazza Matteotti (8–13) e le feste che tornano ogni anno, ognuna con la prossima data a partire dal giorno in cui si crea la vetrina:
  - Calendimaggio, dal primo mercoledì di maggio al sabato;
  - Festa del Perdono, 1–2 agosto;
  - Santa Chiara, 11 agosto;
  - San Rufino, 11–12 agosto;
  - Festa di San Francesco, 3–4 ottobre.
- **Locandina.** Ora è quella che la casa ha preparato per il mercato del sabato.
- **Imposta di soggiorno.** Tariffe 2026 per le locazioni turistiche: 3, 4 o 6 € a notte secondo il prezzo, solo le prime 3 notti, esenti i bambini sotto i 12 anni.
- **Parcheggi.** Tutti a 2 € l'ora e 14 € al giorno: Matteotti (390 posti), Mojano (con le scale mobili per Santa Chiara) e Porta Nuova. Gratuito: San Giacomo. ZTL: solo carico e scarico, al massimo 60 minuti, con il permesso.
- **Muoversi e arrivare.** Linea C di Busitalia dalla stazione al capolinea di Piazza Matteotti (1,30 € in tabaccheria, 1,50 € a bordo). Scale mobili di Mojano. Radio Taxi Assisi.
- **Cosa visitare.** 10 luoghi veri, con orari e prezzi:
  - anfiteatro romano, Cattedrale di San Rufino, Museo Diocesano, Santa Chiara, Piazza del Comune;
  - Foro Romano (5 €), Rocca Maggiore (8 €; cumulativo 10 €);
  - Basilica di San Francesco, San Damiano, Santa Maria degli Angeli.
- **Cosa fare.** Sentiero 350 per l'Eremo delle Carceri, Bosco di San Francesco del FAI, prati del Subasio, ciclovia Assisi–Spoleto.
- **Luoghi.** 23 in tutto, con le descrizioni anche in inglese.

## v2 · Fatture con Adamo dal collegamento Stripe, vetrina «Casa Checco» ad Assisi (6 ottobre 2026)

Nessuna migrazione.

- **Adamo.** Le fatture le crea Adamo dal suo collegamento con Stripe (Impostazioni → Integrazioni → Stripe), senza token: la fase 6F non serve.
  - Il cliente Stripe porta ora i metadati che Adamo legge: `Fiscal_code` (per un'azienda senza codice fiscale, la partita IVA), `Pec` e `Fe_code` (il codice destinatario, oppure `0000000`).
  - La partita IVA di un'azienda va anche come «tax id» del cliente.
- **Vetrina «Casa Checco».** «Crea la guida vetrina» crea ora «Casa Checco», ad Assisi, in un vicolo di fantasia vicino a Piazza Matteotti. La struttura è geolocalizzata sulla piazza (43.07025, 12.61966, da OpenStreetMap), con il link di Maps e le coordinate; luoghi, parcheggi e indicazioni sono quelli di Assisi.
  - Tutte le 17 sezioni sono compilate, comprese le nuove: eventi di fantasia con una locandina disegnata, sezione libera «La storia della casa», parcheggi con prezzi e minuti a piedi, muoversi in zona, servizi extra con unità, due reti Wi-Fi, istruzioni, imposta di soggiorno, 16 luoghi.
  - Le foto sono nuove versioni di quelle della demo: specchiate, ritagliate e con un'altra tonalità (`assets/foto/checco-*.jpg`).

## v2 · Fase 6E — Codici sconto per il primo anno, con Stripe (6 ottobre 2026)

Migrazione `018`: le tabelle `discount_codes` e `discount_redemptions`, la colonna `accounts.intended_discount_code_id` e le colonne `orders.discount_code_id` e `orders.discount_cents`. Nuova classe `Sconti`.

- **Amministrazione → Codici sconto.**
  - Il modulo ha codice (con «Genera»), percentuale o importo, validità, utilizzi massimi, piani e nota, con l'anteprima del prezzo per ogni piano.
  - Ogni codice diventa un coupon Stripe «una volta», che sconta solo il primo anno.
  - L'elenco mostra stato, utilizzi e «Copia il link»; dal dettaglio si vede chi l'ha usato. Disattivazione e creazione vanno nel registro.
- **Per l'host.**
  - «Hai un codice sconto?» compare nel riquadro del piano, al passo «Pubblica» e su `/piano`. Mostra il prezzo barrato e lo sconto del primo anno, oppure il motivo per cui il codice non vale.
  - Il codice si ricontrolla alla pubblicazione. Lo sconto vero lo dice Stripe e si conta una volta sola anche se il webhook arriva due volte.
- **Link con il codice.** `/?codice=…` mostra una fascia in home e applica il codice dopo la registrazione. In «Account & Fatturazione» compare lo sconto avuto e la data del rinnovo a prezzo pieno.

## v2 · Fase 6G — Sezione «Eventi» con locandina, calendario e promemoria (5 ottobre 2026)

Nessuna migrazione. Nuovi file: `app/src/Eventi.php` (la logica delle date, già pronta) e `app/lang/eventi/` (5 lingue).

- **Nuova sezione `events`.**
  - Ogni evento ha nome, categoria, «Quando» a pillole (un giorno, più giorni, ogni settimana, altro), date, orari, luogo con i minuti, prezzo, sito e descrizione.
  - La locandina è una zona sola, per un'immagine o un PDF.
  - I campi si mostrano e si nascondono secondo «Quando» e «Prezzo». Con «Un giorno», «Dal» diventa «Giorno».
- **Pannello.**
  - Ogni evento ha il suo stato: In corso, Tra N giorni, Ricorrente, Passato: nascosto, Manca la data.
  - Per un evento passato che torna ogni anno c'è «Ripeti nel 2027»: sposta le date e toglie la locandina.
  - Una volta al mese al massimo, un'email ricorda gli eventi passati (si può disattivare).
- **Guida.**
  - Gli eventi si dividono in gruppi «In questi giorni», «Più avanti» e «Ogni settimana», con i filtri per categoria. Quelli passati spariscono da soli: le date si guardano quando la pagina si apre.
  - Ogni scheda si apre e mostra locandina, Indicazioni, «Aggiungi al calendario» (file `.ics`) e il sito.
  - In home c'è la fascia scura «Oggi» se c'è un evento oggi. La casella Eventi compare solo se c'è qualcosa nei prossimi 60 giorni, con il numero di quelli in questi giorni.
  - La demo Casa Lucia ha tre eventi di esempio.

## v2 · Fase 6D — Sezione libera, righe compresse, messaggio di benvenuto (5 ottobre 2026)

Nessuna migrazione.

- **S1 · Sezione libera (`custom`).**
  - Contiene il titolo scelto dall'host, un'icona fra 12, testo, elenco, foto e PDF.
  - Si aggiunge più volte: il catalogo la propone sempre.
  - Si copia tra strutture, con una casella sola per tutte le sezioni libere.
  - Nella home pubblica non si conta fra le sezioni pronte.
  - Senza titolo, la guida mostra «Altre informazioni».
- **X2 · Righe compresse.**
  - In tutti i ripetitori le righe salvate si mostrano su una linea di riepilogo, che non mostra mai la password del Wi-Fi. Esempio: «Lucia, per i problemi in casa · +39 0742 000000 · Dalle 8 alle 22».
  - Si aprono con un clic; la riga nuova nasce aperta.
  - Un campo non valido apre la sua riga. Senza JavaScript le righe restano aperte.
- **M2 · Messaggio di benvenuto in «QR & Link».**
  - Una versione per ogni lingua della guida, con il link che apre la guida in quella lingua.
  - Si può ritoccare prima di usarlo, con «Copia» e «Apri WhatsApp».
  - Va bene per WhatsApp, Airbnb e Booking.
- **Parole nuove da far rileggere.**
  - `kind.custom`: «Altre informazioni» / «More information» / «Autres informations» / «Weitere Informationen» / «Más información».
  - `welcome_message`: il messaggio di benvenuto nelle 5 lingue, in `app/lang/*.php`.

## v2 · Sito: chi è già registrato lo vede subito; guida vetrina (5 ottobre 2026)

Nessuna migrazione.

- **Testata del sito.**
  - Chi è dentro vede la sua iniziale e il nome. Si apre un menu con l'indirizzo email, «Le mie guide», «Account & Fatturazione» ed «Esci»; si chiude toccando fuori o con Esc.
  - Sul telefono l'iniziale resta in testata. Chi è fuori ha «Accedi» in testata, non solo nel menu.
- **Home per chi è registrato.**
  - In cima c'è la fascia «Ciao, Nome.», con l'indirizzo con cui sei dentro.
  - Mostra fino a tre guide con lo stato vero (Online, Offline, Bozza) e «Vai alle tue guide». Senza guide propone «Crea la tua prima guida».
  - I pulsanti dell'hero e della chiusura diventano «Vai alle tue guide».
- **Guida vetrina.**
  - In Amministrazione → cliente, «Crea la guida vetrina» crea «Casa dei Gerani», una demo completa nell'account di un cliente vero, con dati di fantasia diversi da Casa Lucia.
  - Si può concedere insieme Plus dimostrativo per 12 mesi.
  - La vetrina è online come demo, non occupa il posto di una struttura del piano e diventa la demo della landing.

## v2 · Fase 6C — Parcheggio, prezzi, «Muoversi in zona», home con tutte le sezioni (3 ottobre 2026)

Migrazione `017`. Converte i dati esistenti; i vecchi campi restano nel JSON. Le guide già pubblicate si leggono nel formato 6 (`Guide::FORMAT`).

- **Importi in euro (`money`).** Si scrive solo il numero, con «€» fisso a destra. Accetta virgola o punto; si salva con la virgola («1,50») ed è uguale in ogni lingua.
- **Parcheggio.**
  - Il tipo si sceglie a pillole.
  - Campi: «Nome o descrizione», indirizzo, link a Maps, «All'ora», «Al giorno», «Nota sul costo» (tradotta), «Minuti a piedi».
  - Con «Privato» o «Pubblico gratuito» i costi spariscono e si svuotano.
  - Nella guida ogni parcheggio è una scheda con le pillole «€/ora», «€/giorno», «min a piedi» (o «Gratuito») e «Apri Maps».
- **Servizi extra.** Il prezzo diventa importo e unità (a persona, a tratta, a notte…), più «Nota sul prezzo». Nella guida si legge «25 € · a tratta», con l'unità tradotta.
- **«Come arrivare» e «Muoversi in zona».**
  - Ognuna ha un riquadro introduttivo nell'editor, che rimanda all'altra.
  - Nella guida ognuna ha una riga sotto il titolo.
  - «Muoversi in zona» è a schede: tipo a pillole, nome, telefono, sito, «Dove si prende», «Orari, biglietti, costi». Ci sono le righe pronte Taxi, Autobus e Noleggio bici. Nella guida ogni scheda ha «Chiama» e «Visita il sito».
- **Conversione (017).**
  - «5 € al giorno» e «€1,50/h» diventano importi; il resto del testo va nella nota.
  - «25 € a tratta» diventa importo e unità.
  - Il vecchio elenco dei trasporti diventa schede di tipo «Altro».
  - Quello che non si riconosce resta intero nella nota, in ogni lingua.
- **Home.**
  - «Cosa trova l'ospite» mostra tutte le sezioni del catalogo in tre gruppi: «La casa», «Arrivare e muoversi», «Il territorio».
  - Il numero delle sezioni è contato dal catalogo («15 sezioni pronte»).
  - A 390 px le sezioni stanno su due colonne compatte.
- **Demo.** I prezzi degli extra sono nel formato nuovo. Casa Lucia ha «Muoversi in zona», senza nomi di aziende.

## v2 · Fase 6B — Luoghi con categorie ed etichette tradotte, «Negozi e spesa» (3 ottobre 2026)

Migrazione `016` (`places.category_key`, `places.badge_key`), che converte i dati esistenti.

- **Categorie ed etichette come chiavi, tradotte da sole.**
  - Nella scheda del luogo, la categoria si sceglie da pillole con le voci di quella sezione (in «Cosa visitare» non c'è la ristorazione), più «Altro…» per il testo libero.
  - «Etichetta» diventa «In evidenza», con «Nessuna», le voci della sezione e «Personalizzata…».
  - Nella guida, nei filtri e nelle traduzioni la voce scelta compare nella lingua dell'ospite; per tradurla non serve niente («Tradotta automaticamente»).
  - Il segnaposto di «Perché lo consigli» cambia con la sezione.
- **Conversione.** La 016 riconosce i testi già scritti (in qualunque lingua) e li trasforma in chiavi; quelli non riconosciuti restano come sono. La copia tra strutture porta le chiavi.
- **Nuova sezione «Negozi e spesa»** (`shop`): alimentari, forno, mercato, farmacia, bancomat… Ha una sua icona e conta nel limite del piano come le altre. Nella demo ci sono due negozi di fantasia.

## v2 · Fase 6A — Campi in linea, silenzio, dotazioni, tipologia (3 ottobre 2026)

Migrazione `015` (`properties.property_type_other`).

- **Righe in linea (tutti i ripetitori).**
  - In alto maniglia, nome e numero («Parcheggio 1»), poi su / giù / togli in fila.
  - Sotto, i campi su una griglia di 12 colonne, con la larghezza scritta nel catalogo; sotto i 640 px un campo per riga.
  - L'aiuto sta sotto il campo. La foto mostra miniatura, «Sostituisci» e «Togli» su una linea.
  - La password del Wi-Fi ha «Mostra»; la «Zona» compare solo con due o più reti.
  - Niente compilazione automatica dei gestori di password nelle righe.
- **Orario del silenzio con interruttore.** Acceso: «Dalle» e «Alle» sulla stessa riga, precompilati 22:00 e 08:00. Spento: gli orari si svuotano e la guida non lo mostra. Le sezioni salvate prima valgono accese se c'era un orario.
- **Dotazioni.** 35 voci in sei gruppi, dalle tassonomie tradotte nelle 5 lingue (`app/lang/tassonomie/`, `Tassonomie.php`). «Le tue dotazioni» come pillole con «×» e «+ Aggiungi una dotazione»; nella guida, spuntate e scritte a mano stanno in un solo elenco.
- **Tipologia.** In più Appartamento e Villa o casale, niente più «Non indicata». Con «Altro» compare «Che tipo di struttura è?».

## v2 · La foto di Casa Lucia (1 ottobre 2026)

- Nuova copertina della demo (`assets/foto/casa.jpg`, fornita dal cliente): il portone in legno ad arco tra la pietra e i fiori. Si vede:
  - sulla schermata di benvenuto della guida (splash);
  - in testa alla guida;
  - nel telefono dell'hero della landing (`borgo-telefono.jpg` e la sua WebP, ritagliate dalla stessa foto).
- Il testo alternativo della copertina della demo è aggiornato.
- Sul server la demo già creata tiene la foto vecchia, che è salvata nello storage. Per vederla: **Amministrazione → Quadro → «Elimina i clienti di esempio», poi «Crea i clienti di esempio»**.

## v2 · Pannello ridisegnato (1 ottobre 2026)

Nessuna migrazione. Il telaio del pannello (`views/layout/cms.php`) vale per l'area host e per l'amministrazione.

- **Barra laterale scura**, al posto delle due barre orizzontali in alto:
  - le voci dell'account («Generale» per l'host, «Amministrazione» per lo staff), con l'icona e la voce attiva segnata (`aria-current`);
  - quando si lavora su una struttura, il suo gruppo: Contenuti, Lingue, Aspetto, QR & Link, Statistiche, Impostazioni, Anteprima, con il pallino dello stato (pubblicata o bozza);
  - in fondo l'utente, il tema e «Esci».
- **Tela chiara arrotondata** su fondo sabbia:
  - in alto le briciole (Le mie guide › Casa Lucia › Aspetto) e le azioni della guida, Anteprima e Pubblica;
  - l'intestazione resta visibile scorrendo.
- **Sul telefono:**
  - la barra diventa un cassetto. È un `<details>`, quindi funziona anche senza JavaScript, e si chiude con Esc, scegliendo una voce o toccando fuori;
  - le voci della guida diventano schede che scorrono sotto l'intestazione;
  - Anteprima diventa un'icona, con l'etichetta per i lettori di schermo.
- **«Vai al contenuto»** per chi usa la tastiera.
- **Le mie guide:**
  - il saluto («Ciao, Lucia.»);
  - tre cartellini pastello con numeri veri: guide online, da finire, il piano con il rinnovo;
  - le schede delle strutture con la foto, lo stato sopra la foto e le scorciatoie Contenuti, QR e Anteprima;
  - la scheda tratteggiata «Aggiungi una struttura».
- **Contenuti:** aperture, QR e sezione più letta su cartellini. Le aperture portano alle Statistiche.
- **Statistiche:** un anello QR / link con la legenda e il testo per chi non vede il grafico, più i cartellini dei totali.
- **Quadro dell'amministrazione:**
  - otto cartellini, e quelli che hanno una pagina portano lì;
  - gli ordini hanno lo stato in pillole colorate.
- I colori pastello derivano dalla tavolozza del progetto: rosa terracotta, mare, ocra, pino. Nel tema scuro diventano toni profondi.
- La procedura di una guida mai pubblicata e le pagine senza accesso restano senza barra, per non distrarre.
- Le schermate del pannello nella landing («Come funziona») sono rifatte con il pannello nuovo.
- `giro-completo.php`: 496 controlli.

## v2 · Landing: le foto delle scene (1 ottobre 2026)

- Le tre scene sotto l'hero hanno le loro foto (fornite dal cliente):
  - `scena-qr.jpg`: l'ospite inquadra il QR all'ingresso;
  - `scena-ospite.jpg`: la guida sul telefono;
  - `scena-host.jpg`: l'host aggiorna dal portatile.
  - Ogni foto è a 1200×750, con il testo alternativo e il centro scelto per la miniatura quadrata del telefono.
- **Versioni WebP più leggere:** `scena-…-1200.webp` e `scena-…-600.webp`, generate da `tools/foto.php` (anche da Diagnostica → «Rigenera le foto WebP»).
  - Il telefono scarica quella da 600 px (circa 30 KB invece di 120).
  - Una WebP più vecchia del suo .jpg non si usa: chi carica una foto nuova la vede subito, anche prima di rigenerare.
- Se una foto manca, al suo posto torna il disegno, come prima.
- `giro-completo.php`: 490 controlli.

## v2 · Landing: movimento e interazioni (1 ottobre 2026)

Nessuna migrazione. Cambiano `home.php`, `app.css`, `landing.js` e `prezzi.js`.

- **Comparse allo scorrimento** su tutta la home: ogni blocco entra salendo e mettendosi a fuoco, gli elementi di un gruppo uno dopo l'altro. Usa una sola curva per tutto e IntersectionObserver (nessun ascoltatore di scroll per le comparse).
- **Hero:** il telefono si inclina verso il puntatore (solo con mouse); le quattro sezioni nel telefono entrano una alla volta.
- **Il tempo che non vedi:** le domande arrivano una alla volta, la guida «scrive» e poi risponde.
- **La frase sul valore:** si colora parola per parola mentre la si legge.
- **Come funziona:** i passi avanzano da soli **un giro**, solo quando la sezione è in vista, con una barra di avanzamento sotto il passo. Si fermano per sempre al primo clic, tasto, passaggio del mouse o fuoco. La schermata entra morbida.
- **Il QR:** una linea di scansione passa due volte quando compare.
- **FAQ:** si aprono e si chiudono con un'animazione morbida (restano `<details>` veri).
- **Piani:**
  - le card si sollevano sotto il puntatore;
  - Portfolio ha i pulsanti − e + accanto al numero di strutture (con etichetta per i lettori di schermo, disattivati al minimo e al massimo), anche in `/piano`;
  - il prezzo fa un piccolo scatto quando cambia.
- **Scene:** ognuna porta dove se ne parla: il QR alla sezione del QR, l'ospite alla demo, l'host a «Come funziona».
- **Header:**
  - un'ombra quando la pagina scorre;
  - una barra di lettura in alto;
  - nel menu, la voce della sezione che si sta guardando (`aria-current`).
- **Pulsanti:** si «premono» (scala 0,98) e la freccia della CTA si sposta.
- **Sicurezze:**
  - con «riduci movimento» nel sistema non si anima niente;
  - senza JavaScript non si nasconde niente: le regole che nascondono valgono solo con `html.anima`, messa da uno script prima del disegno, così non c'è lampo;
  - se `landing.js` non arriva entro 3 secondi, la pagina torna ferma e completa.
- `giro-completo.php`: 488 controlli.

## v2 · Landing ridisegnata (1 ottobre 2026)

Nessuna migrazione. Si caricano solo file.

- **Stili sempre aggiornati.** Il foglio di stile e gli script si linkano con la data del file (`app.css?v=…`, funzione `av()` in `Support.php`). Dopo un caricamento via FTP il browser e la cache del server prendono subito i file nuovi. Prima potevano restare quelli vecchi, e la landing si vedeva senza stili: scene enormi, FAQ come elenco puntato, elenchi dei piani spezzati una parola per riga.
- **Hero.**
  - Il telefono non scende più sopra le scene.
  - «Guarda la demo» e le lingue IT / EN / DE stanno in un solo gruppo, accanto alla CTA, invece di sembrare un terzo bottone.
  - Le tre garanzie hanno la spunta.
- **Scene.**
  - Senza foto, al posto del riquadro piatto con l'icona c'è un disegno: la targa col QR, il telefono con le sezioni, il pannello con «Pubblica».
  - Le foto `scena-*.jpg`, quando ci sono, hanno la precedenza come prima.
  - Sul telefono le scene diventano righe con la miniatura: la pagina è circa il 10% più corta.
- **Cosa trova l'ospite.** Righe compatte con l'icona colorata, più il link per sfogliare la demo.
- **Il tempo che non vedi.** Da cinque blocchi a due colonne:
  - a sinistra problema e soluzione;
  - a destra le domande degli ospiti e la guida che risponde;
  - sotto, i due vantaggi e il valore dell'anno con il prezzo.
  - La frase «Il valore non è soltanto nella guida. È nel tempo che puoi dedicare ad altro.» ha ora uno spazio suo.
- **La guida che lavora per te.** Fascia scura, con il commiato disegnato usando le etichette vere della guida (recensioni e codice sconto).
- **Come funziona.**
  - Tre passi a sinistra e la schermata vera grande a destra, con schede accessibili da tastiera (`assets/landing.js`).
  - Senza JavaScript i passi sono link e le schermate si vedono tutte.
  - Le schermate sono rifatte più ingrandite, per essere leggibili.
- **FAQ.** Titolo a sinistra, con i pulsanti WhatsApp ed email per chi non trova la risposta (dal `config` → `legal`); domande a destra.
- **Piani.** «Confronta tutti i piani» è un bottone centrato.
- **Chiusura.** La CTA finale e «chi c'è dietro» (Blackout Agency) nella stessa fascia, invece di una nota sospesa a metà pagina.
- Menu del sito: in più c'è «Domande».
- Verificata a 390 e 1366 px, tema chiaro e scuro, senza scorrimento orizzontale.
- `giro-completo.php`: 484 controlli.

## v2 · Revisione finale (1 ottobre 2026)

Riletto da capo tutto quello che è cambiato nelle cinque fasi: permessi e blocchi, caricamenti, richieste in uscita, webhook, copia, email, uscite non protette nelle viste, SQL composto a mano. Corretto:

- **File della guida pubblicata.** Togliere dal pannello una foto, un PDF, una sezione o un luogo cancellava subito il file, anche se la guida online lo mostrava ancora: gli ospiti vedevano un'immagine rotta fino alla ripubblicazione. Ora:
  - il file resta finché la guida pubblicata lo usa (`Media::rilascia`);
  - alla pubblicazione successiva si tolgono i file che non usa più nessuno (`Media::pulisciOrfani`), solo se caricati da più di un'ora;
  - dentro la transazione del webhook non si cancella niente.
  - Prima della v2 queste foto rimanevano per sempre nello storage.
- **Link di Google Maps.** Un indirizzo come `https://altro.sito\@www.google.com/…` sembrava di Google al controllo, ma curl poteva portare altrove. Ora si rifiutano gli indirizzi con utente, password, porta, barra rovesciata, spazi o caratteri di controllo, e curl riceve l'indirizzo ricostruito dai pezzi già controllati. In più c'è un tetto di 120 letture all'ora per account.
- **Aggiungi una struttura (Portfolio):** il nome si controlla prima di aggiornare Stripe, così un nome troppo lungo non fa pagare una struttura che poi non nasce.
- **Email «quasi pronta»:** a chi ha già un abbonamento non dice più «paghi solo quando pubblichi».
- **Registro di amministrazione:** testimonianze e foto WebP non indicano più l'amministratore come «cliente interessato».
- Nessun altro problema trovato:
  - ogni rotta di una struttura passa dal controllo di proprietà e di blocco;
  - le uscite delle viste nuove sono protette;
  - l'SQL composto a mano contiene solo numeri interi o costanti.
- `giro-completo.php`: 473 controlli.

## v2 · Fase 5 — Vendita, crescita e demo in Umbria (1 ottobre 2026)

Due migrazioni (`013`, `014`), che partono da sole al primo accesso. Prima di caricare, copia `app/storage/`.

**K1 · Listino**
- Sotto ogni prezzo annuale c'è l'equivalente mensile («circa 9,75 € al mese», prezzo / 12). Nel Portfolio segue il numero di strutture (`prezzi.js`), in landing e in `/piano`.
- Essential perde le voci comuni a tutti i piani. Plus e Portfolio partono da «Tutto di Essential, e in più:» e non ripetono le voci già incluse.
  - Una voce che finisce con i due punti si mostra come titoletto.
  - I testi restano modificabili dall'admin. La migrazione 013 cambia solo i testi predefiniti.
- «Confronta tutti i piani»: una tabella chiusa sotto le card, generata dalle funzioni delle versioni in vendita.

**K2 · La guida fa guadagnare l'host**
- Nelle Impostazioni della struttura c'è il blocco «Dopo il soggiorno», tutto facoltativo:
  - link alle recensioni (Google, Booking.com, Airbnb, altro);
  - prenotazione diretta (link al sito e codice sconto).
- Nel commiato compaiono solo i dati compilati: «Ti è piaciuto il soggiorno?» con un pulsante per piattaforma, e «La prossima volta prenota da noi» con il codice da copiare.
- I link che non sono indirizzi web si scartano, con un avviso.
- Nuova sezione **Servizi extra**: righe con titolo, descrizione, prezzo e foto.
  - Ogni riga ha «Richiedi su WhatsApp» verso il primo contatto, con il messaggio già scritto nella lingua dell'ospite.
  - Si copia anche con «Crea da una struttura esistente».
- In landing c'è il blocco «Una prenotazione diretta in più all'anno paga l'abbonamento.»

**K3 · FAQ, demo, testimonianze**
- FAQ prima del listino, con accordion su `<details>` (tastiera e lettori di schermo). Sono sei domande, con le risposte prese da `LEGGIMI.md`.
- Accanto a «Guarda la demo» si sceglie la lingua: IT / EN / DE, solo quelle che la demo ha.
- Admin → Testimonianze: nome, struttura, testo, foto, visibile sì/no, ordine. La foto sta nello storage senza riga in `media`. In landing il blocco compare solo se c'è almeno una testimonianza visibile. Nessuna è inserita d'esempio.

**K4 · Firma nella guida**
- Nel piè di pagina: «Guida creata con MyHouse Welcome · Crea la tua», con link alla landing e `?ref=guida`.
- Nuova funzione di pacchetto `hide_branding`. La migrazione 013 crea una **versione nuova** di Plus e di Portfolio, uguale a quella in vendita più la funzione, come fa l'admin.
  - Le versioni già vendute non cambiano.
  - Chi aveva scelto Plus o Portfolio senza pagare passa alla versione nuova.
- Il server nasconde la firma solo se il piano lo comprende, anche se la richiesta viene costruita a mano.
- Istantanee della guida: formato 5. Quelle pubblicate prima mostrano la firma e nessun blocco del commiato.

**K5 · Email che riportano l'host a finire**
- I richiami partono:
  - dopo 1 giorno, se l'arrivo è vuoto;
  - dopo 3 giorni, se non ci sono sezioni;
  - dopo 7 giorni, se la guida non è pubblicata;
  - 30 giorni prima del rinnovo automatico, con le aperture dell'anno.
- Le finestre non si sovrappongono (1–3, 3–7, 7–21 giorni): una struttura riceve un richiamo per volta, e chi aggiorna con bozze vecchie non riceve una raffica.
- Niente richiami a strutture bloccate o di esempio.
- I testi sono brevi, in italiano, con un solo pulsante che porta al passo giusto. Ogni email ha anche la versione HTML.
- Tabelle nuove:
  - `email_log`: ogni email una volta sola;
  - `email_optout`: «non mandarmene più», per tipo, confermato con un bottone perché i programmi che aprono i link in anteprima non disiscrivano nessuno.
- Nessun cron obbligatorio: un controllo leggero gira dopo le pagine del pannello e della landing, al massimo ogni 15 minuti, con un file di blocco `storage/richiami.lock`. In più c'è `/cron/{token}` con `MHW_CRON_TOKEN`.

**K6 · Funnel senza cookie**
- Eventi anonimi in `analytics_events`: `landing_view` (i robot non contano), `signup`, `property_created`, `published` (la prima pubblicazione di una guida vera).
- `analytics_events.property_id` diventa facoltativo. Su SQLite la tabella si ricostruisce senza perdere un evento.
- Nel Quadro dell'admin: i quattro numeri degli ultimi 30 giorni e la percentuale di passaggio da un passo all'altro.

**V6 · Landing e demo in Umbria**
- Sotto l'hero, al posto della foto grande, c'è una fascia con tre scene (`scena-qr.jpg`, `scena-ospite.jpg`, `scena-host.jpg`). Le foto non ci sono ancora: ogni card mostra un riquadro colorato con l'icona e si riempie da sola quando carichi i file.
- In «Inizia in pochi minuti» ci sono tre schermate vere del pannello (`assets/foto/pannello-1…3.webp`, 1200×750): Contenuti, Aspetto con l'anteprima, QR & Link.
- Casa Lucia si sposta a **Spello (Umbria)**:
  - locali di fantasia (Osteria dell'Arco con gli strangozzi al tartufo, Bar della Fontana, Gelateria delle Rose);
  - telefono di esempio con il prefisso 0742;
  - una sezione Servizi extra d'esempio.
  - Vale per le installazioni nuove. Le demo già installate restano finché non le ricrei dall'admin.
- `app/tools/foto.php` rigenera con GD `borgo-1200.webp`, `borgo-2000.webp` e `borgo-telefono-600.webp` dai `.jpg`. Si avvia da riga di comando o dal bottone in Diagnostica.

**Prove**
- `giro-completo.php`: 470 controlli (35 nuovi). `aggiornamento.php`: superata da `be97f4a` (la v1 della revisione), `cb6d0dc`, `d6edc08`, `8f27f4f`, `14a9c6e`, `1562635`, `74893f6`.
- Nel browser, a 390 e 1366 px: landing (listino, confronto, FAQ da tastiera, scene, piè di pagina), commiato di Casa Lucia con e senza recensioni, firma.

## v2 · Fase 4 — Portfolio: una struttura prima di pagare, poi copia (1 ottobre 2026)

Nessuna migrazione: il blocco si calcola dai dati che ci sono già (piano scelto, abbonamento, quantità, ordine di creazione).

**Una struttura completa prima del pagamento**
- Con il Portfolio scelto e mai pagato si configura una struttura sola, la prima. Le altre, fino alla quantità scelta, si creano col solo nome e compaiono come card bloccate: «Si attiva dopo il pagamento». Restano eliminabili.
- Il blocco sta sul server: `Entitlements::lockedIds()` ed `editable()`, controllati da tutte le rotte di una struttura. Questo vale anche per gli URL scritti a mano: pagine, salvataggi, sezioni, luoghi, lingue, aspetto, QR, anteprima, pubblicazione, copia.
  - Risponde con un avviso e il rimando a «Le mie guide», non con un errore.
  - Il salvataggio automatico riceve 423 con lo stesso avviso.
- Chi ha già pagato in passato e ora è scaduto non viene bloccato.

**Sblocco**
- Al pagamento (webhook firmato di Stripe, o abbonamento manuale dall'amministrazione) si sbloccano tutte le strutture fino alla quantità pagata.
- «Aggiungi una struttura» oltre la quantità pagata apre una conferma col costo:
  - 60 € + IVA l'anno;
  - circa quanto per la parte dell'anno che resta;
  - il totale dal rinnovo.
- Confermato, la voce delle strutture aggiuntive su Stripe sale di uno con `proration_behavior=create_prorations`. La struttura nasce bloccata e si sblocca quando il webhook conferma la quantità nuova.
- Se Stripe non è configurato, o se l'abbonamento è manuale, compare un messaggio chiaro e non si crea niente.
- Il cambio del numero di strutture da Account & Fatturazione resta com'era: conguaglio fatturato subito (`always_invoice`), applicato solo a pagamento riuscito.

**Crea da una struttura esistente / Copia sezioni da…** (`app/src/Copia.php`)
- Struttura nuova: scelta facoltativa della struttura di origine e di cosa copiare.
  - Già spuntati: rifiuti, dove mangiare, cosa visitare, cosa fare, trasporti, emergenze, informazioni utili, regole, servizi, aspetto (palette, logo, tono del testo), contatti.
  - Non c'è una sezione «servizi extra» nel catalogo, quindi non compare.
- Mai copiati: indirizzo, CIN, reti Wi-Fi, passaggi di arrivo (Check-in & Check-out), «Come arrivare», foto di copertina, parcheggio.
- Si copiano anche:
  - traduzioni, luoghi e loro traduzioni;
  - le lingue della guida e la lingua principale.
- Immagini e PDF sono **duplicati** nello storage (disco o S3) con nomi nuovi: le foto della sezione, quelle dei luoghi, le foto e i PDF nelle righe e il logo. Eliminare una struttura non tocca l'altra.
- Su una struttura che esiste, «Copia sezioni da un'altra struttura»: per ogni sezione che c'è già si sceglie «Saltala» o «Sostituiscila». I file della sezione sostituita si cancellano a copia riuscita.
- Tutto in una transazione. Se qualcosa fallisce, anche solo per il limite di sezioni del piano, non resta niente a metà: i file già scritti nello storage si tolgono.
- Lo storage ha un metodo nuovo, `get()`, su disco e su S3.

**Predisposizione (solo commenti, in `Copia.php`)**
- Libreria dei luoghi a livello di account.
- Sezioni «collegate», che si aggiornano in tutte le guide.

**Demo**
- Marco (`marco@esempio.it` / `dimostrazione1`) è un Portfolio per 2 strutture **non ancora pagato**:
  - B&B Le Rondini è modificabile;
  - Casa sul Mare c'è col solo nome ed è bloccata.
- Vale per le installazioni nuove. Le installazioni esistenti non cambiano.

**Prove**
- `giro-completo.php`: 435 controlli (35 nuovi):
  - Marco bloccato anche dagli URL scritti a mano;
  - sblocco dall'admin;
  - un Portfolio nuovo sbloccato dal webhook;
  - struttura in più con `create_prorations`;
  - copia completa da Casa Lucia con file duplicati e guide indipendenti;
  - «Copia sezioni da…» con saltare e sostituire;
  - copia annullata senza resti.
- `aggiornamento.php`: superata da `be97f4a`, `cb6d0dc`, `d6edc08`, `8f27f4f`, `14a9c6e` e `1562635`.
- Nel browser, a 390 e 1366 px: guide di Marco, avviso, «Crea da una struttura esistente», «Copia sezioni da…», conferma del costo e messaggio senza Stripe.

## v2 · Fase 3, blocco B — Sezioni strutturate, scheda luogo da Maps, fatturazione (1 ottobre 2026)

Due migrazioni (`011`, `012`), che partono da sole al primo accesso. Prima di caricare, copia `app/storage/`.

**Motore dei campi**
- Tipi nuovi: `checks` (più spunte da un elenco fisso), `toggles` (sì / no / non indicato, uno per voce) e `time` (un orario). Nelle righe ripetibili, anche `image` e `pdf`.
- Foto e PDF nelle righe: si caricano col bottone Salva. Il server controlla che il piano li comprenda (`Entitlements`), aggancia solo file di quella struttura e cancella quelli non più usati (riga tolta, «Togli»). Con la sezione si cancellano anche i suoi file. La pubblicazione segnala le foto e i PDF nelle righe se il piano non li comprende.
- Il bottone per scegliere il file è in italiano, non quello del browser.
- Le righe nuove ricevono subito il loro id nel browser, così i salvataggi automatici successivi le riconoscono.
- Istantanee della guida: formato 4. Le guide pubblicate prima si leggono nel formato nuovo, senza ripubblicarle.

**R5 · Sezioni strutturate** (migrazione 011)
- Emergenze: righe nome · telefono · nota, con un «Chiama» per riga. Righe pronte a un tocco, nella lingua della guida: 112, guardia medica, farmacia di turno, veterinario.
- Rifiuti: tipo, giorni della settimana, colore del bidone, dove si trova. In cima alla pagina «Oggi si butta: …», calcolato sul giorno di oggi in Italia (Europe/Rome).
- Servizi: griglia delle dotazioni con icone (lavatrice, asciugatrice, lavastoviglie, asciugacapelli, ferro, culla, seggiolone, aria condizionata, riscaldamento, TV, macchina del caffè, barbecue). Istruzioni ripetibili con titolo, passaggi, foto e PDF.
- Regole: fumo, animali, feste e visitatori (ammesso / non ammesso / non indicato), orari del silenzio, regole aggiuntive.
- Parcheggio: più possibilità (tipo, indirizzo, link Maps, costo, istruzioni, foto) e il campo ZTL.
- Come arrivare: una scheda per mezzo (auto, treno, aereo, autobus), ognuna con i suoi passaggi.
- Conversione dei dati di prima, con le vecchie chiavi lasciate nel JSON:
  - le voci «una per riga» delle emergenze diventano righe, con il telefono separato dal nome («Guardia medica: 075 123456 (notti)» diventa nome, telefono e nota);
  - le voci dei rifiuti diventano righe col testo intero nella descrizione. Il tipo si riconosce solo se è uno solo; i giorni scritti per esteso si riconoscono sempre;
  - il parcheggio unico diventa la prima riga;
  - i passaggi di «Come arrivare» diventano la prima scheda;
  - le altre lingue si allineano riga per riga a quella principale;
  - servizi e regole tengono la loro lista come «Altre dotazioni» e «Regole aggiuntive».
- 65 etichette nuove nella guida ospite, in tutte e 5 le lingue.

**R6 · Scheda luogo**
- Il primo campo è «Incolla il link di Google Maps». Il server segue i link brevi (`maps.app.goo.gl`): massimo 5 secondi, solo verso indirizzi Google, passando anche dalla pagina del consenso. Dal link prende il nome (`/place/<nome>/`) e le coordinate (`!3d…!4d…`, `@lat,lng`, `q=lat,lng`). Se non riesce, nessun errore.
- Sempre visibili: link, nome, categoria, «Perché lo consigli», etichetta. Il resto sta in «Altri dettagli», chiuso.
- Minuti a piedi stimati: distanza in linea d'aria × 1,3, a 4,5 km/h, con l'indicazione «stima, modificabile». Servono le coordinate della casa, che si prendono dal link Maps di «Come arrivare». Nessuna API a pagamento.
- Colonne `places.lat` e `places.lng` (migrazione 011).

**R7 · Fatturazione italiana** (migrazione 012)
- Account & Fatturazione ha i dati di fatturazione:
  - tipo: azienda o professionista, oppure privato;
  - intestatario;
  - partita IVA, controllata con la cifra di controllo;
  - codice fiscale: 16 caratteri col carattere di controllo, oppure 11 cifre per le società;
  - codice destinatario SDI (7 caratteri) o PEC;
  - indirizzo, CAP, città, provincia.
- Gli errori sono indicati campo per campo (`aria-invalid` e messaggio collegato al campo).
- Sono obbligatori prima del primo pagamento: «Pubblica» porta alla scheda e, salvati i dati, riporta alla pubblicazione.
- I dati vanno al cliente Stripe come intestatario e indirizzo, più i metadati `vat`, `cf`, `sdi`, `pec`. Se il cliente esiste già, viene aggiornato. L'applicazione non genera fatture.

**Prove**
- `giro-completo.php`: 400 controlli (40 nuovi).
- `aggiornamento.php`: emergenze, rifiuti, parcheggio e «Come arrivare» nel formato di prima vengono convertiti senza perdere testo, in italiano e in inglese, e si vedono nell'anteprima. Superata partendo da `be97f4a`, `cb6d0dc`, `d6edc08`, `8f27f4f` e `14a9c6e` (blocco A).
- Verifica nel browser a 390 e 1366 px:
  - righe aggiunte, spostate e tolte da tastiera;
  - righe pronte delle emergenze;
  - foto in una riga;
  - nome del luogo preso dal link;
  - errori della fatturazione;
  - guida demo in italiano e in inglese, senza scorrimento orizzontale.

## v2 · Fase 3, blocco A — Struttura e contatti, arrivo e partenza, Wi-Fi (1 ottobre 2026)

Tre migrazioni (`008`, `009`, `010`), che partono da sole al primo accesso. Prima di caricare, copia `app/storage/`.

**Motore dei campi**
- Due tipi nuovi nel catalogo: `repeater` (righe con sottocampi, che si aggiungono, si tolgono e si riordinano trascinando o con «Sposta su/giù» da tastiera) e `choice` (una scelta fissa, con etichette tradotte nei file `lang`).
- Nelle righe ripetibili i sottocampi uguali in ogni lingua (rete, password, telefono) stanno in `sections.data`, i testi in `section_translations.data`. Le due parti si uniscono con un **id di riga stabile**, quindi riordinare o togliere una riga non mescola le traduzioni.
- Aggiornati editor, pagina di traduzione (riga per riga), percentuali di traduzione e guida ospite (ripiego sulla lingua principale riga per riga).
- Nuova classe `Conversione`: un'unica conversione dal formato di prima, usata dalle migrazioni, dalle guide già pubblicate (lette al volo nel formato nuovo, senza ripubblicare né riscrivere le istantanee) e dai dati demo. È idempotente e non cancella niente: i vecchi campi restano nel JSON.
- Istantanee della guida: formato 3.

**R2 · Struttura e contatti** (migrazione 008)
- Campi nuovi, tutti facoltativi: tipologia, indirizzo, CAP, CIN, posti letto (e coordinate, per ora vuote).
- Contatti duplicabili, nella tabella nuova `property_contacts`: nome, ruolo (Host, Co-host, Pulizie e chiavi, Manutenzione, Altro), telefono, «risponde anche su WhatsApp». La migrazione copia nome, telefono e WhatsApp di prima. Se telefono e WhatsApp erano due numeri diversi, diventano due righe: nessun numero si perde. Le vecchie colonne restano e restano allineate al primo contatto.
- Guida ospite: la barra in fondo diventa «Contatta {nome}» e apre un foglio con tutti i contatti (Chiama / WhatsApp). Il CIN compare in piccolo nel piè di pagina.
- L'indirizzo della struttura precompila «Come arrivare». Se manca il link Maps, la guida ne genera uno di ricerca dall'indirizzo.

**R3 · Arrivo e partenza** (migrazione 009)
- Arrivo: modalità (self check-in, accoglienza, cassetta delle chiavi), passaggi, arrivo tardivo, documenti da mostrare, imposta di soggiorno (importo per notte, notti massime, esenzioni e pagamento).
- Partenza: le cinque caselle fisse (chiavi, rifiuti, luci, clima, finestre) diventano la lista ordinabile «Prima di partire», con suggerimenti a un tocco nella lingua della guida (Chiavi, Rifiuti, Luci, Clima, Finestre, Lavastoviglie, Asciugamani). La migrazione converte lingua per lingua, nello stesso ordine, con l'etichetta («Chiavi: …»). Aggiornato anche il congedo.

**R4 · Wi-Fi** (migrazione 010)
- Più reti: zona, nome della rete, password. La rete di prima diventa la prima riga.
- Nella guida, per ogni rete: «Copia password» e un **QR Wi-Fi** (`WIFI:T:WPA;S:…;P:…;;`, con `\ ; , : "` protetti), generato con `Qr.php` dentro la pagina, senza richieste in più.

**Correzioni trovate nelle prove d'aggiornamento**
- La migrazione 005 ripubblica la demo mentre le migrazioni stanno ancora girando. Ora `Guide::build` converte da sé i contenuti e legge i contatti dalle colonne vecchie se la tabella nuova non c'è ancora.

**Database**
- Le migrazioni nuove scelgono la sintassi giusta per SQLite e per MySQL. L'installazione da zero su MySQL resta **non supportata**: lo schema iniziale (`001`) usa sintassi solo SQLite. Si usa SQLite, come oggi. Da sistemare in un passo a parte, con un MySQL di prova.

**Prove**
- `giro-completo.php`: 360 controlli (19 nuovi per il blocco A).
- `aggiornamento.php` controlla che nessun testo, traduzione, dato comune, luogo, foto o numero di telefono si perda, e che guida, congedo e Wi-Fi della demo già pubblicata si leggano nel formato nuovo. Superata partendo da `be97f4a` (la «v1» della revisione), `cb6d0dc` (Fase 2), `d6edc08` e `8f27f4f`.

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
