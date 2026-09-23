# Fotografie originali

Gli originali da cui `tools/build-photos.php` ricava tutti i tagli che il
sito serve. Si tengono qui, fuori dalla cartella pubblica, perché sono la
sorgente: se un giorno serve un formato nuovo, si rigenera da questi e non
si ricampiona un file già compresso.

```bash
php tools/build-photos.php
```

## Provenienza

Tutte fornite dal titolare, tutte pubblicabili. **Il nome del file è il numero
della camera**: l'accoppiamento è confermato, non dedotto.

| File | Camera | Che cosa ritrae |
| --- | --- | --- |
| `camera-02.webp` | Camera 02 — doppia, letti separabili | due letti singoli affiancati; dalla finestra il campanile e la facciata della cattedrale |
| `camera-03.webp` | Camera 03 — matrimoniale | due letti singoli affiancati; dalla finestra il campanile |
| `camera-04.webp` | Camera 04 — matrimoniale | come sopra, con l'armadio in legno chiaro |
| `camera-05.webp` | Camera 05 — singola con letto matrimoniale | letto grande, parquet, scrivania e due sedie impagliate |
| `casa-corridoio.webp` | la casa | il corridoio, con la rosa dei venti intarsiata nel pavimento |
| `vicolo-campanile.webp` `basilica-tramonto.webp` `valle-panorama.webp` | Assisi | vedute su licenza Unsplash — crediti nel piè di pagina del sito |

## Le tre che mancano

Appena arrivano in questa cartella con questi nomi, si rilancia lo script e il
sito le serve: lo manifesto le aspetta già.

| Nome atteso | Che cos'è | Dove andrà | Quanto pesa che manchi |
| --- | --- | --- | --- |
| `camera-01` | la tripla | la sua pagina e l'elenco | **molto**: è la camera più cara del listino, l'unica senza fotografia, ed è quella con la vista dichiarata su San Rufino |
| `san-rufino-finestra` | la cattedrale inquadrata dalla finestra | l'apertura della home, dentro l'arco | molto: è l'immagine che racconta il sito |
| `piazza-san-rufino` | la piazza dall'alto | la fascia larga della sezione «la posizione» | media: oggi c'è la valle |

## Regole

Non ricomprimere a mano i file di `public/assets/img/`: si rigenerano.
Non ritoccare il colore: il manuale chiede luce naturale e niente filtri, e
queste fotografie ce l'hanno già.
