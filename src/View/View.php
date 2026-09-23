<?php

declare(strict_types=1);

namespace ArcoDelVento\View;

/**
 * Il motore di viste: PHP puro.
 *
 * Niente Twig e niente compilazione, perché su hosting condiviso ogni
 * dipendenza in più è una cartella da caricare via FTP e una cache da
 * ricordarsi di svuotare. Le viste sono file .php con l'escape esplicito.
 */
final class View
{
    /** @var array<string,mixed> dati condivisi da tutte le viste */
    private array $shared = [];

    public function __construct(private readonly string $path)
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /** @param array<string,mixed> $data */
    public function render(string $template, array $data = []): string
    {
        return $this->capture($this->path . '/' . $template . '.php', $data + $this->shared);
    }

    /**
     * Rende una pagina dentro il telaio comune.
     *
     * @param array<string,mixed> $data
     */
    public function page(string $page, array $data = []): string
    {
        $data['contenuto'] = $this->render('pages/' . $page, $data);

        return $this->render('layout', $data);
    }

    /** Un frammento riusabile: partials/ e components/ passano da qui. */
    public function partial(string $template, array $data = []): string
    {
        return $this->render($template, $data);
    }

    /** @param array<string,mixed> $data */
    private function capture(string $file, array $data): string
    {
        if (!is_file($file)) {
            throw new \RuntimeException(sprintf('Vista mancante: %s', $file));
        }

        // Le variabili della vista vivono solo qui dentro.
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
