# Fase 6 in versione leggera

1. Scompatta lo zip nella cartella principale del progetto (dove ci sono `index.php` e `app/`).
2. Una sessione nuova di Claude Code per ogni file, in ordine. Il messaggio è sempre lo stesso:
   «Leggi `_brief/claude-code/6A.md` ed eseguilo.» (poi 6B, poi 6C)
3. Dopo la 6C ricevi `welcomebook-v2-fase6.zip`. Provalo prima di caricarlo, e fai una copia di `app/storage/`.
4. `6D.md` è per le proposte facoltative: «Leggi `_brief/claude-code/6D.md` e implementa S1, X2, M2».
5. `6E.md` aggiunge i codici sconto. Non dipende dalle altre: si può fare prima o dopo.
6. `6F.md` collega il sito ad Adamo per fatture e ricevute automatiche. Serve il token di Adamo (Impostazioni → Organizzazione → Sviluppatori): mettilo nella variabile `ADAMO_TOKEN`, mai nel codice. Va fatta dopo la 6E, se usi i codici sconto.
7. `6G.md` aggiunge la sezione «Eventi». Va fatta dopo la 6A. Il mockup è in `mockup/eventi.html`: aprilo tu nel browser, Claude Code non lo legge.
8. `6H.md` aggiunge il cambio di piano per chi ha già un abbonamento. Non dipende dalle altre. Se fai anche la 6F, falla prima: la fattura del conguaglio passa da lì. Mockup in `mockup/cambio-piano.html`.
9. `6I.md` mette il nuovo listino (Essential 97, Plus 127, Portfolio 127 + 70 a struttura, max 10). Chi è già abbonato resta al suo prezzo. `6I-scaglioni-futuro.md` è per quando vorrai gli scaglioni: non usarlo ora.
10. `6J.md` rifiniture dopo la revisione del 9 ottobre (landing, pannello, amministrazione, testi legali). Togli dal file i punti che non vuoi prima di darlo a Claude Code.
11. `6K.md` aggiunge Amministrazione → «SEO e GEO», la pagina pubblica `/domande`, robots.txt, sitemap.xml e llms.txt. Prima o dopo la 6J, indifferente. Mockup in `mockup/seo-geo.html`.
12. `6L.md` sicurezza dei dati: backup cifrati, codice di verifica per l'amministrazione, scarica ed elimina account, cartoncino Wi-Fi, HTTPS. Serve la variabile `MHW_BACKUP_KEY` (mai nel codice).
13. `6M.md` guide fuori da motori e assistenti AI (cinque livelli), link non indovinabile, codici di porte, cassette, casseforti e allarmi sconsigliati (avviso e conferma a proprio rischio), avvisi nel pannello e FAQ.
14. `6N.md` correzione di un errore trovato nella 6M già eseguita: nella modifica di una variante camera con un codice già scritto, senza JavaScript il riquadro di conferma non compare mai e il salvataggio resta bloccato.

| File | Cosa fa | Modello |
|---|---|---|
| 6A | campi in linea, silenzio, dotazioni, tipologia | Sonnet |
| 6B | categorie ed etichette dei luoghi tradotte, Negozi e spesa | Opus |
| 6C | parcheggio, prezzi, muoversi in zona, home | Sonnet |
| 6D | proposte facoltative, a scelta | Sonnet |
| 6E | codici sconto per il primo anno, con Stripe | Opus |
| 6F | fatture e ricevute automatiche con Adamo, invio allo SDI | Opus |
| 6G | sezione «Eventi» con locandina, calendario e promemoria | Sonnet |
| 6H | cambio di piano: salire paga la differenza, scendere dal rinnovo | Opus |
| 6I | nuovo listino (solo prezzi) | Sonnet |
| 6J | rifiniture dopo la revisione del 9 ottobre | Sonnet |
| 6K | SEO e GEO in amministrazione, /domande, robots, sitemap, llms.txt | Sonnet |
| 6L | sicurezza dei dati: backup, accesso admin, dati dell'account | Opus |
| 6M | guide fuori dai motori, codici di accesso con conferma a proprio rischio, avvisi e FAQ | Sonnet |
| 6N | correzione: riquadro di conferma mancante senza JS nella modifica di una variante | Sonnet |
| 7A | indirizzi puliti, email con il marchio, accesso, assistenza, chi abbandona | Sonnet |
| 7B | listino (Essential 6 sezioni + foto profilo, varianti camera eliminate), cambio piano, percorso prima del pagamento, riquadro MyHouse Pro | Opus |
| 7C | foto dell'host, recensioni, sconto per chi torna, foto ingrandibili, QR, traduzioni su richiesta, novità | Opus |
| 7D | Invita un amico visibile e con anteprima, giudizi dei clienti con consenso | Sonnet |
| 7E | vetrina Casa Checco: tutte le sezioni e tutte le lingue | Sonnet |
| 7F | clienti di prova, statistiche di marketing, SEO/GEO e pagine per tipologia | Sonnet |

Fase 7 (7A, 7B, 7C già fatte): in ordine 7A → 7B → 7C → 7D → 7E → 7F, una sessione nuova per file. Prima leggi `7-IN-SOSPESO.md`: il punto 1 (`.htaccess`) va fatto subito sul server. Mockup della foto dell'host in `mockup/foto-host.html`.

In `pronti/` ci sono i file già scritti (traduzioni nelle 5 lingue e elenchi): Claude li copia, non li riscrive.
In `pronti/eventi/` ci sono la classe con la logica delle date (già provata) e le traduzioni degli eventi.
In `pronti/piano/` c'è la classe con le regole e i conti del cambio di piano (già provata).
`TASSONOMIE.md` è solo per te: è la tabella leggibile delle stesse voci, da far rileggere a un madrelingua.

## Fase 8

Ordine: **7D → 8A → 8B → 8C → 8D → 8E → 7F**. La 7E non va più eseguita: è dentro la 8D. Prima leggi `8-STATO-E-SOSPESI.md` (stato dei punti, cosa devi fare tu sul sito, decisioni prese) e approva `mockup/funnel-sito.html`.

| File | Cosa fa | Modello |
|---|---|---|
| 8A | home: tempo risparmiato, «Anche il tuo sito» a domande, scheda con le stelle, email centrate, via l'avviso legale | Sonnet |
| 8B | pannello: dove si trova la struttura (portali e social), domanda sul sito, messaggi WhatsApp con anteprima del link, finestra «passa a Plus/Portfolio», QR | Opus |
| 8C | guida: pagina delle recensioni (portali solo con 5 stelle), finestra della lingua, «Stiamo per partire», riquadri, palette, riscaldamento e aria separati, lingue per piano | Opus |
| 8D | vetrina Casa Checco completa in tutte le lingue, telefono della home con tutte le sezioni | Sonnet |
| 8E | home e domande in inglese, modificabili da «Testi e foto» | Sonnet |

In `pronti/myhouse/` i due loghi MyHouse e in `pronti/email/` il marchio per le email: Claude Code li copia.
