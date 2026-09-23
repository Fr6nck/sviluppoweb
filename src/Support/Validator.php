<?php

declare(strict_types=1);

namespace ArcoDelVento\Support;

/**
 * Convalida lato server dei moduli.
 *
 * I messaggi non li scrive questa classe: restituisce una chiave di errore
 * per campo, e la vista la traduce. Così un errore è scritto una volta sola
 * in italiano e una in inglese, e non due volte in PHP.
 */
final class Validator
{
    /** @var array<string,string> campo => chiave di errore */
    private array $errors = [];

    /** @param array<string,mixed> $data */
    public function __construct(private readonly array $data)
    {
    }

    public function value(string $field, string $default = ''): string
    {
        $value = $this->data[$field] ?? $default;

        return is_string($value) ? trim($value) : $default;
    }

    public function required(string $field): self
    {
        if ($this->value($field) === '') {
            $this->fail($field, 'required');
        }

        return $this;
    }

    public function email(string $field): self
    {
        $value = $this->value($field);
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->fail($field, 'email');
        }

        return $this;
    }

    public function minLength(string $field, int $min): self
    {
        $value = $this->value($field);
        if ($value !== '' && mb_strlen($value) < $min) {
            $this->fail($field, 'too_short');
        }

        return $this;
    }

    public function maxLength(string $field, int $max): self
    {
        if (mb_strlen($this->value($field)) > $max) {
            $this->fail($field, 'too_long');
        }

        return $this;
    }

    /** Data ISO valida e realmente esistente (il 31 febbraio non passa). */
    public function date(string $field): self
    {
        $value = $this->value($field);
        if ($value === '') {
            return $this;
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$parsed || $parsed->format('Y-m-d') !== $value) {
            $this->fail($field, 'date');
        }

        return $this;
    }

    public function integerBetween(string $field, int $min, int $max): self
    {
        $value = $this->value($field);
        if ($value === '') {
            return $this;
        }
        if (!ctype_digit($value) || (int) $value < $min || (int) $value > $max) {
            $this->fail($field, 'range');
        }

        return $this;
    }

    public function accepted(string $field): self
    {
        if ($this->value($field) === '') {
            $this->fail($field, 'accepted');
        }

        return $this;
    }

    public function fail(string $field, string $key): self
    {
        // Il primo errore su un campo è quello che conta: sovrascriverlo con
        // il successivo sposta l'attenzione dell'utente sulla regola sbagliata.
        $this->errors[$field] ??= $key;

        return $this;
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function error(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }
}
