<?php
/**
 * Arco del Vento — genera public/assets/css/tokens.css da docs/tokens.json.
 *
 * tokens.json è il file esportato dal design system "Arco del Vento".
 * Quando il sistema cambia, si sostituisce docs/tokens.json e si rilancia:
 *
 *     php tools/build-tokens.php
 *
 * Non modificare tokens.css a mano: viene riscritto da qui.
 */

declare(strict_types=1);

$root   = dirname(__DIR__);
$tokens = json_decode((string) file_get_contents($root . '/docs/tokens.json'), true, 512, JSON_THROW_ON_ERROR);

/** Risolve gli alias `{altro-token}` in `var(--altro-token)`. */
$value = static function (mixed $v): string {
    $v = (string) $v;
    return preg_replace('/\{([A-Za-z0-9_.-]+)\}/', 'var(--$1)', $v);
};

$themes  = array_column($tokens['color']['themes'], 'id');   // ['avorio', 'notte']
$primary = $themes[0];

/** @return array{0: array<string,string>, 1: array<string,string>} [comuni, per-tema] */
$split = static function (array $family, string $theme, string $primary) use ($value): array {
    $common = $perTheme = [];
    foreach ($family['tokens'] as $t) {
        $v = $t['value'];
        if (is_array($v)) {
            // Un tema senza valore proprio eredita quello del tema primario.
            $perTheme[$t['name']] = $value($v[$theme] ?? $v[$primary]);
        } else {
            $common[$t['name']] = $value($v);
        }
    }
    return [$common, $perTheme];
};

$lines = [];
$lines[] = "/* Arco del Vento — token del design system.";
$lines[] = "   GENERATO da tools/build-tokens.php a partire da docs/tokens.json.";
$lines[] = "   Non modificare a mano.";
$lines[] = "";
$lines[] = "   Due temi: «avorio» (di casa, predefinito) e «notte» (lettura serale e gallerie).";
$lines[] = "   Si sceglie con data-tema=\"avorio\" | \"notte\" su <html>; senza attributo";
$lines[] = "   la pagina segue la preferenza di sistema. */";
$lines[] = "";

// ---------------------------------------------------------------- :root
$decl = [];
$decl[] = "  /* Caratteri */";
foreach ($tokens['type']['families'] as $name => $stack) {
    $decl[] = sprintf('  --font-%s: %s;', $name, $stack);
}

foreach (['color' => 'Colore', 'spacing' => 'Spazio', 'radius' => 'Raggi', 'shadow' => 'Ombre', 'bordo' => 'Bordi', 'larghezza' => 'Larghezze'] as $key => $label) {
    if (!isset($tokens[$key])) {
        continue;
    }
    [$common, $perTheme] = $split($tokens[$key], $primary, $primary);
    $decl[] = '';
    $decl[] = "  /* {$label} */";
    foreach ($common as $n => $v) {
        $decl[] = sprintf('  --%s: %s;', $n, $v);
    }
    foreach ($perTheme as $n => $v) {
        $decl[] = sprintf('  --%s: %s;', $n, $v);
    }
}

$lines[] = ':root {';
$lines[] = implode("\n", $decl);
$lines[] = '}';
$lines[] = '';

// ------------------------------------------------- temi non primari
foreach ($themes as $theme) {
    if ($theme === $primary) {
        continue;
    }
    $decl = [];
    foreach (['color', 'shadow'] as $key) {
        if (!isset($tokens[$key])) {
            continue;
        }
        [, $perTheme] = $split($tokens[$key], $theme, $primary);
        foreach ($perTheme as $n => $v) {
            $decl[] = sprintf('  --%s: %s;', $n, $v);
        }
    }
    $body = implode("\n", $decl);

    $lines[] = sprintf('/* Tema «%s» — scelto esplicitamente. */', $theme);
    $lines[] = sprintf('[data-tema="%s"] {', $theme);
    $lines[] = $body;
    $lines[] = '}';
    $lines[] = '';
    $lines[] = sprintf('/* Tema «%s» — seguendo la preferenza di sistema, se non è stato scelto «%s». */', $theme, $primary);
    $lines[] = '@media (prefers-color-scheme: dark) {';
    $lines[] = sprintf('  :root:not([data-tema="%s"]) {', $primary);
    $lines[] = preg_replace('/^  /m', '    ', $body);
    $lines[] = '  }';
    $lines[] = '}';
    $lines[] = '';
}

// Il fondo della pagina: bundle.css veste i componenti, non il documento.
$lines[] = "/* Il documento. bundle.css veste i componenti, non la pagina che li contiene. */";
$lines[] = "html { background: var(--surface); color-scheme: light dark; }";
$lines[] = "";

file_put_contents($root . '/public/assets/css/tokens.css', implode("\n", $lines));

printf("tokens.css scritto: %d byte\n", filesize($root . '/public/assets/css/tokens.css'));
