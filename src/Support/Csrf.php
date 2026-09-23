<?php

declare(strict_types=1);

namespace ArcoDelVento\Support;

/**
 * Gettone anti-CSRF per i moduli che scrivono (contatti, prenotazione).
 *
 * Un solo gettone per sessione: basta per un sito di poche pagine e non
 * rompe la navigazione con più schede aperte, che con un gettone per
 * modulo è l'errore più comune.
 */
final class Csrf
{
    private const KEY = '_adv_csrf';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::KEY];
    }

    public static function isValid(?string $token): bool
    {
        $expected = $_SESSION[self::KEY] ?? '';

        return is_string($token) && $expected !== '' && hash_equals((string) $expected, $token);
    }

    public static function field(): string
    {
        return sprintf('<input type="hidden" name="_token" value="%s">', Html::attr(self::token()));
    }
}
