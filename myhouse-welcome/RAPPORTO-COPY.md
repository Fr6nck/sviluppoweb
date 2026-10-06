# MyHouse Welcome — revisione del copy e delle immagini

Base: `welcomebook-v2-definitivo-2.zip`. Ho letto tutto il testo che si vede nel pannello dell'attività, in amministrazione e sul sito pubblico (pagine, messaggi di conferma e di errore, email, testi dei piani nel database), l'ho reso coerente e l'ho corretto direttamente nei file. Questo documento dice cosa è cambiato e perché.

## 1. Come si applica

1. Copia il contenuto di `copy-coerente-patch.zip` sopra l'installazione: sono solo i 71 file cambiati o nuovi (questo rapporto compreso), con gli stessi percorsi.
2. Apri il sito: la migrazione `020` parte da sola. Cambia soltanto testi nel database (etichette delle funzioni, elenchi dei piani) e solo dove sono ancora quelli predefiniti: quello che hai riscritto a mano in Amministrazione resta com'è.
3. Nessuna impostazione nuova, nessun indirizzo cambiato (`/admin/pacchetti` resta lo stesso: cambia solo la voce del menu).

`copy-coerente.diff` contiene ogni riga cambiata, se vuoi rivederle una per una.

## 2. Glossario: una parola per ogni cosa

| Si dice | Non più | Dove valeva la confusione |
|---|---|---|
| **struttura** | casa | Aiuti del pannello («Dalla casa», «quello che trovano in casa»). «Casa» resta nei nomi rivolti all'ospite («Regole della casa») e nel titolo della landing. |
| **guida** | Welcome Guide | Descrizione del piano Portfolio. |
| **piano** (Essential, Plus, Portfolio) | pacchetto | L'amministrazione diceva «Pacchetti», «Gestisci pacchetti», colonna «Pacchetto», ma anche «Piano» e «Piani nascosti». Ora la voce del menu è **Piani**. |
| **abbonamento** | — | Il pagamento annuale di un piano. |
| **Check-in & Check-out** | Arrivo e partenza | Il passo 2 della configurazione aveva un nome diverso dalla sezione che compila. |
| **configurazione** | procedura | «Continua la configurazione» ma «Torna alla procedura» e la briciola «Procedura». |
| **foto** | immagine | «Immagine della sezione», «Immagine profilo», «Togli l'immagine» accanto a «Foto di copertina», «Foto del luogo». Ora: foto della sezione, **Foto profilo**. «Logo» resta logo. |
| **etichetta** (di un luogo) | In evidenza / bollino | Il riquadro si chiamava «In evidenza», i campi dentro «etichetta». |
| **luoghi consigliati** | consigli sul posto | Tabella di confronto dei piani contro il pannello («I luoghi che consigli»). |
| **tema** chiaro / scuro | testo scuro / chiaro | In Aspetto le opzioni parlavano di «testo», l'aiuto di «tema». |
| **QR** nel pannello, **QR Code** sulla landing | misto | «Dal QR Code» accanto a «Scarica il QR», «QR & Link». |
| **link** | indirizzo | «Indirizzo della guida» in Impostazioni, «Link della guida» nella pagina QR. |
| **Togli** (lo levo, resta il resto) · **Elimina** (per sempre) · **Disattiva** (lo spengo, tengo i contenuti) | Rimuovi | La zona di caricamento diceva «Rimuovi», tutto il resto «Togli». |
| **confermare** l'email | verificare | Pannello «Email confermata», amministrazione «non verificata». |
| **Apri l'anteprima** | Guarda l'anteprima | Tre pagine dicevano «Guarda», una «Apri». |
| **Mandami di nuovo l'email** | Mandamela di nuovo | Due bottoni diversi per la stessa azione. |
| **disponibile con il piano Plus** | compresa nel / compresi dal piano Plus | Anche un errore di genere: «Logo: compresa nel piano Plus». |

## 3. Regole di scrittura adottate

- **Titoli** con il punto finale, come già facevi («Le mie guide.»). **Bottoni** con un verbo: «Crea la struttura», «Mostra le sezioni», «Accedi».
- **Aiuti dei campi**: una frase, con il punto. L'esempio comincia con «Per esempio:». Gli elenchi dicono sempre «Una riga per…» (righe con più campi) o «Una voce per riga» / «Un passaggio per riga».
- **Campi facoltativi**: «(facoltativo)» accanto all'etichetta, in grigio. Prima era la prima parola dell'aiuto in 46 campi del catalogo e stava tra parentesi in altri 5; e molti aiuti dicevano solo «Facoltativa.», senza aiutare. Nel catalogo la parola resta dov'era: la sposta `SectionCatalog::facoltativo()`.
- **Interruttori**: l'etichetta dice cosa succede quando è acceso («Indica un orario del silenzio», non «Orario del silenzio — Spento: non compare»).
- **Errori**: dicono cosa fare («Scrivi il nome della struttura», non «Il nome non può restare vuoto»).
- **Date** scritte per esteso dappertutto («4 gennaio 2027»): i codici sconto usavano `04/01/2027`.
- **Niente genere quando si parla a chi usa il sito**: «Rieccoti.» al posto di «Bentornato.», «hai raggiunto il massimo» al posto di «sei arrivato».
- **Niente gergo**: via «conguaglio», «notifica firmata», «commiato», «in linea d'aria × 1,3».

## 4. Errori corretti

| Gravità | Dove | Prima | Dopo |
|---|---|---|---|
| Alta | Le mie guide (Portfolio non pagato) | «Il cifra lo cambi prima di pagare» · bottone «Cambia il cifra di strutture» | «Il numero lo cambi prima di pagare» · «Cambia il numero di strutture» |
| Alta | Email di promemoria e pagina per non riceverle più | «Non vuoi più ricevere il avviso prima del rinnovo?» | «Non vuoi più ricevere l'avviso prima del rinnovo?» (l'articolo ora sta nel nome del promemoria) |
| Alta | Nuova struttura, con Portfolio pieno | «Hai già 2 strutture, il massimo del tuo piano» + bottone «Scopri Portfolio» a chi ha già Portfolio | Bottone «Cambia il numero di strutture» |
| Alta | Traduzioni dei luoghi | «Tradotta automaticamente», nella stessa area che dice «nessuna traduzione automatica» | «già tradotta in ogni lingua» |
| Alta | Aspetto | «Logo: compresa nel piano Plus» · «Non è compresa nel tuo piano: toglila» detto del logo | «Logo: disponibile con il piano Plus» · «Non fa parte del tuo piano: per pubblicare, premi «Togli»» |
| Alta | Piano Portfolio sulla landing | Elenco aperto da «Tutto di Essential, e in più:» e subito «Tutte le funzioni di Plus» | «Tutto di Plus, per ogni struttura, e in più:» |
| Media | Regole della casa | Aiuto «Sì, no, o lascia «non indicato»», ma le scelte erano «Ammesso / Non ammesso» («Animali: Ammesso») | Le scelte sono le frasi che l'ospite leggerà: «Animali ammessi / Animali non ammessi / Non indicato» |
| Media | Sezioni aggiuntive | «Wi-Fi e Regole della casa sono le più lette» (non c'è un dato che lo dica) | «di solito si comincia da Wi-Fi e Regole della casa» |
| Media | Contatore delle sezioni | «2 sezioni su 4 utilizzate», ma contano solo quelle attive | «2 su 4 sezioni attive» |
| Media | Blocchi di piano | «Ci sono 1 immagini dentro le sezioni» | «C'è 1 foto nelle sezioni: il piano non la comprende» |
| Media | Come arrivare | Il campo «Come arrivare» dentro la sezione «Come arrivare» | «Indicazioni per mezzo» |
| Media | Eventi | Campi «Minuti», «Come», «Prezzo», «Quanto» | «Distanza (min)», «Mezzo», «Ingresso», «Quanto costa» |
| Media | Servizi | «Le tue dotazioni» nel pannello, nello stesso elenco delle altre nella guida | «Altre dotazioni» |
| Media | Rifiuti | «Giorni in cui si porta fuori» | «Giorni di raccolta» |
| Media | Check-in | «dove si parcheggia la valigia» · «Note finali» | «a che piano si sale» · «Saluto finale» (è il testo che l'ospite legge quando parte) |
| Media | Luoghi | «A piedi (minuti)» con l'aiuto «in linea d'aria × 1,3 a passo tranquillo» | «Minuti a piedi» — «Dalla struttura. È una stima che puoi correggere…» |
| Media | Impostazioni | «Nel commiato della guida compare solo quello che compili» | «Nella pagina di saluto, che l'ospite apre da «Stiamo per partire»…» |
| Media | Dopo il pagamento | «Stripe ci sta confermando il pagamento con una notifica firmata» | «Stiamo aspettando la conferma del pagamento da Stripe» |
| Media | Scelta del piano | Titolo «Scegli la soluzione ideale per te.» | «Scegli il piano.», come sulla landing |
| Media | Installazione | «toglili prima di aprire al pubblico (Amministrazione → Clienti)»: il bottone è nel Quadro | «(Amministrazione → Quadro)» |
| Media | Diagnostica | «Hai caricato un nuovo borgo.jpg…»: la landing oggi usa le tre scene | Elenco aggiornato delle foto |
| Bassa | Account | «Vecchie fatture» · «Per cambiare la password usa Password dimenticata» · virgolette dritte | «Fatture precedenti» · «chiedi un link di recupero» · «…» |
| Bassa | Accesso | Titolo «Accedi», bottone «Entra» | «Accedi» |
| Bassa | Amministrazione → Clienti | «+1 altre» | «+1 altra» |

## 5. Le sezioni: spiegazione e riga di presentazione

Prima solo 5 sezioni su 17 avevano una spiegazione, e nel catalogo «Aggiungi una sezione» c'era solo il nome: «Servizi» e «Servizi extra», «Cosa fare» e «Cosa consigliamo di visitare», «Informazioni utili» e «Sezione libera» non si distinguevano. Ora ogni sezione ha i due testi, scritti allo stesso modo: prima a cosa serve all'ospite, poi come compilarla o cosa va invece in un'altra sezione.

| Sezione | Nel catalogo «Aggiungi una sezione» | Spiegazione sotto il titolo |
|---|---|---|
| **Check-in & Check-out** | — (sempre inclusa) | Come si entra, cosa serve all'arrivo e cosa fare prima di partire: è la sezione che l'ospite apre per prima, ed è sempre inclusa. Non scrivere qui i codici di porte o cassette delle chiavi: mandali all'ospite in privato. |
| **Wi-Fi** | Nome della rete e password, da copiare con un tocco. | Il nome della rete e la password: l'ospite la copia con un tocco, senza chiedertela. Se hai più reti, aggiungi una riga per ognuna e indica la zona che copre. |
| **Servizi** | Le dotazioni e le istruzioni per usarle. | Quello che gli ospiti trovano nella struttura e come si usa: spunta le dotazioni e aggiungi le istruzioni per caldaia, lavatrice, piano cottura. I servizi che offri a parte, di solito a pagamento, vanno in «Servizi extra». |
| **Servizi extra** | Transfer, colazione, late check-out: i servizi a richiesta. | I servizi che offri in più, di solito a pagamento: transfer, colazione, late check-out. Nella guida ogni servizio ha il pulsante «Richiedi su WhatsApp»: perché compaia, in Impostazioni serve un contatto che risponde su WhatsApp. |
| **Regole della casa** | Fumo, animali, feste, orario del silenzio. | Le regole della struttura, dette una volta e con chiarezza: evitano equivoci durante il soggiorno. Scegli le principali tra quelle pronte, che nella guida si traducono da sole, e aggiungi le tue. |
| **Come arrivare** | Indirizzo, mappa e indicazioni per ogni mezzo. | Il viaggio fino alla porta: da dove arriva l'ospite (autostrada, stazione, aeroporto) e come raggiunge la struttura. Gli spostamenti durante il soggiorno vanno in «Muoversi in zona». |
| **Muoversi in zona** | Autobus, taxi, noleggi e navette durante il soggiorno. | Come ci si sposta durante il soggiorno: autobus, taxi, noleggi, navette, scale mobili. Le indicazioni per raggiungere la struttura vanno in «Come arrivare». |
| **Parcheggio** | Dove lasciare l'auto, quanto costa, la ZTL. | Dove lasciare l'auto: una riga per ogni possibilità, con il costo e i minuti a piedi dalla struttura. Se c'è una ZTL, scrivi orari e varchi: eviti le multe ai tuoi ospiti. |
| **Rifiuti e raccolta differenziata** | Giorni di raccolta, colore dei bidoni, dove si trovano. | Come funziona la raccolta differenziata da te: cosa va dove, in quali giorni e in quale bidone. Chi arriva da fuori non conosce le regole del tuo Comune: qui le trova già pronte. |
| **Dove mangiare e bere** | Ristoranti, bar e gelaterie che consigli. | I posti dove mandi i tuoi ospiti a mangiare e a bere. Per ognuno bastano il link di Google Maps e due righe sul perché ti piace: sono i consigli che una ricerca online non dà. |
| **Cosa consigliamo di visitare** | Monumenti, borghi, musei e panorami. | I luoghi da vedere: monumenti, borghi, musei, panorami. Per ognuno bastano il link di Google Maps e due righe sul perché vale la visita. Le attività (escursioni, degustazioni, terme) vanno in «Cosa fare». |
| **Cosa fare** | Escursioni, degustazioni, terme, attività. | Le esperienze da vivere in zona: escursioni, degustazioni, terme, attività per i bambini. Per ognuna bastano il link di Google Maps e due righe sul perché la consigli. I luoghi da visitare vanno in «Cosa consigliamo di visitare». |
| **Negozi e spesa** | Alimentari, forno, mercato, farmacia, bancomat. | Dove fare la spesa e trovare quello che serve ogni giorno: alimentari, forno, mercato, farmacia, bancomat. Con un'etichetta segnali chi è aperto la domenica o fino a tardi. |
| **Emergenze e contatti** | I numeri utili, da chiamare con un tocco. | I numeri da avere sotto mano se qualcosa va storto: l'ospite li chiama con un tocco. Parti dal 112 e aggiungi guardia medica, farmacia e il tuo numero per le urgenze. |
| **Eventi e sagre** | Sagre, mercati e concerti, con le date. | Quello che succede in zona: sagre, mercati, concerti, con la data e la locandina. Gli eventi passati spariscono da soli dalla guida e restano qui finché non li togli; quelli che tornano ogni anno li riproponi con un clic. |
| **Sezione libera** | Titolo, icona e testo li scegli tu. | Per quello che non sta nelle altre sezioni: la piscina, il giardino, la storia del posto. Scegli il titolo e l'icona; puoi aggiungerne quante ne vuoi. |
| **Informazioni utili** | Avvisi pratici che non stanno altrove. | Le cose pratiche che è meglio sapere e che non stanno nelle altre sezioni: l'acqua del rubinetto, il giorno di mercato, il contatore che scatta. Una voce per riga, breve. |

## 6. Amministrazione

- **Piani** al posto di «Pacchetti» (menu, titolo, «Gestisci i piani», colonna «Piano»). «Price ID di Stripe» sempre con lo stesso nome (prima anche «Stripe Price ID», «Stripe Price», «Price ID aggiuntive»).
- **Stati in italiano**: negli ordini e negli abbonamenti si leggeva `paid stripe`, `active · paid`, `dimostrazione`. Ora «Pagato · Stripe», «Attivo · pagato», «di esempio» (file nuovo `views/admin/_stati.php`; un codice sconosciuto si mostra così com'è).
- **Scheda cliente**: «email confermata»; «Cliente su Stripe»; la spiegazione di «Cosa può fare» riscritta («Scrivi 1 per sì e 0 per no, un numero per i limiti, unlimited per «senza limite»»).
- **Testi dei piani**: l'elenco puntato dice che una riga che finisce con i due punti diventa un titoletto. Il campo «Sottotitolo breve» avverte che oggi non compare sulla landing: si salva, ma nessuna pagina lo mostra.
- **Impostazioni**: «Archivio di foto e PDF» (prima «delle foto», ma contiene anche i PDF); «Foto e PDF stanno sul disco del server» al posto di «I media stanno sul disco»; i messaggi in prima persona («Non ho salvato niente», «Non riesco a scrivere») sono diventati impersonali come gli altri.
- **Codici sconto**: date per esteso; «← Codici sconto» per tornare all'elenco, come «← Clienti».
- Ho lasciato **«Quadro»**: è una scelta di voce, non un'incoerenza.

## 7. Il sito pubblico

### Corretto
- «Ogni richiesta richiede poco tempo» → «Ogni risposta ti prende un minuto, ma prova a contare quante volte ripeti le stesse cose in una stagione».
- «smartphone» → «telefono», come nel resto del sito. «Invia il link via WhatsApp o email» → «Mandi il link su WhatsApp o per email, con il messaggio di benvenuto già pronto» (il messaggio pronto esiste, nella pagina QR & Link, e la landing non lo diceva).
- Fascia del codice sconto: «fino al 04/01/2027» → «fino al 4 gennaio 2027».
- Accanto a «Guarda la demo» le lingue sono **IT · EN · FR**: il francese al posto del tedesco. La demo resta disponibile anche in tedesco e spagnolo dal selettore della guida.
- Menu: «Piani» → **«Prezzi»**, la parola che un visitatore cerca. Il blocco si chiama «Piani e prezzi».

### Ampliato, e con quale idea di marketing
| Cosa | Idea | Testo |
|---|---|---|
| Blocco nuovo **«Per chi è»**, dopo «Cosa trova l'ospite» | Segmentazione: ognuno si riconosce nel suo caso | «Ogni struttura ha le sue domande.» — Casa vacanza · B&B e affittacamere · Agriturismo · Più strutture, ognuna con il suo uso tipico |
| Terzo vantaggio in «Il tempo che non vedi» | Un beneficio che la landing quasi non nominava | «Parla la lingua di chi arriva.» — fino a cinque lingue, la guida si apre in quella del telefono |
| Fascia del prezzo | Riformulare il prezzo | «Da 87 € + IVA all'anno: circa 7,25 € al mese.» (calcolato dal listino, non scritto a mano) |
| Sotto il titolo e nella chiusura | Togliere il rischio | «Nessuna app da scaricare · La crei e la provi gratis · Paghi solo quando pubblichi» · «per cominciare non serve la carta di credito» |
| «Le risposte sono già nella tua guida» | Confronto con quello che si usa oggi | «…in un'unica guida, che sostituisce il foglio plastificato e i messaggi copiati e incollati» |
| Domande: da 6 a 11 | Rispondere alle obiezioni nell'ordine in cui arrivano | Nuove: «Posso provarla prima di pagare?», «Quanto ci vuole per prepararla?», «In quali lingue la leggono gli ospiti?», «Ho più di una struttura: come funziona?», «Posso scrivere nella guida il codice della porta?» |
| Descrizione per Google e per i link condivisi | Dire il beneficio e che si prova gratis | «La guida digitale per case vacanza, B&B, affittacamere e agriturismi: check-in, Wi-Fi e consigli in un link e un QR Code. La crei gratis, paghi solo quando la pubblichi.» |
| Elenchi dei piani | Dire cosa c'è, con le parole del pannello | Plus: «5 lingue: italiano, inglese, francese, tedesco, spagnolo», «Foto e PDF nelle sezioni», «Foto profilo dell'host». Portfolio: «Sezioni da copiare da una struttura all'altra», «Un solo account e un solo abbonamento» |

Non ho inventato numeri, percentuali o testimonianze: ogni frase nuova descrive qualcosa che il prodotto fa. Il blocco delle testimonianze resta vuoto finché non ne inserisci di vere.

## 8. Le immagini

| Immagine | Esito | Cosa ho fatto |
|---|---|---|
| `scena-qr.jpg` | Adatta e chiara: il QR in cornice accanto alla porta, il telefono che lo inquadra. Il testo alternativo la descrive bene. | Niente. |
| `scena-ospite.jpg` | Adatta: sul telefono si leggono Wi-Fi, Check-in, Parcheggio, Dove mangiare, gli stessi nomi della guida. | Niente. |
| `scena-host.jpg` | **Un problema.** Sullo schermo del portatile si leggeva «La chiave si trova nella key box accanto al portone. Codice: 4821.»: l'immagine mostrava proprio quello che il prodotto dice di non fare (non scrivere codici nella guida). | Ho tolto «Codice: 4821.» dalla foto e rigenerato le due versioni WebP. Resta un limite: il pannello disegnato nella foto non è quello vero (voci «Home», «Alloggi», «Guida ospiti»). A dimensione di pagina non si legge; se rifai la foto, usa una schermata vera. |
| `pannello-1.webp`, `-2`, `-3` (blocco «Come funziona») | **Due problemi.** Erano state catturate senza i caratteri del sito: titoli in un serif di ripiego, non in Gloock. E il QR della terza si può inquadrare davvero: porta a `myhousewelcome.it/q/8GDAg8m8plZS`, un indirizzo che non ho potuto controllare. | Rifatte tutte e tre a 1200×750 con Gloock e Onest e con i testi nuovi («Dal QR», «Tema»). Il QR della terza ora porta a `myhousewelcome.it/g/casa-checco/benvenuto`, come il QR «Inquadra e prova la demo» della landing. |
| `checco-telefono.jpg` (nel telefono in testa alla pagina) | Adatta. È decorativa e ha il testo alternativo vuoto, come deve essere: il telefono intero ha la sua etichetta. | Niente. |
| `og.jpg` (anteprima quando condividi il link) | Chiara. Dice «case vacanza, B&B e agriturismi»: mancano gli affittacamere, che il sito nomina sempre. | Niente: è un'immagine con il testo dentro, va rifatta dal file di origine. |
| Testi alternativi delle schermate | «con palette, testo e copertina» | «con palette, tema e copertina» |

## 9. Cosa non ho toccato, e perché

- **I testi che legge l'ospite** nelle cinque lingue (`lang/`). Sono fuori da quello che mi hai chiesto e ogni modifica andrebbe rifatta in cinque file. Due cose da sapere: la sezione si chiama «Cosa consigliamo di visitare», più lunga delle sorelle («Cosa fare», «Dove mangiare e bere»); e il messaggio di benvenuto dice «la guida della casa».
- **«Servizi» e «Servizi extra»**: i nomi restano, perché compaiono nelle guide già pubblicate. Ora la spiegazione e la riga nel catalogo dicono la differenza (dotazioni e istruzioni / servizi a richiesta).
- **Termini e Privacy**: sono testi di partenza da far vedere a un legale, come dicono loro stessi.
- **I piani nascosti** Portfolio 2 e Portfolio 3: solo «Welcome Guide» → «guide» nella descrizione.
- **«Una prenotazione diretta in più all'anno paga l'abbonamento»**: è l'affermazione più forte della landing. Regge per quasi ogni prenotazione, ma è una promessa: decidi tu se tenerla così.

## 10. Verifiche

- Tutti i file PHP passano il controllo di sintassi.
- Le 52 prove automatiche di «Invita un amico» passano sul codice modificato, con lo Stripe finto.
- Aggiornamento provato su un database creato con la versione che mi hai mandato: la migrazione `020` si applica (20 su 20) e i testi dei piani cambiano.
- Ho riaperto con un browser 80 pagine tra pannello, amministrazione, sito e guida dell'ospite, prima e dopo, e confrontato il testo: nessun errore, nessuna pagina rotta. Ho guardato le schermate della landing (anche a larghezza di telefono), del catalogo delle sezioni e degli editor.
- **Non verificato**: l'invio vero delle email (ho controllato solo il testo), Stripe vero, il pannello a larghezza di telefono, e l'indirizzo della demo sul sito in produzione, che da qui non si raggiunge.
