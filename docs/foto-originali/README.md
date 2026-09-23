# Fotografie originali

Gli originali da cui `tools/build-photos.php` ricava tutti i tagli che il
sito serve. Si tengono qui, fuori dalla cartella pubblica, perché sono la
sorgente: se un giorno serve un formato nuovo, si rigenera da questi e non
si ricampiona un file già compresso.

```bash
php tools/build-photos.php
```

## Provenienza

| File | Che cosa ritrae | Da dove viene | Si pubblica |
| --- | --- | --- | --- |
| `camera-01.webp` | camera con due letti singoli affiancati, finestra sul campanile | fornita dal titolare | sì |
| `camera-02.webp` | camera con due letti singoli, finestra su campanile e facciata | fornita dal titolare | sì |
| `camera-03.webp` | camera con due letti singoli e armadio, finestra sul campanile | fornita dal titolare | sì |
| `camera-04.webp` | camera con letto matrimoniale, scrivania, parquet | fornita dal titolare | sì |
| `casa-corridoio.webp` | il corridoio, con la rosa dei venti intarsiata nel pavimento | fornita dal titolare | sì |

Le vedute di Assisi in `public/assets/img/foto/` hanno un'altra provenienza —
licenza Unsplash — e i crediti stanno nel piè di pagina del sito.

## Da confermare

**Quale fotografia è quale camera.** La numerazione qui è quella in cui le
fotografie sono arrivate, non una numerazione della casa. Serve una riga di
Daniele. Per cambiarla: si rinominano questi file e si rilancia lo script.

**La quinta camera** non ha ancora una fotografia e tiene il segnaposto
disegnato. Il sito lo dice, non lo nasconde.

## Ancora attese

Lo script ha già il posto pronto per tre file: appena arrivano in questa
cartella con questi nomi, si rilancia e il sito li serve.

| Nome atteso | Che cos'è | Dove andrà |
| --- | --- | --- |
| `san-rufino-finestra` | la cattedrale inquadrata dalla finestra della camera | l'apertura della home, dentro l'arco |
| `piazza-san-rufino` | la piazza vista dall'alto, dalla finestra della casa | la fascia larga della sezione «la posizione» |
| `camera-05` | la quinta camera | al posto del suo segnaposto |

## Regole

Non ricomprimere a mano i file di `public/assets/img/`: si rigenerano.
Non ritoccare il colore: il manuale chiede luce naturale e niente filtri, e
queste fotografie ce l'hanno già.
