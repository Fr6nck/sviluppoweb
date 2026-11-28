<?php
/**
 * CAPTCHA senza servizi esterni, senza cookie e senza sessione.
 *
 * La sfida (cinque cifre) viaggia cifrata dentro il modulo: solo il server può leggerla.
 * L'immagine è un SVG fatto di tratti (nessun testo da copiare), con cifre deformate
 * e tratti di disturbo. Ogni codice vale 15 minuti e un solo tentativo.
 */
declare(strict_types=1);

defined('CM_ROOT') || exit;

const CAPTCHA_TTL = 900;
const CAPTCHA_DIGITS = 5;

function captcha_key(): string
{
    return hash('sha256', 'captcha|' . app_secret(), true);
}

/** Crea una nuova sfida e restituisce il token cifrato da mettere nel modulo. */
function captcha_new(): string
{
    $code = '';
    for ($i = 0; $i < CAPTCHA_DIGITS; $i++) {
        $code .= (string) random_int(0, 9);
    }
    $payload = json_encode(['c' => $code, 'e' => time() + CAPTCHA_TTL, 'n' => bin2hex(random_bytes(8))]);
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt((string) $payload, 'aes-256-gcm', captcha_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return rtrim(strtr(base64_encode($iv . $tag . $cipher), '+/', '-_'), '=');
}

/** Decifra il token. Restituisce ['c' => codice, 'e' => scadenza, 'n' => numero unico] oppure null. */
function captcha_open(string $token): ?array
{
    if ($token === '' || strlen($token) > 400 || !preg_match('/^[A-Za-z0-9_-]+$/', $token)) {
        return null;
    }
    $raw = base64_decode(strtr($token, '-_', '+/'), true);
    if ($raw === false || strlen($raw) < 30) {
        return null;
    }
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', captcha_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    $data = $plain === false ? null : json_decode($plain, true);
    if (!is_array($data) || !isset($data['c'], $data['e'], $data['n']) || (int) $data['e'] < time()) {
        return null;
    }
    return $data;
}

/**
 * Verifica la risposta. Il codice viene "bruciato" a ogni tentativo, giusto o sbagliato:
 * non si può riprovare sulla stessa immagine né riusare un codice già risolto.
 */
function captcha_check(string $token, string $answer): bool
{
    $data = captcha_open($token);
    if ($data === null) {
        return false;
    }
    try {
        q(
            'INSERT INTO ' . t('pf_tokens') . ' (token_hash, kind, subject_id, expires_at, used_at, created_at) VALUES (?, ?, 0, ?, ?, ?)',
            [hash('sha256', 'captcha|' . $data['n']), 'captcha', date('Y-m-d H:i:s', (int) $data['e']), now(), now()]
        );
    } catch (PDOException $e) {
        return false;   // già usato
    }
    $answer = preg_replace('/\D/', '', $answer) ?? '';
    return $answer !== '' && hash_equals((string) $data['c'], $answer);
}

/** Tratti delle cifre su una griglia 10 × 16. */
function captcha_glyphs(): array
{
    return [
        '0' => [[[2, 1], [8, 1], [9, 3], [9, 13], [8, 15], [2, 15], [1, 13], [1, 3], [2, 1]]],
        '1' => [[[2, 4], [5, 1], [5, 15]], [[2, 15], [8, 15]]],
        '2' => [[[1, 3], [3, 1], [7, 1], [9, 3], [9, 6], [1, 15], [9, 15]]],
        '3' => [[[1, 2], [3, 1], [7, 1], [9, 3], [9, 6], [7, 8], [4, 8]], [[7, 8], [9, 10], [9, 13], [7, 15], [3, 15], [1, 14]]],
        '4' => [[[7, 15], [7, 1], [1, 11], [9, 11]]],
        '5' => [[[9, 1], [2, 1], [1, 7], [6, 7], [9, 9], [9, 13], [7, 15], [3, 15], [1, 13]]],
        '6' => [[[8, 1], [4, 1], [1, 5], [1, 13], [3, 15], [7, 15], [9, 13], [9, 10], [7, 8], [3, 8], [1, 10]]],
        '7' => [[[1, 1], [9, 1], [4, 15]], [[3, 8], [8, 8]]],
        '8' => [[[3, 1], [7, 1], [9, 3], [9, 6], [7, 8], [3, 8], [1, 6], [1, 3], [3, 1]], [[3, 8], [1, 10], [1, 13], [3, 15], [7, 15], [9, 13], [9, 10], [7, 8]]],
        '9' => [[[9, 7], [7, 9], [3, 9], [1, 7], [1, 3], [3, 1], [7, 1], [9, 3], [9, 11], [6, 15], [2, 15]]],
    ];
}

/** Disegna la sfida. Le trasformazioni sono già applicate alle coordinate: nell'SVG restano solo tratti. */
function captcha_svg(string $code): string
{
    $w = 240;
    $h = 76;
    $glyphs = captcha_glyphs();
    $rnd = static fn (float $a, float $b): float => $a + ($b - $a) * (random_int(0, 10000) / 10000);
    $paths = [];
    $n = strlen($code);
    $slot = ($w - 28) / max(1, $n);
    for ($i = 0; $i < $n; $i++) {
        $g = $glyphs[$code[$i]] ?? [];
        $scale = $rnd(2.95, 3.5);
        $angle = deg2rad($rnd(-17, 17));
        $skew = $rnd(-0.22, 0.22);
        $cx = 14 + $slot * ($i + 0.5) + $rnd(-4, 4);
        $cy = $h / 2 + $rnd(-5, 5);
        $width = $rnd(3.1, 4.3);
        foreach ($g as $stroke) {
            $d = '';
            foreach ($stroke as $k => [$x, $y]) {
                $px = ($x - 5 + $rnd(-0.35, 0.35)) * $scale;
                $py = ($y - 8 + $rnd(-0.35, 0.35)) * $scale;
                $px += $py * $skew;
                $rx = $px * cos($angle) - $py * sin($angle) + $cx;
                $ry = $px * sin($angle) + $py * cos($angle) + $cy;
                $d .= ($k === 0 ? 'M' : 'L') . round($rx, 1) . ' ' . round($ry, 1);
            }
            $paths[] = '<path d="' . $d . '" stroke="#13294B" stroke-width="' . round($width, 1) . '"/>';
        }
    }
    // Tratti di disturbo: stessi attributi delle cifre, più alcuni in colore.
    for ($i = 0; $i < 2; $i++) {
        $paths[] = '<path d="M' . round($rnd(0, 30), 1) . ' ' . round($rnd(8, $h - 8), 1)
            . 'Q' . round($rnd(60, 180), 1) . ' ' . round($rnd(-10, $h + 10), 1) . ' ' . round($rnd($w - 30, $w), 1) . ' ' . round($rnd(8, $h - 8), 1)
            . '" stroke="#13294B" stroke-width="' . round($rnd(1.0, 1.6), 1) . '"/>';
    }
    for ($i = 0; $i < 4; $i++) {
        $x = $rnd(6, $w - 30);
        $y = $rnd(8, $h - 8);
        $paths[] = '<path d="M' . round($x, 1) . ' ' . round($y, 1) . 'l' . round($rnd(8, 26), 1) . ' ' . round($rnd(-14, 14), 1)
            . '" stroke="#F2A900" stroke-width="' . round($rnd(2.4, 4), 1) . '"/>';
    }
    shuffle($paths);
    $dots = '';
    for ($i = 0; $i < 26; $i++) {
        $dots .= '<circle cx="' . round($rnd(4, $w - 4), 1) . '" cy="' . round($rnd(4, $h - 4), 1) . '" r="' . round($rnd(0.7, 1.7), 1) . '" fill="#13294B" opacity="' . round($rnd(0.25, 0.6), 2) . '"/>';
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '">'
        . '<rect width="' . $w . '" height="' . $h . '" rx="14" fill="#FBF7EF"/>' . $dots
        . '<g fill="none" stroke-linecap="round" stroke-linejoin="round">' . implode('', $paths) . '</g></svg>';
}

/** Campo del modulo: immagine, casella per le cifre, pulsante per cambiare codice. */
function captcha_field(string $prefix, bool $error = false): string
{
    $token = captcha_new();
    return '<div class="field captcha' . ($error ? ' has-error' : '') . '" data-captcha="' . e($prefix . 'captcha.php') . '">'
        . '<label for="captcha">Verifica: scrivi le cinque cifre <span class="req" aria-hidden="true">*</span></label>'
        . '<div class="captcha-row">'
        . '<img class="captcha-img" src="' . e($prefix . 'captcha.php?t=' . $token) . '" width="240" height="76" alt="Codice di verifica: cinque cifre disegnate a mano">'
        . '<button type="button" class="btn btn-line btn-small captcha-new" hidden>' . icon('refresh', 16) . 'Cambia codice</button>'
        . '</div>'
        . '<input type="text" id="captcha" name="captcha" inputmode="numeric" pattern="[0-9 ]*" maxlength="8" autocomplete="off" required aria-describedby="captcha-hint">'
        . '<input type="hidden" name="captcha_t" value="' . e($token) . '">'
        . '<p class="hint" id="captcha-hint">Serve a tenere lontani i programmi automatici. Vale 15 minuti e un solo tentativo.</p>'
        . '</div>';
}

/**
 * Protezioni comuni dei moduli di registrazione: campo esca, tempo minimo di compilazione e limite per IP.
 * Restituisce il messaggio d'errore oppure null.
 */
function spam_guard_fields(): string
{
    return '<div class="hp" aria-hidden="true"><label>Sito web<input type="text" name="sito_web" tabindex="-1" autocomplete="off"></label></div>'
        . '<input type="hidden" name="_ft" value="' . e(form_token()) . '">';
}

function spam_guard_check(string $bucket, int $maxPerHour = 6): ?string
{
    if (post('sito_web') !== '') {
        return 'Richiesta non valida.';
    }
    $age = form_token_age(post('_ft'));
    if ($age < 0) {
        return 'Il modulo è scaduto. Ricarica la pagina e riprova.';
    }
    if ($age < 3) {
        return 'Hai compilato il modulo troppo in fretta. Riprova tra qualche secondo.';
    }
    if (pf_rate_count($bucket, 3600) >= $maxPerHour) {
        return 'Troppe richieste da questa connessione. Riprova più tardi.';
    }
    return null;
}
