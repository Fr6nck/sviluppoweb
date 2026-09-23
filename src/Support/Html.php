<?php

declare(strict_types=1);

namespace ArcoDelVento\Support;

/**
 * Le due funzioni di sicurezza che servono in ogni vista.
 *
 * Tutto ciò che arriva dall'utente o dai contenuti passa da qui prima di
 * finire nel markup: è l'unica difesa contro l'iniezione di HTML.
 */
final class Html
{
    /** Testo dentro il markup. */
    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Valore dentro un attributo. */
    public static function attr(?string $value): string
    {
        return self::e($value);
    }

    /**
     * Costruisce una stringa di attributi, saltando i valori nulli e
     * rendendo `true` un attributo booleano.
     *
     * @param array<string,string|bool|null> $attributes
     */
    public static function attributes(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            $parts[] = $value === true
                ? self::e($name)
                : sprintf('%s="%s"', self::e($name), self::attr((string) $value));
        }

        return $parts === [] ? '' : ' ' . implode(' ', $parts);
    }

    /** JSON-LD già pronto per essere stampato in uno <script>. */
    public static function jsonLd(array $data): string
    {
        // JSON_HEX_TAG chiude la strada a un </script> dentro i dati.
        return (string) json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_PRETTY_PRINT
        );
    }
}
