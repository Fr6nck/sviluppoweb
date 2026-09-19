<?php
namespace MHW;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
        return $_SESSION['csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::token() . '">';
    }

    /** Ogni POST passa di qui. Un token che non combacia ferma la richiesta. */
    public static function check(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
        $sent = $_POST['_csrf'] ?? '';
        if (!is_string($sent) || !hash_equals(self::token(), $sent)) {
            http_response_code(419);
            exit('Sessione scaduta. Tornate indietro e riprovate.');
        }
    }
}
