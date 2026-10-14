<?php

declare(strict_types=1);

namespace ArcoDelVento\Support;

/**
 * Lettore minimo di file .env.
 *
 * Le variabili già presenti nell'ambiente del server vincono su quelle del
 * file: su Hostinger si possono impostare dal pannello senza toccare i file.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $values = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        if (!is_readable($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $coppia = self::parseLine($line);
            if ($coppia !== null) {
                self::$values[$coppia[0]] = $coppia[1];
            }
        }
    }

    /**
     * Una riga del file: [chiave, valore], o null se è un commento o non è
     * un'assegnazione. La usa anche il pannello Configurazione, così legge
     * il file esattamente come lo legge il sito.
     *
     * @return array{0:string,1:string}|null
     */
    public static function parseLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            return null;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Toglie gli apici e il commento in coda a un valore non quotato.
        if (strlen($value) > 1 && $value[0] === $value[-1] && in_array($value[0], ['"', "'"], true)) {
            $value = substr($value, 1, -1);
        } elseif (str_contains($value, ' #')) {
            $value = rtrim(substr($value, 0, strpos($value, ' #')));
        } elseif (str_starts_with($value, '#')) {
            // «CHIAVE=   # spiegazione»: la chiave è vuota e quello che
            // segue è un commento, non il suo valore. Un .env si scrive a
            // mano su un server, e questa è la sbavatura più facile.
            $value = '';
        }

        return [$key, $value];
    }

    /** Il valore impostato nell'ambiente del server (pannello di Hostinger), che vince sul file; null se non c'è. */
    public static function fromServer(string $key): ?string
    {
        $v = $_SERVER[$key] ?? getenv($key);

        return $v !== false && $v !== null && $v !== '' ? (string) $v : null;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $fromServer = self::fromServer($key);
        if ($fromServer !== null) {
            return $fromServer;
        }
        $value = self::$values[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
