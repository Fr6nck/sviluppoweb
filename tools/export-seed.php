<?php
/**
 * Arco del Vento — genera database/seed.sql dai contenuti su file.
 *
 *     php tools/export-seed.php
 *
 * Serve a passare da content/*.php a MySQL senza ricopiare niente a mano.
 * Si lancia quando si decide di attivare il database: importa schema.sql,
 * poi seed.sql, e le camere che il sito già mostra sono nelle tabelle.
 *
 * È generato e non scritto a mano perché i due devono restare d'accordo:
 * un seed copiato una volta e poi dimenticato è peggio di nessun seed.
 */

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';

$root     = dirname(__DIR__);
$camere   = require $root . '/content/rooms.php';
$settings = require $root . '/content/settings.php';

/** Un valore SQL: le stringhe con gli apici, i null come NULL. */
$q = static function (mixed $v): string {
    if ($v === null) {
        return 'NULL';
    }
    if (is_bool($v)) {
        return $v ? '1' : '0';
    }
    if (is_int($v) || is_float($v)) {
        return (string) $v;
    }
    if (is_array($v)) {
        $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return "'" . str_replace(["\\", "'"], ["\\\\", "''"], (string) $v) . "'";
};

$out   = [];
$out[] = '-- Arco del Vento — dati iniziali.';
$out[] = '--';
$out[] = '-- GENERATO da tools/export-seed.php a partire da content/*.php.';
$out[] = '-- Non modificare a mano: si rigenera, e le modifiche andrebbero perse.';
$out[] = '--';
$out[] = '-- Si importa DOPO schema.sql. I valori sono gli stessi che il sito';
$out[] = '-- mostra oggi leggendo i file, marcatori compresi: `confirmed = 0`';
$out[] = '-- vuol dire che il dato è di prova e il sito lo segnala come tale.';
$out[] = '--';
$out[] = sprintf('-- Generato il %s.', date('Y-m-d'));
$out[] = '';
$out[] = 'SET NAMES utf8mb4;';
$out[] = 'START TRANSACTION;';
$out[] = '';

// ------------------------------------------------------- impostazioni
$out[] = '-- Impostazioni del sito. `confirmed` distingue quello che il cliente';
$out[] = '-- ha confermato da quello che il sito non deve ancora dichiarare.';
foreach (['name', 'legal_name', 'type', 'owner', 'established', 'rooms_count', 'address', 'contacts', 'stay', 'legal', 'geo', 'social'] as $chiave) {
    $valore = $settings[$chiave] ?? null;
    // Un blocco è confermato quando ha almeno un valore non nullo dentro.
    $confermato = is_array($valore)
        ? (array_filter($valore, static fn ($v): bool => $v !== null) !== [])
        : $valore !== null;
    $out[] = sprintf(
        'INSERT INTO site_settings (setting_key, value_json, confirmed) VALUES (%s, %s, %d);',
        $q($chiave),
        $q(is_array($valore) ? $valore : [$valore]),
        $confermato ? 1 : 0
    );
}
$out[] = '';

// ------------------------------------------------------------ servizi
$servizi = [];
foreach ($camere as $camera) {
    foreach ((array) $camera['amenities'] as $s) {
        $servizi[$s] = true;
    }
}
$out[] = '-- I servizi. La chiave è la stessa dei file di lingua, quindi la';
$out[] = '-- traduzione resta in content/lang/ e non entra nel database.';
$i = 0;
foreach (array_keys($servizi) as $chiave) {
    $out[] = sprintf('INSERT INTO amenities (amenity_key, position) VALUES (%s, %d);', $q($chiave), ++$i * 10);
}
$out[] = '';

// ------------------------------------------------------------- camere
$out[] = '-- Le cinque camere.';
foreach ($camere as $camera) {
    $out[] = sprintf(
        "INSERT INTO rooms (id, ref, position, published, confirmed, name_confirmed, type_key,\n"
        . "                   occupancy_standard, occupancy_max, beds_json, layouts_json,\n"
        . "                   size_sqm, floor, view_key, bathroom_json,\n"
        . "                   price_confirmed, currency, base_rate, view_san_rufino)\n"
        . "  VALUES (%d, %s, %d, 1, %d, %d, %s, %d, %d, %s, %s, %s, %s, %s, %s, %d, %s, %s, %s);",
        $camera['id'],
        $q($camera['ref']),
        $camera['position'],
        $camera['confirmed'] ? 1 : 0,
        $camera['name_confirmed'] ? 1 : 0,
        $q($camera['type']),
        $camera['occupancy']['standard'],
        $camera['occupancy']['max'],
        $q($camera['beds']),
        $q($camera['layouts']),
        $q($camera['size_sqm']),
        $q($camera['floor']),
        $q($camera['view']),
        $q($camera['bathroom']),
        $camera['price']['confirmed'] ? 1 : 0,
        $q($camera['price']['currency']),
        $q($camera['price']['demo_from']),
        $q($camera['view_san_rufino']),
    );

    foreach ($camera['name'] as $lingua => $nome) {
        $out[] = sprintf(
            'INSERT INTO room_translations (room_id, locale, name, slug) VALUES (%d, %s, %s, %s);',
            $camera['id'], $q($lingua), $q($nome), $q($camera['slug'][$lingua] ?? $camera['ref'])
        );
    }

    $pos = 0;
    foreach ((array) $camera['amenities'] as $servizio) {
        $out[] = sprintf(
            'INSERT INTO room_amenities (room_id, amenity_key, position) VALUES (%d, %s, %d);',
            $camera['id'], $q($servizio), ++$pos * 10
        );
    }

    foreach (['card' => '4/3', 'list' => '3/2', 'hero' => '16/9'] as $ruolo => $rapporto) {
        if (!isset($camera['images'][$ruolo])) {
            continue;
        }
        $out[] = sprintf(
            "INSERT INTO room_images (room_id, role, path, ratio, alt, position, credit)\n"
            . "  VALUES (%d, %s, %s, %s, NULL, 0, %s);",
            $camera['id'], $q($ruolo), $q($camera['images'][$ruolo]['src']), $q($rapporto),
            $q('segnaposto disegnato — sostituire con la fotografia vera')
        );
    }
    $pos = 0;
    foreach ((array) ($camera['images']['gallery'] ?? []) as $immagine) {
        $out[] = sprintf(
            "INSERT INTO room_images (room_id, role, path, ratio, alt, position, credit)\n"
            . "  VALUES (%d, 'gallery', %s, '1/1', NULL, %d, %s);",
            $camera['id'], $q($immagine['src']), ++$pos * 10,
            $q('segnaposto disegnato — sostituire con la fotografia vera')
        );
    }

    $out[] = sprintf(
        'INSERT INTO prices (room_id, nightly_rate, currency, min_nights, confirmed) VALUES (%d, %s, %s, 1, %d);',
        $camera['id'], $q($camera['price']['demo_from']), $q($camera['price']['currency']),
        $camera['price']['confirmed'] ? 1 : 0
    );
    $out[] = '';
}

$out[] = '-- Nessuna recensione: la sezione del sito non compare finché questa';
$out[] = '-- tabella è vuota, ed è esattamente il comportamento voluto.';
$out[] = '';
$out[] = 'COMMIT;';
$out[] = '';

file_put_contents($root . '/database/seed.sql', implode("\n", $out));

printf("seed.sql scritto: %d righe, %d camere.\n", count($out), count($camere));
