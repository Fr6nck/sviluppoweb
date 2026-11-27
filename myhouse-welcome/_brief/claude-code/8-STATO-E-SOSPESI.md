# Fase 8 — stato dei 16 punti, cosa devi fare tu, decisioni prese, suggerimenti

Controllo fatto il 15 ottobre 2026 sullo zip «welcomebook-v2-7A-7C» installato da zero (registrazione, email, conferma, procedura, pannello Essential, amministrazione, vetrina in 5 lingue) e sul sito online dove si poteva leggere.

## I 16 punti della lista precedente

| # | Punto | Stato | Note |
|---|---|---|---|
| 1 | Processi (mail, collegamenti) | Fatto nel codice, **da sistemare online** | Da zero: registrazione → email → conferma → procedura funzionano. Online: i link hanno ancora `/index.php/` (vedi «Da fare tu» 1); foto e logo non si salvano (S3, punto 2); traduzioni ferme (AWS, punto 3). |
| 2 | Email con il marchio | Fatto | Tutte con lo stesso aspetto. La 8A le centra con il marchio come la seconda foto. |
| 3 | Recensioni e stelle, multilingua | Fatto | La 8B/8C lo rifanno come chiedi nel PDF (portali, VRBO, Expedia, social, stelle con testi nuovi). |
| 4 | Sconto per chi torna | Fatto | Spiegazione, campo sconto, anteprima. La 8B aggiunge la domanda «Hai un sito?». |
| 5 | Cambio piano dall'abbonamento | Fatto | «Passa a …» o «Chiedi il cambio di piano» (abbonamento dello staff). |
| 6 | Riquadro MyHouse Pro nero | Fatto | Diventa il percorso a domande (8A/8B, mockup da approvare). |
| 7 | Piani (6 sezioni, foto profilo, varianti, N sezioni, preventivo, traduzioni) | Fatto, **un testo sbagliato** | La voce foto/PDF online è ancora «…la foto del cancello, le istruzioni della lavatrice»: la 8A mette «Possibilità di inserire foto e PDF accanto ai testi…». |
| 8 | Demo con tutte le sezioni | **Non fatto** | La 7E non è stata eseguita: il telefono della home mostra 4 riquadri. Ora è nella 8D. La vetrina creata da zero ha tutte le 18 sezioni e le traduzioni: quella online è vecchia → «Rifai la vetrina» dopo la 8D. |
| 9 | Bottoni evidenti, piano più economico | Fatto | |
| 10 | QR da stampare | Fatto | La 8B cambia i testi come nel PDF e toglie `index.php` dall'indirizzo stampato. |
| 11 | Tempo senza Essential | Fatto | La 8A mette il tempo in grande e gli euro in grassetto piccolo. |
| 12 | Altro «Crea gratis la tua guida» | Fatto | |
| 13 | Piano prima della registrazione | Fatto | |
| 14 | Foto dell'host | Fatto | Tondo in alto a destra e foglio con la foto intera. |
| 15 | «Chiedi informazioni» su Portfolio | Fatto | |
| 16 | Porta un amico ben visibile | **Non fatto** | La 7D non è stata eseguita. In più il riquadro inviti oggi si vede solo con un abbonamento Stripe: con il tuo (attivato dallo staff) non compare. La 7D lo apre a tutti. «Come funziona» porta già alla FAQ giusta. |

## Ordine di esecuzione
**7D → 8A → 8B → 8C → 8D → 8E → 7F**, una sessione nuova di Claude Code per file. La 7E non va più eseguita (è nella 8D). Prima di lanciare 8A/8B guarda `mockup/funnel-sito.html`: se lo approvi, vai; se no, dimmi cosa cambiare.

## Da fare tu
1. **Indirizzi senza `index.php`**: robots.txt ora risponde, quindi `.htaccess` funziona. In Amministrazione → Impostazioni → Sito premi «Prova» e accendi «Indirizzi puliti». Poi rimanda un'email di prova: il link non deve avere `index.php`.
2. **Foto e logo (S3)**: Amministrazione → Impostazioni → Archivio → «Prova» e mandami il messaggio esatto. Le cause tipiche: chiave o segreto sbagliati, regione del bucket diversa da quella impostata (oggi di serie `eu-south-1`, Milano: va attivata nell'account AWS), permessi IAM mancanti (`s3:PutObject`, `s3:GetObject`, `s3:DeleteObject` sul bucket).
3. **Traduzioni (AWS Translate)**: Impostazioni → Traduzioni → «Prova», mandami il messaggio. Utente IAM con `translate:TranslateText`, regione `eu-west-1`.
4. **Vetrina**: dopo la 8D, Amministrazione → Clienti → account della vetrina → «Rifai la vetrina».
5. **Logo MyHouse**: ho ricavato due PNG dalla tua immagine (`pronti/myhouse/`). Se mi mandi l'SVG originale, sostituiscilo: sarà più nitido.
6. **6L** (backup, accesso admin in due passaggi, scarica/elimina account) resta da fare, dopo la 7F.
7. **Legale**: tolto l'avviso «Testo di partenza» come chiedi; ricordati che i testi nuovi (giudizi con consenso, email a chi non finisce, regolamento «Porta un amico») vanno comunque fatti rileggere.

## Decisioni prese (aggiornate al 15 ottobre)
- **Recensioni (tua decisione)**: il messaggio di fine soggiorno porta alla pagina `/recensione` con il voto da 1 a 5. Con 5 stelle: il tuo testo e i bottoni dei portali; da 1 a 4: solo il testo «Dicci cosa possiamo migliorare» con WhatsApp ed email all'host, **nessun link ai portali**. Rischio da sapere: Google, Booking e Airbnb vietano di chiedere recensioni solo a chi è contento; se un ospite lo segnala possono togliere recensioni o richiamare la struttura. Il rischio resta all'host, ma conviene non promuoverlo come «filtro delle recensioni».
- **Portali senza link**: l'host spunta solo dove è presente (Google, Booking.com, Airbnb, VRBO, Expedia, più altri portali con il loro indirizzo). I link li fa il sistema: Google apre la scheda Maps cercando nome e città (è l'unico portale dove si può scrivere una recensione da un link); Booking.com, Airbnb, VRBO ed Expedia aprono il sito, perché lì la recensione si lascia solo dall'app o dall'email che mandano dopo il soggiorno, e la pagina lo dice all'ospite. Gli indirizzi si cambiano da Amministrazione.
- **Lingue**: Essential = 2 lingue (la principale, di serie italiano, e una a scelta); Plus e Portfolio = la principale + le altre quattro di base + una aggiuntiva. Testi della home e dei piani aggiornati di conseguenza.
- **AWS per la lingua aggiuntiva: sì**. Amazon Translate copre tutte e 9 le lingue aggiuntive e costa a caratteri, uguale per ogni lingua: una lingua in più pesa circa un quinto in più per chi la usa, dentro i tetti mensili che ci sono già. Senza, un host italiano non potrebbe scrivere una guida in giapponese. C'è un interruttore per spegnerlo.
- **Finestra della lingua**: alla prima apertura della guida l'ospite sceglie tra le lingue attivate dall'host; la scelta resta in quel telefono.
- **Riscaldamento e aria condizionata**: due sezioni, ognuna conta per le 6 di Essential; dalla 7ª compare la finestra per passare a Plus. Per chi è già al limite, la sezione nuova nasce spenta, con un avviso: nessuno perde testi.
- **Contatti per MyHouse**: chi completa il percorso a domande finisce in Amministrazione → Prospetti → «Interessati a MyHouse», anche se poi non scrive.
- **Home in inglese**: solo la parte pubblica (home e domande). Registrazione, pannello e Termini restano in italiano per ora.

## Suggerimenti (non nei prompt: dimmi quali vuoi)
**UX/UI**
1. In «Le mie guide» una barra «La tua guida è pronta al 70%» con le 3 sezioni che mancano per essere completa: è il modo più semplice per aumentare chi pubblica.
2. Dopo la pubblicazione, una pagina «Fai sapere ai tuoi ospiti» con i tre passi: stampa il QR, manda il messaggio di benvenuto, salva il messaggio di fine soggiorno nelle risposte rapide di Booking/Airbnb.
3. Pannello in inglese per gli host stranieri (dopo la 8E, se arrivano iscrizioni da `/en`).

**Copy**
4. Nell'hero, appena hai i primi clienti, una prova sociale con numeri veri («Già usata da 25 strutture in Umbria»), mai inventata.
5. Sotto il prezzo di Essential, «meno di 2 € a settimana» rende il prezzo più leggero di «circa 8,08 € al mese».

**Immagini e video**
6. Un video di 20–30 secondi nell'hero: un ospite inquadra il QR e apre Wi-Fi e check-in. Vale più di qualsiasi foto.
7. Le schermate di «Come funziona» rifatte con una guida vera e bella (Casa Checco), uguali al prodotto di oggi.

**Marketing**
8. Per la campagna WhatsApp ed email ai 100 host: link con `?codice=` e `utm_source`, così in Prospetti vedi chi arriva da dove.
9. «Porta un amico» anche nelle email: una riga nella conferma di pagamento e nell'email del rinnovo.
10. Una scheda Google (Profilo dell'attività) per MyHouse Welcome, e chiedere ai primi clienti contenti il giudizio della 7D: credibilità e ricerca locale insieme.
11. Accordi con commercialisti, agenzie di pulizia e property manager della zona: «Porta un amico» funziona già come programma di segnalazione.
