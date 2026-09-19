<?php
namespace MHW;

final class Support
{
    public static function now(): string { return gmdate('Y-m-d\TH:i:s\Z'); }
    public static function today(): string { return gmdate('Y-m-d'); }

    public static function e(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function slug(string $s): string
    {
        $s = \iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        $s = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $s) ?? '');
        return trim($s, '-') ?: 'casa';
    }

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = self::slug($base); $try = $slug; $n = 1;
        while (true) {
            $row = Db::one('SELECT id FROM properties WHERE slug = ?', [$try]);
            if (!$row || ($ignoreId !== null && (int) $row['id'] === $ignoreId)) return $try;
            $try = $slug . '-' . (++$n);
        }
    }

    public static function token(int $bytes = 9): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', 'ab'), '=');
    }

    public static function redirect(string $to): never
    {
        header('Location: ' . $to); exit;
    }

    public static function json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit;
    }

    public static function baseUrl(): string
    {
        $cfg = Config::get('base_url');
        if ($cfg) return rtrim($cfg, '/');
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return ($https ? 'https://' : 'http://') . $host;
    }

    public static function money(int $cents, string $currency = 'EUR'): string
    {
        $sym = ['EUR' => '€', 'USD' => '$', 'GBP' => '£'][$currency] ?? $currency . ' ';
        return $sym . number_format($cents / 100, ($cents % 100 === 0) ? 0 : 2, ',', '.');
    }

    public static function flash(?string $msg = null, string $kind = 'ok'): ?array
    {
        if ($msg !== null) { $_SESSION['flash'] = ['msg' => $msg, 'kind' => $kind]; return null; }
        $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
    }
}
