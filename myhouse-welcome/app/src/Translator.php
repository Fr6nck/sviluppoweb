<?php
namespace MHW;

/**
 * Le traduzioni, oggi, le scrive l'host a mano. Nessuna chiamata a servizi
 * esterni: niente DeepL, niente Google, niente intelligenza artificiale.
 *
 * La classe resta come punto d'aggancio per il giorno in cui la traduzione
 * automatica diventerà una funzione di un piano (la feature `auto_translation`
 * esiste già, spenta per tutti). La regola che varrà allora vale già adesso:
 * una traduzione rivista da una persona non viene MAI sovrascritta da una
 * macchina. Lo stato passa da missing a machine a reviewed, mai all'indietro.
 */
final class Translator
{
    public static function enabled(int $accountId): bool
    {
        // Nessun fornitore è collegato: anche con la feature accesa non si
        // traduce niente finché qualcuno non lo implementa qui.
        return false;
    }

    /** Una traduzione "reviewed" è dell'host: nessun processo automatico la tocca. */
    public static function mayOverwrite(?array $current): bool
    {
        return $current === null || ($current['state'] ?? 'missing') !== 'reviewed';
    }
}
