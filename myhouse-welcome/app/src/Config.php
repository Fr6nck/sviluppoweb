<?php
namespace MHW;

final class Config
{
    private static ?array $data = null;

    public static function load(string $path): void { self::$data = require $path; }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$data[$key] ?? $default;
    }

    /** Un valore diverso solo per questa richiesta, senza scriverlo da nessuna parte (Impostazioni → «Prova con questi dati»). */
    public static function sovrascrivi(string $key, mixed $value): void { self::$data[$key] = $value; }

    public static function stripeReady(): bool
    {
        $s = self::get('stripe');
        return $s['secret_key'] !== '' && str_starts_with($s['secret_key'], 'sk_');
    }
}
