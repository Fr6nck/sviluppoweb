#!/bin/sh
# Prova l'aggiornamento di un'installazione vera: schiera la versione indicata
# (predefinita 8f27f4f, quella prima di questo lavoro), la installa con i
# clienti di esempio, poi ci copia sopra il codice nuovo tenendo storage/ e
# controlla che tutto funzioni, che le migrazioni siano partite da sole e che
# nessun codice porta sia sopravvissuto.
set -e
QUI=$(cd "$(dirname "$0")" && pwd)
RADICE=$(cd "$QUI/../.." && pwd)
VECCHIA=${1:-8f27f4f}
PORTA=${PORTA:-8097}
TMP=$(mktemp -d)
W="$TMP/docroot/welcomebook"
pulisci() { [ -n "$PID" ] && kill "$PID" 2>/dev/null; rm -rf "$TMP"; }
trap pulisci EXIT

schiera() { # $1 = cartella con app/
  rm -rf "$W/app/src" "$W/app/views" "$W/app/migrations" "$W/app/lang" "$W/assets"
  mkdir -p "$W/app/storage/uploads"
  cp "$1/app/public/index.php" "$W/"
  cp -r "$1/app/public/assets" "$W/assets"
  for d in src views migrations lang; do [ -d "$1/app/$d" ] && cp -r "$1/app/$d" "$W/app/"; done
  cp "$1/app/config.php" "$W/app/"
  chmod -R 777 "$W/app/storage"
}
avvia() { php -S "127.0.0.1:$PORTA" -t "$TMP/docroot" "$TMP/docroot/router.php" >> "$TMP/server.log" 2>&1 & PID=$!; sleep 1.5; }
ferma() { kill "$PID"; wait "$PID" 2>/dev/null || true; PID=; }

mkdir -p "$TMP/vecchia" "$TMP/docroot"
cat > "$TMP/docroot/router.php" <<'R'
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file(__DIR__ . $path)) return false;
if (str_starts_with($path, '/welcomebook')) { $_SERVER['SCRIPT_NAME'] = '/welcomebook/index.php'; require __DIR__ . '/welcomebook/index.php'; return true; }
return false;
R
mkdir -p "$TMP/vecchia/app"
PREFISSO=$(git -C "$RADICE" rev-parse --show-prefix)
(cd "$(git -C "$RADICE" rev-parse --show-toplevel)" && git archive "$VECCHIA:${PREFISSO}app") > "$TMP/vecchia.tar"
tar -x -C "$TMP/vecchia/app" -f "$TMP/vecchia.tar"
schiera "$TMP/vecchia"
avvia
php "$QUI/aggiornamento.php" prima "http://127.0.0.1:$PORTA/welcomebook/index.php" "$W"
ferma
# Le stesse migrazioni su una copia del database, come le vede PHP prima della 8.4 (vedi php-vecchio.php).
mkdir -p "$TMP/sim/storage"
cp -r "$RADICE/app/src" "$RADICE/app/migrations" "$RADICE/app/config.php" "$TMP/sim/"
cp "$(ls "$W"/app/storage/*.sqlite | head -1)" "$TMP/sim.sqlite"
php "$QUI/php-vecchio.php" "$TMP/sim" "$TMP/sim.sqlite"
schiera "$RADICE"
avvia
php "$QUI/aggiornamento.php" dopo "http://127.0.0.1:$PORTA/welcomebook/index.php" "$W"
