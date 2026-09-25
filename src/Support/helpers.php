<?php
/**
 * Le funzioni che le viste usano di continuo.
 *
 * Sono poche di proposito: tutto ciò che una vista deve saper fare in una
 * riga — mettere in sicurezza del testo, tradurre, costruire un indirizzo,
 * marcare un dato da confermare — e niente di più.
 */

declare(strict_types=1);

use ArcoDelVento\App;
use ArcoDelVento\I18n\Routes;
use ArcoDelVento\Support\Html;

/** Testo in sicurezza dentro il markup. */
function e(?string $value): string
{
    return Html::e($value);
}

/** Attributi da un array, saltando i valori nulli. */
function attrs(array $attributes): string
{
    return Html::attributes($attributes);
}

/** Una stringa tradotta. */
function t(string $key, array $replacements = [], ?string $locale = null): string
{
    return App::instance()->translator()->get($key, $replacements, $locale);
}

/** Una stringa tradotta, già pronta per il markup. */
function te(string $key, array $replacements = [], ?string $locale = null): string
{
    return Html::e(t($key, $replacements, $locale));
}

/** Una lista tradotta (elenchi puntati, servizi, note). */
function tlist(string $key, ?string $locale = null): array
{
    return App::instance()->translator()->list($key, $locale) ?? [];
}

/** L'indirizzo di una pagina, nella lingua corrente se non se ne indica un'altra. */
function url(string $page, array $params = [], array $query = [], ?string $locale = null): string
{
    return Routes::url($page, $locale ?? App::instance()->locale(), $params, $query);
}

/**
 * Un file statico, con la marca temporale per non servire una versione vecchia.
 * Porta davanti la cartella del sito: in prova su blackout.in/assisiapartment
 * un «/assets/…» nudo chiederebbe il file alla radice di blackout.in.
 */
function asset(string $path): string
{
    $relativo = '/assets/' . ltrim($path, '/');
    $file     = App::instance()->config('root') . '/public' . $relativo;
    $pubblico = Routes::base() . $relativo;

    return is_file($file) ? $pubblico . '?v=' . filemtime($file) : $pubblico;
}

/**
 * Un indirizzo assoluto da un percorso del sito: «https://blackout.in» davanti
 * a «/assisiapartment/it/camere». Il percorso la cartella la contiene già, per
 * questo davanti va l'origine e non APP_URL intero — che la ripeterebbe.
 */
function assoluto(string $percorso): string
{
    return rtrim((string) App::instance()->config('app.origin'), '/') . $percorso;
}

/** Un indirizzo dell'area riservata, dentro la cartella del sito. */
function adminUrl(string $sotto = ''): string
{
    return Routes::base() . '/admin' . ($sotto !== '' ? '/' . ltrim($sotto, '/') : '');
}

/** «2026-09-24T12:30:00+02:00» → «24/09/2026, 12:30». */
function dataOra(string $iso): string
{
    $t = strtotime($iso);

    return $t ? date('d/m/Y, H:i', $t) : $iso;
}

/** La lingua corrente. */
function locale(): string
{
    return App::instance()->locale();
}

/** Le impostazioni del sito. */
function site(?string $key = null, mixed $default = null): mixed
{
    $settings = App::instance()->settings();
    if ($key === null) {
        return $settings;
    }
    $value = $settings;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

/**
 * Un valore che il cliente non ha ancora confermato.
 *
 * Tutto ciò che il sito non sa per certo passa da qui, così non c'è modo di
 * scambiare un contenuto di prova per un dato verificato — né leggendo la
 * pagina, né leggendo il codice. Il segno è testo vero, non un colore: chi
 * usa uno screen reader lo sente come chiunque altro lo legge.
 */
function daConfermare(?string $valore = null): string
{
    $etichetta = t('common.to_confirm');

    if ($valore === null || trim($valore) === '') {
        return sprintf('<span class="adv-dc" data-dc>%s</span>', Html::e($etichetta));
    }

    return sprintf(
        '%s <span class="adv-dc" data-dc>%s</span>',
        Html::e($valore),
        Html::e($etichetta)
    );
}

/**
 * Una frase dei contenuti, nella lingua della pagina.
 *
 * Certi dati della casa sono prosa, non numeri: com'è fatta la scala, dove si
 * parcheggia, come stanno gli animali. Quella prosa va scritta una volta per
 * lingua, altrimenti un ospite inglese si trova l'italiano in mezzo alla
 * pagina — che è peggio di un dato mancante, perché sembra un errore del sito
 * e non lo si può nemmeno leggere.
 *
 * Il valore può essere:
 *   - un array per lingua, ['it' => '…', 'en' => '…'] — la forma giusta;
 *   - una stringa, che vale per tutte le lingue: solo per quello che non si
 *     traduce, come «60 Mbps» o il nome di una piazza;
 *   - null, e allora è null anche qui: chi chiama mostrerà «da confermare».
 *
 * Se la lingua corrente manca dall'array il valore NON ricade sull'italiano:
 * restituisce null, perché una riga assente è onesta e una riga nella lingua
 * sbagliata no.
 */
function testoLocale(mixed $valore, ?string $locale = null): ?string
{
    if ($valore === null || $valore === '') {
        return null;
    }

    if (!is_array($valore)) {
        return (string) $valore;
    }

    $lingua = $locale ?? locale();
    $scelto = $valore[$lingua] ?? null;

    if ($scelto === null || $scelto === '') {
        return null;
    }

    // Certe voci sono liste per lingua (i mesi tranquilli, le date di punta):
    // una lista non si stampa come una frase, e il modo di unirla dipende da
    // dove va. Chi la usa se la prende dall'array e la compone lì, invece di
    // farsi restituire «Array» da un cast.
    return is_scalar($scelto) ? (string) $scelto : null;
}

/** Come daConfermare(), ma per un prezzo non ancora fissato. */
function prezzoDaConfermare(): string
{
    return sprintf('<span class="adv-dc" data-dc>%s</span>', Html::e(t('common.price_to_confirm')));
}

/**
 * Una cifra in euro, scritta come la scrive la lingua della pagina.
 *
 * In italiano il simbolo sta staccato e le migliaia si separano col punto:
 * «€ 1.200». In inglese sta attaccato e le migliaia con la virgola: «€1,200».
 * È un dettaglio che nessuno nota quando è giusto e stona subito quando non
 * lo è. Le cifre tabellari le porta già il design system.
 */
function euro(int|float $amount, ?string $locale = null): string
{
    $lingua = $locale ?? locale();

    if ($lingua === 'it') {
        return '€ ' . number_format((float) $amount, 0, ',', '.');
    }

    return '€' . number_format((float) $amount, 0, '.', ',');
}

/** Una data ISO scritta per esteso nella lingua corrente: «ven 12 giugno». */
function dataEstesa(string $iso, ?string $locale = null): string
{
    $locale = $locale ?? App::instance()->locale();
    $date   = DateTimeImmutable::createFromFormat('!Y-m-d', $iso);
    if (!$date) {
        return $iso;
    }

    $giorni = tlist('date.weekdays_short', $locale);
    $mesi   = tlist('date.months', $locale);

    $giorno = $giorni[(int) $date->format('w')] ?? $date->format('D');
    $mese   = $mesi[(int) $date->format('n') - 1] ?? $date->format('F');

    return sprintf('%s %d %s', $giorno, (int) $date->format('j'), $mese);
}

/**
 * Se le tariffe si possono mostrare fuori dal percorso di prenotazione.
 * Dentro il percorso si mostrano sempre: lì l'ospite ha già dato le date, e
 * il prezzo è quello vero per quelle notti e quelle persone.
 */
function prezziPubblici(): bool
{
    return (bool) site('stay.show_prices_publicly', false);
}

/**
 * La tassa di soggiorno per un soggiorno: 3 € a persona per notte, per le
 * prime tre notti. Si calcola sul caso peggiore, perché il sito non chiede
 * l'età degli ospiti e i minori di dodici anni sono esenti.
 *
 * @return array{amount: float, nights: int}|null
 */
function tassaSoggiorno(int $ospiti, int $notti): ?array
{
    $t = site('stay.city_tax');
    if (!is_array($t) || empty($t['amount'])) {
        return null;
    }
    $nottiTassate = min($notti, (int) ($t['max_nights'] ?? $notti));

    return ['amount' => (float) $t['amount'] * $ospiti * $nottiTassate, 'nights' => $nottiTassate];
}

/** Rende un componente di views/components/. */
function component(string $name, array $data = []): string
{
    return App::instance()->view()->partial('components/' . $name, $data);
}

/** Rende un frammento di views/partials/. */
function partial(string $name, array $data = []): string
{
    return App::instance()->view()->partial('partials/' . $name, $data);
}

/**
 * Un'icona a tratto, in linea.
 *
 * I tracciati sono quelli di Lucide (licenza ISC), la stessa famiglia del
 * progetto grafico: 24×24, tratto 2, estremità arrotondate. Stanno qui e non
 * in un file .svg da caricare perché sono pochi byte ciascuno e così non
 * costano una richiesta. Sono decorative: il testo accanto dice già tutto.
 */
function icona(string $nome, int $misura = 16, string $classe = ''): string
{
    static $tracciati = [
        'pin'        => ['<path d="M20 10c0 4.99-5.54 10.19-7.4 11.8a1 1 0 0 1-1.2 0C9.54 20.19 4 14.99 4 10a8 8 0 0 1 16 0"/>', '<circle cx="12" cy="10" r="3"/>'],
        'freccia-su-destra' => ['<path d="M7 7h10v10"/>', '<path d="M7 17 17 7"/>'],
        'freccia-sinistra'  => ['<path d="m12 19-7-7 7-7"/>', '<path d="M19 12H5"/>'],
        'freccia-destra'    => ['<path d="M5 12h14"/>', '<path d="m12 5 7 7-7 7"/>'],
        'freccia-giu'       => ['<path d="M12 5v14"/>', '<path d="m19 12-7 7-7-7"/>'],
        'freccia-su'        => ['<path d="m5 12 7-7 7 7"/>', '<path d="M12 19V5"/>'],
        'giu'        => ['<path d="m6 9 6 6 6-6"/>'],
        'ospiti'     => ['<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>', '<circle cx="9" cy="7" r="4"/>', '<path d="M22 21v-2a4 4 0 0 0-3-3.87"/>', '<path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
        'letto'      => ['<path d="M2 20v-8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v8"/>', '<path d="M4 10V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v4"/>', '<path d="M12 4v6"/>', '<path d="M2 18h20"/>'],
        'metratura'  => ['<path d="M15 3h6v6"/>', '<path d="m21 3-7 7"/>', '<path d="m3 21 7-7"/>', '<path d="M9 21H3v-6"/>'],
        'bagno'      => ['<path d="M9 6 6.5 3.5a1.5 1.5 0 0 0-1-.5C4.68 3 4 3.68 4 4.5V17a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/>', '<path d="M10 5 8 7"/>', '<path d="M2 12h20"/>', '<path d="M7 19v2"/>', '<path d="M17 19v2"/>'],
        'pausa'      => ['<path d="M14 4h4v16h-4z"/>', '<path d="M6 4h4v16H6z"/>'],
        'avvia'      => ['<path d="M6 3l14 9-14 9z"/>'],
        'chiave'     => ['<path d="M2.59 17.41A2 2 0 0 0 2 18.83V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.17a2 2 0 0 0 1.42-.59l.81-.81a6.5 6.5 0 1 0-4-4z"/>', '<circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/>'],
        'parcheggio' => ['<rect width="18" height="18" x="3" y="3" rx="2"/>', '<path d="M9 17V7h4a3 3 0 0 1 0 6H9"/>'],
        'casa'       => ['<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/>', '<path d="M3 10a2 2 0 0 1 .71-1.53l7-6a2 2 0 0 1 2.58 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>'],
        'spunta'     => ['<path d="M20 6 9 17l-5-5"/>'],
        'menu'       => ['<path d="M4 7h16"/>', '<path d="M4 12h16"/>', '<path d="M4 17h16"/>'],
        'chiudi'     => ['<path d="M18 6 6 18"/>', '<path d="m6 6 12 12"/>'],
        'telefono'   => ['<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>'],
        'posta'      => ['<rect width="20" height="16" x="2" y="4" rx="2"/>', '<path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>'],
        'auto'       => ['<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/>', '<circle cx="7" cy="17" r="2"/>', '<path d="M9 17h6"/>', '<circle cx="17" cy="17" r="2"/>'],
        'treno'      => ['<path d="M8 3.1V7a4 4 0 0 0 8 0V3.1"/>', '<path d="m9 15-1-1"/>', '<path d="m15 15 1-1"/>', '<path d="M9 19c-2.8 0-5-2.2-5-5v-4a8 8 0 0 1 16 0v4c0 2.8-2.2 5-5 5Z"/>', '<path d="m8 19-2 3"/>', '<path d="m16 19 2 3"/>'],
        'aereo'      => ['<path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/>'],
        'navigatore' => ['<polygon points="3 11 22 2 13 21 11 13 3 11"/>'],
        'orologio'   => ['<circle cx="12" cy="12" r="10"/>', '<path d="M12 6v6l4 2"/>'],
        'wifi'       => ['<path d="M12 20h.01"/>', '<path d="M2 8.82a15 15 0 0 1 20 0"/>', '<path d="M5 12.86a10 10 0 0 1 14 0"/>', '<path d="M8.5 16.43a5 5 0 0 1 7 0"/>'],
        'messaggio'  => ['<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>'],
        'finestra'   => ['<rect width="18" height="18" x="3" y="3" rx="2"/>', '<path d="M3 12h18"/>', '<path d="M12 3v18"/>'],
        // Per l'area riservata.
        'griglia'    => ['<rect width="7" height="7" x="3" y="3" rx="1"/>', '<rect width="7" height="7" x="14" y="3" rx="1"/>', '<rect width="7" height="7" x="14" y="14" rx="1"/>', '<rect width="7" height="7" x="3" y="14" rx="1"/>'],
        'vassoio'    => ['<path d="M22 12h-6l-2 3h-4l-2-3H2"/>', '<path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>'],
        'edificio'   => ['<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/>', '<path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/>', '<path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/>', '<path d="M10 6h4"/>', '<path d="M10 10h4"/>', '<path d="M10 14h4"/>', '<path d="M10 18h4"/>'],
        'immagine'   => ['<rect width="18" height="18" x="3" y="3" rx="2"/>', '<circle cx="9" cy="9" r="2"/>', '<path d="m21 15-3.09-3.09a2 2 0 0 0-2.82 0L6 21"/>'],
        'testo'      => ['<path d="M4 7V4h16v3"/>', '<path d="M9 20h6"/>', '<path d="M12 4v16"/>'],
        'aiuto'      => ['<circle cx="12" cy="12" r="10"/>', '<path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>', '<path d="M12 17h.01"/>'],
        'utente'     => ['<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/>', '<circle cx="12" cy="7" r="4"/>'],
        'esci'       => ['<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>', '<path d="m16 17 5-5-5-5"/>', '<path d="M21 12H9"/>'],
        'cerca'      => ['<circle cx="11" cy="11" r="8"/>', '<path d="m21 21-4.3-4.3"/>'],
        'campanella' => ['<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/>', '<path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>'],
        'esterno'    => ['<path d="M15 3h6v6"/>', '<path d="M10 14 21 3"/>', '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>'],
        'piu'        => ['<path d="M5 12h14"/>', '<path d="M12 5v14"/>'],
        'sale'       => ['<path d="M22 7 13.5 15.5 8.5 10.5 2 17"/>', '<path d="M16 7h6v6"/>'],
        'scende'     => ['<path d="M22 17 13.5 8.5 8.5 13.5 2 7"/>', '<path d="M16 17h6v-6"/>'],
        'carica'     => ['<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>', '<path d="m17 8-5-5-5 5"/>', '<path d="M12 3v12"/>'],
        'ripristina' => ['<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>', '<path d="M3 3v5h5"/>'],
        'calendario' => ['<rect width="18" height="18" x="3" y="4" rx="2"/>', '<path d="M16 2v4"/>', '<path d="M8 2v4"/>', '<path d="M3 10h18"/>'],
        'filtro'     => ['<path d="M3 6h18"/>', '<path d="M7 12h10"/>', '<path d="M10 18h4"/>'],
        'matita'     => ['<path d="M21.17 6.81a1 1 0 0 0-3.99-3.99L3.84 16.17a2 2 0 0 0-.5.83l-1.32 4.35a.5.5 0 0 0 .62.62l4.35-1.32a2 2 0 0 0 .83-.5z"/>', '<path d="m15 5 4 4"/>'],
        'sole'       => ['<circle cx="12" cy="12" r="4"/>', '<path d="M12 2v2"/>', '<path d="M12 20v2"/>', '<path d="m4.93 4.93 1.41 1.41"/>', '<path d="m17.66 17.66 1.41 1.41"/>', '<path d="M2 12h2"/>', '<path d="M20 12h2"/>', '<path d="m6.34 17.66-1.41 1.41"/>', '<path d="m19.07 4.93-1.41 1.41"/>'],
        'luna'       => ['<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>'],
        'attenzione' => ['<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/>', '<path d="M12 9v4"/>', '<path d="M12 17h.01"/>'],
        // La rosa dei venti del marchio, ridotta a segno: quattro punte e un
        // cerchio. È la sola icona che non viene da Lucide.
        'rosa'       => ['<circle cx="12" cy="12" r="9.5" stroke-width="1"/>', '<path d="M12 2.5 13.6 10.4 21.5 12 13.6 13.6 12 21.5 10.4 13.6 2.5 12 10.4 10.4Z" stroke-width="1.4"/>'],
    ];

    $parti = $tracciati[$nome] ?? $tracciati['spunta'];

    return sprintf(
        '<svg%s width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"'
        . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
        $classe !== '' ? ' class="' . Html::e($classe) . '"' : '',
        $misura,
        $misura,
        implode('', $parti)
    );
}

/**
 * Un titolo con l'ultima parola in corsivo terracotta.
 *
 * Il testo arriva dai file di lingua e dall'area riservata, quindi si mette
 * in sicurezza per intero. Il solo segno ammesso è la barra verticale «|»,
 * che diventa un a capo: così chi scrive decide dove spezzare la riga senza
 * dover scrivere HTML.
 */
function titolo(string $testo, ?string $firma = null): string
{
    $html = implode(' <br>', array_map(
        static fn (string $riga): string => Html::e(trim($riga)),
        explode('|', $testo)
    ));

    if ($firma !== null && trim($firma) !== '') {
        $html .= ($html !== '' && !str_ends_with($html, '<br>') ? ' ' : '') . '<em>' . Html::e(trim($firma)) . '</em>';
    }

    return $html;
}

/** L'occhiello: il filetto terracotta e le parole in maiuscoletto. */
function occhiello(string $testo, string $classe = ''): string
{
    return sprintf(
        '<p class="adv-occhiello%s"><span class="adv-occhiello__filo" aria-hidden="true" data-rule></span>%s</p>',
        $classe !== '' ? ' ' . Html::e($classe) : '',
        Html::e($testo)
    );
}

/** Un testo che può andare a capo con «|», come i titoli. */
function righe(string $testo): string
{
    // Lo spazio prima dell'a capo resta quando il foglio nasconde il <br> su
    // schermo stretto: senza, le due frasi si attaccherebbero.
    return implode(' <br>', array_map(static fn (string $r): string => Html::e(trim($r)), explode('|', $testo)));
}

/** L'indirizzo di Google Maps per un luogo o per la casa. */
function mappa(string $luogo): string
{
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($luogo);
}

/**
 * Un'immagine che si può sostituire dall'area riservata: quella caricata, se
 * c'è, altrimenti l'originale del pacchetto. Vedi src/Media/Immagini.php.
 *
 * @return array{src: ?string, sostituita: bool, alt: ?string, credito: ?string, w: ?int, h: ?int, formati: list<string>}
 */
function immagine(string $posto): array
{
    return App::instance()->media()->risolvi($posto, locale());
}

/**
 * Il testo alternativo: quello scritto nell'area riservata per l'immagine
 * caricata, altrimenti quello dell'originale. Un'immagine caricata senza
 * descrizione resta senza: il testo dell'originale descriverebbe un'altra
 * fotografia.
 */
function immagineAlt(array $img, string $chiaveOriginale): string
{
    return $img['sostituita'] ? (string) ($img['alt'] ?? '') : t($chiaveOriginale);
}

/** Il credito o la didascalia, con la stessa regola del testo alternativo. */
function immagineCredito(array $img, ?string $chiaveOriginale): ?string
{
    if ($img['sostituita']) {
        return $img['credito'];
    }

    return $chiaveOriginale !== null ? t($chiaveOriginale) : null;
}

/** Se un file statico esiste davvero sotto public/assets/. */
function assetEsiste(string $percorso): bool
{
    return is_file(App::instance()->config('root') . '/public/assets/' . ltrim($percorso, '/'));
}

/**
 * Il file più leggero con cui mostrare un'immagine in piccolo nel pannello:
 * la misura ridotta della foto, il logo così com'è, la rosa a 96 px.
 *
 * @param array{src: ?string} $img quello che restituisce immagine()
 */
function anteprima(array $img, string $tipo): ?string
{
    $src = $img['src'] ?? null;
    if (!is_string($src) || $src === '') {
        return null;
    }
    if ($tipo === 'icona') {
        return $src . '-96.png';
    }
    if ($tipo === 'logo' || str_ends_with($src, '.svg')) {
        return $src;
    }
    foreach (['-sm.webp', '-sm.jpg', '.webp', '.jpg'] as $estensione) {
        if (assetEsiste($src . $estensione)) {
            return $src . $estensione;
        }
    }

    return null;
}
