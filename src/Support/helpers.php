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

/** Un file statico, con la marca temporale per non servire una versione vecchia. */
function asset(string $path): string
{
    $path = '/assets/' . ltrim($path, '/');
    $file = App::instance()->config('root') . '/public' . $path;

    return is_file($file) ? $path . '?v=' . filemtime($file) : $path;
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

/** Come daConfermare(), ma per un prezzo non ancora fissato. */
function prezzoDaConfermare(): string
{
    return sprintf('<span class="adv-dc" data-dc>%s</span>', Html::e(t('common.price_to_confirm')));
}

/** Una cifra in euro, con le cifre tabellari già attive dal design system. */
function euro(int|float $amount): string
{
    return '€ ' . number_format((float) $amount, 0, ',', '.');
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
