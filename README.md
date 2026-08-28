# Studio Nova — Sito One-Page

Un sito one-page responsive realizzato solo con **HTML, CSS e JavaScript** — senza framework e senza build. Layout e palette ispirati a designgal.studio: sfondo crema burro, blu navy per i testi, CTA giallo acceso e accenti candy rosa / pesca / lilla, con il carattere Figtree.

## Sezioni

1. **Hero** — wordmark gigante a tutta larghezza, card rosa con titolo e CTA, fiore corallo animato, immagine e striscia "Si fidano di noi"
2. **Lavori** — griglia di progetti con chip multi-tag ed effetti hover
3. **Servizi** — sezione problema → soluzione su sfondo navy, con 4 punti critici e due card servizio (gialla e rosa) con checklist
4. **Lo Studio** — presentazione con ritratto, sottotitolo e citazione di un cliente
5. **Contatti** — form con validazione (subito sopra il footer)

Più un header fisso con navigazione a pillole centrata (menu hamburger a schermo intero su mobile) e un footer navy con link e "Torna su".

## Come avviarlo in locale

Non serve installare nulla. Puoi aprire direttamente `index.html` nel browser, oppure servire la cartella:

```bash
# con Python
python3 -m http.server 8000

# oppure con Node
npx serve .
```

Poi apri http://localhost:8000

## Caratteristiche

- Completamente responsive (breakpoint a 900px e 720px)
- Animazioni allo scroll con `IntersectionObserver`
- Evidenziazione del link attivo durante lo scorrimento
- Menu mobile a schermo intero con hamburger animato
- Validazione del form lato client (campi obbligatori + formato email) con messaggio di conferma
- Illustrazioni SVG locali — funziona anche offline (solo il font Google richiede la connessione; senza, viene usato un font di sistema simile)
- Rispetta `prefers-reduced-motion`

## Struttura

```
├── index.html
├── css/style.css
├── js/main.js
└── assets/         # illustrazioni SVG locali
```

## Personalizzazione

- Colori e font sono variabili CSS all'inizio di `css/style.css` (`:root`)
- Sostituisci gli SVG in `assets/` con le tue foto/immagini (qualsiasi formato) — mantieni gli stessi nomi file o aggiorna `index.html`
- Il form è solo front-end; collega il gestore `submit` in `js/main.js` al tuo backend o a un servizio come Formspree per ricevere davvero i messaggi

---

# Anfiteatro Romano di Assisi — pagina informativa

Una seconda pagina, indipendente dal sito Studio Nova, dedicata all'anfiteatro
romano di Assisi: un edificio del I secolo d.C. smontato e reimpiegato nel
Medioevo, la cui pianta ellittica sopravvive nella forma dell'isolato di case
vicino a Porta Perlici.

Si apre direttamente da `assisi-anfiteatro-romano.html` e non condivide fogli di
stile né script con `index.html` — palette in pietra e terracotta, Cormorant
Garamond per i titoli e Figtree per il testo.

## Sezioni

1. **Hero** — titolo, sintesi e striscia di dati essenziali (epoca, luogo, pianta, ingresso)
2. **Storia** — timeline in quattro tappe, dalla costruzione al reimpiego
3. **Cosa resta** — schema SVG della pianta con l'anello di case, più tre note di lettura
4. **Visita** — tre schede su dove, quando e come guardarlo
5. **Dintorni** — quattro luoghi da unire alla passeggiata

## File

```
├── assisi-anfiteatro-romano.html
├── css/assisi.css
├── js/assisi.js
└── assets/anfiteatro-hero.svg
```

## Note

- Nessuna dipendenza esterna oltre al font di Google; senza connessione si usa lo stack di fallback
- Lo schema della pianta è illustrativo, non un rilievo archeologico
- I contenuti dell'animazione allo scroll restano leggibili anche senza JavaScript
- Orari e accessi ai siti archeologici vanno verificati sui canali ufficiali del Comune di Assisi
