# Fase 6 in versione leggera

1. Scompatta lo zip nella cartella principale del progetto (dove ci sono `index.php` e `app/`).
2. Una sessione nuova di Claude Code per ogni file, in ordine. Il messaggio è sempre lo stesso:
   «Leggi `_brief/claude-code/6A.md` ed eseguilo.» (poi 6B, poi 6C)
3. Dopo la 6C ricevi `welcomebook-v2-fase6.zip`. Provalo prima di caricarlo, e fai una copia di `app/storage/`.
4. `6D.md` è per le proposte facoltative: «Leggi `_brief/claude-code/6D.md` e implementa S1, X2, M2».
5. `6E.md` aggiunge i codici sconto. Non dipende dalle altre: si può fare prima o dopo.
6. `6F.md` collega il sito ad Adamo per fatture e ricevute automatiche. Serve il token di Adamo (Impostazioni → Organizzazione → Sviluppatori): mettilo nella variabile `ADAMO_TOKEN`, mai nel codice. Va fatta dopo la 6E, se usi i codici sconto.
7. `6G.md` aggiunge la sezione «Eventi». Va fatta dopo la 6A. Il mockup è in `mockup/eventi.html`: aprilo tu nel browser, Claude Code non lo legge.

| File | Cosa fa | Modello |
|---|---|---|
| 6A | campi in linea, silenzio, dotazioni, tipologia | Sonnet |
| 6B | categorie ed etichette dei luoghi tradotte, Negozi e spesa | Opus |
| 6C | parcheggio, prezzi, muoversi in zona, home | Sonnet |
| 6D | proposte facoltative, a scelta | Sonnet |
| 6E | codici sconto per il primo anno, con Stripe | Opus |
| 6F | fatture e ricevute automatiche con Adamo, invio allo SDI | Opus |
| 6G | sezione «Eventi» con locandina, calendario e promemoria | Sonnet |

In `pronti/` ci sono i file già scritti (traduzioni nelle 5 lingue e elenchi): Claude li copia, non li riscrive.
In `pronti/eventi/` ci sono la classe con la logica delle date (già provata) e le traduzioni degli eventi.
`TASSONOMIE.md` è solo per te: è la tabella leggibile delle stesse voci, da far rileggere a un madrelingua.
