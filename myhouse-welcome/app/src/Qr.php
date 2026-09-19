<?php
namespace MHW;

/**
 * Generatore di codici QR, modalità byte, livello di correzione M, versioni 1-10.
 * Scritto seguendo l'algoritmo ISO/IEC 18004: nessuna libreria esterna, così
 * funziona su qualunque hosting senza installare niente.
 */
final class Qr
{
    /** Codeword di dati per versione (livello M), indice = versione-1. */
    private const DATA_CW = [16, 28, 44, 64, 86, 108, 124, 154, 182, 216];
    /** Codeword di correzione per blocco (livello M). */
    private const EC_CW   = [10, 16, 26, 18, 24, 16, 18, 22, 22, 26];
    /** Struttura a blocchi: [[numero, dati], [numero, dati]] — il secondo gruppo può mancare. */
    private const BLOCKS  = [
        [[1, 16]], [[1, 28]], [[1, 44]], [[2, 32]], [[2, 43]],
        [[4, 27]], [[4, 31]], [[2, 38], [2, 39]], [[3, 36], [2, 37]], [[4, 43], [1, 44]],
    ];
    /** Centri dei pattern di allineamento. */
    private const ALIGN = [
        [], [6, 18], [6, 22], [6, 26], [6, 30],
        [6, 34], [6, 22, 38], [6, 24, 42], [6, 26, 46], [6, 28, 50],
    ];

    private static array $exp = [];
    private static array $log = [];

    /** Restituisce la matrice booleana del QR (true = modulo nero). */
    public static function matrix(string $text): array
    {
        self::initGf();
        $bytes = array_values(unpack('C*', $text));
        $len = count($bytes);

        $version = 0;
        for ($v = 1; $v <= 10; $v++) {
            $countBits = $v <= 9 ? 8 : 16;
            $need = 4 + $countBits + $len * 8;
            if ($need <= self::DATA_CW[$v - 1] * 8) { $version = $v; break; }
        }
        if ($version === 0) throw new \RuntimeException('Testo troppo lungo per un QR fino alla versione 10.');

        $bits = self::encodeBits($bytes, $version);
        $codewords = self::interleave($bits, $version);
        return self::buildMatrix($codewords, $version);
    }

    /** PNG pronto da servire o salvare. */
    public static function png(string $text, int $scale = 8, int $quiet = 4): string
    {
        $m = self::matrix($text);
        $n = count($m);
        $size = ($n + $quiet * 2) * $scale;
        $img = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($img, 255, 255, 255);
        $dark  = imagecolorallocate($img, 35, 27, 18);      // inchiostro caldo, non nero puro
        imagefilledrectangle($img, 0, 0, $size, $size, $white);
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($m[$y][$x]) {
                    $px = ($x + $quiet) * $scale; $py = ($y + $quiet) * $scale;
                    imagefilledrectangle($img, $px, $py, $px + $scale - 1, $py + $scale - 1, $dark);
                }
            }
        }
        ob_start(); imagepng($img); $out = (string) ob_get_clean();
        imagedestroy($img);
        return $out;
    }

    // ---------------------------------------------------------------- dati

    private static function encodeBits(array $bytes, int $version): array
    {
        $bits = [];
        $push = function (int $val, int $n) use (&$bits) {
            for ($i = $n - 1; $i >= 0; $i--) $bits[] = ($val >> $i) & 1;
        };
        $push(0b0100, 4);                                   // modalità byte
        $push(count($bytes), $version <= 9 ? 8 : 16);
        foreach ($bytes as $b) $push($b, 8);

        $capacity = self::DATA_CW[$version - 1] * 8;
        for ($i = 0; $i < 4 && count($bits) < $capacity; $i++) $bits[] = 0;   // terminatore
        while (count($bits) % 8 !== 0) $bits[] = 0;
        $pad = [0xEC, 0x11]; $k = 0;
        while (count($bits) < $capacity) { $push($pad[$k % 2], 8); $k++; }
        return $bits;
    }

    private static function interleave(array $bits, int $version): array
    {
        $data = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $b = 0; for ($j = 0; $j < 8; $j++) $b = ($b << 1) | $bits[$i + $j];
            $data[] = $b;
        }
        $ecLen = self::EC_CW[$version - 1];
        $blocks = []; $ecBlocks = []; $pos = 0;
        foreach (self::BLOCKS[$version - 1] as [$count, $size]) {
            for ($i = 0; $i < $count; $i++) {
                $chunk = array_slice($data, $pos, $size); $pos += $size;
                $blocks[] = $chunk;
                $ecBlocks[] = self::rs($chunk, $ecLen);
            }
        }
        $out = [];
        $maxData = max(array_map('count', $blocks));
        for ($i = 0; $i < $maxData; $i++)
            foreach ($blocks as $b) if (isset($b[$i])) $out[] = $b[$i];
        for ($i = 0; $i < $ecLen; $i++)
            foreach ($ecBlocks as $b) $out[] = $b[$i];
        return $out;
    }

    // ------------------------------------------------- Reed-Solomon (GF 256)

    private static function initGf(): void
    {
        if (self::$exp) return;
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) $x ^= 0x11D;                    // polinomio primitivo
        }
        for ($i = 255; $i < 512; $i++) self::$exp[$i] = self::$exp[$i - 255];
    }

    private static function rs(array $data, int $ecLen): array
    {
        // polinomio generatore
        $gen = [1];
        for ($i = 0; $i < $ecLen; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            foreach ($gen as $j => $c) {
                $next[$j] ^= $c;
                $next[$j + 1] ^= ($c === 0 ? 0 : self::$exp[(self::$log[$c] + $i) % 255]);
            }
            $gen = $next;
        }
        $rem = array_merge($data, array_fill(0, $ecLen, 0));
        for ($i = 0; $i < count($data); $i++) {
            $coef = $rem[$i];
            if ($coef === 0) continue;
            $lc = self::$log[$coef];
            foreach ($gen as $j => $g) {
                if ($g === 0) continue;
                $rem[$i + $j] ^= self::$exp[(self::$log[$g] + $lc) % 255];
            }
        }
        return array_slice($rem, count($data), $ecLen);
    }

    // -------------------------------------------------------------- matrice

    private static function buildMatrix(array $codewords, int $version): array
    {
        $n = 17 + $version * 4;
        $m = array_fill(0, $n, array_fill(0, $n, null));    // null = modulo libero

        $finder = function (int $r, int $c) use (&$m, $n) {
            for ($dr = -1; $dr <= 7; $dr++) for ($dc = -1; $dc <= 7; $dc++) {
                $rr = $r + $dr; $cc = $c + $dc;
                if ($rr < 0 || $cc < 0 || $rr >= $n || $cc >= $n) continue;
                $in = $dr >= 0 && $dr <= 6 && $dc >= 0 && $dc <= 6;
                $ring = $in && ($dr === 0 || $dr === 6 || $dc === 0 || $dc === 6);
                $core = $in && $dr >= 2 && $dr <= 4 && $dc >= 2 && $dc <= 4;
                $m[$rr][$cc] = ($ring || $core) ? 1 : 0;
            }
        };
        $finder(0, 0); $finder(0, $n - 7); $finder($n - 7, 0);

        for ($i = 8; $i < $n - 8; $i++) {                   // timing
            $m[6][$i] = ($i % 2 === 0) ? 1 : 0;
            $m[$i][6] = ($i % 2 === 0) ? 1 : 0;
        }

        $al = self::ALIGN[$version - 1];
        foreach ($al as $r) foreach ($al as $c) {
            if (($r <= 8 && $c <= 8) || ($r <= 8 && $c >= $n - 9) || ($r >= $n - 9 && $c <= 8)) continue;
            for ($dr = -2; $dr <= 2; $dr++) for ($dc = -2; $dc <= 2; $dc++) {
                $edge = abs($dr) === 2 || abs($dc) === 2;
                $m[$r + $dr][$c + $dc] = ($edge || ($dr === 0 && $dc === 0)) ? 1 : 0;
            }
        }

        $m[$n - 8][8] = 1;                                   // modulo sempre scuro

        // riserva le aree di formato e versione
        $reserved = [];
        for ($i = 0; $i < 9; $i++) { $reserved[] = [8, $i]; $reserved[] = [$i, 8]; }
        for ($i = 0; $i < 8; $i++) { $reserved[] = [8, $n - 1 - $i]; $reserved[] = [$n - 1 - $i, 8]; }
        if ($version >= 7)
            for ($i = 0; $i < 6; $i++) for ($j = 0; $j < 3; $j++) {
                $reserved[] = [$i, $n - 11 + $j]; $reserved[] = [$n - 11 + $j, $i];
            }
        foreach ($reserved as [$r, $c]) if ($m[$r][$c] === null) $m[$r][$c] = 0;

        // posa i dati a zig-zag dal basso a destra
        $bits = [];
        foreach ($codewords as $cw) for ($i = 7; $i >= 0; $i--) $bits[] = ($cw >> $i) & 1;
        $idx = 0; $up = true;
        for ($col = $n - 1; $col > 0; $col -= 2) {
            if ($col === 6) $col--;                          // salta la colonna di timing
            for ($k = 0; $k < $n; $k++) {
                $row = $up ? $n - 1 - $k : $k;
                foreach ([$col, $col - 1] as $c) {
                    if ($m[$row][$c] !== null) continue;
                    $m[$row][$c] = $idx < count($bits) ? $bits[$idx] : 0;
                    $m[$row][$c] |= 2;                       // bit 1 = "è un modulo di dati"
                    $idx++;
                }
            }
            $up = !$up;
        }

        // sceglie la maschera con la penalità piu' bassa
        $best = null; $bestScore = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $cand = self::applyMask($m, $n, $mask);
            self::writeFormat($cand, $n, $mask);
            if ($version >= 7) self::writeVersion($cand, $n, $version);
            $score = self::penalty($cand, $n);
            if ($score < $bestScore) { $bestScore = $score; $best = $cand; }
        }
        return array_map(fn($row) => array_map(fn($v) => (bool) ($v & 1), $row), $best);
    }

    private static function applyMask(array $m, int $n, int $mask): array
    {
        for ($r = 0; $r < $n; $r++) for ($c = 0; $c < $n; $c++) {
            if (!($m[$r][$c] & 2)) continue;                 // solo i moduli di dati
            $flip = match ($mask) {
                0 => ($r + $c) % 2 === 0,
                1 => $r % 2 === 0,
                2 => $c % 3 === 0,
                3 => ($r + $c) % 3 === 0,
                4 => (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0,
                5 => (($r * $c) % 2) + (($r * $c) % 3) === 0,
                6 => (((($r * $c) % 2) + (($r * $c) % 3)) % 2) === 0,
                7 => (((($r + $c) % 2) + (($r * $c) % 3)) % 2) === 0,
            };
            if ($flip) $m[$r][$c] ^= 1;
        }
        return $m;
    }

    private static function writeFormat(array &$m, int $n, int $mask): void
    {
        $data = (0b00 << 3) | $mask;                         // livello M = 00
        $rem = $data << 10;
        for ($i = 14; $i >= 10; $i--) if (($rem >> $i) & 1) $rem ^= 0b10100110111 << ($i - 10);
        $fmt = (($data << 10) | $rem) ^ 0b101010000010010;

        for ($i = 0; $i <= 5; $i++)  $m[8][$i] = ($fmt >> (14 - $i)) & 1;
        $m[8][7] = ($fmt >> 8) & 1;  $m[8][8] = ($fmt >> 7) & 1;  $m[7][8] = ($fmt >> 6) & 1;
        for ($i = 9; $i <= 14; $i++) $m[14 - $i][8] = ($fmt >> (14 - $i)) & 1;

        for ($i = 0; $i <= 7; $i++)  $m[$n - 1 - $i][8] = ($fmt >> (14 - $i)) & 1;
        for ($i = 8; $i <= 14; $i++) $m[8][$n - 15 + $i] = ($fmt >> (14 - $i)) & 1;
    }

    private static function writeVersion(array &$m, int $n, int $version): void
    {
        $rem = $version << 12;
        for ($i = 17; $i >= 12; $i--) if (($rem >> $i) & 1) $rem ^= 0b1111100100101 << ($i - 12);
        $bits = ($version << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $b = ($bits >> $i) & 1;
            $r = intdiv($i, 3); $c = $n - 11 + ($i % 3);
            $m[$r][$c] = $b; $m[$c][$r] = $b;
        }
    }

    private static function penalty(array $m, int $n): int
    {
        $bit = fn($r, $c) => $m[$r][$c] & 1;
        $score = 0;
        // 1) serie di cinque o piu' moduli uguali
        for ($r = 0; $r < $n; $r++) for ($dir = 0; $dir < 2; $dir++) {
            $run = 1; $prev = -1;
            for ($c = 0; $c < $n; $c++) {
                $v = $dir === 0 ? $bit($r, $c) : $bit($c, $r);
                if ($v === $prev) { $run++; if ($run === 5) $score += 3; elseif ($run > 5) $score++; }
                else { $prev = $v; $run = 1; }
            }
        }
        // 2) blocchi 2x2 dello stesso colore
        for ($r = 0; $r < $n - 1; $r++) for ($c = 0; $c < $n - 1; $c++) {
            $v = $bit($r, $c);
            if ($v === $bit($r, $c + 1) && $v === $bit($r + 1, $c) && $v === $bit($r + 1, $c + 1)) $score += 3;
        }
        // 3) sequenze simili al pattern di ricerca
        $pat1 = [1,0,1,1,1,0,1,0,0,0,0]; $pat2 = array_reverse($pat1);
        for ($r = 0; $r < $n; $r++) for ($c = 0; $c <= $n - 11; $c++) {
            for ($dir = 0; $dir < 2; $dir++) {
                $seq = [];
                for ($i = 0; $i < 11; $i++) $seq[] = $dir === 0 ? $bit($r, $c + $i) : $bit($c + $i, $r);
                if ($seq === $pat1 || $seq === $pat2) $score += 40;
            }
        }
        // 4) sbilanciamento fra chiaro e scuro
        $darkCount = 0;
        for ($r = 0; $r < $n; $r++) for ($c = 0; $c < $n; $c++) $darkCount += $bit($r, $c);
        $pct = $darkCount * 100 / ($n * $n);
        $score += (int) (abs($pct - 50) / 5) * 10;
        return $score;
    }
}
