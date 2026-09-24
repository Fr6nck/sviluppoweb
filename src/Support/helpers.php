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
