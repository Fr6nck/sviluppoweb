<?php

declare(strict_types=1);

namespace ArcoDelVento\Http;

/**
 * La risposta HTTP. Anche qui: solo quello che serve.
 */
final class Response
{
    /** @param array<string,string> $headers */
    private function __construct(
        private readonly string $body,
        private readonly int $status = 200,
        private readonly array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function text(string $body, string $contentType = 'text/plain; charset=UTF-8'): self
    {
        return new self($body, 200, ['Content-Type' => $contentType]);
    }

    /** Una risposta senza corpo: 204, o un altro codice. */
    public static function vuota(int $status = 204): self
    {
        return new self('', $status);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    /** La stessa risposta con un'intestazione in più (o sostituita). */
    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;

        return new self($this->body, $this->status, $headers);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }
        echo $this->body;
    }
}
