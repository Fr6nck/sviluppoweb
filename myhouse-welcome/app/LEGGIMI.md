# MyHouse Welcome — applicazione funzionante

PHP 8 + SQLite (o MySQL). **Nessuna dipendenza da installare**: niente Composer,
niente Node, niente librerie esterne. Si carica via FTP e funziona.

---

## Cosa funziona davvero

| | |
|---|---|
| Registrazione, accesso, sessioni | password con `password_hash`, sessione rigenerata a ogni accesso |
| Acquisto di un piano | Stripe Checkout se configurate le chiavi, altrimenti modalità prova dichiarata |
| Webhook Stripe | firma verificata, finestra temporale, riconsegne ignorate |
| Diritti per piano | override → pacchetto comprato → predefinito |
| Versioni di pacchetto congelate | cambiare un piano **non** toglie niente a chi l'ha già comprato |
| CMS della guida | sezioni, testi, Wi-Fi, codici, luoghi, foto |
| Caricamento immagini | riconvertite in JPEG e ridimensionate; i file finti vengono rifiutati |
| Multilingua | quattro lingue; una traduzione confermata non viene mai sovrascritta |
| Pubblicazione a istantanee | gli ospiti leggono una copia congelata, non la bozza |
| QR permanente | generato in PHP; l'indirizzo non cambia mai, nemmeno rinominando la casa |
| Amministrazione | clienti, impersonazione senza password, pacchetti, eccezioni |
| Diagnostica | il server verifica sé stesso invece di promettere |

## Cosa serve configurare per andare in produzione

Tutto il resto funziona da subito. Queste tre cose richiedono chiavi vostre:

1. **Pagamenti reali** — in `app/config.php` (o come variabili d'ambiente):
   `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, `STRIPE_WEBHOOK_SECRET`.
   Senza, gli acquisti restano in modalità prova **e lo dichiarano a schermo**.
   Nel pannello Stripe puntate il webhook a `https://vostrodominio/webhook/stripe`.
2. **Traduzione automatica** — `MHW_TRANSLATE_PROVIDER` (`deepl` o `libre`) e
   `MHW_TRANSLATE_KEY`. Senza, le lingue si compilano a mano: tutto il resto funziona.
3. **HTTPS** — i cookie di sessione diventano `secure` solo sotto HTTPS.

---

## Installazione

### Disposizione consigliata (la più sicura)

Puntate il dominio sulla cartella `public/` e tenete il resto **sopra** la radice pubblica:

```
/home/vostroutente/
  myhouse/          <- src, views, migrations, config.php, storage
  public_html/      <- il contenuto di public/ (index.php, .htaccess, assets/)
```

Così niente del codice né il database è raggiungibile dal web, qualunque server usiate.

### Disposizione per hosting con solo FTP

Se non potete spostare la radice del dominio, caricate il contenuto del pacchetto
dentro `public_html`, lasciando la sottocartella `app/`:

```
public_html/
  index.php  .htaccess  web.config  assets/
  app/       <- protetta da .htaccess, e con nomi non indovinabili
```

> **Leggete questo.** In questa disposizione la protezione della cartella `app/`
> dipende da `.htaccess`, che **nginx non legge affatto** e che Apache ignora se
> `AllowOverride` è disattivato. Per questo il file del database prende un nome
> casuale, deciso all'installazione e custodito in un file `.php` che il server
> esegue invece di servirlo. È una difesa in più, non una garanzia: se potete,
> usate la disposizione consigliata.
>
> Dopo l'installazione aprite **Diagnostica** nel menu di amministrazione: il
> server prova a scaricare il proprio database e vi dice se ci riesce.

### Poi

1. Aprite il dominio: vi porta a `/installa`.
2. Scegliete email e password dell'amministratore. Il database si crea da solo,
   con le funzioni e i tre piani già dentro.
3. Date permessi di scrittura a `app/storage/` e `app/storage/uploads/` (di solito 755 o 775).

### MySQL invece di SQLite

In `config.php` mettete `'driver' => 'mysql'` e le credenziali. Lo schema è lo stesso.

---

## Indirizzi

| Indirizzo | Cosa |
|---|---|
| `/` | sito pubblico con i piani |
| `/registrati`, `/accedi` | account |
| `/pannello` | area host: guide, sezioni, lingue, QR |
| `/g/{slug}` | la guida che vedono gli ospiti |
| `/q/{token}` | l'indirizzo dietro il QR — **non cambia mai** |
| `/qr/{token}.png` | l'immagine del QR |
| `/admin` | clienti, pacchetti, diagnostica |
| `/webhook/stripe` | solo per Stripe, verificato per firma |

---

## Come è fatto

```
app/
  config.php            unica configurazione
  migrations/           lo schema (21 tabelle)
  src/                  Db, Auth, Csrf, Router, View, Entitlements,
                        Guide, Media, Qr, Stripe, Billing, Translator, Installer
  views/                le pagine
  storage/              database e immagini caricate
public/                 index.php, .htaccess, web.config, assets/
```

Il generatore di QR è scritto da zero seguendo ISO/IEC 18004 (modalità byte,
correzione M, versioni 1-10) proprio per non dipendere da librerie esterne.

## Scelte che vale la pena conoscere

- **Il webhook è l'unica fonte di verità sui pagamenti.** Il ritorno dal browser
  non attiva niente: chiunque potrebbe visitare quell'indirizzo.
- **Una versione di pacchetto venduta non si modifica.** L'amministratore che
  cambia un piano ne crea una nuova; gli abbonamenti già venduti restano sulla loro.
- **Le traduzioni confermate sono intoccabili.** Lo stato passa da `missing` a
  `machine` a `reviewed`, e da `reviewed` nessuna macchina torna indietro.
- **Gli ospiti leggono un'istantanea.** Si modifica la guida senza che nessuno
  veda mezze frasi, e ogni versione pubblicata resta conservata.
- **L'impersonazione non passa mai dalla password del cliente** e lascia traccia
  nel registro, in entrata e in uscita.
