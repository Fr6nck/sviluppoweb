# Fase 9 — come si esegue

1. Scompatta lo zip nella cartella principale del progetto (dove ci sono `index.php` e `app/`): i file vanno da soli in `_brief/claude-code/` e `_brief/claude-code/mockup/`.
2. Apri i mockup nel browser (Claude Code non li legge): `giudizi-sconto.html` (9A, 9B), `omaggio-admin.html` (9D, 9E) e `lingua-traduzione.html` (9F), `bianco-e-nero.png` (9J).
3. Una sessione nuova di Claude Code per ogni file, in quest'ordine, con il modello indicato:

| Ordine | File | Modello | Messaggio da scrivere a Claude Code |
|---|---|---|---|
| 1 | 9A | Opus | Leggi `_brief/claude-code/9A.md` ed eseguilo. |
| 2 | 9B | Opus | Leggi `_brief/claude-code/9B.md` ed eseguilo. |
| 3 | 9C | Sonnet | Leggi `_brief/claude-code/9C.md` ed eseguilo. |
| 4 | 9D | Opus | Leggi `_brief/claude-code/9D.md` ed eseguilo. |
| 5 | 9E | Opus | Leggi `_brief/claude-code/9E.md` ed eseguilo. |
| 6 | 9F | Opus | Leggi `_brief/claude-code/9F.md` ed eseguilo. |
| 7 | 9G | Sonnet | Leggi `_brief/claude-code/9G.md` ed eseguilo. |
| 8 | 9H | Sonnet | Leggi `_brief/claude-code/9H.md` ed eseguilo. |
| 9 | 9I | Sonnet | Leggi `_brief/claude-code/9I.md` ed eseguilo. |
| 10 | 9J | Sonnet | Leggi `_brief/claude-code/9J.md` ed eseguilo. |
| 11 | 9K | Opus | Leggi `_brief/claude-code/9K.md` ed eseguilo. |
| 12 | 9L | Opus | Leggi `_brief/claude-code/9L.md` ed eseguilo. |
| 13 | 9M | Opus | Leggi `_brief/claude-code/9M.md` ed eseguilo. |

La 9C, la 9F, la 9G e la 9H non dipendono dalle altre: si possono fare anche prima. La 9I va per ultima. Prima della fase 9 devono essere già eseguite 6L, 8H, 8I e 8J (la 9D e la 9E toccano Termini e Stripe).

4. Dopo la 9A e dopo la 9D lancia `php tools/verifica-stripe.php` con le chiavi di **test** di Stripe: tutte le righe devono dire «ok».
5. Leggi `DA-CONFIGURARE.md` alla fine: ci sono le impostazioni da fare tu in Stripe (email automatiche spente, Smart Retries accese) e i codici omaggio da creare in Amministrazione.

La foto di Casa Checco è già in `_brief/claude-code/pronti/checco/checco-copertina-nuova.jpg` (ricavata da uno schermo: se hai l'originale, sostituiscila con lo stesso nome). In `pronti/tipologie/` ci sono le foto nuove di B&B, agriturismo e property manager.
