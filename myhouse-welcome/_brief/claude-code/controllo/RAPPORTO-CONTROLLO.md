# MyHouse Welcome — controllo completo del 20 ottobre 2026
Copia del sito (zip «completo-2026-10-20-cache-seo») fatta girare in locale con i dati di prova. Esclusi Stripe e Adamo. Screenshot nelle cartelle `shots/` (sito pubblico) e `shots-pannelli/` (pannello cliente `d-*` desktop, `m-*` telefono, admin `*admin*`).
Legenda: **GRAVE** = da correggere prima di vendere · **MEDIO** = da correggere nella prossima fase · **LIEVE** = rifinitura · **DA APPROVARE** = serve una tua decisione.

## 0 · Le tre cose da sistemare subito
1. **Sconto sul rinnovo: i due coupon su Stripe non fanno 55% ma 52,5%.** `Inviti.php:290-316`, `Giudizi.php:194-209`, `Gestione.php:96-103`. Stripe applica i coupon in cascata: 127 × 0,50 × 0,95 = 60,33 €, non 57,15 €. Tutti i testi, la scala del questionario e `Sconti::massimoRinnovo` sommano, l'avviso a 30/7 giorni (che legge l'importo vero da Stripe) direbbe un'altra cifra. **Correzione:** un solo coupon sul rinnovo `mhw-rinnovo-N` con `percent_off` = inviti + giudizio (come già fatto al primo anno con `mhw-primo-anno-N`), in un unico `Sconti::sincronizzaRinnovo()`; riconoscere la composizione dai metadati della subscription. Da provare con `tools/verifica-stripe.php` in modalità test prima di andare online.
2. **Pagina /giudizio vuota** per gli account esclusi dal programma (`Giudizi::escluso`): solo titolo, nessuna spiegazione. Vale anche per un cliente vero che ha una guida vetrina. Mostrare «Il programma non è disponibile per questo account perché …».
3. **Cambio password:** l'aiuto dice «almeno 8 caratteri», il server vuole 10; l'errore finisce 2000 px sotto, fuori schermo, e ricaricando si rimanda il POST. Allineare a 10, `minlength`, redirect con messaggio e ritorno al modulo.

## 1 · Copy del sito in doppia lingua
- MEDIO · `/en`: le 6 schede «Who it is for» e i link del footer portano alle pagine per tipologia solo in italiano, senza avviso; `/registrati?lang=en` e `/accedi` sono in italiano. **DA APPROVARE:** (a) nascondere le schede in EN e segnare «(in Italian)», oppure (b) tradurre pagine per tipologia e registrazione. Consiglio (a) adesso, (b) solo se arrivano clienti stranieri.
- MEDIO · La traduzione automatica ha quattro nomi diversi (questionario «traduzione automatica inclusa», /piano «la guida si traduce da sola», schede piani «traduciamo noi: tu controlli e confermi», tabella «traduzioni suggerite entro un limite annuale», FAQ «Amazon Translate, approvi tu»). Una formula sola ovunque: **«traduzioni suggerite dal traduttore automatico, le approvi tu, comprese nel piano entro un limite annuale»**.
- MEDIO · FAQ «lingue» (`Faq.php:50`, `faq-en.php:14`): «Le traduzioni le scrivi tu» — sbagliato per Plus/Portfolio. FAQ B&B (`tipologie.php:76`): «in omaggio il primo anno» — regola abolita.
- MEDIO · Home, nota di «Il tempo delle risposte»: «dentro le stime pubblicate nel 2026 (stime pubblicate nel 2026 da…)» ripetuto.
- LIEVE · Hero «Guarda la demo | IT EN FR»: la demo ha 5 lingue. Togliere le sigle (c'è già il dialogo).
- LIEVE · Scheda Portfolio «1ª 117 €» accanto a Plus 127 €: aggiungere «da 2 strutture».
- LIEVE · `TestiSito.php:246` `dicono.nota`: segnaposto `{giudizio}%` mentre altrove `{giudizio}` ha già il %: rischio «5%%». `Impostazioni.php:120` cita ancora la casella «Sì, voglio il 5% in più».
- LIEVE · Inviti: «metà prezzo» come traguardo accanto al «55%» confonde → «il 50% degli inviti».
- OK: 209 chiavi IT/EN allineate, nessun italiano residuo nei file EN, FAQ IT/EN con gli stessi id, hreflang e sitemap corretti.

## 2 · Demo Casa Checco
- GRAVE · Check-in: «Imposta di soggiorno 4 € a persona per notte **a notte**», in EN «4 € a persona per notte per night». `Demo.php:244`: `tax_amount => '4 € a persona'`.
- MEDIO · Contatto «Marta, per le pulizie» resta in italiano in EN/FR. Bottoni Booking/Airbnb del commiato a 5 stelle portano alle home generiche: nella demo lasciare solo Google con link vero o la pagina della struttura.
- LIEVE · Dialogo lingua solo in italiano (aggiungere «Choose your language»); commiato con testo chiaro su foto poco contrastato; valuta mista «4 €» / «€3» in EN.
- Migliorie di contenuto, **DA APPROVARE**: (1) il blocco «Partenza» è duplicato tra Check-in e Commiato: in Check-in lasciare solo «Check-out entro le 10:30 → vedi Prima di andare»; (2) Wi-Fi: le due reti hanno la stessa password, usarne due diverse per mostrare «una riga per zona»; (3) «Prenota sul nostro sito» porta a blackout.in/myhouse (pagina per host) dentro una guida per ospiti: sito fittizio o togliere; (4) Eventi con date fisse 2027: «ogni anno, 11–12 agosto»; (5) Emergenze: farmacia e ospedale con «Apri Maps» come le altre sezioni.

## 3 · Responsive
- MEDIO · Home @390: «Confronta tutti i piani» è una tabella da 953 px in 348 px, Plus e Portfolio fuori schermo senza indizio di scorrimento. Prima colonna fissa + ombra di scorrimento, o tabella impilata per piano.
- MEDIO · Home @390: ~19.700 px di altezza; le 6 schede «Per chi è» con foto a tutta larghezza occupano 5 schermate. Su telefono usare la riga compatta (miniatura + titolo) già usata per le tre «scene».
- MEDIO · Pannello @390: la fascia NOVITÀ (240 px, senza X) su ogni pagina, anche in procedura ed editor; il riquadro «Hai già un sito?» sopra l'elenco delle guide; titoli delle sezioni troncati per le pillole «Pronta · EN da tradurre».
- MEDIO · FAQ @390: 28 FAQ su 29 aperte all'arrivo, 8.800 px. Chiuse di default, raggruppate per tema con indice.
- LIEVE · Questionario @390: esito e «Crea gratis con…» sotto la piega del dialogo. Tap target sotto 24 px nei link testuali del footer e della demo. Admin @390: codici MW-XXXXX spezzati su due righe.
- OK: nessuno scorrimento orizzontale su 21 pagine × 2 viewport, nessun errore console, nessun asset 404, immagini con srcset/webp e alt.

## 4 · UX e UI
- MEDIO · Link «Come funziona» dello sconto → `/domande#porta-amico`, ancora inesistente (la FAQ è `#faq-giudizio-sconto`).
- LIEVE · Termini/Privacy/DPA con layout senza menu e footer: aggiungere «← Torna alla home» o il layout del sito. Blocco magenta «MyHouse siti» in home fuori palette: tenere il logo, sfondo scuro come le altre sezioni. `/registrati`: «Ripeti l'email» è attrito; `noindex` su registrati/accedi.
- Procedura in 5 passi (pannello): MEDIO · passo 1 apre con privacy + lingua (16 campi prima del bottone) mentre l'intro promette «bastano nome e città» → scheda struttura in cima, lingua in un `details`; passo 5 «Manca il CIN» è solo testo e il bottone è disabilitato senza causa accanto → link al passo 1 e causa sotto il bottone; passo 2 avvertenza sui codici ripetuta due volte.

## 5 · FAQ coerenti con l'ultimo aggiornamento
- MEDIO · Vedi punto 1 (traduzioni) e punto 8 (omaggio + giudizio).
- LIEVE · `Faq.php:54` e `SectionCatalog.php:390`: «6 sezioni» scritto a mano, prendere `features.sections` di Essential.
- OK: /piu-strutture, /property-manager, spiegone campione, «Non si copiano mai» senza parcheggio, casella unica del programma.

## 6 · Prezzi
- OK: `Plans` dà 117/177/227/327/367/527; mensili 8,08/10,58/14,75; home, /piano, questionario, /piu-strutture, checkout, JSON-LD coerenti (verificato anche nel browser: 2→177 €, 4→277 €, media 88,50 €).
- LIEVE · `questionario.js:112` e `_rinnovo.php:21` arrotondano in modo diverso dal server (`Sconti.php:287`, che ha anche il tetto «mai sotto 1 €»): differenze di 1 cent possibili. Usare ovunque la formula del server.
- LIEVE · Pagine per tipologia: a 11 strutture il prezzo sparisce e il modulo del preventivo resta chiuso in un `details`. /piano oltre 10: la scheda Portfolio torna a 2 strutture e 177 € mentre il riquadro parla di 12 → «da 11 in su: preventivo».
- MEDIO · Preventivo: utente loggato ma nome/email non precompilati; l'unico feedback è il fumetto del browser.

## 7 · Coerenza front-end / back-end
- LIEVE · `config.php:157` `cap_account` = 150.000 ma Impostazioni dice «predefinito 500.000» e `Traduttore.php:272` corregge a mano: mettere 500.000 nel config e togliere la riga.
- MEDIO · Pannello Lingue: tre conteggi diversi per la stessa cosa («3 campi», «(4)», «4 campi») e un flash che rimanda a un bottone che non esiste («Suggerisci le traduzioni mancanti» → si chiama «Traduci tutti»).
- MEDIO · Copia sezioni: il testo dice «le due guide restano indipendenti» ma dalla campione le sezioni restano collegate; flash «Copiate: Informazioni utili» con il nome del catalogo invece del titolo («La piscina»); la sezione libera nasce con titolo «Informazioni utili» (meglio vuoto e obbligatorio).
- OK: 6/18 sezioni, 2/6 lingue, 2–10 strutture, 4 schede/6 giudizi, percentuali e interruttori letti da `Plans`/`Config` sia nel front sia nel pannello.

## 8 · Promo e sconti
- GRAVE · Rinnovo 52,5% invece di 55% (punto 0.1).
- MEDIO · `routes_host.php:1107`: se vince lo sconto di sistema (es. solo giudizio) e l'account ha anche un codice manuale più basso, l'ordine registra comunque il codice manuale → al webhook conta un utilizzo mai applicato e brucia `intended_discount_code_id`. Togliere il fallback.
- MEDIO · Giudizio + omaggio incoerente: Termini 17 e FAQ dicono «non disponibile con i codici omaggio», Termini 18, admin omaggi e codice dicono «resta e si applica al primo addebito». **DA APPROVARE** la regola: consiglio «con un codice omaggio non si aderisce al pagamento; se hai già aderito dal pannello lo sconto resta e va sul primo addebito», e riscrivere il 17 (IT ed EN).
- LIEVE · `Giudizi.php:236` e `Inviti.php:249`: al rinnovo segnano «applicato» se c'è un qualunque sconto, senza controllare quale coupon. Controllare l'id del coupon nella fattura.
- LIEVE · `Omaggi.php:95-107`: prenotazione del codice senza transazione né vincolo UNIQUE: due registrazioni simultanee possono prendere lo stesso codice.
- OK: primo anno con coupon unico idempotente, codice manuale «vale il più alto», omaggio che esclude l'invito, nessun «50%» come tetto totale.

## 9 · Contrattualistica
- MEDIO · `lang/legale.php:391-395` `legale.novita`: le «novità» mostrate nella finestra di ri-accettazione e nell'email sono ancora quelle dell'8J («Il titolare è City Up…») mentre la versione dei Termini è 2026-10-20: chi ri-accetta legge novità sbagliate. Scrivere le tre vere: Giudizio con sconto (due pubblicazioni, ritiro), codice omaggio e rinnovo automatico con avvisi 30/7, traduzioni con tetto annuale.
- MEDIO · Privacy §9 descrive GA4 e Clarity «solo se acconsenti» e il cookie `mhw_consenso`, ma la sezione Cookie dice «solo cookie tecnici» e nel sito non compare il banner. **DA APPROVARE:** se GA4/Clarity non si usano adesso, togliere i riferimenti (restano Matomo senza cookie e il banner pronto); se si usano, configurarli e il banner compare da solo. (Sul live va verificato: in locale manca il dominio.)
- LIEVE · Termini: i punti compaiono in ordine 1…12, 17, 18, 13, 14, 15, 16. Rinumerare o mostrare in ordine.
- LIEVE · `company_capitale` vuoto: finché non lo compili in Impostazioni → Sito, piè di pagina e Titolare escono senza capitale sociale (art. 2250 c.c.). **Serve il dato.**
- LIEVE · `Testimonianze.php:18` CONSENSO legacy cita ancora «la foto scelta»: deprecare.
- OK: PEC assente ovunque; REA, P.IVA, sede; privacy per trattamento con basi e conservazioni («Giudizio con sconto e vetrina» 6.1.b, nessuna foto); rinnovo automatico e recesso; archivio versioni e ri-accettazione per documento; cookie Matomo senza consenso, GA4/Clarity con consenso e revoca.

## 10 · Pannello del cliente
- GRAVE · /giudizio vuota; cambio password (punto 0).
- MEDIO · «Rendi campione» senza conferma, anche su una bozza vuota, con tre messaggi uguali; dopo «Salva» nella campione nessun avviso in pagina (il banner è solo in «Le mie guide»); nella tabella delle gemelle «uguale alla campione» quando in realtà è «non personalizzata · da aggiornare».
- MEDIO · Fascia NOVITÀ, riquadro «Hai già un sito?», pillole (punto 3). Procedura (punto 4).
- LIEVE · Flash doppi (password, campione, adesione giudizio); «Fatto: 1 sezioni aggiornate» (concordanza) e chiarire che le gemelle online si ripubblicano da sole; /inviti 404 quando gli inviti sono spenti, /account/strutture rimanda a /piano senza avviso.
- Spiegoni e cose in più, **DA APPROVARE**: barra inline «1 gemella da aggiornare → Porta le modifiche» dopo il salvataggio; in Lingue barra del tetto per struttura con stima «mancano ≈ X% del tetto» prima di «Traduci tutti»; stato vuoto in Statistiche con «condividi il QR»; checklist per le bozze in «Le mie guide» (CIN, check-in, Wi-Fi, copertina, lingue); «?» contestuale accanto a campione, traduzioni, CIN.
- OK: procedura chiara con autosalvataggio e anteprima; foto, palette «Bianco e nero», QR PDF/SVG/PNG, pubblicazione in 3 lingue, tetto traduzioni visibile, errore del traduttore gestito con «Riprova», eliminazione con nome da riscrivere, nessuno scorrimento orizzontale.

## 11 · Pannello di amministrazione
- GRAVE · Codici sconto: `pattern="[A-Za-z0-9-]{4,24}"` non valido in Chrome (errore console) e «Valido fino al» obbligatorio senza segnalazione: il modulo non parte e non dice perché.
- MEDIO · Quadro: 6–7 riquadri di avviso prima dei numeri (su telefono i KPI al terzo schermo) → un solo riquadro «Da configurare: 6 voci»; numeri incoerenti nella stessa pagina (Clienti esclude gli esempio, Listino no; «Guide pubblicate 1 su 3» conta una guida di un account di esempio).
- MEDIO · Clienti: un account mai pagante risulta «Scaduto (offline)» → stato «Mai attivato»; mancano colonna «Ultimo pagamento/Incassato» e filtro «in prova / manuale / mai pagato». Cerca: «MW» o il codice senza prefisso non trovano nulla.
- MEDIO · Attivazione manuale: la tendina mescola piani in vendita e versioni vecchie («Portfolio 2 v1», «v5»): facile sbagliare → solo le versioni in vendita, le altre in un gruppo «Precedenti». I pagamenti ricevuti fuori Stripe (bonifico) non hanno dove essere segnati: Prospetti e Scadenze non li vedranno mai → campo «importo incassato e data».
- MEDIO · Preventivi: solo «Rispondi» (mailto) e cambio stato; mancano importo proposto, nota, «ricontattare il», storico.
- MEDIO · «Registro» (azioni) e Impostazioni → «Registri» (log tecnici): due nomi quasi uguali → «Registro azioni» e «Log tecnici».
- LIEVE · «Torna al mio account» dall'impersonazione rimanda all'elenco invece che alla scheda; «Regole» in Giudizi e vetrina come paragrafo sotto la tabella (meglio `details` in cima); voce «Richiami» assente dal menu.
- Organizzazione proposta, **DA APPROVARE**: (1) voce **«Cassa»**: incassi per mese Stripe + manuali, IVA, totale anno, CSV per il commercialista; (2) **Scadenze** a 30/60/90 con importo atteso, «manda promemoria» per riga, prove e omaggi in scadenza; (3) **«Da fare oggi»** nel Quadro: preventivi nuovi, giudizi da approvare, rinnovi falliti, email non confermate; (4) CSV su Abbonamenti, Preventivi, Partner, Registro; (5) nella scheda cliente «Attiva Portfolio con N strutture» già sulla versione in vendita.
- OK: nessun errore PHP; impersonazione con fascia e scadenza; registro con codice cliente; ricerca che apre la scheda; anomalie, diagnostica, cache, SEO; layout mobile senza scorrimento.

## 12 · Altro
- /guide-dei-nostri-host è in sitemap ma vuota («Non ci sono ancora guide»): `noindex` e fuori sitemap finché non ha almeno una scheda, oppure mostrare Casa Checco come esempio. **DA APPROVARE.**
- Dati di prova: copertine di Casa Lucia e Le Rondini con 404 (solo nella copia locale).
- Sul live restano da verificare: canonical, indirizzi puliti, dominio in configurazione, banner cookie (in locale non c'è dominio).

## Decisioni che aspetto da te (riassunto)
1. Versione inglese: nascondere le schede per tipologia in EN (consigliato) o tradurre tutto.
2. Giudizio + codice omaggio: regola definitiva.
3. GA4 e Clarity: li usiamo adesso o togliamo i riferimenti dalla privacy.
4. Capitale sociale di City Up S.r.l. (serve il numero).
5. Pagina «Tutte le guide» vuota: nascondere o mostrare Casa Checco.
6. Le 5 migliorie della demo e le «cose in più» di pannello e admin: quali vuoi.
Con le risposte preparo il prompt **9N** (correzioni) per Claude Code, Opus, con i mockup dove servono.
