<?php

declare(strict_types=1);

namespace ArcoDelVento\Admin;

use ArcoDelVento\Storage\ContentOverrides;

/**
 * Il motore dei moduli dell'area riservata.
 *
 * Legge un valore dal modulo, lo controlla secondo il suo tipo (Schema) e lo
 * riduce alla forma che il sito si aspetta. Un campo vuoto diventa null — cioè
 * «da confermare» sul sito — tranne che per i sì/no.
 */
final class Form
{
    /** @param list<string> $lingue */
    public function __construct(private readonly array $lingue)
    {
    }

    /** @return list<string> */
    public function languages(): array
    {
        return $this->lingue;
    }

    /**
     * @param array<string,mixed> $campo
     * @param mixed $grezzo  quello che arriva dal modulo per questo campo
     * @param mixed $base    il valore di partenza, per conservare le lingue non modificate
     * @param list<string> $errori
     */
    public function parse(array $campo, mixed $grezzo, mixed $base, array &$errori): mixed
    {
        $etichetta = (string) $campo['label'];
        $tipo      = (string) $campo['type'];

        if ($tipo === 'bool') {
            return $grezzo === '1' || $grezzo === 1 || $grezzo === true;
        }

        if ($tipo === 'prose') {
            $testi = is_array($base) ? $base : [];
            $pieni = 0;
            foreach ($this->lingue as $lingua) {
                $t = is_array($grezzo) ? trim((string) ($grezzo[$lingua] ?? '')) : '';
                $t = preg_replace("/\r\n?/", "\n", $t) ?? $t;
                if (mb_strlen($t) > 2000) {
                    $errori[] = "{$etichetta}: al massimo 2000 caratteri.";
                }
                $testi[$lingua] = $t === '' ? null : $t;
                $pieni += $t === '' ? 0 : 1;
            }
            if ($pieni === 0) {
                if (!empty($campo['required'])) {
                    $errori[] = "{$etichetta}: serve un testo.";
                }

                return null;
            }
            if ($pieni < count($this->lingue)) {
                // Mezzo tradotto vuol dire che nella lingua mancante il sito
                // scrive [da confermare]: meglio dirlo adesso.
                $errori[] = "{$etichetta}: scrivilo in tutte le lingue del sito ("
                          . implode(', ', array_map('strtoupper', $this->lingue)) . ').';
            }

            return $testi;
        }

        $v = is_scalar($grezzo) ? trim((string) $grezzo) : '';
        if ($v === '') {
            if (!empty($campo['required'])) {
                $errori[] = "{$etichetta}: obbligatorio.";
            }

            return null;
        }
        if (isset($campo['max']) && in_array($tipo, ['text', 'tel', 'email', 'url'], true) && mb_strlen($v) > (int) $campo['max']) {
            $errori[] = "{$etichetta}: al massimo {$campo['max']} caratteri.";

            return null;
        }

        switch ($tipo) {
            case 'text':
                break;

            case 'tel':
                if (!preg_match('/^\+?[\d\s\-\/().]{6,}$/', $v)) {
                    $errori[] = "{$etichetta}: solo cifre, spazi e il «+» davanti.";

                    return null;
                }
                break;

            case 'email':
                if (filter_var($v, FILTER_VALIDATE_EMAIL) === false) {
                    $errori[] = "{$etichetta}: non è un indirizzo e-mail valido.";

                    return null;
                }
                break;

            case 'url':
                if (!preg_match('#^https?://#i', $v) || filter_var($v, FILTER_VALIDATE_URL) === false) {
                    $errori[] = "{$etichetta}: un indirizzo completo, che cominci con https://.";

                    return null;
                }
                break;

            case 'time':
                if (!preg_match('/^([01]?\d|2[0-3])[:.]([0-5]\d)$/', $v, $m)) {
                    $errori[] = "{$etichetta}: un orario come 13:00.";

                    return null;
                }
                $v = sprintf('%02d:%s', (int) $m[1], $m[2]);
                break;

            case 'int':
                if (!preg_match('/^\d+$/', $v)) {
                    $errori[] = "{$etichetta}: un numero intero.";

                    return null;
                }
                $n = (int) $v;
                if ((isset($campo['min']) && $n < $campo['min']) || (isset($campo['max']) && $n > $campo['max'])) {
                    $errori[] = "{$etichetta}: fra {$campo['min']} e {$campo['max']}.";

                    return null;
                }

                return $n;

            case 'money':
            case 'score':
            case 'coord':
                $numero = str_replace(',', '.', $v);
                if (!is_numeric($numero)) {
                    $errori[] = "{$etichetta}: un numero, per esempio " . ($tipo === 'coord' ? '43.0707' : '3,50') . '.';

                    return null;
                }
                $f = (float) $numero;
                [$min, $max] = match ($tipo) {
                    'money' => [0, (float) ($campo['max'] ?? 100000)],
                    'score' => [0, (float) ($campo['scale'] ?? 10)],
                    default => [(float) $campo['min'], (float) $campo['max']],
                };
                if ($f < $min || $f > $max) {
                    $errori[] = "{$etichetta}: fra " . self::numero($min) . ' e ' . self::numero($max) . '.';

                    return null;
                }

                return match ($tipo) {
                    'money' => round($f, 2),
                    'score' => round($f, 1),
                    default => round($f, 6),
                };

            case 'select':
                if (!array_key_exists($v, (array) $campo['options'])) {
                    $errori[] = "{$etichetta}: scelta non valida.";

                    return null;
                }
                break;
        }

        if (isset($campo['pattern']) && !preg_match((string) $campo['pattern'], $v)) {
            $errori[] = "{$etichetta}: " . ($campo['pattern_help'] ?? 'formato non valido.');

            return null;
        }

        return $v;
    }

    /**
     * Quello che va mostrato nel campo: il valore attuale, nella forma del modulo.
     *
     * @param array<string,mixed> $campo
     */
    public static function display(array $campo, mixed $valore, ?string $lingua = null): string
    {
        if ($campo['type'] === 'prose') {
            return is_array($valore) ? (string) ($valore[$lingua] ?? '') : '';
        }
        if ($valore === null || is_array($valore)) {
            return '';
        }
        if (is_float($valore) && in_array($campo['type'], ['money', 'score'], true)) {
            return self::numero($valore);
        }

        return is_bool($valore) ? ($valore ? '1' : '0') : (string) $valore;
    }

    /** 3.5 → «3,5»; 3.0 → «3». */
    public static function numero(float $n): string
    {
        $s = rtrim(rtrim(number_format($n, 6, ',', ''), '0'), ',');

        return $s === '' || $s === '-' ? '0' : $s;
    }

    /** Due valori sono lo stesso dato? (3 e 3.0 sì; null e '' sì) */
    public static function same(mixed $a, mixed $b): bool
    {
        if (is_numeric($a) && is_numeric($b) && !is_string($a) && !is_string($b)) {
            return abs((float) $a - (float) $b) < 0.000001;
        }
        if (is_array($a) && is_array($b)) {
            $a = array_filter($a, static fn ($x): bool => $x !== null && $x !== '');
            $b = array_filter($b, static fn ($x): bool => $x !== null && $x !== '');
            if (count($a) !== count($b)) {
                return false;
            }
            foreach ($a as $k => $v) {
                if (!array_key_exists($k, $b) || !self::same($v, $b[$k])) {
                    return false;
                }
            }

            return true;
        }
        if (($a === null || $a === '') && ($b === null || $b === '')) {
            return true;
        }

        return $a === $b;
    }

    public static function get(array $albero, string $percorso): mixed
    {
        return ContentOverrides::getPath($albero, $percorso);
    }
}
