#!/bin/sh
# Schiera l'applicazione come sull'hosting, le mette davanti un server e ci
# passa sopra il giro completo. Alla fine pulisce tutto.
set -e
QUI=$(cd "$(dirname "$0")" && pwd)
RADICE=$(cd "$QUI/../.." && pwd)
PORTA=${PORTA:-8088}
TMP=$(mktemp -d)
W="$TMP/docroot/welcomebook"

pulisci() { [ -n "$PID" ] && kill "$PID" 2>/dev/null; rm -rf "$TMP"; }
trap pulisci EXIT

mkdir -p "$W/app/storage/uploads"
cp "$RADICE/app/public/index.php" "$W/"
for f in .htaccess web.config controllo.php; do
  [ -f "$RADICE/app/public/$f" ] && cp "$RADICE/app/public/$f" "$W/"
done
cp -r "$RADICE/app/public/assets" "$W/assets"
cp -r "$RADICE/app/src" "$RADICE/app/views" "$RADICE/app/migrations" "$RADICE/app/config.php" "$W/app/"
chmod -R 777 "$W/app/storage"

# Il server di prova fa quello che farebbe Apache con mod_rewrite.
cat > "$TMP/docroot/router.php" <<'EOF'
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file(__DIR__ . $path)) return false;
if (str_starts_with($path, '/welcomebook')) {
    $_SERVER['SCRIPT_NAME'] = '/welcomebook/index.php';
    require __DIR__ . '/welcomebook/index.php';
    return true;
}
return false;
EOF

php -S "127.0.0.1:$PORTA" -t "$TMP/docroot" "$TMP/docroot/router.php" > "$TMP/server.log" 2>&1 &
PID=$!
sleep 2

php "$QUI/giro-completo.php" "http://127.0.0.1:$PORTA/welcomebook/index.php" "$W"
