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

        $definite = [];
        $content = self::capture($file, $data, $definite);
        if ($layout === null) return $content;

        // Una vista che imposta $title o $nav lo fa nel proprio scope: senza
        // questo passaggio il layout non li vedrebbe mai e ogni pagina
        // finirebbe con lo stesso titolo.
        foreach (['title', 'nav', 'theme', 'topnav', 'topright', 'loc'] as $k) {
            if (!isset($data[$k]) && isset($definite[$k])) $data[$k] = $definite[$k];
        }

        return self::capture(MHW_APP . '/views/' . $layout . '.php', $data + ['content' => $content]);
    }

    public static function out(string $tpl, array $data = [], ?string $layout = 'layout/app'): never
    {
        echo self::render($tpl, $data, $layout); exit;
    }

    /** @param array<string,mixed> $definite riceve le variabili che la vista ha creato */
    private static function capture(string $file, array $data, ?array &$definite = null): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        $html = (string) ob_get_clean();
        if ($definite !== null) {
            $definite = array_diff_key(get_defined_vars(), ['file' => 1, 'data' => 1, 'definite' => 1, 'html' => 1]);
        }
        return $html;
    }
}
