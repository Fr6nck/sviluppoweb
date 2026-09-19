<?php
namespace MHW;

final class View
{
    private static array $shared = [];

    public static function share(string $k, mixed $v): void { self::$shared[$k] = $v; }

    public static function render(string $tpl, array $data = [], ?string $layout = 'layout/app'): string
    {
        $data += self::$shared;
        $file = MHW_APP . '/views/' . $tpl . '.php';
        if (!is_file($file)) throw new \RuntimeException("Vista mancante: $tpl");
        $content = self::capture($file, $data);
        if ($layout === null) return $content;
        return self::capture(MHW_APP . '/views/' . $layout . '.php', $data + ['content' => $content]);
    }

    public static function out(string $tpl, array $data = [], ?string $layout = 'layout/app'): never
    {
        echo self::render($tpl, $data, $layout); exit;
    }

    private static function capture(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start(); include $file; return (string) ob_get_clean();
    }
}
