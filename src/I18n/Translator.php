<?php

declare(strict_types=1);

namespace ArcoDelVento\I18n;

/**
 * Le stringhe dell'interfaccia, una lingua per file.
 *
 * Le chiavi sono a punti — «nav.rooms», «booking.step.dates» — e il file di
 * ogni lingua è un array annidato. Una chiave che manca nella lingua richiesta
 * ricade sulla lingua predefinita invece di lasciare un buco nella pagina:
 * un sito mezzo tradotto resta usabile, un sito con le chiavi a vista no.
 */
final class Translator
{
    /** @var array<string, array<string,mixed>> */
    private array $catalogues = [];

    public function __construct(
        private readonly string $path,
        private string $locale,
        private readonly string $fallback,
        private readonly array $available,
    ) {
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = in_array($locale, $this->available, true) ? $locale : $this->fallback;
    }

    /** @return list<string> */
    public function available(): array
    {
        return $this->available;
    }

    /**
     * @param array<string,string|int> $replacements  segnaposto :nome
     */
    public function get(string $key, array $replacements = [], ?string $locale = null): string
    {
        $locale = $locale ?? $this->locale;
        $value  = $this->lookup($key, $locale) ?? $this->lookup($key, $this->fallback);

        if (!is_string($value)) {
            // Meglio la chiave che una stringa vuota: si vede subito cosa manca.
            return $key;
        }

        foreach ($replacements as $name => $replacement) {
            $value = str_replace(':' . $name, (string) $replacement, $value);
        }

        return $value;
    }

    /** @return array<mixed>|null */
    public function list(string $key, ?string $locale = null): ?array
    {
        $value = $this->lookup($key, $locale ?? $this->locale) ?? $this->lookup($key, $this->fallback);

        return is_array($value) ? $value : null;
    }

    public function has(string $key, ?string $locale = null): bool
    {
        return $this->lookup($key, $locale ?? $this->locale) !== null;
    }

    private function lookup(string $key, string $locale): mixed
    {
        $catalogue = $this->catalogue($locale);
        $value     = $catalogue;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /** @return array<string,mixed> */
    private function catalogue(string $locale): array
    {
        if (!isset($this->catalogues[$locale])) {
            $file = $this->path . '/' . $locale . '.php';
            /** @var array<string,mixed> $loaded */
            $loaded = is_file($file) ? require $file : [];
            $this->catalogues[$locale] = $loaded;
        }

        return $this->catalogues[$locale];
    }
}
