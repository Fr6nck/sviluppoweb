<?php
namespace MHW;

/**
 * Dal 7 ottobre 2026 le traduzioni automatiche sono le «traduzioni suggerite»
 * di Traduttore (Amazon Translate, funzione `auto_translation` di Plus e
 * Portfolio): il traduttore propone, il cliente approva. Questa classe resta
 * per la regola di sempre: una traduzione rivista da una persona non viene MAI
 * sovrascritta da una macchina.
 */
final class Translator
{
    public static function enabled(int $accountId): bool
    {
        return Traduttore::perche($accountId) === '';
    }

    /** Una traduzione "reviewed" è dell'host: nessun processo automatico la tocca. */
    public static function mayOverwrite(?array $current): bool
    {
        return $current === null || ($current['state'] ?? 'missing') !== 'reviewed';
    }
}
