<?php

declare(strict_types=1);

namespace ArcoDelVento\Http;

/**
 * La richiesta HTTP, ridotta a quello che serve davvero a questo sito.
 */
final class Request
{
    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $post
     * @param array<string,mixed> $server
     */
    private function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post,
        public readonly array $server,
    ) {
    }

    /**
     * La richiesta corrente. Il percorso arriva già senza la cartella del
     * sito: su https://blackout.in/assisiapartment/it/camere vale «/it/camere»,
     * come sul dominio vero.
     */
    public static function fromGlobals(): self
    {
        $uri  = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? rawurldecode($path) : '/';
        $path = \ArcoDelVento\I18n\Routes::stripBase($path);

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            '/' . trim($path, '/'),
            $_GET,
            $_POST,
            $_SERVER,
        );
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->query[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->isPost() ? $this->post : $this->query;
    }

    /**
     * Le lingue gradite dal browser, in ordine di preferenza.
     *
     * @return list<string>
     */
    public function preferredLocales(): array
    {
        $header = (string) ($this->server['HTTP_ACCEPT_LANGUAGE'] ?? '');
        if ($header === '') {
            return [];
        }

        $weighted = [];
        foreach (explode(',', $header) as $index => $chunk) {
            $parts = explode(';q=', trim($chunk));
            $tag   = strtolower(trim($parts[0]));
            if ($tag === '') {
                continue;
            }
            // A parità di q vince chi è scritto prima: l'indice fa da spareggio.
            $weighted[] = [substr($tag, 0, 2), (float) ($parts[1] ?? 1.0), $index];
        }
        usort($weighted, static fn (array $a, array $b): int => [$b[1], $a[2]] <=> [$a[1], $b[2]]);

        return array_values(array_unique(array_column($weighted, 0)));
    }
}
