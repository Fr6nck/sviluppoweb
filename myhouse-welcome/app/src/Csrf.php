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
            header('Content-Type: text/html; charset=utf-8');
            $dove = Support::url('/');
            exit('<!doctype html><meta charset="utf-8">'
               . '<meta name="viewport" content="width=device-width,initial-scale=1">'
               . '<div style="font-family:system-ui,sans-serif;max-width:560px;margin:48px auto;'
               . 'padding:24px;background:#f9edd2;border-radius:14px;line-height:1.55;color:#231b12">'
               . '<strong style="font-size:18px">La sessione si &egrave; interrotta.</strong>'
               . '<p style="margin:12px 0 0">Il modulo &egrave; stato respinto perch&eacute; il '
               . 'collegamento con il server si &egrave; perso. Succede se la pagina &egrave; rimasta '
               . 'aperta a lungo, oppure se il server non riesce a conservare le sessioni.</p>'
               . '<p style="margin:14px 0 0"><a href="' . htmlspecialchars($dove) . '" '
               . 'style="color:#b4451f;font-weight:600">Ricominciate da qui</a></p></div>');
        }
    }
}
